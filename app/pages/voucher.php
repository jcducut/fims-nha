<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Disbursement Voucher list. Data comes from the REST API (app/routes/voucher.php), no direct DB
// queries. Modeled on app/pages/po/page.php — the add/edit modal and the view/print modal are
// partials included below (no separate PHPMaker sub-page for either, matching the "data-entry now,
// print later" phase-1 scope).
// API.FIMS_URL is the site root; the REST routes live under /api (added unless the env value already ends in it).
$_vl_api = rtrim((string) Config('API.FIMS_URL'), '/');
$_vl_api = preg_match('~/api$~', $_vl_api) ? $_vl_api : $_vl_api . '/api';
// ?dv=<dv_id> opens that DV's view modal on load — the Journal Entry Voucher register page links
// here this way instead of duplicating the DV detail view. Deliberately not named "view": that's a
// reserved PHPMaker query param (Config("VIEW") === "view", used to pick a view-template override)
// and colliding with it makes the framework try to render a template literally named "<dv_id>.php".
$_vl_view = (int) ($_GET['dv'] ?? 0);
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />
<script src="/_shared/js/searchable-select.js?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/js/searchable-select.js') ?>"></script>

<div id="vl-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Disbursement Vouchers</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="vl-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="vl-export" type="button" class="nha-btn-hdr" title="Export the rows on this page to CSV">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </button>
            <button id="vl-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Create a new disbursement voucher">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New DV
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="vl-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="vl-error-msg">Failed to load data.</span>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="vl-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="DRAFT">Draft</option>
                    <option value="FOR_FMD_REVIEW">For FMD Review</option>
                    <option value="FOR_ACCOUNTING">For Accounting</option>
                    <option value="FOR_TREASURY">For Treasury</option>
                    <option value="RELEASED">Released</option>
                    <option value="REJECTED">Rejected</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="vl-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Expense Class</label>
                <select id="vl-filter-class" class="nha-filter-select">
                    <option value="">All Classes</option>
                    <option value="MOOE">MOOE</option>
                    <option value="PS">PS</option>
                    <option value="CAPITAL_OUTLAY">Capital Outlay</option>
                    <option value="OTHER">Other</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="vl-filter-search" class="nha-filter-input" placeholder="DV number, claimant, RFA no…">
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
                        <th>DV Number</th>
                        <th>Date</th>
                        <th>Payee</th>
                        <th>Expense Class</th>
                        <th class="text-end">Gross Amount</th>
                        <th class="text-end">Deductions/WHT</th>
                        <th class="text-end">Net Amount</th>
                        <th>Processing Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="vl-tbody">
                    <tr><td colspan="9"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="vl-from">0</span>–<span id="vl-to">0</span></strong> of <strong><span id="vl-total">0</span></strong></span>
            <div id="vl-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

</div><!-- /#vl-root -->

<?php
include __DIR__ . '/voucher/form.php';     // add / edit modal — defines vf_open(id), vf_close() and calls window.vf_onSaved
include __DIR__ . '/voucher/readone.php';  // view / print modal — defines vr_open(id), vr_close()
?>

<script>
/* ══════════════════════════════════════════════════════════════
   DISBURSEMENT VOUCHER LIST   prefix: vl_
═══════════════════════════════════════════════════════════════ */

const VL_BASE = <?= json_encode($_vl_api) ?>;
const VL_API  = VL_BASE + '/voucher';
const VL_VIEW = <?= (int) $_vl_view ?>; // dv_id to open in the view modal on load (0 = none)

const VL_PAGE_SIZE = 15;
let vl_page = 1;
let vl_rows = [];
let vl_searchTimer = null;

const $vl = id => document.getElementById(id);

function vl_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function vl_boot() {
    await vl_loadFiscalYears();
    vl_bindEvents();
    vl_load();
    if (VL_VIEW > 0) vr_open(VL_VIEW);
}

/* The form modal calls this after a successful save: confirm, then refresh the list. */
window.vf_onSaved = (data, message) => {
    vl_toast(message || 'Disbursement voucher saved.', 'success');
    vl_page = 1;
    vl_load();
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', vl_boot);
else vl_boot();

function vl_bindEvents() {
    $vl('vl-refresh')?.addEventListener('click', () => { vl_page = 1; vl_load(); });
    $vl('vl-export')?.addEventListener('click', vl_exportCsv);
    $vl('vl-add')?.addEventListener('click', () => vf_open());
    ['vl-filter-status', 'vl-filter-fy', 'vl-filter-class'].forEach(id => {
        $vl(id)?.addEventListener('change', () => { vl_page = 1; vl_load(); });
    });
    $vl('vl-filter-search')?.addEventListener('input', () => {
        clearTimeout(vl_searchTimer);
        vl_searchTimer = setTimeout(() => { vl_page = 1; vl_load(); }, 400);
    });
}

/* Fiscal years come from fims.fiscal_year via GET /voucher/fiscal-years → {success, data:[{fiscal_yr}]}, newest first. */
async function vl_loadFiscalYears() {
    const sel = $vl('vl-filter-fy');
    try {
        const res  = await vl_authFetch(VL_API + '/fiscal-years');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${vl_esc(r.fiscal_yr)}">${vl_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        vl_toast('Fiscal years: ' + e.message, 'error');
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function vl_load() {
    window.PagePreloader?.show();
    vl_hideError();
    try {
        const params = new URLSearchParams({
            page         : vl_page,
            limit        : VL_PAGE_SIZE,
            status        : $vl('vl-filter-status').value,
            fiscal_yr     : $vl('vl-filter-fy').value,
            expense_class : $vl('vl-filter-class').value,
            search        : $vl('vl-filter-search').value.trim(),
        });
        const res  = await vl_authFetch(`${VL_API}/list?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load disbursement vouchers.');

        vl_rows = json.data ?? [];
        vl_renderTable(vl_rows);
        vl_renderPagination(json.pagination ?? {});
    } catch (e) {
        vl_rows = [];
        vl_renderTable([]);
        vl_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function vl_renderTable(rows) {
    const tbody = $vl('vl-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No disbursement vouchers found.</span></div></td></tr>';
        return;
    }
    tbody.innerHTML = rows.map((r) => {
        const canEdit     = ['DRAFT', 'REJECTED'].includes(r.status);
        const canDelete   = ['DRAFT', 'CANCELLED'].includes(r.status);
        // Combined deductions + withholding tax (both now separate repeating tables, not DV columns)
        // — gross minus net captures their total without a per-row join.
        const withholding = Math.max(0, Number(r.gross_amount || 0) - Number(r.net_amount || 0));
        return `<tr>
        <td class="nha-mono"><a href="javascript:void(0)" class="nha-tbl-link" onclick="vr_open(${r.dv_id})">${vl_esc(r.dv_no) || '—'}</a></td>
        <td class="nha-td-date">${vl_fmtDate(r.dv_date)}</td>
        <td style="max-width:220px;" title="${vl_esc(r.claimant_name)}">
            <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${vl_esc(r.claimant_name) || '—'}</div>
        </td>
        <td>${vl_classBadge(r.expense_class)}</td>
        <td class="text-end nha-mono">${vl_peso(r.gross_amount)}</td>
        <td class="text-end nha-mono">${vl_peso(withholding)}</td>
        <td class="text-end nha-mono" style="font-weight:600;">${vl_peso(r.net_amount)}</td>
        <td>${vl_statusBadge(r.status)}</td>
        <td class="no-print" style="white-space:nowrap;">
            <button type="button" class="nha-btn-act view" onclick="vr_open(${r.dv_id})" title="View">View</button>
            ${canEdit ? `<button type="button" class="nha-btn-act edit" onclick="vf_open(${r.dv_id})" title="Edit">Edit</button>` : ''}
            ${canDelete ? `<button type="button" class="nha-btn-act danger" onclick="vl_delete(${r.dv_id}, '${vl_escJs(r.dv_no)}')" title="Delete">Delete</button>` : ''}
        </td>
    </tr>`;
    }).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function vl_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = VL_PAGE_SIZE } = p;
    $vl('vl-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $vl('vl-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $vl('vl-total').textContent = total;

    const box = $vl('vl-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="vl_goTo(${i})">${i}</button>`);
    }
    box.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="vl_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="vl_goTo(${current_page + 1})">›</button>`;
}
function vl_goTo(page) { if (page >= 1) { vl_page = page; vl_load(); } }

/* ── Delete / export ───────────────────────────────────────── */
async function vl_delete(id, dvNo) {
    if (!confirm(`Delete Disbursement Voucher "${dvNo}"?\nThis cannot be undone.`)) return;
    window.PagePreloader?.show();
    try {
        const res  = await vl_authFetch(`${VL_API}/delete/${id}`, { method: 'DELETE' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete disbursement voucher.');
        vl_toast(json.message || 'Disbursement voucher deleted.', 'success');
        vl_load();
    } catch (e) {
        vl_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function vl_exportCsv() {
    if (!vl_rows.length) { vl_toast('Nothing to export.', 'warning'); return; }
    const head = ['DV Number', 'Date', 'Payee', 'Expense Class', 'Gross Amount', 'Deductions/WHT', 'Net Amount', 'Processing Status'];
    const cell = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [head.map(cell).join(',')].concat(vl_rows.map(r => [
        r.dv_no, r.dv_date, r.claimant_name, r.expense_class,
        r.gross_amount, Math.max(0, Number(r.gross_amount || 0) - Number(r.net_amount || 0)), r.net_amount,
        r.status,
    ].map(cell).join(',')));
    const url = URL.createObjectURL(new Blob([lines.join('\n')], { type: 'text/csv' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: `disbursement_vouchers_${new Date().toISOString().slice(0, 10)}.csv` });
    a.click();
    URL.revokeObjectURL(url);
}

/* ── Badges / formatters (shared with voucher/form.php + voucher/readone.php) ── */
function vl_statusBadge(s) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'],
        FOR_FMD_REVIEW: ['nha-bdg-amber', 'For FMD Review'],
        FOR_ACCOUNTING: ['nha-bdg-blue', 'For Accounting'],
        FOR_TREASURY: ['nha-bdg-teal', 'For Treasury'],
        RELEASED: ['nha-bdg-green', 'Released'],
        REJECTED: ['nha-bdg-red', 'Rejected'],
        CANCELLED: ['nha-bdg-orange', 'Cancelled'],
    };
    const [cls, label] = map[s] || ['nha-bdg-gray', s || '—'];
    return `<span class="nha-bdg ${cls}">${vl_esc(label)}</span>`;
}
function vl_classBadge(c) {
    const map = { MOOE: ['nha-bdg-teal', 'MOOE'], PS: ['nha-bdg-blue', 'PS'], CAPITAL_OUTLAY: ['nha-bdg-purple', 'Capital Outlay'], OTHER: ['nha-bdg-gray', 'Other'] };
    const [cls, label] = map[c] || ['nha-bdg-gray', '—'];
    return `<span class="nha-bdg ${cls}">${vl_esc(label)}</span>`;
}
function vl_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function vl_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function vl_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function vl_escJs(text) {
    return vl_esc(String(text ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'"));
}
function vl_showError(msg) { $vl('vl-error').style.display = 'flex'; $vl('vl-error-msg').textContent = msg; }
function vl_hideError()    { $vl('vl-error').style.display = 'none'; }

function vl_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
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
    const span = document.createElement('span');
    span.style.flex = '1';
    span.textContent = msg;
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
