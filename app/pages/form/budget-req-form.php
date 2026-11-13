<?php
/**
 * budget-req-form.php
 *
 * Add-a-Program / Edit-a-Program modal, opened from two places:
 *   - app/pages/budget-req-page.php's "+ Add Budget Request" button ('add' mode)
 *   - a Program row's "Edit" action, or the Edit button inside the Program
 *     View modal (readone/budget-req-readone.php) ('edit-program' mode)
 *
 * fims.budget_req is a four-level structure, one request per fiscal year:
 *   fims.budget_req          — header: Fiscal Year, Status (Draft -> For Review)
 *   fims.budget_req_program  — one "Program block" per program added to the
 *                              request (repeatable across saves — each save
 *                              of THIS form adds/edits exactly one of these)
 *   fims.budget_req_project  — one "Project line item" per project added to
 *                              a Program block (repeatable "+ Add Project"
 *                              blocks within this form)
 *   fims.budget_line_items   — one "Chart of Accounts line item" per account
 *                              allocation under a project block (repeatable
 *                              "+ Add Line Item" rows nested inside each block)
 *
 * Trigger:
 *   bgr_openModal('add')                    — add a Program to the request for
 *                                              the chosen fiscal year (creates
 *                                              that request if none exists yet,
 *                                              or appends to it if it's still Draft)
 *   bgr_openModal('edit-program', program)  — edit one existing Program's own
 *                                              projects/line items; `program`
 *                                              must include budget_req_program_id,
 *                                              fiscal_yr, program_code,
 *                                              sub_program_code, projects[]
 *
 * After a successful save this calls window.bgr_afterSave?.(mode, json) —
 * each host page defines that hook to its own refresh logic, keeping this
 * partial decoupled from page-specific behavior.
 *
 * Self-contained styling (scoped under #bgr-card) AND self-contained JS
 * (this file loads its own reference data and binds its own modal events)
 * so it renders and works correctly regardless of which host page includes
 * it or whether that page's own script has run yet.
 */
$_bgr_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<!-- ══════════════════════════════════════════════════════════
     BUDGET REQUEST FORM MODAL   JS namespace: bgr_
══════════════════════════════════════════════════════════ -->
<style>
    #bgr-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #bgr-overlay.open { display:flex; }
    #bgr-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:1440px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:bgrIn .2s ease; }
    @keyframes bgrIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #bgr-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #bgr-header-info { display:flex;align-items:center;gap:10px; }
    #bgr-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .bgr-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .bgr-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #bgr-modal-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #bgr-modal-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #bgr-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 35%, transparent);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    /* #bgr-body/#bgr-footer live inside this <form>, not directly inside
       #bgr-card — a plain <form> isn't a flex container by default, so
       without this, their flex:1/flex-shrink:0 rules below do nothing: the
       form just grows to fit all its content and gets silently clipped by
       #bgr-card's overflow:hidden, with no scrollbar ever appearing. This
       makes the form itself the flex item (filling #bgr-card's remaining
       space) and a flex container in turn for its own children. */
    #bgr-form { flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden; }

    #bgr-body { flex:1;min-height:0;overflow-y:auto;padding:22px; }
    .bgr-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    .bgr-group { display:flex;flex-direction:column;gap:5px; }
    .bgr-group.full { margin-bottom:16px; }
    .bgr-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .bgr-label .req { color:var(--afims-danger, #DC3545); }
    .bgr-input, .bgr-select, .bgr-textarea { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .bgr-textarea { resize:vertical;min-height:64px; }
    .bgr-input:focus, .bgr-select:focus, .bgr-textarea:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .bgr-select:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .bgr-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 16px; }

    .bgr-amt-wrap { display:flex;align-items:stretch;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgr-amt-wrap:focus-within { border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .bgr-amt-prefix { display:flex;align-items:center;padding:0 12px;background:var(--afims-surface-subtle, #F4F7FA);
        color:var(--afims-text-disabled, #A9AAB8);font-weight:700;font-size:13px;border-right:1px solid var(--afims-border, #E4ECF2); }
    .bgr-amt-wrap .bgr-input { border:none;border-radius:0;text-align:right;font-family:'IBM Plex Mono',monospace; }
    .bgr-amt-wrap .bgr-input:focus { box-shadow:none; }

    .bgr-sec-hd { font-size:12px;font-weight:700;color:var(--afims-heading-fill, #000E3E);
        text-transform:uppercase;letter-spacing:.05em;margin:4px 0 10px;padding-bottom:7px;
        border-bottom:2px solid var(--afims-border, #E4ECF2); }

    /* ── Project blocks ───────────────────────────────────────────────── */
    #bgr-blocks-wrap { display:flex;flex-direction:column;gap:16px;margin-bottom:12px; }
    .bgr-block { border:1px solid color-mix(in srgb, var(--afims-border, #E4ECF2) 55%, var(--afims-text-disabled, #A9AAB8));
        border-radius:10px;padding:14px;background:var(--afims-surface-subtle, #F4F7FA);
        box-shadow:0 1px 3px rgba(15,32,69,.08);transition:box-shadow .15s,border-color .15s; }
    .bgr-block:hover { border-color:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 30%, var(--afims-border, #E4ECF2));
        box-shadow:0 2px 10px rgba(15,32,69,.12); }
    .bgr-block-hd { display:flex;align-items:flex-start;gap:10px;padding-bottom:14px;margin-bottom:14px;
        border-bottom:1px solid color-mix(in srgb, var(--afims-border, #E4ECF2) 55%, var(--afims-text-disabled, #A9AAB8)); }
    .bgr-block.bgr-collapsed .bgr-block-hd { padding-bottom:0;margin-bottom:0;border-bottom:none; }
    .bgr-block-project-wrap { flex:1;min-width:0; }
    .bgr-block-project-code-wrap { flex:0 0 260px;min-width:0; }
    .bgr-block-project-code-display { font-family:'IBM Plex Mono',monospace;background:var(--afims-surface, #FFFFFF);
        border-style:dashed;color:var(--afims-text-muted, #62657E);cursor:default; }
    /* Sum of every project block's own Total row (.bgr-block-li-total-amt) —
       see bgr_updateGrandTotal(), kept live on every amount/row/project change. */
    #bgr-grand-total-display { font-family:'IBM Plex Mono',monospace;font-weight:700;text-align:right;
        background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E);cursor:default; }
    .bgr-block-location-wrap { flex:0 0 240px;min-width:0; }
    .bgr-block-toggle { flex-shrink:0;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-heading-fill, #000E3E);
        cursor:pointer;width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        margin-top:20px;transition:transform .15s,background .15s; }
    .bgr-block-toggle:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    .bgr-block.bgr-collapsed .bgr-block-toggle { transform:rotate(-90deg); }
    .bgr-block-body { display:flex;flex-direction:column;gap:12px; }
    .bgr-block.bgr-collapsed .bgr-block-body { display:none; }
    .bgr-block-remove { flex-shrink:0;border:1px solid var(--afims-danger-soft, #FAE1E3);
        background:var(--afims-danger-soft, #FAE1E3);color:var(--afims-danger, #DC3545);
        font-size:24px;line-height:1;cursor:pointer;width:42px;height:42px;border-radius:8px;margin-top:20px;
        transition:background .15s,color .15s; }
    .bgr-block-remove:hover { background:var(--afims-danger, #DC3545);color:var(--afims-pure-white, #FFFFFF); }

    /* ── Line items table (nested per block) ──────────────────────────── */
    /* .bgr-block-li-outer holds two siblings: the scrollable table
       (.bgr-block-li-wrap) and a fixed remove-button column
       (.bgr-block-li-remove-col) that is NOT part of the scroll area, so
       it's always visible without scrolling right. Row heights are kept
       in sync via matching explicit heights below — AND, critically,
       .bgr-block-li-remove-col gets a bottom padding set by JS
       (bgr_syncRemoveColGap) equal to whatever horizontal scrollbar height
       .bgr-block-li-wrap is actually showing. That scrollbar reserves
       space at the bottom of .bgr-block-li-wrap that
       .bgr-block-li-remove-col has no equivalent for, and that was the
       real cause of the two columns drifting out of alignment last time —
       not the row heights themselves. */
    .bgr-block-li-outer { display:flex;align-items:stretch;
        border:1px solid color-mix(in srgb, var(--afims-border, #E4ECF2) 55%, var(--afims-text-disabled, #A9AAB8));
        border-radius:10px;overflow:hidden;background:var(--afims-surface, #FFFFFF); }
    .bgr-block-li-tbl td { background:var(--afims-surface, #FFFFFF); }
    .bgr-block-li-wrap { overflow-x:auto;flex:1;min-width:0; }
    .bgr-block-li-tbl { width:100%;border-collapse:collapse;table-layout:fixed; }
    .bgr-block-li-tbl th { height:34px;box-sizing:border-box;font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:0 6px;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-block-li-tbl td { height:44px;box-sizing:border-box;padding:6px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:middle;
        max-width:0;overflow:hidden; } /* max-width:0 + table-layout:fixed forces the cell (and its input) to the column's declared %, instead of growing to fit the input's own browser-default intrinsic width */
    .bgr-li-input, .bgr-li-select { width:100%;min-width:0;padding:7px 9px;border:1px solid var(--afims-border, #E4ECF2);
        border-radius:6px;font-size:12px;font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);box-sizing:border-box; }
    .bgr-li-input:focus, .bgr-li-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E); }
    .bgr-li-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);
        text-overflow:ellipsis; }
    /* Starts at the same compact height as the other row inputs; drag the
       handle to grow it for longer text. Table row height isn't forced to
       keep up (td has no max-height), so it just grows with it normally —
       except the separate .bgr-li-remove-cell sibling (a plain div, not a
       real table row, used for the split remove-button column) stays fixed
       at 44px and won't follow a resized row. Minor known mismatch, not
       worth a per-row ResizeObserver for. */
    textarea.bgr-li-particulars { resize:vertical;min-height:30px;max-height:200px;line-height:1.3;font-family:inherit; }
    .bgr-li-amt { text-align:right;font-family:'IBM Plex Mono',monospace; }

    .bgr-block-li-total-row td { height:38px;box-sizing:border-box;padding:6px 6px 6px 9px;border-bottom:none;
        background:var(--afims-surface-subtle, #F4F7FA);font-size:12px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .bgr-block-li-total-amt { text-align:right;font-family:'IBM Plex Mono',monospace; }
    /* Lines up with .bgr-block-li-total-row in the split remove-button
       column — that row has no remove button of its own (it's computed),
       but without a matching placeholder here the column's flex-stretch
       (.bgr-block-li-outer) just leaves bare empty space over it instead. */
    .bgr-li-remove-foot { height:38px;box-sizing:border-box;background:var(--afims-surface-subtle, #F4F7FA); }

    .bgr-block-li-remove-col { flex-shrink:0;width:42px;display:flex;flex-direction:column;
        border-left:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF); }
    .bgr-li-remove-head { height:34px;box-sizing:border-box;background:var(--afims-surface-subtle, #F4F7FA);
        border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove-cell { height:44px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove { border:none;background:transparent;color:var(--afims-danger, #DC3545);
        font-size:18px;line-height:1;cursor:pointer;width:26px;height:26px;border-radius:6px; }
    .bgr-li-remove:hover { background:var(--afims-danger-soft, #FAE1E3); }
    .bgr-block-add-li { margin-top:12px; }

    /* ── Per-project Supporting Documents ──────────────────────────────── */
    .bgr-block-docs { margin-top:4px;padding-top:14px;border-top:1px solid var(--afims-border, #E4ECF2); }
    .bgr-block-docs-title { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em;margin:0 0 4px; }
    .bgr-block-docs-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 10px; }
    .bgr-block-doc-tbl { width:100%;border-collapse:collapse;margin-bottom:10px; }
    .bgr-block-doc-tbl th { height:30px;box-sizing:border-box;font-size:10px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:0 6px;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-block-doc-tbl td { padding:6px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:middle; }
    .bgr-doc-panel { display:flex;align-items:center;gap:6px;min-width:0; }
    .bgr-doc-upload-btn { display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;
        color:var(--afims-text, #1A1B21);background:var(--afims-surface-subtle, #F4F7FA);
        border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;padding:6px 10px;
        cursor:pointer;white-space:nowrap;flex-shrink:0; }
    .bgr-doc-upload-btn:hover { background:var(--afims-surface, #FFFFFF); }
    .bgr-doc-uploaded-name { font-size:11px;color:var(--afims-text-muted, #62657E);flex:1;min-width:0;
        overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .bgr-doc-open { font-size:11.5px;color:var(--afims-heading-fill, #000E3E);text-decoration:none;
        white-space:nowrap;flex-shrink:0;font-weight:600; }
    .bgr-doc-remove { border:none;background:transparent;color:var(--afims-danger, #DC3545);
        font-size:16px;line-height:1;cursor:pointer;width:24px;height:24px;border-radius:6px;flex-shrink:0; }
    .bgr-doc-remove:hover { background:var(--afims-danger-soft, #FAE1E3); }
    .bgr-doc-row-error { font-size:11px;color:var(--afims-danger, #DC3545);margin-top:4px; }

    #bgr-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #bgr-footer .bgr-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #bgr-footer .bgr-btn:disabled { opacity:.5;cursor:not-allowed; }
    #bgr-footer .bgr-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #bgr-footer .bgr-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #bgr-footer .bgr-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #bgr-footer .bgr-btn-navy:hover:not(:disabled) { opacity:.92; }

    .bgr-btn-ghost { height:34px;padding:0 16px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);font-size:12px;font-weight:600;
        font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    .bgr-btn-ghost:hover { background:var(--afims-surface-subtle, #F4F7FA); }

    /* ── Project typeahead (twitter typeahead.js) ──────────────────────── */
    /* The library wraps the input in a plain inline-block span by default —
       force it to behave like every other full-width field in this form. */
    .bgr-block-project-wrap .twitter-typeahead { display:block !important;width:100%; }
    .tt-menu { width:100%;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);
        border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14);margin-top:4px;z-index:10000;
        max-height:260px;overflow-y:auto;overflow-x:hidden; }
    .tt-suggestion { padding:8px 12px;font-size:13px;color:var(--afims-text, #1A1B21);cursor:pointer;transition:background .1s,color .1s; }
    .tt-suggestion:hover, .tt-suggestion.tt-cursor { background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    .tt-not-found { padding:8px 12px;font-size:12px;color:var(--afims-text-disabled, #A9AAB8);font-style:italic; }

    /* ── Account Code picker (plain custom dropdown, not typeahead.js) ───
       The Account Code field sits inside .bgr-block-li-wrap (overflow-x:auto,
       which per spec forces its overflow-y to clip too) and .bgr-block-li-outer
       (overflow:hidden, for rounded corners) — both would clip a normal
       position:absolute dropdown no matter its z-index. A third-party
       library fighting that clipping by forcing position:fixed caused a
       real race (it kept re-applying its own absolute-positioned top/left
       after ours), so this field uses no library at all: bgr_initAccountPicker
       builds and positions this dropdown itself, with nothing else able to
       stomp on it. */
    .bgr-acct-menu { display:none;position:fixed;background:var(--afims-surface, #FFFFFF);
        border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14);
        z-index:10000;max-height:260px;overflow-y:auto;overflow-x:hidden; }
    .bgr-acct-menu.open { display:block; }
    .bgr-acct-option { padding:8px 12px;font-size:13px;color:var(--afims-text, #1A1B21);cursor:pointer;transition:background .1s,color .1s; }
    .bgr-acct-option:hover, .bgr-acct-option.active { background:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    .bgr-acct-empty { padding:8px 12px;font-size:12px;color:var(--afims-text-disabled, #A9AAB8);font-style:italic; }

    @media (max-width: 520px) { .bgr-row { grid-template-columns:1fr; } }
</style>

<div id="bgr-overlay">
    <div id="bgr-card">

        <div id="bgr-header">
            <div id="bgr-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
                <div>
                    <p class="bgr-hdr-tag">Budget Requirement</p>
                    <p class="bgr-hdr-title" id="bgr-modal-title">Add Budget Request</p>
                </div>
            </div>
            <button type="button" id="bgr-modal-close">&times;</button>
        </div>

        <div id="bgr-err"></div>

        <form id="bgr-form">
            <div id="bgr-body">
                <input type="hidden" id="bgr-mode" value="add">
                <input type="hidden" id="bgr-id" value="">

                <div class="bgr-row">
                    <div class="bgr-group">
                        <label class="bgr-label">Fiscal Year <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-fiscal-yr" required>
                            <option value="">— Select fiscal year —</option>
                        </select>
                    </div>
                    <div class="bgr-group">
                        <label class="bgr-label">Program <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-program-code" required>
                            <option value="">— Select program —</option>
                        </select>
                    </div>
                </div>
                <div class="bgr-row">
                    <div class="bgr-group">
                        <label class="bgr-label">Sub-Program</label>
                        <select class="bgr-select" id="bgr-sub-program-code">
                            <option value="">— None —</option>
                        </select>
                    </div>
                    <div class="bgr-group">
                        <label class="bgr-label">Grand Total</label>
                        <input type="text" class="bgr-input" id="bgr-grand-total-display" readonly value="0.00">
                    </div>
                </div>
                <p class="bgr-hint" id="bgr-program-context" style="display:none;"></p>

                <p class="bgr-sec-hd">Projects</p>
                <div id="bgr-blocks-wrap"></div>
                <button type="button" id="bgr-add-project-block" class="bgr-btn-ghost">+ Add Project</button>
            </div>
            <div id="bgr-footer">
                <button type="button" class="bgr-btn bgr-btn-ghost" id="bgr-cancel">Cancel</button>
                <button type="submit" class="bgr-btn bgr-btn-navy" id="bgr-save">Save Request</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REQUEST FORM MODAL   prefix: bgr_
   Self-contained: loads its own reference data and binds its own
   events, so it works regardless of which page includes it.
═══════════════════════════════════════════════════════════════ */

const BGR_API                   = '<?php echo $_bgr_api; ?>' + '/budget_req';
const BGR_PROGRAM_API           = '<?php echo $_bgr_api; ?>' + '/program/all';
const BGR_PROJECT_BASE_API      = '<?php echo $_bgr_api; ?>' + '/project';
const BGR_FISCAL_YEAR_API       = '<?php echo $_bgr_api; ?>' + '/fiscal_year';
const BGR_SUB_PROGRAM_API       = '<?php echo $_bgr_api; ?>' + '/sub_program/all';
const BGR_CHART_OF_ACCOUNTS_API = '<?php echo $_bgr_api; ?>' + '/chart_of_accounts/all';
const BGR_ATTACHMENT_API        = '<?php echo $_bgr_api; ?>' + '/budget_req_project_attachment';

// Cached option lists — Program/Sub-Program are prefetched in full and
// filtered client-side (same cascade style as pof_fillProjects() in
// app/pages/po/form.php); Chart-of-Accounts options are prefetched once per
// line-item row; Project is searched live via typeahead instead (see
// bgr_initProjectTypeahead), since the list can be large and is also creatable.
let BGR_PROGRAMS = [];
let BGR_SUB_PROGRAMS = [];
let BGR_COA_OPTIONS = [];
let bgr_liSeq = 0;
let bgr_blockSeq = 0;

const $bgr = id => document.getElementById(id);

// bgr_loadChartOfAccounts is NOT included here — it's only needed once the
// modal actually opens (bgr_openModal awaits it there), so it's not fetched
// at all on page load unless the user opens the form.
async function bgr_formBootstrap() {
    await Promise.all([bgr_loadPrograms(), bgr_loadSubPrograms(), bgr_loadFiscalYears()]);
    bgr_bindFormEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_formBootstrap);
} else {
    bgr_formBootstrap();
}

function bgr_bindFormEvents() {
    $bgr('bgr-modal-close')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-cancel')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-form')?.addEventListener('submit', bgr_save);

    $bgr('bgr-program-code')?.addEventListener('change', bgr_onProgramChange);
    $bgr('bgr-sub-program-code')?.addEventListener('change', bgr_refreshAllProjectCodeFields);
    $bgr('bgr-fiscal-yr')?.addEventListener('change', bgr_refreshAllProjectCodeFields);
    $bgr('bgr-add-project-block')?.addEventListener('click', () => bgr_addProjectBlock());

    // A window resize can change whether a block's line-items table needs a
    // horizontal scrollbar at all, which changes how much gap its remove
    // column needs.
    window.addEventListener('resize', () => {
        if ($bgr('bgr-overlay')?.classList.contains('open')) {
            document.querySelectorAll('#bgr-blocks-wrap .bgr-block').forEach(bgr_syncRemoveColGap);
        }
    });
}

async function bgr_loadPrograms() {
    try {
        const res  = await fetch(BGR_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load programs.');
        BGR_PROGRAMS = json.data ?? [];

        const optsHtml = BGR_PROGRAMS.map(p =>
            `<option value="${bgr_escHtmlAttr(p.program_code)}">${bgr_esc(p.program_code)} — ${bgr_esc(p.program_name)}</option>`
        ).join('');
        $bgr('bgr-program-code').insertAdjacentHTML('beforeend', optsHtml);
        $bgr('bgr-filter-program')?.insertAdjacentHTML('beforeend', optsHtml);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

async function bgr_loadSubPrograms() {
    try {
        const res  = await fetch(BGR_SUB_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load sub-programs.');
        BGR_SUB_PROGRAMS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Program -> Sub-Program cascade, same pattern as pof_fillProjects()/
// pof_onProgramChange() in app/pages/po/form.php: the full list is already
// cached, so this just filters it client-side into the dependent select.
function bgr_fillSubPrograms(programCode, keep = '') {
    const list = programCode ? BGR_SUB_PROGRAMS.filter(sp => sp.program_code === programCode) : [];
    $bgr('bgr-sub-program-code').innerHTML = '<option value="">— None —</option>' + list.map(sp =>
        `<option value="${bgr_escHtmlAttr(sp.sub_program_code)}">${bgr_esc(sp.sub_program_code)} — ${bgr_esc(sp.sub_program_name)}</option>`
    ).join('');
    $bgr('bgr-sub-program-code').value = list.some(sp => sp.sub_program_code === keep) ? keep : '';
}
function bgr_onProgramChange() {
    bgr_fillSubPrograms($bgr('bgr-program-code').value);
    // bgr_fillSubPrograms sets #bgr-sub-program-code's value programmatically
    // (rebuilt <option> list), which doesn't fire that select's own 'change'
    // listener — so this has to be called explicitly here too.
    bgr_refreshAllProjectCodeFields();
}

async function bgr_loadFiscalYears() {
    try {
        const res  = await fetch(`${BGR_FISCAL_YEAR_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');

        const years = json.data ?? [];
        const optsHtml = years.map(fy =>
            `<option value="${bgr_escAttr(fy.fiscal_yr)}">${bgr_esc(fy.fiscal_yr)}</option>`
        ).join('');
        $bgr('bgr-fiscal-yr').insertAdjacentHTML('beforeend', optsHtml);

        // The filter already has the current year as a static, pre-selected
        // option (see budget-req-page.php) -- skip it here so the dropdown
        // doesn't end up with two identical entries.
        const currentYear = String(new Date().getFullYear());
        const filterOptsHtml = years
            .filter(fy => String(fy.fiscal_yr) !== currentYear)
            .map(fy => `<option value="${bgr_escAttr(fy.fiscal_yr)}">${bgr_esc(fy.fiscal_yr)}</option>`)
            .join('');
        $bgr('bgr-filter-fiscal-yr')?.insertAdjacentHTML('beforeend', filterOptsHtml);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// All active, postable, for-budget-proposal chart-of-accounts entries — no
// more expense-class-driven prefix filtering; every such account is
// selectable regardless of PS/MOOE/CO branch. Called when the modal opens
// (bgr_openModal), not at page load — this form's own fields are the only
// thing that needs it.
async function bgr_loadChartOfAccounts() {
    try {
        const res  = await fetch(`${BGR_CHART_OF_ACCOUNTS_API}?postable_only=1&for_budget_proposal=1`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load chart of accounts.');
        BGR_COA_OPTIONS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
        BGR_COA_OPTIONS = [];
    }
}

function bgr_accountLabel(a) {
    return `${a.account_code} — ${a.account_title}`;
}

/* ── Project blocks — one per "Project line item" (fims.budget_req_project),
   each holding its own repeatable Chart-of-Accounts line items table ──── */
function bgr_addProjectBlock(prefillBlock = null) {
    bgr_blockSeq++;
    const block = document.createElement('div');
    block.className = 'bgr-block';
    block.dataset.blockSeq = bgr_blockSeq;
    block.innerHTML = `
        <div class="bgr-block-hd">
            <button type="button" class="bgr-block-toggle" title="Collapse">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div class="bgr-block-project-wrap">
                <label class="bgr-label">Project <span class="req">*</span></label>
                <input type="text" class="bgr-input bgr-block-project-name" placeholder="Type to search or add a new project…" autocomplete="off">
                <input type="hidden" class="bgr-block-project-code" value="">
            </div>
            <div class="bgr-block-project-code-wrap">
                <label class="bgr-label">Project Code</label>
                <input type="text" class="bgr-input bgr-block-project-code-display" readonly placeholder="Select a program first">
            </div>
            <div class="bgr-block-location-wrap">
                <label class="bgr-label">Location</label>
                <input type="text" class="bgr-input bgr-block-location" placeholder="e.g. Brgy. San Jose, Quezon City" maxlength="255">
            </div>
            <button type="button" class="bgr-block-remove" title="Remove project">&times;</button>
        </div>
        <div class="bgr-block-body">
            <div class="bgr-block-li-outer">
                <div class="bgr-block-li-wrap">
                    <table class="bgr-block-li-tbl">
                        <thead><tr>
                            <th style="width:36%;">Account Code - Account Name</th>
                            <th style="width:44%;">Particulars</th>
                            <th style="width:19%;">Proposed Amount</th>
                        </tr></thead>
                        <tbody class="bgr-block-li-tbody"></tbody>
                        <tfoot><tr class="bgr-block-li-total-row">
                            <td colspan="2">Total</td>
                            <td class="bgr-block-li-total-amt">0.00</td>
                        </tr></tfoot>
                    </table>
                </div>
                <div class="bgr-block-li-remove-col">
                    <div class="bgr-li-remove-head"></div>
                    <div class="bgr-li-remove-foot"></div>
                </div>
            </div>
            <button type="button" class="bgr-block-add-li bgr-btn-ghost">+ Add Line Item</button>

            <div class="bgr-block-docs">
                <p class="bgr-block-docs-title">Supporting Documents</p>
                <p class="bgr-block-docs-hint">Upload a file (≤20 MB — PDF, Word, Excel, or image) or paste a link per document.</p>
                <table class="bgr-block-doc-tbl" style="display:none;">
                    <thead><tr>
                        <th style="width:35%;">Document Name</th>
                        <th>Attachment</th>
                        <th style="width:26px;"></th>
                    </tr></thead>
                    <tbody class="bgr-block-doc-tbody"></tbody>
                </table>
                <div class="bgr-block-doc-actions">
                    <button type="button" class="bgr-block-doc-add-upload bgr-btn-ghost">+ Upload File</button>
                    <button type="button" class="bgr-block-doc-add-link bgr-btn-ghost">+ Paste Link</button>
                </div>
            </div>
        </div>
    `;
    $bgr('bgr-blocks-wrap').appendChild(block);

    const $nameInput = $(block.querySelector('.bgr-block-project-name'));
    bgr_initProjectTypeahead($nameInput);
    if (prefillBlock && prefillBlock.project_code) {
        const label = `${prefillBlock.project_code} — ${prefillBlock.project_name || ''}`;
        $nameInput.val(label).data('bgr-matched-text', label);
        bgr_setProjectCode($nameInput, prefillBlock.project_code);
    } else {
        // A brand-new block with nothing picked yet already WOULD create a
        // new project if saved as-is — preview its code immediately, rather
        // than waiting for the user to type something first (the code never
        // depends on the typed name anyway, only on the header's Program/
        // Sub-Program).
        bgr_updateProjectCodeField($nameInput);
    }
    if (prefillBlock && prefillBlock.location) {
        block.querySelector('.bgr-block-location').value = prefillBlock.location;
    }

    block.querySelector('.bgr-block-remove').addEventListener('click', () => bgr_removeProjectBlock(block));
    block.querySelector('.bgr-block-add-li').addEventListener('click', () => bgr_addLineItemRow(block, {}));
    block.querySelector('.bgr-block-toggle').addEventListener('click', () => bgr_toggleProjectBlock(block));

    const items = (prefillBlock && prefillBlock.line_items && prefillBlock.line_items.length) ? prefillBlock.line_items : [{}];
    items.forEach(li => bgr_addLineItemRow(block, li));

    bgr_wireDocSection(block, prefillBlock);

    return block;
}

/* ── Supporting Documents — per project block, staged client-side until the
   whole request is saved (see bgr_save()'s FormData path), except an
   already-saved attachment's own delete, which is immediate (a real,
   independent server action, not part of the "nothing persists until Save"
   staging the rest of this form uses). ──────────────────────────────────── */
const BGR_DOC_MAX_BYTES = 20 * 1024 * 1024; // 20 MB — keep in sync with BudgetReqService::ATTACHMENT_MAX_BYTES
const BGR_DOC_ALLOWED_EXT = new Set(['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif']);
let bgr_docRowSeq = 0;

function bgr_wireDocSection(block, prefillBlock) {
    block.querySelector('.bgr-block-doc-add-upload').addEventListener('click', () => bgr_addDocRow(block, { mode: 'upload' }));
    block.querySelector('.bgr-block-doc-add-link').addEventListener('click', () => bgr_addDocRow(block, { mode: 'link' }));

    // Existing attachments only exist for a project already saved under an
    // existing program (edit mode) — a brand-new block/program has none yet.
    const budgetReqProgramId = $bgr('bgr-id').value;
    const projectCode = prefillBlock && prefillBlock.project_code;
    if (!budgetReqProgramId || !projectCode) return;

    const params = new URLSearchParams({ budget_req_program_id: budgetReqProgramId, project_code: projectCode });
    fetch(`${BGR_ATTACHMENT_API}/list?${params}`, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(json => {
            if (!json.success) return;
            (json.data || []).forEach(a => bgr_addDocRow(block, {
                mode: a.storage_key ? 'upload' : 'link',
                name: a.document_name,
                attachmentId: a.attachment_id,
                viewUrl: a.view_url,
                link: a.external_link || '',
            }));
        })
        .catch(() => {});
}

function bgr_syncDocTableVisibility(block) {
    const tbl = block.querySelector('.bgr-block-doc-tbl');
    tbl.style.display = tbl.querySelector('.bgr-block-doc-tbody tr') ? '' : 'none';
}

function bgr_docUploadPanelHtml(fileId, label, filename, viewUrl) {
    return `<div class="bgr-doc-panel">
        <input type="file" id="${fileId}" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif" style="display:none;">
        <button type="button" class="bgr-doc-upload-btn">${bgr_esc(label)}</button>
        <span class="bgr-doc-uploaded-name">${bgr_esc(filename || '')}</span>
        ${viewUrl ? `<a href="${bgr_escAttr(viewUrl)}" target="_blank" rel="noopener" class="bgr-doc-open">Open ↗</a>` : ''}
    </div>`;
}

function bgr_docLinkPanelHtml(value, viewUrl) {
    return `<div class="bgr-doc-panel">
        <input type="text" class="bgr-input bgr-doc-link" value="${bgr_escAttr(value || '')}" placeholder="https://drive.google.com/…" style="font-size:12px;padding:6px 9px;">
        ${viewUrl ? `<a href="${bgr_escAttr(viewUrl)}" target="_blank" rel="noopener" class="bgr-doc-open">Open ↗</a>` : ''}
    </div>`;
}

// opts: { mode: 'upload'|'link', name?, attachmentId?, viewUrl?, link? }
function bgr_addDocRow(block, opts) {
    const tbody = block.querySelector('.bgr-block-doc-tbody');
    const rowId = 'bgr-doc-row-' + (++bgr_docRowSeq);
    const fileId = rowId + '-file';
    const tr = document.createElement('tr');
    tr.id = rowId;
    tr.dataset.mode = opts.mode;
    if (opts.attachmentId) tr.dataset.attachmentId = opts.attachmentId;

    const contentHtml = opts.mode === 'link'
        ? bgr_docLinkPanelHtml(opts.link, opts.viewUrl)
        : bgr_docUploadPanelHtml(fileId, opts.attachmentId ? opts.name : 'Choose File', opts.attachmentId ? opts.name : '', opts.viewUrl);

    tr.innerHTML = `
        <td><input type="text" class="bgr-input bgr-doc-name" value="${bgr_escAttr(opts.name || '')}" placeholder="Document name" style="font-size:12.5px;padding:6px 9px;"></td>
        <td>${contentHtml}</td>
        <td><button type="button" class="bgr-doc-remove" title="Remove document">&times;</button></td>`;
    tbody.appendChild(tr);
    bgr_syncDocTableVisibility(block);

    if (opts.mode === 'upload' && !opts.attachmentId) {
        const fileInput = tr.querySelector(`#${fileId}`);
        const uploadBtn = tr.querySelector('.bgr-doc-upload-btn');
        uploadBtn.addEventListener('click', () => fileInput.click());
        fileInput.addEventListener('change', () => bgr_handleDocFileSelect(fileInput, tr));
    } else if (opts.mode === 'link') {
        tr.querySelector('.bgr-doc-link').addEventListener('input', (ev) => bgr_onDocLinkInput(ev.target));
    }

    tr.querySelector('.bgr-doc-remove').addEventListener('click', () => bgr_removeDocRow(tr, block));

    return tr;
}

function bgr_handleDocFileSelect(input, row) {
    const file = input.files[0];
    if (!file) return;

    const ext = file.name.split('.').pop().toLowerCase();
    if (!BGR_DOC_ALLOWED_EXT.has(ext)) {
        bgr_toast(`File type not allowed. Accepted: ${[...BGR_DOC_ALLOWED_EXT].join(', ')}`, 'error');
        input.value = '';
        return;
    }
    if (file.size > BGR_DOC_MAX_BYTES) {
        bgr_toast(`File too large — max is ${BGR_DOC_MAX_BYTES / 1024 / 1024} MB.`, 'error');
        input.value = '';
        return;
    }

    row._fileObj = file;
    const nameInput = row.querySelector('.bgr-doc-name');
    if (nameInput && !nameInput.value.trim()) nameInput.value = file.name.replace(/\.[^.]+$/, '');
    row.querySelector('.bgr-doc-uploaded-name').textContent = file.name;
    row.querySelector('.bgr-doc-upload-btn').textContent = 'Re-select';
}

function bgr_onDocLinkInput(input) {
    const row = input.closest('tr');
    const panel = input.closest('.bgr-doc-panel');
    let anchor = panel.querySelector('a.bgr-doc-open');
    const link = input.value.trim();
    if (link) {
        if (!anchor) {
            anchor = document.createElement('a');
            anchor.className = 'bgr-doc-open';
            anchor.target = '_blank';
            anchor.rel = 'noopener';
            anchor.textContent = 'Open ↗';
            panel.appendChild(anchor);
        }
        anchor.href = link;
    } else if (anchor) {
        anchor.remove();
    }
}

async function bgr_removeDocRow(row, block) {
    const attachmentId = row.dataset.attachmentId;
    if (attachmentId) {
        if (!confirm('Delete this document now? This cannot be undone.')) return;
        try {
            const res = await fetch(`${BGR_ATTACHMENT_API}/delete/${attachmentId}`, {
                method: 'DELETE',
                credentials: 'same-origin',
            });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Failed to delete document.');
            bgr_toast('Document deleted successfully.', 'success');
        } catch (e) {
            bgr_toast(e.message, 'error');
            return;
        }
    }
    row.remove();
    bgr_syncDocTableVisibility(block);
}

// Builds this block's supporting_documents entries, pushing any staged File
// objects onto the shared, request-wide fileList (one flat doc_file_N index
// across every project block — see bgr_save()).
function bgr_getProjectDocPayload(block, fileList) {
    const docs = [];
    for (const row of block.querySelectorAll('.bgr-block-doc-tbody tr')) {
        const name = row.querySelector('.bgr-doc-name')?.value.trim() || '';
        const attachmentId = row.dataset.attachmentId;
        if (attachmentId) {
            docs.push({ attachment_id: Number(attachmentId), document_name: name });
        } else if (row.dataset.mode === 'upload' && row._fileObj) {
            docs.push({ file_index: fileList.length, document_name: name });
            fileList.push(row._fileObj);
        } else if (row.dataset.mode === 'link') {
            const link = row.querySelector('.bgr-doc-link')?.value.trim() || '';
            if (link) docs.push({ link, document_name: name });
        }
    }
    return docs;
}

function bgr_removeProjectBlock(block) {
    if ($bgr('bgr-blocks-wrap').children.length <= 1) { bgr_toast('At least one project is required.', 'error'); return; }
    bgr_cleanupAccountMenus(block);
    block.remove();
    // Frees up the series slot this block may have reserved (see
    // bgr_setProjectCode) for whichever other "new project" blocks remain.
    bgr_refreshAllProjectCodeFields();
    bgr_updateGrandTotal();
}

// Collapses a block down to just its header (project name field), hiding the
// chart-of-accounts table — for minimizing projects you're not actively
// editing when a request has several.
function bgr_toggleProjectBlock(block) {
    const collapsed = block.classList.toggle('bgr-collapsed');
    block.querySelector('.bgr-block-toggle').title = collapsed ? 'Expand' : 'Collapse';
}

// Type-directly-to-search project picker (twitter typeahead.js, already
// bundled as jquery/typeahead.jquery.min.js — no extra library needed). The
// visible input IS the search box; minLength:0 means focusing/clicking the
// field opens the dropdown immediately (listing all matching projects),
// and typing still filters that same list live (filtered by the header's
// Program/Sub-Program). Picking a suggestion fills the paired hidden
// .bgr-block-project-code field with its real project_code. Typing a name
// with no match leaves that hidden field empty — bgr_save() then sends the
// typed text as project_name so the backend resolves/creates it atomically
// when the whole request is saved (never the moment the user types).
function bgr_setProjectCode($input, code) {
    const hidden      = $input.closest('.bgr-block-project-wrap').find('.bgr-block-project-code');
    const wasResolved = !!hidden.val();
    hidden.val(code);

    // Only an "existing project" <-> "new project" transition changes which
    // series slot this block reserves from the shared BGR_PROJECT_CODE_CACHE
    // (see bgr_updateProjectCodeField) -- that's when every other "new
    // project" block's preview needs refreshing too, so none of them keep
    // holding a now-stale series number. Plain typing within an
    // already-unresolved block (code staying '' keystroke to keystroke)
    // only needs this one field re-checked.
    if (wasResolved !== !!code) {
        bgr_refreshAllProjectCodeFields();
    } else {
        bgr_updateProjectCodeField($input);
    }
}

// Seeded from the first real /project/next-code lookup under the current
// Program/Sub-Program, then handed out locally (prefix + nextSeries++) to
// every subsequent "new project" block — the database would otherwise keep
// reporting the exact same "next available" series to each of them, since
// none of them are actually saved yet. Reset (see bgr_refreshAllProjectCodeFields
// and bgr_openModal) whenever Program/Sub-Program changes, or the modal
// reopens, since that invalidates the whole prefix.
let BGR_PROJECT_CODE_CACHE = null; // { prefix: string, nextSeries: number } | null

// Fills the read-only "Project Code" field alongside Project: either the
// matched existing project's real code, or a preview of what
// GlobalService::generateProjectCode() would assign if this block ends up
// creating a new one (no existing project resolved) — pure UI; the real
// code (possibly different, if another project was created in the
// meantime) is resolved by the backend only when the whole request is
// actually saved. Shown as soon as the block has no resolved project,
// whether or not anything's been typed yet — the preview depends only on
// the header's Program/Sub-Program, never on the typed name. Fetched once
// per "new project" state per Program/Sub-Program, not on every keystroke —
// see bgr_refreshAllProjectCodeFields() for what clears that so a header
// change re-fetches.
function bgr_updateProjectCodeField($input) {
    // .bgr-block-project-code-display is a sibling of .bgr-block-project-wrap
    // (its own column), not a descendant of it — scope the lookup to the
    // whole block, not just the Project field's own wrap.
    const block   = $input.closest('.bgr-block');
    const wrap    = $input.closest('.bgr-block-project-wrap');
    const display = block.find('.bgr-block-project-code-display');
    const code    = wrap.find('.bgr-block-project-code').val();

    // Matched an existing project — it already has its own code, nothing to preview.
    if (code) {
        display.val(code).removeData('bgr-fetched');
        return;
    }

    const programCode = $bgr('bgr-program-code').value;
    if (!programCode) {
        display.val('').attr('placeholder', 'Select a program first').removeData('bgr-fetched');
        return; // not marked "fetched" — tries again once a Program is picked
    }
    if (display.data('bgr-fetched')) return;
    display.data('bgr-fetched', true);

    // Already seeded by an earlier "new project" block this session — hand
    // out the next series locally instead of re-querying, which would just
    // return the same number the DB still shows (nothing's saved yet).
    if (BGR_PROJECT_CODE_CACHE) {
        BGR_PROJECT_CODE_CACHE.nextSeries += 1;
        display.val(BGR_PROJECT_CODE_CACHE.prefix + String(BGR_PROJECT_CODE_CACHE.nextSeries).padStart(4, '0'));
        return;
    }

    display.val('').attr('placeholder', 'Generating…');
    const params = new URLSearchParams({
        program_code: programCode,
        sub_program_code: $bgr('bgr-sub-program-code').value,
        fiscal_yr: $bgr('bgr-fiscal-yr').value,
    });
    fetch(`${BGR_PROJECT_BASE_API}/next-code?${params}`, { credentials: 'same-origin' })
        .then(res => res.json())
        .then(json => {
            if (!json.success) throw new Error(json.message || 'Could not generate a project code.');
            const fullCode = json.data.project_code;
            const m = fullCode.match(/^(.*-)(\d{4})$/);
            if (m) {
                BGR_PROJECT_CODE_CACHE = { prefix: m[1], nextSeries: parseInt(m[2], 10) };
            }
            display.val(fullCode);
        })
        .catch(() => display.attr('placeholder', 'Code unavailable'));
}

// Re-previews every project block's code — needed because the preview
// depends on the header's Program/Sub-Program, which can change AFTER a
// block was created (including the very first block: 'add' mode resets
// Program to blank before creating it) without that block's own Project
// field ever being touched again. Clears the "already fetched" guard and
// the shared series cache first — a new Program/Sub-Program means a new
// prefix, so the old cached one (and its running count) no longer applies.
function bgr_refreshAllProjectCodeFields() {
    BGR_PROJECT_CODE_CACHE = null;
    document.querySelectorAll('#bgr-blocks-wrap .bgr-block-project-code-display').forEach(el => {
        $(el).removeData('bgr-fetched');
    });
    document.querySelectorAll('#bgr-blocks-wrap .bgr-block-project-name').forEach(el => {
        bgr_updateProjectCodeField($(el));
    });
}

// Every project_code already picked by another block in this same request —
// a project can only appear once per budget request, so these are excluded
// from $input's own suggestions and re-checked on select as a safety net
// against a stale/in-flight async result that was built before the latest
// pick.
function bgr_usedProjectCodes($input) {
    const ownBlock = $input.closest('.bgr-block')[0];

    return Array.from(document.querySelectorAll('#bgr-blocks-wrap .bgr-block-project-code'))
        .filter(el => el.closest('.bgr-block') !== ownBlock && el.value)
        .map(el => el.value);
}

function bgr_initProjectTypeahead($input) {
    $input.typeahead(
        { hint: false, highlight: true, minLength: 0 },
        {
            name: 'bgr-projects',
            display: 'label',
            limit: 8,
            source: (query, syncResults, asyncResults) => {
                clearTimeout($input.data('bgr-ta-timer'));
                $input.data('bgr-ta-timer', setTimeout(async () => {
                    try {
                        const params = new URLSearchParams({
                            search: query,
                            program_code: $bgr('bgr-program-code').value,
                            sub_program_code: $bgr('bgr-sub-program-code').value,
                        });
                        const res  = await fetch(`${BGR_PROJECT_BASE_API}/all?${params}`, { credentials: 'same-origin' });
                        const json = await res.json();
                        const used = bgr_usedProjectCodes($input);
                        asyncResults((json.data ?? [])
                            .filter(p => !used.includes(p.project_code))
                            .map(p => ({
                                project_code: p.project_code,
                                label: `${p.project_code} — ${p.project_name}`,
                            })));
                    } catch (e) {
                        asyncResults([]);
                    }
                }, 250));
            },
            templates: {
                suggestion: item => `<div>${bgr_esc(item.label)}</div>`,
                notFound: '<div class="tt-not-found">No match — this will be added as a new project.</div>',
            },
        }
    );

    // Selecting a suggestion resolves the real project_code. Any later edit
    // that moves the text away from that exact match invalidates it, falling
    // back to "typed name, not yet a real project" — guarded by bgr-syncing
    // so the library's own value-set on selection doesn't immediately
    // re-trigger this and clear the code it just set.
    $input.on('typeahead:select', (ev, item) => {
        if (bgr_usedProjectCodes($input).includes(item.project_code)) {
            bgr_toast('That project is already added to this budget request.', 'error');
            $input.typeahead('val', '');
            bgr_setProjectCode($input, '');
            return;
        }
        $input.data('bgr-syncing', true);
        $input.data('bgr-matched-text', item.label);
        bgr_setProjectCode($input, item.project_code);
        setTimeout(() => $input.removeData('bgr-syncing'), 0);
    });
    $input.on('input', () => {
        if ($input.data('bgr-syncing')) return;
        if ($input.val() !== $input.data('bgr-matched-text')) {
            bgr_setProjectCode($input, '');
        }
    });
}

// The suggestion menu (including the "No match" message, which the library
// treats as non-empty content and keeps visible) doesn't reliably close
// itself on blur alone — a debounced async result can still land and
// re-render it just after focus moved elsewhere. Rather than guess at that
// timing, force-close every project field's menu the moment focus genuinely
// lands somewhere outside it — a different field in the same block, another
// block entirely, or outside the modal. Registered once, globally (not
// per-block), since it has to see every focus change in the document.
// This only catches KEYBOARD-driven focus changes (e.g. Tab) — clicking on
// a plain, non-focusable part of the page (empty space, a label, the
// backdrop) doesn't move focus anywhere, so 'focusin' never fires there.
// The 'mousedown' listener below (which fires on literally any click)
// handles that case.
document.addEventListener('focusin', (ev) => {
    document.querySelectorAll('#bgr-blocks-wrap .bgr-block-project-name').forEach((el) => {
        if (!el.closest('.bgr-block-project-wrap').contains(ev.target)) {
            $(el).typeahead('close');
        }
    });
});
document.addEventListener('mousedown', (ev) => {
    document.querySelectorAll('#bgr-blocks-wrap .bgr-block-project-name').forEach((el) => {
        if (!el.closest('.bgr-block-project-wrap').contains(ev.target)) {
            $(el).typeahead('close');
        }
    });
});

/* ── Line items — repeatable rows nested inside a project block ─────── */
function bgr_addLineItemRow(blockEl, prefill = {}) {
    bgr_liSeq++;
    const seq = bgr_liSeq;
    const tr = document.createElement('tr');
    tr.dataset.seq = seq;
    tr.innerHTML = `
        <td>
            <div class="bgr-li-account-wrap">
                <input type="text" class="bgr-li-input bgr-li-account-code" placeholder="Type to search…" autocomplete="off">
                <input type="hidden" class="bgr-li-account-id" value="${bgr_escHtmlAttr(prefill.account_id)}">
            </div>
        </td>
        <td><textarea class="bgr-li-input bgr-li-particulars" placeholder="Particulars" maxlength="2000" rows="1">${bgr_esc(prefill.particulars)}</textarea></td>
        <td><input type="text" inputmode="decimal" class="bgr-li-input bgr-li-amt" value="${bgr_fmtAmountInitial(prefill.proposed_amount ?? prefill.amount)}" placeholder="0.00" oninput="bgr_fmtAmount(this)"></td>
    `;
    blockEl.querySelector('.bgr-block-li-tbody').appendChild(tr);

    const $acctInput = $(tr.querySelector('.bgr-li-account-code'));
    bgr_initAccountPicker($acctInput);
    if (prefill.account_id != null && prefill.account_id !== '') {
        const opt = BGR_COA_OPTIONS.find(a => String(a.account_id) === String(prefill.account_id));
        const label = opt ? bgr_accountLabel(opt) : '';
        if (label) $acctInput.val(label).data('bgr-matched-text', label);
    }

    // Remove button lives in the separate .bgr-block-li-remove-col sibling,
    // kept in row-sync with the table via matching dataset.seq and matching
    // CSS heights (same split-column pattern the old single-table layout used).
    const removeCell = document.createElement('div');
    removeCell.className = 'bgr-li-remove-cell';
    removeCell.dataset.seq = seq;
    removeCell.innerHTML = '<button type="button" class="bgr-li-remove" title="Remove">&times;</button>';
    removeCell.querySelector('.bgr-li-remove').addEventListener('click', () => bgr_removeLineItemRow(blockEl, seq));
    const removeCol = blockEl.querySelector('.bgr-block-li-remove-col');
    removeCol.insertBefore(removeCell, removeCol.querySelector('.bgr-li-remove-foot'));

    bgr_syncRemoveColGap(blockEl);
    bgr_updateLineItemTotal(blockEl);
}

function bgr_removeLineItemRow(blockEl, seq) {
    const tbody = blockEl.querySelector('.bgr-block-li-tbody');
    if (tbody.rows.length <= 1) { bgr_toast('At least one line item is required.', 'error'); return; }
    blockEl.querySelector(`.bgr-li-remove-cell[data-seq="${seq}"]`)?.remove();
    const row = tbody.querySelector(`tr[data-seq="${seq}"]`);
    if (row) bgr_cleanupAccountMenus(row);
    row?.remove();
    bgr_syncRemoveColGap(blockEl);
    bgr_updateLineItemTotal(blockEl);
}

// .bgr-block-li-wrap's horizontal scrollbar (when the table needs it) reserves
// space at its own bottom that .bgr-block-li-remove-col — a plain non-scrolling
// sibling — doesn't lose. Without compensating for that, the two columns'
// rows drift out of alignment by exactly the scrollbar's height. Measuring
// it directly (rather than guessing a fixed px value) keeps this correct
// regardless of OS/browser scrollbar style.
function bgr_syncRemoveColGap(blockEl) {
    const wrap = blockEl.querySelector('.bgr-block-li-wrap');
    const col  = blockEl.querySelector('.bgr-block-li-remove-col');
    if (!wrap || !col) return;
    const scrollbarHeight = wrap.offsetHeight - wrap.clientHeight;
    col.style.paddingBottom = scrollbarHeight > 0 ? scrollbarHeight + 'px' : '0';
}

// Type-directly-to-search chart-of-accounts picker — a small custom
// dropdown, NOT typeahead.js (unlike the Project picker above). This field
// sits inside .bgr-block-li-wrap (overflow-x:auto, which per spec forces
// its overflow-y to clip too) and .bgr-block-li-outer (overflow:hidden, for
// rounded corners) — both clip a normal position:absolute dropdown no
// matter its z-index. Forcing typeahead.js's own menu to position:fixed to
// escape that caused a real race (the library kept re-applying its own
// absolute-positioned top/left after ours, sometimes winning and hiding the
// menu off-screen). A dropdown built and positioned entirely by us — one
// <div class="bgr-acct-menu"> per row, appended to <body> so no ancestor
// can clip it — has nothing else fighting it.
//
// BGR_COA_OPTIONS is already fully loaded client-side (bgr_loadChartOfAccounts),
// so matching is done locally with no extra request. Selecting an option
// resolves the hidden .bgr-li-account-id field (the input itself already
// shows "code — name"); clearing/typing away from a matched label clears
// the id, so bgr_save() can reject an unresolved row.
function bgr_setAccountId($input, id) {
    $input.closest('tr')[0].querySelector('.bgr-li-account-id').value = id || '';
}

// menu element -> its input element, so the single global focusin/scroll
// listeners below (registered once, not per-row) know which menu goes with
// which field, and bgr_removeLineItemRow can clean up its own entry.
const BGR_ACCT_MENUS = new Map();

function bgr_initAccountPicker($input) {
    const input = $input.get(0);
    const menu  = document.createElement('div');
    menu.className = 'bgr-acct-menu';
    document.body.appendChild(menu);
    BGR_ACCT_MENUS.set(menu, input);
    $input.data('bgr-acct-menu', menu);

    function renderOptions(query) {
        const q = query.trim().toLowerCase();
        const matches = BGR_COA_OPTIONS
            .filter(a => !q || a.account_code.toLowerCase().includes(q) || a.account_title.toLowerCase().includes(q))
            .slice(0, 50);
        menu.innerHTML = matches.length
            ? matches.map(a => `<div class="bgr-acct-option" data-account-id="${bgr_escHtmlAttr(a.account_id)}">${bgr_esc(bgr_accountLabel(a))}</div>`).join('')
            : '<div class="bgr-acct-empty">No matching account.</div>';
    }

    function openMenu() {
        BGR_ACCT_MENUS.forEach((_, m) => { if (m !== menu) m.classList.remove('open'); });
        renderOptions(input.value);
        const rect = input.getBoundingClientRect();
        menu.style.top   = (rect.bottom + 4) + 'px';
        menu.style.left  = rect.left + 'px';
        menu.style.width = rect.width + 'px';
        menu.classList.add('open');
    }

    $input.on('focus', openMenu);
    $input.on('input', () => {
        if ($input.data('bgr-syncing')) return;
        if ($input.val() !== $input.data('bgr-matched-text')) {
            bgr_setAccountId($input, '');
        }
        openMenu();
    });

    // mousedown, not click: a plain click would first let the browser blur
    // the input (nothing else in the menu is focusable), which the global
    // focusin listener above treats as "focus left the field" and closes
    // the menu — hiding the option out from under the click before it ever
    // fires. preventDefault() here stops that blur, so focus never leaves
    // the input and the selection below always registers.
    menu.addEventListener('mousedown', (ev) => {
        const optionEl = ev.target.closest('.bgr-acct-option');
        if (!optionEl) return;
        ev.preventDefault();

        const opt = BGR_COA_OPTIONS.find(a => String(a.account_id) === optionEl.dataset.accountId);
        if (!opt) return;

        const label = bgr_accountLabel(opt);
        $input.data('bgr-syncing', true);
        $input.val(label).data('bgr-matched-text', label);
        bgr_setAccountId($input, opt.account_id);
        setTimeout(() => $input.removeData('bgr-syncing'), 0);
        menu.classList.remove('open');
    });
}

// Registered once, globally (not per-row): closes an open account menu the
// moment focus genuinely lands somewhere outside it (a different field,
// another row, or outside the modal), and when something OTHER than the
// menu itself scrolls — the menu is position:fixed, so it would otherwise
// drift away from the input it belongs to as soon as the (horizontally-
// scrollable) table or the modal body scrolls. Scrolling the menu's own
// suggestion list (ev.target is the menu) must NOT close it — that's just
// the user scrolling through the options, which is the whole point of it
// being scrollable.
document.addEventListener('focusin', (ev) => {
    BGR_ACCT_MENUS.forEach((inputEl, menuEl) => {
        if (inputEl !== ev.target && !menuEl.contains(ev.target)) {
            menuEl.classList.remove('open');
        }
    });
});
// Same reasoning as the project picker's 'mousedown' listener above: a
// click on a plain, non-focusable part of the page doesn't move focus
// anywhere, so 'focusin' alone misses it.
document.addEventListener('mousedown', (ev) => {
    BGR_ACCT_MENUS.forEach((inputEl, menuEl) => {
        if (inputEl !== ev.target && !menuEl.contains(ev.target)) {
            menuEl.classList.remove('open');
        }
    });
});
document.addEventListener('scroll', (ev) => {
    BGR_ACCT_MENUS.forEach((_, menuEl) => {
        if (menuEl.contains(ev.target)) return;
        menuEl.classList.remove('open');
    });
}, true);

// Each account picker's dropdown lives on <body>, detached from its row —
// removing the row alone would otherwise leak that element. Call this with
// whatever's about to be removed/cleared (a single row, a whole project
// block, or the entire #bgr-blocks-wrap) before it's actually removed.
function bgr_cleanupAccountMenus(containerEl) {
    containerEl.querySelectorAll('.bgr-li-account-code').forEach((el) => {
        const menu = $(el).data('bgr-acct-menu');
        if (menu) {
            BGR_ACCT_MENUS.delete(menu);
            menu.remove();
        }
    });
}

/* ── Modal ─────────────────────────────────────────────────── */
async function bgr_openModal(mode, data = null) {
    bgr_hideErr();
    BGR_PROJECT_CODE_CACHE = null; // fresh session — don't carry over a prior open's series cache
    $bgr('bgr-grand-total-display').value = '0.00'; // recomputed as project blocks are (re)built below

    // Lazy-loaded here rather than at page load — the Account Code picker
    // (built inside bgr_addProjectBlock below) needs BGR_COA_OPTIONS already
    // populated, so this must finish before any project block is built.
    window.PagePreloader?.show();
    await bgr_loadChartOfAccounts();
    window.PagePreloader?.hide();

    $bgr('bgr-mode').value = mode;
    $bgr('bgr-fiscal-yr').disabled = false;

    if (mode === 'edit-program') {
        $bgr('bgr-id').value = data.budget_req_program_id;
        $bgr('bgr-modal-title').textContent = 'Edit Program';
        $bgr('bgr-save').textContent = 'Save Program';

        $bgr('bgr-fiscal-yr').value = data.fiscal_yr || '';
        $bgr('bgr-fiscal-yr').disabled = true;
        $bgr('bgr-program-code').value = data.program_code || '';
        bgr_fillSubPrograms(data.program_code, data.sub_program_code);

        const ctx = $bgr('bgr-program-context');
        ctx.textContent = `Editing a program under Budget Request ${data.budget_reference_number} (FY ${data.fiscal_yr}).`;
        ctx.style.display = '';

        bgr_cleanupAccountMenus($bgr('bgr-blocks-wrap'));
        $bgr('bgr-blocks-wrap').innerHTML = '';
        (data.projects && data.projects.length ? data.projects : [{}]).forEach(p => bgr_addProjectBlock(p));
    } else {
        $bgr('bgr-id').value = '';
        $bgr('bgr-modal-title').textContent = 'Add Budget Request';
        $bgr('bgr-save').textContent = 'Save Request';

        // Default a new request to the current year rather than an empty
        // placeholder — the most common case, and the user can still change it.
        const currentYearOption = $bgr('bgr-fiscal-yr').querySelector(`option[value="${new Date().getFullYear()}"]`);
        $bgr('bgr-fiscal-yr').value = currentYearOption ? currentYearOption.value : '';
        $bgr('bgr-program-code').value = '';
        bgr_fillSubPrograms('');

        $bgr('bgr-program-context').style.display = 'none';

        bgr_cleanupAccountMenus($bgr('bgr-blocks-wrap'));
        $bgr('bgr-blocks-wrap').innerHTML = '';
        bgr_addProjectBlock();
    }

    $bgr('bgr-overlay').classList.add('open');
}

function bgr_closeModal() {
    $bgr('bgr-overlay').classList.remove('open');
    bgr_cleanupAccountMenus($bgr('bgr-blocks-wrap'));
    $bgr('bgr-blocks-wrap').innerHTML = '';
    $bgr('bgr-form').reset();
    $bgr('bgr-fiscal-yr').disabled = false;
    bgr_hideErr();
}

function bgr_showErr(msg) {
    const el = $bgr('bgr-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function bgr_hideErr() {
    $bgr('bgr-err').style.display = 'none';
}

async function bgr_save(ev) {
    ev.preventDefault();
    bgr_hideErr();
    const mode = $bgr('bgr-mode').value;
    const id   = $bgr('bgr-id').value;

    const fiscalYr       = $bgr('bgr-fiscal-yr').value;
    const programCode    = $bgr('bgr-program-code').value;
    const subProgramCode = $bgr('bgr-sub-program-code').value;
    if (!fiscalYr) { bgr_showErr('Select a fiscal year.'); return; }
    if (!programCode) { bgr_showErr('Select a program.'); return; }

    const projects = [];
    const docFiles = []; // one flat list across every project block — see bgr_getProjectDocPayload()
    const blocks = document.querySelectorAll('#bgr-blocks-wrap .bgr-block');
    for (let pi = 0; pi < blocks.length; pi++) {
        const block = blocks[pi];
        // A matched suggestion resolves the hidden code; otherwise the typed
        // text has no confirmed match — send it as project_name so the
        // backend resolves/creates it atomically when the request is saved.
        const projectCode = block.querySelector('.bgr-block-project-code').value;
        const projectName = block.querySelector('.bgr-block-project-name').value.trim();
        const location    = block.querySelector('.bgr-block-location').value.trim();
        if (!projectCode && !projectName) { bgr_showErr(`Project ${pi + 1}: select or enter a project.`); return; }

        const rows = Array.from(block.querySelectorAll('.bgr-block-li-tbody tr'));
        if (!rows.length) { bgr_showErr(`Project ${pi + 1}: add at least one line item.`); return; }

        const lineItems = [];
        for (let li = 0; li < rows.length; li++) {
            const tr          = rows[li];
            const accountId   = tr.querySelector('.bgr-li-account-id').value;
            const particulars = tr.querySelector('.bgr-li-particulars').value.trim();
            const amount      = bgr_unformatAmount(tr.querySelector('.bgr-li-amt').value);
            if (!accountId) { bgr_showErr(`Project ${pi + 1}, line item ${li + 1}: select a chart of accounts entry.`); return; }
            if (!particulars) { bgr_showErr(`Project ${pi + 1}, line item ${li + 1}: particulars is required.`); return; }
            if (!amount) { bgr_showErr(`Project ${pi + 1}, line item ${li + 1}: proposed amount is required.`); return; }
            lineItems.push({ account_id: accountId, particulars, proposed_amount: amount });
        }

        const supportingDocuments = bgr_getProjectDocPayload(block, docFiles);

        projects.push({ project_code: projectCode, project_name: projectName, location, line_items: lineItems, supporting_documents: supportingDocuments });
    }
    if (!projects.length) { bgr_showErr('Add at least one project.'); return; }

    const body = { fiscal_yr: fiscalYr, program_code: programCode, sub_program_code: subProgramCode, projects };
    // PHP only populates $_FILES/multipart bodies on POST — editing a program
    // normally PUTs, but switches to POST when there's a new file to carry.
    const url    = mode === 'edit-program' ? `${BGR_API}/program/update/${id}` : `${BGR_API}/add`;
    const method = (mode === 'edit-program' && !docFiles.length) ? 'PUT' : 'POST';

    window.PagePreloader?.show();
    try {
        let fetchOpts;
        if (docFiles.length) {
            const fd = new FormData();
            fd.append('data', JSON.stringify(body));
            docFiles.forEach((f, i) => fd.append('doc_file_' + i, f));
            fetchOpts = { method, credentials: 'same-origin', body: fd };
        } else {
            fetchOpts = { method, credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) };
        }
        const res  = await fetch(url, fetchOpts);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save.');

        const message = mode === 'edit-program'
            ? 'Program updated successfully.'
            : (json.created_new_header ? 'Budget request created successfully.' : 'Program added to the existing budget request.');
        bgr_toast(message, 'success');
        bgr_closeModal();
        window.bgr_afterSave?.(mode, json);
    } catch (e) {
        bgr_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers (also used by host pages' own scripts, loaded after this one) ── */
function bgr_fmtMoney(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function bgr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function bgr_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}
function bgr_escHtmlAttr(text) {
    return String(text ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}
/* ── Comma-formatted amount inputs (display only — commas are stripped
   before validation/submission via bgr_unformatAmount) ──────────────── */
function bgr_fmtAmount(input) {
    const pos   = input.selectionStart;
    const prev  = input.value.length;
    const raw   = input.value.replace(/[^0-9.]/g, '');
    const parts = raw.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('') : parts[0];
    const diff = input.value.length - prev;
    try { input.setSelectionRange(pos + diff, pos + diff); } catch (_) {}

    const block = input.closest('.bgr-block');
    if (block) bgr_updateLineItemTotal(block);
}

// Sum of every line item's Proposed Amount in this block, shown in the
// table's own Total row — recomputed on every amount edit and whenever a
// row is added/removed (see bgr_addLineItemRow/bgr_removeLineItemRow).
function bgr_updateLineItemTotal(blockEl) {
    const cell = blockEl.querySelector('.bgr-block-li-total-amt');
    if (!cell) return;
    const total = Array.from(blockEl.querySelectorAll('.bgr-li-amt'))
        .reduce((sum, input) => sum + (parseFloat(bgr_unformatAmount(input.value)) || 0), 0);
    cell.textContent = bgr_fmtAmountInitial(total) || '0.00';
    bgr_updateGrandTotal();
}

// Sum of every project block's own Total row — recomputed whenever any of
// them changes (bgr_updateLineItemTotal) and whenever a whole project block
// is added/removed (a brand-new block always adds at least one line item,
// which already triggers bgr_updateLineItemTotal; removing one doesn't, so
// bgr_removeProjectBlock calls this directly).
function bgr_updateGrandTotal() {
    const display = $bgr('bgr-grand-total-display');
    if (!display) return;
    const total = Array.from(document.querySelectorAll('#bgr-blocks-wrap .bgr-block-li-total-amt'))
        .reduce((sum, cell) => sum + (parseFloat(bgr_unformatAmount(cell.textContent)) || 0), 0);
    display.value = bgr_fmtAmountInitial(total) || '0.00';
}
function bgr_fmtAmountInitial(v) {
    return (v || v === 0) && v !== '' ? Number(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '';
}
function bgr_unformatAmount(v) {
    return String(v ?? '').replace(/,/g, '');
}
function bgr_statusBadge(name, color) {
    if (!name) return '<span class="nha-td-sub">—</span>';
    const c = /^#[0-9A-Fa-f]{6}$/.test(color || '') ? color : '#6B7280';
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;
        font-size:11px;font-weight:600;color:${c};background:${c}1A;border:1px solid ${c}40;white-space:nowrap;">
        <span style="width:6px;height:6px;border-radius:50%;background:${c};flex-shrink:0;"></span>${bgr_esc(name)}
    </span>`;
}

// The toast is built on FIRST USE, not when this script loads: SweetAlert2 is loaded asynchronously by views/layout.php
// (loadjs bundle "swal"), so a top-level `const … = Swal.mixin(…)` could run before `Swal` existed, throw, and leave the
// const uninitialized for good — every later bgr_toast() then failed with "Cannot access 'BGR_TOAST_SWAL' before
// initialization" (e.g. right after a successful Endorse / Return / Approve).
let bgr_toastSwal = null;

function bgr_toast(msg, type = 'info') {
    const icon = ['success', 'error', 'warning', 'info', 'question'].includes(type) ? type : 'info';

    if (typeof Swal === 'undefined') {
        // SweetAlert2 is still on its way: show the toast as soon as the layout's "swal" bundle is ready
        if (typeof loadjs !== 'undefined' && loadjs.ready) loadjs.ready('swal', () => bgr_toast(msg, type));
        return;
    }

    bgr_toastSwal ??= Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: el => {
            el.onmouseenter = Swal.stopTimer;
            el.onmouseleave = Swal.resumeTimer;
        },
    });
    bgr_toastSwal.fire({ icon, title: msg });
}
</script>
