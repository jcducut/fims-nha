/**
 * LAA (Letter of Advice of Allotment, CO and COE) — DOCX generator.
 *
 *   node scripts/gen_laa_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by LaaPrintService::model() — wording, amount in words, addressee and
 * signatories are already resolved there; this script only lays them out. The
 * same model renders the HTML preview / PDF (LaaPrintService::html).
 *
 * Layout ported from the old FMD gen_laa_docx.js ("LAA - Corporate Receipts
 * Source of Fund" template): A4, 0.8" margins, Arial — date / title 12pt,
 * body 11.5pt, details sheet 10pt (native table rebuilt from the template's
 * embedded sheet A1:H17), footer 9pt.
 *
 * Header: the model's header_parts (letterhead layout — edited for this print
 * or the saved default), else the built-in banner via _shared/docx-branding.js
 * (UAC → Header Management, scope FMD / LAA) → _shared/images/letterhead/nha-letterhead-banner.png.
 * Footer: the model's footer_text.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, Header, Footer,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, UnderlineType, HeightRule, TableLayoutType,
} = docx;
const fs = require('fs');
const path = require('path');
const branding = require('../_shared/docx-branding');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_laa_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Header ────────────────────────────────────────────────────────────────────
const HEADER_W = 640;                // px — the 480pt (6.67") text width
const PART_SCALE = HEADER_W / 630;   // layout sizes are given for a 630 px letterhead

function defaultHeader() {
    try {
        const buf = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-letterhead-banner.png'));
        const isPng = buf.length > 24 && buf.toString('ascii', 1, 4) === 'PNG';
        const w = isPng ? buf.readUInt32BE(16) : 0;
        const h = isPng ? buf.readUInt32BE(20) : 0;
        const k = w && h ? Math.min(HEADER_W / w, 120 / h) : 0;
        return { base64: buf.toString('base64'), width: k ? Math.round(w * k) : HEADER_W, height: k ? Math.round(h * k) : 96 };
    } catch (e) {
        return { base64: null, width: HEADER_W, height: 96 };
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
        ? branding.buildHeaderChildren(docx, { parts, totalWidth: HEADER_W, origin: { left: 1152, top: 432 } }).children
        : [new Paragraph({ children: [] })];
} else {
    const fb = defaultHeader();
    headerChildren = branding.buildHeaderChildren(docx, {
        system: 'FMD', document: 'LAA', data: m,
        fallbackBase64: fb.base64, totalWidth: fb.width, totalHeight: fb.height,
    }).children;
}

// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ_HEAD = 24; // 12pt — date, title and the blank lines around them
const SZ = 23;      // 11.5pt — addressee, intro, closing, signatory
const SZ_TBL = 20;  // 10pt — details sheet
const SZ_FOOT = 18; // 9pt — footer

function run(text, o) {
    o = o || {};
    return new TextRun({
        text: String(text == null ? '' : text),
        font: FONT,
        size: o.size || SZ,
        bold: !!o.bold,
        italics: !!o.italic,
        underline: o.underline ? { type: UnderlineType.SINGLE } : undefined,
    });
}

// Single-spaced, no spacing before/after — the rhythm comes from blank lines.
function p(children, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(children) ? children : [children],
        alignment: o.align !== undefined ? o.align : AlignmentType.LEFT,
        spacing: { before: 0, after: 0 },
        indent: o.indent,
        border: o.border,
        keepNext: o.keepNext,
        run: { font: FONT, size: o.markSize || SZ },
    });
}
const blank = (size, keepNext) => p([], { markSize: size || SZ, keepNext });
// A blank 12pt line with a bottom border, stopping short of the right margin.
const rule = () => p([], { markSize: SZ_HEAD, indent: { right: 260 }, border: { bottom: { style: BorderStyle.SINGLE, size: 8, color: '000000', space: 1 } } });

const NONE = { style: BorderStyle.NONE, size: 0, color: 'auto' };
const noBorder = { top: NONE, bottom: NONE, left: NONE, right: NONE };
const doubleBottom = { top: NONE, left: NONE, right: NONE, bottom: { style: BorderStyle.DOUBLE, size: 6, color: '000000' } };

// ── Body ──────────────────────────────────────────────────────────────────────
const children = [];
const J = AlignmentType.JUSTIFIED;
const R = AlignmentType.RIGHT;
const C = AlignmentType.CENTER;

children.push(blank(SZ_HEAD));
children.push(p([run(m.date, { size: SZ_HEAD })], { indent: { left: 7200 }, markSize: SZ_HEAD })); // set in at 5"
children.push(blank(SZ_HEAD));
children.push(blank(SZ_HEAD));
children.push(new Paragraph({
    children: [
        run('LETTER OF ADVICE OF ALLOTMENT No.', { size: SZ_HEAD }),
        run(' ', { size: SZ_HEAD, bold: true }),
        run(m.title_detail, { size: SZ_HEAD, bold: true, underline: true }),
    ],
    alignment: C,
    spacing: { before: 0, after: 0 },
    run: { font: FONT, size: SZ_HEAD },
}));
children.push(blank(SZ_HEAD));
children.push(blank(SZ_HEAD));

(m.to || []).forEach(function (l) { children.push(p([run(l.text, { bold: l.bold })])); });
children.push(blank());
children.push(p([run(m.greeting)]));
children.push(blank());
children.push(p([run(m.intro)], { align: J }));
children.push(rule());

// Details sheet — template columns A..H (twips, sum 9602 = the text width).
const COL_W = [410, 3743, 1248, 1516, 300, 300, 1706, 379];
const span = (a, b) => COL_W.slice(a, b + 1).reduce((s, w) => s + w, 0);
const cellP = (text, o) => {
    o = o || {};
    return new Paragraph({
        children: text ? [run(text, { size: SZ_TBL, bold: o.bold, italic: o.italic })] : [],
        alignment: o.align !== undefined ? o.align : AlignmentType.LEFT,
        spacing: { before: 0, after: 0 },
        run: { font: FONT, size: o.markSize || SZ_TBL },
    });
};
const tc = (from, to, paras, o) => {
    o = o || {};
    return new TableCell({
        width: { size: span(from, to), type: WidthType.DXA },
        columnSpan: to > from ? to - from + 1 : undefined,
        rowSpan: o.rowSpan,
        verticalAlign: o.valign || VerticalAlign.TOP,
        borders: o.borders || noBorder,
        children: paras || [cellP('')],
    });
};
const tr = (h, cells, ruleType) => new TableRow({ height: { value: h, rule: ruleType || HeightRule.ATLEAST }, cantSplit: true, children: cells });
const tiny = [cellP('', { markSize: 10 })];
const cert = m.cert || {};
// CO: "To transfer obligation partially made to" label row, the text below it.
// COE: no label — the transfer text is one sentence starting on the label row.
const hasLabel = !!m.transfer_label;
const fundNotes = m.fund_notes !== false;   // CO only: "Source of Fund: … / <program>" bottom-left

children.push(new Table({
    width: { size: 9602, type: WidthType.DXA },
    columnWidths: COL_W,
    layout: TableLayoutType.FIXED,
    margins: { top: 0, bottom: 0, left: 40, right: 40 },
    borders: Object.assign({}, noBorder, { insideHorizontal: NONE, insideVertical: NONE }),
    rows: [
        tr(180, [tc(0, 7, tiny)], HeightRule.EXACT),
        tr(270, [
            tc(0, 0),
            hasLabel ? tc(1, 1, [cellP(m.transfer_label)]) : tc(1, 1, [cellP(m.transfer)], { rowSpan: 7 }),
            tc(2, 4),
            tc(5, 5, [cellP('P', { bold: true, align: R })], { valign: VerticalAlign.BOTTOM, borders: doubleBottom }),
            tc(6, 6, [cellP(m.amount, { bold: true, align: R })], { valign: VerticalAlign.BOTTOM, borders: doubleBottom }),
            tc(7, 7),
        ]),
        tr(270, hasLabel
            ? [tc(0, 0), tc(1, 1, [cellP(m.transfer)], { rowSpan: 6 }), tc(2, 6), tc(7, 7)]
            : [tc(0, 0), tc(2, 6), tc(7, 7)]),
        tr(255, [tc(0, 0), tc(2, 6, [cellP(m.amount_words, { bold: true, align: C })]), tc(7, 7)]),
        tr(255, [tc(0, 0), tc(2, 6), tc(7, 7)]),
        tr(255, [tc(0, 0), tc(2, 6), tc(7, 7)]),
        tr(255, [tc(0, 0), tc(2, 7)]),
        tr(255, [tc(0, 0), tc(2, 2), tc(3, 4, [cellP('Source of Fund :')]), tc(5, 7, [cellP(m.fund)])]),
        tr(255, [tc(0, 7)]),
        tr(255, [tc(0, 7)]),
        tr(255, [tc(0, 2), tc(3, 7, [cellP('Certified as to Availability of Allotment:')])]),
        tr(255, [tc(0, 7)]),
        tr(255, [tc(0, 7)]),
        tr(255, [tc(0, 2), tc(3, 6, [cellP(cert.name, { bold: true, align: C })]), tc(7, 7)]),
        tr(255, [tc(0, 0), tc(1, 2, [cellP(fundNotes ? 'Source of Fund: ' + m.fund : '', { bold: true, italic: true })]), tc(3, 6, [cellP(cert.title, { align: C })]), tc(7, 7)]),
        tr(255, [tc(0, 0), tc(1, 2, [cellP(fundNotes ? m.program_of_cob : '', { bold: true, italic: true })]), tc(3, 6, [cellP(cert.dept, { align: C })]), tc(7, 7)]),
        tr(135, [tc(0, 7, tiny)], HeightRule.EXACT),
    ],
}));

children.push(rule());
children.push(blank(SZ_HEAD));
// Closing through the signatory stays together on one page.
children.push(p([run(m.closing)], { align: J, keepNext: true }));
children.push(blank(SZ, true));
children.push(p([run('Very truly yours,')], { keepNext: true }));
children.push(blank(SZ, true));
children.push(blank(SZ, true));
children.push(blank(SZ, true));
const close = m.close || {};
children.push(p([run(close.name, { bold: true })], { keepNext: true }));
children.push(p([run(close.title)], { keepNext: true }));
children.push(p([run(close.dept)]));

// ── Document ──────────────────────────────────────────────────────────────────
const footerChildren = m.footer_text
    ? [p([run(m.footer_text, { size: SZ_FOOT })], { align: C, markSize: SZ_FOOT })]
    : [p([], { markSize: SZ_FOOT })];

const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 }, // A4
                margin: { top: 1152, right: 1152, bottom: 576, left: 1152, header: 432, footer: 288 },
            },
        },
        headers: { default: new Header({ children: headerChildren }) },
        footers: { default: new Footer({ children: footerChildren }) },
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
