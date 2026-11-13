<?php
/**
 * print-preview-readone.php
 *
 * Print preview modal for every printed form — CBA, CAF, RFA, LAA (PrintService::DOCS):
 *   prp_open(payload, { type: 'CBA' | 'CAF' | 'RFA' | 'LAA' })
 * Shows the form as HTML, then downloads it as PDF or DOCX — all three come from
 * POST /api/print/{doc} (app/routes/print.php → PrintService), so the preview is
 * exactly what gets printed.
 *
 * "Edit Letterhead" (same modal) — a header designer:
 *   the header is freely placed items — images and titles. Drag them anywhere
 *   on the preview's header (snapping to the centre / each other), resize with
 *   the corner handle, nudge with the arrow keys, or type X / Y; add an image
 *   (button, or drop a file where it should go) or a title, remove items; edit
 *   the footer text line.
 *   Edits apply to THIS print only (payload `letterhead`); closing the preview
 *   or the tab drops them. Saver roles (LetterheadLayoutService::SAVER_LEVELS)
 *   also get "Save as default header" (used by everyone's prints from then on)
 *   and "Restore original" (back to the built-in NHA letterhead).
 *   API: GET / POST / DELETE /api/letterhead/{doc} (LetterheadLayoutService) —
 *   each form keeps its own saved default.
 *
 * Trigger: prp_open(payload, { type }) — payload fields are documented on each
 * form's model() (CbaPrintService, CafPrintService, RfaPrintService, LaaPrintService).
 *
 * Not wired into any page yet. To use it, include it in a page that already
 * loads _shared/css/nha-fims.css:
 *   <?php include __DIR__ . '/readone/print-preview-readone.php'; ?>
 * Self-contained: wraps itself in .nha-root and needs no page helpers.
 */
$_prp_api = \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
.nha-root .prp-frame-wrap { background: #eceff1; border-radius: var(--rs, 7px); overflow: hidden; }
.nha-root .prp-frame { display: block; width: 100%; height: 72vh; border: 0; background: #eceff1; }
.nha-root .prp-lh-open .prp-frame { height: calc(72vh - 300px); min-height: 230px; }
.nha-root .prp-msg { padding: 60px 20px; text-align: center; color: var(--subtle, #62657E); font-size: 13px; }
.nha-root .prp-err {
    margin-top: 10px; padding: 8px 12px; border-radius: var(--rs, 7px);
    background: #FDECEC; color: #B42318; font-size: 13px;
}
.nha-root .prp-msg[hidden], .nha-root .prp-err[hidden], .nha-root .prp-frame[hidden], .nha-root .prp-lh[hidden],
.nha-root .prp-lh-sec[hidden], .nha-root .prp-lh-item[hidden] { display: none; }

/* ── Letterhead designer ── */
.nha-root .prp-lh { border: 1px solid var(--bdr, #E4ECF2); border-radius: var(--rs, 7px); padding: 8px 12px; margin-bottom: 12px;
    max-height: 300px; overflow-y: auto; font-size: 12px; }
.nha-root .prp-lh-help { margin: 0 0 4px; color: var(--subtle, #62657E); }
.nha-root .prp-lh-sec { display: flex; align-items: center; gap: 8px; margin: 8px 0 4px; font-size: 11px; font-weight: 700;
    letter-spacing: .05em; text-transform: uppercase; color: var(--subtle, #62657E); }
.nha-root .prp-lh-sec .grow { flex: 1; }
.nha-root .prp-lh-item { display: grid; grid-template-columns: 36px 1fr auto auto; gap: 10px; align-items: center;
    padding: 6px 0; border-top: 1px solid var(--bdr, #E4ECF2); }
.nha-root .prp-lh-no { font-weight: 700; color: var(--subtle, #62657E); text-align: center; cursor: grab; user-select: none; white-space: nowrap; touch-action: none; }
.nha-root .prp-lh-item.is-dragging { opacity: .4; }
.nha-root .prp-lh-item.is-drop { box-shadow: inset 0 2px 0 var(--afims-heading-fill, #000E3E); }
.nha-root .prp-lh-item.is-sel { background: var(--surf, #F4F7FA); box-shadow: inset 3px 0 0 var(--afims-heading-fill, #000E3E); }
.nha-root .prp-lh-body { display: flex; align-items: center; gap: 8px; min-width: 0; flex-wrap: wrap; }
.nha-root .prp-lh-badge { font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 4px;
    background: var(--surf, #F4F7FA); color: var(--subtle, #62657E); }
.nha-root .prp-lh-thumb { width: 90px; height: 34px; display: flex; align-items: center; justify-content: center; border-radius: 6px;
    background: repeating-conic-gradient(#f3f4f6 0% 25%, #fff 0% 50%) 50% / 12px 12px; overflow: hidden; }
.nha-root .prp-lh-thumb img { max-width: 100%; max-height: 30px; }
.nha-root .prp-lh-field { display: inline-flex; align-items: center; gap: 4px; color: var(--subtle, #62657E); white-space: nowrap; }
.nha-root .prp-lh-in { padding: 4px 6px; font-size: 12px; border: 1px solid var(--bdr, #E4ECF2); border-radius: 6px; background: #fff; }
.nha-root .prp-lh-in.num { width: 56px; }
.nha-root .prp-lh-in.txt { flex: 1 1 180px; min-width: 140px; }
.nha-root .prp-lh-place { display: flex; align-items: center; gap: 6px; }
.nha-root .prp-lh-icon { padding: 3px 8px; font-size: 12px; line-height: 1.2; border: 1px solid var(--bdr, #E4ECF2); border-radius: 6px;
    background: #fff; cursor: pointer; }
.nha-root .prp-lh-icon:disabled { opacity: .35; cursor: default; }
.nha-root .prp-lh-actions { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; padding-top: 8px; border-top: 1px solid var(--bdr, #E4ECF2); }
.nha-root .prp-lh-actions .grow { flex: 1; }
.nha-root .prp-lh-status { min-height: 16px; margin-top: 4px; color: var(--subtle, #62657E); }
.nha-root .prp-lh-status.ok { color: #067647; }
.nha-root .prp-lh-status.bad { color: #B42318; }
.nha-root .prp-lh input[type=file] { display: none; }
@media (max-width: 760px) { .nha-root .prp-lh-item { grid-template-columns: 36px 1fr; } }
</style>

<!-- ── PRINT PREVIEW MODAL ───────────────────────────── -->
<div id="prp-root" class="nha-root">
    <div id="prp-overlay" class="nha-overlay no-print">
        <div class="nha-modal nha-modal-lg" role="dialog" aria-modal="true" aria-labelledby="prp-title">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="prp-title">Print Preview — Certificate of Budget Allocation</h3>
                <button type="button" class="nha-modal-close" id="prp-close" aria-label="Close">&times;</button>
            </div>
            <div class="nha-modal-body" id="prp-body">
                <div class="prp-lh" id="prp-lh" hidden>
                    <p class="prp-lh-help" id="prp-lh-help">
                        <b>Drag items anywhere in the header of the preview below</b> — they snap to the centre and to each
                        other (hold Alt to place freely); click one to select it, then drag its corner handle to resize or
                        use the arrow keys to nudge it. Changes apply to <b>this printout only</b>
                        (preview, PDF and DOCX) — closing the preview brings back the default header.
                    </p>

                    <div class="prp-lh-sec">
                        <span class="grow">Header items</span>
                        <input type="file" id="prp-lh-addfile" accept="image/*">
                        <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" id="prp-lh-add-image">+ Add image</button>
                        <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" id="prp-lh-add-title">+ Add title</button>
                    </div>
                    <div id="prp-lh-items"></div>

                    <div class="prp-lh-sec" id="prp-lh-footer-sec"><span class="grow">Footer</span></div>
                    <div class="prp-lh-item" id="prp-lh-footer-row" style="grid-template-columns: 1fr auto;">
                        <input type="text" class="prp-lh-in" id="prp-lh-footer" maxlength="300" autocomplete="off" aria-label="Footer text">
                        <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" id="prp-lh-footer-reset">Use default</button>
                    </div>

                    <div class="prp-lh-actions">
                        <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" id="prp-lh-reset">Reset to default</button>
                        <span class="grow"></span>
                        <button type="button" class="nha-btn nha-btn-outline nha-btn-sm" id="prp-lh-restore" hidden>Restore original</button>
                        <button type="button" class="nha-btn nha-btn-primary nha-btn-sm" id="prp-lh-save" hidden>Save as default header</button>
                    </div>
                    <div class="prp-lh-status" id="prp-lh-status" role="status"></div>
                </div>
                <div class="prp-frame-wrap">
                    <div class="prp-msg" id="prp-msg">Loading preview&hellip;</div>
                    <!-- allow-same-origin (no allow-scripts): nothing runs inside, but the
                         designer can update the header in place for instant feedback -->
                    <iframe class="prp-frame" id="prp-frame" title="CBA print preview" sandbox="allow-same-origin" hidden></iframe>
                </div>
                <div class="prp-err" id="prp-err" role="alert" hidden></div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-outline" id="prp-lh-toggle" style="margin-right:auto;" aria-expanded="false" aria-controls="prp-lh">Edit Letterhead</button>
                <button type="button" class="nha-btn nha-btn-ghost" id="prp-cancel">Close</button>
                <button type="button" class="nha-btn nha-btn-outline" id="prp-docx" disabled>Download DOCX</button>
                <button type="button" class="nha-btn nha-btn-primary" id="prp-pdf" disabled>Download PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   PRINT PREVIEW MODAL   prefix: prp_
═══════════════════════════════════════════════════════════════ */

const PRP_API            = '<?php echo $_prp_api; ?>';
// Printed documents this preview handles — prp_open(payload, { type }).
// hdrPt = the header's printed width (CBA: A4 with 1in sides; RFA: legal with 0.5in sides);
// wide = a landscape form, shown in the xl modal;
// letterhead: false = the form has its own printed title block — no letterhead, no editor
// (matches LetterheadLayoutService::DOCUMENTS, which lists only the letterhead forms).
const PRP_TYPES = {
    CBA: { title: 'Certificate of Budget Allocation',        print: '/print/cba', hdrPt: 468 },
    RFA: { title: 'Request for Fund Allotment',              print: '/print/rfa', hdrPt: 540 },
    LAA: { title: 'Letter of Advice of Allotment',           print: '/print/laa', hdrPt: 480 },
    CAF: { title: 'Certificate as to Availability of Funds', print: '/print/caf', hdrPt: 468 },
    LAAREQ: { title: 'LAA Release Request',                 print: '/print/laareq', hdrPt: 468 },
    PO:  { title: 'Purchase Order',                          print: '/print/po', letterhead: false },
    PR:  { title: 'Purchase Requisition',                    print: '/print/pr', hdrPt: 532.8 },
    IAR: { title: 'Inspection and Acceptance Report',        print: '/print/iar', letterhead: false },
    LV:  { title: 'Liquidation Voucher',                     print: '/print/lv', letterhead: false },
    JEV: { title: 'Journal Entry Voucher',                   print: '/print/jev', letterhead: false, wide: true },   // A4 landscape
    FTLA: { title: 'Fund Transfer Letter of Advice',         print: '/print/ftla', hdrPt: 510 },
    FTLAREQ: { title: 'FTLA Request',                        print: '/print/ftlareq', hdrPt: 468 },   // the LAA release request's memo layout
    CFA: { title: 'Certified Funds Available',               print: '/print/cfa', hdrPt: 451.28 },   // funding-request memo (A4, shared memo layout)
    MEMO: { title: 'Memo Request',                           print: '/print/memo', hdrPt: 451.28 },   // the originating unit's memo (non-FMD; A4, shared memo layout)    // pdfOnly: an official PDF form — the preview shows the PDF itself; no DOCX, no letterhead editor.
    DV:  { title: 'Disbursement Voucher',                    print: '/print/dv', pdfOnly: true },
};
let prp_type = 'CBA';
const prp_printApi      = () => PRP_API + PRP_TYPES[prp_type].print;
const prp_letterheadApi = () => PRP_API + '/letterhead/' + prp_type;
const PRP_IMAGE_MAX_BYTES = 2 * 1024 * 1024;
const PRP_MAX_ITEMS       = 8;

let prp_payload = null;   // payload of the certificate currently shown
let prp_timer   = null;   // debounce for re-rendering while typing

// Letterhead designer state for the open preview:
//   base   — what this print starts from (saved default, else the built-in layout)
//   layout — the working copy being edited; sent as payload.letterhead once dirty
let prp_lh = { base: null, layout: null, dirty: false, saved: false, canSave: false, factory: null, assets: {} };

const $prp = id => document.getElementById(id);
const prp_clone = o => JSON.parse(JSON.stringify(o));

function prp_headers(json) {
    const token = window.ew?.API_JWT_TOKEN;
    return Object.assign(json ? { 'Content-Type': 'application/json' } : {}, token ? { 'X-Authorization': 'Bearer ' + token } : {});
}

async function prp_fetchJson(url, opts = {}) {
    const res = await fetch(url, Object.assign({ credentials: 'same-origin' }, opts));
    const json = await res.json().catch(() => ({}));
    if (!res.ok || !json.success) throw new Error(json.message || `Request failed (${res.status}).`);
    return json;
}

function prp_request(format) {
    return prp_fetchJson(prp_printApi(), {
        method: 'POST',
        headers: prp_headers(true),
        body: JSON.stringify(Object.assign({}, prp_payload, { format })),
    });
}

document.addEventListener('DOMContentLoaded', () => {
    $prp('prp-close')?.addEventListener('click', prp_close);
    $prp('prp-cancel')?.addEventListener('click', prp_close);
    $prp('prp-pdf')?.addEventListener('click', () => prp_download('pdf'));
    $prp('prp-docx')?.addEventListener('click', () => prp_download('docx'));
    $prp('prp-lh-toggle')?.addEventListener('click', prp_lhToggle);

    $prp('prp-lh-add-image')?.addEventListener('click', () => $prp('prp-lh-addfile').click());
    $prp('prp-lh-addfile')?.addEventListener('change', e => prp_lhReadPng(e.target, src =>
        prp_lhAdd({ type: 'image', src, height: 55 })));
    $prp('prp-lh-add-title')?.addEventListener('click', () =>
        prp_lhAdd({ type: 'title', line1: 'NEW TITLE', line2: '', size1: 14, size2: 9, bold1: true, width: 300, align: 'center' }));

    $prp('prp-lh-footer')?.addEventListener('input', e => {
        prp_lh.layout.footer_text = e.target.value;
        prp_lhChanged(false);
    });
    $prp('prp-lh-footer-reset')?.addEventListener('click', () => {
        prp_lh.layout.footer_text = prp_lh.base.footer_text;
        $prp('prp-lh-footer').value = prp_lh.layout.footer_text;
        prp_lhChanged(false);
    });

    $prp('prp-lh-reset')?.addEventListener('click', prp_lhResetToBase);

    // Image files dropped on the modal: on the designer panel → added at the end;
    // anywhere else in the modal → ignored (never opened by the browser).
    // Drops on the preview's header are handled inside the preview (prp_frameWire).
    const overlay = $prp('prp-overlay');
    overlay?.addEventListener('dragover', e => {
        if (!prp_hasFiles(e)) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = e.target.closest('#prp-lh') ? 'copy' : 'none';
    });
    overlay?.addEventListener('drop', e => {
        if (!prp_hasFiles(e)) return;
        e.preventDefault();
        if (e.target.closest('#prp-lh') && prp_lh.layout) prp_dropFile(e.dataTransfer.files[0], { mode: 'append' });
    });
    $prp('prp-lh-save')?.addEventListener('click', prp_lhSave);
    $prp('prp-lh-restore')?.addEventListener('click', prp_lhRestore);
});

document.addEventListener('keydown', e => {
    if (!$prp('prp-overlay')?.classList.contains('open')) return;
    if (e.key === 'Escape') return prp_close();
    prp_hdrKey(e);   // arrow keys nudge the selected header item (focus stays on this page while dragging)
});

function prp_setReady(ready) {
    $prp('prp-pdf').disabled  = !ready;
    $prp('prp-docx').disabled = !ready;
}

function prp_showError(message) {
    $prp('prp-err').textContent = message;
    $prp('prp-err').hidden = !message;
}

/**
 * Opens the preview with the caller's data and the DEFAULT letterhead.
 * opts.type — a PRP_TYPES key: 'CBA' (default), 'RFA', 'LAA', 'CAF', 'LAAREQ', 'PO', 'PR',
 * 'IAR', 'LV', 'JEV', 'FTLA', 'FTLAREQ', 'CFA', 'MEMO' or 'DV'.
 */
async function prp_open(payload, opts = {}) {
    prp_type = PRP_TYPES[String(opts.type || '').toUpperCase()] ? String(opts.type).toUpperCase() : 'CBA';
    const coe = String(payload?.document_type || '').toUpperCase() === 'COE';             // CBA / LAA have a COE version
    const regional = String(payload?.issuing_office || '').toUpperCase() === 'REGIONAL';  // CBA / CAF have a regional version
    $prp('prp-title').textContent = 'Print Preview — ' + PRP_TYPES[prp_type].title
        + (coe && (prp_type === 'CBA' || prp_type === 'LAA') ? ' (COE)' : '')
        + (regional && (prp_type === 'CBA' || prp_type === 'CAF') ? ' (Regional)' : '');
    prp_payload = Object.assign({}, payload || {});
    delete prp_payload.letterhead;   // letterhead edits belong to one open preview only
    const pdfOnly = !!PRP_TYPES[prp_type].pdfOnly;
    const letterhead = !pdfOnly && PRP_TYPES[prp_type].letterhead !== false;   // editable letterhead?
    $prp('prp-docx').hidden = pdfOnly;
    $prp('prp-lh-toggle').hidden = !letterhead;
    if (!letterhead && !$prp('prp-lh').hidden) prp_lhToggle();   // close a designer left open by another form
    const dialog = $prp('prp-overlay').querySelector('.nha-modal');   // landscape forms ('wide') get the xl modal
    dialog.classList.toggle('nha-modal-xl', !!PRP_TYPES[prp_type].wide);
    dialog.classList.toggle('nha-modal-lg', !PRP_TYPES[prp_type].wide);
    $prp('prp-overlay').classList.add('open');
    await (pdfOnly ? prp_renderPdf() : Promise.all([letterhead ? prp_lhLoad() : null, prp_render()]));
}

/* ── Module record → print payload ─────────────────────────────────────────────
 * One mapping per record type, shared by every page that prints it
 * (field names: system_docs/modules/PRINT-FORMS.md). */

/** A DV (GET /voucher/read/{id} — DisbursementVoucherService) → the DV form. */
function prp_dvPayload(dv) {
    const pct = r => (r != null && r !== '' ? ` ${Number(r)}%` : '');
    return {
        voucher_number: dv.dv_no || '',
        payee_name: dv.claimant_name || '',
        payee_address: dv.claimant_address || '',
        payee_tin: dv.employee_no_tin || '',
        particulars: dv.particulars || '',
        gross_amount: dv.gross_amount,
        // listed under the particulars: the deductions, then the withholding taxes (ATC lines)
        deductions: [
            ...(dv.deductions || []).map(d => ({ description: (d.name || 'Deduction') + pct(d.rate), amount: d.deduction_amount })),
            ...(dv.atc_lines || []).map(a => ({ description: ('Withholding tax ' + (a.atc_code || '')).trim() + pct(a.rate), amount: a.withholding_tax })),
        ],
        accounting_entries: (dv.jev?.lines || []).map(l => ({ account_code: l.account_code, debit: l.debit, credit: l.credit })),
    };
}

/** The JEV of a DV (dv.jev + its lines) → the JEV form. Account titles are filled in server-side. */
function prp_jevFromDv(dv) {
    const j = dv.jev || {};
    return {
        jev_number: j.jev_no || '',
        jev_date: j.jev_date || '',
        lines: (j.lines || []).map(l => ({
            account_code: l.account_code, debit: l.debit, credit: l.credit, description: l.description || '',
            sl_code: l.sl_code || '', sl_path: [l.sl_name, l.sl_detail_name].filter(Boolean).join(' › '),
        })),
        reference_no: dv.dv_no || '',
        payee_name: dv.claimant_name || '',
        particulars: j.particulars || dv.particulars || '',
        rfa_number: dv.rfa_allocation_number || '',
    };
}

/**
 * A PO's IAR (a row of GET /po-dr/list — PoDrService: po_number, iar_number, si_number / si_date,
 * receipts [{ dr_number, delivery_date, total_amount, entries [{ account_code, account_title, amount }],
 * items [{ description, serial_no, amount, property_no }] }], and the PO's vendor_name, po_date,
 * po_amount, dr_total) → the IAR form. Each item of a receipt is a line (property no. → stock no.,
 * amount → unit price, quantity 1); a receipt without items (they only apply from ₱50,000) prints as
 * one line — its account titles, its total. Date inspected = the latest delivery; partial delivery
 * while the delivered total is below the PO amount. `po` (optional) overrides the PO details.
 */
function prp_iarPayload(rec, po = {}) {
    const receipts = rec.receipts || [];
    const dates = receipts.map(d => d.delivery_date).filter(Boolean).sort();
    const delivered = Number(rec.dr_total) || receipts.reduce((s, d) => s + (Number(d.total_amount) || 0), 0);
    const poTotal = Number(po.total_amount ?? po.po_amount ?? rec.po_amount) || 0;
    const items = [];
    receipts.forEach(d => {
        const dr = d.dr_number ? `DR No. ${d.dr_number}` : '';
        if ((d.items || []).length) {
            d.items.forEach(it => items.push({
                stock_no: it.property_no || '',
                unit_cost: it.amount,
                quantity: 1,
                description: [it.description, it.serial_no ? `S/N ${it.serial_no}` : '', dr ? `(${dr})` : ''].filter(Boolean).join(' '),
            }));
        } else {
            const titles = (d.entries || []).map(e => e.account_title || e.account_code).filter(Boolean).join(', ');
            items.push({ stock_no: '', unit_cost: d.total_amount, quantity: 1,
                description: [titles, dr ? `(${dr})` : ''].filter(Boolean).join(' ') || dr });
        }
    });
    return {
        iar_number: rec.iar_number || '',
        po_number: rec.po_number || '',
        po_date: po.po_date || rec.po_date || '',
        supplier_name: po.vendor_name || rec.vendor_name || '',
        si_number: rec.si_number || '',
        si_date: rec.si_date || '',
        requesting_department: rec.requesting_office || '',
        items,
        date_inspected: dates.length ? dates[dates.length - 1] : '',
        delivery: poTotal > 0 && delivered + 0.005 < poTotal ? 'partial' : 'complete',
    };
}

/**
 * One delivery receipt of a PO's pro-forma JEV (GET /po-dr/proforma → data.receipts[i]) → the JEV form:
 * the rows as edited on screen (receipt.draft.rows — { code, title, debit, credit }, iar-module.php) when
 * there is a draft, else the stored rows ({ account_code, account_title, debit, credit }).
 */
function prp_jevFromProforma(poNumber, receipt) {
    const rows = receipt.draft
        ? receipt.draft.rows.map(r => ({ account_code: r.code, account_title: r.title, debit: r.debit, credit: r.credit }))
        : (receipt.rows || []);
    return {
        lines: rows.map(r => ({ account_title: r.account_title, account_code: r.account_code, debit: r.debit, credit: r.credit })),
        particulars: `Pro-forma JEV — Delivery Receipt No. ${receipt.dr_number} of PO No. ${poNumber}`,
    };
}

let prp_pdfUrl = null;   // blob URL of the PDF shown for a pdfOnly form

/** pdfOnly forms (the DV): fetch the PDF and show it in the frame (the browser's PDF viewer). */
async function prp_renderPdf() {
    const seq = ++prp_renderSeq;
    const current = $prp('prp-frame');
    prp_showError('');
    prp_setReady(false);
    current.hidden = true;
    $prp('prp-msg').hidden = false;
    $prp('prp-msg').textContent = 'Loading preview…';
    try {
        const json = await prp_request('pdf');
        if (seq !== prp_renderSeq) return;
        const bin = atob(json.data);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        if (prp_pdfUrl) URL.revokeObjectURL(prp_pdfUrl);
        prp_pdfUrl = URL.createObjectURL(new Blob([bytes], { type: 'application/pdf' }));
        // A sandboxed frame can't run the PDF viewer — this one is unsandboxed (blob: URL, our own PDF).
        const frame = current.cloneNode(false);
        frame.removeAttribute('srcdoc');
        frame.removeAttribute('sandbox');
        frame.hidden = false;
        frame.src = prp_pdfUrl;
        current.replaceWith(frame);
        $prp('prp-msg').hidden = true;
        prp_setReady(true);
    } catch (e) {
        if (seq !== prp_renderSeq) return;
        $prp('prp-msg').textContent = 'The preview could not be loaded.';
        prp_showError(e.message);
    }
}

let prp_renderSeq = 0;   // only the newest render may update the frame

/**
 * (Re)load the certificate HTML into the preview frame.
 * opts.silent — keep the current preview on screen and swap the new content
 * into it in place (no "Loading…", scroll position kept); used to confirm the
 * designer's instant header changes against the server's rendering.
 */
async function prp_render(opts = {}) {
    const current = $prp('prp-frame');
    const silent = !!opts.silent && !current.hidden && !!current.contentDocument?.body;
    const seq = ++prp_renderSeq;
    prp_showError('');
    if (!silent) {
        prp_setReady(false);
        current.hidden = true;
        $prp('prp-msg').hidden = false;
        $prp('prp-msg').textContent = 'Loading preview…';
    }

    try {
        const json = await prp_request('html');
        if (seq !== prp_renderSeq) return true;   // a newer render is on its way

        if (silent) {
            if (prp_dragging) {
                // Never swap the header out from under a drag — try again after it.
                clearTimeout(prp_timer);
                prp_timer = setTimeout(() => prp_render({ silent: true }), 600);
                return true;
            }
            // Same document, new content: nothing flashes and the scroll stays.
            const doc = $prp('prp-frame').contentDocument;
            const fresh = new DOMParser().parseFromString(json.html, 'text/html');
            doc.head.replaceWith(doc.importNode(fresh.head, true));
            doc.body.replaceWith(doc.importNode(fresh.body, true));
            prp_lhPatch();   // re-attach the header editing handles while designing
        } else {
            // Fresh iframe per full render — Chrome can leave a reused,
            // re-hidden iframe blank when its srcdoc is replaced.
            const frame = current.cloneNode(false);
            frame.removeAttribute('srcdoc');
            frame.removeAttribute('src');                        // after a pdfOnly form
            frame.setAttribute('sandbox', 'allow-same-origin');  // HTML preview: no scripts
            frame.hidden = false;
            frame.addEventListener('load', () => prp_lhPatch());
            frame.srcdoc = json.html;
            current.replaceWith(frame);
            $prp('prp-msg').hidden = true;
        }
        prp_setReady(true);
        return true;
    } catch (e) {
        if (seq !== prp_renderSeq) return false;
        if (!silent) $prp('prp-msg').textContent = 'The preview could not be loaded.';
        prp_showError(e.message);
        return false;
    }
}

/** Closing drops this print's letterhead edits. */
function prp_close() {
    $prp('prp-overlay').classList.remove('open');
    clearTimeout(prp_timer);
    if (prp_payload) delete prp_payload.letterhead;
    prp_lh.dirty = false;
}

async function prp_download(format) {
    const btn = $prp(format === 'pdf' ? 'prp-pdf' : 'prp-docx');
    const label = btn.textContent;
    prp_showError('');
    prp_setReady(false);
    btn.textContent = 'Generating…';
    try {
        const json = await prp_request(format);
        const bin = atob(json.data);
        const bytes = new Uint8Array(bin.length);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        const url = URL.createObjectURL(new Blob([bytes], { type: json.mime }));
        const a = document.createElement('a');
        a.href = url;
        a.download = json.filename;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);
    } catch (e) {
        prp_showError(e.message);
    } finally {
        btn.textContent = label;
        prp_setReady(true);
    }
}

/* ══ Letterhead designer ══════════════════════════════════════ */

function prp_lhToggle() {
    const panel = $prp('prp-lh');
    const show = panel.hidden;
    panel.hidden = !show;
    $prp('prp-body').classList.toggle('prp-lh-open', show);
    $prp('prp-lh-toggle').setAttribute('aria-expanded', String(show));
    $prp('prp-lh-toggle').textContent = show ? 'Hide Letterhead' : 'Edit Letterhead';
    if (show || prp_lh.dirty) {
        prp_lhPatch();   // designing: header items become draggable in the preview
    } else {
        prp_render({ silent: true });   // nothing changed: back to the exact default header
    }
}

/** The designer is open — the preview's header is editable. */
function prp_lhEditing() {
    return !$prp('prp-lh').hidden;
}

function prp_lhStatus(text, cls) {
    $prp('prp-lh-status').textContent = text || '';
    $prp('prp-lh-status').className = 'prp-lh-status' + (cls ? ' ' + cls : '');
}

/** Load what this print starts from: the saved default, else the built-in layout. */
async function prp_lhLoad() {
    try {
        const json = await prp_fetchJson(prp_letterheadApi(), { headers: prp_headers(false) });
        const d = json.data;
        prp_lh = { base: d.layout, layout: prp_clone(d.layout), dirty: false, saved: d.saved,
            canSave: d.can_save, hasFooter: d.has_footer !== false, factory: d.factory, assets: d.assets || {} };
        // Documents without a footer line (RFA) hide the footer editor.
        $prp('prp-lh-footer-sec').hidden = !prp_lh.hasFooter;
        $prp('prp-lh-footer-row').hidden = !prp_lh.hasFooter;
        prp_lhStatus(d.saved ? 'Starting from the saved default header.' : 'Starting from the original NHA header.');
    } catch (e) {
        prp_lh.layout = null;
        prp_lhStatus('The letterhead could not be loaded: ' + e.message, 'bad');
    }
    prp_lhRenderItems();
}

/**
 * Something changed: send the working layout with the print, update the
 * preview's header instantly, and confirm with a quiet server render once the
 * user pauses (the server's rendering is what the PDF / DOCX use).
 */
function prp_lhChanged(structural) {
    prp_lh.dirty = true;
    prp_payload.letterhead = prp_clone(prp_lh.layout);
    if (structural) prp_lhRenderItems(); else prp_lhTotal();
    prp_lhStatus('Changed for this print.');
    prp_lhPatch();
    clearTimeout(prp_timer);
    prp_timer = setTimeout(() => prp_render({ silent: true }), 900);
}

/* ── Instant header / footer in the preview ──
   Mirrors LetterheadLayoutService::toParts() + DocumentBranding::composeHeaderHtml():
   every size and position is in units — px on a 630 px wide letterhead — and is
   printed at the document's header width (CBA 468 pt, RFA 540 pt, LAA 480 pt). */
const PRP_LH_PX  = 630;
const PRP_AREA_H = 150;   // header area height in units (LetterheadLayoutService::AREA_HEIGHT)
const PRP_SNAP   = 4;     // snapping distance in units (hold Alt while dragging to turn it off)
const prp_hdrPt = () => PRP_TYPES[prp_type].hdrPt;   // printed header width (pt)
const prp_pt    = v => Math.round(v * (prp_hdrPt() / PRP_LH_PX) * 100) / 100 + 'pt';
const prp_round = v => Math.round(v * 10) / 10;
const prp_dims = {};   // image src → {w, h} (natural size)

function prp_imgSrc(item) {
    return item.src || prp_lh.assets[item.asset] || '';
}

/** Natural size of an image; unknown → load it and patch again when known. */
function prp_imgDims(src) {
    if (prp_dims[src]) return prp_dims[src].w ? prp_dims[src] : null;
    prp_dims[src] = {};
    const img = new Image();
    img.onload = () => { prp_dims[src] = { w: img.naturalWidth, h: img.naturalHeight }; if (!prp_dragging) prp_lhPatch(); };
    img.src = src;
    return null;
}

/** Height (units) of a title's text: each non-empty line at 1.15 × its size. */
function prp_titleH(it) {
    return [[it.line1, it.size1], [it.line2, it.size2]]
        .reduce((h, [text, size]) => h + ((text || '').trim() ? (Number(size) || 11) * 1.15 : 0), 0);
}

/** An item's box {w, h} in units, or null while an image's size is still unknown. */
function prp_itemBox(it) {
    if (it.type === 'title') {
        // An empty title prints nothing, but while designing it keeps a box to grab.
        return { w: Number(it.width) || 300, h: Math.max(prp_titleH(it), prp_lhEditing() ? 14 : 0) };
    }
    const d = prp_imgSrc(it) ? prp_imgDims(prp_imgSrc(it)) : null;
    if (!d) return null;
    const h = Number(it.height) || 55;
    return { w: h * d.w / d.h, h };
}

/** Keep an item's box on the header area. */
function prp_clampPos(it, b) {
    it.x = prp_round(Math.max(0, Math.min(PRP_LH_PX - Math.min(b.w, PRP_LH_PX), Number(it.x) || 0)));
    it.y = prp_round(Math.max(0, Math.min(PRP_AREA_H - Math.min(b.h, PRP_AREA_H), Number(it.y) || 0)));
}

/** Draw the header (and footer) from the working layout — when edited, or while designing. */
function prp_lhPatch() {
    const doc = $prp('prp-frame')?.contentDocument;
    const L = prp_lh.layout;
    if (doc?.body) prp_frameWire(doc);   // file drops + arrow keys in the preview
    if (PRP_TYPES[prp_type].letterhead === false) return;   // own title block — never paint a letterhead (state may be another form's)
    if (!doc || !L || (!prp_lh.dirty && !prp_lhEditing())) return;
    const hdr = doc.querySelector('.hdr');
    const ftr = doc.querySelector('.ftr-text');
    if (hdr) {
        hdr.replaceChildren(...prp_lhHeaderNodes(doc, L.items));
        // The outlines / handles / guides belong to designing only — gone as
        // soon as the designer is hidden (the edits themselves stay).
        hdr.classList.toggle('lh-editing', prp_lhEditing());
        if (!prp_lhEditing()) doc.getElementById('prp-edit-css')?.remove();
        prp_hdrDecorate(doc);
    }
    if (ftr) ftr.textContent = (L.footer_text || '').trim() || prp_lh.factory?.footer_text || '';
}

/** The header as the server prints it: a box with every item at its x / y. */
function prp_lhHeaderNodes(doc, items) {
    const editing = prp_lhEditing();
    const canvas = doc.createElement('div');
    canvas.className = 'lh-canvas';
    let bottom = 0;
    items.forEach((it, index) => {
        if (!editing && it.type === 'title' && !(it.line1 || '').trim() && !(it.line2 || '').trim()) return;
        const b = prp_itemBox(it);
        if (!b) return;   // patched again once the image has loaded
        const el = doc.createElement('div');
        el.className = 'lh-item';
        el.dataset.index = String(index);
        el.style.cssText = `position:absolute;left:${prp_pt(Number(it.x) || 0)};top:${prp_pt(Number(it.y) || 0)};`
            + `width:${prp_pt(b.w)};` + (it.type === 'title' ? `text-align:${it.align || 'center'};` : '');
        if (it.type === 'title') {
            [[it.line1, it.size1, it.bold1], [it.line2, it.size2, false]].forEach(([text, size, bold]) => {
                if (!(text || '').trim()) return;
                const div = doc.createElement('div');
                div.style.cssText = `font-family:Arial,sans-serif;font-size:${prp_pt(Number(size) || 11)};`
                    + `font-weight:${bold ? 'bold' : 'normal'};color:#000;line-height:1.15;white-space:nowrap;`;
                div.textContent = text.trim();
                el.appendChild(div);
            });
        } else {
            const img = doc.createElement('img');
            img.alt = '';
            img.draggable = false;
            img.src = prp_imgSrc(it);
            img.style.cssText = `display:block;width:${prp_pt(b.w)};height:${prp_pt(b.h)};`;
            el.appendChild(img);
        }
        canvas.appendChild(el);
        bottom = Math.max(bottom, (Number(it.y) || 0) + b.h);
    });
    if (!canvas.children.length && !editing) return [];
    // Designing: never shorter than 30 units, so an empty header can still take a drop.
    canvas.style.cssText = `position:relative;width:${prp_hdrPt()}pt;height:${prp_pt(editing ? Math.max(bottom, 30) : bottom)};margin:0 auto;`;
    return [canvas];
}

/* ── Free placement right in the preview's header (while designing) ──
   Drag an item anywhere on the header; it snaps to the centre line and to the
   other items' edges / middles (hold Alt to place freely). The selected item
   has a corner handle: images scale, titles get wider / narrower. Arrow keys
   nudge the selected item by 1 unit (Shift: 10). The preview iframe runs no
   scripts of its own; these listeners belong to this page. Outlines, handles
   and guides exist only on screen — the PDF and DOCX come from the server. */

let prp_dragging = false;   // a header drag is in progress
let prp_sel = -1;           // selected item index (highlighted in header + list)

// Every rule is scoped to `.hdr.lh-editing` (set only while the designer is open),
// so nothing of it can show on the preview once designing stops.
const PRP_EDIT_CSS =
    '.hdr.lh-editing{position:relative;}' +
    '.lh-editing .lh-canvas{outline:1px dashed rgba(98,101,126,.45);outline-offset:2px;}' +
    '.lh-editing .lh-item{cursor:grab;outline:1px dashed rgba(98,101,126,.55);outline-offset:1px;touch-action:none;user-select:none;}' +
    '.lh-editing .lh-item:hover{outline-color:#000E3E;background:rgba(0,14,62,.03);}' +
    '.lh-editing .lh-item.lh-sel{outline:2px solid #000E3E;z-index:5;}' +
    '.lh-editing .lh-item.lh-dragging{opacity:.75;cursor:grabbing;}' +
    '.lh-editing .lh-item.lh-empty::after{content:"(empty title)";font:italic 9pt Arial,sans-serif;color:#9aa4b2;}' +
    '.lh-editing .lh-handle{position:absolute;right:-6px;bottom:-6px;width:10px;height:10px;background:#fff;border:2px solid #000E3E;border-radius:2px;cursor:nwse-resize;z-index:6;}' +
    '.lh-editing .lh-item.lh-title .lh-handle{cursor:ew-resize;bottom:auto;top:50%;margin-top:-6px;}' +
    '.lh-editing .lh-guide{position:absolute;background:#d92d20;pointer-events:none;z-index:7;}' +
    '.lh-editing .lh-guide.v{top:-6pt;bottom:-6pt;width:1px;}' +
    '.lh-editing .lh-guide.h{left:-6pt;right:-6pt;height:1px;}' +
    '.lh-editing .lh-item.lh-drop-replace{outline:2px dashed #0f766e;background:rgba(15,118,110,.10);}' +
    '.lh-editing .lh-drop-point{position:absolute;width:14px;height:14px;margin:-7px 0 0 -7px;border-radius:50%;border:2px solid #0f766e;background:rgba(15,118,110,.25);pointer-events:none;z-index:8;}' +
    '.lh-editing .lh-tip{position:absolute;left:0;right:0;bottom:-15pt;text-align:center;font:8pt Arial,sans-serif;color:#62657E;pointer-events:none;}';

/** Make the preview's header items draggable / resizable / selectable. */
function prp_hdrDecorate(doc) {
    if (!prp_lhEditing()) return;
    if (!doc.getElementById('prp-edit-css')) {
        const st = doc.createElement('style');
        st.id = 'prp-edit-css';
        st.textContent = PRP_EDIT_CSS;
        doc.head.appendChild(st);
    }
    const hdr = doc.querySelector('.hdr');
    doc.querySelectorAll('.hdr .lh-item[data-index]').forEach(el => {
        const i = Number(el.dataset.index);
        const it = prp_lh.layout.items[i];
        el.classList.toggle('lh-title', it?.type === 'title');
        el.classList.toggle('lh-sel', i === prp_sel);
        el.classList.toggle('lh-empty', !el.textContent.trim() && !el.querySelector('img'));
        el.title = 'Drag to place anywhere · click to select · Alt = no snapping';
        if (i === prp_sel) {
            const handle = doc.createElement('div');
            handle.className = 'lh-handle';
            handle.title = it?.type === 'title' ? 'Drag to change the title box width' : 'Drag to resize';
            el.appendChild(handle);
        }
        el.addEventListener('pointerdown', e => prp_hdrPointerDown(e, doc, el));
    });
    if (hdr && !hdr.querySelector('.lh-tip')) {
        const tip = doc.createElement('div');
        tip.className = 'lh-tip';
        tip.textContent = 'Drag items anywhere · corner handle resizes · arrow keys nudge · drop an image file here to add it';
        hdr.appendChild(tip);
    }
}

/** Units per screen pixel on the preview's header right now. */
function prp_unitsPerPx(doc) {
    const c = doc.querySelector('.hdr .lh-canvas');
    const w = c ? c.getBoundingClientRect().width : 0;
    return w ? PRP_LH_PX / w : 1;
}

/**
 * Snap a moving box {x, y, w, h} to the header's centre / edges and to the
 * other items' edges and middles. Returns the snapped x / y and the guide
 * lines to show ({v: [x…], h: [y…]} in units).
 */
function prp_snap(box, skip) {
    const xs = [0, PRP_LH_PX / 2, PRP_LH_PX];
    const ys = [0];
    prp_lh.layout.items.forEach((it, i) => {
        if (i === skip) return;
        const b = prp_itemBox(it);
        if (!b) return;
        const x = Number(it.x) || 0, y = Number(it.y) || 0;
        xs.push(x, x + b.w / 2, x + b.w);
        ys.push(y, y + b.h / 2, y + b.h);
    });
    const best = (anchors, lines) => {
        let hit = null;
        anchors.forEach(([off, v]) => lines.forEach(line => {
            const d = Math.abs(line - v);
            if (d <= PRP_SNAP && (!hit || d < hit.d)) hit = { d, pos: line - off, line };
        }));
        return hit;
    };
    const hx = best([[0, box.x], [box.w / 2, box.x + box.w / 2], [box.w, box.x + box.w]], xs);
    const hy = best([[0, box.y], [box.h / 2, box.y + box.h / 2], [box.h, box.y + box.h]], ys);
    return {
        x: hx ? hx.pos : box.x,
        y: hy ? hy.pos : box.y,
        guides: { v: hx ? [hx.line] : [], h: hy ? [hy.line] : [] },
    };
}

function prp_showGuides(doc, guides) {
    const canvas = doc.querySelector('.hdr .lh-canvas');
    canvas?.querySelectorAll('.lh-guide').forEach(g => g.remove());
    if (!canvas || !guides) return;
    guides.v.forEach(x => {
        const g = doc.createElement('div');
        g.className = 'lh-guide v';
        g.style.left = prp_pt(x);
        canvas.appendChild(g);
    });
    guides.h.forEach(y => {
        const g = doc.createElement('div');
        g.className = 'lh-guide h';
        g.style.top = prp_pt(y);
        canvas.appendChild(g);
    });
}

function prp_hdrPointerDown(e, doc, el) {
    if (e.button !== 0) return;
    e.preventDefault();
    e.stopPropagation();
    const i = Number(el.dataset.index);
    const it = prp_lh.layout.items[i];
    const b0 = prp_itemBox(it);
    if (!it || !b0) return;
    const resizing = !!e.target.closest('.lh-handle');
    const k = prp_unitsPerPx(doc);
    const sx = e.clientX, sy = e.clientY;
    const x0 = Number(it.x) || 0, y0 = Number(it.y) || 0;
    const h0 = Number(it.height) || 55, w0 = Number(it.width) || 300;
    el.setPointerCapture?.(e.pointerId);
    prp_dragging = true;

    const canvas = doc.querySelector('.hdr .lh-canvas');
    const grow = () => {   // the header grows / shrinks with its lowest item, live
        let bottom = 0;
        prp_lh.layout.items.forEach(o => { const b = prp_itemBox(o); if (b) bottom = Math.max(bottom, (Number(o.y) || 0) + b.h); });
        if (canvas) canvas.style.height = prp_pt(Math.max(bottom, 30));
    };

    let moved = false;
    const onMove = ev => {
        const dx = (ev.clientX - sx) * k, dy = (ev.clientY - sy) * k;
        if (!moved && Math.abs(ev.clientX - sx) < 3 && Math.abs(ev.clientY - sy) < 3) return;
        if (!moved) { moved = true; el.classList.add('lh-dragging'); }

        if (resizing) {
            if (it.type === 'title') {
                it.width = prp_round(Math.max(20, Math.min(PRP_LH_PX - x0, w0 + dx)));
            } else {
                // Scale with the pointer, keeping the image's proportions.
                const s = Math.max((b0.w + dx) / b0.w, (b0.h + dy) / b0.h);
                it.height = prp_round(Math.max(10, Math.min(PRP_AREA_H - y0, h0 * s)));
            }
            const b = prp_itemBox(it);
            el.style.width = prp_pt(b.w);
            const img = el.querySelector('img');
            if (img) { img.style.width = prp_pt(b.w); img.style.height = prp_pt(b.h); }
            prp_showGuides(doc, null);
        } else {
            const snap = ev.altKey ? { x: x0 + dx, y: y0 + dy, guides: null } : prp_snap({ x: x0 + dx, y: y0 + dy, w: b0.w, h: b0.h }, i);
            it.x = snap.x;
            it.y = snap.y;
            prp_clampPos(it, b0);
            el.style.left = prp_pt(it.x);
            el.style.top = prp_pt(it.y);
            prp_showGuides(doc, snap.guides);
        }
        grow();
    };
    const onEnd = ev => {
        el.removeEventListener('pointermove', onMove);
        el.removeEventListener('pointerup', onEnd);
        el.removeEventListener('pointercancel', onEnd);
        prp_dragging = false;
        el.classList.remove('lh-dragging');
        prp_showGuides(doc, null);
        if (!moved || ev.type !== 'pointerup') {
            if (!moved) prp_lhSelect(i);
            return;
        }
        prp_sel = i;
        prp_lhChanged(true);
    };
    el.addEventListener('pointermove', onMove);
    el.addEventListener('pointerup', onEnd);
    el.addEventListener('pointercancel', onEnd);
}

/** Arrow keys move the selected item (1 unit, Shift: 10) while designing. */
function prp_hdrKey(e) {
    if (!prp_lhEditing() || prp_sel < 0 || !prp_lh.layout) return;
    if (e.target.closest?.('input, textarea, select, [contenteditable]')) return;
    const d = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] }[e.key];
    const it = prp_lh.layout.items[prp_sel];
    const b = it && prp_itemBox(it);
    if (!d || !b) return;
    e.preventDefault();
    const step = e.shiftKey ? 10 : 1;
    it.x = (Number(it.x) || 0) + d[0] * step;
    it.y = (Number(it.y) || 0) + d[1] * step;
    prp_clampPos(it, b);
    prp_lhChanged(true);
}

/** Select an item: outline it in the header and highlight its settings row. */
function prp_lhSelect(i) {
    prp_sel = i;
    prp_lhPatch();   // moves the resize handle onto it
    document.querySelectorAll('#prp-lh-items .prp-lh-item[data-index]').forEach(row => {
        const on = Number(row.dataset.index) === i;
        row.classList.toggle('is-sel', on);
        if (on) row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });
}

/** Add an item — placed centred at the top of the header unless it says where. */
function prp_lhAdd(item) {
    if (prp_lh.layout.items.length >= PRP_MAX_ITEMS) return prp_lhStatus(`The header can have at most ${PRP_MAX_ITEMS} items.`, 'bad');
    if (item.x == null) {
        const b = prp_itemBox(item) || { w: item.width || 55, h: item.height || 55 };
        item.x = (PRP_LH_PX - b.w) / 2;
        item.y = 0;
        prp_clampPos(item, b);
    }
    prp_lh.layout.items.push(item);
    prp_sel = prp_lh.layout.items.length - 1;
    prp_lhChanged(true);
}

/** Waits for an image's natural size (for placing a new image). */
function prp_imgReady(src) {
    return new Promise(resolve => {
        if (prp_dims[src]?.w) return resolve(prp_dims[src]);
        const img = new Image();
        img.onload = () => { prp_dims[src] = { w: img.naturalWidth, h: img.naturalHeight }; resolve(prp_dims[src]); };
        img.onerror = () => resolve(null);
        img.src = src;
    });
}

/** File input → image (any common type) → done(PNG data URL). */
function prp_lhReadPng(input, done) {
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    prp_imageFromFile(file).then(src => prp_imgReady(src).then(() => done(src)), e => prp_lhStatus(e.message, 'bad'));
}

/**
 * Any image the browser can open (PNG, JPG, WebP, GIF, …) → PNG data URL the
 * server accepts: a small PNG is kept as-is; anything else is drawn onto a
 * canvas (large photos scaled down — a header item never needs more) and
 * re-encoded as PNG, shrinking further until it is under 2 MB.
 */
async function prp_imageFromFile(file) {
    if (!file || !/^image\//.test(file.type)) throw new Error('That file is not an image.');
    const readUrl = blob => new Promise((resolve, reject) => {
        const r = new FileReader();
        r.onload = () => resolve(String(r.result));
        r.onerror = () => reject(new Error('The file could not be read.'));
        r.readAsDataURL(blob);
    });
    const url = URL.createObjectURL(file);
    try {
        const img = await new Promise((resolve, reject) => {
            const im = new Image();
            im.onload = () => resolve(im);
            im.onerror = () => reject(new Error('This image type cannot be used — try PNG or JPG.'));
            im.src = url;
        });
        const w0 = img.naturalWidth, h0 = img.naturalHeight;
        if (!w0 || !h0) throw new Error('The image is empty.');
        let k = Math.min(1, 2400 / w0, 1000 / h0);
        if (file.type === 'image/png' && k === 1 && file.size <= PRP_IMAGE_MAX_BYTES) return await readUrl(file);

        for (let tries = 0; tries < 6; tries++, k *= 0.75) {
            const canvas = document.createElement('canvas');
            canvas.width = Math.max(10, Math.round(w0 * k));
            canvas.height = Math.max(10, Math.round(h0 * k));
            canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
            const png = canvas.toDataURL('image/png');
            if ((png.length - 22) * 0.75 <= PRP_IMAGE_MAX_BYTES) return png;
        }
        throw new Error('The image is too large even after resizing.');
    } finally {
        URL.revokeObjectURL(url);
    }
}

/* ── Drop an image file from the computer onto the header ──
   On the middle of an image item → replaces it (green outline);
   anywhere else on the header → added right where it was dropped (green dot).
   Not designing → opens the designer and adds the image. */

const prp_hasFiles = e => [...(e.dataTransfer?.types || [])].includes('Files');

/** Listeners on the preview document (once per document; survive quiet refreshes). */
function prp_frameWire(doc) {
    if (!doc || doc.__prpWired) return;
    doc.__prpWired = true;
    doc.addEventListener('keydown', prp_hdrKey);
    doc.addEventListener('dragover', e => {
        if (!prp_hasFiles(e)) return;
        e.preventDefault();   // never let the browser open the file instead
        const t = prp_dropTarget(doc, e);
        e.dataTransfer.dropEffect = t ? 'copy' : 'none';
        prp_dropShow(doc, t);
    });
    doc.addEventListener('dragleave', e => { if (!e.relatedTarget) prp_dropShow(doc, null); });
    doc.addEventListener('drop', e => {
        if (!prp_hasFiles(e)) return;
        e.preventDefault();
        const t = prp_dropTarget(doc, e);
        prp_dropShow(doc, null);
        if (t) prp_dropFile(e.dataTransfer.files[0], t);
    });
}

/** Where a file dropped at this point would go, or null (outside the header). */
function prp_dropTarget(doc, e) {
    const hdr = doc.querySelector('.hdr');
    if (!hdr || !prp_lh.layout) return null;
    const hr = hdr.getBoundingClientRect();
    // Forgiving: anywhere from the top of the page down to just below the header.
    if (e.clientY > hr.bottom + 24) return null;
    if (!prp_lhEditing()) return { mode: 'append' };

    const items = prp_lh.layout.items;
    for (const el of [...doc.querySelectorAll('.hdr .lh-item[data-index]')].reverse()) {   // topmost first
        const r = el.getBoundingClientRect();
        const i = Number(el.dataset.index);
        if (items[i]?.type === 'image' && e.clientX >= r.left + r.width * 0.2 && e.clientX <= r.right - r.width * 0.2
            && e.clientY >= r.top + r.height * 0.2 && e.clientY <= r.bottom - r.height * 0.2) {
            return { mode: 'replace', index: i, el };
        }
    }
    const canvas = doc.querySelector('.hdr .lh-canvas');
    if (!canvas) return { mode: 'append' };
    const cr = canvas.getBoundingClientRect();
    const k = prp_unitsPerPx(doc);
    return { mode: 'place', x: (e.clientX - cr.left) * k, y: Math.max(0, (e.clientY - cr.top) * k), px: e.clientX - cr.left, py: e.clientY - cr.top };
}

/** Show where the drop will land (dot / outline), or clear it (t = null). */
function prp_dropShow(doc, t) {
    doc.querySelectorAll('.hdr .lh-drop-replace').forEach(el => el.classList.remove('lh-drop-replace'));
    let dot = doc.getElementById('prp-drop-point');
    if (!t || t.mode !== 'place') dot?.remove();
    if (!t) return;
    if (t.mode === 'replace') { t.el.classList.add('lh-drop-replace'); return; }
    if (t.mode === 'place') {
        if (!dot) {
            dot = doc.createElement('div');
            dot.id = 'prp-drop-point';
            dot.className = 'lh-drop-point';
            doc.querySelector('.hdr .lh-canvas')?.appendChild(dot);
        }
        dot.style.left = t.px + 'px';
        dot.style.top = t.py + 'px';
    }
}

/** Put a dropped image file where the drop target says. */
async function prp_dropFile(file, t) {
    let src;
    try {
        src = await prp_imageFromFile(file);
        await prp_imgReady(src);
    } catch (e) {
        if (!prp_lhEditing()) prp_lhToggle();
        return prp_lhStatus(e.message, 'bad');
    }
    if (!prp_lhEditing()) prp_lhToggle();
    const items = prp_lh.layout.items;
    if (t.mode === 'replace') {
        items[t.index].src = src;
        delete items[t.index].asset;
        prp_sel = t.index;
        prp_lhChanged(true);
        prp_lhStatus('Image replaced for this print.');
        return;
    }
    const item = { type: 'image', src, height: 55 };
    if (t.mode === 'place') {
        const b = prp_itemBox(item);
        item.x = t.x - b.w / 2;   // centred on the drop point
        item.y = t.y - b.h / 2;
        prp_clampPos(item, b);
    }
    prp_lhAdd(item);
    if (prp_lh.layout.items.includes(item)) prp_lhStatus('Image added for this print.');
}

/* small DOM helpers */
function prp_el(tag, attrs, children) {
    const el = document.createElement(tag);
    Object.entries(attrs || {}).forEach(([k, v]) => {
        if (k === 'text') el.textContent = v;
        else if (k === 'on') Object.entries(v).forEach(([ev, fn]) => el.addEventListener(ev, fn));
        else if (v === true) el.setAttribute(k, '');
        else if (v !== false && v != null) el.setAttribute(k, v);
    });
    (children || []).forEach(c => c && el.appendChild(c));
    return el;
}

function prp_num(label, value, min, max, step, onInput) {
    return prp_el('label', { class: 'prp-lh-field' }, [
        document.createTextNode(label),
        prp_el('input', { type: 'number', class: 'prp-lh-in num', min, max, step, value,
            on: { input: e => { if (e.target.value !== '') onInput(Number(e.target.value)); } } }),
    ]);
}

/** Draw one row per header item (left to right). */
function prp_lhRenderItems() {
    const wrap = $prp('prp-lh-items');
    const L = prp_lh.layout;
    const can = !!prp_lh.canSave;
    $prp('prp-lh-save').hidden = !can;
    $prp('prp-lh-restore').hidden = !(can && prp_lh.saved);
    $prp('prp-lh-restore').textContent = 'Restore original';
    delete $prp('prp-lh-restore').dataset.armed;
    if (!L) { wrap.replaceChildren(); return; }

    $prp('prp-lh-footer').value = L.footer_text || '';
    $prp('prp-lh-add-image').disabled = L.items.length >= PRP_MAX_ITEMS;
    $prp('prp-lh-add-title').disabled = L.items.length >= PRP_MAX_ITEMS;

    if (!L.items.length) {
        wrap.replaceChildren(prp_el('div', { class: 'prp-lh-item', style: 'grid-template-columns:1fr;',
            text: 'No header items — the printout has no header. Add an image or a title.' }));
        return;
    }

    const rows = L.items.map((item, i) => {
        const set = (key, val) => { item[key] = val; prp_lhChanged(false); };
        let body;
        if (item.type === 'image') {
            const input = prp_el('input', { type: 'file', accept: 'image/*',
                on: { change: e => prp_lhReadPng(e.target, src => { item.src = src; delete item.asset; prp_lhChanged(true); }) } });
            body = prp_el('div', { class: 'prp-lh-body' }, [
                prp_el('span', { class: 'prp-lh-badge', text: 'Image' }),
                prp_el('div', { class: 'prp-lh-thumb' }, [prp_el('img', { alt: '', src: item.src || prp_lh.assets[item.asset] || '' })]),
                input,
                prp_el('button', { type: 'button', class: 'prp-lh-icon', text: 'Replace…', on: { click: () => input.click() } }),
                prp_num('Size', item.height, 15, 120, 1, v => set('height', v)),
            ]);
        } else {
            body = prp_el('div', { class: 'prp-lh-body' }, [
                prp_el('span', { class: 'prp-lh-badge', text: 'Title' }),
                prp_el('input', { type: 'text', class: 'prp-lh-in txt', maxlength: 80, value: item.line1, 'aria-label': 'Title line 1',
                    on: { input: e => set('line1', e.target.value) } }),
                prp_num('pt', item.size1, 6, 28, 0.5, v => set('size1', v)),
                prp_el('label', { class: 'prp-lh-field' }, [
                    prp_el('input', { type: 'checkbox', checked: !!item.bold1, on: { change: e => set('bold1', e.target.checked) } }),
                    document.createTextNode('Bold'),
                ]),
                prp_el('input', { type: 'text', class: 'prp-lh-in txt', maxlength: 80, value: item.line2, placeholder: 'Line 2 (optional)',
                    'aria-label': 'Title line 2', on: { input: e => set('line2', e.target.value) } }),
                prp_num('pt', item.size2, 5, 20, 0.5, v => set('size2', v)),
            ]);
        }

        // Position (units on the 630-wide header); a title also has its box width + alignment.
        const setPos = (key, val) => {
            item[key] = val;
            const b = prp_itemBox(item);
            if (b) prp_clampPos(item, b);
            prp_lhChanged(false);
        };
        const place = prp_el('div', { class: 'prp-lh-place' }, [
            prp_num('X', item.x, 0, PRP_LH_PX, 1, v => setPos('x', v)),
            prp_num('Y', item.y, 0, PRP_AREA_H, 1, v => setPos('y', v)),
            ...(item.type === 'title' ? [
                prp_num('Width', item.width, 20, PRP_LH_PX, 1, v => set('width', v)),
                prp_el('select', { class: 'prp-lh-in', 'aria-label': 'Text alignment', on: { change: e => set('align', e.target.value) } },
                    ['left', 'center', 'right'].map(a => prp_el('option', { value: a, text: a[0].toUpperCase() + a.slice(1), selected: item.align === a }))),
            ] : []),
        ]);
        const center = () => {
            const b = prp_itemBox(item);
            if (!b) return;
            item.x = prp_round((PRP_LH_PX - b.w) / 2);
            prp_lhChanged(true);
        };
        const tools = prp_el('div', { class: 'prp-lh-place' }, [
            prp_el('button', { type: 'button', class: 'prp-lh-icon', title: 'Centre across the page', 'aria-label': 'Centre', text: '↔', on: { click: center } }),
            prp_el('button', { type: 'button', class: 'prp-lh-icon', title: 'Remove', 'aria-label': 'Remove', text: '✕',
                on: { click: () => { L.items.splice(i, 1); prp_sel = -1; prp_lhChanged(true); } } }),
        ]);
        // Press the ⠿ grip and drag onto another row to change the drawing order
        // (lower rows are drawn on top). Pointer events: works with mouse and touch.
        const grip = prp_el('div', { class: 'prp-lh-no', title: 'Drag to change which item is drawn on top', text: '⠿ ' + (i + 1) });
        grip.addEventListener('pointerdown', e => prp_dragStart(e, i, grip));
        return prp_el('div', { class: 'prp-lh-item' + (i === prp_sel ? ' is-sel' : ''), 'data-index': i }, [grip, body, place, tools]);
    });

    rows.push(prp_el('div', { class: 'prp-lh-help', id: 'prp-lh-total', style: 'margin-top:4px;' }));
    wrap.replaceChildren(...rows);
    prp_lhTotal();
    // Measure every image now, so the first instant update already has their sizes.
    L.items.forEach(it => { if (it.type === 'image' && prp_imgSrc(it)) prp_imgDims(prp_imgSrc(it)); });
}

/** Item row under a screen point, or null. */
function prp_rowAt(x, y) {
    const el = document.elementFromPoint(x, y)?.closest('#prp-lh-items .prp-lh-item[data-index]');
    return el || null;
}

function prp_dragStart(e, from, grip) {
    if (e.button !== undefined && e.button !== 0) return;
    e.preventDefault();
    const wrap = $prp('prp-lh-items');
    const srcRow = grip.closest('.prp-lh-item');
    srcRow.classList.add('is-dragging');
    let target = null;

    const move = ev => {
        const row = prp_rowAt(ev.clientX, ev.clientY);
        if (row !== target) {
            target?.classList.remove('is-drop');
            target = row && row !== srcRow ? row : null;
            target?.classList.add('is-drop');
        }
    };
    const end = ev => {
        window.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', end);
        window.removeEventListener('pointercancel', end);
        window.removeEventListener('blur', end);
        if (ev?.type === 'pointerup') move(ev);   // the release point decides the target
        wrap.querySelectorAll('.is-dragging, .is-drop').forEach(el => el.classList.remove('is-dragging', 'is-drop'));
        if (!target || ev?.type !== 'pointerup') return;
        const to = Number(target.dataset.index);
        const items = prp_lh.layout.items;
        const [moved] = items.splice(from, 1);
        items.splice(to, 0, moved);
        prp_lhChanged(true);
    };
    window.addEventListener('pointermove', move);
    window.addEventListener('pointerup', end);
    window.addEventListener('pointercancel', end);
    window.addEventListener('blur', end);
}

function prp_lhTotal() {
    const el = $prp('prp-lh-total');
    if (!el || !prp_lh.layout) return;
    el.textContent = `X / Y are the item's top-left corner on the header (${PRP_LH_PX} wide, up to ${PRP_AREA_H} tall); `
        + 'the printed header is as tall as its lowest item. Drag ⠿ to change which item is drawn on top (lower rows are on top).';
}

/** Back to what this print started from (the saved default or the original). */
async function prp_lhResetToBase() {
    prp_lh.layout = prp_clone(prp_lh.base);
    prp_lh.dirty = false;
    delete prp_payload.letterhead;
    prp_lhRenderItems();
    prp_lhStatus(prp_lh.saved ? 'Back to the saved default header.' : 'Back to the original NHA header.');
    await prp_render({ silent: true });
}

/** Saver roles: make the current design everyone's default header. */
async function prp_lhSave() {
    const btn = $prp('prp-lh-save');
    btn.disabled = true;
    prp_lhStatus('Saving…');
    try {
        const json = await prp_fetchJson(prp_letterheadApi(), {
            method: 'POST', headers: prp_headers(true), body: JSON.stringify({ layout: prp_lh.layout }),
        });
        prp_lh.base = json.data.layout;
        prp_lh.layout = prp_clone(json.data.layout);
        prp_lh.saved = true;
        prp_lh.dirty = false;
        delete prp_payload.letterhead;   // the saved default is now what prints
        prp_lhRenderItems();
        prp_lhStatus(json.message + ' Everyone\'s prints use it from now on.', 'ok');
        await prp_render({ silent: true });
    } catch (e) {
        prp_lhStatus(e.message, 'bad');
    } finally {
        btn.disabled = false;
    }
}

/** Saver roles: drop the saved default (click twice to confirm). */
async function prp_lhRestore() {
    const btn = $prp('prp-lh-restore');
    if (!btn.dataset.armed) {
        btn.dataset.armed = '1';
        btn.textContent = 'Click again to restore the original';
        setTimeout(() => { if (btn.dataset.armed) { delete btn.dataset.armed; btn.textContent = 'Restore original'; } }, 4000);
        return;
    }
    delete btn.dataset.armed;
    btn.disabled = true;
    try {
        const json = await prp_fetchJson(prp_letterheadApi(), { method: 'DELETE', headers: prp_headers(false) });
        prp_lh.base = json.data.layout;
        prp_lh.layout = prp_clone(json.data.layout);
        prp_lh.saved = false;
        prp_lh.dirty = false;
        delete prp_payload.letterhead;
        prp_lhRenderItems();
        prp_lhStatus(json.message, 'ok');
        await prp_render({ silent: true });
    } catch (e) {
        prp_lhStatus(e.message, 'bad');
    } finally {
        btn.disabled = false;
    }
}
</script>
