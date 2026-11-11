<?php

namespace PHPMaker2024\FIMS;

@session_start();
$_bgr_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/css/nha-fims.css?v=<?php echo @filemtime($_SERVER['DOCUMENT_ROOT'].'/css/nha-fims.css'); ?>" />
<style>
@media (min-width: 901px) {
    .nha-root .bgr-summary-2col { grid-template-columns: repeat(2, 1fr); }
}
/* "Beginning Balance" spans Fund Balance + Adjustment + Carry Over Obligation */
.nha-root .nha-tbl thead th.bgr-grp { text-align: center; border-bottom: 1px solid rgba(255, 255, 255, .28); }
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
                <div class="nha-hdr-title">Corporate Operating Budget (COB)</div>
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
            <div class="nha-filter-group-fixed">
                <label class="nha-filter-label" for="bgr-filter-fiscal-yr">Fiscal Year</label>
                <input type="number" id="bgr-filter-fiscal-yr" class="nha-filter-input" style="width:160px;" placeholder="Fiscal year" min="2000" max="2100">
            </div>
        </div>
    </div>

    <!-- ── TABLE ──────────────────────────────────────────── -->
    <div class="nha-table-wrap">
        <div class="nha-tbl-scroll">
            <table class="nha-tbl">
                <thead>
                    <tr>
                        <th rowspan="2">#</th>
                        <th rowspan="2">Fiscal Year</th>
                        <th rowspan="2">Type of Fund</th>
                        <th rowspan="2">Program / Project</th>
                        <th colspan="3" class="bgr-grp">Beginning Balance</th>
                        <th rowspan="2" class="text-end">New Funds</th>
                    </tr>
                    <tr>
                        <th class="text-end">Fund Balance</th>
                        <th class="text-end">Adjustment</th>
                        <th class="text-end">Carry Over Obligation</th>
                    </tr>
                </thead>
                <tbody id="bgr-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="bgr-from">0</span>–<span id="bgr-to">0</span></strong> of <strong><span id="bgr-total">0</span></strong></span>
            <div id="bgr-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

</div><!-- /#bgr-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   CORPORATE OPERATING BUDGET (COB) LIST   prefix: bgr_  (read-only)
═══════════════════════════════════════════════════════════════ */

const BGR_API = '<?php echo $_bgr_api; ?>' + '/budget_req';

let bgr_page = 1;
const BGR_PAGE_SIZE = 15;
const BGR_CURRENT_YEAR = new Date().getFullYear();

const $bgr = id => document.getElementById(id);

async function bgr_bootstrapLoad() {
    await bgr_load();
    bgr_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_bootstrapLoad);
} else {
    bgr_bootstrapLoad();
}

function bgr_bindEvents() {
    $bgr('bgr-refresh')?.addEventListener('click', () => { bgr_page = 1; bgr_load(); });
    $bgr('bgr-filter-fiscal-yr')?.addEventListener('change', () => { bgr_page = 1; bgr_load(); });
}

/* ── Load ──────────────────────────────────────────────────── */
// The table only ever shows the selected fiscal year. It starts on the current year, and a
// blank or out-of-range entry snaps back to the current year rather than meaning "all years".
function bgr_fiscalYear() {
    const input = $bgr('bgr-filter-fiscal-yr');
    const year  = parseInt(input.value, 10);
    input.value = (year >= 2000 && year <= 2100) ? year : BGR_CURRENT_YEAR;
    return input.value;
}

async function bgr_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            fiscal_yr : bgr_fiscalYear(),
            page      : bgr_page,
            limit     : BGR_PAGE_SIZE,
        });
        const res  = await fetch(`${BGR_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        bgr_renderTable(json.data ?? []);
        bgr_renderPagination(json.pagination ?? {});
        $bgr('bgr-kpi-total').textContent  = json.pagination?.total ?? (json.data ?? []).length;
        $bgr('bgr-kpi-amount').textContent = bgr_fmtMoney(json.total_amount ?? 0);
    } catch (e) {
        bgr_renderTable([]);
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function bgr_renderTable(rows) {
    const tbody = $bgr('bgr-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No budget requests found.</span></div></td></tr>';
        return;
    }
    const offset = (bgr_page - 1) * BGR_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        // program_code is set on every row; a row is project-level when project_code is set too
        const isProgram = !r.project_code;
        const code = isProgram ? r.program_code : r.project_code;
        const name = isProgram ? r.program_name : r.project_name;
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td>${bgr_esc(r.fiscal_yr)}</td>
        <td>${r.fund_type ? bgr_esc(r.fund_type) : '<span style="color:var(--muted, #94a3b8);">—</span>'}</td>
        <td>
            <div style="font-weight:500;">${bgr_esc(code || '—')}</div>
            ${name ? `<div class="nha-td-sub" style="margin-top:2px;">${bgr_esc(name)}${isProgram ? '' : ' · ' + bgr_esc(r.program_code)}</div>` : ''}
        </td>
        <td class="text-end"><div style="font-weight:600;">${bgr_fmtMoney(r.amount)}</div></td>
        <td class="text-end">${bgr_fmtAdjustment(r.adjustment)}</td>
        <td class="text-end"><div style="font-weight:600;color:var(--sec, #62657E);">${bgr_fmtMoney(r.carry_over_obligation)}</div></td>
        <td class="text-end"><div style="font-weight:600;color:#16a34a;">${bgr_fmtMoney(r.new_funds)}</div></td>
    </tr>`;
    }).join('');
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

/* ── Helpers ───────────────────────────────────────────────── */
function bgr_fmtMoney(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
// Signed, colour-coded adjustment: + green (increase), − red (decrease), — for none.
function bgr_fmtAdjustment(v) {
    const n = Number(v || 0);
    if (!n) return '<span style="color:var(--muted, #94a3b8);">—</span>';
    const abs   = Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const color = n > 0 ? '#16a34a' : '#dc2626';   // green / red, same as the Change column on the revision page
    return `<span style="font-weight:600;color:${color};">${n > 0 ? '+' : '−'} ₱ ${abs}</span>`;
}
function bgr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function bgr_toast(msg, type = 'info') {
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
    t.innerHTML = `<span style="color:${color};flex-shrink:0;margin-top:1px">${icons[type] || icons.info}</span><div style="flex:1"><span>${msg}</span></div><button onclick="this.parentElement.remove()" style="border:none;background:none;cursor:pointer;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;padding:0 2px;flex-shrink:0">&times;</button>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
