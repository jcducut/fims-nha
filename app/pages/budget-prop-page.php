<?php

namespace PHPMaker2024\FIMS;

/**
 * budget-prop-page.php — "Budget Proposal": the Financial Management Department's list of the budget requests
 * of every Regional / District office, one row per request:
 *
 *   Submission Ref No. | Region / Originating Unit | Submitted Date | Amount | Status | (row actions)
 *
 * The list comes from its own API route, GET /fmd/budget_req/list (app/routes/fmd_budget_req.php), which
 * decides the scope on the server — only users of the central review RC (FMD, by rc_code) get an answer, and it is
 * always every Regional / District office — so this page sends no RC parameters and does no RC lookup of its own.
 * Anyone else who opens the page gets that route's 403 message and an empty list.
 *
 * Row actions act on the whole request (Budget Processing for user level 2, Budget Preview for level 8,
 * Delete of a Draft). They open the shared modals from form/budget-processing-form.php and
 * readone/budget-preview-readone.php (same layout; the Budget Head's Return reason is typed in the preview's own
 * remarks box), which rely on the helpers (and fill the Fiscal Year / Program filters) of
 * form/budget-req-form.php — included below for that, its Add modal is simply not used here.
 */
@session_start();
$_bgr_api = Config('API.FIMS_URL');

// User level 2 = Budget Officer: Delete is hidden for this level, and the per-row "Budget Processing" action is
// shown only to this level.
// User level 8 = Budget Head: only this level sees the "Budget Preview" action (whose modal has Return / Approve
// buttons on an Endorsed request).
$userLevel = CurrentUserLevel();
$_bgr_isBudgetOfficer = ((int) $userLevel === 2);
$_bgr_isBudgetHead = ((int) $userLevel === 8);
$_bgr_canModify = !$_bgr_isBudgetOfficer;
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?php echo @filemtime($_SERVER['DOCUMENT_ROOT'].'/_shared/css/nha-fims.css'); ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .bgr-summary-2col { grid-template-columns: repeat(2, 1fr); }
}

/* ── Actions dropdown menu ──────────────────────────────────── */
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
    border:none;background:none;width:100%;text-align:left;transition:background .1s; }
.nha-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.nha-menu-item.danger:hover { background:#fdecec;color:#c0392b; }
.nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
.nha-menu-divider { border-top:1px solid var(--afims-border, #E4ECF2);margin:3px 0; }
</style>

<div id="bgr-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Budget Proposal</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="bgr-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary bgr-summary-2col">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Requests</div>
                    <div class="nha-sc-val" id="bgr-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Amount</div>
                    <div class="nha-sc-val" id="bgr-kpi-amount">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="bgr-filter-fiscal-yr" class="nha-filter-select">
                    <option value="">All Years</option>
                    <option value="<?php echo date('Y'); ?>" selected><?php echo date('Y'); ?></option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Program</label>
                <select id="bgr-filter-program" class="nha-filter-select">
                    <option value="">All</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead id="bgr-thead">
                    <tr>
                        <th>Submission Ref No.</th>
                        <th>Region / Originating Unit</th>
                        <th>Submitted Date</th>
                        <th class="text-end">Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="bgr-tbody">
                    <tr><td colspan="6"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="bgr-from">0</span>–<span id="bgr-to">0</span></strong> of <strong><span id="bgr-total">0</span></strong></span>
            <div id="bgr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__.'/form/budget-req-form.php'; ?>
<?php include __DIR__.'/form/budget-processing-form.php'; ?>
<?php include __DIR__.'/readone/budget-preview-readone.php'; ?>

</div><!-- /#bgr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET PROPOSAL LIST   prefix: bgr_ (the shared modals and form/budget-req-form.php expect it)
   Fetches the FMD's list from GET /fmd/budget_req/list — see the header comment of this file — and shows
   one row per budget request: Submission Ref No. (budget_reference_number, the fiscal year under it),
   Region / Originating Unit (the requesting office and the Region it belongs to), Submitted Date
   (budget_req.submitted_at — the date of the request's latest save / submission: stamped when it is created, when a
   Program is added or edited, and when it is submitted for review; "—" only for requests older than the column), Amount (the sum of the
   request's line items' proposed amounts, li_total) and Status. The row actions act on that whole request.
═══════════════════════════════════════════════════════════════ */

let bgr_page = 1;
const BGR_PAGE_SIZE = 15;
const BGR_LIST_API = '<?php echo $_bgr_api; ?>' + '/fmd/budget_req/list';
// false for user level 2 (Budget Officer) — hides the row Delete action.
const BGR_CAN_MODIFY = <?php echo $_bgr_canModify ? 'true' : 'false'; ?>;
// true only for user level 2 (Budget Officer) — shows the row "Budget Processing" action.
const BGR_CAN_BUDGET_PROCESSING = <?php echo $_bgr_isBudgetOfficer ? 'true' : 'false'; ?>;
// true only for user level 8 (Budget Head) — shows the row "Budget Preview" action,
// and Return / Approve inside its modal.
const BGR_IS_BUDGET_HEAD = <?php echo $_bgr_isBudgetHead ? 'true' : 'false'; ?>;
let bgr_flatRows = [];   // [{ header, program }] of the rows on screen, for the row actions

window.bgr_afterSave = () => bgr_load();

async function bgr_bootstrapLoad() {
    await bgr_load();
    bgr_bindListEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_bootstrapLoad);
} else {
    bgr_bootstrapLoad();
}

function bgr_bindListEvents() {
    $bgr('bgr-refresh')?.addEventListener('click', () => { bgr_page = 1; bgr_load(); });

    ['bgr-filter-fiscal-yr', 'bgr-filter-program'].forEach(id => {
        $bgr(id)?.addEventListener('change', () => { bgr_page = 1; bgr_load(); });
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function bgr_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            fiscal_yr   : $bgr('bgr-filter-fiscal-yr')?.value || '',
            program_code: $bgr('bgr-filter-program')?.value || '',
            page        : bgr_page,
            limit       : BGR_PAGE_SIZE,
        });

        const res  = await fetch(`${BGR_LIST_API}?${params}`, { credentials: 'same-origin' });
        // Read the body even on an error status: the route answers 403 + a message for anyone who is not
        // the Financial Management Department, and that message is more useful than a bare "HTTP 403".
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error(json?.message || 'HTTP ' + res.status);

        bgr_renderTable(json.data ?? []);
        bgr_renderPagination(json.pagination ?? {});
        $bgr('bgr-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $bgr('bgr-kpi-amount').textContent = bgr_fmtMoney(json.total_amount ?? 0);
    } catch (e) {
        bgr_renderTable([]);
        bgr_renderPagination({});
        $bgr('bgr-kpi-total').textContent  = 0;
        $bgr('bgr-kpi-amount').textContent = bgr_fmtMoney(0);
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// Postgres timestamps arrive as "2026-11-10 08:30:00.123456+08": make that ISO enough for every browser's
// Date(), then show it as "Nov 10, 2026". '' when there is no date.
function bgr_fmtDate(value) {
    if (!value) return '';
    const iso = String(value).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d = new Date(iso);
    return isNaN(d) ? String(value) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

/* ── Table — one row per request ──────────────────────────────────── */
function bgr_renderTable(headers) {
    const tbody = $bgr('bgr-tbody');
    // `program` only tells the row actions whether the request has any Programs yet; the Budget Processing /
    // Budget Preview modals are both handed the request's real Programs (header.programs).
    bgr_flatRows = headers.map(header => ({ header, program: (header.programs ?? [])[0] ?? null }));

    if (!bgr_flatRows.length) {
        tbody.innerHTML = '<tr><td colspan="6"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget requests found.</span></div></td></tr>';
        return;
    }

    tbody.innerHTML = bgr_flatRows.map((flatRow, idx) => {
        const h = flatRow.header;
        const submitted = bgr_fmtDate(h.submitted_at);
        return `<tr>
            <td>
                <code class="nha-code">${bgr_esc(h.budget_reference_number)}</code>
                <div class="nha-td-sub">FY ${bgr_esc(h.fiscal_yr)}</div>
            </td>
            <td>
                <div class="nha-td-bold">${bgr_esc(h.rc_name || h.rc_id || '—')}${h.rc_code ? ` <code class="nha-code">${bgr_esc(h.rc_code)}</code>` : ''}</div>
                ${h.rc_parent_name ? `<div class="nha-td-sub">${bgr_esc(h.rc_parent_name)}</div>` : ''}
            </td>
            <td>${submitted ? `<span class="nha-td-date">${bgr_esc(submitted)}</span>` : '<span class="nha-td-sub">—</span>'}</td>
            <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(h.li_total)}</div></td>
            <td>${bgr_statusBadge(h.status_name, h.status_color)}</td>
            <td>${bgr_actionButtons(flatRow, idx)}</td>
        </tr>`;
    }).join('');
}

/* ── Row actions — all act on the whole request ───────────────────── */
function bgr_actionButtons(flatRow, idx) {
    const { header, program } = flatRow;
    const isDraft = header.status_name === 'Draft';

    // Hidden once the request has been Endorsed or Approved — it can't be processed again.
    const budgetProcessingItem = (BGR_CAN_BUDGET_PROCESSING && !['Endorsed', 'Approved'].includes(header.status_name))
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_budgetProcessing(${idx});bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
            Budget Processing
        </a>`
        : '';
    // Read-only counterpart of Budget Processing — every line item with its
    // Type of Fund, Suggested Amount and Component, nothing to fill in.
    // Budget Head (level 8) only.
    const budgetPreviewItem = (BGR_IS_BUDGET_HEAD && program)
        ? `<a class="nha-menu-item" href="javascript:void(0);" onclick="bgr_budgetPreview(${idx});bgr_closeMenus();">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            Budget Preview
        </a>`
        : '';
    const deleteItem = (BGR_CAN_MODIFY && isDraft)
        ? `<a class="nha-menu-item danger" href="javascript:void(0);" onclick="bgr_closeMenus();bgr_delete('${header.budget_reference_number}', ${header.fiscal_yr});">
            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            Delete
        </a>`
        : '';

    const items = [budgetProcessingItem, budgetPreviewItem].filter(Boolean).join('');
    const deleteSection = deleteItem ? `<div class="nha-menu-divider"></div>${deleteItem}` : '';

    if (!items && !deleteSection) return '';

    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" onclick="bgr_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">${items}${deleteSection}</div>
    </div>`;
}

/* ── Actions dropdown — open/close (closes others, positions via fixed
   coords so the table's own overflow never clips it) ─────────────── */
function bgr_toggleMenu(btn) {
    const drop = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    bgr_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.style.top = (rect.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
    drop.classList.add('open');
}

function bgr_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) bgr_closeMenus();
});
document.addEventListener('scroll', bgr_closeMenus, true);

/* "Budget Processing" row action (level 2 / Budget Officer only) — opens the
   Budget Processing modal (form/budget-processing-form.php, bpr_*): one card per Program,
   a section per Project in it with its line-item table (Type of Fund, Program Sources,
   Program Uses, Component, account, Amount, Suggested Amount), a Subtotal per Project and a
   Grand Total. The row is a whole request, so the modal gets ALL of its Programs. */
function bgr_budgetProcessing(idx) {
    const { header } = bgr_flatRows[idx];
    const programs = header.programs ?? [];
    if (!programs.length) {
        bgr_toast('This budget request has no programs yet.', 'warning');
        return;
    }
    bpr_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        // the requesting office and when it was submitted, for the modal's "Originating Unit" / "Date Submitted"
        rc_id: header.rc_id,
        rc_code: header.rc_code,
        rc_name: header.rc_name,
        submitted_at: header.submitted_at,
    }, programs);
}

/* "Budget Preview" row action (level 8 / Budget Head only) — opens the read-only Budget Preview modal
   (readone/budget-preview-readone.php, bpv_*): the same columns as the Budget Processing one, one block per
   Project (info card + line-item table). The row is a whole request, so the modal gets ALL of its Programs. */
function bgr_budgetPreview(idx) {
    const { header } = bgr_flatRows[idx];
    const programs = header.programs ?? [];
    if (!programs.length) return;
    bpv_openModal({
        budget_reference_number: header.budget_reference_number,
        fiscal_yr: header.fiscal_yr,
        status_name: header.status_name,
        status_color: header.status_color,
        // the requesting office, for the modal's "Originating Unit / Region"
        rc_id: header.rc_id,
        rc_code: header.rc_code,
        rc_name: header.rc_name,
        // Return / Approve (+ the remarks box): Budget Head only, and only once the request is Endorsed.
        can_decide: BGR_IS_BUDGET_HEAD && header.status_name === 'Endorsed',
    }, programs);
}

/* ── Pagination ────────────────────────────────────────────── */
function bgr_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = BGR_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $bgr('bgr-from').textContent  = from;
    $bgr('bgr-to').textContent    = to;
    $bgr('bgr-total').textContent = total;

    const container = $bgr('bgr-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="bgr_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="bgr_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="bgr_goTo(${current_page + 1})">›</button>`;
}

function bgr_goTo(page) { bgr_page = page; bgr_load(); }

async function bgr_delete(id, fiscalYr) {
    if (!confirm(`Delete the entire budget request for fiscal year ${fiscalYr}? This removes ALL its programs and cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${BGR_API}/delete/${id}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete budget request.');

        bgr_toast('Budget request deleted successfully.', 'success');
        bgr_load();
    } catch (e) {
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}
</script>
