<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_brv_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/css/nha-fims.css') ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .brv-summary-2col { grid-template-columns: repeat(2, 1fr); }
}
/* POV (point of view) dropdown in the page header */
.nha-root .brv-pov { display: inline-flex; align-items: center; gap: 8px; margin: 0; }
.nha-root .brv-pov-label {
    font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--sec, #62657E);
}
.nha-root .brv-pov .nha-filter-select { width: auto; min-width: 150px; font-weight: 600; }
/* a row action that is switched off (e.g. Edit on an approved request) */
.nha-root .nha-btn-act[disabled],
.nha-root .nha-btn-act.edit[disabled]:hover {
    background: var(--afims-surface, #FFFFFF); border-color: var(--bdr, #E4ECF2); color: var(--sec, #62657E);
    opacity: .5; cursor: not-allowed;
}
.nha-root .brv-reason {
    max-width: 260px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>

<div id="brv-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Budget Revision Requests</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <label class="brv-pov" for="brv-pov">
                <span class="brv-pov-label">POV</span>
                <select id="brv-pov" class="nha-filter-select" title="Point of view — decides which row actions you get">
                    <option value="ORIGINATING">Originating</option>
                    <option value="FMD">FMD Officer</option>
                </select>
            </label>
            <button id="brv-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="brv-add" type="button" class="nha-btn-primary">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Revision Request
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary brv-summary-2col">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Revisions</div>
                    <div class="nha-sc-val" id="brv-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Net Change</div>
                    <div class="nha-sc-val" id="brv-kpi-change">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Sum of (to − from), matches current filters</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <input type="number" id="brv-filter-fiscal-yr" class="nha-filter-input" placeholder="All Years" min="2000" max="2100">
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Budget Request</label>
                <select id="brv-filter-req" class="nha-filter-select">
                    <option value="">All</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Budget Request</th>
                        <th>From</th>
                        <th>To</th>
                        <th>Change</th>
                        <th>Reason</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="brv-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="brv-from">0</span>–<span id="brv-to">0</span></strong> of <strong><span id="brv-total">0</span></strong></span>
            <div id="brv-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

    <!-- Modal must live inside .nha-root — every .nha-overlay / .nha-modal rule in nha-fims.css is scoped to it -->
    <?php include __DIR__ . '/form/budget-rev-form.php'; ?>
    <?php include __DIR__ . '/form/budget-rev-review.php'; ?>

</div><!-- /#brv-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REVISION REQUESTS LIST   prefix: brv_
═══════════════════════════════════════════════════════════════ */

const BRV_API = '<?php echo $_brv_api; ?>' + '/budget_revision';

let brv_page = 1;
let brv_viewRow = null;   // the revision currently open in the details modal
const BRV_PAGE_SIZE = 15;

// `status` codes returned by the API (BudgetRevisionService) → label + badge colour
const BRV_STATUS = {
    PENDING : { label: 'Pending',  cls: 'nha-bdg-amber' },
    APPROVED: { label: 'Approved', cls: 'nha-bdg-green' },
    DECLINED: { label: 'Declined', cls: 'nha-bdg-red'   },
};

// POV (point of view) decides which row actions the viewer gets. It is a view switch only —
// the API does not enforce it.
//   Originating : files and edits revision requests -> Edit
//   FMD Officer : reviews them                      -> More Action (details + approve / decline)
// Delete is not offered in either.
const BRV_POV = {
    ORIGINATING: { label: 'Originating', actions: ['edit'] },
    FMD        : { label: 'FMD Officer', actions: ['view'] },
};
const BRV_POV_KEY = 'brv.pov';
let brv_pov  = brv_loadPov();
let brv_rows = [];   // the rows last rendered, so changing POV can redraw without refetching

const $brv = id => document.getElementById(id);

// the choice is remembered in this browser; storage can be blocked, so always fall back to the default
function brv_loadPov() {
    try {
        const saved = localStorage.getItem(BRV_POV_KEY);
        if (saved && BRV_POV[saved]) return saved;
    } catch (e) { /* storage unavailable */ }
    return 'ORIGINATING';
}
function brv_setPov(value) {
    if (!BRV_POV[value]) return;
    brv_pov = value;
    try { localStorage.setItem(BRV_POV_KEY, value); } catch (e) { /* storage unavailable */ }
    brv_renderTable(brv_rows);
}

async function brv_bootstrapLoad() {
    if ($brv('brv-pov')) $brv('brv-pov').value = brv_pov;
    // brvBudgetReqsReady comes from form/budget-rev-form.php, which fills the
    // form's Budget Request dropdown. Wait for it so Edit can preselect a value.
    await Promise.all([brv_load(), brv_fillFilterOptions()]);
    brv_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', brv_bootstrapLoad);
} else {
    brv_bootstrapLoad();
}

// The filter reuses the budget requests the form already fetched from /budget_req/all.
// Pass `items` to rebuild the list from fresh data; otherwise it uses the form's first load.
async function brv_fillFilterOptions(items) {
    items = items ?? (await window.brvBudgetReqsReady) ?? [];
    const filter = $brv('brv-filter-req');
    const keep = filter.value;
    filter.querySelectorAll('option:not([value=""])').forEach(o => o.remove());
    items.forEach(({ value, label }) => {
        const opt = document.createElement('option');
        opt.value = value;
        opt.textContent = label;
        filter.appendChild(opt);
    });
    filter.value = keep;   // keep the current selection if it still exists
}

// Approving changes a budget request's amount, so refresh what the form and the filter
// are showing (the labels carry the amount, and From Amount autofills from it).
async function brv_refreshBudgetReqs() {
    const items = await window.brvForm?.reload?.();
    if (items) brv_fillFilterOptions(items);
}

function brv_bindEvents() {
    $brv('brv-refresh')?.addEventListener('click', () => { brv_page = 1; brv_load(); });
    $brv('brv-add')?.addEventListener('click', () => brv_openModal('add'));
    $brv('brv-modal-close')?.addEventListener('click', brv_closeModal);
    $brv('brv-cancel')?.addEventListener('click', brv_closeModal);
    $brv('brv-form')?.addEventListener('submit', brv_save);

    $brv('brv-pov')?.addEventListener('change', e => brv_setPov(e.target.value));

    $brv('brv-view-close')?.addEventListener('click', brv_closeView);
    $brv('brv-view-cancel')?.addEventListener('click', brv_closeView);
    $brv('brv-approve')?.addEventListener('click', () => brv_review('APPROVED'));
    $brv('brv-decline')?.addEventListener('click', () => brv_review('DECLINED'));

    ['brv-filter-fiscal-yr', 'brv-filter-req'].forEach(id => {
        $brv(id)?.addEventListener('change', () => { brv_page = 1; brv_load(); });
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function brv_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            fiscal_yr     : $brv('brv-filter-fiscal-yr')?.value || '',
            budget_req_id : $brv('brv-filter-req')?.value       || '',
            page          : brv_page,
            limit         : BRV_PAGE_SIZE,
        });
        const res  = await fetch(`${BRV_API}/list?${params}`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        brv_renderTable(json.data ?? []);
        brv_renderPagination(json.pagination ?? {});
        $brv('brv-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $brv('brv-kpi-change').textContent = brv_fmtChange(json.total_change ?? 0);
    } catch (e) {
        brv_renderTable([]);
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function brv_renderTable(rows) {
    brv_rows = rows;
    const tbody = $brv('brv-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget revision requests found.</span></div></td></tr>';
        return;
    }
    const offset = (brv_page - 1) * BRV_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const code   = r.project_code || r.program_code || '—';
        const name   = r.project_name || r.program_name || '';
        const diff   = Number(r.to_amount || 0) - Number(r.from_amount || 0);
        const color  = diff > 0 ? '#16a34a' : (diff < 0 ? '#dc2626' : 'inherit');
        const reason = r.reason ? brv_esc(r.reason) : '<span class="nha-td-sub">—</span>';
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>
            <div style="font-weight:500;">FY ${brv_esc(r.fiscal_yr ?? '—')} — ${brv_esc(code)}</div>
            ${name ? `<div class="nha-td-sub" style="margin-top:2px;">${brv_esc(name)}</div>` : ''}
            ${r.to_budget_req_id ? `<div class="nha-td-sub" style="margin-top:2px;" title="Funds move to this COB">→ To: FY ${brv_esc(r.to_fiscal_yr ?? '—')} — ${brv_esc(r.to_project_code || r.to_program_code || '—')}</div>` : ''}
        </td>
        <td class="text-end">${brv_fmtMoney(r.from_amount)}</td>
        <td class="text-end"><div style="font-weight:600;">${brv_fmtMoney(r.to_amount)}</div></td>
        <td class="text-end" style="color:${color};font-weight:600;">${brv_fmtChange(diff)}</td>
        <td><div class="brv-reason" title="${brv_esc(r.reason || '')}">${reason}</div></td>
        <td>${brv_statusBadge(r.status)}</td>
        <td>${brv_esc(brv_fmtDate(r.created_at))}</td>
        <td>${brv_rowActions(r)}</td>
    </tr>`;
    }).join('');
}

// The action buttons a row gets depend on the POV (see BRV_POV) and on its status:
// an APPROVED request has already been applied to its budget request, so Edit is switched off.
function brv_rowActions(r) {
    const id     = Number(r.id);
    const can    = BRV_POV[brv_pov].actions;
    const locked = r.status === 'APPROVED';
    return (can.includes('view') ? `
            <button class="nha-btn-act view" onclick="brv_view(${id})" title="More action: view details, approve or decline">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                More Action
            </button>` : '')
        + (can.includes('edit') ? `
            <button class="nha-btn-act edit" ${locked ? 'disabled' : `onclick="brv_edit(${id})"`} title="${locked ? "Approved requests can't be edited" : 'Edit'}">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>` : '');
}

/* ── Pagination ────────────────────────────────────────────── */
function brv_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = BRV_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $brv('brv-from').textContent  = from;
    $brv('brv-to').textContent    = to;
    $brv('brv-total').textContent = total;

    const container = $brv('brv-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="brv_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="brv_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="brv_goTo(${current_page + 1})">›</button>`;
}

function brv_goTo(page) { brv_page = page; brv_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function brv_openModal(mode, row = null) {
    $brv('brv-mode').value = mode;
    $brv('brv-id').value = row?.id || '';
    $brv('brv-modal-title').textContent = mode === 'edit' ? 'Edit Budget Revision Request' : 'Add Budget Revision Request';
    // the From COB / To COB fields are searchable dropdowns owned by form/budget-rev-form.php
    if (window.brvForm) window.brvForm.setBudgetReq(row?.from_budget_req_id ?? '', row?.to_budget_req_id ?? '');
    else {
        $brv('brv-budget-req-id').value = row?.from_budget_req_id || '';
        $brv('brv-to-budget-req-id').value = row?.to_budget_req_id || '';
    }
    $brv('brv-from-amount').value = row?.from_amount ?? '';
    $brv('brv-to-amount').value = row?.to_amount ?? '';
    $brv('brv-reason').value = row?.reason || '';

    $brv('brv-overlay').classList.add('open');
}

function brv_closeModal() {
    $brv('brv-overlay').classList.remove('open');
    $brv('brv-form').reset();
}

async function brv_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BRV_API}/read/${id}`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget revision request.');
        if (json.data.status === 'APPROVED') {   // approved after this page was loaded
            brv_toast("This request is already approved, so it can't be edited.", 'warning');
            brv_load();
            return;
        }
        brv_openModal('edit', json.data);
    } catch (e) {
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function brv_save(ev) {
    ev.preventDefault();
    const mode = $brv('brv-mode').value;
    const id   = $brv('brv-id').value;

    const payload = {
        from_budget_req_id : $brv('brv-budget-req-id').value,
        to_budget_req_id   : $brv('brv-to-budget-req-id').value,   // '' = no To COB (cleared on edit)
        from_amount        : $brv('brv-from-amount').value,
        to_amount          : $brv('brv-to-amount').value,
        reason             : $brv('brv-reason').value.trim(),
    };

    if (!payload.from_budget_req_id) { brv_toast('Please select a From COB.', 'error'); return; }
    if (payload.to_budget_req_id && Number(payload.to_amount) >= Number(payload.from_amount)) {
        brv_toast('To Amount must be lower than From Amount to move funds to the To COB.', 'error');
        return;
    }

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${BRV_API}/update/${id}` : `${BRV_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: brv_apiHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save budget revision request.');

        brv_toast(`Budget revision request ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        brv_closeModal();
        brv_load();
    } catch (e) {
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Details / review modal ────────────────────────────────── */
async function brv_view(id) {
    window.PagePreloader?.show();
    try {
        // read fresh from the API so the status shown is the current one
        const res  = await fetch(`${BRV_API}/read/${id}`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget revision request.');
        brv_openView(json.data);
    } catch (e) {
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function brv_openView(r) {
    const isProject = !!r.project_code;
    const code = r.project_code || r.program_code || '—';
    const name = r.project_name || r.program_name || '';
    const diff = Number(r.to_amount || 0) - Number(r.from_amount || 0);

    brv_viewRow = r;
    $brv('brv-view-id').value = r.id;
    $brv('brv-view-title').textContent = `Budget Revision Request #${r.id}`;

    // the budget request being revised
    const type = $brv('brv-view-type');
    type.textContent = isProject ? 'Project' : 'Program';
    type.className = 'nha-bdg ' + (isProject ? 'nha-bdg-teal' : 'nha-bdg-blue');
    $brv('brv-view-title-req').textContent = name ? `${code} — ${name}` : code;
    $brv('brv-view-req-sub').textContent = `FY ${r.fiscal_yr ?? '—'}`
        + (isProject ? ` · Program: ${r.program_code}${r.program_name ? ' — ' + r.program_name : ''}` : '');
    $brv('brv-view-req-amount').textContent = brv_fmtMoney(r.budget_req_amount);

    // the To COB — a card when the revision has one, a one-line note when it doesn't
    const hasTo = !!r.to_budget_req_id;
    $brv('brv-view-to-card').hidden = !hasTo;
    $brv('brv-view-to-none').hidden = hasTo;
    if (hasTo) {
        const toIsProject = !!r.to_project_code;
        const toCode = r.to_project_code || r.to_program_code || '—';
        const toName = r.to_project_name || r.to_program_name || '';
        const toType = $brv('brv-view-to-type');
        toType.textContent = toIsProject ? 'Project' : 'Program';
        toType.className = 'nha-bdg ' + (toIsProject ? 'nha-bdg-teal' : 'nha-bdg-blue');
        $brv('brv-view-to-title-req').textContent = toName ? `${toCode} — ${toName}` : toCode;
        $brv('brv-view-to-req-sub').textContent = `FY ${r.to_fiscal_yr ?? '—'}`
            + (toIsProject ? ` · Program: ${r.to_program_code}${r.to_program_name ? ' — ' + r.to_program_name : ''}` : '');
        $brv('brv-view-to-req-amount').textContent = brv_fmtMoney(r.to_budget_req_amount);
    }

    // the revision
    $brv('brv-view-from').textContent = brv_fmtMoney(r.from_amount);
    $brv('brv-view-to').textContent   = brv_fmtMoney(r.to_amount);
    const change = $brv('brv-view-change');
    change.textContent = brv_fmtChange(diff);
    change.className = 'brv-v-cell-v' + (diff > 0 ? ' is-up' : diff < 0 ? ' is-down' : '');
    brv_renderEffect(r);

    const reason = $brv('brv-view-reason');
    reason.textContent = r.reason || 'No reason provided.';
    reason.classList.toggle('is-empty', !r.reason);
    $brv('brv-view-meta').textContent = `Requested by ${brv_requestedBy(r)} on ${brv_fmtDateTime(r.created_at)}`;

    // status — Decline / Approve only while the request is still pending
    const known   = BRV_STATUS[r.status];
    const s       = known || { label: r.status || 'Unknown', cls: 'nha-bdg-gray' };
    const pending = r.status === 'PENDING';
    const badge   = $brv('brv-view-status');
    badge.textContent = s.label;
    badge.className   = 'nha-bdg ' + s.cls;
    $brv('brv-view-status-note').textContent = pending ? 'Waiting for a decision'
        : known ? `${s.label} on ${brv_fmtDateTime(r.update_at)}` : 'Unrecognised status';
    $brv('brv-view-actions').hidden = !pending;
    brv_setReviewBusy(false);

    $brv('brv-view-overlay').classList.add('open');
}

function brv_closeView() {
    $brv('brv-view-overlay').classList.remove('open');
}

// What approving does to the two COBs. Only shown when the revision has a To COB: then it MOVES
// funds — the From COB goes down to To Amount and the To COB goes up by the difference
// (measured against the From COB's current amount, as the server does).
function brv_renderEffect(r) {
    const box = $brv('brv-view-effect');
    if (!r.to_budget_req_id) { box.hidden = true; return; }

    const fromCode = r.project_code || r.program_code || 'the From COB';
    const toCode   = r.to_project_code || r.to_program_code || 'the To COB';
    const fromNow  = Number(r.budget_req_amount || 0);
    const toNow    = Number(r.to_budget_req_amount || 0);
    const target   = Number(r.to_amount || 0);
    const moved    = Math.round((fromNow - target) * 100) / 100;

    let text, warn = false;
    if (r.status === 'APPROVED')      text = `Funds were moved from ${fromCode} to ${toCode}.`;
    else if (r.status === 'DECLINED') text = `Declined — no funds were moved from ${fromCode} to ${toCode}.`;
    else if (moved > 0) {
        text = `On approval ${brv_fmtMoney(moved)} moves from ${fromCode} (${brv_fmtMoney(fromNow)} → ${brv_fmtMoney(target)}) `
             + `to ${toCode} (${brv_fmtMoney(toNow)} → ${brv_fmtMoney(toNow + moved)}).`;
    } else {
        text = `This can't be approved: To Amount (${brv_fmtMoney(target)}) is not lower than ${fromCode}'s current amount (${brv_fmtMoney(fromNow)}).`;
        warn = true;
    }
    box.textContent = text;
    box.classList.toggle('is-warn', warn);
    box.hidden = false;
}

function brv_setReviewBusy(busy) {
    ['brv-approve', 'brv-decline'].forEach(id => { $brv(id).disabled = busy; });
}

async function brv_review(status) {
    const id   = $brv('brv-view-id').value;
    const verb = status === 'APPROVED' ? 'approve' : 'decline';

    // Approving changes money, so say exactly what will happen before asking.
    let question = `Are you sure you want to ${verb} budget revision request #${id}?`;
    if (status === 'APPROVED' && brv_viewRow) {
        const v       = brv_viewRow;
        const current = Number(v.budget_req_amount || 0);
        const target  = Number(v.to_amount || 0);
        const moved   = Math.round((current - target) * 100) / 100;
        if (v.to_budget_req_id && moved > 0) {
            // a transfer: the From COB gives up what the To COB receives
            const toCurrent = Number(v.to_budget_req_amount || 0);
            const fromCode  = v.project_code || v.program_code || 'The From COB';
            const toCode    = v.to_project_code || v.to_program_code || 'The To COB';
            question += `\n\n${fromCode} (From COB) will change from ${brv_fmtMoney(current)} to ${brv_fmtMoney(target)}, `
                      + `and ${brv_fmtChange(-moved)} will be added to its adjustment.`
                      + `\n${toCode} (To COB) will change from ${brv_fmtMoney(toCurrent)} to ${brv_fmtMoney(toCurrent + moved)}, `
                      + `and ${brv_fmtChange(moved)} will be added to its adjustment.`;
        } else if (!v.to_budget_req_id) {
            question += `\n\nThe budget request amount will change from ${brv_fmtMoney(current)} to ${brv_fmtMoney(target)}, `
                      + `and ${brv_fmtChange(target - current)} will be added to its adjustment.`;
        }
    }
    if (!id || !confirm(question)) return;

    brv_setReviewBusy(true);   // no double submit
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BRV_API}/review/${id}`, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: brv_apiHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify({ status }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || `Failed to ${verb} budget revision request.`);

        brv_toast(json.message || `Budget revision request ${verb}d.`, 'success');
        brv_closeView();
        brv_load();
        if (status === 'APPROVED') brv_refreshBudgetReqs();
    } catch (e) {
        brv_toast(e.message, 'error');
        brv_setReviewBusy(false);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
// The API only knows who is logged in when the request carries the JWT PHPMaker puts
// on the page (ew.API_JWT_TOKEN). Without this header the request is anonymous, so
// /budget_revision/add cannot tell who is filing the revision.
function brv_apiHeaders(extra = {}) {
    const headers = { ...extra };
    if (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) {
        headers[ew.API_JWT_AUTHORIZATION_HEADER || 'X-Authorization'] = 'Bearer ' + ew.API_JWT_TOKEN;
    }
    return headers;
}
function brv_fmtMoney(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// Signed amount: +₱ 1,000.00 / −₱ 500.00 / ₱ 0.00
function brv_fmtChange(v) {
    const n = Number(v || 0);
    if (n === 0) return brv_fmtMoney(0);
    return (n > 0 ? '+' : '−') + brv_fmtMoney(Math.abs(n));
}
function brv_fmtDate(ts) {
    return ts ? String(ts).slice(0, 10) : '—';
}
function brv_fmtDateTime(ts) {
    return ts ? String(ts).slice(0, 16) : '—';
}
// The built-in administrator is user_id -1 and has no row in uac.users.
function brv_requestedBy(r) {
    if (r.requested_by_name) return r.requested_by_name;
    if (r.requested_by_username) return r.requested_by_username;
    return Number(r.user_id) === -1 ? 'Administrator' : `User #${r.user_id}`;
}
function brv_statusBadge(code) {
    const s = BRV_STATUS[code] || { label: code || 'Unknown', cls: 'nha-bdg-gray' };
    return `<span class="nha-bdg ${s.cls}">${brv_esc(s.label)}</span>`;
}
function brv_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function brv_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    const icons  = {
        success: '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>',
        error:   '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>',
        warning: '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>',
        info:    '<svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>',
    };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const color = colors[type] || colors.info;
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    t.innerHTML = `<span style="color:${color};flex-shrink:0;margin-top:1px">${icons[type] || icons.info}</span><div style="flex:1"><span>${brv_esc(msg)}</span></div><button onclick="this.parentElement.remove()" style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
