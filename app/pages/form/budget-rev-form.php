<?php
namespace PHPMaker2024\FIMS;
$_brv_form_api = Config('API.FIMS_URL');
?>
<style>
/* ── Budget revision form ──────────────────────────────────────────────────
   Scoped to .nha-root so it picks up the nha-fims.css tokens. It does not rely on Bootstrap's form classes: every control is
   styled here, so the form looks the same wherever it is included. */

/* a wide modal for the two budget-line cards and the arrow between them */
.nha-root #brv-overlay .nha-modal.brv-modal-wide { max-width: 900px; }

/* The modal is a flex column (header · scrolling body · footer) capped at 90vh, but this form's body and footer sit INSIDE a <form>.
   The form has to be the flex item that passes the height on; left as a plain block it just grows past the modal, which clips it —
   the body would not scroll and the footer (Cancel / Submit Request) would be pushed out of sight. */
.nha-root #brv-overlay .nha-modal > form { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
.nha-root #brv-overlay .nha-modal-body { min-height: 0; }
.nha-root #brv-overlay .nha-modal-foot { flex-shrink: 0; }

.nha-root .brv-intro { margin: 0 0 18px; font-size: 13px; line-height: 1.5; color: var(--sec, #62657E); }

/* ── numbered steps ── */
.nha-root .brv-step + .brv-step { margin-top: 24px; padding-top: 22px; border-top: 1px solid var(--bdr, #E4ECF2); }
.nha-root .brv-step-hd {
    display: flex; align-items: center; flex-wrap: wrap; gap: 4px 10px; margin: 0 0 14px;
    font-size: 14px; font-weight: 700; color: var(--afims-heading, #000E3E);
}
.nha-root .brv-step-n {
    display: inline-flex; align-items: center; justify-content: center; flex-shrink: 0; width: 22px; height: 22px; border-radius: 50%;
    font-size: 12px; font-weight: 700; color: var(--afims-pure-white, #FFFFFF); background: var(--afims-heading-fill, #000E3E);
}
.nha-root .brv-step-sub { font-size: 12px; font-weight: 400; color: var(--subtle, #62657E); }

/* ── labels, inputs ── */
.nha-root .brv-lbl {
    display: block; margin: 0 0 5px; font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase;
    color: var(--sec, #62657E);
}
.nha-root .brv-req { color: var(--red, #DC3545); }
.nha-root .brv-optional { font-weight: 600; letter-spacing: 0; text-transform: none; color: var(--subtle, #62657E); }
.nha-root .brv-in {
    display: block; box-sizing: border-box; width: 100%; padding: 8px 12px; font: inherit; font-size: 13px; color: var(--txt, #1A1B21);
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr, #E4ECF2); border-radius: 8px;
    transition: border-color .15s, box-shadow .15s;
}
/* the theme gives every text input min-width:250px (FIMS.css, >= 576px) — it would overflow the compact filter bar */
.nha-root .brv-in[type="text"] { min-width: 0 !important; }
.nha-root .brv-in::placeholder { color: var(--afims-text-disabled, #A9AAB8); }
.nha-root .brv-in:focus { outline: none; border-color: var(--afims-heading-fill, #000E3E); box-shadow: 0 0 0 3px rgba(15, 32, 69, .1); }
.nha-root .brv-in:disabled { color: var(--subtle, #62657E); background: var(--surf, #F4F7FA); cursor: not-allowed; }
.nha-root textarea.brv-in { min-height: 84px; resize: vertical; line-height: 1.45; }

/* ── step 1: From budget line → To budget line ──
   Three columns (card · arrow · card). Where the browser supports subgrid the cards share their three rows (label, box, summary), so
   the two boxes are the same height and the arrow sits level with them; elsewhere the arrow is centred on the whole row. */
.nha-root .brv-cobrow { display: grid; grid-template-columns: minmax(0, 1fr); gap: 14px; align-items: start; }
.nha-root .brv-cobcol { min-width: 0; }
.nha-root .brv-flow {
    display: flex; align-items: center; justify-content: center; flex-shrink: 0; width: 30px; height: 30px; border-radius: 50%; justify-self: center; align-self: center;
    color: var(--sec, #62657E); background: var(--surf, #F4F7FA); border: 1px solid var(--bdr, #E4ECF2);
}
.nha-root .brv-flow svg { width: 15px; height: 15px; }
@media (max-width: 767px) { .nha-root .brv-cobrow > .brv-flow svg { transform: rotate(90deg); } }
@media (min-width: 768px) {
    .nha-root .brv-cobrow { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); column-gap: 12px; row-gap: 0; }
    /* explicit columns: the arrow is pinned to a row below, and an item with a fixed row but no column would be auto-placed first */
    .nha-root .brv-cobrow > :nth-child(1) { grid-column: 1; }
    .nha-root .brv-cobrow > :nth-child(2) { grid-column: 2; }
    .nha-root .brv-cobrow > :nth-child(3) { grid-column: 3; }
}
@media (min-width: 768px) {
    @supports (grid-template-rows: subgrid) {
        .nha-root .brv-cobrow { grid-template-rows: auto auto auto; }
        .nha-root .brv-cobcol { display: grid; grid-row: span 3; grid-template-rows: subgrid; }
        .nha-root .brv-cobrow > .brv-flow { grid-row: 2; }
    }
}

/* a card's title row: the label + what the card is for */
.nha-root .brv-cob-hd { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; margin-bottom: 6px; }
.nha-root .brv-cob-hd .brv-lbl { margin: 0; }
.nha-root .brv-cob-role { font-size: 11.5px; color: var(--subtle, #62657E); text-align: right; }

/* the box: the filter bar on top, the search field below. No overflow:hidden — the dropdown lists open outside it. */
.nha-root .brv-reqgroup { display: flex; flex-direction: column; background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr, #E4ECF2); border-radius: 10px; }
.nha-root .brv-filterbar {
    position: relative;   /* the three filter lists anchor to the bar, not to their narrow inputs */
    display: flex; flex-wrap: wrap; align-items: center; gap: 8px;
    padding: 8px 10px; background: var(--surf, #F4F7FA);
    border-bottom: 1px solid var(--bdr, #E4ECF2);
    border-radius: 9px 9px 0 0;
}
.nha-root .brv-filterbar-label {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;
    color: var(--sec, #62657E);
}
.nha-root .brv-filterbar .brv-combo { position: static; flex: 1 1 90px; min-width: 0; }
.nha-root .brv-combo-field { position: relative; }   /* holds the input + its clear (×) button */
.nha-root .brv-filterbar .brv-combo-fy { flex: 0 1 84px; }
.nha-root .brv-filterbar .brv-in { height: 30px; padding: 3px 26px 3px 10px; font-size: 12.5px; }
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
.nha-root .brv-combo .brv-in { padding-right: 34px; }
.nha-root .brv-combo-clear {
    position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
    border: 0; background: transparent; color: var(--muted, #A9AAB8);
    font-size: 18px; line-height: 1; padding: 2px 6px; cursor: pointer;
}
.nha-root .brv-combo-clear:hover { color: var(--txt, #1A1B21); }
.nha-root .brv-combo-list {
    position: absolute; z-index: 20; top: calc(100% + 4px); left: 0; right: 0;
    max-height: 240px; overflow-y: auto; margin: 0; padding: 4px; list-style: none;
    background: var(--afims-surface, #FFFFFF); border: 1px solid var(--bdr, #E4ECF2);
    border-radius: 10px; box-shadow: 0 8px 24px rgba(0, 0, 0, .12);
}
.nha-root .brv-combo-clear[hidden],
.nha-root .brv-combo-list[hidden],
.nha-root .brv-summary[hidden],
.nha-root .brv-change[hidden] { display: none; }
.nha-root .brv-opt {
    display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 1px 8px; align-items: center;
    padding: 8px 10px; border-radius: 7px; cursor: pointer;
    font-size: 13px; color: var(--txt, #1A1B21);
}
.nha-root .brv-opt:hover,
.nha-root .brv-opt.is-active { background: var(--navy-lt, #F4F7FA); }
.nha-root .brv-opt-main { font-weight: 600; overflow-wrap: anywhere; }
.nha-root .brv-opt-sub { grid-column: 1 / -1; font-size: 11.5px; color: var(--subtle, #62657E); }
.nha-root .brv-opt-empty { padding: 10px; font-size: 12.5px; text-align: center; color: var(--subtle, #62657E); }
.nha-root .brv-hint { margin-top: 4px; font-size: 11.5px; color: var(--subtle, #62657E); }
/* "Showing n of N" on the left, the Clear filters link on the right — it never changes the box height */
.nha-root .brv-hintrow { display: flex; align-items: baseline; justify-content: space-between; gap: 8px; min-height: 22px; }

/* the picked budget request */
.nha-root .brv-summary {
    margin-top: 10px; padding: 11px 14px; font-size: 12.5px;
    background: var(--blue-bg, #E6EBF6); border: 1px solid var(--navy-bdr, #E6EBF6); border-left: 3px solid var(--afims-heading-fill, #000E3E);
    border-radius: 10px;
}
.nha-root .brv-summary-top { display: flex; align-items: center; gap: 8px; font-size: 13px; }
.nha-root .brv-summary-sub { margin-top: 3px; color: var(--subtle, #62657E); white-space: pre-line; overflow-wrap: anywhere; line-height: 1.45; }
.nha-root .brv-summary-amt { margin-top: 7px; }

/* ── step 2: the revision ── */
.nha-root .brv-rev { display: grid; grid-template-columns: minmax(0, 1fr); gap: 12px; align-items: start; }
@media (min-width: 640px) {
    .nha-root .brv-rev { grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); }
    /* the arrow sits level with the two inputs (below their labels) */
    .nha-root .brv-rev > .brv-flow { margin-top: 24px; align-self: start; }
}
@media (max-width: 639px) { .nha-root .brv-rev > .brv-flow svg { transform: rotate(90deg); } }   /* stacked: the arrow points down */
.nha-root .brv-fld { min-width: 0; }
.nha-root .brv-money { position: relative; }
.nha-root .brv-money-sym { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; color: var(--sec, #62657E); pointer-events: none; }
.nha-root .brv-money .brv-in { padding-left: 28px; text-align: right; font-size: 15px; font-weight: 700; font-variant-numeric: tabular-nums; -moz-appearance: textfield; }
.nha-root .brv-money .brv-in::-webkit-outer-spin-button,
.nha-root .brv-money .brv-in::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

/* from/to change indicator */
.nha-root .brv-change {
    margin-top: 12px; padding: 9px 12px; border-radius: 8px; font-size: 12.5px; font-weight: 600;
    background: var(--surf, #F4F7FA); color: var(--subtle, #62657E);
}
.nha-root .brv-change.is-up   { background: var(--green-bg, #DBF2E5); color: var(--green-txt, #0C7539); }
.nha-root .brv-change.is-down { background: var(--red-bg, #FAE1E3);   color: var(--red-txt, #8F222D); }
.nha-root .brv-change.is-move { background: var(--blue-bg, #E6EBF6);  color: var(--afims-primary-text, #0035A9); }
.nha-root .brv-reason-fld { margin-top: 16px; }   /* not .brv-reason: the page's table uses that name for its truncated Reason cell */
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
            <button type="button" class="nha-modal-close" id="brv-modal-close" aria-label="Close">&times;</button>
        </div>
        <form id="brv-form">
            <div class="nha-modal-body">
                <input type="hidden" id="brv-mode" value="add">
                <input type="hidden" id="brv-id" value="">
                <input type="hidden" id="brv-line-id" value="">
                <input type="hidden" id="brv-to-line-id" value="">

                <p class="brv-intro">Revise a budget line's amount, or move funds from one budget line to another. A revision only takes effect once it has been approved; then the From line's adjustment goes down and the To line's goes up.</p>

                <!-- ── 1 · the budget lines: From → To ── -->
                <section class="brv-step">
                    <h4 class="brv-step-hd"><span class="brv-step-n">1</span> Budget lines <span class="brv-step-sub">Pick the line to revise and, if funds move, the line that receives them.</span></h4>

                    <div class="brv-cobrow">

                        <!-- From budget line (budget_revision.from_budget_line_item_id): a filter bar (Fiscal Year · Program · Project) attached to the searchable field -->
                        <div class="brv-cobcol">
                            <div class="brv-cob-hd">
                                <label class="brv-lbl" for="brv-req-input">From Budget Line <span class="brv-req">*</span></label>
                                <span class="brv-cob-role">The line being revised</span>
                            </div>

                            <div class="brv-reqgroup">
                                <div class="brv-filterbar" role="group" aria-label="Filter the budget lines">
                                    <span class="brv-filterbar-label">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                        Filter
                                    </span>
                                    <div class="brv-combo brv-combo-fy">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-fy-input" placeholder="Year" aria-label="Filter by fiscal year"
                                                   maxlength="4" inputmode="numeric" autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-fy-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-fy-clear" aria-label="Clear fiscal year" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-fy-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-program-input" placeholder="Program" aria-label="Filter by program"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-program-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-program-clear" aria-label="Clear program" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-program-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-project-input" placeholder="Project" aria-label="Filter by project"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-project-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-project-clear" aria-label="Clear project" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-project-list" role="listbox" hidden></ul>
                                    </div>
                                </div>

                                <div class="brv-reqfield">
                                    <div class="brv-combo">
                                        <input type="text" class="brv-in" id="brv-req-input" placeholder="Search by account, particulars, reference no. or project"
                                               required autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-req-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-req-clear" aria-label="Clear budget line" hidden>&times;</button>
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
                                    <span class="nha-bdg nha-bdg-gray" id="brv-sum-type">Status</span>
                                    <strong id="brv-sum-title"></strong>
                                </div>
                                <div class="brv-summary-sub" id="brv-sum-sub"></div>
                                <div class="brv-summary-amt">
                                    <span>Current amount <strong id="brv-sum-amount"></strong></span>
                                </div>
                            </div>
                        </div>

                        <!-- the funds flow from the From line to the To line -->
                        <div class="brv-flow" aria-hidden="true" title="Funds move from the From budget line to the To budget line">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
                        </div>

                        <!-- To budget line (budget_revision.to_budget_line_item_id): optional — the line that receives the funds the From line gives up.
                             The same box as the From line (the same filter bar on top, the searchable field below). -->
                        <div class="brv-cobcol">
                            <div class="brv-cob-hd">
                                <label class="brv-lbl" for="brv-to-req-input">To Budget Line <span class="brv-optional">(optional)</span></label>
                                <span class="brv-cob-role">Receives the funds</span>
                            </div>

                            <div class="brv-reqgroup">
                                <div class="brv-filterbar" role="group" aria-label="Filter the To budget lines">
                                    <span class="brv-filterbar-label">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                                        Filter
                                    </span>
                                    <div class="brv-combo brv-combo-fy">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-to-fy-input" placeholder="Year" aria-label="Filter the To budget lines by fiscal year"
                                                   maxlength="4" inputmode="numeric" autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-to-fy-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-to-fy-clear" aria-label="Clear fiscal year" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-to-fy-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-to-program-input" placeholder="Program" aria-label="Filter the To budget lines by program"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-to-program-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-to-program-clear" aria-label="Clear program" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-to-program-list" role="listbox" hidden></ul>
                                    </div>
                                    <div class="brv-combo">
                                        <div class="brv-combo-field">
                                            <input type="text" class="brv-in" id="brv-to-project-input" placeholder="Project" aria-label="Filter the To budget lines by project"
                                                   autocomplete="off"
                                                   role="combobox" aria-expanded="false" aria-controls="brv-to-project-list" aria-autocomplete="list">
                                            <button type="button" class="brv-combo-clear" id="brv-to-project-clear" aria-label="Clear project" hidden>&times;</button>
                                        </div>
                                        <ul class="brv-combo-list" id="brv-to-project-list" role="listbox" hidden></ul>
                                    </div>
                                </div>

                                <div class="brv-reqfield">
                                    <div class="brv-combo">
                                        <input type="text" class="brv-in" id="brv-to-req-input" placeholder="Optional — account, particulars, reference no. or project"
                                               autocomplete="off"
                                               role="combobox" aria-expanded="false" aria-controls="brv-to-req-list" aria-autocomplete="list">
                                        <button type="button" class="brv-combo-clear" id="brv-to-req-clear" aria-label="Clear To budget line" hidden>&times;</button>
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
                                    <span class="nha-bdg nha-bdg-gray" id="brv-to-sum-type">Status</span>
                                    <strong id="brv-to-sum-title"></strong>
                                </div>
                                <div class="brv-summary-sub" id="brv-to-sum-sub"></div>
                                <div class="brv-summary-amt">
                                    <span>Current amount <strong id="brv-to-sum-amount"></strong></span>
                                </div>
                            </div>
                        </div>

                    </div><!-- /.brv-cobrow -->
                </section>

                <!-- ── 2 · the revision: amounts and reason ── -->
                <section class="brv-step">
                    <h4 class="brv-step-hd"><span class="brv-step-n">2</span> Revision</h4>

                    <div class="brv-rev">
                        <div class="brv-fld">
                            <label class="brv-lbl" for="brv-from-amount">From Amount <span class="brv-req">*</span></label>
                            <div class="brv-money">
                                <span class="brv-money-sym">&#8369;</span>
                                <input type="number" class="brv-in" id="brv-from-amount" step="0.01" min="0" placeholder="Auto-filled" disabled>
                            </div>
                            <div class="brv-hint">The From budget line's current amount.</div>
                        </div>
                        <div class="brv-flow" aria-hidden="true">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6l6 6-6 6"/></svg>
                        </div>
                        <div class="brv-fld">
                            <label class="brv-lbl" for="brv-to-amount">To Amount <span class="brv-req">*</span></label>
                            <div class="brv-money">
                                <span class="brv-money-sym">&#8369;</span>
                                <input type="number" class="brv-in" id="brv-to-amount" step="0.01" min="0" placeholder="0.00" required>
                            </div>
                            <div class="brv-hint">What the From budget line's amount becomes.</div>
                        </div>
                    </div>

                    <div class="brv-change" id="brv-change" hidden></div>

                    <div class="brv-fld brv-reason-fld">
                        <label class="brv-lbl" for="brv-reason">Reason</label>
                        <textarea class="brv-in" id="brv-reason" rows="3" placeholder="Explain why this budget needs to be revised"></textarea>
                    </div>
                </section>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" id="brv-cancel">Cancel</button>
                <button type="submit" class="nha-btn nha-btn-primary" id="brv-save">Submit Request</button>
            </div>
        </form>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════════════════
   Budget line pickers — data comes from GET /budget_req/all (fetched once, shared).

   A revision changes a BUDGET LINE: one chart-of-accounts line item (fims.budget_line_items) of a budget request. The request
   is a header (reference no. "BP-001", office, fiscal year, status) carrying programs[] > projects[] > line_items[]; the pickers
   flatten that into one list of lines. A line's CURRENT amount is its proposed amount plus its adjustment — what approving a
   revision changes (the From line's adjustment goes down, the To line's goes up). The value a picker holds is the line's id.

   Two pickers share one layout, and each has its OWN filters and selection:
     From budget line — required (budget_revision.from_budget_line_item_id)
     To budget line   — optional (budget_revision.to_budget_line_item_id): the line that receives the funds the From line
                        gives up. Its list never offers the From line.

   Each picker is four searchable dropdowns that work together:
     Fiscal Year  — years that have a budget line
     Program      — programs that have a budget line
     Project      — projects only. Pick a program first and it lists just that
                    program's projects; pick a project first and it fills in
                    its program.
     the field    — the three filters form a compact bar attached to it (with a
                    "Clear" link); the list is narrowed by them and by whatever
                    is typed into the field itself (account, particulars, reference
                    no., office, program, project or status)

   Self-contained: needs nothing from the host page. It exposes:
     window.brvBudgetReqsReady  promise → [{ value, label }] — the budget REQUESTS, value = the reference number (the host page
                                reuses it for its own filter, no second request)
     window.brvForm.setBudgetLine(fromId, toId)   preselect the From / To line by id (used by Edit); does NOT touch From Amount,
                                so a saved value survives

   From Amount is disabled: it always follows the picked From line. Picking a
   From line fills it with that line's current amount; clearing the line
   clears it.

   With a To line the revision MOVES funds, so To Amount must be lower than From Amount:
   the From line goes down to To Amount and the To line goes up by the difference.
══════════════════════════════════════════════════════════════════════════ */
(function () {
    const $ = id => document.getElementById(id);
    const API = '<?php echo $_brv_form_api; ?>' + '/budget_req/all';
    const peso = v => '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const signedPeso = v => (v < 0 ? '−' : '+') + peso(Math.abs(v));
    const LIST_MAX = 100;   // most lines listed at once; typing narrows the rest down

    // reqs: the budget requests · lines: every budget line of them, flattened (both pickers read these) · loaded: the first fetch has finished
    const S = { reqs: [], lines: [], loaded: false };

    // One line, as the pickers use it: its id, the request it belongs to (reference no., fiscal year, office, status), its program and
    // project, its account, its particulars and its amounts. amount = proposed + adjustment, the line's CURRENT amount.
    function toLines(requests) {
        const out = [];
        requests.forEach(h => (Array.isArray(h.programs) ? h.programs : []).forEach(pg =>
            (Array.isArray(pg.projects) ? pg.projects : []).forEach(pj =>
                (Array.isArray(pj.line_items) ? pj.line_items : []).forEach(li => {
                    const proposed = Number(li.proposed_amount || 0), adjustment = Number(li.adjustment || 0);
                    out.push({
                        id: li.budget_line_item_id, ref: String(h.budget_reference_number), fy: h.fiscal_yr,
                        office: h.rc_name || h.rc_code || '', parentOffice: h.rc_parent_name || '', status: h.status_name, statusColor: h.status_color,
                        programCode: pg.program_code || '', programName: pg.program_name || '', projectCode: pj.project_code || '', projectName: pj.project_name || '',
                        accountId: li.account_id || '', accountCode: li.account_code || '', accountTitle: li.account_title || '', particulars: li.particulars || '',
                        proposed, adjustment, amount: proposed + adjustment,
                    });
                }))));
        return out;
    }

    const keyOf      = l => String(l.id);
    const amountOf   = l => l.amount;
    const withName   = (code, name) => (code ? code + (name ? ' — ' + name : '') : '');
    // "5-02-010 — Travelling Expenses"; a line with no account known shows its id
    const titleOf    = l => withName(l.accountCode, l.accountTitle) || l.accountTitle || `Budget line #${l.id}`;
    // "5-02-010 (BP-001)": short enough for a sentence
    const nameOf     = l => (l.accountCode || `line #${l.id}`) + ` (${l.ref})`;
    const placeOf    = l => [l.programCode, l.projectCode].filter(Boolean).join(' / ') || 'No project yet';
    const shortLabel = l => `FY ${l.fy} — ${l.ref} — ${titleOf(l)}`;
    // What a picker's input shows once a line is picked: its account. The full shortLabel is the input's tooltip and the summary
    // card shows the rest.
    const inputLabel = l => titleOf(l);
    const plural     = (n, word) => `${n} ${word}${n === 1 ? '' : 's'}`;
    const programLabel = code => {
        const l = S.lines.find(x => x.programCode === code);
        return l ? withName(code, l.programName) : code;
    };
    const searchText = l => [l.ref, l.fy, l.office, l.parentOffice, l.status, l.programCode, l.programName, l.projectCode, l.projectName,
        l.accountId, l.accountCode, l.accountTitle, l.particulars].filter(v => v !== null && v !== undefined && v !== '').join(' ').toLowerCase();
    const rowItem = l => ({
        row: l,
        main: titleOf(l),
        sub: `${l.ref} · FY ${l.fy} · ${placeOf(l)} · ${peso(amountOf(l))}`,
        status: { name: l.status, color: l.statusColor },
    });
    // A budget request, as the host page's Budget Request filter lists it
    const reqLabel = h => `FY ${h.fiscal_yr} — ${h.budget_reference_number}` + ((h.rc_name || h.rc_code) ? ' — ' + (h.rc_name || h.rc_code) : '');

    // A status pill in the status's own colour (settings_status.color) — the same look as the Budget Requests list. Only a plain
    // #RRGGBB colour is put into a style; anything else (or none) falls back to a grey pill.
    const HEX_COLOR = /^#[0-9A-Fa-f]{6}$/;
    function paintStatus(el, name, color) {
        el.textContent = name || '—';
        if (name && HEX_COLOR.test(color || '')) {
            el.className = 'nha-bdg';
            el.style.cssText = `color:${color};background:${color}1A;border:1px solid ${color}40;`;
        } else {
            el.className = 'nha-bdg nha-bdg-gray';
            el.style.cssText = '';
        }
        return el;
    }

    // the form-level fields (not part of either picker)
    const el = { form: $('brv-form'), from: $('brv-from-amount'), to: $('brv-to-amount'), change: $('brv-change') };

    // element ids of each picker (the From picker keeps the original ids)
    const FROM_IDS = {
        id: 'brv-line-id',
        fyIn: 'brv-fy-input', fyClear: 'brv-fy-clear', fyList: 'brv-fy-list',
        prIn: 'brv-program-input', prClear: 'brv-program-clear', prList: 'brv-program-list',
        pjIn: 'brv-project-input', pjClear: 'brv-project-clear', pjList: 'brv-project-list',
        reqIn: 'brv-req-input', reqClear: 'brv-req-clear', reqList: 'brv-req-list',
        hint: 'brv-req-hint', reset: 'brv-filter-reset',
        summary: 'brv-req-summary', sumType: 'brv-sum-type', sumTitle: 'brv-sum-title', sumSub: 'brv-sum-sub', sumAmount: 'brv-sum-amount',
    };
    const TO_IDS = {
        id: 'brv-to-line-id',
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
                if (it.status && it.status.name) li.appendChild(paintStatus(document.createElement('span'), it.status.name, it.status.color));
                if (it.sub) {
                    const s = document.createElement('span');
                    s.className = 'brv-opt-sub';
                    s.textContent = it.sub;
                    li.appendChild(s);
                }
                list.appendChild(li);
            });
            if (shown.more > 0) {   // a long list is cut short: say so
                const li = document.createElement('li');
                li.className = 'brv-opt-empty';
                li.textContent = `Showing the first ${shown.length} of ${shown.length + shown.more} — type to narrow down`;
                list.appendChild(li);
            }
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

    // The card under a picker (From and To share the same layout): the request's status, the line's account, then — one per line —
    // its request / office / fiscal year, its program › project, its particulars and (when revisions changed it) proposed vs adjusted,
    // and its current amount.
    function renderCard(l, box, statusEl, titleEl, subEl, amountEl) {
        box.hidden = !l;
        if (!l) return;
        paintStatus(statusEl, l.status, l.statusColor);
        titleEl.textContent = titleOf(l);
        const place = [withName(l.programCode, l.programName), withName(l.projectCode, l.projectName)].filter(Boolean).join(' › ') || 'No project yet';
        subEl.textContent = [
            `${l.ref}${l.office ? ' · ' + l.office : ''} · FY ${l.fy}`,
            place,
            l.particulars,
            l.adjustment !== 0 ? `Proposed ${peso(l.proposed)} · adjusted ${signedPeso(l.adjustment)}` : '',
        ].filter(Boolean).join('\n');
        amountEl.textContent = peso(amountOf(l));
    }

    /* ── one picker: filter bar + searchable field + summary card ──────────
       ids       the element ids (FROM_IDS / TO_IDS)
       exclude   () => a line the list must not offer (the To picker excludes the From line)
       required  the field is mandatory — native validation until a line is picked
       onSelect  (line, { autofill }) after the selection changed
       onClear   the user pressed × on the field (runs just before the selection is cleared) */
    function makePicker(ids, { exclude = () => null, required = false, requiredMsg = '', onSelect = () => {}, onClear = () => {} } = {}) {
        const pe = {};
        Object.keys(ids).forEach(k => { pe[k] = $(ids[k]); });

        // fy: '' or '2026' · program: null | { code, label, full } · project: null | { code, label, full, program }
        // selected: the picked budget line or null
        const P = { fy: '', program: null, project: null, selected: null };

        // every line this picker may offer
        const baseRows = () => {
            const ex = exclude();
            return ex ? S.lines.filter(l => keyOf(l) !== keyOf(ex)) : S.lines;
        };

        /* ── filtering ── */
        // Lines that satisfy the active filters. Each dropdown switches off the
        // filter(s) it feeds, so its suggestions only react to the OTHER filters.
        function matchRows({ fy = true, program = true, project = true, q = '' } = {}) {
            const needle = q.toLowerCase();
            return baseRows().filter(l => {
                if (fy && P.fy && String(l.fy) !== P.fy) return false;
                if (program && P.program && l.programCode !== P.program.code) return false;
                if (project && P.project && l.projectCode !== P.project.code) return false;
                if (!needle) return true;
                return searchText(l).includes(needle);
            });
        }

        function fyItems(q) {
            const counts = new Map();
            matchRows({ fy: false }).forEach(l => {
                const y = String(l.fy);
                counts.set(y, (counts.get(y) || 0) + 1);
            });
            return [...counts.keys()].sort((a, b) => b - a).filter(y => y.includes(q))
                .map(y => ({ key: y, main: y, sub: plural(counts.get(y), 'budget line') }));
        }

        // The programs that have a budget line.
        function programItems(q) {
            const progs = new Map();   // code → { name, n }
            matchRows({ program: false, project: false }).forEach(l => {
                if (!l.programCode) return;
                const p = progs.get(l.programCode) || { name: l.programName, n: 0 };
                p.n++;
                progs.set(l.programCode, p);
            });
            const needle = q.toLowerCase();
            return [...progs.keys()].sort()
                .filter(code => !needle || `${code} ${progs.get(code).name}`.toLowerCase().includes(needle))
                .map(code => {
                    const label = withName(code, progs.get(code).name);
                    return { code, label, main: label, sub: plural(progs.get(code).n, 'budget line') };
                });
        }

        // Projects only — just the picked program's projects when a program is set.
        function projectItems(q) {
            const projs = new Map();   // code → { name, program, programName, n }
            matchRows({ project: false }).forEach(l => {
                if (!l.projectCode) return;
                const p = projs.get(l.projectCode) || { name: l.projectName, program: l.programCode, programName: l.programName, n: 0 };
                p.n++;
                projs.set(l.projectCode, p);
            });
            const needle = q.toLowerCase();
            return [...projs.entries()]
                .sort(([ac, a], [bc, b]) => String(a.program).localeCompare(String(b.program)) || ac.localeCompare(bc))
                .filter(([code, p]) => !needle || `${code} ${p.name} ${p.program}`.toLowerCase().includes(needle))
                .map(([code, p]) => {
                    const label = withName(code, p.name);
                    return {
                        code, label, main: label, program: p.program,
                        // with a program already picked the parent is obvious; otherwise show it
                        sub: (P.program ? '' : `Under ${withName(p.program, p.programName)} · `) + plural(p.n, 'budget line'),
                    };
                });
        }

        const reqItems = q => {
            const all = matchRows({ q });
            const items = all.slice(0, LIST_MAX).map(rowItem);
            items.more = all.length - items.length;
            return items;
        };

        /* ── state → UI ── */
        function refresh() {
            if (S.loaded) pe.hint.textContent = `Showing ${matchRows().length} of ${baseRows().length} budget lines`;
            pe.reset.hidden = !(P.fy || P.program || P.project);   // "Clear filters" shows only while a filter is on
        }

        // Set (or clear) the picked budget line.
        function select(line, opts = {}) {
            const ex = exclude();
            if (line && ex && keyOf(line) === keyOf(ex)) line = null;   // never the same line as the other picker
            P.selected = line || null;
            pe.id.value = line ? keyOf(line) : '';   // the budget line's id
            pe.reqIn.value = line ? inputLabel(line) : '';
            pe.reqIn.title = line ? shortLabel(line) : '';
            pe.reqClear.hidden = !line;
            // native validation message when submitting without a pick
            if (required) pe.reqIn.setCustomValidity(line ? '' : requiredMsg);
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
            onType: v => { const y = v.trim(); P.fy = baseRows().some(l => String(l.fy) === y) ? y : ''; refresh(); },
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

        // Drop every filter. The picked budget line, if any, is left alone.
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
        requiredMsg: 'Please select a From budget line from the list.',
        onSelect: (line, { autofill = false } = {}) => {
            // funds can't move from a line to itself, so picking the To line as the From line drops the To line
            const to = toPicker && toPicker.selected();
            if (line && to && keyOf(to) === keyOf(line)) toPicker.select(null);
            // autofill: true only when the USER picks a line. Preselecting one for Edit
            // must leave the saved From Amount alone.
            if (line && autofill) el.from.value = amountOf(line).toFixed(2);   // From Amount (disabled) = its current amount
            if (toPicker) toPicker.refresh();   // the To list no longer offers the From line
            updateChange();
        },
        // From Amount follows the From line, so clearing the line clears it too
        onClear: () => { el.from.value = ''; },
    });
    toPicker = makePicker(TO_IDS, {
        exclude: () => fromPicker && fromPicker.selected(),
        onSelect: () => updateChange(),
    });

    function updateChange() {
        const f = el.from.value, t = el.to.value;
        const from = fromPicker.selected(), to = toPicker.selected();
        el.to.setCustomValidity('');   // set again below when a To line makes the amounts invalid
        if (f === '' || t === '' || isNaN(f) || isNaN(t)) { el.change.hidden = true; return; }
        const d = Math.round((Number(t) - Number(f)) * 100) / 100;
        el.change.hidden = false;

        if (to) {
            // With a To line the revision moves funds: the From line must go DOWN, the To line gets the difference.
            if (d < 0) {
                el.change.className = 'brv-change is-move';
                el.change.textContent = `${peso(-d)} moves from ${from ? nameOf(from) : 'the From budget line'} to ${nameOf(to)}`;
            } else {
                const msg = 'To Amount must be lower than From Amount to move funds to the To budget line.';
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
    // Fetch /budget_req/all into S.reqs + S.lines; resolves to the [{ value, label }] list of budget requests the host page uses.
    async function loadRows() {
        const res = await fetch(API, { credentials: 'same-origin' });
        // Read the body even on an error status, so the server's own message is shown rather than a bare "HTTP 4xx".
        const json = await res.json().catch(() => null);
        if (!res.ok || !json || !json.success) throw new Error((json && json.message) || (res.ok ? 'Failed to load budget requests.' : 'HTTP ' + res.status));

        // newest year first; within a year by office, then by reference number (BP-2 before BP-10)
        const officeOf = h => h.rc_name || h.rc_code || '';
        S.reqs = (json.data ?? []).slice().sort((a, b) =>
            (b.fiscal_yr - a.fiscal_yr)
            || officeOf(a).localeCompare(officeOf(b))
            || String(a.budget_reference_number).localeCompare(String(b.budget_reference_number), undefined, { numeric: true }));
        // the lines follow that order, then program, project and account
        S.lines = toLines(S.reqs).map((l, i) => ({ l, i })).sort((a, b) =>
            (a.l.ref === b.l.ref ? 0 : a.i - b.i)
            || a.l.programCode.localeCompare(b.l.programCode) || a.l.projectCode.localeCompare(b.l.projectCode)
            || a.l.accountCode.localeCompare(b.l.accountCode, undefined, { numeric: true }) || a.i - b.i).map(x => x.l);
        S.loaded = true;
        fromPicker.refresh();
        toPicker.refresh();
        return S.reqs.map(h => ({ value: String(h.budget_reference_number), label: reqLabel(h) }));
    }

    const ready = (async function () {
        try {
            return await loadRows();
        } catch (e) {
            S.loaded = true;
            [FROM_IDS.hint, TO_IDS.hint].forEach(id => { $(id).textContent = 'Could not load budget lines.'; });
            if (typeof window.brv_toast === 'function') window.brv_toast(e.message, 'error');
            else console.error(e);
            return [];
        }
    })();

    window.brvBudgetReqsReady = ready;
    window.brvForm = {
        ready,
        // Preselect the From line and the To line by id (the latter may be omitted / '' / null for none; a line that is no longer on
        // the list — or a revision filed before budget lines were revised, which has none — leaves that picker empty). The host
        // page has already put the saved From/To Amount in the inputs, so refresh the chip too.
        setBudgetLine: (fromId, toId) => ready.then(() => {
            const find = v => (v === '' || v == null ? null : (S.lines.find(l => keyOf(l) === String(v)) || null));
            fromPicker.select(find(fromId));
            toPicker.select(find(toId));
            updateChange();
        }),
        // Re-fetch the budget requests — a line's amount changes when a revision is approved.
        // Keeps the current From / To pick (with its fresh amount) but never touches From Amount,
        // which only follows a user pick. Resolves to the new [{ value, label }] list of requests, or null on failure.
        reload: async () => {
            try {
                const items = await loadRows();
                const fresh = line => (line ? S.lines.find(l => keyOf(l) === keyOf(line)) || null : null);
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
