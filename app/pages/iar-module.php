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
                    <input type="text" id="amp-filter-search" class="nha-filter-input" placeholder="Search PO no., DR no., description, invoice, serial, property, PAR…">
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
                        <th>PO Number</th>
                        <th>IAR Number</th>
                        <th>Sales Invoice #</th>
                        <th>PO Date</th>
                        <th>Requesting Office/Dept</th>
                        <th style="text-align:right;">Total Amount</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="amp-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
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
   DR MODULE PO LIST   prefix: amp_   (1 PO → many delivery receipt lines)
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
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No PO records found.</span></div></td></tr>';
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
            <td style="vertical-align:top;"><code class="nha-code">${amp_esc(r.po_number)}</code></td>
            <td style="vertical-align:top;white-space:nowrap;">${r.iar_number ? '<code class="nha-code">' + amp_esc(r.iar_number) + '</code>' : '—'}</td>
            <td style="vertical-align:top;">${amp_esc(r.si_number || '—')}</td>
            <td style="vertical-align:top;white-space:nowrap;">${amp_esc(r.po_date || '—')}</td>          <!-- front-end only for now: no service/data yet -->
            <td style="vertical-align:top;">${amp_esc(r.requesting_office || '—')}</td>
            <td style="vertical-align:top;text-align:right;white-space:nowrap;">${line('<strong>' + money((r.lines || []).reduce((t, ln) => t + Number(ln.amount || 0), 0)) + '</strong>', 'justify-content:flex-end;')}</td>
            ${actionsCell(r)}
        </tr>`;
    }).join('');
    function actionsCell(r) {
        return `<td style="white-space:nowrap;vertical-align:top;">
            <button class="nha-btn-act" onclick="amp_viewAll(${rows.indexOf(r)})" title="View all items of this PO">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                View
            </button>
            <button class="nha-btn-act" onclick="amp_proforma('${amp_escAttr(r.po_number)}')" title="Pro-forma JEV of each delivery receipt">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Pro-forma
            </button>
            <button class="nha-btn-act edit" onclick="amp_edit('${amp_escAttr(r.po_number)}')" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
            <button class="nha-btn-act danger" onclick="amp_delete('${amp_escAttr(r.po_number)}')" title="Delete">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>
        </td>`;
    }
}


// Open every item of the PO, with its delivery receipt number (Actions → View)
function amp_viewAll(i) {
    const r = amp_rows[i];
    if (!r) return;
    const money = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const dash = v => v ? amp_esc(v) : '—';
    const lines = r.lines || [];
    const total = lines.reduce((s, ln) => s + Number(ln.amount || 0), 0);
    $amp('amp-view-body').innerHTML = `
        <div class="amp-v-meta">
            <div><div class="k">PO Number</div><div class="v">${amp_esc(r.po_number)}</div></div>
            <div><div class="k">IAR Number</div><div class="v">${dash(r.iar_number)}</div></div>
            <div><div class="k">Sales Invoice #</div><div class="v">${dash(r.si_number)}</div></div>
            <div><div class="k">Sales Invoice Date</div><div class="v">${dash(r.si_date)}</div></div>
            <div><div class="k">Items</div><div class="v">${lines.length}</div></div>
        </div>
        <table class="amp-v-table">
            <thead><tr><th>#</th><th>Items</th><th>Delivery Date</th><th>Delivery Receipt</th><th style="text-align:center;">Quantity</th><th style="text-align:center;">Unit</th><th style="text-align:right;">Unit Price</th><th style="text-align:right;">Amount</th></tr></thead>
            <tbody>${lines.map((ln, n) => `<tr>
                <td>${n + 1}</td>
                <td style="white-space:pre-wrap;">${dash(ln.description)}</td>
                <td style="white-space:nowrap;">${dash(ln.delivery_date)}</td>
                <td>${dash(ln.dr_number)}</td>
                <td style="text-align:center;">${Number(ln.quantity || 0).toLocaleString('en-PH', { maximumFractionDigits: 0 })}</td>
                <td style="text-align:center;">${dash(ln.unit)}</td>
                <td style="text-align:right;white-space:nowrap;">${money(ln.unit_price)}</td>
                <td style="text-align:right;white-space:nowrap;">${money(ln.amount)}</td></tr>`).join('')}</tbody>
            <tfoot><tr><td colspan="7" style="text-align:right;">Total</td><td style="text-align:right;">${money(total)}</td></tr></tfoot>
        </table>`;
    $amp('amp-view-title').textContent = 'Actual Delivery';
    $amp('amp-view-overlay').classList.add('open');
}

/* ── Pro-forma JEV: one tab per delivery receipt ───────────── */
let amp_pf = { po: '', receipts: [] };

async function amp_proforma(poNumber) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${AMP_API}/proforma?po_number=${encodeURIComponent(poNumber)}`, { credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the pro-forma.');
        amp_pf = { po: json.data.po_number, receipts: json.data.receipts || [] };
        amp_renderProforma(0);
        $amp('amp-view-title').textContent = 'Pro-forma JEV';
        $amp('amp-view-overlay').classList.add('open');
    } catch (e) {
        amp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

function amp_renderProforma(active) {
    const money = v => Number(v || 0) ? Number(v).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
    const rc = amp_pf.receipts[active];
    if (!rc) { $amp('amp-view-body').innerHTML = '<p>No delivery receipts.</p>'; return; }
    const peso = v => Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const totDebit  = rc.rows.reduce((t, r) => t + Number(r.debit  || 0), 0);
    const totCredit = rc.rows.reduce((t, r) => t + Number(r.credit || 0), 0);
    const diff      = Math.round((totDebit - totCredit) * 100) / 100;
    const balanced  = diff === 0;
    $amp('amp-view-body').innerHTML = `
        <div class="amp-v-meta">
            <div><div class="k">PO Number</div><div class="v">${amp_esc(amp_pf.po)}</div></div>
            <div><div class="k">Delivery Receipt No.</div><div class="v">${amp_esc(rc.dr_number)}</div></div>
        </div>
        <div class="amp-tabs">${amp_pf.receipts.map((r, i) =>
            `<button type="button" class="amp-tab${i === active ? ' active' : ''}" onclick="amp_renderProforma(${i})">${amp_esc(r.dr_number || '—')}</button>`).join('')}</div>
        <div class="amp-jev">
            <div class="amp-jev-head">
                <div class="amp-jev-h amp-jev-c1" style="grid-row:1 / span 2;">Accounts and Explanation</div>
                <div class="amp-jev-h amp-jev-ctr" style="grid-row:1 / span 2;grid-column:2;">SL<br>Code</div>
                <div class="amp-jev-h amp-jev-ctr amp-jev-grp" style="grid-row:1;grid-column:3 / span 2;">Subsidiary Ledger</div>
                <div class="amp-jev-h amp-jev-ctr" style="grid-row:1 / span 2;grid-column:5;">GL Acct<br>Code</div>
                <div class="amp-jev-h amp-jev-ctr amp-jev-grp" style="grid-row:1;grid-column:6 / span 2;">General Ledger</div>
                <div class="amp-jev-h amp-jev-ctr" style="grid-row:2;grid-column:3;">Debit (₱)</div>
                <div class="amp-jev-h amp-jev-ctr" style="grid-row:2;grid-column:4;">Credit (₱)</div>
                <div class="amp-jev-h amp-jev-ctr" style="grid-row:2;grid-column:6;">Debit (₱)</div>
                <div class="amp-jev-h amp-jev-ctr" style="grid-row:2;grid-column:7;">Credit (₱)</div>
            </div>
            ${rc.rows.length ? rc.rows.map((r, i) => {
                // amount cell: the peso sign sits at the left of the first row's debit cells (accounting format)
                const amt = (v, peso) => `<div class="amp-jev-amt"><span>${peso && Number(v || 0) ? '₱' : ''}</span><span>${money(v)}</span></div>`;
                return `<div class="amp-jev-row">
                    <div class="amp-jev-c1">${amp_esc(r.account_title)}</div>
                    <div></div>
                    ${amt(r.debit, i === 0)}
                    ${amt(r.credit, false)}
                    <div class="amp-jev-code">${amp_esc(r.account_code)}</div>
                    ${amt(r.debit, i === 0)}
                    ${amt(r.credit, false)}</div>`;
            }).join('') : '<div class="amp-jev-empty">No lines yet.</div>'}
        </div>
        <div class="amp-jev-sum">
            <div class="amp-jev-card"><div class="k">Total Debit</div><div class="v" style="color:var(--afims-primary-text, #0035A9);">₱${peso(totDebit)}</div></div>
            <div class="amp-jev-card"><div class="k">Total Credit</div><div class="v" style="color:var(--afims-primary-text, #0035A9);">₱${peso(totCredit)}</div></div>
            <div class="amp-jev-card"><div class="k">Difference</div><div class="v" style="color:${balanced ? '#16A34A' : 'var(--afims-danger, #DC3545)'};">₱${peso(diff)}</div></div>
            <div class="amp-jev-status">
                <div class="amp-jev-pill ${balanced ? 'ok' : 'bad'}">${balanced ? '&#10004; Balanced' : '&#10006; Not balanced'}</div>            </div>
        </div>
        <div class="amp-jev-actions">
            <button type="button" class="amp-jev-btn"${balanced ? '' : ' disabled'}>Create JEV</button>
        </div>`;
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
    $amp('amp-po-number').value = row?.po_number || '';
    // IAR number (auto-generated, read-only) and the Sales Invoice details live on the PO's IAR
    $amp('amp-iar-number').value = row?.iar_number || '';
    $amp('amp-si-number').value  = row?.si_number || '';
    $amp('amp-si-date').value    = row?.si_date || '';
    $amp('amp-lines').innerHTML = '';
    const lines = row?.lines?.length ? row.lines : [{}];     // always at least one line
    lines.forEach(ln => amp_addLine(ln));                     // defined in form/dr-module-form.php
    $amp('amp-save').disabled = false;
    $amp('amp-overlay').classList.add('open');
    $amp('amp-po-number').focus();
}

function amp_closeModal() {
    $amp('amp-overlay').classList.remove('open');
    $amp('amp-form').reset();
    $amp('amp-lines').innerHTML = '';
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
        si_number : $amp('amp-si-number').value.trim(),       // saved on the PO's IAR (fims.iar_module)
        si_date   : $amp('amp-si-date').value,
        lines     : amp_collectLines(),                       // defined in form/dr-module-form.php
    };
    if (!payload.lines.length) { amp_showErr('Add at least one delivery receipt line.'); return; }

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
        $amp('amp-save').disabled = false;
    } finally {
        window.PagePreloader?.hide();
    }
}

async function amp_delete(poNumber) {
    if (!confirm(`Delete PO '${poNumber}' with all its delivery receipt lines? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${AMP_API}/delete?po_number=${encodeURIComponent(poNumber)}`, { method: 'DELETE', credentials: 'same-origin', headers: amp_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete.');

        amp_toast(json.message || 'Deleted.', 'success');
        amp_load();
    } catch (e) {
        amp_toast(e.message, 'error');
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
