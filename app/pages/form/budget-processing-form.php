<?php
/**
 * budget-processing-form.php
 *
 * "Budget Processing" modal for a budget request. Header: "Submission Ref. No. <ref>". Below it:
 *   - a meta line: Originating Unit (the requesting office), Date Submitted, and the number of Programs / Projects;
 *   - one CARD per Program: its Program and Sub-Program (each with its code as a chip; "[No sub-program]" when it
 *     has none), then one section per Project — "Project <code chip> <name>" with that Project's line items as a
 *     table whose last row is "Subtotal – Project <code>": the totals of THAT Project's line items, under the Amount
 *     and Suggested Amount columns. A Program that has no Projects yet shows "No projects yet.";
 *   - a note, the GRAND TOTAL bar (every Program), one REMARKS / COMPLIANCE NEEDED box, then Return / Endorse.
 * The line-item table, with the processing inputs per column:
 *       Type of Fund (fims.type_of_fund) · Component (fims.component_type) ·
 *       Account Code · Account Description (the account name, the line's particulars under it) ·
 *       Amount (what the office asked for) · Suggested Amount (the input).
 * Account Description and Amount come from the office's proposal and are shown in read-only boxes (the Account
 * Description follows the Account Code when that is changed); Account Code and Suggested Amount are editable.
 * The per-Project Subtotals and the Grand Total (all Projects) cover BOTH amount columns (Amount and Suggested);
 * the Suggested ones follow the inputs as they are edited.
 *
 * Account Code — the Budget Officer (the only role that gets this modal) can re-code a line to another chart-of-accounts
 *                entry: a searchable picker (bpr_pick*), over the postable,
 *                for-budget-proposal accounts of GET /chart_of_accounts/all (the list the Budget Request form uses).
 *                The box shows the account code, the list "code — title"; type to filter by either. It is never empty:
 *                clearing it (or typing something that is not picked) puts the previous account back. Saved on
 *                fims.budget_line_items.account_id (NOT NULL, FK to fims.chart_of_accounts) by Endorse, which sends
 *                account_id for every line; the server only writes the lines whose account actually changed.
 *
 * Supporting Documents — under each Project's table, the documents the originating office attached to THAT Project
 *                (fims.budget_req_project_attachment: an uploaded file or a pasted link, keyed by the Program and the project
 *                code). They are read-only here, one line each: the name (a link that opens the document), File / Link, the date,
 *                and "Open ↗". They are not part of
 *                the list data (opening a file needs a freshly signed URL), so when the modal opens each Project asks for its own
 *                (GET /budget_req_project_attachment/list?budget_req_program_id=…&project_code=…, all in parallel); the table
 *                is usable straight away and each section says "Loading…" until its answer comes. A section whose request failed
 *                says so and offers Retry. A link is only opened when it is a web address (http / https) — the server stores a
 *                pasted link as typed, so anything else is shown as text, never as a clickable link.
 *
 * (There used to be Program Sources and Program Uses columns — fims.budget_line_items.program_source_code / program_uses.
 * They were dropped, migration alter_table/22.)
 *
 * The picker: type to filter by code or title, pick from the list (arrow keys + Enter work too); typed text that is not
 * picked is dropped when the box loses focus. The list is one shared element on <body> (position:fixed), because the
 * table's overflow would clip a normal dropdown — the same approach as the Account Code picker of the Budget Request form.
 *
 * Amount is the line's proposed_amount, as the office requested it — read-only.
 * Suggested Amount is fims.budget_line_items.suggested_amount. It starts as a copy of the Amount (a previously
 * saved suggested amount above 0 is kept) and can be edited, or cleared (blank is saved as 0.00).
 *
 * Footer actions:
 *   Return  — needs REMARKS (the box above the footer; required): PUT /budget_req/return/{id} { remarks } sets
 *             the request's status to "Returned" (back to the originating office — the Budget Officer is the first
 *             step of the review chain) and saves the remarks on fims.budget_req.remarks; the line
 *             items are left untouched.
 *   Endorse — PUT /budget_req/endorse/{id}: Type of Fund and Component are REQUIRED on every line item of
 *             EVERY Project (Suggested Amount is optional); saves them, and the
 *             Account Code, to fims.budget_line_items and
 *             sets the request's status to "Endorsed" — now with the Budget Head (one transaction). If something required is missing the
 *             first such field is focused and its Project section is outlined in red. The remarks box is not used.
 * The review is a step-by-step chain (BudgetReqService::REVIEW_STEPS): For Review -> Budget Officer (this modal) -> Endorsed
 * -> Budget Head (budget-preview-readone.php) -> Approved. A request the Budget Head sent back ("Returned to Budget Officer")
 * comes to this modal again, with the Head's remarks shown above the details; the Officer can endorse it again or return it.
 * The list rows say whether this user may act (row.review.can_decide); the API enforces it again.
 * Status lives on the request header, so Return/Endorse apply to the whole request, and the modal shows every
 * Project of the request it is given.
 *
 * Trigger: bpr_openModal(requestContext, programs) — called from the list pages' "Budget Processing" row
 * action, where:
 *   requestContext = { budget_reference_number, fiscal_yr, status_name, status_color, rc_id, rc_code, rc_name, submitted_at, remarks, review }
 *                    (rc_* = the requesting office, submitted_at = when it was submitted, remarks = the reason of the last Return,
 *                    review = the row's review-chain info; all from the list row)
 *   programs       = ONE program or an ARRAY of them, each
 *                    { program_code, program_name, sub_program_code, sub_program_name, projects[] }
 *                    (each project has project_code, project_name, line_items[], straight from the list data; the program's
 *                    budget_req_program_id is what a Project's Supporting Documents are looked up by)
 * The line items are NOT re-fetched; only the three option lists are (once, then cached) and, per Project, its Supporting Documents.
 *
 * Included directly into budget-req-page.php / budget-prop-page.php (inside #bgr-root, so the .nha-root-scoped
 * modal styles apply). Depends on bgr_esc, bgr_escHtmlAttr, bgr_fmtMoney, bgr_fmtAmount, bgr_fmtAmountInitial,
 * bgr_unformatAmount and bgr_toast from form/budget-req-form.php, which must be included on the same page.
 * After Return/Endorse it calls window.bgr_afterSave() so the list reloads.
 */
$_bpr_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
/* ── Modal header: "Submission Ref. No. <ref>" ── */
.nha-root #bpr-overlay .bpr-ref {
    margin-left: 8px; font-family: var(--mono); font-weight: 600; letter-spacing: .02em;
    color: color-mix(in srgb, var(--afims-primary, #0035A9) 40%, #FFFFFF);
}

/* the Account Code picker: the code in the mono face, as the read-only box it replaces showed it */
.nha-root .bpr-tbl .bpr-pick-input[data-pick="account"] { font-family: var(--mono); font-size: 12px; }

/* ── Meta line: Originating Unit · Date Submitted · Programs · Projects ── */
.nha-root .bpr-meta { display: flex; flex-wrap: wrap; gap: 6px 32px; margin: 0 0 18px; font-size: 14px; color: var(--txt); }
.nha-root .bpr-meta-k { font-weight: 600; color: var(--sec); }
.nha-root .bpr-meta strong { font-weight: 700; }
.nha-root .bpr-muted { color: var(--subtle); }

/* ── One card per Program: head (Program / Sub-Program) · a section per Project (its table ends with that Project's Subtotal) ── */
.nha-root .bpr-prog { margin-bottom: 18px; border: 1px solid var(--bdr); border-radius: 12px; overflow: hidden; background: var(--afims-surface, #FFFFFF); }
.nha-root .bpr-prog-head { display: grid; gap: 9px; padding: 15px 18px; background: var(--surf); }
.nha-root .bpr-row { display: grid; grid-template-columns: 110px minmax(0, 1fr); gap: 12px; align-items: baseline; font-size: 14px; }
.nha-root .bpr-row-k { font-weight: 600; color: var(--sec); }
.nha-root .bpr-row-v { color: var(--txt); overflow-wrap: anywhere; }
.nha-root .bpr-row-v strong { font-weight: 700; }
.nha-root .bpr-chip {
    display: inline-block; margin-right: 1px; padding: 2px 7px; border-radius: 4px; vertical-align: 1px;
    font: 600 11px/1.4 var(--mono); color: var(--blue-txt, #1D4ED8);
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--navy-bdr, #E6EBF6);
}

/* a Project section: its title line + its line-items table (the red outline = something required is missing) */
.nha-root .bpr-block { padding: 15px 18px 17px; border-top: 1px solid var(--bdr); }
.nha-root .bpr-proj { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 10px; margin-bottom: 11px; font-size: 14px; color: var(--txt); }
.nha-root .bpr-proj-k { font-weight: 600; color: var(--sec); }
.nha-root .bpr-tbl-wrap { border: 1px solid var(--bdr); border-radius: 10px; overflow: hidden; }
.nha-root .bpr-block.has-error .bpr-tbl-wrap { border-color: var(--red, #DC3545); box-shadow: 0 0 0 3px rgba(220, 53, 69, .12); }
/* one fixed column grid (see BPR_COLGROUP) shared by every table: the columns line up from one Project to the next; a narrow screen scrolls */
.nha-root .bpr-tbl { table-layout: fixed; width: 100%; min-width: 880px; }
.nha-root .bpr-tbl td { padding: 10px 8px; vertical-align: middle; }
.nha-root .bpr-tbl thead th { padding-left: 8px; padding-right: 8px; }
.nha-root .bpr-tbl select.bpr-field { width: 100%; text-overflow: ellipsis; }
.nha-root .bpr-tbl .bpr-pick-input { width: 100%; text-overflow: ellipsis; }
.nha-root .bpr-tbl td.bpr-nowrap { white-space: nowrap; }
.nha-root .bpr-tbl input.bpr-suggested { width: 100%; text-align: right; font-weight: 700; font-variant-numeric: tabular-nums; }
.nha-root .bpr-req { color: #FF8A8A; margin-left: 2px; }
.nha-root .bpr-empty { padding: 18px 12px; text-align: center; font-size: 12.5px; color: var(--subtle); }
.nha-root .bpr-noproj { padding: 18px 20px; border-top: 1px solid var(--bdr); text-align: center; font-size: 12.5px; font-style: italic; color: var(--subtle); }
.nha-root #bpr-blocks > .bpr-noproj { border: 1px solid var(--bdr); border-radius: 12px; margin-bottom: 18px; }

/* read-only boxes (Account Description / Amount): they look like the inputs beside them, tinted so
   they read as fixed — what the originating office proposed */
.nha-root .bpr-ro {
    box-sizing: border-box; display: flex; flex-direction: column; justify-content: center; min-height: 36px; padding: 7px 10px;
    font-size: 13px; line-height: 1.35; color: var(--txt); overflow-wrap: anywhere;
    background: var(--surf); border: 1px solid var(--bdr); border-radius: var(--rs, 7px);
}
.nha-root .bpr-ro-code { font-family: var(--mono); font-size: 12px; white-space: nowrap; }
.nha-root .bpr-ro-amt { align-items: flex-end; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; }
.nha-root .bpr-desc-sub { margin-top: 2px; font-size: 11.5px; color: var(--subtle); }

/* ── Supporting Documents of a Project (under its table): one line per document ── */
.nha-root .bpr-docs { margin-top: 14px; }
.nha-root .bpr-docs-hd {
    display: flex; align-items: center; gap: 8px; margin-bottom: 8px;
    font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--sec);
}
.nha-root .bpr-docs-n {
    min-width: 18px; padding: 1px 6px; border-radius: 10px; text-align: center; letter-spacing: 0;
    font-size: 11px; color: var(--sec); background: var(--surf); border: 1px solid var(--bdr);
}
.nha-root .bpr-doc-list { margin: 0; padding: 0; list-style: none; border: 1px solid var(--bdr); border-radius: 10px; overflow: hidden; }
.nha-root .bpr-doc { display: flex; align-items: center; gap: 10px; padding: 9px 12px; font-size: 13px; color: var(--txt); background: var(--afims-surface, #FFFFFF); }
.nha-root .bpr-doc + .bpr-doc { border-top: 1px solid var(--bdr); }
.nha-root .bpr-doc-ico { width: 16px; height: 16px; flex-shrink: 0; color: var(--subtle); }
.nha-root .bpr-doc-name { flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
.nha-root a.bpr-doc-link { color: var(--afims-primary-text, #0035A9); text-decoration: none; }
.nha-root a.bpr-doc-link:hover { text-decoration: underline; }
.nha-root a.bpr-doc-link:focus-visible, .nha-root .bpr-doc-open:focus-visible { outline: 2px solid var(--afims-primary, #0035A9); outline-offset: 2px; border-radius: 3px; }
.nha-root .bpr-doc-kind {
    flex-shrink: 0; padding: 1px 7px; border-radius: 4px; font-size: 10.5px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    color: var(--sec); background: var(--surf); border: 1px solid var(--bdr);
}
.nha-root .bpr-doc-date { flex-shrink: 0; font-size: 11.5px; color: var(--subtle); white-space: nowrap; }
.nha-root .bpr-doc-open { flex-shrink: 0; font-size: 12px; font-weight: 600; white-space: nowrap; text-decoration: none; color: var(--afims-heading-fill, #000E3E); }
.nha-root .bpr-doc-open:hover { text-decoration: underline; }
.nha-root .bpr-doc-na { flex-shrink: 0; max-width: 40%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 12px; font-style: italic; color: var(--subtle); }
.nha-root .bpr-docs-note {
    padding: 10px 12px; font-size: 12.5px; font-style: italic; color: var(--subtle);
    background: var(--surf); border: 1px dashed var(--bdr); border-radius: 10px;
}
.nha-root .bpr-docs-err { font-style: normal; color: var(--red-txt, #8F222D); background: var(--red-bg, #FAE1E3); border-color: transparent; }
.nha-root .bpr-docs-retry {
    margin-left: 6px; padding: 0; border: 0; background: none; cursor: pointer; font: inherit; font-weight: 700;
    text-decoration: underline; color: inherit;
}
@media (max-width: 640px) { .nha-root .bpr-doc-date, .nha-root .bpr-doc-kind { display: none; } }

/* Subtotal (the last row of each Project's table, under its Amount / Suggested Amount columns) */
.nha-root .bpr-tbl tfoot td {
    padding: 11px 8px; font-size: 13.5px; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap;
    background: var(--surf); border-top: 1px solid var(--bdr); color: var(--txt);
}
.nha-root .bpr-tbl tfoot td:first-child { white-space: normal; }

/* Grand Total (every Project): the Amount total and the Suggested Amount total */
.nha-root .bpr-grand {
    display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: baseline; gap: 6px 30px; font-weight: 700;
}
.nha-root .bpr-foot-v small { margin-right: 2px; font-size: 10.5px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--sec); }
.nha-root .bpr-note { margin: 2px 0 14px; font-size: 12.5px; color: var(--sec); }
.nha-root .bpr-grand {
    margin-bottom: 20px; padding: 16px 22px; border-radius: 12px; font-size: 16px;
    background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF);
}
.nha-root .bpr-grand .bpr-foot-v small { color: rgba(255, 255, 255, .7); }

/* a required dropdown left empty (or an invalid amount) when Endorse is clicked */
.nha-root .bpr-field.is-invalid, .nha-root .bpr-pick-input.is-invalid, .nha-root .bpr-remarks.is-invalid {
    border-color: var(--red, #DC3545);
    box-shadow: 0 0 0 3px rgba(220, 53, 69, .15);
}

/* ── Remarks ── */
.nha-root .bpr-remarks-label {
    display: block; margin: 4px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .04em;
    text-transform: uppercase; color: var(--sec);
}
.nha-root .bpr-remarks {
    display: block; width: 100%; min-height: 78px; padding: 10px 12px; box-sizing: border-box;
    font: inherit; font-size: 13px; color: var(--txt); resize: vertical;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 8px;
}
.nha-root .bpr-remarks:focus { outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15, 32, 69, .08); }

.nha-root .bpr-err {
    margin-top: 12px; padding: 8px 10px; font-size: 12.5px; border-radius: var(--rs, 7px);
    background: var(--red-bg, #FAE1E3); color: var(--red-txt, #8F222D);
}
.nha-root .bpr-err[hidden] { display: none; }

/* ── "Returned to Budget Officer": who sent it back and why (the Budget Head's remarks) ── */
.nha-root .bpr-returned {
    margin: 0 0 16px; padding: 10px 12px; font-size: 13px; border-radius: var(--rs, 7px);
    background: #FFF8EB; color: #92400E; border: 1px solid #F59E0B; overflow-wrap: anywhere;
}
.nha-root .bpr-returned[hidden] { display: none; }

/* ── Footer: Return (outlined) / Endorse (filled) ── */
.nha-root .nha-modal-foot .nha-btn[disabled] { opacity: .6; cursor: not-allowed; }
.nha-root .nha-modal-foot .bpr-btn-return {
    background: var(--afims-surface, #FFFFFF); color: #B45309; border: 1px solid #F59E0B;
}
.nha-root .nha-modal-foot .bpr-btn-return:hover:not([disabled]) { background: #FFF8EB; }
.nha-root .nha-modal-foot .bpr-btn-endorse {
    background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF);
    border: 1px solid var(--afims-heading-fill, #000E3E);
}
.nha-root .nha-modal-foot .bpr-btn-endorse:hover:not([disabled]) { opacity: .92; }

@media (max-width: 640px) {
    .nha-root .bpr-row { grid-template-columns: 1fr; gap: 2px; }
}

/* ── Account Code picker list. NOT scoped under .nha-root: it is appended to <body> (see the script), above the
      modal overlay, so it only uses the global --afims-* tokens. ── */
.bpr-pick-menu {
    display: none; position: fixed; z-index: 10000; max-height: 260px; overflow-x: hidden; overflow-y: auto; padding: 4px 0;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, .14); font-family: 'IBM Plex Sans', sans-serif;
}
.bpr-pick-menu.open { display: block; }
.bpr-pick-option { padding: 8px 12px; font-size: 13px; line-height: 1.4; color: var(--afims-text, #1A1B21); cursor: pointer; }
.bpr-pick-option:hover, .bpr-pick-option.active { background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF); }
.bpr-pick-option.selected { font-weight: 700; }
.bpr-pick-empty, .bpr-pick-hint { padding: 8px 12px; font-size: 12px; font-style: italic; color: var(--afims-text-disabled, #A9AAB8); }
</style>

<!-- ── BUDGET PROCESSING MODAL ───────────────────────────── -->
<div id="bpr-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="bpr-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="bpr-title">Submission Ref. No. <span class="bpr-ref" id="bpr-ref"></span></h3>
            <button type="button" class="nha-modal-close" id="bpr-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body" id="bpr-body">

            <!-- only on a request the Budget Head sent back: the Head's remarks -->
            <div class="bpr-returned" id="bpr-returned" hidden></div>

            <!-- Originating Unit · Date Submitted · Programs · Projects -->
            <div class="bpr-meta" id="bpr-meta"></div>

            <!-- one card per Program: Program / Sub-Program, a section per Project (its line items table, ending with its Subtotal; Type of Fund + Component are required to Endorse) -->
            <div id="bpr-blocks"></div>

            <p class="bpr-note">Account code, description and amount are prefilled from the originating unit's proposal. The Budget Officer may change the Account Code (the description follows it). Suggested Amount starts as a copy of the amount — FMD may edit it before endorsing.</p>

            <div class="bpr-grand">
                <span>Grand Total</span>
                <span class="bpr-foot-v"><small>Amount</small> <span id="bpr-grand-p">₱ 0.00</span></span>
                <span class="bpr-foot-v"><small>Suggested</small> <span id="bpr-grand-s">₱ 0.00</span></span>
            </div>

            <label class="bpr-remarks-label" for="bpr-remarks">Remarks / Compliance Needed (required when returning)</label>
            <textarea class="bpr-remarks" id="bpr-remarks" rows="3" maxlength="2000"
                      placeholder="e.g. missing supporting documents, suggested proposal amount"></textarea>

            <div class="bpr-err" id="bpr-err" hidden></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn bpr-btn-return" id="bpr-return">Return</button>
            <button type="button" class="nha-btn bpr-btn-endorse" id="bpr-endorse">Endorse</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET PROCESSING MODAL   prefix: bpr_
   Depends on bgr_esc / bgr_escHtmlAttr / bgr_fmtMoney / bgr_fmtAmount /
   bgr_fmtAmountInitial / bgr_unformatAmount / bgr_toast
   from form/budget-req-form.php, included on the same page. The line items
   come from the already-fetched list data; only the Type of Fund / Component /
   Account Code option lists are fetched (once, then cached).
═══════════════════════════════════════════════════════════════ */

const BPR_TYPE_OF_FUND_API  = '<?php echo $_bpr_api; ?>' + '/type_of_fund/all';
const BPR_COMPONENT_API     = '<?php echo $_bpr_api; ?>' + '/component_type/all';
const BPR_ACCOUNT_API      = '<?php echo $_bpr_api; ?>' + '/chart_of_accounts/all?postable_only=1&for_budget_proposal=1';   // the Account Code picker: the same list as the Budget Request form
const BPR_BUDGET_REQ_API    = '<?php echo $_bpr_api; ?>' + '/budget_req';
const BPR_ATTACHMENT_API    = '<?php echo $_bpr_api; ?>' + '/budget_req_project_attachment';   // a Project's Supporting Documents

let BPR_FUNDS = null;       // [{ type_of_fund_id, name }]       — null until loaded
let BPR_COMPONENTS = null;  // [{ component_type_id, name }]     — null until loaded
let BPR_ACCOUNTS = null;    // [{ account_id, account_code, account_title }] — null until loaded

let bpr_ctx = null;         // requestContext of the modal that is currently open
let bpr_rowCount = 0;       // number of line-item rows rendered, across all blocks
let bpr_renderSeq = 0;      // bumped by every render and every close: a Supporting Documents answer that arrives after that is dropped

const $bpr = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bpr('bpr-close')?.addEventListener('click', bpr_closeModal);
    $bpr('bpr-return')?.addEventListener('click', bpr_return);
    $bpr('bpr-endorse')?.addEventListener('click', bpr_endorse);
    bpr_pickInit();

    // "Retry" in a Project's Supporting Documents section whose request failed
    $bpr('bpr-blocks')?.addEventListener('click', e => {
        const retry = e.target.closest('.bpr-docs-retry');
        if (retry) bpr_loadDocsFor(retry.closest('.bpr-docs'), bpr_renderSeq);
    });

    $bpr('bpr-body')?.addEventListener('input', e => {
        const el = e.target;

        // the Suggested Amount totals (the Subtotal of its Project, the Grand Total) follow the inputs as they are typed
        if (el.matches('.bpr-suggested')) bpr_updateTotals();

        // a field that was flagged as missing stops looking invalid once filled; its block loses the
        // red outline once nothing is left to fix in it
        if (el.matches('.bpr-field')) {
            el.classList.remove('is-invalid');
            const block = el.closest('.bpr-block');
            if (block && !block.querySelector('.is-invalid')) block.classList.remove('has-error');
        }
        if (el.matches('.bpr-remarks')) el.classList.remove('is-invalid');
    });
});

async function bpr_loadOptions() {
    if (BPR_FUNDS && BPR_COMPONENTS && BPR_ACCOUNTS) return;

    const responses = await Promise.all([
        fetch(BPR_TYPE_OF_FUND_API, { credentials: 'same-origin' }),
        fetch(BPR_COMPONENT_API,    { credentials: 'same-origin' }),
        fetch(BPR_ACCOUNT_API,      { credentials: 'same-origin' }),
    ]);
    if (responses.some(r => !r.ok)) throw new Error('Failed to load the Type of Fund / Component / Account Code lists.');

    const [funds, components, accounts] = await Promise.all(responses.map(r => r.json()));
    if (!funds.success)       throw new Error(funds.message || 'Failed to load types of fund.');
    if (!components.success)  throw new Error(components.message || 'Failed to load components.');
    if (!accounts.success)    throw new Error(accounts.message || 'Failed to load the chart of accounts.');

    BPR_FUNDS      = funds.data ?? [];
    BPR_COMPONENTS = components.data ?? [];
    BPR_ACCOUNTS   = accounts.data ?? [];
}

// `programs` is one program or an array of them (see the header comment).
async function bpr_openModal(requestContext, programs) {
    window.PagePreloader?.show();
    try {
        await bpr_loadOptions();
    } catch (e) {
        bgr_toast(e.message, 'error');
        return;
    } finally {
        window.PagePreloader?.hide();
    }

    bpr_ctx = requestContext;
    bpr_render(requestContext, programs);
    bpr_hideErr();

    // A request the Budget Head sent back ("Returned to Budget Officer") shows the Head's remarks above the details.
    const returnedBox = $bpr('bpr-returned');
    returnedBox.hidden = !requestContext.review?.is_returned;
    returnedBox.textContent = requestContext.review?.is_returned
        ? 'Returned by the Budget Head' + (requestContext.remarks ? ' — ' + requestContext.remarks : '.')
        : '';
    $bpr('bpr-remarks').value = '';
    $bpr('bpr-remarks').classList.remove('is-invalid');
    bpr_updateButtons(false);
    $bpr('bpr-overlay').classList.add('open');
}

function bpr_closeModal() {
    bpr_pickClose();
    bpr_renderSeq++;   // documents still on their way belong to a modal nobody is looking at
    $bpr('bpr-overlay').classList.remove('open');
}

/* ── Dropdowns ───────────────────────────────────────────────── */
function bpr_optionsHtml(options, idKey, selectedId) {
    const sel = selectedId === null || selectedId === undefined ? '' : String(selectedId);
    return '<option value="">— Select —</option>' + options.map(o =>
        `<option value="${Number(o[idKey])}"${String(o[idKey]) === sel ? ' selected' : ''}>${bgr_esc(o.name)}</option>`
    ).join('');
}

/* ── Account Code: a searchable picker ──────────────────────────────────
   The Account Code stores the account_id and shows the account_code in its box ("code — title" in the list), and is
   never empty (`required`). BPR_PICKERS says, per kind, which list is searched and which hidden input holds the code
   (there is only one kind now, `account`; the picker code stays generic). Each cell holds a visible search input
   (.bpr-pick-input, with data-pick = its kind and the text of the current choice in data-label) and a hidden input
   (.bpr-account) with the code that Endorse sends.
   ONE shared list (.bpr-pick-menu) serves every input of the column. It lives on <body> and is position:fixed because the
   table's overflow would clip a normal dropdown, and it is positioned by hand under (or, when there is no room
   below, over) whichever input it is open for. Because it is one element — not one per row — re-rendering the
   table leaves nothing behind to clean up. Events are delegated from #bpr-blocks for the same reason. */
const BPR_PICK_MAX = 50;       // most items listed at once; typing narrows the rest down
let bpr_pickMenu = null;       // the shared list element (created on first use)
let bpr_pickInput = null;      // the input the list is currently open for
let bpr_pickMatches = [];      // the items currently listed
let bpr_pickActive = -1;       // index of the highlighted item (-1 = none)

// The pickers. `items` is a function because the lists are only loaded when the modal first opens.
// `text` (optional) is what the box shows for an item — default "name — code"; `option` (optional) is what the list shows — default `text`.
// `required`: the box can't be emptied (an empty / unpicked box puts the previous choice back).
const BPR_PICKERS = {
    account: { title: 'Account Code',    items: () => BPR_ACCOUNTS, code: 'account_id',       name: 'account_title',    hiddenClass: 'bpr-account', none: 'No matching account.',
               required: true,
               text:   a => a.account_code,
               option: a => `${a.account_code} — ${a.account_title}`,
               // typing matches the account CODE (the stored code is the id) and the title
               extra:  'account_code' },
};
const bpr_pickCfg    = input => BPR_PICKERS[input.dataset.pick];
const bpr_pickHidden = (input, cfg) => input.closest('.bpr-pick-wrap').querySelector('.' + cfg.hiddenClass);
const bpr_pickLabel  = (cfg, item) => cfg.text ? cfg.text(item) : `${item[cfg.name]} — ${item[cfg.code]}`;
const bpr_pickOption = (cfg, item) => cfg.option ? cfg.option(item) : bpr_pickLabel(cfg, item);
const bpr_pickFind   = (cfg, code) => cfg.items().find(x => String(x[cfg.code]) === String(code));

// The label to show for a saved code: its label; `fallback` (or the bare code) if that item is no longer in the list
// (e.g. an account that is not postable any more: the line keeps it until the officer picks another one).
function bpr_pickLabelFor(kind, code, fallback) {
    if (code === null || code === undefined || code === '') return '';
    const cfg  = BPR_PICKERS[kind];
    const item = bpr_pickFind(cfg, code);
    return item ? bpr_pickLabel(cfg, item) : String(fallback ?? code);
}

function bpr_pickEnsureMenu() {
    if (bpr_pickMenu) return bpr_pickMenu;
    bpr_pickMenu = document.createElement('div');
    bpr_pickMenu.className = 'bpr-pick-menu';
    bpr_pickMenu.setAttribute('role', 'listbox');
    document.body.appendChild(bpr_pickMenu);

    // mousedown, not click: a click would first blur the input (nothing in the list takes focus), which closes the
    // list from under the click. preventDefault() keeps focus in the input, so the pick always registers.
    bpr_pickMenu.addEventListener('mousedown', ev => {
        ev.preventDefault();
        const opt = ev.target.closest('.bpr-pick-option');
        if (opt && bpr_pickInput) bpr_pickChoose(bpr_pickInput, opt.dataset.code);
    });
    return bpr_pickMenu;
}

function bpr_pickIsOpen() {
    return !!bpr_pickMenu && bpr_pickMenu.classList.contains('open');
}

// Lists the items (by the input's kind) matching `query` (name or code, any case).
function bpr_pickRender(query) {
    const cfg = bpr_pickCfg(bpr_pickInput);
    const q = query.trim().toLowerCase();
    const all = cfg.items().filter(p => !q
        || String(p[cfg.code]).toLowerCase().includes(q)
        || String(p[cfg.name]).toLowerCase().includes(q)
        || (cfg.extra && String(p[cfg.extra] ?? '').toLowerCase().includes(q)));
    bpr_pickMatches = all.slice(0, BPR_PICK_MAX);
    bpr_pickActive  = q && bpr_pickMatches.length ? 0 : -1;   // once something is typed, Enter takes the best match

    const chosen = bpr_pickHidden(bpr_pickInput, cfg).value;
    bpr_pickMenu.innerHTML = bpr_pickMatches.length
        ? bpr_pickMatches.map((p, i) =>
            `<div class="bpr-pick-option${i === bpr_pickActive ? ' active' : ''}${String(p[cfg.code]) === chosen ? ' selected' : ''}" role="option" data-code="${bgr_escHtmlAttr(p[cfg.code])}">${bgr_esc(bpr_pickOption(cfg, p))}</div>`
          ).join('') + (all.length > BPR_PICK_MAX ? `<div class="bpr-pick-hint">Showing the first ${BPR_PICK_MAX} of ${all.length} — type to narrow down.</div>` : '')
        : `<div class="bpr-pick-empty">${cfg.none}</div>`;
}

// Puts the list under the input — or over it when there is more room above — wide enough to read, kept on screen.
function bpr_pickPosition() {
    if (!bpr_pickInput) return;
    const r      = bpr_pickInput.getBoundingClientRect();
    const width  = Math.min(Math.max(r.width, 340), window.innerWidth - 16);
    const left   = Math.max(8, Math.min(r.left, window.innerWidth - width - 8));
    const below  = window.innerHeight - r.bottom;
    const menu   = bpr_pickMenu;
    menu.style.width = width + 'px';
    menu.style.left  = left + 'px';
    if (below < 200 && r.top > below) {
        menu.style.top       = 'auto';
        menu.style.bottom    = (window.innerHeight - r.top + 4) + 'px';
        menu.style.maxHeight = Math.max(120, Math.min(260, r.top - 12)) + 'px';
    } else {
        menu.style.bottom    = 'auto';
        menu.style.top       = (r.bottom + 4) + 'px';
        menu.style.maxHeight = Math.max(120, Math.min(260, below - 12)) + 'px';
    }
}

// Browsers undo a select()-on-focus with the mouse-up of the same click, which would leave the old label in the box
// and make typing APPEND to it. So the mouse-up that ends the focusing click is cancelled (once; bpr_pickCommit
// removes it if no click came, e.g. when focus arrived by Tab).
const bpr_pickKeepSelection = ev => ev.preventDefault();

// `fresh` = just focused: show EVERY item (not only the current choice) and select the text, so typing replaces it.
function bpr_pickOpen(input, fresh) {
    bpr_pickInput = input;
    bpr_pickEnsureMenu();
    if (fresh) {
        input.select();
        input.addEventListener('mouseup', bpr_pickKeepSelection, { once: true });
    }
    bpr_pickRender(fresh ? '' : input.value);
    bpr_pickPosition();
    bpr_pickMenu.classList.add('open');
    input.setAttribute('aria-expanded', 'true');
}

function bpr_pickClose() {
    if (bpr_pickMenu) bpr_pickMenu.classList.remove('open');
    if (bpr_pickInput) bpr_pickInput.setAttribute('aria-expanded', 'false');
    bpr_pickInput  = null;
    bpr_pickActive = -1;
}

function bpr_pickSetActive(i) {
    const opts = [...bpr_pickMenu.querySelectorAll('.bpr-pick-option')];
    if (!opts.length) return;
    bpr_pickActive = Math.max(0, Math.min(opts.length - 1, i));
    opts.forEach((o, k) => o.classList.toggle('active', k === bpr_pickActive));
    opts[bpr_pickActive].scrollIntoView?.({ block: 'nearest' });
}

// Makes `code` this input's choice (an unknown / empty code clears it) and closes the list.
function bpr_pickChoose(input, code) {
    const cfg   = bpr_pickCfg(input);
    const item  = bpr_pickFind(cfg, code);
    const label = item ? bpr_pickLabel(cfg, item) : '';
    bpr_pickHidden(input, cfg).value = item ? item[cfg.code] : '';
    input.value         = label;
    input.dataset.label = label;
    input.title = item ? bpr_pickOption(cfg, item) : '';
    if (input.dataset.pick === 'account') bpr_syncAccountTitle(input, item);
    bpr_pickClearInvalid(input);
    bpr_pickClose();
}

// The Account Description box (same row) shows the title of the account picked in the Account Code box.
function bpr_syncAccountTitle(input, item) {
    const title = input.closest('tr')?.querySelector('.bpr-acct-title');
    if (title && item) title.textContent = item.account_title ?? '';
}

// A picker flagged as missing stops looking invalid once it has a choice; its block loses the red outline once nothing is left to fix in it.
function bpr_pickClearInvalid(input) {
    if (!input.classList.contains('is-invalid') || !bpr_pickHidden(input, bpr_pickCfg(input)).value) return;
    input.classList.remove('is-invalid');
    const block = input.closest('.bpr-block');
    if (block && !block.querySelector('.is-invalid')) block.classList.remove('has-error');
}

// The input lost focus. An EMPTY box means "none chosen" (a `required` picker, like Account Code, puts its previous choice back instead).
// Text that is exactly an item's label counts as picking it. Any other typed text was never picked: it is dropped
// and the previous choice is shown again.
function bpr_pickCommit(input) {
    input.removeEventListener('mouseup', bpr_pickKeepSelection);
    const cfg  = bpr_pickCfg(input);
    const text = input.value.trim();
    if (text === '') {
        if (cfg.required) input.value = input.dataset.label || '';
        else              bpr_pickChoose(input, '');
    } else {
        const exact = cfg.items().find(p => bpr_pickLabel(cfg, p).toLowerCase() === text.toLowerCase());
        if (exact) bpr_pickChoose(input, exact[cfg.code]);
        else       input.value = input.dataset.label || '';
    }
    if (bpr_pickInput === input) bpr_pickClose();
}

function bpr_pickKey(ev) {
    const input = ev.target;
    const open  = bpr_pickIsOpen() && bpr_pickInput === input;

    if (ev.key === 'ArrowDown' || ev.key === 'ArrowUp') {
        ev.preventDefault();
        if (!open) { bpr_pickOpen(input, true); bpr_pickSetActive(0); return; }
        bpr_pickSetActive(bpr_pickActive + (ev.key === 'ArrowDown' ? 1 : -1));
    } else if (ev.key === 'Enter' && open && bpr_pickActive >= 0) {
        ev.preventDefault();
        bpr_pickChoose(input, bpr_pickMatches[bpr_pickActive][bpr_pickCfg(input).code]);
    } else if (ev.key === 'Escape' && open) {
        ev.preventDefault();
        ev.stopPropagation();
        input.value = input.dataset.label || '';    // cancel: back to the previous choice
        bpr_pickClose();
    }
}

// Wires the delegated events once. The list follows its input when something scrolls or the window resizes.
function bpr_pickInit() {
    const host = $bpr('bpr-blocks');
    if (!host) return;
    host.addEventListener('focusin',  e => { if (e.target.matches('.bpr-pick-input')) bpr_pickOpen(e.target, true); });
    host.addEventListener('input',    e => { if (e.target.matches('.bpr-pick-input')) bpr_pickOpen(e.target, false); });
    host.addEventListener('keydown',  e => { if (e.target.matches('.bpr-pick-input')) bpr_pickKey(e); });
    host.addEventListener('focusout', e => { if (e.target.matches('.bpr-pick-input')) bpr_pickCommit(e.target); });

    // a click on a part of the page that takes no focus doesn't blur the input, so close the list here too
    document.addEventListener('mousedown', e => {
        if (bpr_pickIsOpen() && !bpr_pickMenu.contains(e.target) && e.target !== bpr_pickInput) bpr_pickClose();
    });
    // Something else scrolled (the modal body, the table): the list follows its input — unless the input has scrolled
    // out of the visible part of the modal, when the list closes rather than float over unrelated content.
    // Scrolling the LIST itself must not move or close it.
    document.addEventListener('scroll', e => {
        if (!bpr_pickIsOpen() || bpr_pickMenu.contains(e.target)) return;
        const r = bpr_pickInput.getBoundingClientRect();
        const b = $bpr('bpr-body').getBoundingClientRect();
        if (r.bottom < b.top || r.top > b.bottom) bpr_pickClose();
        else bpr_pickPosition();
    }, true);
    window.addEventListener('resize', () => { if (bpr_pickIsOpen()) bpr_pickPosition(); });
}

/* ── Table ───────────────────────────────────────────────────── */
// Column order: Type of Fund · Component · Account Code · Account Description · Amount · Suggested Amount
const BPR_THEAD = `<thead><tr>
    <th>Type of Fund<span class="bpr-req">*</span></th>
    <th>Component<span class="bpr-req">*</span></th>
    <th>Account Code<span class="bpr-req">*</span></th>
    <th>Account Description</th>
    <th class="text-right">Amount</th>
    <th class="text-right">Suggested Amount</th>
</tr></thead>`;
// Column widths (they add up to 100%), the same for every table. The amounts need room for 16 characters ("1,402,000,000.00").
const BPR_COLGROUP = '<colgroup>' + [16, 16, 14, 20, 16, 18].map(w => `<col style="width:${w}%">`).join('') + '</colgroup>';

// The Suggested Amount is optional. It starts as a copy of the Amount (the proposed amount) — unless the line item
// already has a saved suggested amount (above 0), which is kept. Either way it is an ordinary input: it can be
// changed, or cleared (blank is saved as 0.00).
function bpr_initialSuggested(li) {
    return Number(li.suggested_amount || 0) > 0 ? li.suggested_amount : li.proposed_amount;
}

// A searchable-picker cell (Account Code): the visible search input + the hidden code that
// Endorse sends. `fallback` is the text to show when the saved code is not in the picker's list any more.
function bpr_pickCellHtml(kind, code, fallback) {
    const cfg   = BPR_PICKERS[kind];
    const label = bgr_escHtmlAttr(bpr_pickLabelFor(kind, code, fallback));
    return `<td><div class="bpr-pick-wrap">
            <input type="text" class="nha-filter-input bpr-pick-input" data-pick="${kind}" autocomplete="off" placeholder="Type to search…"
                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-label="${cfg.title}"
                   title="${label}" value="${label}" data-label="${label}">
            <input type="hidden" class="${cfg.hiddenClass}" value="${bgr_escHtmlAttr(code ?? '')}">
        </div></td>`;
}

function bpr_rowHtml(li) {
    return `<tr data-li-id="${Number(li.budget_line_item_id)}">
        <td><select class="nha-filter-select bpr-field bpr-fund">${bpr_optionsHtml(BPR_FUNDS, 'type_of_fund_id', li.type_of_fund_id)}</select></td>
        <td><select class="nha-filter-select bpr-field bpr-component">${bpr_optionsHtml(BPR_COMPONENTS, 'component_type_id', li.component_id)}</select></td>
        ${bpr_pickCellHtml('account', li.account_id, li.account_code)}
        <td><div class="bpr-ro"><span class="bpr-acct-title">${bgr_esc(li.account_title)}</span>${li.particulars ? `<span class="bpr-desc-sub">${bgr_esc(li.particulars)}</span>` : ''}</div></td>
        <td class="text-right bpr-proposed" data-amount="${bgr_escHtmlAttr(li.proposed_amount ?? 0)}"><div class="bpr-ro bpr-ro-amt">${bgr_fmtAmountInitial(li.proposed_amount ?? 0)}</div></td>
        <td class="text-right"><input type="text" inputmode="decimal" autocomplete="off" class="nha-filter-input bpr-field bpr-suggested"
                   placeholder="0.00"
                   value="${bgr_escHtmlAttr(bgr_fmtAmountInitial(bpr_initialSuggested(li)))}"
                   oninput="bgr_fmtAmount(this)"></td>
    </tr>`;
}

function bpr_amountOf(input) {
    return Number(bgr_unformatAmount(input.value)) || 0;
}

// Sets every total: the Subtotal of each PROJECT (its own line items only) and the Grand Total (all Projects) —
// the Amounts (fixed) and the Suggested Amount inputs (live).
function bpr_updateTotals() {
    let grandP = 0, grandS = 0;
    document.querySelectorAll('#bpr-blocks .bpr-block').forEach(block => {
        const p = [...block.querySelectorAll('.bpr-proposed')].reduce((sum, el) => sum + (Number(el.dataset.amount) || 0), 0);
        const s = [...block.querySelectorAll('.bpr-suggested')].reduce((sum, el) => sum + bpr_amountOf(el), 0);
        block.querySelector('.bpr-sub-p').textContent = bgr_fmtMoney(p);
        block.querySelector('.bpr-sub-s').textContent = bgr_fmtMoney(s);
        grandP += p;
        grandS += s;
    });
    $bpr('bpr-grand-p').textContent = bgr_fmtMoney(grandP);
    $bpr('bpr-grand-s').textContent = bgr_fmtMoney(grandS);
}

function bpr_tableHtml(project) {
    const lineItems = project.line_items ?? [];
    const rowsHtml  = lineItems.length
        ? lineItems.map(bpr_rowHtml).join('')
        : '<tr><td colspan="6"><div class="bpr-empty">No line items have been added under this project.</div></td></tr>';

    return `<div class="bpr-tbl-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl bpr-tbl">${BPR_COLGROUP}${BPR_THEAD}<tbody>${rowsHtml}</tbody>
                <tfoot><tr><td colspan="4" class="text-right">Subtotal – Project${project.project_code ? ' ' + bgr_esc(project.project_code) : ''}</td><td class="text-right bpr-sub-p">${bgr_fmtMoney(0)}</td><td class="text-right bpr-sub-s">${bgr_fmtMoney(0)}</td></tr></tfoot>
            </table>
        </div>
    </div>`;
}

/* ── Meta line, Program cards, Project sections ───────────────────── */
// Postgres timestamps arrive as "2026-11-10 08:30:00.123456+08": make that ISO enough for every browser's Date(),
// then show it as "Nov 10, 2026". '' when there is no date (or it is not one: then as it came).
function bpr_fmtDate(value) {
    if (!value) return '';
    const iso = String(value).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d = new Date(iso);
    return isNaN(d) ? String(value) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

const bpr_chip = code => `<span class="bpr-chip">${bgr_esc(code)}</span>`;

// The requesting office: its id in bold (e.g. "R07"); its name is in the tooltip
function bpr_unitHtml(ctx) {
    const id = ctx.rc_id ? String(ctx.rc_id) : (ctx.rc_code ? String(ctx.rc_code) : '');
    if (!id && !ctx.rc_name) return '<span class="bpr-muted">—</span>';
    return `<strong title="${bgr_escHtmlAttr(ctx.rc_name ?? '')}">${bgr_esc(id || ctx.rc_name)}</strong>`;
}

function bpr_metaHtml(ctx, programs) {
    const projectCount = programs.reduce((n, p) => n + (p.projects ?? []).length, 0);
    const item = (label, valueHtml) => `<span><span class="bpr-meta-k">${label}</span> ${valueHtml}</span>`;
    return item('Originating Unit:', bpr_unitHtml(ctx))
         + item('Date Submitted:', bgr_esc(bpr_fmtDate(ctx.submitted_at)) || '<span class="bpr-muted">—</span>')
         + item('Programs:', programs.length)
         + item('Projects:', projectCount);
}

// "<code chip> name"; the name is bold for the Program line. '—' when there is neither.
function bpr_codeNameHtml(code, name, bold) {
    if (!code && !name) return '<span class="bpr-muted">—</span>';
    const nameHtml = bold ? `<strong>${bgr_esc(name || '')}</strong>` : bgr_esc(name || '');
    return (code ? bpr_chip(code) + ' ' : '') + nameHtml;
}

/* ── Supporting Documents of a Project ───────────────────────────────── */
const BPR_ICON_FILE = '<svg class="bpr-doc-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>';
const BPR_ICON_LINK = '<svg class="bpr-doc-ico" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14a3.5 3.5 0 005 0l4-4a3.5 3.5 0 00-5-5l-.5.5M14 10a3.5 3.5 0 00-5 0l-4 4a3.5 3.5 0 005 5l.5-.5"/></svg>';

// The section's frame. Its documents are looked up by the Program's id + the project code (that is how they are stored), so a
// Project without either gets no section rather than a false "none attached".
function bpr_docsHtml(program, project) {
    const programId = program.budget_req_program_id;
    if (programId === null || programId === undefined || programId === '' || !project.project_code) return '';
    return `<div class="bpr-docs" data-program-id="${bgr_escHtmlAttr(programId)}" data-project-code="${bgr_escHtmlAttr(project.project_code)}">
        <div class="bpr-docs-hd"><span>Supporting Documents</span><span class="bpr-docs-n" hidden></span></div>
        <div class="bpr-docs-body"><div class="bpr-docs-note">Loading documents…</div></div>
    </div>`;
}

// A pasted link is stored as typed (the server does not check what it is), so only a web address may become a clickable
// link: a javascript: / data: / "file:" link is shown as text instead. '' = not openable.
function bpr_safeUrl(url) {
    const u = String(url ?? '').trim();
    return /^https?:\/\/\S+$/i.test(u) ? u : '';
}

// One attachment: { attachment_id, document_name, storage_key, external_link, uploaded_at, view_url }.
// view_url is a signed link for an uploaded file, the pasted link otherwise.
function bpr_docRowHtml(a) {
    const isFile = !!a.storage_key;
    const name   = a.document_name || (isFile ? 'Document' : (a.external_link || 'Document'));
    const url    = bpr_safeUrl(a.view_url || a.external_link);
    const date   = bpr_fmtDate(a.uploaded_at);
    const href   = bgr_escHtmlAttr(url);
    // the name opens the document too (the same address as "Open ↗"); with no openable address it is plain text
    const nameEl = url
        ? `<a class="bpr-doc-name bpr-doc-link" href="${href}" target="_blank" rel="noopener noreferrer" title="${bgr_escHtmlAttr(name)}">${bgr_esc(name)}</a>`
        : `<span class="bpr-doc-name" title="${bgr_escHtmlAttr(name)}">${bgr_esc(name)}</span>`;
    const open   = url
        ? `<a class="bpr-doc-open" href="${href}" target="_blank" rel="noopener noreferrer" aria-label="Open ${bgr_escHtmlAttr(name)}">Open ↗</a>`
        : (isFile ? '<span class="bpr-doc-na">File unavailable</span>'
                  : `<span class="bpr-doc-na" title="${bgr_escHtmlAttr(a.external_link || '')}">Not a web link: ${bgr_esc(a.external_link || '—')}</span>`);
    return `<li class="bpr-doc">
        ${isFile ? BPR_ICON_FILE : BPR_ICON_LINK}
        ${nameEl}
        <span class="bpr-doc-kind">${isFile ? 'File' : 'Link'}</span>
        ${date ? `<span class="bpr-doc-date">${bgr_esc(date)}</span>` : ''}
        ${open}
    </li>`;
}

function bpr_fillDocs(box, docs) {
    const count = box.querySelector('.bpr-docs-n');
    count.textContent = docs.length;
    count.hidden = docs.length === 0;
    box.querySelector('.bpr-docs-body').innerHTML = docs.length
        ? `<ul class="bpr-doc-list">${docs.map(bpr_docRowHtml).join('')}</ul>`
        : '<div class="bpr-docs-note">No supporting documents were attached to this project.</div>';
}

// Asks for ONE Project's documents. `seq` is the render it was asked for: an answer that arrives after the modal was closed or
// re-opened for another request (or after its section was replaced) is dropped.
async function bpr_loadDocsFor(box, seq) {
    if (!box) return;
    const body  = box.querySelector('.bpr-docs-body');
    const stale = () => seq !== bpr_renderSeq || !box.isConnected;
    body.innerHTML = '<div class="bpr-docs-note">Loading documents…</div>';
    try {
        const params = new URLSearchParams({ budget_req_program_id: box.dataset.programId, project_code: box.dataset.projectCode });
        const res    = await fetch(`${BPR_ATTACHMENT_API}/list?${params}`, { credentials: 'same-origin' });
        const json   = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error((json && json.message) || 'HTTP ' + res.status);
        if (stale()) return;
        bpr_fillDocs(box, Array.isArray(json.data) ? json.data : []);
    } catch (e) {
        if (stale()) return;
        body.innerHTML = `<div class="bpr-docs-note bpr-docs-err">Could not load the supporting documents — ${bgr_esc(e.message)}<button type="button" class="bpr-docs-retry">Retry</button></div>`;
    }
}

// Every Project section of the freshly rendered modal, at once.
function bpr_loadDocs(seq) {
    document.querySelectorAll('#bpr-blocks .bpr-docs').forEach(box => { bpr_loadDocsFor(box, seq); });
}

// One Project section: its title line + its table + its Supporting Documents. The table is the part that gets the red outline
// when something required is missing.
function bpr_projectHtml(project, id, program) {
    return `<section class="bpr-block" data-block="${id}">
        <div class="bpr-proj"><span class="bpr-proj-k">Project</span> ${bpr_codeNameHtml(project.project_code, project.project_name)}</div>
        ${bpr_tableHtml(project)}
        ${bpr_docsHtml(program, project)}
    </section>`;
}

// One Program card: head (Program + Sub-Program), then a section per Project (or "No projects yet.")
function bpr_programHtml(program, pi) {
    const projects = program.projects ?? [];
    const row = (label, valueHtml) => `<div class="bpr-row"><span class="bpr-row-k">${label}</span><span class="bpr-row-v">${valueHtml}</span></div>`;
    const hasSub = program.sub_program_code || program.sub_program_name;
    const body = projects.length
        ? projects.map((project, j) => bpr_projectHtml(project, `b${pi}_${j}`, program)).join('')
        : '<div class="bpr-noproj">No projects yet.</div>';

    return `<section class="bpr-prog" data-prog="${pi}">
        <div class="bpr-prog-head">
            ${row('Program', bpr_codeNameHtml(program.program_code, program.program_name, true))}
            ${row('Sub-Program', hasSub ? bpr_codeNameHtml(program.sub_program_code, program.sub_program_name) : '<span class="bpr-muted">[No sub-program]</span>')}
        </div>
        ${body}
    </section>`;
}

// `programArg`: one program or an array of them
function bpr_render(requestContext, programArg) {
    const programs = Array.isArray(programArg) ? programArg : (programArg ? [programArg] : []);
    bpr_pickClose();   // a list left open over the old rows would point at inputs that are about to be replaced
    bpr_rowCount = programs.flatMap(p => p.projects ?? []).reduce((n, pj) => n + (pj.line_items ?? []).length, 0);

    $bpr('bpr-ref').textContent = requestContext.budget_reference_number ?? '';
    $bpr('bpr-meta').innerHTML  = bpr_metaHtml(requestContext, programs);
    $bpr('bpr-blocks').innerHTML = programs.length
        ? programs.map(bpr_programHtml).join('')
        : '<div class="bpr-noproj">No programs yet.</div>';

    bpr_updateTotals();   // the opening totals
    bpr_loadDocs(++bpr_renderSeq);   // the Projects' Supporting Documents fill in as their answers arrive
}

/* ── Messages / buttons ──────────────────────────────────────── */
function bpr_showErr(msg) {
    const el = $bpr('bpr-err');
    el.textContent = msg;
    el.hidden = false;
}
function bpr_hideErr() {
    $bpr('bpr-err').hidden = true;
}

// While a request is in flight both actions are locked; Endorse also stays
// disabled when there are no line items to endorse.
function bpr_updateButtons(busy) {
    $bpr('bpr-return').disabled  = busy;
    $bpr('bpr-endorse').disabled = busy || bpr_rowCount === 0;
}

/* ── Validation / collecting ─────────────────────────────────── */
// A Suggested Amount is optional — blank is fine (saved as 0.00) — but if something is typed it has to be a number, 0 or more.
function bpr_isValidAmount(value) {
    const v = bgr_unformatAmount(value).trim();
    return v === '' || (!isNaN(Number(v)) && Number(v) >= 0);
}

// Endorse needs a Type of Fund AND a Component on every line item, in every block.
// The first offender is focused (which scrolls it into view) and every block with a problem is outlined in red.
function bpr_validate() {
    let firstInvalid = null;
    const badBlocks = new Set();

    document.querySelectorAll('#bpr-blocks .bpr-field').forEach(field => {
        const invalid = field.matches('.bpr-suggested') ? !bpr_isValidAmount(field.value)
                                                        : !field.value;   // Type of Fund, Component
        field.classList.toggle('is-invalid', invalid);
        if (invalid) {
            badBlocks.add(field.closest('.bpr-block'));
            if (!firstInvalid) firstInvalid = field;
        }
    });

    // Account Code is required too (every line already has one and the box can't be emptied, so this is a safety net)
    document.querySelectorAll('#bpr-blocks .bpr-pick-input[data-pick="account"]').forEach(input => {
        const invalid = !bpr_pickHidden(input, bpr_pickCfg(input)).value;
        input.classList.toggle('is-invalid', invalid);
        if (invalid) {
            badBlocks.add(input.closest('.bpr-block'));
            if (!firstInvalid) firstInvalid = input;
        }
    });

    document.querySelectorAll('#bpr-blocks .bpr-block').forEach(block => {
        block.classList.toggle('has-error', badBlocks.has(block));
    });

    if (firstInvalid) {
        firstInvalid.focus();
        bpr_showErr(firstInvalid.matches('.bpr-suggested') ? 'Suggested Amount must be a number, 0 or more.'
                  : firstInvalid.matches('.bpr-pick-input') ? 'Account Code is required for every line item.'
                  : 'Type of Fund and Component are required for every line item.');
        return false;
    }
    return true;
}

// Rows of every block.
function bpr_collectItems() {
    return [...document.querySelectorAll('#bpr-blocks tr[data-li-id]')].map(tr => ({
        budget_line_item_id: Number(tr.dataset.liId),
        type_of_fund_id:     tr.querySelector('.bpr-fund').value || null,
        account_id:          tr.querySelector('.bpr-account').value,
        suggested_amount:   bgr_unformatAmount(tr.querySelector('.bpr-suggested').value).trim(),
        component_id:        tr.querySelector('.bpr-component').value || null,
    }));
}

/* ── Actions ─────────────────────────────────────────────────── */
async function bpr_endorse() {
    bpr_hideErr();
    if (!bpr_validate()) return;
    if (!confirm(`Endorse budget request ${bpr_ctx.budget_reference_number} to the Budget Head? The Type of Fund, Component, Account Code and Suggested Amount will be saved to its line items and its status will change to Endorsed.`)) return;

    await bpr_send('endorse', { items: bpr_collectItems() }, 'Failed to endorse the budget request.');
}

// Returning needs a reason: the REMARKS box above the footer is required.
async function bpr_return() {
    bpr_hideErr();
    const box = $bpr('bpr-remarks');
    const remarks = box.value.trim();
    if (remarks === '') {
        box.classList.add('is-invalid');
        box.focus();
        bpr_showErr('Remarks are required when returning a request — tell the requesting office what to fix.');
        return;
    }
    // The Budget Officer's Return always reaches the originating office (review.return_actor is null for this step).
    const returnTo = bpr_ctx.review?.return_actor ? `the ${bpr_ctx.review.return_actor}` : 'the originating office';
    if (!confirm(`Return budget request ${bpr_ctx.budget_reference_number} to ${returnTo}? Your remarks will be sent with it.`)) return;

    await bpr_send('return', { remarks }, 'Failed to return the budget request.');
}

async function bpr_send(action, body, failMessage) {
    bpr_updateButtons(true);
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BPR_BUDGET_REQ_API}/${action}/${encodeURIComponent(bpr_ctx.budget_reference_number)}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || failMessage);

        bgr_toast(json.message, 'success');
        bpr_closeModal();
        window.bgr_afterSave?.();
    } catch (e) {
        bpr_showErr(e.message);
    } finally {
        bpr_updateButtons(false);
        window.PagePreloader?.hide();
    }
}
</script>
