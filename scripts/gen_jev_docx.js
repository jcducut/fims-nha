/**
 * JEV (Journal Entry Voucher) — DOCX generator.
 *
 *   node scripts/gen_jev_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by JevPrintService::model() — every word and amount is already resolved
 * there; this script only lays it out. The same model renders the HTML preview /
 * PDF (JevPrintService::html).
 *
 * The NHA paper JEV (old GLOBAL JevServices::renderPrintHtml): A4 landscape —
 * title box + office / JEV No. / Date; the entries table (Responsibility Center,
 * Accounts and Explanation, SL Code, Subsidiary Ledger Dr/Cr, Account Code, P,
 * General Ledger Dr/Cr) with the reference / particulars / RFA / SARO lines and
 * TOTAL; three signatories; footer remarks and the temporary reference number.
 *
 * No header or footer: the JEV prints its own title block, so it has no
 * letterhead (it is not in LetterheadLayoutService::DOCUMENTS).
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, UnderlineType, TableLayoutType, HeightRule,
    PageOrientation, ShadingType,
} = docx;
const fs = require('fs');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_jev_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Page (A4 landscape) ───────────────────────────────────────────────────────
const PAGE_W = 16838, PAGE_H = 11906;
const SIDE = 680, TOPM = 510;              // 12mm / 9mm
const W = PAGE_W - SIDE * 2;               // 15478 twips content width
const pct = (...shares) => {               // twip widths from percentages, summing to W
    const ws = shares.map(s => Math.round(W * s / 100));
    ws[ws.length - 1] += W - ws.reduce((a, b) => a + b, 0);
    return ws;
};


// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Arial', MONO = 'Courier New';
const SZ = 15, SZ_SM = 14, SZ_XS = 13, SZ_8 = 16, SZ_TITLE = 32;   // 7.5 / 7 / 6.5 / 8 / 16pt
const PESO = '₱ ';

function run(text, o) {
    o = o || {};
    return new TextRun({
        text: String(text == null ? '' : text), font: o.font || FONT, size: o.size || SZ,
        bold: !!o.bold, italics: !!o.italic, color: o.color,
        underline: o.underline ? { type: UnderlineType.SINGLE } : undefined,
    });
}
function p(children, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(children) ? children : [children],
        alignment: o.align || AlignmentType.LEFT,
        spacing: { before: o.before || 0, after: o.after || 0 },
        indent: o.indent,
    });
}
const lines = (text, o) => String(text == null ? '' : text).split(/\r?\n/).map(l => p([run(l, o)], o));
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT;

const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const THIN = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const THICK = { style: BorderStyle.SINGLE, size: 12, color: '000000' };
const GRID = { top: THIN, bottom: THIN, left: THIN, right: THIN };

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        borders: o.borders || GRID,
        columnSpan: o.span,
        rowSpan: o.rowSpan,
        verticalAlign: o.valign || VerticalAlign.CENTER,
        shading: o.fill ? { type: ShadingType.CLEAR, color: 'auto', fill: o.fill } : undefined,
        margins: o.margins || { top: 20, bottom: 20, left: 60, right: 60 },
    });
}
function table(widths, rows, borders) {
    return new Table({
        width: { size: widths.reduce((a, b) => a + b, 0), type: WidthType.DXA },
        columnWidths: widths,
        layout: TableLayoutType.FIXED,
        borders: borders || { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE },
        rows,
    });
}
const row = (cells, h) => new TableRow({ children: cells, cantSplit: true, height: h ? { value: h, rule: HeightRule.ATLEAST } : undefined });
const OUTER = { top: THICK, bottom: THICK, left: THICK, right: THICK, insideHorizontal: NONE, insideVertical: NONE };

// ── Body ──────────────────────────────────────────────────────────────────────
const body = [];

// 1. Title box | office, JEV No., Date
const hw = pct(78, 22);
body.push(table(hw, [row([
    cell([p([run('JOURNAL ENTRY VOUCHER', { bold: true, size: SZ_TITLE })], { align: C }),
          p([run('NATIONAL HOUSING AUTHORITY', { bold: true, size: 24, font: 'Times New Roman', underline: true })], { align: C, before: 60 })],
        hw[0], { borders: { top: THICK, bottom: THICK, left: THICK, right: THICK }, margins: { top: 100, bottom: 100, left: 120, right: 120 } }),
    cell([
        table([hw[1]], [
            row([cell([p([run(m.office, { bold: true, size: 21 })], { align: C })], hw[1], { borders: { top: NONE, bottom: THICK, left: NONE, right: NONE } })], 330),
            row([cell([p([run('JEV No. :  ' + (m.jev_number || ''), { size: 17 })])], hw[1], { borders: { top: NONE, bottom: THIN, left: NONE, right: NONE } })], 330),
            row([cell([p([run('Date :  ' + (m.date || ''), { size: 17 })])], hw[1], { borders: { top: NONE, bottom: NONE, left: NONE, right: NONE } })], 330),
        ]),
        p([run('', { size: 2 })]),   // Word needs a paragraph after a nested table — keep it hairline
    ], hw[1], { borders: { top: THICK, bottom: THICK, left: THICK, right: THICK }, margins: { top: 0, bottom: 0, left: 0, right: 0 } }),
])], OUTER));

// 2. Entries table — 9 columns
const cw = pct(6.6, 43, 6.9, 8.3, 8.3, 8.3, 1.9, 8.3, 8.4);
const th = (text, o) => p([run(text, Object.assign({ bold: true }, o || {}))], { align: C });
const rows = [
    row([
        cell([th('Responsibility', { size: 12 }), th('Center', { size: 12 })], cw[0], { rowSpan: 3, margins: { top: 20, bottom: 20, left: 20, right: 20 } }),
        cell([th('Accounts and Explanation')], cw[1], { rowSpan: 3 }),
        cell([th('SL', { size: SZ_XS }), th('Code', { size: SZ_XS })], cw[2], { rowSpan: 3 }),
        cell([th('ACCOUNTING ENTRIES', { size: 17 })], cw[3] + cw[4] + cw[5], { span: 3 }),
        cell([p([])], cw[6], { rowSpan: 3 }),
        cell([p([])], cw[7] + cw[8], { span: 2, borders: { top: NONE, bottom: THIN, left: THIN, right: NONE } }),
    ]),
    row([
        cell([th('Subsidiary Ledger')], cw[3] + cw[4], { span: 2 }),
        cell([th('Account Code')], cw[5], { rowSpan: 2 }),
        cell([th('General Ledger')], cw[7] + cw[8], { span: 2 }),
    ]),
    row([cell([th('Debit')], cw[3]), cell([th('Credit')], cw[4]), cell([th('Debit')], cw[7]), cell([th('Credit')], cw[8])]),
];
// one body row from 9 [paragraphs] cells
const bodyRow = (cells) => row(cw.map((w, i) => cell(cells[i] || [p([])], w)), 230);   // always all 9 cells
const txt = (paras) => bodyRow([null, paras]);
const am = (v) => [p([run(v, { font: MONO })], { align: R })];
let count = 0;
(m.rows || []).forEach(function (r) {
    count++;
    if (r.kind === 'gl') {
        rows.push(bodyRow([[p([run(r.rc, { size: SZ_SM })], { align: C })], [p([run(r.text)])], null, null, null,
            [p([run(r.code, { font: MONO, bold: true })], { align: C })], [p([run(r.p, { bold: true })], { align: C })], am(r.gl_d), am(r.gl_c)]));
    } else if (r.kind === 'desc') {
        rows.push(txt(lines(r.text, { indent: { left: 240 } })));
    } else {
        rows.push(bodyRow([null, [p([run(r.text, { size: SZ_SM, color: '333333' })], { indent: { left: 400 } })],
            [p([run(r.sl)], { align: C })], am(r.sl_d), am(r.sl_c), null, null, null, null]));
    }
});
for (; count < (m.min_rows || 0); count++) rows.push(txt([p([])]));
rows.push(txt([p([run('Reference:', { color: '444444' })])]));
rows.push(txt([p([run('DV#  ' + (m.reference_no || ''))], { indent: { left: 240 } })]));
rows.push(txt([p([run(m.payee || '')], { indent: { left: 240 } })]));
rows.push(txt([p([run('Particulars', { bold: true })])]));
rows.push(txt(lines(m.particulars || '', { after: 0 })));
rows.push(txt([p([run('RFA No.  ' + (m.rfa_number || ''))])]));
rows.push(txt([p([run('SARO  ' + (m.saro_number || ''))])]));
const t = m.totals || {};
const tot = (v) => [p([run(PESO + v, { font: MONO, bold: true })], { align: R })];
const TOP2 = Object.assign({}, GRID, { top: { style: BorderStyle.SINGLE, size: 14, color: '000000' } });
rows.push(row([
    cell([p([run('TOTAL', { bold: true })], { align: C })], cw[0] + cw[1] + cw[2], { span: 3, borders: TOP2, fill: 'F6F6F6' }),
    cell(tot(t.sl_d), cw[3], { borders: TOP2, fill: 'F6F6F6' }), cell(tot(t.sl_c), cw[4], { borders: TOP2, fill: 'F6F6F6' }),
    cell([p([])], cw[5], { borders: TOP2, fill: 'F6F6F6' }), cell([p([])], cw[6], { borders: TOP2, fill: 'F6F6F6' }),
    cell(tot(t.gl_d), cw[7], { borders: TOP2, fill: 'F6F6F6' }), cell(tot(t.gl_c), cw[8], { borders: TOP2, fill: 'F6F6F6' }),
], 260));
body.push(table(cw, rows, { top: THICK, bottom: THICK, left: THICK, right: THICK, insideHorizontal: NONE, insideVertical: NONE }));

// 3. Signatories
const sw = pct(33.33, 33.33, 33.34);
body.push(table(sw, [row((m.signatories || []).map((s, i) => cell([
    p([run(s.label, { size: SZ_8 })]),
    p([run(s.name || ' ', { bold: true, size: 17, underline: !!s.name })], { align: C, before: 300 }),
    p([run(s.title)], { align: C }),
], sw[i], { valign: VerticalAlign.TOP, margins: { top: 80, bottom: 100, left: 100, right: 100 } })))],
{ top: NONE, bottom: THICK, left: THICK, right: THICK, insideHorizontal: NONE, insideVertical: NONE }));

if (m.footer) body.push(p([run(m.footer, { bold: true, italic: true, size: SZ_8 })], { before: 60 }));
if (m.temp_number) body.push(p([run('Temporary Reference No.: ', { font: MONO, color: '555555' }), run(m.temp_number, { font: MONO, bold: true, color: '555555' })], { align: R, before: 60 }));

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                // docx swaps width / height for LANDSCAPE — pass the portrait size
                size: { width: PAGE_H, height: PAGE_W, orientation: PageOrientation.LANDSCAPE },
                margin: { top: TOPM, right: SIDE, bottom: TOPM, left: SIDE, header: 300, footer: 300 },
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
