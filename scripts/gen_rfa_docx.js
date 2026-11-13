/**
 * RFA (Request for Fund Allotment) — DOCX generator.
 *
 *   node scripts/gen_rfa_docx.js <model.json> <out.docx>
 *
 * Called by app/services/PrintService.php (renderDocx). The JSON is the model
 * built by RfaPrintService::model() — the form variant (STAFF / REGIONAL
 * OFFICE), signatories and every amount are already resolved there; this script
 * only lays them out. The same model renders the HTML preview / PDF
 * (RfaPrintService::html).
 *
 * Layout ported from the old FMD gen_rfa_docx.js: legal paper (8.5" × 14"),
 * 0.5" sides, Arial 11pt.
 *
 * Header: the model's header_parts (letterhead layout — edited for this print
 * or the saved default), else the built-in banner via _shared/docx-branding.js
 * (UAC → Header Management, scope FMD / RFA) → _shared/images/letterhead/nha-letterhead-banner.png.
 */
const docx = require('docx');
const {
    Document, Packer, Paragraph, TextRun, Table, TableRow, TableCell, Header,
    AlignmentType, BorderStyle, WidthType, VerticalAlign, UnderlineType, TabStopType,
} = docx;
const fs = require('fs');
const path = require('path');
const branding = require('../_shared/docx-branding');

const [, , dataPath, outPath] = process.argv;
if (!dataPath || !outPath) {
    process.stderr.write('usage: node gen_rfa_docx.js <model.json> <out.docx>\n');
    process.exit(2);
}
const m = JSON.parse(fs.readFileSync(dataPath, 'utf8'));

// ── Header ────────────────────────────────────────────────────────────────────
const HEADER_W = 720;            // px — full 7.5" text width
const PART_SCALE = HEADER_W / 630; // layout sizes are given for a 630 px letterhead

function defaultHeader() {
    try {
        const buf = fs.readFileSync(path.join(__dirname, '..', '_shared', 'images', 'letterhead', 'nha-letterhead-banner.png'));
        const isPng = buf.length > 24 && buf.toString('ascii', 1, 4) === 'PNG';
        const w = isPng ? buf.readUInt32BE(16) : 0;
        const h = isPng ? buf.readUInt32BE(20) : 0;
        const k = w && h ? Math.min(HEADER_W / w, 140 / h) : 0;
        return { base64: buf.toString('base64'), width: k ? Math.round(w * k) : HEADER_W, height: k ? Math.round(h * k) : 109 };
    } catch (e) {
        return { base64: null, width: HEADER_W, height: 109 };
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
        ? branding.buildHeaderChildren(docx, { parts, totalWidth: HEADER_W, origin: { left: 720, top: 288 } }).children
        : [new Paragraph({ children: [] })];
} else {
    const fb = defaultHeader();
    headerChildren = branding.buildHeaderChildren(docx, {
        system: 'FMD', document: 'RFA', data: m,
        fallbackBase64: fb.base64, totalWidth: fb.width, totalHeight: fb.height,
    }).children;
}

// ── Text helpers ──────────────────────────────────────────────────────────────
const FONT = 'Arial';
const SZ = 22; // 11pt

function run(text, o) {
    o = o || {};
    return new TextRun({
        text: String(text == null ? '' : text),
        font: o.font || FONT,
        size: o.size || SZ,
        bold: !!o.bold,
        underline: o.underline ? { type: UnderlineType.SINGLE } : undefined,
    });
}

function p(runs, o) {
    o = o || {};
    return new Paragraph({
        children: Array.isArray(runs) ? runs : [runs],
        alignment: o.align !== undefined ? o.align : AlignmentType.LEFT,
        spacing: { before: o.before || 0, after: o.after !== undefined ? o.after : 80, line: o.line },
        tabStops: o.tabStops,
        indent: o.indent,
        border: o.border,
    });
}

const NONE = { style: BorderStyle.NONE, size: 0, color: 'auto' };
const noBorders = { top: NONE, bottom: NONE, left: NONE, right: NONE };
const tableBorders = Object.assign({}, noBorders, { insideHorizontal: NONE, insideVertical: NONE });
const rule = (style, size, space) => ({ bottom: { style, size, color: '000000', space } });

function cell(widthDxa, children, o) {
    o = o || {};
    return new TableCell({
        width: o.pct ? { size: widthDxa, type: WidthType.PERCENTAGE } : { size: widthDxa, type: WidthType.DXA },
        borders: noBorders,
        verticalAlign: VerticalAlign.TOP,
        children: children.length ? children : [p([run('')], { after: 0 })],
    });
}

// A Part II field: label, then a right-aligned 14-character underlined slot.
function field(label, value, prefix) {
    const blank = !value;
    const slot = blank ? '_'.repeat(14) : '_'.repeat(Math.max(0, 14 - value.length)) + value;
    const small = !blank && value.replace(/,/g, '').length > 12;
    return new Paragraph({
        children: [
            run(label),
            run('\t'),
            ...(prefix ? [run(prefix)] : []),
            new TextRun({ text: slot, font: FONT, size: small ? 18 : SZ, underline: { type: UnderlineType.SINGLE } }),
        ],
        tabStops: [{ type: TabStopType.RIGHT, position: 4650 }],
        spacing: { before: 0, after: 60 },
    });
}

function partRows(rows) {
    return rows.map(function (r) {
        if (r[0] === 'text') return p([run(r[1])], { after: 0 });
        if (r[0] === 'project') {
            const name = r[1] || '_'.repeat(12);
            const size = r[1] && r[1].length > 12 ? Math.max(12, Math.floor(SZ * 12 / r[1].length)) : SZ;
            return p([run('   '), new TextRun({ text: name, font: FONT, size, underline: { type: UnderlineType.SINGLE } })], { after: 0 });
        }
        return field(r[1], r[2], r[3]);
    });
}

// Old form wraps particulars by character count so the amount sits on the last line.
function wordWrap(text, max) {
    const out = [];
    let cur = '';
    text.split(' ').forEach(function (w) {
        if (!cur) { cur = w; return; }
        if ((cur + ' ' + w).length <= max) cur += ' ' + w;
        else { out.push(cur); cur = w; }
    });
    if (cur) out.push(cur);
    return out;
}

const chk = (on) => on ? '☒' : '☐';
const nameRun = (name) => run(name || '_'.repeat(20), { bold: !!name, underline: !!name });

// ── Part I ────────────────────────────────────────────────────────────────────
const children = [];
const J = AlignmentType.JUSTIFY;
const C = AlignmentType.CENTER;

// Regional office's form (issuing_office REGIONAL): its form number top right.
if (m.form_no) children.push(p([run(m.form_no, { bold: true })], { align: AlignmentType.RIGHT, after: 0 }));
children.push(p([run('Part I: To be accomplished by Requesting Unit', { underline: true })], { align: J, after: 0 }));
children.push(p([
    run('REQUEST FOR FUND ALLOTMENT NO. (To be filled-out by ' + m.filled_out_by + ') '),
    run(m.rfa_no || '____________', { underline: !!m.rfa_no, bold: !!m.rfa_no }),
], { align: J, before: 240, after: 0 }));
children.push(p([run('(For Job Orders, Contracts, Loan Agreements, Project Proposals,')], { align: J, after: 0 }));
children.push(p([run(' Change Orders, Price Escalations, etc.)')], { align: J, after: 0 }));
children.push(p([run('   ' + m.rfa_type, { bold: true })], { align: J, before: 100, after: 120, border: rule(BorderStyle.SINGLE, 6, 4) }));

children.push(p([
    run('ALLOTMENT TO BE MADE IN FAVOR OF:  '),
    run(m.payee || '_'.repeat(20), { bold: !!m.payee, underline: !!m.payee }),
    run('\t'),
    run('DATE:  '),
    run(m.date, { bold: true, underline: true }),
], { align: J, before: 240, after: 120, tabStops: [{ type: TabStopType.LEFT, position: 7200 }] }));
children.push(p([], { after: 120, border: rule(BorderStyle.SINGLE, 6, 4) }));

const amountTab = [{ type: TabStopType.RIGHT, position: 8640 }];
children.push(p([run('DESCRIPTION/NATURE OF REQUEST'), run('\t'), run('AMOUNT', { bold: true })], { align: J, after: 0, tabStops: amountTab }));
// Regional form: "Request for Fund Allotment" above the particulars, which print bold.
if (m.desc_heading) children.push(p([run(m.desc_heading, { bold: true })], { align: J, after: 0 }));
const lineBold = !!m.particulars_bold;

const lines = (m.particulars || []).flatMap(function (l, i) {
    if (i === 0) {
        const w = wordWrap(l, 58);
        return [w[0]].concat(w.slice(1).flatMap((r) => wordWrap(r, 63)));
    }
    return wordWrap(l, 63);
});
if (lines.length) {
    lines.forEach(function (line, i) {
        const last = i === lines.length - 1;
        children.push(last
            ? p([run(line, { bold: lineBold }), run('\t'), run('₱ ' + m.amount, { bold: true })], { align: J, after: 0, tabStops: amountTab })
            : p([run(line, { bold: lineBold })], { align: J, after: 0 }));
    });
    children.push(p([run('')], { after: 0 }));
} else {
    for (let i = 0; i < 4; i++) children.push(p([run('')], { after: 0 }));
}
children.push(p([run('')], { after: 120 }));
children.push(p([], { align: J, before: 100, after: 120, border: rule(BorderStyle.SINGLE, 6, 4) }));

children.push(new Paragraph({
    children: [
        run('CERTIFIED CORRECT:'), run('\t'),
        run('Allotment/Obligation item necessary, lawful and incurred under my direct supervision chargeable against my regional/department/district budget, prices just and reasonable and not in excess of current rates locality.'),
    ],
    tabStops: [{ type: TabStopType.LEFT, position: 2160 }],
    indent: { left: 2160, hanging: 2160 },
    alignment: J,
    spacing: { before: 0, after: 0 },
}));
children.push(p([run('')], { after: 120 }));

children.push(new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    borders: tableBorders,
    rows: [new TableRow({ children: [
        cell(50, [], { pct: true }),
        cell(50, [
            p([nameRun(m.cert.name)], { align: C, after: 0 }),
            p([run(m.cert.role)], { align: C, after: 0 }),
            p([run(m.cert.title)], { align: C, after: 0 }),
            p([run(m.cert.office)], { align: C, after: 0 }),
        ], { pct: true }),
    ] })],
}));
children.push(p([run('')], { after: 0 }));
children.push(p([], { after: 120, border: rule(BorderStyle.DOUBLE, 6, 1) }));

// ── Part II ───────────────────────────────────────────────────────────────────
children.push(p([run('Part II: To be accomplished by the ' + m.part2_by, { underline: true })], { after: 120 }));

children.push(new Table({
    width: { size: 10640, type: WidthType.DXA },
    columnWidths: [4860, 270, 5510],
    borders: tableBorders,
    rows: [new TableRow({ children: [
        cell(4860, [p([run('A.  Program/Project Reference', { underline: true })], { align: C, before: 120, after: 120 })].concat(partRows(m.part_a))),
        cell(270, []),
        cell(5510, [p([run('B.  Allotment Reference', { underline: true })], { align: C, before: 120, after: 120 })].concat(partRows(m.part_b))),
    ] })],
}));
children.push(p([run('')], { after: 0 }));

// C. Funding Source — borderless 4-column table (indent | SARO | OR | others)
const fs1 = m.funding;
children.push(p([run('C. Funding Source:', { underline: true })], { after: 0, line: 360, indent: { left: 734 } }));
children.push(p([run('')], { after: 0 }));
const FW = [734, 2966, 2900, 4200];
const fsRow = (cols) => new TableRow({ children: cols.map((c, i) => cell(FW[i], c ? [c] : [])) });
const fsP = (runs, o) => p(runs, Object.assign({ after: 0 }, o || {}));
children.push(new Table({
    width: { size: FW.reduce((a, b) => a + b, 0), type: WidthType.DXA },
    columnWidths: FW,
    borders: tableBorders,
    rows: [
        fsRow([null, fsP([run('NG Support')]), null, fsP([run('Others:')])]),
        fsRow([null, null, null, fsP([run('Name of Agency: ' + (fs1.agency || '__________'))])]),
        fsRow([null, fsP([run('Subsidy  ' + chk(fs1.subsidy))]), null, fsP([run('Equity  ' + chk(fs1.equity))])]),
        fsRow([null, fsP([run('SARO', { underline: true })], { align: C }), fsP([run('OR', { underline: true })], { align: C }),
            fsP([run('Corporate Receipts', { underline: true })], { align: C })]),
        fsRow([null, fsP([run('No.: '), run(fs1.saro_no || '_'.repeat(14), { underline: true })]),
            fsP([run('No.: ' + (fs1.or_no || '_'.repeat(14)))]), fsP([run(chk(fs1.corporate))], { align: C })]),
        fsRow([null, fsP([run('Date: '), run(fs1.saro_date || '_'.repeat(14), { underline: true })]),
            fsP([run('Date: ' + (fs1.or_date || '_'.repeat(14)))]), null]),
    ],
}));

children.push(p([run('CERTIFIED FUNDS AVAILABLE: ')], { align: C, before: 240, after: 0 }));
children.push(p([run('')], { after: 0 }));
children.push(p([run('_______________________  ', { bold: true })], { align: C, after: 0 }));
children.push(p([run(m.cert_funds.name)], { align: C, after: 0 }));
children.push(p([run(m.cert_funds.title)], { align: C, after: 120 }));
children.push(p([], { before: 120, after: 120, border: rule(BorderStyle.DOUBLE, 6, 1) }));

const signBlock = (label, name, role) => cell(50, [
    p([run(label, { bold: true })], { align: C, after: 240 }),
    p([run('')], { after: 120 }),
    p([nameRun(name)], { align: C, after: 0 }),
    p([run(role)], { align: C, after: 0 }),
], { pct: true });
children.push(new Table({
    width: { size: 100, type: WidthType.PERCENTAGE },
    borders: tableBorders,
    rows: [new TableRow({ children: [
        signBlock('RECOMMENDING APPROVAL:', m.recommending, '(Finance Unit Head)'),
        signBlock('APPROVED:', m.approved, '(Regional Manager)'),
    ] })],
}));

// ── Document ──────────────────────────────────────────────────────────────────
const doc = new Document({
    sections: [{
        properties: {
            page: {
                size: { width: 12240, height: 20160 }, // 8.5" × 14" legal
                margin: { top: 432, right: 720, bottom: 432, left: 720, header: 288 },
            },
        },
        headers: { default: new Header({ children: headerChildren }) },
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
