<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Letterhead layout for printed documents — the header as freely placed items
 * (images and titles, anywhere on the header area) plus the footer text line.
 *
 *   layout = {
 *     items: [
 *       { type: 'image', asset: 'logo'|'brand'|'seal'  OR  src: 'data:image/png;base64,…',
 *         x, y, height },
 *       { type: 'title', line1, line2, size1, size2, bold1, x, y, width,
 *         align: left|center|right (text alignment inside its box) },
 *       …   (later items are drawn on top of earlier ones)
 *     ],
 *     footer_text: '…'
 *   }
 *
 * Every size and position is in px on a 630 px wide letterhead (the "unit"),
 * x / y = the item's top-left corner on the header area (630 × AREA_HEIGHT);
 * the printed header is as tall as its lowest item. Printouts scale the units
 * to their own header width (CBA 468 pt, RFA 540 pt, LAA 480 pt).
 *
 * Older layouts (a row of items with width_pct / align) are converted to
 * positions on load, looking the same as before — see fromRow().
 *
 * Where a print's letterhead comes from:
 *   1. the layout edited in the print preview (payload `letterhead`) — that print only;
 *   2. the saved default for the document (fims.letterhead_layout, "Save as default header");
 *   3. the built-in letterhead (the NHA banner image + DEFAULT_FOOTER_TEXT).
 * factoryLayout() is the built-in letterhead as editable items — the parts were
 * cut from the banner (_shared/images/letterhead/nha-letterhead-banner.png) into
 * the same folder (see ASSETS).
 *
 * Rendering reuses the shared composer: DocumentBranding::composeHeaderHtml()
 * (HTML/PDF) and docx-branding buildHeaderChildren({parts}) (DOCX).
 */
class LetterheadLayoutService
{
    // Printed documents that use this letterhead (editable in the preview, with a
    // saved default — one per code, so the FTLA request keeps its own even though it
    // shares the LAA release request's layout) and whether they print a footer line. Forms not listed here
    // print with no letterhead at all — they carry their own printed title block
    // (PO, IAR, LV, JEV) or are official PDF forms (DV); see PrintService::model().
    public const DOCUMENTS = [
        'CBA' => ['footer' => true],
        'RFA' => ['footer' => false],   // the RFA form has no footer
        'LAA' => ['footer' => true],
        'CAF' => ['footer' => true],
        'LAAREQ' => ['footer' => false],   // the regional LAA release memo has no footer
        'PR'  => ['footer' => false],
        'FTLA' => ['footer' => false],      // the letter to the bank
        'FTLAREQ' => ['footer' => false],   // the regional FTLA request memo
        'CFA' => ['footer' => false],       // Certified Funds Available (funding-request memo)
        'MEMO' => ['footer' => false],      // the originating unit's memo request (non-FMD)
    ];

    public static function isDocument(string $doc): bool
    {
        return isset(self::DOCUMENTS[$doc]);
    }

    public static function hasFooter(string $doc): bool
    {
        return !empty(self::DOCUMENTS[$doc]['footer']);
    }

    // Who may save / restore the default: System Administrator, FMD Department Manager.
    public const SAVER_LEVELS = [-1, 4];

    public const DEFAULT_FOOTER_TEXT = 'Elliptical Road, Diliman Quezon City; Trunkline: (02) 8790-0800; www.nha.gov.ph';

    // Built-in images an item can reference by name (instead of embedding them),
    // in _shared/images/letterhead/. The keys are stored in saved layouts — keep them.
    private const ASSETS = [
        'logo'  => 'nha-logo.png',             // NHA logo
        'brand' => 'pambansang-pabahay.png',   // Pambansang Pabahay para sa Pilipino
        'seal'  => 'bagong-pilipinas.png',     // Bagong Pilipinas
    ];

    public const LETTERHEAD_PX    = 630;              // composer's reference width
    public const AREA_HEIGHT      = 150;              // header area height, same units
    private const MAX_ITEMS       = 8;
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const MAX_TOTAL_BYTES = 5 * 1024 * 1024;
    private const TEXT_MAX        = 80;
    private const FOOTER_MAX      = 300;
    private const ALIGNS          = ['left', 'center', 'right'];

    private const TABLE = 'fims.letterhead_layout';
    private const SQL_FILE = '_assets/sql/01 - fims/01 - table/16 - fims_letterhead_layout.sql';

    // The NHA banner as a row (column shares) — placed by fromRow() into factoryLayout().
    private const FACTORY_ROW = [
        ['type' => 'image', 'asset' => 'logo', 'height' => 57, 'width_pct' => 14, 'align' => 'center'],
        ['type' => 'title', 'line1' => 'NATIONAL HOUSING AUTHORITY', 'line2' => 'Build Better and More Housing',
            'size1' => 16, 'size2' => 9, 'bold1' => true, 'width_pct' => 51, 'align' => 'center'],
        ['type' => 'image', 'asset' => 'brand', 'height' => 55, 'width_pct' => 20, 'align' => 'center'],
        ['type' => 'image', 'asset' => 'seal', 'height' => 66, 'width_pct' => 15, 'align' => 'center'],
    ];

    /** The built-in letterhead as editable items (looks like the NHA banner). */
    public static function factoryLayout(): array
    {
        return [
            'items'       => (new self())->fromRow(self::FACTORY_ROW),
            'footer_text' => self::DEFAULT_FOOTER_TEXT,
        ];
    }

    /** True when the current user's level (PHPMaker: may be "2,8") may save the default. */
    public static function canSave(): bool
    {
        $levels = array_map('intval', explode(',', (string) App\CurrentUserLevel()));

        return (bool) array_intersect($levels, self::SAVER_LEVELS);
    }

    private function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    private function assetPath(string $asset): string
    {
        return $this->repoRoot() . '/_shared/images/letterhead/' . self::ASSETS[$asset];
    }

    /** URLs of the built-in item images, for the editor's thumbnails. */
    public function assetUrls(): array
    {
        $urls = [];
        foreach (self::ASSETS as $name => $file) {
            $urls[$name] = '/_shared/images/letterhead/' . $file . '?v=' . @filemtime($this->assetPath($name));
        }

        return $urls;
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Saved default (fims.letterhead_layout)
    // ═════════════════════════════════════════════════════════════════════════

    /** The saved default layout, or null (none saved, or the table isn't created yet). */
    public function saved(string $doc): ?array
    {
        try {
            $json = App\ExecuteScalar(
                'SELECT layout FROM ' . self::TABLE . ' WHERE document_code = ' . App\QuotedValue($doc, App\DataType::STRING, 'DB'),
                'DB'
            );
        } catch (\Throwable $e) {
            return null;   // table missing (SQL not run yet) — behave as "nothing saved"
        }
        $layout = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($layout)) {
            return null;
        }
        try {
            return $this->normalize($layout);
        } catch (\InvalidArgumentException $e) {
            App\LogError('LetterheadLayoutService: saved ' . $doc . ' layout is invalid: ' . $e->getMessage());

            return null;
        }
    }

    /** What the editor starts from: the saved default, else the built-in layout. */
    public function info(string $doc): array
    {
        $saved = $this->saved($doc);

        return [
            'success' => true,
            'data'    => [
                'layout'     => $saved ?? self::factoryLayout(),
                'saved'      => $saved !== null,
                'can_save'   => self::canSave(),
                'has_footer' => self::hasFooter($doc),
                'factory'  => self::factoryLayout(),
                'assets'   => $this->assetUrls(),
            ],
        ];
    }

    public function save(string $doc, array $layout, int $userId): array
    {
        try {
            $layout = $this->normalize($layout);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'httpCode' => 400];
        }

        try {
            App\Execute(
                'INSERT INTO ' . self::TABLE . ' (document_code, layout, updated_by, updated_at) VALUES ('
                    . App\QuotedValue($doc, App\DataType::STRING, 'DB') . ', '
                    . App\QuotedValue(json_encode($layout, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), App\DataType::STRING, 'DB') . '::jsonb, '
                    . ($userId > 0 ? (int) $userId : 'NULL') . ', NOW())
                 ON CONFLICT (document_code) DO UPDATE
                    SET layout = EXCLUDED.layout, updated_by = EXCLUDED.updated_by, updated_at = NOW()',
                'DB'
            );
        } catch (\Throwable $e) {
            App\LogError('LetterheadLayoutService::save ' . $e->getMessage());

            return ['success' => false, 'httpCode' => 500, 'message' => $this->tableExists()
                ? 'The header could not be saved.'
                : 'Saving needs the letterhead table — run ' . self::SQL_FILE . ' (npm run sql) first.'];
        }

        return ['success' => true, 'message' => 'Saved as the default header.', 'data' => ['layout' => $layout, 'saved' => true]];
    }

    /** "Restore original": drop the saved default so the built-in letterhead is used again. */
    public function reset(string $doc): array
    {
        try {
            App\Execute('DELETE FROM ' . self::TABLE . ' WHERE document_code = ' . App\QuotedValue($doc, App\DataType::STRING, 'DB'), 'DB');
        } catch (\Throwable $e) {
            if ($this->tableExists()) {
                App\LogError('LetterheadLayoutService::reset ' . $e->getMessage());

                return ['success' => false, 'message' => 'The original header could not be restored.', 'httpCode' => 500];
            }
            // No table means nothing was ever saved — already the original.
        }

        return ['success' => true, 'message' => 'Original header restored.', 'data' => ['layout' => self::factoryLayout(), 'saved' => false]];
    }

    private function tableExists(): bool
    {
        try {
            return (bool) App\ExecuteScalar("SELECT to_regclass('" . self::TABLE . "') IS NOT NULL", 'DB');
        } catch (\Throwable $e) {
            return false;
        }
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Validation + rendering
    // ═════════════════════════════════════════════════════════════════════════

    private function cleanText(mixed $v, int $max): string
    {
        return mb_substr(trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) ($v ?? '')) ?? ''), 0, $max);
    }

    private function clamp(mixed $v, float $min, float $max, float $default): float
    {
        return is_numeric($v) ? max($min, min($max, (float) $v)) : $default;
    }

    /**
     * Validate a layout from the client or the DB and return it in canonical
     * form. Throws InvalidArgumentException with a user-facing message.
     */
    public function normalize(array $layout): array
    {
        $items = $layout['items'] ?? [];
        if (!is_array($items)) {
            throw new \InvalidArgumentException('The header layout is not valid.');
        }
        if (count($items) > self::MAX_ITEMS) {
            throw new \InvalidArgumentException('The header can have at most ' . self::MAX_ITEMS . ' items.');
        }
        $items = array_values($items);

        // An older row layout (no positions yet) → the same look, as positions.
        $placed = true;
        foreach ($items as $item) {
            if (!is_array($item) || !is_numeric($item['x'] ?? null) || !is_numeric($item['y'] ?? null)) {
                $placed = false;
                break;
            }
        }
        if (!$placed && $items) {
            $items = $this->fromRow($items);
        }

        $out   = [];
        $bytes = 0;
        foreach ($items as $i => $item) {
            $n = $i + 1;
            if (!is_array($item)) {
                throw new \InvalidArgumentException("Header item {$n} is not valid.");
            }
            $pos = [
                'x' => round($this->clamp($item['x'] ?? null, 0, self::LETTERHEAD_PX - 1, 0), 1),
                'y' => round($this->clamp($item['y'] ?? null, 0, self::AREA_HEIGHT - 1, 0), 1),
            ];

            if (($item['type'] ?? '') === 'image') {
                $img = ['type' => 'image'];
                if (isset($item['asset']) && isset(self::ASSETS[$item['asset']])) {
                    $img['asset'] = $item['asset'];
                } else {
                    $png = $this->parsePng((string) ($item['src'] ?? ''));
                    if ($png === null) {
                        throw new \InvalidArgumentException("Header item {$n}: the image must be a PNG of at most 2 MB.");
                    }
                    $bytes += $png['bytes'];
                    $img['src'] = 'data:image/png;base64,' . $png['base64'];
                }
                $img['height'] = round($this->clamp($item['height'] ?? null, 10, self::AREA_HEIGHT, 55), 1);
                $out[] = $img + $pos;
            } elseif (($item['type'] ?? '') === 'title') {
                $out[] = [
                    'type'  => 'title',
                    'line1' => $this->cleanText($item['line1'] ?? '', self::TEXT_MAX),
                    'line2' => $this->cleanText($item['line2'] ?? '', self::TEXT_MAX),
                    'size1' => round($this->clamp($item['size1'] ?? null, 6, 28, 16), 1),
                    'size2' => round($this->clamp($item['size2'] ?? null, 5, 20, 9), 1),
                    'bold1' => !in_array($item['bold1'] ?? true, [false, 'false', 0, '0'], true),
                    'width' => round($this->clamp($item['width'] ?? null, 20, self::LETTERHEAD_PX, 300), 1),
                    'align' => in_array($item['align'] ?? '', self::ALIGNS, true) ? $item['align'] : 'center',
                ] + $pos;
            } else {
                throw new \InvalidArgumentException("Header item {$n} must be an image or a title.");
            }
        }
        if ($bytes > self::MAX_TOTAL_BYTES) {
            throw new \InvalidArgumentException('The header images add up to more than 5 MB.');
        }

        $footer = $this->cleanText($layout['footer_text'] ?? '', self::FOOTER_MAX);

        return ['items' => $out, 'footer_text' => $footer === '' ? self::DEFAULT_FOOTER_TEXT : $footer];
    }

    /** Height (units) of a title's text: each non-empty line at 1.15 × its size. */
    public static function titleHeight(array $item): float
    {
        $h = 0.0;
        foreach ([1, 2] as $i) {
            if (trim((string) ($item["line{$i}"] ?? '')) !== '') {
                $h += (float) ($item["size{$i}"] ?? ($i === 1 ? 16 : 9)) * 1.15;
            }
        }

        return $h;
    }

    /**
     * A row of items (the older layout: each with a width_pct column share and
     * an align inside it, vertically centred on the row) → the same look as
     * positioned items. Mirrors how the row used to print: columns scaled to
     * fill 630 units, 3 units of padding each side, images shrunk to fit.
     */
    public function fromRow(array $items): array
    {
        $items = array_values(array_filter($items, 'is_array'));
        $total = 0.0;
        foreach ($items as $item) {
            $total += $this->clamp($item['width_pct'] ?? null, 3, 100, 20);
        }
        $total = $total ?: 1;

        $left  = 0.0;
        $boxes = [];
        foreach ($items as $item) {
            $colW  = self::LETTERHEAD_PX * $this->clamp($item['width_pct'] ?? null, 3, 100, 20) / $total;
            $align = in_array($item['align'] ?? '', self::ALIGNS, true) ? $item['align'] : 'center';
            $out   = $item;
            unset($out['width_pct']);

            if (($item['type'] ?? '') === 'title') {
                $out['x']     = $left + 3;
                $out['width'] = max(20, $colW - 6);
                $out['align'] = $align;
                $h            = self::titleHeight($item);
            } else {
                unset($out['align']);
                $h    = $this->clamp($item['height'] ?? null, 10, self::AREA_HEIGHT, 55);
                $info = $this->imageInfo($item);
                $w    = $info ? $h * $info['width'] / $info['height'] : $h;
                $maxW = $colW - 6;
                if ($w > $maxW && $maxW > 0) {
                    $h *= $maxW / $w;
                    $w  = $maxW;
                }
                $out['height'] = $h;
                $out['x'] = match ($align) {
                    'left'  => $left + 3,
                    'right' => $left + $colW - 3 - $w,
                    default => $left + ($colW - $w) / 2,
                };
            }
            $boxes[] = [$out, $h];
            $left   += $colW;
        }

        $rowH = $boxes ? max(array_column($boxes, 1)) : 0;
        $out  = [];
        foreach ($boxes as [$item, $h]) {
            $item['x']      = round(max(0, $item['x']), 1);
            $item['y']      = round(max(0, ($rowH - $h) / 2), 1);
            if (isset($item['height'])) {
                $item['height'] = round($item['height'], 1);
            }
            if (isset($item['width'])) {
                $item['width'] = round($item['width'], 1);
            }
            $out[] = $item;
        }

        return $out;
    }

    /** An image item's PNG: ['base64', 'width', 'height'] or null. */
    private function imageInfo(array $item): ?array
    {
        if (isset($item['asset']) && isset(self::ASSETS[$item['asset']])) {
            $path = $this->assetPath($item['asset']);
            $info = is_file($path) ? @getimagesize($path) : false;

            return $info ? ['base64' => base64_encode((string) file_get_contents($path)), 'width' => $info[0], 'height' => $info[1]] : null;
        }

        return $this->parsePng((string) ($item['src'] ?? ''));
    }

    /** PNG (data URI or bare base64) → ['base64', 'width', 'height', 'bytes'] or null. */
    public function parsePng(string $raw): ?array
    {
        $b64   = preg_replace('#^data:image/png;base64,#i', '', trim($raw));
        $bytes = base64_decode($b64, true);
        if ($bytes === false || $bytes === '' || strlen($bytes) > self::MAX_IMAGE_BYTES
            || strncmp($bytes, "\x89PNG\r\n\x1a\n", 8) !== 0) {
            return null;
        }
        $info = @getimagesizefromstring($bytes);
        if (!$info || $info[2] !== IMAGETYPE_PNG || $info[0] < 10 || $info[1] < 10 || $info[0] > 8000 || $info[1] > 3000) {
            return null;
        }

        return ['base64' => base64_encode($bytes), 'width' => (int) $info[0], 'height' => (int) $info[1], 'bytes' => strlen($bytes)];
    }

    /**
     * A normalized layout → positioned parts for the shared composer
     * (DocumentBranding::composeHeaderHtml / docx-branding buildHeaderChildren),
     * in drawing order. Every part has x, y (units); images carry 'base64' and
     * render {width, height} (the chosen height at the image's own proportions);
     * titles carry text {…} and box_width.
     */
    public function toParts(array $layout): array
    {
        $parts = [];
        foreach ($layout['items'] as $item) {
            $part = ['kind' => $item['type'] === 'title' ? 'text' : 'image', 'x' => (float) $item['x'], 'y' => (float) $item['y']];

            if ($item['type'] === 'title') {
                if ($item['line1'] === '' && $item['line2'] === '') {
                    continue;   // an empty title prints nothing
                }
                $part['align']     = $item['align'];
                $part['box_width'] = (float) $item['width'];
                $part['text']      = [
                    'line1' => $item['line1'], 'line2' => $item['line2'], 'font' => 'Arial',
                    'line1_size_pt' => $item['size1'], 'line2_size_pt' => $item['size2'],
                    'line1_bold' => $item['bold1'], 'line2_bold' => false,
                    'line1_color' => '000000', 'line2_color' => '000000',
                ];
            } else {
                $png = $this->imageInfo($item);
                if ($png === null) {
                    continue;
                }
                $h = (float) $item['height'];
                $part['base64'] = $png['base64'];
                $part['render'] = ['width' => round($h * $png['width'] / $png['height'], 2), 'height' => $h];
            }
            $parts[] = $part;
        }

        return $parts;
    }
}
