<?php

namespace PHPMaker2024\FIMS;

/**
 * budget-revision-page.php — "Budget Revision Requests": the requests to change a BUDGET LINE's amount (a chart-of-accounts line
 * item of a budget request) and optionally move the difference to a second budget line. Originating offices file and edit
 * them; the Financial Management Department (FMD) reviews them — approves or declines. Approving changes the lines'
 * budget_line_items.adjustment: the From line goes down, the To line goes up.
 *
 * Who is looking decides what a row offers. There is no point-of-view switch: it follows the logged-in user's office.
 */
@session_start();
$_brv_api = Config('API.FIMS_URL');

// Who is looking: the logged-in user's office. uac.users gives the rc_id (that column only) and is joined to
// fims.responsibility_code for the RC itself — the same lookup as budget-req-page.php and memo-req-page.php.
//   FMD (the Financial Management Department: rc_code 35-074-0000000-032, rc_id 013 in this data) -> the FMD point of
//       view: every row gets a "Review Request" action (details + Approve / Decline), or "View Details" once it has been
//       decided. FMD does not edit the offices' requests.
//   Anyone else -> the originating point of view: they file revision requests and get an Edit action on each row
//       (switched off once the request is approved, because it has then been applied to its budget request).
// FMD is recognised by its rc_code, NOT its rc_id (the id's format differs between datasets: "013" vs "RC-013"). Mirrors
// GlobalService::CENTRAL_REVIEW_RC_CODE. A user whose office can't be found (no users row — e.g. the built-in
// administrator —, no rc_id, or a failed lookup) gets the originating view, never an error page and never the review actions.
$userid = CurrentUserID();
$_brv_fmdRcCode = '35-074-0000000-032';
$_brv_isFmd = false;
try {
    $_brv_rc = ExecuteRow(
        'SELECT rc.rc_id, rc.rc_code, rc.rc_name FROM uac.users u '
        .'JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id WHERE u.user_id = '.(int) $userid,
        'DB'
    ) ?: null;
    $_brv_isFmd = $_brv_rc !== null && trim((string) ($_brv_rc['rc_code'] ?? '')) === $_brv_fmdRcCode;
} catch (\Throwable $e) {
    $_brv_isFmd = false;
    LogError('budget-revision-page: could not look up the current user\'s RC — '.$e->getMessage());
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<?php /* GetUrl() without a leading slash adds the app's base path (the app may live in a sub-folder / virtual directory), and the
          cache-buster is read from the file next to this page, not from DOCUMENT_ROOT — so a changed stylesheet is never served
          from a browser's stale cache. */ ?>
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .brv-summary-2col { grid-template-columns: repeat(2, 1fr); }
}

/* ── Actions dropdown menu ──────────────────────────────────── */
/* The same menu as the Budget Proposal / Budget Requirements lists, which keep their copy inline too. */
.nha-tools-menu { position:relative;display:inline-block; }
.nha-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;
    font-size:11px;font-weight:600;cursor:pointer;border:1px solid var(--afims-border, #E4ECF2);
    background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;
    font-family:'IBM Plex Sans',sans-serif;transition:all .15s; }
.nha-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA);border-color:var(--afims-text-disabled, #A9AAB8); }
.nha-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.nha-tools-dropdown { display:none;position:fixed;min-width:170px;background:var(--afims-surface, #FFFFFF);
    border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.12);
    z-index:9999;padding:4px 0; }
.nha-tools-dropdown.open { display:block; }
.nha-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;
    color:var(--afims-text, #1A1B21);text-decoration:none;white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;transition:background .1s;
    font-family:'IBM Plex Sans',sans-serif; }
.nha-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
/* an action that is switched off (Edit on an approved request) */
.nha-menu-item[disabled] { opacity:.5;cursor:not-allowed; }
.nha-menu-item[disabled]:hover { background:none;color:var(--afims-text, #1A1B21); }

/* ── Table cells ────────────────────────────────────────────── */
.nha-root .brv-up   { color: var(--green-txt, #0C7539); }
.nha-root .brv-down { color: var(--red-txt, #8F222D); }
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
                <p class="nha-hdr-sub" id="brv-hdr-sub"><?php echo $_brv_isFmd
                    ? 'Review the revision requests filed by the offices — approve or decline them.'
                    : 'File a revision request for a budget request and follow its status.'; ?></p>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="brv-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="brv-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="File a budget revision request">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Revision Request
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover every request matching the filters, not just this page) ── -->
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
                <select id="brv-filter-fiscal-yr" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
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
                        <th>Request No.</th>
                        <th>Budget Line</th>
                        <th class="text-right">From</th>
                        <th class="text-right">To</th>
                        <th class="text-right">Change</th>
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
   One row per revision request: Request No. (its id — the number the details modal is titled with, the fiscal year of
   the budget line's request under it), the Budget Line being revised (its account, and where it sits; + the To line when
   funds move to another one), From / To / Change amounts, Reason, Status, and when it was requested and by whom.
   Row actions depend on who is looking (BRV_IS_FMD, set by the PHP at the top of this file):
     FMD         -> Review Request (pending) / View Details (decided): the details modal with Approve / Decline
     anyone else -> Edit (off once the request is approved)
   Delete is not offered to either. The API does not enforce this split — it only decides what the page offers.
═══════════════════════════════════════════════════════════════ */

const BRV_BASE = '<?php echo $_brv_api; ?>';
const BRV_API  = BRV_BASE + '/budget_revision';
// true when the logged-in user belongs to the Financial Management Department (see the PHP at the top).
const BRV_IS_FMD = <?php echo $_brv_isFmd ? 'true' : 'false'; ?>;

let brv_page = 1;
let brv_viewRow = null;   // the revision currently open in the details modal
const BRV_PAGE_SIZE = 15;

// `status` codes returned by the API (BudgetRevisionService) → label + badge colour
const BRV_STATUS = {
    PENDING : { label: 'Pending',  cls: 'nha-bdg-amber' },
    APPROVED: { label: 'Approved', cls: 'nha-bdg-green' },
    DECLINED: { label: 'Declined', cls: 'nha-bdg-red'   },
};

const $brv = id => document.getElementById(id);

async function brv_bootstrapLoad() {
    // brvBudgetReqsReady comes from form/budget-rev-form.php, which fills the
    // form's Budget Request dropdown. Wait for it so Edit can preselect a value.
    await Promise.all([brv_load(), brv_fillFilterOptions(), brv_fillYears()]);
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

// The Fiscal Year filter lists the fiscal years from /fiscal_year/all (the same list the other pages use).
// If it can't be read the filter still works as "All Years".
async function brv_fillYears() {
    const select = $brv('brv-filter-fiscal-yr');
    try {
        const res  = await fetch(`${BRV_BASE}/fiscal_year/all`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'HTTP ' + res.status);

        const keep = select.value;
        select.querySelectorAll('option:not([value=""])').forEach(o => o.remove());
        (json.data ?? []).forEach(({ fiscal_yr }) => {
            if (fiscal_yr === null || fiscal_yr === undefined || fiscal_yr === '') return;
            const opt = document.createElement('option');
            opt.value = fiscal_yr;
            opt.textContent = fiscal_yr;
            select.appendChild(opt);
        });
        select.value = keep;
    } catch (e) {
        brv_toast('Could not load the fiscal years: ' + e.message, 'warning');
    }
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
            budget_reference_number : $brv('brv-filter-req')?.value || '',
            page          : brv_page,
            limit         : BRV_PAGE_SIZE,
        });
        const res  = await fetch(`${BRV_API}/list?${params}`, { credentials: 'same-origin', headers: brv_apiHeaders() });
        // Read the body even on an error status, so the server's own message is shown rather than a bare "HTTP 4xx".
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'HTTP ' + res.status);

        brv_renderTable(json.data ?? []);
        brv_renderPagination(json.pagination ?? {});
        $brv('brv-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $brv('brv-kpi-change').textContent = brv_fmtChange(json.total_change ?? 0);
    } catch (e) {
        brv_renderTable([]);
        brv_renderPagination({});
        $brv('brv-kpi-total').textContent  = 0;
        $brv('brv-kpi-change').textContent = brv_fmtChange(0);
        brv_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table — one row per revision request ──────────────────── */
function brv_renderTable(rows) {
    const tbody = $brv('brv-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget revision requests found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => {
        const diff   = Number(r.to_amount || 0) - Number(r.from_amount || 0);
        const trend  = diff > 0 ? 'brv-up' : (diff < 0 ? 'brv-down' : '');
        const reason = r.reason ? brv_esc(r.reason) : '<span class="nha-td-sub">—</span>';
        const date   = brv_fmtDate(r.created_at);
        return `<tr>
        <td>
            <code class="nha-code">#${brv_esc(r.id)}</code>
            <div class="nha-td-sub">FY ${brv_esc(r.fiscal_yr ?? '—')}</div>
        </td>
        <td>
            <div class="nha-td-bold">${brv_esc(brv_lineTitle(r, ''))}</div>
            <div class="nha-td-sub">${brv_esc(brv_linePlace(r, ''))}</div>
            ${r.to_budget_line_item_id ? `<div class="nha-td-sub" title="Funds move to this budget line">→ To: ${brv_esc(brv_lineTitle(r, 'to_'))}${r.to_budget_reference_number ? ' (' + brv_esc(r.to_budget_reference_number) + ')' : ''}</div>` : ''}
        </td>
        <td class="text-end">${brv_fmtMoney(r.from_amount)}</td>
        <td class="text-end"><div style="font-weight:600;">${brv_fmtMoney(r.to_amount)}</div></td>
        <td class="text-end ${trend}" style="font-weight:600;">${brv_fmtChange(diff)}</td>
        <td><div class="brv-reason" title="${brv_esc(r.reason || '')}">${reason}</div></td>
        <td>${brv_statusBadge(r.status)}</td>
        <td>
            ${date ? `<span class="nha-td-date">${brv_esc(date)}</span>` : '<span class="nha-td-sub">—</span>'}
            <div class="nha-td-sub">${brv_esc(brv_requestedBy(r))}</div>
        </td>
        <td>${brv_rowActions(r)}</td>
    </tr>`;
    }).join('');
}

/* ── Row actions ───────────────────────────────────────────── */
const BRV_ICON_VIEW  = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>';
const BRV_ICON_REVIEW = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';
const BRV_ICON_EDIT  = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>';

// The menu a row gets depends on who is looking (BRV_IS_FMD) and, for the originating view, on the request's status:
// an APPROVED request has already been applied to its budget request, so Edit is switched off.
function brv_rowActions(r) {
    const id = Number(r.id);
    let item;
    if (BRV_IS_FMD) {
        const pending = r.status === 'PENDING';
        item = `<button type="button" class="nha-menu-item" onclick="brv_closeMenus();brv_view(${id});">
            ${pending ? BRV_ICON_REVIEW : BRV_ICON_VIEW}
            ${pending ? 'Review Request' : 'View Details'}
        </button>`;
    } else if (r.status === 'APPROVED') {
        item = `<button type="button" class="nha-menu-item" disabled title="Approved requests can't be edited">
            ${BRV_ICON_EDIT}
            Edit
        </button>`;
    } else {
        item = `<button type="button" class="nha-menu-item" onclick="brv_closeMenus();brv_edit(${id});">
            ${BRV_ICON_EDIT}
            Edit
        </button>`;
    }

    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="brv_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">${item}</div>
    </div>`;
}

/* ── Actions dropdown — open/close (closes others, positions via fixed
   coords so the table's own overflow never clips it; opens upwards when
   there is no room below) ───────────────────────────────────────── */
function brv_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    brv_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.classList.add('open');
    const below = rect.bottom + 4;
    const fits  = below + drop.offsetHeight <= window.innerHeight - 8;
    drop.style.top   = (fits ? below : Math.max(8, rect.top - drop.offsetHeight - 4)) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
}

function brv_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) brv_closeMenus();
});
document.addEventListener('scroll', brv_closeMenus, true);

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
    // the From / To budget line fields are searchable dropdowns owned by form/budget-rev-form.php
    if (window.brvForm) window.brvForm.setBudgetLine(row?.from_budget_line_item_id ?? '', row?.to_budget_line_item_id ?? '');
    else {
        $brv('brv-line-id').value = row?.from_budget_line_item_id || '';
        $brv('brv-to-line-id').value = row?.to_budget_line_item_id || '';
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
        from_budget_line_item_id : $brv('brv-line-id').value,
        to_budget_line_item_id   : $brv('brv-to-line-id').value,   // '' = no To line (cleared on edit)
        from_amount              : $brv('brv-from-amount').value,
        to_amount                : $brv('brv-to-amount').value,
        reason                   : $brv('brv-reason').value.trim(),
    };

    if (!payload.from_budget_line_item_id) { brv_toast('Please select a From budget line.', 'error'); return; }
    if (payload.to_budget_line_item_id && Number(payload.to_amount) >= Number(payload.from_amount)) {
        brv_toast('To Amount must be lower than From Amount to move funds to the To budget line.', 'error');
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
    const diff = Number(r.to_amount || 0) - Number(r.from_amount || 0);

    brv_viewRow = r;
    $brv('brv-view-id').value = r.id;
    $brv('brv-view-title').textContent = `Budget Revision Request #${r.id}`;

    // the budget line being revised: its request's status, its account, where it sits (request · office · fiscal year, program ›
    // project, particulars) and its CURRENT amount (proposed + adjustment). One that no longer exists says so.
    brv_paintStatus($brv('brv-view-type'), r.budget_req_status_name, r.budget_req_status_color);
    $brv('brv-view-title-req').textContent = brv_lineTitle(r, '');
    $brv('brv-view-req-sub').textContent = brv_lineSub(r, '');
    $brv('brv-view-req-amount').textContent = brv_fmtMoney(r.line_amount);

    // the To line — a card when the revision has one, a one-line note when it doesn't
    const hasTo = !!r.to_budget_line_item_id;
    $brv('brv-view-to-card').hidden = !hasTo;
    $brv('brv-view-to-none').hidden = hasTo;
    if (hasTo) {
        brv_paintStatus($brv('brv-view-to-type'), r.to_budget_req_status_name, r.to_budget_req_status_color);
        $brv('brv-view-to-title-req').textContent = brv_lineTitle(r, 'to_');
        $brv('brv-view-to-req-sub').textContent = brv_lineSub(r, 'to_');
        $brv('brv-view-to-req-amount').textContent = brv_fmtMoney(r.to_line_amount);
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

    // status — Decline / Approve only while the request is still pending, and only for FMD (the reviewers)
    const known   = BRV_STATUS[r.status];
    const s       = known || { label: r.status || 'Unknown', cls: 'nha-bdg-gray' };
    const pending = r.status === 'PENDING';
    const badge   = $brv('brv-view-status');
    badge.textContent = s.label;
    badge.className   = 'nha-bdg ' + s.cls;
    $brv('brv-view-status-note').textContent = pending ? 'Waiting for a decision'
        : known ? `${s.label} on ${brv_fmtDateTime(r.update_at)}` : 'Unrecognised status';
    $brv('brv-view-actions').hidden = !(pending && BRV_IS_FMD);
    brv_setReviewBusy(false);

    $brv('brv-view-overlay').classList.add('open');
}

function brv_closeView() {
    $brv('brv-view-overlay').classList.remove('open');
}

// What approving does to the two budget lines. Only shown when the revision has a To line: then it MOVES
// funds — the From line goes down to To Amount (its adjustment is deducted) and the To line goes up by the difference
// (its adjustment is added), measured against the From line's current amount, as the server does.
function brv_renderEffect(r) {
    const box = $brv('brv-view-effect');
    if (!r.to_budget_line_item_id) { box.hidden = true; return; }

    const fromCode = brv_lineName(r, '') || 'the From budget line';
    const toCode   = brv_lineName(r, 'to_') || 'the To budget line';
    const fromNow  = Number(r.line_amount || 0);
    const toNow    = Number(r.to_line_amount || 0);
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
        const current = Number(v.line_amount || 0);
        const target  = Number(v.to_amount || 0);
        const moved   = Math.round((current - target) * 100) / 100;
        if (v.to_budget_line_item_id && moved > 0) {
            // a transfer: the From line gives up what the To line receives
            const toCurrent = Number(v.to_line_amount || 0);
            const fromCode  = brv_lineName(v, '') || 'The From budget line';
            const toCode    = brv_lineName(v, 'to_') || 'The To budget line';
            question += `\n\n${fromCode} (From budget line) will change from ${brv_fmtMoney(current)} to ${brv_fmtMoney(target)}, `
                      + `and ${brv_fmtChange(-moved)} will be added to its adjustment.`
                      + `\n${toCode} (To budget line) will change from ${brv_fmtMoney(toCurrent)} to ${brv_fmtMoney(toCurrent + moved)}, `
                      + `and ${brv_fmtChange(moved)} will be added to its adjustment.`;
        } else if (!v.to_budget_line_item_id) {
            question += `\n\nThe budget line amount will change from ${brv_fmtMoney(current)} to ${brv_fmtMoney(target)}, `
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
// Postgres timestamps arrive as "2026-11-10 08:30:00.123456+08": make that ISO enough for every browser's
// Date(), then show it as "Nov 10, 2026". '' when there is no date.
function brv_fmtDate(ts) {
    if (!ts) return '';
    const iso = String(ts).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d = new Date(iso);
    return isNaN(d) ? String(ts) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
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
// The budget line of a revision row: $pre is '' for the From line, 'to_' for the To line (the row has account_code…, to_account_code…).
// "5-02-010 — Travelling Expenses"; a revision filed before budget lines were revised has none ('—'), a line that has since been
// deleted shows its id.
function brv_lineTitle(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    const code = r[pre + 'account_code'], title = r[pre + 'account_title'];
    if (code) return code + (title ? ' — ' + title : '');
    if (title) return title;
    return id ? `Budget line #${id}` : '—';
}
// "5-02-010 (BP-001)": short enough for a sentence ('' when there is no line)
function brv_lineName(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    if (!id) return '';
    return (r[pre + 'account_code'] || `line #${id}`) + (r[pre + 'budget_reference_number'] ? ` (${r[pre + 'budget_reference_number']})` : '');
}
// "BP-001 · P-100 / PJ-1" — where the line sits, for the list
function brv_linePlace(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    if (!id) return 'Filed before budget lines could be revised';
    if (r[pre + 'fiscal_yr'] === null || r[pre + 'fiscal_yr'] === undefined) return 'This budget line no longer exists';
    return [r[pre + 'budget_reference_number'], [r[pre + 'program_code'], r[pre + 'project_code']].filter(Boolean).join(' / ')].filter(Boolean).join(' · ');
}
// The details card's text, one line each: "BP-001 · Region III Office · FY 2026", "P-100 — Core Shelter › PJ-1 — Resettlement", the
// particulars, and — when approved revisions have changed it — "Proposed ₱ … · adjusted ±₱ …".
function brv_lineSub(r, pre) {
    const id = r[(pre ? 'to' : 'from') + '_budget_line_item_id'];
    if (!id) return 'Filed before budget lines could be revised — pick its budget line when editing it';
    const fy = r[pre + 'fiscal_yr'];
    if (fy === null || fy === undefined) return 'This budget line no longer exists';
    const withName = (code, name) => (code ? code + (name ? ' — ' + name : '') : '');
    const office = r[pre + 'rc_name'] || r[pre + 'rc_code'];
    const adj = Number(r[pre + 'line_adjustment'] || 0);
    return [
        `${r[pre + 'budget_reference_number']}${office ? ' · ' + office : ''} · FY ${fy}`,
        [withName(r[pre + 'program_code'], r[pre + 'program_name']), withName(r[pre + 'project_code'], r[pre + 'project_name'])].filter(Boolean).join(' › ') || 'No project yet',
        r[pre + 'particulars'] || '',
        adj !== 0 ? `Proposed ${brv_fmtMoney(r[pre + 'proposed_amount'])} · adjusted ${brv_fmtChange(adj)}` : '',
    ].filter(Boolean).join('\n');
}
// A budget request's status pill in the status's own colour (settings_status.color); only a plain #RRGGBB colour is put into a
// style, anything else (or no status) falls back to a grey pill.
function brv_paintStatus(el, name, color) {
    el.textContent = name || '—';
    if (name && /^#[0-9A-Fa-f]{6}$/.test(color || '')) {
        el.className = 'nha-bdg';
        el.style.cssText = `color:${color};background:${color}1A;border:1px solid ${color}40;`;
    } else {
        el.className = 'nha-bdg nha-bdg-gray';
        el.style.cssText = '';
    }
}
function brv_statusBadge(code) {
    const s = BRV_STATUS[code] || { label: code || 'Unknown', cls: 'nha-bdg-gray' };
    return `<span class="nha-bdg ${s.cls}">${brv_esc(s.label)}</span>`;
}
// HTML-escapes text for markup AND for attribute values (the Reason's tooltip is one): innerHTML leaves quotes alone, so
// they are escaped here — otherwise a reason containing a double quote could close the attribute and inject its own.
function brv_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
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
