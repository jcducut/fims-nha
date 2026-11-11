<?php
/**
 * budget-preview-readone.php
 *
 * Read-only "Budget Preview" modal for a budget request — the read-only counterpart of the Budget Processing
 * modal (form/budget-processing-form.php): the same columns, but laid out per Project (the Processing modal has
 * since moved to Program cards with a meta line, Subtotals and a Grand Total; this one has not). Header: "Submission Ref. No. <ref>" with the
 * request's status badge. Then, for every Project of the request, one block:
 *   - an info card: Originating Unit / Region (the requesting office), Program, Sub-Program, Project
 *     (each with its code as a chip), and
 *   - that Project's line items as a table, as plain text (nothing to fill in):
 *       Type of Fund · Program Sources · Program Uses · Component · Account Code · Description (the account name,
 *       the line's particulars under it) · Amount (proposed) · Suggested Amount,
 *     with a Total row under both amount columns.
 * A Program that has no Projects yet gets its card and a "No projects yet." note.
 *
 * Program Sources are programs (budget_line_items.program_source_code = a fims.program.program_code) and Program Uses
 * are sub-programs (budget_line_items.program_uses = a fims.sub_program.sub_program_code). The preview looks the
 * names up once from GET /program/all and GET /sub_program/all (each cached) to show "Name" with the code under
 * it. If a lookup fails the preview still opens and shows the bare codes of that column (the other column is
 * not affected): a read-only view is not worth blocking on it.
 *
 * Trigger: bpv_openModal(requestContext, programs) — called from the list pages' "Budget Preview" row action:
 *   requestContext = { budget_reference_number, fiscal_yr, status_name, status_color, rc_id, rc_code, rc_name, can_decide }
 *                    (the rc_* fields are the requesting office, from the list row)
 *   programs       = ONE program or an ARRAY of them, each
 *                    { program_code, program_name, sub_program_code, sub_program_name, projects[] }
 *                    (each project has project_code, project_name, line_items[] carrying type_of_fund_name,
 *                    component_name, program_source_code and program_uses, straight from the list data)
 * Takes already-fetched request data — only the program / sub-program names are looked up.
 *
 * Footer (Return / Approve and the REMARKS box are hidden unless requestContext.can_decide is true — the list
 * page sets it for the Budget Head on an Endorsed request; without them the footer is just Close):
 *   Return  — needs REMARKS (the box above the footer; required): PUT /budget_req/return/{id} { remarks }:
 *             status -> "Returned", remarks saved on fims.budget_req.remarks.
 *   Approve — PUT /budget_req/approve/{id}: status -> "Approved" (only an Endorsed request can be approved; the
 *             server enforces that too). The remarks box is not used.
 * Status lives on the request header, so both apply to the whole request. After either one it calls
 * window.bgr_afterSave() so the list reloads.
 *
 * Included directly into budget-req-page.php / budget-prop-page.php (inside #bgr-root, so the .nha-root-scoped
 * modal styles apply). Depends on bgr_esc, bgr_escHtmlAttr, bgr_fmtMoney, bgr_statusBadge and bgr_toast from
 * form/budget-req-form.php, which must be included on the same page.
 */
$_bpv_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
/* ── Modal header: "Submission Ref. No. <ref>" + status ── */
.nha-root #bpv-overlay .bpv-ref {
    margin-left: 8px; font-family: var(--mono); font-weight: 600; letter-spacing: .02em;
    color: color-mix(in srgb, var(--afims-primary, #0035A9) 40%, #FFFFFF);
}
.nha-root #bpv-overlay .bpv-status { margin-left: 12px; vertical-align: 1px; }

/* ── One block per Project: info card + line-items table ── */
.nha-root .bpv-block { margin-bottom: 20px; }
.nha-root .bpv-info {
    display: grid; gap: 9px; padding: 15px 18px;
    background: var(--surf); border: 1px solid var(--bdr); border-radius: 12px;
}
.nha-root .bpv-info-row { display: grid; grid-template-columns: 190px minmax(0, 1fr); gap: 12px; align-items: baseline; font-size: 13px; }
.nha-root .bpv-info-k { font-weight: 600; color: var(--sec); }
.nha-root .bpv-info-v { color: var(--txt); overflow-wrap: anywhere; }
.nha-root .bpv-info-v strong { font-weight: 700; }
.nha-root .bpv-muted { color: var(--subtle); }
.nha-root .bpv-chip {
    display: inline-block; margin-right: 1px; padding: 2px 7px; border-radius: 4px; vertical-align: 1px;
    font: 600 11px/1.4 var(--mono); color: var(--blue-txt, #1D4ED8);
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--navy-bdr, #E6EBF6);
}

.nha-root .bpv-tbl-wrap { margin-top: 14px; border: 1px solid var(--bdr); border-radius: 12px; overflow: hidden; }
.nha-root .bpv-tbl td { padding: 10px 12px; vertical-align: middle; }
.nha-root .bpv-tbl td.bpv-nowrap { white-space: nowrap; }
.nha-root .bpv-tbl td.bpv-mono { font-family: var(--mono); font-size: 12px; }
.nha-root .bpv-tbl td.bpv-amt { white-space: nowrap; font-weight: 700; font-variant-numeric: tabular-nums; }
.nha-root .bpv-desc-sub { margin-top: 2px; font-size: 11.5px; color: var(--subtle); }
.nha-root .bpv-tbl tfoot td { padding: 11px 12px; font-weight: 700; background: var(--surf); border-top: 1px solid var(--bdr); }
.nha-root .bpv-empty, .nha-root .bpv-noproj { padding: 18px 12px; text-align: center; font-size: 12.5px; color: var(--subtle); }
.nha-root .bpv-noproj { margin-top: 14px; border: 1px solid var(--bdr); border-radius: 12px; font-style: italic; }

/* ── Remarks (only when the user can decide) ── */
.nha-root .bpv-remarks-label {
    display: block; margin: 4px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .04em;
    text-transform: uppercase; color: var(--sec);
}
.nha-root .bpv-remarks {
    display: block; width: 100%; min-height: 78px; padding: 10px 12px; box-sizing: border-box;
    font: inherit; font-size: 13px; color: var(--txt); resize: vertical;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr); border-radius: 8px;
}
.nha-root .bpv-remarks:focus { outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15, 32, 69, .08); }
.nha-root .bpv-remarks.is-invalid { border-color: var(--red, #DC3545); box-shadow: 0 0 0 3px rgba(220, 53, 69, .15); }
.nha-root .bpv-remarks-wrap[hidden] { display: none; }

.nha-root .bpv-err {
    margin-top: 12px; padding: 8px 10px; font-size: 12.5px; border-radius: var(--rs, 7px);
    background: var(--red-bg, #FAE1E3); color: var(--red-txt, #8F222D);
}
.nha-root .bpv-err[hidden] { display: none; }

/* ── Footer: Return (outlined) / Approve (filled) — or just Close ── */
.nha-root .nha-modal-foot .nha-btn[hidden], .nha-root .nha-modal-foot .nha-btn-ghost[hidden] { display: none; }
.nha-root .nha-modal-foot .nha-btn[disabled] { opacity: .6; cursor: not-allowed; }
.nha-root .nha-modal-foot .bpv-btn-return {
    background: var(--afims-surface, #FFFFFF); color: #B45309; border: 1px solid #F59E0B;
}
.nha-root .nha-modal-foot .bpv-btn-return:hover:not([disabled]) { background: #FFF8EB; }
.nha-root .nha-modal-foot .bpv-btn-approve {
    background: var(--afims-heading-fill, #000E3E); color: var(--afims-pure-white, #FFFFFF);
    border: 1px solid var(--afims-heading-fill, #000E3E);
}
.nha-root .nha-modal-foot .bpv-btn-approve:hover:not([disabled]) { opacity: .92; }

@media (max-width: 640px) {
    .nha-root .bpv-info-row { grid-template-columns: 1fr; gap: 2px; }
}
</style>

<!-- ── BUDGET PREVIEW MODAL ──────────────────────────────── -->
<div id="bpv-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="bpv-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="bpv-title">Submission Ref. No. <span class="bpv-ref" id="bpv-ref"></span><span class="bpv-status" id="bpv-status"></span></h3>
            <button type="button" class="nha-modal-close" id="bpv-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body" id="bpv-body">

            <!-- one block per Project: info card + its table of line items (read-only) -->
            <div id="bpv-blocks"></div>

            <div class="bpv-remarks-wrap" id="bpv-remarks-wrap" hidden>
                <label class="bpv-remarks-label" for="bpv-remarks">Remarks / Compliance Needed (required when returning)</label>
                <textarea class="bpv-remarks" id="bpv-remarks" rows="3" maxlength="2000"
                          placeholder="e.g. missing supporting documents, suggested proposal amount"></textarea>
            </div>

            <div class="bpv-err" id="bpv-err" hidden></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn-ghost" id="bpv-cancel" hidden>Close</button>
            <button type="button" class="nha-btn bpv-btn-return" id="bpv-return" hidden>Return</button>
            <button type="button" class="nha-btn bpv-btn-approve" id="bpv-approve" hidden>Approve</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET PREVIEW MODAL   prefix: bpv_
   Depends on bgr_esc / bgr_escHtmlAttr / bgr_fmtMoney / bgr_statusBadge /
   bgr_toast from form/budget-req-form.php, included on the same page.
   Takes already-fetched request data; only the program / sub-program names (for the
   Program Sources / Program Uses columns) are looked up, once. Return / Approve call the API.
═══════════════════════════════════════════════════════════════ */

const BPV_BUDGET_REQ_API = '<?php echo $_bpv_api; ?>' + '/budget_req';
const BPV_PROGRAM_API    = '<?php echo $_bpv_api; ?>' + '/program/all';       // names of the program sources
const BPV_SUBPROGRAM_API = '<?php echo $_bpv_api; ?>' + '/sub_program/all';   // names of the program uses

let BPV_SOURCES = null;   // [{ program_code, program_name }]            — null until loaded (a failed load is NOT cached)
let BPV_USES = null;      // [{ sub_program_code, sub_program_name }]    — same
let bpv_ctx = null;       // requestContext of the modal that is currently open

const $bpv = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bpv('bpv-close')?.addEventListener('click', bpv_closeModal);
    $bpv('bpv-cancel')?.addEventListener('click', bpv_closeModal);
    $bpv('bpv-return')?.addEventListener('click', () => bpv_decide('return'));
    $bpv('bpv-approve')?.addEventListener('click', () => bpv_decide('approve'));

    $bpv('bpv-remarks')?.addEventListener('input', e => e.target.classList.remove('is-invalid'));
});

// One name list. Never throws: null on any failure.
async function bpv_fetchList(url) {
    try {
        const res  = await fetch(url, { credentials: 'same-origin' });
        const json = res.ok ? await res.json() : null;
        if (json && json.success) return json.data ?? [];
    } catch (e) { /* fall through */ }
    return null;
}

// The lists that turn a program_source_code / program_uses into a name. Each is fetched once and cached. Never
// throws: a list that fails just shows the bare codes this time (the other list is unaffected) and is tried
// again the next time the preview opens.
async function bpv_loadLookups() {
    const [sources, uses] = await Promise.all([
        BPV_SOURCES ?? bpv_fetchList(BPV_PROGRAM_API),
        BPV_USES    ?? bpv_fetchList(BPV_SUBPROGRAM_API),
    ]);
    if (sources) BPV_SOURCES = sources;
    if (uses)    BPV_USES    = uses;
    return { sources: sources ?? [], uses: uses ?? [] };
}

// `programs` is one program or an array of them (see the header comment).
async function bpv_openModal(requestContext, programs) {
    window.PagePreloader?.show();
    let lookups;
    try {
        lookups = await bpv_loadLookups();
    } finally {
        window.PagePreloader?.hide();
    }

    bpv_ctx = requestContext;
    bpv_render(requestContext, programs, lookups);

    // Return / Approve (and the remarks they need) only appear when the list page says this user may decide;
    // otherwise the footer is just Close.
    const canDecide = !!requestContext.can_decide;
    $bpv('bpv-return').hidden       = !canDecide;
    $bpv('bpv-approve').hidden      = !canDecide;
    $bpv('bpv-cancel').hidden       = canDecide;
    $bpv('bpv-remarks-wrap').hidden = !canDecide;
    $bpv('bpv-remarks').value = '';
    $bpv('bpv-remarks').classList.remove('is-invalid');
    bpv_setBusy(false);
    $bpv('bpv-err').hidden = true;

    $bpv('bpv-overlay').classList.add('open');
}

function bpv_setBusy(busy) {
    $bpv('bpv-return').disabled  = busy;
    $bpv('bpv-approve').disabled = busy;
}

function bpv_showErr(msg) {
    $bpv('bpv-err').textContent = msg;
    $bpv('bpv-err').hidden = false;
}

async function bpv_decide(action) {
    $bpv('bpv-err').hidden = true;
    const init = { method: 'PUT', credentials: 'same-origin' };

    if (action === 'approve') {
        if (!confirm(`Approve budget request ${bpv_ctx.budget_reference_number}? Its status will change to Approved.`)) return;
    } else {
        // Returning needs a reason: the REMARKS box above the footer is required.
        const box = $bpv('bpv-remarks');
        const remarks = box.value.trim();
        if (remarks === '') {
            box.classList.add('is-invalid');
            box.focus();
            bpv_showErr('Remarks are required when returning a request — tell the requesting office what to fix.');
            return;
        }
        if (!confirm(`Return budget request ${bpv_ctx.budget_reference_number} to the requesting office? Your remarks will be sent with it.`)) return;
        init.headers = { 'Content-Type': 'application/json' };
        init.body    = JSON.stringify({ remarks });
    }

    bpv_setBusy(true);
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BPV_BUDGET_REQ_API}/${action}/${encodeURIComponent(bpv_ctx.budget_reference_number)}`, init);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || `Failed to ${action} the budget request.`);

        bgr_toast(json.message, 'success');
        bpv_closeModal();
        window.bgr_afterSave?.();
    } catch (e) {
        bpv_showErr(e.message);
    } finally {
        bpv_setBusy(false);
        window.PagePreloader?.hide();
    }
}

function bpv_closeModal() {
    $bpv('bpv-overlay').classList.remove('open');
}

/* ── Table ───────────────────────────────────────────────────── */
// A line item that hasn't been through Budget Processing has no Type of Fund / Component / Program Source / Program Use yet.
function bpv_value(text) {
    return text ? bgr_esc(text) : '<span class="nha-td-sub">—</span>';
}

// Program Sources (programs) / Program Uses (sub-programs): the item's name with its code under it; the bare code
// if it can't be found in the list; a dash when none was chosen.
function bpv_codeCellHtml(code, items, codeKey, nameKey) {
    if (code === null || code === undefined || code === '') return '<span class="nha-td-sub">—</span>';
    const p = items.find(x => String(x[codeKey]) === String(code));
    return p
        ? `<div>${bgr_esc(p[nameKey])}</div><div class="bpv-desc-sub">${bgr_esc(p[codeKey])}</div>`
        : `<div>${bgr_esc(code)}</div>`;
}

// Column order matches Budget Processing: Type of Fund · Program Sources · Program Uses · Component · Account Code · Description · Amount · Suggested Amount
const BPV_THEAD = `<thead><tr>
    <th>Type of Fund</th>
    <th>Program Sources</th>
    <th>Program Uses</th>
    <th>Component</th>
    <th>Account Code</th>
    <th>Description</th>
    <th class="text-right">Amount</th>
    <th class="text-right">Suggested Amount</th>
</tr></thead>`;

function bpv_rowHtml(li, lk) {
    return `<tr>
        <td>${bpv_value(li.type_of_fund_name)}</td>
        <td>${bpv_codeCellHtml(li.program_source_code, lk.sources, 'program_code', 'program_name')}</td>
        <td>${bpv_codeCellHtml(li.program_uses, lk.uses, 'sub_program_code', 'sub_program_name')}</td>
        <td>${bpv_value(li.component_name)}</td>
        <td class="bpv-nowrap bpv-mono">${bgr_esc(li.account_code)}</td>
        <td><div>${bgr_esc(li.account_title)}</div>${li.particulars ? `<div class="bpv-desc-sub">${bgr_esc(li.particulars)}</div>` : ''}</td>
        <td class="text-right bpv-amt">${bgr_fmtMoney(li.proposed_amount)}</td>
        <td class="text-right bpv-amt">${bgr_fmtMoney(li.suggested_amount)}</td>
    </tr>`;
}

function bpv_tableHtml(project, lk) {
    const lineItems = project.line_items ?? [];
    const sum = key => lineItems.reduce((s, li) => s + (Number(li[key]) || 0), 0);
    const rowsHtml = lineItems.length
        ? lineItems.map(li => bpv_rowHtml(li, lk)).join('')
        : '<tr><td colspan="8"><div class="bpv-empty">No line items have been added under this project.</div></td></tr>';

    return `<div class="bpv-tbl-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl bpv-tbl">${BPV_THEAD}<tbody>${rowsHtml}</tbody>
                <tfoot><tr><td colspan="6" class="text-right">Total</td><td class="text-right bpv-total-p">${bgr_fmtMoney(sum('proposed_amount'))}</td><td class="text-right bpv-total-s">${bgr_fmtMoney(sum('suggested_amount'))}</td></tr></tfoot>
            </table>
        </div>
    </div>`;
}

/* ── Info card + block ───────────────────────────────────────── */
// "<chip: code> name" — a dash when there is neither
function bpv_codeNameHtml(code, name) {
    if (!code && !name) return '<span class="bpv-muted">—</span>';
    return (code ? `<span class="bpv-chip">${bgr_esc(code)}</span> ` : '') + bgr_esc(name || '');
}

// The requesting office: its id in bold (e.g. "R07"), its name after it
function bpv_unitHtml(ctx) {
    const id = ctx.rc_id ? String(ctx.rc_id) : (ctx.rc_code ? String(ctx.rc_code) : '');
    if (!id && !ctx.rc_name) return '<span class="bpv-muted">—</span>';
    return `<strong>${bgr_esc(id || ctx.rc_name)}</strong>` + (id && ctx.rc_name ? ` <span class="bpv-muted">${bgr_esc(ctx.rc_name)}</span>` : '');
}

function bpv_cardHtml(ctx, program, project) {
    const row = (label, valueHtml) => `<div class="bpv-info-row"><span class="bpv-info-k">${label}</span><span class="bpv-info-v">${valueHtml}</span></div>`;
    return `<div class="bpv-info">
        ${row('Originating Unit / Region', bpv_unitHtml(ctx))}
        ${row('Program', bpv_codeNameHtml(program.program_code, program.program_name))}
        ${row('Sub-Program', bpv_codeNameHtml(program.sub_program_code, program.sub_program_name))}
        ${row('Project', project ? bpv_codeNameHtml(project.project_code, project.project_name) : '<span class="bpv-muted">—</span>')}
    </div>`;
}

// One block = the info card + (the Project's table | "No projects yet." when the Program has no Project)
function bpv_blockHtml(ctx, program, project, lk) {
    return `<section class="bpv-block">
        ${bpv_cardHtml(ctx, program, project)}
        ${project ? bpv_tableHtml(project, lk) : '<div class="bpv-noproj">No projects yet.</div>'}
    </section>`;
}

// `programArg`: one program or an array of them
function bpv_render(requestContext, programArg, lookups) {
    const programs = Array.isArray(programArg) ? programArg : (programArg ? [programArg] : []);

    $bpv('bpv-ref').textContent = requestContext.budget_reference_number ?? '';
    $bpv('bpv-status').innerHTML = requestContext.status_name ? bgr_statusBadge(requestContext.status_name, requestContext.status_color) : '';

    const blocks = [];
    programs.forEach(program => {
        const projects = program.projects ?? [];
        if (!projects.length) blocks.push(bpv_blockHtml(requestContext, program, null, lookups));
        else projects.forEach(project => blocks.push(bpv_blockHtml(requestContext, program, project, lookups)));
    });
    $bpv('bpv-blocks').innerHTML = blocks.length ? blocks.join('') : '<div class="bpv-noproj">No programs yet.</div>';
}
</script>
