/**
 * FTLA (Fund Transfer Letter of Advice) — DOCX generator.
 *
 *   node scripts/gen_ftla_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by FtlaPrintService::model() — every word and amount is already resolved
 * there; this script only lays it out. The same model renders the HTML preview /
 * PDF (FtlaPrintService::html).
 *
 * The letter to the bank (old TREASURY "Print FTLA" / "Print DRFTLA", FMD's fund
 * transfer letter): A4 portrait, 15mm sides, Arial 9.5pt — date; TO / SUBJECT;
 * the body for the model's layout (single fill-in lines / debit_list table +
 * "and credit …" / pairs FROM-TO table); declaration; certified-copy request;
 * two signatories; the DV NO.s footer; FOR BANK USE ONLY.
 *
 * Header: the model's header_parts (letterhead layout — edited for this print
 * or the saved default), else the built-in banner via _shared/docx-branding.js
 * (UAC → Header Management, scope FMD / FTLA) →
 * _shared/images/letterhead/nha-letterhead-banner.png. No footer line.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, Header,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, UnderlineType, TableLayoutType,
} = docx;
const fs = require('fs');
const path = require('path');
const branding = require('../_shared/docx-branding');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_ftla_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Page (A4 portrait) ────────────────────────────────────────────────────────
const SIDE = 850, TOPM = 680;              // 15mm / 12mm
const W = 11906 - SIDE * 2;                // 10206 twips content width (510pt)
const pct = (...shares) => {               // twip widths from percentages, summing to W
    const ws = shares.map(s => Math.round(W * s / 100));
    ws[ws.length - 1] += W - ws.reduce((a, b) => a + b, 0);
    return ws;
};

// ── Header ────────────────────────────────────────────────────────────────────
const HEADER_W = 680;                      // px — the 510pt text width
const PART_SCALE = HEADER_W / 630;

function defaultHeader() {
    try {
        const buf = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-letterhead-banner.png'));
        const isPng = buf.length > 24 && buf.toString('ascii', 1, 4) === 'PNG';
        const w = isPng ? buf.readUInt32BE(16) : 0;
        const h = isPng ? buf.readUInt32BE(20) : 0;
        const k = w && h ? Math.min(HEADER_W / w, 120 / h) : 0;
        return { base64: buf.toString('base64'), width: k ? Math.round(w * k) : HEADER_W, height: k ? Math.round(h * k) : 102 };
    } catch (e) {
        return { base64: null, width: HEADER_W, height: 102 };
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
        ? branding.buildHeaderChildren(docx, { parts, totalWidth: HEADER_W, origin: { left: SIDE, top: 400 } }).children
        : [new Paragraph({ children: [] })];
} else {
    const fb = defaultHeader();
    headerChildren = branding.buildHeaderChildren(docx, {
        system: 'FMD', document: 'FTLA', data: m,
        fallbackBase64: fb.base64, totalWidth: fb.width, totalHeight: fb.height,
    }).children;
}

// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ = 19, SZ_SM = 18, SZ_LG = 21;     // 9.5 / 9 / 10.5pt

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
        border: o.border,
        indent: o.indent,
    });
}
const C = AlignmentType.CENTER, R = AlignmentType.RIGHT, J = AlignmentType.JUSTIFIED;

const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const LINE = { style: BorderStyle.SINGLE, size: 6, color: '000000' };
const ALL = { top: LINE, bottom: LINE, left: LINE, right: LINE };

function cell(children, width, o) {
    o = o || {};
    return new TableCell({
        children: children.length ? children : [p([])],
        width: { size: width, type: WidthType.DXA },
        borders: Object.assign({ top: NONE, bottom: NONE, left: NONE, right: NONE }, o.borders || {}),
        columnSpan: o.span,
        rowSpan: o.rowSpan,
        verticalAlign: o.valign || VerticalAlign.BOTTOM,
        margins: o.margins || { top: 0, bottom: 60, left: 0, right: 0 },
    });
}
function table(widths, rows) {
    return new Table({
        width: { size: widths.reduce((a, b) => a + b, 0), type: WidthType.DXA },
        columnWidths: widths,
        layout: TableLayoutType.FIXED,
        borders: { top: NONE, bottom: NONE, left: NONE, right: NONE, insideHorizontal: NONE, insideVertical: NONE },
        rows,
    });
}
const row = (cells) => new TableRow({ children: cells, cantSplit: true });
const GRID = { margins: { top: 50, bottom: 50, left: 100, right: 100 }, valign: VerticalAlign.CENTER, borders: ALL };
const gcell = (children, width, o) => cell(children, width, Object.assign({}, GRID, o || {}));
const th = (text) => [p([run(text, { bold: true, size: SZ_SM })], { align: C })];
const g = (text, align, bold) => [p([run(text, { size: SZ_SM, bold: !!bold })], { align })];
const amount = (v, width, bold) => gcell([table([300, width - 500], [row([
    cell([p([run('P', { size: SZ_SM })])], 300, { margins: { top: 0, bottom: 0, left: 0, right: 0 } }),
    cell([p([run(v, { size: SZ_SM, bold: !!bold })], { align: R })], width - 500, { margins: { top: 0, bottom: 0, left: 0, right: 0 } }),
])]), p([run('', { size: 2 })])], width);   // Word needs a paragraph after a nested table — keep it hairline

// ── Body ──────────────────────────────────────────────────────────────────────
const body = [];
body.push(p([run(m.date)], { after: 160 }));

// TO / SUBJECT
const hw = pct(13, 3, 84);
const toLines = [p([run(m.to_bank, { bold: true })])];
if (m.to_branch) toLines.push(p([run(m.to_branch)]));
const top = { valign: VerticalAlign.TOP };
body.push(table(hw, [
    row([cell([p([run('TO')])], hw[0], top), cell([p([run(':')])], hw[1], top), cell(toLines, hw[2], top)]),
    row([cell([p([run('SUBJECT')])], hw[0], top), cell([p([run(':')])], hw[1], top), cell([p([run(m.subject, { bold: true })])], hw[2], top)]),
]));
body.push(p([], { after: 200 }));

if (m.layout === 'single') {
    const s = m.single;
    const fw = pct(24, 46, 30);
    const UL = { bottom: LINE };
    body.push(table(fw, [
        row([cell([p([run('Please debit our Account')])], fw[0]), cell([p([run(s.from_name)], { align: C })], fw[1] + fw[2], { span: 2, borders: UL })]),
        row([cell([p([run('Account No.')])], fw[0]), cell([p([run(s.from_no)], { align: C })], fw[1], { borders: UL }),
             cell([p([run('the amount of PESOS:')], { indent: { left: 160 } })], fw[2])]),
        row([cell([p([])], fw[0]), cell([p([run(s.words)], { align: C })], fw[1] + fw[2], { span: 2, borders: UL })]),
        row([cell([p([])], fw[0]), cell([p([run('Php '), run(s.amount_text, { underline: true })])], fw[1] + fw[2], { span: 2 })]),
        row([cell([p([run('and credit')])], fw[0]), cell([p([run(s.to_name)], { align: C })], fw[1] + fw[2], { span: 2, borders: UL })]),
        row([cell([p([run('Account No.:')])], fw[0]), cell([p([run(s.to_no)], { align: C })], fw[1], { borders: UL }),
             cell([p([run('representing')], { indent: { left: 160 } })], fw[2])]),
        row([cell([p([run(m.purpose)])], W, { span: 3, borders: UL })]),
    ]));
    body.push(p([], { after: 160 }));
} else if (m.layout === 'debit_list') {
    const dw = pct(26, 48, 26);
    body.push(p([run('Please '), run('debit', { bold: true, underline: true }), run(' the following depository/disbursing accounts:')], { after: 120 }));
    const rows = [
        row([gcell(th('BANK ACCOUNT'), W, { span: 3 })]),
        row([gcell(th('NUMBER'), dw[0]), gcell(th('NAME'), dw[1]), gcell(th('AMOUNT'), dw[2])]),
    ];
    (m.debits || []).forEach(d => rows.push(row([gcell(g(d.no, C), dw[0]), gcell(g(d.name), dw[1]), amount(d.amount, dw[2])])));
    rows.push(row([gcell(g('TOTAL', AlignmentType.LEFT, true), dw[0] + dw[1], { span: 2 }), amount(m.total, dw[2], true)]));
    body.push(table(dw, rows));
    body.push(p([run('and '), run('credit', { bold: true, underline: true }), run(' the account of '),
        run('(' + m.credit.no + ') (' + m.credit.name + ')', { underline: true }), run(' in the total amount of '),
        run(m.total_words + ' (Php ' + m.total + ')', { underline: true }), run('.')], { align: J, before: 200, after: 160 }));
} else {
    const pw = pct(14, 24, 14, 24, 24);
    body.push(p([run('Please effect the fund transfer as follows:')], { after: 120 }));
    const rows = [
        row([gcell(th('FROM'), pw[0] + pw[1], { span: 2 }), gcell(th('TO'), pw[2] + pw[3], { span: 2 }), gcell(th('AMOUNT'), pw[4], { rowSpan: 2 })]),
        row([gcell(th('Account No.'), pw[0]), gcell(th('Account Name'), pw[1]), gcell(th('Account No.'), pw[2]), gcell(th('Account Name'), pw[3])]),
    ];
    (m.groups || []).forEach(grp => grp.rows.forEach((r, i) => {
        const span = grp.rows.length > 1 ? grp.rows.length : undefined;
        const cells = i === 0 ? [gcell(g(grp.from_no, C), pw[0], { rowSpan: span }), gcell(g(grp.from_name), pw[1], { rowSpan: span })] : [];
        rows.push(row(cells.concat([gcell(g(r.to_no, C), pw[2]), gcell(g(r.to_name), pw[3]), amount(r.amount, pw[4])])));
    }));
    rows.push(row([gcell(g('TOTAL', R, true), pw[0] + pw[1] + pw[2] + pw[3], { span: 4 }), amount(m.total, pw[4], true)]));
    body.push(table(pw, rows));
    body.push(p([], { after: 160 }));
}

body.push(p([run('We declare under penalty of perjury that our co-depositors are still living. This also authorizes you to debit our account for the corresponding service charge.')], { align: J, after: 120 }));
body.push(p([run('Kindly provide us with a certified true copy of duly validated Fund Transfer Letter Advice for our record purposes.', { size: SZ_LG })], { align: J, after: 560 }));

// Signatories
const sw = pct(48, 4, 48);
const sigCell = (s, width, align) => cell([p([run(s.name || ' ', { bold: true })], { align })]
    .concat((s.lines || []).map(l => p([run(l)], { align }))), width, { valign: VerticalAlign.TOP });
const sigs = m.signatories || [{}, {}];
body.push(table(sw, [row([sigCell(sigs[0], sw[0], AlignmentType.LEFT), cell([p([])], sw[1]), sigCell(sigs[1], sw[2], R)])]));

// DV NO.s footer (DRFTLA)
if ((m.dv_numbers || []).length || m.fund_line) {
    if ((m.dv_numbers || []).length) {
        body.push(p([run('* DV NO.s:', { size: SZ_SM })], { before: 200 }));
        m.dv_numbers.forEach(d => body.push(p([run(d, { size: SZ_SM })])));
    }
    if (m.fund_line) body.push(p([run(m.fund_line, { size: SZ_SM, bold: true })], { before: 120 }));
}

// FOR BANK USE ONLY
body.push(p([run('FOR BANK USE ONLY', { bold: true, size: SZ_LG })],
    { before: 320, after: 40, border: { top: { style: BorderStyle.SINGLE, size: 10, color: '000000', space: 8 } } }));
body.push(p([run('We have debited/credited above accounts as instructed:', { size: SZ_LG })], { after: 280 }));
const bw = pct(25, 25, 25, 25);
const bank = m.bank || [];
body.push(table(bw, [
    row(bank.map((b, i) => cell([p([run(b[0], { size: SZ_SM })])], bw[i], { margins: { top: 0, bottom: 80, left: 0, right: 240 } }))),
    row(bank.map((b, i) => cell([p([run(b[1] || ' ', { underline: !!b[1] })])], bw[i],
        { borders: { top: LINE }, margins: { top: 80, bottom: 0, left: 0, right: 240 }, valign: VerticalAlign.TOP }))),
]));

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 },   // A4
                margin: { top: Array.isArray(m.header_parts) && !m.header_parts.length ? TOPM : 2100, right: SIDE, bottom: TOPM, left: SIDE, header: 400, footer: 400 },
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
