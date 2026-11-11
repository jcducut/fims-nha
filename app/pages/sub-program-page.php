<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_sp_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="sp-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Sub-Programs</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="sp-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="sp-add" type="button" class="nha-btn-hdr" style="background:var(--afims-heading-fill, #000E3E);border:1px solid rgba(255,255,255,.3);" title="Add a sub-program">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Sub-Program
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Sub-Programs</div>
                    <div class="nha-sc-val" id="sp-kpi-total">—</div>
                </div>
            </div>
            <p class="nha-kpi-sub">All records</p>
        </div>
    </div>

    <!-- ── FILTERS ────────────────────────────────────────── -->
    <div class="nha-filters no-print">
        <div class="nha-filter-row">
            <div class="nha-filter-group">
                <label class="nha-filter-label">Program</label>
                <select id="sp-filter-program" class="nha-filter-select">
                    <option value="">All Programs</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="sp-filter-search" class="nha-filter-input" placeholder="Search sub-program code, name…">
                    <button class="nha-filter-clear-btn" id="sp-search-clear" hidden type="button">×</button>
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
                        <th>Sub-Program Code</th>
                        <th>Sub-Program Name</th>
                        <th>Program</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="sp-tbody">
                    <tr><td colspan="5"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="sp-from">0</span>–<span id="sp-to">0</span></strong> of <strong><span id="sp-total">0</span></strong></span>
            <div id="sp-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/sub-program-form.php'; ?>

</div><!-- /#sp-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   SUB-PROGRAMS LIST   prefix: sp_
═══════════════════════════════════════════════════════════════ */

const SP_API         = '<?php echo $_sp_api; ?>' + '/sub_program';
const SP_PROGRAM_API = '<?php echo $_sp_api; ?>' + '/program/all';

let sp_page = 1;
const SP_PAGE_SIZE = 15;
let sp_liSeq = 0;
let sp_codePrefix = null;   // last 3 digits of the selected program code, e.g. "001"
let sp_codeNext   = 1;      // next sub-program number for that prefix (from GET /sub_program/next-code)
let sp_codeReq    = 0;      // guards against out-of-order responses when the program changes quickly

const $sp = id => document.getElementById(id);

async function sp_bootstrapLoad() {
    await Promise.all([sp_load(), sp_loadPrograms()]);
    sp_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', sp_bootstrapLoad);
} else {
    sp_bootstrapLoad();
}

async function sp_loadPrograms() {
    try {
        const res  = await fetch(SP_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load programs.');

        const rows = json.data ?? [];
        const options = rows.map(p =>
            `<option value="${sp_escAttr(p.program_code)}">${sp_esc(p.program_code)} — ${sp_esc(p.program_name)}</option>`
        ).join('');

        $sp('sp-filter-program').insertAdjacentHTML('beforeend', options);
        $sp('sp-program-code').insertAdjacentHTML('beforeend', options);
    } catch (e) {
        sp_toast(e.message, 'error');
    }
}

function sp_bindEvents() {
    $sp('sp-refresh')?.addEventListener('click', () => { sp_page = 1; sp_load(); });
    $sp('sp-add')?.addEventListener('click', () => sp_openModal('add'));
    $sp('sp-modal-close')?.addEventListener('click', sp_closeModal);
    $sp('sp-cancel')?.addEventListener('click', sp_closeModal);
    $sp('sp-form')?.addEventListener('submit', sp_save);
    $sp('sp-li-add')?.addEventListener('click', () => sp_addLineItemRow());
    $sp('sp-program-code')?.addEventListener('change', sp_refreshLineItemCodes);

    $sp('sp-filter-program')?.addEventListener('change', () => { sp_page = 1; sp_load(); });

    let searchTimer;
    const searchEl = $sp('sp-filter-search');
    const clearBtn = $sp('sp-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { sp_page = 1; sp_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        sp_page = 1; sp_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function sp_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search       : $sp('sp-filter-search')?.value  || '',
            program_code : $sp('sp-filter-program')?.value || '',
            page         : sp_page,
            limit        : SP_PAGE_SIZE,
        });
        const res  = await fetch(`${SP_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        sp_renderTable(json.data ?? []);
        sp_renderPagination(json.pagination ?? {});
        $sp('sp-kpi-total').textContent = json.pagination?.total ?? (json.data ?? []).length;
    } catch (e) {
        sp_renderTable([]);
        sp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function sp_renderTable(rows) {
    const tbody = $sp('sp-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No sub-programs found.</span></div></td></tr>';
        return;
    }
    const offset = (sp_page - 1) * SP_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><code class="nha-code">${sp_esc(r.sub_program_code)}</code></td>
        <td><div style="font-weight:600;">${sp_esc(r.sub_program_name)}</div></td>
        <td>
            <div style="font-weight:500;">${sp_esc(r.program_code)}</div>
            ${r.program_name ? `<div class="nha-td-sub" style="margin-top:2px;">${sp_esc(r.program_name)}</div>` : ''}
        </td>
        <td>
            <button class="nha-btn-act edit" onclick="sp_edit('${sp_escAttr(r.sub_program_code)}')" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
            <button class="nha-btn-act danger" onclick="sp_delete('${sp_escAttr(r.sub_program_code)}')" title="Delete">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>
        </td>
    </tr>`).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function sp_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = SP_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $sp('sp-from').textContent  = from;
    $sp('sp-to').textContent    = to;
    $sp('sp-total').textContent = total;

    const container = $sp('sp-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="sp_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="sp_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="sp_goTo(${current_page + 1})">›</button>`;
}

function sp_goTo(page) { sp_page = page; sp_load(); }

/* ── Line items (Add mode) ────────────────────────────────── */
function sp_addLineItemRow(values = null) {
    const id = ++sp_liSeq;
    // No explicit code -> auto-generate "<last 3 digits of program>-<NNN>" (001-001, 001-002, …).
    const autoCode = values ? '' : sp_takeCode();
    const row = document.createElement('div');
    row.className = 'sp-li-row';
    row.dataset.rowId = id;
    row.innerHTML = `
        <input type="text" class="sp-input sp-li-code" maxlength="20" placeholder="e.g. SUBPRG-001" value="${sp_escAttr(values?.sub_program_code || autoCode)}">
        <input type="text" class="sp-input sp-li-name" maxlength="255" placeholder="e.g. Core Shelter Assistance" value="${sp_escAttr(values?.sub_program_name || '')}">
        <button type="button" class="sp-li-rm" title="Remove"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg></button>
    `;
    row.querySelector('.sp-li-rm').addEventListener('click', () => sp_removeLineItemRow(id));
    const codeInput = row.querySelector('.sp-li-code');
    if (autoCode) codeInput.dataset.auto = '1';
    codeInput.addEventListener('input', () => { delete codeInput.dataset.auto; });   // user edited it: leave it alone
    $sp('sp-li-rows').appendChild(row);
    sp_updateLineItemRemoveState();
}

function sp_removeLineItemRow(id) {
    const row = $sp('sp-li-rows').querySelector(`[data-row-id="${id}"]`);
    row?.remove();
    sp_updateLineItemRemoveState();
}

function sp_updateLineItemRemoveState() {
    const rows = $sp('sp-li-rows').querySelectorAll('.sp-li-row');
    rows.forEach(row => {
        row.querySelector('.sp-li-rm').disabled = rows.length <= 1;
    });
}

function sp_clearLineItems() {
    $sp('sp-li-rows').innerHTML = '';
    sp_liSeq = 0;
    sp_codePrefix = null;
    sp_codeNext   = 1;
    sp_codeReq++;
}

/* ── Auto-generated sub-program codes ─────────────────────── */
function sp_takeCode() {
    if (sp_codePrefix === null) return '';
    return `${sp_codePrefix}-${String(sp_codeNext++).padStart(3, '0')}`;
}

// Program picked/changed: fetch where its sequence starts, then (re)number the rows still holding
// an auto-generated (or empty) code. Rows the user typed a code into are kept as-is.
async function sp_refreshLineItemCodes() {
    if ($sp('sp-mode').value !== 'add') return;
    const programCode = $sp('sp-program-code').value;
    const req = ++sp_codeReq;
    sp_codePrefix = null;
    sp_codeNext   = 1;

    const rows = Array.from($sp('sp-li-rows').querySelectorAll('.sp-li-code'));
    rows.forEach(inp => { if (inp.dataset.auto === '1') { inp.value = ''; delete inp.dataset.auto; } });
    if (!programCode) return;

    try {
        const res  = await fetch(`${SP_API}/next-code?program_code=${encodeURIComponent(programCode)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Could not generate sub-program codes.');
        if (req !== sp_codeReq) return;   // program changed again, or the modal was closed

        sp_codePrefix = json.data.prefix;
        sp_codeNext   = json.data.next;
        $sp('sp-li-rows').querySelectorAll('.sp-li-code').forEach(inp => {
            if (inp.value.trim() === '') { inp.value = sp_takeCode(); inp.dataset.auto = '1'; }
        });
    } catch (e) {
        if (req === sp_codeReq) sp_showErr(e.message);
    }
}

/* ── Modal ─────────────────────────────────────────────────── */
function sp_openModal(mode, row = null) {
    sp_hideErr();
    $sp('sp-mode').value = mode;
    $sp('sp-modal-title').textContent = mode === 'edit' ? 'Edit Sub-Program' : 'Add Sub-Program';
    $sp('sp-program-code').value = row?.program_code || '';

    if (mode === 'edit') {
        $sp('sp-edit-fields').style.display = '';
        $sp('sp-li-section').style.display = 'none';
        $sp('sp-sub-program-code').value = row?.sub_program_code || '';
        $sp('sp-sub-program-code').disabled = true;
        $sp('sp-sub-program-name').value = row?.sub_program_name || '';
    } else {
        $sp('sp-edit-fields').style.display = 'none';
        $sp('sp-li-section').style.display = '';
        sp_clearLineItems();
        sp_addLineItemRow();
    }

    $sp('sp-overlay').classList.add('open');
}

function sp_closeModal() {
    $sp('sp-overlay').classList.remove('open');
    $sp('sp-form').reset();
    sp_clearLineItems();
    sp_hideErr();
}

function sp_showErr(msg) {
    const el = $sp('sp-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function sp_hideErr() {
    $sp('sp-err').style.display = 'none';
}

async function sp_edit(code) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${SP_API}/read/${encodeURIComponent(code)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load sub-program.');
        sp_openModal('edit', json.data);
    } catch (e) {
        sp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function sp_save(ev) {
    ev.preventDefault();
    sp_hideErr();
    const mode = $sp('sp-mode').value;
    const programCode = $sp('sp-program-code').value;

    window.PagePreloader?.show();
    try {
        let url, method, payload;

        if (mode === 'edit') {
            const code = $sp('sp-sub-program-code').value.trim();
            payload = {
                sub_program_name : $sp('sp-sub-program-name').value.trim(),
                program_code     : programCode,
            };
            url    = `${SP_API}/update/${encodeURIComponent(code)}`;
            method = 'PUT';
        } else {
            const rows = Array.from($sp('sp-li-rows').querySelectorAll('.sp-li-row'));
            const subPrograms = rows.map(row => ({
                sub_program_code : row.querySelector('.sp-li-code').value.trim(),
                sub_program_name : row.querySelector('.sp-li-name').value.trim(),
            }));

            if (!programCode) throw new Error('Please select a program.');
            if (!subPrograms.length) throw new Error('Please add at least one sub-program.');
            for (const sp of subPrograms) {
                if (!sp.sub_program_code || !sp.sub_program_name) {
                    throw new Error('Each line item needs both a code and a name.');
                }
            }

            payload = { program_code: programCode, sub_programs: subPrograms };
            url    = `${SP_API}/add-with-items`;
            method = 'POST';
        }

        const res  = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save sub-program.');

        sp_toast(`Sub-program ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        sp_closeModal();
        sp_load();
    } catch (e) {
        sp_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function sp_delete(code) {
    if (!confirm(`Delete sub-program '${code}'? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${SP_API}/delete/${encodeURIComponent(code)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete sub-program.');

        sp_toast('Sub-program deleted successfully.', 'success');
        sp_load();
    } catch (e) {
        sp_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function sp_esc(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function sp_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
}

function sp_toast(msg, type = 'info') {
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
