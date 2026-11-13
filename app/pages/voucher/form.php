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

/* GL-grouped Accounting Entries grid (Journal Entry Voucher layout) — same convention as
   readone.php's #vr-root rules. */
#vf-root .vje-grp-edit-table tr.vje-grp-hdr td { padding-top: 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); background: var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vje-grp-edit-table tr.vje-grp-hdr:first-child td { padding-top: 6px; }
#vf-root .vje-grp-edit-table tr.vje-grp-hdr input.vje-grp-acct { font-weight: 700; background: var(--afims-surface, #fff); }
#vf-root .vje-grp-edit-table tr.vje-grp-hdr .vje-grp-debit,
#vf-root .vje-grp-edit-table tr.vje-grp-hdr .vje-grp-credit { font-weight: 700; font-family: "IBM Plex Mono", monospace; text-align: right; }
#vf-root .vje-grp-edit-table tr.vje-grp-line td:first-child { padding-left: 22px; }
#vf-root .vje-grp-edit-table .vje-grp-add-btn { font-size: 11.5px; padding: 4px 10px; }

#vf-root .vf-ded-row { border: 1px solid var(--afims-border, #E4ECF2); border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; position: relative; }
#vf-root .vf-ded-remove { position: absolute; top: 8px; right: 10px; background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 4px 6px; border-radius: 5px; }
#vf-root .vf-ded-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
/* Table-cell remove buttons (ATC / Accounting Entries rows) — same look as .vf-ded-remove but
   WITHOUT position:absolute, which inside a <td> (no positioned ancestor of its own) escaped to
   the nearest positioned ancestor up the DOM tree — the modal itself — landing as a stray red ×
   floating near the modal's close button instead of inside its own row. */
#vf-root .vf-table-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 4px 6px; border-radius: 5px; }
#vf-root .vf-table-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#vf-root .vf-ded-row select { width: 100%; }

#vf-root .vf-errors { display: none; margin-bottom: 14px; padding: 12px 16px; background: var(--afims-danger-soft, #FAE1E3);
    border: 1px solid var(--afims-danger-soft, #FAE1E3); border-left: 4px solid var(--afims-danger, #DC3545); border-radius: 10px; }
#vf-root .vf-errors.visible { display: block; }
#vf-root .vf-errors ul { margin: 6px 0 0; padding-left: 18px; font-size: 13px; color: var(--afims-danger-text, #8F222D); line-height: 1.7; }

#vf-root .vf-rfa-dropdown { position: relative; display: none; margin-top: 4px; max-height: 260px; overflow-y: auto;
    border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; background: var(--afims-surface, #fff); box-shadow: 0 6px 16px rgba(0,0,0,.08); }
#vf-root .vf-rfa-dropdown.open { display: block; }
#vf-root .vf-rfa-opt { padding: 8px 12px; cursor: pointer; font-size: 12.5px; border-bottom: 1px solid var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-rfa-opt:hover { background: var(--afims-surface-subtle, #F4F7FA); }
#vf-root .vf-rfa-opt small { display: block; color: var(--afims-text-muted, #62657E); margin-top: 2px; }
#vf-root .vf-rfa-opt.vf-rfa-opt-empty { cursor: default; color: var(--afims-text-muted, #62657E); }
#vf-root .vf-rfa-chips { display: flex; flex-direction: column; gap: 8px; margin-top: 10px; }
#vf-root .vf-rfa-chip { border: 1px solid var(--afims-primary-soft, #E6EBF6); border-radius: 10px; padding: 10px 12px; background: var(--afims-primary-soft, #E6EBF6); }
#vf-root .vf-rfa-chip-hdr { display: flex; justify-content: space-between; align-items: center; gap: 10px; }
#vf-root .vf-rfa-chip-hdr strong { font-size: 13px; color: var(--afims-primary-text, #0035A9); }
#vf-root .vf-rfa-chip-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 2px 6px; border-radius: 5px; }
#vf-root .vf-rfa-chip-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }
#vf-root .vf-rfa-chip-dl { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px 16px; margin: 8px 0 0; }
#vf-root .vf-rfa-chip-dl dt { font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: var(--afims-text-muted, #62657E); margin: 0; }
#vf-root .vf-rfa-chip-dl dd { font-size: 12.5px; font-weight: 600; color: var(--afims-text, #1e293b); margin: 1px 0 0; }

#vf-root .vf-dz { border: 2px dashed var(--afims-border, #E4ECF2); border-radius: 10px; padding: 18px; text-align: center; cursor: pointer; }
#vf-root .vf-dz.drag-over { border-color: var(--afims-primary, #0035A9); background: var(--afims-primary-soft, #E6EBF6); }
#vf-root .vf-dz-hint { font-size: 11px; color: var(--afims-text-muted, #62657E); margin-top: 4px; }
#vf-root .vf-att-link-row { display: flex; gap: 8px; margin-top: 10px; align-items: center; }
#vf-root .vf-att-link-row input { flex: 1; font-size: 13px; padding: 8px 10px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 6px; box-sizing: border-box; }
#vf-root .vf-att-link-row input#vf-att-link-name { flex: 0 0 200px; }
#vf-root .vf-att-list { display: flex; flex-direction: column; gap: 8px; margin-top: 12px; }
#vf-root .vf-att-name a { color: var(--afims-primary-text, #0035A9); text-decoration: none; }
#vf-root .vf-att-name a:hover { text-decoration: underline; }
#vf-root .vf-att-row { display: flex; align-items: center; gap: 10px; padding: 8px 12px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px; }
#vf-root .vf-att-name { flex: 1; font-size: 13px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
#vf-root .vf-att-remove { background: none; border: none; cursor: pointer; color: var(--afims-danger, #DC3545); font-size: 16px; line-height: 1; padding: 2px 6px; border-radius: 5px; }
#vf-root .vf-att-remove:hover { background: var(--afims-danger-soft, #FAE1E3); }

@media (max-width: 768px) {
    #vf-root .vf-grid3, #vf-root .vf-grid2 { grid-template-columns: 1fr; }
    #vf-root .vf-span2, #vf-root .vf-span3 { grid-column: span 1; }
    #vf-root .vf-rfa-chip-dl { grid-template-columns: repeat(2, 1fr); }
    #vf-root .vf-att-link-row { flex-wrap: wrap; }
    #vf-root .vf-att-link-row input#vf-att-link-name { flex: 1 1 100%; }
}
</style>

<div id="vf-root" class="nha-root">
    <div id="vf-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-full" role="dialog" aria-modal="true" aria-labelledby="vf-modal-title">

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

                <!-- 1 — Vendor first: picking one auto-fills the claimant fields below. Vendor
                     wins over whatever the RFA (2) below would otherwise auto-fill — it's never
                     overwritten once set, see vf_onRfaSelectionChange()'s "never overwrites what
                     the user already set" guard. -->
                <div class="vf-card">
                    <p class="vf-card-title">Claimant &amp; Particulars</p>
                    <div class="vf-grid2">
                        <div class="nha-fg">
                            <label>Vendor (Supplier / Contractor) <span class="vf-hint" style="display:inline;">(optional — autofills claimant below)</span></label>
                            <select id="vf-vendor"><option value="">— None / type claimant manually —</option></select>
                        </div>
                        <div class="nha-fg">
                            <label>PO Number <span class="vf-hint" style="display:inline;">(optional)</span></label>
                            <input type="text" id="vf-po-number" placeholder="e.g. GSD-2025-09-103">
                        </div>
                    </div>
                    <div class="vf-vendor-card" id="vf-vendor-card">
                        <div><label>Vendor Name</label><span id="vf-vc-name">—</span></div>
                        <div><label>Type</label><span id="vf-vc-type">—</span></div>
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

                <!-- 2 — RFA (multi-select): picking one or more auto-fills the department/vendor
                     claimant fields (1, if not already set there) and the Budget Reference ledger
                     (4) further down. Several RFAs can be cited at once — each selected RFA's own
                     details stay visible as a chip below the picker. One RFA only ever backs one DV
                     at a time (fims.budget_allocation.dv_id — see
                     DisbursementVoucherService::linkRfas()), so an RFA already used elsewhere won't
                     appear here. -->
                <div class="vf-card">
                    <p class="vf-card-title">Request for Allotment (RFA)</p>
                    <div class="nha-fg">
                        <label>RFA <span class="nha-req">*</span> <span class="vf-hint" style="display:inline;">(search and select one or more approved RFAs)</span></label>
                        <input type="text" id="vf-rfa-search" placeholder="Search RFA number or reference no…" autocomplete="off">
                        <div id="vf-rfa-dropdown" class="vf-rfa-dropdown"></div>
                        <span class="vf-hint" id="vf-rfa-empty-hint" style="display:none;color:var(--afims-warning-text,#8A6400);">No RFAs are available yet — an RFA (Request for Allotment) must exist before a Disbursement Voucher can be created. Contact Budget/FMD to set one up.</span>
                        <span class="vf-err" id="err-rfa">At least one RFA is required.</span>
                    </div>
                    <div id="vf-rfa-chips" class="vf-rfa-chips"></div>
                </div>

                <!-- 3 — Document identity (DV Date drives the DV Number preview; Fiscal Year is
                     derived from it and Expense Class from the RFA(s) above — neither is asked for
                     directly anymore, see vf_derivedFiscalYear()/vf_derivedExpenseClass()) -->
                <div class="vf-card">
                    <p class="vf-card-title">Document Identity</p>
                    <div class="nha-fg" style="max-width:260px;">
                        <label>DV Date <span class="nha-req">*</span></label>
                        <input type="date" id="vf-dv-date">
                        <span class="vf-err" id="err-dv-date">DV Date is required.</span>
                    </div>
                </div>

                <!-- 4 — Budget Reference — the paper form's Accounting Entries box (Box B); its
                     Dept./Project/Fund Source/Budget/Obligation are filled in by the RFA (2) above -->
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

                <!-- 4b — Attachments (optional) — Supporting Documents for this voucher, same
                     fims.file_attachments / multiple_node_id convention the CBA/RFA form uses
                     (see FileUploadServices). Files upload to storage as soon as they're dropped;
                     the batch itself (multiple_node_id) is only registered when the voucher is
                     saved (vf_save -> vf_persistAttachments). -->
                <div class="vf-card">
                    <p class="vf-card-title">Attachments</p>
                    <div class="vf-dz" id="vf-dz" onclick="document.getElementById('vf-att-input').click()"
                         ondragover="event.preventDefault(); this.classList.add('drag-over');"
                         ondragleave="this.classList.remove('drag-over');"
                         ondrop="vf_onAttDrop(event)">
                        <strong>Click to upload</strong> or drag and drop supporting documents here
                        <div class="vf-dz-hint">Max 25MB each &middot; PDF, DOC, XLS, JPG, PNG</div>
                    </div>
                    <input type="file" id="vf-att-input" multiple hidden
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" onchange="vf_onAttChange(event)">
                    <div class="vf-att-link-row">
                        <input type="text" id="vf-att-link-name" placeholder="Document name">
                        <input type="text" id="vf-att-link-url" placeholder="e.g. https://drive.google.com/…">
                        <button type="button" class="nha-btn nha-btn-ghost" onclick="vf_addLinkAttachment()">+ Add Link</button>
                    </div>
                    <div class="vf-att-list" id="vf-att-list"></div>
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

                <!-- 7 — Accounting Entries (optional) — same GL-grouped Journal Entry Voucher
                     layout as readone.php's Accounting Entries card; Accounting reviews/edits
                     before their own save. -->
                <div class="vf-card" style="margin-bottom:0;">
                    <div class="vje-section-hdr">
                        <p class="vf-card-title" style="margin:0;">Accounting Entries <span class="vf-hint" style="display:inline;font-weight:400;text-transform:none;">(optional)</span></p>
                    </div>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;">
                        <span class="vje-amt-badge">Required if filled in: DR = CR (balanced)</span>
                    </div>
                    <p class="vf-hint" style="margin:0 0 8px;">Grouped by GL account (UACS code) — pick the account once per group, then add one SL Code line per amount under it.</p>
                    <div style="overflow-x:auto;">
                        <table class="vf-ledger vje-grp-edit-table" style="min-width:760px;">
                            <thead><tr>
                                <th style="text-align:left;">Account Code / Description</th>
                                <th style="text-align:left;">SL Code</th>
                                <th style="text-align:right;width:110px;">Debit (₱)</th>
                                <th style="text-align:right;width:110px;">Credit (₱)</th>
                                <th style="width:26px;">#</th>
                                <th style="width:30px;"></th>
                            </tr></thead>
                            <tbody id="vf-jev-tbody"></tbody>
                            <tfoot><tr class="vf-l-total">
                                <td colspan="2" style="text-align:right;">Total</td>
                                <td class="vf-l-val" id="vf-jev-total-debit">₱ 0.00</td>
                                <td class="vf-l-val" id="vf-jev-total-credit">₱ 0.00</td>
                                <td></td>
                                <td></td>
                            </tr></tfoot>
                        </table>
                    </div>
                    <button type="button" class="nha-btn nha-btn-ghost" style="margin-top:8px;" onclick="vf_addNewJevGroup()">+ Add GL Account</button>
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
let vf_paymentsPrior = 0;    // sum of net_amount other active vouchers already drew against the selected RFA(s)
let vf_selectedRfaIds = [];  // allocation_id values picked in the RFA multi-select, in selection order
let vf_attachments = [];     // staged Supporting Documents: { document_name, link, size, uploading }
let vf_attDirty = false;     // true once the attachments list has changed since open — avoids re-registering an unchanged batch on every save
let vf_existingMultipleNodeId = null; // this voucher's current multiple_node_id (edit mode), reused as-is when attachments weren't touched
let vf_dedTypes = [];         // deduction type options (fims.deduction_types)
let vf_dedSeq   = 0;          // row-id counter for the Deductions grid
let vf_coaOptions = [];       // postable chart-of-accounts rows — reused by the Account Code field and the Accounting Entries grid
let VF_ATC = [];              // active ATC codes — see ATC card (6)
let VF_RC  = [];              // responsibility centers, for ATC "Chargeability"
let VF_SL  = [];              // SL masters, for the Accounting Entries grid's SL Code picker
let vf_atcSeq = 0;            // row-id counter for the ATC grid
let vf_jevSeq = 0;            // row-id counter for the Accounting Entries grid
let vf_jevGroupSeq = 0;       // GL account-group id counter for the Accounting Entries grid (see vf_addJevGroup())

function vf_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

// The API JWT (window.ew.API_JWT_TOKEN, read by vf_authFetch) expires 10 minutes after login/page
// load with no silent refresh — leaving this modal open past that turns every call below into a
// 401. Give that one specific, actionable message instead of a raw "HTTP 401".
const VF_SESSION_EXPIRED_MSG = 'Your session has expired. Please refresh the page and log in again.';
function vf_isSessionExpiredMessage(msg) { return msg === VF_SESSION_EXPIRED_MSG; }

async function vf_getJson(path) {
    const res = await vf_authFetch(VF_API + path);
    if (res.status === 401) throw new Error(VF_SESSION_EXPIRED_MSG);
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
        // allSettled, not all: one reference-data fetch failing (e.g. deduction types) used to
        // short-circuit the whole load — hiding whatever else also failed behind a single error
        // message, and skipping vf_loadExisting()/vf_recalc() below entirely even though most of
        // the form (including the RFA list) may have loaded fine. Collect every failure instead so
        // the form still opens with whatever did load, and the user sees the full picture.
        const results = await Promise.allSettled([
            vf_refReady, vf_dedTypesReady, vf_coaReady, vf_atcReady, vf_rcReady, vf_slReady,
            vf_loadVendors(), vf_loadRfaOptions(),
        ]);
        const loadErrors = results.filter(r => r.status === 'rejected').map(r => r.reason?.message || String(r.reason));

        if (vf_id) {
            try {
                await vf_loadExisting();
            } catch (e) {
                loadErrors.push(e.message);
            }
        } else {
            vf_refreshDvNumber();
        }
        // Defensive: if the RFA search box picked up an autofocus while vf_loadExisting() was still
        // in flight, its dropdown may have rendered before vf_selectedRfaIds was populated — close it
        // now so a stale, unfiltered list isn't left showing an RFA the chips below already cover.
        $vf('vf-rfa-dropdown').classList.remove('open');
        vf_recalc();
        // One expired token fails every one of the reference-data calls above at once — show the
        // single actionable message instead of eight copies of the same thing.
        if (loadErrors.some(vf_isSessionExpiredMessage)) {
            vf_showErrors([VF_SESSION_EXPIRED_MSG]);
        } else if (loadErrors.length) {
            vf_showErrors(loadErrors);
        }
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
    ['dv-date', 'rfa', 'claimant', 'particulars', 'gross'].forEach(vf_clearFieldError);
    $vf('vf-dv-date').value       = new Date().toISOString().slice(0, 10);
    $vf('vf-rfa-search').value    = '';
    $vf('vf-rfa-dropdown').classList.remove('open');
    $vf('vf-rfa-empty-hint').style.display = 'none';
    vf_selectedRfaIds = [];
    vf_renderRfaChips();

    vf_attachments  = [];
    vf_attDirty     = false;
    vf_existingMultipleNodeId = null;
    $vf('vf-att-input').value = '';
    $vf('vf-att-link-name').value = '';
    $vf('vf-att-link-url').value  = '';
    vf_renderAttachments();

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
    vf_jevGroupSeq = 0;
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
    $vf('vf-rfa-search').addEventListener('input', vf_renderRfaDropdown);
    $vf('vf-rfa-search').addEventListener('focus', vf_renderRfaDropdown);
    document.addEventListener('click', e => {
        if (!$vf('vf-rfa-search').contains(e.target) && !$vf('vf-rfa-dropdown').contains(e.target)) {
            $vf('vf-rfa-dropdown').classList.remove('open');
        }
    });
    $vf('vf-vendor').addEventListener('change', vf_onVendorChange);
    $vf('vf-gross').addEventListener('input', () => vf_recalc());
    $vf('vf-obligation').addEventListener('input', () => vf_recalc());
}

/* ── Reference data ────────────────────────────────────────── */
let vf_fiscalYears = []; // fims.fiscal_year rows — Fiscal Year isn't asked for directly anymore
                          // (see vf_derivedFiscalYear()), but a DV Date's year still has to match
                          // one of these for the backend's own fiscal_yr existence check to pass.

async function vf_loadFiscalYears() {
    try {
        const json = await vf_getJson('/voucher/fiscal-years');
        vf_fiscalYears = (json.data ?? []).map(r => Number(r.fiscal_yr)).filter(n => !isNaN(n));
    } catch (e) {
        vf_fiscalYears = [];
        vf_refReady = null;
        throw new Error('Failed to load fiscal years: ' + e.message);
    }
}

/** DV Date's own year if it's a configured fiscal year, otherwise the closest configured one. */
function vf_derivedFiscalYear() {
    const wanted = new Date($vf('vf-dv-date').value || Date.now()).getFullYear();
    if (!vf_fiscalYears.length) return wanted;
    if (vf_fiscalYears.includes(wanted)) return wanted;
    return vf_fiscalYears.reduce((best, y) => Math.abs(y - wanted) < Math.abs(best - wanted) ? y : best, vf_fiscalYears[0]);
}

/** Best-effort Expense Class from the selected RFA(s)' budget category — see vf_onRfaSelectionChange's mapping. */
function vf_derivedExpenseClass() {
    const map = { 'Infrastructure': 'CAPITAL_OUTLAY', 'Goods and Services': 'MOOE', 'Consultancy': 'MOOE' };
    for (const id of vf_selectedRfaIds) {
        const mapped = map[vf_rfas[id]?.category_name];
        if (mapped) return mapped;
    }
    return '';
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
        // postable_only (not the region-scoped /chart-of-accounts/all) — posting a voucher isn't
        // restricted to the preparer's own region, so every active+postable GL account should be
        // selectable here regardless of which region it belongs to.
        const json = await vf_getJson('/chart_of_accounts/all?postable_only=1');
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
            `<option value="${vf_esc(v.vendor_id)}">${vf_esc(v.vendor_code)} — ${vf_esc(v.vendor_name)}${v.vendor_type ? ' (' + vf_esc(v.vendor_type) + ')' : ''}</option>`).join('');
    } catch (e) {
        sel.innerHTML = '<option value="">Could not load vendors</option>';
        throw new Error('Failed to load vendors: ' + e.message);
    }
}

async function vf_loadRfaOptions() {
    try {
        // exclude_dv_id: lets THIS voucher's own already-linked RFAs keep showing (as "already
        // yours"), and leaves its own prior draw out of paid_amount so it doesn't double-count
        // against itself. One RFA only backs one DV at a time server-side (budget_allocation.dv_id)
        // — an RFA linked to a DIFFERENT, still-active DV simply isn't in this list at all.
        const qs   = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const json = await vf_getJson(`/voucher/rfa/all${qs}`);
        vf_rfas = {};
        (json.data ?? []).forEach(r => { vf_rfas[r.allocation_id] = r; });
        $vf('vf-rfa-empty-hint').style.display = (json.data ?? []).length ? 'none' : 'block';
        vf_renderRfaDropdown();
    } catch (e) {
        vf_rfas = {};
        $vf('vf-rfa-empty-hint').style.display = 'none';
        throw new Error('Failed to load RFAs: ' + e.message);
    }
}

/* ── RFA multi-select ─────────────────────────────────────────
   vf_rfas (allocation_id -> row, from /voucher/rfa/all) is the full option pool; vf_selectedRfaIds
   is which of those the user has picked, in order. Each selection renders as a removable chip
   showing that RFA's own details (department, fiscal year, vendor, remaining balance) — "show the
   details of the RFA" — and Budget Reference (4) further down sums Budget/Payments/Balance across
   every selected RFA, see vf_recalc(). The actual link (fims.budget_allocation.dv_id) is only
   written server-side on save — see DisbursementVoucherService::linkRfas(). ───────────────────── */
function vf_renderRfaDropdown() {
    const box    = $vf('vf-rfa-dropdown');
    const search = $vf('vf-rfa-search').value.trim().toLowerCase();
    const options = Object.values(vf_rfas)
        .filter(r => !vf_selectedRfaIds.includes(String(r.allocation_id)))
        .filter(r => !search || r.allocation_number?.toLowerCase().includes(search) || r.request_referrence_no?.toLowerCase().includes(search))
        .slice(0, 30);

    box.innerHTML = options.length
        ? options.map(r => `
            <div class="vf-rfa-opt" onclick="vf_selectRfa('${r.allocation_id}')">
                <strong>${vf_esc(r.allocation_number)}</strong>
                <small>${vf_esc(r.department_name || 'Unassigned Dept.')} &middot; FY ${vf_esc(r.fiscal_year)} &middot; ${vf_peso(r.remaining_balance)} left of ${vf_peso(r.budget_amount)}</small>
            </div>`).join('')
        : '<div class="vf-rfa-opt vf-rfa-opt-empty">No matching approved RFAs.</div>';
    box.classList.add('open');
}

function vf_selectRfa(id) {
    id = String(id);
    if (!vf_selectedRfaIds.includes(id)) vf_selectedRfaIds.push(id);
    $vf('vf-rfa-search').value = '';
    $vf('vf-rfa-dropdown').classList.remove('open');
    vf_clearFieldError('rfa');
    vf_onRfaSelectionChange();
}

function vf_removeRfa(id) {
    vf_selectedRfaIds = vf_selectedRfaIds.filter(x => x !== String(id));
    vf_onRfaSelectionChange();
}

function vf_renderRfaChips() {
    const box = $vf('vf-rfa-chips');
    if (!vf_selectedRfaIds.length) { box.innerHTML = ''; return; }
    box.innerHTML = vf_selectedRfaIds.map(id => {
        const r = vf_rfas[id];
        if (!r) return '';
        return `<div class="vf-rfa-chip">
            <div class="vf-rfa-chip-hdr">
                <strong>${vf_esc(r.allocation_number)}</strong>
                <button type="button" class="vf-rfa-chip-remove" title="Remove" onclick="vf_removeRfa('${id}')">&times;</button>
            </div>
            <dl class="vf-rfa-chip-dl">
                <div><dt>Department</dt><dd>${vf_esc(r.department_name) || 'Unassigned'}</dd></div>
                <div><dt>Fiscal Year</dt><dd>${vf_esc(r.fiscal_year)}</dd></div>
                <div><dt>Vendor</dt><dd>${vf_esc(r.vendor_name) || '—'}</dd></div>
                <div><dt>Remaining</dt><dd>${vf_peso(r.remaining_balance)} of ${vf_peso(r.budget_amount)}</dd></div>
            </dl>
        </div>`;
    }).join('');
}

/** Re-fetches each selected RFA's live detail, re-autofills dept/fund/vendor/expense class, and
    recomputes the Budget Reference ledger. */
async function vf_onRfaSelectionChange() {
    vf_renderRfaChips();
    if (!vf_selectedRfaIds.length) {
        vf_paymentsPrior = 0;
        $vf('vf-payments-hint').textContent = '';
        vf_recalc();
        return;
    }

    window.PagePreloader?.show();
    try {
        const qs = vf_id ? `?exclude_dv_id=${vf_id}` : '';
        const details = await Promise.all(vf_selectedRfaIds.map(id =>
            vf_getJson(`/voucher/rfa/read/${id}${qs}`).then(j => j.data).catch(() => vf_rfas[id])));
        details.forEach(rfa => { if (rfa) vf_rfas[rfa.allocation_id] = rfa; });
        vf_renderRfaChips();

        // Dept/Project joins every selected RFA's own department — several RFAs commonly share one
        // requesting office, but this still reads correctly when they don't.
        const depts = [...new Set(details.filter(Boolean).map(r => r.department_name).filter(Boolean))];
        $vf('vf-dept-project').value = depts.join('; ');

        $vf('vf-budget').value     = details.filter(Boolean).reduce((s, r) => s + (Number(r.budget_amount) || 0), 0).toFixed(2);
        $vf('vf-obligation').value = $vf('vf-budget').value;
        vf_paymentsPrior = details.filter(Boolean).reduce((s, r) => s + (Number(r.paid_amount) || 0), 0);
        $vf('vf-payments-hint').textContent = vf_paymentsPrior > 0 ? ' (prior draws on these RFAs)' : '';

        // PO number / vendor: first selected RFA that carries one wins — best-effort autofill,
        // never overwrites what the user already set.
        const withPo     = details.find(r => r?.po_number);
        const withVendor = details.find(r => r?.vendor_id);
        if (withPo && !$vf('vf-po-number').value) $vf('vf-po-number').value = withPo.po_number;
        if (withVendor) {
            if (!vf_vendors[withVendor.vendor_id]) {
                vf_vendors[withVendor.vendor_id] = { vendor_id: withVendor.vendor_id, vendor_name: withVendor.vendor_name, address: withVendor.vendor_address, tin: withVendor.vendor_tin };
                $vf('vf-vendor').insertAdjacentHTML('beforeend',
                    `<option value="${vf_esc(withVendor.vendor_id)}">${vf_esc(withVendor.vendor_name)}</option>`);
            }
            if (!$vf('vf-vendor').value) {
                $vf('vf-vendor').value = withVendor.vendor_id;
                vf_onVendorChange();
            }
        }
    } catch (e) {
        vf_showErrors([vf_isSessionExpiredMessage(e.message) ? e.message : 'Could not load RFA details: ' + e.message]);
    } finally {
        window.PagePreloader?.hide();
        vf_recalc();
    }
}

function vf_onVendorChange() {
    const v = vf_vendors[$vf('vf-vendor').value];
    const card = $vf('vf-vendor-card');
    if (!v) { card.classList.remove('visible'); return; }
    $vf('vf-vc-name').textContent    = v.vendor_name || '—';
    $vf('vf-vc-type').textContent    = v.vendor_type || '—';
    $vf('vf-vc-address').textContent = v.address || '—';
    $vf('vf-vc-tin').textContent     = v.tin || '—';
    card.classList.add('visible');
    // Vendor fields win over whatever was typed manually, so the claimant always matches the vendor on file.
    $vf('vf-claimant').value         = v.vendor_name || '';
    $vf('vf-claimant-address').value = v.address || '';
    $vf('vf-claimant-tin').value     = v.tin || '';
    vf_clearFieldError('claimant');
}

async function vf_refreshDvNumber() {
    const d = $vf('vf-dv-date').value;
    if (!d) return;
    try {
        const json = await vf_getJson('/voucher/dv-number?dv_date=' + encodeURIComponent(d));
        $vf('vf-dv-number').textContent = json.data.dv_no;
    } catch (e) { /* the server assigns a number on save if this stays "—" */ }
}

/* ── Attachments (optional — Supporting Documents, same fims.file_attachments /
   multiple_node_id convention as the CBA/RFA form; see FileUploadServices) ──
   Each file uploads to storage (POST /upload/file) as soon as it's picked — vf_attachments just
   tracks the resulting {document_name, link} entries. The batch itself (multiple_node_id) is only
   registered — via POST /upload/multi-upload — when the voucher is actually saved, see
   vf_persistAttachments(). ─────────────────────────────────────────────────────────────────────── */
const VF_ATT_MAX_BYTES = 26214400; // 25MB — mirrors FileUploadServices' own limit

function vf_onAttDrop(e) {
    e.preventDefault();
    $vf('vf-dz').classList.remove('drag-over');
    if (e.dataTransfer.files?.length) vf_stageAttFiles(e.dataTransfer.files);
}
function vf_onAttChange(e) {
    if (e.target.files?.length) vf_stageAttFiles(e.target.files);
    e.target.value = '';
}

async function vf_stageAttFiles(fileList) {
    for (const file of Array.from(fileList)) {
        if (file.size > VF_ATT_MAX_BYTES) {
            vl_toastOrAlert(`"${file.name}" is over the 25MB limit and was not added.`);
            continue;
        }
        const row = { document_name: file.name.replace(/\.[^./]+$/, ''), link: null, size: file.size, uploading: true };
        vf_attachments.push(row);
        vf_renderAttachments();

        const form = new FormData();
        form.append('file', file);
        try {
            const res  = await vf_authFetch(`${VF_API}/upload/file`, { method: 'POST', body: form });
            const json = await res.json();
            if (!json.success) throw new Error(json.message || 'Upload failed.');
            row.link = json.link;
            row.uploading = false;
            vf_attDirty = true;
        } catch (e) {
            vf_attachments = vf_attachments.filter(r => r !== row);
            vl_toastOrAlert(`Failed to upload "${file.name}": ${e.message}`);
        }
        vf_renderAttachments();
    }
}

function vf_removeAttachment(idx) {
    vf_attachments.splice(idx, 1);
    vf_attDirty = true;
    vf_renderAttachments();
}

/** A typed reference (Google Drive, SharePoint, etc.) instead of an uploaded file — same
    fims.ip_filemeta.link convention FileUploadServices already accepts either way; nothing to
    upload, so this just stages the entry straight into vf_attachments like a finished upload. */
function vf_addLinkAttachment() {
    const nameEl = $vf('vf-att-link-name');
    const urlEl  = $vf('vf-att-link-url');
    const name = nameEl.value.trim();
    const url  = urlEl.value.trim();
    if (!name || !url) { vl_toastOrAlert('Enter both a document name and a link.'); return; }
    if (!/^https?:\/\//i.test(url)) { vl_toastOrAlert('Link must start with http:// or https://'); return; }
    vf_attachments.push({ document_name: name, link: url, size: null });
    vf_attDirty = true;
    nameEl.value = '';
    urlEl.value  = '';
    vf_renderAttachments();
}

function vf_renderAttachments() {
    $vf('vf-att-list').innerHTML = vf_attachments.map((a, idx) => {
        const isLink = /^https?:\/\//i.test(a.link || '');
        const name   = isLink
            ? `<a href="${vf_esc(a.link)}" target="_blank" rel="noopener noreferrer">${vf_esc(a.document_name)}</a>`
            : vf_esc(a.document_name);
        return `<div class="vf-att-row">
            <span class="vf-att-name" title="${vf_esc(a.document_name)}">${a.uploading ? '⏳ ' : ''}${name}${a.size ? ' <small>(' + vf_fmtSize(a.size) + ')</small>' : ''}</span>
            <button type="button" class="vf-att-remove" title="Remove" onclick="vf_removeAttachment(${idx})">&times;</button>
        </div>`;
    }).join('');
}

function vf_fmtSize(bytes) {
    return bytes >= 1024 * 1024 ? (bytes / (1024 * 1024)).toFixed(1) + ' MB' : Math.ceil(bytes / 1024) + ' KB';
}

/** Small helper so an upload failure is visible even before the list page's toast container exists. */
function vl_toastOrAlert(msg) {
    if (typeof window.vl_toast === 'function') window.vl_toast(msg, 'error');
    else alert(msg);
}

/**
 * Registers the staged files as one Supporting Documents batch (POST /upload/multi-upload) and
 * returns the multiple_node_id to save on the voucher — null if there's nothing to attach, or
 * undefined if attachments haven't changed since this voucher was opened (vf_attDirty), in which
 * case the caller reuses whatever multiple_node_id the voucher already has.
 */
async function vf_persistAttachments() {
    if (!vf_attDirty) return undefined;
    if (!vf_attachments.length) return null;

    const file_data = vf_attachments.filter(a => a.link).map(a => ({ document_name: a.document_name, link: a.link }));
    if (!file_data.length) return null;

    const res  = await vf_authFetch(`${VF_API}/upload/multi-upload`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ file_data }),
    });
    if (res.status === 401) throw new Error(VF_SESSION_EXPIRED_MSG);
    const json = await res.json();
    if (!json.success) throw new Error(json.message || 'Failed to save attachments.');
    return json.multiple_node_id;
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
        <td><input type="text" class="vf-atc-code" placeholder="Search ATC code…"></td>
        <td><input type="number" class="vf-atc-rate" min="0" max="100" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="vf-atc-base" min="0" step="0.01" placeholder="0.00"></td>
        <td class="vf-l-val vf-atc-wh">₱ 0.00</td>
        <td><select class="vf-atc-rc">
            <option value="">— None —</option>
            ${VF_RC.map(r => `<option value="${vf_esc(r.rc_id)}">${vf_esc(r.rc_id)} — ${vf_esc(r.rc_name)}</option>`).join('')}
        </select></td>
        <td><button type="button" class="vf-table-remove" title="Remove" onclick="vf_removeAtcLine(${idx})">&times;</button></td>`;
    tr.querySelector('.vf-atc-code').value = a?.atc_code ?? '';
    tr.querySelector('.vf-atc-rate').value = a?.rate ?? '';
    tr.querySelector('.vf-atc-base').value = a ? (Number(a.tax_base) || 0).toFixed(2) : '';
    tr.querySelector('.vf-atc-rc').value   = a?.chargeability ?? '';
    tr.querySelector('.vf-atc-code').addEventListener('change', () => vf_onAtcCodeSelect(idx));
    tr.querySelectorAll('.vf-atc-rate, .vf-atc-base').forEach(el => {
        el.addEventListener('input', () => vf_recalcAtcLine(idx));
    });
    $vf('vf-atc-tbody').appendChild(tr);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(tr.querySelector('.vf-atc-code'), VF_ATC.map(c => ({ value: c.atc_code, label: c.description })));
    vf_recalcAtcLine(idx);
}

function vf_removeAtcLine(idx) {
    $vf(`vf-atc-row-${idx}`)?.remove();
    vf_recalcAtc();
}

/**
 * Picking an ATC code prefills the rest of its row — the Rate(%) always follows the code's own
 * BIR default (fims.atc_codes.tax_rate), overwriting whatever was there before, since the rate is
 * intrinsic to the code, not a free choice; Tax Base and Chargeability are "never overwrite what's
 * already there" fills instead — Tax Base defaults to this DV's net-of-deductions amount, and
 * Chargeability defaults to the first selected RFA's own Responsibility Center
 * (fims.budget_allocation.requesting_rc_id), since that's who the withholding is normally charged
 * against.
 */
function vf_onAtcCodeSelect(idx) {
    const tr = $vf(`vf-atc-row-${idx}`);
    if (!tr) return;
    const code    = tr.querySelector('.vf-atc-code').value;
    const atcDef  = VF_ATC.find(c => c.atc_code === code);
    const rateEl  = tr.querySelector('.vf-atc-rate');
    const baseEl  = tr.querySelector('.vf-atc-base');
    const rcEl    = tr.querySelector('.vf-atc-rc');

    if (atcDef && isFinite(Number(atcDef.tax_rate))) rateEl.value = Number(atcDef.tax_rate).toFixed(2);

    if (!baseEl.value) {
        const gross = parseFloat($vf('vf-gross').value) || 0;
        baseEl.value = Math.max(0, gross - vf_totalDeductions()).toFixed(2);
    }

    if (!rcEl.value) {
        const primaryRc = vf_rfas[vf_selectedRfaIds[0]]?.requesting_rc_id;
        if (primaryRc) rcEl.value = primaryRc;
    }

    vf_recalcAtcLine(idx);
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

/* ── Accounting Entries (optional — see card 7's doc comment) — same GL-grouped Journal Entry
   Voucher layout as readone.php's vje_ Accounting Entries grid (Resp Center | Accounts and
   Explanation | SL Code | SL Detail | Subsidiary Ledger Dr/Cr | GL Acct Code | General Ledger
   Dr/Cr | #), minus auto-sync from Deduction/ATC rows (that only kicks in once Accounting is
   preparing the real Journal Entry at FOR_ACCOUNTING — see readone.php's vje_syncAutoLines). ──── */

function vf_jevGroupHdr(gid) {
    return $vf('vf-jev-tbody').querySelector(`tr.vje-grp-hdr[data-group-id="${gid}"]`);
}
function vf_jevGroupAcct(gid) {
    return vf_jevGroupHdr(gid)?.querySelector('.vje-grp-acct').value.trim() || '';
}
function vf_jevGroupLines(gid) {
    return [...$vf('vf-jev-tbody').querySelectorAll(`tr.vje-grp-line[data-group-id="${gid}"]`)];
}

function vf_addJevGroup(acctCode = '') {
    const gid = ++vf_jevGroupSeq;
    const hdr = document.createElement('tr');
    hdr.className = 'vje-grp-hdr';
    hdr.dataset.groupId = gid;
    hdr.innerHTML = `
        <td colspan="2"><input type="text" class="vje-grp-acct" placeholder="Search GL account code (UACS)…"></td>
        <td class="vf-l-val vje-grp-debit"></td>
        <td class="vf-l-val vje-grp-credit"></td>
        <td style="text-align:right;"><button type="button" class="nha-btn nha-btn-ghost vje-grp-add-btn" title="Add an SL Code line under this account">+ Line</button></td>
        <td><button type="button" class="vf-table-remove" title="Remove this account and its lines">&times;</button></td>`;
    hdr.querySelector('.vje-grp-acct').value = acctCode;
    $vf('vf-jev-tbody').appendChild(hdr);
    // Bound after the row is attached to the DOM — nhaSearchableSelect reads the input's live
    // parentElement to anchor the dropdown, which is unreliable on a still-detached node.
    nhaSearchableSelect(hdr.querySelector('.vje-grp-acct'), vf_coaOptions.map(a => ({ value: a.account_code, label: a.account_title })));
    hdr.querySelector('.vje-grp-acct').addEventListener('change', () => vf_onJevGroupAcctChange(gid));
    hdr.querySelector('.vje-grp-add-btn').addEventListener('click', () => { vf_addJevGroupLine(gid); vf_renumberJev(); vf_recalcJev(); });
    hdr.querySelector('.vf-table-remove').addEventListener('click', () => vf_removeJevGroup(gid));
    vf_updateJevGroupTotals(gid);
    return gid;
}

function vf_addNewJevGroup() {
    const gid = vf_addJevGroup('');
    vf_addJevGroupLine(gid);
    vf_renumberJev();
}

function vf_removeJevGroup(gid) {
    vf_jevGroupLines(gid).forEach(tr => tr.remove());
    vf_jevGroupHdr(gid)?.remove();
    vf_renumberJev();
    vf_recalcJev();
}

/** The group's own Debit/Credit total — summed from this group's SL Code lines (never
    independently edited). */
function vf_updateJevGroupTotals(gid) {
    const hdr = vf_jevGroupHdr(gid);
    if (!hdr) return;
    const lines = vf_jevGroupLines(gid).map(tr => ({
        debit: parseFloat(tr.querySelector('.vje-debit').value) || 0,
        credit: parseFloat(tr.querySelector('.vje-credit').value) || 0,
    }));
    hdr.querySelector('.vje-grp-debit').textContent  = vf_peso(lines.reduce((s, l) => s + l.debit, 0));
    hdr.querySelector('.vje-grp-credit').textContent = vf_peso(lines.reduce((s, l) => s + l.credit, 0));
}

/** GL Account Code changed on a group header — rebuild every line's SL Code combo (nested
    SL Code -> SL Detail options) under the newly-picked GL account, preserving a line's previous
    pick only if it still belongs to this account. */
function vf_onJevGroupAcctChange(gid) {
    const acctCode = vf_jevGroupAcct(gid);
    vf_jevGroupLines(gid).forEach(tr => {
        const lineId = tr.dataset.lineId;
        const [slCode, slDetailId] = vf_jevComboValue(tr);
        vf_populateSlCombo(lineId, acctCode, slCode, slDetailId);
    });
    vf_updateJevGroupTotals(gid);
    vf_recalcJev();
}

function vf_addJevGroupLine(gid, d = null) {
    const hdr = vf_jevGroupHdr(gid);
    if (!hdr) return null;
    const lineId = ++vf_jevSeq;
    const tr = document.createElement('tr');
    tr.id = `vf-jev-row-${lineId}`;
    tr.className = 'vje-grp-line';
    tr.dataset.groupId = gid;
    tr.dataset.lineId  = lineId;
    tr.innerHTML = `
        <td><input type="text" class="vje-line-desc" placeholder="Description"></td>
        <td><select class="vje-sl-combo"><option value="">— None —</option></select></td>
        <td><input type="number" class="vje-debit" min="0" step="0.01" placeholder="0.00"></td>
        <td><input type="number" class="vje-credit" min="0" step="0.01" placeholder="0.00"></td>
        <td class="vf-l-val vje-line-no"></td>
        <td><button type="button" class="vf-table-remove" title="Remove line" onclick="vf_removeJevGroupLine(${lineId})">&times;</button></td>`;
    tr.querySelector('.vje-line-desc').value = d?.description ?? '';
    tr.querySelector('.vje-debit').value  = Number(d?.debit)  > 0 ? Number(d.debit).toFixed(2)  : '';
    tr.querySelector('.vje-credit').value = Number(d?.credit) > 0 ? Number(d.credit).toFixed(2) : '';
    tr.querySelector('.vje-debit').addEventListener('input', vf_recalcJev);
    tr.querySelector('.vje-credit').addEventListener('input', vf_recalcJev);
    // Insert right after this group's last line (or right after its header if it has none yet) —
    // keeps every line visually/physically under its own GL header.
    const existing = vf_jevGroupLines(gid);
    (existing.length ? existing[existing.length - 1] : hdr).after(tr);
    vf_populateSlCombo(lineId, vf_jevGroupAcct(gid), d?.sl_code ?? null, d?.sl_detail_id ?? null);
    vf_recalcJev();
    return lineId;
}

function vf_removeJevGroupLine(lineId) {
    const tr = $vf(`vf-jev-row-${lineId}`);
    const gid = tr?.dataset.groupId;
    tr?.remove();
    if (gid && vf_jevGroupLines(gid).length === 0) vf_removeJevGroup(gid); // header with no lines left is dead weight
    vf_renumberJev();
    vf_recalcJev();
}

/** Running "01", "02", … across every line (not header) row, in on-screen order. */
function vf_renumberJev() {
    let n = 0;
    $vf('vf-jev-tbody').querySelectorAll('tr.vje-grp-line').forEach(tr => {
        n += 1;
        tr.querySelector('.vje-line-no').textContent = String(n).padStart(2, '0');
    });
}

/** Reads a line's current SL Code combo pick, split back into [sl_code, sl_detail_id] (both null
    if "— None —" is selected) — see vf_populateSlCombo()'s value encoding. */
function vf_jevComboValue(tr) {
    const raw = tr.querySelector('.vje-sl-combo')?.value || '';
    if (!raw) return [null, null];
    const [slCode, slDetailId] = raw.split('::');
    return [slCode || null, slDetailId || null];
}

/** SL Code is a sub-list of the chosen GL Account Code (fims.sl_masters.account_code); each SL Code
    in turn has its own SL Details. Rather than two separate pickers, this builds ONE combo select
    per line: an <optgroup> per matching SL Code (nested under the GL account just like the SL
    Detail is nested under its SL Code), with its SL Details as the selectable leaf options. An SL
    Code with no details is offered directly as a leaf option. Option values are
    "slCode::slDetailId" so vf_jevComboValue() can recover both in one read. Re-selects
    preSlCode/preDetailId if that combination still exists under $acctCode. */
async function vf_populateSlCombo(lineId, acctCode, preSlCode = null, preDetailId = null) {
    const tr  = $vf(`vf-jev-row-${lineId}`);
    const sel = tr?.querySelector('.vje-sl-combo');
    if (!sel) return;
    const matching = acctCode ? VF_SL.filter(s => s.account_code === acctCode) : [];
    sel.innerHTML = '<option value="">— None —</option>';
    if (!matching.length) return;
    const detailLists = await Promise.all(matching.map(s =>
        vf_getJson(`/voucher/sl-codes/${encodeURIComponent(s.sl_code)}/details`).then(j => j.data ?? []).catch(() => [])));
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

function vf_collectJevLines() {
    const lines = [];
    $vf('vf-jev-tbody').querySelectorAll('tr.vje-grp-hdr').forEach(hdr => {
        const gid          = hdr.dataset.groupId;
        const account_code = hdr.querySelector('.vje-grp-acct').value.trim();
        vf_jevGroupLines(gid).forEach(tr => {
            const debit       = parseFloat(tr.querySelector('.vje-debit').value)  || 0;
            const credit      = parseFloat(tr.querySelector('.vje-credit').value) || 0;
            const description = tr.querySelector('.vje-line-desc').value.trim();
            const [sl_code, sl_detail_id] = vf_jevComboValue(tr);
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

function vf_recalcJev() {
    const lines = vf_collectJevLines();
    const totalDebit  = lines.reduce((s, l) => s + l.debit, 0);
    const totalCredit = lines.reduce((s, l) => s + l.credit, 0);
    $vf('vf-jev-total-debit').textContent  = vf_peso(totalDebit);
    $vf('vf-jev-total-credit').textContent = vf_peso(totalCredit);
    $vf('vf-jev-tbody').querySelectorAll('tr.vje-grp-hdr').forEach(hdr => vf_updateJevGroupTotals(hdr.dataset.groupId));
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

    vf_selectedRfaIds = (dv.rfas ?? []).map(r => String(r.allocation_id));
    (dv.rfas ?? []).forEach(r => { vf_rfas[r.allocation_id] = r; });
    vf_renderRfaChips();

    vf_attachments = (dv.file_attachments ?? []).map(a => ({
        document_name: a.file_meta?.document_name || 'Supporting Document',
        link: a.file_meta?.link || null,
        size: null,
    }));
    vf_attDirty = false;
    vf_existingMultipleNodeId = dv.multiple_node_id || null;
    vf_renderAttachments();

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
    vf_jevGroupSeq = 0;
    {
        let lastAcct = null, gid = null;
        (dv.jev?.lines ?? []).forEach(l => {
            if (l.account_code !== lastAcct) {
                gid = vf_addJevGroup(l.account_code);
                lastAcct = l.account_code;
            }
            vf_addJevGroupLine(gid, l);
        });
    }
    vf_renumberJev();
    vf_recalcJev();

    $vf('vf-dept-project').value = dv.dept_project || '';
    $vf('vf-fund-source').value  = dv.fund_source || '';
    $vf('vf-account-code').value = dv.account_code || '';
    $vf('vf-budget').value       = dv.budget_amount || '';
    $vf('vf-obligation').value   = dv.obligation_amount || '';
    $vf('vf-remarks').value      = dv.remarks || '';

    // As last saved — same snapshot-not-live convention as budget_amount/obligation_amount above;
    // re-selecting an RFA chip (vf_onRfaSelectionChange) refreshes it live if needed.
    vf_paymentsPrior = parseFloat(dv.payments_prior_amount) || 0;
    $vf('vf-payments-hint').textContent = vf_paymentsPrior > 0 ? ' (prior draws on these RFAs)' : '';
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
    check(vf_selectedRfaIds.length > 0, 'rfa',      'At least one RFA is required.');
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
        fiscal_yr          : vf_derivedFiscalYear(),
        expense_class      : vf_derivedExpenseClass(),
        rfa_allocation_ids : vf_selectedRfaIds.map(Number),
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
        const persistedNodeId = await vf_persistAttachments();
        body.multiple_node_id = persistedNodeId === undefined ? vf_existingMultipleNodeId : persistedNodeId;

        const res  = await vf_authFetch(vf_id ? `${VF_API}/voucher/update/${vf_id}` : `${VF_API}/voucher/add`, {
            method  : vf_id ? 'PUT' : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify(body),
        });
        if (res.status === 401) { vf_showErrors([VF_SESSION_EXPIRED_MSG]); return; }
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
