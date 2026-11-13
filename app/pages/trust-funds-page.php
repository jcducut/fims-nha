<?php

namespace PHPMaker2024\FIMS;

/**
 * trust-funds-page.php — "Trust Funds": the register of every Trust Fund of fims.fund_tracking and the Official Receipts
 * (ORs) recorded against them (SRS 1.1.2.10 — Trust Fund MOAs Management; 1.1.2.10.1 — monitor budgetary requirements vs
 * downloaded funds; Fund Sourcing Division, Financial Management Department).
 *
 * Ported from the FMD app's trust-funds-page.php to FIMS, in the same shape as budget-prop-page.php and
 * saro-registry-page.php: the shared nha-fims.css look, one "Actions" dropdown per row, modals instead of separate pages.
 * What stays from the FMD page is the idea of it: two tabs —
 *   Trust Fund — the tree: each Trust Fund is a parent row that expands into the ORs under it (OR.parent_id → Trust Fund);
 *   OR         — a flat list of OR records, each naming the Trust Fund it belongs to —
 * with the filters, summary cards and Export around them. Data comes from the FIMS API only:
 *   /fund_tracking/list  /fund_tracking/read/{id}  /fund_tracking/delete/{id}     (app/routes/fund_tracking.php)
 *   /fiscal_year/all  /program/all  /agency/all
 *
 * New Entry opens a small chooser — Trust Fund or OR. Both forms are modals included below:
 *   form/trust-fund-form.php      the Trust Fund form      window.trustFundModal.open(id?)   → calls window.tfl_reload() after a save
 *   form/saro-processing-form.php the OR form (the SARO form held to the OR tracking type)  window.sarModal.open(id?)
 *                                                                                          → calls window.sr_reload() after a save
 * Edit opens whichever of the two fits the row. View Details is a modal of this page, built from /fund_tracking/read/{id}.
 * Delete (last item of a Trust Fund row's Actions menu) asks first, then calls DELETE /fund_tracking/delete/{id}; the API
 * refuses while ORs are still linked to the Trust Fund. An OR is not deleted from here — as on the FMD page.
 */
@session_start();
$_tfl_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<script src="/_shared/js/searchable-select.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/js/searchable-select.js') ?>"></script>

<style>
    #tfl-root { --tfl-line: color-mix(in srgb, var(--afims-success, #11A752) 50%, transparent); }

    /* ── Summary cards: grow with the amount instead of clipping it ── */
    #tfl-root .nha-summary { display: flex; flex-wrap: wrap; gap: 14px; }
    #tfl-root .nha-summary > .nha-sc { flex: 1 1 210px; min-width: 210px; }
    #tfl-root .nha-sc-val { white-space: nowrap; }

    /* Program: a search box like Agency / Program — it keeps room for "All programs" next to its magnifier icon (the default
       130px group minimum cut the text off), and has no × button, so the right padding the shared search style reserves for one goes */
    #tfl-root .tfl-program-group { flex: 1 1 190px; min-width: 190px; }
    #tfl-root .tfl-program-group .nha-filter-input { padding-right: 10px; }

    /* the Trust Fund / OR switch sits right above the table it controls */
    #tfl-root .nha-tabs { max-width: 360px; }

    /* ── Table ── */
    #tfl-root .tfl-tbl { min-width: 1180px; }
    #tfl-root .tfl-tbl .tfl-num { font-family: 'IBM Plex Mono', monospace; text-align: right; white-space: nowrap; }
    #tfl-root .tfl-empty { text-align: center; color: var(--afims-text-disabled, #A9AAB8); padding: 32px; font-size: 13px; }

    /* Each row sets --tfl-row-bg; its cells paint it. The Actions cell is sticky (below), so it needs the same
       opaque colour as its row — a variable keeps that in one place for every row state. */
    #tfl-root .tfl-parent, #tfl-root .tfl-or { --tfl-row-bg: var(--afims-surface, #FFFFFF); }
    #tfl-root .tfl-parent > td, #tfl-root .tfl-child > td, #tfl-root .tfl-or > td { background: var(--tfl-row-bg); }
    #tfl-root .tfl-parent.has-kids { cursor: pointer; }
    #tfl-root .tfl-parent:hover, #tfl-root .tfl-or:hover { --tfl-row-bg: var(--afims-surface-subtle, #F4F7FA); }

    /* Actions stay reachable at the right edge while the wide table scrolls sideways */
    #tfl-root .tfl-tbl thead th:last-child { position: sticky; right: 0; z-index: 3; background: var(--afims-heading-fill, #000E3E); }
    #tfl-root .tfl-parent > td:last-child, #tfl-root .tfl-child > td:last-child, #tfl-root .tfl-or > td:last-child {
        position: sticky; right: 0; z-index: 2; box-shadow: -10px 0 10px -10px rgba(15, 32, 69, .28); }

    /* ── Actions dropdown (the same menu as budget-prop-page.php / saro-registry-page.php) ──────────────────────────
       The menu is `position: fixed`, so the table's own overflow never clips it — but it sits inside the sticky Actions
       cell, which is a stacking context of its own. Every row's cell has the same z-index, so the cells of the rows BELOW
       would paint over the open menu: the cell that owns it is lifted above them while it is open. */
    #tfl-root .nha-tools-menu { position: relative; display: inline-block; }
    #tfl-root .nha-menu-trigger { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 6px;
        font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--afims-border, #E4ECF2);
        background: var(--afims-surface, #FFFFFF); color: var(--afims-text, #1A1B21); white-space: nowrap;
        font-family: 'IBM Plex Sans', sans-serif; transition: all .15s; }
    #tfl-root .nha-menu-trigger:hover, #tfl-root .nha-menu-trigger[aria-expanded="true"] { background: var(--afims-surface-subtle, #F4F7FA); border-color: var(--afims-text-disabled, #A9AAB8); }
    #tfl-root .nha-menu-trigger svg { width: 12px; height: 12px; flex-shrink: 0; }
    #tfl-root .nha-tools-dropdown { display: none; position: fixed; min-width: 170px; background: var(--afims-surface, #FFFFFF);
        border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        z-index: 9999; padding: 4px 0; text-align: left; }
    #tfl-root .nha-tools-dropdown.open { display: block; }
    #tfl-root .nha-menu-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; font-size: 12px; font-weight: 500;
        color: var(--afims-text, #1A1B21); text-decoration: none; white-space: nowrap; cursor: pointer;
        border: none; background: none; width: 100%; text-align: left; transition: background .1s; font-family: 'IBM Plex Sans', sans-serif; }
    #tfl-root .nha-menu-item:hover, #tfl-root .nha-menu-item:focus-visible { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-heading-fill, #000E3E); outline: none; }
    #tfl-root .nha-menu-item svg { width: 13px; height: 13px; flex-shrink: 0; opacity: .7; }
    #tfl-root .nha-menu-divider { border-top: 1px solid var(--afims-border, #E4ECF2); margin: 3px 0; }
    #tfl-root .nha-menu-item.danger { color: var(--afims-danger, #DC3545); }
    #tfl-root .nha-menu-item.danger:hover, #tfl-root .nha-menu-item.danger:focus-visible { background: var(--afims-danger-soft, #FAE1E3); color: var(--afims-danger, #DC3545); }
    #tfl-root .tfl-tbl tr > td.tfl-menu-open { z-index: 30; }

    /* parent: the STRONG row — bolder type; opened, a deeper blue band, a blue left rail and a stem down to its ORs */
    #tfl-root .tfl-parent > td:first-child { vertical-align: top; white-space: nowrap; }
    #tfl-root .tfl-parent .tfl-ref { font-size: 13px; font-weight: 700; }
    #tfl-root .tfl-parent.expanded { --tfl-row-bg: color-mix(in srgb, var(--afims-primary-soft, #E6EBF6) 90%, var(--afims-primary, #0035A9)); }
    #tfl-root .tfl-parent.expanded > td { border-bottom: none; }
    #tfl-root .tfl-parent.expanded:hover { --tfl-row-bg: color-mix(in srgb, var(--afims-primary-soft, #E6EBF6) 80%, var(--afims-primary, #0035A9)); }
    #tfl-root .tfl-parent.expanded > td:first-child { position: relative; box-shadow: inset 4px 0 0 var(--afims-primary, #0035A9); }
    #tfl-root .tfl-parent.expanded > td:first-child::after { content: ''; position: absolute; left: 26px; top: 34px; bottom: -1px; width: 2px; background: var(--tfl-line); }

    #tfl-root .tfl-ref-line { display: flex; align-items: center; }
    #tfl-root .tfl-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; padding-left: 28px; }
    #tfl-root .tfl-caret { display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 22px; height: 22px; margin-right: 6px;
        border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; background: var(--afims-surface, #FFFFFF); color: var(--afims-heading, #000E3E);
        transition: background .15s, color .15s, border-color .15s; }
    #tfl-root .tfl-caret svg { width: 12px; height: 12px; transition: transform .15s; }
    #tfl-root .tfl-caret.none { visibility: hidden; }
    #tfl-root .tfl-parent.has-kids:hover .tfl-caret { border-color: var(--afims-heading, #000E3E); }
    #tfl-root .tfl-parent.expanded .tfl-caret { background: var(--afims-heading-fill, #000E3E); border-color: var(--afims-primary, #0035A9); color: var(--afims-pure-white, #FFFFFF); }
    #tfl-root .tfl-parent.expanded .tfl-caret svg { transform: rotate(90deg); }

    /* children: the LIGHTER rows — smaller type, a green rail, a tree connector (│ └) from the parent's chevron, dashed
       row lines and a solid closing line after the last one */
    #tfl-root .tfl-child { --tfl-row-bg: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 14%, var(--afims-surface, #FFFFFF)); }
    #tfl-root .tfl-child > td { border-bottom: 1px dashed var(--afims-border, #E4ECF2); font-size: 12px; }
    #tfl-root .tfl-child:hover { --tfl-row-bg: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 34%, var(--afims-surface, #FFFFFF)); }
    #tfl-root .tfl-child.last > td { border-bottom: 2px solid var(--afims-border-strong, #C5CEDA); }
    #tfl-root .tfl-child > td:first-child { position: relative; white-space: nowrap; box-shadow: inset 3px 0 0 var(--afims-success, #11A752); }
    #tfl-root .tfl-child > td:first-child::before { content: ''; position: absolute; left: 26px; top: 0; bottom: -1px; width: 2px; background: var(--tfl-line); }
    #tfl-root .tfl-child.last > td:first-child::before { bottom: 50%; }
    #tfl-root .tfl-child > td:first-child::after { content: ''; position: absolute; left: 26px; top: 50%; width: 16px; height: 2px; background: var(--tfl-line); }
    #tfl-root .tfl-child-ref { padding-left: 40px; }
    #tfl-root .tfl-child-ref .tfl-tags { padding-left: 0; }
    #tfl-root .tfl-child .tfl-ref { font-size: 12px; font-weight: 600; }

    /* OR tab — a flat list, so no band, caret or connector: plain rows that keep the OR's green identity (rail + tag) */
    #tfl-root .tfl-or > td:first-child { white-space: nowrap; vertical-align: top; box-shadow: inset 3px 0 0 var(--afims-success, #11A752); }
    #tfl-root .tfl-or .tfl-tags { padding-left: 0; }
    #tfl-root .tfl-or .tfl-ref { font-size: 12.5px; font-weight: 700; }
    #tfl-root .tfl-tf-name { margin-top: 2px; font-size: 11px; color: var(--afims-text-muted, #62657E); }

    #tfl-root .tfl-tag { display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    #tfl-root .tfl-tag-type { background: var(--afims-primary-text, #0035A9); color: var(--afims-surface, #FFFFFF); letter-spacing: .04em; }
    #tfl-root .tfl-tag-count { background: var(--afims-surface, #FFFFFF); color: var(--afims-success-hover, #0C7539); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--afims-success, #11A752) 45%, transparent); }
    #tfl-root .tfl-tag-child { background: var(--afims-success-soft, #DBF2E5); color: var(--afims-success-hover, #0C7539); }
    #tfl-root .tfl-tag-none { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-disabled, #A9AAB8); }

    /* ── Modals (shared .nha-overlay / .nha-modal) ── */
    #tfl-root .tfl-hint { display: block; margin-top: 4px; font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); }

    /* New Entry chooser */
    #tfl-root .tfl-new-opt { display: flex; flex-direction: column; gap: 2px; width: 100%; margin-bottom: 10px; padding: 12px 14px; text-align: left;
        background: var(--afims-surface, #FFFFFF); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; cursor: pointer;
        color: var(--afims-heading, #000E3E); font-family: inherit; transition: border-color .15s, background .15s; }
    #tfl-root .tfl-new-opt:last-child { margin-bottom: 0; }
    #tfl-root .tfl-new-opt:hover, #tfl-root .tfl-new-opt:focus-visible { border-color: var(--afims-success, #11A752); background: var(--afims-surface-subtle, #F4F7FA); outline: none; }
    #tfl-root .tfl-new-opt-title { font-size: 13.5px; font-weight: 700; }
    #tfl-root .tfl-new-opt-desc { font-size: 12px; color: var(--afims-text-muted, #62657E); }

    /* ── View Details modal — the look of the Budget Preview modal (readone/budget-preview-readone.php): the reference in the
       title, bordered info cards of label / value rows ── */
    #tfl-root .tflv-ref { margin-left: 8px; font-family: var(--mono); font-weight: 600; letter-spacing: .02em;
        color: color-mix(in srgb, var(--afims-primary, #0035A9) 40%, #FFFFFF); }
    #tfl-root .tflv-badge { margin-left: 12px; vertical-align: 1px; }
    #tfl-root .tflv-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 20px; }
    #tfl-root .tflv-stat { padding: 12px 16px; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-left: 3px solid var(--afims-primary, #0035A9); border-radius: 12px; }
    #tfl-root .tflv-stat-l { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--sec); }
    #tfl-root .tflv-stat-v { margin-top: 5px; font: 700 17px/1.2 var(--mono); color: var(--afims-heading, #000E3E); white-space: nowrap; }
    #tfl-root .tflv-sec { margin-bottom: 20px; }
    #tfl-root .tflv-sec:last-child { margin-bottom: 0; }
    #tfl-root .tflv-sec-hd { margin: 0 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--sec); }
    #tfl-root .tflv-info { display: grid; gap: 9px; padding: 15px 18px; background: var(--surf); border: 1px solid var(--bdr); border-radius: 12px; }
    #tfl-root .tflv-row { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 12px; align-items: baseline; font-size: 13px; }
    #tfl-root .tflv-k { font-weight: 600; color: var(--sec); }
    #tfl-root .tflv-v { color: var(--txt); overflow-wrap: anywhere; }
    #tfl-root .tflv-chip { display: inline-block; margin-right: 4px; padding: 2px 7px; border-radius: 4px; vertical-align: 1px;
        font: 600 11px/1.4 var(--mono); color: var(--afims-primary-text, #0035A9); background: var(--afims-surface, #FFFFFF); border: 1px solid var(--navy-bdr, #E6EBF6); }
    #tfl-root .tflv-muted { color: var(--subtle); }
    #tfl-root .tflv-doc { display: flex; align-items: center; gap: 10px; padding: 9px 12px; margin-bottom: 6px; font-size: 13px;
        background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 10px; }
    #tfl-root .tflv-doc svg { width: 16px; height: 16px; flex-shrink: 0; color: var(--afims-primary, #0035A9); }
    #tfl-root .tflv-doc a, #tfl-root .tflv-doc span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    /* the ORs of a Trust Fund, as a small table */
    #tfl-root .tflv-or-wrap { overflow-x: auto; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 12px; }
    #tfl-root .tflv-or { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    #tfl-root .tflv-or th { padding: 8px 12px; text-align: left; white-space: nowrap; font-size: 10.5px; font-weight: 700; letter-spacing: .05em;
        text-transform: uppercase; color: var(--sec); background: var(--surf); border-bottom: 1px solid var(--bdr); }
    #tfl-root .tflv-or td { padding: 9px 12px; border-bottom: 1px solid var(--bdr); }
    #tfl-root .tflv-or tr:last-child td { border-bottom: none; }
    #tfl-root .tflv-or .r { text-align: right; white-space: nowrap; font-family: var(--mono); }
    @media (max-width: 640px) { #tfl-root .tflv-row { grid-template-columns: 1fr; gap: 2px; } }
</style>

<div id="tfl-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Trust Funds</div>
                <div class="nha-hdr-sub">Status of Trust Funds · Fund Sourcing Division · SRS Ref: 1.1.2.10</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="tfl-new" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);border:none;" title="New Trust Fund / OR entry">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Entry
            </button>
            <button id="tfl-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="tfl-export-open" type="button" class="nha-btn-hdr" title="Export">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Export
            </button>
            <span class="nha-hdr-upd" id="tfl-updated">—</span>
        </div>
    </div>

    <!-- Error banner -->
    <div id="tfl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="tfl-error-msg">Failed to load data. Please refresh.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-md">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="tfl-fy" class="nha-filter-select"><option value="">All Years</option></select>
            </div>
            <div class="nha-filter-group nha-filter-group-lg tfl-program-group">
                <label class="nha-filter-label">Program</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="tfl-program" class="nha-filter-input" placeholder="All programs" autocomplete="off" title="Type a program code or name, then pick it from the list. Clear the box for all programs.">
                </div>
            </div>
            <!-- Agency / Program: live-searches the agency name OR the sub-program / program name or code (agency_or_program_search —
                 trust fund entries don't reliably carry a meaningful reference number). Typing narrows the list as you go; picking an
                 agency from the suggestions applies it at once. -->
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Agency / Program</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="tfl-search" class="nha-filter-input" placeholder="Filter by agency or program…" autocomplete="off">
                    <button class="nha-filter-clear-btn" id="tfl-search-clear" hidden type="button">×</button>
                </div>
            </div>
            <div class="nha-filter-group nha-filter-group-sm">
                <label class="nha-filter-label">Per Page</label>
                <select id="tfl-limit" class="nha-filter-select">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button id="tfl-apply" type="button" class="nha-btn nha-btn-primary nha-btn-sm">Apply</button>
                <button id="tfl-reset" type="button" class="nha-btn nha-btn-ghost nha-btn-sm">Reset</button>
            </div>
        </div>
    </div>

    <!-- ── SUMMARY CARDS ──────────────────────────────────── -->
    <div class="nha-summary" id="tfl-cards">
        <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Records</div><div class="nha-sc-val">—</div></div></div><p class="nha-kpi-sub">Trust fund entries</p></div>
        <div class="nha-sc nha-sc-green"><div class="nha-sc-top"><div><div class="nha-sc-label">Total OR Amount</div><div class="nha-sc-val">—</div></div></div><p class="nha-kpi-sub">From OR records under these trust funds</p></div>
    </div>

    <!-- ── RECORD-TYPE TABS: Trust Fund (the tree) / OR (flat list) ── -->
    <div class="nha-tabs no-print" role="tablist" aria-label="Record type">
        <button type="button" class="nha-tab active" id="tfl-tab-trust" data-tab="TRUST_FUND" role="tab" aria-selected="true">Trust Fund</button>
        <button type="button" class="nha-tab" id="tfl-tab-or" data-tab="OR" role="tab" aria-selected="false">OR</button>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl tfl-tbl">
                <!-- the header row is drawn per tab by tfl_renderHead() -->
                <thead id="tfl-thead"></thead>
                <tbody id="tfl-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap no-print">
            <span class="nha-pag-info">Showing <strong><span id="tfl-from">0</span>–<span id="tfl-to">0</span></strong> of <strong><span id="tfl-total">0</span></strong> records<span id="tfl-expand-hint"> — click a row with a chevron to expand its ORs</span></span>
            <div id="tfl-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ══════════════ VIEW MODAL ══════════════ -->
    <div id="tfl-view-ov" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-lg">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="tfl-view-title">Trust Fund Record</h3>
                <button type="button" class="nha-modal-close" id="tfl-view-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="tfl-view-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="tfl-view-done">Close</button>
                <button type="button" class="nha-btn nha-btn-primary" id="tfl-view-edit">Edit</button>
            </div>
        </div>
    </div>

    <!-- ══════════════ NEW ENTRY CHOOSER ══════════════ -->
    <div id="tfl-new-ov" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-sm">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title">New Entry</h3>
                <button type="button" class="nha-modal-close" id="tfl-new-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <p class="tfl-hint" style="margin:0 0 14px;">Choose which form to open</p>
                <button type="button" class="tfl-new-opt" id="tfl-new-trust">
                    <span class="tfl-new-opt-title">Trust Fund Form</span>
                    <span class="tfl-new-opt-desc">Record a new trust fund entry</span>
                </button>
                <button type="button" class="tfl-new-opt" id="tfl-new-or">
                    <span class="tfl-new-opt-title">OR Form</span>
                    <span class="tfl-new-opt-desc">Record an official receipt against a trust fund</span>
                </button>
            </div>
        </div>
    </div>

    <?php
    // The two forms, both modals in their own files: the Trust Fund form, and the OR form — the SARO processing form held to the
    // OR tracking type (an OR is linked to a Trust Fund, picked from a list in that form). $sarSupplementalFields = false keeps it
    // the plain fields whatever nature of fund is chosen. The Trust Fund form calls window.tfl_reload() after a save, the OR form
    // window.sr_reload() (both defined below).
    $sarAllowedTypes = ['OR'];
    $sarSupplementalFields = false;
    include __DIR__ . '/form/trust-fund-form.php';
    include __DIR__ . '/form/saro-processing-form.php';
    ?>

    <!-- ══════════════ EXPORT MODAL ══════════════ -->
    <div id="tfl-xm-ov" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-sm">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="tfl-xm-title">Export Trust Funds</h3>
                <button type="button" class="nha-modal-close" id="tfl-xm-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <p class="tfl-hint" style="margin:0 0 14px;">All matching records are exported, not just the current page.</p>
                <div class="nha-fg">
                    <label for="tfl-xm-fy">Fiscal Year</label>
                    <select id="tfl-xm-fy"><option value="">All Years</option></select>
                </div>
                <div class="nha-fg">
                    <label for="tfl-xm-program">Program</label>
                    <input type="text" id="tfl-xm-program" placeholder="All programs" autocomplete="off">
                </div>
                <div class="nha-fg">
                    <label for="tfl-xm-agency">Agency</label>
                    <input type="text" id="tfl-xm-agency" placeholder="All agencies" autocomplete="off">
                </div>
                <div class="nha-fg" style="margin-bottom:0;">
                    <label for="tfl-xm-format">Export As</label>
                    <select id="tfl-xm-format">
                        <option value="csv">Excel-compatible (.csv)</option>
                        <option value="pdf">PDF (opens the print dialog — Save as PDF)</option>
                    </select>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="tfl-xm-cancel">Cancel</button>
                <button type="button" class="nha-btn nha-btn-primary" id="tfl-xm-submit">Export</button>
            </div>
        </div>
    </div>

</div><!-- /#tfl-root -->

<script>
/* ═══════════════════════════════════════════════════════════════
   TRUST FUNDS   prefix: tfl_
   SRS 1.1.2.10 | Fund Sourcing Division | API-driven
═══════════════════════════════════════════════════════════════ */

const TFL_API = <?= json_encode($_tfl_api) ?>;
const TFL_U = {
    list:     TFL_API + '/fund_tracking/list',
    read:     id => TFL_API + '/fund_tracking/read/' + encodeURIComponent(id),
    del:      id => TFL_API + '/fund_tracking/delete/' + encodeURIComponent(id),
    years:    TFL_API + '/fiscal_year/all',
    programs: TFL_API + '/program/all',
    agencies: TFL_API + '/agency/all',
};

// This page is scoped to Trust Funds and the ORs recorded against them. The API takes the tracking type spelled either way
// (the master list says "TRUST FUND"); every row is compared on one spelling — see tfl_typeCode().
const TFL_TRUST = 'TRUST_FUND';
const TFL_OR = 'OR';

const $tfl = id => document.getElementById(id);
const tfl_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ── State ──────────────────────────────────────────────────── */
const tfl = {
    tab: TFL_TRUST,           // 'TRUST_FUND' (tree of Trust Funds) | 'OR' (flat list of OR records)
    fy: '', program: '', search: '',
    page: 1, limit: 10,
    total: 0,                 // top-level rows matching the filters
    rows: [],                 // this page's rows — Trust Fund tab: each with its `children` (the ORs under it)
    loaded: false, error: null,
    req: 0,                   // bumped per request so a stale response never overwrites a newer one
    cardsReq: 0,
    expanded: new Set(),      // ids of the rows currently expanded
    searchTimer: null,
    programs: [],             // [{value: program_code, label: program_name}]
    programCodes: new Set(),
    agencies: [],             // agency names
};

/* ── API helpers ────────────────────────────────────────────── */
async function tfl_api(url, opts = {}) {
    const res = await fetch(url, {
        credentials: 'same-origin',
        ...opts,
        headers: { 'Content-Type': 'application/json', ...(opts.headers || {}) },
    });
    let json = null;
    try { json = await res.json(); } catch (_) { /* not JSON */ }
    if (!res.ok || !json || json.success === false) {
        throw new Error((json && (json.message || json.error)) || ('HTTP ' + res.status));
    }
    return json;
}

function tfl_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const color = colors[type] || colors.info;
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    t.innerHTML = `<div style="flex:1"><span>${tfl_esc(msg)}</span></div><button type="button" style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    t.querySelector('button').addEventListener('click', () => t.remove());
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4500);
}

function tfl_showError(msg) { $tfl('tfl-error').style.display = 'flex'; $tfl('tfl-error-msg').textContent = msg; }
function tfl_hideError() { $tfl('tfl-error').style.display = 'none'; }
function tfl_setUpdated() {
    $tfl('tfl-updated').textContent = 'Updated: ' + new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

/* ── Formatting ─────────────────────────────────────────────── */
function tfl_peso(v) {
    return '₱ ' + (parseFloat(v) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function tfl_fmtDate(s) {
    if (!s) return '—';
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s));
    const d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(s);
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}

/* ── Record accessors (fims.fund_tracking row shape) ─────────── */
// The master list spells the type "TRUST FUND" (older data "TRUST"); compare every spelling as TRUST_FUND, or no row would
// ever be recognised as a Trust Fund (the tab filter, the cards, the export).
function tfl_typeCode(r) {
    const c = String(r.tracking_type?.fund_tracking_code || '').trim().toUpperCase().replace(/[\s_]+/g, '_');
    return c === 'TRUST' ? TFL_TRUST : c;
}
const tfl_kids = r => (Array.isArray(r.children) ? r.children : []);
const tfl_parentOf = r => (Array.isArray(r.parent) && r.parent.length ? r.parent[0] : null);
const tfl_sum = list => list.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);
// the ORs of a Trust Fund (children are asked for by type, but /read returns every child)
const tfl_orsOf = r => tfl_kids(r).filter(c => tfl_typeCode(c) === TFL_OR);

function tfl_findRecord(id) {
    for (const r of tfl.rows) {
        if (Number(r.id) === id) return r;
        const kid = tfl_kids(r).find(c => Number(c.id) === id);
        if (kid) return kid;
    }
    return null;
}

/* ── Requests ────────────────────────────────────────────────────
   Trust Fund tab — the tree: GET /fund_tracking/list?type=TRUST_FUND&type=OR&roots_only=1&root_type=TRUST_FUND returns the
   top-level Trust Funds, each with a `children` array of the ORs whose parent it is. Both types are needed: `type` also decides
   which CHILDREN come back, so asking for TRUST_FUND alone would drop every OR; root_type keeps the top-level rows to Trust Funds
   (an OR with no Trust Fund belongs on the OR tab, not here). page/limit count the top-level rows, so paging walks Trust Funds —
   a Trust Fund's ORs are never split across pages.
   OR tab — a flat list of OR records (type=OR), each naming the Trust Fund it belongs to.
   Fiscal Year, Program and the search narrow the top-level rows; a Trust Fund that matches always shows all of its ORs. The
   search is agency_or_program_search — free text against the agency name and the sub-program / program name or code. */
function tfl_listParams(f = tfl, tab = f.tab) {
    const p = new URLSearchParams();
    if (tab === TFL_OR) {
        p.append('type', TFL_OR);
    } else {
        p.append('type', TFL_TRUST);
        p.append('type', TFL_OR);
        p.set('roots_only', '1');
        p.set('root_type', TFL_TRUST);
    }
    if (f.fy) p.set('fiscal_year', f.fy);
    if (f.program) p.set('program_code', f.program);
    if (f.search) p.set('agency_or_program_search', f.search);
    return p;
}

// Walks /fund_tracking/list page by page (no row cap) and returns every matching record
async function tfl_fetchAll(f, tab) {
    const PAGE = 500;
    const rows = [];
    let page = 1, last = 1;
    do {
        const p = tfl_listParams(f, tab);
        p.set('page', page);
        p.set('limit', PAGE);
        const json = await tfl_api(TFL_U.list + '?' + p);
        rows.push(...(json.data || []));
        last = json.pagination?.last_page ?? 1;
        page++;
    } while (page <= last);
    return rows;
}

/* ── Summary cards ───────────────────────────────────────────────
   Counted from EVERY record matching the filters of the active tab (all pages, not just the table page on screen).
   Trust Fund tab: the trust funds, and the amount of the ORs under them. OR tab: the OR records and their amount. */
function tfl_renderCards(c) {
    c = c || { total: 0, orAmount: 0 };
    const isOr = tfl.tab === TFL_OR;
    const card = (cls, label, value, sub, title) =>
        `<div class="nha-sc ${cls}"><div class="nha-sc-top"><div><div class="nha-sc-label">${label}</div><div class="nha-sc-val"${title ? ` title="${tfl_esc(title)}"` : ''}>${value}</div></div></div><p class="nha-kpi-sub">${sub}</p></div>`;
    $tfl('tfl-cards').innerHTML =
        card('nha-sc-navy', isOr ? 'Total OR Records' : 'Total Records', Number(c.total || 0).toLocaleString(), isOr ? 'OR entries' : 'Trust fund entries') +
        card('nha-sc-green', 'Total OR Amount', tfl_esc(tfl_peso(c.orAmount)), isOr ? 'Across the OR records' : 'From OR records under these trust funds', tfl_peso(c.orAmount));
}

async function tfl_loadCards() {
    const token = ++tfl.cardsReq;
    const tab = tfl.tab;
    try {
        const rows = await tfl_fetchAll(tfl, tab);
        if (token !== tfl.cardsReq) return;                // superseded by a newer request (or another tab)
        tfl_renderCards({
            total: rows.length,
            orAmount: tab === TFL_OR ? tfl_sum(rows) : rows.reduce((s, r) => s + tfl_sum(tfl_orsOf(r)), 0),
        });
        tfl_setUpdated();
    } catch (e) {
        if (token !== tfl.cardsReq) return;
        console.warn('[tfl_loadCards]', e);
    }
}

/* ── Filter dropdowns ───────────────────────────────────────── */
async function tfl_loadYears() {
    try {
        const json = await tfl_api(TFL_U.years);
        const years = [...new Set((json.data || []).map(r => Number(r.fiscal_yr)).filter(Boolean))].sort((a, b) => a - b);
        const opts = years.map(y => `<option value="${y}">${y}</option>`).join('');
        $tfl('tfl-fy').insertAdjacentHTML('beforeend', opts);
    } catch (e) { console.warn('[tfl_loadYears]', e); }
}

async function tfl_loadPrograms() {
    try {
        const json = await tfl_api(TFL_U.programs);
        tfl.programs = (json.data || []).map(p => ({ value: String(p.program_code), label: String(p.program_name ?? '') }));
        tfl.programCodes = new Set(tfl.programs.map(p => p.value));
        nhaSearchableSelect($tfl('tfl-program'), tfl.programs);
        nhaSearchableSelect($tfl('tfl-xm-program'), tfl.programs);
    } catch (e) { console.warn('[tfl_loadPrograms]', e); }
}

/* The Program input shows the code of the program picked from the list; anything typed that isn't a
   real program code is not a filter, so it is dropped. */
function tfl_programValue(inputId) {
    const v = $tfl(inputId).value.trim();
    return tfl.programCodes.has(v) ? v : '';
}

/* A small type-ahead over the agency names. It has the look of the shared .nha-ss-dropdown, but prints the whole name in the
   body font — nhaSearchableSelect prints a value as a one-line monospace "code", which would run a long agency name off the list. */
function tfl_nameCombo(input, getNames, onPick) {
    const box = document.createElement('div');
    box.className = 'nha-ss-dropdown';
    box.style.fontFamily = "'IBM Plex Sans', sans-serif";   // it lives on <body>, outside .nha-root, so it doesn't inherit the page font
    document.body.appendChild(box);
    let list = [];

    const position = () => {
        const r = input.getBoundingClientRect();
        Object.assign(box.style, { position: 'fixed', left: r.left + 'px', top: (r.bottom + 2) + 'px', width: Math.max(r.width, 260) + 'px' });
    };
    const hide = () => { box.style.display = 'none'; };
    const render = () => {
        const q = input.value.trim().toLowerCase();
        list = getNames().filter(n => !q || n.toLowerCase().includes(q)).slice(0, 50);
        box.innerHTML = list.length
            ? list.map((n, i) => `<div class="nha-ss-opt" data-i="${i}"><span class="nha-ss-opt-label" style="color:var(--afims-text, #1A1B21);white-space:normal;">${tfl_esc(n)}</span></div>`).join('')
            : '<div class="nha-ss-empty">No matching agencies</div>';
        position();
        box.style.display = 'block';
    };

    input.addEventListener('input', render);
    input.addEventListener('focus', render);
    input.addEventListener('blur', () => setTimeout(hide, 150));
    box.addEventListener('mousedown', e => {
        const el = e.target.closest('.nha-ss-opt');
        if (!el) return;
        e.preventDefault();                                  // keep focus on the input
        input.value = list[Number(el.dataset.i)];
        hide();
        onPick(input.value);
    });
    const reposition = () => { if (box.style.display === 'block') position(); };
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
}

async function tfl_loadAgencies() {
    try {
        const json = await tfl_api(TFL_U.agencies);
        tfl.agencies = (json.data || [])
            .filter(a => a.is_active !== false && String(a.name || '').trim() !== '')
            .map(a => String(a.name))
            .sort((a, b) => a.localeCompare(b));
    } catch (e) { console.warn('[tfl_loadAgencies]', e); }
}

/* ── Cells ───────────────────────────────────────────────────── */
const tfl_dash = '<span class="nha-text-muted">—</span>';

function tfl_agencyCell(r) {
    const n = r.agency?.name || r.agency_name;
    return n ? `<span style="font-size:12px;font-weight:600;">${tfl_esc(n)}</span>` : tfl_dash;
}

function tfl_programCell(r) {
    const sp = r.sub_program;
    if (!sp) return tfl_dash;
    return `<div style="font-size:12px;line-height:1.35;">
        <div style="font-weight:500;">${tfl_esc(sp.sub_program_name)}</div>
        <div class="nha-mono" style="font-size:10px;color:var(--afims-text-disabled, #A9AAB8);">${tfl_esc(sp.sub_program_code)}${sp.program_name ? ' · ' + tfl_esc(sp.program_name) : ''}</div>
    </div>`;
}

function tfl_amountCell(v) {
    const val = parseFloat(v) || 0;
    return val ? `<span>${tfl_esc(tfl_peso(val))}</span>` : '—';
}

function tfl_remarksCell(v) {
    const t = String(v ?? '').trim();
    if (!t) return tfl_dash;
    return `<span style="font-size:11.5px;color:var(--afims-text-muted, #62657E);" title="${tfl_esc(t)}">${tfl_esc(t.length > 60 ? t.slice(0, 60) + '…' : t)}</span>`;
}

const tfl_refLink = r =>
    `<a href="#" class="nha-tbl-link nha-mono tfl-ref" onclick="event.preventDefault();event.stopPropagation();tfl_view(${Number(r.id)})">${tfl_esc(r.reference_number || '—')}</a>`;

/* ── Rows ────────────────────────────────────────────────────── */
const TFL_CARET = '<span class="tfl-caret__CLS__" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg></span>';
const TFL_VIEW_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const TFL_EDIT_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
const TFL_DELETE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
const TFL_MENU_ICON = '<svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>';

// One "Actions" button per row that opens a menu — the same control as budget-prop-page.php: View Details and Edit for every row;
// Delete, under a divider, for a Trust Fund only (an OR is not deleted from here). The cell stops the click from reaching the
// row, which would expand / collapse it.
function tfl_toolsCell(r) {
    const id = Number(r.id);
    const type = tfl_typeCode(r) === TFL_OR ? TFL_OR : TFL_TRUST;
    const del = type === TFL_TRUST
        ? `<div class="nha-menu-divider" role="separator"></div>
                <button type="button" role="menuitem" class="nha-menu-item danger" onclick="tfl_closeMenus();tfl_delete(${id})">${TFL_DELETE_ICON}Delete</button>`
        : '';
    return `
    <td class="no-print" style="text-align:center;" onclick="event.stopPropagation()">
        <div class="nha-tools-menu">
            <button type="button" class="nha-menu-trigger" aria-haspopup="menu" aria-expanded="false" onclick="tfl_toggleMenu(this)">${TFL_MENU_ICON}Actions</button>
            <div class="nha-tools-dropdown" role="menu">
                <button type="button" role="menuitem" class="nha-menu-item" onclick="tfl_closeMenus();tfl_view(${id})">${TFL_VIEW_ICON}View Details</button>
                <button type="button" role="menuitem" class="nha-menu-item" onclick="tfl_closeMenus();tfl_edit(${id}, '${type}')">${TFL_EDIT_ICON}Edit</button>
                ${del}
            </div>
        </div>
    </td>`;
}

/* ── Actions dropdown — open / close ───────────────────────────────
   Positioned with fixed coordinates from the button (so the table's own scrolling never clips it); it opens upwards
   instead when there is no room below (the last rows of a page). Only one is open at a time. It closes on a click
   elsewhere, Escape, scrolling and resizing. */
function tfl_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const wasOpen = drop.classList.contains('open');
    tfl_closeMenus();
    if (wasOpen) return;

    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
    btn.closest('td').classList.add('tfl-menu-open');   // lifts the sticky cell above the rows below (see the CSS)
    const rect = btn.getBoundingClientRect(), h = drop.offsetHeight;
    const fitsBelow = rect.bottom + 4 + h <= window.innerHeight - 8;
    drop.style.top = (fitsBelow ? rect.bottom + 4 : Math.max(8, rect.top - 4 - h)) + 'px';
    drop.style.right = (document.documentElement.clientWidth - rect.right) + 'px';
}

function tfl_closeMenus() {
    document.querySelectorAll('#tfl-root .nha-tools-dropdown.open').forEach(drop => {
        drop.classList.remove('open');
        const menu = drop.closest('.nha-tools-menu');
        menu.querySelector('.nha-menu-trigger').setAttribute('aria-expanded', 'false');
        menu.closest('td').classList.remove('tfl-menu-open');
    });
}

// mousedown, not click: the Actions cell swallows clicks (see above), but not this
document.addEventListener('mousedown', e => { if (!e.target.closest('.nha-tools-menu')) tfl_closeMenus(); });
document.addEventListener('scroll', tfl_closeMenus, true);
window.addEventListener('resize', tfl_closeMenus);

// Trust Fund tab — a Trust Fund says what it IS (its type) and how many ORs sit beneath it
function tfl_parentRow(r, expanded) {
    const kids = tfl_orsOf(r);
    const hasKids = kids.length > 0;
    const tags = '<span class="tfl-tag tfl-tag-type">TRUST FUND</span>' + (hasKids
        ? `<span class="tfl-tag tfl-tag-count" title="${kids.length} OR under this Trust Fund">${kids.length} OR</span>`
        : '<span class="tfl-tag tfl-tag-none">No OR yet</span>');
    return `
    <tr class="tfl-parent${hasKids ? ' has-kids' : ''}${expanded ? ' expanded' : ''}"
        ${hasKids ? `aria-expanded="${expanded ? 'true' : 'false'}" onclick="tfl_toggle(${Number(r.id)})"` : ''}>
        <td>
            <div class="tfl-ref-line">${TFL_CARET.replace('__CLS__', hasKids ? '' : ' none')}${tfl_refLink(r)}</div>
            <div class="tfl-tags">${tags}</div>
        </td>
        <td>${tfl_agencyCell(r)}</td>
        <td>${tfl_programCell(r)}</td>
        <td class="tfl-num">${tfl_amountCell(r.amount)}</td>
        <td class="nha-td-date" style="white-space:nowrap;">${tfl_esc(tfl_fmtDate(r.date))}</td>
        <td class="tfl-num">${hasKids ? tfl_amountCell(tfl_sum(kids)) : '—'}</td>
        <td>${tfl_remarksCell(r.remarks)}</td>
        ${tfl_toolsCell(r)}
    </tr>`;
}

// One OR under its Trust Fund — same columns, but a recessed row with a tree connector back to the parent's chevron.
// "OR Total" is a Trust Fund-level roll-up, so it stays blank here (this OR's own amount is in the Amount column).
function tfl_childRow(c, isLast) {
    return `
    <tr class="tfl-child${isLast ? ' last' : ''}">
        <td>
            <div class="tfl-child-ref">
                <div class="tfl-ref-line">${tfl_refLink(c)}</div>
                <div class="tfl-tags"><span class="tfl-tag tfl-tag-child">OR</span></div>
            </div>
        </td>
        <td>${tfl_agencyCell(c)}</td>
        <td>${tfl_programCell(c)}</td>
        <td class="tfl-num">${tfl_amountCell(c.amount)}</td>
        <td class="nha-td-date" style="white-space:nowrap;">${tfl_esc(tfl_fmtDate(c.date))}</td>
        <td class="tfl-num">—</td>
        <td>${tfl_remarksCell(c.remarks)}</td>
        ${tfl_toolsCell(c)}
    </tr>`;
}

// OR tab — the Trust Fund an OR belongs to, from the API's `parent` (a one-item array). An OR recorded without one says so.
function tfl_trustFundCell(r) {
    const tf = tfl_parentOf(r);
    if (!tf) return '<span class="tfl-tag tfl-tag-none" title="This OR has no parent Trust Fund">No Trust Fund</span>';
    const ref = tf.reference_number || tf.name || '—';
    const sub = tf.name && tf.name !== ref ? `<div class="tfl-tf-name">${tfl_esc(tf.name)}</div>` : '';
    return `<a href="#" class="nha-tbl-link nha-mono" onclick="event.preventDefault();event.stopPropagation();tfl_view(${Number(tf.id)})">${tfl_esc(ref)}</a>${sub}`;
}

// OR tab — one flat OR record: no tree, so no caret, indent or roll-up column
function tfl_orRow(r) {
    return `
    <tr class="tfl-or">
        <td>
            ${tfl_refLink(r)}
            <div class="tfl-tags"><span class="tfl-tag tfl-tag-child">OR</span></div>
        </td>
        <td>${tfl_trustFundCell(r)}</td>
        <td>${tfl_agencyCell(r)}</td>
        <td>${tfl_programCell(r)}</td>
        <td class="tfl-num">${tfl_amountCell(r.amount)}</td>
        <td class="nha-td-date" style="white-space:nowrap;">${tfl_esc(tfl_fmtDate(r.date))}</td>
        <td>${tfl_remarksCell(r.remarks)}</td>
        ${tfl_toolsCell(r)}
    </tr>`;
}

/* ── Table + record-type tabs ─────────────────────────────────── */
const TFL_HEAD = {
    TRUST_FUND: `<tr>
        <th style="min-width:210px;">Reference No.</th>
        <th style="min-width:170px;">Agency</th>
        <th style="min-width:190px;">Program / Sub-Program</th>
        <th style="text-align:right;">Amount (₱)</th>
        <th>Date</th>
        <th style="text-align:right;">OR Total (₱)</th>
        <th style="min-width:150px;">Remarks</th>
        <th class="no-print" style="text-align:center;">Actions</th>
    </tr>`,
    OR: `<tr>
        <th style="min-width:180px;">OR No.</th>
        <th style="min-width:170px;">Trust Fund</th>
        <th style="min-width:170px;">Agency</th>
        <th style="min-width:190px;">Program / Sub-Program</th>
        <th style="text-align:right;">Amount (₱)</th>
        <th>Date</th>
        <th style="min-width:150px;">Remarks</th>
        <th class="no-print" style="text-align:center;">Actions</th>
    </tr>`,
};
const TFL_TAB_TEXT = {
    TRUST_FUND: { empty: 'No Trust Fund records found.', exportTitle: 'Export Trust Funds' },
    OR:         { empty: 'No OR records found.',         exportTitle: 'Export OR Records' },
};

function tfl_renderHead() { $tfl('tfl-thead').innerHTML = TFL_HEAD[tfl.tab]; }

function tfl_renderTable() {
    const body = $tfl('tfl-tbody');
    const isOr = tfl.tab === TFL_OR;

    if (tfl.error) {
        body.innerHTML = `<tr><td colspan="8" class="tfl-empty">Could not load records: ${tfl_esc(tfl.error)}</td></tr>`;
    } else if (!tfl.loaded) {
        body.innerHTML = '<tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>';
    } else if (!tfl.rows.length) {
        body.innerHTML = `<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">${TFL_TAB_TEXT[tfl.tab].empty}</span></div></td></tr>`;
    } else if (isOr) {
        body.innerHTML = tfl.rows.map(tfl_orRow).join('');
    } else {
        body.innerHTML = tfl.rows.map(r => {
            const kids = tfl_orsOf(r);
            const expanded = kids.length > 0 && tfl.expanded.has(Number(r.id));
            return tfl_parentRow(r, expanded) + (expanded ? kids.map((c, i, all) => tfl_childRow(c, i === all.length - 1)).join('') : '');
        }).join('');
    }
    $tfl('tfl-expand-hint').style.display = isOr ? 'none' : '';
    tfl_renderPager();
}

function tfl_toggle(id) {
    // accordion: opening one Trust Fund closes whichever other one was open
    id = Number(id);
    const wasOpen = tfl.expanded.has(id);
    tfl.expanded.clear();
    if (!wasOpen) tfl.expanded.add(id);
    tfl_renderTable();
}

function tfl_switchTab(tab) {
    tab = tab === TFL_OR ? TFL_OR : TFL_TRUST;
    if (tab === tfl.tab) return;
    tfl.tab = tab;
    document.querySelectorAll('#tfl-root .nha-tab').forEach(b => {
        const on = b.dataset.tab === tab;
        b.classList.toggle('active', on);
        b.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    tfl_renderHead();
    // don't leave the other tab's rows (or cards) on screen while this one loads
    tfl.rows = []; tfl.total = 0; tfl.loaded = false; tfl.error = null; tfl.expanded.clear(); tfl.page = 1;
    tfl_renderCards(null);
    tfl_renderTable();
    tfl_loadTable();
    tfl_loadCards();
}

/* ── Pagination ─────────────────────────────────────────────── */
function tfl_renderPager() {
    const total = tfl.loaded && !tfl.error ? tfl.total : 0;
    const from = total ? (tfl.page - 1) * tfl.limit + 1 : 0;
    const to = total ? Math.min(from + tfl.rows.length - 1, total) : 0;
    $tfl('tfl-from').textContent = from;
    $tfl('tfl-to').textContent = to;
    $tfl('tfl-total').textContent = total.toLocaleString();

    const box = $tfl('tfl-pagination');
    const last = Math.max(1, Math.ceil(total / tfl.limit));
    if (last <= 1) { box.innerHTML = ''; return; }

    const cur = tfl.page;
    const nums = [...new Set([1, last, cur - 1, cur, cur + 1])].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);
    let html = `<button type="button" class="nha-pag-btn${cur <= 1 ? ' disabled' : ''}" onclick="tfl_goTo(${cur - 1})">‹</button>`;
    nums.forEach((p, i) => {
        if (i > 0 && p - nums[i - 1] > 1) html += '<span class="nha-pag-ellipsis">…</span>';
        html += `<button type="button" class="nha-pag-btn${p === cur ? ' active' : ''}" onclick="tfl_goTo(${p})">${p}</button>`;
    });
    html += `<button type="button" class="nha-pag-btn${cur >= last ? ' disabled' : ''}" onclick="tfl_goTo(${cur + 1})">›</button>`;
    box.innerHTML = html;
}

function tfl_goTo(page) {
    const last = Math.max(1, Math.ceil(tfl.total / tfl.limit));
    if (page < 1 || page > last || page === tfl.page) return;
    tfl.page = page;
    tfl_loadTable();
}

/* ── Load ───────────────────────────────────────────────────── */
async function tfl_loadTable() {
    const token = ++tfl.req;
    const tab = tfl.tab;
    try {
        const p = tfl_listParams();
        p.set('page', tfl.page);
        p.set('limit', tfl.limit);
        const json = await tfl_api(TFL_U.list + '?' + p);
        if (token !== tfl.req) return;                    // superseded by a newer request (or another tab)
        let rows = json.data || [];
        // Trust Fund tab: Trust Funds only. root_type already narrows the roots; this also drops a parentless OR if the API ever ignored it
        if (tab !== TFL_OR) rows = rows.filter(r => tfl_typeCode(r) === TFL_TRUST);
        tfl.rows = rows;
        tfl.total = json.pagination?.total ?? rows.length;
        tfl.loaded = true;
        tfl.error = null;
        tfl_hideError();
        tfl_setUpdated();
    } catch (e) {
        if (token !== tfl.req) return;
        tfl.error = e.message;
        tfl_showError('Could not load data: ' + e.message);
    }
    tfl_renderTable();
}

function tfl_reloadTable() { tfl.page = 1; return tfl_loadTable(); }
function tfl_reload() { return Promise.all([tfl_loadCards(), tfl_loadTable()]); }

/* ── Apply / reset filters ──────────────────────────────────── */
function tfl_apply() {
    tfl.fy = $tfl('tfl-fy').value;
    tfl.program = tfl_programValue('tfl-program');
    if (!tfl.program) $tfl('tfl-program').value = '';
    tfl.search = $tfl('tfl-search').value.trim();
    tfl.limit = parseInt($tfl('tfl-limit').value) || 10;
    tfl.expanded.clear();
    tfl_reloadTable();
    tfl_loadCards();
}

function tfl_resetFilters() {
    ['tfl-fy', 'tfl-program', 'tfl-search'].forEach(id => { $tfl(id).value = ''; });
    $tfl('tfl-search-clear').hidden = true;
    $tfl('tfl-limit').value = '10';
    tfl_apply();
}

/* ── Overlays ───────────────────────────────────────────────── */
function tfl_openOverlay(id) { $tfl(id).classList.add('open'); }
function tfl_closeOverlay(id) { $tfl(id).classList.remove('open'); }

/* ── View modal ─────────────────────────────────────────────── */
let tfl_viewId = null, tfl_viewType = TFL_TRUST;

const TFL_DOC_ICON = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';

const tfl_vrow = (label, valueHtml) => `<div class="tflv-row"><div class="tflv-k">${label}</div><div class="tflv-v">${valueHtml}</div></div>`;
const tfl_vsec = (title, innerHtml) => `<div class="tflv-sec"><h4 class="tflv-sec-hd">${title}</h4>${innerHtml}</div>`;
const tfl_vstat = (label, v) => `<div class="tflv-stat"><div class="tflv-stat-l">${label}</div><div class="tflv-stat-v">${tfl_esc(tfl_peso(v))}</div></div>`;

async function tfl_view(id) {
    window.PagePreloader?.show();
    try {
        const d = (await tfl_api(TFL_U.read(id))).data;
        tfl_viewId = Number(d.id);
        const type = tfl_typeCode(d) === TFL_OR ? TFL_OR : TFL_TRUST;
        tfl_viewType = type;
        const sp = d.sub_program;
        const parent = tfl_parentOf(d);
        const ors = type === TFL_TRUST ? tfl_orsOf(d) : [];
        const has = v => v !== null && v !== undefined && String(v).trim() !== '';
        const chip = code => `<span class="tflv-chip">${tfl_esc(code)}</span>`;
        const agency = d.agency?.name || d.agency_name;

        // title: "Reference No. <ref>" and the record type as a badge
        $tfl('tfl-view-title').innerHTML = `Reference No. <span class="tflv-ref">${tfl_esc(d.reference_number || '#' + d.id)}</span>`
            + `<span class="tflv-badge nha-bdg nha-bdg-navy">${type === TFL_OR ? 'OR' : 'TRUST FUND'}</span>`;

        let html = `<div class="tflv-stats">${tfl_vstat('Amount', d.amount)}${ors.length ? tfl_vstat('OR Total', tfl_sum(ors)) : ''}</div>`;

        const classification = [
            tfl_vrow('Tracking Type', d.tracking_type ? `${tfl_esc(d.tracking_type.fund_tracking_code)} <span class="tflv-muted">— ${tfl_esc(d.tracking_type.fund_tracking_name || '')}</span>` : tfl_dash),
            tfl_vrow('Fiscal Year', d.fiscal_year ? 'FY ' + tfl_esc(d.fiscal_year) : tfl_dash),
            tfl_vrow('Program', sp && sp.program_code ? `${chip(sp.program_code)}${tfl_esc(sp.program_name || '')}` : tfl_dash),
            tfl_vrow('Sub-Program', sp ? `${chip(sp.sub_program_code)}${tfl_esc(sp.sub_program_name)}` : tfl_dash),
        ];
        if (type === TFL_OR) {
            classification.push(tfl_vrow('Trust Fund', parent
                ? `<a href="#" class="nha-tbl-link nha-mono" onclick="event.preventDefault();tfl_view(${Number(parent.id)})">${tfl_esc(parent.reference_number || '#' + parent.id)}</a>`
                : '<span class="tflv-muted">None — this OR has no parent Trust Fund</span>'));
        }
        html += tfl_vsec('Classification', `<div class="tflv-info">${classification.join('')}</div>`);

        const info = [
            tfl_vrow('Agency / LGU', has(agency) ? tfl_esc(agency) : tfl_dash),
            tfl_vrow(type === TFL_OR ? 'Date' : 'Date of SARO', has(d.date) ? tfl_esc(tfl_fmtDate(d.date)) : tfl_dash),
        ];
        if (has(d.name)) info.unshift(tfl_vrow('Name / Title', tfl_esc(d.name)));
        if (has(d.bank_account_no)) info.push(tfl_vrow('Bank Account No.', tfl_esc(d.bank_account_no)));
        if (has(d.remarks)) info.push(tfl_vrow('Remarks', tfl_esc(d.remarks)));
        html += tfl_vsec('Fund Information', `<div class="tflv-info">${info.join('')}</div>`);

        // the ORs recorded against this Trust Fund
        if (ors.length) {
            const rows = ors.map(c => `<tr>
                <td><a href="#" class="nha-tbl-link nha-mono" onclick="event.preventDefault();tfl_view(${Number(c.id)})">${tfl_esc(c.reference_number || '#' + c.id)}</a></td>
                <td>${c.fiscal_year ? 'FY ' + tfl_esc(c.fiscal_year) : tfl_dash}</td>
                <td class="r">${tfl_esc(tfl_peso(c.amount))}</td>
                <td style="white-space:nowrap;">${tfl_esc(tfl_fmtDate(c.date))}</td>
            </tr>`).join('');
            html += tfl_vsec(`OR Records (${ors.length})`, `<div class="tflv-or-wrap"><table class="tflv-or">
                <thead><tr><th>OR No.</th><th>Fiscal Year</th><th class="r">Amount</th><th>Date</th></tr></thead>
                <tbody>${rows}</tbody></table></div>`);
        }

        const files = (d.file_attachments || []).filter(a => !a.deleted_at && a.file_meta && !a.file_meta.deleted_at);
        if (files.length) {
            html += tfl_vsec('Supporting Documents', files.map(f => {
                const meta = f.file_meta || {};
                const url = meta.view_url || (/^https?:\/\//i.test(meta.link || '') ? meta.link : '');
                const name = tfl_esc(meta.document_name || 'Document');
                return `<div class="tflv-doc">${TFL_DOC_ICON}${url ? `<a class="nha-tbl-link" href="${tfl_esc(url)}" target="_blank" rel="noopener">${name}</a>` : `<span>${name}</span>`}</div>`;
            }).join(''));
        }

        $tfl('tfl-view-body').innerHTML = html;
        $tfl('tfl-view-body').scrollTop = 0;
        tfl_openOverlay('tfl-view-ov');
    } catch (e) {
        tfl_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── New / Edit ─────────────────────────────────────────────────
   Two forms, both modals in their own files (included above). A Trust Fund: window.trustFundModal.open(id?), which calls
   window.tfl_reload() after a save. An OR — the SARO processing form held to the OR type: window.sarModal.open(id?), which
   calls window.sr_reload() — that name is fixed by that form — so the cards and the table refresh, staying on the current page. */
window.tfl_reload = tfl_reload;
window.sr_reload = tfl_reload;

async function tfl_openTrustForm(id = null) {
    if (!window.trustFundModal) { tfl_toast('The Trust Fund form could not be loaded. Please refresh the page.', 'error'); return; }
    try {
        await window.trustFundModal.open(id);
    } catch (e) {
        console.error('[tfl_openTrustForm]', e);
        tfl_toast('Could not open the Trust Fund form: ' + e.message, 'error');
    }
}

async function tfl_openOrForm(id = null) {
    if (!window.sarModal) { tfl_toast('The OR form could not be loaded. Please refresh the page.', 'error'); return; }
    try {
        await window.sarModal.open(id);
    } catch (e) {
        console.error('[tfl_openOrForm]', e);
        tfl_toast('Could not open the OR form: ' + e.message, 'error');
    }
}

// Edit opens whichever form fits the record
function tfl_edit(id, type) {
    tfl_closeOverlay('tfl-view-ov');
    return type === TFL_OR ? tfl_openOrForm(id) : tfl_openTrustForm(id);
}

function tfl_newOpen() { tfl_openOverlay('tfl-new-ov'); }
function tfl_newPick(kind) {
    tfl_closeOverlay('tfl-new-ov');
    return kind === TFL_OR ? tfl_openOrForm(null) : tfl_openTrustForm(null);
}

/* ── Delete ─────────────────────────────────────────────────────
   DELETE /fund_tracking/delete/{id} — a soft delete (the API stamps deleted_at). The API refuses with a 409 and says why
   while ORs are still linked to the Trust Fund; that message is shown as it comes. On success the cards and table refresh,
   staying on the current page — or stepping back one page when the deleted row was the only one on a page that is not the
   first. */
async function tfl_delete(id) {
    id = Number(id);
    const rec = tfl_findRecord(id);
    const ref = (rec && rec.reference_number) || '#' + id;
    if (!confirm(`Delete trust fund ${ref}? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        await tfl_api(TFL_U.del(id), { method: 'DELETE' });
        tfl_toast(`Trust fund ${ref} deleted.`, 'success');
        if (tfl.rows.length === 1 && Number(tfl.rows[0].id) === id && tfl.page > 1) tfl.page--;
        await tfl_reload();
    } catch (e) {
        tfl_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Export ──────────────────────────────────────────────────────
   Follows the active tab (Trust Funds, or OR records) and has its own Fiscal Year / Program / Agency, independent of the
   Filters bar and of the table's paging — it walks /fund_tracking/list page by page and includes EVERY matching record, one flat
   line each. Excel-compatible CSV, or a print-ready table for "Save as PDF" (no spreadsheet / PDF library is installed in this app). */
function tfl_exportOpen() {
    $tfl('tfl-xm-title').textContent = TFL_TAB_TEXT[tfl.tab].exportTitle;
    $tfl('tfl-xm-fy').innerHTML = $tfl('tfl-fy').innerHTML;     // mirror the years the Filters bar has loaded
    $tfl('tfl-xm-fy').value = '';
    $tfl('tfl-xm-program').value = '';
    $tfl('tfl-xm-agency').value = '';
    $tfl('tfl-xm-format').value = 'csv';
    tfl_openOverlay('tfl-xm-ov');
}

const TFL_EXPORT_HEAD = {
    TRUST_FUND: ['Agency', 'Program', 'Sub-Program', 'Reference No.', 'OR No.', 'Amount', 'Date', 'Remarks'],
    OR:         ['Agency', 'Program', 'Sub-Program', 'OR No.', 'Trust Fund No.', 'Amount', 'Date', 'Remarks'],
};
const TFL_EXPORT_AMOUNT_COL = 5;

// One flat line per record — the table's columns, with full, unabbreviated values
function tfl_exportRows(records, tab) {
    return records.map(r => {
        const sp = r.sub_program;
        const tf = tfl_parentOf(r);
        return [
            r.agency?.name || r.agency_name || '',
            sp && sp.program_code ? [sp.program_code, sp.program_name].filter(Boolean).join(' - ') : '',
            sp ? [sp.sub_program_code, sp.sub_program_name].filter(Boolean).join(' - ') : '',
            r.reference_number || '',
            tab === TFL_OR
                ? (tf ? (tf.reference_number || tf.name || '') : '')                                   // the Trust Fund this OR belongs to
                : tfl_orsOf(r).map(c => c.reference_number).filter(Boolean).join(', '),               // the ORs under this Trust Fund
            parseFloat(r.amount) || 0,
            r.date ? tfl_fmtDate(r.date) : '',
            r.remarks || '',
        ];
    });
}

function tfl_download(filename, mime, content) {
    const url = URL.createObjectURL(new Blob([content], { type: mime }));
    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

const tfl_exportName = tab => (tab === TFL_OR ? 'OR_Records_' : 'Trust_Funds_') + new Date().toISOString().slice(0, 10);

function tfl_exportHead(tab) {
    return TFL_EXPORT_HEAD[tab].map((h, i) => (i === TFL_EXPORT_AMOUNT_COL ? h + ' (PHP)' : h));
}

function tfl_exportCsv(data, tab) {
    // a text cell that starts with = + - @ would be run as a formula by Excel — a leading ' makes it plain text
    const cell = v => `"${(typeof v === 'string' && /^[=+\-@\t\r]/.test(v) ? "'" + v : String(v ?? '')).replace(/"/g, '""')}"`;
    const lines = [tfl_exportHead(tab).map(cell).join(',')].concat(data.map(r => r.map(cell).join(',')));
    // BOM so Excel opens it as UTF-8
    tfl_download(tfl_exportName(tab) + '.csv', 'text/csv;charset=utf-8', String.fromCharCode(0xFEFF) + lines.join('\r\n'));
}

function tfl_exportPdf(data, tab, subtitle) {
    const title = (tab === TFL_OR ? 'OR Records' : 'Trust Funds') + ' — National Housing Authority';
    const head = tfl_exportHead(tab).map((h, i) => `<th${i === TFL_EXPORT_AMOUNT_COL ? ' class="r"' : ''}>${tfl_esc(h)}</th>`).join('');
    const body = data.map(r => '<tr>' + r.map((v, i) => i === TFL_EXPORT_AMOUNT_COL
        ? `<td class="r">${tfl_esc(Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }))}</td>`
        : `<td>${tfl_esc(v)}</td>`).join('') + '</tr>').join('');

    const html = `<!doctype html><html><head><meta charset="utf-8"><title>${tfl_esc(title)}</title><style>
        body{font:9px 'IBM Plex Sans',Arial,sans-serif;color:#1A1B21;margin:0}
        h1{font-size:15px;margin:0 0 2px;color:#000E3E} p{margin:0 0 10px;color:#62657E;font-size:10px}
        table{border-collapse:collapse;width:100%} th{background:#000E3E;color:#fff;text-align:left;padding:5px 6px;font-size:8.5px}
        td{padding:4px 6px;border-bottom:1px solid #E4ECF2;vertical-align:top} th.r,td.r{text-align:right;white-space:nowrap}
        tr{page-break-inside:avoid} thead{display:table-header-group} @page{size:A4 landscape;margin:10mm}
    </style></head><body><h1>${tfl_esc(title)}</h1><p>${tfl_esc(subtitle)}</p>
    <table><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></body></html>`;

    // A hidden iframe, not a popup, so a popup blocker can't swallow it
    const frame = document.createElement('iframe');
    frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0;';
    frame.onload = () => {
        frame.contentWindow.focus();
        frame.contentWindow.onafterprint = () => frame.remove();
        frame.contentWindow.print();
        setTimeout(() => frame.remove(), 60000);
    };
    frame.srcdoc = html;
    document.body.appendChild(frame);
}

async function tfl_exportSubmit() {
    const tab = tfl.tab;   // the export follows the active tab
    const format = $tfl('tfl-xm-format').value === 'pdf' ? 'pdf' : 'csv';
    const f = {
        fy: $tfl('tfl-xm-fy').value,
        program: tfl_programValue('tfl-xm-program'),
        search: $tfl('tfl-xm-agency').value.trim(),      // by name, the same agency_or_program_search the Filters bar uses
    };

    const parts = [tab === TFL_OR ? 'OR records' : 'Trust Funds'];
    if (f.fy) parts.push('Fiscal Year: ' + f.fy);
    if (f.program) parts.push('Program: ' + f.program);
    if (f.search) parts.push('Agency: ' + f.search);
    const subtitle = parts.join('  |  ');

    const btn = $tfl('tfl-xm-submit');
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Exporting…';
    try {
        let records = await tfl_fetchAll(f, tab);
        // Trust Funds only on the Trust Fund tab — root_type already narrows the roots; this also drops a parentless OR if the API ever ignored it
        if (tab !== TFL_OR) records = records.filter(r => tfl_typeCode(r) === TFL_TRUST);
        if (!records.length) { tfl_toast('No ' + (tab === TFL_OR ? 'OR' : 'trust fund') + ' records match the selected filters.', 'warning'); return; }
        const data = tfl_exportRows(records, tab);
        format === 'pdf' ? tfl_exportPdf(data, tab, subtitle) : tfl_exportCsv(data, tab);
        tfl_closeOverlay('tfl-xm-ov');
    } catch (e) {
        console.error('[tfl_exportSubmit]', e);
        tfl_toast('Export failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = label;
    }
}

/* ── Events ─────────────────────────────────────────────────── */
function tfl_bind() {
    $tfl('tfl-refresh').addEventListener('click', async () => {
        const btn = $tfl('tfl-refresh');
        btn.classList.add('loading');
        try { await tfl_reload(); } finally { btn.classList.remove('loading'); }
    });
    $tfl('tfl-new').addEventListener('click', tfl_newOpen);
    $tfl('tfl-export-open').addEventListener('click', tfl_exportOpen);

    document.querySelectorAll('#tfl-root .nha-tab').forEach(b => b.addEventListener('click', () => tfl_switchTab(b.dataset.tab)));

    $tfl('tfl-apply').addEventListener('click', tfl_apply);
    $tfl('tfl-reset').addEventListener('click', tfl_resetFilters);
    ['tfl-fy', 'tfl-limit'].forEach(id => $tfl(id).addEventListener('change', tfl_apply));

    // Picking a program from the list (or clearing the box) applies right away
    $tfl('tfl-program').addEventListener('change', tfl_apply);

    // Agency / Program: typing live-searches (debounced); picking an agency from the suggestions applies at once
    const search = $tfl('tfl-search'), clear = $tfl('tfl-search-clear');
    search.addEventListener('input', () => {
        clear.hidden = !search.value;
        clearTimeout(tfl.searchTimer);
        tfl.searchTimer = setTimeout(tfl_apply, 400);
    });
    clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; search.focus(); tfl_apply(); });
    tfl_nameCombo(search, () => tfl.agencies, () => { clear.hidden = false; clearTimeout(tfl.searchTimer); tfl_apply(); });
    tfl_nameCombo($tfl('tfl-xm-agency'), () => tfl.agencies, () => {});

    $tfl('tfl-view-close').addEventListener('click', () => tfl_closeOverlay('tfl-view-ov'));
    $tfl('tfl-view-done').addEventListener('click', () => tfl_closeOverlay('tfl-view-ov'));
    $tfl('tfl-view-edit').addEventListener('click', () => tfl_edit(tfl_viewId, tfl_viewType));

    $tfl('tfl-new-close').addEventListener('click', () => tfl_closeOverlay('tfl-new-ov'));
    $tfl('tfl-new-trust').addEventListener('click', () => tfl_newPick(TFL_TRUST));
    $tfl('tfl-new-or').addEventListener('click', () => tfl_newPick(TFL_OR));

    $tfl('tfl-xm-close').addEventListener('click', () => tfl_closeOverlay('tfl-xm-ov'));
    $tfl('tfl-xm-cancel').addEventListener('click', () => tfl_closeOverlay('tfl-xm-ov'));
    $tfl('tfl-xm-submit').addEventListener('click', tfl_exportSubmit);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { tfl_closeMenus(); ['tfl-view-ov', 'tfl-new-ov', 'tfl-xm-ov'].forEach(tfl_closeOverlay); }
    });
}

/* ── Bootstrap ──────────────────────────────────────────────── */
async function tfl_boot() {
    tfl_bind();
    tfl_renderHead();
    tfl_loadTable();
    tfl_loadCards();
    await Promise.all([tfl_loadYears(), tfl_loadPrograms(), tfl_loadAgencies()]);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', tfl_boot);
} else {
    tfl_boot();
}
</script>
