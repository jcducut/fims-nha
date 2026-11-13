<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_brv_api = Config('API.FIMS_URL');
$_amp_api = $_brv_api;
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
/* ── Actions dropdown (the same menu as the Memo Requests list) ── */
.iar-menu { position:relative;display:inline-block; }
.iar-menu-trigger { display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:6px;font-size:11px;font-weight:600;cursor:pointer;
    border:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Sans',sans-serif;transition:all .15s; }
.iar-menu-trigger:hover { background:var(--afims-surface-subtle, #F4F7FA);border-color:var(--afims-text-disabled, #A9AAB8); }
.iar-menu-trigger svg { width:12px;height:12px;flex-shrink:0; }
.iar-menu-drop { display:none;position:fixed;min-width:170px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
    box-shadow:0 8px 24px rgba(0,0,0,.12);z-index:9999;padding:4px 0; }
.iar-menu-drop.open { display:block; }
.iar-menu-item { display:flex;align-items:center;gap:8px;padding:8px 12px;font-size:12px;font-weight:500;color:var(--afims-text, #1A1B21);white-space:nowrap;cursor:pointer;
    border:none;background:none;width:100%;text-align:left;transition:background .1s;font-family:'IBM Plex Sans',sans-serif; }
.iar-menu-item:hover { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E); }
.iar-menu-item svg { width:13px;height:13px;flex-shrink:0;opacity:.7; }
.iar-menu-divider { border-top:1px solid var(--afims-border, #E4ECF2);margin:3px 0; }
</style>

<div id="amp-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Inspection and Acceptance Report Module</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="amp-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="amp-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a PO record">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add PO Record
            </button>
        </div>
    </div>

    <!-- ── KPI ────────────────────────────────────────────── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total PO Numbers</div>
                    <div class="nha-sc-val" id="amp-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="amp-filter-search" class="nha-filter-input" placeholder="Search PO no., IAR no., DR no., description, invoice, serial, property no.…">
                    <button class="nha-filter-clear-btn" id="amp-search-clear" hidden type="button">×</button>
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
                        <th>IAR Number</th>
                        <th>PO Number</th>
                        <th>PO Date</th>
                        <th>Requesting Office/Dept</th>
                        <th>Vendor</th>
                        <th>Sales Invoice #</th>
                        <th style="text-align:right;">PO Amount</th>
                        <th style="text-align:right;">Total DR Amount</th>
                        <th style="text-align:right;">Balance</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="amp-tbody">
                    <tr><td colspan="11"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="amp-from">0</span>–<span id="amp-to">0</span></strong> of <strong><span id="amp-total">0</span></strong></span>
            <div id="amp-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/dr-module-form.php'; ?>

</div><!-- /#amp-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   DR MODULE PO LIST   prefix: amp_   (1 PO → 1 IAR → many delivery receipts)
═══════════════════════════════════════════════════════════════ */

const AMP_API = '<?php echo $_amp_api; ?>' + '/po-dr';

let amp_page = 1;
let amp_rows = [];
const AMP_PAGE_SIZE = 15;

const $amp = id => document.getElementById(id);

async function amp_bootstrapLoad() {
    amp_bindEvents();
    await amp_load();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', amp_bootstrapLoad);
} else {
    amp_bootstrapLoad();
}

function amp_bindEvents() {
    $amp('amp-refresh')?.addEventListener('click', () => { amp_page = 1; amp_load(); });
    $amp('amp-add')?.addEventListener('click', () => amp_openModal('add'));
    $amp('amp-modal-close')?.addEventListener('click', amp_closeModal);
    $amp('amp-cancel')?.addEventListener('click', amp_closeModal);
    $amp('amp-form')?.addEventListener('submit', amp_save);

    let searchTimer;
    const searchEl = $amp('amp-filter-search');
    const clearBtn = $amp('amp-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { amp_page = 1; amp_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        amp_page = 1; amp_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function amp_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search : $amp('amp-filter-search')?.value || '',
            page   : amp_page,
            limit  : AMP_PAGE_SIZE,
        });
        const res  = await fetch(`${AMP_API}/list?${params}`, { credentials: 'same-origin', headers: amp_authHeaders() });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        amp_renderTable(json.data ?? []);
        amp_renderPagination(json.pagination ?? {});
        $amp('amp-kpi-total').textContent = json.pagination?.total ?? (json.data ?? []).length;
    } catch (e) {
        amp_renderTable([]);
        amp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function amp_renderTable(rows) {
    const tbody = $amp('amp-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="11"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No PO records found.</span></div></td></tr>';
        return;
    }
    const offset = (amp_page - 1) * AMP_PAGE_SIZE;
    const money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    amp_rows = rows;
    // One row per PO; the Actions "View" button opens every delivered line of that PO.
    const line = (html, style = '') => `<div class="amp-line" style="${style}">${html}</div>`;
    tbody.innerHTML = rows.map((r, i) => {
        return `<tr>
            <td class="nha-td-seq" style="vertical-align:top;">${offset + i + 1}</td>
            <td style="vertical-align:top;white-space:nowrap;">${r.iar_number ? '<code class="nha-code">' + amp_esc(r.iar_number) + '</code>' : '—'}</td>
            <td style="vertical-align:top;"><code class="nha-code">${amp_esc(r.po_number)}</code></td>
            <td style="vertical-align:top;white-space:nowrap;">${amp_esc(r.po_date || '—')}</td>
            <td style="vertical-align:top;">${amp_esc(r.requesting_office || '—')}</td>
            <td style="vertical-align:top;">${amp_esc(r.vendor_name || '—')}</td>
            <td style="vertical-align:top;">${amp_esc(r.si_number || '—')}</td>
            <td style="vertical-align:top;text-align:right;white-space:nowrap;">${r.po_amount === null || r.po_amount === undefined ? '—' : money(r.po_amount)}</td>
            <td style="vertical-align:top;text-align:right;white-space:nowrap;"><strong>${money(r.dr_total)}</strong></td>
            <td style="vertical-align:top;text-align:right;white-space:nowrap;">${r.balance === null || r.balance === undefined ? '—'
                : `<strong style="color:${Number(r.balance) < 0 ? 'var(--afims-danger, #DC3545)' : (Number(r.balance) === 0 ? '#16A34A' : 'inherit')};">${money(r.balance)}</strong>`}</td>
            ${actionsCell(r)}
        </tr>`;
    }).join('');
    function actionsCell(r) {
        return `<td style="white-space:nowrap;vertical-align:top;">
            <div class="iar-menu">
                <button type="button" class="iar-menu-trigger" aria-haspopup="true" aria-expanded="false" onclick="amp_toggleMenu(this)">
                    <svg fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                    Actions
                </button>
                <div class="iar-menu-drop">
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();amp_viewAll(${rows.indexOf(r)})" title="View all items of this PO">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        View
                    </button>
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();amp_printIar(${rows.indexOf(r)})" title="Print the Inspection and Acceptance Report">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                        Print IAR
                    </button>
                    <div class="iar-menu-divider"></div>
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();amp_proforma('${amp_escAttr(r.po_number)}')" title="Pro-forma JEV of each delivery receipt">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        Pro-forma
                    </button>
                    <div class="iar-menu-divider"></div>
                    <button type="button" class="iar-menu-item" onclick="amp_closeMenus();amp_edit('${amp_escAttr(r.po_number)}')" title="Edit">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        Edit
                    </button>
                </div>
            </div>
        </td>`;
    }
}


// Actions dropdown of a row (fixed position, so the table's scrolling never clips it)
function amp_toggleMenu(btn) {
    const drop = btn.closest('.iar-menu').querySelector('.iar-menu-drop');
    const isOpen = drop.classList.contains('open');
    amp_closeMenus();
    if (isOpen) return;
    const r = btn.getBoundingClientRect();
    drop.style.top = (r.bottom + 4) + 'px';
    drop.style.right = (window.innerWidth - r.right) + 'px';
    drop.classList.add('open');
    btn.setAttribute('aria-expanded', 'true');
}
function amp_closeMenus() {
    document.querySelectorAll('.iar-menu-drop.open').forEach(d => d.classList.remove('open'));
    document.querySelectorAll('.iar-menu-trigger[aria-expanded="true"]').forEach(b => b.setAttribute('aria-expanded', 'false'));
}
document.addEventListener('click', e => { if (!e.target.closest('.iar-menu')) amp_closeMenus(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') amp_closeMenus(); });
document.addEventListener('scroll', amp_closeMenus, true);

// Open every item of the PO, with its delivery receipt number (Actions → View)
function amp_viewAll(i) {
    const r = amp_rows[i];
    if (!r) return;
    const money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dash = v => v ? amp_esc(v) : '—';
    const lines = r.receipts || [];
    const total = lines.reduce((s, ln) => s + Number(ln.total_amount || 0), 0);
    const vrow = (k, v) => `<div class="amp-vw-row"><div class="amp-vw-k">${k}</div><div class="amp-vw-v">${v}</div></div>`;
    $amp('amp-view-body').innerHTML = `
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Inspection and Acceptance Report</h4>
            ${vrow('IAR Number', r.iar_number ? '<span class="amp-vw-code">' + amp_esc(r.iar_number) + '</span>' : '—')}
            ${vrow('Vendor', dash(r.vendor_name))}
            ${vrow('Total Amount', '<span class="amp-vw-num">₱' + money(total) + '</span>')}
            ${vrow('Delivery Receipts', lines.length)}
        </div>
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Delivery Receipts</h4><div class="amp-vw-tblwrap">
        <table class="amp-v-table">
            <thead><tr><th style="width:40px;"></th><th>Delivery Date</th><th>Delivery Receipt</th><th style="text-align:right;">Total Amount</th></tr></thead>
            <tbody>${lines.map(ln => {
                const items = ln.items || [];
                const has   = items.length > 0;
                // a delivery receipt with items gets a dropdown arrow; its items open in the row below
                return `<tr>
                    <td>${has ? '<button type="button" class="amp-v-toggle" onclick="amp_toggleItems(this)" aria-expanded="false" title="Show items">&#9656;</button>' : ''}</td>
                    <td style="white-space:nowrap;">${dash(ln.delivery_date)}</td>
                    <td>${dash(ln.dr_number)}${has ? '<span class="amp-v-count">' + items.length + (items.length === 1 ? ' item' : ' items') + '</span>' : ''}</td>
                    <td style="text-align:right;white-space:nowrap;">${money(ln.total_amount)}</td>
                </tr>${has ? `<tr class="amp-v-sub" style="display:none;"><td></td><td colspan="3">
                    <table class="amp-v-table amp-v-inner">
                        <thead><tr><th>Description - Item</th><th>Serial No.</th><th>Prop. No.</th><th style="text-align:right;">Amount</th></tr></thead>
                        <tbody>${items.map(it => `<tr>
                            <td style="white-space:pre-wrap;">${dash(it.description)}</td>
                            <td>${dash(it.serial_no)}</td>
                            <td>${dash(it.property_no)}</td>
                            <td style="text-align:right;white-space:nowrap;">${it.amount ? money(it.amount) : '—'}</td></tr>`).join('')}</tbody>
                    </table></td></tr>` : ''}`;
            }).join('')}</tbody>
            <tfoot><tr><td colspan="3" style="text-align:right;">Total</td><td style="text-align:right;">${money(total)}</td></tr></tfoot>
        </table></div></div>`;
    $amp('amp-view-card').classList.remove('amp-wide');
    $amp('amp-view-title').textContent = 'Actual Delivery';
    $amp('amp-view-ref').textContent = r.iar_number || '';
    $amp('amp-view-overlay').classList.add('open');
}

// Actual Delivery: open / close the items of a delivery receipt (the row right below it)
function amp_toggleItems(btn) {
    const row = btn.closest('tr')?.nextElementSibling;
    if (!row || !row.classList.contains('amp-v-sub')) return;
    const open = row.style.display === 'none';
    row.style.display = open ? '' : 'none';
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.title = open ? 'Hide items' : 'Show items';
    btn.innerHTML = open ? '&#9662;' : '&#9656;';
}

/* ── Pro-forma JEV: one tab per delivery receipt ───────────── */
let amp_pf = { po: '', iar: '', receipts: [] };

async function amp_proforma(poNumber) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${AMP_API}/proforma?po_number=${encodeURIComponent(poNumber)}`, { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the pro-forma.');
        amp_pf = { po: json.data.po_number, iar: json.data.iar_number || '', receipts: json.data.receipts || [] };
        amp_renderProforma(0);
        $amp('amp-view-card').classList.add('amp-wide');
        $amp('amp-view-title').textContent = 'Pro-forma JEV';
        $amp('amp-view-ref').textContent = amp_pf.iar || '';
        $amp('amp-view-overlay').classList.add('open');
    } catch (e) {
        amp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* The pro-forma of one delivery receipt, built from that DR's stored account lines (line_transaction.lines): each line is a
   debit, and the DR total is credited to Accounts Payable. Every row can be edited — the account, and either a debit or a
   credit amount — and rows can be added / removed, in a draft kept per delivery receipt (tab). The total debit and the total
   credit may NOT exceed the DR's total amount: Submit is disabled while either does. The two totals must also be EQUAL (balanced). Nothing is saved yet. */
function amp_pfDraft(rc) {
    if (!rc.draft) {
        rc.draft = {
            rows: rc.rows.map(r => ({ code: r.account_code, title: r.account_title || '', debit: Number(r.debit) || 0, credit: Number(r.credit) || 0 })),
        };
        if (!rc.draft.rows.length) rc.draft.rows.push({ code: '', title: '', debit: 0, credit: 0 });
    }
    return rc.draft;
}

function amp_pfTotals(rc) {
    const d = amp_pfDraft(rc);
    const cents = k => Math.round(d.rows.reduce((t, r) => t + (Number(r[k]) || 0), 0) * 100);
    return { debit: cents('debit'), credit: cents('credit') };
}

function amp_renderProforma(active) {
    const rc = amp_pf.receipts[active];
    if (!rc) { $amp('amp-view-body').innerHTML = '<p>No delivery receipts.</p>'; return; }
    const d   = amp_pfDraft(rc);
    const num = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const lbl = r => r.code ? r.code + (r.title ? ' - ' + r.title : '') : '';
    const rowHtml = (r, i) => `<div class="amp-jev-row amp-pf-row" data-i="${i}">
            <div class="amp-jev-c1">
                <div class="amp-combo"><input type="text" class="amp-input amp-pf-acct" placeholder="Search account code or title" value="${amp_attrEsc(lbl(r))}"
                        data-code="${amp_attrEsc(r.code)}" data-label="${amp_attrEsc(lbl(r))}"><div class="amp-combo-list"></div></div>
                <button type="button" class="amp-link-danger amp-pf-del" title="Remove row"${d.rows.length < 2 ? ' disabled' : ''}>&times;</button>
            </div>
            <div class="amp-jev-amt"><span>${i === 0 ? '₱' : ''}</span><input type="text" inputmode="decimal" class="amp-input amp-pf-debit" placeholder="0.00" value="${Number(r.debit) > 0 ? amp_attrEsc(num(r.debit)) : ''}"></div>
            <div class="amp-jev-amt"><span></span><input type="text" inputmode="decimal" class="amp-input amp-pf-credit" placeholder="0.00" value="${Number(r.credit) > 0 ? amp_attrEsc(num(r.credit)) : ''}"></div>
        </div>`;
    const tab = (r, i) => `<button type="button" class="amp-tab${i === active ? ' active' : ''}" onclick="amp_renderProforma(${i})">${amp_esc(r.dr_number || '—')}</button>`;
    const vrow = (k, v) => `<div class="amp-vw-row"><div class="amp-vw-k">${k}</div><div class="amp-vw-v">${v}</div></div>`;
    $amp('amp-view-body').innerHTML = `
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Delivery Receipt</h4>
            ${vrow('IAR Number', amp_pf.iar ? '<span class="amp-vw-code">' + amp_esc(amp_pf.iar) + '</span>' : '—')}
            ${vrow('Delivery Receipt No.', amp_esc(rc.dr_number))}
            ${vrow('Total Amount', '<span class="amp-vw-num">₱' + num(rc.total) + '</span>')}
        </div>
        <div class="amp-vw-card"><h4 class="amp-vw-card-title">Pro-forma JEV</h4>
        <div class="amp-tabs">${amp_pf.receipts.map(tab).join('')}</div>
        <div class="amp-pf-top"><button type="button" class="amp-add-btn" id="amp-pf-add">+ Add row</button></div>
        <div class="amp-jev">
            <div class="amp-jev-head">
                <div class="amp-jev-h">Account Code - Account Name</div>
                <div class="amp-jev-h amp-jev-ctr">Debit (₱)</div>
                <div class="amp-jev-h amp-jev-ctr">Credit (₱)</div>
            </div>
            ${d.rows.map(rowHtml).join('')}
            <div class="amp-jev-row amp-jev-total">
                <div class="amp-jev-c1" style="gap:10px;">Total <span class="amp-jev-pill bad" id="amp-pf-pill" style="display:none;padding:4px 10px;font-size:11px;text-transform:none;letter-spacing:0;">&#10006; Exceeds DR total</span></div>
                <div class="amp-jev-amt"><span>₱</span><span id="amp-pf-tot-debit">0.00</span></div>
                <div class="amp-jev-amt"><span>₱</span><span id="amp-pf-tot-credit">0.00</span></div>
            </div>
        </div>
        <div class="amp-pf-bar">
            <span class="amp-pf-msg" id="amp-pf-msg"></span>
        </div>
        <div class="amp-jev-actions">
            <button type="button" class="nha-btn-act" style="margin-right:8px;" onclick="amp_printProformaJev(${active})" title="Print this pro-forma JEV (the rows as edited)">Print JEV</button>
            <button type="button" class="amp-jev-btn" id="amp-pf-submit" onclick="amp_submitJev(${active})">Submit</button>
        </div></div>`;

    // wire the editable rows
    document.querySelectorAll('#amp-view-body .amp-pf-row').forEach(el => {
        const i = Number(el.dataset.i), row = d.rows[i];
        amp_pfCombo(el.querySelector('.amp-combo'), acc => { row.code = acc.account_code; row.title = acc.account_title; amp_pfRefresh(active); });
        const deb = el.querySelector('.amp-pf-debit'), cre = el.querySelector('.amp-pf-credit');
        // a row is either a debit or a credit: typing in one clears the other
        deb.addEventListener('input', () => { deb.value = amp_liveFmt(deb.value, true); row.debit = parseFloat(amp_unfmt(deb.value)) || 0; if (deb.value) { cre.value = ''; row.credit = 0; } amp_pfRefresh(active); });
        cre.addEventListener('input', () => { cre.value = amp_liveFmt(cre.value, true); row.credit = parseFloat(amp_unfmt(cre.value)) || 0; if (cre.value) { deb.value = ''; row.debit = 0; } amp_pfRefresh(active); });
        deb.addEventListener('blur', () => { if (deb.value) deb.value = amp_fmtMoney(amp_unfmt(deb.value)); });
        cre.addEventListener('blur', () => { if (cre.value) cre.value = amp_fmtMoney(amp_unfmt(cre.value)); });
        el.querySelector('.amp-pf-del').addEventListener('click', () => { d.rows.splice(i, 1); amp_renderProforma(active); });
    });
    $amp('amp-pf-add').addEventListener('click', () => {
        d.rows.push({ code: '', title: '', debit: 0, credit: 0 });
        amp_renderProforma(active);
        const all = document.querySelectorAll('#amp-view-body .amp-pf-acct');
        all[all.length - 1]?.focus();
    });
    amp_pfRefresh(active);
}

/* null when the pro-forma can be submitted, else { text, hard, pill, submitOnly } — hard = shown as a red pill (exceeds the DR total);
   submitOnly = not shown while editing: it is only reported, as a SweetAlert warning, when Submit is clicked (not balanced) */
function amp_pfError(rc) {
    const d = amp_pfDraft(rc);
    const t = amp_pfTotals(rc);
    const cap = Math.round((Number(rc.total) || 0) * 100);
    if (d.rows.some(r => !r.code || !((Number(r.debit) > 0) || (Number(r.credit) > 0)))) return { text: 'Choose an account code and enter a debit or a credit amount for every row.', hard: false };
    if (t.debit > cap || t.credit > cap) {
        const f = c => (c / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return { text: 'The total (₱' + f(Math.max(t.debit, t.credit)) + ') exceeds the total amount of this delivery receipt (₱' + f(cap) + ').', hard: true, pill: '&#10006; Exceeds DR total' };
    }
    if (t.debit !== t.credit) {                     // a journal entry must balance
        const f = c => (c / 100).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return { text: 'Not balanced: total debit ₱' + f(t.debit) + ' and total credit ₱' + f(t.credit) + ' are not equal (difference ₱' + f(Math.abs(t.debit - t.credit)) + ').', submitOnly: true };
    }
    return null;
}

/* Updates the totals, the warning and the Submit button without redrawing (the inputs keep their focus) */
function amp_pfRefresh(active) {
    const rc = amp_pf.receipts[active];
    if (!rc) return;
    const money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const t = amp_pfTotals(rc);
    const set = (id, text) => { const el = $amp(id); if (el) el.textContent = text; };
    set('amp-pf-tot-debit', money(t.debit / 100));
    set('amp-pf-tot-credit', money(t.credit / 100));
    const err = amp_pfError(rc), msg = $amp('amp-pf-msg'), pill = $amp('amp-pf-pill');
    if (pill) { pill.innerHTML = err && err.pill ? err.pill : ''; pill.style.display = err && err.hard ? '' : 'none'; }   // warning pill: exceeds DR total / not balanced
    if (msg) {
        msg.textContent = err && !err.hard && !err.submitOnly ? err.text : '';           // grey: an incomplete row
        msg.classList.add('muted');
    }
    const btn = $amp('amp-pf-submit');
    if (btn) btn.disabled = !!err && !err.submitOnly;                                  // "not balanced" is reported on click, so the button stays enabled
}

// Submit the pro-forma as a JEV. It cannot be submitted when a total exceeds the DR total (checked again here). There is no
// JEV page / endpoint to receive it yet, so for now it only says so.
function amp_submitJev(active) {
    const rc = amp_pf.receipts[active];
    if (!rc) return;
    const err = amp_pfError(rc);
    if (err && err.submitOnly) {                              // not balanced: a SweetAlert warning (the app loads SweetAlert2 globally)
        if (window.Swal) {
            window.Swal.fire({ icon: 'warning', title: 'Not balanced', text: err.text, confirmButtonText: 'OK', confirmButtonColor: '#0f2045', customClass: { container: 'amp-swal' } });
        } else {
            amp_toast(err.text, 'warning');
        }
        return;
    }
    if (err) { amp_toast(err.text, 'error'); return; }
    amp_toast('Submitting a JEV for ' + rc.dr_number + ' is not available yet.', 'info');
}

/* ── Print (the shared print preview, included at the end) ─── */

// Print IAR → the IAR form from this PO's list row (IAR / SI numbers, its delivery receipts, and the PO details the list
// already carries: vendor, PO date, PO amount, balance) — prp_iarPayload() builds the payload.
function amp_printIar(i) {
    const r = amp_rows[i];
    if (r) prp_open(prp_iarPayload(r), { type: 'IAR' });
}

// Print JEV (pro-forma view) → the JEV form for the delivery receipt on screen, with the rows as edited (its draft).
function amp_printProformaJev(active) {
    const rc = amp_pf.receipts[active];
    if (rc) prp_open(prp_jevFromProforma(amp_pf.po, rc), { type: 'JEV' });
}

/* Searchable account code dropdown of a pro-forma row (GET /po-dr/accounts?search=): only an account picked from the list is kept */
function amp_pfCombo(wrap, onPick) {
    const input = wrap.querySelector('.amp-pf-acct'), list = wrap.querySelector('.amp-combo-list');
    let typed = false, timer = null, seq = 0, hits = [];
    const label = a => a.account_code + ' - ' + a.account_title;
    const render = () => {
        list.innerHTML = hits.length
            ? hits.map((a, i) => '<div class="amp-combo-opt' + (a.account_code === input.dataset.code ? ' active' : '') + '" data-i="' + i + '">' + amp_textEsc(label(a)) + '</div>').join('')
            : '<div class="amp-combo-empty">No matching account code</div>';
    };
    const load = async () => {
        const mine = ++seq;
        try {
            const q    = typed ? input.value.trim() : '';
            const res  = await fetch(AMP_API + '/accounts?search=' + encodeURIComponent(q), { credentials: 'same-origin', headers: amp_authHeaders() });
            const json = await res.json();
            if (mine !== seq) return;
            hits = json.success ? (json.data || []) : [];
        } catch (e) { if (mine !== seq) return; hits = []; }
        render();
    };
    const open = () => { wrap.classList.add('open'); render(); load(); };
    const pick = a => {
        input.value = input.dataset.label = label(a);
        input.dataset.code = a.account_code;
        typed = false;
        wrap.classList.remove('open');
        onPick(a);
    };
    input.addEventListener('focus', () => { typed = false; input.select(); open(); });
    input.addEventListener('click', () => { if (!wrap.classList.contains('open')) open(); });
    input.addEventListener('input', () => { typed = true; wrap.classList.add('open'); clearTimeout(timer); timer = setTimeout(load, 250); });
    list.addEventListener('mousedown', e => {
        const opt = e.target.closest('.amp-combo-opt');
        if (!opt) return;
        e.preventDefault();
        pick(hits[Number(opt.dataset.i)]);
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') wrap.classList.remove('open');
        if (e.key === 'Enter' && wrap.classList.contains('open')) { e.preventDefault(); if (hits[0]) pick(hits[0]); }
    });
    input.addEventListener('blur', () => {
        wrap.classList.remove('open');
        typed = false;
        if (input.value !== (input.dataset.label || '')) input.value = input.dataset.label || '';
    });
}

/* ── Pagination ────────────────────────────────────────────── */
function amp_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = AMP_PAGE_SIZE } = p;
    $amp('amp-from').textContent  = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    $amp('amp-to').textContent    = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $amp('amp-total').textContent = total;

    const container = $amp('amp-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="amp_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="amp_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="amp_goTo(${current_page + 1})">›</button>`;
}
function amp_goTo(page) { if (page < 1) return; amp_page = page; amp_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function amp_openModal(mode, row = null) {
    amp_hideErr();
    $amp('amp-mode').value = mode;
    $amp('amp-orig-po').value = row?.po_number || '';        // the PO is addressed by its current PO number
    $amp('amp-modal-title').textContent = mode === 'edit' ? 'Edit PO Record' : 'Add PO Record';
    amp_setPoValue(row);                                     // PO picker + office / vendor / PO date / PO amount (form/dr-module-form.php)
    // IAR number (typed by the user) and the Sales Invoice details live on the PO's IAR
    $amp('amp-iar-number').value = row?.iar_number || '';
    $amp('amp-si-number').value  = row?.si_number || '';
    $amp('amp-si-date').value    = row?.si_date || '';
    amp_initDrForm(row);                                      // one delivery receipt: DR no./date, balanced entries (form/dr-module-form.php)
    $amp('amp-overlay').classList.add('open');
    if (mode === 'add') $amp('amp-po-number').focus();
}

function amp_closeModal() {
    $amp('amp-overlay').classList.remove('open');
    $amp('amp-form').reset();
    $amp('amp-entries').innerHTML = '';
    amp_hideErr();
}

function amp_showErr(msg) { const el = $amp('amp-err'); el.textContent = msg; el.style.display = 'block'; }
function amp_hideErr() { $amp('amp-err').style.display = 'none'; }

async function amp_edit(poNumber) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${AMP_API}/read?po_number=${encodeURIComponent(poNumber)}`, { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load record.');
        amp_openModal('edit', json.data);
    } catch (e) {
        amp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function amp_save(ev) {
    ev.preventDefault();
    amp_hideErr();
    const mode = $amp('amp-mode').value;
    const orig = $amp('amp-orig-po').value;
    const payload = {
        po_number : $amp('amp-po-number').value.trim(),
        iar_number: $amp('amp-iar-number').value.trim(),      // typed by the user (required, unique)
        si_number : $amp('amp-si-number').value.trim(),       // saved on the PO's IAR (fims.inspection_acceptance_report)
        si_date   : $amp('amp-si-date').value,
        dr        : amp_collectDr(),                          // ONE delivery receipt (defined in form/dr-module-form.php)
    };
    const bad = amp_drError();                                 // can't submit while the entry is not balanced
    if (bad) { amp_showErr(bad); return; }

    $amp('amp-save').disabled = true;
    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${AMP_API}/update?po_number=${encodeURIComponent(orig)}` : `${AMP_API}/add`;
        const res    = await fetch(url, {
            method: mode === 'edit' ? 'PUT' : 'POST',
            credentials: 'same-origin',
            headers: amp_authHeaders({ 'Content-Type': 'application/json' }),
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save.');

        amp_toast(json.message || 'Saved.', 'success');
        amp_closeModal();
        amp_load();
    } catch (e) {
        amp_showErr(e.message);
        amp_updateTotals();                                    // re-enables Save only while the entry is balanced
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function amp_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function amp_escAttr(text) {
    return String(text ?? '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function amp_toast(msg, type = 'info') {
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
    const x = document.createElement('button');
    x.innerHTML = '&times;';
    x.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;';
    x.onclick = () => t.remove();
    t.append(span, x);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
<?php
// Print IAR / Print JEV → the shared print preview (prp_open + prp_iarPayload / prp_jevFromProforma).
include_once __DIR__ . '/readone/print-preview-readone.php';
?>
