<?php
/**
 * budget-req-readone.php
 *
 * Two read-only accordion views sharing one modal shell (JS namespace bgrv_):
 *
 *  - bgrv_openModal(requestContext, program) — "View" row action on one
 *    Program within a budget request. Lists every Sub-Program under that
 *    Program (master/reference data from fims.sub_program — NOT just the
 *    one sub-program this particular budget request happens to use), each
 *    expandable to that Sub-Program's Projects, annotated with THIS
 *    request's proposed amounts (per project, and a per-sub-program total).
 *      requestContext = { budget_reference_number, fiscal_yr, status_name, status_color, is_draft }
 *      program        = { budget_req_program_id, program_code, program_name, sub_program_name, projects[] }
 *    requestContext is used as-is (Fiscal Year / Status cards). The
 *    Sub-Programs/Projects shown are NOT read from program.projects[]
 *    though — this fetches them itself (filtered by program.program_code),
 *    since the full tree isn't part of the already-fetched list data; only
 *    the amounts come from program.projects[].
 *
 *  - bgrv_openCobModal(fiscalYr) — "View COB" button in the list page's
 *    filter bar, passed that same filter's current value ('' = every
 *    fiscal year). No row/request context, no summary cards, no accordion —
 *    a flat, always-visible indented outline (Program / Sub-Program /
 *    Project), built by its own renderer (bgrv_cobRowsHtml), not the
 *    panel/card one bgrv_openModal uses below. Sourced from the actual
 *    budget requests for that year (BGR_API + '/all?fiscal_yr=...'), not
 *    raw Program/Sub-Program/Project master data — so it only ever shows
 *    what's really been budgeted, flattened across every header the fetch
 *    returns (more than one for "All Years", or when the caller's RC scope
 *    spans several descendant rc_ids).
 *
 * Exclusive-accordion behavior (bgrv_openModal only) is scoped to each
 * panel's own immediate list (see bgrv_togglePanel).
 *
 * Included directly into budget-req-page.php rather than registered as its
 * own routed page — these are modals, not separate pages. Read-only: use
 * the list row's own "Edit" action to edit a Program.
 *
 * Depends on bgr_esc, bgr_fmtMoney, bgr_statusBadge and bgr_toast, plus the
 * BGR_PROGRAM_API / BGR_SUB_PROGRAM_API / BGR_PROJECT_BASE_API constants —
 * all defined in form/budget-req-form.php, which must be included on the
 * same page.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     PROGRAM VIEW MODAL   JS namespace: bgrv_
══════════════════════════════════════════════════════════ -->
<style>
    #bgrv-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #bgrv-overlay.open { display:flex; }
    #bgrv-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:820px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        transition:max-width .2s ease; }
    #bgrv-card.bgrv-wide { max-width:1040px; }

    #bgrv-header { display:flex;align-items:center;justify-content:space-between;
        padding:14px 18px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #bgrv-header-info { display:flex;align-items:center;gap:9px;min-width:0; }
    #bgrv-header-info svg { width:15px;height:15px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .bgrv-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .bgrv-hdr-title { font-size:15px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    /* COB mode's headline figure — the one number meant to grab attention,
       so it lives in the header bar itself (always visible, even once the
       list below scrolls) rather than buried in the small counts line.
       Bold/white/mono against the navy header is the same restrained
       "callout" treatment the rest of this app uses for emphasis — no new
       color introduced. */
    #bgrv-header-total { display:none;flex-direction:column;align-items:flex-end;margin-right:4px;flex-shrink:0; }
    #bgrv-header-total.show { display:flex; }
    .bgrv-header-total-label { font-size:9px;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.06em;margin:0 0 1px; }
    .bgrv-header-total-val { font-size:19px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);
        font-family:'IBM Plex Mono',monospace;line-height:1; }
    #bgrv-header-actions { display:flex;align-items:center;gap:8px;flex-shrink:0; }
    #bgrv-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:16px;line-height:1;transition:background .15s; }
    #bgrv-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #bgrv-body { flex:1;min-height:0;overflow-y:auto;padding:20px; }

    #bgrv-summary { display:grid;grid-template-columns:repeat(4, 1fr);gap:10px;margin-bottom:18px; }
    .bgrv-sum-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;padding:10px 12px;background:var(--afims-surface-subtle, #F4F7FA); }
    .bgrv-sum-label { font-size:10px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px; }
    .bgrv-sum-val { font-size:14px;font-weight:700;color:var(--afims-text, #1A1B21); }
    @media (max-width: 640px) { #bgrv-summary { grid-template-columns:repeat(2, 1fr); } }

    .bgrv-sec-hd { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px;
        display:flex;align-items:baseline;justify-content:space-between;gap:10px; }
    .bgrv-sec-hd-total { font-size:11px;font-weight:600;color:var(--afims-text-muted, #62657E);
        text-transform:none;letter-spacing:normal; }

    .bgrv-panel-list { display:flex;flex-direction:column;gap:8px; }
    .bgrv-panel-body .bgrv-panel-list { margin-top:8px; }
    .bgrv-panel { border:1px solid var(--afims-border, #E4ECF2);border-radius:10px;overflow:hidden;
        transition:box-shadow .15s,border-color .15s; }
    .bgrv-panel.open { box-shadow:0 2px 8px rgba(15,32,69,.08);border-color:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 25%, var(--afims-border, #E4ECF2)); }
    .bgrv-panel-hd { display:flex;align-items:center;justify-content:space-between;gap:10px;
        padding:10px 12px;background:var(--afims-surface-subtle, #F4F7FA);cursor:pointer;transition:background .15s; }
    .bgrv-panel-hd:hover { background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 60%, var(--afims-border, #E4ECF2)); }
    .bgrv-panel-hd-main { display:flex;align-items:center;gap:8px;min-width:0; }
    .bgrv-panel-chevron { flex-shrink:0;width:13px;height:13px;color:var(--afims-heading-fill, #000E3E);transition:transform .15s; }
    .bgrv-panel.open .bgrv-panel-chevron { transform:rotate(90deg); }
    .bgrv-panel-title { font-size:13px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .bgrv-panel-hd-meta { display:flex;align-items:center;gap:10px;flex-shrink:0; }
    .bgrv-panel-meta-item { font-size:11px;color:var(--afims-text-muted, #62657E);white-space:nowrap; }
    .bgrv-panel-meta-amt { font-size:12px;font-weight:700;color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Mono',monospace; }

    .bgrv-panel-body { display:none;padding:0 12px 12px; }
    .bgrv-panel.open .bgrv-panel-body { display:block; }

    .bgrv-proj-list { display:flex;flex-direction:column;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgrv-proj-row { display:flex;align-items:center;gap:10px;padding:8px 10px;font-size:12px;color:var(--afims-text, #1A1B21);
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgrv-proj-row:last-child { border-bottom:none; }
    .bgrv-proj-row:nth-child(even) { background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 45%, transparent); }
    .bgrv-proj-code { font-weight:700;font-family:'IBM Plex Mono',monospace;flex-shrink:0; }
    .bgrv-proj-name { color:var(--afims-text, #1A1B21);flex:1;min-width:0; }
    .bgrv-proj-amt { font-weight:700;font-family:'IBM Plex Mono',monospace;color:var(--afims-text, #1A1B21);white-space:nowrap;flex-shrink:0; }
    .bgrv-proj-empty { padding:12px;text-align:center;color:var(--afims-text-muted, #62657E);font-size:12px;font-style:italic; }

    #bgrv-empty { padding:24px 0;text-align:center;color:var(--afims-text-muted, #62657E);font-size:12px; }

    /* ── COB mode (bgrv_openCobModal): flat indented outline, no cards ── */
    .bgrv-cob-list { border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .bgrv-cob-row { padding:5px 10px;font-size:12px;color:var(--afims-text, #1A1B21);
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .bgrv-cob-list .bgrv-cob-row:last-child { border-bottom:none; }
    .bgrv-cob-program { font-weight:700;font-size:13px;background:var(--afims-surface-subtle, #F4F7FA); }
    .bgrv-cob-subprogram { padding-left:28px;font-weight:600;background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 50%, transparent); }
    .bgrv-cob-project { padding-left:52px;color:var(--afims-text-muted, #62657E); }

    /* COB mode only — larger text to match the wider modal (.bgrv-wide, see #bgrv-card above). */
    #bgrv-card.bgrv-wide .bgrv-hdr-title { font-size:17px; }
    #bgrv-card.bgrv-wide .bgrv-header-total-label { font-size:11px; }
    #bgrv-card.bgrv-wide .bgrv-header-total-val { font-size:23px; }
    #bgrv-card.bgrv-wide .bgrv-sec-hd,
    #bgrv-card.bgrv-wide .bgrv-sec-hd-total { font-size:13px; }
    #bgrv-card.bgrv-wide .bgrv-cob-row { padding:8px 14px;font-size:14px; }
    #bgrv-card.bgrv-wide .bgrv-cob-program { font-size:15px; }
    #bgrv-card.bgrv-wide .bgrv-cob-subprogram { padding-left:32px; }
    #bgrv-card.bgrv-wide .bgrv-cob-project { padding-left:58px; }
    .bgrv-cob-empty { font-style:italic; }
</style>

<div id="bgrv-overlay">
    <div id="bgrv-card">
        <div id="bgrv-header">
            <div id="bgrv-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div style="min-width:0;">
                    <p class="bgrv-hdr-tag" id="bgrv-hdr-tag">Program</p>
                    <p class="bgrv-hdr-title" id="bgrv-modal-title">Program</p>
                </div>
            </div>
            <div id="bgrv-header-total">
                <p class="bgrv-header-total-label">Total Proposed</p>
                <p class="bgrv-header-total-val" id="bgrv-header-total-val">—</p>
            </div>
            <div id="bgrv-header-actions">
                <button type="button" id="bgrv-close">&times;</button>
            </div>
        </div>

        <div id="bgrv-body">
            <div id="bgrv-summary"></div>

            <p class="bgrv-sec-hd">
                <span id="bgrv-sec-hd-label">Sub-Programs</span>
                <span class="bgrv-sec-hd-total" id="bgrv-sec-hd-total"></span>
            </p>
            <div id="bgrv-panels"></div>
            <div id="bgrv-empty" style="display:none;"></div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   PROGRAM / COB VIEW MODAL   prefix: bgrv_   — see docblock above for
   bgrv_openModal vs. bgrv_openCobModal.
═══════════════════════════════════════════════════════════════ */

const $bgrv = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bgrv('bgrv-close')?.addEventListener('click', bgrv_closeModal);
});

function bgrv_closeModal() {
    $bgrv('bgrv-overlay').classList.remove('open');
}

// Replaces #bgrv-summary's cards wholesale — the two modes show entirely
// different stats (Fiscal Year/Status tied to a request vs. plain counts),
// so this is simpler than keeping two parallel hard-coded markup blocks.
// An empty array hides the row entirely (COB mode has no use for it).
function bgrv_setSummary(cards) {
    const el = $bgrv('bgrv-summary');
    el.style.display = cards.length ? '' : 'none';
    el.style.gridTemplateColumns = cards.length ? `repeat(${cards.length}, 1fr)` : '';
    el.innerHTML = cards.map(c => `
        <div class="bgrv-sum-card">
            <div class="bgrv-sum-label">${c.label}</div>
            <div class="bgrv-sum-val" id="${c.id}">${c.value ?? '—'}</div>
        </div>`).join('');
}

/* ── Mode 1: one Program's Sub-Programs -> Projects, with this request's
   own amounts annotated in ──────────────────────────────────────────── */
async function bgrv_openModal(requestContext, program) {
    $bgrv('bgrv-card').classList.remove('bgrv-wide'); // COB mode only — see bgrv_openCobModal
    $bgrv('bgrv-hdr-tag').textContent = 'Program';
    $bgrv('bgrv-modal-title').textContent = `${program.program_code} — ${program.program_name}`;
    $bgrv('bgrv-sec-hd-label').textContent = 'Sub-Programs';
    $bgrv('bgrv-sec-hd-total').textContent = '';
    $bgrv('bgrv-header-total').classList.remove('show'); // COB mode's headline figure only
    bgrv_setSummary([
        { id: 'bgrv-sum-fiscal-yr',    label: 'Fiscal Year',     value: bgr_esc(requestContext.fiscal_yr ?? '—') },
        { id: 'bgrv-sum-status',      label: 'Status',           value: bgr_statusBadge(requestContext.status_name, requestContext.status_color) },
        { id: 'bgrv-sum-subprograms', label: '# Sub-Programs',   value: '—' },
        { id: 'bgrv-sum-projects',    label: '# Projects',       value: '—' },
    ]);
    $bgrv('bgrv-panels').innerHTML = '';
    $bgrv('bgrv-empty').textContent = 'This program has no sub-programs yet.';
    $bgrv('bgrv-empty').style.display = 'none';
    $bgrv('bgrv-overlay').classList.add('open');

    // project_code -> proposed amount, summed from this request's own
    // chosen projects/line items — independent of the master-data fetch below.
    const projectAmounts = new Map();
    (program.projects ?? []).forEach(p => {
        const subtotal = (p.line_items ?? []).reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0);
        projectAmounts.set(p.project_code, subtotal);
    });

    window.PagePreloader?.show();
    try {
        const params = new URLSearchParams({ program_code: program.program_code });
        const [subRes, projRes] = await Promise.all([
            fetch(`${BGR_SUB_PROGRAM_API}?${params}`, { credentials: 'same-origin' }),
            fetch(`${BGR_PROJECT_BASE_API}/all?${params}`, { credentials: 'same-origin' }),
        ]);
        const subJson  = await subRes.json();
        const projJson = await projRes.json();
        if (!subJson.success) throw new Error(subJson.message || 'Failed to load sub-programs.');
        if (!projJson.success) throw new Error(projJson.message || 'Failed to load projects.');

        const subPrograms = subJson.data ?? [];
        const projects    = projJson.data ?? [];
        $bgrv('bgrv-panels').innerHTML = subPrograms.length
            ? bgrv_subProgramAccordionHtml(subPrograms, projects, projectAmounts, true)
            : '';
        $bgrv('bgrv-empty').style.display = subPrograms.length ? 'none' : '';
        $bgrv('bgrv-sum-subprograms').textContent = subPrograms.length;
        $bgrv('bgrv-sum-projects').textContent = projects.length;
    } catch (e) {
        $bgrv('bgrv-panels').innerHTML = '';
        $bgrv('bgrv-empty').style.display = '';
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

/* ── Mode 2: every Program/Sub-Program/Project as a flat indented outline
   — no request context, no amounts, nothing to click ─────────────────── */
// fiscalYr: '' = every fiscal year (the page filter's "All Years" option).
// Scoped through the actual budget requests (/budget_req/all), not raw
// Program/Sub-Program/Project master data — so this only ever shows what's
// really been budgeted for that year (under the caller's own RC scope,
// same as the list page), never a Program/Project that happens to exist in
// master data but was never added to any request.
async function bgrv_openCobModal(fiscalYr = '') {
    $bgrv('bgrv-card').classList.add('bgrv-wide');
    $bgrv('bgrv-hdr-tag').textContent = 'Corporate Operating Budget (COB)';
    $bgrv('bgrv-modal-title').textContent = fiscalYr ? `All Programs — FY ${bgr_esc(fiscalYr)}` : 'All Programs — All Years';
    $bgrv('bgrv-sec-hd-label').textContent = 'Programs';
    $bgrv('bgrv-sec-hd-total').textContent = '';
    $bgrv('bgrv-header-total-val').textContent = '—';
    $bgrv('bgrv-header-total').classList.add('show');
    bgrv_setSummary([]);
    $bgrv('bgrv-panels').innerHTML = '';
    $bgrv('bgrv-empty').textContent = 'No programs found.';
    $bgrv('bgrv-empty').style.display = 'none';
    $bgrv('bgrv-overlay').classList.add('open');

    window.PagePreloader?.show();
    try {
        const params = fiscalYr ? `?fiscal_yr=${encodeURIComponent(fiscalYr)}` : '';
        const res  = await fetch(`${BGR_API}/all${params}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget requests.');

        const headers = json.data ?? [];

        // Flattened across every header this fetch returned — more than one
        // when "All Years" is picked, or when the caller's RC scope spans
        // several descendant rc_ids for the same year. A Program (or
        // Sub-Program) added more than once collapses to a single row; its
        // Projects and the grand total still count every occurrence.
        const programMap   = new Map(); // program_code -> { program_code, program_name }
        const subByProgram = new Map(); // program_code -> Map(sub_program_code -> { sub_program_code, sub_program_name })
        const projByProgram = new Map(); // program_code -> [{ project_code, project_name, sub_program_code }, ...]
        let grandTotal   = 0;
        let projectCount = 0;

        headers.forEach(h => {
            (h.programs || []).forEach(prog => {
                if (!programMap.has(prog.program_code)) {
                    programMap.set(prog.program_code, { program_code: prog.program_code, program_name: prog.program_name });
                    subByProgram.set(prog.program_code, new Map());
                    projByProgram.set(prog.program_code, []);
                }
                if (prog.sub_program_code) {
                    subByProgram.get(prog.program_code).set(prog.sub_program_code, {
                        sub_program_code: prog.sub_program_code,
                        sub_program_name: prog.sub_program_name,
                    });
                }
                (prog.projects || []).forEach(p => {
                    projByProgram.get(prog.program_code).push({
                        project_code: p.project_code,
                        project_name: p.project_name,
                        sub_program_code: prog.sub_program_code,
                    });
                    projectCount++;
                    (p.line_items || []).forEach(li => { grandTotal += Number(li.proposed_amount) || 0; });
                });
            });
        });

        const programs = Array.from(programMap.values());
        const subProgramCount = Array.from(subByProgram.values()).reduce((sum, m) => sum + m.size, 0);

        const rowsHtml = programs.map(prog => {
            const progSubPrograms = Array.from(subByProgram.get(prog.program_code).values());
            const progProjects    = projByProgram.get(prog.program_code) || [];
            return `<div class="bgrv-cob-row bgrv-cob-program">${bgr_esc(prog.program_code)} — ${bgr_esc(prog.program_name)}</div>`
                + bgrv_cobRowsHtml(progSubPrograms, progProjects);
        }).join('');

        $bgrv('bgrv-header-total-val').textContent = bgr_fmtMoney(grandTotal);
        $bgrv('bgrv-panels').innerHTML = rowsHtml ? `<div class="bgrv-cob-list">${rowsHtml}</div>` : '';
        $bgrv('bgrv-empty').style.display = programs.length ? 'none' : '';
        $bgrv('bgrv-sec-hd-total').textContent =
            `${programs.length} program(s) · ${subProgramCount} sub-program(s) · ${projectCount} project(s)`;
    } catch (e) {
        $bgrv('bgrv-panels').innerHTML = '';
        $bgrv('bgrv-empty').style.display = '';
        $bgrv('bgrv-sec-hd-total').textContent = '';
        $bgrv('bgrv-header-total-val').textContent = '—';
        bgr_toast(e.message, 'error');
    } finally {
        window.PagePreloader?.hide();
    }
}

// bgrv_openModal only: groups the given Projects by sub_program_code — a
// project with no match (including a null/empty sub_program_code) falls
// into a trailing "No Sub-Program" group rather than silently disappearing.
function bgrv_subProgramAccordionHtml(subPrograms, projects, projectAmounts, showAmounts) {
    const bySubProgram = new Map();
    subPrograms.forEach(sp => bySubProgram.set(sp.sub_program_code, []));

    const unassigned = [];
    projects.forEach(p => {
        const key = p.sub_program_code;
        if (key && bySubProgram.has(key)) {
            bySubProgram.get(key).push(p);
        } else {
            unassigned.push(p);
        }
    });

    const groups = subPrograms.map(sp => ({
        label: `${bgr_esc(sp.sub_program_code)} — ${bgr_esc(sp.sub_program_name)}`,
        projects: bySubProgram.get(sp.sub_program_code) || [],
    }));
    if (unassigned.length) {
        groups.push({ label: 'No Sub-Program', projects: unassigned });
    }

    const panelsHtml = groups.map((g, idx) => bgrv_subProgramPanelHtml(g.label, g.projects, idx === 0, projectAmounts, showAmounts));
    return `<div class="bgrv-panel-list">${panelsHtml.join('')}</div>`;
}

function bgrv_subProgramPanelHtml(label, projectsList, openByDefault, projectAmounts, showAmounts) {
    let subtotal = 0;
    const bodyHtml = projectsList.length
        ? `<div class="bgrv-proj-list">${projectsList.map(p => {
            let amtHtml = '';
            if (showAmounts) {
                const amount = projectAmounts.get(p.project_code) ?? 0;
                subtotal += amount;
                amtHtml = `<span class="bgrv-proj-amt">${bgr_fmtMoney(amount)}</span>`;
            }
            return `<div class="bgrv-proj-row">
                <span class="bgrv-proj-code">${bgr_esc(p.project_code)}</span>
                <span class="bgrv-proj-name">${bgr_esc(p.project_name)}</span>
                ${amtHtml}
            </div>`;
        }).join('')}</div>`
        : '<div class="bgrv-proj-empty">No projects added to this sub-program yet.</div>';

    const metaAmtHtml = showAmounts ? `<span class="bgrv-panel-meta-amt">${bgr_fmtMoney(subtotal)}</span>` : '';

    return `<div class="bgrv-panel${openByDefault ? ' open' : ''}">
        <div class="bgrv-panel-hd" onclick="bgrv_togglePanel(this)">
            <div class="bgrv-panel-hd-main">
                <svg class="bgrv-panel-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                <div class="bgrv-panel-title">${label}</div>
            </div>
            <div class="bgrv-panel-hd-meta">
                <span class="bgrv-panel-meta-item">${projectsList.length} project(s)</span>
                ${metaAmtHtml}
            </div>
        </div>
        <div class="bgrv-panel-body">${bodyHtml}</div>
    </div>`;
}

// Exclusive accordion, scoped to the clicked panel's own immediate list
// (bgrv_openModal only — bgrv_openCobModal has nothing collapsible).
function bgrv_togglePanel(headerEl) {
    const panel = headerEl.closest('.bgrv-panel');
    const list  = headerEl.closest('.bgrv-panel-list');
    Array.from(list.children).forEach(p => {
        if (p === panel) {
            p.classList.toggle('open');
        } else {
            p.classList.remove('open');
        }
    });
}

// bgrv_openCobModal only: flat indented rows (Sub-Program, then its
// Projects) for one Program — same sub_program_code grouping rule as
// bgrv_subProgramAccordionHtml above, just rendered as plain rows instead
// of accordion panels.
function bgrv_cobRowsHtml(subPrograms, projects) {
    const bySubProgram = new Map();
    subPrograms.forEach(sp => bySubProgram.set(sp.sub_program_code, []));

    const unassigned = [];
    projects.forEach(p => {
        const key = p.sub_program_code;
        if (key && bySubProgram.has(key)) {
            bySubProgram.get(key).push(p);
        } else {
            unassigned.push(p);
        }
    });

    const groups = subPrograms.map(sp => ({
        label: `${bgr_esc(sp.sub_program_code)} — ${bgr_esc(sp.sub_program_name)}`,
        projects: bySubProgram.get(sp.sub_program_code) || [],
    }));
    if (unassigned.length) {
        groups.push({ label: 'No Sub-Program', projects: unassigned });
    }
    if (!groups.length) {
        return '<div class="bgrv-cob-row bgrv-cob-subprogram bgrv-cob-empty">No sub-programs under this program yet.</div>';
    }

    return groups.map(g => {
        const projRows = g.projects.length
            ? g.projects.map(p => `<div class="bgrv-cob-row bgrv-cob-project">${bgr_esc(p.project_code)} — ${bgr_esc(p.project_name)}</div>`).join('')
            : '<div class="bgrv-cob-row bgrv-cob-project bgrv-cob-empty">No projects yet.</div>';
        return `<div class="bgrv-cob-row bgrv-cob-subprogram">${g.label}</div>${projRows}`;
    }).join('');
}
</script>
