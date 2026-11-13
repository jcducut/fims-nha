<?php
namespace PHPMaker2024\FIMS;

// Purchase Order ADD / EDIT modal — a partial included by app/pages/po/page.php (not a page of its own).
// Ported from GLOBAL/includes/inc.po-form.php, trimmed to the `fims` schema: no parent PR /
// allocation / fund source / obligation. Vendors come from fims.vendors via /vendors/all.
//
// API:  pof_open()      → new PO
//       pof_open(id)    → edit PO #id (DRAFT / REJECTED only)
//       pof_close()
// Hook: window.pof_onSaved = (data) => {...}   called after a successful save (the list page reloads itself).
//
// Fonts and nha-fims.css are loaded by the including page.
if (!isset($_pof_api)) {
    // API.BASE_URL is the site root; the REST routes live under /api (added unless the env value already ends in it).
    $_pof_api = rtrim((string) Config('API.FIMS_URL'), '/');
    $_pof_api = preg_match('~/api$~', $_pof_api) ? $_pof_api : $_pof_api . '/api';
}
?>

<style>
/* The modal CSS in nha-fims.css is scoped under .nha-root, so the overlay lives inside its own .nha-root. */
#pof-root .pof-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
#pof-root .pof-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#pof-root .pof-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
#pof-root .pof-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
#pof-root .pof-span2 { grid-column: span 2; }
#pof-root .pof-hint  { font-size: 11px; color: var(--afims-text-muted, #62657E); margin-top: 3px; }
#pof-root .pof-err   { display: none; font-size: 11px; color: var(--afims-danger, #DC3545); margin-top: 3px; }

#pof-root .pof-number { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px; margin-bottom: 14px;
    border: 2px solid var(--afims-primary-soft, #E6EBF6); border-radius: 12px;
    background: linear-gradient(to right, var(--afims-primary-soft, #E6EBF6), var(--afims-surface, #fff)); }
#pof-root .pof-number small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
#pof-root .pof-number strong { font-family: "IBM Plex Mono", monospace; font-size: 20px; color: var(--afims-primary-text, #0035A9); }

#pof-root .pof-vendor-card { display: none; margin-top: 10px; padding: 12px 16px; gap: 20px; flex-wrap: wrap;
    background: var(--afims-success-soft, #DBF2E5); border: 1px solid var(--afims-success-soft-hover, #C4E9D4); border-radius: 10px; }
#pof-root .pof-vendor-card.visible { display: flex; }
#pof-root .pof-vendor-card label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-success-hover, #0C7539); }
#pof-root .pof-vendor-card span { font-size: 13px; font-weight: 600; color: var(--afims-success-hover, #0C7539); }

#pof-root .pof-items-wrap { overflow-x: auto; }
#pof-root .pof-items { width: 100%; min-width: 760px; border-collapse: collapse; }
#pof-root .pof-items th { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-muted, #62657E); font-size: 10px; font-weight: 700;
    text-transform: uppercase; letter-spacing: .06em; padding: 9px 10px; text-align: left; border-bottom: 1px solid var(--afims-border, #E4ECF2); white-space: nowrap; }
#pof-root .pof-items td { padding: 7px 10px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); vertical-align: middle; }
#pof-root .pof-items tfoot td { background: var(--afims-surface-subtle, #F4F7FA); border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; padding: 10px; }
#pof-root .pof-items input { width: 100%; font-size: 12.5px; padding: 6px 9px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 7px; box-sizing: border-box; font-family: inherit; }
#pof-root .pof-line-total, #pof-root .pof-grand { font-family: "IBM Plex Mono", monospace; font-weight: 700; white-space: nowrap; }
#pof-root .pof-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); padding: 4px 6px; border-radius: 5px; font-size: 16px; line-height: 1; }
#pof-root .pof-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#pof-root .pof-add-item { margin-top: 10px; }

#pof-root .pof-errors { display: none; margin-bottom: 14px; padding: 12px 16px; background: var(--afims-danger-soft, #FAE1E3);
    border: 1px solid var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 10px; }
#pof-root .pof-errors.visible { display: block; }
#pof-root .pof-errors ul { margin: 6px 0 0; padding-left: 18px; font-size: 13px; color: var(--afims-danger-text, #8F222D); line-height: 1.7; }

@media (max-width: 768px) {
    #pof-root .pof-grid3, #pof-root .pof-grid2 { grid-template-columns: 1fr; }
    #pof-root .pof-span2 { grid-column: span 1; }
}
</style>

<div id="pof-root" class="nha-root">
    <div id="pof-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="pof-modal-title">

            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="pof-modal-title">New Purchase Order</h3>
                <button type="button" class="nha-modal-close" onclick="pof_close()" aria-label="Close">&times;</button>
            </div>

            <div class="nha-modal-body">

                <div class="pof-number">
                    <div><small>PO Number</small><strong id="pof-po-number">—</strong></div>
                    <div style="text-align:right;"><small>Status</small><span class="nha-bdg nha-bdg-gray" id="pof-status">DRAFT</span></div>
                </div>

                <div class="pof-errors" id="pof-errors">
                    <strong style="font-size:13px;color:var(--afims-danger,#DC3545);">Please fix the following before saving:</strong>
                    <ul id="pof-errors-ul"></ul>
                </div>

                <!-- 1 — Document identity -->
                <div class="pof-card">
                    <p class="pof-card-title">Document Identity</p>
                    <div class="pof-grid3">
                        <div class="nha-fg">
                            <label>PO Date <span class="nha-req">*</span></label>
                            <input type="date" id="pof-po-date">
                            <span class="pof-err" id="err-po-date">PO Date is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Fiscal Year <span class="nha-req">*</span></label>
                            <select id="pof-fiscal-yr"><option value="">Loading…</option></select>
                            <span class="pof-err" id="err-fiscal-yr">Fiscal year is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Expense Class</label>
                            <select id="pof-expense-class">
                                <option value="">— Select —</option>
                                <option value="MOOE">MOOE</option>
                                <option value="PS">PS</option>
                                <option value="CAPITAL_OUTLAY">Capital Outlay</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="pof-grid2">
                        <div class="nha-fg">
                            <label>Program</label>
                            <select id="pof-program"><option value="">— None —</option></select>
                        </div>
                        <div class="nha-fg">
                            <label>Project</label>
                            <select id="pof-project"><option value="">— None —</option></select>
                            <span class="pof-hint">Picking a project sets its program automatically.</span>
                        </div>
                    </div>
                </div>

                <!-- 2 — Vendor -->
                <div class="pof-card">
                    <p class="pof-card-title">Vendor / Supplier</p>
                    <div class="pof-grid2">
                        <div class="nha-fg">
                            <label>Vendor <span class="nha-req">*</span></label>
                            <select id="pof-vendor"><option value="">Loading vendors…</option></select>
                            <span class="pof-err" id="err-vendor">Please select a vendor.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Contract Number</label>
                            <input type="text" id="pof-contract-number" maxlength="100" placeholder="If contract-backed">
                        </div>
                    </div>
                    <div class="pof-vendor-card" id="pof-vendor-card">
                        <div><label>Vendor Name</label><span id="pof-vc-name">—</span></div>
                        <div><label>Code</label><span id="pof-vc-code">—</span></div>
                        <div><label>TIN</label><span id="pof-vc-tin">—</span></div>
                        <div><label>Type</label><span id="pof-vc-type">—</span></div>
                        <div><label>Address</label><span id="pof-vc-address">—</span></div>
                    </div>
                </div>

                <!-- 3 — Delivery & payment -->
                <div class="pof-card">
                    <p class="pof-card-title">Delivery &amp; Payment Terms</p>
                    <div class="pof-grid3">
                        <div class="nha-fg pof-span2">
                            <label>Delivery Place</label>
                            <input type="text" id="pof-delivery-place" placeholder="e.g. NHA Main Office, Elliptical Road, Diliman, QC">
                        </div>
                        <div class="nha-fg">
                            <label>Delivery Date Required</label>
                            <input type="date" id="pof-delivery-date">
                        </div>
                    </div>
                    <div class="pof-grid2">
                        <div class="nha-fg">
                            <label>Delivery Terms</label>
                            <input type="text" id="pof-delivery-terms" placeholder="e.g. Within 30 days upon receipt of PO">
                        </div>
                        <div class="nha-fg">
                            <label>Payment Terms</label>
                            <input type="text" id="pof-payment-terms" placeholder="e.g. 30 days net after acceptance">
                        </div>
                    </div>
                </div>

                <!-- 4 — Line items -->
                <div class="pof-card">
                    <p class="pof-card-title">Line Items <span id="pof-item-count" class="nha-bdg nha-bdg-blue" style="margin-left:6px;">0 items</span></p>
                    <div class="pof-items-wrap">
                        <table class="pof-items">
                            <thead>
                                <tr>
                                    <th style="width:36px;">#</th>
                                    <th>Description <span class="nha-req">*</span></th>
                                    <th style="width:90px;">UOM <span class="nha-req">*</span></th>
                                    <th style="width:110px;">Qty <span class="nha-req">*</span></th>
                                    <th style="width:140px;">Unit Cost <span class="nha-req">*</span></th>
                                    <th style="width:150px;">Total Cost</th>
                                    <th>Remarks</th>
                                    <th style="width:36px;"></th>
                                </tr>
                            </thead>
                            <tbody id="pof-items-tbody"></tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="5" style="text-align:right;font-size:12px;font-weight:700;text-transform:uppercase;color:var(--afims-text-muted,#62657E);">Grand Total</td>
                                    <td colspan="3"><span class="pof-grand" id="pof-grand">₱ 0.00</span></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <button type="button" class="nha-btn nha-btn-ghost pof-add-item" onclick="pof_addItem()">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add Item
                    </button>
                    <span class="pof-err" id="err-items">At least one line item is required.</span>
                </div>

                <!-- 5 — Particulars -->
                <div class="pof-card" style="margin-bottom:0;">
                    <p class="pof-card-title">Particulars &amp; Remarks</p>
                    <div class="pof-grid2">
                        <div class="nha-fg">
                            <label>Particulars</label>
                            <textarea id="pof-particulars" rows="3" placeholder="Brief description of goods or services to be procured…"></textarea>
                        </div>
                        <div class="nha-fg">
                            <label>Remarks</label>
                            <textarea id="pof-remarks" rows="3" placeholder="Optional remarks or special instructions…"></textarea>
                        </div>
                    </div>
                </div>

            </div><!-- /.nha-modal-body -->

            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="pof_close()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-outline" id="pof-btn-draft"  onclick="pof_save('DRAFT')">Save as Draft</button>
                <button type="button" class="nha-btn nha-btn-primary" id="pof-btn-submit" onclick="pof_save('SUBMITTED')">Save &amp; Submit</button>
            </div>

        </div>
    </div>
</div><!-- /#pof-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PURCHASE ORDER FORM MODAL   prefix: pof_
═══════════════════════════════════════════════════════════════ */

const POF_API      = <?= json_encode($_pof_api) ?>;
const POF_EDITABLE = ['DRAFT', 'REJECTED'];

const $pof = id => document.getElementById(id);
let pof_id       = 0;          // 0 = add mode, otherwise the po_id being edited
let pof_itemSeq  = 0;
let pof_saving   = false;
let pof_projects = [];         // every project, for filtering by the selected program
let pof_vendors  = {};         // vendor_id -> row, for the summary card
let pof_refReady = null;       // promise: fiscal years + programs/projects, loaded once on first open
let pof_bound    = false;

function pof_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function pof_getJson(path) {
    const res  = await pof_authFetch(POF_API + path);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

/* ── Open / close ──────────────────────────────────────────── */
async function pof_open(id = 0) {
    pof_id = Number(id) || 0;
    pof_reset();
    $pof('pof-modal-title').textContent = pof_id ? 'Edit Purchase Order' : 'New Purchase Order';
    $pof('pof-overlay').classList.add('open');

    if (!pof_bound) { pof_bindEvents(); pof_bound = true; }

    window.PagePreloader?.show();
    try {
        // Vendors are re-read on every open so a vendor added since the last open shows up.
        pof_refReady ??= Promise.all([pof_loadFiscalYears(), pof_loadPrograms()]);
        await Promise.all([pof_refReady, pof_loadVendors()]);

        if (pof_id) {
            await pof_loadExisting();
        } else {
            $pof('pof-fiscal-yr').value = [...$pof('pof-fiscal-yr').options].some(o => o.value === String(new Date().getFullYear()))
                ? String(new Date().getFullYear()) : '';
            pof_addItem();
            pof_refreshPoNumber();
        }
    } catch (e) {
        pof_showErrors([e.message]);
    } finally {
        window.PagePreloader?.hide();
    }
}

function pof_close() {
    if (pof_saving) return;
    $pof('pof-overlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $pof('pof-overlay')?.classList.contains('open')) pof_close();
});

function pof_reset() {
    pof_showErrors([]);
    ['po-date', 'fiscal-yr', 'vendor'].forEach(pof_clearFieldError);
    pof_setFieldError('items', false);
    $pof('pof-po-date').value         = new Date().toISOString().slice(0, 10);
    $pof('pof-fiscal-yr').value       = '';
    $pof('pof-expense-class').value   = '';
    $pof('pof-program').value         = '';
    pof_fillProjects('');
    $pof('pof-vendor').value          = '';
    $pof('pof-vendor-card').classList.remove('visible');
    ['contract-number', 'delivery-place', 'delivery-date', 'delivery-terms', 'payment-terms', 'particulars', 'remarks']
        .forEach(f => { $pof('pof-' + f).value = ''; });
    $pof('pof-items-tbody').innerHTML = '';
    pof_itemSeq = 0;
    pof_recalc();
    $pof('pof-po-number').textContent = '—';
    $pof('pof-status').textContent    = 'DRAFT';
    $pof('pof-btn-draft').disabled = $pof('pof-btn-submit').disabled = false;
}

function pof_bindEvents() {
    $pof('pof-po-date').addEventListener('change', () => { if (!pof_id) pof_refreshPoNumber(); });
    $pof('pof-vendor').addEventListener('change', pof_onVendorChange);
    $pof('pof-program').addEventListener('change', pof_onProgramChange);
    $pof('pof-project').addEventListener('change', pof_onProjectChange);
}

/* ── Reference data ────────────────────────────────────────── */
async function pof_loadFiscalYears() {
    const sel = $pof('pof-fiscal-yr');
    try {
        const json = await pof_getJson('/fiscal_year/all');
        sel.innerHTML = '<option value="">Select fiscal year</option>' + (json.data ?? []).map(r =>
            `<option value="${pof_esc(r.fiscal_yr)}">${pof_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load</option>';
        pof_refReady = null;                       // retry on next open
        throw new Error('Failed to load fiscal years: ' + e.message);
    }
}

async function pof_loadPrograms() {
    try {
        const [prog, proj] = await Promise.all([pof_getJson('/program/all'), pof_getJson('/project/all')]);
        pof_projects = proj.data ?? [];
        $pof('pof-program').innerHTML = '<option value="">— None —</option>' + (prog.data ?? []).map(p =>
            `<option value="${pof_esc(p.program_code)}">${pof_esc(p.program_code)} — ${pof_esc(p.program_name)}</option>`).join('');
        pof_fillProjects('');
    } catch (e) {
        pof_refReady = null;
        throw new Error('Failed to load programs / projects: ' + e.message);
    }
}

function pof_fillProjects(programCode, keep = '') {
    const list = programCode ? pof_projects.filter(p => p.program_code === programCode) : pof_projects;
    $pof('pof-project').innerHTML = '<option value="">— None —</option>' + list.map(p =>
        `<option value="${pof_esc(p.project_code)}">${pof_esc(p.project_code)} — ${pof_esc(p.project_name)}</option>`).join('');
    $pof('pof-project').value = list.some(p => p.project_code === keep) ? keep : '';
}

function pof_onProgramChange() {
    pof_fillProjects($pof('pof-program').value, $pof('pof-project').value);
}

function pof_onProjectChange() {
    const proj = pof_projects.find(p => p.project_code === $pof('pof-project').value);
    if (proj) $pof('pof-program').value = proj.program_code;
}

async function pof_loadVendors() {
    const sel = $pof('pof-vendor');
    try {
        const json = await pof_getJson('/vendors/all?active=1');
        pof_vendors = {};
        (json.data ?? []).forEach(v => { pof_vendors[v.vendor_id] = v; });
        sel.innerHTML = '<option value="">— Select Vendor —</option>' + (json.data ?? []).map(v =>
            `<option value="${pof_esc(v.vendor_id)}">${pof_esc(v.vendor_code)} — ${pof_esc(v.vendor_name)}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load vendors</option>';
        throw new Error('Failed to load vendors: ' + e.message);
    }
}

function pof_onVendorChange() {
    const v = pof_vendors[$pof('pof-vendor').value];
    const card = $pof('pof-vendor-card');
    if (!v) { card.classList.remove('visible'); return; }
    $pof('pof-vc-name').textContent    = v.vendor_name || '—';
    $pof('pof-vc-code').textContent    = v.vendor_code || '—';
    $pof('pof-vc-tin').textContent     = v.tin || '—';
    $pof('pof-vc-type').textContent    = v.vendor_type || '—';
    $pof('pof-vc-address').textContent = v.address || '—';
    card.classList.add('visible');
    pof_clearFieldError('vendor');
}

async function pof_refreshPoNumber() {
    const d = $pof('pof-po-date').value;
    if (!d) return;
    try {
        const json = await pof_getJson('/purchase-order/po-number?po_date=' + encodeURIComponent(d));
        $pof('pof-po-number').textContent = json.data.po_number;
    } catch (e) { /* the server assigns a number on save if this stays "—" */ }
}

/* ── Edit mode ─────────────────────────────────────────────── */
async function pof_loadExisting() {
    const json = await pof_getJson('/purchase-order/read/' + pof_id);
    const po   = json.data;
    if (!POF_EDITABLE.includes(po.status)) {
        pof_showErrors([`This purchase order is ${po.status} and can no longer be edited.`]);
        $pof('pof-btn-draft').disabled = $pof('pof-btn-submit').disabled = true;
    }

    $pof('pof-po-number').textContent = po.po_number;
    $pof('pof-status').textContent    = po.status;
    $pof('pof-po-date').value         = String(po.po_date).slice(0, 10);
    $pof('pof-fiscal-yr').value       = po.fiscal_yr;
    $pof('pof-expense-class').value   = po.expense_class || '';
    $pof('pof-program').value         = po.program_code || '';
    pof_fillProjects(po.program_code || '', po.project_code || '');

    // A vendor that went inactive since this PO was saved isn't in the active list — keep it selectable.
    if (po.vendor_id && !pof_vendors[po.vendor_id]) {
        pof_vendors[po.vendor_id] = { vendor_id: po.vendor_id, vendor_code: po.vendor_code, vendor_name: po.vendor_name, tin: po.vendor_tin, address: po.vendor_address, vendor_type: po.vendor_type };
        $pof('pof-vendor').insertAdjacentHTML('beforeend',
            `<option value="${pof_esc(po.vendor_id)}">${pof_esc(po.vendor_code || '')} — ${pof_esc(po.vendor_name)} (inactive)</option>`);
    }
    $pof('pof-vendor').value = po.vendor_id;
    pof_onVendorChange();

    $pof('pof-contract-number').value = po.contract_number || '';
    $pof('pof-delivery-place').value  = po.delivery_place || '';
    $pof('pof-delivery-date').value   = po.delivery_date_required ? String(po.delivery_date_required).slice(0, 10) : '';
    $pof('pof-delivery-terms').value  = po.delivery_terms || '';
    $pof('pof-payment-terms').value   = po.payment_terms || '';
    $pof('pof-particulars').value     = po.particulars || '';
    $pof('pof-remarks').value         = po.remarks || '';

    $pof('pof-items-tbody').innerHTML = '';
    (po.items ?? []).forEach(it => pof_addItem(it));
    if (!(po.items ?? []).length) pof_addItem();
}

/* ── Line items ────────────────────────────────────────────── */
function pof_addItem(d = null) {
    const idx = ++pof_itemSeq;
    const tr  = document.createElement('tr');
    tr.id = `pof-row-${idx}`;
    tr.innerHTML = `
        <td class="pof-seq" style="text-align:center;color:var(--afims-text-disabled,#A9AAB8);font-size:11px;font-weight:600;"></td>
        <td><input type="text" class="pof-desc" placeholder="Item / service description"></td>
        <td><input type="text" class="pof-uom"  placeholder="pcs"></td>
        <td><input type="number" class="pof-qty"  min="0.0001" step="0.0001" placeholder="0"></td>
        <td><input type="number" class="pof-cost" min="0" step="0.01" placeholder="0.00"></td>
        <td class="pof-line-total">₱ 0.00</td>
        <td><input type="text" class="pof-rem" placeholder="—"><input type="hidden" class="pof-acct"></td>
        <td><button type="button" class="pof-remove" title="Remove" onclick="pof_removeItem(${idx})">&times;</button></td>`;
    // Values are set through the DOM (not interpolated into the HTML) so item text can't break the markup.
    tr.querySelector('.pof-desc').value = d?.item_description ?? '';
    tr.querySelector('.pof-uom').value  = d?.unit_of_measure ?? '';
    tr.querySelector('.pof-qty').value  = d?.quantity_ordered != null ? Number(d.quantity_ordered) : '';
    tr.querySelector('.pof-cost').value = d?.unit_cost ?? '';
    tr.querySelector('.pof-rem').value  = d?.remarks ?? '';
    tr.querySelector('.pof-acct').value = d?.account_code ?? '';
    tr.querySelector('.pof-qty').addEventListener('input', pof_recalc);
    tr.querySelector('.pof-cost').addEventListener('input', pof_recalc);
    $pof('pof-items-tbody').appendChild(tr);
    pof_recalc();
}

function pof_removeItem(idx) {
    $pof(`pof-row-${idx}`)?.remove();
    pof_recalc();
}

function pof_recalc() {
    let grand = 0;
    const rows = $pof('pof-items-tbody').querySelectorAll('tr');
    rows.forEach((tr, i) => {
        const qty  = parseFloat(tr.querySelector('.pof-qty').value)  || 0;
        const cost = parseFloat(tr.querySelector('.pof-cost').value) || 0;
        const line = Math.round(qty * cost * 100) / 100;
        grand += line;
        tr.querySelector('.pof-line-total').textContent = pof_peso(line);
        tr.querySelector('.pof-seq').textContent = i + 1;
    });
    $pof('pof-grand').textContent = pof_peso(grand);
    $pof('pof-item-count').textContent = `${rows.length} item${rows.length !== 1 ? 's' : ''}`;
}

function pof_collectItems() {
    const items = [];
    $pof('pof-items-tbody').querySelectorAll('tr').forEach(tr => {
        const desc = tr.querySelector('.pof-desc').value.trim();
        if (!desc) return;                                  // blank rows are ignored
        items.push({
            item_description : desc,
            unit_of_measure  : tr.querySelector('.pof-uom').value.trim(),
            quantity_ordered : parseFloat(tr.querySelector('.pof-qty').value)  || 0,
            unit_cost        : parseFloat(tr.querySelector('.pof-cost').value) || 0,
            remarks          : tr.querySelector('.pof-rem').value.trim(),
            account_code     : tr.querySelector('.pof-acct').value.trim(),
        });
    });
    return items;
}

/* ── Validation ────────────────────────────────────────────── */
function pof_setFieldError(field, show) {
    const err = $pof(`err-${field}`);
    if (err) err.style.display = show ? 'block' : 'none';
}
function pof_clearFieldError(field) { pof_setFieldError(field, false); }

function pof_validate() {
    const errors = [];
    const check = (ok, field, msg) => { pof_setFieldError(field, !ok); if (!ok) errors.push(msg); };

    check(!!$pof('pof-po-date').value,   'po-date',   'PO Date is required.');
    check(!!$pof('pof-fiscal-yr').value, 'fiscal-yr', 'Fiscal Year is required.');
    check(!!$pof('pof-vendor').value,    'vendor',    'Please select a vendor.');

    const items = pof_collectItems();
    pof_setFieldError('items', items.length === 0);
    if (!items.length) errors.push('At least one line item with a description is required.');
    items.forEach((it, i) => {
        const n = i + 1;
        if (!it.unit_of_measure)      errors.push(`Line ${n}: Unit of Measure is required.`);
        if (it.quantity_ordered <= 0) errors.push(`Line ${n}: Quantity must be greater than zero.`);
        if (it.unit_cost < 0)         errors.push(`Line ${n}: Unit Cost cannot be negative.`);
    });
    if (items.length && !errors.length && items.reduce((s, i) => s + i.quantity_ordered * i.unit_cost, 0) <= 0) {
        errors.push('Total amount must be greater than zero.');
    }
    return { errors, items };
}

function pof_showErrors(list) {
    const ul = $pof('pof-errors-ul');
    ul.innerHTML = '';
    list.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
    $pof('pof-errors').classList.toggle('visible', list.length > 0);
    // The errors box is at the top of the scrolling modal body — bring it into view.
    if (list.length) $pof('pof-errors').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── Save ──────────────────────────────────────────────────── */
async function pof_save(status) {
    if (pof_saving) return;
    pof_showErrors([]);

    const { errors, items } = pof_validate();
    if (errors.length) { pof_showErrors(errors); return; }

    const poNumber = $pof('pof-po-number').textContent.trim();
    const body = {
        // Only sent when adding; the server assigns one if this is empty, and never changes it on edit.
        po_number              : pof_id || poNumber === '—' ? undefined : poNumber,
        po_date                : $pof('pof-po-date').value,
        fiscal_yr              : $pof('pof-fiscal-yr').value,
        program_code           : $pof('pof-program').value,
        project_code           : $pof('pof-project').value,
        expense_class          : $pof('pof-expense-class').value,
        vendor_id              : $pof('pof-vendor').value,
        contract_number        : $pof('pof-contract-number').value.trim(),
        delivery_place         : $pof('pof-delivery-place').value.trim(),
        delivery_date_required : $pof('pof-delivery-date').value,
        delivery_terms         : $pof('pof-delivery-terms').value.trim(),
        payment_terms          : $pof('pof-payment-terms').value.trim(),
        particulars            : $pof('pof-particulars').value.trim(),
        remarks                : $pof('pof-remarks').value.trim(),
        status,
        items,
    };

    pof_saving = true;
    $pof('pof-btn-draft').disabled = $pof('pof-btn-submit').disabled = true;
    window.PagePreloader?.show();
    let saved = null;
    try {
        const res  = await pof_authFetch(pof_id ? `${POF_API}/purchase-order/update/${pof_id}` : `${POF_API}/purchase-order/add`, {
            method  : pof_id ? 'PUT' : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) {
            pof_showErrors(json.errors?.length ? json.errors : [json.message || 'Failed to save purchase order.']);
            return;
        }
        saved = json;
    } catch (e) {
        pof_showErrors(['Could not save: ' + e.message]);
        return;
    } finally {
        pof_saving = false;
        window.PagePreloader?.hide();
        $pof('pof-btn-draft').disabled = $pof('pof-btn-submit').disabled = false;
    }

    pof_close();
    if (typeof window.pof_onSaved === 'function') window.pof_onSaved(saved.data, saved.message);
}

/* ── Helpers ───────────────────────────────────────────────── */
function pof_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function pof_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
