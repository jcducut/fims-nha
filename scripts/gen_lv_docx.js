/**
 * LV (Liquidation Voucher) — DOCX generator.
 *
 *   node scripts/gen_lv_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by LvPrintService::model() — every word and amount is already resolved
 * there; this script only lays it out. The same model renders the HTML preview /
 * PDF (LvPrintService::html).
 *
 * Layout from the old REGIONAL gen_lv_docx.js (legal paper here, as its PDF):
 * title box + LV No.; Disbursing Officer; Department/Region; PARTICULARS / AMOUNT
 * (lines, chargeability, cash-advance breakdown — at least model.particular_rows
 * rows); amount in words; cash advance / refund lines; OBLIGATION (the FMD
 * ledger) + ACCOUNTING ENTRIES; Journalized / Indexed; certifications A–C; the
 * "Summary of Petty Cash" second page when model.pc_summary is set.
 *
 * No header or footer: the LV prints its own title block, so it has no
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
    process.stderr.write('usage: node gen_lv_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Page ──────────────────────────────────────────────────────────────────────
const MARGIN = 720;                        // 0.5in
const W = 12240 - MARGIN * 2;              // 10800 twips content width
const pct = (...shares) => {               // twip widths from percentages, summing to W
    const ws = shares.map(s => Math.round(W * s / 100));
    ws[ws.length - 1] += W - ws.reduce((a, b) => a + b, 0);
    return ws;
};


// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Times New Roman';
const SZ = 18, SZ_SM = 16, SZ_MD = 20, SZ_TITLE = 26;   // 9 / 8 / 10 / 13pt
const PESO = '₱ ';

function run(text, o) {
    o = o || {};
    return new TextRun({
        text: String(text == null ? '' : text), font: FONT, size: o.size || SZ,
        bold: !!o.bold, underline: o.underline ? { type: UnderlineType.SINGLE } : undefined,
    });
}
function p(children, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(children) ? children : [children],
        alignment: o.align || AlignmentType.LEFT,
        spacing: { before: o.before || 0, after: o.after || 0 },
        indent: o.indent,
        pageBreakBefore: !!o.pageBreak,
    });
}
const lines = (text, o) => String(text == null ? '' : text).split(/\r?\n/).map(l => p([run(l, o)], o));
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT;

const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const ALL = { top: LINE, bottom: LINE, left: LINE, right: LINE };
const OPEN_TOP = { top: NONE, bottom: LINE, left: LINE, right: LINE };   // boxes stacked under the one above

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        borders: Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o.borders || {}),
        columnSpan: o.span,
        rowSpan: o.rowSpan,
        verticalAlign: o.valign || VerticalAlign.CENTER,
        margins: o.margins || { top: 20, bottom: 20, left: 80, right: 80 },
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
const boxLine = (children, h) => table([W], [row([cell([p(children)], W, { borders: OPEN_TOP })], h || 360)]);

// ── Body ──────────────────────────────────────────────────────────────────────
const body = [];

// 1. Title box | LV No.
const tw = pct(72, 28);
body.push(table(tw, [row([
    cell([p([run('NATIONAL HOUSING AUTHORITY', { bold: true, size: SZ_TITLE })], { align: C, after: 40 }),
          p([run('LIQUIDATION VOUCHER', { bold: true, size: SZ_TITLE })], { align: C })], tw[0], { borders: ALL }),
    cell([p([run('LV No: ', { bold: true, size: SZ_MD }), run(m.lv_number, { size: SZ_MD })])], tw[1], { borders: ALL }),
], 840)]));

// 2–3. Disbursing Officer, Department/Region
body.push(boxLine([run('Disbursing Officer/Regional Bank Account: ', { bold: true }), run(m.disbursing_officer)]));
body.push(boxLine([run('Department/Region: ', { bold: true }), run(m.department_region)]));

// 4. PARTICULARS / AMOUNT — the breakdown's three amount columns sit inside PARTICULARS
const pw = pct(80, 20);
const bw = pct(41.6, 12.8, 12.8, 12.8, 20);          // description | a | b | c | AMOUNT
const side = (last) => ({ top: NONE, bottom: last ? LINE : NONE, left: LINE, right: LINE });
const sideIn = (last) => ({ top: NONE, bottom: last ? LINE : NONE, left: NONE, right: NONE });
const content = [];
(m.lines || []).forEach(l => content.push({ kind: 'line', l }));
if (m.chargeability) content.push({ kind: 'charge' });
(m.breakdown || []).forEach(r => content.push({ kind: 'bd', r }));
const total = Math.max(m.particular_rows || 0, content.length);
const partRows = [row([
    cell([p([run('PARTICULARS', { bold: true })], { align: C })], bw[0] + bw[1] + bw[2] + bw[3], { span: 4, borders: ALL }),
    cell([p([run('AMOUNT', { bold: true })], { align: C })], bw[4], { borders: ALL }),
], 340)];
for (let i = 0; i < total; i++) {
    const last = i === total - 1;
    const c = content[i];
    // the PARTICULARS side is one cell (left border) + the three amount sub-cells (right border on the last)
    const leftB = { top: NONE, bottom: last ? LINE : NONE, left: LINE, right: NONE };
    const midB = sideIn(last);
    const endB = { top: NONE, bottom: last ? LINE : NONE, left: NONE, right: LINE };
    let desc = [p([])], a = [p([])], b = [p([])], cc = [p([])], amount = [p([])];
    if (c && c.kind === 'line') {
        desc = lines(c.l.desc, { indent: c.l.sub ? { left: 280 } : undefined });
        amount = [p([run(c.l.amount, { bold: true })], { align: R })];
    } else if (c && c.kind === 'charge') {
        desc = [p([run('Chargeability: ' + m.chargeability, { bold: true })])];
    } else if (c) {
        desc = [p([run(c.r.desc)], { indent: c.r.indent ? { left: 280 } : undefined })];
        a = [p([run(c.r.a)], { align: R })];
        b = [p([run(c.r.b)], { align: R })];
        cc = [p([run(c.r.c)], { align: R })];
    }
    const lineRow = c && c.kind !== 'bd';
    partRows.push(row(lineRow
        ? [cell(desc, bw[0] + bw[1] + bw[2] + bw[3], { span: 4, borders: side(last) }), cell(amount, bw[4], { borders: side(last) })]
        : [cell(desc, bw[0], { borders: leftB }), cell(a, bw[1], { borders: midB }), cell(b, bw[2], { borders: midB }),
           cell(cc, bw[3], { borders: endB }), cell(amount, bw[4], { borders: side(last) })], 270));
}
body.push(table(bw, partRows));

// 5. Amount in words
body.push(boxLine([run('AMOUNT IN WORDS: ' + (m.amount_words || ''), { bold: true })]));

// 6. Cash advance / refund (label | arrow | ₱ box)
const cw = pct(65, 15, 20);
const arrowRow = (label, value, amount) => row([
    cell([p([run(label + ' ', { bold: true }), run(value, { underline: !!value })])], cw[0], { borders: OPEN_TOP }),
    cell([p([run('────▶')], { align: C })], cw[1], { borders: OPEN_TOP }),
    cell([p([run(PESO + (amount || ''))])], cw[2], { borders: OPEN_TOP }),
], 360);
body.push(table(cw, [
    arrowRow('AMOUNT OF CASH ADVANCE PER DV NO.', m.ca_dv_number, m.ca_amount),
    arrowRow(m.refund_label, m.refund_date, m.refund_amount),
]));

// 7. OBLIGATION (ledger lines first) | ACCOUNTING ENTRIES
const aw = pct(42, 15, 21.5, 21.5);
const ow = [Math.round(aw[0] * 0.34), Math.round(aw[0] * 0.36)];
ow.push(aw[0] - ow[0] - ow[1]);
const aeRows = [
    row([
        cell([p([run('OBLIGATION', { bold: true })], { align: C })], aw[0], { borders: ALL, rowSpan: 2 }),
        cell([p([run('ACCOUNTING ENTRIES', { bold: true })], { align: C })], aw[1] + aw[2] + aw[3], { borders: ALL, span: 3 }),
    ], 300),
    row([
        cell([p([run('ACCOUNT CODE', { bold: true, size: SZ_SM })], { align: C })], aw[1], { borders: ALL }),
        cell([p([run('DEBIT', { bold: true })], { align: C })], aw[2], { borders: ALL }),
        cell([p([run('CREDIT', { bold: true })], { align: C })], aw[3], { borders: ALL }),
    ], 300),
];
const entries = m.entries || [], ledger = m.obligation || [];
const aeCount = Math.max(m.entry_rows || 0, entries.length, ledger.length);
for (let i = 0; i < aeCount; i++) {
    const e = entries[i] || {};
    const o = ledger[i];
    // ledger line: label | ref | amount — with no amount, the text runs across ref + amount
    const flat = { top: 0, bottom: 0, left: 0, right: 0 };
    const ledgerCells = o && (o[2]
        ? [cell([p([run(o[0], { bold: true })])], ow[0] - 54, { margins: flat }),
           cell([p([run(o[1])])], ow[1] - 53, { margins: flat }),
           cell([p([run(PESO + o[2], { underline: o[3] })], { align: R })], ow[2] - 53, { margins: flat })]
        : [cell([p([run(o[0], { bold: true })])], ow[0] - 54, { margins: flat }),
           cell([p([run(o[1])])], ow[1] + ow[2] - 106, { margins: flat, span: 2 })]);
    const obl = o
        ? [new Table({
            width: { size: aw[0] - 160, type: WidthType.DXA }, columnWidths: [ow[0] - 54, ow[1] - 53, ow[2] - 53], layout: TableLayoutType.FIXED,
            borders: { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE },
            rows: [new TableRow({ children: ledgerCells })],
        }), p([run('', { size: 2 })])]   // Word needs a paragraph after a nested table — keep it hairline
        : [p([run(e.obligation)])];
    aeRows.push(row([
        cell(obl, aw[0], { borders: ALL }),
        cell([p([run(e.code)], { align: C })], aw[1], { borders: ALL }),
        cell([p([run(e.debit)], { align: R })], aw[2], { borders: ALL }),
        cell([p([run(e.credit)], { align: R })], aw[3], { borders: ALL }),
    ], 270));
}
body.push(table(aw, aeRows));

// 8. Journalized by/date | Indexed by/date
const jw = pct(50, 50);
body.push(table(jw, [row([
    cell([p([run('Journalized by/date: ', { bold: true }), run(m.journalized)])], jw[0], { borders: OPEN_TOP, valign: VerticalAlign.TOP }),
    cell([p([run('Indexed by/date: ', { bold: true }), run(m.indexed)])], jw[1], { borders: OPEN_TOP, valign: VerticalAlign.TOP }),
], 700)]));

// 9. Certifications A | B | C
const sw = pct(33.33, 33.33, 33.34);
body.push(table(sw, [row((m.certs || []).map((c, i) => {
    const first = [run(c.letter + '  ', { bold: true }), run('Certified: ' + c.text)];
    if (c.letter === 'B') first.push(run(' ' + PESO + (m.cert_b_amount || ''), { bold: true }));
    // fixed-height text (a 3-line frame) + a JEV line in every box (blank in A / B)
    // keep the names and the "Date:" lines level across the three boxes
    const textRows = [new TableRow({ height: { value: 720, rule: HeightRule.EXACT }, children: [
        cell([p(first)], sw[i] - 200, { margins: { top: 0, bottom: 0, left: 0, right: 0 }, valign: VerticalAlign.TOP })] })];
    const paras = [
        new Table({ width: { size: sw[i] - 200, type: WidthType.DXA }, columnWidths: [sw[i] - 200], layout: TableLayoutType.FIXED,
            borders: { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE }, rows: textRows }),
        p([], { after: 200 }),
        p([run(c.name || ' ', { bold: true })], { align: C }),
        p([run(c.title)], { align: C, after: 120 }),
        p([run(c.letter === 'C' ? 'JEV No.: ' + (m.jev_no || '') : ' ')], { after: 320 }),
        p([run('Date:')]),
    ];
    return cell(paras, sw[i], { borders: OPEN_TOP, valign: VerticalAlign.TOP, margins: { top: 80, bottom: 80, left: 100, right: 100 } });
}), 2300)]));

// 10. Summary of Petty Cash — second page
if ((m.pc_summary || []).length) {
    body.push(p([run('SUMMARY OF PETTY CASH', { bold: true, size: SZ_TITLE })], { align: C, after: 200, pageBreak: true }));
    const lw = pct(75, 25);
    body.push(table(lw, m.pc_summary.map(r => {
        const bal = r.type === 'balance';
        const label = r.type === 'received' || bal ? r.label : (r.type === 'add' ? 'Add: ' : 'Less: ') + r.label;
        const b = { top: bal ? LINE : NONE };
        return row([cell([p([run(label, { bold: bal })])], lw[0], { borders: b }),
            cell([p([run(PESO + r.amount, { bold: bal })], { align: R })], lw[1], { borders: b })], 300);
    })));
}

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 20160 },   // legal
                margin: { top: MARGIN, right: MARGIN, bottom: MARGIN, left: MARGIN, header: 400, footer: 400 },
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
