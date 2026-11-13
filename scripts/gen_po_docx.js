/**
 * PO (Purchase Order) — DOCX generator.
 *
 *   node scripts/gen_po_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by PurchaseOrderPrintService::model() — every word and amount is already
 * resolved there; this script only lays it out. The same model renders the HTML
 * preview / PDF (PurchaseOrderPrintService::html).
 *
 * Layout merged from the old REGIONAL gen_po_docx.js and the GSD / REGIONAL
 * buildPoHtml(): legal paper (8.5" × 14"), 0.55" sides, Times New Roman 9pt,
 * every section inside one outlined box — title block (NHA logo, PURCHASE ORDER /
 * NATIONAL HOUSING AUTHORITY / Agency), supplier rows, Gentlemen, delivery /
 * payment, items (at least model.min_rows rows), TOTAL LOT PRICE, X-X-X, info
 * rows, total in words, penalty, Conforme + signatories, Funds Available / ALOBS.
 *
 * No header or footer: the PO prints its own title block, so it has no
 * letterhead (it is not in LetterheadLayoutService::DOCUMENTS).
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, ImageRun, Table, TableRow, TableCell,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, UnderlineType, TableLayoutType, HeightRule,
} = docx;
const fs = require('fs');
const path = require('path');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_po_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Page ──────────────────────────────────────────────────────────────────────
const MARGIN = 792;                        // 0.55in
const W = 12240 - MARGIN * 2;              // 10656 twips content width
const pct = (...shares) => {               // twip widths from percentages, summing to W
    const ws = shares.map(s => Math.round(W * s / 100));
    ws[ws.length - 1] += W - ws.reduce((a, b) => a + b, 0);
    return ws;
};


// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Times New Roman';
const SZ = 18, SZ_SM = 16, SZ_MD = 20, SZ_LG = 28, SZ_XL = 22;   // 9 / 8 / 10 / 14 / 11pt

function run(text, o) {
    o = o || {};
    return new TextRun({
        text: String(text == null ? '' : text), font: FONT, size: o.size || SZ,
        bold: !!o.bold, italics: !!o.italic, underline: o.underline ? { type: UnderlineType.SINGLE } : undefined,
    });
}
function p(children, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(children) ? children : [children],
        alignment: o.align || AlignmentType.LEFT,
        spacing: { before: o.before || 0, after: o.after || 0 },
        border: o.border,
    });
}
// Multi-line text → one paragraph per line
const lines = (text, o) => String(text == null ? '' : text).split(/\r?\n/).map(l => p([run(l, o)], o));

const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const OUTER = { style: BorderStyle.SINGLE, size: 10, color: '000000' };
const borders = (o) => Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o || {});

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        borders: borders(o.borders),
        columnSpan: o.span,
        verticalAlign: o.valign || VerticalAlign.TOP,
        margins: { top: 30, bottom: 30, left: 80, right: 80 },
    });
}
function table(widths, rows, o) {
    o = o || {};
    return new Table({
        width: { size: W, type: WidthType.DXA },
        columnWidths: widths,
        layout: TableLayoutType.FIXED,
        borders: { top: o.top || NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE },
        rows,
    });
}
const row = (cells, h) => new TableRow({ children: cells, cantSplit: true, height: h ? { value: h, rule: HeightRule.ATLEAST } : undefined });
const peso = (v) => v ? '₱ ' + v : '';

// ── Body ──────────────────────────────────────────────────────────────────────
const body = [];

// Title block — the NHA logo (as GSD's form), PURCHASE ORDER / NHA / Agency
const titleParas = [];
try {
    const logo = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-logo.png'));
    const w = logo.readUInt32BE(16), h = logo.readUInt32BE(20);
    titleParas.push(new Paragraph({ children: [new ImageRun({ type: 'png', data: logo, transformation: { width: 53, height: Math.round(53 * h / w) } })], alignment: AlignmentType.CENTER }));
} catch (e) { /* no logo file — title text only */ }
titleParas.push(p([run('PURCHASE ORDER', { bold: true, size: SZ_LG })], { align: AlignmentType.CENTER }));
titleParas.push(p([run('NATIONAL HOUSING AUTHORITY', { bold: true, size: SZ_XL })], { align: AlignmentType.CENTER }));
titleParas.push(p([run('Agency', { underline: true, size: SZ_MD })], { align: AlignmentType.CENTER, after: 80 }));
body.push(table([W], [row([cell(titleParas, W, { borders: { bottom: LINE } })])]));

// Supplier | P.O. No. / Date / Mode of Procurement
const sw = pct(11, 43, 20, 26);
const mid = { left: LINE };
body.push(table(sw, [
    row([cell([p([run('Supplier:', { bold: true })])], sw[0]), cell([p([run(m.supplier.name, { bold: true, size: SZ_MD })])], sw[1]),
         cell([p([run('P.O. No.:', { bold: true })])], sw[2], { borders: mid }), cell([p([run(m.po_number, { bold: true, underline: true })])], sw[3])]),
    row([cell([p([run('Address:', { bold: true })])], sw[0]), cell(lines(m.supplier.address), sw[1]),
         cell([p([run('Date:', { bold: true })])], sw[2], { borders: mid }), cell([p([run(m.date, { underline: true })])], sw[3])]),
    row([cell([p([run('TIN:', { bold: true })])], sw[0]), cell([p([run(m.supplier.tin)])], sw[1]),
         cell([p([run('Mode of Procurement:', { bold: true })])], sw[2], { borders: mid }), cell([p([run(m.mode)])], sw[3])]),
]));

// Gentlemen
body.push(table([W], [row([cell([
    p([run('Gentlemen:', { bold: true, size: SZ_MD })], { before: 40 }),
    p([run('Please furnish this Office the following articles subject to the terms and conditions contained herein:', { italic: true })], { align: AlignmentType.CENTER, after: 40 }),
], W)])], { top: LINE }));

// Delivery / payment
const dw = pct(17, 34, 18, 31);
body.push(table(dw, [
    row([cell([p([run('Place of Delivery:', { bold: true })])], dw[0]), cell([p([run(m.delivery_place)])], dw[1]),
         cell([p([run('Delivery Term:', { bold: true })])], dw[2], { borders: mid }), cell([p([run(m.delivery_term, { bold: true, underline: true })])], dw[3])], 300),
    row([cell([p([run('Date of Delivery:', { bold: true })])], dw[0]), cell([p([run(m.delivery_date)])], dw[1]),
         cell([p([run('Payment Term:', { bold: true })])], dw[2], { borders: mid }), cell([p([run(m.payment_term, { bold: true, underline: true })])], dw[3])], 300),
], { top: LINE }));

// Items
const iw = pct(7, 8, 48, 9, 14, 14);
const colB = (i, extra) => Object.assign(i > 0 ? { left: LINE } : {}, extra || {});
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT;
const itemRows = [row(['Item No.', 'Unit', 'Description', 'Quantity', 'Unit Cost', 'Amount'].map((h, i) =>
    cell([p([run(h, { bold: true, size: SZ_SM })], { align: C })], iw[i], { borders: colB(i, { top: LINE, bottom: LINE }), valign: VerticalAlign.CENTER })))];
(m.items || []).forEach(function (it) {
    itemRows.push(row([
        cell([p([run(it.no)], { align: C })], iw[0], { borders: colB(0) }),
        cell([p([run(it.unit)], { align: C })], iw[1], { borders: colB(1) }),
        cell(lines(it.desc), iw[2], { borders: colB(2) }),
        cell([p([run(it.qty)], { align: C })], iw[3], { borders: colB(3) }),
        cell([p([run(peso(it.unit_cost))], { align: R })], iw[4], { borders: colB(4) }),
        cell([p([run(peso(it.amount))], { align: R })], iw[5], { borders: colB(5) }),
    ]));
});
for (let i = (m.items || []).length; i < (m.min_rows || 0); i++) {
    itemRows.push(row(iw.map((w, c) => cell([p([run('')])], w, { borders: colB(c) })), 260));
}
const blankCells = (from, to) => iw.slice(from, to + 1).map((w, k) => cell([p([run('')])], w, { borders: colB(from + k) }));
itemRows.push(row([
    ...blankCells(0, 1),
    cell([p([run('TOTAL LOT PRICE:', { bold: true, size: SZ_MD })], { align: C, before: 160 })], iw[2], { borders: colB(2) }),
    ...blankCells(3, 3),
    cell([p([run(peso(m.total), { bold: true })], { align: R, before: 160 })], iw[4], { borders: colB(4) }),
    cell([p([run(peso(m.total), { bold: true })], { align: R, before: 160 })], iw[5], { borders: colB(5) }),
]));
itemRows.push(row([
    ...blankCells(0, 1),
    cell([p([run('X-X-X-X-X-X-X-X-X-X', { italic: true })], { align: C, after: 160 })], iw[2], { borders: colB(2) }),
    ...blankCells(3, 5),
]));
(m.info || []).forEach(function (r) {
    itemRows.push(row([
        cell([p([run(r[0], { bold: true })], { align: R })], iw[0] + iw[1], { span: 2 }),
        cell(lines(r[1]), iw[2], { borders: colB(2) }),
        ...blankCells(3, 5),
    ]));
});
itemRows.push(row(iw.map((w, c) => cell([p([run('')])], w, { borders: colB(c) })), 800));
body.push(table(iw, itemRows));

// Total in words | total
const tw = pct(75, 25);
body.push(table(tw, [row([
    cell([p([run('Total Amount in Words: ', { bold: true }), run(m.total_words + '.', { bold: true, underline: true })])], tw[0], { valign: VerticalAlign.CENTER }),
    cell([p([run(peso(m.total), { bold: true, size: SZ_MD })], { align: R })], tw[1], { borders: { left: LINE }, valign: VerticalAlign.CENTER }),
])], { top: LINE }));

// Penalty
body.push(table([W], [row([cell([p([run(m.penalty, { italic: true, size: SZ_SM })])], W)])], { top: LINE }));

// Conforme | approving signatories
const gw = pct(54, 46);
const underline = (w) => new Table({
    width: { size: w, type: WidthType.DXA }, columnWidths: [w], layout: TableLayoutType.FIXED,
    rows: [new TableRow({ height: { value: 520, rule: HeightRule.ATLEAST }, children: [new TableCell({ width: { size: w, type: WidthType.DXA }, borders: borders({ bottom: LINE }), children: [p([])] })] })],
});
const sigRows = [row([
    cell([
        p([run('Conforme:', { bold: true })], { after: 40 }),
        underline(gw[0] - 200),
        p([run('Signature over printed name of supplier', { italic: true, size: SZ_SM })], { align: C }),
        underline(gw[0] - 200),
        p([run('DATE', { bold: true })], { align: C, after: 60 }),
    ], gw[0]),
    cell([
        p([run('')], { after: 600 }),
        p([run(m.approving.name || ' ', { bold: true, underline: !!m.approving.name })], { align: C }),
        p([run(m.approving.title, { size: SZ_SM })], { align: C, after: 400 }),
        p([run(m.gm.name || ' ', { bold: true, underline: !!m.gm.name })], { align: C }),
        p([run(m.gm.title, { size: SZ_SM })], { align: C }),
    ], gw[1]),
])];
if (m.prepared_initials) {
    sigRows.push(row([cell([p([run(m.prepared_initials, { italic: true, size: SZ_SM })])], gw[0]), cell([p([])], gw[1])]));
}
body.push(table(gw, sigRows, { top: LINE }));

// Funds Available | ALOBS
const fw = pct(52, 48);
const label = (text, value, o) => p([run(text, { bold: true }), run(value ? ' ' + value : '', o)]);
body.push(table(fw, [row([
    cell([
        label('Funds Available:', m.funds_available),
        underline(fw[0] - 200),
        p([run('')], { after: 160 }),
        p([run(m.certified.name || ' ', { bold: true, underline: !!m.certified.name })], { align: C }),
        p([run(m.certified.title, { size: SZ_SM })], { align: C, after: 60 }),
    ], fw[0]),
    cell([
        label('ALOBS No.:', m.alobs.no, { underline: true }),
        underline(fw[1] - 200),
        p([run('')], { after: 120 }),
        label('Amount:', m.alobs.amount),
        underline(fw[1] - 200),
        ...(m.alobs.adj ? [label('Adj:', m.alobs.adj)] : []),
    ], fw[1], { borders: { left: LINE } }),
])], { top: LINE }));

// ── Document — everything inside one outlined box ────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 20160 },   // legal
                margin: { top: MARGIN, right: MARGIN, bottom: MARGIN, left: MARGIN, header: 720, footer: 720 },
            },
        },
        children: [new Table({
            width: { size: W, type: WidthType.DXA },
            columnWidths: [W],
            layout: TableLayoutType.FIXED,
            rows: [new TableRow({ children: [new TableCell({
                width: { size: W, type: WidthType.DXA },
                borders: { top: OUTER, bottom: OUTER, left: OUTER, right: OUTER },
                margins: { top: 0, bottom: 0, left: 0, right: 0 },
                children: body,
            })] })],
        })],
    }],
});

Packer.toBuffer(doc).then(function (buf) {
    fs.writeFileSync(outPath, buf);
    console.log('OK:' + outPath);
}).catch(function (err) {
    process.stderr.write(err.message + '\n');
    process.exit(1);
});
