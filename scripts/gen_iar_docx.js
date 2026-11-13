/**
 * IAR (Inspection and Acceptance Report) — DOCX generator.
 *
 *   node scripts/gen_iar_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by IarPrintService::model() — every word and amount is already resolved
 * there; this script only lays it out. The same model renders the HTML preview /
 * PDF (IarPrintService::html).
 *
 * Layout from the old ACCOUNTING (PIS) IarPdfService: letter paper, 0.55"
 * margins, Times New Roman — title, Supplier + IAR No., P.O. No. / S.I. No. with
 * dates, Requisitioning Office/Dept., items table (Stock No., Unit Price,
 * Description, Quantity — "*** Nothing Follows ***", at least model.min_rows
 * rows), the inspection checks, three signatories.
 *
 * No header or footer: the IAR prints its own title block, so it has no
 * letterhead (it is not in LetterheadLayoutService::DOCUMENTS).
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, UnderlineType, TableLayoutType, HeightRule,
} = docx;
const fs = require('fs');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_iar_docx.js <model.json> <out.docx>\n');
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
const SZ = 20, SZ_SM = 17, SZ_XS = 19, SZ_TH = 22, SZ_TITLE = 30;   // 10 / 8.5 / 9.5 / 11 / 15pt
const BOX_ON = '☒', BOX_OFF = '☐';                            // ☒ / ☐

function run(text, o) {
    o = o || {};
    return new TextRun({
        text: String(text == null ? '' : text), font: o.font || FONT, size: o.size || SZ,
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
const lines = (text, o) => String(text == null ? '' : text).split(/\r?\n/).map(l => p([run(l, o)], o));
const box = (on) => run((on ? BOX_ON : BOX_OFF) + ' ', { font: 'Segoe UI Symbol', size: 24 });
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT;

const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const borders = (o) => Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o || {});

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        borders: borders(o.borders),
        verticalAlign: o.valign || VerticalAlign.TOP,
        margins: { top: 30, bottom: 30, left: 70, right: 70 },
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

// "Label:" + underlined value cells, laid out on one line
function infoRow(shares, cells) {
    const ws = pct(...shares);
    return table(ws, [row(cells.map(function (c, i) {
        if (c === null) return cell([p([])], ws[i]);
        return c.value !== undefined
            ? cell([p([run(c.value || ' ')])], ws[i], { borders: { bottom: LINE }, valign: VerticalAlign.BOTTOM })
            : cell([p([run(c.label, { bold: true })])], ws[i], { valign: VerticalAlign.BOTTOM });
    }))]);
}

// ── Body ──────────────────────────────────────────────────────────────────────
const body = [];
body.push(p([run('INSPECTION AND ACCEPTANCE REPORT', { bold: true, size: SZ_TITLE })], { align: C, after: 320 }));

body.push(infoRow([9, 53, 3, 10, 25], [{ label: 'Supplier:' }, { value: m.supplier }, null, { label: 'IAR No.:' }, { value: m.iar_number }]));
body.push(infoRow([9, 19, 6, 18, 8, 16, 6, 18], [
    { label: 'P.O. No.' }, { value: m.po_number }, { label: 'Date:' }, { value: m.po_date },
    { label: 'S.I. No.' }, { value: m.si_number }, { label: 'Date:' }, { value: m.si_date },
]));
body.push(infoRow([25, 45, 30], [{ label: 'Requisitioning Office/Dept.:' }, { value: m.department }, null]));
body.push(p([], { after: 200 }));

// Items: STOCK NO. | UNIT PRICE | DESCRIPTION | QUANTITY
const iw = pct(14, 18, 54, 14);
const side = (extra) => Object.assign({ left: LINE, right: LINE }, extra || {});
const itemRows = [row(['STOCK NO.', 'UNIT PRICE', 'DESCRIPTION', 'QUANTITY'].map((h, i) =>
    cell([p([run(h, { bold: true, size: SZ_TH })], { align: C })], iw[i], { borders: side({ top: LINE, bottom: LINE }), valign: VerticalAlign.CENTER })))];
const dataRow = (vals) => row(vals.map((v, i) => cell(v, iw[i], { borders: side() })), 280);
(m.items || []).forEach(function (it) {
    itemRows.push(dataRow([[p([run(it.stock_no)], { align: C })], [p([run(it.unit_cost)], { align: R })], lines(it.desc), [p([run(it.qty)], { align: C })]]));
});
let filled = (m.items || []).length;
if (filled > 0) {
    itemRows.push(dataRow([[], [], [p([run('*** Nothing Follows ***', { italic: true })], { align: C })], []]));
    filled++;
}
for (let i = filled; i < (m.min_rows || 0); i++) itemRows.push(dataRow([[], [], [], []]));
// close the table's bottom edge
itemRows.push(row(iw.map((w) => cell([p([run('', { size: 2 })])], w, { borders: { top: LINE } })), 20));
body.push(table(iw, itemRows));
body.push(p([], { after: 160 }));

// Inspection checks
const cw = pct(36, 36, 28);
const NBSP = String.fromCharCode(0xA0);   // Word drops underlines on trailing plain spaces
const dateLine = (v) => p([run("Date Inspected: ", { bold: true, size: SZ_XS }), run(v ? NBSP + NBSP + v + NBSP + NBSP : NBSP.repeat(30), { size: SZ_XS, underline: true })], { after: 160 });
body.push(table(cw, [row([
    cell([dateLine(m.date_verified), p([box(!m.complete), run('Inspected, verified and found in order as to quantity.', { size: SZ_XS })])], cw[0]),
    cell([dateLine(m.date_accepted), p([box(m.complete), run('Inspected, accepted and found to be in accordance with the specification.', { size: SZ_XS })])], cw[1]),
    cell([p([], { after: 300 }), p([box(m.complete), run('Complete delivery', { size: SZ_XS })]), p([box(!m.complete), run('Partial delivery', { size: SZ_XS })])], cw[2]),
])]));

// Three signatories
const sw = pct(33.34, 33.33, 33.33);
body.push(p([], { after: 600 }));
body.push(table(sw, [row((m.signatories || []).map((s, i) => cell([
    p([run(s.name || ' ', { bold: true })], { align: C, border: { bottom: { style: BorderStyle.SINGLE, size: 6, color: '000000', space: 1 } } }),
    p([run(s.label, { size: SZ_SM })], { align: C, before: 40 }),
], sw[i])))]));

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 15840 },   // letter
                margin: { top: MARGIN, right: MARGIN, bottom: MARGIN, left: MARGIN, header: 500, footer: 500 },
            },
        },
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
