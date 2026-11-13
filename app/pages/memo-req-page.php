<?php

namespace PHPMaker2024\FIMS;

@session_start();
$_mrq_api = Config('API.FIMS_URL');

// Who is looking: the logged-in user's office. uac.users gives the rc_id (that column only) and is joined to
// fims.responsibility_code for the RC itself — the same lookup as budget-req-page.php. The Financial Management
// Department (FMD: rc_code 35-074-0000000-032, rc_id 013 in this data) REVIEWS every office's memo requests, so for its
// users the list is the central one (every office, ?central=1), Edit and Delete are hidden, and each row gets a "Process"
// action (details + Approve / Return, readone/memo-process-readone.php). Everyone else sees their own office's requests —
// the API decides that from the logged-in user, not from anything sent here — and gets Edit and Delete on a Draft request
// (and Edit alone on one the FMD review Returned to them).
// FMD is recognised by its rc_code, NOT its rc_id (the id's format differs between datasets: "013" vs "RC-013"). Mirrors
// GlobalService::CENTRAL_REVIEW_RC_CODE, which the API checks again server-side (process() refuses anyone else).
$userid = CurrentUserID();
$_mrq_fmdRcCode = '35-074-0000000-032';
$_mrq_isFmd = false;
try {
    $_mrq_rc = ExecuteRow(
        'SELECT rc.rc_id, rc.rc_code, rc.rc_name FROM uac.users u '
        .'JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id WHERE u.user_id = '.(int) $userid,
        'DB'
    ) ?: null;
    $_mrq_isFmd = $_mrq_rc !== null && trim((string) ($_mrq_rc['rc_code'] ?? '')) === $_mrq_fmdRcCode;
} catch (\Throwable $e) {
    $_mrq_isFmd = false;   // can't tell who this is -> the ordinary view, never an error page
    LogError('memo-req-page: could not look up the current user\'s RC — '.$e->getMessage());
}
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<?php /* GetUrl() without a leading slash adds the app's base path (the app may live in a sub-folder / virtual directory), and the
          cache-buster is read from the file next to this page, not from DOCUMENT_ROOT (which is not the app folder on every server) —
          so a changed stylesheet is never served from a browser's stale cache. */ ?>
<link rel="stylesheet" href="<?php echo GetUrl('_shared/css/nha-fims.css'); ?>?v=<?php echo @filemtime(dirname(__DIR__, 2).'/_shared/css/nha-fims.css'); ?>" />
<style>
/* ── Actions dropdown menu ──────────────────────────────────── */
/* The same menu as the Budget Requirements list (budget-req-page.php), which keeps its copy inline too. */
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
.nha-menu-item.danger:hover { background:#fdecec;color:#c0392b; }
.nha-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
/* An item tinted with a status colour (settings_status.color), passed in per item as --nha-item-color. */
.nha-menu-item.has-color { color:var(--nha-item-color); }
.nha-menu-item.has-color svg { opacity:1; }
.nha-menu-item.has-color:hover { background:color-mix(in srgb, var(--nha-item-color) 12%, #fff);color:var(--nha-item-color); }
.nha-menu-divider { border-top:1px solid var(--afims-border, #E4ECF2);margin:3px 0; }

/* ── KPI row: only 3 cards here, not the 4 .nha-summary assumes elsewhere — fill the row instead of leaving a
   fourth column empty. */
#mrq-root .nha-summary { grid-template-columns: repeat(3, 1fr); }
@media (max-width: 900px) {
    #mrq-root .nha-summary { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div id="mrq-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Memo Requests</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="mrq-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="mrq-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Create a memo request">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                <span id="mrq-add-label">New CBA Memo Request</span>
            </button>
        </div>
    </div>

    <!-- ── TABS (CBA / RFA / LAA — budget_allocation.allocation_type) ──── -->
    <div class="nha-tabs no-print">
        <button type="button" class="nha-tab active" data-type="CBA" onclick="mrq_switchTab('CBA')">CBA</button>
        <button type="button" class="nha-tab" data-type="RFA" onclick="mrq_switchTab('RFA')">RFA</button>
        <button type="button" class="nha-tab" data-type="LAA" onclick="mrq_switchTab('LAA')">LAA</button>
    </div>

    <!-- ── KPI CARDS (cover every row matching the filters, not just this page) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total Memo Requests</div>
                <div class="nha-sc-val" id="mrq-kpi-total">—</div>
            </div></div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-teal">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total PR Amount</div>
                <div class="nha-sc-val" id="mrq-kpi-pr">—</div>
            </div></div>
            <p class="nha-kpi-sub">Purchase requests raised</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total PO Amount</div>
                <div class="nha-sc-val" id="mrq-kpi-po">—</div>
            </div></div>
            <p class="nha-kpi-sub">Purchase orders issued</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="mrq-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="mrq-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="mrq-filter-search" class="nha-filter-input" placeholder="Search request ref, allocation no., PR / PO no., vendor…">
                    <button class="nha-filter-clear-btn" id="mrq-search-clear" hidden type="button">×</button>
                </div>
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
                        <th>Request Reference Number</th>
                        <th>FY</th>
                        <th>Requesting Office</th>
                        <th>Program / Project</th>
                        <th>Vendor</th>
                        <th>Amount</th>
                        <th>Submitted Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="mrq-tbody">
                    <tr><td colspan="10"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="mrq-from">0</span>–<span id="mrq-to">0</span></strong> of <strong><span id="mrq-total">0</span></strong></span>
            <div id="mrq-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php
// "New Memo Request" opens the CBA form as a modal (window.cbaModal.open()). $cbaAsModal tells the
// form to render inside an overlay modal instead of as a full page — the same file is still the
// standalone page behind the "Memo Request Form" menu item. In modal mode it exposes
// window.cbaModal and calls window.mrq_load() after a successful save, to refresh this list.
$cbaAsModal = true;
include __DIR__.'/form/budget-allocation-form.php';

// The request-details modal: opened read-only by every row's "View" action (everybody), and with the Endorse / Approve / Return
// buttons by FMD's "Process" action (only FMD is offered it, and the API refuses anyone else).
include __DIR__.'/readone/memo-process-readone.php';
?>

</div><!-- /#mrq-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   MEMO REQUESTS LIST   prefix: mrq_
   Backed by BudgetAllocationService → fims.budget_allocation
   (routes in app/routes/budget_allocation.php, modeled on /crud)

   Create  → the CBA form modal (app/pages/form/budget-allocation-form.php, window.cbaModal)
   Edit    → the same form modal opened on that record: window.cbaModal.open(id)
   View    → (Actions menu, every row, every user) the request's details, read-only: the same modal as Process, opened
             without the decision buttons — mpr_open(id, true) in readone/memo-process-readone.php
   Preview → (Actions menu, Draft requests only) PATCH status_id = the 'For Preview' status
   Process → (Actions menu, FMD only — instead of Edit / Delete) the details modal with Endorse / Approve / Return:
             readone/memo-process-readone.php (mpr_*), PATCH /budget_allocation/process/{id}. The review is a chain:
             For Preview (FMD Section Chief) -> For Division Manager (FMD Division Manager) -> For Department Manager
             (FMD Department Manager) -> Approved; Return at any step. The buttons show only for the user level whose
             step it is. Every Return needs REMARKS (typed in the modal, saved on budget_allocation.remarks); a request
             that is back with someone shows the last return's remarks at the top of its View / Process modal.

   The reference shown for every row ("Request Reference Number") is budget_allocation.request_referrence_no — RR-001,
   RR-002, … generated by the database when the row is created. The search box matches it too.
═══════════════════════════════════════════════════════════════ */

const MRQ_BASE = '<?php echo $_mrq_api; ?>';
const MRQ_API  = MRQ_BASE + '/budget_allocation';
// true for the Financial Management Department (see the PHP at the top): the central list, no Edit / Delete, a Process action.
const MRQ_IS_FMD = <?php echo $_mrq_isFmd ? 'true' : 'false'; ?>;

let mrq_page = 1;
let mrq_rows = [];
const MRQ_PAGE_SIZE = 15;

// Which allocation_type the list (and KPI cards) are scoped to. budget_allocation holds all three kinds of memo
// request in one table (see form/budget-allocation-form.php's "Allocation Type" select) — the tabs just filter the
// same /budget_allocation/list by it. Starts from ?type= in the page URL so a link such as
// memo-req-page?type=RFA opens straight onto that tab; kept in sync with the URL (replaceState, no new history
// entries) so "New Memo Request" — which reads window.location.search itself, see budget-allocation-form.php's
// cbaBoot() — pre-selects the matching Allocation Type without any extra wiring.
const MRQ_TYPES = ['CBA', 'RFA', 'LAA'];
let mrq_activeType = (() => {
    const t = (new URLSearchParams(window.location.search).get('type') || '').toUpperCase();
    return MRQ_TYPES.includes(t) ? t : 'CBA';
})();

// Set once the Fiscal Year filter has been defaulted to the current year (see mrq_fillFilters) — so a later
// refill (e.g. mrq_statusId re-reading statuses) never overrides a year the user has since picked themselves.
let mrq_fyDefaulted = false;

// Loaded once at start-up; they fill the two filter dropdowns.
const MRQ_LOOKUPS = { fiscalYears: [], statuses: [] };
const MRQ_LOOKUP_SOURCES = {
    fiscalYears : `${MRQ_BASE}/fiscal_year/all`,
    statuses    : `${MRQ_API}/statuses`,
};

// The two statuses the "Preview" action moves a request between (names of memo-request statuses in
// fims.settings_status; their ids are looked up, never hardcoded). 'For Preview' is seeded by
// sample_data/06 - fims_settings_status_budget_allocation.sql, the same status the form's "Submit for Preview" gives.
const MRQ_STATUS_DRAFT    = 'Draft';
const MRQ_STATUS_PREVIEW  = 'For Preview';
const MRQ_STATUS_RETURNED = 'Returned';   // back with the requesting office (see mrq_isReturned())

const $mrq = id => document.getElementById(id);

// Status names are compared ignoring case and surrounding spaces.
function mrq_sameStatus(a, b) { return String(a || '').trim().toLowerCase() === String(b || '').trim().toLowerCase(); }
// Only a request that is still a Draft can be sent on for preview.
function mrq_isDraft(r)       { return mrq_sameStatus(r.status_name, MRQ_STATUS_DRAFT); }
// A request the FMD review sent back to the requesting office (status exactly "Returned" — NOT "Returned to Section Chief" /
// "Returned to Division Manager", which are with FMD): its office can Edit it again, and the form's Save dialog offers
// "Submit for Preview" to send it on once corrected.
function mrq_isReturned(r)    { return mrq_sameStatus(r.status_name, MRQ_STATUS_RETURNED); }
// A status's colour (settings_status.color, from the statuses loaded at start-up), or '' when it has none, is not loaded,
// or is not a plain hex colour — nothing else may be put into a style attribute.
function mrq_statusColor(name) {
    const c = (MRQ_LOOKUPS.statuses.find(s => mrq_sameStatus(s.name, name)) || {}).color || '';
    return /^#[0-9a-fA-F]{3,8}$/.test(c) ? c : '';
}

async function mrq_bootstrapLoad() {
    window.PagePreloader?.show();
    try {
        await mrq_loadLookups();
    } finally {
        window.PagePreloader?.hide();
    }
    mrq_bindEvents();
    mrq_syncTabUrl();   // the tab the page opened on (default or ?type=) belongs in the URL from the start
    await mrq_load();
}

/* ── Tabs (CBA / RFA / LAA) ───────────────────────────────────── */
// Keeps ?type= on the page URL equal to mrq_activeType, without adding a history entry — so Back/Forward
// doesn't step through tabs, but a refresh (or a copied link) reopens the same one.
function mrq_syncTabUrl() {
    document.querySelectorAll('.nha-tabs .nha-tab').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.type === mrq_activeType);
    });
    const addLabel = $mrq('mrq-add-label');
    if (addLabel) addLabel.textContent = `New ${mrq_activeType} Memo Request`;
    const url = new URL(window.location.href);
    url.searchParams.set('type', mrq_activeType);
    window.history.replaceState(window.history.state, '', url);
}

function mrq_switchTab(type) {
    if (!MRQ_TYPES.includes(type) || type === mrq_activeType) return;
    mrq_activeType = type;
    mrq_syncTabUrl();
    mrq_page = 1;
    mrq_load();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mrq_bootstrapLoad);
} else {
    mrq_bootstrapLoad();
}

/* ── HTTP ──────────────────────────────────────────────────── */
// Resolves with the parsed body on { success: true }; otherwise throws the
// server's message (the API answers 400 / 404 with { success:false, message }).
async function mrq_request(url, options = {}) {
    const res = await fetch(url, { credentials: 'same-origin', ...options });
    let json = null;
    try { json = await res.json(); } catch (_) { /* non-JSON body */ }
    if (!res.ok || !json?.success) {
        throw new Error(json?.message || `Request failed (HTTP ${res.status}).`);
    }
    return json;
}

/* ── Lookups ───────────────────────────────────────────────── */
async function mrq_loadLookups() {
    const keys    = Object.keys(MRQ_LOOKUP_SOURCES);
    const results = await Promise.allSettled(keys.map(k => mrq_request(MRQ_LOOKUP_SOURCES[k])));
    results.forEach((r, i) => {
        if (r.status === 'fulfilled') {
            MRQ_LOOKUPS[keys[i]] = r.value.data ?? [];
        } else {
            mrq_toast(`Could not load ${keys[i]}: ${r.reason.message}`, 'error');
        }
    });
    mrq_fillFilters();
}

function mrq_optionsHtml(placeholder, rows, valueOf, labelOf) {
    return `<option value="">${mrq_esc(placeholder)}</option>` +
        rows.map(r => `<option value="${mrq_esc(valueOf(r))}">${mrq_esc(labelOf(r))}</option>`).join('');
}

// Rebuilds both filter dropdowns from MRQ_LOOKUPS and keeps what is selected — it is also called when a lookup is re-read
// after start-up (see mrq_statusId), which must not reset the filters the user has set.
function mrq_fillFilters() {
    const fy = $mrq('mrq-filter-fy').value;
    const st = $mrq('mrq-filter-status').value;
    $mrq('mrq-filter-fy').innerHTML     = mrq_optionsHtml('All Years',  MRQ_LOOKUPS.fiscalYears, f => f.fiscal_yr, f => f.fiscal_yr);
    $mrq('mrq-filter-status').innerHTML = mrq_optionsHtml('All Status', MRQ_LOOKUPS.statuses,    s => s.id,        s => s.name);
    $mrq('mrq-filter-fy').value     = fy;
    $mrq('mrq-filter-status').value = st;

    // Default the Fiscal Year filter to the current year — once, the first time it's populated, and only if the
    // user hasn't already picked a year (fy was still empty) and the current year is actually one of the options.
    if (!mrq_fyDefaulted) {
        mrq_fyDefaulted = true;
        if (!fy) {
            const currentYear = String(new Date().getFullYear());
            const hasCurrentYear = MRQ_LOOKUPS.fiscalYears.some(f => String(f.fiscal_yr) === currentYear);
            if (hasCurrentYear) $mrq('mrq-filter-fy').value = currentYear;
        }
    }
}

/* ── Events ────────────────────────────────────────────────── */
function mrq_bindEvents() {
    $mrq('mrq-refresh')?.addEventListener('click', () => { mrq_page = 1; mrq_load(); });
    $mrq('mrq-add')?.addEventListener('click', mrq_new);

    ['mrq-filter-fy', 'mrq-filter-status'].forEach(id => {
        $mrq(id)?.addEventListener('change', () => { mrq_page = 1; mrq_load(); });
    });

    let searchTimer;
    const searchEl = $mrq('mrq-filter-search');
    const clearBtn = $mrq('mrq-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { mrq_page = 1; mrq_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        mrq_page = 1; mrq_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function mrq_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search          : $mrq('mrq-filter-search')?.value || '',
            fiscal_year     : $mrq('mrq-filter-fy')?.value     || '',
            status_id       : $mrq('mrq-filter-status')?.value || '',
            allocation_type : mrq_activeType,
            page            : mrq_page,
            limit           : MRQ_PAGE_SIZE,
        });
        // FMD reviews every office's requests: ask for the central list. The API only grants it to a user of the FMD
        // office (the parameter alone grants nothing), and gives everybody else just their own office's requests.
        if (MRQ_IS_FMD) params.set('central', '1');
        const json = await mrq_request(`${MRQ_API}/list?${params}`);

        mrq_rows = json.data ?? [];
        mrq_renderTable(mrq_rows);
        mrq_renderPagination(json.pagination ?? {});
        $mrq('mrq-kpi-total').textContent = json.pagination?.total ?? mrq_rows.length;
        $mrq('mrq-kpi-pr').textContent    = mrq_money(json.summary?.total_pr_amount);
        $mrq('mrq-kpi-po').textContent    = mrq_money(json.summary?.total_po_amount);
    } catch (e) {
        mrq_rows = [];
        mrq_renderTable([]);
        mrq_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function mrq_money(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// status_color comes from settings_status — only a plain hex colour is allowed
// into the inline style.
function mrq_statusBadge(name, color) {
    if (!name) return '—';
    const safe = /^#[0-9a-fA-F]{3,8}$/.test(color || '') ? color : '#6B7280';
    return `<span class="nha-bdg" style="color:${safe};background:color-mix(in srgb, ${safe} 14%, #fff);">${mrq_esc(name)}</span>`;
}

// The reference a row is known by: the database-generated request reference (RR-001, …) — the only one there is
// (memo_ref_no was dropped, alter_table/20).
function mrq_refOf(r)       { return r.request_referrence_no || ''; }
function mrq_parentRefOf(r) { return r.parent_request_referrence_no || ''; }

// A Postgres timestamp ("2026-11-10 08:30:00.123456+08") as "Nov 10, 2026" with the time underneath; '—' when empty or
// not a date. Made ISO enough for every browser first (T separator, milliseconds only, "+08" -> "+08:00").
function mrq_dateCell(value) {
    if (!value) return '—';
    const iso = String(value).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d   = new Date(iso);
    if (isNaN(d)) return mrq_esc(value);
    return `<div style="white-space:nowrap;">${d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })}</div>` +
           `<div class="nha-td-sub" style="margin-top:2px;">${d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' })}</div>`;
}

function mrq_renderTable(rows) {
    const tbody = $mrq('mrq-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="10"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No memo requests found.</span></div></td></tr>';
        return;
    }
    const offset = (mrq_page - 1) * MRQ_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const id = Number(r.allocation_id);
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>
            <code class="nha-code">${mrq_esc(mrq_refOf(r))}</code>
            ${r.allocation_number ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.allocation_number)}</div>` : ''}
            <div style="margin-top:3px;display:flex;gap:4px;flex-wrap:wrap;">
                ${r.allocation_type ? `<span class="nha-bdg nha-bdg-blue">${mrq_esc(r.allocation_type)}</span>` : ''}
                ${r.document_type   ? `<span class="nha-bdg nha-bdg-gray">${mrq_esc(r.document_type)}</span>` : ''}
                ${mrq_truthy(r.is_bidding) ? '<span class="nha-bdg nha-bdg-purple">Bidding</span>' : ''}
            </div>
            ${mrq_parentRefOf(r) ? `<div class="nha-td-sub" style="margin-top:3px;">↳ ${mrq_esc(mrq_parentRefOf(r))}</div>` : ''}
        </td>
        <td>${mrq_esc(r.fiscal_year)}</td>
        <td>
            <div>${mrq_esc(r.requesting_rc_name || '—')}</div>
            ${r.requesting_district_name ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.requesting_district_name)}</div>` : ''}
        </td>
        <td>
            <div>${mrq_esc(r.program_name || '—')}</div>
            ${r.sub_program_name ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.sub_program_name)}</div>` : ''}
            ${r.project_name ? `<div class="nha-td-sub" style="margin-top:2px;">${mrq_esc(r.project_name)}</div>` : ''}
        </td>
        <td>${mrq_esc(r.vendor_name || '—')}</td>
        <td style="white-space:nowrap;">${mrq_money(r.requested_total)}</td>
        <td>${mrq_dateCell(r.created_at)}</td>
        <td>${mrq_statusBadge(r.status_name, r.status_color)}</td>
        <td>${mrq_actionMenu(id, mrq_isDraft(r), mrq_isReturned(r))}</td>
    </tr>`;
    }).join('');
}

/* ── Actions dropdown ──────────────────────────────────────── */
// One "Actions" button per row that opens a small menu — the same idea as the Budget Requirements list. Sections, with a
// divider between each one that is shown:
//   View     — the request's details, read-only: on every row, for every user;
//   Preview  — only while the request is still a Draft: it moves it on to For Preview;
//   Edit and Delete — only while the request is a Draft (once it has left Draft it can no longer be changed or removed
//              by the requesting office); FMD never gets them;
//   Edit     — also on a request that was Returned to the requesting office (Delete stays Draft-only);
//   Process  — FMD only (in place of Edit and Delete), on every row.
// The menu is positioned with fixed coordinates, so the table's own scrolling never clips it.
function mrq_actionMenu(id, isDraft, isReturned = false) {
    // View: the details modal without its decision buttons (see readone/memo-process-readone.php).
    const viewItem = `<button type="button" class="nha-menu-item" data-action="view" title="See the details of this memo request" onclick="mrq_closeMenus();mpr_open(${id}, true);">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                View
            </button>`;

    // Preview is tinted with the colour of the status it gives (For Preview's settings_status.color); Edit and Delete
    // keep the plain look. With no usable colour it is a plain item too.
    const color = isDraft ? mrq_statusColor(MRQ_STATUS_PREVIEW) : '';
    const previewItem = !isDraft ? '' : `<button type="button" class="nha-menu-item${color ? ' has-color' : ''}"${color ? ` style="--nha-item-color:${color};"` : ''} data-action="preview" title="Submit for preview: its status becomes ${MRQ_STATUS_PREVIEW}" onclick="mrq_closeMenus();mrq_preview(${id});">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Preview
            </button>`;

    // FMD REVIEWS requests instead of editing them: Edit and Delete are hidden for it and each row gets "Process" (a modal
    // with the request's details and Approve / Return — readone/memo-process-readone.php). Everybody else gets Edit and
    // Delete, but only on a Draft row — and a Returned row gets Edit alone.
    const processItem = `<button type="button" class="nha-menu-item" data-action="process" title="Review this request: approve it or return it" onclick="mrq_closeMenus();mpr_open(${id});">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                Process
            </button>`;
    const editItem = `<button type="button" class="nha-menu-item" data-action="edit" onclick="mrq_closeMenus();mrq_edit(${id});">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>`;
    const deleteItem = `<button type="button" class="nha-menu-item danger" data-action="delete" onclick="mrq_closeMenus();mrq_delete(${id});">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>`;
    const editAndDelete = `${editItem}<div class="nha-menu-divider"></div>${deleteItem}`;
    // the sections that apply to this row, a divider between each — so a row never ends (or starts) with a stray divider
    const sections = [viewItem, previewItem, MRQ_IS_FMD ? processItem : (isDraft ? editAndDelete : (isReturned ? editItem : ''))]
        .filter(Boolean)
        .join('<div class="nha-menu-divider"></div>');
    return `<div class="nha-tools-menu">
        <button type="button" class="nha-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="mrq_toggleMenu(this)">
            <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
            Actions
        </button>
        <div class="nha-tools-dropdown">
            ${sections}
        </div>
    </div>`;
}

// Opens this row's menu (closing any other one); a second click on the same button closes it again.
function mrq_toggleMenu(btn) {
    const drop   = btn.closest('.nha-tools-menu').querySelector('.nha-tools-dropdown');
    const isOpen = drop.classList.contains('open');
    mrq_closeMenus();
    if (isOpen) return;

    const rect = btn.getBoundingClientRect();
    drop.style.top   = (rect.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - rect.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}

function mrq_closeMenus() {
    document.querySelectorAll('.nha-tools-dropdown.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.nha-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}

document.addEventListener('click', e => {
    if (!e.target.closest('.nha-tools-menu')) mrq_closeMenus();
});
document.addEventListener('keydown', e => { if (e.key === 'Escape') mrq_closeMenus(); });
document.addEventListener('scroll', mrq_closeMenus, true);

function mrq_truthy(v) { return v === true || v === 1 || v === '1' || v === 't' || v === 'true'; }

/* ── Pagination ────────────────────────────────────────────── */
function mrq_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = MRQ_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $mrq('mrq-from').textContent  = from;
    $mrq('mrq-to').textContent    = to;
    $mrq('mrq-total').textContent = total;

    const container = $mrq('mrq-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="mrq_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="mrq_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="mrq_goTo(${current_page + 1})">›</button>`;
}

function mrq_goTo(page) { if (page >= 1) { mrq_page = page; mrq_load(); } }

/* ── Create / edit ─────────────────────────────────────────── */
// New: the CBA form pops up as a modal. window.cbaModal comes from the include above.
function mrq_new() {
    if (!window.cbaModal) {
        mrq_toast('The memo request form could not be loaded. Please refresh the page.', 'error');
        return;
    }
    window.cbaModal.open();
}

// Edit: the same form modal as "New", opened on that record — window.cbaModal.open(id) loads it and saves it back. It used to
// navigate to the full-page BudgetAllocationForm, a separate page with its own permission, so a user who can create (the
// modal) got "no permission" on Edit.
function mrq_edit(id) {
    if (!window.cbaModal) {
        mrq_toast('The memo request form could not be loaded. Please refresh the page.', 'error');
        return;
    }
    window.cbaModal.open(id);
}

/* ── Preview: Draft -> For Preview ─────────────────────────── */
// The id of the memo-request status called `name`. It is looked up in the statuses loaded at start-up; when it is not
// there they are read again once (it may have been added since the page opened). Throws when it still isn't set up.
async function mrq_statusId(name) {
    const find = () => MRQ_LOOKUPS.statuses.find(s => mrq_sameStatus(s.name, name));
    let hit = find();
    if (!hit) {
        const json = await mrq_request(MRQ_LOOKUP_SOURCES.statuses);
        MRQ_LOOKUPS.statuses = json.data ?? [];
        mrq_fillFilters();   // the Status filter now offers it too (what is selected stays selected)
        hit = find();
    }
    if (!hit) {
        throw new Error(`The status "${name}" is not set up in the database yet, so nothing was changed. ` +
            'Ask an administrator to run the SQL installer (node install_system.js --sql-only).');
    }
    return Number(hit.id);
}

// "Preview" in a Draft row's Actions menu: after a confirmation, sets the request's status to For Preview — the same
// status the memo form's "Submit for Preview" gives — and refreshes the list.
async function mrq_preview(id) {
    const row = mrq_rows.find(r => Number(r.allocation_id) === Number(id));
    if (!row || !mrq_isDraft(row)) {
        mrq_toast(`Only a ${MRQ_STATUS_DRAFT} request can be submitted for preview.`, 'warning');
        return;
    }
    const ref = mrq_refOf(row) ? `"${mrq_refOf(row)}"` : `#${id}`;
    if (!confirm(`Submit memo request ${ref} for preview? Its status will change from ${MRQ_STATUS_DRAFT} to ${MRQ_STATUS_PREVIEW}.`)) return;

    window.PagePreloader?.show();
    try {
        // looked up first: when the status is missing this throws and nothing has been changed
        const statusId = await mrq_statusId(MRQ_STATUS_PREVIEW);
        await mrq_request(`${MRQ_API}/update/${encodeURIComponent(id)}`, {
            method : 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ status_id: statusId }),
        });
        mrq_toast(`Memo request ${ref} submitted for preview.`, 'success');
        mrq_load();
    } catch (e) {
        mrq_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Delete (soft) ─────────────────────────────────────────── */
async function mrq_delete(id) {
    const row = mrq_rows.find(r => Number(r.allocation_id) === Number(id));
    const ref = row && mrq_refOf(row) ? `"${mrq_refOf(row)}"` : `#${id}`;
    if (!confirm(`Delete memo request ${ref}? It will no longer appear in the list.`)) return;

    window.PagePreloader?.show();
    try {
        await mrq_request(`${MRQ_API}/delete/${encodeURIComponent(id)}`, { method: 'DELETE' });
        mrq_toast('Memo request deleted successfully.', 'success');
        // deleting the last row of a page would leave an empty page behind
        if (mrq_rows.length === 1 && mrq_page > 1) mrq_page--;
        mrq_load();
    } catch (e) {
        mrq_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function mrq_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}

// SweetAlert2 toast — same convention as _shared/notification.php's Swal.fire({toast:true, ...}) calls.
// Loaded globally by views/layout.php (js/sweetalert2.all.min.js), so Swal is always available here.
function mrq_toast(msg, type = 'info') {
    const icons = { success: 'success', error: 'error', warning: 'warning', info: 'info' };
    Swal.fire({
        icon: icons[type] || icons.info,
        text: msg,                 // text, not html — messages may contain server text
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
    });
}
</script>
