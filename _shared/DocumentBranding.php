<?php
/**
 * =============================================================================
 * FIMS Document Branding (PHP)
 * =============================================================================
 * The PHP twin of _shared/docx-branding.js.
 *
 * Documents leave FIMS by two routes:
 *
 *   Print  → Node generator → DOCX   (uses _shared/docx-branding.js)
 *   Preview→ Dompdf HTML    → PDF    (uses THIS class)
 *
 * Both must show the same letterhead, so both read the same mirror that UAC's
 * Header Management page publishes to _shared/branding/. Without this, the PDF
 * preview would keep rendering its own static assets/pdf/header.png while the
 * printed DOCX showed the managed one.
 *
 * The letterhead is four parts laid out in a row:
 *
 *   [ NHA logo ]  [ NATIONAL HOUSING AUTHORITY  ]  [ Pambansang ]  [ Bagong    ]
 *                 [ Build Better and More Housing]  [ Pabahay    ]  [ Pilipinas ]
 *      LOGO                   TITLE                    BRAND           SEAL
 *
 * Resolution order matches the DOCX side exactly:
 *   1. a whole-header image published by UAC
 *   2. the four composed parts (only when ALL of them are set)
 *   3. whatever the caller passes as a fallback (the old static PNG)
 *
 * No namespace — this is required directly by services in several systems,
 * the same way _shared/PagePermission.php is.
 *
 * @package GovFlow360
 * @subpackage shared
 * =============================================================================
 */

class DocumentBranding
{
    /** Where UAC publishes the active assets. */
    public const BRANDING_DIR = __DIR__ . DIRECTORY_SEPARATOR . 'branding';

    /** Read once per request — these are short-lived HTTP calls. */
    private static ?array $manifest = null;

    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }
        $path = self::BRANDING_DIR . DIRECTORY_SEPARATOR . 'manifest.json';
        if (!is_file($path)) {
            return self::$manifest = [];
        }
        $decoded = json_decode((string) @file_get_contents($path), true);
        return self::$manifest = is_array($decoded) ? $decoded : [];
    }

    /** Forget the cached manifest (used by tests and after a re-publish). */
    public static function reset(): void
    {
        self::$manifest = null;
    }

    // =========================================================================
    // PUBLISHING — keeping the disk mirror in step with the database
    // =========================================================================

    /**
     * The one query that defines what "published" means. Shared so UAC and the
     * consuming systems can never drift apart on it.
     */
    public const ACTIVE_ASSETS_SQL = "
        SELECT a.header_asset_id, a.asset_code, a.asset_name,
               a.scope_system_code, a.scope_document_code,
               a.mime_type, a.image_data, a.checksum_sha256,
               a.render_width, a.render_height, a.image_width, a.image_height,
               a.part_kind, a.column_pct, a.align,
               a.text_line1, a.text_line2, a.font_name,
               a.line1_size_pt, a.line2_size_pt,
               a.line1_bold, a.line2_bold, a.line1_color, a.line2_color,
               a.updated_at, t.type_code,
               s.slot_code, s.display_order,
               s.default_column_pct, s.default_align,
               s.default_render_width, s.default_render_height
          FROM uac.document_header_assets a
          JOIN uac.document_asset_types  t ON t.document_asset_type_id = a.document_asset_type_id
          LEFT JOIN uac.document_header_slots s ON s.header_slot_id = a.header_slot_id
         WHERE a.is_active = TRUE
         ORDER BY s.display_order NULLS FIRST";

    public const ACTIVE_SLOTS_SQL = "
        SELECT slot_code FROM uac.document_header_slots
         WHERE is_active = TRUE ORDER BY display_order";

    private const ALLOWED_MIME = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
    ];

    private static function slug(?string $v): string
    {
        if ($v === null || $v === '') {
            return 'all';
        }
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $v)) ?: 'all';
    }

    /**
     * Write the mirror (images + manifest.json) from rows shaped by
     * ACTIVE_ASSETS_SQL. This is the single implementation — UAC's
     * DocumentHeaderService and the print routes both come through here.
     *
     * @param array $rows      active assets
     * @param array $slotCodes every slot the letterhead expects
     * @return array published file names
     */
    public static function publish(array $rows, array $slotCodes = []): array
    {
        $dir = self::BRANDING_DIR;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return [];
        }

        $published = [];
        $manifest  = [
            'generated_at' => date('c'),
            'note'         => 'Written from uac.document_header_assets. Do not edit by hand.',
            'assets'       => [],
            'header_slots' => array_values($slotCodes),
            'header_parts' => [],
        ];

        foreach ($rows as $row) {
            $isPart = ($row['slot_code'] ?? null) !== null;

            if (($row['part_kind'] ?? 'image') === 'text') {
                $manifest['header_parts'][] = [
                    'slot'       => $row['slot_code'],
                    'kind'       => 'text',
                    'order'      => (int) ($row['display_order'] ?? 0),
                    'system'     => $row['scope_system_code'],
                    'document'   => $row['scope_document_code'],
                    'column_pct' => (float) ($row['column_pct'] ?? $row['default_column_pct'] ?? 25),
                    'align'      => $row['align'] ?? $row['default_align'] ?? 'center',
                    'text'       => [
                        'line1'         => $row['text_line1'],
                        'line2'         => $row['text_line2'],
                        'font'          => $row['font_name'] ?: 'Arial',
                        'line1_size_pt' => (float) ($row['line1_size_pt'] ?? 16),
                        'line2_size_pt' => (float) ($row['line2_size_pt'] ?? 9),
                        'line1_bold'    => self::truthy($row['line1_bold'] ?? true),
                        'line2_bold'    => self::truthy($row['line2_bold'] ?? false),
                        'line1_color'   => $row['line1_color'] ?: '000000',
                        'line2_color'   => $row['line2_color'] ?: '000000',
                    ],
                    'asset_code' => $row['asset_code'],
                    'asset_name' => $row['asset_name'],
                    'updated_at' => $row['updated_at'],
                ];
                continue;
            }

            $bytes = base64_decode((string) $row['image_data'], true);
            if ($bytes === false || $bytes === '') {
                continue;
            }

            $ext  = self::ALLOWED_MIME[$row['mime_type']] ?? 'png';
            $name = $isPart
                ? 'header-part-' . self::slug($row['slot_code']) . '-'
                    . self::slug($row['scope_system_code']) . '-'
                    . self::slug($row['scope_document_code']) . '.' . $ext
                : strtolower($row['type_code']) . '-'
                    . self::slug($row['scope_system_code']) . '-'
                    . self::slug($row['scope_document_code']) . '.' . $ext;

            if (@file_put_contents($dir . DIRECTORY_SEPARATOR . $name, $bytes) === false) {
                continue;
            }
            $published[] = $name;

            $common = [
                'system'     => $row['scope_system_code'],
                'document'   => $row['scope_document_code'],
                'file'       => $name,
                'mime'       => $row['mime_type'],
                'render'     => [
                    'width'  => (int) ($row['render_width']  ?: $row['default_render_width']  ?: 60),
                    'height' => (int) ($row['render_height'] ?: $row['default_render_height'] ?: 60),
                ],
                'intrinsic'  => [
                    'width'  => isset($row['image_width'])  ? (int) $row['image_width']  : null,
                    'height' => isset($row['image_height']) ? (int) $row['image_height'] : null,
                ],
                'asset_code' => $row['asset_code'],
                'asset_name' => $row['asset_name'],
                'checksum'   => $row['checksum_sha256'],
                'updated_at' => $row['updated_at'],
            ];

            if ($isPart) {
                $manifest['header_parts'][] = $common + [
                    'slot'       => $row['slot_code'],
                    'kind'       => 'image',
                    'order'      => (int) ($row['display_order'] ?? 0),
                    'column_pct' => (float) ($row['column_pct'] ?? $row['default_column_pct'] ?? 25),
                    'align'      => $row['align'] ?? $row['default_align'] ?? 'center',
                ];
            } else {
                $manifest['assets'][] = $common + ['type' => $row['type_code']];
            }
        }

        // Drop files from scopes that are no longer active.
        foreach ((scandir($dir) ?: []) as $name) {
            if ($name === '.' || $name === '..' || in_array($name, $published, true)) {
                continue;
            }
            if (preg_match('/^(header|footer)(-part)?-[a-z0-9\-]+-[a-z0-9\-]+\.(png|jpe?g)$/i', $name)
                && is_file($dir . DIRECTORY_SEPARATOR . $name)) {
                @unlink($dir . DIRECTORY_SEPARATOR . $name);
            }
        }

        @file_put_contents(
            $dir . DIRECTORY_SEPARATOR . 'manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        self::reset();
        return $published;
    }

    private static function truthy($v): bool
    {
        return $v === true || $v === 1 || $v === '1' || $v === 't' || $v === 'true';
    }

    /**
     * Rebuild the mirror if it has gone missing.
     *
     * The mirror lives outside version control, so a fresh deployment — or any
     * cleanup of _shared/branding/ — starts with an empty directory. Without
     * this, every document would silently revert to the letterhead compiled
     * into the generators until an administrator happened to press Re-publish.
     * The database is the source of truth; this makes the disk catch up.
     *
     * Cheap in the normal case: one is_file() check and nothing else.
     *
     * @param callable $fetchRows fn(string $sql): array — runs a query
     * @return bool whether a rebuild happened
     */
    public static function ensurePublished(callable $fetchRows): bool
    {
        if (is_file(self::BRANDING_DIR . DIRECTORY_SEPARATOR . 'manifest.json')) {
            return false;
        }

        try {
            $rows  = $fetchRows(self::ACTIVE_ASSETS_SQL) ?: [];
            if (!$rows) {
                return false;   // nothing configured — the fallback is correct
            }
            $slots = array_column($fetchRows(self::ACTIVE_SLOTS_SQL) ?: [], 'slot_code');
            self::publish($rows, $slots);
            return true;
        } catch (\Throwable $e) {
            // Never let branding break a print run.
            return false;
        }
    }

    private static function norm($v): ?string
    {
        return ($v === null || $v === '') ? null : strtoupper((string) $v);
    }

    /**
     * How well an entry matches the requested scope. -1 = does not apply.
     * Mirrors scopeScore() in docx-branding.js.
     */
    private static function scopeScore(array $entry, ?string $system, ?string $document): int
    {
        $eSys = self::norm($entry['system']   ?? null);
        $eDoc = self::norm($entry['document'] ?? null);

        if ($eSys !== null && $eSys !== $system)   return -1;
        if ($eDoc !== null && $eDoc !== $document) return -1;

        if ($eSys !== null && $eDoc !== null) return 4;
        if ($eDoc !== null) return 3;
        if ($eSys !== null) return 2;
        return 1;
    }

    private static function dataUri(string $file, ?string $mime = null): ?string
    {
        $path = self::BRANDING_DIR . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) {
            return null;
        }
        $bytes = @file_get_contents($path);
        if ($bytes === false || $bytes === '') {
            return null;
        }
        if (!$mime) {
            $mime = preg_match('/\.jpe?g$/i', $file) ? 'image/jpeg' : 'image/png';
        }
        // Dompdf runs with isRemoteEnabled=false, so images must be inlined.
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }

    /**
     * The whole-header (or footer) image published by UAC, as a data URI.
     *
     * @param string $type HEADER | FOOTER
     */
    public static function wholeImageSrc(string $type, ?string $system = null, ?string $document = null): ?string
    {
        $type     = self::norm($type) ?? 'HEADER';
        $system   = self::norm($system);
        $document = self::norm($document);

        $best = null;
        $bestScore = 0;
        foreach ((self::manifest()['assets'] ?? []) as $a) {
            if (self::norm($a['type'] ?? null) !== $type) continue;
            $score = self::scopeScore($a, $system, $document);
            if ($score > $bestScore) {
                $best = $a;
                $bestScore = $score;
            }
        }

        return $best && !empty($best['file'])
            ? self::dataUri($best['file'], $best['mime'] ?? null)
            : null;
    }

    /**
     * The four parts, left to right, best-scoped entry per slot.
     *
     * @return array<int,array> empty when nothing is published
     */
    public static function headerParts(?string $system = null, ?string $document = null): array
    {
        $system   = self::norm($system);
        $document = self::norm($document);

        $bySlot = [];
        foreach ((self::manifest()['header_parts'] ?? []) as $p) {
            $score = self::scopeScore($p, $system, $document);
            if ($score < 0) continue;
            $slot = self::norm($p['slot'] ?? null) ?? '';
            if (!isset($bySlot[$slot]) || $score > $bySlot[$slot]['_score']) {
                $bySlot[$slot] = $p + ['_score' => $score];
            }
        }

        $parts = array_values($bySlot);
        usort($parts, static fn ($a, $b) => ($a['order'] ?? 0) <=> ($b['order'] ?? 0));
        return $parts;
    }

    /**
     * Render the letterhead as HTML for Dompdf.
     *
     * @param float       $widthPt  total width in points (the PDFs use 468pt)
     * @param string|null $fallbackSrc  data URI of the caller's own static image
     * @return string  HTML — never empty unless there is nothing at all to show
     */
    public static function headerHtml(
        float $widthPt,
        ?string $fallbackSrc = null,
        ?string $system = null,
        ?string $document = null
    ): string {
        // ── 1. Whole-header image ───────────────────────────────────────────
        $whole = self::wholeImageSrc('HEADER', $system, $document);
        if ($whole !== null) {
            return '<img src="' . $whole . '" style="width:' . $widthPt . 'pt;" alt="">';
        }

        // ── 2. Composed parts, but only once every one of them is set ───────
        $parts    = self::headerParts($system, $document);
        $expected = count(self::manifest()['header_slots'] ?? []);
        $usable   = array_values(array_filter($parts, static function ($p) {
            return ($p['kind'] ?? '') === 'text'
                ? (($p['text']['line1'] ?? '') !== '' || ($p['text']['line2'] ?? '') !== '')
                : !empty($p['file']);
        }));

        // A half-configured letterhead would print as a lone line of text on an
        // official document — worse than the old banner. Same rule as the DOCX.
        if ($usable && ($expected === 0 || count($usable) >= $expected)) {
            return self::composeHeaderHtml($usable, $widthPt);
        }

        // ── 3. The caller's own static image ────────────────────────────────
        return $fallbackSrc
            ? '<img src="' . $fallbackSrc . '" style="width:' . $widthPt . 'pt;" alt="">'
            : '';
    }

    /**
     * Lay out letterhead parts in one row (the composed letterhead).
     *
     * Used by headerHtml() for the UAC-published parts, and directly by callers
     * that build the parts themselves (e.g. a per-print header edited in a
     * preview). Each part: kind 'text' with text{line1, line2, font, line{n}_size_pt,
     * line{n}_bold, line{n}_color}, or an image with either 'src' (data URI) or
     * 'file' (in the branding mirror) plus render{width, height} in px at a
     * 630 px wide letterhead; column_pct and align as in the manifest.
     *
     * @param array $parts    left to right
     * @param float $widthPt  total width in points
     */
    public static function composeHeaderHtml(array $parts, float $widthPt): string
    {
        $usable = array_values($parts);
        if (!$usable) {
            return '';
        }
        if (self::isPlaced($usable)) {
            return self::composePlacedHeaderHtml($usable, $widthPt);
        }

        $totalPct = 0.0;
        foreach ($usable as $p) {
            $totalPct += (float) ($p['column_pct'] ?? 0);
        }
        if ($totalPct <= 0) {
            $totalPct = count($usable);
        }

        // px -> pt, so the parts sit at the same relative size as in the DOCX.
        $scale = $widthPt / 630.0;

        $cells = '';
        foreach ($usable as $p) {
            $pct   = (float) ($p['column_pct'] ?? (100 / count($usable)));
            $wPct  = ($pct / $totalPct) * 100;
            $align = strtolower((string) ($p['align'] ?? 'center'));
            $align = in_array($align, ['left', 'right', 'center'], true) ? $align : 'center';

            if (($p['kind'] ?? '') === 'text') {
                $t    = $p['text'] ?? [];
                $font = htmlspecialchars((string) ($t['font'] ?? 'Arial'), ENT_QUOTES);
                $body = '';
                foreach ([1, 2] as $i) {
                    $line = (string) ($t["line{$i}"] ?? '');
                    if ($line === '') continue;
                    $size  = (float) ($t["line{$i}_size_pt"] ?? ($i === 1 ? 16 : 9)) * $scale;
                    $bold  = !empty($t["line{$i}_bold"]) ? 'bold' : 'normal';
                    $color = preg_replace('/[^0-9A-Fa-f]/', '', (string) ($t["line{$i}_color"] ?? '000000'));
                    $color = strlen($color) === 6 ? $color : '000000';
                    $body .= '<div style="font-family:' . $font . ',sans-serif;'
                           . 'font-size:' . round($size, 2) . 'pt;font-weight:' . $bold . ';'
                           . 'color:#' . $color . ';line-height:1.15;">'
                           . htmlspecialchars($line, ENT_QUOTES) . '</div>';
                }
                $inner = $body;
            } else {
                // 'src' = data URI handed in by the caller; 'file' = mirror file.
                $src = isset($p['src'])
                    ? (preg_match('#^data:image/(png|jpe?g);base64,[A-Za-z0-9+/=]+$#', (string) $p['src']) ? $p['src'] : null)
                    : (!empty($p['file']) ? self::dataUri($p['file'], $p['mime'] ?? null) : null);
                if ($src === null) {
                    $inner = '';
                } else {
                    $w = (float) ($p['render']['width']  ?? 60) * $scale;
                    $h = (float) ($p['render']['height'] ?? 60) * $scale;
                    $inner = '<img src="' . $src . '" style="width:' . round($w, 2) . 'pt;'
                           . 'height:' . round($h, 2) . 'pt;" alt="">';
                }
            }

            $cells .= '<td style="width:' . round($wPct, 3) . '%;text-align:' . $align . ';'
                    . 'vertical-align:middle;padding:0 2pt;">' . $inner . '</td>';
        }

        return '<table style="width:' . $widthPt . 'pt;border-collapse:collapse;'
             . 'margin:0 auto;border:0;"><tr>' . $cells . '</tr></table>';
    }

    /**
     * True when every part carries a position (x, y) — a freely placed
     * letterhead instead of a row of columns.
     */
    public static function isPlaced(array $parts): bool
    {
        foreach ($parts as $p) {
            if (!is_numeric($p['x'] ?? null) || !is_numeric($p['y'] ?? null)) {
                return false;
            }
        }

        return (bool) $parts;
    }

    /** A placed part's [width, height] in px at a 630 px letterhead. */
    public static function placedSize(array $p): array
    {
        if (($p['kind'] ?? '') === 'text') {
            $t = $p['text'] ?? [];
            $h = 0.0;
            foreach ([1, 2] as $i) {
                if ((string) ($t["line{$i}"] ?? '') !== '') {
                    $h += (float) ($t["line{$i}_size_pt"] ?? ($i === 1 ? 16 : 9)) * 1.15;
                }
            }

            return [(float) ($p['box_width'] ?? 300), $h];
        }

        return [(float) ($p['render']['width'] ?? 60), (float) ($p['render']['height'] ?? 60)];
    }

    /** Height (px at a 630 px letterhead) a placed letterhead needs: its lowest bottom edge. */
    public static function placedHeight(array $parts): float
    {
        $h = 0.0;
        foreach ($parts as $p) {
            $h = max($h, (float) $p['y'] + self::placedSize($p)[1]);
        }

        return $h;
    }

    /**
     * Placed parts → a box $widthPt wide, each part absolutely positioned at
     * its x / y (px at a 630 px letterhead, scaled to $widthPt). Titles sit in
     * a box_width wide box with their align; lines never wrap, so the height
     * is exactly placedHeight().
     */
    private static function composePlacedHeaderHtml(array $parts, float $widthPt): string
    {
        $scale = $widthPt / 630.0;
        $pt    = static fn (float $v): string => round($v * $scale, 2) . 'pt';
        $items = '';

        foreach ($parts as $p) {
            $style = 'position:absolute;left:' . $pt((float) $p['x']) . ';top:' . $pt((float) $p['y']) . ';';

            if (($p['kind'] ?? '') === 'text') {
                $t     = $p['text'] ?? [];
                $font  = htmlspecialchars((string) ($t['font'] ?? 'Arial'), ENT_QUOTES);
                $align = in_array($p['align'] ?? '', ['left', 'right', 'center'], true) ? $p['align'] : 'center';
                $body  = '';
                foreach ([1, 2] as $i) {
                    $line = (string) ($t["line{$i}"] ?? '');
                    if ($line === '') continue;
                    $size  = (float) ($t["line{$i}_size_pt"] ?? ($i === 1 ? 16 : 9)) * $scale;
                    $bold  = !empty($t["line{$i}_bold"]) ? 'bold' : 'normal';
                    $color = preg_replace('/[^0-9A-Fa-f]/', '', (string) ($t["line{$i}_color"] ?? '000000'));
                    $color = strlen($color) === 6 ? $color : '000000';
                    $body .= '<div style="font-family:' . $font . ',sans-serif;'
                           . 'font-size:' . round($size, 2) . 'pt;font-weight:' . $bold . ';'
                           . 'color:#' . $color . ';line-height:1.15;white-space:nowrap;">'
                           . htmlspecialchars($line, ENT_QUOTES) . '</div>';
                }
                $items .= '<div style="' . $style . 'width:' . $pt((float) ($p['box_width'] ?? 300)) . ';'
                        . 'text-align:' . $align . ';">' . $body . '</div>';
                continue;
            }

            $src = isset($p['src'])
                ? (preg_match('#^data:image/(png|jpe?g);base64,[A-Za-z0-9+/=]+$#', (string) $p['src']) ? $p['src'] : null)
                : (!empty($p['file']) ? self::dataUri($p['file'], $p['mime'] ?? null) : null);
            if ($src === null) {
                continue;
            }
            [$w, $h] = self::placedSize($p);
            $items  .= '<div style="' . $style . '"><img src="' . $src . '" style="display:block;'
                     . 'width:' . $pt($w) . ';height:' . $pt($h) . ';" alt=""></div>';
        }

        return '<div style="position:relative;width:' . $widthPt . 'pt;height:'
             . $pt(self::placedHeight($parts)) . ';margin:0 auto;">' . $items . '</div>';
    }

    /**
     * Footer HTML, for symmetry. Footers are a single image today.
     */
    public static function footerHtml(
        float $widthPt,
        ?string $fallbackSrc = null,
        ?string $system = null,
        ?string $document = null
    ): string {
        $src = self::wholeImageSrc('FOOTER', $system, $document) ?? $fallbackSrc;
        return $src
            ? '<img src="' . $src . '" style="width:' . $widthPt . 'pt;" alt="">'
            : '';
    }
}
