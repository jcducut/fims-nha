<?php
/**
 * dr-module-form.php
 *
 * Add / Edit modal + read-only view modals for the IAR (Inspection and Acceptance Report) Module:
 * 1 PO → 1 IAR → many delivery receipts; each save handles ONE delivery receipt (DR no., date,
 * account rows whose total stays within the PO balance, optional item details from 50,000).
 *
 * Trigger:
 *   amp_openModal('add')                 — new delivery receipt (PO picked from the Budget Allocation)
 *   amp_openModal('edit', poWithReceipts) — edit the PO header and one of its delivery receipts
 *
 * Included from iar-module.php (which defines the amp_* list/save logic).
 */
$_ampf_api = $_amp_api ?? \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
    /* list page: stacked DR lines + clickable DR number */
    .amp-line { min-height:30px;display:flex;align-items:center; }
    .amp-iar-link { font-weight:700;color:var(--afims-primary-text, #0035A9);text-decoration:underline;cursor:pointer; }
    .amp-iar-link:hover { opacity:.8; }

    /* ═══ Add / Edit modal — same design as the Memo Request form (budget-allocation-form.php): shared modal shell,
           section cards with a blue-bar header, 40px rounded fields, uppercase labels ═══ */
    #amp-overlay { display:none;position:fixed;inset:0;z-index:9100;box-sizing:border-box;padding:16px;
        background:rgba(15,32,69,.55);align-items:center;justify-content:center;overflow-y:auto; }
    #amp-overlay.open { display:flex; }
    #amp-card { display:flex;flex-direction:column;width:100%;max-width:1360px;max-height:92vh;overflow:hidden;
        background:var(--afims-surface, #FFFFFF);border-radius:14px;box-shadow:0 24px 64px rgba(0,0,0,.22);
        font-family:'IBM Plex Sans',sans-serif;font-size:14px;color:var(--afims-text, #1A1B21);animation:ampIn .18s ease; }
    #amp-card *, #amp-card *::before, #amp-card *::after { box-sizing:border-box; }
    #amp-card .amp-input, #amp-card .amp-combo { box-sizing:border-box !important;width:100% !important;max-width:100% !important;min-width:0 !important; }
    #amp-card .amp-group > *, #amp-card .amp-row > * { min-width:0; }
    @keyframes ampIn { from{opacity:0;transform:translateY(14px) scale(.98);} to{opacity:1;transform:none;} }

    #amp-header { display:flex;align-items:center;justify-content:space-between;flex-shrink:0;
        padding:16px 20px;background:var(--afims-heading-fill, #000E3E); }
    .amp-hdr-title { margin:0;font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF); }
    #amp-modal-close { display:flex;align-items:center;justify-content:center;width:28px;height:28px;font-size:18px;line-height:1;cursor:pointer;
        color:var(--afims-pure-white, #FFFFFF);background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 15%, transparent);
        border:1px solid rgba(255,255,255,.25);border-radius:6px;opacity:.85; }
    #amp-modal-close:hover { opacity:1; }

    #amp-err { display:none;margin:16px 22px 0;padding:8px 12px;font-size:12px;line-height:1.5;color:var(--afims-danger, #DC3545);
        background:var(--afims-danger-soft, #FAE1E3);border:1px solid color-mix(in srgb, var(--afims-danger, #DC3545) 25%, transparent);border-radius:8px; }

    #amp-form { display:flex;flex-direction:column;min-height:0;flex:1; }
    #amp-body { flex:1;overflow-y:auto;padding:18px 22px; }

    /* section cards */
    .amp-section { margin-bottom:18px;padding:22px 24px 24px;background:var(--afims-surface, #FFFFFF);
        border:1px solid var(--afims-border, #E4ECF2);border-radius:12px;box-shadow:0 1px 2px rgba(15,32,69,.05); }
    .amp-section-header { display:flex;align-items:center;gap:10px;margin:0 0 18px;padding-bottom:12px;
        border-bottom:1px solid var(--afims-border, #E4ECF2);font-size:15px;font-weight:700;line-height:1.3;color:var(--afims-heading, #000E3E); }
    .amp-section-header::before { content:'';flex-shrink:0;width:4px;height:16px;border-radius:2px;background:var(--afims-primary, #0035A9); }
    .amp-section-header .amp-hdr-right { margin-left:auto;display:flex;align-items:center;gap:10px; }
    .amp-section-header .opt { font-size:12px;font-weight:500;color:var(--afims-text-muted, #62657E); }

    /* grid + fields */
    .amp-row { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px 20px;align-items:start;margin-bottom:18px; }
    .amp-section > .amp-row:last-child { margin-bottom:0; }
    .amp-row.c5 { grid-template-columns:repeat(5,minmax(0,1fr));column-gap:20px; }
    .amp-row.c2 { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .amp-group { display:flex;flex-direction:column;gap:6px;min-width:0;position:relative; }
    @media (max-width:1100px) { .amp-row.c5 { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media (max-width:760px) { .amp-row, .amp-row.c5 { grid-template-columns:minmax(0,1fr) minmax(0,1fr) !important; } }
    .amp-label { display:block;margin:0;font-size:12px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:var(--afims-text, #1A1B21); }
    .amp-label .req { margin-left:2px;color:var(--afims-danger, #DC3545); }
    .amp-label .opt { font-weight:500;text-transform:none;letter-spacing:0;color:var(--afims-text-muted, #62657E); }
    .amp-input { display:block;width:100%;min-height:40px;padding:9px 12px;font-family:'IBM Plex Sans',sans-serif;font-weight:400;line-height:1.4;
        color:var(--afims-text, #1A1B21);background-color:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);
        border-radius:8px;outline:none;transition:border-color .15s, box-shadow .15s, background-color .15s; }
    .amp-input:focus { border-color:var(--afims-primary, #0035A9);box-shadow:0 0 0 3px color-mix(in srgb, var(--afims-primary, #0035A9) 14%, transparent); }
    .amp-input[readonly] { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-secondary, #5B5E73); }
    textarea.amp-input { resize:vertical;min-height:64px;line-height:1.55; }
    .amp-num { text-align:right; }

    /* searchable dropdowns (PO number, account code) */
    .amp-combo { position:relative; }
    .amp-combo-list { display:none;position:absolute;left:0;right:0;top:100%;margin-top:2px;z-index:9999;max-height:220px;overflow-y:auto;
        background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14); }
    .amp-combo.open .amp-combo-list { display:block; }
    .amp-combo-opt { padding:9px 12px;font-size:13px;cursor:pointer;color:var(--afims-text, #1A1B21); }
    .amp-combo-opt:hover, .amp-combo-opt.active { background:var(--afims-surface-subtle, #F4F7FA); }
    .amp-combo-empty { padding:9px 12px;font-size:12px;color:var(--afims-text-muted, #62657E); }

    .amp-sec-head { display:flex;align-items:center;justify-content:space-between;margin:2px 0 12px; }
    .amp-sec-title { font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text, #1A1B21); }

    /* account rows: Account Code | Amount | remove */
    .amp-ent { display:grid;grid-template-columns:minmax(0,3fr) minmax(0,1fr) 36px;gap:14px;align-items:center;margin-bottom:10px; }
    .amp-ent-head { font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text, #1A1B21);margin-bottom:6px; }
    .amp-ent-head .req { color:var(--afims-danger, #DC3545); }
    .amp-ent-remove { font-size:20px;line-height:1;padding:0; }
    .amp-ent-total { margin-top:6px;padding-top:12px;border-top:1px solid var(--afims-border, #E4ECF2); }
    .amp-ent-total-lbl { font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em; }
    .amp-ent-total-val { font-size:15px;font-weight:700;padding:0 12px;font-variant-numeric:tabular-nums; }
    @media (max-width:760px) { .amp-ent { grid-template-columns:minmax(0,1fr) 36px; } .amp-ent > .amp-combo, .amp-ent-head > span:first-child, .amp-ent-total > div:first-child { grid-column:1 / -1; } }

    /* optional item rows: Description | Serial No. | Amount | Prop. No. | remove — one line per item, every field its own cell
       (a fixed left margin on each cell, so they are always apart) */
    .amp-itm { display:flex;align-items:flex-start;margin-bottom:12px; }
    .amp-itm > .c { min-width:0;margin-left:16px; }
    .amp-itm > .c:first-child { margin-left:0; }
    .amp-itm .c-desc { flex:3 1 0; }
    .amp-itm .c-serial { flex:1.4 1 0; }
    .amp-itm .c-amt, .amp-itm .c-prop { flex:1.2 1 0; }
    .amp-itm .c-x { flex:0 0 28px;text-align:center;padding-top:8px; }
    .amp-itm textarea.amp-input { min-height:64px;resize:vertical; }
    .amp-itm-head { font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.04em;color:var(--afims-text, #1A1B21);margin-bottom:6px;align-items:flex-end; }
    @media (max-width:900px) { .amp-itm { flex-wrap:wrap; } .amp-itm .c-desc { flex:1 1 100%;margin-left:0;margin-bottom:10px; }
        .amp-itm .c-serial, .amp-itm .c-amt, .amp-itm .c-prop { flex:1 1 40%; } .amp-itm-head { display:none; } }

    /* confirm modal (e.g. when the PO is changed) */
    #amp-confirm-overlay { display:none;position:fixed;inset:0;z-index:9300;box-sizing:border-box;padding:16px;
        background:rgba(15,32,69,.55);align-items:center;justify-content:center; }
    #amp-confirm-overlay.open { display:flex; }
    #amp-confirm-card { width:100%;max-width:480px;overflow:hidden;background:var(--afims-surface, #FFFFFF);border-radius:14px;
        box-shadow:0 24px 64px rgba(0,0,0,.22);font-family:'IBM Plex Sans',sans-serif;font-size:14px;color:var(--afims-text, #1A1B21);animation:ampIn .18s ease; }
    #amp-confirm-card .amp-cf-hdr { display:flex;align-items:center;gap:10px;padding:16px 20px;background:var(--afims-heading-fill, #000E3E); }
    #amp-confirm-card .amp-cf-title { margin:0;font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF); }
    #amp-confirm-card .amp-cf-body { padding:20px 22px;line-height:1.6; }
    #amp-confirm-card .amp-cf-body p { margin:0 0 10px; }
    #amp-confirm-card .amp-cf-body p:last-child { margin-bottom:0; }
    .swal2-container.amp-swal { z-index:9400 !important; }       /* SweetAlert2 (loaded app-wide) above the add/edit modal (9100) */
    .amp-swal .swal2-popup { font-family:'IBM Plex Sans',sans-serif; }
    .amp-cf-warn { display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border-radius:8px;font-size:13px;
        color:#92400E;background:#FEF3C7;border:1px solid #FDE68A;text-align:left;margin-top:12px; }
    #amp-confirm-card .amp-cf-foot { display:flex;justify-content:flex-end;gap:8px;padding:14px 20px;border-top:1px solid var(--afims-border, #E4ECF2); }
    #amp-confirm-card .amp-cf-btn { height:38px;padding:0 22px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);font-size:13px;
        font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #amp-confirm-card .amp-cf-btn.primary { background:var(--afims-danger, #DC3545);border-color:var(--afims-danger, #DC3545);color:#fff; }
    /* EDIT mode: only IAR no., invoice, DR no./date and the items can be changed — PO, account rows and "add row" are locked */
    #amp-edit-note { display:none;margin-bottom:18px;padding:10px 14px;border-radius:8px;font-size:13px;line-height:1.5;
        color:#92400E;background:#FEF3C7;border:1px solid #FDE68A; }
    .amp-edit #amp-edit-note { display:block; }
    .amp-edit #amp-add-entry { display:none; }
    .amp-edit #amp-entries .amp-ent-remove { visibility:hidden; }
    .amp-edit #amp-entries .amp-input, .amp-edit #amp-po-number { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-secondary, #5B5E73);cursor:not-allowed; }
    .amp-link-danger { border:none;background:none;cursor:pointer;color:var(--afims-danger, #DC3545);font-size:12px;font-weight:600;white-space:nowrap; }
    .amp-link-danger:disabled { opacity:.35;cursor:not-allowed; }
    .amp-add-btn { border:1px dashed var(--afims-heading-fill, #000E3E);background:transparent;color:var(--afims-heading-fill, #000E3E);
        border-radius:8px;height:34px;padding:0 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:'IBM Plex Sans',sans-serif; }
    .amp-add-btn:hover { background:rgba(15,32,69,.05); }

    #amp-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;flex-shrink:0;
        padding:14px 20px;background:var(--afims-surface, #FFFFFF);border-top:1px solid var(--afims-border, #E4ECF2); }
    #amp-footer .amp-btn { height:38px;padding:0 22px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    #amp-footer .amp-btn:disabled { opacity:.5;cursor:not-allowed; }
    #amp-footer .amp-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #amp-footer .amp-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
</style>

<div id="amp-overlay">
    <div id="amp-card">
        <div id="amp-header">
            <h3 class="amp-hdr-title" id="amp-modal-title">Add PO Record</h3>
            <button type="button" id="amp-modal-close" aria-label="Close">&times;</button>
        </div>

        <div id="amp-err"></div>

        <form id="amp-form" autocomplete="off">
            <div id="amp-body">
                <input type="hidden" id="amp-mode" value="add">
                <input type="hidden" id="amp-orig-po" value="">
                <div id="amp-edit-note"><strong>Editing:</strong> only the IAR number, the Sales Invoice details, the DR number and date, and the items can be changed.
                    The PO and the account entries are locked — to record another delivery, add a new record.</div>

                <!-- ── Purchase Order (picked from the Budget Allocation) ── -->
                <div class="amp-section">
                    <h4 class="amp-section-header">Purchase Order</h4>
                    <div class="amp-row">
                        <div class="amp-group">
                            <label class="amp-label" for="amp-po-number">PO Number <span class="req">*</span></label>
                            <div class="amp-combo" id="amp-po-combo">
                                <input type="text" class="amp-input" id="amp-po-number" required maxlength="100" placeholder="Search PO number…">
                                <div class="amp-combo-list"></div>
                            </div>
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-iar-number">IAR Number <span class="req">*</span></label>
                            <input type="text" class="amp-input" id="amp-iar-number" required maxlength="50" placeholder="e.g. IAR-2026-0001">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-si-number">Sales Invoice # <span class="opt">(optional)</span></label>
                            <input type="text" class="amp-input" id="amp-si-number" maxlength="100">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-si-date">Sales Invoice Date <span class="opt">(optional)</span></label>
                            <input type="date" class="amp-input" id="amp-si-date">
                        </div>
                    </div>

                    <!-- Filled from the selected PO (Budget Allocation RFA): read-only -->
                    <div class="amp-row c5">
                        <div class="amp-group">
                            <label class="amp-label" for="amp-po-office">Requesting Office/Dept</label>
                            <input type="text" class="amp-input" id="amp-po-office" readonly tabindex="-1" placeholder="Loaded from the PO">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-po-vendor">Vendor</label>
                            <input type="text" class="amp-input" id="amp-po-vendor" readonly tabindex="-1" placeholder="Loaded from the PO">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-po-date">PO Date</label>
                            <input type="text" class="amp-input" id="amp-po-date" readonly tabindex="-1" placeholder="Loaded from the PO">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-po-amount">PO Amount</label>
                            <input type="text" class="amp-input amp-num" id="amp-po-amount" readonly tabindex="-1" placeholder="Loaded from the PO">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-po-balance">Balance <span class="opt">(after this DR)</span></label>
                            <input type="text" class="amp-input amp-num" id="amp-po-balance" readonly tabindex="-1" placeholder="Loaded from the PO" style="font-weight:700;">
                        </div>
                    </div>
                </div>

                <!-- ── One delivery receipt per save ── -->
                <div class="amp-section">
                    <h4 class="amp-section-header">Delivery Receipt
                        <span class="amp-hdr-right"><select id="amp-dr-pick" class="amp-input" style="width:auto !important;min-height:34px;padding:4px 10px;display:none;" title="Choose which delivery receipt of this PO to edit"></select></span>
                    </h4>
                    <input type="hidden" id="amp-dr-id" value="">
                    <div class="amp-row c2">
                        <div class="amp-group">
                            <label class="amp-label" for="amp-dr-number">DR Number <span class="req">*</span></label>
                            <input type="text" class="amp-input" id="amp-dr-number" required maxlength="100">
                        </div>
                        <div class="amp-group">
                            <label class="amp-label" for="amp-dr-date">Delivery Date <span class="req">*</span></label>
                            <input type="date" class="amp-input" id="amp-dr-date" required>
                        </div>
                    </div>

                    <div class="amp-sec-head">
                        <span class="amp-sec-title">Account Entries</span>
                        <button type="button" class="amp-add-btn" id="amp-add-entry">+ Add row</button>
                    </div>
                    <div class="amp-ent amp-ent-head"><span>Account Code <span class="req">*</span></span><span style="text-align:right;">Amount (₱) <span class="req">*</span></span><span></span></div>
                    <div id="amp-entries"></div>

                    <!-- total of the amounts; Save stays disabled while it exceeds the PO balance -->
                    <div class="amp-ent amp-ent-total">
                        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;">
                            <span class="amp-ent-total-lbl">Total</span>
                            <span class="amp-jev-pill ok" id="amp-tot-pill" style="padding:4px 10px;font-size:11px;"></span>
                        </div>
                        <div class="amp-num amp-ent-total-val" id="amp-tot-amount">0.00</div>
                        <span></span>
                    </div>
                </div>

                <!-- ── Opens when the total is 50,000 or more; all optional. Any number of items, one line each. ── -->
                <div class="amp-section" id="amp-item-box" style="display:none;">
                    <h4 class="amp-section-header">Items <span class="opt">(optional — total is 50,000 or more)</span>
                        <span class="amp-hdr-right"><button type="button" class="amp-add-btn" id="amp-add-item">+ Add item</button></span>
                    </h4>
                    <div class="amp-itm amp-itm-head"><span class="c c-desc">Description</span><span class="c c-serial">Serial No.</span><span class="c c-amt" style="text-align:right;">Amount</span><span class="c c-prop">Prop. No.</span><span class="c c-x"></span></div>
                    <div id="amp-items"></div>
                </div>
            </div>
            <div id="amp-footer">
                <button type="button" class="amp-btn amp-btn-ghost" id="amp-cancel">Cancel</button>
                <button type="submit" class="amp-btn amp-btn-navy" id="amp-save">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Confirm modal (used when the PO is changed): resolved by amp_confirm() -->
<div id="amp-confirm-overlay">
    <div id="amp-confirm-card" role="alertdialog" aria-modal="true" aria-labelledby="amp-cf-title">
        <div class="amp-cf-hdr"><h3 class="amp-cf-title" id="amp-cf-title">Change PO?</h3></div>
        <div class="amp-cf-body" id="amp-cf-body"></div>
        <div class="amp-cf-foot">
            <button type="button" class="amp-cf-btn" id="amp-cf-cancel">Cancel</button>
            <button type="button" class="amp-cf-btn primary" id="amp-cf-ok">Continue</button>
        </div>
    </div>
</div>

<!-- Read-only view of one DR (opened by clicking a DR number in the list) -->
<style>
    /* the View / Pro-forma window: the same design as the Memo Request details (dark header, light body, white cards, Close in the footer) */
    #amp-view-overlay { display:none;position:fixed;inset:0;z-index:9100;padding:16px;box-sizing:border-box;align-items:center;justify-content:center;
        background:rgba(15,32,69,.55);font-family:'IBM Plex Sans',sans-serif; }
    #amp-view-overlay.open { display:flex; }
    #amp-view-card { display:flex;flex-direction:column;width:100%;max-width:1100px;max-height:100%;overflow:hidden;border-radius:14px;
        background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text, #1A1B21);box-shadow:0 24px 64px rgba(0,0,0,.35); }
    #amp-view-card.amp-wide { max-width:1500px; }                   /* the Pro-forma JEV: room for the account, debit and credit columns */
    .amp-vw-hdr { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;background:var(--afims-heading-fill, #000E3E);color:#fff; }
    .amp-vw-title { margin:0;font-size:15px;font-weight:600;color:inherit; }
    .amp-vw-ref { margin-left:8px;font-family:'IBM Plex Mono',monospace;font-weight:600;letter-spacing:.02em;color:#C7D4F5; }
    .amp-vw-close { width:30px;height:30px;font-size:20px;line-height:1;color:#fff;cursor:pointer;border-radius:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25); }
    .amp-vw-close:hover { background:rgba(255,255,255,.22); }
    #amp-view-body { flex:1;overflow:auto;padding:18px 20px 6px; }
    .amp-vw-card { margin-bottom:14px;padding:14px 18px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:12px; }
    .amp-vw-card-title { margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--afims-text-secondary, #475569); }
    .amp-vw-row { display:grid;grid-template-columns:190px minmax(0,1fr);gap:12px;align-items:baseline;padding:4px 0;font-size:13px; }
    .amp-vw-k { font-weight:600;color:var(--afims-text-secondary, #475569); }
    .amp-vw-v { overflow-wrap:anywhere; }
    .amp-vw-code { display:inline-block;padding:2px 7px;font:600 11px/1.4 'IBM Plex Mono',monospace;color:#1D4ED8;background:var(--afims-surface, #FFFFFF);border:1px solid #E6EBF6;border-radius:4px; }
    .amp-vw-num { font:700 13px/1.4 'IBM Plex Mono',monospace;letter-spacing:.02em; }
    .amp-vw-tblwrap { overflow-x:auto;border:1px solid var(--afims-border, #E4ECF2);border-radius:10px; }
    .amp-vw-foot { display:flex;justify-content:flex-end;gap:10px;padding:12px 20px;background:var(--afims-surface, #FFFFFF);border-top:1px solid var(--afims-border, #E4ECF2); }
    .amp-vw-btn { padding:8px 18px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;border-radius:8px;color:var(--afims-text, #1A1B21);background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2); }
    .amp-vw-btn:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    @media (max-width:640px) { .amp-vw-row { grid-template-columns:1fr;gap:2px; } }
    .amp-v-meta { display:flex;gap:32px;flex-wrap:wrap;margin-bottom:18px; }
    .amp-v-meta .k { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-disabled, #A9AAB8); }
    .amp-v-meta .v { font-size:14px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .amp-v-table { width:100%;border-collapse:collapse;font-size:13px; }
    .amp-v-table th { text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;white-space:nowrap;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-v-table td { padding:9px 10px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:top; }
    .amp-tabs { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-tab { border:none;background:none;cursor:pointer;padding:9px 16px;font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;
        color:var(--afims-text-disabled, #A9AAB8);border-bottom:2px solid transparent;margin-bottom:-1px; }
    .amp-tab:hover { color:var(--afims-text, #1A1B21); }
    .amp-tab.active { color:var(--afims-primary-text, #0035A9);border-bottom-color:var(--afims-primary-text, #0035A9); }
    /* pro-forma JEV (grid layout, not a table) */
    .amp-jev { border:1px solid var(--afims-border, #E4ECF2);border-radius:12px;overflow:visible;background:var(--afims-surface-subtle, #F4F7FA);font-size:13px;
        --amp-jev-cols:minmax(0,4fr) minmax(0,1.3fr) minmax(0,1.3fr); }       /* Account Code - Account Name | Debit | Credit */
    .amp-jev-head, .amp-jev-row { display:grid;grid-template-columns:var(--amp-jev-cols);align-items:center; }
    .amp-jev-h { padding:10px 12px;font-size:12px;font-weight:700;color:var(--afims-text-secondary, #5B5E73);line-height:1.25; }
    .amp-jev-grp { border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-jev-head { border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-jev-row { padding:0;border-bottom:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF); }
    .amp-jev-row > div { padding:10px 12px; }
    .amp-jev-ctr { text-align:center; }
    .amp-jev-num { text-align:right;white-space:nowrap; }
    .amp-jev-sum { display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-top:16px;padding:14px;border-radius:12px;background:var(--afims-surface-subtle, #F4F7FA); }
    .amp-jev-card { min-width:150px;padding:12px 16px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:10px; }
    .amp-jev-card .k { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-secondary, #5B5E73);margin-bottom:4px; }
    .amp-jev-card .v { font-size:18px;font-weight:700; }
    .amp-jev-status { margin-left:auto;text-align:right; }
    .amp-jev-pill { display:inline-block;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700; }
    .amp-jev-pill.ok  { background:#D8F3E3;color:#16A34A; }
    .amp-jev-pill.bad { background:var(--afims-danger-soft, #FAE1E3);color:var(--afims-danger, #DC3545); }
    .amp-jev-hint { margin-top:6px;font-size:11px;color:var(--afims-text-secondary, #5B5E73); }
    .amp-jev-actions { display:flex;justify-content:flex-end;margin-top:14px; }
    .amp-jev-btn { height:38px;padding:0 24px;border-radius:8px;border:1px solid var(--afims-heading-fill, #000E3E);background:var(--afims-heading-fill, #000E3E);
        color:var(--afims-pure-white, #FFFFFF);font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    .amp-jev-btn:hover:not(:disabled) { opacity:.9; }
    .amp-jev-btn:disabled { opacity:.5;cursor:not-allowed; }
    .amp-jev-row { border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-jev-row:last-child { border-bottom:none; }
    .amp-jev-row > div { border-left:1px solid var(--afims-border, #E4ECF2);min-height:42px;display:flex;align-items:center; }
    .amp-jev-row > div:first-child { border-left:none; }
    .amp-jev-c1, .amp-jev-code { font-weight:700;color:var(--afims-text, #1A1B21); }
    .amp-jev-code { justify-content:center;text-align:center; }
    .amp-jev-amt { justify-content:space-between; }
    .amp-jev-row > .amp-jev-amt:nth-child(2), .amp-jev-row > .amp-jev-amt:nth-child(3) { font-weight:700;color:var(--afims-text, #1A1B21); }
    .amp-jev-row > .amp-jev-amt { font-variant-numeric:tabular-nums; }
    /* totals row: the debit and credit totals under their columns */
    .amp-jev-row.amp-jev-total { background:var(--afims-surface-subtle, #F4F7FA);border-top:2px solid var(--afims-border, #E4ECF2); }
    .amp-jev-row.amp-jev-total > div { font-weight:700;color:var(--afims-text, #1A1B21); }
    .amp-jev-row.amp-jev-total > .amp-jev-c1 { justify-content:flex-end;text-transform:uppercase;font-size:12px;letter-spacing:.05em; }
    .amp-jev-head { border-radius:11px 11px 0 0; }
    .amp-jev-row.amp-jev-total { border-radius:0 0 11px 11px; }
    /* editable pro-forma rows: account (searchable) + amount, and a remove button */
    .amp-pf-row .amp-input { min-height:36px;padding:6px 10px; }
    .amp-pf-row .amp-jev-c1 { gap:8px; }
    .amp-pf-row .amp-jev-c1 .amp-combo { flex:1;min-width:0; }
    .amp-pf-row .amp-jev-amt { gap:8px; }
    .amp-pf-row .amp-jev-amt .amp-input { flex:1;min-width:0;text-align:right; }
    .amp-pf-row .amp-pf-del { font-size:20px;line-height:1;padding:0 4px; }
    .amp-pf-top { display:flex;justify-content:flex-end;margin-bottom:10px; }
    .amp-pf-bar { display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:12px; }
    .amp-pf-msg { font-size:13px;font-weight:600;color:var(--afims-danger, #DC3545); }
    .amp-pf-msg.muted { font-weight:500;color:var(--afims-text-muted, #62657E); }
    .amp-jev-empty { padding:16px;text-align:center;color:var(--afims-text-secondary, #5B5E73); }
    @media (max-width:900px) { .amp-jev { overflow-x:auto; } .amp-jev-head, .amp-jev-row { min-width:560px; } }
    .amp-v-table tfoot td { font-weight:700;border-bottom:none; }
    /* Actual Delivery: a delivery receipt row with a dropdown of its items */
    .amp-v-toggle { width:24px;height:24px;border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;background:var(--afims-surface, #FFFFFF);
        color:var(--afims-primary-text, #0035A9);cursor:pointer;font-size:12px;line-height:1;padding:0; }
    .amp-v-toggle:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    .amp-v-count { margin-left:8px;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600;
        color:var(--afims-primary-text, #0035A9);background:color-mix(in srgb, var(--afims-primary, #0035A9) 10%, #fff); }
    .amp-v-sub > td { background:var(--afims-surface-subtle, #F4F7FA);padding:12px 16px 16px; }
    .amp-v-inner { background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .amp-v-inner th { background:var(--afims-surface-subtle, #F4F7FA); }
</style>

<div id="amp-view-overlay">
    <div id="amp-view-card" role="dialog" aria-modal="true" aria-labelledby="amp-view-title">
        <div class="amp-vw-hdr">
            <h3 class="amp-vw-title"><span id="amp-view-title">Actual Delivery</span><span class="amp-vw-ref" id="amp-view-ref"></span></h3>
            <button type="button" class="amp-vw-close" id="amp-view-close" aria-label="Close">&times;</button>
        </div>
        <div id="amp-view-body"></div>
        <div class="amp-vw-foot">
            <button type="button" class="amp-vw-btn" id="amp-view-done">Close</button>
        </div>
    </div>
</div>

<script>
document.getElementById('amp-view-close')?.addEventListener('click', () => document.getElementById('amp-view-overlay').classList.remove('open'));
document.getElementById('amp-view-done')?.addEventListener('click', () => document.getElementById('amp-view-overlay').classList.remove('open'));

const AMP_FORM_API = '<?php echo $_ampf_api; ?>' + '/po-dr';

function amp_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}

function amp_attrEsc(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}
function amp_textEsc(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
}

/* ── Number formatting (thousands separators) ──────────────── */
function amp_unfmt(v) { return String(v ?? '').replace(/,/g, '').trim(); }
function amp_fmtInt(v) { const n = parseInt(amp_unfmt(v), 10); return isNaN(n) ? '' : n.toLocaleString('en-US'); }
function amp_fmtMoney(v) {
    const n = parseFloat(amp_unfmt(v));
    return isNaN(n) ? '' : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function amp_liveFmt(v, decimals) {
    let s = amp_unfmt(v).replace(decimals ? /[^0-9.]/g : /[^0-9]/g, '');
    let dec = '';
    if (decimals) {
        const i = s.indexOf('.');
        if (i !== -1) { dec = '.' + s.slice(i + 1).replace(/\./g, '').slice(0, 2); s = s.slice(0, i); }
    }
    s = s.replace(/^0+(?=\d)/, '');
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + dec;
}

/* Today's date (local) as YYYY-MM-DD — default Delivery Date for a new line */
function amp_today() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

/* ── PO picker: searches the Budget Allocation's RFA POs (po number + PO date shown) ──
   Selecting one fills Requesting Office/Dept, Vendor, PO Date and PO Amount (requested_amount of the RFA). */
function amp_fillPo(po) {
    const set = (id, v) => { const el = document.getElementById(id); if (el) el.value = v ?? ''; };
    set('amp-po-office', po?.requesting_office || '');
    set('amp-po-vendor', po?.vendor_name || '');
    set('amp-po-date',   po?.po_date || '');
    set('amp-po-amount', po && po.po_amount !== null && po.po_amount !== undefined ? amp_fmtMoney(po.po_amount) : '');
    amp_poMoney.amount    = po && po.po_amount !== null && po.po_amount !== undefined ? Number(po.po_amount) : null;   // null = no PO limit (legacy PO)
    amp_poMoney.delivered = po ? Number(po.dr_total || 0) : 0;                                                          // total of the DRs already recorded
    amp_refreshBalance();
}

/* PO balance: what a delivery receipt may still use = PO amount - the totals of the PO's other DRs.
   When editing, the DR being edited is not counted against itself. */
const amp_poMoney = { amount: null, delivered: 0, ownDr: 0, ownPo: '' };
function amp_isEdit() { return document.getElementById('amp-mode').value === 'edit'; }
function amp_availableBalance() {
    if (amp_poMoney.amount === null) return null;
    const own = document.getElementById('amp-po-number').value === amp_poMoney.ownPo ? amp_poMoney.ownDr : 0;   // own DR only counts for its own PO
    return Math.round((amp_poMoney.amount - (amp_poMoney.delivered - own)) * 100) / 100;
}
/* The Balance field shows what is left on the PO AFTER the amounts entered in this DR (available balance - total of the rows),
   so it changes as the user edits the amounts; red when it would go below 0. */
function amp_showBalance() {
    const el = document.getElementById('amp-po-balance'), avail = amp_availableBalance();
    if (!el) return;
    if (avail === null) { el.value = ''; el.style.color = ''; return; }
    const left = Math.round((avail - amp_totals().total) * 100) / 100;
    el.value = amp_fmtMoney(left);
    el.style.color = left < 0 ? 'var(--afims-danger, #DC3545)' : (left === 0 ? '#16A34A' : '');
}
function amp_refreshBalance() {
    amp_showBalance();
    if (typeof amp_updateTotals === 'function') amp_updateTotals();
}

/* Picking a PO pre-fills the account rows from the PO's budget allocation (line_transaction: account_code + requested_amount).
   The user can change the accounts and amounts. Only on a new DR whose rows are still untouched, and the amounts are
   limited to what is left on the PO (the rows are filled in order until the balance is used up). */
function amp_prefillEntries(po, force = false) {
    if (!force && document.getElementById('amp-mode').value !== 'add') return;
    const rows = [...document.querySelectorAll('#amp-entries .amp-ent')];
    const untouched = rows.every(r => !r.querySelector('.amp-f-acct').dataset.code && !r.querySelector('.amp-f-amount').value);
    if (!force && !untouched) return;
    let left = amp_availableBalance();                       // null = no PO limit
    const fill = [];
    (po.lines || []).forEach(l => {
        let amt = Number(l.amount) || 0;
        if (left !== null) { amt = Math.min(amt, Math.max(0, left)); left = Math.round((left - amt) * 100) / 100; }
        if (amt > 0) fill.push({ account_code: l.account_code, account_title: l.account_title, amount: amt });
    });
    if (!fill.length) return;
    document.getElementById('amp-entries').innerHTML = '';
    fill.forEach(en => amp_addEntry(en));
    amp_updateTotals();
}

/* A SweetAlert2 confirm (the app loads it globally) instead of the browser's confirm(): resolves true on OK, false on Cancel / Esc / click outside.
   html = the message (already-safe HTML built here, never user text). */
function amp_confirm({ title = 'Please confirm', html = '', ok = 'Continue' } = {}) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
        return window.Swal.fire({
            title, html, icon: 'warning',
            showCancelButton: true, confirmButtonText: ok, cancelButtonText: 'Cancel',
            confirmButtonColor: '#DC3545', cancelButtonColor: '#6B7280',
            reverseButtons: true, focusCancel: true,         // Cancel is the safe default
            customClass: { container: 'amp-swal' },
        }).then(r => !!r.isConfirmed);
    }
    return new Promise(resolve => {                          // fallback: the modal built into this form
        const overlay = document.getElementById('amp-confirm-overlay');
        document.getElementById('amp-cf-title').textContent = title;
        document.getElementById('amp-cf-body').innerHTML = html;
        const okBtn = document.getElementById('amp-cf-ok'), noBtn = document.getElementById('amp-cf-cancel');
        okBtn.textContent = ok;
        const done = v => {
            overlay.classList.remove('open');
            okBtn.removeEventListener('click', onOk); noBtn.removeEventListener('click', onNo);
            overlay.removeEventListener('mousedown', onOut); document.removeEventListener('keydown', onKey, true);
            resolve(v);
        };
        const onOk = () => done(true), onNo = () => done(false);
        const onOut = e => { if (e.target === overlay) done(false); };
        const onKey = e => { if (e.key === 'Escape') { e.stopPropagation(); done(false); } };
        okBtn.addEventListener('click', onOk); noBtn.addEventListener('click', onNo);
        overlay.addEventListener('mousedown', onOut); document.addEventListener('keydown', onKey, true);
        overlay.classList.add('open');
        noBtn.focus();                                     // the safe choice has the focus
    });
}

/* Is there anything in the account rows or the item lines that a PO change would throw away? */
function amp_hasDrData() {
    const entries = [...document.querySelectorAll('#amp-entries .amp-ent')].some(r =>
        r.querySelector('.amp-f-acct').dataset.code || r.querySelector('.amp-f-amount').value.trim());
    const items = [...document.querySelectorAll('#amp-items .amp-itm')].some(r =>
        [...r.querySelectorAll('input, textarea')].some(el => el.value.trim()));
    return entries || items;
}

/* The PO was changed to another one: forget the account rows and items entered for the old PO and fill the rows again from
   the new PO's budget allocation. On a new DR the IAR number / invoice of the old PO go too (the new PO has its own IAR). */
function amp_resetForNewPo(po) {
    document.getElementById('amp-entries').innerHTML = '';
    document.getElementById('amp-items').innerHTML = '';
    amp_addEntry();                                        // one blank account row
    amp_prefillEntries(po, true);                          // replaced by the new PO's lines when it has any
    if (document.getElementById('amp-mode').value === 'add') {
        ['amp-iar-number', 'amp-si-number', 'amp-si-date'].forEach(id => { document.getElementById(id).value = ''; });
        amp_prefillIar(po.po_number);                      // the new PO's IAR number / invoice, when it already has an IAR
    }
    amp_updateTotals();
}

/* Adding a DR to a PO that already has an IAR: use that IAR number and Sales Invoice details */
async function amp_prefillIar(poNumber) {
    if (document.getElementById('amp-mode').value !== 'add') return;
    try {
        const res  = await fetch(AMP_FORM_API + '/read?po_number=' + encodeURIComponent(poNumber), { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (!json.success || !json.data) return;      // no IAR yet for this PO: the user enters a new IAR number
        document.getElementById('amp-iar-number').value = json.data.iar_number || '';
        if (json.data.si_number) document.getElementById('amp-si-number').value = json.data.si_number;
        if (json.data.si_date)   document.getElementById('amp-si-date').value   = json.data.si_date;
    } catch (e) { /* leave the fields as they are */ }
}

const amp_poState = { valid: '' };   // the last PO number picked from the list (or loaded on edit)
function amp_setPoValue(po) {        // programmatic set (add → clear, edit → existing PO)
    const input = document.getElementById('amp-po-number');
    input.value = amp_poState.valid = po?.po_number || '';
    amp_poMoney.ownPo = po?.po_number || '';
    amp_fillPo(po);
}

(function amp_initPoCombo() {
    const wrap = document.getElementById('amp-po-combo');
    if (!wrap) return;
    const input = wrap.querySelector('#amp-po-number'), list = wrap.querySelector('.amp-combo-list');
    let typed = false, timer = null, seq = 0, hits = [];
    const render = () => {
        list.innerHTML = hits.length
            ? hits.map((p, i) => '<div class="amp-combo-opt' + (p.po_number === amp_poState.valid ? ' active' : '') + '" data-i="' + i + '">'
                + '<strong>' + amp_textEsc(p.po_number) + '</strong>'
                + '<span style="float:right;color:var(--afims-text-disabled,#6b7280);font-size:12px;margin-left:12px;">' + amp_textEsc(p.po_date || '') + '</span></div>').join('')
            : '<div class="amp-combo-empty">No matching PO</div>';
    };
    const load = async () => {
        const mine = ++seq;
        try {
            const q = typed ? input.value.trim() : '';
            const res = await fetch(AMP_FORM_API + '/pos?search=' + encodeURIComponent(q), { credentials: 'same-origin', headers: amp_authHeaders() });
            const json = await res.json();
            if (mine !== seq) return;                       // a newer search is already running
            hits = json.success ? (json.data || []) : [];
        } catch (e) { if (mine !== seq) return; hits = []; }
        render();
    };
    const open = () => { if (input.readOnly) return; wrap.classList.add('open'); render(); load(); };      // the PO cannot be changed while editing
    const pick = async p => {
        const changing = !!amp_poState.valid && amp_poState.valid !== p.po_number;      // another PO than the one already chosen
        if (changing && amp_hasDrData()) {
            wrap.classList.remove('open');
            const yes = await amp_confirm({
                title : 'Change the PO?',
                html  : '<p>You are changing the PO from <strong>' + amp_textEsc(amp_poState.valid) + '</strong> to <strong>' + amp_textEsc(p.po_number) + '</strong>.</p>'
                      + '<div class="amp-cf-warn"><span>&#9888;</span><span>The account entries and items already added for this delivery receipt will be removed, '
                      + 'and the entries will be filled again from the new PO.</span></div>',
                ok    : 'Change PO',
            });
            if (!yes) {                                     // keep the old PO and everything entered
                input.value = amp_poState.valid;
                typed = false;
                return;
            }
        }
        input.value = amp_poState.valid = p.po_number;
        amp_fillPo(p);
        typed = false;
        wrap.classList.remove('open');
        if (changing) { amp_resetForNewPo(p); }
        else { amp_prefillIar(p.po_number); amp_prefillEntries(p); }
    };
    input.addEventListener('focus', () => { typed = false; input.select(); open(); });
    input.addEventListener('click', () => { if (!wrap.classList.contains('open')) open(); });
    input.addEventListener('input', () => { typed = true; wrap.classList.add('open'); clearTimeout(timer); timer = setTimeout(load, 250); });
    list.addEventListener('mousedown', e => {
        const opt = e.target.closest('.amp-combo-opt');
        if (!opt) return;
        e.preventDefault();
        pick(hits[+opt.dataset.i]);
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') wrap.classList.remove('open');
        if (e.key === 'Enter' && wrap.classList.contains('open')) {
            e.preventDefault();
            if (hits[0]) pick(hits[0]);
        }
    });
    // only a PO picked from the list is kept: half-typed text goes back to the last selection
    input.addEventListener('blur', () => {
        wrap.classList.remove('open');
        typed = false;
        if (input.value !== amp_poState.valid) {
            input.value = amp_poState.valid;
            if (!amp_poState.valid) amp_fillPo(null);
        }
    });
})();


/* ── Delivery receipt: DR no. / date + account rows (account code, amount) ──
   One DR per save. The total of the amounts may not exceed the PO balance.
   From AMP_ITEM_MIN_AMOUNT up, the optional item fields (description, serial, amount, property no.) open. */
const AMP_ITEM_MIN_AMOUNT = 50000;   // keep in sync with PoDrService::ITEM_MIN_AMOUNT
let amp_receipts = [];               // the PO's delivery receipts (edit mode), for the DR picker

/* Account Code: searchable over the whole chart of accounts (GET /po-dr/accounts?search=) */
function amp_initAcctCombo(wrap) {
    const input = wrap.querySelector('.amp-f-acct'), list = wrap.querySelector('.amp-combo-list');
    let typed = false, timer = null, seq = 0, hits = [];
    const label = a => a.account_code + ' - ' + a.account_title;
    const render = () => {
        list.innerHTML = hits.length
            ? hits.map((a, i) => '<div class="amp-combo-opt' + (a.account_code === input.dataset.code ? ' active' : '') + '" data-i="' + i + '">' + amp_textEsc(label(a)) + '</div>').join('')
            : '<div class="amp-combo-empty">No matching account code</div>';
    };
    const load = async () => {
        const mine = ++seq;
        try {
            const q = typed ? input.value.trim() : '';
            const res = await fetch(AMP_FORM_API + '/accounts?search=' + encodeURIComponent(q), { credentials: 'same-origin', headers: amp_authHeaders() });
            const json = await res.json();
            if (mine !== seq) return;
            hits = json.success ? (json.data || []) : [];
        } catch (e) { if (mine !== seq) return; hits = []; }
        render();
    };
    const open = () => { if (input.readOnly) return; wrap.classList.add('open'); render(); load(); };      // locked while editing
    const pick = a => {
        input.value = input.dataset.label = label(a);
        input.dataset.code = a.account_code;
        typed = false;
        wrap.classList.remove('open');
    };
    input.addEventListener('focus', () => { typed = false; input.select(); open(); });
    input.addEventListener('click', () => { if (!wrap.classList.contains('open')) open(); });
    input.addEventListener('input', () => { typed = true; wrap.classList.add('open'); clearTimeout(timer); timer = setTimeout(load, 250); });
    list.addEventListener('mousedown', e => {
        const opt = e.target.closest('.amp-combo-opt');
        if (!opt) return;
        e.preventDefault();
        pick(hits[+opt.dataset.i]);
        amp_updateTotals();
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') wrap.classList.remove('open');
        if (e.key === 'Enter' && wrap.classList.contains('open')) {
            e.preventDefault();
            if (hits[0]) { pick(hits[0]); amp_updateTotals(); }
        }
    });
    // only an account picked from the list is kept: half-typed text goes back to the last selection
    input.addEventListener('blur', () => {
        wrap.classList.remove('open');
        typed = false;
        if (input.value !== (input.dataset.label || '')) input.value = input.dataset.label || '';
        if (!input.value) input.dataset.code = '';
        amp_updateTotals();
    });
}

function amp_addEntry(en = {}) {
    const box = document.getElementById('amp-entries');
    const el  = document.createElement('div');
    el.className = 'amp-ent';
    const a = Number(en.amount || 0);
    const lbl = en.account_code ? en.account_code + (en.account_title ? ' - ' + en.account_title : '') : '';
    el.innerHTML = `
        <div class="amp-combo">
            <input type="text" class="amp-input amp-f-acct" placeholder="Search account code or title" value="${amp_attrEsc(lbl)}"
                   data-code="${amp_attrEsc(en.account_code || '')}" data-label="${amp_attrEsc(lbl)}">
            <div class="amp-combo-list"></div>
        </div>
        <input type="text" inputmode="decimal" class="amp-input amp-f-amount amp-num" placeholder="0.00" value="${a > 0 ? amp_attrEsc(amp_fmtMoney(a)) : ''}">
        <button type="button" class="amp-link-danger amp-ent-remove" title="Remove row">&times;</button>`;
    amp_initAcctCombo(el.querySelector('.amp-combo'));
    if (amp_isEdit()) { el.querySelectorAll('input').forEach(i => { i.readOnly = true; i.tabIndex = -1; }); }   // account rows cannot be edited
    const amt = el.querySelector('.amp-f-amount');
    amt.addEventListener('input', () => { amt.value = amp_liveFmt(amt.value, true); amp_updateTotals(); });
    amt.addEventListener('blur', () => { if (amt.value) amt.value = amp_fmtMoney(amp_unfmt(amt.value)); });
    el.querySelector('.amp-ent-remove').addEventListener('click', () => { el.remove(); amp_updateTotals(); });
    box.appendChild(el);
    amp_updateTotals();
    return el;
}

/* total of the amounts (in cents, so the sum is exact) */
function amp_totals() {
    let cents = 0;
    document.querySelectorAll('#amp-entries .amp-ent').forEach(r => {
        cents += Math.round((parseFloat(amp_unfmt(r.querySelector('.amp-f-amount').value)) || 0) * 100);
    });
    return { total: cents / 100 };
}

/* message when the DR can't be submitted yet, '' when it can. The limit is the PO balance (no debit/credit to balance). */
function amp_drError() {
    const rows = [...document.querySelectorAll('#amp-entries .amp-ent')];
    if (rows.length < 1) return 'Add at least one account row.';
    for (let i = 0; i < rows.length; i++) {
        const r = rows[i], a = parseFloat(amp_unfmt(r.querySelector('.amp-f-amount').value)) || 0;
        if (!r.querySelector('.amp-f-acct').dataset.code) return 'Row ' + (i + 1) + ': please choose an account code.';
        if (a <= 0) return 'Row ' + (i + 1) + ': enter an amount greater than 0.';
    }
    const avail = amp_availableBalance(), total = amp_totals().total;
    if (!amp_isEdit() && avail !== null && Math.round(total * 100) > Math.round(avail * 100)) {   // an edit never changes the total
        return 'The total (' + amp_fmtMoney(total) + ') exceeds the PO balance (' + amp_fmtMoney(Math.max(0, avail)) + ').';
    }
    return '';
}

function amp_updateTotals() {
    const total = amp_totals().total;
    const amtEl = document.getElementById('amp-tot-amount');
    if (amtEl) amtEl.textContent = amp_fmtMoney(total) || '0.00';
    amp_showBalance();                                       // keep the Balance field in step with the amounts
    const avail = amp_availableBalance(), over = !amp_isEdit() && avail !== null && Math.round(total * 100) > Math.round(avail * 100);
    const pill = document.getElementById('amp-tot-pill');
    if (pill) {
        pill.className = 'amp-jev-pill ' + (over ? 'bad' : 'ok');
        pill.innerHTML = over ? '&#10006; Exceeds PO balance' : '';          // only the warning is shown; nothing when the total is within the balance
        pill.style.display = pill.innerHTML ? '' : 'none';
    }
    // the optional item fields open from 50,000
    const box = document.getElementById('amp-item-box');
    if (box) {
        box.style.display = total >= AMP_ITEM_MIN_AMOUNT ? '' : 'none';
        if (box.style.display === '' && !document.querySelector('#amp-items .amp-itm')) amp_addItem();   // start with one blank item line
    }
    // can't submit while a row is incomplete or the total is over the PO balance
    const save = document.getElementById('amp-save');
    if (save) save.disabled = !!amp_drError();
}

/* Fill the DR part of the form: dr = a delivery receipt of the PO (edit) or {} (new) */
function amp_loadDr(dr = {}) {
    const set = (id, v) => { document.getElementById(id).value = v ?? ''; };
    set('amp-dr-id', dr.dr_number || '');                   // reference: the DR number as loaded (blank for a new DR)
    amp_poMoney.ownDr = Number(dr.total_amount || 0);      // this DR's current total is part of the PO's delivered amount while editing
    set('amp-dr-number', dr.dr_number || '');
    set('amp-dr-date', dr.delivery_date || amp_today());
    document.getElementById('amp-entries').innerHTML = '';
    const entries = dr.entries?.length ? dr.entries : [{}];            // a new DR starts with one account row
    entries.forEach(en => amp_addEntry(en));
    document.getElementById('amp-items').innerHTML = '';
    (dr.items || []).forEach(it => amp_addItem(it));         // the DR's saved items, one line each
    amp_refreshBalance();
}

/* Set up the DR part when the modal opens: add → blank DR; edit → a picker over the PO's DRs */
function amp_initDrForm(row) {
    const edit = amp_isEdit();
    document.getElementById('amp-form').classList.toggle('amp-edit', edit);          // locks the PO + account rows (CSS above)
    document.getElementById('amp-po-number').readOnly = edit;
    amp_receipts = row?.receipts || [];
    const pick = document.getElementById('amp-dr-pick');
    if (row && amp_receipts.length) {
        pick.innerHTML = amp_receipts.map((r, i) => '<option value="' + i + '">' + amp_textEsc(r.dr_number) + '</option>').join('');
        pick.style.display = '';
        amp_loadDr(amp_receipts[0]);
    } else {
        pick.style.display = 'none';
        pick.innerHTML = '';
        amp_loadDr({});
    }
}
document.getElementById('amp-dr-pick')?.addEventListener('change', e => amp_loadDr(amp_receipts[+e.target.value] || {}));
document.getElementById('amp-add-entry')?.addEventListener('click', () => {
    const el = amp_addEntry();
    el.querySelector('.amp-f-acct').focus();
});
/* One optional item = one line: Description | Serial No. | Amount | Prop. No. */
function amp_addItem(it = {}) {
    const box = document.getElementById('amp-items');
    const el  = document.createElement('div');
    el.className = 'amp-itm';
    const a = Number(it.amount || 0);
    el.innerHTML = `
        <div class="c c-desc"><textarea class="amp-input amp-i-desc" rows="2" placeholder="Item description">${amp_textEsc(it.description)}</textarea></div>
        <div class="c c-serial"><input type="text" class="amp-input amp-i-serial" maxlength="100" value="${amp_attrEsc(it.serial_no)}"></div>
        <div class="c c-amt"><input type="text" inputmode="decimal" class="amp-input amp-num amp-i-amount" placeholder="0.00" value="${a > 0 ? amp_attrEsc(amp_fmtMoney(a)) : ''}"></div>
        <div class="c c-prop"><input type="text" class="amp-input amp-i-prop" maxlength="100" value="${amp_attrEsc(it.property_no)}"></div>
        <div class="c c-x"><button type="button" class="amp-link-danger amp-ent-remove" title="Remove item">&times;</button></div>`;
    const amt = el.querySelector('.amp-i-amount');
    amt.addEventListener('input', () => { amt.value = amp_liveFmt(amt.value, true); });
    amt.addEventListener('blur', () => { if (amt.value) amt.value = amp_fmtMoney(amp_unfmt(amt.value)); });
    el.querySelector('.amp-ent-remove').addEventListener('click', () => { el.remove(); });
    box.appendChild(el);
    return el;
}
document.getElementById('amp-add-item')?.addEventListener('click', () => amp_addItem().querySelector('.amp-i-desc').focus());

/* ── Collect the DR into the API payload ───────────────────── */
function amp_collectDr() {
    const v = id => document.getElementById(id).value.trim();
    const dr = {
        dr_ref        : v('amp-dr-id') || null,
        dr_number     : v('amp-dr-number'),
        delivery_date : v('amp-dr-date'),
        entries       : [...document.querySelectorAll('#amp-entries .amp-ent')].map(r => ({
            account_code : r.querySelector('.amp-f-acct').dataset.code || '',
            amount       : amp_unfmt(r.querySelector('.amp-f-amount').value),
        })),
    };
    if (document.getElementById('amp-item-box').style.display !== 'none') {       // only when the optional items are open
        dr.items = [...document.querySelectorAll('#amp-items .amp-itm')].map(r => ({
            description : r.querySelector('.amp-i-desc').value.trim(),
            serial_no   : r.querySelector('.amp-i-serial').value.trim(),
            amount      : amp_unfmt(r.querySelector('.amp-i-amount').value),
            property_no : r.querySelector('.amp-i-prop').value.trim(),
        }));
    }
    return dr;
}
</script>
