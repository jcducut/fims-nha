'use strict';
/**
 * =============================================================================
 * FIMS DOCX Branding Resolver
 * =============================================================================
 * Resolves the header (letterhead) and footer image that a DOCX generator
 * should stamp onto a document.
 *
 * The images used to be hard-coded as base64 string literals inside
 * REGIONAL/scripts/gen_cba_docx.js, and the other generators either duplicated
 * the literal or scraped it back out of that file with a regex. They are now
 * managed in UAC (Header Management page -> uac.document_header_assets), which
 * mirrors whatever is ACTIVE into _shared/branding/ alongside a manifest.json.
 *
 * Generators are short-lived `node gen_x_docx.js data.json out.docx` processes
 * with no database credentials, so this module reads the mirror off disk rather
 * than querying PostgreSQL.
 *
 * Resolution order, most specific first:
 *   1. base64 handed in by the caller       (data._branding.header)
 *   2. manifest entry for system + document (header-regional-cba.png)
 *   3. manifest entry for the document      (header-all-cba.png)
 *   4. manifest entry for the system        (header-regional-all.png)
 *   5. manifest entry for the global default(header-all-all.png)
 *   6. the generator's own embedded base64  (fallbackBase64)
 *
 * Step 6 is why nothing breaks before an administrator uploads anything: every
 * generator keeps its original literal as a last resort.
 *
 * Usage:
 *   const branding = require('../../_shared/docx-branding');
 *   const header = branding.resolve('HEADER', {
 *       system: 'REGIONAL',
 *       document: 'CBA',
 *       data,                       // the generator's parsed JSON payload
 *       fallbackBase64: HEADER_B64,
 *       defaultWidth: 630,
 *       defaultHeight: 95,
 *   });
 *   // -> { data: Buffer|null, width, height, type: 'png'|'jpg', source: '...' }
 *
 * @package GovFlow360
 * @subpackage shared
 * =============================================================================
 */

const fs   = require('fs');
const path = require('path');

/** Where UAC publishes the active assets. */
const BRANDING_DIR = path.join(__dirname, 'branding');

/** Established NHA render sizes, matching what the generators used before. */
const DEFAULT_SIZE = {
    HEADER: { width: 630, height: 95 },
    FOOTER: { width: 415, height: 35 },
};

// Read once per process. Generators run for a fraction of a second and exit,
// so there is nothing to invalidate.
let manifestCache;

function readManifest() {
    if (manifestCache !== undefined) return manifestCache;

    try {
        const raw = fs.readFileSync(path.join(BRANDING_DIR, 'manifest.json'), 'utf8');
        const parsed = JSON.parse(raw);
        manifestCache = Array.isArray(parsed?.assets) ? parsed.assets : [];
    } catch (e) {
        // No manifest yet (nothing uploaded), or it is unreadable. Either way
        // the caller falls back to its embedded base64.
        manifestCache = [];
    }
    return manifestCache;
}

function norm(v) {
    return v === null || v === undefined || v === '' ? null : String(v).toUpperCase();
}

/**
 * Score how well a manifest entry matches the requested scope.
 * Returns -1 when the entry does not apply at all.
 */
function scopeScore(entry, system, document) {
    const eSys = norm(entry.system);
    const eDoc = norm(entry.document);

    // A scoped entry must match the request; a null scope matches anything.
    if (eSys !== null && eSys !== system) return -1;
    if (eDoc !== null && eDoc !== document) return -1;

    if (eSys !== null && eDoc !== null) return 4; // system + document
    if (eDoc !== null) return 3;                  // document only
    if (eSys !== null) return 2;                  // system only
    return 1;                                     // global default
}

function extToType(fileName) {
    return /\.jpe?g$/i.test(fileName) ? 'jpg' : 'png';
}

function bufferFromBase64(b64) {
    if (!b64 || typeof b64 !== 'string') return null;
    // Tolerate a data: URI, in case a caller passes one straight through.
    const stripped = b64.replace(/^data:[^;,]+;base64,/i, '').replace(/\s+/g, '');
    if (!stripped) return null;
    try {
        const buf = Buffer.from(stripped, 'base64');
        return buf.length ? buf : null;
    } catch (e) {
        return null;
    }
}

function typeFromBuffer(buf, fallback) {
    if (!buf || buf.length < 4) return fallback;
    if (buf[0] === 0x89 && buf[1] === 0x50) return 'png';
    if (buf[0] === 0xff && buf[1] === 0xd8) return 'jpg';
    return fallback;
}

/**
 * Resolve one branding asset.
 *
 * @param {string} assetType 'HEADER' | 'FOOTER'
 * @param {object} [opts]
 * @param {string} [opts.system]          Owning system, e.g. 'REGIONAL'
 * @param {string} [opts.document]        Form code, e.g. 'CBA', 'PR', 'PO'
 * @param {object} [opts.data]            Generator payload; an override may
 *                                        arrive as data._branding.header /
 *                                        .footer (base64 or data URI)
 * @param {string} [opts.fallbackBase64]  The generator's embedded literal
 * @param {number} [opts.defaultWidth]
 * @param {number} [opts.defaultHeight]
 * @returns {{data: Buffer|null, width: number, height: number, type: string, source: string, assetCode: (string|null)}}
 */
function resolve(assetType, opts = {}) {
    const type     = norm(assetType) || 'HEADER';
    const system   = norm(opts.system);
    const document = norm(opts.document);

    const fallbackSize = DEFAULT_SIZE[type] || DEFAULT_SIZE.HEADER;
    const width  = opts.defaultWidth  || fallbackSize.width;
    const height = opts.defaultHeight || fallbackSize.height;

    const miss = {
        data: null, width, height, type: 'png', source: 'none', assetCode: null,
    };

    // ── 1. Explicit override in the payload ──────────────────────────────────
    // Lets a PHP route pass an image straight through without touching disk.
    const override = opts.data && opts.data._branding
        ? opts.data._branding[type.toLowerCase()]
        : null;
    if (override) {
        const buf = bufferFromBase64(typeof override === 'string' ? override : override.base64);
        if (buf) {
            return {
                data: buf,
                width:  (override && override.width)  || width,
                height: (override && override.height) || height,
                type: typeFromBuffer(buf, 'png'),
                source: 'payload',
                assetCode: (override && override.asset_code) || null,
            };
        }
    }

    // ── 2-5. Best match from the UAC-published manifest ─────────────────────
    let best = null;
    let bestScore = 0;

    for (const entry of readManifest()) {
        if (norm(entry.type) !== type) continue;
        const score = scopeScore(entry, system, document);
        if (score > bestScore) {
            best = entry;
            bestScore = score;
        }
    }

    if (best && best.file) {
        try {
            const buf = fs.readFileSync(path.join(BRANDING_DIR, best.file));
            if (buf.length) {
                return {
                    data: buf,
                    width:  (best.render && best.render.width)  || width,
                    height: (best.render && best.render.height) || height,
                    type: typeFromBuffer(buf, extToType(best.file)),
                    source: 'uac',
                    assetCode: best.asset_code || null,
                };
            }
        } catch (e) {
            // Manifest points at a file that is gone — fall through to the
            // generator's embedded literal rather than failing the print.
        }
    }

    // ── 6. The generator's own embedded base64 ───────────────────────────────
    const fb = bufferFromBase64(opts.fallbackBase64);
    if (fb) {
        return {
            data: fb,
            width,
            height,
            type: typeFromBuffer(fb, 'png'),
            source: 'fallback',
            assetCode: null,
        };
    }

    return miss;
}

/** Convenience wrappers. */
function resolveHeader(opts = {}) {
    return resolve('HEADER', opts);
}

function resolveFooter(opts = {}) {
    return resolve('FOOTER', opts);
}

// ─────────────────────────────────────────────────────────────────────────────
// Composed header — the letterhead as four managed parts
// ─────────────────────────────────────────────────────────────────────────────
//
//   [ NHA logo ]  [ NATIONAL HOUSING AUTHORITY  ]  [ Pambansang ]  [ Bagong    ]
//                 [ Build Better and More Housing]  [ Pabahay    ]  [ Pilipinas ]
//     LOGO                    TITLE                    BRAND           SEAL
//    (image)                  (text)                  (image)         (image)
//
// UAC publishes each part separately so a single seal or the tagline can be
// changed without re-cutting the whole banner.

/**
 * The active parts for a scope, left to right. Same most-specific-wins rule as
 * resolve(), applied per slot so parts from different scopes can coexist.
 *
 * @returns {Array} ordered parts, or [] when nothing is published
 */
function resolveHeaderParts(opts = {}) {
    const system   = norm(opts.system);
    const document = norm(opts.document);

    let manifest;
    try {
        const raw = fs.readFileSync(path.join(BRANDING_DIR, 'manifest.json'), 'utf8');
        manifest = JSON.parse(raw);
    } catch (e) {
        return [];
    }

    const parts = Array.isArray(manifest?.header_parts) ? manifest.header_parts : [];
    if (!parts.length) return [];

    // Keep only the best-scoped entry per slot.
    const bySlot = new Map();
    for (const p of parts) {
        const score = scopeScore(p, system, document);
        if (score < 0) continue;
        const slot = norm(p.slot);
        const prev = bySlot.get(slot);
        if (!prev || score > prev._score) {
            bySlot.set(slot, Object.assign({}, p, { _score: score }));
        }
    }

    return [...bySlot.values()].sort((a, b) => (a.order || 0) - (b.order || 0));
}

/**
 * One named part of the letterhead, as a ready-to-use image.
 *
 * Some forms (PR, CRF) don't print a full-width letterhead — they put a small
 * square logo inside a table cell. For those, the NHA logo part is the right
 * thing to draw, not the whole banner squashed into a square.
 *
 * @param {string} slotCode  LOGO | BRAND | SEAL
 * @returns {{data: Buffer|null, width, height, type, source}}
 */
function resolveHeaderPartImage(slotCode, opts = {}) {
    const want = norm(slotCode);
    const miss = {
        data: null,
        width:  opts.defaultWidth  || 60,
        height: opts.defaultHeight || 60,
        type: 'png',
        source: 'none',
    };

    const part = resolveHeaderParts(opts).find(p => norm(p.slot) === want && p.kind !== 'text');
    if (part && part.file) {
        try {
            const buf = fs.readFileSync(path.join(BRANDING_DIR, part.file));
            if (buf.length) {
                return {
                    data: buf,
                    // The caller's size wins: it knows the cell it is filling.
                    width:  opts.defaultWidth  || (part.render && part.render.width)  || 60,
                    height: opts.defaultHeight || (part.render && part.render.height) || 60,
                    type: typeFromBuffer(buf, extToType(part.file)),
                    source: 'uac-part',
                };
            }
        } catch (e) {
            // Fall through to the caller's fallback.
        }
    }

    const fb = bufferFromBase64(opts.fallbackBase64);
    if (fb) {
        return {
            data: fb,
            width:  opts.defaultWidth  || 60,
            height: opts.defaultHeight || 60,
            type: typeFromBuffer(fb, 'png'),
            source: 'fallback',
        };
    }

    return miss;
}

/** px -> DXA (twentieths of a point) at 96 dpi. */
function pxToDxa(px) {
    return Math.round((Number(px) || 0) * 15);
}

/**
 * How many parts the letterhead is supposed to have, per the manifest UAC
 * publishes. 0 when unknown, which disables the completeness check.
 */
function expectedSlotCount() {
    try {
        const raw = fs.readFileSync(path.join(BRANDING_DIR, 'manifest.json'), 'utf8');
        const m = JSON.parse(raw);
        return Array.isArray(m?.header_slots) ? m.header_slots.length : 0;
    } catch (e) {
        return 0;
    }
}

/**
 * Build the elements that go inside a docx Header.
 *
 * Resolution order:
 *   1. a whole-header image (one picture for the entire letterhead)
 *   2. the four composed parts
 *   3. the generator's embedded base64 literal
 *
 * Step 1 comes first so an uploaded full-width banner still overrides the
 * parts, and step 3 means a generator with nothing published prints exactly as
 * it always did.
 *
 * @param {object} docxLib  the generator's `require('docx')` — passed in so this
 *                          module stays dependency-free
 * @param {object} opts     system, document, data, fallbackBase64,
 *                          totalWidth (default 630), totalHeight (default 95),
 *                          parts (optional) — explicit parts to compose instead
 *                          of steps 1-2: same shape as the manifest's
 *                          header_parts, but an image part carries its bytes as
 *                          `data` (Buffer) instead of a mirror `file`
 * @returns {{children: Array, source: string}}
 */
function buildHeaderChildren(docxLib, opts = {}) {
    const {
        Paragraph, TextRun, ImageRun, Table, TableRow, TableCell,
        AlignmentType, WidthType, VerticalAlign, BorderStyle, TableLayoutType,
    } = docxLib;

    const totalWidth  = opts.totalWidth  || DEFAULT_SIZE.HEADER.width;
    const totalHeight = opts.totalHeight || DEFAULT_SIZE.HEADER.height;

    const alignOf = (a) => ({
        left:   AlignmentType.LEFT,
        right:  AlignmentType.RIGHT,
        center: AlignmentType.CENTER,
    })[String(a || 'center').toLowerCase()] || AlignmentType.CENTER;

    const explicitParts = Array.isArray(opts.parts) && opts.parts.length ? opts.parts : null;

    // ── 0. Freely placed parts (every part has x / y) ───────────────────────
    if (explicitParts && explicitParts.every(p => Number.isFinite(Number(p.x)) && Number.isFinite(Number(p.y)))) {
        return { source: 'placed', children: buildPlacedHeader(docxLib, explicitParts, totalWidth, opts.origin || {}) };
    }

    // ── 1. Whole-header image (also covers the embedded fallback) ───────────
    const whole = explicitParts ? { data: null } : resolveHeader({
        system: opts.system,
        document: opts.document,
        data: opts.data,
        // Deliberately no fallback here: we want to know whether a real
        // whole-header image is published before we consider the parts.
        defaultWidth: totalWidth,
        defaultHeight: totalHeight,
    });

    if (whole.data && (whole.source === 'payload' || whole.source === 'uac')) {
        return {
            source: whole.source,
            children: [new Paragraph({
                children: [new ImageRun({
                    data: whole.data,
                    transformation: { width: whole.width, height: whole.height },
                    type: whole.type,
                })],
                spacing: { after: 0 },
            })],
        };
    }

    // ── 2. Composed parts ───────────────────────────────────────────────────
    const parts = explicitParts || resolveHeaderParts(opts);
    const usable = parts.filter(p =>
        (p.kind === 'text' && (p.text?.line1 || p.text?.line2)) ||
        (p.kind !== 'text' && (p.file || p.data)));

    // Only compose once EVERY part is in place. A half-configured letterhead —
    // say the agency name uploaded but the logos still missing — would
    // otherwise print as a lone line of text on official documents, which is
    // worse than printing the old built-in banner.
    const expected = expectedSlotCount();
    // Explicit parts are the caller's complete choice — no slot count to meet.
    const complete = usable.length > 0 && (explicitParts !== null || expected === 0 || usable.length >= expected);

    if (complete) {
        const NONE = { style: BorderStyle.NONE, size: 0, color: 'FFFFFF' };
        const noBorders = { top: NONE, bottom: NONE, left: NONE, right: NONE };

        // Normalise the column shares so they always fill the width exactly,
        // however the administrator has tuned them.
        const totalPct = usable.reduce((s, p) => s + (Number(p.column_pct) || 0), 0) || usable.length;
        const totalDxa = pxToDxa(totalWidth);

        // Work the column widths out up front: Word needs them in the table
        // grid, not just on the cells, or it lays the row out evenly.
        const colWidths = usable.map(p =>
            Math.max(1, Math.round(totalDxa * ((Number(p.column_pct) || (100 / usable.length)) / totalPct))));
        // Rounding can drift a twip or two; give the remainder to the widest
        // column so the row still adds up to the full width.
        const drift = totalDxa - colWidths.reduce((s, w) => s + w, 0);
        if (drift !== 0) {
            const widest = colWidths.indexOf(Math.max(...colWidths));
            colWidths[widest] += drift;
        }

        const cells = usable.map((p, i) => {
            const colDxa = colWidths[i];
            const align  = alignOf(p.align);

            let children;

            if (p.kind === 'text') {
                const t = p.text || {};
                const font = t.font || 'Arial';
                const mk = (txt, sizePt, bold, color) => new Paragraph({
                    children: [new TextRun({
                        text: String(txt),
                        bold: !!bold,
                        font,
                        // docx sizes are in half-points. Text sizes are given for a
                        // 630 px letterhead (like the part image sizes); scale them to
                        // the real width (px → pt is × 0.75) so the title keeps the
                        // same proportion as in the HTML/PDF letterhead
                        // (DocumentBranding: size × widthPt / 630).
                        size: Math.round((Number(sizePt) || 11) * (totalWidth * 0.75 / 630) * 2),
                        color: (color || '000000').replace(/^#/, ''),
                    })],
                    alignment: align,
                    spacing: { before: 0, after: 0 },
                });
                children = [];
                if (t.line1) children.push(mk(t.line1, t.line1_size_pt, t.line1_bold, t.line1_color));
                if (t.line2) children.push(mk(t.line2, t.line2_size_pt, t.line2_bold, t.line2_color));
            } else {
                let buf = Buffer.isBuffer(p.data) ? p.data : null;
                if (!buf) {
                    try {
                        buf = fs.readFileSync(path.join(BRANDING_DIR, p.file));
                    } catch (e) {
                        buf = null;
                    }
                }
                children = buf && buf.length
                    ? [new Paragraph({
                        children: [new ImageRun({
                            data: buf,
                            transformation: {
                                width:  (p.render && p.render.width)  || 60,
                                height: (p.render && p.render.height) || 60,
                            },
                            type: typeFromBuffer(buf, extToType(p.file)),
                        })],
                        alignment: align,
                        spacing: { before: 0, after: 0 },
                    })]
                    // An empty cell still has to hold the column open.
                    : [new Paragraph({ text: '', spacing: { before: 0, after: 0 } })];
            }

            return new TableCell({
                width: { size: colDxa, type: WidthType.DXA },
                verticalAlign: VerticalAlign.CENTER,
                borders: noBorders,
                margins: { top: 0, bottom: 0, left: i === 0 ? 0 : 40, right: 40 },
                children,
            });
        });

        return {
            source: 'uac-parts',
            children: [new Table({
                width: { size: totalDxa, type: WidthType.DXA },
                columnWidths: colWidths,
                layout: TableLayoutType.FIXED,
                borders: noBorders,
                rows: [new TableRow({ children: cells })],
            })],
        };
    }

    // ── 3. The generator's embedded base64 ──────────────────────────────────
    const fb = bufferFromBase64(opts.fallbackBase64);
    if (fb) {
        return {
            source: 'fallback',
            children: [new Paragraph({
                children: [new ImageRun({
                    data: fb,
                    transformation: { width: totalWidth, height: totalHeight },
                    type: typeFromBuffer(fb, 'png'),
                })],
                spacing: { after: 0 },
            })],
        };
    }

    return { source: 'none', children: [] };
}

/**
 * A freely placed letterhead (the print preview's header designer) as Word
 * header content. Parts are in drawing order, with x / y / box_width and
 * render {width, height} in px at the real header width (`totalWidth`); text
 * sizes are still given for a 630 px letterhead and are scaled here, as in the
 * row layout above.
 *
 * Word has no "absolute box" for a header, so:
 *   - an empty first paragraph exactly as tall as the letterhead holds the space;
 *   - images float in it ("in front of text"), positioned from the page edge;
 *   - each title is a text frame, positioned from the page edge;
 *   - a hairline paragraph closes the header (Word wants a plain last paragraph).
 * `origin` {left, top} (twips) is where the header area starts on the page: the
 * left margin and the header distance from the top edge.
 */
function buildPlacedHeader(docxLib, parts, totalWidth, origin) {
    const {
        Paragraph, TextRun, ImageRun, AlignmentType, FrameAnchorType,
        HorizontalPositionRelativeFrom, VerticalPositionRelativeFrom, TextWrappingType,
    } = docxLib;
    const TWIPS = 15;      // per px (and 635 EMU per twip for the image offsets)
    const k     = totalWidth / 630;
    const left  = Number(origin.left) || 0;
    const top   = Number(origin.top) || 0;
    const alignOf = a => ({ left: AlignmentType.LEFT, right: AlignmentType.RIGHT })[a] || AlignmentType.CENTER;

    const floats = [];
    const frames = [];
    let bottom = 0;   // px

    parts.forEach((p, i) => {
        const x = Number(p.x) || 0;
        const y = Number(p.y) || 0;

        if (p.kind === 'text') {
            const t = p.text || {};
            const lines = [
                [t.line1, t.line1_size_pt, t.line1_bold, t.line1_color],
                [t.line2, t.line2_size_pt, t.line2_bold, t.line2_color],
            ].filter(l => l[0]);
            if (!lines.length) return;
            const runs = [];
            let h = 0;
            lines.forEach(([txt, sizePt, bold, color], n) => {
                const pt = (Number(sizePt) || 11) * k * 0.75;   // printed size
                h += pt * 1.15 / 0.75;
                runs.push(new TextRun({
                    text: String(txt), bold: !!bold, font: (t.font || 'Arial'),
                    size: Math.round(pt * 2), color: (color || '000000').replace(/^#/, ''),
                    break: n > 0 ? 1 : undefined,
                }));
            });
            bottom = Math.max(bottom, y + h);
            frames.push(new Paragraph({
                frame: {
                    type: 'absolute',
                    position: { x: Math.round(left + x * TWIPS), y: Math.round(top + y * TWIPS) },
                    width: Math.max(1, Math.round((Number(p.box_width) || 300) * TWIPS)),
                    anchor: { horizontal: FrameAnchorType.PAGE, vertical: FrameAnchorType.PAGE },
                },
                alignment: alignOf(p.align),
                spacing: { before: 0, after: 0 },
                children: runs,
            }));
            return;
        }

        const buf = Buffer.isBuffer(p.data) ? p.data : null;
        if (!buf || !buf.length) return;
        const w = Math.max(1, Math.round(Number(p.render?.width) || 60));
        const h = Math.max(1, Math.round(Number(p.render?.height) || 60));
        bottom = Math.max(bottom, y + h);
        floats.push(new ImageRun({
            data: buf,
            type: typeFromBuffer(buf, 'png'),
            transformation: { width: w, height: h },
            floating: {
                horizontalPosition: { relative: HorizontalPositionRelativeFrom.PAGE, offset: Math.round((left + x * TWIPS) * 635) },
                verticalPosition:   { relative: VerticalPositionRelativeFrom.PAGE, offset: Math.round((top + y * TWIPS) * 635) },
                allowOverlap: true,
                behindDocument: false,
                wrap: { type: TextWrappingType.NONE },
                zIndex: i + 1,
            },
        }));
    });

    if (!floats.length && !frames.length) return [new Paragraph({ children: [] })];

    return [
        new Paragraph({
            children: floats,
            spacing: { before: 0, after: 0, line: Math.max(20, Math.round(bottom * TWIPS)), lineRule: 'exact' },
        }),
        ...frames,
        new Paragraph({ children: [], spacing: { before: 0, after: 0, line: 20, lineRule: 'exact' } }),
    ];
}

/**
 * Pull a base64 literal back out of a generator script. Kept here because
 * gen_pr/po/crf/ftla/laa_docx.js each had their own copy of this regex scrape
 * against gen_cba_docx.js; they now share this one.
 *
 * @param {string} scriptPath Absolute path to the generator to read
 * @param {string} varName    e.g. 'HEADER_B64'
 * @returns {string|null}
 */
function extractEmbeddedBase64(scriptPath, varName) {
    try {
        const src    = fs.readFileSync(scriptPath, 'utf8');
        const prefix = `const ${varName} = '`;
        const idx    = src.indexOf(prefix);
        if (idx < 0) return null;
        const start = idx + prefix.length;
        const end   = src.indexOf("';", start);
        return end < 0 ? null : src.substring(start, end);
    } catch (e) {
        return null;
    }
}

module.exports = {
    BRANDING_DIR,
    DEFAULT_SIZE,
    resolve,
    resolveHeader,
    resolveFooter,
    resolveHeaderParts,
    resolveHeaderPartImage,
    buildHeaderChildren,
    extractEmbeddedBase64,
};
