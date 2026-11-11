<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_vnd_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="vnd-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Vendors</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="vnd-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="vnd-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a vendor">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Vendor
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS (cover every row matching the filters, not just this page) ── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Total Vendors</div>
                <div class="nha-sc-val" id="vnd-kpi-total">—</div>
            </div></div>
            <p class="nha-kpi-sub">Matches current filters</p>
        </div>
        <div class="nha-sc nha-sc-green">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Active</div>
                <div class="nha-sc-val" id="vnd-kpi-active">—</div>
            </div></div>
            <p class="nha-kpi-sub">Can be used on purchase orders</p>
        </div>
        <div class="nha-sc nha-sc-amber">
            <div class="nha-sc-top"><div>
                <div class="nha-sc-label">Inactive / Blacklisted</div>
                <div class="nha-sc-val" id="vnd-kpi-inactive">—</div>
            </div></div>
            <p class="nha-kpi-sub">Hidden from the PO vendor list</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Vendor Type</label>
                <select id="vnd-filter-type" class="nha-filter-select">
                    <option value="">All Types</option>
                </select>
            </div>
            <div class="nha-filter-group">
                <label class="nha-filter-label">Status</label>
                <select id="vnd-filter-status" class="nha-filter-select">
                    <option value="">All Status</option>
                    <option value="ACTIVE">Active</option>
                    <option value="INACTIVE">Inactive</option>
                    <option value="BLACKLISTED">Blacklisted</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="vnd-filter-search" class="nha-filter-input" placeholder="Search vendor code, name, TIN…">
                    <button class="nha-filter-clear-btn" id="vnd-search-clear" hidden type="button">×</button>
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
                        <th>Vendor Code</th>
                        <th>Vendor Name</th>
                        <th>Type</th>
                        <th>Contact</th>
                        <th>Tax</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="vnd-tbody">
                    <tr><td colspan="8"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="vnd-from">0</span>–<span id="vnd-to">0</span></strong> of <strong><span id="vnd-total">0</span></strong></span>
            <div id="vnd-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/vendor-form.php'; ?>

</div><!-- /#vnd-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   VENDORS LIST   prefix: vnd_
   (routes live under /vendors — nginx blocks any URL containing /vendor/)
═══════════════════════════════════════════════════════════════ */

const VND_API = '<?php echo $_vnd_api; ?>' + '/vendors';

let vnd_page = 1;
const VND_PAGE_SIZE = 15;

const $vnd = id => document.getElementById(id);

async function vnd_bootstrapLoad() {
    await Promise.all([vnd_load(), vnd_loadTypes()]);
    vnd_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', vnd_bootstrapLoad);
} else {
    vnd_bootstrapLoad();
}

/* Vendor types fill both the filter and the form's select. */
async function vnd_loadTypes() {
    try {
        const res  = await fetch(`${VND_API}/types`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load vendor types.');

        const options = (json.data ?? []).map(t =>
            `<option value="${vnd_escAttr(t.vendor_type)}">${vnd_esc(t.vendor_type)}</option>`
        ).join('');
        $vnd('vnd-filter-type').insertAdjacentHTML('beforeend', options);
        $vnd('vnd-vendor-type').insertAdjacentHTML('beforeend', options);
    } catch (e) {
        vnd_toast(e.message, 'error');
    }
}

function vnd_bindEvents() {
    $vnd('vnd-refresh')?.addEventListener('click', () => { vnd_page = 1; vnd_load(); });
    $vnd('vnd-add')?.addEventListener('click', () => vnd_openModal('add'));
    $vnd('vnd-modal-close')?.addEventListener('click', vnd_closeModal);
    $vnd('vnd-cancel')?.addEventListener('click', vnd_closeModal);
    $vnd('vnd-form')?.addEventListener('submit', vnd_save);

    ['vnd-filter-type', 'vnd-filter-status'].forEach(id => {
        $vnd(id)?.addEventListener('change', () => { vnd_page = 1; vnd_load(); });
    });

    let searchTimer;
    const searchEl = $vnd('vnd-filter-search');
    const clearBtn = $vnd('vnd-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { vnd_page = 1; vnd_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        vnd_page = 1; vnd_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function vnd_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search : $vnd('vnd-filter-search')?.value || '',
            type   : $vnd('vnd-filter-type')?.value   || '',
            status : $vnd('vnd-filter-status')?.value || '',
            page   : vnd_page,
            limit  : VND_PAGE_SIZE,
        });
        const res  = await fetch(`${VND_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        vnd_renderTable(json.data ?? []);
        vnd_renderPagination(json.pagination ?? {});
        $vnd('vnd-kpi-total').textContent    = json.pagination?.total ?? (json.data ?? []).length;
        $vnd('vnd-kpi-active').textContent   = json.summary?.active   ?? '—';
        $vnd('vnd-kpi-inactive').textContent = json.summary?.inactive ?? '—';
    } catch (e) {
        vnd_renderTable([]);
        vnd_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function vnd_truthy(v) { return v === true || v === 1 || v === '1' || v === 't' || v === 'true'; }

function vnd_statusBadge(s) {
    const st  = (s || 'ACTIVE').toUpperCase();
    const map = { ACTIVE: ['nha-bdg-green', 'Active'], INACTIVE: ['nha-bdg-gray', 'Inactive'], BLACKLISTED: ['nha-bdg-red', 'Blacklisted'] };
    const [cls, label] = map[st] || ['nha-bdg-gray', st];
    return `<span class="nha-bdg ${cls}">${vnd_esc(label)}</span>`;
}

function vnd_renderTable(rows) {
    const tbody = $vnd('vnd-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="8"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No vendors found.</span></div></td></tr>';
        return;
    }
    const offset = (vnd_page - 1) * VND_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => {
        const contact = [r.contact_number, r.email].filter(Boolean).map(vnd_esc).join(' · ');
        return `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><code class="nha-code">${vnd_esc(r.vendor_code)}</code></td>
        <td>
            <div style="font-weight:600;">${vnd_esc(r.vendor_name)}</div>
            ${r.tin ? `<div class="nha-td-sub" style="margin-top:2px;">TIN ${vnd_esc(r.tin)}</div>` : ''}
        </td>
        <td>${r.vendor_type ? `<span class="nha-bdg nha-bdg-blue">${vnd_esc(r.vendor_type)}</span>` : '—'}</td>
        <td>
            <div>${vnd_esc(r.contact_person || '—')}</div>
            ${contact ? `<div class="nha-td-sub" style="margin-top:2px;">${contact}</div>` : ''}
        </td>
        <td>
            ${vnd_truthy(r.is_vat_registered) ? '<span class="nha-bdg nha-bdg-teal">VAT</span>' : '<span class="nha-bdg nha-bdg-gray">Non-VAT</span>'}
            ${r.tax_classification ? `<div class="nha-td-sub" style="margin-top:2px;">${vnd_esc(r.tax_classification)}</div>` : ''}
        </td>
        <td>${vnd_statusBadge(r.status)}</td>
        <td>
            <button class="nha-btn-act edit" onclick="vnd_edit(${Number(r.vendor_id)})" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
        </td>
    </tr>`;
    }).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function vnd_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = VND_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $vnd('vnd-from').textContent  = from;
    $vnd('vnd-to').textContent    = to;
    $vnd('vnd-total').textContent = total;

    const container = $vnd('vnd-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="vnd_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="vnd_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="vnd_goTo(${current_page + 1})">›</button>`;
}

function vnd_goTo(page) { if (page >= 1) { vnd_page = page; vnd_load(); } }

/* ── Modal ─────────────────────────────────────────────────── */
const VND_TEXT_FIELDS = {          // payload key → input id
    vendor_code         : 'vnd-vendor-code',
    vendor_name         : 'vnd-vendor-name',
    vendor_type         : 'vnd-vendor-type',
    status              : 'vnd-status',
    tin                 : 'vnd-tin',
    tax_classification  : 'vnd-tax-class',
    address             : 'vnd-address',
    contact_person      : 'vnd-contact-person',
    contact_number      : 'vnd-contact-number',
    email               : 'vnd-email',
    bank_name           : 'vnd-bank-name',
    bank_account_number : 'vnd-bank-acct',
};

function vnd_openModal(mode, row = null) {
    vnd_hideErr();
    $vnd('vnd-mode').value = mode;
    $vnd('vnd-id').value   = row?.vendor_id || '';
    $vnd('vnd-modal-title').textContent = mode === 'edit' ? 'Edit Vendor' : 'Add Vendor';

    for (const [key, id] of Object.entries(VND_TEXT_FIELDS)) {
        $vnd(id).value = row?.[key] ?? '';
    }
    if (!row?.status) $vnd('vnd-status').value = 'ACTIVE';
    $vnd('vnd-vat').checked = vnd_truthy(row?.is_vat_registered);

    $vnd('vnd-vendor-code').disabled = mode === 'edit';
    $vnd('vnd-code-hint').textContent = mode === 'edit'
        ? 'Vendor codes cannot be changed after creation.'
        : 'Unique identifier. Cannot be changed after creation.';
    $vnd('vnd-overlay').classList.add('open');
}

function vnd_closeModal() {
    $vnd('vnd-overlay').classList.remove('open');
    $vnd('vnd-form').reset();
    vnd_hideErr();
}

function vnd_showErr(msg) {
    const el = $vnd('vnd-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function vnd_hideErr() {
    $vnd('vnd-err').style.display = 'none';
}

async function vnd_edit(id) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${VND_API}/read/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load vendor.');
        vnd_openModal('edit', json.data);
    } catch (e) {
        vnd_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function vnd_save(ev) {
    ev.preventDefault();
    vnd_hideErr();
    const mode = $vnd('vnd-mode').value;
    const id   = $vnd('vnd-id').value;

    const payload = {};
    for (const [key, inputId] of Object.entries(VND_TEXT_FIELDS)) {
        payload[key] = $vnd(inputId).value.trim();
    }
    payload.is_vat_registered = $vnd('vnd-vat').checked;
    if (mode === 'edit') delete payload.vendor_code;       // locked

    if (mode === 'add' && !payload.vendor_code) { vnd_showErr('Vendor code is required.'); return; }
    if (!payload.vendor_name)                   { vnd_showErr('Vendor name is required.'); return; }
    if (payload.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(payload.email)) { vnd_showErr('Email address is not valid.'); return; }

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${VND_API}/update/${encodeURIComponent(id)}` : `${VND_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save vendor.');

        vnd_toast(`Vendor ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        vnd_closeModal();
        vnd_load();
    } catch (e) {
        vnd_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function vnd_esc(text) {
    if (text === null || text === undefined || text === '') return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function vnd_escAttr(text) {
    return vnd_esc(text);
}

function vnd_toast(msg, type = 'info') {
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
    span.textContent = msg;                 // textContent — messages may contain server text
    const close = document.createElement('button');
    close.innerHTML = '&times;';
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
