# Print Forms — CBA, RFA, LAA, CAF, CFA, Memo Request, LAA release request, PO, PR, IAR, LV, JEV, FTLA, FTLA request and DV

Printed forms for every department, built **one form per document**: when the
same document exists in several old department modules (FMD, REGIONAL, GSD,
GLOBAL, ACCOUNTING, TREASURY), it is built once here and each department's
differences are options in that form (payload fields / variants), never a copy.

Built so far: the **CBA** (Certificate of Budget Allocation, CO / COE), the
**RFA** (Request for Fund Allotment), the **LAA** (Letter of Advice of
Allotment, CO / COE), the **CAF** (Certificate as to Availability of Funds) and
the **LAA release request** (the regional office's memorandum). All share one
print pipeline (`PrintService`), the preview modal
(`prp_open(payload, { type: 'CBA' | 'RFA' | 'LAA' | 'CAF' | 'LAAREQ' })`) and the
letterhead designer (saved default kept per document). The **PO**, **PR**,
**IAR**, **LV**, **JEV**, **FTLA** and **FTLA request** follow the same pattern;
the **DV** is PDF only — a pdftk fill of the official form (see "DV —
Disbursement Voucher").

### Department options

| Form | FMD (default) | REGIONAL (`issuing_office: 'REGIONAL'`) | GLOBAL |
|---|---|---|---|
| CBA | CO / COE (`document_type`) | regional wording + signatory — see "Regional CBA" | used FMD's generators — nothing to add |
| CAF | FMD wording | 3 wordings (`body_variant`), "Certified:" block — see "Regional CAF" | — |
| RFA | forms 1a–2d (`form_variant`) | "RFA Form No." label, "Request for Fund Allotment" + bold "Re:" particulars — see RFA | used FMD's generators |
| LAA | CO / COE | the region's "LAA" is a different document → the **LAA release request** form (`LAAREQ`) | used FMD's generators |
| PO | FMD printed the screen only | same form as GSD (REGIONAL's DOCX + HTML merged) | printed the screen only |
| PR | FMD printed the screen only | same form as GSD (REGIONAL's DOCX + HTML merged) | printed the screen only |
| IAR | — (only ACCOUNTING's PIS printed it — one form) | — | — |
| LV | the obligation ledger (`obligation`) in the OBLIGATION column — FMD's pre-printed-stock print | the full form (REGIONAL's DOCX + PDF merged): cash-advance breakdown, petty cash summary page | — |
| JEV | the NHA paper JEV (DCD layout — the default); GAD: `office` "MAIN OFFICE", `approved_by_label` "Approved by:", `responsibility_center` | the same form with the regional office as `office` (replaces REGIONAL's simplified preview) | already printed this form (`JevServices::renderPrintHtml`) |
| FTLA | FMD's fund transfer letter = TREASURY's (one letter); TREASURY's variants are options: `subject`, signatories, the DRFTLA `dv_numbers` footer | the region's FTLA is a different document → the **FTLA request** memo (`FTLAREQ`) | — |
| DV | MAIN route (`route`, default): FMD manager + accountant certify Box B | `route: 'REGIONAL'`: accountant alone in Box B, approving officer in Box C | — |

The **PO** merges GSD's and REGIONAL's printouts (they were the same form) — see
"PO — Purchase Order".

Compared field by field with the old REGIONAL generators (`REGIONAL/scripts/gen_{cba,caf,rfa,laa}_docx.js`)
and `BudgetAllocationPdfService` (2026-11-10).

## Structure

Follows the repo's conventions: one flat, auto-loaded service per feature in
`app/services/`, one route file per service in `app/routes/`, readone partials
in `app/pages/readone/`, DOCX generators in `scripts/` (as in the other FIMS
modules' `scripts/`).

```
app/services/PrintService.php        shared pipeline: DOCS registry, print(doc, payload, format),
                                     PDF (Dompdf), DOCX (node), header / footer, amount in words,
                                     money, dates, signatory lookup — static helpers
app/services/CbaPrintService.php     CBA (CO / COE / regional): model(), html(); STYLE + style()
app/services/CafPrintService.php     CAF (FMD / regional): model(); html() = the CBA's layout
app/services/RfaPrintService.php     RFA (forms 1a–2d, regional labels): model(), html()
app/services/LaaPrintService.php     LAA (CO / COE): model(), html()
app/services/LaaRequestPrintService.php   LAA release request (regional memo): model(), html() — the shared memo layout
app/services/FtlaPrintService.php    FTLA (letter to the bank): model(), html()
app/services/FtlaRequestPrintService.php  FTLA request (regional memo): model(); html() = the LAA release request's
app/services/CfaPrintService.php     CFA (funding-request memo FMD certifies): model(); html() = the LAA release request's
app/services/MemoRequestPrintService.php  MEMO (originating unit's memo request, non-FMD): model(), bodyBlocks(); html() = the LAA release request'sapp/services/PurchaseOrderPrintService.php PO: model() (from po_id and/or payload), html()
app/services/PurchaseRequestPrintService.php PR: model(), html()
app/services/IarPrintService.php     IAR: model(), html()
app/services/LvPrintService.php      LV: model(), html()
app/services/JevPrintService.php     JEV: model(), html() — A4 landscape
app/services/DvPrintService.php      DV (PDF only): model(), pdf() — pdftk fill of the official form
app/services/LetterheadLayoutService.php   letterhead layout + saved default per document
app/routes/print.php                 POST /api/print/{doc}, GET /api/print/resolve-signatory
app/routes/letterhead.php            GET / POST / DELETE /api/letterhead/{doc}
app/pages/readone/print-preview-readone.php   preview modal + letterhead designer (prefix prp_)
scripts/gen_{cba,rfa,laa,laareq,po,pr,iar,lv,jev,ftla}_docx.js   Word layouts (the CAF uses gen_cba_docx.js,
                                     the FTLA request, the CFA and the MEMO gen_laareq_docx.js)
_shared/template/dv_form.pdf, dv_background.pdf, dv.pdf   DV fillable forms + background
```

**Certificate styles.** The CBA, the CAF and their regional versions are one
layout (`CbaPrintService::html()` / `gen_cba_docx.js`). How each looks is the
model's `style` — `CbaPrintService::STYLE` is the CBA's own look and each variant
changes only its keys via `CbaPrintService::style([...])`: `size`, `title_size`,
`title_bold`, `title_italic`, `date_italic`, `date_align`, `indent_pt`,
`line_height`, `name_italic`, `foot_size`, `foot_italic`, `foot_bold`
(`label` | `value` | `none`), `allocnum_bold`, `sig_layout` (`right` | `certified`).

Flow: page Print button → `prp_open(payload, {type})` → `POST /api/print/{doc}` →
`PrintService::print()` → the form's `model()` + letterhead → `html()` (preview,
and the PDF through Dompdf) or `scripts/gen_<form>_docx.js` (DOCX).

Form services call the shared helpers statically (`PrintService::money(...)`)
rather than extending a base class: services load alphabetically
(`app/api.php`), so `CbaPrintService.php` loads before `PrintService.php`.

### Adding a form

1. `app/services/XxxPrintService.php` — `model(array $p): array` (every printed
   word; returns at least `file_base`) and `html(array $m, string $media): string`
   (use `PrintService::headerHtml()`, `repeatingHeaderCss()`, `bodyOpen()` /
   `bodyClose()` for the letterhead). Department variants are options here. A
   form that shares another's layout calls that form's `html()` (as
   `CafPrintService` does with `CbaPrintService`).
2. One line in `PrintService::DOCS` (service, generator, paper, PDF font;
   `'orientation' => 'landscape'` for a landscape form) and — only when it uses
   the NHA letterhead — in `LetterheadLayoutService::DOCUMENTS` (has a footer
   line?). A form with its own printed title block stays out of `DOCUMENTS`.
3. `scripts/gen_xxx_docx.js` — only when no existing layout fits (the CAF reuses
   the CBA's).
4. The preview's `PRP_TYPES` (title, `/print/xxx`; header width in pt for a
   letterhead form, or `letterhead: false` for one with its own title block;
   `wide: true` opens a landscape form in the xl modal), and a section in this doc.

### Naming

- **Print service:** when the form's module already has a data service here,
  name it after that service, so the two sit side by side —
  `PurchaseOrderService` → `PurchaseOrderPrintService`, and the PR →
  `PurchaseRequestPrintService`. Forms NHA calls by their acronym, with no data
  service here, use the acronym in PascalCase: `CbaPrintService`,
  `CafPrintService`, `RfaPrintService`, `LaaPrintService`, `DvPrintService`,
  `LvPrintService`, `JevPrintService`, `IarPrintService`, `FtlaPrintService`.
- **`PrintService::DOCS` key / URL:** the form's short code — `CBA`, `PO`, `DV`
  → `POST /api/print/cba`, `/print/po`, `/print/dv`.
- **DOCX generator:** `scripts/gen_<code>_docx.js` in lower case (`gen_po_docx.js`),
  as in the other FIMS modules' `scripts/`.
- **Images:** lower-kebab file names under `_shared/images/letterhead/`.

**Header on every page (all forms):** the DOCX letterhead is a Word header,
so it repeats on every page. The PDF does the same: in print media the header
(and the footer line) sit directly in `<body>` as `position:fixed` elements,
which Dompdf repeats on every page, and the `@page` top margin reserves the
header's real height (`headerHeightPt()` / `repeatingHeaderCss()` /
`bodyOpen()` in `PrintService`) — page 1 looks the same, later pages
keep the letterhead. The on-screen preview is one continuous sheet.

**Long text (PDF):** Dompdf can't split a table row across pages (a row taller
than a page leaves a blank page and runs into the footer), so long text is
kept out of rows: the LAA description stays beside the amount block up to
`LAA_SIDE_BY_SIDE_MAX` (600) characters and flows below it when longer; RFA
particulars lines are plain blocks, the last one sharing a row with the amount
unless it is longer than `RFA_AMOUNT_ROW_MAX` (300) characters. Normal-length
text prints exactly as the template; the DOCX (Word splits rows) is unchanged.

**Long words:** a run with no spaces (a reference, a URL, test input) breaks at
the edge on every form, as the form's textarea wraps it — `PrintService::html()`
adds `word-wrap / overflow-wrap: break-word` to each form's style (Word already
breaks such words in the DOCX).

## CBA — Certificate of Budget Allocation

Status: **print form only** (2026-11-09). The CBA request workflow (create / save /
approve, reading `fims.budget_allocation`) is not built yet; the print takes its
data from a payload the caller sends. It is not yet wired into any page or menu.

Ported from the old FMD module (`FMD/scripts/gen_cba_docx.js` +
`gen_coe_cba_docx.js`, `_shared/FMD/budget-allocation-print/js/print-preview.js`),
rebuilt on this repo's components.

## Pieces

| File | Role |
|---|---|
| `app/services/CbaPrintService.php` | `model()` holds all certificate wording; `html()` (preview + PDF) |
| `app/services/PrintService.php` | Pipeline: Dompdf PDF, DOCX via Node, letterhead, signatory lookup |
| `app/services/LetterheadLayoutService.php` | Letterhead layout (header items + footer text): built-in layout, validation, saved default (`fims.letterhead_layout`), conversion to header parts |
| `app/routes/print.php` | `POST /api/print/cba` (`format` = html / pdf / docx), `GET /api/print/resolve-signatory` |
| `app/routes/letterhead.php` | `GET / POST / DELETE /api/letterhead/{doc}` — editor data, save default, restore original |
| `scripts/gen_cba_docx.js` | Lays out the model as DOCX (`docx` npm package) |
| `app/pages/readone/print-preview-readone.php` | Preview modal: `prp_open(payload, { type })`, Download PDF / DOCX, Edit Letterhead designer |
| `_shared/images/letterhead/nha-letterhead-banner.png` | Built-in banner (printed when nothing is edited or saved) |
| `_shared/images/letterhead/{nha-logo,pambansang-pabahay,bagong-pilipinas}.png` | Built-in header items (cut from the banner) — asset keys `logo` / `brand` / `seal` in layouts |
| `_shared/DocumentBranding.php`, `_shared/docx-branding.js` | Shared letterhead composer — extended (additively) so callers can pass header parts directly; DOCX title text now scales with the header width like the HTML side |
| `_assets/sql/01 - fims/01 - table/16 - fims_letterhead_layout.sql` | Table for the saved default header |

One model renders all three outputs, so the preview, PDF and DOCX always match.

## Payload (all optional)

`allocation_number`, `document_type` (`CO` default / `COE`), `project_description`,
`requested_amount`, `allocated_amount`, `fiscal_year_label` (or `fiscal_year`),
`fund_source_name`, `program_of_cob`, `category_name`, `is_bidding` (default true),
`date` (default today, "F d, Y"), `approved_by_name` ("NAME" or "NAME — Title"),
`letterhead` (`{items, footer_text}` — this print's letterhead, see below).

## Rules carried over from FMD

- Amount printed = `requested_amount`, else `allocated_amount`; a "Cash Requirement"
  phrase is added whenever `allocated_amount` > 0.
- **CO**: bold title; validity says "for bidding purposes" unless `is_bidding` is false;
  "Sources of Fund" drops the trailing "(CODE)" and appends "(Program of COB)";
  "Program: <category>" only when there is a Cash Requirement; two reminders.
- **COE**: plain title, first-line indent, "for whatever legal purpose it may serve",
  fund source printed as sent, one reminder.
- Signatory: `approved_by_name`, else the first user (lowest `user_id`) holding the
  `uac.user_levels` role **FMD Department Manager**, else the old FMD default name.

### Regional CBA (`issuing_office: 'REGIONAL'`)

From the old REGIONAL `gen_cba_docx.js` / `BudgetAllocationPdfService::buildCbaHtml()`
(`CbaPrintService::regionalModel()`):

- One italic paragraph, nothing bold: "This is to certify that **budgetary
  requirement** necessary to cover the <description> in the amount of <One
  Thousand and 00/100 Pesos> (P<amount>) **is** provided for in the Approved
  Corporate Operating Budget for CY <year>." (centavos always written; no year →
  "… Operating Budget.")
- Validity always "for whatever legal purpose it may serve and valid up to
  December 31, <year>." (no "bidding purposes", no "only").
- Signatory from the payload — `signatory_name` (or `approved_by_name`),
  `signatory_title`, `signatory_office` — name bold italic, title and office
  italic. Not looked up: the regional signatory isn't the FMD Department Manager.
- Footnote: the CBA number and "Source of Funds: <fund>" in plain italic. No
  "Program:" line, no reminders.
- 12pt at 1.5 lines, title 15pt bold, paragraphs indented 1in.
- Payload: `allocation_number` (or `cba_no`), `date`, `project_description` (or
  `description`), `amount` (else `allocated_amount` / `requested_amount` — the
  regional order), `fiscal_year_label` (or `fiscal_year`), `fund_source_name` (or
  `source_of_funds`), the signatory fields above. Preview title "… (Regional)".

## Letterhead

### Where a print's letterhead comes from

1. **Edited in the preview** (payload `letterhead`) — that printout only.
2. **Saved default** for the document (`fims.letterhead_layout`, "Save as default header").
3. **Built-in**: the NHA banner (`letterhead/nha-letterhead-banner.png`, or UAC Header
   Management once that is set up in this repo) + the default footer text.

Only the forms listed in `LetterheadLayoutService::DOCUMENTS` have a letterhead
(CBA, RFA, LAA, CAF, CFA, Memo Request, LAA release request, PR, FTLA, FTLA request). The forms with their own printed
title block — **PO, IAR, LV, JEV** — print **no header and no footer**, and it
can't be edited: `PrintService::model()` sets them empty whatever the payload or a
saved default says, `/api/letterhead/{doc}` answers 404 for them, and the preview
hides Edit Letterhead (`PRP_TYPES … letterhead: false`). The DV (an official PDF
form) has none either. A new form with its own title block: leave it out of
`DOCUMENTS` and set `letterhead: false` in `PRP_TYPES`.

### Header designer (Edit Letterhead, in the preview)

The header is **freely placed items**, each an **image** or a **title**, set anywhere on the header area. All sizes and positions are in **units**: px on a 630 px wide letterhead. The header area is 630 × 150 units, and each printout scales it to its own header width (CBA 468 pt, RFA 540 pt, LAA 480 pt).

- **+ Add image** (PNG) / **+ Add title** adds a new item centred at the top. **✕** removes an item. Up to 8 items.
- **Drag in the preview's header** (while the designer is open):
  - Drag an item **anywhere**. It snaps (red guide lines) to the page centre, to the edges, and to the other items' edges and middles. Hold **Alt** to place it freely.
  - The header grows or shrinks live with its lowest item.
  - Click an item to select it; its settings row is highlighted.
  - The selected item has a **corner handle**. On an image it scales the image (proportions kept). On a title it changes the box width; font sizes are set in the list.
  - **Arrow keys** nudge the selected item 1 unit, or 10 with Shift.
  - Outlines, handles, guides and the hint exist only on screen (`prp_hdrDecorate()`). The PDF and DOCX are rendered by the server and never have them.
- **Drop an image file from the computer onto the header**:
  - On the middle of an image item: it replaces that image (green outline).
  - Anywhere else on the header: it is added, centred where it was dropped (green dot).
  - Designer closed: the designer opens and the image is added at the top.
  - Dropped on the designer panel: added at the top.
  - Anywhere else in the preview, the drop is ignored (the browser never opens the file).
- Any image the browser can open (PNG, JPG, WebP, GIF…) works, whether dropped, added with **+ Add image**, or swapped with **Replace…**. The browser converts it to PNG and scales large photos down (max 2400 × 1000 px) to stay under 2 MB (`prp_imageFromFile()`). The server still accepts only PNG.
- **In the item list:**
  - **X / Y**: the item's top-left corner.
  - **↔**: centres the item across the page.
  - Image **Size**: its height (10–150).
  - Title fields: line 1 / line 2 text (max 80 characters each), line sizes, bold for line 1, box **Width**, and text **alignment** inside the box. Title lines never wrap.
  - Drag the **⠿** grip to change the drawing order. Lower rows are drawn on top.
- **Real-time:** every change updates the preview's header and footer instantly in the browser (`prp_lhPatch()` mirrors `toParts()` + `composeHeaderHtml()`). About 0.9 s after the last change, a quiet server render confirms it in place. There is no "Loading…" and the scroll position is kept. The preview iframe is `sandbox="allow-same-origin"` (no scripts), so the designer can patch it.
- **Footer** text line (max 300 characters), with **Use default**.

Edits apply to that printout only. **Closing the preview or the tab drops them**: `prp_open()` always starts from the saved default, or the built-in layout if there is none. **Reset to default** returns to that starting point while the preview is open.

**Save as default header** / **Restore original** are only for System Administrator (`-1`) and FMD Department Manager (`4`) (`LetterheadLayoutService::SAVER_LEVELS`; the API checks too).
- Save stores the current design (items + footer) as the default for everyone's prints.
- Restore deletes it so the built-in letterhead is used again (click twice to confirm).

The built-in layout is the NHA banner as items: NHA logo, the title "NATIONAL HOUSING AUTHORITY" / "Build Better and More Housing" (Arial 16 / 9, line 1 bold), Pambansang Pabahay, and Bagong Pilipinas. It is placed by `LetterheadLayoutService::fromRow()` so it looks exactly like the banner.

**Older layouts.** A saved default from before free placement is a row with `width_pct` / `align` columns. It is converted to positions on load (`fromRow()`) and looks the same.

**Rendering** reuses the shared composers:
- **HTML/PDF:** `DocumentBranding::composeHeaderHtml()` places every item absolutely in a box as tall as the lowest item.
- **DOCX:** `docx-branding.buildHeaderChildren({parts, origin})` builds:
  - a first paragraph exactly that tall;
  - images floating in front of the text;
  - titles as text frames.

  Everything is positioned from the page edge. `origin` = the generator's left margin and header distance, in twips.

An empty item list prints no header.

### Layout JSON (payload `letterhead`, table column `layout`)

```json
{ "items": [
    { "type": "image", "asset": "logo", "x": 14.8, "y": 4.5, "height": 57 },
    { "type": "image", "src": "data:image/png;base64,…", "x": 500, "y": 0, "height": 55 },
    { "type": "title", "line1": "…", "line2": "…", "size1": 16, "size2": 9, "bold1": true,
      "x": 91.2, "y": 18.6, "width": 315.3, "align": "center" } ],
  "footer_text": "…" }
```

- `asset` = a built-in image (`logo` / `brand` / `seal`); `src` = an uploaded PNG.
- Items are drawn in list order, so later items sit on top.
- Validated server-side: PNG only, ≤ 2 MB each, ≤ 5 MB total, ≤ 8 items. Positions are clamped to the header area.

### Setup

Saving needs the table: run
`_assets/sql/01 - fims/01 - table/16 - fims_letterhead_layout.sql` (`npm run sql`).
Without it, printing and per-print edits work; Save returns a message saying so.

## RFA — Request for Fund Allotment

Ported from the old FMD `gen_rfa_docx.js` / `print-preview.js` `buildRfaHtml()`.

| | |
|---|---|
| Endpoint | `POST /api/print/rfa` (`format` = html / pdf / docx) |
| Model / HTML | `RfaPrintService::model()` / `html()` |
| DOCX | `scripts/gen_rfa_docx.js` |
| Preview | `prp_open(payload, { type: 'RFA' })` |
| Paper | Legal (8.5" × 14"), 0.5" sides, Arial 11pt; header 540pt (DOCX 720 px) |
| Letterhead | Same designer, saved default under `RFA`; **no footer** (`LetterheadLayoutService::DOCUMENTS`) |
| File name | `RFA-<number>` (no double prefix when the number already starts with RFA) |

**Form variant** (`RFA_VARIANTS`): `form_variant` (1a 1a2 1b 2a 2b 2c 2d), else
by the requesting office's `uac.departments.department_type` (payload
`requesting_department_type`, or looked up from `requesting_department_id` /
`requesting_region_id`): **MAIN → 1a STAFF form** (filled out by FMD, Part I
signed "(Group/ Department Manager)", Part II by the Financial Management
Department); anything else → **2a REGIONAL OFFICE form** (filled out by
Regional Offices, "(Regional/ /District Manager)", Part II by the Finance Unit).
`filled_out_by` / `rfa_type` override the variant.

**Content:** Part I — RFA No., type line, "allotment to be made in favor of"
(`payee_name`, trailing "(CODE)" dropped), date, description lines
(`particulars`, amount on the last line), Certified Correct signatory
(`cert_correct_name/_title/_office`). Part II — A. Program/Project Reference
(a–g: `project_name`, `total_estimated_cost`, `total_funds_available`,
`total_funded_to_date`, `balance` = b − c, `payments_outstanding`,
`this_request` = amount, `total_payments` = e + f), B. Allotment Reference
(h–j: `total_contract_cost` = amount, `adjustment_additive/_deductive`,
`total_allotment` = amount), C. Funding Source (`funding_source`: subsidy,
equity, corporate_receipts checkboxes; SARO / OR no. + date; agency name),
Certified Funds Available (`certified_funds_name`, title default "Chief
Corporate Accountant"), Recommending Approval / Approved. Amounts print in
14-character underlined slots; blanks print as underscores.

**Signatories** (kept as on the old FMD form — confirmed 2026-11-09): RECOMMENDING APPROVAL prints
`recommending_name`, else the current **FMD Department Manager**; APPROVED
prints `approved_name`, else a blank line. (Labels "(Finance Unit Head)" /
"(Regional Manager)" are fixed by the form.)

**Regional RFA** (`issuing_office: 'REGIONAL'`, old REGIONAL `gen_rfa_docx.js`):
"RFA Form No. <variant>" (e.g. "RFA Form No. 2a") bold at the top right; a bold
"Request for Fund Allotment" line under DESCRIPTION/NATURE OF REQUEST; the
particulars print bold, the first line prefixed "Re:". RECOMMENDING APPROVAL
(over "(Finance Unit Head)") prints `recommending_name` only — never the FMD
Department Manager. Everything else as the FMD form (pick the regional
`form_variant`, 2a–2d).

In the PDF, ₱ ☐ ☒ ● use DejaVu Sans (not in Helvetica).

## LAA — Letter of Advice of Allotment (CO and COE)

Ported from the old FMD `gen_laa_docx.js` / `print-preview.js` `buildCoLaaHtml()`
("LAA - Corporate Receipts Source of Fund" template). The **COE** LAA (old
`gen_coe_laa_docx.js`) is the same letter and layout, printed with
`document_type: 'COE'` — see "COE LAA" below.

| | |
|---|---|
| Endpoint | `POST /api/print/laa` (`format` = html / pdf / docx) |
| Model / HTML | `LaaPrintService::model()` / `html()` |
| DOCX | `scripts/gen_laa_docx.js` |
| Preview | `prp_open(payload, { type: 'LAA' })` |
| Paper | A4, 0.8" margins (480pt text width); Arial — date / title 12pt, body 11.5pt, details sheet 10pt, footer 9pt |
| Letterhead | Same designer, saved default under `LAA`; has a footer line (designer footer text) |
| File name | `LAA-<number>` (spaces → `_`; no double prefix) |

**Content:** date set in at 5" (`date`: `Y-m-d` → "March 4, 2026", other text
as given, blank → today); title "LETTER OF ADVICE OF ALLOTMENT No. **<number>
(<office without its code>) CY <year>**" (year = the 4-digit year in
`fiscal_year_label`, else a 4-digit `fiscal_year`); addressee (`to_name`, else
the office; `to_title`; `to_org` "National Housing Authority"; `to_address`
"Elliptical Rd., Diliman, Quezon City"); `greeting` "Dear Sir/Madam:"; intro
naming the source of fund; **details sheet** — "To transfer obligation partially
made to" + "<office> to cover the <description>", **P <amount>** double
underlined, "** AMOUNT IN WORDS **", Source of Fund, "Certified as to
Availability of Allotment:" signatory, bottom-left "Source of Fund: <fund>" /
`program_of_cob`; closing; "Very truly yours," + closing signatory.

**Data:** office = `office_name`, else `uac.departments.department_name` of
`requesting_department_id` / `requesting_region_id`; amount = `amount`, else
`allocated_amount` / `requested_amount`; fund = `fund_source_name` without
"(CODE)", default "Corporate Receipts".

**Signatories:** certifier = `cert_name`, else the current **FMD Department
Manager** (title "Department Manager", "Financial Management Department");
closing = `close_name`, else the current **FSG Group Manager** (this repo's
role; old code used "Group Manager", tried as a fallback), else the old FMD
default name (title "Group Manager", "Financial Services Group").

HTML note: the details sheet is built from simple fixed-width tables (no row
spans) because Dompdf mis-sizes the template's merged grid; the DOCX keeps the
template's exact merged grid (columns A–H, 9602 twips).

### COE LAA (`document_type: 'COE'`)

Same endpoint, model, HTML, generator, paper, letterhead (saved default `LAA`)
and signatories as the CO LAA. Preview title "… (COE)"; file name `COE-LAA-<number>`.
What differs (old `gen_coe_laa_docx.js`):

- **Addressee** — the Regional Manager by name: `to_name` (bold, e.g.
  "ENGR. JOVITA G. PANOPIO"), `to_title` "Regional Manager", `to_org` "National
  Housing Authority", `to_address` "Elliptical Road, Diliman, Q.C.".
- **Greeting** — `greeting`, else built from `to_name`'s title + surname
  ("Dear Engr. Panopio,"; Engr. / Atty. / Ar. / Arch. / Dr. / Mr. / Ms. / Mrs.),
  else "Dear Sir/Madam:".
- **Details sheet** — no label line; one sentence "To transfer obligation made
  to <office without its code> to cover the payment for the <description>"
  (no "partially").
- **Amount in words** — "** … PESOS & 4/100 **" (centavos only when not zero).
- **No** bottom-left "Source of Fund: … / <program>" notes.

`to_name` is not on the allocation record and isn't resolved automatically
(a "Regional Manager" role holder can't be tied to the requesting region yet) —
the caller passes it when the form is wired in; without it the letter prints
title / org / address and "Dear Sir/Madam:".

## CAF — Certificate as to Availability of Funds

Ported from the old FMD `gen_caf_docx.js` / `print-preview.js` `buildCafHtml()`
("CAF_if_for_LGU" template). A one-page certificate with the CBA's layout, so it
**reuses the CBA renderers**: `CafPrintService::model()` sets `kind: 'CAF'`, and `CafPrintService::html()` (= `CbaPrintService::html()`) /
`scripts/gen_cba_docx.js` switch to the CAF styles.

| | |
|---|---|
| Endpoint | `POST /api/print/caf` (`format` = html / pdf / docx) |
| Model / HTML | `CafPrintService::model()` / `html()` (the CBA layout, kind CAF) |
| DOCX | `scripts/gen_cba_docx.js` (kind CAF) |
| Preview | `prp_open(payload, { type: 'CAF' })` |
| Paper | A4, 1in margins; Times New Roman 12pt, title 14pt |
| Letterhead | Same designer, saved default under `CAF`; has a footer line |
| File name | `CAF-<caf number>` (no double prefix; blank → `CAF-DRAFT`) |

**Content:** date (upright, right-aligned); title "*CERTIFICATE AS TO
AVAILABILITY OF FUNDS*" (bold italic); one italic certify paragraph at 1.5
lines with a 0.5in first-line indent:

> This is to certify that the amount of **<WORDS & nn/100 PESOS> (<amount>)**
> necessary to cover <purpose> to be released to **<payee> <payee_title>**, with
> principal office at <address> is available from <fund> per RFA# <rfa>.

(payee / title / address parts only when given; no fund → "the appropriate fund
source"; ordinals in the purpose — "1st", "2nd" — get a superscript suffix);
signatory centred in the right half (name bold italic); footnote "**RFA# <rfa>**"
and "Source of Fund: **<fund>**" (italic). No validity paragraph, no reminders.

**Data:** `caf_number`, `allocation_number` (or `rfa_number`, the linked RFA),
`caf_amount` (or `amount`), `purpose`, `payee_name`, `payee_title`,
`payee_office_address`, `fund_source_name`, `date`, `approved_by_name`. A
trailing code like " (CR)" / " (GSD)" is dropped from the fund and payee names.

**Signatory:** `approved_by_name`, else the current **FMD Department Manager**
(same as the CBA). The old CAF's placeholder name (used only when nobody holds
the role) differed from the CBA's; the CBA's default is used for both.

### Regional CAF (`issuing_office: 'REGIONAL'`)

From the old REGIONAL `gen_caf_docx.js` / `cafServices::buildPrintPayload()`
(`CafPrintService::regionalModel()`). Same certificate layout with its own style:
date top-left italic, title bold italic 12pt, paragraph indented 1in, a
**"Certified:"** block (name bold and title italic on one line, office below),
no footnote lines.

The amount: "**<WORDS> PESOS ONLY (P 1,000.00)**", with centavos "**… PESOS & 50/100
ONLY**" (whole tens) or "**… PESOS AND TWENTY-FIVE CENTAVOS ONLY**"; the payee in
capitals. Wording by `body_variant`:

| `body_variant` | Wording |
|---|---|
| `entered_into_with` (default) | "… necessary to cover the <purpose> entered into with **<PAYEE>** with principal office at <address> is available from <fund> per RFA# <no>." |
| `in_favor_of` (e.g. EHAP) | "… in favor of **<PAYEE>** necessary to cover the <purpose> is available from <fund> per RFA# <no>." |
| `in_favor_of_petty` | "… in favor of **<PAYEE>**, <purpose> is available from <fund>." — title "… AVAILABILITY OF **FUND**" |

`ref_type: 'LAA'` prints "LAA#" instead of "RFA#". The old module guessed the
variant (and the payee) from the purpose text; here the caller sends
`body_variant`.

Payload: `caf_number`, `date`, `caf_amount` (or `amount`), `purpose` (or
`description`), `payee_name`, `payee_office_address`, `fund_source_name` (printed
as given, e.g. "Corporate Receipts (CR)"), `allocation_number` (or `rfa_number` /
`laa_number`), `ref_type`, `certified_name`, `certified_title` (default "Chief
Corporate Accountant A"), `certified_office`, `body_variant`.

## LAA release request — regional memorandum

The region's memorandum asking FMD to release an LAA (old REGIONAL
`gen_laa_docx.js`, which the REGIONAL module called its "LAA"). A different
document from the LAA, so its own form.

| | |
|---|---|
| Endpoint | `POST /api/print/laareq` (`format` = html / pdf / docx) |
| Model / HTML | `LaaRequestPrintService::model()` / `html()` |
| DOCX | `scripts/gen_laareq_docx.js` |
| Preview | `prp_open(payload, { type: 'LAAREQ' })` |
| Paper | Letter, 1in sides; Arial 11pt |
| Letterhead | Same designer, saved default under `LAAREQ`; no footer line |
| File name | `LAA-REQUEST-<laa number>` (or `LAA-REQUEST`) |

**Content:** date; **MEMORANDUM**; rows **FOR** / **THRU** (name bold, title
below — each only with a name) and **SUBJECT** (bold, default "Request for the
Release of Letter of Advice of Allotment (LAA)"); a rule; "May we respectfully
request for the release of Letter of Advice of Allotment (LAA) for the proposed
budget for <description> amounting to **<Words> (Php <amount>)**."; "For your
consideration and appropriate action please."; "Thank you."; signatory (name
bold, title).

Payload: `date`, `for_name` / `for_title`, `thru_name` / `thru_title`,
`subject`, `description` (or `project_description`), `amount`, `sig_name` /
`sig_title` / `sig_office`, `laa_number` (file name only).

Small fixes against the old text: a space in "Allotment (LAA)" (the old form
had "Allotment(LAA)" and "WORDS(Php …)"), and a period after the amount.

**Shared memo layout.** `html()` and `gen_laareq_docx.js` print any memo model:
`date`, `rows` (FOR / THRU — `LaaRequestPrintService::memoRows()`), `subject`,
`paragraphs` (justified, each a list of `{text, bold}` runs), `closing` (the
last plain line), `signatory` {`name`, `title`, `office`}. The **FTLA request**
(`FtlaRequestPrintService`) builds that model and prints through it. Optional
parts (the **CFA** uses them; the LAA / FTLA requests print byte-identical
without them): `paper` 'A4', `letterhead_first_page_only`, a `{ list: [[label,
value], …] }` paragraph, `certification` (lines of runs on the right),
`signature_rows` [{ `label`, `signers`: [{ `name`, `title_lines` }] }], an empty
`closing`, `brand_document`, and `pdf_running_header` (PrintService). The
**Memo Request** (`MemoRequestPrintService`) adds: `{ block: 'item' }` paragraphs
(hanging-indent "I." / "a." / "1." items, a right `aside` column, continuations),
`{ block: 'table' }` paragraphs (bordered; header, group and total rows), a run's
`underline`, a signature row's `center`, and the running header's `page_first` /
`italic` — the others still print byte-identical (checked: LAAREQ, FTLAREQ, CFA
HTML + DOCX XML unchanged).

## PO — Purchase Order

One form merged from the old REGIONAL `gen_po_docx.js` and the GSD / REGIONAL
`BudgetAllocationPdfService::buildPoHtml()` (the same form; FMD and GLOBAL only
printed the screen). Prints straight from a saved PO in this repo
(`fims.purchase_orders` + items).

| | |
|---|---|
| Endpoint | `POST /api/print/po` (`format` = html / pdf / docx) |
| Model / HTML | `PurchaseOrderPrintService::model()` / `html()` |
| DOCX | `scripts/gen_po_docx.js` |
| Preview | `prp_open({ po_id }, { type: 'PO' })` |
| Paper | Legal, 0.55in sides; Times New Roman 9pt, everything in one outlined box |
| Letterhead | **None** — the form's own title block (NHA logo, as GSD's); not editable, no footer line |
| File name | `PO-<number>` (no double prefix; blank → `PO-DRAFT`) |

**Data:** `po_id` (or `id`) loads the saved PO through
`PurchaseOrderService::fetchById()` — number, date, supplier name / address / TIN,
delivery place / date / terms, payment terms, items, total, particulars (as the
Purpose). Any payload field also sent **overrides** the saved value; without
`po_id` the payload alone is printed. Not stored on a PO here, so payload only:
`mode_of_procurement`, `requisitioners`, `references` (or `pr_number` → "Refer to
P.R. No. …"), `warranty`, the signatories, `prepared_by_initials`,
`funds_available`, `alobs_no` (or `allocation_number`), `alobs_amount`, `alobs_adj`.

**Content:** title block "PURCHASE ORDER / NATIONAL HOUSING AUTHORITY / Agency";
Supplier / Address / TIN | P.O. No. / Date / Mode of Procurement; "Gentlemen: Please
furnish this Office …"; Place / Date of Delivery | Delivery / Payment Term; items
(Item No., Unit, Description, Quantity, Unit Cost, Amount — at least 4 rows, as
GSD's); TOTAL LOT PRICE; X-X-X-X-X-X-X-X-X-X; Purpose / Requisitioners / References /
Warranty (each only when set); Total Amount in Words; the penalty clause (1/10 of
1% per day of delay); Conforme (supplier signature, DATE) | approving officer
(default title "Chairperson, CBAC") and General Manager; prepared-by initials;
Funds Available (certified by — default the current **FMD Department Manager**,
title "Manager, FMD") | ALOBS No. / Amount / Adj. Blank boxes print as lines to
fill in by hand, as on the old forms.

## PR — Purchase Requisition

One form merged from the old REGIONAL `gen_pr_docx.js` and the GSD / REGIONAL
`PurchaseRequestPdfService` (identical apart from comments; FMD and GLOBAL only
printed the screen). No PR module in this repo yet, so it prints from the
payload.

| | |
|---|---|
| Endpoint | `POST /api/print/pr` (`format` = html / pdf / docx) |
| Model / HTML | `PurchaseRequestPrintService::model()` / `html()` |
| DOCX | `scripts/gen_pr_docx.js` |
| Preview | `prp_open(payload, { type: 'PR' })` |
| Paper | Legal, 0.55in sides; Times New Roman 9pt |
| Letterhead | The NHA letterhead (saved default under `PR`); no footer line |
| File name | `PR-<number>` (no double prefix; blank → `PR-DRAFT`) |

**Content:** PURCHASE REQUISITION + P.R. NO.; "To the Purchasing Officer — Please
Purchase the following item(s)" + Date; items (Warehouse Stock on Hand, QTY,
UNIT, DESCRIPTION, Dept./Office Qty. on Hand, Estimated Cost per unit / total — at
least 10 rows), "NOTE: Included Tax" (`note`), two blank rows, Total; END
USER/PURPOSE; CHARGE TO + Requested Delivery Date; "To be accomplished by
Financial Management Department": A. Program/Project Reference (a–g) | B.
Allotment Reference (h–n) on underlined amount lines; "Request for fund
allotment No."; four signatory boxes.

**Data:** `pr_number`, `pr_date`, `purpose`, `department_name` (or `charge_to`),
`delivery_date`, `items` [{ `item_description`, `unit_of_measure`,
`quantity_requested`, `unit_cost`, `total_cost`?, `warehouse_stock`?,
`dept_qty_on_hand`? }], `total_amount` (default the items' sum), `note`;
FMD part — `project_description` (under a.), `total_estimate_cost` (a),
`total_funds_available` (b), `funded_to_date` (c), `fund_balance` (d),
`outstanding_payable` (e), `request_if_approved` (f), `total_payments_project` (g),
`total_contract_cost` (h), `additional_allotment` (i), `total_allotment` (j),
`prior_payment` (k), `this_payment` (l), `total_payments` (m),
`balance_to_complete` (n) — a, h, j and n default to the total; c and d print
without "₱" (as the old form); `fund_allotment_no` (or `allocation_number`);
`sig1_name` … `sig4_name` with roles `sig1_role` … `sig4_role` (defaults
"Transport Operations Supvr. GSD", "Officer-In-Charge, GSD", "Dept. Manager,
FMD", "Group Manager, MSG").

Lines f, i and l were blank on GSD's HTML; REGIONAL's DOCX filled them — the
merged form prints them when sent.

## IAR — Inspection and Acceptance Report

Ported from the old ACCOUNTING (PIS) `IarPdfService` and the browser-print route
in `PmsClearingAccounts.php` (the same form; no other department printed an IAR).
No inspection module in this repo yet, so it prints from the payload — the shape
of the old `PmsClearingAccountsService::getIarData()`.

| | |
|---|---|
| Endpoint | `POST /api/print/iar` (`format` = html / pdf / docx) |
| Model / HTML | `IarPrintService::model()` / `html()` |
| DOCX | `scripts/gen_iar_docx.js` |
| Preview | `prp_open(payload, { type: 'IAR' })` |
| Paper | Letter, 0.55in margins; Times New Roman 10pt |
| Letterhead | **None** (as the old form); not editable, no footer line |
| File name | `IAR-<iar_number>` (blank → the PO number, then `IAR-DRAFT`) |

**Content:** INSPECTION AND ACCEPTANCE REPORT; Supplier + IAR No.; P.O. No. + date,
S.I. No. + date; Requisitioning Office/Dept.; items (Stock No., Unit Price,
Description, Quantity) closed by "*** Nothing Follows ***", at least 15 rows;
two "Date Inspected" checks + Complete / Partial delivery; three signatories.

**Data:** `iar_number`, `supplier_name`, `po_number`, `po_date`, `si_number`,
`si_date`, `requesting_department`, `items` [{ `stock_no`?, `unit_cost`,
`description`, `quantity` }], `date_inspected`, `delivery` (`complete` default |
`partial`; or `is_complete_delivery` true/false), `inspector_name` / `_label`,
`div_manager_name` / `_label`, `dept_manager_name` / `_label` (labels default
"Inspector, Accounting Rep.", "Div. Mgr., MMD-GSD", "Dept. Manager, GSD").
Complete delivery checks "Inspected, accepted … specification" (with the date)
and "Complete delivery"; partial checks "Inspected, verified … quantity" (with
the date) and "Partial delivery".

Changes from the old print: the S.I. date prints (the old PDF left it blank;
its browser print showed it), Stock No. prints when sent (always blank before),
quantities keep their decimals, and "Div. Mgnr." is spelled "Div. Mgr.".

## LV — Liquidation Voucher

One form merged from the old REGIONAL `gen_lv_docx.js` and
`LiquidationVoucherServices::buildLvHtml()` (the full form — its DOCX and PDF
had drifted apart) and FMD's `gen_liquidation_docx.js` (text for pre-printed LV
stock, whose own content — the obligation ledger — prints here in the
OBLIGATION column). ACCOUNTING's DCD pages print cash-advance registers, not the
LV. No LV module in this repo yet, so it prints from the payload.

| | |
|---|---|
| Endpoint | `POST /api/print/lv` (`format` = html / pdf / docx) |
| Model / HTML | `LvPrintService::model()` / `html()` |
| DOCX | `scripts/gen_lv_docx.js` |
| Preview | `prp_open(payload, { type: 'LV' })` |
| Paper | Legal, 0.5in margins; Times New Roman 9pt (REGIONAL's PDF paper — its DOCX was letter) |
| Letterhead | **None** — the form's own "NATIONAL HOUSING AUTHORITY / LIQUIDATION VOUCHER" box; not editable, no footer line |
| File name | `LV-<lv_number>` (no double prefix; blank → `LV-DRAFT`) |

**Content:** title box + LV No.; Disbursing Officer/Regional Bank Account;
Department/Region; PARTICULARS / AMOUNT (the lines, "Chargeability:", the
cash-advance breakdown — at least 15 rows); AMOUNT IN WORDS; AMOUNT OF CASH
ADVANCE PER DV NO. / AMOUNT REFUNDED (arrow + ₱ box); OBLIGATION + ACCOUNTING
ENTRIES (account code, debit, credit — at least 15 rows); Journalized by/date,
Indexed by/date; certifications A (Regional Manager), B (OIC of FMD, with the
allotment amount), C (OIC of Accounting, JEV No.); page 2 "SUMMARY OF PETTY
CASH" when `pc_summary` is sent.

**Data:** `lv_number`, `disbursing_officer`, `department_region`;
`particulars` — text (one line with `gross_amount`) or lines [{ `description`,
`amount`, `sub`? }] (`sub` = indented VAT-type line, left out of the sum);
`gross_amount` (default the lines' sum), `amount_in_words` (default "… PESOS
AND … CENTAVOS ONLY"), `chargeability` (or `fund_source_code` +
`fund_source_name`);
cash advance — `ca_dv_number`, `ca_amount`, `prior_liquidations` [{ `lv_number`,
`amount` }], `tax_deposit_amount`, `cash_refund_deposit_amount` → the breakdown
rows (Cash Advance thru DV No. / Less: / prior LVs / This Liq / Tax Deposit /
Cash Refund Deposit, with REGIONAL's three amount columns: item, running
subtotal, cash advance total);
`refund_or_number` ("AMOUNT REFUNDED PER OR NO. … AND DTD"; without it
"…PER DEPOSIT DATED:"), `refund_date`, `refund_amount` (default
`cash_refund_deposit_amount`);
`obligation` — FMD's ledger { `dept_proj`, `fund_source`, `budget_ref`,
`budget`, `obligation_ref`, `obligation`, `balance`, `payments_ref`,
`payments`, `this_request_ref`, `this_request`, `total_payments` };
`accounting_entries` [{ `obligation`?, `account_code`, `debit`, `credit` }];
`journalized_by_date`, `indexed_by_date`; `cert_a_name` / `_title`,
`cert_b_name` / `_title`, `cert_b_amount` (default `gross_amount`),
`cert_c_name` / `_title`, `jev_no`; `pc_summary` [{ `type` (received | less |
add | balance), `label`, `amount` }].

Changes from the old prints: the LV No. prints in the DOCX too (REGIONAL's
DOCX left it blank); one breakdown layout for both outputs (REGIONAL's DOCX and
PDF differed); "LV" isn't doubled before numbers that already start with it.
Not ported: FMD's text-only print for pre-printed LV stock — its positions were
tuned to a physical form we have no template of.

## JEV — Journal Entry Voucher

One form: the NHA paper JEV, from the old GLOBAL
`JevServices::renderPrintHtml()` — itself the merge of ACCOUNTING's
`/jev-listing/print` (GAD) and `/jev-listing/dcd-print` (DCD) templates. DCD's
`check-db-journal.php` / `jev-form.js` previews and PCDS's Excel export are
copies of the same form. Not carried over: REGIONAL's simplified
`jev-readone` preview (the regional office prints this form instead) and its
payroll-remittance summary sheet (`gen_jev_remittance_docx.js` — a remittance
JEV prints on this form with its entries). No JEV module in this repo yet, so it
prints from the payload.

| | |
|---|---|
| Endpoint | `POST /api/print/jev` (`format` = html / pdf / docx) |
| Model / HTML | `JevPrintService::model()` / `html()` |
| DOCX | `scripts/gen_jev_docx.js` |
| Preview | `prp_open(payload, { type: 'JEV' })` (xl modal — landscape) |
| Paper | A4 landscape, 12mm sides / 9mm top-bottom; Arial 7.5–8pt, amounts in Courier New |
| Letterhead | **None** — the form's "JOURNAL ENTRY VOUCHER / NATIONAL HOUSING AUTHORITY" box; not editable, no footer line |
| File name | `JEV-<jev_number>` (no double prefix; blank → `JEV-DRAFT`) |

**Content:** title box + office / JEV No. / Date; entries table —
Responsibility Center, Accounts and Explanation, SL Code, Subsidiary Ledger
Debit / Credit, Account Code, P, General Ledger Debit / Credit (at least 8
entry rows); Reference: DV# + payee; Particulars; RFA No.; SARO; TOTAL (SL and
GL, "-" when zero); Prepared by / Reviewed by / Certified Correct (or Approved
by); footer remarks; "Temporary Reference No." for an unposted JEV.

**Data:** `jev_number` (blank or `TEMP-…` → the No. stays blank and the
temporary reference prints under the form, as the old form), `jev_date`,
`office` (default "MAIN OFFICE"; the book or regional office name),
`responsibility_center` (every line's default), `lines` [{ `account_title`,
`account_code`, `debit`, `credit`, `description`?, `sl_code`?, `sl_path`?
("Parent › Child"), `responsibility_center`? }] — a line with an SL also prints
its description and an indented SL row with the SL code and SL debit / credit
(the SL code defaults to the path's last part), `reference_no` (DV#),
`payee_name`, `particulars`, `rfa_number`, `saro_number`, `prepared_by_name` /
`_title`, `reviewed_by_name` / `_title`, `approved_by_name` / `_title` (titles
default "Chief Corporate Accountant", "Division Manager", "Department
Manager"), `approved_by_label` (default "Certified Correct:"),
`footer_remarks`.

## FTLA — Fund Transfer Letter of Advice

The letter to the bank — "AUTHORITY TO DEBIT/CREDIT ACCOUNT". One form for every
page that printed it: TREASURY `ftlafundtransfer.php` ("Print FTLA"),
`ftla-preparation.php` ("Print DRFTLA" — Disbursement Record thru FTLA),
`ftlasettlement.php` (settlement letter), FMD `fund-transfer-form.php` /
`gen_fund_transfer_docx.js` (which filled a sample Word template) and
`FP-FS/cross-access-readone.php`. The region's "FTLA" is a request memo to
Treasury — the separate **FTLA request** below. DCD's print on its FTLA page is
the FTLA's JEV (the JEV form). No fund-transfer module in this repo yet, so it
prints from the payload.

| | |
|---|---|
| Endpoint | `POST /api/print/ftla` (`format` = html / pdf / docx) |
| Model / HTML | `FtlaPrintService::model()` / `html()` |
| DOCX | `scripts/gen_ftla_docx.js` |
| Preview | `prp_open(payload, { type: 'FTLA' })` |
| Paper | A4, 15mm sides / 12mm top-bottom; Arial 9.5pt |
| Letterhead | The NHA letterhead (as the old prints), saved default under `FTLA`; no footer line |
| File name | `FTLA-<ftla_number>` (no double prefix; blank → `FTLA-DRAFT`) |

**Content:** date; TO (bank, branch) / SUBJECT; the body — picked from the
accounts (`model['layout']`):

- `single` — one debit → one credit: "Please debit our Account ___ Account No.
  ___ the amount of PESOS: ___(words)___ Php ___ and credit ___ Account No.: ___
  representing ___(purpose)___";
- `debit_list` — several debit accounts → one credit account: "Please **debit**
  the following depository/disbursing accounts:" BANK ACCOUNT (NUMBER / NAME /
  AMOUNT) + TOTAL, then "and **credit** the account of (no.) (name) in the total
  amount of (words) (Php …)";
- `pairs` — anything else: "Please effect the fund transfer as follows:" FROM
  (Account No., Name) / TO (Account No., Name) / AMOUNT + TOTAL — a FROM account
  shared by several rows spans them.

Then the declaration ("We declare under penalty of perjury …"), the certified
true copy request, two signatories (left / right), the DRFTLA footer ("* DV
NO.s:" + the fund class and FTLA number) when sent, and **FOR BANK USE ONLY**
(Signature Verified by / Checked by / Approved by / Posted by).

**Data:** `ftla_number`, `date` (or `transfer_date`), `to_bank_name` (default
"LANDBANK OF THE PHILIPPINES"), `to_bank_branch`, `subject` (default "AUTHORITY
TO DEBIT/CREDIT ACCOUNT"; the old pages also used "AUTHORITY TO TRANSFER FUNDS",
"AUTHORITY TO TRANSFER FUNDS TO GENERAL FUND ACCOUNT", "AUTHORITY TO TRANSFER
SETTLEMENT FUNDS TO MAIN DEPOSITORY ACCOUNT"); one transfer — `from_account_name`,
`from_account_number`, `to_account_name`, `to_account_number`, `amount` (or
`transfer_amount`), `purpose`; or several — `transfers` [{
`from_account_number`, `from_account_name`, `to_account_number`,
`to_account_name`, `amount` }] (each row pairs a debit with a credit);
`sig_left_name` / `_title`, `sig_right_name` / `_title` (titles default
"Officer-in-Charge / Treasury Department" and "Acting Group Manager / Financial
Services Group"; a `\n` breaks the line — the settlement letter used "Financial
Planning Analyst" + "Officer-In-Charge / Treasury Department"); `dv_numbers`
(list or one per line) and `fund_classification` (default "COE/MOOE" — prints
with the FTLA number); `bank_sig_verified`, `bank_checked`, `bank_approved`,
`bank_posted`. Amounts in words: "… Pesos and … Centavos Only".

Changes from the old prints: TREASURY's placeholder signatory names ("MARIA C.
SANTOS", "JOSE R. DELA CRUZ") aren't printed — blank until sent; the
certified-copy request prints on every layout (TREASURY's multi-account print
left it out; the settlement letter had it); the settlement letter's
"aggregate amount" debit table prints as the `debit_list` layout.

## FTLA request — regional memorandum

The regional office's memo asking Treasury for a fund transfer, ported from the
old REGIONAL `gen_ftla_docx.js` / `FtlaPdfService`. Prints through the **LAA
release request's memo layout** (see "Shared memo layout" there) — the same
MEMORANDUM / FOR / THRU / SUBJECT format.

| | |
|---|---|
| Endpoint | `POST /api/print/ftlareq` (`format` = html / pdf / docx) |
| Model / HTML | `FtlaRequestPrintService::model()` / `LaaRequestPrintService::html()` |
| DOCX | `scripts/gen_laareq_docx.js` |
| Preview | `prp_open(payload, { type: 'FTLAREQ' })` |
| Paper | Letter, 1in sides; Arial 11pt (the memo layout — REGIONAL's DOCX was A4 Times 12pt) |
| Letterhead | Same designer, saved default under `FTLAREQ`; no footer line |
| File name | `FTLA-REQUEST-<ftla number>` (or `FTLA-REQUEST`) |

**Content:** date; MEMORANDUM; FOR / THRU (each only with a name) / SUBJECT; a
rule; "We humbly request the transfer of fund to cover the <particulars> in the
amount of **<WORDS> (Php <amount>).**"; "The above-mentioned amount is funded
under the LAA No. **<no.> (<department>)** CY <year> & RFA No. **<no.>** dated
<date>. Kindly transfer said amount to **<account>.**" (LAA and RFA each
optional); "Kindly inform us and provide us with a copy of Fund Transfer Letter
Advice (FTLA) once it is approved."; "For your consideration."; signatory (name,
title, office).

**Data:** `date`, `for_name` / `for_title`, `thru_name` / `thru_title`,
`subject` (default "Request for Fund Transfer"), `particulars`, `amount`,
`amount_words` (default from `amount`), `laa_number`, `laa_department`,
`fiscal_year`, `rfa_number`, `rfa_date`, `bank_account_line`, `sig_name` /
`sig_title` / `sig_office` (or `signatory_name` / `_title` / `_office`, the old
keys), `ftla_number` (file name only). DV numbers aren't printed (as the old
memo). "Php" instead of "₱" — the memo font has no ₱ in the PDF.

## CFA — Certified Funds Available

The requesting office's **funding-request memorandum** that FMD certifies — not
the CAF certificate (CafPrintService). Printed from the Memo Request form when
`allocation_type` is CFA (offered for COE only). Built from a scanned CFA
(December 2023, Gratuity Pay). Prints through the **shared memo layout** (see
"Shared memo layout" under the LAA release request) with its optional parts.

| | |
|---|---|
| Endpoint | `POST /api/print/cfa` (`format` = html / pdf / docx) |
| Model / HTML | `CfaPrintService::model()` / `LaaRequestPrintService::html()` |
| DOCX | `scripts/gen_laareq_docx.js` |
| Preview | `prp_open(payload, { type: 'CFA' })` |
| Paper | A4, 1in sides; Arial 11pt |
| Letterhead | The NHA letterhead on **page 1 only** (saved default under `CFA`); no footer line |
| Page 2 on | Running title (italic, top right) + "Page X of Y" — PDF: `pdf_running_header` (PrintService::renderPdf, Dompdf page_script); DOCX: the default header after a first-page header |
| File name | `CFA-<cfa number>` (or `CFA`) |

**Content:** date; MEMORANDUM; FOR / THRU / SUBJECT (upper case); a rule; the
request — the body as typed (each line a paragraph, blank lines only separate),
then the fixed indented list **Main Office :** / **Regional Offices :** (labels
underlined, values entered), then the paragraphs after it (`body_after`, e.g. the
cost and the closing); "Label : value" lines typed in the body also print as an
indented list; "Certified Funds Available: **Php …**" and "RFA# … / <FUND SOURCE>" on the right;
the requesting department manager and FMD's certifying officer side by side;
"Recommending Approval:" (side by side) and "Approved:".

**Fixed vs entered:** only the form's labels and wording are fixed (MEMORANDUM,
FOR / THRU / SUBJECT, the "Main Office" / "Regional Offices" labels, "Certified
Funds Available: Php", "RFA# …", "Recommending Approval:", "Approved:", "Page X of
Y"). Every value — every name and title included — prints **only as entered, no
defaults**: the FOR / THRU / SUBJECT rows and the Main Office / Regional Offices
list always print (fixed labels), an empty value prints blank, a blank running
title leaves page 2's header as just "Page X of Y".

**Data:** `date` (blank → the print date), `for_name` / `for_title`,
`thru_name` / `thru_title`, `subject`, `body` (text) or `paragraphs` [text],
`main_office`, `regional_offices`, `body_after`, `amount`, `rfa_number`, `fund_source_name`, `requested_by_name` / `_title`,
`certified_by_name` / `_title`, `recommending` [{ `name`, `title` }] (none → the
section is left out), `approved_by_name` / `_title`, `running_title`,
`cfa_number` (file name). A `\n` in a title starts a new line.

From the Memo Request form: `body` = Purpose / Description, `amount` = the
requested amount, `rfa_number` = the linked (parent) memo. The rest comes from
the form's **Certified Funds Available** section (`#cfa_section`, shown
only when `allocation_type` is CFA — `cfaApplyMode()`): FOR / THRU (name +
title), Subject, Main Office / Regional Offices, the paragraphs after the list,
the fund source, the page-2 header title, the requesting / certifying officers,
Recommending Approval rows (`cfaAddRecommending()`) and Approved by —
collected by `cfaPayloadFields()`. No defaults: an empty input prints blank.
These inputs are **not saved** with the memo request yet (print-time only);
reprinting a saved memo needs a column for them first.

## MEMO — Memo Request (originating unit)

The **originating unit's memorandum** to the General Manager asking for a budget,
which FMD then certifies ("Certified Budget Allocation" + the CBA#). Printed from
the Memo Request form by **every unit except FMD** — FMD users keep printing the
CBA / RFA / LAA / CFA. Built from a scanned memo (January 2024, Amendment to
Order No. 4, security services). Prints through the **shared memo layout** (see
"Shared memo layout" under the LAA release request) with its optional parts.

| | |
|---|---|
| Endpoint | `POST /api/print/memo` (`format` = html / pdf / docx) |
| Model / HTML | `MemoRequestPrintService::model()` / `LaaRequestPrintService::html()` |
| DOCX | `scripts/gen_laareq_docx.js` |
| Preview | `prp_open(payload, { type: 'MEMO' })` |
| Paper | A4, 1in sides; Arial 11pt (tables 10pt) |
| Letterhead | The NHA letterhead on **page 1 only** (saved default under `MEMO`); no footer line |
| Page 2 on | "Page X of Y" **first**, then the running title — upright, top right (`pdf_running_header.page_first`, `italic: false`) |
| File name | `MEMO-<memo number>` (or `MEMO`) |

**Content:** date; MEMORANDUM; FOR / THRU / SUBJECT (upper case); a rule; the
request as typed (below); "Certified Budget Allocation: Php <amount underlined>"
and "CBA#… / <fund>" on the right; the originating unit's head and FMD's
certifying officer side by side; "Recommending Approval:" (side by side);
"Approved by:" **centered**. Each signature label stays on the page of its names.

**The body as typed** (`MemoRequestPrintService::bodyBlocks()`) — one line = one
block, blank lines only separate:

| Typed line | Prints as |
|---|---|
| `I. Backgrounder` | section heading, bold — numeral at the margin, text at 0.5in |
| `a. The first-year …` | lettered item — letter at 0.5in, text at 0.75in (hanging) |
| `    This amendment …` (leading spaces) | continues at the last item's text indent |
| `1. Summary of Computation \| – Annex "A"` | numbered item; after ` \| ` a right column at 4.25in; a list prints tight |
| `\| No. \| Areas \| … \|` (lines starting with `\|`) | a bordered table: first line = header (centered); a one-cell line spans the table; a line with fewer cells = total row (label spans the rest, right, bold). Whole numbers center, amounts right |
| anything else | a justified paragraph |
| `**text**` anywhere | bold |

**Fixed vs entered:** only the form's labels and wording are fixed (MEMORANDUM,
FOR / THRU / SUBJECT, "Certified Budget Allocation: Php", "CBA#",
"Recommending Approval:", "Approved by:", "Page X of Y"). Every value prints
**only as entered, no defaults**. With no amount the certification prints a blank
line for FMD to fill in; the "CBA#… / <fund>" line prints only when entered.

**Data:** `date` (blank → the print date), `for_name` / `for_title`,
`thru_name` / `thru_title`, `subject`, `body`, `amount`, `cba_number`,
`fund_source_name`, `requested_by_name` / `_title`, `certified_by_name` /
`_title`, `recommending` [{ `name`, `title` }] (none → left out),
`approved_by_name` / `_title`, `running_title`, `memo_number` (file name).
A `\n` in a title starts a new line.

**Not wired yet:** the Memo Request form (`form/budget-allocation-form.php`) does
not offer this print yet — it is being changed on another branch. After that
merge: non-FMD users print `MEMO`, FMD keeps the CBA / RFA / LAA / CFA.

## DV — Disbursement Voucher

General Form No. 1 (revised November 3, 1992). **PDF only**: the official form
is a fillable PDF filled with pdftk, ported from the old REGIONAL
`DisbursementService::buildDvFieldMap()` / `fillDvTemplate()` — no HTML or DOCX
layout, no letterhead (the form carries its own heading). No DV module in this
repo yet, so it prints from the payload.

| | |
|---|---|
| Endpoint | `POST /api/print/dv` (`format` = pdf only — html / docx → 400) |
| Model / PDF | `DvPrintService::model()` / `pdf()` (`PrintService::DOCS` `'pdf_only' => true`) |
| Preview | `prp_open(payload, { type: 'DV' })` — shows the PDF in the browser's viewer; no DOCX, no Edit Letterhead |
| Paper | Legal |
| File name | `<voucher_number>` (blank → `DV-DRAFT`) |
| Requires | `pdftk` on the PHP host (`/usr/bin/pdftk` in the Docker image; `PDFTK_PATH` overrides) |

**Two papers (`paper`):**

- `plain` (default) — prints the whole form on blank paper:
  `_shared/template/dv_form.pdf` (the fields) filled and flattened over page 1 of
  `dv_background.pdf` (the form itself), plus page 2 — the back, Instructions +
  Checklist (`back_page: false` leaves it out).
- `preprinted` — `_shared/template/dv.pdf`, REGIONAL's original field layout for
  the physical green forms: text only, to feed pre-printed DV stock. One page.

**Templates.** `dv_background.pdf` is the cleaned 200-dpi scan of the official
form, front and back, from `ACCOUNTING/app/pdf-forms/templates/DV-10-9-2026.pdf`
(the clearest copy available; replace it when a cleaner one turns up — keep 2
legal pages, front first). `dv_form.pdf` is `dv.pdf` with every field's
rectangle moved onto the scan's boxes; Amount in Words is taller (up to 3 lines,
7pt) than on `dv.pdf`. If the background is replaced, the field rectangles have
to be re-aligned to it.

pdftk works on temp copies of the templates — run straight from a mounted
folder (the Docker dev setup) each call took 20 s+.

**Data:** `voucher_number`, `route` (`MAIN` | `REGIONAL` — also the agency line
"NATIONAL HOUSING AUTHORITY - MAIN OFFICE" / "- REGIONAL OFFICE"; `paying_agency`
overrides it), `payee_name`, `payee_address`, `payee_tin`, `particulars`,
`gross_amount` (or `amount`; printed in the amount column, Box B's "₱" line and
Box C with the amount in words), `deductions` [{ `description`, `amount` }] (up
to 12 lines, listed under the particulars, then "Total Deductions" —
`total_deductions` or their sum), `accounting_entries` [{ `obligation`, `fpa`,
`object_code`, `account_code`, `debit`, `credit` }] (up to 11 rows);
Box A — `cert_officer1_name` / `_title` (centred), with `cert_officer2_name` /
`_title` the two print side by side; Box B — MAIN: `fmd_officer_name` / `_title`
(blank → the current FMD Department Manager, "Department Manager, FMD") and
`accountant_name` / `_title`; REGIONAL: `accountant_name` / `_title` alone;
Box C — `approving_officer` / `approver_title` (REGIONAL; MAIN leaves it blank).

Not on the form's fields (filled by hand, as on the old print): Journalized /
Indexed, the CHECK and OFFICIAL RECEIPT boxes, Received From / releasing
officer, and For COA Use Only.

## Requirements

- Node on the PHP host (Dockerfile installs it) and the `docx` npm package —
  listed in `package.json` / `package-lock.json`, so `npm install` brings it.

## Module integration

Where each form prints from (every page includes
`app/pages/readone/print-preview-readone.php` with `include_once`). The
record → payload mappings live **once**, in that partial, next to `prp_open()`:
`prp_dvPayload(dv)`, `prp_jevFromDv(dv)`, `prp_iarPayload(rec, po)`,
`prp_jevFromProforma(poNumber, receipt)` — a page that prints the same record
calls the same helper.

| Module page | Button | Form | Data |
|---|---|---|---|
| Memo Request — `form/budget-allocation-form.php` | Print Preview | CBA / RFA / LAA / CFA (by `allocation_type`) | the form on screen (`printCBA()`) |
| Purchase Orders — `po/page.php` (row) and `po/readone.php` | Print | PO | `{ po_id }` — PurchaseOrderPrintService loads the PO |
| Disbursement Vouchers — `voucher/readone.php` (`voucher.php`'s view modal) | Print / Print JEV (once Accounting saved the JEV) | DV / JEV | `GET /voucher/read/{id}` → `prp_dvPayload` / `prp_jevFromDv` |
| Journal Entry Vouchers — `journal-entry-voucher.php` (row) | Print | JEV | the row's DV (`/voucher/read/{dv_id}`) → `prp_jevFromDv` |
| IAR Module — `iar-module.php` (row; pro-forma view) | Print IAR; Print JEV | IAR; JEV | the row (IAR header + delivery lines) + its PO (`/purchase-order/all?search=`) → `prp_iarPayload`; the delivery receipt's pro-forma → `prp_jevFromProforma` |

DV deductions print with the withholding taxes (ATC lines) under the
particulars; JEV account titles are filled in server-side from
`fims.chart_of_accounts` (`JevPrintService::accountTitles()`); the IAR's date
inspected is the latest delivery, and it is a complete delivery when the
delivered amount reaches the PO total.

## Next (not done)

- **Done — Memo Request:** the memo request form
  (`app/pages/form/budget-allocation-form.php`, the modal of `memo-req-page.php`)
  prints through this preview: **Print Preview** → `printCBA()` builds the payload
  from the form and calls `prp_open(payload, { type })` — `allocation_type` CBA →
  CBA (CO / COE), RFA → RFA, LAA → LAA, CFA → CFA; a Regional / District
  requesting office prints the regional CBA. Still open there: the LAA's
  FOR / THRU (no fields on the form yet — prints "Dear Sir/Madam"), the CO
  "Sources of Fund" line (only when a budget line has a Type of Fund), and a Print
  action on the list rows (the list's records keep their amounts in
  `line_transaction`).
- **Done — PO, DV, JEV, IAR:** see "Module integration" above. Not printed by
  those modules yet: the DV's obligation / budget ledger (no DV form fields for
  it), the IAR's inspector / signatories and inspection status (no data —
  prints blank / complete-by-amount), the PO readone (no PHPMaker route serves
  `po/readone.php`, so its Print isn't reachable until one does).
- Call `prp_open(..., { type: 'LV' })` from the LV module once it exists.
- Call `prp_open(..., { type: 'FTLA' })` / `{ type: 'FTLAREQ' }` from the fund
  transfer pages once they exist.
- Next: the Payroll / Check / Replenishment DV variants — see "Adding a form" above.
