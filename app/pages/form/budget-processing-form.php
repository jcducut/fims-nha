<?php
/**
 * budget-processing-form.php
 *
 * "Budget Processing" modal for one Program (fims.budget_req_program) within
 * a budget request. Shows the request's review data on top, then the
 * Program's budget line items organised in TABS — one tab per Project, each
 * tab holding its own table of that Project's line items. Every row has two
 * dropdowns — Type of Fund (fims.type_of_fund) and Component
 * (fims.component_type) — and a Suggestion Amount input
 * (fims.budget_line_items.suggestion_amount).
 *
 * Footer actions:
 *   Close   — dismisses the modal, nothing is saved.
 *   Return  — PUT /budget_req/return/{id}: sets the request's status to
 *             "Returned"; the line items are left untouched.
 *   Endorse — PUT /budget_req/endorse/{id}: Type of Fund and Component are
 *             REQUIRED on every line item of EVERY tab (Suggestion Amount is
 *             optional, blank = 0.00); saves them to fims.budget_line_items
 *             and sets the request's status to "Endorsed" (one transaction).
 *             If a required field is missing on another tab, that tab is
 *             opened and marked with a red dot.
 * Status lives on the request header, so Return/Endorse apply to the whole
 * request (fiscal year), not just the Program shown here.
 *
 * Trigger: bpr_openModal(requestContext, program) — called from the list
 * page's "Budget Processing" row action, where:
 *   requestContext = { budget_reference_number, fiscal_yr, status_name, status_color }
 *   program        = { program_code, program_name, sub_program_name, projects[] }
 *                    (each project has line_items[], straight from the list data)
 * The line items are NOT re-fetched; only the two dropdown option lists are.
 *
 * Included directly into budget-req-page.php (inside #bgr-root, so the
 * .nha-root-scoped modal styles apply). Depends on bgr_esc, bgr_escHtmlAttr,
 * bgr_fmtMoney, bgr_fmtAmount, bgr_fmtAmountInitial, bgr_unformatAmount,
 * bgr_statusBadge and bgr_toast from form/budget-req-form.php, which must be
 * included on the same page. After Return/Endorse it calls
 * window.bgr_afterSave() so the list reloads.
 */
$_bpr_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
.nha-root .bpr-label {
    margin: 18px 0 8px; font-size: 11px; font-weight: 700; letter-spacing: .06em;
    text-transform: uppercase; color: var(--sec, #62657E);
}
.nha-root .bpr-label:first-of-type { margin-top: 0; }

.nha-root .bpr-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.nha-root .bpr-cell {
    padding: 8px 10px; background: var(--surf, #F4F7FA); border: 1px solid var(--bdr, #E4ECF2);
    border-radius: var(--rs, 7px); min-width: 0;
}
.nha-root .bpr-cell-k { font-size: 11px; color: var(--subtle, #62657E); }
.nha-root .bpr-cell-v { margin-top: 2px; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }

/* One tab per project — builds on the shared .nha-tabs / .nha-tab styles */
.nha-root .bpr-tabs { overflow-x: auto; margin-bottom: 12px; }
.nha-root .bpr-tabs .nha-tab { flex: 1 0 auto; }
.nha-root .bpr-tabs .nha-tab-cnt { background: var(--muted, #A9AAB8); }
.nha-root .bpr-tab.has-error::after {
    content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--red, #DC3545); flex-shrink: 0;
}
.nha-root .bpr-panel[hidden] { display: none; }
.nha-root .bpr-panel-hd {
    display: flex; align-items: baseline; justify-content: space-between; gap: 12px; flex-wrap: wrap;
    margin-bottom: 8px;
}
.nha-root .bpr-panel-title { font-size: 13px; font-weight: 700; }
.nha-root .bpr-panel-meta { font-size: 12px; color: var(--subtle, #62657E); }

.nha-root .bpr-tbl-wrap { border: 1px solid var(--bdr, #E4ECF2); border-radius: var(--rs, 7px); overflow: hidden; }
.nha-root .bpr-tbl td { vertical-align: middle; }
.nha-root .bpr-tbl td.bpr-nowrap { white-space: nowrap; }
.nha-root .bpr-tbl .bpr-col-select { min-width: 170px; }
.nha-root .bpr-tbl .bpr-col-particulars { min-width: 160px; }
.nha-root .bpr-tbl .bpr-col-amount { min-width: 150px; }
.nha-root .bpr-tbl .bpr-suggestion { text-align: right; font-family: var(--mono); }
.nha-root .bpr-req { color: #FF8A8A; margin-left: 2px; }
.nha-root .bpr-empty { padding: 18px 12px; text-align: center; font-size: 12.5px; color: var(--subtle, #62657E); }

/* a required dropdown left empty (or an invalid amount) when Endorse is clicked */
.nha-root .bpr-field.is-invalid {
    border-color: var(--red, #DC3545);
    box-shadow: 0 0 0 3px rgba(220, 53, 69, .15);
}

.nha-root .bpr-err {
    margin-top: 12px; padding: 8px 10px; font-size: 12.5px; border-radius: var(--rs, 7px);
    background: var(--red-bg, #FAE1E3); color: var(--red-txt, #8F222D);
}
.nha-root .bpr-err[hidden] { display: none; }

.nha-root .nha-modal-foot .nha-btn[disabled] { opacity: .6; cursor: not-allowed; }

@media (max-width: 640px) {
    .nha-root .bpr-grid { grid-template-columns: 1fr; }
}
</style>

<!-- ── BUDGET PROCESSING MODAL ───────────────────────────── -->
<div id="bpr-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-xl" role="dialog" aria-modal="true" aria-labelledby="bpr-title">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="bpr-title">Budget Processing</h3>
            <button type="button" class="nha-modal-close" id="bpr-close" aria-label="Close">&times;</button>
        </div>
        <div class="nha-modal-body">

            <!-- review data: the budget request this Program belongs to -->
            <div class="bpr-label">Budget Request</div>
            <div class="bpr-grid">
                <div class="bpr-cell"><div class="bpr-cell-k">Fiscal Year</div><div class="bpr-cell-v" id="bpr-fiscal-yr">—</div></div>
                <div class="bpr-cell"><div class="bpr-cell-k">Program</div><div class="bpr-cell-v" id="bpr-program">—</div></div>
                <div class="bpr-cell"><div class="bpr-cell-k">Sub-Program</div><div class="bpr-cell-v" id="bpr-sub-program">—</div></div>
                <div class="bpr-cell"><div class="bpr-cell-k">Status</div><div class="bpr-cell-v" id="bpr-status">—</div></div>
                <div class="bpr-cell"><div class="bpr-cell-k"># Line Items</div><div class="bpr-cell-v" id="bpr-li-count">0</div></div>
                <div class="bpr-cell"><div class="bpr-cell-k">Total Proposed Amount</div><div class="bpr-cell-v" id="bpr-total">—</div></div>
            </div>

            <!-- the budget line items: one tab per project, each with its own table
                 (Type of Fund + Component are required to Endorse) -->
            <div class="bpr-label">Budget Line Items</div>
            <div id="bpr-line-items">
                <div class="nha-tabs bpr-tabs" id="bpr-tabs" role="tablist" aria-label="Projects"></div>
                <div id="bpr-panels"></div>
            </div>
            <div class="bpr-tbl-wrap" id="bpr-empty" hidden>
                <div class="bpr-empty">No projects have been added to this program yet.</div>
            </div>

            <div class="bpr-err" id="bpr-err" hidden></div>
        </div>
        <div class="nha-modal-foot">
            <button type="button" class="nha-btn-ghost" id="bpr-cancel">Close</button>
            <button type="button" class="nha-btn nha-btn-danger" id="bpr-return">Return</button>
            <button type="button" class="nha-btn nha-btn-success" id="bpr-endorse">Endorse</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET PROCESSING MODAL   prefix: bpr_
   Depends on bgr_esc / bgr_escHtmlAttr / bgr_fmtMoney / bgr_fmtAmount /
   bgr_fmtAmountInitial / bgr_unformatAmount / bgr_statusBadge / bgr_toast
   from form/budget-req-form.php, included on the same page. The line items
   come from the already-fetched list data; only the Type of Fund / Component
   option lists are fetched (once, then cached).
═══════════════════════════════════════════════════════════════ */

const BPR_TYPE_OF_FUND_API  = '<?php echo $_bpr_api; ?>' + '/type_of_fund/all';
const BPR_COMPONENT_API     = '<?php echo $_bpr_api; ?>' + '/component_type/all';
const BPR_BUDGET_REQ_API    = '<?php echo $_bpr_api; ?>' + '/budget_req';

let BPR_FUNDS = null;       // [{ type_of_fund_id, name }]   — null until loaded
let BPR_COMPONENTS = null;  // [{ component_type_id, name }] — null until loaded

let bpr_ctx = null;         // requestContext of the modal that is currently open
let bpr_rowCount = 0;       // number of line-item rows rendered, across all tabs

const $bpr = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bpr('bpr-close')?.addEventListener('click', bpr_closeModal);
    $bpr('bpr-cancel')?.addEventListener('click', bpr_closeModal);
    $bpr('bpr-return')?.addEventListener('click', bpr_return);
    $bpr('bpr-endorse')?.addEventListener('click', bpr_endorse);

    $bpr('bpr-tabs')?.addEventListener('click', e => {
        const tab = e.target.closest('.bpr-tab');
        if (tab) bpr_selectTab(tab.dataset.tab);
    });

    // A field that was flagged as missing stops looking invalid once filled;
    // its tab loses the red dot once nothing is left to fix in that tab.
    $bpr('bpr-panels')?.addEventListener('input', e => {
        if (!e.target.matches('.bpr-field')) return;
        e.target.classList.remove('is-invalid');

        const panel = e.target.closest('.bpr-panel');
        if (panel && !panel.querySelector('.is-invalid')) {
            $bpr(`bpr-tab-${panel.dataset.panel}`)?.classList.remove('has-error');
        }
    });
});

async function bpr_loadOptions() {
    if (BPR_FUNDS && BPR_COMPONENTS) return;

    const [fundsRes, componentsRes] = await Promise.all([
        fetch(BPR_TYPE_OF_FUND_API, { credentials: 'same-origin' }),
        fetch(BPR_COMPONENT_API,    { credentials: 'same-origin' }),
    ]);
    if (!fundsRes.ok || !componentsRes.ok) throw new Error('Failed to load the Type of Fund / Component lists.');

    const funds      = await fundsRes.json();
    const components = await componentsRes.json();
    if (!funds.success)      throw new Error(funds.message || 'Failed to load types of fund.');
    if (!components.success) throw new Error(components.message || 'Failed to load components.');

    BPR_FUNDS      = funds.data ?? [];
    BPR_COMPONENTS = components.data ?? [];
}

async function bpr_openModal(requestContext, program) {
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
    bpr_render(requestContext, program);
    bpr_hideErr();
    bpr_updateButtons(false);
    $bpr('bpr-overlay').classList.add('open');
}

function bpr_closeModal() {
    $bpr('bpr-overlay').classList.remove('open');
}

function bpr_optionsHtml(options, idKey, selectedId) {
    const sel = selectedId === null || selectedId === undefined ? '' : String(selectedId);
    return '<option value="">— Select —</option>' + options.map(o =>
        `<option value="${Number(o[idKey])}"${String(o[idKey]) === sel ? ' selected' : ''}>${bgr_esc(o.name)}</option>`
    ).join('');
}

// Column order: Type of Fund · Account Code · Account Name · Particulars · Proposed Amount · Suggestion Amount · Component
// (no Project column — the tab is the project).
const BPR_THEAD = `<thead><tr>
    <th class="bpr-col-select">Type of Fund<span class="bpr-req">*</span></th>
    <th>Account Code</th>
    <th>Account Name</th>
    <th class="bpr-col-particulars">Particulars</th>
    <th class="text-right">Proposed Amount</th>
    <th class="text-right bpr-col-amount">Suggestion Amount</th>
    <th class="bpr-col-select">Component<span class="bpr-req">*</span></th>
</tr></thead>`;

// Suggestion Amount is optional and pre-filled with the saved value (0.00 for a line item that never had one).
function bpr_rowHtml(li) {
    return `<tr data-li-id="${Number(li.budget_line_item_id)}">
        <td><select class="nha-filter-select bpr-field bpr-fund" required>${bpr_optionsHtml(BPR_FUNDS, 'type_of_fund_id', li.type_of_fund_id)}</select></td>
        <td class="bpr-nowrap">${bgr_esc(li.account_code)}</td>
        <td>${bgr_esc(li.account_title)}</td>
        <td>${bgr_esc(li.particulars)}</td>
        <td class="amt-cell">${bgr_fmtMoney(li.proposed_amount)}</td>
        <td><input type="text" inputmode="decimal" autocomplete="off" class="nha-filter-input bpr-field bpr-suggestion"
                   placeholder="0.00" value="${bgr_escHtmlAttr(bgr_fmtAmountInitial(li.suggestion_amount))}"
                   oninput="bgr_fmtAmount(this)"></td>
        <td><select class="nha-filter-select bpr-field bpr-component" required>${bpr_optionsHtml(BPR_COMPONENTS, 'component_type_id', li.component_id)}</select></td>
    </tr>`;
}

function bpr_panelHtml(project, idx) {
    const lineItems = project.line_items ?? [];
    const subtotal  = lineItems.reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0);
    const rowsHtml  = lineItems.length
        ? lineItems.map(bpr_rowHtml).join('')
        : '<tr><td colspan="7"><div class="bpr-empty">No line items have been added under this project.</div></td></tr>';

    return `<div class="bpr-panel" role="tabpanel" id="bpr-panel-${idx}" data-panel="${idx}" aria-labelledby="bpr-tab-${idx}" hidden>
        <div class="bpr-panel-hd">
            <div class="bpr-panel-title">${bgr_esc(project.project_code)}${project.project_name ? ' — ' + bgr_esc(project.project_name) : ''}</div>
            <div class="bpr-panel-meta">${lineItems.length} line item(s) · ${bgr_fmtMoney(subtotal)}</div>
        </div>
        <div class="bpr-tbl-wrap">
            <div class="nha-tbl-scroll">
                <table class="nha-tbl bpr-tbl">${BPR_THEAD}<tbody>${rowsHtml}</tbody></table>
            </div>
        </div>
    </div>`;
}

function bpr_selectTab(idx) {
    const key = String(idx);
    document.querySelectorAll('#bpr-tabs .bpr-tab').forEach(tab => {
        const on = tab.dataset.tab === key;
        tab.classList.toggle('active', on);
        tab.setAttribute('aria-selected', on ? 'true' : 'false');
    });
    document.querySelectorAll('#bpr-panels .bpr-panel').forEach(panel => {
        panel.hidden = panel.dataset.panel !== key;
    });
}

function bpr_render(requestContext, program) {
    $bpr('bpr-fiscal-yr').textContent    = requestContext.fiscal_yr ?? '—';
    $bpr('bpr-program').textContent      = `${program.program_code} — ${program.program_name}`;
    $bpr('bpr-sub-program').textContent  = program.sub_program_name || '—';
    $bpr('bpr-status').innerHTML         = bgr_statusBadge(requestContext.status_name, requestContext.status_color);

    const projects = program.projects ?? [];
    const allLineItems = projects.flatMap(p => p.line_items ?? []);
    bpr_rowCount = allLineItems.length;

    $bpr('bpr-li-count').textContent = bpr_rowCount;
    $bpr('bpr-total').textContent    = bgr_fmtMoney(allLineItems.reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0));

    // One tab + one panel (with its own table) per project; the tab shows its line-item count.
    $bpr('bpr-tabs').innerHTML = projects.map((p, i) =>
        `<button type="button" class="nha-tab bpr-tab" role="tab" id="bpr-tab-${i}" data-tab="${i}" aria-controls="bpr-panel-${i}" title="${bgr_escHtmlAttr(p.project_name)}">${bgr_esc(p.project_code)}<span class="nha-tab-cnt">${(p.line_items ?? []).length}</span></button>`
    ).join('');
    $bpr('bpr-panels').innerHTML = projects.map(bpr_panelHtml).join('');

    $bpr('bpr-line-items').hidden = projects.length === 0;
    $bpr('bpr-empty').hidden      = projects.length > 0;
    if (projects.length) bpr_selectTab(0);
}

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

// A Suggestion Amount is optional — blank is fine (saved as 0.00) — but if
// something is typed it has to be a number, 0 or more.
function bpr_isValidAmount(value) {
    const v = bgr_unformatAmount(value).trim();
    return v === '' || (!isNaN(Number(v)) && Number(v) >= 0);
}

// Endorse needs a Type of Fund AND a Component on every line item, in every tab.
// Fields in tabs other than the open one are checked too; the first offender's
// tab is opened (so it can be focused) and every tab with a problem gets a red dot.
function bpr_validate() {
    let firstInvalid = null;
    const badTabs = new Set();

    document.querySelectorAll('#bpr-panels .bpr-field').forEach(field => {
        const invalid = field.matches('.bpr-suggestion') ? !bpr_isValidAmount(field.value) : !field.value;
        field.classList.toggle('is-invalid', invalid);
        if (invalid) {
            badTabs.add(field.closest('.bpr-panel').dataset.panel);
            if (!firstInvalid) firstInvalid = field;
        }
    });

    document.querySelectorAll('#bpr-tabs .bpr-tab').forEach(tab => {
        tab.classList.toggle('has-error', badTabs.has(tab.dataset.tab));
    });

    if (firstInvalid) {
        bpr_selectTab(firstInvalid.closest('.bpr-panel').dataset.panel);   // reveal it before focusing
        firstInvalid.focus();
        bpr_showErr(firstInvalid.matches('.bpr-suggestion')
            ? 'Suggestion Amount must be a number, 0 or more.'
            : 'Type of Fund and Component are required for every line item.');
        return false;
    }
    return true;
}

// Rows of every tab — hidden panels are still in the DOM.
function bpr_collectItems() {
    return [...document.querySelectorAll('#bpr-panels tr[data-li-id]')].map(tr => ({
        budget_line_item_id: Number(tr.dataset.liId),
        type_of_fund_id:     tr.querySelector('.bpr-fund').value || null,
        suggestion_amount:   bgr_unformatAmount(tr.querySelector('.bpr-suggestion').value).trim(),
        component_id:        tr.querySelector('.bpr-component').value || null,
    }));
}

async function bpr_endorse() {
    bpr_hideErr();
    if (!bpr_validate()) return;
    if (!confirm(`Endorse the fiscal year ${bpr_ctx.fiscal_yr} budget request? The Type of Fund, Suggestion Amount and Component will be saved to its line items and its status will change to Endorsed.`)) return;

    await bpr_send('endorse', { items: bpr_collectItems() }, 'Failed to endorse the budget request.');
}

async function bpr_return() {
    bpr_hideErr();
    if (!confirm(`Return the fiscal year ${bpr_ctx.fiscal_yr} budget request? Its status will change to Returned.`)) return;

    await bpr_send('return', {}, 'Failed to return the budget request.');
}

async function bpr_send(action, body, failMessage) {
    bpr_updateButtons(true);
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BPR_BUDGET_REQ_API}/${action}/${bpr_ctx.budget_reference_number}`, {
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
