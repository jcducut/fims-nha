<?php
namespace PHPMaker2024\FIMS;

// Disbursement Voucher ADD / EDIT modal — a partial included by app/pages/voucher.php (not a page of
// its own). Modeled on app/pages/po/form.php. One generic voucher shape for any claimant/payment
// type (vendor payment, payroll, reimbursement, etc.) — the paper General Form No. 1 is itself a
// single layout; what differs is just what gets written in Particulars/Amount. A claimant is
// optionally a registered vendor (fims.vendors via /vendors/all, autofills name/address/TIN) or
// free text typed directly (e.g. a payroll group name).
// "RFA" = fims.budget_allocation WHERE allocation_type = 'RFA' (/voucher/rfa/all, /voucher/rfa/read/{id}).
//
// API:  vf_open()      → new DV
//       vf_open(id)    → edit DV #id (DRAFT / REJECTED only)
//       vf_close()
// Hook: window.vf_onSaved = (data, message) => {...}   called after a successful save.
//
// Fonts and nha-fims.css are loaded by the including page.
if (!isset($_vf_api)) {
    $_vf_api = rtrim((string) Config('API.FIMS_URL'), '/');
    $_vf_api = preg_match('~/api$~', $_vf_api) ? $_vf_api : $_vf_api . '/api';
}
?>

<style>
#vf-root .vf-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
#vf-root .vf-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#vf-root .vf-grid3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
#vf-root .vf-grid2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
#vf-root .vf-span2 { grid-column: span 2; }
#vf-root .vf-span3 { grid-column: span 3; }
#vf-root .vf-hint  { font-size: 11px; color: var(--afims-text-muted, #62657E); margin-top: 3px; }
#vf-root .vf-err   { display: none; font-size: 11px; color: var(--afims-danger, #DC3545); margin-top: 3px; }

#vf-root .vf-number { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px; margin-bottom: 14px;
    border: 2px solid var(--afims-primary-soft, #E6EBF6); border-radius: 12px;
    background: linear-gradient(to right, var(--afims-primary-soft, #E6EBF6), var(--afims-surface, #fff)); }
#vf-root .vf-number small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-number strong { font-family: "IBM Plex Mono", monospace; font-size: 20px; color: var(--afims-primary-text, #0035A9); }

#vf-root .vf-vendor-card { display: none; margin-top: 10px; padding: 12px 16px; gap: 20px; flex-wrap: wrap;
    background: var(--afims-success-soft, #DBF2E5); border: 1px solid var(--afims-success-soft-hover, #C4E9D4); border-radius: 10px; }
#vf-root .vf-vendor-card.visible { display: flex; }
#vf-root .vf-vendor-card label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-success-hover, #0C7539); }
#vf-root .vf-vendor-card span { font-size: 13px; font-weight: 600; color: var(--afims-success-hover, #0C7539); }

#vf-root .vf-ledger { border-collapse: collapse; width: 100%; font-size: 13px; }
#vf-root .vf-ledger td, #vf-root .vf-ledger th { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-ledger td.vf-l-label { color: var(--afims-text-muted, #62657E); font-weight: 600; width: 45%; }
#vf-root .vf-ledger td.vf-l-val { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; }
#vf-root .vf-ledger tr.vf-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; }
#vf-root .vf-ledger input, #vf-root .vf-ledger select { width: 100%; font-size: 12.5px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; font-family: inherit; }

#vf-root .vf-ded-row { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; position: relative; }
#vf-root .vf-ded-remove { position: absolute; top: 8px; right: 10px; background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 4px 6px; border-radius: 5px; }
#vf-root .vf-ded-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#vf-root .vf-ded-row select { width: 100%; }

#vf-root .vf-errors { display: none; margin-bottom: 14px; padding: 12px 16px; background: var(--afims-danger-soft, #FAE1E3);
    border: 1px solid var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 10px; }
#vf-root .vf-errors.visible { display: block; }
#vf-root .vf-errors ul { margin: 6px 0 0; padding-left: 18px; font-size: 13px; color: var(--afims-danger-text, #8F222D); line-height: 1.7; }

@media (max-width: 768px) {
    #vf-root .vf-grid3, #vf-root .vf-grid2 { grid-template-columns: 1fr; }
    #vf-root .vf-span2, #vf-root .vf-span3 { grid-column: span 1; }
}
</style>

<div id="vf-root" class="nha-root">
    <div id="vf-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="vf-modal-title">

            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="vf-modal-title">New Disbursement Voucher</h3>
                <button type="button" class="nha-modal-close" onclick="vf_close()" aria-label="Close">&times;</button>
            </div>

            <div class="nha-modal-body">

                <div class="vf-number">
                    <div><small>DV Number</small><strong id="vf-dv-number">—</strong></div>
                    <div style="text-align:right;"><small>Status</small><span class="nha-bdg nha-bdg-gray" id="vf-status">DRAFT</span></div>
                </div>

                <div class="vf-errors" id="vf-errors">
                    <strong style="font-size:13px;color:var(--afims-danger,#DC3545);">Please fix the following before saving:</strong>
                    <ul id="vf-errors-ul"></ul>
                </div>

                <!-- 1 — RFA first: picking one auto-fills Expense Class (2), the department/vendor
                     claimant fields (3), and the Budget Reference ledger (4) further down -->
                <div class="vf-card">
                    <p class="vf-card-title">Request for Allotment (RFA)</p>
                    <div class="nha-fg">
                        <label>RFA <span class="nha-req">*</span></label>
                        <select id="vf-rfa"><option value="">Select RFA</option></select>
                        <span class="vf-hint">Picking an RFA fills in Expense Class (if its budget category maps to one), the department, vendor (if any), and the Budget Reference ledger below.</span>
                        <span class="vf-err" id="err-rfa">RFA is required.</span>
                    </div>
                </div>

                <!-- 2 — Document identity (DV Date drives the DV Number preview) -->
                <div class="vf-card">
                    <p class="vf-card-title">Document Identity</p>
                    <div class="vf-grid3">
                        <div class="nha-fg">
                            <label>DV Date <span class="nha-req">*</span></label>
                            <input type="date" id="vf-dv-date">
                            <span class="vf-err" id="err-dv-date">DV Date is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Fiscal Year <span class="nha-req">*</span></label>
                            <select id="vf-fiscal-yr"><option value="">Loading…</option></select>
                            <span class="vf-err" id="err-fiscal-yr">Fiscal year is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Expense Class <span class="vf-hint" style="display:inline;">(optional, for reporting — auto-filled from RFA when possible)</span></label>
                            <select id="vf-expense-class">
                                <option value="">— None —</option>
                                <option value="MOOE">MOOE</option>
                                <option value="PS">Personnel Services</option>
                                <option value="CAPITAL_OUTLAY">Capital Outlay</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- 3 — Claimant & particulars — matches the paper General Form No. 1's layout:
                     claimant name/address/TIN, then Particulars of Payment / Amount -->
                <div class="vf-card">
                    <p class="vf-card-title">Claimant &amp; Particulars</p>
                    <div class="vf-grid2">
                        <div class="nha-fg">
                            <label>Vendor <span class="vf-hint" style="display:inline;">(optional — autofills claimant below)</span></label>
                            <select id="vf-vendor"><option value="">— None / type claimant manually —</option></select>
                        </div>
                        <div class="nha-fg">
                            <label>PO Number <span class="vf-hint" style="display:inline;">(optional)</span></label>
                            <input type="text" id="vf-po-number" placeholder="e.g. GSD-2025-09-103">
                        </div>
                    </div>
                    <div class="vf-vendor-card" id="vf-vendor-card">
                        <div><label>Vendor Name</label><span id="vf-vc-name">—</span></div>
                        <div><label>Address</label><span id="vf-vc-address">—</span></div>
                        <div><label>TIN</label><span id="vf-vc-tin">—</span></div>
                    </div>
                    <div class="vf-grid2" style="margin-top:12px;">
                        <div class="nha-fg">
                            <label>Claimant Name <span class="nha-req">*</span></label>
                            <input type="text" id="vf-claimant" placeholder="e.g. Jackson Auto Services, or Abelar, Reena E. et al.">
                            <span class="vf-err" id="err-claimant">Claimant Name is required.</span>
                        </div>
                        <div class="nha-fg">
                            <label>Address / Employee No. / TIN</label>
                            <input type="text" id="vf-claimant-address" placeholder="Address">
                            <input type="text" id="vf-claimant-tin" placeholder="Employee No. / TIN" style="margin-top:6px;">
                        </div>
                    </div>
                    <div class="nha-fg" style="margin-top:12px;">
                        <label>Particulars <span class="nha-req">*</span></label>
                        <textarea id="vf-particulars" rows="3" placeholder="To payment for…"></textarea>
                        <span class="vf-err" id="err-particulars">Particulars is required.</span>
                    </div>
                    <div class="nha-fg" style="margin-top:12px;max-width:260px;">
                        <label>Amount <span class="nha-req">*</span></label>
                        <input type="number" id="vf-gross" min="0" step="0.01" placeholder="0.00">
                        <span class="vf-err" id="err-gross">Amount must be greater than zero.</span>
                    </div>
                </div>

                <!-- 4 — Budget Reference — the paper form's Accounting Entries box (Box B); its
                     Dept./Project/Fund Source/Budget/Obligation are filled in by the RFA (1) above -->
                <div class="vf-card">
                    <p class="vf-card-title">Budget Reference</p>
                    <div class="vf-grid3">
                        <div class="nha-fg">
                            <label>Dept. / Project</label>
                            <input type="text" id="vf-dept-project" placeholder="e.g. GSD / RM-Motor Vehicle">
                        </div>
                        <div class="nha-fg">
                            <label>Fund Source</label>
                            <input type="text" id="vf-fund-source" placeholder="e.g. Corporate Receipts">
                        </div>
                        <div class="nha-fg">
                            <label>Account Code <span class="nha-req">*</span></label>
                            <input type="text" id="vf-account-code" placeholder="Search Chart of Accounts…">
                            <span class="vf-err" id="err-account-code">Account Code is required.</span>
                        </div>
                    </div>
                    <table class="vf-ledger" style="margin-top:10px;">
                        <tr>
                            <td class="vf-l-label">Budget</td>
                            <td><input type="number" id="vf-budget" min="0" step="0.01" placeholder="0.00"></td>
                        </tr>
                        <tr>
                            <td class="vf-l-label">Obligation</td>
                            <td><input type="number" id="vf-obligation" min="0" step="0.01" placeholder="0.00"></td>
                        </tr>
                        <tr><td class="vf-l-label">Payments<span id="vf-payments-hint" style="font-weight:400;"></span></td><td class="vf-l-val" id="vf-payments">₱ 0.00</td></tr>
                        <tr><td class="vf-l-label">This Request</td><td class="vf-l-val" id="vf-this-request">₱ 0.00</td></tr>
                        <tr class="vf-l-total"><td class="vf-l-label">Total Payments</td><td class="vf-l-val" id="vf-total-payments">₱ 0.00</td></tr>
                        <tr><td class="vf-l-label">Balance</td><td class="vf-l-val" id="vf-balance">₱ 0.00</td></tr>
                    </table>
                    <div class="nha-fg" style="margin-top:12px;">
                        <label>Remarks</label>
                        <textarea id="vf-remarks" rows="2" placeholder="Optional remarks…"></textarea>
                    </div>
                </div>

                <!-- 5 — Deductions ("Less:" on the paper form — non-tax amounts withheld from
                     gross, e.g. contractor retention. Withholding tax is separate — see ATC (6) -->
                <div class="vf-card">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <p class="vf-card-title" style="margin:0;">Deductions</p>
                        <span class="vf-hint" style="display:inline;">Optional — add only what applies to this DV</span>
                    </div>
                    <div id="vf-ded-container"></div>
                    <button type="button" class="nha-btn nha-btn-ghost" onclick="vf_addDeductionLine()">+ Add Deduction</button>
                    <div class="vf-number" style="margin-top:12px;margin-bottom:0;">
                        <div><small>Net Amount</small><strong id="vf-net">₱ 0.00</strong></div>
                    </div>
                </div>

                <!-- 6 — ATC (optional) — normally Accounting's job during FOR_ACCOUNTING, but
                     Originating can pre-fill it here if already known; Accounting can still
                     review/replace it later. Same fields/behavior as readone.php's ATC card. -->
                <div class="vf-card">
                    <div class="vje-section-hdr">
                        <p class="vf-card-title" style="margin:0;">ATC <span class="vf-hint" style="display:inline;font-weight:400;text-transform:none;">(optional)</span></p>
                    </div>
                    <p class="vf-hint" style="margin:0 0 8px;">Only add ATC rows if withholding tax applies and the classification is already known — Accounting can add or change this later regardless.</p>
                    <div style="overflow-x:auto;">
                        <table class="vf-ledger" style="min-width:760px;">
                            <thead><tr>
                                <th style="text-align:left;">ATC Code — Description</th>
                                <th style="text-align:right;width:85px;">Rate (%)</th>
                                <th style="text-align:right;width:130px;">Tax Base (₱)</th>
                                <th style="text-align:right;width:130px;">Withholding Tax (₱)</th>
                                <th style="text-align:left;width:170px;">Chargeability</th>
                                <th style="width:30px;"></th>
                            </tr></thead>
                            <tbody id="vf-atc-tbody"></tbody>
                            <tfoot><tr class="vf-l-total">
                                <td colspan="3" style="text-align:right;">Total Withholding Tax</td>
                                <td class="vf-l-val" id="vf-atc-total">₱ 0.00</td>
                                <td colspan="2"></td>
                            </tr></tfoot>
                        </table>
                    </div>
                    <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vf_addAtcLine()">+ Add ATC Row</button>
                </div>

                <!-- 7 — Accounting Entries (optional) — same grid/behavior as readone.php's
                     Accounting Entries card; Accounting reviews/edits before their own save. -->
                <div class="vf-card" style="margin-bottom:0;">
                    <div class="vje-section-hdr">
                        <p class="vf-card-title" style="margin:0;">Accounting Entries <span class="vf-hint" style="display:inline;font-weight:400;text-transform:none;">(optional)</span></p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                        <span class="vje-amt-badge">Required if filled in: DR = CR (balanced)</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="vf-ledger" style="min-width:760px;">
                            <thead><tr>
                                <th style="width:26px;">#</th>
                                <th style="text-align:left;">Account Code</th>
                                <th style="text-align:left;">SL Code</th>
                                <th style="text-align:left;">SL Detail</th>
                                <th style="text-align:right;width:110px;">Debit (₱)</th>
                                <th style="text-align:right;width:110px;">Credit (₱)</th>
                                <th style="width:30px;"></th>
                            </tr></thead>
                            <tbody id="vf-jev-tbody"></tbody>
                            <tfoot><tr class="vf-l-total">
                                <td colspan="4" style="text-align:right;">Total</td>
                                <td class="vf-l-val" id="vf-jev-total-debit">₱ 0.00</td>
                                <td class="vf-l-val" id="vf-jev-total-credit">₱ 0.00</td>
                                <td></td>
                            </tr></tfoot>
                        </table>
                    </div>
                    <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vf_addJevLine()">+ Add Line</button>
                    <div style="margin-top:12px;">
                        <span id="vf-jev-balance-msg" style="font-weight:600;font-size:13px;"></span>
                    </div>
                </div>

            </div><!-- /.nha-modal-body -->

            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="vf_close()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-outline" id="vf-btn-draft"  onclick="vf_save('DRAFT')">Save as Draft</button>
                <button type="button" class="nha-btn nha-btn-primary" id="vf-btn-submit" onclick="vf_save('FOR_FMD_REVIEW')">Save &amp; Submit to FMD</button>
            </div>

        </div>
    </div>
</div><!-- /#vf-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   DISBURSEMENT VOUCHER FORM MODAL   prefix: vf_
═══════════════════════════════════════════════════════════════ */

const VF_API      = <?= json_encode($_vf_api) ?>;
const VF_EDITABLE = ['DRAFT', 'REJECTED'];

const $vf = id => document.getElementById(id);
let vf_id      = 0;         // 0 = add mode, otherwise the dv_id being edited
let vf_saving  = false;
let vf_vendors = {};         // vendor_id -> row, for the summary card
let vf_rfas    = {};         // allocation_id -> row
let vf_refReady = null;      // promise: fiscal years, loaded once on first open
let vf_dedTypesReady = null;  // promise: deduction types, loaded once on first open
let vf_coaReady = null;       // promise: postable chart-of-accounts codes, loaded once on first open
let vf_atcReady = null;       // promise: active ATC codes, loaded once on first open
let vf_rcReady  = null;       // promise: responsibility centers (ATC Chargeability), loaded once on first open
let vf_slReady  = null;       // promise: SL masters (Accounting Entries SL Code picker), loaded once on first open
let vf_bound   = false;
let vf_paymentsPrior = 0;    // sum of net_amount other active vouchers already drew against the selected RFA
let vf_dedTypes = [];         // deduction type options (fims.deduction_types)
let vf_dedSeq   = 0;          // row-id counter for the Deductions grid
let vf_coaOptions = [];       // postable chart-of-accounts rows — reused by the Account Code field and the Accounting Entries grid
let VF_ATC = [];              // active ATC codes — see ATC card (6)
let VF_RC  = [];              // responsibility centers, for ATC "Chargeability"
let VF_SL  = [];              // SL masters, for the Accounting Entries grid's SL Code picker
let vf_atcSeq = 0;            // row-id counter for the ATC grid
let vf_jevSeq = 0;            // row-id counter for the Accounting Entries grid

function vf_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function vf_getJson(path) {
    const res  = await vf_authFetch(VF_API + path);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

/* ── Open / close ──────────────────────────────────────────── */
async function vf_open(id = 0) {
    vf_id = Number(id) || 0;
    vf_reset();
    $vf('vf-modal-title').textContent = vf_id ? 'Edit Disbursement Voucher' : 'New Disbursement Voucher';
    $vf('vf-overlay').classList.add('open');

    if (!vf_bound) { vf_bindEvents(); vf_bound = true; }

    window.PagePreloader?.show();
    try {
        vf_refReady ??= vf_loadFiscalYears();
        vf_dedTypesReady ??= vf_loadDeductionTypes();
        vf_coaReady ??= vf_loadCoaOptions();
        vf_atcReady ??= vf_loadAtcCodes();
        vf_rcReady  ??= vf_loadRcOptions();
        vf_slReady  ??= vf_loadSlOptions();
        await Promise.all([vf_refReady, vf_dedTypesReady, vf_coaReady, vf_atcReady, vf_rcReady, vf_slReady, vf_loadVendors(), vf_loadRfaOptions()]);

        if (vf_id) {
            await vf_loadExisting();
        } else {
            $vf('vf-fiscal-yr').value = [...$vf('vf-fiscal-yr').options].some(o => o.value === String(new Date().getFullYear()))
                ? String(new Date().getFullYear()) : '';
            vf_refreshDvNumber();
        }
        vf_recalc();
    } catch (e) {
        vf_showErrors([e.message]);
    } finally {
        window.PagePreloader?.hide();
    }
}

function vf_close() {
    if (vf_saving) return;
    $vf('vf-overlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $vf('vf-overlay')?.classList.contains('open')) vf_close();
});

function vf_reset() {
    vf_showErrors([]);
    ['dv-date', 'fiscal-yr', 'rfa', 'claimant', 'particulars', 'gross'].forEach(vf_clearFieldError);
    $vf('vf-dv-date').value       = new Date().toISOString().slice(0, 10);
    $vf('vf-fiscal-yr').value     = '';
    $vf('vf-expense-class').value = '';
    $vf('vf-rfa').value           = '';

    $vf('vf-vendor').value = '';
    $vf('vf-vendor-card').classList.remove('visible');
    $vf('vf-po-number').value = '';
    $vf('vf-claimant').value = '';
    $vf('vf-claimant-address').value = '';
    $vf('vf-claimant-tin').value = '';
    $vf('vf-particulars').value = '';
    $vf('vf-gross').value = '';

    $vf('vf-ded-container').innerHTML = '';
    vf_dedSeq = 0;

    $vf('vf-atc-tbody').innerHTML = '';
    vf_atcSeq = 0;
    vf_recalcAtc();

    $vf('vf-jev-tbody').innerHTML = '';
    vf_jevSeq = 0;
    vf_recalcJev();

    $vf('vf-dept-project').value = '';
    $vf('vf-fund-source').value = '';
    $vf('vf-account-code').value = '';
    $vf('vf-budget').value = '';
    $vf('vf-obligation').value = '';
    $vf('vf-remarks').value = '';
    vf_paymentsPrior = 0;
    $vf('vf-payments-hint').textContent = '';

    $vf('vf-dv-number').textContent = '—';
    $vf('vf-status').textContent    = 'DRAFT';
    $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = false;
}

function vf_bindEvents() {
    $vf('vf-dv-date').addEventListener('change', () => { if (!vf_id) vf_refreshDvNumber(); });
    $vf('vf-rfa').addEventListener('change', vf_onRfaChange);
    $vf('vf-vendor').addEventListener('change', vf_onVendorChange);
    $vf('vf-gross').addEventListener('input', () => vf_recalc());
    $vf('vf-obligation').addEventListener('input', () => vf_recalc());
}

/* ── Reference data ────────────────────────────────────────── */
async function vf_loadFiscalYears() {
    const sel = $vf('vf-fiscal-yr');
    try {
        const json = await vf_getJson('/voucher/fiscal-years');
        sel.innerHTML = '<option value="">Select fiscal year</option>' + (json.data ?? []).map(r =>
            `<option value="${vf_esc(r.fiscal_yr)}">${vf_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load</option>';
        vf_refReady = null;
        throw new Error('Failed to load fiscal years: ' + e.message);
    }
}

async function vf_loadDeductionTypes() {
    try {
        const json = await vf_getJson('/voucher/deduction-types');
        vf_dedTypes = json.data ?? [];
    } catch (e) {
        vf_dedTypesReady = null;
        throw new Error('Failed to load deduction types: ' + e.message);
    }
}

async function vf_loadCoaOptions() {
    try {
        const json = await vf_getJson('/chart-of-accounts/all?status=Active');
        vf_coaOptions = (json.data ?? []).filter(a => a.posting_allowed);
        nhaSearchableSelect($vf('vf-account-code'), vf_coaOptions.map(a => ({ value: a.account_code, label: a.account_title })));
    } catch (e) {
        vf_coaReady = null;
        throw new Error('Failed to load chart of accounts: ' + e.message);
    }
}

async function vf_loadAtcCodes() {
    try {
        const json = await vf_getJson('/voucher/atc-codes');
        VF_ATC = json.data ?? [];
    } catch (e) {
        vf_atcReady = null;
        throw new Error('Failed to load ATC codes: ' + e.message);
    }
}

async function vf_loadRcOptions() {
    try {
        const json = await vf_getJson('/responsibility-center/all');
        VF_RC = json.data ?? [];
    } catch (e) {
        vf_rcReady = null;
        throw new Error('Failed to load responsibility centers: ' + e.message);
    }
}

async function vf_loadSlOptions() {
    try {
        const json = await vf_getJson('/voucher/sl-codes');
        VF_SL = json.data ?? [];
    } catch (e) {
        vf_slReady = null;
        throw new Error('Failed to load SL codes: ' + e.message);
    }
}

async function vf_loadVendors() {
    const sel = $vf('vf-vendor');
    try {
        const json = await vf_getJson('/vendors/all?active=1');
        vf_vendors = {};
        (json.data ?? []).forEach(v => { vf_vendors[v.vendor_id] = v; });
        sel.innerHTML = '<option value="">— None / type claimant manually —</option>' + (json.data ?? []).map(v =>
            `<option value="${vf_esc(v.vendor_id)}">${vf_esc(v.vendor_code)} — ${vf_esc(v.vendor_name)}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load vendors</option>';
        throw new Error('Failed to load vendors: ' + e.message);
    }
}

async function vf_loadRfaOptions() {
    const sel = $vf('vf-rfa');
    try {
        // exclude_dv_id: this voucher's own prior draw (editing) is left out of paid_amount, so
        // it doesn't double-count against itself. Several DVs can share one RFA (progress
        // payments) — remaining_balance in the label is just a heads-up, not a hard limit.
        const qs   = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const json = await vf_getJson(`/voucher/rfa/all${qs}`);
        vf_rfas = {};
        (json.data ?? []).forEach(r => { vf_rfas[r.allocation_id] = r; });
        sel.innerHTML = '<option value="">Select RFA</option>' + (json.data ?? []).map(r =>
            `<option value="${vf_esc(r.allocation_id)}">${vf_esc(r.allocation_number)} — ${vf_esc(r.department_name || 'Unassigned Dept.')} (FY ${vf_esc(r.fiscal_year)}) · ${vf_peso(r.remaining_balance)} left of ${vf_peso(r.budget_amount)}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load RFAs</option>';
        throw new Error('Failed to load RFAs: ' + e.message);
    }
}

function vf_onVendorChange() {
    const v = vf_vendors[$vf('vf-vendor').value];
    const card = $vf('vf-vendor-card');
    if (!v) { card.classList.remove('visible'); return; }
    $vf('vf-vc-name').textContent    = v.vendor_name || '—';
    $vf('vf-vc-address').textContent = v.address || '—';
    $vf('vf-vc-tin').textContent     = v.tin || '—';
    card.classList.add('visible');
    // Vendor fields win over whatever was typed manually, so the claimant always matches the vendor on file.
    $vf('vf-claimant').value         = v.vendor_name || '';
    $vf('vf-claimant-address').value = v.address || '';
    $vf('vf-claimant-tin').value     = v.tin || '';
    vf_clearFieldError('claimant');
}

/* ── RFA autofill ──────────────────────────────────────────── */
async function vf_onRfaChange() {
    const id = $vf('vf-rfa').value;
    if (!id) { vf_paymentsPrior = 0; $vf('vf-payments-hint').textContent = ''; vf_recalc(); return; }
    vf_clearFieldError('rfa');

    window.PagePreloader?.show();
    try {
        const qs   = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const json = await vf_getJson(`/voucher/rfa/read/${id}${qs}`);
        const rfa  = json.data;

        $vf('vf-dept-project').value = rfa.department_name || '';
        $vf('vf-budget').value       = rfa.budget_amount || '';
        $vf('vf-obligation').value   = rfa.budget_amount || '';
        vf_paymentsPrior = parseFloat(rfa.paid_amount) || 0;
        $vf('vf-payments-hint').textContent = vf_paymentsPrior > 0 ? ' (prior draws on this RFA)' : '';

        // Best-effort mapping — the RFA's budget category (Infrastructure/Goods and
        // Services/Consultancy) isn't the same vocabulary as Expense Class, so this is a sensible
        // default only; never overwrites a value the user already picked themselves.
        const expenseClassMap = { 'Infrastructure': 'CAPITAL_OUTLAY', 'Goods and Services': 'MOOE', 'Consultancy': 'MOOE' };
        if (!$vf('vf-expense-class').value && expenseClassMap[rfa.category_name]) {
            $vf('vf-expense-class').value = expenseClassMap[rfa.category_name];
        }

        if (rfa.po_number) $vf('vf-po-number').value = rfa.po_number;

        if (rfa.vendor_id) {
            if (!vf_vendors[rfa.vendor_id]) {
                vf_vendors[rfa.vendor_id] = { vendor_id: rfa.vendor_id, vendor_name: rfa.vendor_name, address: rfa.vendor_address, tin: rfa.vendor_tin };
                $vf('vf-vendor').insertAdjacentHTML('beforeend',
                    `<option value="${vf_esc(rfa.vendor_id)}">${vf_esc(rfa.vendor_name)}</option>`);
            }
            $vf('vf-vendor').value = rfa.vendor_id;
            vf_onVendorChange();
        }
    } catch (e) {
        vf_showErrors(['Could not load RFA: ' + e.message]);
    } finally {
        window.PagePreloader?.hide();
        vf_recalc();
    }
}

async function vf_refreshDvNumber() {
    const d = $vf('vf-dv-date').value;
    if (!d) return;
    try {
        const json = await vf_getJson('/voucher/dv-number?dv_date=' + encodeURIComponent(d));
        $vf('vf-dv-number').textContent = json.data.dv_no;
    } catch (e) { /* the server assigns a number on save if this stays "—" */ }
}

/* ── Deductions (optional — e.g. contractor retention) ───────── */
function vf_addDeductionLine(d = null) {
    const idx = ++vf_dedSeq;
    const div = document.createElement('div');
    div.className = 'vf-ded-row';
    div.id = `vf-ded-row-${idx}`;
    div.innerHTML = `
        <button type="button" class="vf-ded-remove" title="Remove" onclick="vf_removeDeductionLine(${idx})">&times;</button>
        <select class="vf-ded-type">
            <option value="">— Select type —</option>
            ${vf_dedTypes.map(t => `<option value="${vf_esc(t.deduction_type_id)}">${vf_esc(t.name)}</option>`).join('')}
        </select>
        <span class="vf-hint vf-ded-desc" style="display:block;"></span>
        <div class="vf-grid3" style="margin-top:8px;">
            <div class="nha-fg"><label>Rate (%)</label><input type="number" class="vf-ded-rate" min="0" step="0.01" placeholder="e.g. 10"></div>
            <div class="nha-fg"><label>Gross Amount (₱)</label><input type="text" class="vf-ded-gross" readonly></div>
            <div class="nha-fg"><label>Deduction Amount (₱)</label><input type="number" class="vf-ded-amount" min="0" step="0.01" placeholder="Auto-computed"></div>
        </div>`;
    $vf('vf-ded-container').appendChild(div);

    const typeSel  = div.querySelector('.vf-ded-type');
    const descSpan = div.querySelector('.vf-ded-desc');
    typeSel.addEventListener('change', () => {
        const t = vf_dedTypes.find(t => String(t.deduction_type_id) === typeSel.value);
        descSpan.textContent = t?.description || '';
    });
    div.querySelector('.vf-ded-rate').addEventListener('input', () => vf_recalcDeductionLine(idx));

    if (d) {
        typeSel.value = d.deduction_type_id ?? '';
        typeSel.dispatchEvent(new Event('change'));
        div.querySelector('.vf-ded-rate').value = d.rate ?? '';
        div.querySelector('.vf-ded-amount').value = Number(d.deduction_amount || 0).toFixed(2);
    }
    vf_recalcDeductionLine(idx);
}

function vf_removeDeductionLine(idx) {
    $vf(`vf-ded-row-${idx}`)?.remove();
    vf_recalc();
}

/* A deduction's gross_amount always mirrors the DV's own Amount (not separately editable — see
   the "Gross Amount" field's readonly styling); its deduction_amount is rate% of that unless the
   user types an amount directly (no rate given = flat peso deduction). */
function vf_recalcDeductionLine(idx) {
    const row = $vf(`vf-ded-row-${idx}`);
    if (!row) return;
    const gross = parseFloat($vf('vf-gross').value) || 0;
    row.querySelector('.vf-ded-gross').value = vf_peso(gross);
    const rate = parseFloat(row.querySelector('.vf-ded-rate').value);
    if (!isNaN(rate) && rate > 0) {
        row.querySelector('.vf-ded-amount').value = (gross * rate / 100).toFixed(2);
    }
    vf_recalc();
}

function vf_totalDeductions() {
    let total = 0;
    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        total += parseFloat(row.querySelector('.vf-ded-amount').value) || 0;
    });
    return total;
}

function vf_collectDeductions() {
    const out = [];
    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        const typeId = row.querySelector('.vf-ded-type').value;
        const amount = parseFloat(row.querySelector('.vf-ded-amount').value) || 0;
        if (!typeId && amount <= 0) return; // blank trailing row
        out.push({
            deduction_type_id: typeId || null,
            rate             : row.querySelector('.vf-ded-rate').value === '' ? null : parseFloat(row.querySelector('.vf-ded-rate').value),
            gross_amount     : parseFloat($vf('vf-gross').value) || 0,
            deduction_amount : amount,
        });
    });
    return out;
}

/* ── ATC (optional — see card 6's doc comment) — same fields/behavior as readone.php's vje_ ATC
   grid, minus the auto-sync-into-Accounting-Entries wiring (that's Accounting's job once the DV
   reaches FOR_ACCOUNTING; here it's just a plain add/remove grid, saved as-is). ───────────────── */
function vf_addAtcLine(a = null) {
    const idx = ++vf_atcSeq;
    const tr = document.createElement('tr');
    tr.id = `vf-atc-row-${idx}`;
    tr.innerHTML = `
        <td><select class="vf-atc-code">
            <option value="">— Select ATC —</option>
            ${VF_ATC.map(c => `<option value="${vf_esc(c.atc_code)}">${vf_esc(c.atc_code)} — ${vf_esc(c.description)}</option>`).join('')}
        </select></td>
        <td><input type="number" class="vf-atc-rate" min="0" max="100" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="vf-atc-base" min="0" step="0.01" placeholder="0.00"></td>
        <td class="vf-l-val vf-atc-wh">₱ 0.00</td>
        <td><select class="vf-atc-rc">
            <option value="">— None —</option>
            ${VF_RC.map(r => `<option value="${vf_esc(r.rc_id)}">${vf_esc(r.rc_id)} — ${vf_esc(r.rc_name)}</option>`).join('')}
        </select></td>
        <td><button type="button" class="vf-ded-remove" title="Remove" onclick="vf_removeAtcLine(${idx})">&times;</button></td>`;
    tr.querySelector('.vf-atc-code').value = a?.atc_code ?? '';
    tr.querySelector('.vf-atc-rate').value = a?.rate ?? '';
    tr.querySelector('.vf-atc-base').value = a ? (Number(a.tax_base) || 0).toFixed(2) : '';
    tr.querySelector('.vf-atc-rc').value   = a?.chargeability ?? '';
    tr.querySelectorAll('.vf-atc-code, .vf-atc-rate, .vf-atc-base').forEach(el => {
        el.addEventListener(el.tagName === 'SELECT' ? 'change' : 'input', () => vf_recalcAtcLine(idx));
    });
    $vf('vf-atc-tbody').appendChild(tr);
    vf_recalcAtcLine(idx);
}

function vf_removeAtcLine(idx) {
    $vf(`vf-atc-row-${idx}`)?.remove();
    vf_recalcAtc();
}

function vf_recalcAtcLine(idx) {
    const tr = $vf(`vf-atc-row-${idx}`);
    if (!tr) return;
    const rate = parseFloat(tr.querySelector('.vf-atc-rate').value) || 0;
    const base = parseFloat(tr.querySelector('.vf-atc-base').value) || 0;
    tr.querySelector('.vf-atc-wh').textContent = vf_peso(base * rate / 100);
    vf_recalcAtc();
}

function vf_collectAtcLines() {
    const lines = [];
    $vf('vf-atc-tbody').querySelectorAll('tr').forEach(tr => {
        const atc_code = tr.querySelector('.vf-atc-code').value;
        const rate     = parseFloat(tr.querySelector('.vf-atc-rate').value) || 0;
        const tax_base = parseFloat(tr.querySelector('.vf-atc-base').value) || 0;
        if (!atc_code && tax_base <= 0) return; // blank trailing row
        lines.push({ atc_code, rate, tax_base, chargeability: tr.querySelector('.vf-atc-rc').value || null });
    });
    return lines;
}

function vf_recalcAtc() {
    const lines = vf_collectAtcLines();
    const totalWh = lines.reduce((s, l) => s + (l.tax_base * l.rate / 100), 0);
    $vf('vf-atc-total').textContent = vf_peso(totalWh);
}

/* ── Accounting Entries (optional — see card 7's doc comment) — same fields/behavior as
   readone.php's vje_ Accounting Entries grid, minus auto-sync from Deduction/ATC rows (that only
   kicks in once Accounting is preparing the real Journal Entry at FOR_ACCOUNTING). ─────────────── */
function vf_addJevLine(d = null) {
    const idx = ++vf_jevSeq;
    const tr = document.createElement('tr');
    tr.id = `vf-jev-row-${idx}`;
    tr.innerHTML = `
        <td class="vf-l-val">${idx}</td>
        <td><input type="text" class="vf-jev-acct" placeholder="Search account code…"></td>
        <td><input type="text" class="vf-jev-sl-code" placeholder="Search SL code…"></td>
        <td><select class="vf-jev-sl-detail"><option value="">SL Detail…</option></select></td>
        <td><input type="number" class="vf-jev-debit" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="vf-jev-credit" min="0" step="0.01" placeholder="0.00"></td>
        <td><button type="button" class="vf-ded-remove" title="Remove" onclick="vf_removeJevLine(${idx})">&times;</button></td>`;
    tr.querySelector('.vf-jev-acct').value    = d?.account_code ?? '';
    tr.querySelector('.vf-jev-sl-code').value = d?.sl_code ?? '';
    tr.querySelector('.vf-jev-debit').value   = Number(d?.debit)  > 0 ? Number(d.debit).toFixed(2)  : '';
    tr.querySelector('.vf-jev-credit').value  = Number(d?.credit) > 0 ? Number(d.credit).toFixed(2) : '';
    tr.querySelector('.vf-jev-debit').addEventListener('input', vf_recalcJev);
    tr.querySelector('.vf-jev-credit').addEventListener('input', vf_recalcJev);
    tr.querySelector('.vf-jev-sl-code').addEventListener('change', () => vf_loadJevSlDetails(idx, d?.sl_detail_id ?? null));
    $vf('vf-jev-tbody').appendChild(tr);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(tr.querySelector('.vf-jev-acct'), vf_coaOptions.map(a => ({ value: a.account_code, label: a.account_title })));
    nhaSearchableSelect(tr.querySelector('.vf-jev-sl-code'), VF_SL.map(s => ({ value: s.sl_code, label: s.sl_name })));
    if (d?.sl_code) vf_loadJevSlDetails(idx, d?.sl_detail_id ?? null);
    vf_recalcJev();
}

async function vf_loadJevSlDetails(idx, selectDetailId = null) {
    const tr = $vf(`vf-jev-row-${idx}`);
    if (!tr) return;
    const slCode = tr.querySelector('.vf-jev-sl-code').value.trim();
    const sel    = tr.querySelector('.vf-jev-sl-detail');
    if (!slCode) { sel.innerHTML = '<option value="">SL Detail…</option>'; return; }
    try {
        const json = await vf_getJson(`/voucher/sl-codes/${encodeURIComponent(slCode)}/details`);
        sel.innerHTML = '<option value="">— None —</option>' + (json.data ?? []).map(d =>
            `<option value="${vf_esc(d.sl_detail_id)}">${vf_esc(d.detail_code)} — ${vf_esc(d.detail_name)}</option>`).join('');
        if (selectDetailId) sel.value = String(selectDetailId);
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load</option>';
    }
}

function vf_removeJevLine(idx) {
    $vf(`vf-jev-row-${idx}`)?.remove();
    vf_recalcJev();
}

function vf_collectJevLines() {
    const lines = [];
    $vf('vf-jev-tbody').querySelectorAll('tr').forEach(tr => {
        const account_code = tr.querySelector('.vf-jev-acct').value.trim();
        const debit        = parseFloat(tr.querySelector('.vf-jev-debit').value)  || 0;
        const credit       = parseFloat(tr.querySelector('.vf-jev-credit').value) || 0;
        const sl_code      = tr.querySelector('.vf-jev-sl-code').value.trim();
        const sl_detail_id = tr.querySelector('.vf-jev-sl-detail').value;
        if (!account_code && debit <= 0 && credit <= 0) return; // blank trailing row
        lines.push({
            account_code, debit, credit,
            sl_code      : sl_code || null,
            sl_detail_id : sl_detail_id ? Number(sl_detail_id) : null,
        });
    });
    return lines;
}

function vf_recalcJev() {
    const lines = vf_collectJevLines();
    const totalDebit  = lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
    $vf('vf-jev-total-debit').textContent  = vf_peso(totalDebit);
    $vf('vf-jev-total-credit').textContent = vf_peso(totalCredit);
    const msg = $vf('vf-jev-balance-msg');
    if (lines.length === 0) {
        msg.textContent = '';
    } else {
        const balanced = lines.length >= 2 && totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.005;
        msg.textContent = balanced ? 'Balanced ✓' : 'Not balanced — debits must equal credits';
        msg.style.color = balanced ? 'var(--afims-success, #16A34A)' : 'var(--afims-danger, #DC3545)';
    }
}

/* ── Live computation ──────────────────────────────────────── */
function vf_recalc() {
    const gross = parseFloat($vf('vf-gross').value) || 0;
    $vf('vf-ded-container').querySelectorAll('.vf-ded-row').forEach(row => {
        row.querySelector('.vf-ded-gross').value = vf_peso(gross);
    });

    const netAmount = Math.max(0, gross - vf_totalDeductions());
    $vf('vf-net').textContent = vf_peso(netAmount);

    const obligation    = parseFloat($vf('vf-obligation').value) || 0;
    const totalPayments = vf_paymentsPrior + netAmount;
    const balance       = obligation - totalPayments;

    $vf('vf-payments').textContent       = vf_peso(vf_paymentsPrior);
    $vf('vf-this-request').textContent   = vf_peso(netAmount);
    $vf('vf-total-payments').textContent = vf_peso(totalPayments);
    $vf('vf-balance').textContent        = vf_peso(balance);
}

/* ── Edit mode ─────────────────────────────────────────────── */
async function vf_loadExisting() {
    const json = await vf_getJson('/voucher/read/' + vf_id);
    const dv   = json.data;
    if (!VF_EDITABLE.includes(dv.status)) {
        vf_showErrors([`This disbursement voucher is ${dv.status} and can no longer be edited.`]);
        $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = true;
    }

    $vf('vf-dv-number').textContent = dv.dv_no;
    $vf('vf-status').textContent    = dv.status;
    $vf('vf-dv-date').value         = String(dv.dv_date).slice(0, 10);
    $vf('vf-fiscal-yr').value       = dv.fiscal_yr;
    $vf('vf-expense-class').value   = dv.expense_class || '';
    $vf('vf-rfa').value             = dv.rfa_allocation_id || '';

    if (dv.vendor_id && !vf_vendors[dv.vendor_id]) {
        vf_vendors[dv.vendor_id] = { vendor_id: dv.vendor_id, vendor_name: dv.claimant_name, address: dv.claimant_address, tin: dv.employee_no_tin };
        $vf('vf-vendor').insertAdjacentHTML('beforeend',
            `<option value="${vf_esc(dv.vendor_id)}">${vf_esc(dv.claimant_name)} (inactive)</option>`);
    }
    $vf('vf-vendor').value = dv.vendor_id || '';
    vf_onVendorChange();

    $vf('vf-po-number').value        = dv.po_number || '';
    $vf('vf-claimant').value         = dv.claimant_name || '';
    $vf('vf-claimant-address').value = dv.claimant_address || '';
    $vf('vf-claimant-tin').value     = dv.employee_no_tin || '';
    $vf('vf-particulars').value      = dv.particulars || '';
    $vf('vf-gross').value            = dv.gross_amount || '';

    $vf('vf-ded-container').innerHTML = '';
    vf_dedSeq = 0;
    (dv.deductions ?? []).forEach(d => vf_addDeductionLine(d));

    $vf('vf-atc-tbody').innerHTML = '';
    vf_atcSeq = 0;
    (dv.atc_lines ?? []).forEach(a => vf_addAtcLine(a));
    vf_recalcAtc();

    $vf('vf-jev-tbody').innerHTML = '';
    vf_jevSeq = 0;
    (dv.jev?.lines ?? []).forEach(l => vf_addJevLine(l));
    vf_recalcJev();

    $vf('vf-dept-project').value = dv.dept_project || '';
    $vf('vf-fund-source').value  = dv.fund_source || '';
    $vf('vf-account-code').value = dv.account_code || '';
    $vf('vf-budget').value       = dv.budget_amount || '';
    $vf('vf-obligation').value   = dv.obligation_amount || '';
    $vf('vf-remarks').value      = dv.remarks || '';

    // As last saved — same snapshot-not-live convention as budget_amount/obligation_amount above;
    // re-picking the RFA dropdown (vf_onRfaChange) refreshes it live if needed.
    vf_paymentsPrior = parseFloat(dv.payments_prior_amount) || 0;
    $vf('vf-payments-hint').textContent = vf_paymentsPrior > 0 ? ' (prior draws on this RFA)' : '';
}

/* ── Validation ────────────────────────────────────────────── */
function vf_setFieldError(field, show) {
    const err = $vf(`err-${field}`);
    if (err) err.style.display = show ? 'block' : 'none';
}
function vf_clearFieldError(field) { vf_setFieldError(field, false); }

function vf_validate() {
    const errors = [];
    const check = (ok, field, msg) => { vf_setFieldError(field, !ok); if (!ok) errors.push(msg); };

    check(!!$vf('vf-dv-date').value,   'dv-date',   'DV Date is required.');
    check(!!$vf('vf-fiscal-yr').value, 'fiscal-yr', 'Fiscal Year is required.');
    check(!!$vf('vf-rfa').value,       'rfa',       'RFA is required.');
    check(!!$vf('vf-claimant').value.trim(), 'claimant', 'Claimant Name is required.');
    check(!!$vf('vf-particulars').value.trim(), 'particulars', 'Particulars is required.');
    check((parseFloat($vf('vf-gross').value) || 0) > 0, 'gross', 'Amount must be greater than zero.');
    check(!!$vf('vf-account-code').value.trim(), 'account-code', 'Account Code is required.');

    return errors;
}

function vf_showErrors(list) {
    const ul = $vf('vf-errors-ul');
    ul.innerHTML = '';
    list.forEach(m => { const li = document.createElement('li'); li.textContent = m; ul.appendChild(li); });
    $vf('vf-errors').classList.toggle('visible', list.length > 0);
    if (list.length) $vf('vf-errors').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

/* ── Save ──────────────────────────────────────────────────── */
async function vf_save(status) {
    if (vf_saving) return;
    vf_showErrors([]);

    const errors = vf_validate();
    if (errors.length) { vf_showErrors(errors); return; }

    const dvNumber = $vf('vf-dv-number').textContent.trim();
    const body = {
        dv_no              : vf_id || dvNumber === '—' ? undefined : dvNumber,
        dv_date            : $vf('vf-dv-date').value,
        fiscal_yr          : $vf('vf-fiscal-yr').value,
        expense_class      : $vf('vf-expense-class').value,
        rfa_allocation_id  : $vf('vf-rfa').value || null,
        dept_project       : $vf('vf-dept-project').value.trim(),
        fund_source        : $vf('vf-fund-source').value.trim(),
        account_code       : $vf('vf-account-code').value.trim(),
        budget_amount      : parseFloat($vf('vf-budget').value) || 0,
        obligation_amount  : parseFloat($vf('vf-obligation').value) || 0,
        remarks            : $vf('vf-remarks').value.trim(),
        vendor_id          : $vf('vf-vendor').value || null,
        claimant_name      : $vf('vf-claimant').value.trim(),
        claimant_address   : $vf('vf-claimant-address').value.trim(),
        employee_no_tin    : $vf('vf-claimant-tin').value.trim(),
        po_number          : $vf('vf-po-number').value.trim(),
        particulars        : $vf('vf-particulars').value.trim(),
        gross_amount       : parseFloat($vf('vf-gross').value) || 0,
        deductions         : vf_collectDeductions(),
        atc_lines          : vf_collectAtcLines(),
        lines              : vf_collectJevLines(),
        status,
    };

    vf_saving = true;
    $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = true;
    window.PagePreloader?.show();
    let saved = null;
    try {
        const res  = await vf_authFetch(vf_id ? `${VF_API}/voucher/update/${vf_id}` : `${VF_API}/voucher/add`, {
            method  : vf_id ? 'PUT' : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) {
            vf_showErrors(json.errors?.length ? json.errors : [json.message || 'Failed to save disbursement voucher.']);
            return;
        }
        saved = json;
    } catch (e) {
        vf_showErrors(['Could not save: ' + e.message]);
        return;
    } finally {
        vf_saving = false;
        window.PagePreloader?.hide();
        $vf('vf-btn-draft').disabled = $vf('vf-btn-submit').disabled = false;
    }

    vf_close();
    if (typeof window.vf_onSaved === 'function') window.vf_onSaved(saved.data, saved.message);
}

/* ── Helpers ───────────────────────────────────────────────── */
function vf_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function vf_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
