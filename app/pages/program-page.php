<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_prg_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="prg-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Programs</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="prg-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="prg-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a program">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Program
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Programs</div>
                    <div class="nha-sc-val" id="prg-kpi-total">—</div>
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
                    <input type="text" id="prg-filter-search" class="nha-filter-input" placeholder="Search program code, name…">
                    <button class="nha-filter-clear-btn" id="prg-search-clear" hidden type="button">×</button>
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
                        <th>Program Code</th>
                        <th>Program Name</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="prg-tbody">
                    <tr><td colspan="4"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="prg-from">0</span>–<span id="prg-to">0</span></strong> of <strong><span id="prg-total">0</span></strong></span>
            <div id="prg-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/program-form.php'; ?>

</div><!-- /#prg-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PROGRAMS LIST   prefix: prg_
═══════════════════════════════════════════════════════════════ */

const PRG_API = '<?php echo $_prg_api; ?>' + '/program';

let prg_page = 1;
const PRG_PAGE_SIZE = 15;

const $prg = id => document.getElementById(id);

async function prg_bootstrapLoad() {
    await prg_load();
    prg_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', prg_bootstrapLoad);
} else {
    prg_bootstrapLoad();
}

function prg_bindEvents() {
    $prg('prg-refresh')?.addEventListener('click', () => { prg_page = 1; prg_load(); });
    $prg('prg-add')?.addEventListener('click', () => prg_openModal('add'));
    $prg('prg-modal-close')?.addEventListener('click', prg_closeModal);
    $prg('prg-cancel')?.addEventListener('click', prg_closeModal);
    $prg('prg-form')?.addEventListener('submit', prg_save);

    let searchTimer;
    const searchEl = $prg('prg-filter-search');
    const clearBtn = $prg('prg-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { prg_page = 1; prg_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        prg_page = 1; prg_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function prg_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search    : $prg('prg-filter-search')?.value || '',
            page      : prg_page,
            limit     : PRG_PAGE_SIZE,
        });
        const res  = await fetch(`${PRG_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        prg_renderTable(json.data ?? []);
        prg_renderPagination(json.pagination ?? {});
        $prg('prg-kpi-total').textContent = json.pagination?.total ?? (json.data ?? []).length;
    } catch (e) {
        prg_renderTable([]);
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function prg_renderTable(rows) {
    const tbody = $prg('prg-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="4"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No programs found.</span></div></td></tr>';
        return;
    }
    const offset = (prg_page - 1) * PRG_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><code class="nha-code">${prg_esc(r.program_code)}</code></td>
        <td><div style="font-weight:600;">${prg_esc(r.program_name)}</div></td>
        <td>
            <button class="nha-btn-act edit" onclick="prg_edit('${prg_escAttr(r.program_code)}')" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
            <button class="nha-btn-act danger" onclick="prg_delete('${prg_escAttr(r.program_code)}')" title="Delete">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>
        </td>
    </tr>`).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function prg_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = PRG_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $prg('prg-from').textContent  = from;
    $prg('prg-to').textContent    = to;
    $prg('prg-total').textContent = total;

    const container = $prg('prg-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="prg_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="prg_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="prg_goTo(${current_page + 1})">›</button>`;
}

function prg_goTo(page) { prg_page = page; prg_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function prg_openModal(mode, row = null) {
    prg_hideErr();
    $prg('prg-mode').value = mode;
    $prg('prg-modal-title').textContent = mode === 'edit' ? 'Edit Program' : 'Add Program';
    $prg('prg-program-code').value = row?.program_code || '';
    $prg('prg-program-code').disabled = mode === 'edit';
    $prg('prg-code-hint').textContent = mode === 'edit'
        ? 'Program codes cannot be changed after creation.'
        : 'Preview — year-RC code-sequence. The final code is assigned when you save.';
    $prg('prg-program-name').value = row?.program_name || '';
    $prg('prg-save').disabled = false;
    if (mode === 'add') prg_loadCodePreview();     // defined in form/program-form.php
    $prg('prg-overlay').classList.add('open');
}

function prg_closeModal() {
    $prg('prg-overlay').classList.remove('open');
    $prg('prg-form').reset();
    prg_hideErr();
}

function prg_showErr(msg) {
    const el = $prg('prg-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function prg_hideErr() {
    $prg('prg-err').style.display = 'none';
}

async function prg_edit(code) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${PRG_API}/read/${encodeURIComponent(code)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load program.');
        prg_openModal('edit', json.data);
    } catch (e) {
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function prg_save(ev) {
    ev.preventDefault();
    prg_hideErr();
    const mode = $prg('prg-mode').value;
    const code = $prg('prg-program-code').value.trim();
    const payload = {
        program_code : code,
        program_name : $prg('prg-program-name').value.trim(),
    };

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${PRG_API}/update/${encodeURIComponent(code)}` : `${PRG_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: prg_authHeaders({ 'Content-Type': 'application/json' }),   // JWT → server knows whose RC code to use
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save program.');

        // On add, the server's message names the code it actually assigned ("Program 2026-…-102 created successfully.").
        prg_toast(mode === 'edit' ? 'Program updated successfully.' : (json.message || 'Program created successfully.'), 'success');
        prg_closeModal();
        prg_load();
    } catch (e) {
        prg_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function prg_delete(code) {
    if (!confirm(`Delete program '${code}'? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${PRG_API}/delete/${encodeURIComponent(code)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete program.');

        prg_toast('Program deleted successfully.', 'success');
        prg_load();
    } catch (e) {
        prg_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function prg_esc(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function prg_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}

function prg_toast(msg, type = 'info') {
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
