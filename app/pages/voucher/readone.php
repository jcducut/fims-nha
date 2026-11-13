<?php
namespace PHPMaker2024\FIMS;

// Disbursement Voucher VIEW modal — a partial included by app/pages/voucher.php (not a page of its
// own). Print opens the official DV (General Form No. 1, DvPrintService) and Print JEV this DV's
// Journal Entry Voucher (JevPrintService), both in the shared print preview included at the end.
//
// API:  vr_open(id)
//       vr_close()
//
// Fonts and nha-fims.css are loaded by the including page.
if (!isset($_vr_api)) {
    $_vr_api = rtrim((string) Config('API.FIMS_URL'), '/');
    $_vr_api = preg_match('~/api$~', $_vr_api) ? $_vr_api : $_vr_api . '/api';
}
// Mirrors DisbursementVoucherService::authorizeTransition() for UX only (hiding a button here is
// cosmetic — the API is what actually enforces it); superadmin (uac.users.user_level_id = '-1')
// bypasses like everywhere else — GlobalService::getUserRcScope() would be the shared helper for
// this, but app/lib isn't autoloaded in page context (only app/api.php requires it), so this is
// inlined the same way $_vr_rcId already was. NOT IsSysAdmin(), which only tracks PHPMaker's own
// hardcoded admin-login path and is never true for a DB-authenticated user.
$_vr_currentUserId = (int) CurrentUserID();
$_vr_userRow        = $_vr_currentUserId > 0
    ? ExecuteRow("SELECT user_level_id, rc_id FROM uac.users WHERE user_id = {$_vr_currentUserId}", 'DB')
    : null;
$_vr_isSysAdmin    = !$_vr_userRow || SameText((string) $_vr_userRow['user_level_id'], '-1');
$_vr_rcId          = $_vr_isSysAdmin ? null : ($_vr_userRow['rc_id'] ?? null);
?>

<style>
#vr-root .vr-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 16px 18px; margin-bottom: 14px; }
#vr-root .vr-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#vr-root dl.vr-dl { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 20px; margin: 0; }
#vr-root dl.vr-dl.vr-dl3 { grid-template-columns: repeat(3, 1fr); }
#vr-root dl.vr-dl dt { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0; }
#vr-root dl.vr-dl dd { font-size: 14px; font-weight: 600; color: var(--afims-text, #1e293b); margin: 2px 0 0; }
#vr-root .vr-particulars { font-size: 13px; line-height: 1.6; white-space: pre-wrap; }
#vr-root .vr-ledger { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vr-ledger td { padding: 6px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vr-ledger td.vr-l-label { color: var(--afims-text-muted, #62657E); font-weight: 600; width: 55%; }
#vr-root .vr-ledger td.vr-l-val { text-align: right; font-family: "IBM Plex Mono", monospace; font-weight: 600; }
#vr-root .vr-ledger tr.vr-l-total td { border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; font-weight: 700; }
#vr-root .vr-net { display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 12px 18px; margin-bottom: 14px;
    border: 2px solid var(--afims-primary-soft, #E6EBF6); border-radius: 12px;
    background: linear-gradient(to right, var(--afims-primary-soft, #E6EBF6), var(--afims-surface, #fff)); }
#vr-root .vr-net small { display: block; font-size: 11px; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
#vr-root .vr-net strong { font-family: "IBM Plex Mono", monospace; font-size: 20px; color: var(--afims-primary-text, #0035A9); }
#vr-root .vr-rejection { padding: 10px 14px; background: var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 8px; font-size: 13px; color: var(--afims-danger-text, #8F222D); margin-bottom: 14px; }
#vr-root .vje-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); padding: 4px 6px; border-radius: 5px; font-size: 16px; line-height: 1; }
#vr-root .vje-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#vr-root .vr-ledger input, #vr-root .vr-ledger select { width: 100%; font-size: 12.5px; padding: 6px 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; font-family: inherit; }
#vr-root .vje-section-hdr { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
#vr-root .vje-badge { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; padding: 3px 9px; border-radius: 999px;
    background: var(--afims-success-soft, #DBF2E5); color: var(--afims-success-hover, #0C7539); }
#vr-root .vje-amt-badge { font-size: 11px; font-weight: 700; padding: 4px 10px; border-radius: 8px; background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text, #1e293b); }
#vr-root .vje-tax-box { padding: 12px 16px; border-radius: 10px; border: 1px solid var(--afims-warning-soft, #FDF0D5); background: var(--afims-warning-soft, #FEF9EC); }
#vr-root .vje-tax-box dl { display: grid; grid-template-columns: 1fr auto; gap: 6px 0; margin: 0; }
#vr-root .vje-tax-box dt { font-size: 12.5px; color: var(--afims-warning-text, #8A6400); }
#vr-root .vje-tax-box dd { margin: 0; text-align: right; font-family: "IBM Plex Mono", monospace; font-size: 12.5px; color: var(--afims-warning-text, #8A6400); }
#vr-root .vje-tax-box dl.vje-tax-total dt, #vr-root .vje-tax-box dl.vje-tax-total dd { font-weight: 700; font-size: 13.5px; }
#vr-root .vje-auto-tag { display: inline-block; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .03em;
    padding: 1px 6px; border-radius: 999px; background: var(--afims-primary-soft, #E6EBF6); color: var(--afims-primary-text, #0035A9); margin-left: 4px; }
#vr-root .vr-rfa-list { display: flex; flex-direction: column; gap: 10px; }
#vr-root .vr-rfa-item { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; padding: 10px 12px; }
#vr-root .vr-att-list { display: flex; flex-direction: column; gap: 6px; }
#vr-root .vr-att-item { display: block; font-size: 13px; color: var(--afims-primary-text, #0035A9); text-decoration: none; }
#vr-root .vr-att-item:hover { text-decoration: underline; }

#vr-root .vje-grp-table { border-collapse: collapse; width: 100%; font-size: 13px; }
#vr-root .vje-grp-table th { padding: 5px 4px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: var(--afims-text-muted, #62657E); border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vje-grp-table td { padding: 5px 4px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-grp-hdr td { font-weight: 700; padding-top: 10px; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#vr-root .vje-grp-hdr:first-child td { padding-top: 5px; }
#vr-root .vje-grp-sub td:first-child { padding-left: 20px; color: var(--afims-text, #1e293b); font-weight: 400; }
#vr-root .vje-grp-amt { text-align: right; font-family: "IBM Plex Mono", monospace; white-space: nowrap; width: 120px; }
#vr-root .vje-grp-code { text-align: center; font-family: "IBM Plex Mono", monospace; color: var(--afims-text-muted, #62657E); width: 60px; }

#vr-root .vje-grp-edit-table tr.vje-grp-hdr td { padding-top: 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface-subtle, #F4F7FA); }
#vr-root .vje-grp-edit-table tr.vje-grp-hdr:first-child td { padding-top: 6px; }
#vr-root .vje-grp-edit-table tr.vje-grp-hdr input.vje-grp-acct { font-weight: 700; background: var(--afims-surface, #fff); }
#vr-root .vje-grp-edit-table tr.vje-grp-hdr .vje-grp-debit,
#vr-root .vje-grp-edit-table tr.vje-grp-hdr .vje-grp-credit { font-weight: 700; font-family: "IBM Plex Mono", monospace; text-align: right; }
#vr-root .vje-grp-edit-table tr.vje-grp-line td:first-child { padding-left: 22px; }
#vr-root .vje-grp-edit-table .vje-grp-add-btn { font-size: 11.5px; padding: 4px 10px; }

@media print {
    body * { visibility: hidden; }
    #vr-overlay, #vr-overlay * { visibility: visible; }
    #vr-overlay { position: absolute; top: 0; left: 0; width: 100%; }
    #vr-root .nha-modal-hdr, #vr-root .nha-modal-foot, #vr-root .no-print { display: none !important; }
}
</style>

<div id="vr-root" class="nha-root">
    <div id="vr-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-full" role="dialog" aria-modal="true" aria-labelledby="vr-modal-title">

            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="vr-modal-title">Disbursement Voucher</h3>
                <button type="button" class="nha-modal-close" onclick="vr_close()" aria-label="Close">&times;</button>
            </div>

            <div class="nha-modal-body" id="vr-body">
                <div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>
            </div>

            <div class="nha-modal-foot no-print">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="vr_close()">Close</button>
                <button type="button" class="nha-btn nha-btn-outline" onclick="vr_print()">Print</button>
                <button type="button" class="nha-btn nha-btn-outline" id="vr-btn-jev" onclick="vr_printJev()" style="display:none;">Print JEV</button>
                <span id="vr-actions"></span>
            </div>

        </div>
    </div>
</div><!-- /#vr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   DISBURSEMENT VOUCHER VIEW / PRINT MODAL   prefix: vr_
═══════════════════════════════════════════════════════════════ */

const VR_API = <?= json_encode($_vr_api) ?>;
const VR_IS_SYSADMIN     = <?= json_encode((bool) $_vr_isSysAdmin) ?>;
const VR_CURRENT_USER_ID = <?= json_encode($_vr_currentUserId) ?>;
const VR_RC_ID           = <?= json_encode($_vr_rcId) ?>;
// Mirrors DisbursementVoucherService::STAGE_HOLDER_RC — UX only, server is authoritative.
const VR_STAGE_HOLDER_RC = { FOR_FMD_REVIEW: '013', FOR_ACCOUNTING: '012', FOR_TREASURY: '014' };
const $vr = id => document.getElementById(id);
let vr_id  = 0;
let vr_dv  = null;

function vr_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function vr_open(id) {
    vr_id = Number(id) || 0;
    if (!vr_id) return;
    $vr('vr-overlay').classList.add('open');
    $vr('vr-body').innerHTML = '<div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div>';
    $vr('vr-actions').innerHTML = '';

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/read/${vr_id}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load disbursement voucher.');
        vr_dv = json.data;
        // vje_groupedEntriesCard() needs GL account titles even outside the Accounting stage (a
        // RELEASED voucher's printout should still show its entries) — vje_init() only loads this
        // while vje_visible(dv), so fetch it here too whenever there's a JEV to show at all.
        if (!VJE_COA.length && (vr_dv.jev?.lines ?? []).length) {
            try {
                const coaJson = await vr_authFetch(`${VR_API}/chart_of_accounts/all?postable_only=1`).then(r => r.json());
                VJE_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
            } catch (e) { /* grouped card falls back to showing the raw account_code */ }
        }
        vr_render(vr_dv);
        $vr('vr-btn-jev').style.display = (vr_dv.jev?.lines || []).length ? '' : 'none';   // once Accounting saved the JEV
    } catch (e) {
        $vr('vr-body').innerHTML = `<div class="nha-error" style="display:flex;"><span>${vr_esc(e.message)}</span></div>`;
    } finally {
        window.PagePreloader?.hide();
    }
}

function vr_close() {
    $vr('vr-overlay').classList.remove('open');
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && $vr('vr-overlay')?.classList.contains('open')) vr_close();
});

// Print → the official DV form (PDF, pdftk fill); Print JEV → this DV's Journal Entry Voucher.
// Both open the shared print preview (included below); the payloads are built by its prp_* helpers.
function vr_print() {
    if (vr_dv) prp_open(prp_dvPayload(vr_dv), { type: 'DV' });
}

function vr_printJev() {
    if (vr_dv?.jev) prp_open(prp_jevFromDv(vr_dv), { type: 'JEV' });
}

function vr_render(dv) {
    $vr('vr-modal-title').textContent = `Disbursement Voucher — ${dv.dv_no}`;

    const rejection = dv.status === 'REJECTED' && dv.rejection_reason
        ? `<div class="vr-rejection"><strong>Rejected:</strong> ${vr_esc(dv.rejection_reason)}</div>` : '';

    const claimantSection = `
        <div class="vr-card">
            <p class="vr-card-title">Claimant &amp; Particulars</p>
            <dl class="vr-dl">
                <div><dt>Claimant</dt><dd>${vr_esc(dv.claimant_name) || '—'}</dd></div>
                <div><dt>Address / Employee No. / TIN</dt><dd>${vr_esc(dv.claimant_address) || '—'}${dv.employee_no_tin ? ' / ' + vr_esc(dv.employee_no_tin) : ''}</dd></div>
                <div><dt>PO Number</dt><dd>${vr_esc(dv.po_number) || '—'}</dd></div>
                <div><dt>PO Amount</dt><dd>${dv.po_amount ? vr_peso(dv.po_amount) : '—'}</dd></div>
            </dl>
            <p class="vr-particulars" style="margin-top:12px;">${vr_esc(dv.particulars) || '—'}</p>
            <dl class="vr-dl vr-dl3" style="margin-top:12px;">
                <div><dt>Gross Amount</dt><dd>${vr_peso(dv.gross_amount)}</dd></div>
                <div><dt>Net Amount</dt><dd>${vr_peso(dv.net_amount)}</dd></div>
            </dl>
        </div>`;

    // Set by Originating at creation (see app/pages/voucher/form.php) — shown whenever any exist,
    // not gated to the Accounting stage like the ATC/Accounting Entries cards below.
    const deductionSection = (dv.deductions ?? []).length ? `
        <div class="vr-card">
            <p class="vr-card-title">Deduction</p>
            <table class="vr-ledger">
                <thead><tr>
                    <th style="text-align:left;">Type</th>
                    <th style="text-align:right;">Rate</th>
                    <th style="text-align:right;">Gross</th>
                    <th style="text-align:right;">Amount</th>
                </tr></thead>
                ${dv.deductions.map(d => `<tr>
                    <td class="vr-l-label">${vr_esc(d.name)}</td>
                    <td style="text-align:right;">${d.rate != null ? d.rate + '%' : '—'}</td>
                    <td style="text-align:right;">${vr_peso(d.gross_amount)}</td>
                    <td class="vr-l-val">${vr_peso(d.deduction_amount)}</td>
                </tr>`).join('')}
                <tr class="vr-l-total"><td colspan="3" style="text-align:right;">Total Deductions</td><td class="vr-l-val">${vr_peso(dv.deductions.reduce((s, d) => s + (Number(d.deduction_amount) || 0), 0))}</td></tr>
            </table>
        </div>` : '';

    $vr('vr-body').innerHTML = `
        ${rejection}
        <div class="vr-net">
            <div><small>Net Amount</small><strong>${vr_peso(dv.net_amount)}</strong></div>
            <div style="text-align:right;"><small>Status</small><span class="nha-bdg ${vr_statusClass(dv.status)}">${vr_esc(dv.status)}</span></div>
        </div>
        <div class="vr-card">
            <p class="vr-card-title">Document Identity</p>
            <dl class="vr-dl vr-dl3">
                <div><dt>DV No.</dt><dd class="nha-mono">${vr_esc(dv.dv_no)}</dd></div>
                <div><dt>DV Date</dt><dd>${vr_fmtDate(dv.dv_date)}</dd></div>
            </dl>
        </div>
        <div class="vr-card">
            <p class="vr-card-title">Request for Allotment (RFA)</p>
            ${vr_rfaSection(dv)}
        </div>
        ${claimantSection}
        <div class="vr-card">
            <p class="vr-card-title">Budget Reference</p>
            <dl class="vr-dl vr-dl3" style="margin-bottom:12px;">
                <div><dt>Dept. / Project</dt><dd>${vr_esc(dv.dept_project) || '—'}</dd></div>
                <div><dt>Fund Source</dt><dd>${vr_esc(dv.fund_source) || '—'}</dd></div>
                <div><dt>Account Code</dt><dd>${vr_accountCodeDisplay(dv)}</dd></div>
            </dl>
            <table class="vr-ledger">
                <tr><td class="vr-l-label">Budget</td><td class="vr-l-val">${vr_peso(dv.budget_amount)}</td></tr>
                <tr><td class="vr-l-label">Obligation</td><td class="vr-l-val">${vr_peso(dv.obligation_amount)}</td></tr>
                <tr><td class="vr-l-label">Payments${(Number(dv.payments_prior_amount) || 0) > 0 ? ' <small>(prior draws on these RFAs)</small>' : ''}</td><td class="vr-l-val">${vr_peso(dv.payments_prior_amount)}</td></tr>
                <tr><td class="vr-l-label">This Request</td><td class="vr-l-val">${vr_peso(dv.net_amount)}</td></tr>
                <tr class="vr-l-total"><td class="vr-l-label">Total Payments</td><td class="vr-l-val">${vr_peso((Number(dv.payments_prior_amount) || 0) + (Number(dv.net_amount) || 0))}</td></tr>
                <tr><td class="vr-l-label">Balance</td><td class="vr-l-val">${vr_peso(dv.balance_amount)}</td></tr>
            </table>
            ${dv.remarks ? `<p class="vr-particulars" style="margin-top:12px;"><strong>Remarks:</strong> ${vr_esc(dv.remarks)}</p>` : ''}
        </div>
        ${vr_attachmentsSection(dv)}
        ${deductionSection}
        ${vje_visible(dv) ? vje_section(dv) : vje_groupedEntriesCard(dv)}
        <div class="vr-card" style="margin-bottom:0;">
            <p class="vr-card-title">Workflow History</p>
            <dl class="vr-dl vr-dl3">
                <div><dt>Originating (Submitted)</dt><dd>${vr_fmtDateTime(dv.submitted_at)}</dd></div>
                <div><dt>FMD (Obligation Checked)</dt><dd>${vr_fmtDateTime(dv.fmd_approved_at)}</dd></div>
                <div><dt>Accounting (Processed)</dt><dd>${vr_fmtDateTime(dv.accounting_processed_at)}</dd></div>
                <div><dt>Treasury (Released)</dt><dd>${vr_fmtDateTime(dv.treasury_released_at)}</dd></div>
            </dl>
        </div>`;

    vr_renderActions(dv);
    if (vje_visible(dv)) vje_init(dv);
}

/* One or more RFAs this DV was drawn against (see app/pages/voucher/form.php's multi-select) —
   dv.rfas is the full-detail array from DisbursementVoucherService::fetchById(), built from
   fims.budget_allocation.dv_id (reverse lookup — see DisbursementVoucherService::linkRfas()). */
function vr_rfaSection(dv) {
    const rfas = dv.rfas ?? [];
    if (!rfas.length) return `<p class="vr-particulars">—</p>`;
    return `<div class="vr-rfa-list">${rfas.map(r => `
        <div class="vr-rfa-item">
            <strong class="nha-mono">${vr_esc(r.allocation_number)}</strong>
            <dl class="vr-dl vr-dl3" style="margin-top:6px;">
                <div><dt>Department</dt><dd>${vr_esc(r.department_name) || 'Unassigned'}</dd></div>
                <div><dt>Fiscal Year</dt><dd>${vr_esc(r.fiscal_year)}</dd></div>
                <div><dt>Vendor</dt><dd>${vr_esc(r.vendor_name) || '—'}</dd></div>
                <div><dt>Remaining</dt><dd>${vr_peso(r.remaining_balance)} of ${vr_peso(r.budget_amount)}</dd></div>
            </dl>
        </div>`).join('')}</div>`;
}

/* Supporting Documents attached via the Attachments card on the form — dv.file_attachments comes
   from FileUploadServices::attachmentsFor(). Nothing to show when the voucher has none. */
function vr_attachmentsSection(dv) {
    const files = dv.file_attachments ?? [];
    if (!files.length) return '';
    return `<div class="vr-card">
        <p class="vr-card-title">Attachments</p>
        <div class="vr-att-list">${files.map(f => {
            const url = f.file_meta?.view_url || f.file_meta?.link || '';
            const name = vr_esc(f.file_meta?.document_name || 'Supporting Document');
            return url ? `<a class="vr-att-item" href="${url}" target="_blank" rel="noopener noreferrer">${name}</a>` : `<span class="vr-att-item">${name}</span>`;
        }).join('')}</div>
    </div>`;
}

// Mirrors DisbursementVoucherService::authorizeTransition() — hiding a button is cosmetic only,
// the API re-checks and is the real gate.
function vr_mayTransition(dv, target) {
    if (VR_IS_SYSADMIN) return true;
    const holderRc = VR_STAGE_HOLDER_RC[dv.status] || null; // RC currently holding the voucher (null = DRAFT)
    if (target === 'CANCELLED') {
        const isCreator = VR_CURRENT_USER_ID > 0 && Number(dv.created_by) === VR_CURRENT_USER_ID;
        const isHolder = holderRc !== null && VR_RC_ID === holderRc;
        return isCreator || isHolder;
    }
    if (holderRc === null) return true; // DRAFT -> FOR_FMD_REVIEW: Originating submits, open to anyone.
    return VR_RC_ID === holderRc;
}

function vr_renderActions(dv) {
    const allowed = (dv.allowed_transitions || []).filter(t => vr_mayTransition(dv, t));
    const btn = (label, status, cls) => `<button type="button" class="nha-btn ${cls}" style="margin-left:8px;" onclick="vr_changeStatus('${status}')">${label}</button>`;
    let html = '';
    if (allowed.includes('FOR_FMD_REVIEW')) html += btn('Submit to FMD', 'FOR_FMD_REVIEW', 'nha-btn-primary');
    if (allowed.includes('FOR_ACCOUNTING')) html += btn('FMD Approve', 'FOR_ACCOUNTING', 'nha-btn-primary');
    if (allowed.includes('FOR_TREASURY'))   html += btn('Forward to Treasury', 'FOR_TREASURY', 'nha-btn-primary');
    if (allowed.includes('RELEASED'))       html += btn('Release Payment', 'RELEASED', 'nha-btn-primary');
    if (allowed.includes('REJECTED'))       html += btn('Reject', 'REJECTED', 'nha-btn-outline');
    if (allowed.includes('CANCELLED'))      html += btn('Cancel', 'CANCELLED', 'nha-btn-ghost');
    $vr('vr-actions').innerHTML = html;
}

/* ══════════════════════════════════════════════════════════════
   JOURNAL ENTRY (Accounting stage)   prefix: vje_
   Three sub-sections, all saved together via one POST .../journal-entry:
     - Withholding Tax Classification (ATC) — 0+ rows, fims.disbursement_voucher_atc_lines
     - Tax Computation — derived display only, nothing to save
     - Accounting Entries (DV) — the actual debit/credit JEV, with optional SL Code/SL Detail
═══════════════════════════════════════════════════════════════ */

let VJE_COA = []; // posting_allowed chart-of-accounts rows, loaded once per modal open
let VJE_ATC = []; // active ATC codes, loaded once per modal open
let VJE_RC  = []; // responsibility centers, for ATC "Chargeability", loaded once per modal open
let VJE_SL  = []; // SL masters, for the Accounting Entries grid's SL Code picker, loaded once per modal open
let vje_seq    = 0;
let vje_groupSeq = 0; // GL account-group id counter for the Accounting Entries grid (see vje_addGroup())
let vje_atcSeq = 0;
let vje_dv     = null; // the open DV — ATC rows reference its net_amount (deductions already applied) as a Tax Base default
let vje_autoMode = true; // true until a JEV has been saved at least once this visit — governs whether Accounting Entries auto-syncs from Deduction/ATC rows (see vje_syncAutoLines)

// Visible only while the voucher is at FOR_ACCOUNTING and the viewer is Accounting Dept (RC 012)
// or sysadmin — same rule the server enforces in DisbursementVoucherService::saveJournalEntry().
function vje_visible(dv) {
    return dv.status === 'FOR_ACCOUNTING' && (VR_IS_SYSADMIN || VR_RC_ID === '012');
}

function vje_section(dv) {
    return vje_atcCard(dv) + vje_entriesCard(dv);
}

/**
 * Read-only "Accounting Entries" presentation shown whenever a JEV exists but Accounting isn't
 * actively editing it (any stage past FOR_ACCOUNTING — see vje_visible()'s doc comment) — the
 * editable grid (vje_entriesCard) disappears once the DV moves on, so without this the printed
 * voucher would show no journal entry at all past that point. Grouped by GL account (UACS code):
 * the group's own row carries the account's Debit/Credit TOTAL (summed from its own lines), and
 * each line under it is its SL Code breakdown. The sub-line label is the line's own description
 * (set when Accounting prepared the JEV — see vje_syncAutoLines()) falling back to its SL Detail /
 * SL Code name, and the small number is the line's position in the JEV (jevl.line_number), not an
 * SL code.
 */
function vje_groupedEntriesCard(dv) {
    const lines = dv.jev?.lines ?? [];
    if (!lines.length) return '';

    const groups = [];
    const byAccount = new Map();
    lines.forEach(l => {
        if (!byAccount.has(l.account_code)) {
            const group = { account_code: l.account_code, account_title: l.account_title, lines: [], debit: 0, credit: 0 };
            byAccount.set(l.account_code, group);
            groups.push(group);
        }
        const g = byAccount.get(l.account_code);
        g.lines.push(l);
        g.debit  += Number(l.debit)  || 0;
        g.credit += Number(l.credit) || 0;
    });

    const rows = groups.map(g => {
        const subRows = g.lines.map(l => `<tr class="vje-grp-sub">
                <td>${vr_esc(l.description || l.sl_detail_name || l.sl_name || '—')}</td>
                <td class="vje-grp-code">${vr_esc(l.sl_code) || ''}</td>
                <td class="vje-grp-amt">${Number(l.debit)  > 0 ? vr_peso(l.debit)  : ''}</td>
                <td class="vje-grp-amt">${Number(l.credit) > 0 ? vr_peso(l.credit) : ''}</td>
                <td class="vje-grp-code">${String(l.line_number).padStart(2, '0')}</td>
            </tr>`).join('');
        return `<tr class="vje-grp-hdr">
                <td>${vr_esc(g.account_code)} — ${vr_esc(g.account_title || '—')}</td>
                <td></td>
                <td class="vje-grp-amt">${vr_peso(g.debit)}</td>
                <td class="vje-grp-amt">${vr_peso(g.credit)}</td>
                <td></td>
            </tr>${subRows}`;
    }).join('');

    const totalDebit  = lines.reduce((s, l) => s + (Number(l.debit)  || 0), 0);
    const totalCredit = lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);

    return `<div class="vr-card">
        <p class="vr-card-title">Accounting Entries</p>
        <div style="overflow-x:auto;">
        <table class="vje-grp-table" style="min-width:600px;">
            <thead><tr>
                <th style="text-align:left;">Account Code / Description</th>
                <th style="width:70px;">SL Code</th>
                <th style="width:110px;">Debit (₱)</th>
                <th style="width:110px;">Credit (₱)</th>
                <th style="width:26px;">#</th>
            </tr></thead>
            <tbody>${rows}</tbody>
            <tfoot><tr class="vje-grp-hdr">
                <td colspan="2" style="text-align:right;">Total</td>
                <td class="vje-grp-amt">${vr_peso(totalDebit)}</td>
                <td class="vje-grp-amt">${vr_peso(totalCredit)}</td>
                <td></td>
            </tr></tfoot>
        </table>
        </div>
    </div>`;
}

function vje_atcCard(dv) {
    return `
        <div class="vr-card">
            <div class="vje-section-hdr">
                <p class="vr-card-title" style="margin:0;">ATC</p>
                <span class="vje-badge">Accounting Entry</span>
            </div>
            <p class="vr-hint" style="margin:0 0 8px;">Only add ATC rows if withholding tax applies to this transaction.</p>
            <div style="overflow-x:auto;">
                <table class="vr-ledger" style="min-width:760px;">
                    <thead><tr>
                        <th style="text-align:left;">ATC Code — Description</th>
                        <th style="text-align:right;width:85px;">Rate (%)</th>
                        <th style="text-align:right;width:130px;">Tax Base (₱)</th>
                        <th style="text-align:right;width:130px;">Withholding Tax (₱)</th>
                        <th style="text-align:left;width:170px;">Chargeability</th>
                        <th style="width:30px;"></th>
                    </tr></thead>
                    <tbody id="vje-atc-tbody"></tbody>
                    <tfoot><tr class="vr-l-total">
                        <td colspan="3" style="text-align:right;">Total Withholding Tax</td>
                        <td class="vr-l-val" id="vje-atc-total">₱ 0.00</td>
                        <td colspan="2"></td>
                    </tr></tfoot>
                </table>
            </div>
            <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vje_addAtcLine()">+ Add ATC Row</button>

            <div class="vje-tax-box" style="margin-top:18px;">
                <div class="vje-section-hdr" style="margin-bottom:8px;">
                    <p style="margin:0;font-weight:700;font-size:13px;">Tax Computation</p>
                    <span class="vje-badge">Accounting Entry</span>
                </div>
                <dl>
                    <dt>Gross Amount</dt><dd id="vje-tax-gross">₱ 0.00</dd>
                    <dt>Tax Base</dt><dd id="vje-tax-base">₱ 0.00</dd>
                    <dt>Withholding Tax</dt><dd id="vje-tax-wh">₱ 0.00</dd>
                </dl>
                <dl class="vje-tax-total" style="margin-top:6px;border-top:1px solid var(--afims-warning-soft,#FDF0D5);padding-top:6px;">
                    <dt>Net Amount</dt><dd id="vje-tax-net">₱ 0.00</dd>
                </dl>
            </div>
        </div>`;
}

function vje_entriesCard(dv) {
    return `
        <div class="vr-card">
            <div class="vje-section-hdr">
                <p class="vr-card-title" style="margin:0;">Accounting Entries</p>
                <span class="vje-badge">Accounting Entry</span>
            </div>
            <p class="vr-hint" style="margin:0 0 8px;">Grouped by GL account (UACS code) — pick the account once per group (<span class="vje-auto-tag">auto</span> groups are filled from Deduction/ATC rows that have a UACS account code, and stay in sync with them until this Journal Entry is first saved), then add one SL Code line per amount under it.</p>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                <span class="vje-amt-badge">Required: DR = CR (balanced)</span>
                <span class="vje-amt-badge">Gross: <span id="vje-badge-gross">₱ 0.00</span></span>
                <span class="vje-amt-badge">Net after Deductions: <span id="vje-badge-net-ded">₱ 0.00</span></span>
            </div>
            <div style="overflow-x:auto;">
                <table class="vr-ledger vje-grp-edit-table" style="min-width:760px;">
                    <thead><tr>
                        <th style="text-align:left;">Account Code / Description</th>
                        <th style="text-align:left;">SL Code</th>
                        <th style="text-align:right;width:110px;">Debit (₱)</th>
                        <th style="text-align:right;width:110px;">Credit (₱)</th>
                        <th style="width:26px;">#</th>
                        <th style="width:30px;"></th>
                    </tr></thead>
                    <tbody id="vje-lines-tbody"></tbody>
                    <tfoot><tr class="vr-l-total">
                        <td colspan="2" style="text-align:right;">Total</td>
                        <td class="vr-l-val" id="vje-total-debit">₱ 0.00</td>
                        <td class="vr-l-val" id="vje-total-credit">₱ 0.00</td>
                        <td></td>
                        <td></td>
                    </tr></tfoot>
                </table>
            </div>
            <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vje_addNewGroup()">+ Add GL Account</button>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;">
                <span id="vje-balance-msg" style="font-weight:600;font-size:13px;"></span>
                <button type="button" class="nha-btn nha-btn-primary" onclick="vje_save()">Save Journal Entry</button>
            </div>
        </div>`;
}

async function vje_getJson(path) {
    const res  = await vr_authFetch(VR_API + path);
    if (!res.ok) throw new Error('HTTP ' + res.status);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Request failed.');
    return json;
}

async function vje_init(dv) {
    vje_dv = dv;
    try {
        const [coaJson, atcJson, rcJson, slJson] = await Promise.all([
            // postable_only (not the region-scoped /chart-of-accounts/all) — posting a voucher
            // isn't restricted to the preparer's own region, so every active+postable GL account
            // should be selectable here regardless of which region it belongs to.
            vje_getJson('/chart_of_accounts/all?postable_only=1'),
            vje_getJson('/voucher/atc-codes'),
            vje_getJson('/responsibility-center/all'),
            vje_getJson('/voucher/sl-codes'),
        ]);
        VJE_COA = (coaJson.data ?? []).filter(a => a.posting_allowed);
        VJE_ATC = atcJson.data ?? [];
        VJE_RC  = rcJson.data ?? [];
        VJE_SL  = slJson.data ?? [];
    } catch (e) {
        VJE_COA = []; VJE_ATC = []; VJE_RC = []; VJE_SL = [];
    }

    vje_atcSeq = 0;
    $vr('vje-atc-tbody').innerHTML = '';
    (dv.atc_lines ?? []).forEach(a => vje_addAtcLine(a));
    vje_recalcAtc();

    vje_seq = 0;
    vje_groupSeq = 0;
    $vr('vje-lines-tbody').innerHTML = '';
    if (dv.jev && (dv.jev.lines ?? []).length) {
        // Already saved once — respect what Accounting committed; auto-sync stops here even if
        // they add more ATC rows afterward (see vje_syncAutoLines's doc comment). Lines were saved
        // in GL-grouped order (see vje_collectLines()), so a run of consecutive same-account lines
        // reopens as one group, same as it was entered.
        vje_autoMode = false;
        let lastAcct = null, gid = null;
        dv.jev.lines.forEach(l => {
            if (l.account_code !== lastAcct) {
                gid = vje_addGroup(l.account_code);
                lastAcct = l.account_code;
            }
            vje_addGroupLine(gid, l);
        });
        vje_renumber();
        vje_recalc();
    } else {
        vje_autoMode = true;
        vje_syncAutoLines();
    }

    $vr('vje-badge-gross').textContent = vr_peso(dv.gross_amount);
    $vr('vje-badge-net-ded').textContent = vr_peso(dv.net_amount);
}

/* ── Withholding Tax Classification (ATC) ────────────────────── */
function vje_addAtcLine(a = null) {
    const idx = ++vje_atcSeq;
    const tr = document.createElement('tr');
    tr.id = `vje-atc-row-${idx}`;
    tr.innerHTML = `
        <td><input type="text" class="vje-atc-code" placeholder="Search ATC code…"></td>
        <td><input type="number" class="vje-atc-rate" min="0" max="100" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="vje-atc-base" min="0" step="0.01" placeholder="0.00"></td>
        <td class="vr-l-val vje-atc-wh">₱ 0.00</td>
        <td><select class="vje-atc-rc">
            <option value="">— None —</option>
            ${VJE_RC.map(r => `<option value="${vr_esc(r.rc_id)}">${vr_esc(r.rc_id)} — ${vr_esc(r.rc_name)}</option>`).join('')}
        </select></td>
        <td><button type="button" class="vje-remove" title="Remove" onclick="vje_removeAtcLine(${idx})">&times;</button></td>`;
    tr.querySelector('.vje-atc-code').value = a?.atc_code ?? '';
    tr.querySelector('.vje-atc-rate').value = a?.rate ?? '';
    tr.querySelector('.vje-atc-base').value = a ? (Number(a.tax_base) || 0).toFixed(2) : (Number(vje_dv?.net_amount) || 0).toFixed(2);
    tr.querySelector('.vje-atc-rc').value   = a?.chargeability ?? '';
    tr.querySelector('.vje-atc-code').addEventListener('change', () => vje_onAtcCodeSelect(idx));
    tr.querySelectorAll('.vje-atc-rate, .vje-atc-base').forEach(el => el.addEventListener('input', () => vje_recalcAtcLine(idx)));
    $vr('vje-atc-tbody').appendChild(tr);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(tr.querySelector('.vje-atc-code'), VJE_ATC.map(c => ({ value: c.atc_code, label: c.description })));
    vje_recalcAtcLine(idx);
}

function vje_removeAtcLine(idx) {
    $vr(`vje-atc-row-${idx}`)?.remove();
    vje_recalcAtc();
    vje_syncAutoLines();
}

/**
 * Picking an ATC code prefills the rest of its row — same convention as app/pages/voucher/form.php's
 * vf_onAtcCodeSelect(): Rate(%) always follows the code's own BIR default (fims.atc_codes.tax_rate,
 * overwriting whatever was there), Tax Base is already defaulted to this DV's net_amount at row
 * creation (see vje_addAtcLine) and is left alone here, and Chargeability defaults — if not already
 * set — to the first RFA this DV cites (fims.budget_allocation.requesting_rc_id), since that's who
 * the withholding is normally charged against.
 */
function vje_onAtcCodeSelect(idx) {
    const tr = $vr(`vje-atc-row-${idx}`);
    if (!tr) return;
    const code   = tr.querySelector('.vje-atc-code').value;
    const atcDef = VJE_ATC.find(c => c.atc_code === code);
    const rateEl = tr.querySelector('.vje-atc-rate');
    const rcEl   = tr.querySelector('.vje-atc-rc');

    if (atcDef && isFinite(Number(atcDef.tax_rate))) rateEl.value = Number(atcDef.tax_rate).toFixed(2);

    if (!rcEl.value) {
        const primaryRc = vje_dv?.rfas?.[0]?.requesting_rc_id;
        if (primaryRc) rcEl.value = primaryRc;
    }

    vje_recalcAtcLine(idx);
}

function vje_recalcAtcLine(idx) {
    const tr = $vr(`vje-atc-row-${idx}`);
    if (!tr) return;
    const rate = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
    const base = parseFloat(tr.querySelector('.vje-atc-base').value) || 0;
    tr.querySelector('.vje-atc-wh').textContent = vr_peso(base * rate / 100);
    vje_recalcAtc();
    vje_syncAutoLines();
}

function vje_collectAtcLines() {
    const lines = [];
    $vr('vje-atc-tbody').querySelectorAll('tr').forEach(tr => {
        const atc_code = tr.querySelector('.vje-atc-code').value;
        const rate     = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
        const tax_base = parseFloat(tr.querySelector('.vje-atc-base').value) || 0;
        if (!atc_code && tax_base <= 0) return; // blank trailing row
        lines.push({ atc_code, rate, tax_base, chargeability: tr.querySelector('.vje-atc-rc').value || null });
    });
    return lines;
}

function vje_recalcAtc() {
    const lines = vje_collectAtcLines();
    const totalWh = lines.reduce((s, l) => s + (l.tax_base * l.rate / 100), 0);
    $vr('vje-atc-total').textContent = vr_peso(totalWh);

    const gross   = Number(vje_dv?.gross_amount) || 0;
    const netPreWh = Number(vje_dv?.net_amount) || 0; // already net of deductions
    const totalBase = lines.reduce((s, l) => s + l.tax_base, 0);
    $vr('vje-tax-gross').textContent = vr_peso(gross);
    $vr('vje-tax-base').textContent  = vr_peso(totalBase);
    $vr('vje-tax-wh').textContent    = vr_peso(totalWh);
    $vr('vje-tax-net').textContent   = vr_peso(netPreWh - totalWh);
}

/* ── Accounting Entries (DV) ──────────────────────────────────── */

/**
 * Upserts the auto-derived lines (Dr expense for the full gross, Cr each Deduction/ATC row that
 * carries a mapped UACS account code, Cr Accounts Payable for whatever's left) into the grid,
 * tracked via each row's data-auto-source so repeat syncs update in place instead of duplicating.
 * Only runs while vje_autoMode is true — see vje_init()'s doc comment. A no-op once Accounting has
 * saved this Journal Entry at least once this visit.
 */
/* ── Accounting Entries grid: GL-grouped, "Select GL then its sub-lines" ──────────────────────
   One header row per GL account (its own Account Code picker + add/remove-group controls),
   followed by one row per amount under that account — Description, an optional nested SL Code
   combo (SL Code -> SL Detail), Debit/Credit, and a running line number. Mirrors
   app/pages/voucher/form.php's cascading GL -> SL Code convention (vf_populateSlCombo) but one
   level up: GL -> its own lines. ── */

function vje_groupHdr(gid) {
    return $vr('vje-lines-tbody').querySelector(`tr.vje-grp-hdr[data-group-id="${gid}"]`);
}
function vje_groupAcct(gid) {
    return vje_groupHdr(gid)?.querySelector('.vje-grp-acct').value.trim() || '';
}
function vje_groupLines(gid) {
    return [...$vr('vje-lines-tbody').querySelectorAll(`tr.vje-grp-line[data-group-id="${gid}"]`)];
}
/** The existing group whose Account Code already matches, or a freshly created one. */
function vje_findOrCreateGroup(acctCode) {
    const hdr = [...$vr('vje-lines-tbody').querySelectorAll('tr.vje-grp-hdr')]
        .find(h => h.querySelector('.vje-grp-acct').value.trim() === acctCode && acctCode !== '');
    return hdr ? hdr.dataset.groupId : vje_addGroup(acctCode);
}

function vje_addGroup(acctCode = '') {
    const gid = ++vje_groupSeq;
    const hdr = document.createElement('tr');
    hdr.className = 'vje-grp-hdr';
    hdr.dataset.groupId = gid;
    hdr.innerHTML = `
        <td colspan="2"><input type="text" class="vje-grp-acct" placeholder="Search GL account code (UACS)…"></td>
        <td class="vr-l-val vje-grp-debit"></td>
        <td class="vr-l-val vje-grp-credit"></td>
        <td style="text-align:right;"><button type="button" class="nha-btn nha-btn-ghost vje-grp-add-btn" title="Add an SL Code line under this account">+ Line</button></td>
        <td><button type="button" class="vje-remove" title="Remove this account and its lines">&times;</button></td>`;
    hdr.querySelector('.vje-grp-acct').value = acctCode;
    $vr('vje-lines-tbody').appendChild(hdr);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(hdr.querySelector('.vje-grp-acct'), VJE_COA.map(a => ({ value: a.account_code, label: a.account_title })));
    hdr.querySelector('.vje-grp-acct').addEventListener('change', () => vje_onGroupAcctChange(gid));
    hdr.querySelector('.vje-grp-add-btn').addEventListener('click', () => { vje_addGroupLine(gid); vje_renumber(); vje_recalc(); });
    hdr.querySelector('.vje-remove').addEventListener('click', () => vje_removeGroup(gid));
    vje_updateGroupHeaderTotals(gid);
    return gid;
}

/** The group's own Debit/Credit total — summed from this group's SL Code lines (never
    independently edited). */
function vje_updateGroupHeaderTotals(gid) {
    const hdr = vje_groupHdr(gid);
    if (!hdr) return;
    const lines = vje_groupLines(gid).map(tr => ({
        debit: parseFloat(tr.querySelector('.vje-debit').value) || 0,
        credit: parseFloat(tr.querySelector('.vje-credit').value) || 0,
    }));
    hdr.querySelector('.vje-grp-debit').textContent  = vr_peso(lines.reduce((s, l) => s + l.debit, 0));
    hdr.querySelector('.vje-grp-credit').textContent = vr_peso(lines.reduce((s, l) => s + l.credit, 0));
}

function vje_addNewGroup() {
    const gid = vje_addGroup('');
    vje_addGroupLine(gid);
    vje_renumber();
}

function vje_removeGroup(gid) {
    vje_groupLines(gid).forEach(tr => tr.remove());
    vje_groupHdr(gid)?.remove();
    vje_renumber();
    vje_recalc();
}

/** GL Account Code changed on a group header — rebuild every line's SL Code combo (nested
    SL Code -> SL Detail options) under the newly-picked GL account, preserving a line's previous
    pick only if it still belongs to this account. */
function vje_onGroupAcctChange(gid) {
    const acctCode = vje_groupAcct(gid);
    vje_groupLines(gid).forEach(tr => {
        const lineId = tr.dataset.lineId;
        const [slCode, slDetailId] = vje_comboValue(tr);
        vje_populateSlCombo(lineId, acctCode, slCode, slDetailId);
    });
    vje_updateGroupHeaderTotals(gid);
    vje_recalc();
}

function vje_addGroupLine(gid, d = null) {
    const hdr = vje_groupHdr(gid);
    if (!hdr) return null;
    const lineId = ++vje_seq;
    const tr = document.createElement('tr');
    tr.id = `vje-row-${lineId}`;
    tr.className = 'vje-grp-line';
    tr.dataset.groupId = gid;
    tr.dataset.lineId  = lineId;
    tr.innerHTML = `
        <td><input type="text" class="vje-line-desc" placeholder="Description"></td>
        <td><select class="vje-sl-combo"><option value="">— None —</option></select></td>
        <td><input type="number" class="vje-debit" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="vje-credit" min="0" step="0.01" placeholder="0.00"></td>
        <td class="vr-l-val vje-line-no"></td>
        <td><button type="button" class="vje-remove" title="Remove line" onclick="vje_removeGroupLine(${lineId})">&times;</button></td>`;
    tr.querySelector('.vje-line-desc').value = d?.description ?? '';
    tr.querySelector('.vje-debit').value  = Number(d?.debit)  > 0 ? Number(d.debit).toFixed(2)  : '';
    tr.querySelector('.vje-credit').value = Number(d?.credit) > 0 ? Number(d.credit).toFixed(2) : '';
    tr.querySelector('.vje-debit').addEventListener('input', vje_recalc);
    tr.querySelector('.vje-credit').addEventListener('input', vje_recalc);
    // Insert right after this group's last line (or right after its header if it has none yet) —
    // keeps every line visually/physically under its own GL header.
    const existing = vje_groupLines(gid);
    (existing.length ? existing[existing.length - 1] : hdr).after(tr);
    vje_populateSlCombo(lineId, vje_groupAcct(gid), d?.sl_code ?? null, d?.sl_detail_id ?? null);
    vje_recalc();
    return lineId;
}

function vje_removeGroupLine(lineId) {
    const tr = $vr(`vje-row-${lineId}`);
    const gid = tr?.dataset.groupId;
    tr?.remove();
    if (gid && vje_groupLines(gid).length === 0) vje_removeGroup(gid); // header with no lines left is dead weight
    vje_renumber();
    vje_recalc();
}

/** Running "01", "02", … across every line (not header) row, in on-screen order — same number the
    read-only vje_groupedEntriesCard() prints once this JEV is saved. */
function vje_renumber() {
    let n = 0;
    $vr('vje-lines-tbody').querySelectorAll('tr.vje-grp-line').forEach(tr => {
        n += 1;
        tr.querySelector('.vje-line-no').textContent = String(n).padStart(2, '0');
        const descCell = tr.querySelector('.vje-line-desc').parentElement;
        let tag = descCell.querySelector('.vje-auto-tag');
        if (tr.dataset.autoSource) {
            if (!tag) descCell.insertAdjacentHTML('beforeend', ' <span class="vje-auto-tag">auto</span>');
        } else {
            tag?.remove();
        }
    });
}

function vje_syncAutoLines() {
    if (!vje_autoMode) return;
    const gross = Number(vje_dv?.gross_amount) || 0;

    // description is what the grouped read-only "Accounting Entries" card (vje_groupedEntriesCard())
    // prints under each GL account's header — persisted with the line on save, same as
    // account_code/debit/credit, so the printed voucher keeps reading correctly after this editing
    // session ends.
    vje_upsertAutoLine('dr', { account_code: vje_dv?.account_code || '', debit: gross, credit: 0, description: vje_dv?.claimant_name || '' });

    const dedIds = new Set();
    let dedCreditTotal = 0;
    (vje_dv?.deductions ?? []).forEach(d => {
        if (!d.account_code) return;
        dedIds.add(String(d.dv_deduction_id));
        dedCreditTotal += Number(d.deduction_amount) || 0;
        vje_upsertAutoLine(`ded:${d.dv_deduction_id}`, { account_code: d.account_code, debit: 0, credit: Number(d.deduction_amount) || 0, description: d.name });
    });
    vje_pruneAutoLines('ded:', id => dedIds.has(id));

    const atcIds = new Set();
    let atcCreditTotal = 0;
    $vr('vje-atc-tbody').querySelectorAll('tr').forEach(tr => {
        const idx  = tr.id.replace('vje-atc-row-', '');
        const code = tr.querySelector('.vje-atc-code').value;
        const rate = parseFloat(tr.querySelector('.vje-atc-rate').value) || 0;
        const base = parseFloat(tr.querySelector('.vje-atc-base').value) || 0;
        const atcDef = VJE_ATC.find(c => c.atc_code === code);
        if (!code || !atcDef?.account_code || base <= 0 || rate <= 0) return;
        const credit = Math.round(base * rate) / 100;
        atcIds.add(idx);
        atcCreditTotal += credit;
        // Several ATC rows commonly share one GL (e.g. several withholding rates all post to "Due
        // to BIR") — vje_upsertAutoLine finds-or-creates that GL's group, so they land as sibling
        // lines under ONE header instead of one header each.
        vje_upsertAutoLine(`atc:${idx}`, { account_code: atcDef.account_code, debit: 0, credit, description: `${rate}% ${atcDef.description}` });
    });
    vje_pruneAutoLines('atc:', id => atcIds.has(id));

    const apRemainder = Math.round((gross - dedCreditTotal - atcCreditTotal) * 100) / 100;
    if (apRemainder > 0.004) {
        vje_upsertAutoLine('ap', { account_code: '2-01-01-010', debit: 0, credit: apRemainder, description: 'Net Amount Payable' });
    } else {
        const tr = $vr('vje-lines-tbody').querySelector('tr.vje-grp-line[data-auto-source="ap"]');
        const gid = tr?.dataset.groupId;
        tr?.remove();
        if (gid && vje_groupLines(gid).length === 0) vje_removeGroup(gid);
    }

    vje_renumber();
    vje_recalc();
}

function vje_upsertAutoLine(sourceKey, d) {
    const tr = $vr('vje-lines-tbody').querySelector(`tr.vje-grp-line[data-auto-source="${sourceKey}"]`);
    if (!tr) {
        const gid = vje_findOrCreateGroup(d.account_code || '');
        const lineId = vje_addGroupLine(gid, d);
        $vr(`vje-row-${lineId}`).dataset.autoSource = sourceKey;
        return;
    }
    // If the account this line should post to changed (e.g. a different ATC code's mapped
    // account), move it to the right group rather than leaving it under the old one.
    if ((d.account_code || '') !== vje_groupAcct(tr.dataset.groupId)) {
        const oldGid = tr.dataset.groupId;
        tr.remove();
        if (vje_groupLines(oldGid).length === 0) vje_removeGroup(oldGid);
        const gid = vje_findOrCreateGroup(d.account_code || '');
        const lineId = vje_addGroupLine(gid, d);
        $vr(`vje-row-${lineId}`).dataset.autoSource = sourceKey;
        return;
    }
    tr.querySelector('.vje-line-desc').value = d.description || '';
    tr.querySelector('.vje-debit').value  = d.debit  > 0 ? d.debit.toFixed(2)  : '';
    tr.querySelector('.vje-credit').value = d.credit > 0 ? d.credit.toFixed(2) : '';
}

/** Removes auto-tracked lines whose source (a Deduction or live ATC row) no longer applies, and
    any group left with no lines as a result. */
function vje_pruneAutoLines(prefix, keepFn) {
    $vr('vje-lines-tbody').querySelectorAll(`tr.vje-grp-line[data-auto-source^="${prefix}"]`).forEach(tr => {
        if (keepFn(tr.dataset.autoSource.slice(prefix.length))) return;
        const gid = tr.dataset.groupId;
        tr.remove();
        if (vje_groupLines(gid).length === 0) vje_removeGroup(gid);
    });
}

/** Reads a line's current SL Code combo pick, split back into [sl_code, sl_detail_id] (both null
    if "— None —" is selected) — see vje_populateSlCombo()'s value encoding. */
function vje_comboValue(tr) {
    const raw = tr.querySelector('.vje-sl-combo')?.value || '';
    if (!raw) return [null, null];
    const [slCode, slDetailId] = raw.split('::');
    return [slCode || null, slDetailId || null];
}

/** SL Code is a sub-list of the chosen GL Account Code (fims.sl_masters.account_code); same
    cascading convention as app/pages/voucher/form.php's vf_populateSlCombo() — each SL Code in turn
    has its own SL Details, so rather than two separate pickers this builds ONE combo select per
    line: an <optgroup> per matching SL Code, with its SL Details as the selectable leaf options (an
    SL Code with no details is offered directly as a leaf option). Option values are
    "slCode::slDetailId" so vje_comboValue() can recover both in one read. Re-selects
    preSlCode/preDetailId if that combination still exists under $acctCode. */
async function vje_populateSlCombo(lineId, acctCode, preSlCode = null, preDetailId = null) {
    const tr  = $vr(`vje-row-${lineId}`);
    const sel = tr?.querySelector('.vje-sl-combo');
    if (!sel) return;
    const matching = acctCode ? VJE_SL.filter(s => s.account_code === acctCode) : [];
    sel.innerHTML = '<option value="">— None —</option>';
    if (!matching.length) return;
    const detailLists = await Promise.all(matching.map(s =>
        vje_getJson(`/voucher/sl-codes/${encodeURIComponent(s.sl_code)}/details`).then(j => j.data ?? []).catch(() => [])));
    matching.forEach((s, i) => {
        const details = detailLists[i];
        if (!details.length) {
            const opt = document.createElement('option');
            opt.value = `${s.sl_code}::`;
            opt.textContent = `${s.sl_code} — ${s.sl_name}`;
            sel.appendChild(opt);
            return;
        }
        const og = document.createElement('optgroup');
        og.label = `${s.sl_code} — ${s.sl_name}`;
        details.forEach(d => {
            const opt = document.createElement('option');
            opt.value = `${s.sl_code}::${d.sl_detail_id}`;
            opt.textContent = `${d.detail_code} — ${d.detail_name}`;
            og.appendChild(opt);
        });
        sel.appendChild(og);
    });
    if (preSlCode) {
        const want = `${preSlCode}::${preDetailId ?? ''}`;
        if ([...sel.options].some(o => o.value === want)) sel.value = want;
    }
}

function vje_collectLines() {
    const lines = [];
    $vr('vje-lines-tbody').querySelectorAll('tr.vje-grp-hdr').forEach(hdr => {
        const gid          = hdr.dataset.groupId;
        const account_code = hdr.querySelector('.vje-grp-acct').value.trim();
        vje_groupLines(gid).forEach(tr => {
            const debit       = parseFloat(tr.querySelector('.vje-debit').value)  || 0;
            const credit      = parseFloat(tr.querySelector('.vje-credit').value) || 0;
            const description = tr.querySelector('.vje-line-desc').value.trim();
            const [sl_code, sl_detail_id] = vje_comboValue(tr);
            if (!account_code && debit <= 0 && credit <= 0 && !description) return; // blank trailing line
            lines.push({
                account_code, debit, credit,
                sl_code      : sl_code,
                sl_detail_id : sl_detail_id ? Number(sl_detail_id) : null,
                description  : description || null,
            });
        });
    });
    return lines;
}

function vje_recalc() {
    const lines = vje_collectLines();
    const totalDebit  = lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
    $vr('vje-total-debit').textContent  = vr_peso(totalDebit);
    $vr('vje-total-credit').textContent = vr_peso(totalCredit);
    // Every group's own Debit/Credit total is always its own lines' sum — recomputed here too (not
    // just on account change) so typing a debit/credit updates it live.
    $vr('vje-lines-tbody').querySelectorAll('tr.vje-grp-hdr').forEach(hdr => vje_updateGroupHeaderTotals(hdr.dataset.groupId));
    const balanced = lines.length >= 2 && totalDebit > 0 && Math.abs(totalDebit - totalCredit) < 0.005;
    const msg = $vr('vje-balance-msg');
    msg.textContent = balanced ? 'Balanced ✓' : 'Not balanced — debits must equal credits';
    msg.style.color = balanced ? 'var(--afims-success, #16A34A)' : 'var(--afims-danger, #DC3545)';
    return balanced;
}

async function vje_save() {
    const lines = vje_collectLines();
    const atc_lines = vje_collectAtcLines();
    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${vr_id}/journal-entry`, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({ atc_lines, lines }),
        });
        const json = await res.json();
        if (!json.success) {
            alert((json.errors?.length ? json.errors.join('\n') : null) || json.message || 'Failed to save Journal Entry.');
            return;
        }
        await vr_open(vr_id); // reload — "Forward to Treasury" appears once the JEV is balanced
    } catch (e) {
        alert('Could not save: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function vr_changeStatus(status) {
    let notes = null;
    if (status === 'REJECTED') {
        notes = prompt('Reason for rejecting this disbursement voucher:');
        if (notes === null) return;
        if (!notes.trim()) { alert('A reason is required when rejecting.'); return; }
    } else if (!confirm(`Change this disbursement voucher to ${status}?`)) {
        return;
    }

    window.PagePreloader?.show();
    try {
        const res  = await vr_authFetch(`${VR_API}/voucher/${vr_id}/status`, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({ status, notes }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to change status.');
        window.vf_onSaved?.(null, json.message); // reuses the list page's toast + reload hook
        await vr_open(vr_id);
    } catch (e) {
        alert(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function vr_statusClass(s) {
    const map = {
        DRAFT: 'nha-bdg-gray', FOR_FMD_REVIEW: 'nha-bdg-amber', FOR_ACCOUNTING: 'nha-bdg-blue',
        FOR_TREASURY: 'nha-bdg-teal', RELEASED: 'nha-bdg-green', REJECTED: 'nha-bdg-red', CANCELLED: 'nha-bdg-orange',
    };
    return map[s] || 'nha-bdg-gray';
}
function vr_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
/* Shows the account title alongside the code — same Chart of Accounts lookup the searchable-select
   pickers (nhaSearchableSelect, form.php / vje_addLine) show as their option labels — and flags it
   if this DV's own account isn't posting_allowed (shouldn't happen for a voucher saved since that
   check was added, but can for older/legacy data). */
function vr_accountCodeDisplay(dv) {
    if (!dv.account_code) return '—';
    const code = vr_esc(dv.account_code);
    if (dv.account_posting_allowed === false) {
        return `${code} <span class="nha-bdg nha-bdg-red" title="Not postable or no longer exists in the Chart of Accounts">⚠ invalid</span>`;
    }
    return dv.account_title ? `${code} — ${vr_esc(dv.account_title)}` : code;
}
function vr_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function vr_fmtDateTime(s) {
    if (!s) return '— (not yet)';
    const d = new Date(String(s).replace(' ', 'T'));
    return isNaN(d) ? '—' : d.toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function vr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
</script>
<?php
// Print / Print JEV → the shared print preview (prp_open + the prp_dvPayload / prp_jevFromDv helpers).
include_once __DIR__ . '/../readone/print-preview-readone.php';
?>
