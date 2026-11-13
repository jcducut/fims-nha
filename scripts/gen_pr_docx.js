/**
 * PR (Purchase Requisition) — DOCX generator.
 *
 *   node scripts/gen_pr_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by PurchaseRequestPrintService::model() — every word and amount is
 * already resolved there; this script only lays it out. The same model renders
 * the HTML preview / PDF (PurchaseRequestPrintService::html).
 *
 * Layout merged from the old REGIONAL gen_pr_docx.js and the GSD / REGIONAL
 * PurchaseRequestPdfService: legal paper (8.5" × 14"), 0.55" sides, Times New
 * Roman 9pt — title + P.R. No., "To the Purchasing Officer" + date, items table
 * (7 columns, at least model.min_rows rows, NOTE, Total), END USER/PURPOSE,
 * CHARGE TO + requested delivery date, the FMD part (a–g | h–n), Request for
 * fund allotment No., four signatories.
 *
 * Header: the model's header_parts (letterhead layout — edited for this print
 * or the saved default), else the built-in banner via _shared/docx-branding.js
 * (UAC → Header Management, scope FMD / PR) →
 * _shared/images/letterhead/nha-letterhead-banner.png. No footer line.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, Header,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, UnderlineType, TableLayoutType, HeightRule,
} = docx;
const fs = require('fs');
const path = require('path');
const branding = require('../_shared/docx-branding');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_pr_docx.js <model.json> <out.docx>\n');
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

// ── Header ────────────────────────────────────────────────────────────────────
const HEADER_W = 710;                      // px — the 532.8pt text width
const PART_SCALE = HEADER_W / 630;

function defaultHeader() {
    try {
        const buf = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-letterhead-banner.png'));
        const isPng = buf.length > 24 && buf.toString('ascii', 1, 4) === 'PNG';
        const w = isPng ? buf.readUInt32BE(16) : 0;
        const h = isPng ? buf.readUInt32BE(20) : 0;
        const k = w && h ? Math.min(HEADER_W / w, 130 / h) : 0;
        return { base64: buf.toString('base64'), width: k ? Math.round(w * k) : HEADER_W, height: k ? Math.round(h * k) : 107 };
    } catch (e) {
        return { base64: null, width: HEADER_W, height: 107 };
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
        ? branding.buildHeaderChildren(docx, { parts, totalWidth: HEADER_W, origin: { left: MARGIN, top: 500 } }).children
        : [new Paragraph({ children: [] })];
} else {
    const fb = defaultHeader();
    headerChildren = branding.buildHeaderChildren(docx, {
        system: 'FMD', document: 'PR', data: m,
        fallbackBase64: fb.base64, totalWidth: fb.width, totalHeight: fb.height,
    }).children;
}

// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Times New Roman';
const SZ = 18, SZ_SM = 16, SZ_XS = 14, SZ_MD = 20, SZ_TITLE = 26;   // 9 / 8 / 7 / 10 / 13pt

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
        indent: o.indent,
        border: o.border,
    });
}
const lines = (text, o) => String(text == null ? '' : text).split(/\r?\n/).map(l => p([run(l, o)], o));
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT;

const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 4, color: '000000' };
const borders = (o) => Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o || {});

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        borders: borders(o.borders),
        columnSpan: o.span,
        rowSpan: o.rowSpan,
        verticalAlign: o.valign || VerticalAlign.TOP,
        margins: { top: 20, bottom: 20, left: 60, right: 60 },
    });
}
function table(widths, rows) {
    return new Table({
        width: { size: W, type: WidthType.DXA },
        columnWidths: widths,
        layout: TableLayoutType.FIXED,
        borders: { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE },
        rows,
    });
}
const row = (cells, h) => new TableRow({ children: cells, cantSplit: true, height: h ? { value: h, rule: HeightRule.ATLEAST } : undefined });

// ── Body ──────────────────────────────────────────────────────────────────────
const body = [];
body.push(p([run('PURCHASE REQUISITION', { bold: true, underline: true, size: SZ_TITLE })], { align: C, after: 40 }));

const hw = pct(60, 40);
body.push(table(hw, [row([cell([p([])], hw[0]), cell([p([run('P.R. NO.', { bold: true, size: SZ_MD }), run('   '), run(m.pr_number, { underline: true, size: SZ_MD })])], hw[1])])]));
body.push(table(hw, [row([
    cell([p([run('To the Purchasing Officer', { bold: true, size: SZ_MD })]), p([run('Please Purchase the following item(s)', { italic: true })], { indent: { left: 360 } })], hw[0]),
    cell([p([run('Date', { bold: true, size: SZ_MD }), run('    ' + (m.date || ''), { size: SZ_MD })])], hw[1]),
])]));
body.push(p([], { after: 60 }));

// Items: Warehouse | QTY | UNIT | DESCRIPTION | Dept./Office | Per Unit | Total Amount
const iw = pct(10.28, 5.99, 5.99, 41.59, 10.65, 12.75, 12.75);
const grid = (i, extra) => Object.assign({ top: LINE, bottom: LINE, left: i > 0 ? LINE : NONE, right: i < 6 ? LINE : NONE }, extra || {});
const head = (texts) => texts.map(t => p([run(t, { bold: true, size: SZ_SM })], { align: C }));
const itemRows = [
    row([
        cell(head(['Warehouse', 'Stock on', 'Hand']), iw[0], { borders: grid(0), rowSpan: 2, valign: VerticalAlign.CENTER }),
        cell(head(['QTY']), iw[1], { borders: grid(1), rowSpan: 2, valign: VerticalAlign.CENTER }),
        cell(head(['UNIT']), iw[2], { borders: grid(2), rowSpan: 2, valign: VerticalAlign.CENTER }),
        cell(head(['DESCRIPTION']), iw[3], { borders: grid(3), rowSpan: 2, valign: VerticalAlign.CENTER }),
        cell(head(['Dept./Office', 'Qty. on', 'Hand']), iw[4], { borders: grid(4), rowSpan: 2, valign: VerticalAlign.CENTER }),
        cell(head(['Estimated Cost']), iw[5] + iw[6], { borders: grid(6), span: 2, valign: VerticalAlign.CENTER }),
    ]),
    row([
        cell(head(['Per', 'Unit']), iw[5], { borders: grid(5), valign: VerticalAlign.CENTER }),
        cell(head(['Total', 'Amount']), iw[6], { borders: grid(6), valign: VerticalAlign.CENTER }),
    ]),
];
const dataRow = (vals, h) => row(vals.map((v, i) => cell(v, iw[i], { borders: grid(i), valign: VerticalAlign.CENTER })), h);
const t8 = (text, align) => [p([run(text, { size: SZ_SM })], { align })];
(m.items || []).forEach(function (it) {
    itemRows.push(dataRow([t8(it.warehouse, C), t8(it.qty, C), t8(it.unit, C), lines(it.desc, { size: SZ_SM }), t8(it.dept_qty, C), t8(it.unit_cost, R), t8(it.amount, R)]));
});
const blankRow = () => dataRow([[], [], [], [], [], [], []], 230);
for (let i = (m.items || []).length; i < (m.min_rows || 0); i++) itemRows.push(blankRow());
itemRows.push(dataRow([[], [], [], [p([run('NOTE: ', { bold: true, underline: true, size: SZ_SM }), run(m.note, { italic: true, size: SZ_SM })], { align: C })], [], [], []]));
itemRows.push(blankRow(), blankRow());
itemRows.push(row([
    cell([p([])], iw[0] + iw[1] + iw[2] + iw[3], { span: 4 }),
    cell([p([run('Total:', { bold: true, size: SZ_MD })], { align: R })], iw[4]),
    cell([p([])], iw[5]),
    cell([p([run(m.total, { bold: true, size: SZ_MD })], { align: R })], iw[6]),
]));
body.push(table(iw, itemRows));

// END USER/PURPOSE
body.push(p([run('END USER/PURPOSE:', { bold: true, size: SZ_MD })], { before: 80, after: 40 }));
body.push(...lines(m.purpose || ' ', { indent: { left: 120 } }));
body.push(p([], { after: 120 }));

// CHARGE TO | Requested Delivery Date
const cw = pct(55, 45);
body.push(table(cw, [row([
    cell([p([run('CHARGE TO:', { bold: true, size: SZ_MD })]), p([run(m.charge_to, { bold: true, size: SZ_MD })], { indent: { left: 360 } })], cw[0]),
    cell([p([run('Requested')]), p([run('Delivery')]), p([run('Date')]), p([run(m.delivery_date)])], cw[1]),
])]));

// FMD part
body.push(p([run('To be accomplished by Financial Management Department', { italic: true, size: SZ_SM })],
    { before: 40, after: 40, border: { bottom: { style: BorderStyle.SINGLE, size: 4, color: '000000', space: 1 } } }));
const fw = pct(32, 18, 32, 18);
const peso = (v, withSign) => v ? (withSign ? '₱ ' : '') + v : '';
const label = (letter, ls) => ls.map((l, i) => p([run((i === 0 ? letter + '   ' : '      ') + l, { size: SZ_SM })]));
const fmdRows = [row([
    cell([p([run('A. Program/Project Reference', { bold: true, size: SZ_SM })], { align: C })], fw[0] + fw[1], { span: 2 }),
    cell([p([run('B. Allotment Reference', { bold: true, size: SZ_SM })], { align: C })], fw[2] + fw[3], { span: 2 }),
])];
(m.part_a || []).forEach(function (a, i) {
    const b = m.part_b[i];
    const aLabel = label(a[0], a[1]);
    if (i === 0 && m.project) aLabel.push(p([run('      ' + m.project, { italic: true, size: SZ_SM })]));
    fmdRows.push(row([
        cell(aLabel, fw[0]),
        cell([p([run(peso(a[2], a[3]), { size: SZ_SM })], { align: R })], fw[1], { borders: { bottom: LINE }, valign: VerticalAlign.BOTTOM }),
        cell(label(b[0], b[1]), fw[2]),
        cell([p([run(peso(b[2], b[3]), { size: SZ_SM })], { align: R })], fw[3], { borders: { bottom: LINE }, valign: VerticalAlign.BOTTOM }),
    ]));
});
body.push(table(fw, fmdRows));

// Request for fund allotment No. (box, right)
const bw = pct(72, 28);
body.push(p([], { after: 60 }));
body.push(table(bw, [row([
    cell([p([])], bw[0]),
    cell([p([run('Request for fund allotment', { size: SZ_XS })]), p([run('No.  ' + (m.fund_allotment_no || ''), { bold: true, size: SZ_XS })])],
        bw[1], { borders: { top: LINE, bottom: LINE, left: LINE, right: LINE } }),
])]));
body.push(p([], { after: 200 }));

// Four signatories
const sw = pct(25, 25, 25, 25);
const sb = (i, extra) => Object.assign({ left: i > 0 ? LINE : NONE, right: i < 3 ? LINE : NONE }, extra);
body.push(table(sw, [
    row(sw.map((w, i) => cell([p([])], w, { borders: sb(i, { top: LINE }) })), 720),
    row((m.signatories || []).map((s, i) => cell([
        p([run(s.name || ' ', { bold: true, underline: !!s.name, size: SZ_SM })], { align: C }),
        p([run(s.role, { size: SZ_SM })], { align: C }),
    ], sw[i], { borders: sb(i, { bottom: LINE }) }))),
]));

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 20160 },   // legal
                margin: { top: 2160, right: MARGIN, bottom: MARGIN, left: MARGIN, header: 500, footer: 500 },
            },
        },
        headers: { default: new Header({ children: headerChildren }) },
        children: body,
    }],
});

Packer.toBuffer(doc).then(function (buf) {
    fs.writeFileSync(outPath, buf);
    console.log('OK:' + outPath);
}).catch(function (err) {
    process.stderr.write(err.message + '\n');
    process.exit(1);
});
