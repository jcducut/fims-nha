<?php
/**
 * budget-req-form.php
 *
 * Add-a-Program / Edit-a-Program modal, opened from two places:
 *   - app/pages/budget-req-page.php's "+ Add Budget Request" button ('add' mode)
 *   - a Program row's "Edit" action, or the Edit button inside the Program
 *     View modal (readone/budget-req-readone.php) ('edit-program' mode)
 *
 * fims.budget_req is a four-level structure, one request per fiscal year:
 *   fims.budget_req          — header: Fiscal Year, Status (Draft -> For Review)
 *   fims.budget_req_program  — one "Program block" per program added to the
 *                              request (repeatable across saves — each save
 *                              of THIS form adds/edits exactly one of these)
 *   fims.budget_req_project  — one "Project line item" per project added to
 *                              a Program block (repeatable "+ Add Project"
 *                              blocks within this form)
 *   fims.budget_line_items   — one "Chart of Accounts line item" per account
 *                              allocation under a project block (repeatable
 *                              "+ Add Line Item" rows nested inside each block)
 *
 * Trigger:
 *   bgr_openModal('add')                    — add a Program to the request for
 *                                              the chosen fiscal year (creates
 *                                              that request if none exists yet,
 *                                              or appends to it if it's still Draft)
 *   bgr_openModal('edit-program', program)  — edit one existing Program's own
 *                                              projects/line items; `program`
 *                                              must include budget_req_program_id,
 *                                              fiscal_yr, program_code,
 *                                              sub_program_code, projects[]
 *
 * After a successful save this calls window.bgr_afterSave?.(mode, json) —
 * each host page defines that hook to its own refresh logic, keeping this
 * partial decoupled from page-specific behavior.
 *
 * Self-contained styling (scoped under #bgr-card) AND self-contained JS
 * (this file loads its own reference data and binds its own modal events)
 * so it renders and works correctly regardless of which host page includes
 * it or whether that page's own script has run yet.
 */
$_bgr_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<!-- ══════════════════════════════════════════════════════════
     BUDGET REQUEST FORM MODAL   JS namespace: bgr_
══════════════════════════════════════════════════════════ -->
<style>
    #bgr-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #bgr-overlay.open { display:flex; }
    #bgr-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:1040px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:bgrIn .2s ease; }
    @keyframes bgrIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #bgr-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #bgr-header-info { display:flex;align-items:center;gap:10px; }
    #bgr-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .bgr-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .bgr-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #bgr-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #bgr-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #bgr-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    /* #bgr-body/#bgr-footer live inside this <form>, not directly inside
       #bgr-card — a plain <form> isn't a flex container by default, so
       without this, their flex:1/flex-shrink:0 rules below do nothing: the
       form just grows to fit all its content and gets silently clipped by
       #bgr-card's overflow:hidden, with no scrollbar ever appearing. This
       makes the form itself the flex item (filling #bgr-card's remaining
       space) and a flex container in turn for its own children. */
    #bgr-form { flex:1;min-height:0;display:flex;flex-direction:column;overflow:hidden; }

    #bgr-body { flex:1;min-height:0;overflow-y:auto;padding:22px; }
    .bgr-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    .bgr-group { display:flex;flex-direction:column;gap:5px; }
    .bgr-group.full { margin-bottom:16px; }
    .bgr-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .bgr-label .req { color:var(--afims-danger, #DC3545); }
    .bgr-input, .bgr-select, .bgr-textarea { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .bgr-textarea { resize:vertical;min-height:64px; }
    .bgr-input:focus, .bgr-select:focus, .bgr-textarea:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .bgr-select:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .bgr-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 16px; }

    .bgr-amt-wrap { display:flex;align-items:stretch;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgr-amt-wrap:focus-within { border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .bgr-amt-prefix { display:flex;align-items:center;padding:0 12px;background:var(--afims-surface-subtle, #F4F7FA);
        color:var(--afims-text-disabled, #A9AAB8);font-weight:700;font-size:13px;border-right:1px solid var(--afims-border, #E4ECF2); }
    .bgr-amt-wrap .bgr-input { border:none;border-radius:0;text-align:right;font-family:'IBM Plex Mono',monospace; }
    .bgr-amt-wrap .bgr-input:focus { box-shadow:none; }

    .bgr-sec-hd { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em;margin:4px 0 10px; }

    /* ── Project blocks ───────────────────────────────────────────────── */
    #bgr-blocks-wrap { display:flex;flex-direction:column;gap:16px;margin-bottom:12px; }
    .bgr-block { border:2px solid color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, transparent);
        border-radius:10px;padding:14px;box-shadow:0 1px 4px rgba(0,0,0,.06); }
    .bgr-block-hd { display:flex;align-items:flex-start;gap:10px; }
    .bgr-block-project-wrap { flex:1;min-width:0; }
    .bgr-block-toggle { flex-shrink:0;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-heading-fill, #000E3E);
        cursor:pointer;width:42px;height:42px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        margin-top:20px;transition:transform .15s,background .15s; }
    .bgr-block-toggle:hover { background:var(--afims-border, #E4ECF2); }
    .bgr-block.bgr-collapsed .bgr-block-toggle { transform:rotate(-90deg); }
    .bgr-block-body { display:flex;flex-direction:column;gap:12px;margin-top:12px; }
    .bgr-block.bgr-collapsed .bgr-block-body { display:none; }
    .bgr-block-remove { flex-shrink:0;border:1px solid var(--afims-danger-soft, #FAE1E3);
        background:var(--afims-danger-soft, #FAE1E3);color:var(--afims-danger, #DC3545);
        font-size:24px;line-height:1;cursor:pointer;width:42px;height:42px;border-radius:8px;margin-top:20px;
        transition:background .15s,color .15s; }
    .bgr-block-remove:hover { background:var(--afims-danger, #DC3545);color:var(--afims-pure-white, #FFFFFF); }

    /* ── Line items table (nested per block) ──────────────────────────── */
    /* .bgr-block-li-outer holds two siblings: the scrollable table
       (.bgr-block-li-wrap) and a fixed remove-button column
       (.bgr-block-li-remove-col) that is NOT part of the scroll area, so
       it's always visible without scrolling right. Row heights are kept
       in sync via matching explicit heights below — AND, critically,
       .bgr-block-li-remove-col gets a bottom padding set by JS
       (bgr_syncRemoveColGap) equal to whatever horizontal scrollbar height
       .bgr-block-li-wrap is actually showing. That scrollbar reserves
       space at the bottom of .bgr-block-li-wrap that
       .bgr-block-li-remove-col has no equivalent for, and that was the
       real cause of the two columns drifting out of alignment last time —
       not the row heights themselves. */
    .bgr-block-li-outer { display:flex;align-items:stretch;border:1px solid var(--afims-border, #E4ECF2);
        border-radius:10px;overflow:hidden; }
    .bgr-block-li-wrap { overflow-x:auto;flex:1;min-width:0; }
    .bgr-block-li-tbl { width:100%;border-collapse:collapse;min-width:860px;table-layout:fixed; }
    .bgr-block-li-tbl th { height:34px;box-sizing:border-box;font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:0 6px;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-block-li-tbl td { height:44px;box-sizing:border-box;padding:6px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:middle; }
    .bgr-li-input, .bgr-li-select { width:100%;padding:7px 9px;border:1px solid var(--afims-border, #E4ECF2);
        border-radius:6px;font-size:12px;font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);box-sizing:border-box; }
    .bgr-li-input:focus, .bgr-li-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E); }
    .bgr-li-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);
        text-overflow:ellipsis; }
    .bgr-li-amt { text-align:right;font-family:'IBM Plex Mono',monospace; }

    .bgr-block-li-remove-col { flex-shrink:0;width:42px;display:flex;flex-direction:column;
        border-left:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF); }
    .bgr-li-remove-head { height:34px;box-sizing:border-box;background:var(--afims-surface-subtle, #F4F7FA);
        border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove-cell { height:44px;box-sizing:border-box;display:flex;align-items:center;justify-content:center;
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgr-li-remove { border:none;background:transparent;color:var(--afims-danger, #DC3545);
        font-size:18px;line-height:1;cursor:pointer;width:26px;height:26px;border-radius:6px; }
    .bgr-li-remove:hover { background:var(--afims-danger-soft, #FAE1E3); }
    .bgr-block-add-li { margin-top:12px;
        border:2px solid color-mix(in srgb, var(--afims-heading-fill, #000E3E) 35%, transparent) !important; }

    #bgr-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #bgr-footer .bgr-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #bgr-footer .bgr-btn:disabled { opacity:.5;cursor:not-allowed; }
    #bgr-footer .bgr-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #bgr-footer .bgr-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #bgr-footer .bgr-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #bgr-footer .bgr-btn-navy:hover:not(:disabled) { opacity:.92; }

    .bgr-btn-ghost { height:34px;padding:0 16px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);font-size:12px;font-weight:600;
        font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    .bgr-btn-ghost:hover { background:var(--afims-surface-subtle, #F4F7FA); }

    /* ── Project typeahead (twitter typeahead.js) ─────────────────────── */
    /* The library wraps the input in a plain inline-block span by default —
       force it to behave like every other full-width field in this form. */
    .bgr-block-project-wrap .twitter-typeahead { display:block !important;width:100%; }
    .tt-menu { width:100%;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);
        border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14);margin-top:4px;overflow:hidden;z-index:10000; }
    .tt-suggestion { padding:8px 12px;font-size:13px;color:var(--afims-text, #1A1B21);cursor:pointer; }
    .tt-suggestion:hover, .tt-suggestion.tt-cursor { background:var(--afims-surface-subtle, #F4F7FA); }
    .tt-not-found { padding:8px 12px;font-size:12px;color:var(--afims-text-disabled, #A9AAB8);font-style:italic; }

    @media (max-width: 520px) { .bgr-row { grid-template-columns:1fr; } }
</style>

<div id="bgr-overlay">
    <div id="bgr-card">

        <div id="bgr-header">
            <div id="bgr-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h3M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
                <div>
                    <p class="bgr-hdr-tag">Budget Requirement</p>
                    <p class="bgr-hdr-title" id="bgr-modal-title">Add Budget Request</p>
                </div>
            </div>
            <button type="button" id="bgr-modal-close">&times;</button>
        </div>

        <div id="bgr-err"></div>

        <form id="bgr-form">
            <div id="bgr-body">
                <input type="hidden" id="bgr-mode" value="add">
                <input type="hidden" id="bgr-id" value="">

                <div class="bgr-row">
                    <div class="bgr-group">
                        <label class="bgr-label">Fiscal Year <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-fiscal-yr" required>
                            <option value="">— Select fiscal year —</option>
                        </select>
                    </div>
                    <div class="bgr-group">
                        <label class="bgr-label">Program <span class="req">*</span></label>
                        <select class="bgr-select" id="bgr-program-code" required>
                            <option value="">— Select program —</option>
                        </select>
                    </div>
                </div>
                <div class="bgr-row">
                    <div class="bgr-group">
                        <label class="bgr-label">Sub-Program</label>
                        <select class="bgr-select" id="bgr-sub-program-code">
                            <option value="">— None —</option>
                        </select>
                    </div>
                </div>
                <p class="bgr-hint" id="bgr-program-context" style="display:none;"></p>

                <p class="bgr-sec-hd">Projects</p>
                <div id="bgr-blocks-wrap"></div>
                <button type="button" id="bgr-add-project-block" class="bgr-btn-ghost">+ Add Project</button>
            </div>
            <div id="bgr-footer">
                <button type="button" class="bgr-btn bgr-btn-ghost" id="bgr-cancel">Cancel</button>
                <button type="submit" class="bgr-btn bgr-btn-navy" id="bgr-save">Save Request</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   BUDGET REQUEST FORM MODAL   prefix: bgr_
   Self-contained: loads its own reference data and binds its own
   events, so it works regardless of which page includes it.
═══════════════════════════════════════════════════════════════ */

const BGR_API                   = '<?php echo $_bgr_api; ?>' + '/budget_req';
const BGR_PROGRAM_API           = '<?php echo $_bgr_api; ?>' + '/program/all';
const BGR_PROJECT_BASE_API      = '<?php echo $_bgr_api; ?>' + '/project';
const BGR_FISCAL_YEAR_API       = '<?php echo $_bgr_api; ?>' + '/fiscal_year';
const BGR_SUB_PROGRAM_API       = '<?php echo $_bgr_api; ?>' + '/sub_program/all';
const BGR_CHART_OF_ACCOUNTS_API = '<?php echo $_bgr_api; ?>' + '/chart_of_accounts/all';

// Cached option lists — Program/Sub-Program are prefetched in full and
// filtered client-side (same cascade style as pof_fillProjects() in
// app/pages/po/form.php); Chart-of-Accounts options are prefetched once per
// line-item row; Project is searched live via typeahead instead (see
// bgr_initProjectTypeahead), since the list can be large and is also creatable.
let BGR_PROGRAMS = [];
let BGR_SUB_PROGRAMS = [];
let BGR_COA_OPTIONS = [];
let bgr_liSeq = 0;
let bgr_blockSeq = 0;

const $bgr = id => document.getElementById(id);

async function bgr_formBootstrap() {
    await Promise.all([bgr_loadPrograms(), bgr_loadSubPrograms(), bgr_loadFiscalYears(), bgr_loadChartOfAccounts()]);
    bgr_bindFormEvents();
}
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bgr_formBootstrap);
} else {
    bgr_formBootstrap();
}

function bgr_bindFormEvents() {
    $bgr('bgr-modal-close')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-cancel')?.addEventListener('click', bgr_closeModal);
    $bgr('bgr-form')?.addEventListener('submit', bgr_save);

    $bgr('bgr-program-code')?.addEventListener('change', bgr_onProgramChange);
    $bgr('bgr-add-project-block')?.addEventListener('click', () => bgr_addProjectBlock());

    // A window resize can change whether a block's line-items table needs a
    // horizontal scrollbar at all, which changes how much gap its remove
    // column needs.
    window.addEventListener('resize', () => {
        if ($bgr('bgr-overlay')?.classList.contains('open')) {
            document.querySelectorAll('#bgr-blocks-wrap .bgr-block').forEach(bgr_syncRemoveColGap);
        }
    });
}

async function bgr_loadPrograms() {
    try {
        const res  = await fetch(BGR_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load programs.');
        BGR_PROGRAMS = json.data ?? [];

        const optsHtml = BGR_PROGRAMS.map(p =>
            `<option value="${bgr_escHtmlAttr(p.program_code)}">${bgr_esc(p.program_code)} — ${bgr_esc(p.program_name)}</option>`
        ).join('');
        $bgr('bgr-program-code').insertAdjacentHTML('beforeend', optsHtml);
        $bgr('bgr-filter-program')?.insertAdjacentHTML('beforeend', optsHtml);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

async function bgr_loadSubPrograms() {
    try {
        const res  = await fetch(BGR_SUB_PROGRAM_API, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load sub-programs.');
        BGR_SUB_PROGRAMS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// Program -> Sub-Program cascade, same pattern as pof_fillProjects()/
// pof_onProgramChange() in app/pages/po/form.php: the full list is already
// cached, so this just filters it client-side into the dependent select.
function bgr_fillSubPrograms(programCode, keep = '') {
    const list = programCode ? BGR_SUB_PROGRAMS.filter(sp => sp.program_code === programCode) : [];
    $bgr('bgr-sub-program-code').innerHTML = '<option value="">— None —</option>' + list.map(sp =>
        `<option value="${bgr_escHtmlAttr(sp.sub_program_code)}">${bgr_esc(sp.sub_program_code)} — ${bgr_esc(sp.sub_program_name)}</option>`
    ).join('');
    $bgr('bgr-sub-program-code').value = list.some(sp => sp.sub_program_code === keep) ? keep : '';
}
function bgr_onProgramChange() {
    bgr_fillSubPrograms($bgr('bgr-program-code').value);
}

async function bgr_loadFiscalYears() {
    try {
        const res  = await fetch(`${BGR_FISCAL_YEAR_API}/all`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load fiscal years.');

        const optsHtml = (json.data ?? []).map(fy =>
            `<option value="${bgr_escAttr(fy.fiscal_yr)}">${bgr_esc(fy.fiscal_yr)}</option>`
        ).join('');
        $bgr('bgr-fiscal-yr').insertAdjacentHTML('beforeend', optsHtml);
        $bgr('bgr-filter-fiscal-yr')?.insertAdjacentHTML('beforeend', optsHtml);
    } catch (e) {
        bgr_toast(e.message, 'error');
    }
}

// All active, postable chart-of-accounts entries — no more expense-class-driven
// prefix filtering; every account is selectable regardless of PS/MOOE/CO branch.
async function bgr_loadChartOfAccounts() {
    try {
        const res  = await fetch(`${BGR_CHART_OF_ACCOUNTS_API}?postable_only=1`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load chart of accounts.');
        BGR_COA_OPTIONS = json.data ?? [];
    } catch (e) {
        bgr_toast(e.message, 'error');
        BGR_COA_OPTIONS = [];
    }
}

function bgr_coaOptionsHtml(selected) {
    const sel = selected != null ? String(selected) : '';
    const opts = ['<option value="">— Select —</option>'];
    BGR_COA_OPTIONS.forEach(a => {
        opts.push(`<option value="${bgr_escHtmlAttr(a.account_id)}" ${String(a.account_id) === sel ? 'selected' : ''}>${bgr_esc(a.account_code)} — ${bgr_esc(a.account_title)}</option>`);
    });
    return opts.join('');
}

/* ── Project blocks — one per "Project line item" (fims.budget_req_project),
   each holding its own repeatable Chart-of-Accounts line items table ──── */
function bgr_addProjectBlock(prefillBlock = null) {
    bgr_blockSeq++;
    const block = document.createElement('div');
    block.className = 'bgr-block';
    block.dataset.blockSeq = bgr_blockSeq;
    block.innerHTML = `
        <div class="bgr-block-hd">
            <button type="button" class="bgr-block-toggle" title="Collapse">
                <svg width="24" height="24" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>
            <div class="bgr-block-project-wrap">
                <label class="bgr-label">Project <span class="req">*</span></label>
                <input type="text" class="bgr-input bgr-block-project-name" placeholder="Type to search or add a new project…" autocomplete="off">
                <input type="hidden" class="bgr-block-project-code" value="">
            </div>
            <button type="button" class="bgr-block-remove" title="Remove project">&times;</button>
        </div>
        <div class="bgr-block-body">
            <div class="bgr-block-li-outer">
                <div class="bgr-block-li-wrap">
                    <table class="bgr-block-li-tbl">
                        <thead><tr>
                            <th style="width:150px;">Account Code</th>
                            <th style="width:300px;">Account Name</th>
                            <th>Particulars</th>
                            <th style="width:170px;">Proposed Amount</th>
                        </tr></thead>
                        <tbody class="bgr-block-li-tbody"></tbody>
                    </table>
                </div>
                <div class="bgr-block-li-remove-col">
                    <div class="bgr-li-remove-head"></div>
                </div>
            </div>
            <button type="button" class="bgr-block-add-li bgr-btn-ghost">+ Add Line Item</button>
        </div>
    `;
    $bgr('bgr-blocks-wrap').appendChild(block);

    const $nameInput = $(block.querySelector('.bgr-block-project-name'));
    bgr_initProjectTypeahead($nameInput);
    if (prefillBlock && prefillBlock.project_code) {
        const label = `${prefillBlock.project_code} — ${prefillBlock.project_name || ''}`;
        $nameInput.val(label).data('bgr-matched-text', label);
        bgr_setProjectCode($nameInput, prefillBlock.project_code);
    }

    block.querySelector('.bgr-block-remove').addEventListener('click', () => bgr_removeProjectBlock(block));
    block.querySelector('.bgr-block-add-li').addEventListener('click', () => bgr_addLineItemRow(block, {}));
    block.querySelector('.bgr-block-toggle').addEventListener('click', () => bgr_toggleProjectBlock(block));

    const items = (prefillBlock && prefillBlock.line_items && prefillBlock.line_items.length) ? prefillBlock.line_items : [{}];
    items.forEach(li => bgr_addLineItemRow(block, li));

    return block;
}

function bgr_removeProjectBlock(block) {
    if ($bgr('bgr-blocks-wrap').children.length <= 1) { bgr_toast('At least one project is required.', 'error'); return; }
    block.remove();
}

// Collapses a block down to just its header (project name field), hiding the
// chart-of-accounts table — for minimizing projects you're not actively
// editing when a request has several.
function bgr_toggleProjectBlock(block) {
    const collapsed = block.classList.toggle('bgr-collapsed');
    block.querySelector('.bgr-block-toggle').title = collapsed ? 'Expand' : 'Collapse';
}

// Type-directly-to-search project picker (twitter typeahead.js, already
// bundled as jquery/typeahead.jquery.min.js — no extra library needed). The
// visible input IS the search box from the first keystroke, no separate
// dropdown-open step; matches are searched live (filtered by the header's
// Program/Sub-Program). Picking a suggestion fills the paired hidden
// .bgr-block-project-code field with its real project_code. Typing a name
// with no match leaves that hidden field empty — bgr_save() then sends the
// typed text as project_name so the backend resolves/creates it atomically
// when the whole request is saved (never the moment the user types).
function bgr_setProjectCode($input, code) {
    $input.closest('.bgr-block-project-wrap').find('.bgr-block-project-code').val(code);
}

function bgr_initProjectTypeahead($input) {
    $input.typeahead(
        { hint: false, highlight: true, minLength: 1 },
        {
            name: 'bgr-projects',
            display: 'label',
            limit: 8,
            source: (query, syncResults, asyncResults) => {
                clearTimeout($input.data('bgr-ta-timer'));
                $input.data('bgr-ta-timer', setTimeout(async () => {
                    try {
                        const params = new URLSearchParams({
                            search: query,
                            program_code: $bgr('bgr-program-code').value,
                            sub_program_code: $bgr('bgr-sub-program-code').value,
                        });
                        const res  = await fetch(`${BGR_PROJECT_BASE_API}/all?${params}`, { credentials: 'same-origin' });
                        const json = await res.json();
                        asyncResults((json.data ?? []).map(p => ({
                            project_code: p.project_code,
                            label: `${p.project_code} — ${p.project_name}`,
                        })));
                    } catch (e) {
                        asyncResults([]);
                    }
                }, 250));
            },
            templates: {
                suggestion: item => `<div>${bgr_esc(item.label)}</div>`,
                notFound: '<div class="tt-not-found">No match — this will be added as a new project.</div>',
            },
        }
    );

    // Selecting a suggestion resolves the real project_code. Any later edit
    // that moves the text away from that exact match invalidates it, falling
    // back to "typed name, not yet a real project" — guarded by bgr-syncing
    // so the library's own value-set on selection doesn't immediately
    // re-trigger this and clear the code it just set.
    $input.on('typeahead:select', (ev, item) => {
        $input.data('bgr-syncing', true);
        $input.data('bgr-matched-text', item.label);
        bgr_setProjectCode($input, item.project_code);
        setTimeout(() => $input.removeData('bgr-syncing'), 0);
    });
    $input.on('input', () => {
        if ($input.data('bgr-syncing')) return;
        if ($input.val() !== $input.data('bgr-matched-text')) {
            bgr_setProjectCode($input, '');
        }
    });
}

// The suggestion menu (including the "No match" message, which the library
// treats as non-empty content and keeps visible) doesn't reliably close
// itself on blur alone — a debounced async result can still land and
// re-render it just after focus moved elsewhere. Rather than guess at that
// timing, force-close every project field's menu the moment focus genuinely
// lands somewhere outside it — a different field in the same block, another
// block entirely, or outside the modal. Registered once, globally (not
// per-block), since it has to see every focus change in the document.
document.addEventListener('focusin', (ev) => {
    document.querySelectorAll('#bgr-blocks-wrap .bgr-block-project-name').forEach((el) => {
        if (!el.closest('.bgr-block-project-wrap').contains(ev.target)) {
            $(el).typeahead('close');
        }
    });
});

/* ── Line items — repeatable rows nested inside a project block ─────── */
function bgr_addLineItemRow(blockEl, prefill = {}) {
    bgr_liSeq++;
    const seq = bgr_liSeq;
    const tr = document.createElement('tr');
    tr.dataset.seq = seq;
    tr.innerHTML = `
        <td><select class="bgr-li-select bgr-li-account" onchange="bgr_liAccountChange(this)">${bgr_coaOptionsHtml(prefill.account_id)}</select></td>
        <td><input type="text" class="bgr-li-input bgr-li-account-name" value="${bgr_escHtmlAttr(prefill.account_title)}" title="${bgr_escHtmlAttr(prefill.account_title)}" disabled></td>
        <td><input type="text" class="bgr-li-input bgr-li-particulars" value="${bgr_escHtmlAttr(prefill.particulars)}" placeholder="Particulars" maxlength="2000"></td>
        <td><input type="text" inputmode="decimal" class="bgr-li-input bgr-li-amt" value="${bgr_fmtAmountInitial(prefill.proposed_amount ?? prefill.amount)}" placeholder="0.00" oninput="bgr_fmtAmount(this)"></td>
    `;
    blockEl.querySelector('.bgr-block-li-tbody').appendChild(tr);

    // Remove button lives in the separate .bgr-block-li-remove-col sibling,
    // kept in row-sync with the table via matching dataset.seq and matching
    // CSS heights (same split-column pattern the old single-table layout used).
    const removeCell = document.createElement('div');
    removeCell.className = 'bgr-li-remove-cell';
    removeCell.dataset.seq = seq;
    removeCell.innerHTML = '<button type="button" class="bgr-li-remove" title="Remove">&times;</button>';
    removeCell.querySelector('.bgr-li-remove').addEventListener('click', () => bgr_removeLineItemRow(blockEl, seq));
    blockEl.querySelector('.bgr-block-li-remove-col').appendChild(removeCell);

    bgr_syncRemoveColGap(blockEl);
}

function bgr_removeLineItemRow(blockEl, seq) {
    const tbody = blockEl.querySelector('.bgr-block-li-tbody');
    if (tbody.rows.length <= 1) { bgr_toast('At least one line item is required.', 'error'); return; }
    blockEl.querySelector(`.bgr-li-remove-cell[data-seq="${seq}"]`)?.remove();
    tbody.querySelector(`tr[data-seq="${seq}"]`)?.remove();
    bgr_syncRemoveColGap(blockEl);
}

// .bgr-block-li-wrap's horizontal scrollbar (when the table needs it) reserves
// space at its own bottom that .bgr-block-li-remove-col — a plain non-scrolling
// sibling — doesn't lose. Without compensating for that, the two columns'
// rows drift out of alignment by exactly the scrollbar's height. Measuring
// it directly (rather than guessing a fixed px value) keeps this correct
// regardless of OS/browser scrollbar style.
function bgr_syncRemoveColGap(blockEl) {
    const wrap = blockEl.querySelector('.bgr-block-li-wrap');
    const col  = blockEl.querySelector('.bgr-block-li-remove-col');
    if (!wrap || !col) return;
    const scrollbarHeight = wrap.offsetHeight - wrap.clientHeight;
    col.style.paddingBottom = scrollbarHeight > 0 ? scrollbarHeight + 'px' : '0';
}

function bgr_liAccountChange(sel) {
    const tr   = sel.closest('tr');
    const opt  = BGR_COA_OPTIONS.find(a => String(a.account_id) === sel.value);
    const name = opt ? opt.account_title : '';
    const nameInput = tr.querySelector('.bgr-li-account-name');
    nameInput.value = name;
    nameInput.title = name;
}

/* ── Modal ─────────────────────────────────────────────────── */
function bgr_openModal(mode, data = null) {
    bgr_hideErr();

    $bgr('bgr-mode').value = mode;
    $bgr('bgr-fiscal-yr').disabled = false;

    if (mode === 'edit-program') {
        $bgr('bgr-id').value = data.budget_req_program_id;
        $bgr('bgr-modal-title').textContent = 'Edit Program';
        $bgr('bgr-save').textContent = 'Save Program';

        $bgr('bgr-fiscal-yr').value = data.fiscal_yr || '';
        $bgr('bgr-fiscal-yr').disabled = true;
        $bgr('bgr-program-code').value = data.program_code || '';
        bgr_fillSubPrograms(data.program_code, data.sub_program_code);

        const ctx = $bgr('bgr-program-context');
        ctx.textContent = `Editing a program under Budget Request #${data.budget_reference_number} (FY ${data.fiscal_yr}).`;
        ctx.style.display = '';

        $bgr('bgr-blocks-wrap').innerHTML = '';
        (data.projects && data.projects.length ? data.projects : [{}]).forEach(p => bgr_addProjectBlock(p));
    } else {
        $bgr('bgr-id').value = '';
        $bgr('bgr-modal-title').textContent = 'Add Budget Request';
        $bgr('bgr-save').textContent = 'Save Request';

        // Default a new request to the current year rather than an empty
        // placeholder — the most common case, and the user can still change it.
        const currentYearOption = $bgr('bgr-fiscal-yr').querySelector(`option[value="${new Date().getFullYear()}"]`);
        $bgr('bgr-fiscal-yr').value = currentYearOption ? currentYearOption.value : '';
        $bgr('bgr-program-code').value = '';
        bgr_fillSubPrograms('');

        $bgr('bgr-program-context').style.display = 'none';

        $bgr('bgr-blocks-wrap').innerHTML = '';
        bgr_addProjectBlock();
    }

    $bgr('bgr-overlay').classList.add('open');
}

function bgr_closeModal() {
    $bgr('bgr-overlay').classList.remove('open');
    $bgr('bgr-blocks-wrap').innerHTML = '';
    $bgr('bgr-form').reset();
    $bgr('bgr-fiscal-yr').disabled = false;
    bgr_hideErr();
}

function bgr_showErr(msg) {
    const el = $bgr('bgr-err');
    el.textContent = msg;
    el.style.display = 'block';
}
function bgr_hideErr() {
    $bgr('bgr-err').style.display = 'none';
}

async function bgr_save(ev) {
    ev.preventDefault();
    bgr_hideErr();
    const mode = $bgr('bgr-mode').value;
    const id   = $bgr('bgr-id').value;

    const fiscalYr       = $bgr('bgr-fiscal-yr').value;
    const programCode    = $bgr('bgr-program-code').value;
    const subProgramCode = $bgr('bgr-sub-program-code').value;
    if (!fiscalYr) { bgr_showErr('Select a fiscal year.'); return; }
    if (!programCode) { bgr_showErr('Select a program.'); return; }

    const projects = [];
    const blocks = document.querySelectorAll('#bgr-blocks-wrap .bgr-block');
    for (let pi = 0; pi < blocks.length; pi++) {
        const block = blocks[pi];
        // A matched suggestion resolves the hidden code; otherwise the typed
        // text has no confirmed match — send it as project_name so the
        // backend resolves/creates it atomically when the request is saved.
        const projectCode = block.querySelector('.bgr-block-project-code').value;
        const projectName = block.querySelector('.bgr-block-project-name').value.trim();
        if (!projectCode && !projectName) { bgr_showErr(`Project ${pi + 1}: select or enter a project.`); return; }

        const rows = Array.from(block.querySelectorAll('.bgr-block-li-tbody tr'));
        if (!rows.length) { bgr_showErr(`Project ${pi + 1}: add at least one line item.`); return; }

        const lineItems = [];
        for (let li = 0; li < rows.length; li++) {
            const tr          = rows[li];
            const accountId   = tr.querySelector('.bgr-li-account').value;
            const particulars = tr.querySelector('.bgr-li-particulars').value.trim();
            const amount      = bgr_unformatAmount(tr.querySelector('.bgr-li-amt').value);
            if (!accountId) { bgr_showErr(`Project ${pi + 1}, line item ${li + 1}: select a chart of accounts entry.`); return; }
            if (!particulars) { bgr_showErr(`Project ${pi + 1}, line item ${li + 1}: particulars is required.`); return; }
            if (!amount) { bgr_showErr(`Project ${pi + 1}, line item ${li + 1}: proposed amount is required.`); return; }
            lineItems.push({ account_id: accountId, particulars, proposed_amount: amount });
        }

        projects.push({ project_code: projectCode, project_name: projectName, line_items: lineItems });
    }
    if (!projects.length) { bgr_showErr('Add at least one project.'); return; }

    const body = { fiscal_yr: fiscalYr, program_code: programCode, sub_program_code: subProgramCode, projects };
    const url    = mode === 'edit-program' ? `${BGR_API}/program/update/${id}` : `${BGR_API}/add`;
    const method = mode === 'edit-program' ? 'PUT' : 'POST';

    window.PagePreloader?.show();
    try {
        const res  = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to save.');

        const message = mode === 'edit-program'
            ? 'Program updated successfully.'
            : (json.created_new_header ? 'Budget request created successfully.' : 'Program added to the existing budget request.');
        bgr_toast(message, 'success');
        bgr_closeModal();
        window.bgr_afterSave?.(mode, json);
    } catch (e) {
        bgr_showErr(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Helpers (also used by host pages' own scripts, loaded after this one) ── */
function bgr_fmtMoney(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function bgr_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}
function bgr_escAttr(text) {
    return String(text ?? '').replace(/'/g, "\\'");
}
function bgr_escHtmlAttr(text) {
    return String(text ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}
/* ── Comma-formatted amount inputs (display only — commas are stripped
   before validation/submission via bgr_unformatAmount) ──────────────── */
function bgr_fmtAmount(input) {
    const pos   = input.selectionStart;
    const prev  = input.value.length;
    const raw   = input.value.replace(/[^0-9.]/g, '');
    const parts = raw.split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    input.value = parts.length > 1 ? parts[0] + '.' + parts.slice(1).join('') : parts[0];
    const diff = input.value.length - prev;
    try { input.setSelectionRange(pos + diff, pos + diff); } catch (_) {}
}
function bgr_fmtAmountInitial(v) {
    return (v || v === 0) && v !== '' ? Number(v).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '';
}
function bgr_unformatAmount(v) {
    return String(v ?? '').replace(/,/g, '');
}
function bgr_statusBadge(name, color) {
    if (!name) return '<span class="nha-td-sub">—</span>';
    const c = /^#[0-9A-Fa-f]{6}$/.test(color || '') ? color : '#6B7280';
    return `<span style="display:inline-flex;align-items:center;gap:5px;padding:3px 9px;border-radius:999px;
        font-size:11px;font-weight:600;color:${c};background:${c}1A;border:1px solid ${c}40;white-space:nowrap;">
        <span style="width:6px;height:6px;border-radius:50%;background:${c};flex-shrink:0;"></span>${bgr_esc(name)}
    </span>`;
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
