/**
 * CBA (Certificate of Budget Allocation) and CAF — DOCX generator.
 *
 *   node scripts/gen_cba_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by CbaPrintService::model() (CBA) or CafPrintService::model() (CAF) — all the
 * wording (amount in words, validity clause, reminders, signatory) is already
 * resolved there, so this script only lays it out. The same model renders the
 * HTML preview / PDF (CbaPrintService::html), keeping them in step.
 *
 * Layout ported from the old FMD generators (gen_cba_docx.js for CO,
 * gen_coe_cba_docx.js for COE): A4, 1in margins, Times New Roman 11pt.
 *
 * Header: the model's header_parts — the letterhead layout's items (images and
 * titles, freely placed; edited for this print or the saved default — see
 * LetterheadLayoutService), an empty list = no header — else (null) the
 * built-in banner: _shared/docx-branding.js (UAC → Header Management, scope
 * FMD / CBA) → _shared/images/letterhead/nha-letterhead-banner.png.
 * Footer: the model's footer_text, as a centered text line.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, Header, Footer, TabStopType,
} = docx;
const fs = require('fs');
const path = require('path');
const branding = require('../_shared/docx-branding');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_cba_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Header ────────────────────────────────────────────────────────────────────
const HEADER_W = 630;   // px — the letterhead width the layout sizes are given for

// Built-in banner (fallback after UAC Header Management), keeping its
// proportions inside 630 × 120 px.
function defaultHeader() {
    try {
        const buf = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-letterhead-banner.png'));
        // PNG: width/height are big-endian uint32 at bytes 16-23 (IHDR).
        const isPng = buf.length > 24 && buf.toString('ascii', 1, 4) === 'PNG';
        const w = isPng ? buf.readUInt32BE(16) : 0;
        const h = isPng ? buf.readUInt32BE(20) : 0;
        const k = w && h ? Math.min(HEADER_W / w, 120 / h) : 0;
        return { base64: buf.toString('base64'), width: k ? Math.round(w * k) : HEADER_W, height: k ? Math.round(h * k) : 95 };
    } catch (e) {
        return { base64: null, width: HEADER_W, height: 95 };
    }
}

let headerChildren;
if (Array.isArray(m.header_parts)) {
    // The layout's items, composed by docx-branding (images already validated).
    const parts = m.header_parts.map(function (p) {
        return p.base64 ? Object.assign({}, p, { data: Buffer.from(p.base64, 'base64') }) : p;
    });
    headerChildren = parts.length
        // origin: left margin / header distance (twips) — see the page margins below
        ? branding.buildHeaderChildren(docx, { parts, totalWidth: HEADER_W, origin: { left: 1440, top: 708 } }).children
        : [new Paragraph({ children: [] })];   // every item removed — no header
} else {
    const fb = defaultHeader();
    headerChildren = branding.buildHeaderChildren(docx, {
        system: 'FMD', document: m.brand_document || 'CBA', data: m,
        fallbackBase64: fb.base64, totalWidth: fb.width, totalHeight: fb.height,
    }).children;
}

// ── Text helpers ──────────────────────────────────────────────────────────────
// The model's style (CbaPrintService::STYLE — the CBA's own look; the CAF and the
// regional CBA change some keys): sizes in pt, italics, indent, footnote bolding.
const ST = Object.assign({
    size: 11, title_size: 11, title_bold: true, title_italic: false,
    date_italic: true, indent_pt: 0, line_height: null, name_italic: false,
    foot_size: 10, foot_italic: false, foot_bold: 'label', allocnum_bold: true,
    date_align: 'right', sig_layout: 'right',
}, m.style || {});
const FONT = 'Times New Roman';
const SZ = ST.size * 2;          // docx sizes are half-points
const SZ_SM = ST.foot_size * 2;  // footnote + reminders

function run(text, o) {
    o = o || {};
    return new TextRun({
        text: String(text == null ? '' : text), font: FONT, size: o.size || SZ,
        bold: !!o.bold, italics: !!o.italics, superScript: !!o.sup,
    });
}

function para(children, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(children) ? children : [children],
        alignment: o.align || AlignmentType.LEFT,
        spacing: { before: o.before || 0, after: o.after !== undefined ? o.after : 80 },
        indent: o.indent,
    });
}

const noBorder = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
const noBorders = { top: noBorder, bottom: noBorder, left: noBorder, right: noBorder };
// First-line indent of the certificate paragraphs (COE 22pt, CAF a 0.5in tab, …).
const certIndent = ST.indent_pt ? { firstLine: ST.indent_pt * 20 } : undefined;

// ── Body ──────────────────────────────────────────────────────────────────────
const children = [];

// 1. Date — right-aligned (regional CAF: left)
children.push(para([run(m.date, { italics: ST.date_italic })], {
    align: ST.date_align === 'left' ? AlignmentType.LEFT : AlignmentType.RIGHT, after: 320,
}));

// 2. Title — centered
children.push(para([run(m.title, {
    bold: ST.title_bold, italics: ST.title_italic, size: ST.title_size !== ST.size ? ST.title_size * 2 : undefined,
})], { align: AlignmentType.CENTER, after: 300 }));

// 3. Certify paragraph — italic, justified, amount phrases bold (ordinal suffixes
//    superscript). Without a validity paragraph (CAF) it takes the gap before
//    the signatory.
const hasValidity = !!m.validity;
children.push(new Paragraph({
    children: (m.body || []).map(function (r) { return run(r.text, { italics: true, bold: r.bold, sup: r.sup }); }),
    alignment: AlignmentType.JUSTIFY,
    spacing: Object.assign({ before: 0, after: hasValidity ? 260 : 900 },
        ST.line_height ? { line: Math.round(240 * ST.line_height), lineRule: 'auto' } : {}),
    indent: certIndent,
}));

// 4. Validity paragraph (CBA only)
if (hasValidity) {
    children.push(new Paragraph({
        children: [run(m.validity, { italics: true })],
        alignment: AlignmentType.JUSTIFY,
        spacing: Object.assign({ before: 0, after: 900 },
            ST.line_height ? { line: Math.round(240 * ST.line_height), lineRule: 'auto' } : {}),
        indent: certIndent,
    }));
}

// 5. Signatory — centered in the right half (borderless 2-column table), or the
//    regional CAF's "Certified:" block: name (bold) and title (italic) on one line
//    at the template's tab stops, the office below.
const sig = m.signatory || { name: '', title_lines: [] };
if (ST.sig_layout === 'certified') {
    children.push(para([run('Certified:', { italics: true })], { after: 0 }));
    for (let i = 0; i < 4; i++) children.push(para([run('')], { after: 0 }));   // room for the signature
    children.push(new Paragraph({
        children: [run('\t\t'), run(sig.name, { bold: true }), run('\t'), run((sig.title_lines || [])[0] || '', { italics: true })],
        tabStops: [{ type: TabStopType.LEFT, position: 4500 }],
        spacing: { before: 0, after: 0 },
    }));
    if ((sig.title_lines || [])[1]) children.push(para([run('\t\t' + sig.title_lines[1], { italics: true })], { after: 0 }));
} else children.push(new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    borders: Object.assign({}, noBorders, { insideHorizontal: noBorder, insideVertical: noBorder }),
    rows: [new TableRow({
        children: [
            new TableCell({ width: { size: 50, type: WidthType.PERCENTAGE }, borders: noBorders, children: [para([run('')], { after: 0 })] }),
            new TableCell({
                width: { size: 50, type: WidthType.PERCENTAGE },
                borders: noBorders,
                verticalAlign: VerticalAlign.TOP,
                children: [para([run(sig.name, { bold: true, italics: ST.name_italic })], { align: AlignmentType.CENTER, after: 0 })].concat(
                    (sig.title_lines || []).map(function (l) {
                        return para([run(l, { italics: true })], { align: AlignmentType.CENTER, after: 0 });
                    })
                ),
            }),
        ],
    })],
}));

// 6. Footnote — allocation number + Sources of Fund / Program
children.push(para([run('')], { after: 800 }));
if (m.allocation_number) {
    children.push(para([run(m.allocation_number, { bold: ST.allocnum_bold, italics: true, size: SZ_SM })], { after: 0 }));
}
(m.footnote || []).forEach(function (l) {
    // foot_bold: which part is bold — 'label' (CBA), 'value' (CAF) or 'none' (regional CBA)
    children.push(para([
        run(l.label, { bold: ST.foot_bold === 'label', italics: ST.foot_italic, size: SZ_SM }),
        run(l.value, { bold: ST.foot_bold === 'value', italics: ST.foot_italic, size: SZ_SM }),
    ], { after: 0 }));
});

// 7. Reminder(s) (CBA only)
const reminders = m.reminders || { heading: '', items: [] };
if (reminders.items.length) {
    children.push(para([run('')], { after: 240 }));
    children.push(para([run(reminders.heading, { bold: true, size: SZ_SM })], { after: 80 }));
}
reminders.items.forEach(function (text, i) {
    children.push(new Paragraph({
        children: [run((i + 1) + '.  ' + text, { italics: true, size: SZ_SM })],
        alignment: AlignmentType.JUSTIFY,
        spacing: { before: 0, after: i < reminders.items.length - 1 ? 120 : 0 },
        indent: { left: 360, hanging: 360 },
    }));
});

// ── Document ──────────────────────────────────────────────────────────────────
// Footer: the footer_text line — Arial 8.5pt, slightly spaced, dark grey.
const footerChildren = [new Paragraph({
    children: [new TextRun({ text: String(m.footer_text || ''), font: 'Arial', size: 17, color: '333333', characterSpacing: 12 })],
    alignment: AlignmentType.CENTER,
    spacing: { after: 0 },
})];

const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 11906, height: 16838 }, // A4
                margin: { top: 1440, right: 1440, bottom: 1440, left: 1440, header: 708, footer: 708 },
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
