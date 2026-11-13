/**
 * LAA release request (regional memorandum) — DOCX generator.
 *
 *   node scripts/gen_laareq_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by LaaRequestPrintService::model() — or FtlaRequestPrintService::model()
 * (the FTLA request memo) or CfaPrintService::model() (the CFA memo), which share
 * this layout — every word is already resolved there; this script only lays it
 * out. The same model renders the HTML preview / PDF (LaaRequestPrintService::html).
 *
 * Layout ported from the old REGIONAL gen_laa_docx.js: letter paper (8.5" × 11"),
 * 1in sides, Arial 11pt; MEMORANDUM, FOR / THRU / SUBJECT rows (label 1.2",
 * colon 0.3", value 5"), a rule, the request paragraph, closing and signatory.
 * Optional model parts (the CFA): paper 'A4'; a { list } paragraph (indented
 * "Label : value" rows); certification lines; signature_rows (side-by-side
 * signatories with a label); letterhead_first_page_only (the letterhead is the
 * first-page header, pdf_running_header.lines + "Page X of Y" the header after).
 * The memo request (MemoRequestPrintService) adds: { block: 'item' } paragraphs
 * (hanging indent, optional right "aside" column), { block: 'table' } paragraphs
 * (bordered, header / group / total rows), run underline, a centered signature
 * row, and pdf_running_header.page_first / italic.
 *
 * Header: the model's header_parts (letterhead layout — edited for this print
 * or the saved default), else the built-in banner via _shared/docx-branding.js
 * (UAC → Header Management, scope FMD / LAAREQ) →
 * _shared/images/letterhead/nha-letterhead-banner.png. No footer line.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, Header,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, PageNumber, TabStopType,
} = docx;
const fs = require('fs');
const path = require('path');
const branding = require('../_shared/docx-branding');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_laareq_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Page ──────────────────────────────────────────────────────────────────────
const A4 = m.paper === 'A4';
const PAGE = A4 ? { width: 11906, height: 16838 } : { width: 12240, height: 15840 };   // A4 / letter
const TEXT_W = PAGE.width - 2880;    // 1in sides (twips): 9026 A4 / 9360 letter

// ── Header ────────────────────────────────────────────────────────────────────
const HEADER_W = A4 ? 602 : 624;     // px — the 451.28pt / 468pt (6.5") text width
const PART_SCALE = HEADER_W / 630;   // layout sizes are given for a 630 px letterhead

function defaultHeader() {
    try {
        const buf = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-letterhead-banner.png'));
        const isPng = buf.length > 24 && buf.toString('ascii', 1, 4) === 'PNG';
        const w = isPng ? buf.readUInt32BE(16) : 0;
        const h = isPng ? buf.readUInt32BE(20) : 0;
        const k = w && h ? Math.min(HEADER_W / w, 120 / h) : 0;
        return { base64: buf.toString('base64'), width: k ? Math.round(w * k) : HEADER_W, height: k ? Math.round(h * k) : 94 };
    } catch (e) {
        return { base64: null, width: HEADER_W, height: 94 };
    }
}

let headerChildren;
if (Array.isArray(m.header_parts)) {
    const parts = m.header_parts.map(function (p) {
        const q = Object.assign({}, p);
        if (p.base64) q.data = Buffer.from(p.base64, 'base64');
        if (p.render) q.render = { width: Math.round(p.render.width * PART_SCALE), height: Math.round(p.render.height * PART_SCALE) };
        ['x', 'y', 'box_width'].forEach(function (key) { if (p[key] != null) q[key] = p[key] * PART_SCALE; });
        return q;
    });
    headerChildren = parts.length
        // origin: left margin / header distance (twips) — see the page margins below
        ? branding.buildHeaderChildren(docx, { parts, totalWidth: HEADER_W, origin: { left: 1440, top: 708 } }).children
        : [new Paragraph({ children: [] })];
} else {
    const fb = defaultHeader();
    headerChildren = branding.buildHeaderChildren(docx, {
        system: 'FMD', document: m.brand_document || 'LAAREQ', data: m,
        fallbackBase64: fb.base64, totalWidth: fb.width, totalHeight: fb.height,
    }).children;
}

// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ = 22;   // 11pt

function run(text, o) {
    o = o || {};
    return new TextRun({ text: String(text == null ? '' : text), font: FONT, size: o.size || SZ, bold: !!o.bold,
        italics: o.italic ? true : undefined, underline: o.underline ? {} : undefined });   // unset unless used (no extra XML)
}
function p(children, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(children) ? children : [children],
        alignment: o.align || AlignmentType.LEFT,
        spacing: { before: o.before || 0, after: o.after !== undefined ? o.after : 0 },
        border: o.border,
        indent: o.indent,
        ...(o.keepNext ? { keepNext: true } : {}),   // only when asked: keeps the other forms' XML unchanged
    });
}

const NONE = { style: BorderStyle.NONE, size: 0, color: 'auto' };
const noBorder = { top: NONE, bottom: NONE, left: NONE, right: NONE };
const COLS = [1728, 432, TEXT_W - 2160];   // label 1.2" | colon 0.3" | value (5" on letter)

const cell = (paras, i) => new TableCell({
    borders: noBorder, width: { size: COLS[i], type: WidthType.DXA }, verticalAlign: VerticalAlign.TOP, children: paras,
});
const row = (label, valueParas) => new TableRow({ children: [
    cell([p([run(label, { bold: true })])], 0),
    cell([p([run(':')])], 1),
    cell(valueParas, 2),
] });

// ── Body ──────────────────────────────────────────────────────────────────────
const children = [];
children.push(p([run(m.date)], { after: 280 }));
children.push(p([run('MEMORANDUM', { bold: true })], { after: 280 }));

const rows = (m.rows || []).map(r => row(r.label, [p([run(r.name, { bold: true })]), p([run(r.title || '')], { after: 200 })]));
rows.push(row('SUBJECT', [p([run(m.subject, { bold: true })])]));
children.push(new Table({
    width: { size: COLS.reduce((s, w) => s + w, 0), type: WidthType.DXA },
    columnWidths: COLS,
    borders: Object.assign({}, noBorder, { insideHorizontal: NONE, insideVertical: NONE }),
    rows,
}));

children.push(p([], { before: 160, after: 240, border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: '000000', space: 1 } } }));
const plainTable = (widths, rows, o) => new Table({
    width: { size: widths.reduce((s, w) => s + w, 0), type: WidthType.DXA },
    columnWidths: widths,
    borders: Object.assign({}, noBorder, { insideHorizontal: NONE, insideVertical: NONE }),
    indent: o && o.indent ? { size: o.indent, type: WidthType.DXA } : undefined,
    rows,
});
const plainCell = (paras, width) => new TableCell({
    borders: noBorder, width: { size: width, type: WidthType.DXA }, verticalAlign: VerticalAlign.TOP, children: paras,
});

(m.paragraphs || []).forEach(para => {
    if (para && para.list) {   // indented "Label : value" rows
        const lw = [2600, 432, TEXT_W - 720 - 3032];
        children.push(plainTable(lw, para.list.map(([label, value]) => new TableRow({ children: [
            plainCell([p([run(label, { underline: true })], { after: 120 })], lw[0]),
            plainCell([p([run(':')])], lw[1]),
            plainCell([p([run(value)], { after: 120 })], lw[2]),
        ] })), { indent: 720 }));
        children.push(p([], { after: 160 }));
        return;
    }
    if (para && para.block === 'item') {   // hanging-indent item (the memo request): label at label_at, text at text_at (pt)
        const left = Math.round(para.text_at * 20);
        const hang = Math.round((para.text_at - para.label_at) * 20);
        const kids = [];
        const tabs = [];
        if (para.label) {
            kids.push(run(para.label, { bold: para.label_bold }), run('\t'));
            tabs.push({ type: TabStopType.LEFT, position: left });
        }
        para.runs.forEach(r => kids.push(run(r.text, { bold: r.bold })));
        if (para.aside) {   // the right column ("– Annex "A"")
            kids.push(run('\t'), run(para.aside));
            tabs.push({ type: TabStopType.LEFT, position: Math.round(para.aside_at * 20) });
        }
        children.push(new Paragraph({
            children: kids,
            alignment: para.aside ? AlignmentType.LEFT : AlignmentType.JUSTIFIED,
            spacing: { before: 0, after: Math.round((para.after || 0) * 20) },
            indent: { left, hanging: hang },
            tabStops: tabs,
        }));
        return;
    }
    if (para && para.block === 'table') {   // bordered table: header, rows, one-cell group rows, total rows
        const cols = para.header.length;
        const W = para.widths.map(f => Math.round(f * TEXT_W));
        W[cols - 1] = TEXT_W - W.slice(0, cols - 1).reduce((s, w) => s + w, 0);
        const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
        const ALIGN = { center: AlignmentType.CENTER, right: AlignmentType.RIGHT };
        const tcell = (runs, j, o) => {
            o = o || {};
            const span = o.span || 1;
            return new TableCell({
                borders: { top: LINE, bottom: LINE, left: LINE, right: LINE },
                width: { size: W.slice(j, j + span).reduce((s, w) => s + w, 0), type: WidthType.DXA },
                columnSpan: span > 1 ? span : undefined,
                margins: { left: 80, right: 80 },
                verticalAlign: VerticalAlign.TOP,
                children: [new Paragraph({ alignment: o.align || AlignmentType.LEFT,
                    children: runs.map(r => run(r.text, { bold: o.bold || r.bold, size: 20 })) })],
            });
        };
        const trs = [new TableRow({ children: para.header.map((h, j) => tcell(h, j, { align: AlignmentType.CENTER })) })];
        para.rows.forEach(r => {
            let cells;
            if (r.kind === 'group') {
                cells = [tcell(r.cells[0], 0, { span: cols })];
            } else if (r.kind === 'total') {
                cells = [tcell(r.cells[0], 0, { span: r.span, align: AlignmentType.RIGHT, bold: true })]
                    .concat(r.cells.slice(1).map((c, k) => tcell(c, r.span + k, { align: ALIGN[para.align[r.span + k]], bold: true })));
            } else {
                cells = r.cells.map((c, j) => tcell(c, j, { align: ALIGN[para.align[j]] }));
            }
            trs.push(new TableRow({ children: cells }));
        });
        children.push(new Table({ width: { size: TEXT_W, type: WidthType.DXA }, columnWidths: W, rows: trs }));
        children.push(p([], { after: Math.round((para.after || 0) * 20) - 200 }));   // the space after (less the empty line's own height)
        return;
    }
    children.push(p(para.map(r => run(r.text, { bold: r.bold })), { align: AlignmentType.JUSTIFIED, after: 280 }));
});
if (m.closing) children.push(p([run(m.closing)]));

// FMD's certification (e.g. "Certified Funds Available: Php …"), on the right half
(m.certification || []).forEach((line, i) =>
    children.push(p(line.map(r => run(r.text, { bold: r.bold, underline: r.underline })), { before: i === 0 ? 360 : 0, indent: { left: Math.round(TEXT_W / 2) } })));

if (Array.isArray(m.signature_rows) && m.signature_rows.length) {
    // rows of side-by-side signatories, each under an optional label ("Recommending Approval:")
    m.signature_rows.forEach(r => {
        // keepNext: the label and the signature space stay on the page of the names
        const align = r.center ? AlignmentType.CENTER : undefined;   // e.g. the memo request's "Approved by:"
        if (r.label) children.push(p([run(r.label)], { before: 480, keepNext: true, align }));
        const n = Math.max(1, (r.signers || []).length);
        const widths = Array.from({ length: n }, (_, i) => i < n - 1 ? Math.floor(TEXT_W / n) : TEXT_W - Math.floor(TEXT_W / n) * (n - 1));
        children.push(p([], { after: 720, keepNext: true }));   // space for the wet signature
        children.push(plainTable(widths, [new TableRow({ children: (r.signers || []).map((s, i) => plainCell(
            [p([run(s.name || ' ', { bold: true })], { align })].concat((s.title_lines || []).map(l => p([run(l)], { align }))), widths[i])) })]));
    });
} else {
    // Space for the wet signature
    for (let i = 0; i < 4; i++) children.push(p([run('')], { after: i === 3 ? 80 : 0 }));
    const sig = m.signatory || {};
    children.push(p([run(sig.name, { bold: true })]));
    children.push(p([run(sig.title)]));
    if (sig.office) children.push(p([run(sig.office)]));
}

// ── Document ──────────────────────────────────────────────────────────────────
// Letterhead on page 1 only (the CFA): it is the first-page header; later pages get the running
// title + "Page X of Y" at the top right.
const firstOnly = !!m.letterhead_first_page_only;
// The memo request: "Page X of Y" first, upright (pdf_running_header.page_first / italic).
const rh = m.pdf_running_header || {};
const running = rh.lines || [];
const rhItalic = rh.italic !== false;
const titleParas = running.map(l => p([run(l, { italic: rhItalic, size: 18 })], { align: AlignmentType.RIGHT }));
const pageNoPara = new Paragraph({ alignment: AlignmentType.RIGHT, children: [
    new TextRun({ font: FONT, size: 18, italics: rhItalic ? true : undefined, children: ['Page ', PageNumber.CURRENT, ' of ', PageNumber.TOTAL_PAGES] }),
] });
const runningHeader = new Header({ children: rh.page_first ? [pageNoPara].concat(titleParas) : titleParas.concat([pageNoPara]) });
const doc = new Document({
    sections: [{
        properties: Object.assign({
            page: {
                size: PAGE,
                margin: { top: 1440, right: 1440, bottom: 1080, left: 1440, header: 708, footer: 708 },
            },
        }, firstOnly ? { titlePage: true } : {}),
        headers: firstOnly
            ? { first: new Header({ children: headerChildren }), default: runningHeader }
            : { default: new Header({ children: headerChildren }) },
        children,
    }],
});

Packer.toBuffer(doc).then(function (buf) {
    fs.writeFileSync(outPath, buf);
    console.log('OK:' + outPath);
}).catch(function (err) {
    process.stderr.write(err.message + '\n');
    process.exit(1);
});
