<?php
/**
 * budget-preview-readone.php
 *
 * Read-only "Budget Preview" modal for one Program (fims.budget_req_program)
 * within a budget request — the read-only counterpart of the Budget
 * Processing modal (form/budget-processing-form.php). Same layout: the
 * request's review data on top, then the Program's budget line items in
 * TABS — one tab per Project, each tab its own table of that Project's line
 * items, including their Type of Fund, Suggestion Amount and Component —
 * shown as plain text instead of inputs, with nothing to fill in or save.
 *
 * Trigger: bpv_openModal(requestContext, program) — called from the list
 * page's "Budget Preview" row action, where:
 *   requestContext = { budget_reference_number, fiscal_yr, status_name, status_color }
 *   program        = { program_code, program_name, sub_program_name, projects[] }
 *                    (each project has line_items[] carrying type_of_fund_name
 *                    and component_name, straight from the list data)
 * Takes already-fetched data — the preview itself fetches nothing.
 *
 * Footer actions (Return / Approve are hidden unless requestContext.can_decide
 * is true — the list page sets it for the Budget Head on an Endorsed request):
 *   Close   — dismisses the modal.
 *   Return  — PUT /budget_req/return/{id}:  status -> "Returned".
 *   Approve — PUT /budget_req/approve/{id}: status -> "Approved" (only an
 *             Endorsed request can be approved; the server enforces that too).
 * Status lives on the request header, so both apply to the whole request
 * (fiscal year), not just the Program shown here. After either one it calls
 * window.bgr_afterSave() so the list reloads.
 *
 * Included directly into budget-req-page.php (inside #bgr-root, so the
 * .nha-root-scoped modal styles apply). Depends on bgr_esc, bgr_escHtmlAttr,
 * bgr_fmtMoney, bgr_statusBadge and bgr_toast from form/budget-req-form.php,
 * which must be included on the same page.
 */
$_bpv_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
.nha-root .bpv-label {
    margin: 18px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--sec, #62657E);
}
.nha-root .bpv-label:first-of-type { margin-top: 0; }

.nha-root .bpv-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.nha-root .bpv-cell {
    padding: 8px 10px; background: var(--surf, #F4F7FA); border: 1px solid var(--bdr, #E4ECF2);
    border-radius: var(--rs, 7px); min-width: 0;
}
.nha-root .bpv-cell-k { font-size: 11px; color: var(--subtle, #62657E); }
.nha-root .bpv-cell-v { margin-top: 2px; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }

/* One tab per project — builds on the shared .nha-tabs / .nha-tab styles */
.nha-root .bpv-tabs { overflow-x: auto; margin-bottom: 12px; }
.nha-root .bpv-tabs .nha-tab { flex: 1 0 auto; }
.nha-root .bpv-tabs .nha-tab-cnt { background: var(--muted, #A9AAB8); }
.nha-root .bpv-panel[hidden] { display: none; }
.nha-root .bpv-panel-hd {
    display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    margin-bottom: 8px;
}
.nha-root .bpv-panel-title { font-size: 13px; font-weight: 700; }
.nha-root .bpv-panel-meta { font-size: 12px; color: var(--subtle, #62657E); }

.nha-root .bpv-tbl-wrap { border: 1px solid var(--bdr, #E4ECF2); border-radius: var(--rs, 7px); overflow: hidden; }
.nha-root .bpv-tbl td { vertical-align: middle; }
.nha-root .bpv-tbl td.bpv-nowrap { white-space: nowrap; }
.nha-root .bpv-tbl .bpv-col-particulars { min-width: 160px; }
.nha-root .bpv-empty { padding: 18px 12px; text-align: center; font-size: 12.5px; color: var(--subtle, #62657E); }

.nha-root .bpv-err {
    margin-top: 12px; padding: 8px 10px; font-size: 12.5px; border-radius: var(--rs, 7px);
    background: var(--red-bg, #FAE1E3); color: var(--red-txt, #8F222D);
}
.nha-root .bpv-err[hidden] { display: none; }

.nha-root .nha-modal-foot .nha-btn[hidden] { display: none; }
.nha-root .nha-modal-foot .nha-btn[disabled] { opacity: .6; cursor: not-allowed; }

@media (max-width: 640px) {
    .nha-root .bpv-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ── BUDGET PREVIEW MODAL ──────────────────────────────── -->
<div id="bpv-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="bpv-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="bpv-title">Budget Preview</h3>
            <button type="button" class="nha-modal-close" id="bpv-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body">

            <!-- review data: the budget request this Program belongs to -->
            <div class="bpv-label">Budget Request</div>
            <div class="bpv-grid">
                <div class="bpv-cell"><div class="bpv-cell-k">Fiscal Year</div><div class="bpv-cell-v" id="bpv-fiscal-yr">—</div></div>
                <div class="bpv-cell"><div class="bpv-cell-k">Program</div><div class="bpv-cell-v" id="bpv-program">—</div></div>
                <div class="bpv-cell"><div class="bpv-cell-k">Sub-Program</div><div class="bpv-cell-v" id="bpv-sub-program">—</div></div>
                <div class="bpv-cell"><div class="bpv-cell-k">Status</div><div class="bpv-cell-v" id="bpv-status">—</div></div>
                <div class="bpv-cell"><div class="bpv-cell-k"># Line Items</div><div class="bpv-cell-v" id="bpv-li-count">0</div></div>
                <div class="bpv-cell"><div class="bpv-cell-k">Total Proposed Amount</div><div class="bpv-cell-v" id="bpv-total">—</div></div>
            </div>

            <!-- the budget line items: one tab per project, each with its own table (read-only) -->
            <div class="bpv-label">Budget Line Items</div>
            <div id="bpv-line-items">
                <div class="nha-tabs bpv-tabs" id="bpv-tabs" role="tablist" aria-label="Projects"></div>
                <div id="bpv-panels"></div>
            </div>
            <div class="bpv-tbl-wrap" id="bpv-empty" hidden>
                <div class="bpv-empty">No projects have been added to this program yet.</div>
            </div>

            <div class="bpv-err" id="bpv-err" hidden></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn-ghost" id="bpv-cancel">Close</button>
            <button type="button" class="nha-btn nha-btn-danger" id="bpv-return" hidden>Return</button>
            <button type="button" class="nha-btn nha-btn-success" id="bpv-approve" hidden>Approve</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET PREVIEW MODAL   prefix: bpv_
   Depends on bgr_esc / bgr_escHtmlAttr / bgr_fmtMoney / bgr_statusBadge /
   bgr_toast from form/budget-req-form.php, included on the same page.
   Takes already-fetched data — the preview itself fetches nothing; only
   Return / Approve call the API.
═══════════════════════════════════════════════════════════════ */

const BPV_BUDGET_REQ_API = '<?php echo $_bpv_api; ?>' + '/budget_req';

let bpv_ctx = null;   // requestContext of the modal that is currently open

const $bpv = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bpv('bpv-close')?.addEventListener('click', bpv_closeModal);
    $bpv('bpv-cancel')?.addEventListener('click', bpv_closeModal);
    $bpv('bpv-return')?.addEventListener('click', () => bpv_decide('return'));
    $bpv('bpv-approve')?.addEventListener('click', () => bpv_decide('approve'));

    $bpv('bpv-tabs')?.addEventListener('click', e => {
        const tab = e.target.closest('.bpv-tab');
        if (tab) bpv_selectTab(tab.dataset.tab);
    });
});

function bpv_openModal(requestContext, program) {
    bpv_ctx = requestContext;
    bpv_render(requestContext, program);

    // Return / Approve only appear when the list page says this user may decide.
    const canDecide = !!requestContext.can_decide;
    $bpv('bpv-return').hidden  = !canDecide;
    $bpv('bpv-approve').hidden = !canDecide;
    bpv_setBusy(false);
    $bpv('bpv-err').hidden = true;

    $bpv('bpv-overlay').classList.add('open');
}

function bpv_setBusy(busy) {
    $bpv('bpv-return').disabled  = busy;
    $bpv('bpv-approve').disabled = busy;
}

async function bpv_decide(action) {
    const msg = action === 'approve'
        ? `Approve the fiscal year ${bpv_ctx.fiscal_yr} budget request? Its status will change to Approved.`
        : `Return the fiscal year ${bpv_ctx.fiscal_yr} budget request? Its status will change to Returned.`;
    if (!confirm(msg)) return;

    $bpv('bpv-err').hidden = true;
    bpv_setBusy(true);
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BPV_BUDGET_REQ_API}/${action}/${bpv_ctx.budget_reference_number}`, {
            method: 'PUT',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || `Failed to ${action} the budget request.`);

        bgr_toast(json.message, 'success');
        bpv_closeModal();
        window.bgr_afterSave?.();
    } catch (e) {
        $bpv('bpv-err').textContent = e.message;
        $bpv('bpv-err').hidden = false;
    } finally {
        bpv_setBusy(false);
        window.PagePreloader?.hide();
    }
}

function bpv_closeModal() {
    $bpv('bpv-overlay').classList.remove('open');
}

// A line item that hasn't been through Budget Processing has no Type of Fund / Component yet.
function bpv_value(text) {
    return text ? bgr_esc(text) : '<span class="nha-td-sub">—</span>';
}

function bpv_render(requestContext, program) {
    $bpv('bpv-fiscal-yr').textContent   = requestContext.fiscal_yr ?? '—';
    $bpv('bpv-program').textContent     = `${program.program_code} — ${program.program_name}`;
    $bpv('bpv-sub-program').textContent = program.sub_program_name || '—';
    $bpv('bpv-status').innerHTML        = bgr_statusBadge(requestContext.status_name, requestContext.status_color);

    const projects = program.projects ?? [];
    const allLineItems = projects.flatMap(p => p.line_items ?? []);
    $bpv('bpv-li-count').textContent = allLineItems.length;
    $bpv('bpv-total').textContent    = bgr_fmtMoney(allLineItems.reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0));

    // One tab + one panel (with its own table) per project; the tab shows its line-item count.
    $bpv('bpv-tabs').innerHTML = projects.map((p, i) =>
        `<button type="button" class="nha-tab bpv-tab" role="tab" id="bpv-tab-${i}" data-tab="${i}" aria-controls="bpv-panel-${i}" title="${bgr_escHtmlAttr(p.project_name)}">${bgr_esc(p.project_code)}<span class="nha-tab-cnt">${(p.line_items ?? []).length}</span></button>`
    ).join('');
    $bpv('bpv-panels').innerHTML = projects.map(bpv_panelHtml).join('');

    $bpv('bpv-line-items').hidden = projects.length === 0;
    $bpv('bpv-empty').hidden      = projects.length > 0;
    if (projects.length) bpv_selectTab(0);
}

// Column order matches Budget Processing: Type of Fund · Account Code · Account Name · Particulars · Proposed Amount · Suggestion Amount · Component
// (no Project column — the tab is the project).
const BPV_THEAD = `<thead><tr>
    <th>Type of Fund</th>
    <th>Account Code</th>
    <th>Account Name</th>
    <th class="bpv-col-particulars">Particulars</th>
    <th class="text-right">Proposed Amount</th>
    <th class="text-right">Suggestion Amount</th>
    <th>Component</th>
</tr></thead>`;

function bpv_rowHtml(li) {
    return `<tr>
        <td>${bpv_value(li.type_of_fund_name)}</td>
        <td class="bpv-nowrap">${bgr_esc(li.account_code)}</td>
        <td>${bgr_esc(li.account_title)}</td>
        <td>${bgr_esc(li.particulars)}</td>
        <td class="amt-cell">${bgr_fmtMoney(li.proposed_amount)}</td>
        <td class="amt-cell">${bgr_fmtMoney(li.suggestion_amount)}</td>
        <td>${bpv_value(li.component_name)}</td>
    </tr>`;
}

function bpv_panelHtml(project, idx) {
    const lineItems = project.line_items ?? [];
    const subtotal  = lineItems.reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0);
    const rowsHtml  = lineItems.length
        ? lineItems.map(bpv_rowHtml).join('')
        : '<tr><td colspan="7"><div class="bpv-empty">No line items have been added under this project.</div></td></tr>';

    return `<div class="bpv-panel" role="tabpanel" id="bpv-panel-${idx}" data-panel="${idx}" aria-labelledby="bpv-tab-${idx}" hidden>
        <div class="bpv-panel-hd">
            <div class="bpv-panel-title">${bgr_esc(project.project_code)}${project.project_name ? ' — ' + bgr_esc(project.project_name) : ''}</div>
            <div class="bpv-panel-meta">${lineItems.length} line item(s) · ${bgr_fmtMoney(subtotal)}</div>
        </div>
        <div class="bpv-tbl-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl bpv-tbl">${BPV_THEAD}<tbody>${rowsHtml}</tbody></table>
            </div>
        </div>
    </div>`;
}

function bpv_selectTab(idx) {
    const key = String(idx);
    document.querySelectorAll('#bpv-tabs .bpv-tab').forEach(tab => {
        const on = tab.dataset.tab === key;
        tab.classList.toggle('active', on);
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('#bpv-panels .bpv-panel').forEach(panel => {
        panel.hidden = panel.dataset.panel !== key;
    });
}
</script>
