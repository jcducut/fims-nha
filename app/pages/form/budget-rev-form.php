<?php
namespace PHPMaker2024\FIMS;
$_brv_form_api = Config('API.FIMS_URL');
?>
<style>
/* ── Budget revision form: sections, searchable dropdowns, summary ─────────
   Scoped to .nha-root so it picks up the nha-fims.css variables. */
.nha-root .brv-section {
    font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--sec, #62657E);
}

/* From COB and To COB share one row as two separate columns (they stack on narrow screens).
   Each column is label / box / summary card; where the browser supports subgrid the three rows are
   shared, so the two boxes are the same height and the two inputs sit on the same line. */
.nha-root .brv-cobrow { display: grid; grid-template-columns: minmax(0, 1fr); gap: 16px; align-items: start; }
.nha-root .brv-cobcol { min-width: 0; }
@media (min-width: 768px) {
    .nha-root .brv-cobrow { grid-template-columns: repeat(2, minmax(0, 1fr)); column-gap: 16px; row-gap: 0; }
}
@media (min-width: 768px) {
    @supports (grid-template-rows: subgrid) {
        .nha-root .brv-cobrow { grid-template-rows: auto auto auto; }
        .nha-root .brv-cobcol { display: grid; grid-row: span 3; grid-template-rows: subgrid; }
    }
}
/* a wide modal for the two columns (bigger than .nha-modal-md) */
.nha-root #brv-overlay .nha-modal.brv-modal-wide { max-width: 860px; }

/* COB field with its filter bar (both From COB and To COB): one bordered box, the bar on top, the field below.
   No overflow:hidden here — the dropdown lists must be able to open outside the box. */
.nha-root .brv-reqgroup { display: flex; flex-direction: column; border: 1px solid var(--bdr, #E4ECF2); border-radius: var(--rs, 7px); }
.nha-root .brv-filterbar {
    position: relative;   /* the three filter lists anchor to the bar, not to their narrow inputs */
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    padding: 8px 10px; background: var(--surf, #F4F7FA);
    border-bottom: 1px solid var(--bdr, #E4ECF2);
    border-radius: var(--rs, 7px) var(--rs, 7px) 0 0;
}
.nha-root .brv-filterbar-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--sec, #62657E);
}
.nha-root .brv-filterbar .brv-combo { position: static; flex: 1 1 90px; min-width: 0; }
.nha-root .brv-combo-field { position: relative; }   /* holds the input + its clear (×) button */
.nha-root .brv-filterbar .brv-combo-fy { flex: 0 1 84px; }
.nha-root .brv-filterbar .form-control { height: 30px; padding: 3px 26px 3px 10px; font-size: 12.5px; }
.nha-root .brv-filterbar .brv-combo-clear { right: 3px; font-size: 16px; }
/* the filter lists span the whole bar (positioned against the bar), so they never overflow the modal */
.nha-root .brv-filterbar .brv-combo-list { left: 0; right: 0; top: calc(100% + 4px); }
.nha-root .brv-filter-reset {
    margin-left: auto; border: 0; background: transparent; padding: 2px 4px; cursor: pointer;
    font-size: 12px; font-weight: 600; text-decoration: underline;
    color: var(--afims-primary-text, #0035A9);
}
.nha-root .brv-filter-reset[hidden] { display: none; }
.nha-root .brv-reqfield { padding: 10px; }

/* searchable dropdown (combobox) */
.nha-root .brv-combo { position: relative; }
/* The theme (FIMS.css, viewports >= 576px) makes text inputs width:auto with min-width:250px, which
   would overflow the compact filter bar — so the combo inputs always fill their wrapper. */
.nha-root .brv-combo .form-control { display: block; width: 100%; min-width: 0 !important; padding-right: 34px; }
.nha-root .brv-combo-clear {
    position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
    border: 0; background: transparent; color: var(--muted, #A9AAB8);
    font-size: 18px; line-height: 1; padding: 2px 6px; cursor: pointer;
}
.nha-root .brv-combo-clear:hover { color: var(--txt, #1A1B21); }
.nha-root .brv-combo-list {
    position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0;
    max-height: 220px; overflow-y: auto; margin: 0; padding: 4px; list-style: none;
    background: var(--card, #fff); border: 1px solid var(--bdr, #E4ECF2);
    border-radius: var(--rs, 7px); box-shadow: var(--sh-md, 0 4px 14px rgba(0,0,0,.10));
}
.nha-root .brv-combo-clear[hidden],
.nha-root .brv-combo-list[hidden],
.nha-root .brv-summary[hidden],
.nha-root .brv-change[hidden] { display: none; }
.nha-root .brv-opt {
    display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1px 8px; align-items: center;
    padding: 7px 10px; border-radius: var(--rxs, 5px); cursor: pointer;
    font-size: 13px; color: var(--txt, #1A1B21);
}
.nha-root .brv-opt:hover,
.nha-root .brv-opt.is-active { background: var(--navy-lt, #F4F7FA); }
.nha-root .brv-opt-main { font-weight: 500; overflow-wrap: anywhere; }
.nha-root .brv-opt-sub { grid-column: 1 / -1; font-size: 11.5px; color: var(--subtle, #62657E); }
.nha-root .brv-opt-empty { padding: 10px; font-size: 12.5px; text-align: center; color: var(--subtle, #62657E); }
.nha-root .brv-hint { margin-top: 4px; font-size: 11.5px; color: var(--subtle, #62657E); }
/* "Showing n of N" on the left, the Clear filters link on the right — it never changes the box height */
.nha-root .brv-hintrow { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; min-height: 22px; }

/* selected budget request */
.nha-root .brv-summary {
    margin-top: 10px; padding: 10px 12px; font-size: 12.5px;
    background: var(--blue-bg, #E6EBF6); border: 1px solid var(--navy-bdr, #E6EBF6);
    border-radius: var(--rs, 7px);
}
.nha-root .brv-summary-top { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.nha-root .brv-summary-sub { margin-top: 2px; color: var(--subtle, #62657E); }
.nha-root .brv-summary-amt { margin-top: 6px; }

/* from/to change indicator */
.nha-root .brv-change {
    padding: 6px 10px; border-radius: var(--rs, 7px); font-size: 12.5px; font-weight: 600;
    background: var(--surf, #F4F7FA); color: var(--subtle, #62657E);
}
.nha-root .brv-change.is-up   { background: var(--green-bg, #DBF2E5); color: var(--green-txt, #0C7539); }
.nha-root .brv-change.is-down { background: var(--red-bg, #FAE1E3);   color: var(--red-txt, #8F222D); }
.nha-root .brv-change.is-move { background: var(--blue-bg, #E6EBF6);  color: var(--afims-primary-text, #0035A9); }

/* "(optional)" tag next to a label */
.nha-root .brv-optional { font-weight: 400; color: var(--subtle, #62657E); }
</style>

<!-- The wrapper makes the modal styles apply wherever this form is included
     (every .nha-modal rule is scoped to .nha-root); padding/background are
     neutralised so it takes no space on the page. -->
<div class="nha-root" style="padding:0;background:none;">

<!-- ── ADD / EDIT MODAL ─────────────────────────────────── -->
<div id="brv-overlay" class="nha-overlay">
    <div class="nha-modal nha-modal-md brv-modal-wide">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="brv-modal-title">Add Budget Revision Request</h3>
            <button type="button" class="nha-modal-close" id="brv-modal-close">&times;</button>
        </div>
        <form id="brv-form">
            <div class="nha-modal-body">
                <input type="hidden" id="brv-mode" value="add">
                <input type="hidden" id="brv-id" value="">
                <input type="hidden" id="brv-budget-req-id" value="">
                <input type="hidden" id="brv-to-budget-req-id" value="">
                <div class="row g-3">

                    <!-- ── From COB | To COB: one row, two separate columns ── -->
                    <div class="col-12">
                    <div class="brv-cobrow">

                    <!-- From COB (budget_revision.from_budget_req_id): a filter bar (Fiscal Year · Program · Project) attached to the searchable field -->
                    <div class="brv-cobcol">
                        <label class="form-label" for="brv-req-input">From COB <span class="text-danger">*</span></label>

                        <div class="brv-reqgroup">
                            <div class="brv-filterbar" role="group" aria-label="Filter the COB list">
                                <span class="brv-filterbar-label">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                    Filter
                                </span>
                                <div class="brv-combo brv-combo-fy">
                                    <div class="brv-combo-field">
                                        <input type="text" class="form-control" id="brv-fy-input" placeholder="Year" aria-label="Filter by fiscal year"
                                               maxlength="4" inputmode="numeric" autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-fy-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-fy-clear" aria-label="Clear fiscal year" hidden>&times;</button>
                                    </div>
                                    <ul class="brv-combo-list" id="brv-fy-list" role="listbox" hidden></ul>
                                </div>
                                <div class="brv-combo">
                                    <div class="brv-combo-field">
                                        <input type="text" class="form-control" id="brv-program-input" placeholder="Program" aria-label="Filter by program"
                                               autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-program-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-program-clear" aria-label="Clear program" hidden>&times;</button>
                                    </div>
                                    <ul class="brv-combo-list" id="brv-program-list" role="listbox" hidden></ul>
                                </div>
                                <div class="brv-combo">
                                    <div class="brv-combo-field">
                                        <input type="text" class="form-control" id="brv-project-input" placeholder="Project" aria-label="Filter by project"
                                               autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-project-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-project-clear" aria-label="Clear project" hidden>&times;</button>
                                    </div>
                                    <ul class="brv-combo-list" id="brv-project-list" role="listbox" hidden></ul>
                                </div>
                            </div>

                            <div class="brv-reqfield">
                                <div class="brv-combo">
                                    <input type="text" class="form-control" id="brv-req-input" placeholder="Search by program, project or fiscal year"
                                           required autocomplete="off"
                                           role="combobox" aria-expanded="false" aria-controls="brv-req-list" aria-autocomplete="list">
                                    <button type="button" class="brv-combo-clear" id="brv-req-clear" aria-label="Clear COB" hidden>&times;</button>
                                    <ul class="brv-combo-list" id="brv-req-list" role="listbox" hidden></ul>
                                </div>
                                <div class="brv-hintrow">
                                    <div class="brv-hint" id="brv-req-hint">Loading budget requests…</div>
                                    <button type="button" class="brv-filter-reset" id="brv-filter-reset" hidden>Clear filters</button>
                                </div>
                            </div>
                        </div>

                        <div class="brv-summary" id="brv-req-summary" hidden>
                            <div class="brv-summary-top">
                                <span class="nha-bdg nha-bdg-blue" id="brv-sum-type">Program</span>
                                <strong id="brv-sum-title"></strong>
                            </div>
                            <div class="brv-summary-sub" id="brv-sum-sub"></div>
                            <div class="brv-summary-amt">
                                <span>Current amount <strong id="brv-sum-amount"></strong></span>
                            </div>
                        </div>
                    </div>

                    <!-- To COB (budget_revision.to_budget_req_id): optional — the COB that receives the funds the From COB gives up.
                         The same box as the From COB (the same filter bar on top, the searchable field below). -->
                    <div class="brv-cobcol">
                        <label class="form-label" for="brv-to-req-input">To COB <span class="brv-optional">(optional — receives the funds)</span></label>

                        <div class="brv-reqgroup">
                            <div class="brv-filterbar" role="group" aria-label="Filter the To COB list">
                                <span class="brv-filterbar-label">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                    Filter
                                </span>
                                <div class="brv-combo brv-combo-fy">
                                    <div class="brv-combo-field">
                                        <input type="text" class="form-control" id="brv-to-fy-input" placeholder="Year" aria-label="Filter the To COB by fiscal year"
                                               maxlength="4" inputmode="numeric" autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-to-fy-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-to-fy-clear" aria-label="Clear fiscal year" hidden>&times;</button>
                                    </div>
                                    <ul class="brv-combo-list" id="brv-to-fy-list" role="listbox" hidden></ul>
                                </div>
                                <div class="brv-combo">
                                    <div class="brv-combo-field">
                                        <input type="text" class="form-control" id="brv-to-program-input" placeholder="Program" aria-label="Filter the To COB by program"
                                               autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-to-program-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-to-program-clear" aria-label="Clear program" hidden>&times;</button>
                                    </div>
                                    <ul class="brv-combo-list" id="brv-to-program-list" role="listbox" hidden></ul>
                                </div>
                                <div class="brv-combo">
                                    <div class="brv-combo-field">
                                        <input type="text" class="form-control" id="brv-to-project-input" placeholder="Project" aria-label="Filter the To COB by project"
                                               autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-to-project-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-to-project-clear" aria-label="Clear project" hidden>&times;</button>
                                    </div>
                                    <ul class="brv-combo-list" id="brv-to-project-list" role="listbox" hidden></ul>
                                </div>
                            </div>

                            <div class="brv-reqfield">
                                <div class="brv-combo">
                                    <input type="text" class="form-control" id="brv-to-req-input" placeholder="Optional — search by program, project or year"
                                           autocomplete="off"
                                           role="combobox" aria-expanded="false" aria-controls="brv-to-req-list" aria-autocomplete="list">
                                    <button type="button" class="brv-combo-clear" id="brv-to-req-clear" aria-label="Clear To COB" hidden>&times;</button>
                                    <ul class="brv-combo-list" id="brv-to-req-list" role="listbox" hidden></ul>
                                </div>
                                <div class="brv-hintrow">
                                    <div class="brv-hint" id="brv-to-req-hint">Loading budget requests…</div>
                                    <button type="button" class="brv-filter-reset" id="brv-to-filter-reset" hidden>Clear filters</button>
                                </div>
                            </div>
                        </div>

                        <div class="brv-summary" id="brv-to-req-summary" hidden>
                            <div class="brv-summary-top">
                                <span class="nha-bdg nha-bdg-blue" id="brv-to-sum-type">Program</span>
                                <strong id="brv-to-sum-title"></strong>
                            </div>
                            <div class="brv-summary-sub" id="brv-to-sum-sub"></div>
                            <div class="brv-summary-amt">
                                <span>Current amount <strong id="brv-to-sum-amount"></strong></span>
                            </div>
                        </div>
                    </div>

                    </div><!-- /.brv-cobrow -->
                    </div>

                    <!-- ── The revision ── -->
                    <div class="col-12 brv-section">Revision</div>
                    <div class="col-6">
                        <label class="form-label" for="brv-from-amount">From Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">&#8369;</span>
                            <input type="number" class="form-control text-end" id="brv-from-amount" step="0.01" min="0" placeholder="Auto-filled" disabled>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="brv-to-amount">To Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">&#8369;</span>
                            <input type="number" class="form-control text-end" id="brv-to-amount" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    <div class="col-12" id="brv-change-wrap">
                        <div class="brv-change" id="brv-change" hidden></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="brv-reason">Reason</label>
                        <textarea class="form-control" id="brv-reason" rows="3" placeholder="Explain why this budget needs to be revised"></textarea>
                    </div>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn-ghost" id="brv-cancel">Cancel</button>
                <button type="submit" class="nha-btn-primary" id="brv-save">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════════════════
   COB pickers — data comes from GET /budget_req/all (fetched once, shared).

   Two pickers share one layout, and each has its OWN filters and selection:
     From COB — required (budget_revision.from_budget_req_id)
     To COB   — optional (budget_revision.to_budget_req_id): the COB that receives
                the funds the From COB gives up. Its list never offers the From COB.

   Each picker is four searchable dropdowns that work together:
     Fiscal Year  — years that have a budget request
     Program      — programs only
     Project      — projects only. Pick a program first and it lists just that
                    program's projects; pick a project first and it fills in
                    its program.
     the field    — the three filters form a compact bar attached to it (with a
                    "Clear" link); the list is narrowed by them and by whatever
                    is typed into the field itself

   Self-contained: needs nothing from the host page. It exposes:
     window.brvBudgetReqsReady  promise → [{ value, label }] (the host page
                                reuses it for its own filter, no second request)
     window.brvForm.setBudgetReq(fromId, toId)   preselect the From / To COB (used by
                                Edit); does NOT touch From Amount, so a saved value survives

   From Amount is disabled: it always follows the picked From COB. Picking a
   From COB fills it with that COB's current amount; clearing the COB
   clears it.

   With a To COB the revision MOVES funds, so To Amount must be lower than From Amount:
   the From COB goes down to To Amount and the To COB goes up by the difference.
══════════════════════════════════════════════════════════════════════════ */
(function () {
    const $ = id => document.getElementById(id);
    const API = '<?php echo $_brv_form_api; ?>' + '/budget_req/all';
    const peso = v => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    // rows: every budget request (both pickers read them) · loaded: the first fetch has finished
    const S = { rows: [], loaded: false };

    // A project-level row carries BOTH codes; project_code is what marks it as a project.
    const isProject  = r => !!r.project_code;
    const codeOf     = r => r.project_code || r.program_code || '—';
    const nameOf     = r => r.project_name || r.program_name || '';
    const titleOf    = r => codeOf(r) + (nameOf(r) ? ' — ' + nameOf(r) : '');
    const shortLabel = r => `FY ${r.fiscal_yr} — ${titleOf(r)}`;
    // What a COB input shows once a request is picked: just its code — the program
    // code for a program-level request (the project code for a project-level one). No fiscal year,
    // no name; the full shortLabel is the input's tooltip and the summary card shows the rest.
    const inputLabel = r => codeOf(r);
    const fullLabel  = r => `${shortLabel(r)} (${peso(r.amount)})`;
    const plural     = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
    const programLabel = code => {
        const r = S.rows.find(x => x.program_code === code);
        return code + (r && r.program_name ? ' — ' + r.program_name : '');
    };
    const searchText = r => [r.fiscal_yr, r.program_code, r.program_name, r.project_code, r.project_name, isProject(r) ? 'project' : 'program']
        .join(' ').toLowerCase();
    const rowItem = r => ({
        row: r,
        main: titleOf(r),
        sub: `FY ${r.fiscal_yr} · ${isProject(r) ? 'Project under ' + r.program_code : 'Program'} · ${peso(r.amount)}`,
        badge: isProject(r) ? 'Project' : 'Program',
        badgeClass: isProject(r) ? 'nha-bdg-teal' : 'nha-bdg-blue',
    });

    // the form-level fields (not part of either picker)
    const el = { form: $('brv-form'), from: $('brv-from-amount'), to: $('brv-to-amount'), change: $('brv-change') };

    // element ids of each picker (the From COB keeps the original ids)
    const FROM_IDS = {
        id: 'brv-budget-req-id',
        fyIn: 'brv-fy-input', fyClear: 'brv-fy-clear', fyList: 'brv-fy-list',
        prIn: 'brv-program-input', prClear: 'brv-program-clear', prList: 'brv-program-list',
        pjIn: 'brv-project-input', pjClear: 'brv-project-clear', pjList: 'brv-project-list',
        reqIn: 'brv-req-input', reqClear: 'brv-req-clear', reqList: 'brv-req-list',
        hint: 'brv-req-hint', reset: 'brv-filter-reset',
        summary: 'brv-req-summary', sumType: 'brv-sum-type', sumTitle: 'brv-sum-title', sumSub: 'brv-sum-sub', sumAmount: 'brv-sum-amount',
    };
    const TO_IDS = {
        id: 'brv-to-budget-req-id',
        fyIn: 'brv-to-fy-input', fyClear: 'brv-to-fy-clear', fyList: 'brv-to-fy-list',
        prIn: 'brv-to-program-input', prClear: 'brv-to-program-clear', prList: 'brv-to-program-list',
        pjIn: 'brv-to-project-input', pjClear: 'brv-to-project-clear', pjList: 'brv-to-project-list',
        reqIn: 'brv-to-req-input', reqClear: 'brv-to-req-clear', reqList: 'brv-to-req-list',
        hint: 'brv-to-req-hint', reset: 'brv-to-filter-reset',
        summary: 'brv-to-req-summary', sumType: 'brv-to-sum-type', sumTitle: 'brv-to-sum-title', sumSub: 'brv-to-sum-sub', sumAmount: 'brv-to-sum-amount',
    };

    /* ── searchable dropdown helper ────────────────────────────────────── */
    function combo({ input, list, clear, items, query, empty, onPick, onType, onClear, onBlur }) {
        let shown = [], active = -1;
        const isOpen = () => !list.hidden;
        const syncClear = () => { if (clear) clear.hidden = !input.value; };

        function render() {
            shown = items(query());
            list.textContent = '';
            active = -1;
            if (!shown.length) {
                const li = document.createElement('li');
                li.className = 'brv-opt-empty';
                li.textContent = !S.loaded ? 'Loading…' : (empty ? empty() : 'No matches');
                list.appendChild(li);
                return;
            }
            shown.forEach((it, i) => {
                const li = document.createElement('li');
                li.className = 'brv-opt';
                li.setAttribute('role', 'option');
                li.dataset.i = String(i);
                const main = document.createElement('span');
                main.className = 'brv-opt-main';
                main.textContent = it.main;
                li.appendChild(main);
                if (it.badge) {
                    const b = document.createElement('span');
                    b.className = 'nha-bdg ' + it.badgeClass;
                    b.textContent = it.badge;
                    li.appendChild(b);
                }
                if (it.sub) {
                    const s = document.createElement('span');
                    s.className = 'brv-opt-sub';
                    s.textContent = it.sub;
                    li.appendChild(s);
                }
                list.appendChild(li);
            });
        }
        function open()  { render(); list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
        function close() { list.hidden = true; input.setAttribute('aria-expanded', 'false'); active = -1; }
        function setActive(i) {
            const opts = list.querySelectorAll('.brv-opt');
            opts.forEach(o => o.classList.remove('is-active'));
            if (!opts.length) { active = -1; return; }
            active = (i + opts.length) % opts.length;
            opts[active].classList.add('is-active');
            opts[active].scrollIntoView({ block: 'nearest' });
        }
        function pick(i) {
            const it = shown[i];
            if (!it) return;
            close();
            onPick(it);
            syncClear();
        }

        input.addEventListener('focus', open);
        input.addEventListener('click', () => { if (!isOpen()) open(); });
        input.addEventListener('input', () => { onType && onType(input.value); syncClear(); open(); });
        input.addEventListener('blur', () => { close(); onBlur && onBlur(); syncClear(); });
        input.addEventListener('keydown', e => {
            if (e.key === 'ArrowDown')      { e.preventDefault(); if (!isOpen()) open(); setActive(active + 1); }
            else if (e.key === 'ArrowUp')   { e.preventDefault(); if (!isOpen()) open(); setActive(active - 1); }
            else if (e.key === 'Enter' && isOpen()) {
                e.preventDefault();                       // never submit the form while picking
                if (active >= 0) pick(active);
                else if (shown.length === 1) pick(0);
            }
            else if (e.key === 'Escape' && isOpen()) { e.preventDefault(); e.stopPropagation(); close(); }
            else if (e.key === 'Tab') close();
        });
        // mousedown (not click) so the input keeps focus and blur never beats the pick
        list.addEventListener('mousedown', e => {
            e.preventDefault();
            const li = e.target.closest('.brv-opt');
            if (li) pick(Number(li.dataset.i));
        });
        if (clear) clear.addEventListener('click', () => { onClear(); syncClear(); input.focus(); });
        document.addEventListener('mousedown', e => { if (!input.parentElement.contains(e.target) && !list.contains(e.target)) close(); });

        return { close, syncClear };
    }

    // The card under a COB field (From COB and To COB share the same layout).
    function renderCard(r, box, typeEl, titleEl, subEl, amountEl) {
        box.hidden = !r;
        if (!r) return;
        const proj = isProject(r);
        typeEl.textContent = proj ? 'Project' : 'Program';
        typeEl.className = 'nha-bdg ' + (proj ? 'nha-bdg-teal' : 'nha-bdg-blue');
        titleEl.textContent = titleOf(r);
        subEl.textContent = `FY ${r.fiscal_yr}` + (proj
            ? ` · Program: ${r.program_code}${r.program_name ? ' — ' + r.program_name : ''}` : '');
        amountEl.textContent = peso(r.amount);
    }

    /* ── one COB picker: filter bar + searchable field + summary card ──────
       ids       the element ids (FROM_IDS / TO_IDS)
       exclude   () => a row the list must not offer (the To COB excludes the From COB)
       required  the field is mandatory — native validation until a COB is picked
       onSelect  (row, { autofill }) after the selection changed
       onClear   the user pressed × on the field (runs just before the selection is cleared) */
    function makePicker(ids, { exclude = () => null, required = false, requiredMsg = '', onSelect = () => {}, onClear = () => {} } = {}) {
        const pe = {};
        Object.keys(ids).forEach(k => { pe[k] = $(ids[k]); });

        // fy: '' or '2026' · program: null | { code, label, full } · project: null | { code, label, full, program }
        // selected: the picked budget request (a row) or null
        const P = { fy: '', program: null, project: null, selected: null };

        // every row this picker may offer
        const baseRows = () => {
            const ex = exclude();
            return ex ? S.rows.filter(r => String(r.id) !== String(ex.id)) : S.rows;
        };

        /* ── filtering ── */
        // Rows that satisfy the active filters. Each dropdown switches off the
        // filter(s) it feeds, so its suggestions only react to the OTHER filters.
        function matchRows({ fy = true, program = true, project = true, q = '' } = {}) {
            const needle = q.toLowerCase();
            return baseRows().filter(r => {
                if (fy && P.fy && String(r.fiscal_yr) !== P.fy) return false;
                if (program && P.program && r.program_code !== P.program.code) return false;
                if (project && P.project && r.project_code !== P.project.code) return false;
                if (!needle) return true;
                return searchText(r).includes(needle);
            });
        }

        function fyItems(q) {
            const counts = new Map();
            matchRows({ fy: false }).forEach(r => {
                const y = String(r.fiscal_yr);
                counts.set(y, (counts.get(y) || 0) + 1);
            });
            return [...counts.keys()].sort((a, b) => b - a).filter(y => y.includes(q))
                .map(y => ({ key: y, main: y, sub: plural(counts.get(y), 'budget request') }));
        }

        // Programs only.
        function programItems(q) {
            const progs = new Map();   // code → { name, n }
            matchRows({ program: false, project: false }).forEach(r => {
                if (!r.program_code) return;
                const p = progs.get(r.program_code) || { name: r.program_name || '', n: 0 };
                p.n++;
                progs.set(r.program_code, p);
            });
            const needle = q.toLowerCase();
            return [...progs.keys()].sort()
                .filter(code => !needle || `${code} ${progs.get(code).name}`.toLowerCase().includes(needle))
                .map(code => {
                    const label = code + (progs.get(code).name ? ' — ' + progs.get(code).name : '');
                    return { code, label, main: label, sub: plural(progs.get(code).n, 'budget request') };
                });
        }

        // Projects only — just the picked program's projects when a program is set.
        function projectItems(q) {
            const projs = new Map();   // code → { name, program, programName, n }
            matchRows({ project: false }).forEach(r => {
                if (!r.project_code) return;
                const p = projs.get(r.project_code)
                    || { name: r.project_name || '', program: r.program_code, programName: r.program_name || '', n: 0 };
                p.n++;
                projs.set(r.project_code, p);
            });
            const needle = q.toLowerCase();
            return [...projs.entries()]
                .sort(([ac, a], [bc, b]) => String(a.program).localeCompare(String(b.program)) || ac.localeCompare(bc))
                .filter(([code, p]) => !needle || `${code} ${p.name} ${p.program}`.toLowerCase().includes(needle))
                .map(([code, p]) => {
                    const label = code + (p.name ? ' — ' + p.name : '');
                    return {
                        code, label, main: label, program: p.program,
                        // with a program already picked the parent is obvious; otherwise show it
                        sub: (P.program ? '' : `Under ${p.program}${p.programName ? ' — ' + p.programName : ''} · `) + plural(p.n, 'budget request'),
                    };
                });
        }

        const reqItems = q => matchRows({ q }).map(rowItem);

        /* ── state → UI ── */
        function refresh() {
            if (S.loaded) pe.hint.textContent = `Showing ${matchRows().length} of ${baseRows().length} budget requests`;
            pe.reset.hidden = !(P.fy || P.program || P.project);   // "Clear filters" shows only while a filter is on
        }

        // Set (or clear) the picked budget request.
        function select(row, opts = {}) {
            const ex = exclude();
            if (row && ex && String(row.id) === String(ex.id)) row = null;   // never the same COB as the other picker
            P.selected = row || null;
            pe.id.value = row ? String(row.id) : '';
            pe.reqIn.value = row ? inputLabel(row) : '';
            pe.reqIn.title = row ? shortLabel(row) : '';
            pe.reqClear.hidden = !row;
            // native validation message when submitting without a pick
            if (required) pe.reqIn.setCustomValidity(row ? '' : requiredMsg);
            renderCard(P.selected, pe.summary, pe.sumType, pe.sumTitle, pe.sumSub, pe.sumAmount);
            onSelect(P.selected, opts);
        }

        /* ── wire the dropdowns ── */
        // A typed value is only a search; the applied filter changes on pick/clear,
        // and leaving the field restores the applied value.
        // The compact filter inputs show just the code (label); the full "code — name" is their tooltip.
        const showProgram = () => { pe.prIn.value = P.program ? P.program.label : ''; pe.prIn.title = P.program ? P.program.full : ''; };
        const showProject = () => { pe.pjIn.value = P.project ? P.project.label : ''; pe.pjIn.title = P.project ? P.project.full : ''; };
        const prQuery = () => (P.program && pe.prIn.value === P.program.label ? '' : pe.prIn.value.trim());
        const pjQuery = () => (P.project && pe.pjIn.value === P.project.label ? '' : pe.pjIn.value.trim());

        const fyCombo = combo({
            input: pe.fyIn, list: pe.fyList, clear: pe.fyClear, items: fyItems,
            query: () => (pe.fyIn.value === P.fy ? '' : pe.fyIn.value.trim()),
            onPick: it => { P.fy = it.key; pe.fyIn.value = it.key; refresh(); },
            // typing an exact year applies it straight away
            onType: v => { const y = v.trim(); P.fy = baseRows().some(r => String(r.fiscal_yr) === y) ? y : ''; refresh(); },
            onClear: () => { P.fy = ''; pe.fyIn.value = ''; refresh(); },
            onBlur: () => { pe.fyIn.value = P.fy; },
        });

        const clearProject = () => { P.project = null; showProject(); pjCombo.syncClear(); };

        const prCombo = combo({
            input: pe.prIn, list: pe.prList, clear: pe.prClear, items: programItems, query: prQuery,
            onPick: it => {
                P.program = { code: it.code, label: it.code, full: it.label };
                showProgram();
                // a project only makes sense under its own program
                if (P.project && P.project.program !== it.code) clearProject();
                refresh();
            },
            onClear: () => { P.program = null; showProgram(); clearProject(); refresh(); },
            onBlur: () => showProgram(),
        });

        const pjCombo = combo({
            input: pe.pjIn, list: pe.pjList, clear: pe.pjClear, items: projectItems, query: pjQuery,
            empty: () => (P.program && !pjQuery() ? 'No projects under this program' : 'No matches'),
            onPick: it => {
                P.project = { code: it.code, label: it.code, full: it.label, program: it.program };
                showProject();
                // picking a project fills in the program it belongs to
                if (!P.program || P.program.code !== it.program) {
                    P.program = { code: it.program, label: it.program, full: programLabel(it.program) };
                    showProgram();
                    prCombo.syncClear();
                }
                refresh();
            },
            onClear: () => { P.project = null; showProject(); refresh(); },
            onBlur: () => showProject(),
        });

        const reqCombo = combo({
            input: pe.reqIn, list: pe.reqList, clear: pe.reqClear, items: reqItems,
            query: () => (P.selected && pe.reqIn.value === inputLabel(P.selected) ? '' : pe.reqIn.value.trim()),
            onPick: it => select(it.row, { autofill: true }),
            onClear: () => { onClear(); select(null); },
            onBlur: () => { pe.reqIn.value = P.selected ? inputLabel(P.selected) : ''; },
        });

        // Drop every filter. The picked budget request, if any, is left alone.
        function clearFilters() {
            P.fy = ''; P.program = null; P.project = null;
            pe.fyIn.value = ''; showProgram(); showProject();
            [fyCombo, prCombo, pjCombo].forEach(c => { c.close(); c.syncClear(); });
            refresh();
        }
        pe.reset.addEventListener('click', clearFilters);

        // filters + pick back to empty
        function reset() {
            clearFilters();
            reqCombo.close();
            select(null);
        }

        return { select, selected: () => P.selected, refresh, reset };
    }

    /* ── the two pickers ───────────────────────────────────────────────── */
    let fromPicker, toPicker;   // each one's callbacks look at the other

    fromPicker = makePicker(FROM_IDS, {
        required: true,
        requiredMsg: 'Please select a From COB from the list.',
        onSelect: (row, { autofill = false } = {}) => {
            // funds can't move from a COB to itself, so picking the To COB as the From COB drops the To COB
            const to = toPicker && toPicker.selected();
            if (row && to && String(to.id) === String(row.id)) toPicker.select(null);
            // autofill: true only when the USER picks a request. Preselecting one for Edit
            // must leave the saved From Amount alone.
            if (row && autofill) el.from.value = Number(row.amount || 0).toFixed(2);   // From Amount (disabled) = its current amount
            if (toPicker) toPicker.refresh();   // the To list no longer offers the From COB
            updateChange();
        },
        // From Amount follows the From COB, so clearing the COB clears it too
        onClear: () => { el.from.value = ''; },
    });
    toPicker = makePicker(TO_IDS, {
        exclude: () => fromPicker && fromPicker.selected(),
        onSelect: () => updateChange(),
    });

    function updateChange() {
        const f = el.from.value, t = el.to.value;
        const from = fromPicker.selected(), to = toPicker.selected();
        el.to.setCustomValidity('');   // set again below when a To COB makes the amounts invalid
        if (f === '' || t === '' || isNaN(f) || isNaN(t)) { el.change.hidden = true; return; }
        const d = Math.round((Number(t) - Number(f)) * 100) / 100;
        el.change.hidden = false;

        if (to) {
            // With a To COB the revision moves funds: the From COB must go DOWN, the To COB gets the difference.
            if (d < 0) {
                el.change.className = 'brv-change is-move';
                el.change.textContent = `${peso(-d)} moves from ${from ? codeOf(from) : 'the From COB'} to ${codeOf(to)}`;
            } else {
                const msg = 'To Amount must be lower than From Amount to move funds to the To COB.';
                el.change.className = 'brv-change is-down';
                el.change.textContent = msg;
                el.to.setCustomValidity(msg);
            }
            return;
        }

        el.change.className = 'brv-change' + (d > 0 ? ' is-up' : d < 0 ? ' is-down' : '');
        el.change.textContent = d === 0 ? 'No change' : `${d > 0 ? 'Increase' : 'Decrease'} of ${peso(Math.abs(d))}`;
    }
    el.to.addEventListener('input', updateChange);

    function resetUI() {
        fromPicker.reset();
        toPicker.reset();
        updateChange();
    }
    // the host page calls form.reset() when closing the modal
    el.form.addEventListener('reset', () => setTimeout(resetUI, 0));
    fromPicker.select(null);
    toPicker.select(null);

    /* ── load data ─────────────────────────────────────────────────────── */
    // Fetch /budget_req/all into S.rows; resolves to the [{ value, label }] list the host page uses.
    async function loadRows() {
        const res = await fetch(API, { credentials: 'same-origin' });
        if (!res.ok) throw new Error('HTTP ' + res.status);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load budget requests.');

        // newest year first; within a year each program, then the projects under it
        S.rows = (json.data ?? []).slice().sort((a, b) =>
            (b.fiscal_yr - a.fiscal_yr)
            || String(a.program_code || '').localeCompare(String(b.program_code || ''))
            || ((a.project_code ? 1 : 0) - (b.project_code ? 1 : 0))
            || String(a.project_code || '').localeCompare(String(b.project_code || '')));
        S.loaded = true;
        fromPicker.refresh();
        toPicker.refresh();
        return S.rows.map(r => ({ value: String(r.id), label: fullLabel(r) }));
    }

    const ready = (async function () {
        try {
            return await loadRows();
        } catch (e) {
            S.loaded = true;
            [FROM_IDS.hint, TO_IDS.hint].forEach(id => { $(id).textContent = 'Could not load budget requests.'; });
            if (typeof window.brv_toast === 'function') window.brv_toast(e.message, 'error');
            else console.error(e);
            return [];
        }
    })();

    window.brvBudgetReqsReady = ready;
    window.brvForm = {
        ready,
        // Preselect the From COB and the To COB (the latter may be omitted / '' / null for none).
        // The host page has already put the saved From/To Amount in the inputs, so refresh the chip too.
        setBudgetReq: (id, toId) => ready.then(() => {
            const find = v => (v === '' || v == null ? null : (S.rows.find(r => String(r.id) === String(v)) || null));
            fromPicker.select(find(id));
            toPicker.select(find(toId));
            updateChange();
        }),
        // Re-fetch the budget requests — their amounts change when a revision is approved.
        // Keeps the current From / To pick (with its fresh amount) but never touches From Amount,
        // which only follows a user pick. Resolves to the new [{ value, label }] list, or null on failure.
        reload: async () => {
            try {
                const items = await loadRows();
                const fresh = row => (row ? S.rows.find(r => String(r.id) === String(row.id)) || null : null);
                if (fromPicker.selected()) fromPicker.select(fresh(fromPicker.selected()));
                if (toPicker.selected()) toPicker.select(fresh(toPicker.selected()));
                return items;
            } catch (e) {
                console.error(e);
                return null;
            }
        },
    };
})();
</script>

</div><!-- /.nha-root (form wrapper) -->
