<?php
/**
 * trust-fund-form.php
 *
 * New / Edit modal for a Trust Fund — a fims.fund_tracking record of the TRUST FUND tracking type (SRS 1.1.2.10.2 —
 * Record fund downloading to NHA; FMD V4 "Trust Fund MOAs Management"). Ported from the FMD app's trust-fund-form.php
 * (a full page there) the same way saro-processing-form.php is hosted: this file is INCLUDED by a list page and renders an
 * overlay modal that stays hidden until it is opened.
 *
 *   Host page (see trust-funds-page.php):
 *     include __DIR__ . '/form/trust-fund-form.php';
 *     window.trustFundModal.open();        // New entry
 *     window.trustFundModal.open(42);      // Edit fims.fund_tracking.id = 42
 *     window.trustFundModal.close();
 *   After a successful save the modal closes and calls window.tfl_reload() when the host defines it.
 *
 * API (app/routes/fund_tracking.php and friends — the user is the logged-in session, never sent):
 *   GET   /fund_tracking/read/{id}      POST /fund_tracking/add      PATCH /fund_tracking/update/{id}
 *   GET   /fund-tracking-type/all  /fiscal_year/all  /sub_program/all  /agency/all  /nature-of-fund/all
 *   POST  /agency/add                   (a new agency, typed into the Agency field — see below)
 *   POST  /upload/file (multipart)  POST /upload/multi-upload     (an OR's Supporting Documents)
 *   POST  /fund_tracking/add also takes `ors` — the OR records added under a NEW trust fund (see "OR Records" below)
 *
 * What changed from the FMD version (a Trust Fund is an ordinary fims.fund_tracking row now — there is no /trust_fund API):
 *   Program (program_id)       → Sub-Program (sub_program_code); its program is shown under it. Optional, as before
 *   Agency (an agency id)      → the agency's NAME, saved as text in agency_name. It is picked from /agency/all — or TYPED: a name
 *                                that is not on the list is added to fims.agency (POST /agency/add) when the trust fund is saved,
 *                                so there is no need to leave this form to create it. It is created at SAVE, not as you type, so
 *                                cancelling the form never leaves a stray agency behind. A name that matches an existing agency in
 *                                any letter case (an inactive one too) uses that agency instead — fims.agency.name is unique
 *   (new) Fiscal Year          → fiscal_year, a plain year picked from fims.fiscal_year. The list page filters by it
 *   Reference No.              → still suggested from the program's initials + the year (e.g. BEDP-2026), but editable now:
 *                                a Trust Fund saved with no sub-program has nothing to suggest it from, and the API
 *                                requires a reference number
 *   Date of SARO               → date;  Remarks → remarks;  Amount → amount
 *   OR No. (hidden link)       → gone. A Trust Fund is the PARENT of its ORs (the OR form picks the Trust Fund), and an
 *                                edit never sends parent_id, so an existing record keeps whatever link it has
 *   (new) OR Records           → while CREATING a trust fund, any number of ORs can be added at the bottom with "Add OR" — the same
 *                                idea as the NCA Releases of the SARO form (saro-processing-form.php). Each is a full OR entry — the
 *                                same fields as the OR form (Sub-Program, Nature of Fund, Fiscal Year, OR No., Name, Amount, Date,
 *                                Unreleased, Bank Account No. and its own Supporting Documents), plus Remarks, which the list page
 *                                shows for an OR. It becomes an OR record with the new trust fund as its parent and takes its agency
 *                                (its Fiscal Year and Sub-Program start as the trust fund's and follow it until changed on the card);
 *                                the API saves the trust fund and all of them in one transaction, and the cards' documents are
 *                                attached afterwards. An existing trust fund's ORs are added with the OR form instead
 *   created_by                 → no longer sent; the API stamps user_id from the session
 */

namespace PHPMaker2024\FIMS;

$apiBase = Config('API.FIMS_URL');
?>

<style>
    /* ── Layout ─────────────────────────────────────────────── */
    .nha-root .tf-modal .nha-modal-body { padding: 18px 22px; }
    .nha-root .tf-sec { margin-bottom: 22px; }
    .nha-root .tf-sec:last-child { margin-bottom: 0; }
    .nha-root .tf-sec-hd { margin: 0 0 12px; padding-bottom: 6px; border-bottom: 1.5px solid var(--afims-border, #E4ECF2);
        font-size: 12px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: var(--afims-heading, #000E3E); }
    .nha-root .tf-hint { display: block; margin-top: 4px; font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); }
    .nha-root .tf-hint strong { color: var(--afims-text-muted, #62657E); font-weight: 600; }
    .nha-root .tf-err { display: none; margin-bottom: 14px; padding: 10px 14px; border-radius: 8px; font-size: 12.5px; line-height: 1.45;
        background: var(--afims-danger-soft, #FAE1E3); border: 1px solid var(--afims-danger-soft, #FAE1E3); color: var(--afims-danger, #DC3545); }

    /* ── Fields ─────────────────────────────────────────────── */
    .nha-root .nha-fg .tf-money { position: relative; }
    .nha-root .nha-fg .tf-money > span { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 13px;
        color: var(--afims-text-muted, #62657E); pointer-events: none; }
    .nha-root .nha-fg .tf-money > input { padding-left: 28px; }
    .nha-root .nha-fg .tf-invalid { border-color: var(--afims-danger, #DC3545); box-shadow: 0 0 0 3px rgba(220, 53, 69, .12); }
    .nha-root .nha-fg .tf-has-value { border-color: var(--afims-heading-fill, #000E3E); }
    .nha-root .nha-fg input:disabled, .nha-root .nha-fg select:disabled { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-disabled, #A9AAB8); cursor: not-allowed; }
    .nha-ss-dropdown mark { background: var(--afims-warning-soft, #FFF8DB); color: inherit; border-radius: 2px; padding: 0 1px; }
    /* the "Add “…” as a new agency" row at the foot of the Agency list (the dropdown lives on <body>, outside .nha-root) */
    .nha-ss-dropdown .tf-opt-new { border-top: 1px solid var(--afims-border, #E4ECF2); }
    .nha-ss-dropdown .tf-opt-new .nha-ss-opt-code { color: var(--afims-success-hover, #0C7539); }
    .nha-root .tf-hint .tf-new-note { color: var(--afims-success-hover, #0C7539); font-weight: 600; }

    /* ── OR records (new trust fund only) ───────────────────── */
    /* the OR block comes after everything that belongs to the trust fund itself, so it is set apart: a green heading with a
       count (an OR is green under its trust fund on the list page too), and every OR is its own card with an accent bar */
    .nha-root .tf-or-sec { margin-top: 30px; }
    .nha-root .tf-or-sec .tf-sec-hd { display: flex; align-items: center; gap: 8px; color: var(--afims-success-hover, #0C7539); border-bottom-color: var(--afims-success, #11A752); }
    .nha-root .tf-count { display: inline-flex; align-items: center; justify-content: center; min-width: 20px; height: 20px; padding: 0 6px; border-radius: 10px;
        background: var(--afims-success-soft, #DBF2E5); color: var(--afims-success-hover, #0C7539); font-size: 11px; font-weight: 700; letter-spacing: 0; }
    /* every OR is a card: a header band — a collapse toggle (chevron + title) that says what the card is while it is open and
       summarises it in one line once collapsed, and the remove button — then the body. The body is laid out by what each field is
       FOR: the receipt itself (OR No., Fiscal Year, Date, Amount) first, then its classification, then the optional details */
    .nha-root .tf-or-row { border: 1px solid var(--afims-border, #E4ECF2); border-left: 3px solid var(--afims-success, #11A752); border-radius: 10px;
        background: var(--afims-surface, #FFFFFF); margin-bottom: 12px; overflow: hidden; }
    .nha-root .tf-or-hd { display: flex; align-items: center; gap: 6px; padding: 4px 8px 4px 4px;
        background: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 32%, var(--afims-surface, #FFFFFF)); }
    .nha-root .tf-or-toggle { flex: 1; min-width: 0; display: flex; align-items: center; gap: 8px; padding: 7px 8px; border: none; border-radius: 7px; background: none;
        cursor: pointer; text-align: left; font: inherit; font-size: 13px; color: var(--afims-heading, #000E3E); transition: background .15s; }
    .nha-root .tf-or-toggle:hover { background: color-mix(in srgb, var(--afims-success-soft, #DBF2E5) 60%, transparent); }
    .nha-root .tf-or-toggle:focus-visible { outline: 2px solid var(--afims-primary, #0035A9); outline-offset: -2px; }
    .nha-root .tf-or-chev { width: 14px; height: 14px; flex-shrink: 0; color: var(--afims-text-muted, #62657E); transition: transform .15s; }
    .nha-root .tf-or-row.collapsed .tf-or-chev { transform: rotate(-90deg); }
    .nha-root .tf-or-title { flex-shrink: 0; font-weight: 700; }
    .nha-root .tf-or-meta, .nha-root .tf-or-sum { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-size: 11.5px; font-weight: 400; color: var(--afims-text-muted, #62657E); }
    .nha-root .tf-or-meta strong { font-weight: 600; color: var(--afims-heading, #000E3E); }
    .nha-root .tf-or-sum { display: none; font-size: 12px; }
    .nha-root .tf-or-row.collapsed .tf-or-meta { display: none; }
    .nha-root .tf-or-row.collapsed .tf-or-sum { display: block; }
    .nha-root .tf-or-sum strong { font-weight: 600; color: var(--afims-heading, #000E3E); }
    .nha-root .tf-or-sum .tf-or-sum-none { font-style: italic; color: var(--afims-text-disabled, #A9AAB8); }
    .nha-root .tf-or-body { padding: 14px 14px 2px; border-top: 1px solid var(--afims-border, #E4ECF2); }
    .nha-root .tf-or-row.collapsed .tf-or-body { display: none; }
    /* one grid for the whole card; minmax(0,…) so a long value can never push a column wider than its share */
    .nha-root .tf-or-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0 12px; }
    .nha-root .tf-or-grid .tf-span-2 { grid-column: span 2; }
    .nha-root .tf-or-grid .tf-span-3 { grid-column: span 3; }
    @media (max-width: 760px) {
        .nha-root .tf-or-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .nha-root .tf-or-grid .tf-span-3 { grid-column: 1 / -1; }
    }
    /* a picked Sub-Program must not look like the field that has focus: inside a card its border stays neutral */
    .nha-root .tf-or-row .nha-fg .tf-has-value { border-color: var(--afims-border-strong, #C5CEDA); }
    .nha-root .tf-or-rm { flex-shrink: 0; background: none; border: 1px solid transparent; border-radius: 6px; padding: 4px 8px; cursor: pointer;
        color: var(--afims-danger, #DC3545); display: flex; align-items: center; transition: background .15s, border-color .15s; }
    .nha-root .tf-or-rm:hover { background: var(--afims-danger-soft, #FAE1E3); border-color: var(--afims-danger-soft, #FAE1E3); }
    .nha-root .tf-or-foot { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
    .nha-root .tf-or-total { font-size: 12px; font-weight: 600; color: var(--afims-text, #1A1B21); }
    .nha-root .tf-or-total.over { color: var(--afims-warning-text, #7E6908); }
    .nha-root .tf-or-grid .tf-span-all { grid-column: 1 / -1; }
    /* the documents: a heading with its "Add Document" button on the same line, and a one-line drop zone — they used to cost about
       130px of height for a card that mostly has none */
    .nha-root .tf-or-docs-wrap { border-top: 1px dashed var(--afims-border, #E4ECF2); margin-top: 2px; padding: 10px 0 12px; }
    .nha-root .tf-or-docs-hd { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 0 0 8px; min-height: 28px;
        font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); }
    /* the card's own heading already says "Supporting Documents" — the row doesn't repeat it, so its remove button needn't clear a label */
    .nha-root .tf-or-docs .tf-doc-label { display: none; }
    .nha-root .tf-or-docs .tf-doc-row { align-items: center; margin-bottom: 8px; }
    .nha-root .tf-or-docs .tf-doc-row:last-child { margin-bottom: 0; }
    .nha-root .tf-or-docs .tf-doc-rm { margin-top: 0; padding: 6px 9px; }
    .nha-root .tf-or-docs .tf-dz { display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 2px 10px; padding: 9px 12px; background: var(--afims-surface-subtle, #F4F7FA); }
    .nha-root .tf-or-docs .tf-dz svg { margin: 0; flex-shrink: 0; }
    .nha-root .tf-or-docs .tf-dz.drag-over { background: var(--afims-primary-soft, #E6EBF6); }

    /* ── Supporting documents (each OR card's own — the same as the SARO form's) ── */
    .nha-root .tf-doc-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; align-items: flex-start; margin-bottom: 12px; }
    .nha-root .tf-doc-label { display: block; margin-bottom: 4px; font-size: 12px; font-weight: 600; color: var(--afims-text, #1A1B21); }
    .nha-root .tf-doc-rm { margin-top: 22px; background: none; border: 1px solid var(--afims-danger-soft, #FAE1E3); border-radius: 6px; padding: 7px 10px;
        cursor: pointer; color: var(--afims-danger, #DC3545); display: flex; align-items: center; justify-content: center; transition: background .15s; }
    .nha-root .tf-doc-rm:hover { background: var(--afims-danger-soft, #FAE1E3); }
    .nha-root .tf-dz { position: relative; border: 2px dashed var(--afims-border, #E4ECF2); border-radius: 8px; background: var(--afims-surface, #FFFFFF);
        padding: 12px; text-align: center; cursor: pointer; transition: border-color .15s, background .15s; }
    .nha-root .tf-dz.drag-over { border-color: var(--afims-primary, #0035A9); background: var(--afims-primary-soft, #E6EBF6); }
    .nha-root .tf-dz.uploading { pointer-events: none; opacity: .6; }
    .nha-root .tf-dz input[type="file"] { position: absolute; inset: 0; opacity: 0; width: 100%; height: 100%; cursor: pointer; padding: 0; border: 0; }
    .nha-root .tf-dz svg { width: 18px; height: 18px; color: var(--afims-text-disabled, #A9AAB8); margin: 0 auto 4px; display: block; }
    .nha-root .tf-dz p { margin: 0; font-size: 12.5px; color: var(--afims-text-muted, #62657E); }
    .nha-root .tf-dz small { font-size: 11px; color: var(--afims-text-disabled, #A9AAB8); }
    .nha-root .tf-chip { display: none; align-items: center; gap: 8px; border: 1px solid var(--afims-border, #E4ECF2); border-radius: 8px;
        padding: 8px 10px; background: var(--afims-surface, #FFFFFF); font-size: 12.5px; }
    .nha-root .tf-chip-name { font-weight: 500; color: var(--afims-heading, #000E3E); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 320px; }
    .nha-root .tf-chip-size { color: var(--afims-text-muted, #62657E); font-size: 11px; white-space: nowrap; }
    .nha-root .tf-chip-x { margin-left: auto; background: none; border: none; color: var(--afims-danger, #DC3545); cursor: pointer; font-size: 12px; }
    .nha-root .tf-doc-note { display: none; margin: 6px 0 0; font-size: 11.5px; color: var(--afims-warning-text, #7E6908); }
    .nha-root .tf-doc-note.error { color: var(--afims-danger, #DC3545); }

    /* ── Info card ──────────────────────────────────────────── */
    .nha-root .tf-info { display: flex; gap: 10px; margin-top: 18px; padding: 12px 14px; border-radius: 8px;
        background: var(--afims-primary-soft, #E6EBF6); font-size: 12px; color: var(--afims-text, #1A1B21); }
    .nha-root .tf-info svg { width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px; color: var(--afims-primary, #0035A9); }
    .nha-root .tf-info p { margin: 0 0 4px; font-weight: 700; }
    .nha-root .tf-info ul { margin: 0; padding-left: 18px; }
</style>

<!-- The wrapper makes the shared .nha-overlay / .nha-modal rules (scoped to .nha-root) apply wherever this
     form is included; padding/background are neutralised so it takes no space. -->
<div class="nha-root" style="padding:0;background:none;">
<div id="tf-overlay" class="nha-overlay no-print">
    <form id="tf-form" class="nha-modal nha-modal-lg tf-modal" novalidate autocomplete="off">
        <div class="nha-modal-hdr">
            <h3 class="nha-modal-title" id="tf-title">Trust Fund — New Entry</h3>
            <button type="button" class="nha-modal-close" id="tf-close" aria-label="Close">&times;</button>
        </div>

        <div class="nha-modal-body" id="tf-body">
            <div id="tf-err" class="tf-err" role="alert"></div>

            <!-- ── Program ──────────────────────────────────────────── -->
            <div class="tf-sec">
                <h4 class="tf-sec-hd">Program</h4>
                <div class="nha-fg" style="margin-bottom:0;">
                    <label for="tf-sub">Sub-Program</label>
                    <input type="text" id="tf-sub" placeholder="Search sub-program code, name or program…">
                    <input type="hidden" id="tf-sub-code" value="">
                    <span class="tf-hint" id="tf-sub-hint">Program associated with this trust fund entry (optional)</span>
                </div>
            </div>

            <!-- ── Agency & Fund Tracking ───────────────────────────── -->
            <div class="tf-sec">
                <h4 class="tf-sec-hd">Agency &amp; Fund Tracking</h4>

                <div class="nha-fg-row">
                    <div class="nha-fg">
                        <label for="tf-ref">Reference No. <span class="nha-req">*</span></label>
                        <input type="text" id="tf-ref" maxlength="225" placeholder="Suggested from the selected program">
                        <span class="tf-hint">Suggested from the program's name and the fiscal year (e.g. BEDP-2026) — change it if this download has its own</span>
                    </div>
                    <div class="nha-fg">
                        <label for="tf-year">Fiscal Year <span class="nha-req">*</span></label>
                        <select id="tf-year"><option value="">Loading…</option></select>
                    </div>
                </div>

                <div class="nha-fg" style="margin-bottom:0;">
                    <label for="tf-agency">Agency / LGU Name <span class="nha-req">*</span></label>
                    <input type="text" id="tf-agency" maxlength="200" placeholder="Search agency, or type a new one…">
                    <input type="hidden" id="tf-agency-name" value="">
                    <span class="tf-hint" id="tf-agency-hint">Select the agency or LGU that downloaded / released the trust fund — or type its name to add it</span>
                </div>
            </div>

            <!-- ── Download Amount & Date ───────────────────────────── -->
            <div class="tf-sec">
                <h4 class="tf-sec-hd">Download Amount &amp; Date</h4>
                <div class="nha-fg-row">
                    <div class="nha-fg" style="margin-bottom:0;">
                        <label for="tf-amount">Trust Fund Amount <span class="nha-req">*</span></label>
                        <div class="tf-money"><span>&#8369;</span><input type="text" inputmode="decimal" id="tf-amount" placeholder="0.00"></div>
                        <span class="tf-hint">Actual cash amount received / downloaded by NHA from the agency</span>
                    </div>
                    <div class="nha-fg" style="margin-bottom:0;">
                        <label for="tf-date">Date of SARO</label>
                        <input type="date" id="tf-date">
                        <span class="tf-hint">Date of the SARO associated with this trust fund download</span>
                    </div>
                </div>
            </div>

            <!-- ── Remarks ──────────────────────────────────────────── -->
            <div class="tf-sec">
                <h4 class="tf-sec-hd">Remarks</h4>
                <div class="nha-fg" style="margin-bottom:0;">
                    <label for="tf-remarks">Remarks / Notes</label>
                    <textarea id="tf-remarks" rows="3" placeholder="Optional free-form notes about this trust fund entry…"></textarea>
                </div>
            </div>

            <!-- ── OR Records (only while creating a trust fund) ───────────────
                 Last on purpose: everything above belongs to the trust fund itself; this block is the child OR records created
                 with it, each a record of its own with this trust fund as its parent. -->
            <div class="tf-sec tf-or-sec" id="tf-or-section" style="display:none;">
                <h4 class="tf-sec-hd">OR Records <span class="tf-count" id="tf-or-count">0</span></h4>
                <span class="tf-hint" style="margin:0 0 12px;">Optional. Each OR below is a separate record with this trust fund as its parent fund, listed under it on the Trust Funds page, and has the same fields as a normal OR. Its Fiscal Year and Sub-Program start as this trust fund's and follow it until you change them on that OR; its Agency is this trust fund's. They are saved together with the trust fund — all or none.</span>
                <div id="tf-ors"></div>
                <span class="tf-hint" id="tf-or-empty" style="margin:0 0 10px;">No OR added — you can add them now or later with the OR form.</span>
                <div class="tf-or-foot">
                    <button type="button" class="nha-btn nha-btn-ghost nha-btn-sm" id="tf-or-add">
                        <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        Add OR
                    </button>
                    <span class="tf-or-total" id="tf-or-total"></span>
                </div>
            </div>

            <div class="tf-info">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <div>
                    <p>Trust Fund Process Reminders</p>
                    <ul>
                        <li>Trust Fund entries record cash downloaded by NHA from an agency or LGU under a MOA</li>
                        <li>Reference No., Agency / LGU, Fiscal Year and Amount are required; Sub-Program, Date and Remarks are optional</li>
                        <li>The Amount is the actual cash received — not the SARO face value</li>
                        <li>Official Receipts (ORs) against this fund can be added at the bottom while creating it — they are saved together with it, linked to it as their parent, and show under it on the Trust Funds page. To add an OR to a trust fund that already exists, use the OR form</li>
                        <li>ORs added here are saved with the trust fund — if any of them is rejected, nothing is saved</li>
                        <li>A trust fund cannot be deleted while ORs are still linked to it</li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="nha-modal-foot">
            <button type="button" class="nha-btn nha-btn-ghost" id="tf-cancel">Cancel</button>
            <button type="submit" class="nha-btn nha-btn-primary" id="tf-save">Save Trust Fund</button>
        </div>
    </form>
</div>
</div><!-- /.nha-root (modal wrapper) -->

<script>
(function () {
    'use strict';

    const API = <?= json_encode($apiBase) ?>;
    const U = {
        read:     id => API + '/fund_tracking/read/' + encodeURIComponent(id),
        add:      API + '/fund_tracking/add',
        update:   id => API + '/fund_tracking/update/' + encodeURIComponent(id),
        types:    API + '/fund-tracking-type/all',
        years:    API + '/fiscal_year/all',
        subs:     API + '/sub_program/all',
        agencies: API + '/agency/all',
        agencyAdd: API + '/agency/add',
        natures:  API + '/nature-of-fund/all',
        upload:   API + '/upload/file',
        multi:    API + '/upload/multi-upload',
    };
    const MAX_BYTES = 25 * 1024 * 1024;

    const $ = id => document.getElementById(id);
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    // Escaped text with the first case-insensitive match of q wrapped in <mark>
    const hl = (text, q) => {
        text = String(text ?? '');
        const i = q ? text.toLowerCase().indexOf(q.toLowerCase()) : -1;
        return i < 0 ? esc(text) : esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
    };
    // The tracking type of a Trust Fund is spelled "TRUST FUND" in the master list ("TRUST" / "TRUST_FUND" in older data)
    const isTrustCode = code => ['TRUST', 'TRUST FUND'].includes(String(code || '').trim().toUpperCase().replace(/[\s_]+/g, ' '));

    const S = {
        editId: null,
        saving: false,
        opening: false,
        loaded: {},          // which lookups have been read
        typeId: null,        // fims.fund_tracking_type id of the TRUST FUND type
        subs: [],            // sub-programs
        natures: [],         // natures of fund [{nature_of_fund_id, name}] — for the OR cards
        agencies: [],        // the agencies offered in the list (active ones) [{name}]
        agencyNames: new Map(),  // EVERY agency name (inactive too), lower-cased → its exact spelling: decides "already exists?"
        origAgency: '',      // the agency text of the record being edited — kept as it is, never re-created
    };

    // An agency name as the user typed it, cleaned up: trimmed, inner runs of spaces collapsed
    const normName = s => String(s ?? '').trim().replace(/\s+/g, ' ');
    const nameKey = s => normName(s).toLowerCase();

    /* ── HTTP ─────────────────────────────────────────────────────
       Resolves with the parsed body on { success: true }; otherwise throws the server's message. */
    async function api(url, opts = {}) {
        const headers = typeof opts.body === 'string' ? { 'Content-Type': 'application/json' } : {};
        const res = await fetch(url, { credentials: 'same-origin', ...opts, headers: { ...headers, ...(opts.headers || {}) } });
        let json = null;
        try { json = await res.json(); } catch (_) { /* not JSON */ }
        if (!res.ok || !json || json.success === false) {
            const err = new Error((json && (json.message || json.error)) || ('HTTP ' + res.status));
            err.data = json;
            throw err;
        }
        return json;
    }

    function toast(msg, type = 'info') {
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
        x.type = 'button';
        x.innerHTML = '&times;';
        x.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
        x.addEventListener('click', () => t.remove());
        t.append(span, x);
        wrap.appendChild(t);
        setTimeout(() => t.remove(), 5000);
    }

    function showErr(msg) {
        const el = $('tf-err');
        el.textContent = msg || '';
        el.style.display = msg ? 'block' : 'none';
        if (msg) $('tf-body').scrollTop = 0;
    }

    /* ── Money input: comma-separated while typing, 2dp on blur ── */
    const parseMoney = str => parseFloat(String(str ?? '').replace(/,/g, ''));
    const fmtMoney = v => Number(v).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function fmtMoneyTyping(str) {
        const s = String(str).replace(/[^\d.]/g, '');
        const dot = s.indexOf('.');
        let intPart = dot === -1 ? s : s.slice(0, dot);
        const dec = dot === -1 ? null : s.slice(dot + 1).replace(/\./g, '').slice(0, 2);
        intPart = intPart.replace(/^0+(?=\d)/, '').replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        return dec === null ? intPart : (intPart || '0') + '.' + dec;
    }

    function wireMoneyInput(input) {
        input.addEventListener('input', () => {
            const before = input.value;
            const caret = input.selectionStart;
            const sig = before.slice(0, caret).replace(/[^\d.]/g, '').length;   // digits/dots left of the caret
            const formatted = fmtMoneyTyping(before);
            if (formatted === before) return;
            input.value = formatted;
            let pos = 0, seen = 0;
            while (pos < formatted.length && seen < sig) { if (/[\d.]/.test(formatted.charAt(pos))) seen++; pos++; }
            input.setSelectionRange(pos, pos);
        });
        input.addEventListener('blur', () => {
            if (input.value === '') return;
            const v = parseMoney(input.value);
            if (!isNaN(v)) input.value = fmtMoney(v);
        });
    }

    /* ── Searchable combobox ──────────────────────────────────────
       A text box + hidden value + a dropdown (the shared .nha-ss-dropdown look, positioned `fixed` from the box so the
       modal's scrolling never clips it and it floats above the overlay). The lists are loaded once, so it filters in memory.
         items() → the options     match(item) → the text a typed query is looked for in
         key / label → the saved value / the text kept in the box once picked     render(item, q) → option html
         onChange() → (optional) called when the USER changes the value: picks one, or empties it. */
    function makeCombo(cfg) {
        const el = x => (typeof x === 'string' ? $(x) : x);      // input / hidden are element ids or the elements themselves (an OR card's picker is made on its own)
        const input = el(cfg.input), hidden = el(cfg.hidden);
        const box = document.createElement('div');
        box.className = 'nha-ss-dropdown';
        box.style.fontFamily = "'IBM Plex Sans', sans-serif";   // it lives on <body>, outside .nha-root, so it doesn't inherit the page font
        document.body.appendChild(box);
        let list = [], atFocus = '';   // atFocus: the value when the box got focus

        const position = () => {
            const r = input.getBoundingClientRect();
            Object.assign(box.style, { position: 'fixed', left: r.left + 'px', top: (r.bottom + 2) + 'px', width: Math.max(r.width, 280) + 'px' });
        };
        const hide = () => { box.style.display = 'none'; };
        const show = html => { box.innerHTML = html; position(); box.style.display = 'block'; };
        const note = text => show(`<div class="nha-ss-empty">${esc(text)}</div>`);

        function refresh(q) {
            let items = cfg.items();
            if (q) { const ql = q.toLowerCase(); items = items.filter(it => cfg.match(it).toLowerCase().includes(ql)); }
            list = items.slice(0, 60);
            // a creatable combo (cfg.creatable) ends the list with "Add “…”" when what is typed is not an existing item
            const name = cfg.creatable ? normName(q) : '';
            if (name && !cfg.creatable.known(name)) list.push({ name, __new: true });
            if (!list.length) return note(q ? cfg.noMatch : cfg.empty);
            show(list.map((it, i) => it.__new
                ? `<div class="nha-ss-opt tf-opt-new" data-i="${i}">${cfg.creatable.renderNew(it.name)}</div>`
                : `<div class="nha-ss-opt" data-i="${i}">${cfg.render(it, q)}</div>`).join(''));
        }

        function pick(item) {
            hidden.value = cfg.key(item);
            input.value = cfg.label(item);
            input.classList.add('tf-has-value');
            hide();
            atFocus = hidden.value;
            if (cfg.onChange) cfg.onChange();
        }

        input.addEventListener('input', () => {
            hidden.value = '';
            input.classList.remove('tf-has-value');
            refresh(input.value.trim());
        });
        // a picked item leaves its label in the box, which is not a search term — show the full list instead
        input.addEventListener('focus', () => { atFocus = hidden.value; refresh(hidden.value ? '' : input.value.trim()); });
        // A creatable combo lets the user USE a name that is not in the list: whatever is typed (cleaned up) is committed as the
        // value — an existing item in any letter case becomes that item's own spelling. Done when the box loses focus, on Enter,
        // and by the form on Save (commit()), so a name typed and left in the box is never lost. Returns whether it committed.
        function commit() {
            if (!cfg.creatable || hidden.value) return false;
            const name = normName(input.value);
            if (!name) return false;
            hidden.value = cfg.creatable.known(name) || name;
            input.value = hidden.value;
            input.classList.add('tf-has-value');
            return true;
        }
        // what is settled when the user leaves the box (or presses Enter)
        function settle() {
            commit();
            if (!hidden.value) { input.value = ''; input.classList.remove('tf-has-value'); }   // nothing picked → nothing kept
            if (hidden.value !== atFocus) { atFocus = hidden.value; if (cfg.onChange) cfg.onChange(); }   // the user changed / emptied it
        }

        input.addEventListener('blur', () => { commit(); setTimeout(() => { hide(); settle(); }, 150); });   // commit now: a click on Save follows at once
        input.addEventListener('keydown', e => { if (e.key === 'Enter' && cfg.creatable) { e.preventDefault(); settle(); hide(); } });
        box.addEventListener('mousedown', e => {
            e.preventDefault();                                  // keep focus on the input (also when the list's scrollbar is grabbed)
            const el = e.target.closest('.nha-ss-opt');
            if (!el) return;
            pick(list[Number(el.dataset.i)]);
        });
        const reposition = () => { if (box.style.display === 'block') position(); };
        window.addEventListener('scroll', reposition, true);
        window.addEventListener('resize', reposition);

        return {
            set(key, label) { hidden.value = key; input.value = label; input.classList.add('tf-has-value'); },
            reset() { hidden.value = ''; input.value = ''; input.classList.remove('tf-has-value'); hide(); },
            hide,
            commit,
            value: () => hidden.value,
            destroy() {                                          // removes the dropdown and its listeners when an OR card goes away
                box.remove();
                window.removeEventListener('scroll', reposition, true);
                window.removeEventListener('resize', reposition);
            },
        };
    }

    /* ── Lookups (read once; a failed one is retried the next time the form opens) ── */
    const opt = (value, text) => {
        const o = document.createElement('option');
        o.value = value;
        o.textContent = text;
        return o;
    };

    const LOOKUPS = {
        types: {
            label: 'tracking types', url: U.types,
            apply(rows) {
                const t = rows.find(r => !r.deleted_at && isTrustCode(r.fund_tracking_code));
                S.typeId = t ? Number(t.fund_tracking_type_id) : null;
            },
        },
        years: {
            label: 'fiscal years', url: U.years,
            apply(rows) {
                const ys = [...new Set(rows.map(r => Number(r.fiscal_yr)).filter(Boolean))].sort((a, b) => a - b);
                const el = $('tf-year');
                el.innerHTML = '';
                el.appendChild(opt('', 'Select fiscal year'));
                ys.forEach(y => el.appendChild(opt(y, String(y))));
                // an OR row added before the list arrived gets the same options (keeping what it had picked)
                document.querySelectorAll('#tf-ors .tf-or-year').forEach(sel => { const cur = sel.value; sel.innerHTML = el.innerHTML; sel.value = cur; });
            },
        },
        subs: {
            label: 'sub-programs', url: U.subs,
            apply(rows) { S.subs = rows; },
        },
        natures: {
            label: 'natures of fund', url: U.natures,
            apply(rows) {
                S.natures = rows.filter(n => n.is_active !== false);
                // an OR card added before the list arrived gets the same options (keeping what it had picked)
                document.querySelectorAll('#tf-ors .tf-or-nature').forEach(sel => { const cur = sel.value; sel.innerHTML = natureOptions(); sel.value = cur; });
            },
        },
        agencies: {
            label: 'agencies', url: U.agencies,
            apply(rows) {
                // the list offers the active agencies; the name index holds ALL of them, because fims.agency.name is unique —
                // an inactive agency's name can't be added again, it is simply used
                S.agencyNames = new Map(rows.filter(a => nameKey(a.name) !== '').map(a => [nameKey(a.name), String(a.name)]));
                S.agencies = rows
                    .filter(a => a.is_active !== false && String(a.name || '').trim() !== '')
                    .sort((a, b) => String(a.name).localeCompare(String(b.name)));
            },
        },
    };

    // an agency just added to fims.agency joins the list and the name index at once, without reading them again
    function registerAgency(name) {
        S.agencyNames.set(nameKey(name), name);
        if (!S.agencies.some(a => nameKey(a.name) === nameKey(name))) {
            S.agencies.push({ name, is_active: true });
            S.agencies.sort((a, b) => String(a.name).localeCompare(String(b.name)));
        }
    }

    /* ── The Agency field creates an agency that is not there yet ─────
       isNewAgency(): the agency in the field is not in fims.agency (and is not simply the text the record being edited already
       carries). ensureAgency(name) is what Save calls first: an agency that exists is used with its own spelling; a new one is
       added with POST /agency/add. If that is refused because someone added the same name a moment ago, the list is read again
       and that agency is used; only if it still isn't there does the save stop, with the reason. */
    const AGENCY_HINT = 'Select the agency or LGU that downloaded / released the trust fund — or type its name to add it';

    function isNewAgency() {
        const name = $('tf-agency-name').value;
        return name !== '' && !S.agencyNames.has(nameKey(name)) && nameKey(name) !== nameKey(S.origAgency);
    }

    function syncAgencyHint() {
        $('tf-agency-hint').innerHTML = isNewAgency()
            ? '<span class="tf-new-note">New agency</span> — “' + esc($('tf-agency-name').value) + '” will be added to the agency list when you save'
            : AGENCY_HINT;
    }

    async function ensureAgency(name) {
        name = normName(name);
        const known = S.agencyNames.get(nameKey(name));
        if (known) return { name: known, created: false };
        if (nameKey(name) === nameKey(S.origAgency)) return { name, created: false };   // the record's own, untouched agency text

        try {
            await api(U.agencyAdd, { method: 'POST', body: JSON.stringify({ name, is_active: true }) });
        } catch (e) {
            let again = null;
            try { again = (await api(U.agencies)).data || []; } catch (_) { /* fall through to the error below */ }
            const found = again && again.find(a => nameKey(a.name) === nameKey(name));
            if (!found) throw new Error('The agency “' + name + '” could not be added: ' + e.message);
            LOOKUPS.agencies.apply(again);                    // someone else added it: take their spelling and refresh the list
            return { name: String(found.name), created: false };
        }
        registerAgency(name);
        return { name, created: true };
    }

    async function ensureLookups() {
        const pending = Object.keys(LOOKUPS).filter(k => !S.loaded[k]);
        const results = await Promise.allSettled(pending.map(k => api(LOOKUPS[k].url)));
        const failed = [];
        results.forEach((r, i) => {
            const k = pending[i];
            if (r.status === 'fulfilled') { LOOKUPS[k].apply(r.value.data || []); S.loaded[k] = true; }
            else failed.push(`${LOOKUPS[k].label} (${r.reason.message})`);
        });
        if (failed.length) showErr('Could not load ' + failed.join(', ') + '. Close and reopen this form to try again.');
        else if (!S.typeId) showErr('There is no "Trust Fund" tracking type, so a trust fund cannot be saved yet. Add it under Fund Tracking Types first.');
    }

    /* ── Reference No. suggestion ─────────────────────────────────
       The program name's initials + the year, e.g. "Bonifacio East Development Project" → "BEDP-2026". Long names are kept short by
       ignoring the "[CODE]" suffix and anything in parentheses; stopping at the first clause word (in, for, at, under, …) — what
       follows is usually location / beneficiaries, not the name; skipping filler words (of, and, the, …) and number-only words;
       and using at most REF_MAX_INITIALS letters. "Acquisition of lot, site development and core housing construction in Buhi
       Resettlement Project …" → "ALSDC-2026". '' when there is no usable name. */
    const REF_MAX_INITIALS = 5;
    const REF_FILLER = ['of', 'and', 'the', 'a', 'an', 'on', 'to', 'with'];
    const REF_STOP = ['in', 'for', 'at', 'under', 'from', 'by', 'affected'];

    function generateRef(name, year) {
        const words = String(name || '').replace(/\[.*?\]|\(.*?\)/g, ' ').split(/[^A-Za-z0-9]+/).filter(Boolean);
        let initials = '';
        for (let i = 0; i < words.length && initials.length < REF_MAX_INITIALS; i++) {
            const w = words[i].toLowerCase();
            if (REF_STOP.includes(w) && initials) break;
            if (REF_FILLER.includes(w) || REF_STOP.includes(w)) continue;
            if (/^\d+$/.test(w)) continue;
            initials += w.charAt(0).toUpperCase();
        }
        return initials ? initials + '-' + (year || new Date().getFullYear()) : '';
    }

    const subOf = () => S.subs.find(s => s.sub_program_code === $('tf-sub-code').value) || null;

    // Fills the Reference No. while it is empty or still holds what was suggested (data-auto); once the user types their
    // own it is left alone, and so is the reference of a record being edited.
    function suggestRef() {
        const input = $('tf-ref');
        if (input.value.trim() !== '' && input.dataset.auto !== '1') return;
        const sp = subOf();
        const ref = sp ? generateRef(sp.program_name || sp.sub_program_name, $('tf-year').value) : '';
        input.value = ref;
        input.dataset.auto = ref ? '1' : '';
        syncOrDefaults();    // the ORs name this reference as their parent
    }

    function syncSubHint() {
        const sp = subOf();
        $('tf-sub-hint').innerHTML = sp && sp.program_name
            ? 'Program: <strong>' + esc(sp.program_name) + '</strong>'
            : 'Program associated with this trust fund entry (optional)';
    }

    /* ── Pickers ────────────────────────────────────────────────── */
    // A Sub-Program picker over the loaded list: the trust fund's own, and one for every OR card
    const subPicker = (input, hidden, onChange) => makeCombo({
        input, hidden, onChange,
        items: () => S.subs,
        match: s => [s.sub_program_code, s.sub_program_name, s.program_code, s.program_name].join(' '),
        key: s => s.sub_program_code,
        label: s => s.sub_program_code + ' — ' + s.sub_program_name,
        render: (s, q) => `<span class="nha-ss-opt-code">${hl(s.sub_program_code, q)}</span><span class="nha-ss-opt-label">${hl(s.sub_program_name, q)}${s.program_name ? ' · ' + esc(s.program_name) : ''}</span>`,
        empty: 'No sub-programs found', noMatch: 'No matching sub-programs',
    });

    // when the trust fund's sub-program changes, its OR cards that haven't been given their own follow it
    const subCombo = subPicker('tf-sub', 'tf-sub-code', () => { syncSubHint(); suggestRef(); syncOrDefaults(); });

    const agencyCombo = makeCombo({
        input: 'tf-agency', hidden: 'tf-agency-name',
        items: () => S.agencies,
        match: a => a.name,
        key: a => a.name,                                   // the agency's NAME is what is saved
        label: a => a.name,
        render: (a, q) => `<span class="nha-ss-opt-label" style="color:var(--afims-text, #1A1B21);white-space:normal;">${hl(a.name, q)}</span>`,
        empty: 'No agencies yet — type a name to add one', noMatch: 'No matching agencies',
        // typing a name that is not on the list offers "Add “…”" and, once left in the box, uses it as a new agency
        creatable: {
            known: name => S.agencyNames.get(nameKey(name)) || '',
            renderNew: name => `<span class="nha-ss-opt-code">+ New</span><span class="nha-ss-opt-label" style="color:var(--afims-text, #1A1B21);white-space:normal;">Add “${esc(name)}” as a new agency</span>`,
        },
        onChange: syncAgencyHint,
    });

    /* ── OR Records (a NEW trust fund only) ─────────────────────────
       While creating a trust fund, the Official Receipts recorded against it can be added right here. Each row becomes its own OR
       record whose parent fund is this trust fund: the API (POST /fund_tracking/add, `ors`) saves the trust fund and all of its ORs
       in one transaction, each OR taking the trust fund's agency and sub-program. A row nothing has been entered in is ignored.
       Nothing is offered for an existing trust fund — its ORs are records of their own: add one with the OR form, its Parent Fund
       set to the trust fund. */
    const orVisible = () => !S.editId;

    const OR_RM_ICON = '<svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>';

    function renumberOrs() {
        const rows = document.querySelectorAll('#tf-ors .tf-or-row');
        rows.forEach((row, i) => { row.querySelector('.tf-or-title').textContent = 'OR #' + (i + 1); });
        $('tf-or-count').textContent = rows.length;
        $('tf-or-empty').style.display = rows.length ? 'none' : '';
    }

    // the options of an OR card's Nature of Fund (the list the OR form offers)
    const natureOptions = () => '<option value="">— None —</option>'
        + S.natures.map(n => `<option value="${esc(n.nature_of_fund_id)}">${esc(n.name)}</option>`).join('');

    // A full OR entry — the same fields as the OR form (the SARO form held to the OR type): Sub-Program, Nature of Fund, Fiscal Year,
    // OR No., Name, Amount, Date, Unreleased, Bank Account No., and its own Supporting Documents (plus Remarks, which the Trust Funds
    // page lists for an OR). Tracking Type (OR) and Parent Fund (this trust fund) are fixed, so they are only shown. Fiscal Year and
    // Sub-Program start as the trust fund's and follow it until changed on the card (syncOrDefaults); the agency is the trust fund's.
    function addOrRow() {
        const row = document.createElement('div');
        row.className = 'tf-or-row';
        row.innerHTML = `
            <div class="tf-or-hd">
                <button type="button" class="tf-or-toggle" aria-expanded="true" title="Collapse / expand this OR">
                    <svg class="tf-or-chev" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.4"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    <strong class="tf-or-title"></strong>
                    <span class="tf-or-meta">Tracking Type <strong>OR</strong> · Parent Fund <strong class="tf-or-parent">this trust fund</strong></span>
                    <span class="tf-or-sum"></span>
                </button>
                <button type="button" class="tf-or-rm" title="Remove this OR" aria-label="Remove this OR">${OR_RM_ICON}</button>
            </div>
            <div class="tf-or-body">
                <!-- laid out by what the field is for, on a 4-column grid: the receipt itself first (OR No., Fiscal Year, Date, Amount),
                     then where it belongs (Sub-Program takes three columns, Nature of Fund the fourth), then the optional details -->
                <div class="tf-or-grid">
                    <div class="nha-fg"><label>OR No. <span class="nha-req">*</span></label><input type="text" class="tf-or-ref" maxlength="225" placeholder="Actual OR number"></div>
                    <div class="nha-fg"><label>Fiscal Year <span class="nha-req">*</span></label><select class="tf-or-year"></select></div>
                    <div class="nha-fg"><label>Date</label><input type="date" class="tf-or-date"></div>
                    <div class="nha-fg"><label>Amount <span class="nha-req">*</span></label><div class="tf-money"><span>&#8369;</span><input type="text" inputmode="decimal" class="tf-or-amount" placeholder="0.00"></div></div>
                    <div class="nha-fg tf-span-3"><label>Sub-Program</label><input type="text" class="tf-or-sub" placeholder="Search sub-program code, name or program…"><input type="hidden" class="tf-or-sub-code" value=""></div>
                    <div class="nha-fg"><label>Nature of Fund</label><select class="tf-or-nature"></select></div>
                    <div class="nha-fg tf-span-2"><label>Name</label><input type="text" class="tf-or-name" maxlength="225" placeholder="Optional"></div>
                    <div class="nha-fg"><label>Unreleased</label><div class="tf-money"><span>&#8369;</span><input type="text" inputmode="decimal" class="tf-or-unreleased" placeholder="0.00"></div></div>
                    <div class="nha-fg"><label>Bank Account No.</label><input type="text" class="tf-or-bank" maxlength="225" placeholder="e.g. 0702-1068-34"></div>
                    <div class="nha-fg tf-span-all"><label>Remarks</label><input type="text" class="tf-or-remarks" placeholder="Optional"></div>
                </div>
                <div class="tf-or-docs-wrap">
                    <div class="tf-or-docs-hd">
                        <span>Supporting Documents</span>
                        <button type="button" class="nha-btn nha-btn-ghost nha-btn-sm tf-or-doc-add">
                            <svg width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add Document
                        </button>
                    </div>
                    <div class="tf-or-docs"></div>
                </div>
            </div>`;

        // collapse / expand from the header; while collapsed, the header sums the card up so it can still be told apart
        const toggle = row.querySelector('.tf-or-toggle');
        toggle.addEventListener('click', () => setOrCollapsed(row, !row.classList.contains('collapsed')));
        row.addEventListener('input', () => updateOrSummary(row));
        row.addEventListener('change', () => updateOrSummary(row));

        // the same pick-lists as the trust fund's own Fiscal Year; a change made on the card stops it following the trust fund
        const year = row.querySelector('.tf-or-year');
        year.innerHTML = $('tf-year').innerHTML;
        year.addEventListener('change', () => { year.dataset.touched = '1'; });
        row.querySelector('.tf-or-nature').innerHTML = natureOptions();
        const sub = row.querySelector('.tf-or-sub');
        row._sub = subPicker(sub, row.querySelector('.tf-or-sub-code'), () => { sub.dataset.touched = '1'; });

        row.querySelectorAll('.tf-money input').forEach(wireMoneyInput);      // Amount and Unreleased
        const docs = row.querySelector('.tf-or-docs');
        wireDocList(docs, row.querySelector('.tf-or-doc-add'));
        addDocRow(undefined, docs);

        $('tf-ors').appendChild(row);
        renumberOrs();
        syncOrDefaults();   // starts the card at the trust fund's fiscal year / sub-program, and names its parent
        updateOrSummary(row);
        return row;
    }

    function setOrCollapsed(row, collapsed) {
        row.classList.toggle('collapsed', collapsed);
        row.querySelector('.tf-or-toggle').setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

    const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

    // "OR No. · ₱ amount · date" — what the header of a collapsed card shows
    function updateOrSummary(row) {
        const f = c => row.querySelector('.tf-or-' + c);
        const ref = f('ref').value.trim(), amount = parseMoney(f('amount').value), m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(f('date').value);
        const parts = [ref ? `<strong>${esc(ref)}</strong>` : '<span class="tf-or-sum-none">No OR No. yet</span>'];
        if (!isNaN(amount) && amount > 0) parts.push('₱ ' + fmtMoney(amount));
        if (m && MONTHS[+m[2] - 1]) parts.push(MONTHS[+m[2] - 1] + ' ' + (+m[3]) + ', ' + m[1]);
        f('sum').innerHTML = parts.join(' · ');
    }

    // An OR card follows the trust fund's Fiscal Year and Sub-Program until they are changed on the card itself, and always shows
    // which trust fund it will be a child of (its reference, once there is one).
    function syncOrDefaults() {
        const year = $('tf-year').value, ref = $('tf-ref').value.trim();
        const subCode = subCombo.value(), subLabel = $('tf-sub').value;
        document.querySelectorAll('#tf-ors .tf-or-row').forEach(row => {
            const y = row.querySelector('.tf-or-year'), sub = row.querySelector('.tf-or-sub');
            if (!y.dataset.touched) y.value = year;
            if (!sub.dataset.touched) { if (subCode) row._sub.set(subCode, subLabel); else row._sub.reset(); }
            row.querySelector('.tf-or-parent').textContent = ref || 'this trust fund';
        });
    }

    // the cards with anything entered in them (an untouched card is ignored — the pre-filled lookups don't count); n is the number
    // its title shows
    function orEntries() {
        return [...document.querySelectorAll('#tf-ors .tf-or-row')].map((row, i) => {
            const f = c => row.querySelector('.tf-or-' + c);
            return { row, n: i + 1, ref: f('ref').value.trim(), name: f('name').value.trim(), date: f('date').value, amount: f('amount').value.trim(),
                unreleased: f('unreleased').value.trim(), bank: f('bank').value.trim(), remarks: f('remarks').value.trim(),
                year: f('year').value, sub: f('sub-code').value, nature: f('nature').value, docs: getDocs(f('docs')) };
        }).filter(e => e.ref || e.name || e.date || e.amount || e.unreleased || e.bank || e.remarks || e.docs.length);
    }

    // "Total OR: ₱ …", with a (non-blocking) note when it is more than the trust fund's own amount
    function updateOrTotal() {
        const el = $('tf-or-total');
        const total = (orVisible() ? orEntries() : []).reduce((sum, e) => sum + (parseMoney(e.amount) || 0), 0);
        if (!total) { el.textContent = ''; el.classList.remove('over'); return; }
        const tf = parseMoney($('tf-amount').value);
        const over = !isNaN(tf) && tf > 0 && total > tf;
        el.textContent = 'Total OR: ₱ ' + fmtMoney(total) + (over ? ' — more than the trust fund amount (₱ ' + fmtMoney(tf) + ')' : '');
        el.classList.toggle('over', over);
    }

    function syncOr() {
        $('tf-or-section').style.display = orVisible() ? '' : 'none';
        updateOrTotal();
    }

    function clearOrs() {
        document.querySelectorAll('#tf-ors .tf-or-row').forEach(row => row._sub && row._sub.destroy());   // their dropdowns live on <body>
        $('tf-ors').innerHTML = '';
        renumberOrs();
        updateOrTotal();
    }

    /* ── Supporting Documents (each OR card's own) ──────────────────
       The same machinery as the SARO form's: files upload one at a time via POST /upload/file (the document name is taken from
       the file); the records are saved FIRST and only then are each OR's documents grouped (POST /upload/multi-upload → a new
       multiple_node_id, PATCHed onto that OR) — doing it the other way round would leave orphaned file rows behind whenever the
       records themselves were rejected. A file over 25MB falls back to a link. */
    const DZ_ICON = '<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6H16a5 5 0 011 9.9M12 12v9m0-9l-3 3m3-3l3 3"/></svg>';

    function fmtSize(bytes) { return bytes >= 1048576 ? (bytes / 1048576).toFixed(1) + ' MB' : Math.ceil(bytes / 1024) + ' KB'; }

    // `list` is the container to add the row to — an OR card's own list
    function addDocRow(label, list) {
        const row = document.createElement('div');
        row.className = 'tf-doc-row';
        row.innerHTML = `
            <div>
                <label class="tf-doc-label">${esc(label || 'Supporting Document')}</label>
                <div class="tf-dz">
                    <input type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
                    ${DZ_ICON}
                    <p>Drag &amp; drop a file, or click to browse</p>
                    <small>Max 25MB · PDF, DOC, XLS, JPG, PNG</small>
                </div>
                <div class="tf-chip"><span class="tf-chip-name"></span><span class="tf-chip-size"></span><button type="button" class="tf-chip-x">&#10005; Remove</button></div>
                <input type="text" class="tf-doc-link" placeholder="e.g. https://drive.google.com/…" style="display:none;margin-top:8px;">
                <p class="tf-doc-note"></p>
                <input type="hidden" class="tf-doc-name" value="">
            </div>
            <button type="button" class="tf-doc-rm" title="Remove this row" aria-label="Remove this row">
                <svg width="15" height="15" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>`;
        list.appendChild(row);
        return row;
    }

    function docNote(row, message, isError) {
        const n = row.querySelector('.tf-doc-note');
        n.textContent = message || '';
        n.className = 'tf-doc-note' + (isError ? ' error' : '');
        n.style.display = message ? 'block' : 'none';
    }

    async function processFile(row, file) {
        docNote(row, '', false);
        // the document name always comes from the file itself — set up front so it survives an oversize fallback
        row.querySelector('.tf-doc-name').value = file.name.replace(/\.[^./]+$/, '');

        if (file.size > MAX_BYTES) {
            row.querySelector('.tf-dz').style.display = 'none';
            row.querySelector('.tf-chip').style.display = 'none';
            const link = row.querySelector('.tf-doc-link');
            link.style.display = 'block';
            link.focus();
            docNote(row, 'That file is ' + fmtSize(file.size) + ', which is over the 25MB limit. Please provide a link instead.', true);
            return;
        }

        const dz = row.querySelector('.tf-dz');
        dz.classList.add('uploading');
        docNote(row, 'Uploading…', false);
        const fd = new FormData();
        fd.append('file', file);
        try {
            const json = await api(U.upload, { method: 'POST', body: fd });
            row.querySelector('.tf-doc-link').value = json.link;
            row.querySelector('.tf-chip-name').textContent = file.name;
            row.querySelector('.tf-chip-size').textContent = fmtSize(file.size);
            dz.style.display = 'none';
            row.querySelector('.tf-chip').style.display = 'flex';
            docNote(row, '', false);
        } catch (e) {
            docNote(row, e.message || 'Upload failed. Please try again or use a link instead.', true);
        } finally {
            dz.classList.remove('uploading');
        }
    }

    function clearDocFile(row) {
        row.querySelector('.tf-doc-name').value = '';
        row.querySelector('.tf-doc-link').value = '';
        const file = row.querySelector('input[type="file"]');
        if (file) file.value = '';
        row.querySelector('.tf-chip').style.display = 'none';
        row.querySelector('.tf-dz').style.display = '';      // back to the stylesheet's own layout
        docNote(row, '', false);
    }

    // the documents in one list
    function getDocs(list) {
        return [...list.querySelectorAll('.tf-doc-row')].map(row => ({
            document_name: row.querySelector('.tf-doc-name').value.trim(),
            link: row.querySelector('.tf-doc-link').value.trim(),
        })).filter(d => d.document_name || d.link);
    }

    // Wires one document list (drag & drop, remove, "Add Document"); every OR card's list uses it.
    function wireDocList(list, addBtn) {
        list.addEventListener('change', e => {
            if (e.target.matches('input[type="file"]') && e.target.files && e.target.files[0]) processFile(e.target.closest('.tf-doc-row'), e.target.files[0]);
        });
        list.addEventListener('dragover', e => { const dz = e.target.closest('.tf-dz'); if (dz) { e.preventDefault(); dz.classList.add('drag-over'); } });
        list.addEventListener('dragleave', e => { const dz = e.target.closest('.tf-dz'); if (dz) dz.classList.remove('drag-over'); });
        list.addEventListener('drop', e => {
            const dz = e.target.closest('.tf-dz');
            if (!dz) return;
            e.preventDefault();
            dz.classList.remove('drag-over');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) processFile(dz.closest('.tf-doc-row'), e.dataTransfer.files[0]);
        });
        list.addEventListener('click', e => {
            if (e.target.closest('.tf-chip-x')) clearDocFile(e.target.closest('.tf-doc-row'));
            else if (e.target.closest('.tf-doc-rm')) e.target.closest('.tf-doc-row').remove();
        });
        addBtn.addEventListener('click', () => addDocRow(undefined, list));
    }

    // Each OR's own Supporting Documents, attached after the save (the API returns or_ids in the order they were sent).
    // Non-fatal: the records already exist, so a failure here is a warning.
    async function attachOrDocs(orIds, entries) {
        const failed = [];
        for (let i = 0; i < entries.length; i++) {
            if (!entries[i].docs.length || !orIds[i]) continue;
            try {
                const up = await api(U.multi, { method: 'POST', body: JSON.stringify({ file_data: entries[i].docs }) });
                if (up.multiple_node_id) await api(U.update(orIds[i]), { method: 'PATCH', body: JSON.stringify({ multiple_node_id: up.multiple_node_id }) });
            } catch (e) {
                console.warn('[tf attachOrDocs]', e);
                failed.push(`OR #${entries[i].n} (${e.message})`);
            }
        }
        if (failed.length) toast('The records were saved, but the supporting documents of ' + failed.join(', ') + ' could not be attached.', 'warning');
    }

    /* ── Open / populate / close ────────────────────────────────── */
    function setMode() {
        $('tf-title').textContent = S.editId ? 'Trust Fund — Edit Entry' : 'Trust Fund — New Entry';
        $('tf-save').textContent = S.editId ? 'Update Trust Fund' : 'Save Trust Fund';
        syncOr();   // ORs can only be added while creating
    }

    function resetForm() {
        $('tf-form').reset();
        showErr('');
        clearOrs();
        subCombo.reset();
        agencyCombo.reset();
        $('tf-ref').dataset.auto = '';
        $('tf-form').querySelectorAll('.tf-invalid').forEach(el => el.classList.remove('tf-invalid'));
        S.origAgency = '';
        syncSubHint();
        syncAgencyHint();
    }

    // select `value`, adding an option for it first when the list doesn't have it (a year retired since the record was saved)
    function selectValue(id, value) {
        const el = $(id);
        if (value === null || value === undefined || value === '') { el.value = ''; return; }
        if (![...el.options].some(o => String(o.value) === String(value))) el.appendChild(opt(value, String(value)));
        el.value = String(value);
    }

    function populate(r) {
        const sp = r.sub_program;
        if (r.sub_program_code) subCombo.set(r.sub_program_code, sp ? sp.sub_program_code + ' — ' + sp.sub_program_name : r.sub_program_code);
        syncSubHint();

        selectValue('tf-year', r.fiscal_year);
        $('tf-ref').value = r.reference_number || '';       // data-auto stays off: the saved reference is never re-suggested
        $('tf-ref').dataset.auto = '';

        const agency = r.agency_name || (r.agency && r.agency.name) || '';
        S.origAgency = agency;                              // saved as it is unless the user changes it — never "created" afresh
        if (agency) agencyCombo.set(agency, agency);        // even a name that is no longer on the agency list
        syncAgencyHint();

        $('tf-amount').value = r.amount != null ? fmtMoney(r.amount) : '';
        $('tf-date').value = r.date ? String(r.date).slice(0, 10) : '';
        $('tf-remarks').value = r.remarks || '';
    }

    async function open(id) {
        if (S.opening) return;
        S.opening = true;
        window.PagePreloader?.show();
        try {
            resetForm();
            S.editId = id ? Number(id) : null;
            setMode();
            $('tf-overlay').classList.add('open');
            $('tf-body').scrollTop = 0;

            await ensureLookups();

            if (S.editId) {
                try {
                    const rec = (await api(U.read(S.editId))).data;
                    if (!isTrustCode(rec.tracking_type && rec.tracking_type.fund_tracking_code)) {
                        toast('Record #' + S.editId + ' is not a Trust Fund.', 'error');
                        close();
                        return;
                    }
                    populate(rec);
                } catch (e) {
                    toast('Could not load record #' + S.editId + ': ' + e.message, 'error');
                    close();
                    return;
                }
            } else {
                // a new entry starts in the current fiscal year, if it is on the list
                const thisYear = String(new Date().getFullYear());
                if ([...$('tf-year').options].some(o => o.value === thisYear)) $('tf-year').value = thisYear;
            }
        } finally {
            S.opening = false;
            window.PagePreloader?.hide();
        }
    }

    function close() {
        $('tf-overlay').classList.remove('open');
        subCombo.hide();
        agencyCombo.hide();
    }

    /* ── Save ───────────────────────────────────────────────────── */
    async function save(ev) {
        ev.preventDefault();
        if (S.saving) return;
        showErr('');
        agencyCombo.commit();            // an agency name typed and still sitting in the box counts
        syncAgencyHint();

        const v = id => $(id).value.trim();
        $('tf-form').querySelectorAll('.tf-invalid').forEach(el => el.classList.remove('tf-invalid'));
        const missing = [];
        const need = (id, label, bad) => { if (bad) { $(id).classList.add('tf-invalid'); missing.push(label); } };
        need('tf-ref', 'Reference No.', v('tf-ref') === '');
        need('tf-agency', 'Agency / LGU Name', $('tf-agency-name').value === '');
        need('tf-year', 'Fiscal Year', !$('tf-year').value);
        need('tf-amount', 'Trust Fund Amount', !(parseMoney(v('tf-amount')) > 0));
        if (missing.length) return showErr('Please fill in the required fields: ' + missing.join(', ') + '.');
        if (!S.typeId) return showErr('There is no "Trust Fund" tracking type, so a trust fund cannot be saved yet. Add it under Fund Tracking Types first.');

        // OR rows that were started: each needs its fiscal year, OR No. and amount (as an OR entered on its own does), and no two
        // may share an OR No.
        const orProblems = [];
        const seenRefs = new Map();
        const andList = a => (a.length > 1 ? a.slice(0, -1).join(', ') + ' and ' + a[a.length - 1] : a[0]);
        (orVisible() ? orEntries() : []).forEach(e => {
            const f = c => e.row.querySelector('.tf-or-' + c);
            const bad = [];
            if (!e.year) { f('year').classList.add('tf-invalid'); bad.push('Fiscal Year'); }
            if (!e.ref) { f('ref').classList.add('tf-invalid'); bad.push('OR No.'); }
            if (!(parseMoney(e.amount) > 0)) { f('amount').classList.add('tf-invalid'); bad.push('Amount'); }
            if (bad.length) { setOrCollapsed(e.row, false); orProblems.push(`OR #${e.n}: ${andList(bad)} ${bad.length > 1 ? 'are' : 'is'} required.`); return; }
            const key = e.ref.toUpperCase();
            if (seenRefs.has(key)) { setOrCollapsed(e.row, false); f('ref').classList.add('tf-invalid'); orProblems.push(`OR #${e.n}: the OR No. is already used by OR #${seenRefs.get(key)}.`); }
            else seenRefs.set(key, e.n);
        });
        if (orProblems.length) return showErr(orProblems.join(' '));

        const btn = $('tf-save');
        const label = btn.textContent;
        S.saving = true;
        btn.disabled = true;
        btn.textContent = S.editId ? 'Updating…' : 'Saving…';
        window.PagePreloader?.show();
        let addedAgency = '';            // the agency created by THIS save, so a failure after it can say so
        try {
            // an agency that is not on the list yet is added to it first; the trust fund is then saved with that agency
            const agency = await ensureAgency($('tf-agency-name').value);
            if (agency.created) addedAgency = agency.name;
            syncAgencyHint();            // it exists now: the "new agency" note goes

            // Every field the form has is always sent (null clears an optional one on update, '' clears the text); parent_id,
            // nature of fund, name and the rest are not on this form, so they are never sent and an edit leaves them as they are.
            const payload = {
                tracking_type_id: S.typeId,
                fiscal_year: parseInt($('tf-year').value, 10) || null,
                sub_program_code: $('tf-sub-code').value || null,
                agency_name: agency.name,
                reference_number: v('tf-ref'),
                amount: parseMoney(v('tf-amount')),
                date: v('tf-date') || null,
                remarks: v('tf-remarks'),
            };

            // The ORs go with a NEW trust fund, in the same request: the API saves them all or none, each with the new trust fund
            // as its parent and with its agency. Fiscal Year, Sub-Program and Nature of Fund are always sent as the card shows
            // them (null = none), so a card that was changed on its own keeps its own value and one that was cleared stays empty.
            const ors = orVisible() ? orEntries() : [];
            if (ors.length) {
                payload.ors = ors.map(e => ({
                    fiscal_year: parseInt(e.year, 10) || null,
                    sub_program_code: e.sub || null,
                    nature_of_fund_id: parseInt(e.nature, 10) || null,
                    reference_number: e.ref,
                    name: e.name,
                    date: e.date || null,
                    amount: parseMoney(e.amount),
                    unreleased: parseMoney(e.unreleased) || null,
                    bank_account_no: e.bank,
                    remarks: e.remarks,
                }));
            }

            const wasEdit = !!S.editId;
            let json = {};
            if (wasEdit) json = await api(U.update(S.editId), { method: 'PATCH', body: JSON.stringify(payload) });
            else json = await api(U.add, { method: 'POST', body: JSON.stringify(payload) });

            await attachOrDocs(json.or_ids || [], ors);          // the ORs exist now: attach each one's documents
            close();
            const orCount = (json.or_ids || []).length;
            toast((wasEdit ? 'Trust fund updated successfully.'
                    : orCount ? `Trust fund saved successfully, with ${orCount} OR record${orCount > 1 ? 's' : ''} under it.` : 'Trust fund saved successfully.')
                + (agency.created ? ` The new agency “${agency.name}” was added to the agency list.` : ''), 'success');
            if (typeof window.tfl_reload === 'function') window.tfl_reload();
        } catch (e) {
            // the agency stays added even though the trust fund was not saved — say so, so pressing Save again is no surprise
            showErr(e.message + (addedAgency ? ` (The new agency “${addedAgency}” has already been added to the agency list.)` : ''));
            btn.textContent = label;
        } finally {
            S.saving = false;
            btn.disabled = false;
            window.PagePreloader?.hide();
        }
    }

    /* ── Wire-up (the markup exists once, here; every open starts it over via resetForm) ── */
    wireMoneyInput($('tf-amount'));
    $('tf-form').addEventListener('submit', save);
    $('tf-form').addEventListener('keydown', e => { if (e.key === 'Enter' && e.target.tagName !== 'TEXTAREA' && e.target.type !== 'submit') e.preventDefault(); });
    $('tf-close').addEventListener('click', close);
    $('tf-cancel').addEventListener('click', close);
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && $('tf-overlay').classList.contains('open') && !S.saving) close(); });

    // typing a Reference No. of your own stops it being re-suggested; a new fiscal year re-suggests a suggested one
    $('tf-ref').addEventListener('input', () => { $('tf-ref').dataset.auto = ''; syncOrDefaults(); });
    $('tf-year').addEventListener('change', () => { suggestRef(); syncOrDefaults(); });

    // OR Records
    $('tf-or-add').addEventListener('click', () => { addOrRow().querySelector('.tf-or-ref').focus(); });
    $('tf-ors').addEventListener('click', e => {
        const rm = e.target.closest('.tf-or-rm');
        if (!rm) return;
        const row = rm.closest('.tf-or-row');
        if (row._sub) row._sub.destroy();
        row.remove();
        renumberOrs();
        updateOrTotal();
    });
    $('tf-ors').addEventListener('input', updateOrTotal);        // runs after a money input has formatted itself
    $('tf-amount').addEventListener('input', updateOrTotal);     // the trust fund's own amount is what the total is compared with

    window.trustFundModal = { open, close };
}());
</script>
