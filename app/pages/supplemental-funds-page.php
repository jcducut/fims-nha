<?php

namespace PHPMaker2024\FIMS;

/**
 * supplemental-funds-page.php — "Supplemental Fund Tracking": the list of every record of fims.fund_tracking whose
 * Nature of Fund is SUPPLEMENTAL (SRS 1.2.1 — Fund Sourcing Division, Financial Management Department).
 *
 * Built from saro-registry-page.php (same filters, summary cards, Amount Unit, Export, View Details and the same
 * "SARO processing form" modal), but locked to one nature of fund: every request carries nature_of_fund=SUPPLEMENTAL, so
 * nothing else ever shows here. A Supplemental record can be a SARO, an NCA or an OR, so Tracking Type is any of them.
 * It is a FLAT list — each Supplemental record is a row of its own, exactly once. (A tree like the SARO page's would
 * either list the non-Supplemental SARO a record hangs under as a row, or drop a Supplemental NCA whose SARO is not
 * Supplemental.) The record it is linked to by parent_id is shown as a tag under its reference number instead.
 * Data comes from the FIMS API only:
 *   /fund_tracking/list  /fund_tracking/read/{id}      (app/routes/fund_tracking.php — fims.fund_tracking)
 *   /fiscal_year/all  /fund-tracking-type/all  /program/all
 *
 * New Entry / Edit is the "SARO processing form" modal in form/saro-processing-form.php (included below):
 * window.sarModal.open(id?, { nature }). A new entry opens with Nature of Fund already set to SUPPLEMENTAL, so what is
 * entered here lands in this list. The form is used exactly as the SARO / NCA page uses it — the same input fields (Name,
 * Amount, Date, Unreleased, Bank Account No., documents …); SUPPLEMENTAL is just the nature of fund, so the form's own
 * Supplemental-only fields (Purpose, Supplemental Details) are switched off with $sarSupplementalFields = false below.
 * After a save the form calls window.sr_reload(), which this page defines.
 * Row actions are one "Actions" dropdown per row (View Details / Edit / Delete), the same control as budget-prop-page.php;
 * View Details opens a modal of this page, built from /fund_tracking/read/{id}; Delete asks first, then calls
 * DELETE /fund_tracking/delete/{id}.
 */
@session_start();
$_sr_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<script src="/_shared/js/searchable-select.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/js/searchable-select.js') ?>"></script>

<style>
    #sr-root { --sr-amt-scale: .85; }

    /* ── Summary cards: grow with the amount instead of clipping it ── */
    #sr-root .nha-summary { display: flex; flex-wrap: wrap; gap: 14px; }
    #sr-root .nha-summary > .nha-sc { flex: 1 1 210px; min-width: 210px; }
    #sr-root .nha-sc-val { white-space: nowrap; }
    #sr-root .sr-amt-val { zoom: var(--sr-amt-scale, 1); }

    #sr-root .sr-range { display: flex; align-items: center; gap: 6px; }
    #sr-root .sr-range .nha-filter-select { width: 100px; }
    #sr-root .sr-fy-group { flex: 0 0 auto; max-width: none; }   /* two selects need more than nha-filter-group-lg's 200px */
    /* Program: a search box like Reference No. — it keeps room for "All programs" next to its magnifier icon (the default 130px
       group minimum cut the text off), and has no × button, so the right padding the shared search style reserves for one goes */
    #sr-root .sr-program-group { flex: 1 1 190px; min-width: 190px; }
    #sr-root .sr-program-group .nha-filter-input { padding-right: 10px; }

    /* ── Table: one row per Supplemental record ── */
    #sr-root .sr-tbl { min-width: 1200px; }
    #sr-root .sr-tbl .sr-num { font-family: 'IBM Plex Mono', monospace; text-align: right; white-space: nowrap; }
    #sr-root .sr-amt { font-size: calc(1em * var(--sr-amt-scale, 1)); white-space: nowrap; }
    #sr-root .sr-empty { text-align: center; color: var(--afims-text-disabled, #A9AAB8); padding: 32px; font-size: 13px; }

    /* Each row sets --sr-row-bg; its cells paint it. The Actions cell is sticky (below), so it needs the same
       opaque colour as its row — a variable keeps that in one place for every row state. */
    #sr-root .sr-row { --sr-row-bg: var(--afims-surface, #FFFFFF); }
    #sr-root .sr-row > td { background: var(--sr-row-bg); }
    #sr-root .sr-row:hover { --sr-row-bg: var(--afims-surface-subtle, #F4F7FA); }
    #sr-root .sr-row > td:first-child { vertical-align: top; white-space: nowrap; }
    #sr-root .sr-row .sr-ref { font-size: 13px; font-weight: 700; }

    /* Actions stay reachable at the right edge while the wide table scrolls sideways */
    #sr-root .sr-tbl thead th:last-child { position: sticky; right: 0; z-index: 3; background: var(--afims-heading-fill, #000E3E); }
    #sr-root .sr-row > td:last-child { position: sticky; right: 0; z-index: 2; box-shadow: -10px 0 10px -10px rgba(15, 32, 69, .28); }

    /* ── Actions dropdown (the same menu as budget-prop-page.php / saro-registry-page.php) ──────────────────────────
       The menu is `position: fixed`, so the table's own overflow never clips it — but it sits inside the sticky Actions
       cell, which is a stacking context of its own. Every row's cell has the same z-index, so the cells of the rows BELOW
       would paint over the open menu: the cell that owns it is lifted above them while it is open. */
    #sr-root .nha-tools-menu { position: relative; display: inline-block; }
    #sr-root .nha-menu-trigger { display: inline-flex; align-items: center; gap: 5px; padding: 5px 10px; border-radius: 6px;
        font-size: 11px; font-weight: 600; cursor: pointer; border: 1px solid var(--afims-border, #E4ECF2);
        background: var(--afims-surface, #FFFFFF); color: var(--afims-text, #1A1B21); white-space: nowrap;
        font-family: 'IBM Plex Sans', sans-serif; transition: all .15s; }
    #sr-root .nha-menu-trigger:hover, #sr-root .nha-menu-trigger[aria-expanded="true"] { background: var(--afims-surface-subtle, #F4F7FA); border-color: var(--afims-text-disabled, #A9AAB8); }
    #sr-root .nha-menu-trigger svg { width: 12px; height: 12px; flex-shrink: 0; }
    #sr-root .nha-tools-dropdown { display: none; position: fixed; min-width: 170px; background: var(--afims-surface, #FFFFFF);
        border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
        z-index: 9999; padding: 4px 0; text-align: left; }
    #sr-root .nha-tools-dropdown.open { display: block; }
    #sr-root .nha-menu-item { display: flex; align-items: center; gap: 8px; padding: 8px 12px; font-size: 12px; font-weight: 500;
        color: var(--afims-text, #1A1B21); text-decoration: none; white-space: nowrap; cursor: pointer;
        border: none; background: none; width: 100%; text-align: left; transition: background .1s; font-family: 'IBM Plex Sans', sans-serif; }
    #sr-root .nha-menu-item:hover, #sr-root .nha-menu-item:focus-visible { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-heading-fill, #000E3E); outline: none; }
    #sr-root .nha-menu-item svg { width: 13px; height: 13px; flex-shrink: 0; opacity: .7; }
    #sr-root .nha-menu-divider { border-top: 1px solid var(--afims-border, #E4ECF2); margin: 3px 0; }
    #sr-root .nha-menu-item.danger { color: var(--afims-danger, #DC3545); }
    #sr-root .nha-menu-item.danger:hover, #sr-root .nha-menu-item.danger:focus-visible { background: var(--afims-danger-soft, #FAE1E3); color: var(--afims-danger, #DC3545); }
    #sr-root .sr-tbl .sr-row > td.sr-menu-open { z-index: 30; }

    /* what a record IS (its tracking type) and, when it has one, the record it is linked to */
    #sr-root .sr-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 5px; }
    #sr-root .sr-tag { display: inline-flex; align-items: center; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 20px; white-space: nowrap; }
    #sr-root .sr-tag-type { background: var(--afims-primary-text, #0035A9); color: var(--afims-surface, #FFFFFF); letter-spacing: .04em; }
    #sr-root .sr-tag-link { background: var(--afims-surface, #FFFFFF); color: var(--afims-success-hover, #0C7539); box-shadow: inset 0 0 0 1px color-mix(in srgb, var(--afims-success, #11A752) 45%, transparent); }

    /* ── Modals (shared .nha-overlay / .nha-modal) ── */
    #sr-root .sr-hint { display: block; margin-top: 4px; font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); }

    /* ── View Details modal — the look of the Budget Preview modal (readone/budget-preview-readone.php): the reference in the
       title, bordered info cards of label / value rows ── */
    #sr-root .srv-ref { margin-left: 8px; font-family: var(--mono); font-weight: 600; letter-spacing: .02em;
        color: color-mix(in srgb, var(--afims-primary, #0035A9) 40%, #FFFFFF); }
    #sr-root .srv-badge { margin-left: 12px; vertical-align: 1px; }
    #sr-root .srv-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 20px; }
    #sr-root .srv-stat { padding: 12px 16px; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-left: 3px solid var(--afims-primary, #0035A9); border-radius: 12px; }
    #sr-root .srv-stat-l { font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--sec); }
    #sr-root .srv-stat-v { margin-top: 5px; font: 700 17px/1.2 var(--mono); color: var(--afims-heading, #000E3E); white-space: nowrap; }
    #sr-root .srv-sec { margin-bottom: 20px; }
    #sr-root .srv-sec:last-child { margin-bottom: 0; }
    #sr-root .srv-sec-hd { margin: 0 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--sec); }
    #sr-root .srv-info { display: grid; gap: 9px; padding: 15px 18px; background: var(--surf); border: 1px solid var(--bdr); border-radius: 12px; }
    #sr-root .srv-row { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 12px; align-items: baseline; font-size: 13px; }
    #sr-root .srv-k { font-weight: 600; color: var(--sec); }
    #sr-root .srv-v { color: var(--txt); overflow-wrap: anywhere; }
    #sr-root .srv-chip { display: inline-block; margin-right: 4px; padding: 2px 7px; border-radius: 4px; vertical-align: 1px;
        font: 600 11px/1.4 var(--mono); color: var(--afims-primary-text, #0035A9); background: var(--afims-surface, #FFFFFF); border: 1px solid var(--navy-bdr, #E6EBF6); }
    #sr-root .srv-muted { color: var(--subtle); }
    #sr-root .srv-doc { display: flex; align-items: center; gap: 10px; padding: 9px 12px; margin-bottom: 6px; font-size: 13px;
        background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 10px; }
    #sr-root .srv-doc svg { width: 16px; height: 16px; flex-shrink: 0; color: var(--afims-primary, #0035A9); }
    #sr-root .srv-doc a, #sr-root .srv-doc span { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    /* the records linked to this one (its children), as a small table */
    #sr-root .srv-nca-wrap { overflow-x: auto; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 12px; }
    #sr-root .srv-nca { width: 100%; border-collapse: collapse; font-size: 12.5px; }
    #sr-root .srv-nca th { padding: 8px 12px; text-align: left; white-space: nowrap; font-size: 10.5px; font-weight: 700; letter-spacing: .05em;
        text-transform: uppercase; color: var(--sec); background: var(--surf); border-bottom: 1px solid var(--bdr); }
    #sr-root .srv-nca td { padding: 9px 12px; border-bottom: 1px solid var(--bdr); }
    #sr-root .srv-nca tr:last-child td { border-bottom: none; }
    #sr-root .srv-nca .r { text-align: right; white-space: nowrap; font-family: var(--mono); }
    @media (max-width: 640px) { #sr-root .srv-row { grid-template-columns: 1fr; gap: 2px; } }
</style>

<div id="sr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Supplemental Fund Tracking</div>
                <div class="nha-hdr-sub">Supplemental Budget Allocation — Fund Tracking &amp; Monitoring</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="sr-new" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);border:none;" title="New Supplemental Fund entry">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New Entry
            </button>
            <button id="sr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="sr-export-open" type="button" class="nha-btn-hdr" title="Export">
                <svg viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>
                Export
            </button>
            <span class="nha-hdr-upd" id="sr-updated">—</span>
        </div>
    </div>

    <!-- Error banner -->
    <div id="sr-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="sr-error-msg">Failed to load data. Please refresh.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group sr-fy-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <div class="sr-range">
                    <select id="sr-fy-from" class="nha-filter-select"><option value="">From</option></select>
                    <span class="nha-text-muted">–</span>
                    <select id="sr-fy-to" class="nha-filter-select"><option value="">To</option></select>
                </div>
            </div>
            <div class="nha-filter-group nha-filter-group-md">
                <label class="nha-filter-label">Tracking Type</label>
                <select id="sr-type" class="nha-filter-select"><option value="">All Types</option></select>
            </div>
            <div class="nha-filter-group nha-filter-group-lg sr-program-group">
                <label class="nha-filter-label">Program</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="sr-program" class="nha-filter-input" placeholder="All programs" autocomplete="off" title="Type a program code or name, then pick it from the list. Clear the box for all programs.">
                </div>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Reference No.</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    <input type="text" id="sr-search" class="nha-filter-input" placeholder="Filter by reference no…">
                    <button class="nha-filter-clear-btn" id="sr-search-clear" hidden type="button">×</button>
                </div>
            </div>
            <div class="nha-filter-group nha-filter-group-sm">
                <label class="nha-filter-label">Per Page</label>
                <select id="sr-limit" class="nha-filter-select">
                    <option value="10">10 / page</option>
                    <option value="25">25 / page</option>
                    <option value="50">50 / page</option>
                    <option value="100">100 / page</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-md">
                <label class="nha-filter-label">Amount Unit</label>
                <select id="sr-unit" class="nha-filter-select">
                    <option value="full">Centavos (Full Amount)</option>
                    <option value="thousand">Thousands (K)</option>
                    <option value="million">Millions (M)</option>
                    <option value="billion">Billions (B)</option>
                    <option value="trillion">Trillions (T)</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-actions">
                <button id="sr-apply" type="button" class="nha-btn nha-btn-primary nha-btn-sm">Apply</button>
                <button id="sr-reset" type="button" class="nha-btn nha-btn-ghost nha-btn-sm">Reset</button>
            </div>
        </div>
    </div>

    <!-- ── SUMMARY CARDS ──────────────────────────────────── -->
    <div class="nha-summary" id="sr-cards">
        <div class="nha-sc nha-sc-navy"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Records</div><div class="nha-sc-val">—</div></div></div><p class="nha-kpi-sub">Supplemental entries</p></div>
        <div class="nha-sc nha-sc-blue"><div class="nha-sc-top"><div><div class="nha-sc-label">Total Amount</div><div class="nha-sc-val">—</div></div></div><p class="nha-kpi-sub">Supplemental entries</p></div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl sr-tbl">
                <thead>
                    <tr>
                        <th style="min-width:210px;">Reference No.</th>
                        <th style="min-width:170px;">Name / Title</th>
                        <th style="min-width:190px;">Program / Sub-Program</th>
                        <th>Nature of Fund</th>
                        <th>Fiscal Year</th>
                        <th style="text-align:right;">Amount (₱)</th>
                        <th>Date</th>
                        <th style="text-align:right;">Unreleased (₱)</th>
                        <th class="no-print" style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody id="sr-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap no-print">
            <span class="nha-pag-info">Showing <strong><span id="sr-from">0</span>–<span id="sr-to">0</span></strong> of <strong><span id="sr-total">0</span></strong> records</span>
            <div id="sr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- ══════════════ VIEW MODAL ══════════════ -->
    <div id="sr-view-ov" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-lg">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="sr-view-title">Supplemental Fund Record</h3>
                <button type="button" class="nha-modal-close" id="sr-view-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="sr-view-body"></div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="sr-view-done">Close</button>
                <button type="button" class="nha-btn nha-btn-primary" id="sr-view-edit">Edit</button>
            </div>
        </div>
    </div>

    <?php
    // New Entry / Edit is the "SARO processing form", a modal in its own file. It opens with window.sarModal.open(id?, { nature })
    // and calls window.sr_reload() (defined below) after a save. A Supplemental record can be a SARO, an NCA or an OR, so all
    // three are offered (a Trust Fund has its own page). $sarSupplementalFields = false: the same input fields as the SARO / NCA
    // form, with no Purpose and no Supplemental Details section.
    $sarAllowedTypes = ['SARO', 'NCA', 'OR'];
    $sarSupplementalFields = false;
    include __DIR__ . '/form/saro-processing-form.php';
    ?>

    <!-- ══════════════ EXPORT MODAL ══════════════ -->
    <div id="sr-xm-ov" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-sm">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title">Export Supplemental Fund Tracking</h3>
                <button type="button" class="nha-modal-close" id="sr-xm-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body">
                <p class="sr-hint" style="margin:0 0 14px;">All matching records are exported, not just the current page.</p>
                <div class="nha-fg">
                    <label>Fiscal Year</label>
                    <div class="sr-range">
                        <select id="sr-xm-fy-from"><option value="">From</option></select>
                        <span class="nha-text-muted">–</span>
                        <select id="sr-xm-fy-to"><option value="">To</option></select>
                    </div>
                </div>
                <div class="nha-fg">
                    <label for="sr-xm-type">Tracking Type</label>
                    <select id="sr-xm-type"><option value="">All Types</option></select>
                </div>
                <div class="nha-fg">
                    <label for="sr-xm-program">Program</label>
                    <input type="text" id="sr-xm-program" placeholder="All programs" autocomplete="off">
                </div>
                <div class="nha-fg">
                    <label for="sr-xm-unit">Amount Unit</label>
                    <select id="sr-xm-unit">
                        <option value="full">Centavos (Full Amount)</option>
                        <option value="thousand">Thousands (K)</option>
                        <option value="million">Millions (M)</option>
                        <option value="billion">Billions (B)</option>
                        <option value="trillion">Trillions (T)</option>
                    </select>
                </div>
                <div class="nha-fg" style="margin-bottom:0;">
                    <label for="sr-xm-format">Export As</label>
                    <select id="sr-xm-format">
                        <option value="csv">Excel-compatible (.csv)</option>
                        <option value="pdf">PDF (opens the print dialog — Save as PDF)</option>
                    </select>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="sr-xm-cancel">Cancel</button>
                <button type="button" class="nha-btn nha-btn-primary" id="sr-xm-submit">Export</button>
            </div>
        </div>
    </div>

</div><!-- /#sr-root -->

<script>
/* ═══════════════════════════════════════════════════════════════
   SUPPLEMENTAL FUND TRACKING   prefix: sr_
   SRS 1.2.1 | Fund Sourcing Division | API-driven
═══════════════════════════════════════════════════════════════ */

const SR_API = <?= json_encode($_sr_api) ?>;
const SR_U = {
    list:     SR_API + '/fund_tracking/list',
    read:     id => SR_API + '/fund_tracking/read/' + encodeURIComponent(id),
    del:      id => SR_API + '/fund_tracking/delete/' + encodeURIComponent(id),
    years:    SR_API + '/fiscal_year/all',
    types:    SR_API + '/fund-tracking-type/all',
    programs: SR_API + '/program/all',
};

// This page is dedicated to Supplemental Funds — every list request is locked to this nature of fund (any tracking type).
const SR_NATURE = 'SUPPLEMENTAL';

// The tracking types a Supplemental record can have (Trust Fund has its own page).
const SR_ALLOWED_TYPES = ['SARO', 'NCA', 'OR'];

const $sr = id => document.getElementById(id);
const sr_esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

/* ── State ──────────────────────────────────────────────────── */
const sr = {
    fyFrom: '', fyTo: '', type: '', program: '', search: '',
    page: 1, limit: 10,
    total: 0,                 // records matching the filters
    rows: [],                 // this page's records
    loaded: false, error: null,
    req: 0,                   // bumped per request so a stale response never overwrites a newer one
    cardsReq: 0,
    cards: null,              // last summary, kept so an Amount Unit change needn't refetch
    unit: 'full',
    searchTimer: null,
    programs: [],             // [{value: program_code, label: program_name}]
    programCodes: new Set(),
};

/* ── API helpers ────────────────────────────────────────────── */
async function sr_api(url, opts = {}) {
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

function sr_toast(msg, type = 'info') {
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
    t.innerHTML = `<div style="flex:1"><span>${sr_esc(msg)}</span></div><button type="button" style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    t.querySelector('button').addEventListener('click', () => t.remove());
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4500);
}

function sr_showError(msg) { $sr('sr-error').style.display = 'flex'; $sr('sr-error-msg').textContent = msg; }
function sr_hideError() { $sr('sr-error').style.display = 'none'; }
function sr_setUpdated() {
    $sr('sr-updated').textContent = 'Updated: ' + new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit' });
}

/* ── Amount Unit — display-scale for every peso amount on the page (cards + table).
      A pure display preference: it never changes what is fetched, so switching it
      re-renders from the data already loaded. ───────────────── */
const SR_UNITS = {
    full:     { divisor: 1,             suffix: '',  label: 'Centavos (Full Amount)', scale: 0.85 },
    thousand: { divisor: 1000,          suffix: 'K', label: 'Thousands (K)',          scale: 0.9 },
    million:  { divisor: 1000000,       suffix: 'M', label: 'Millions (M)' },
    billion:  { divisor: 1000000000,    suffix: 'B', label: 'Billions (B)' },
    trillion: { divisor: 1000000000000, suffix: 'T', label: 'Trillions (T)' },
};

// A scaled value under 1 (e.g. ₱2,000,000 shown in Billions = 0.002) would round to "0.00" and look
// like an actually-zero amount — widen the fraction digits just enough to show two significant digits.
function sr_decimals(scaled) {
    const abs = Math.abs(scaled);
    if (abs === 0 || abs >= 1) return 2;
    return Math.min(8, Math.max(2, Math.ceil(-Math.log10(abs)) + 1));
}

function sr_peso(v, unitKey = sr.unit) {
    const unit = SR_UNITS[unitKey] || SR_UNITS.full;
    const scaled = (parseFloat(v) || 0) / unit.divisor;
    return '₱ ' + scaled.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: sr_decimals(scaled) }) + unit.suffix;
}

function sr_pesoFull(v) {
    return '₱ ' + (parseFloat(v) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function sr_setUnit(key) {
    if (!SR_UNITS[key]) return;
    sr.unit = key;
    $sr('sr-root').style.setProperty('--sr-amt-scale', SR_UNITS[key].scale || 1);
    sr_renderCards(sr.cards);
    sr_renderTable();
}

function sr_fmtDate(s) {
    if (!s) return '—';
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(s));
    const d = m ? new Date(+m[1], +m[2] - 1, +m[3]) : new Date(s);
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}

/* ── Record accessors (fims.fund_tracking row shape) ─────────── */
const sr_kids = r => (Array.isArray(r.children) ? r.children : []);
const sr_typeCode = r => String(r.tracking_type?.fund_tracking_code || '').trim().toUpperCase();
const sr_parentOf = r => (Array.isArray(r.parent) && r.parent.length ? r.parent[0] : null);
const sr_sum = list => list.reduce((s, c) => s + (parseFloat(c.amount) || 0), 0);

/* ── Requests ────────────────────────────────────────────────────
   Every list request carries nature_of_fund=SUPPLEMENTAL, so only Supplemental records ever come back — the table, the
   summary cards and the export all go through here. No `type` unless a Tracking Type is picked, no roots_only: it is a
   flat list, each Supplemental record once. Search matches the reference number. */
function sr_listParams(f = sr) {
    const p = new URLSearchParams({ nature_of_fund: SR_NATURE });
    if (f.type) p.set('type', f.type);
    if (f.search) p.set('search', f.search);
    if (f.fyFrom) p.set('fiscal_year_from', f.fyFrom);
    if (f.fyTo) p.set('fiscal_year_to', f.fyTo);
    if (f.program) p.set('program_code', f.program);
    return p;
}

// Walks /fund_tracking/list page by page (no row cap) and returns every matching record
async function sr_fetchAll(f) {
    const PAGE = 500;
    const rows = [];
    let page = 1, last = 1;
    do {
        const p = sr_listParams(f);
        p.set('page', page);
        p.set('limit', PAGE);
        const json = await sr_api(SR_U.list + '?' + p);
        rows.push(...(json.data || []));
        last = json.pagination?.last_page ?? 1;
        page++;
    } while (page <= last);
    return rows;
}

/* ── Summary cards ───────────────────────────────────────────────
   Counted from EVERY Supplemental record matching the Fiscal Year range and the Program filter (all pages, not just the
   table page on screen). Tracking Type and the reference search narrow the table only. */
function sr_renderCards(c) {
    c = c || { total: 0, amount: 0 };
    const card = (cls, label, valueHtml, sub) =>
        `<div class="nha-sc ${cls}"><div class="nha-sc-top"><div><div class="nha-sc-label">${label}</div>${valueHtml}</div></div>${sub}</div>`;
    const sub = text => `<p class="nha-kpi-sub">${text}</p>`;

    $sr('sr-cards').innerHTML =
        card('nha-sc-navy', 'Total Records', `<div class="nha-sc-val">${Number(c.total || 0).toLocaleString()}</div>`, sub('Supplemental entries')) +
        card('nha-sc-blue', 'Total Amount', `<div class="nha-sc-val sr-amt-val" title="${sr_esc(sr_pesoFull(c.amount))}">${sr_esc(sr_peso(c.amount))}</div>`, sub('Supplemental entries'));
}

async function sr_loadCards() {
    const token = ++sr.cardsReq;
    try {
        const rows = await sr_fetchAll({ fyFrom: sr.fyFrom, fyTo: sr.fyTo, program: sr.program });
        if (token !== sr.cardsReq) return;                // superseded by a newer request
        sr.cards = { total: rows.length, amount: sr_sum(rows) };
        sr_renderCards(sr.cards);
        sr_setUpdated();
    } catch (e) {
        if (token !== sr.cardsReq) return;
        console.warn('[sr_loadCards]', e);
    }
}

/* ── Filter dropdowns ───────────────────────────────────────── */
async function sr_loadYears() {
    try {
        const json = await sr_api(SR_U.years);
        const years = [...new Set((json.data || []).map(r => Number(r.fiscal_yr)).filter(Boolean))].sort((a, b) => a - b);
        const opts = years.map(y => `<option value="${y}">${y}</option>`).join('');
        $sr('sr-fy-from').insertAdjacentHTML('beforeend', opts);
        $sr('sr-fy-to').insertAdjacentHTML('beforeend', opts);
    } catch (e) { console.warn('[sr_loadYears]', e); }
}

async function sr_loadTypes() {
    try {
        const json = await sr_api(SR_U.types);
        const codes = [...new Set((json.data || [])
            .filter(t => !t.deleted_at && SR_ALLOWED_TYPES.includes(String(t.fund_tracking_code || '').toUpperCase()))
            .map(t => String(t.fund_tracking_code).toUpperCase()))];
        $sr('sr-type').insertAdjacentHTML('beforeend', codes.map(c => `<option value="${sr_esc(c)}">${sr_esc(c)}</option>`).join(''));
    } catch (e) { console.warn('[sr_loadTypes]', e); }
}

async function sr_loadPrograms() {
    try {
        const json = await sr_api(SR_U.programs);
        sr.programs = (json.data || []).map(p => ({ value: String(p.program_code), label: String(p.program_name ?? '') }));
        sr.programCodes = new Set(sr.programs.map(p => p.value));
        nhaSearchableSelect($sr('sr-program'), sr.programs);
        nhaSearchableSelect($sr('sr-xm-program'), sr.programs);
    } catch (e) { console.warn('[sr_loadPrograms]', e); }
}

/* The Program input shows the code of the program picked from the list; anything typed that isn't a
   real program code is not a filter, so it is dropped. */
function sr_programValue(inputId) {
    const v = $sr(inputId).value.trim();
    return sr.programCodes.has(v) ? v : '';
}

/* ── Cells ───────────────────────────────────────────────────── */
const sr_dash = '<span class="nha-text-muted">—</span>';

function sr_programCell(r) {
    const sp = r.sub_program;
    if (!sp) return sr_dash;
    return `<div style="font-size:12px;line-height:1.35;">
        <div style="font-weight:500;">${sr_esc(sp.sub_program_name)}</div>
        <div class="nha-mono" style="font-size:10px;color:var(--afims-text-disabled, #A9AAB8);">${sr_esc(sp.sub_program_code)}${sp.program_name ? ' · ' + sr_esc(sp.program_name) : ''}</div>
    </div>`;
}

const sr_natureCell = r => (r.nature_of_fund ? `<span class="nha-bdg nha-bdg-navy">${sr_esc(r.nature_of_fund.name)}</span>` : sr_dash);
const sr_yearCell = r => (r.fiscal_year ? `<span style="font-size:11px;">FY ${sr_esc(r.fiscal_year)}</span>` : sr_dash);
// The Name the form collects. A record saved before this form took a Name may only have a Purpose, so that is the fallback.
const sr_nameCell = r => {
    const t = (r.name && String(r.name).trim()) || (r.purpose && String(r.purpose).trim()) || '';
    return t ? `<span style="font-size:12px;">${sr_esc(t)}</span>` : sr_dash;
};

function sr_amountCell(v) {
    const val = parseFloat(v) || 0;
    return val ? `<span class="sr-amt" title="${sr_esc(sr_pesoFull(val))}">${sr_esc(sr_peso(val))}</span>` : '—';
}

function sr_unreleasedCell(v) {
    const val = parseFloat(v) || 0;
    return val
        ? `<span class="nha-bdg nha-bdg-red" title="${sr_esc(sr_pesoFull(val))}" style="font-size:calc(11px * var(--sr-amt-scale, 1));">${sr_esc(sr_peso(val))}</span>`
        : '—';
}

const sr_refLink = r =>
    `<a href="#" class="nha-tbl-link nha-mono sr-ref" onclick="event.preventDefault();sr_view(${Number(r.id)})">${sr_esc(r.reference_number || '—')}</a>`;

// What a record IS (SARO / NCA / OR) and, when it has one, the record it is linked to — e.g. "SARO SARO-26-001"
function sr_refTags(r) {
    const type = sr_typeCode(r);
    const p = sr_parentOf(r);
    return (type ? `<span class="sr-tag sr-tag-type">${sr_esc(type)}</span>` : '')
        + (p ? `<span class="sr-tag sr-tag-link" title="The record this one is linked to">${sr_esc(sr_typeCode(p) || 'Parent')} ${sr_esc(p.reference_number || '#' + p.id)}</span>` : '');
}

/* ── Table ───────────────────────────────────────────────────── */
const SR_VIEW_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>';
const SR_EDIT_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';
const SR_DELETE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>';
const SR_MENU_ICON = '<svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>';

// One "Actions" button per row that opens a menu — the same control as budget-prop-page.php (View Details / Edit, then
// Delete under a divider).
const sr_toolsCell = r => {
    const id = Number(r.id);
    return `
    <td class="no-print" style="text-align:center;">
        <div class="nha-tools-menu">
            <button type="button" class="nha-menu-trigger" aria-haspopup="menu" aria-expanded="false" onclick="sr_toggleMenu(this)">${SR_MENU_ICON}Actions</button>
            <div class="nha-tools-dropdown" role="menu">
                <button type="button" role="menuitem" class="nha-menu-item" onclick="sr_closeMenus();sr_view(${id})">${SR_VIEW_ICON}View Details</button>
                <button type="button" role="menuitem" class="nha-menu-item" onclick="sr_closeMenus();sr_edit(${id})">${SR_EDIT_ICON}Edit</button>
                <div class="nha-menu-divider" role="separator"></div>
                <button type="button" role="menuitem" class="nha-menu-item danger" onclick="sr_closeMenus();sr_delete(${id})">${SR_DELETE_ICON}Delete</button>
            </div>
        </div>
    </td>`;
};

/* ── Actions dropdown — open / close ───────────────────────────────
   Positioned with fixed coordinates from the button (so the table's own scrolling never clips it); it opens upwards
   instead when there is no room below (the last rows of a page). Only one is open at a time. It closes on a click
   elsewhere, Escape, scrolling and resizing. */
function sr_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const wasOpen = drop.classList.contains('open');
    sr_closeMenus();
    if (wasOpen) return;

    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
    btn.closest('td').classList.add('sr-menu-open');   // lifts the sticky cell above the rows below (see the CSS)
    const rect = btn.getBoundingClientRect(), h = drop.offsetHeight;
    const fitsBelow = rect.bottom + 4 + h <= window.innerHeight - 8;
    drop.style.top = (fitsBelow ? rect.bottom + 4 : Math.max(8, rect.top - 4 - h)) + 'px';
    drop.style.right = (document.documentElement.clientWidth - rect.right) + 'px';
}

function sr_closeMenus() {
    document.querySelectorAll('#sr-root .nha-tools-dropdown.open').forEach(drop => {
        drop.classList.remove('open');
        const menu = drop.closest('.nha-tools-menu');
        menu.querySelector('.nha-menu-trigger').setAttribute('aria-expanded', 'false');
        menu.closest('td').classList.remove('sr-menu-open');
    });
}

document.addEventListener('mousedown', e => { if (!e.target.closest('.nha-tools-menu')) sr_closeMenus(); });
document.addEventListener('scroll', sr_closeMenus, true);
window.addEventListener('resize', sr_closeMenus);

function sr_row(r) {
    return `
    <tr class="sr-row">
        <td>
            ${sr_refLink(r)}
            <div class="sr-tags">${sr_refTags(r)}</div>
        </td>
        <td>${sr_nameCell(r)}</td>
        <td>${sr_programCell(r)}</td>
        <td>${sr_natureCell(r)}</td>
        <td>${sr_yearCell(r)}</td>
        <td class="sr-num">${sr_amountCell(r.amount)}</td>
        <td class="nha-td-date" style="white-space:nowrap;">${sr_esc(sr_fmtDate(r.date))}</td>
        <td class="sr-num">${sr_unreleasedCell(r.unreleased)}</td>
        ${sr_toolsCell(r)}
    </tr>`;
}

function sr_renderTable() {
    const body = $sr('sr-tbody');

    if (sr.error) {
        body.innerHTML = `<tr><td colspan="9" class="sr-empty">Could not load records: ${sr_esc(sr.error)}</td></tr>`;
    } else if (!sr.loaded) {
        body.innerHTML = `<tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>`;
    } else if (!sr.rows.length) {
        body.innerHTML = `<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No Supplemental Fund records found.</span></div></td></tr>`;
    } else {
        body.innerHTML = sr.rows.map(sr_row).join('');
    }
    sr_renderPager();
}

/* ── Pagination ─────────────────────────────────────────────── */
function sr_renderPager() {
    const total = sr.loaded && !sr.error ? sr.total : 0;
    const from = total ? (sr.page - 1) * sr.limit + 1 : 0;
    const to = total ? Math.min(from + sr.rows.length - 1, total) : 0;
    $sr('sr-from').textContent = from;
    $sr('sr-to').textContent = to;
    $sr('sr-total').textContent = total.toLocaleString();

    const box = $sr('sr-pagination');
    const last = Math.max(1, Math.ceil(total / sr.limit));
    if (last <= 1) { box.innerHTML = ''; return; }

    const cur = sr.page;
    const nums = [...new Set([1, last, cur - 1, cur, cur + 1])].filter(p => p >= 1 && p <= last).sort((a, b) => a - b);
    let html = `<button type="button" class="nha-pag-btn${cur <= 1 ? ' disabled' : ''}" onclick="sr_goTo(${cur - 1})">‹</button>`;
    nums.forEach((p, i) => {
        if (i > 0 && p - nums[i - 1] > 1) html += '<span class="nha-pag-ellipsis">…</span>';
        html += `<button type="button" class="nha-pag-btn${p === cur ? ' active' : ''}" onclick="sr_goTo(${p})">${p}</button>`;
    });
    html += `<button type="button" class="nha-pag-btn${cur >= last ? ' disabled' : ''}" onclick="sr_goTo(${cur + 1})">›</button>`;
    box.innerHTML = html;
}

function sr_goTo(page) {
    const last = Math.max(1, Math.ceil(sr.total / sr.limit));
    if (page < 1 || page > last || page === sr.page) return;
    sr.page = page;
    sr_loadTable();
}

/* ── Load ───────────────────────────────────────────────────── */
async function sr_loadTable() {
    const token = ++sr.req;
    try {
        const p = sr_listParams();
        p.set('page', sr.page);
        p.set('limit', sr.limit);
        const json = await sr_api(SR_U.list + '?' + p);
        if (token !== sr.req) return;                    // superseded by a newer request
        sr.rows = json.data || [];
        sr.total = json.pagination?.total ?? sr.rows.length;
        sr.loaded = true;
        sr.error = null;
        sr_hideError();
        sr_setUpdated();
    } catch (e) {
        if (token !== sr.req) return;
        sr.error = e.message;
        sr_showError(e.message);
    }
    sr_renderTable();
}

function sr_reloadTable() { sr.page = 1; return sr_loadTable(); }
function sr_reload() { return Promise.all([sr_loadCards(), sr_loadTable()]); }

/* ── Apply / reset filters ──────────────────────────────────── */
function sr_apply() {
    // Auto-swap From/To if entered backwards
    let from = $sr('sr-fy-from').value, to = $sr('sr-fy-to').value;
    if (from && to && parseInt(from) > parseInt(to)) {
        [from, to] = [to, from];
        $sr('sr-fy-from').value = from;
        $sr('sr-fy-to').value = to;
    }
    sr.fyFrom = from;
    sr.fyTo = to;
    sr.type = $sr('sr-type').value;
    sr.program = sr_programValue('sr-program');
    if (!sr.program) $sr('sr-program').value = '';
    sr.search = $sr('sr-search').value.trim();
    sr.limit = parseInt($sr('sr-limit').value) || 10;
    sr_reloadTable();
    sr_loadCards();
}

function sr_resetFilters() {
    ['sr-fy-from', 'sr-fy-to', 'sr-type', 'sr-program', 'sr-search'].forEach(id => { $sr(id).value = ''; });
    $sr('sr-search-clear').hidden = true;
    $sr('sr-limit').value = '10';
    sr_apply();
}

/* ── View modal ─────────────────────────────────────────────── */
let sr_viewId = null;

function sr_openOverlay(id) { $sr(id).classList.add('open'); }
function sr_closeOverlay(id) { $sr(id).classList.remove('open'); }

const SR_DOC_ICON = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';

const sr_vrow = (label, valueHtml) => `<div class="srv-row"><div class="srv-k">${label}</div><div class="srv-v">${valueHtml}</div></div>`;
const sr_vsec = (title, innerHtml) => `<div class="srv-sec"><h4 class="srv-sec-hd">${title}</h4>${innerHtml}</div>`;
const sr_vstat = (label, v) => `<div class="srv-stat"><div class="srv-stat-l">${label}</div><div class="srv-stat-v">${sr_esc(sr_pesoFull(v))}</div></div>`;

async function sr_view(id) {
    window.PagePreloader?.show();
    try {
        const d = (await sr_api(SR_U.read(id))).data;
        sr_viewId = Number(d.id);
        const type = sr_typeCode(d);
        const sp = d.sub_program;
        const parent = sr_parentOf(d);
        const kids = sr_kids(d);
        const supp = d.supplemental;
        const has = v => v !== null && v !== undefined && String(v).trim() !== '';
        const text = v => (has(v) ? sr_esc(v) : sr_dash);
        const date = v => (has(v) ? sr_esc(sr_fmtDate(v)) : sr_dash);
        const money = v => (parseFloat(v) ? sr_esc(sr_pesoFull(v)) : sr_dash);
        const chip = code => `<span class="srv-chip">${sr_esc(code)}</span>`;

        // title: "Reference No. <ref>" and the tracking type as a badge
        $sr('sr-view-title').innerHTML = `Reference No. <span class="srv-ref">${sr_esc(d.reference_number || '#' + d.id)}</span>`
            + (type ? `<span class="srv-badge nha-bdg nha-bdg-navy">${sr_esc(type)}</span>` : '');

        // Amount and Unreleased as on the SARO / NCA page; a record that already has Supplemental Details (saved before this
        // form dropped them) also shows its Budgetary Requirement
        let html = `<div class="srv-stats">${sr_vstat('Amount', d.amount)}${sr_vstat('Unreleased', d.unreleased)}${supp ? sr_vstat('Budgetary Requirement', supp.budgetary_requirement) : ''}</div>`;

        const classification = [
            sr_vrow('Tracking Type', d.tracking_type ? `${sr_esc(d.tracking_type.fund_tracking_code)} <span class="srv-muted">— ${sr_esc(d.tracking_type.fund_tracking_name || '')}</span>` : sr_dash),
            sr_vrow('Nature of Fund', d.nature_of_fund ? sr_esc(d.nature_of_fund.name) : sr_dash),
            sr_vrow('Fiscal Year', d.fiscal_year ? 'FY ' + sr_esc(d.fiscal_year) : sr_dash),
            sr_vrow('Program', sp && sp.program_code ? `${chip(sp.program_code)}${sr_esc(sp.program_name || '')}` : sr_dash),
            sr_vrow('Sub-Program', sp ? `${chip(sp.sub_program_code)}${sr_esc(sp.sub_program_name)}` : sr_dash),
        ];
        if (parent) {
            classification.push(sr_vrow('Parent Fund', `<a href="#" class="nha-tbl-link nha-mono" onclick="event.preventDefault();sr_view(${Number(parent.id)})">${sr_esc(parent.reference_number || '#' + parent.id)}</a> `
                + `<span class="srv-muted">${sr_esc(parent.tracking_type?.fund_tracking_code || '')}</span>`));
        }
        html += sr_vsec('Classification', `<div class="srv-info">${classification.join('')}</div>`);

        const info = [sr_vrow('Date', date(d.date))];
        if (has(d.name)) info.unshift(sr_vrow('Name / Title', sr_esc(d.name)));
        if (has(d.purpose)) info.push(sr_vrow('Purpose', sr_esc(d.purpose)));
        if (has(d.bank_account_no)) info.push(sr_vrow('Bank Account No.', sr_esc(d.bank_account_no)));
        if (d.agency && has(d.agency.name)) info.push(sr_vrow('Agency', sr_esc(d.agency.name)));
        if (has(d.remarks)) info.push(sr_vrow('Remarks', sr_esc(d.remarks)));
        html += sr_vsec('Fund Information', `<div class="srv-info">${info.join('')}</div>`);

        if (supp) {
            html += sr_vsec('Supplemental Details', `<div class="srv-info">${[
                sr_vrow('Region', text(supp.regional_office)),
                sr_vrow('Budgetary Requirement', money(supp.budgetary_requirement)),
                sr_vrow('Date Received', date(supp.date_received)),
                sr_vrow('Date Forwarded to NDRRMC', date(supp.date_forwarded_ndrrmc)),
                sr_vrow('No. of Days with FMD', text(supp.days_fmd)),
                sr_vrow('Date Forwarded to OP', date(supp.date_forwarded_op)),
                sr_vrow('No. of Days with NDRRMC', text(supp.days_ndrrmc)),
                sr_vrow('Date of OP Approval', date(supp.date_approved_op)),
                sr_vrow('No. of Days with OP', text(supp.days_op)),
                sr_vrow('Total No. of Days', text(supp.days_total)),
            ].join('')}</div>`);
        }

        // the records linked to this one (their parent_id is this record)
        if (kids.length) {
            const rows = kids.map(c => `<tr>
                <td><a href="#" class="nha-tbl-link nha-mono" onclick="event.preventDefault();sr_view(${Number(c.id)})">${sr_esc(c.reference_number || '#' + c.id)}</a></td>
                <td>${sr_esc(sr_typeCode(c) || '—')}</td>
                <td>${c.fiscal_year ? 'FY ' + sr_esc(c.fiscal_year) : sr_dash}</td>
                <td class="r">${sr_esc(sr_pesoFull(c.amount))}</td>
                <td style="white-space:nowrap;">${sr_esc(sr_fmtDate(c.date))}</td>
            </tr>`).join('');
            html += sr_vsec(`Linked Records (${kids.length})`, `<div class="srv-nca-wrap"><table class="srv-nca">
                <thead><tr><th>Reference No.</th><th>Type</th><th>Fiscal Year</th><th class="r">Amount</th><th>Date</th></tr></thead>
                <tbody>${rows}</tbody></table></div>`);
        }

        const files = (d.file_attachments || []).filter(a => !a.deleted_at && a.file_meta && !a.file_meta.deleted_at);
        if (files.length) {
            html += sr_vsec('Supporting Documents', files.map(f => {
                const meta = f.file_meta || {};
                const url = meta.view_url || (/^https?:\/\//i.test(meta.link || '') ? meta.link : '');
                const name = sr_esc(meta.document_name || 'Document');
                return `<div class="srv-doc">${SR_DOC_ICON}${url ? `<a class="nha-tbl-link" href="${sr_esc(url)}" target="_blank" rel="noopener">${name}</a>` : `<span>${name}</span>`}</div>`;
            }).join(''));
        }

        $sr('sr-view-body').innerHTML = html;
        $sr('sr-view-body').scrollTop = 0;
        sr_openOverlay('sr-view-ov');
    } catch (e) {
        sr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── New / Edit ─────────────────────────────────────────────────
   The form is the modal in form/saro-processing-form.php (included above): window.sarModal.open(null, { nature }) for
   a new entry — which opens with Nature of Fund already set to SUPPLEMENTAL — and window.sarModal.open(id) to edit
   (the record keeps its own nature of fund). After a save the form calls window.sr_reload() — that name is fixed by
   the form — so the cards and the table refresh, staying on the current page. */
window.sr_reload = sr_reload;

async function sr_openForm(id = null) {
    if (!window.sarModal) { sr_toast('The entry form could not be loaded. Please refresh the page.', 'error'); return; }
    try {
        await window.sarModal.open(id, { nature: SR_NATURE });
    } catch (e) {
        console.error('[sr_openForm]', e);
        sr_toast('Could not open the entry form: ' + e.message, 'error');
    }
}

const sr_edit = id => { sr_closeOverlay('sr-view-ov'); return sr_openForm(id); };

/* ── Delete ─────────────────────────────────────────────────────
   DELETE /fund_tracking/delete/{id} — a soft delete (the API stamps deleted_at). The API refuses with a 409 and says why
   while other records still have this one as their parent (e.g. a SARO that has NCAs under it); that message is shown as it
   comes. On success the cards and table refresh, staying on the current page — or stepping back one page when the deleted
   record was the only row on a page that is not the first. */
async function sr_delete(id) {
    id = Number(id);
    const rec = sr.rows.find(r => Number(r.id) === id);
    const ref = (rec && rec.reference_number) || '#' + id;
    if (!confirm(`Delete Supplemental Fund record ${ref}? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        await sr_api(SR_U.del(id), { method: 'DELETE' });
        sr_toast(`Record ${ref} deleted.`, 'success');
        if (sr.rows.length === 1 && sr.page > 1) sr.page--;
        await sr_reload();
    } catch (e) {
        sr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Export ──────────────────────────────────────────────────────
   Has its own Fiscal Year / Tracking Type / Program / Amount Unit, independent of the Filters bar and of the table's
   paging — it walks /fund_tracking/list page by page and includes EVERY matching Supplemental record, once each, as a
   flat list (the record it is linked to is in the Parent Reference No. column). Excel-compatible CSV, or a print-ready
   table for "Save as PDF" (no spreadsheet / PDF library is installed in this app). */
function sr_exportOpen() {
    [['sr-xm-fy-from', 'sr-fy-from'], ['sr-xm-fy-to', 'sr-fy-to'], ['sr-xm-type', 'sr-type']].forEach(([to, from]) => {
        $sr(to).innerHTML = $sr(from).innerHTML;
        $sr(to).value = '';
    });
    $sr('sr-xm-program').value = '';
    $sr('sr-xm-unit').value = sr.unit;                   // default to the page's current unit
    $sr('sr-xm-format').value = 'csv';
    sr_openOverlay('sr-xm-ov');
}

const SR_EXPORT_HEAD = ['Reference No.', 'Name / Title', 'Program', 'Sub-Program', 'Tracking Type', 'Nature of Fund', 'Fiscal Year', 'Amount', 'Date', 'Unreleased', 'Parent Reference No.'];
const SR_EXPORT_AMOUNT_COLS = [7, 9];

// One flat line per record — the table's columns. Amount / Unreleased are divided by the chosen unit.
function sr_exportRows(records, unitKey) {
    const unit = SR_UNITS[unitKey] || SR_UNITS.full;
    const scale = v => { const s = (parseFloat(v) || 0) / unit.divisor; return Number(s.toFixed(sr_decimals(s))); };
    return records.map(r => {
        const sp = r.sub_program;
        const parent = sr_parentOf(r);
        return [
            r.reference_number || '',
            (r.name || r.purpose || '').trim(),
            sp && sp.program_code ? [sp.program_code, sp.program_name].filter(Boolean).join(' - ') : '',
            sp ? [sp.sub_program_code, sp.sub_program_name].filter(Boolean).join(' - ') : '',
            sr_typeCode(r),
            r.nature_of_fund?.name || '',
            r.fiscal_year ? 'FY ' + r.fiscal_year : '',
            scale(r.amount),
            r.date ? sr_fmtDate(r.date) : '',
            scale(r.unreleased),
            parent ? (parent.reference_number || '') : '',
        ];
    });
}

function sr_download(filename, mime, content) {
    const url = URL.createObjectURL(new Blob([content], { type: mime }));
    const a = Object.assign(document.createElement('a'), { href: url, download: filename });
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function sr_exportHead(unitKey) {
    const unit = SR_UNITS[unitKey] || SR_UNITS.full;
    const suffix = unit.suffix ? ', in ' + unit.label.replace(/\s*\(.*\)/, '') : '';
    return SR_EXPORT_HEAD.map((h, i) => (SR_EXPORT_AMOUNT_COLS.includes(i) ? `${h} (PHP${suffix})` : h));
}

function sr_exportCsv(data, unitKey) {
    // a text cell that starts with = + - @ would be run as a formula by Excel — a leading ' makes it plain text
    const cell = v => `"${(typeof v === 'string' && /^[=+\-@\t\r]/.test(v) ? "'" + v : String(v ?? '')).replace(/"/g, '""')}"`;
    const lines = [sr_exportHead(unitKey).map(cell).join(',')].concat(data.map(r => r.map(cell).join(',')));
    // BOM so Excel opens it as UTF-8
    sr_download('Supplemental_Fund_Tracking_' + new Date().toISOString().slice(0, 10) + '.csv', 'text/csv;charset=utf-8', String.fromCharCode(0xFEFF) + lines.join('\r\n'));
}

function sr_exportPdf(data, unitKey, subtitle) {
    const head = sr_exportHead(unitKey).map((h, i) => `<th${SR_EXPORT_AMOUNT_COLS.includes(i) ? ' class="r"' : ''}>${sr_esc(h)}</th>`).join('');
    const body = data.map(r => '<tr>' + r.map((v, i) => SR_EXPORT_AMOUNT_COLS.includes(i)
        ? `<td class="r">${sr_esc(Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: sr_decimals(Number(v)) }))}</td>`
        : `<td>${sr_esc(v)}</td>`).join('') + '</tr>').join('');

    const html = `<!doctype html><html><head><meta charset="utf-8"><title>Supplemental Fund Tracking</title><style>
        body{font:9px 'IBM Plex Sans',Arial,sans-serif;color:#1A1B21;margin:0}
        h1{font-size:15px;margin:0 0 2px;color:#000E3E} p{margin:0 0 10px;color:#62657E;font-size:10px}
        table{border-collapse:collapse;width:100%} th{background:#000E3E;color:#fff;text-align:left;padding:5px 6px;font-size:8.5px}
        td{padding:4px 6px;border-bottom:1px solid #E4ECF2;vertical-align:top} th.r,td.r{text-align:right;white-space:nowrap}
        tr{page-break-inside:avoid} thead{display:table-header-group} @page{size:A4 landscape;margin:10mm}
    </style></head><body><h1>Supplemental Fund Tracking — National Housing Authority</h1><p>${sr_esc(subtitle)}</p>
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

async function sr_exportSubmit() {
    const format = $sr('sr-xm-format').value === 'pdf' ? 'pdf' : 'csv';
    const unitKey = $sr('sr-xm-unit').value;
    let from = $sr('sr-xm-fy-from').value, to = $sr('sr-xm-fy-to').value;
    if (from && to && parseInt(from) > parseInt(to)) [from, to] = [to, from];
    const f = { fyFrom: from, fyTo: to, type: $sr('sr-xm-type').value, program: sr_programValue('sr-xm-program') };

    const parts = [];
    if (from || to) parts.push('Fiscal Year: ' + (from || 'Start') + ' – ' + (to || 'Latest'));
    parts.push('Tracking Type: ' + (f.type || 'All'));
    if (f.program) parts.push('Program: ' + f.program);
    const subtitle = parts.join('  |  ');

    const btn = $sr('sr-xm-submit');
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Exporting…';
    try {
        const records = await sr_fetchAll(f);
        if (!records.length) { sr_toast('No Supplemental Fund records match the selected filters.', 'warning'); return; }
        const data = sr_exportRows(records, unitKey);
        format === 'pdf' ? sr_exportPdf(data, unitKey, subtitle) : sr_exportCsv(data, unitKey);
        sr_closeOverlay('sr-xm-ov');
    } catch (e) {
        console.error('[sr_exportSubmit]', e);
        sr_toast('Export failed: ' + e.message, 'error');
    } finally {
        btn.disabled = false;
        btn.textContent = label;
    }
}

/* ── Events ─────────────────────────────────────────────────── */
function sr_bind() {
    $sr('sr-refresh').addEventListener('click', async () => {
        const btn = $sr('sr-refresh');
        btn.classList.add('loading');
        try { await sr_reload(); } finally { btn.classList.remove('loading'); }
    });
    $sr('sr-new').addEventListener('click', () => sr_openForm(null));
    $sr('sr-export-open').addEventListener('click', sr_exportOpen);

    $sr('sr-apply').addEventListener('click', sr_apply);
    $sr('sr-reset').addEventListener('click', sr_resetFilters);
    ['sr-fy-from', 'sr-fy-to', 'sr-type', 'sr-limit'].forEach(id => $sr(id).addEventListener('change', sr_apply));
    $sr('sr-unit').addEventListener('change', e => sr_setUnit(e.target.value));

    // Picking a program from the list (or clearing the box) applies right away
    $sr('sr-program').addEventListener('change', sr_apply);

    const search = $sr('sr-search'), clear = $sr('sr-search-clear');
    search.addEventListener('input', () => {
        clear.hidden = !search.value;
        clearTimeout(sr.searchTimer);
        sr.searchTimer = setTimeout(sr_apply, 400);
    });
    clear.addEventListener('click', () => { search.value = ''; clear.hidden = true; search.focus(); sr_apply(); });

    $sr('sr-view-close').addEventListener('click', () => sr_closeOverlay('sr-view-ov'));
    $sr('sr-view-done').addEventListener('click', () => sr_closeOverlay('sr-view-ov'));
    $sr('sr-view-edit').addEventListener('click', () => sr_edit(sr_viewId));

    $sr('sr-xm-close').addEventListener('click', () => sr_closeOverlay('sr-xm-ov'));
    $sr('sr-xm-cancel').addEventListener('click', () => sr_closeOverlay('sr-xm-ov'));
    $sr('sr-xm-submit').addEventListener('click', sr_exportSubmit);

    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') { sr_closeMenus(); ['sr-view-ov', 'sr-xm-ov'].forEach(sr_closeOverlay); }
    });
}

/* ── Bootstrap ──────────────────────────────────────────────── */
async function sr_boot() {
    sr_bind();
    sr_loadTable();
    sr_loadCards();
    await Promise.all([sr_loadYears(), sr_loadTypes(), sr_loadPrograms()]);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sr_boot);
} else {
    sr_boot();
}
</script>
