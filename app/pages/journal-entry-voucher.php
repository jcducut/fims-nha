<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Journal Entry Voucher register — read-only. A JEV is only ever created/edited from within its
// DV's Accounting stage (see app/pages/voucher/readone.php's "Journal Entry" section +
// DisbursementVoucherService::saveJournalEntry()); this page is a ledger/listing of every JEV
// prepared so far, each linking back to its DV. Data comes from the REST API
// (GET /voucher/journal-entries in app/routes/voucher.php).
$_jel_api  = rtrim((string) Config('API.FIMS_URL'), '/');
$_jel_api  = preg_match('~/api$~', $_jel_api) ? $_jel_api : $_jel_api . '/api';
// Voucher.php's own view modal is what actually renders a JEV's lines — this page's "View" action
// just links there instead of duplicating that detail view.
$_jel_voucherView = 'Voucher';
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="jel-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Journal Entry Vouchers</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="jel-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
        </div>
    </div>

    <p class="nha-hint no-print" style="margin:0 0 14px;color:var(--afims-text-muted,#62657E);font-size:13px;">
        A Journal Entry is prepared by Accounting while processing a Disbursement Voucher — this is a read-only register of every one saved so far. Click a row to open its voucher.
    </p>

    <!-- Error banner -->
    <div id="jel-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="jel-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="jel-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="jel-filter-search" class="nha-filter-input" placeholder="JEV number, DV number, claimant…">
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
                        <th>JEV Number</th>
                        <th>JEV Date</th>
                        <th>DV Number</th>
                        <th>Claimant</th>
                        <th>Fiscal Year</th>
                        <th class="text-end">Total Debit</th>
                        <th class="text-end">Total Credit</th>
                        <th>DV Status</th>
                        <th class="no-print">Print</th>
                    </tr>
                </thead>
                <tbody id="jel-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="jel-from">0</span>–<span id="jel-to">0</span></strong> of <strong><span id="jel-total">0</span></strong></span>
            <div id="jel-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

</div><!-- /#jel-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   JOURNAL ENTRY VOUCHER REGISTER   prefix: jel_
═══════════════════════════════════════════════════════════════ */

const JEL_BASE = <?= json_encode($_jel_api) ?>;
const JEL_API  = JEL_BASE + '/voucher';
const JEL_VOUCHER_VIEW = <?= json_encode($_jel_voucherView) ?>;

const JEL_PAGE_SIZE = 15;
let jel_page = 1;
let jel_searchTimer = null;

const $jel = id => document.getElementById(id);

function jel_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function jel_boot() {
    await jel_loadFiscalYears();
    jel_bindEvents();
    jel_load();
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', jel_boot);
else jel_boot();

function jel_bindEvents() {
    $jel('jel-refresh')?.addEventListener('click', () => { jel_page = 1; jel_load(); });
    $jel('jel-filter-fy')?.addEventListener('change', () => { jel_page = 1; jel_load(); });
    $jel('jel-filter-search')?.addEventListener('input', () => {
        clearTimeout(jel_searchTimer);
        jel_searchTimer = setTimeout(() => { jel_page = 1; jel_load(); }, 400);
    });
}

/* Fiscal years come from fims.fiscal_year via GET /voucher/fiscal-years → {success, data:[{fiscal_yr}]}, newest first. */
async function jel_loadFiscalYears() {
    const sel = $jel('jel-filter-fy');
    try {
        const res  = await jel_authFetch(JEL_API + '/fiscal-years');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${jel_esc(r.fiscal_yr)}">${jel_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        jel_showError('Fiscal years: ' + e.message);
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function jel_load() {
    window.PagePreloader?.show();
    jel_hideError();
    try {
        const params = new URLSearchParams({
            page      : jel_page,
            limit     : JEL_PAGE_SIZE,
            fiscal_yr : $jel('jel-filter-fy').value,
            search    : $jel('jel-filter-search').value.trim(),
        });
        const res  = await jel_authFetch(`${JEL_API}/journal-entries?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load journal entry vouchers.');

        jel_renderTable(json.data ?? []);
        jel_renderPagination(json.pagination ?? {});
    } catch (e) {
        jel_renderTable([]);
        jel_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function jel_renderTable(rows) {
    const tbody = $jel('jel-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No journal entry vouchers found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map(r => `<tr style="cursor:pointer;" onclick="jel_viewDv(${r.dv_id})">
        <td class="nha-mono">${jel_esc(r.jev_no) || '—'}</td>
        <td class="nha-td-date">${jel_fmtDate(r.jev_date)}</td>
        <td class="nha-mono"><a href="${JEL_VOUCHER_VIEW}?dv=${r.dv_id}" class="nha-tbl-link" onclick="event.stopPropagation()">${jel_esc(r.dv_no) || '—'}</a></td>
        <td style="max-width:220px;" title="${jel_esc(r.claimant_name)}">
            <div style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${jel_esc(r.claimant_name) || '—'}</div>
        </td>
        <td>${jel_esc(r.fiscal_yr) || '—'}</td>
        <td class="text-end nha-mono">${jel_peso(r.total_debit)}</td>
        <td class="text-end nha-mono">${jel_peso(r.total_credit)}</td>
        <td>${jel_statusBadge(r.dv_status)}</td>
        <td class="no-print"><button type="button" class="nha-btn-act" title="Print this Journal Entry Voucher"
            onclick="event.stopPropagation(); jel_print(${r.dv_id}, this)">Print</button></td>
    </tr>`).join('');
}

function jel_viewDv(dvId) {
    window.location.href = `${JEL_VOUCHER_VIEW}?dv=${dvId}`;
}

// Print → the JEV form in the shared print preview (included below): the JEV and its lines come with
// their DV (GET /voucher/read/{id}); prp_jevFromDv() builds the payload.
async function jel_print(dvId, btn) {
    if (btn) btn.disabled = true;
    try {
        const res  = await jel_authFetch(`${JEL_API}/read/${dvId}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the voucher.');
        if (!json.data.jev) throw new Error('This voucher has no journal entry yet.');
        await prp_open(prp_jevFromDv(json.data), { type: 'JEV' });
    } catch (e) {
        jel_showError(e.message);
    } finally {
        if (btn) btn.disabled = false;
    }
}

/* ── Pagination ────────────────────────────────────────────── */
function jel_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = JEL_PAGE_SIZE } = p;
    $jel('jel-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $jel('jel-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $jel('jel-total').textContent = total;

    const box = $jel('jel-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="jel_goTo(${i})">${i}</button>`);
    }
    box.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="jel_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="jel_goTo(${current_page + 1})">›</button>`;
}
function jel_goTo(page) { if (page >= 1) { jel_page = page; jel_load(); } }

/* ── Badges / formatters ───────────────────────────────────── */
function jel_statusBadge(s) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'], FOR_FMD_REVIEW: ['nha-bdg-amber', 'For FMD Review'],
        FOR_ACCOUNTING: ['nha-bdg-blue', 'For Accounting'], FOR_TREASURY: ['nha-bdg-teal', 'For Treasury'],
        RELEASED: ['nha-bdg-green', 'Released'], REJECTED: ['nha-bdg-red', 'Rejected'], CANCELLED: ['nha-bdg-orange', 'Cancelled'],
    };
    const [cls, label] = map[s] || ['nha-bdg-gray', s || '—'];
    return `<span class="nha-bdg ${cls}">${jel_esc(label)}</span>`;
}
function jel_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function jel_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function jel_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function jel_showError(msg) { $jel('jel-error').style.display = 'flex'; $jel('jel-error-msg').textContent = msg; }
function jel_hideError()    { $jel('jel-error').style.display = 'none'; }
</script>
<?php
// Print → the shared print preview (prp_open + prp_jevFromDv).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
