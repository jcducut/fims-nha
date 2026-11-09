<?php
namespace PHPMaker2024\FIMS;
@session_start();
$_proj_api = Config('API.FIMS_URL');
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<div id="proj-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-title">Projects</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <button id="proj-refresh" type="button" class="nha-btn-hdr" title="Refresh">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                Refresh
            </button>
            <button id="proj-add" type="button" class="nha-btn-hdr" style="background:var(--afims-success, #11A752);" title="Add a project">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                Add Project
            </button>
        </div>
    </div>

    <!-- ── KPI CARDS ─────────────────────────────────────── -->
    <div class="nha-summary">
        <div class="nha-sc nha-sc-navy">
            <div class="nha-sc-top">
                <div>
                    <div class="nha-sc-label">Total Projects</div>
                    <div class="nha-sc-val" id="proj-kpi-total">—</div>
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
                <select id="proj-filter-program" class="nha-filter-select">
                    <option value="">All Programs</option>
                </select>
            </div>
            <div class="nha-filter-group nha-filter-group-search">
                <label class="nha-filter-label">Search</label>
                <div class="nha-filter-search-wrap">
                    <svg class="nha-filter-search-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="text" id="proj-filter-search" class="nha-filter-input" placeholder="Search project code, name…">
                    <button class="nha-filter-clear-btn" id="proj-search-clear" hidden type="button">×</button>
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
                        <th>Project Code</th>
                        <th>Project Name</th>
                        <th>Program</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="proj-tbody">
                    <tr><td colspan="5"><div class="nha-loader"><div class="nha-spinner-sm"></div> Loading…</div></td></tr>
                </tbody>
            </table>
        </div>
        <div class="nha-pag-wrap">
            <span class="nha-pag-info">Showing <strong><span id="proj-from">0</span>–<span id="proj-to">0</span></strong> of <strong><span id="proj-total">0</span></strong></span>
            <div id="proj-pagination" class="nha-pag-btns"></div>
        </div>
    </div>

<?php include __DIR__ . '/form/project-form.php'; ?>

</div><!-- /#proj-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PROJECTS LIST   prefix: proj_
═══════════════════════════════════════════════════════════════ */

const PROJ_API             = '<?php echo $_proj_api; ?>' + '/project';
const PROJ_PROGRAM_API     = '<?php echo $_proj_api; ?>' + '/program/all';
const PROJ_SUB_PROGRAM_API = '<?php echo $_proj_api; ?>' + '/sub_program/all';

let proj_page = 1;
const PROJ_PAGE_SIZE = 15;
let PROJ_SUB_PROGRAMS = [];

const $proj = id => document.getElementById(id);

async function proj_bootstrapLoad() {
    await Promise.all([proj_load(), proj_loadPrograms(), proj_loadSubPrograms()]);
    proj_bindEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', proj_bootstrapLoad);
} else {
    proj_bootstrapLoad();
}

async function proj_loadPrograms() {
    try {
        const res  = await fetch(PROJ_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load programs.');

        const rows = json.data ?? [];
        const options = rows.map(p =>
            `<option value="${proj_escAttr(p.program_code)}">${proj_esc(p.program_code)} — ${proj_esc(p.program_name)}</option>`
        ).join('');

        $proj('proj-filter-program').insertAdjacentHTML('beforeend', options);
        $proj('proj-program-code').insertAdjacentHTML('beforeend', options);
    } catch (e) {
        proj_toast(e.message, 'error');
    }
}

async function proj_loadSubPrograms() {
    try {
        const res  = await fetch(PROJ_SUB_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load sub-programs.');
        PROJ_SUB_PROGRAMS = json.data ?? [];
    } catch (e) {
        proj_toast(e.message, 'error');
    }
}

// Program -> Sub-Program cascade, same pattern as pof_fillProjects()/
// pof_onProgramChange() in app/pages/po/form.php.
function proj_fillSubPrograms(programCode, keep = '') {
    const list = programCode ? PROJ_SUB_PROGRAMS.filter(sp => sp.program_code === programCode) : [];
    $proj('proj-sub-program-code').innerHTML = '<option value="">— None —</option>' + list.map(sp =>
        `<option value="${proj_escAttr(sp.sub_program_code)}">${proj_esc(sp.sub_program_code)} — ${proj_esc(sp.sub_program_name)}</option>`
    ).join('');
    $proj('proj-sub-program-code').value = list.some(sp => sp.sub_program_code === keep) ? keep : '';
}
function proj_onProgramChange() {
    proj_fillSubPrograms($proj('proj-program-code').value);
}

function proj_bindEvents() {
    $proj('proj-refresh')?.addEventListener('click', () => { proj_page = 1; proj_load(); });
    $proj('proj-add')?.addEventListener('click', () => proj_openModal('add'));
    $proj('proj-modal-close')?.addEventListener('click', proj_closeModal);
    $proj('proj-cancel')?.addEventListener('click', proj_closeModal);
    $proj('proj-form')?.addEventListener('submit', proj_save);
    $proj('proj-program-code')?.addEventListener('change', proj_onProgramChange);

    $proj('proj-filter-program')?.addEventListener('change', () => { proj_page = 1; proj_load(); });

    let searchTimer;
    const searchEl = $proj('proj-filter-search');
    const clearBtn = $proj('proj-search-clear');
    searchEl?.addEventListener('input', () => {
        if (clearBtn) clearBtn.hidden = !searchEl.value;
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => { proj_page = 1; proj_load(); }, 300);
    });
    clearBtn?.addEventListener('click', () => {
        if (searchEl) { searchEl.value = ''; searchEl.focus(); }
        clearBtn.hidden = true;
        proj_page = 1; proj_load();
    });
}

/* ── Load ──────────────────────────────────────────────────── */
async function proj_load() {
    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({
            search       : $proj('proj-filter-search')?.value  || '',
            program_code : $proj('proj-filter-program')?.value || '',
            page         : proj_page,
            limit        : PROJ_PAGE_SIZE,
        });
        const res  = await fetch(`${PROJ_API}/list?${params}`, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load.');

        proj_renderTable(json.data ?? []);
        proj_renderPagination(json.pagination ?? {});
        $proj('proj-kpi-total').textContent = json.pagination?.total ?? (json.data ?? []).length;
    } catch (e) {
        proj_renderTable([]);
        proj_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Table ─────────────────────────────────────────────────── */
function proj_renderTable(rows) {
    const tbody = $proj('proj-tbody');
    if (!rows.length) {
        tbody.innerHTML = '<tr><td colspan="5"><div class="nha-empty nha-empty-sm"><span class="nha-empty-title">No projects found.</span></div></td></tr>';
        return;
    }
    const offset = (proj_page - 1) * PROJ_PAGE_SIZE;
    tbody.innerHTML = rows.map((r, i) => `<tr>
        <td class="nha-td-seq">${offset + i + 1}</td>
        <td><code class="nha-code">${proj_esc(r.project_code)}</code></td>
        <td><div style="font-weight:600;">${proj_esc(r.project_name)}</div></td>
        <td>
            <div style="font-weight:500;">${proj_esc(r.program_code)}</div>
            ${r.program_name ? `<div class="nha-td-sub" style="margin-top:2px;">${proj_esc(r.program_name)}</div>` : ''}
        </td>
        <td>
            <button class="nha-btn-act edit" onclick="proj_edit('${proj_escAttr(r.project_code)}')" title="Edit">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </button>
            <button class="nha-btn-act danger" onclick="proj_delete('${proj_escAttr(r.project_code)}')" title="Delete">
                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                Delete
            </button>
        </td>
    </tr>`).join('');
}

/* ── Pagination ────────────────────────────────────────────── */
function proj_renderPagination(p) {
    const { current_page = 1, last_page = 1, total = 0, per_page = PROJ_PAGE_SIZE } = p;
    const from = total > 0 ? (current_page - 1) * per_page + 1 : 0;
    const to   = total > 0 ? Math.min(current_page * per_page, total) : 0;
    $proj('proj-from').textContent  = from;
    $proj('proj-to').textContent    = to;
    $proj('proj-total').textContent = total;

    const container = $proj('proj-pagination');
    if (last_page <= 1) { container.innerHTML = ''; return; }
    const pages = [];
    for (let i = Math.max(1, current_page - 2); i <= Math.min(last_page, current_page + 2); i++) {
        pages.push(`<button class="nha-pag-btn${i === current_page ? ' active' : ''}" onclick="proj_goTo(${i})">${i}</button>`);
    }
    container.innerHTML =
        `<button class="nha-pag-btn${current_page === 1 ? ' disabled' : ''}" onclick="proj_goTo(${current_page - 1})">‹</button>` +
        pages.join('') +
        `<button class="nha-pag-btn${current_page === last_page ? ' disabled' : ''}" onclick="proj_goTo(${current_page + 1})">›</button>`;
}

function proj_goTo(page) { proj_page = page; proj_load(); }

/* ── Modal ─────────────────────────────────────────────────── */
function proj_openModal(mode, row = null) {
    proj_hideErr();
    $proj('proj-mode').value = mode;
    $proj('proj-modal-title').textContent = mode === 'edit' ? 'Edit Project' : 'Add Project';
    $proj('proj-project-code').value = row?.project_code || '';
    $proj('proj-project-code').disabled = mode === 'edit';
    $proj('proj-project-name').value = row?.project_name || '';
    $proj('proj-program-code').value = row?.program_code || '';
    proj_fillSubPrograms(row?.program_code, row?.sub_program_code);
    $proj('proj-overlay').classList.add('open');
}

function proj_closeModal() {
    $proj('proj-overlay').classList.remove('open');
    $proj('proj-form').reset();
    proj_hideErr();
}

function proj_showErr(msg) {
    const el = $proj('proj-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function proj_hideErr() {
    $proj('proj-err').style.display = 'none';
}

async function proj_edit(code) {
    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${PROJ_API}/read/${encodeURIComponent(code)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load project.');
        proj_openModal('edit', json.data);
    } catch (e) {
        proj_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

async function proj_save(ev) {
    ev.preventDefault();
    proj_hideErr();
    const mode = $proj('proj-mode').value;
    const code = $proj('proj-project-code').value.trim();
    const payload = {
        project_code     : code,
        project_name     : $proj('proj-project-name').value.trim(),
        program_code     : $proj('proj-program-code').value,
        sub_program_code : $proj('proj-sub-program-code').value,
    };

    window.PagePreloader?.show();
    try {
        const url    = mode === 'edit' ? `${PROJ_API}/update/${encodeURIComponent(code)}` : `${PROJ_API}/add`;
        const method = mode === 'edit' ? 'PUT' : 'POST';
        const res    = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save project.');

        proj_toast(`Project ${mode === 'edit' ? 'updated' : 'created'} successfully.`, 'success');
        proj_closeModal();
        proj_load();
    } catch (e) {
        proj_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

async function proj_delete(code) {
    if (!confirm(`Delete project '${code}'? This cannot be undone.`)) return;

    window.PagePreloader?.show();
    try {
        const res  = await fetch(`${PROJ_API}/delete/${encodeURIComponent(code)}`, {
            method: 'DELETE',
            credentials: 'same-origin',
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to delete project.');

        proj_toast('Project deleted successfully.', 'success');
        proj_load();
    } catch (e) {
        proj_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers ───────────────────────────────────────────────── */
function proj_esc(text) {
    if (!text) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function proj_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}

function proj_toast(msg, type = 'info') {
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
