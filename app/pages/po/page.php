<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Purchase Order list. Data comes from the REST API (app/routes/purchase-order.php), no direct DB queries.
// Ported from GLOBAL/includes/inc.po-page.php, trimmed to what exists in the `fims` schema
// (no PR / allocation / workflow engine / payment workflow).
// API.FIMS_URL is the site root; the REST routes live under /api (added unless the env value already ends in it).
$_pol_api  = rtrim((string) Config('API.FIMS_URL'), '/');
$_pol_api  = preg_match('~/api$~', $_pol_api) ? $_pol_api : $_pol_api . '/api';
// The add/edit form is a modal (app/pages/po/form.php, included below) — it needs no page of its own.
// The detail view is still a separate PHPMaker custom page; PHPMaker derives its route from the file name:
//   purchase-order-readone.php (view includes app/pages/po/readone.php) → /PurchaseOrderReadone
$_pol_view = 'PurchaseOrderReadone';
// ?edit=<po_id> opens the edit modal on load (the detail page's Edit button links here).
$_pol_edit = (int) ($_GET['edit'] ?? 0);
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="pol-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title">Purchase Orders</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="pol-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="pol-export" type="button" class="nha-btn-hdr" title="Export the rows on this page to CSV">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Export
            </button>
            <!-- Same nha-btn-hdr shape/size as Refresh + Export (nha-btn-primary is a plain button and looked out of place here); green marks the primary action. -->
            <button id="pol-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Create a new purchase order">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                New PO
            </button>
        </div>
    </div>

    <!-- Error banner -->
    <div id="pol-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="pol-error-msg">Failed to load data.</span>
    </div>

    <!-- ── KPI CARDS (cover every row matching the filters, not just this page) ── -->
    <!-- <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total PO Amount</div>
                <div class="nha-sc-val" id="pol-kpi-total">—</div>
            </div></div>
            <p class="nha-kpi-sub" id="pol-kpi-total-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Approved</div>
                <div class="nha-sc-val" id="pol-kpi-approved">—</div>
            </div></div>
            <p class="nha-kpi-sub">Ready for delivery / DV</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Pending Approval</div>
                <div class="nha-sc-val" id="pol-kpi-pending">—</div>
            </div></div>
            <p class="nha-kpi-sub">Submitted, awaiting action</p>
        </div>
        <div class="nha-sc nha-sc-red">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Rejected / Cancelled</div>
                <div class="nha-sc-val" id="pol-kpi-rejected">—</div>
            </div></div>
            <p class="nha-kpi-sub">Requires attention</p>
        </div>
    </div> -->

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="pol-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="DRAFT">Draft</option>
                    <option value="SUBMITTED">Submitted</option>
                    <option value="APPROVED">Approved</option>
                    <option value="REJECTED">Rejected</option>
                    <option value="CANCELLED">Cancelled</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Fiscal Year</label>
                <select id="pol-filter-fy" class="nha-filter-select">
                    <option value="">All Years</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Expense Class</label>
                <select id="pol-filter-class" class="nha-filter-select">
                    <option value="">All Classes</option>
                    <option value="PS">PS</option>
                    <option value="MOOE">MOOE</option>
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
                    <input type="text" id="pol-filter-search" class="nha-filter-input" placeholder="PO number, vendor, contract no…">
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
                        <th>PO Number</th>
                        <th>PO Date</th>
                        <th>Fiscal Year</th>
                        <th>Expense Class</th>
                        <th>Vendor</th>
                        <th>Program / Project</th>
                        <th class="text-end">Total (₱)</th>
                        <th class="text-end">Outstanding (₱)</th>
                        <th>Status</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>
                <tbody id="pol-tbody">
                    <tr><td colspan="11"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="pol-from">0</span>–<span id="pol-to">0</span></strong> of <strong><span id="pol-total">0</span></strong></span>
            <div id="pol-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

</div><!-- /#pol-root -->

<?php include __DIR__ . '/form.php'; // add / edit modal — defines pof_open(id), pof_close() and calls window.pof_onSaved ?>

<script>
/* ══════════════════════════════════════════════════════════════
   PURCHASE ORDER LIST   prefix: pol_
═══════════════════════════════════════════════════════════════ */

const POL_BASE = <?= json_encode($_pol_api) ?>;
const POL_API  = POL_BASE + '/purchase-order';
const POL_VIEW = <?= json_encode($_pol_view) ?>;
const POL_EDIT = <?= (int) $_pol_edit ?>;     // po_id to open in the edit modal on load (0 = none)

const POL_PAGE_SIZE = 15;
let pol_page = 1;
let pol_rows = [];          // rows of the page currently shown (used by CSV export)
let pol_searchTimer = null;

const $pol = id => document.getElementById(id);

/* Cookie session works on its own; the bearer header is added when the page has a token. */
function pol_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function pol_boot() {
    await pol_loadFiscalYears();
    pol_bindEvents();
    pol_load();
    if (POL_EDIT > 0) pof_open(POL_EDIT);
}

/* The form modal calls this after a successful save: confirm, then refresh the list (and its KPI totals). */
window.pof_onSaved = (data, message) => {
    pol_toast(message || 'Purchase order saved.', 'success');
    pol_page = 1;
    pol_load();
    // A reload on ?edit=<id> would reopen the modal — drop the param once the user has saved.
    if (POL_EDIT > 0 && window.history.replaceState) window.history.replaceState(null, '', window.location.pathname);
};
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pol_boot);
else pol_boot();

function pol_bindEvents() {
    $pol('pol-refresh')?.addEventListener('click', () => { pol_page = 1; pol_load(); });
    $pol('pol-export')?.addEventListener('click', pol_exportCsv);
    $pol('pol-add')?.addEventListener('click', () => pof_open());
    ['pol-filter-status', 'pol-filter-fy', 'pol-filter-class'].forEach(id => {
        $pol(id)?.addEventListener('change', () => { pol_page = 1; pol_load(); });
    });
    $pol('pol-filter-search')?.addEventListener('input', () => {
        clearTimeout(pol_searchTimer);
        pol_searchTimer = setTimeout(() => { pol_page = 1; pol_load(); }, 400);
    });
}

/* Fiscal years come from fims.fiscal_year via GET /fiscal_year/all → {success, data:[{fiscal_yr}]}, newest first. */
async function pol_loadFiscalYears() {
    const sel = $pol('pol-filter-fy');
    try {
        const res  = await pol_authFetch(POL_BASE + '/fiscal_year/all');
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        console.log('Fiscal years:', json);
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');
        sel.innerHTML = '<option value="">All Years</option>' +
            (json.data ?? []).map(r => `<option value="${pol_esc(r.fiscal_yr)}">${pol_esc(r.fiscal_yr)}</option>`).join('');
    } catch (e) {
        pol_toast('Fiscal years: ' + e.message, 'error');
    }
}

/* ── Load ──────────────────────────────────────────────────── */
async function pol_load() {
    window.PagePreloader?.show();
    pol_hideError();
    try {
        const params = new URLSearchParams({
            page          : pol_page,
            limit         : POL_PAGE_SIZE,
            status        : $pol('pol-filter-status').value,
            fiscal_yr     : $pol('pol-filter-fy').value,
            expense_class : $pol('pol-filter-class').value,
            search        : $pol('pol-filter-search').value.trim(),
        });
        const res  = await pol_authFetch(`${POL_API}/list?${params}`);
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load purchase orders.');

        pol_rows = json.data ?? [];
        pol_renderTable(pol_rows);
        pol_renderPagination(json.pagination ?? {});
        pol_renderSummary(json.summary ?? {});
    } catch (e) {
        pol_rows = [];
        pol_renderTable([]);
        pol_showError('Could not load data: ' + e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* The KPI cards are commented out in the markup, so every write is null-safe. */
function pol_renderSummary(s) {
    const set = (id, v) => { const el = $pol(id); if (el) el.textContent = v; };
    set('pol-kpi-total',     pol_peso(s.total_amount ?? 0));
    set('pol-kpi-total-sub', 'Outstanding: ' + pol_peso(s.outstanding_amount ?? 0));
    set('pol-kpi-approved',  s.approved ?? 0);
    set('pol-kpi-pending',   s.pending  ?? 0);
    set('pol-kpi-rejected',  s.rejected ?? 0);
}

/* ── Table ─────────────────────────────────────────────────── */
function pol_renderTable(rows) {
    const tbody = $pol('pol-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="11"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No purchase orders found.</span></div></td></tr>';
        return;
    }
    const offset = (pol_page - 1) * POL_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const canEdit   = ['DRAFT', 'REJECTED'].includes(r.status);
        const canDelete = ['DRAFT', 'CANCELLED'].includes(r.status);
        const target    = r.project_code
            ? `${pol_esc(r.project_code)}${r.project_name ? ' — ' + pol_esc(r.project_name) : ''}`
            : (r.program_code ? `${pol_esc(r.program_code)}${r.program_name ? ' — ' + pol_esc(r.program_name) : ''}` : '—');
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td class="nha-mono"><a href="${POL_VIEW}?id=${r.po_id}" class="nha-tbl-link">${pol_esc(r.po_number) || '—'}</a></td>
        <td class="nha-td-date">${pol_fmtDate(r.po_date)}</td>
        <td class="nha-td-xs">FY ${pol_esc(r.fiscal_yr)}</td>
        <td>${pol_classBadge(r.expense_class)}</td>
        <td style="max-width:200px;" title="${pol_esc(r.vendor_name)}">
            <div style="font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${pol_esc(r.vendor_name) || '—'}</div>
            ${r.vendor_code ? `<div class="nha-td-sub">${pol_esc(r.vendor_code)}</div>` : ''}
        </td>
        <td class="nha-td-sub">${target}</td>
        <td class="text-end nha-mono" style="font-weight:600;">${pol_peso(r.total_amount)}</td>
        <td class="text-end nha-mono nha-td-xs">${pol_peso(r.outstanding_amount)}</td>
        <td>${pol_statusBadge(r.status)}</td>
        <td class="no-print" style="white-space:nowrap;">
            <a class="nha-btn-act view" href="${POL_VIEW}?id=${r.po_id}" title="View">View</a>
            ${canEdit ? `<button type="button" class="nha-btn-act edit" onclick="pof_open(${r.po_id})" title="Edit">Edit</button>` : ''}
            ${canDelete ? `<button type="button" class="nha-btn-act danger" onclick="pol_delete(${r.po_id}, '${pol_escJs(r.po_number)}')" title="Delete">Delete</button>` : ''}
        </td>
    </tr>`;
    }).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function pol_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = POL_PAGE_SIZE } = p;
    $pol('pol-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $pol('pol-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $pol('pol-total').textContent = total;

    const box = $pol('pol-pagination');
    if (last_page <= 1) { box.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="pol_goTo(${i})">${i}</button>`);
    }
    box.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="pol_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="pol_goTo(${current_page + 1})">›</button>`;
}
function pol_goTo(page) { if (page >= 1) { pol_page = page; pol_load(); } }

/* ── Delete / export ───────────────────────────────────────── */
async function pol_delete(id, poNumber) {
    if (!confirm(`Delete Purchase Order "${poNumber}"?\nThis cannot be undone.`)) return;
    window.PagePreloader?.show();
    try {
        const res  = await pol_authFetch(`${POL_API}/delete/${id}`, { method: 'DELETE' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete purchase order.');
        pol_toast(json.message || 'Purchase order deleted.', 'success');
        pol_load();
    } catch (e) {
        pol_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function pol_exportCsv() {
    if (!pol_rows.length) { pol_toast('Nothing to export.', 'warning'); return; }
    const head = ['PO Number', 'PO Date', 'Fiscal Year', 'Expense Class', 'Vendor Code', 'Vendor', 'Program', 'Project', 'Total Amount', 'Outstanding', 'Status'];
    const cell = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const lines = [head.map(cell).join(',')].concat(pol_rows.map(r => [
        r.po_number, r.po_date, r.fiscal_yr, r.expense_class, r.vendor_code, r.vendor_name,
        r.program_code, r.project_code, r.total_amount, r.outstanding_amount, r.status,
    ].map(cell).join(',')));
    const url = URL.createObjectURL(new Blob([lines.join('\n')], { type: 'text/csv' }));
    const a = Object.assign(document.createElement('a'), { href: url, download: `purchase_orders_${new Date().toISOString().slice(0, 10)}.csv` });
    a.click();
    URL.revokeObjectURL(url);
}

/* ── Badges / formatters ───────────────────────────────────── */
function pol_statusBadge(s) {
    const map = {
        DRAFT: ['nha-bdg-gray', 'Draft'], SUBMITTED: ['nha-bdg-amber', 'Submitted'],
        APPROVED: ['nha-bdg-green', 'Approved'], REJECTED: ['nha-bdg-red', 'Rejected'],
        CANCELLED: ['nha-bdg-orange', 'Cancelled'],
    };
    const [cls, label] = map[s] || ['nha-bdg-gray', s || '—'];
    return `<span class="nha-bdg ${cls}">${pol_esc(label)}</span>`;
}
function pol_classBadge(c) {
    const map = { PS: ['nha-bdg-blue', 'PS'], MOOE: ['nha-bdg-teal', 'MOOE'], CAPITAL_OUTLAY: ['nha-bdg-purple', 'CO'], CO: ['nha-bdg-purple', 'CO'], OTHER: ['nha-bdg-gray', 'Other'] };
    const [cls, label] = map[c] || ['nha-bdg-gray', c || '—'];
    return `<span class="nha-bdg ${cls}">${pol_esc(label)}</span>`;
}
function pol_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function pol_fmtDate(s) {
    if (!s) return '—';
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? '—' : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function pol_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
/* For values placed inside a single-quoted JS string in an inline onclick. */
function pol_escJs(text) {
    return pol_esc(String(text ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'"));
}
function pol_showError(msg) { $pol('pol-error').style.display = 'flex'; $pol('pol-error-msg').textContent = msg; }
function pol_hideError()    { $pol('pol-error').style.display = 'none'; }

function pol_toast(msg, type = 'info') {
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
    span.textContent = msg;            // textContent: messages may contain server text
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
