<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

require_once dirname(__DIR__, 2) . '/_shared/DocumentBranding.php';

/**
 * Printed forms — the shared print pipeline for every form (CBA, CAF, RFA, LAA, …).
 *
 * Each form is its own service with the wording and layout: model() (every
 * printed word, from the payload) and html() (preview + PDF) — CbaPrintService,
 * CafPrintService, RfaPrintService, LaaPrintService. They are registered in self::DOCS; this class
 * runs them and renders the result, so the preview, PDF and DOCX can never drift:
 *   html → <Form>PrintService::html()              shown in the print preview
 *                                                   (app/pages/readone/print-preview-readone.php)
 *   pdf  → the same HTML rendered by Dompdf
 *   docx → scripts/gen_<form>_docx.js (Node + the docx npm package), from the model
 *
 * The letterhead (header items + footer line) comes from LetterheadLayoutService
 * (edited per print in the preview, or the saved default), else the built-in banner
 * via _shared/DocumentBranding.php / _shared/docx-branding.js.
 *
 * The static helpers below (money, amountInWords, signatory lookup, header CSS, …)
 * are shared by the form services — called as PrintService::money(...) (not
 * inherited: services load alphabetically, so a base class would load too late).
 *
 * Routes: app/routes/print.php. Docs: system_docs/modules/PRINT-FORMS.md.
 */
class PrintService
{
    public const BRAND_SYSTEM   = 'FMD';

    // Built-in letterhead banner (under _shared/images/), printed when no layout
    // is edited or saved; scripts/gen_*_docx.js read the same file.
    private const BANNER = 'letterhead/nha-letterhead-banner.png';
    // Roles the resolve-signatory endpoint may look up.
    public const SIGNATORY_ROLES = ['FMD Department Manager'];
    public const FORMATS = ['html', 'pdf', 'docx'];
    // Printed when nobody holds the FMD Department Manager role (old FMD default).
    private const DEFAULT_SIGNATORY_NAME  = 'CYNTHIA FLORENCE T. CARIAGA';
    private const DEFAULT_SIGNATORY_TITLE = "Department Manager\nFinancial Management Department";
    private const MIME = [
        'pdf'  => 'application/pdf',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    // Printed forms: the service that builds each one (model() + html()), its DOCX
    // generator (scripts/), paper (+ 'orientation', default portrait) and PDF font.
    // Add a new form here.
    public const DOCS = [
        'CBA' => ['service' => CbaPrintService::class, 'script' => 'gen_cba_docx.js', 'paper' => 'A4',    'font' => 'Times-Roman'],
        'CAF' => ['service' => CafPrintService::class, 'script' => 'gen_cba_docx.js', 'paper' => 'A4',    'font' => 'Times-Roman'],   // the CBA's layout
        'RFA' => ['service' => RfaPrintService::class, 'script' => 'gen_rfa_docx.js', 'paper' => 'legal', 'font' => 'Helvetica'],
        'LAA' => ['service' => LaaPrintService::class, 'script' => 'gen_laa_docx.js', 'paper' => 'A4',    'font' => 'Helvetica'],
        'LAAREQ' => ['service' => LaaRequestPrintService::class, 'script' => 'gen_laareq_docx.js', 'paper' => 'letter', 'font' => 'Helvetica'],   // regional memo
        'PO'  => ['service' => PurchaseOrderPrintService::class, 'script' => 'gen_po_docx.js', 'paper' => 'legal', 'font' => 'Times-Roman'],
        'PR'  => ['service' => PurchaseRequestPrintService::class, 'script' => 'gen_pr_docx.js', 'paper' => 'legal', 'font' => 'Times-Roman'],
        'IAR' => ['service' => IarPrintService::class, 'script' => 'gen_iar_docx.js', 'paper' => 'letter', 'font' => 'Times-Roman'],
        'LV'  => ['service' => LvPrintService::class, 'script' => 'gen_lv_docx.js', 'paper' => 'legal', 'font' => 'Times-Roman'],
        'JEV' => ['service' => JevPrintService::class, 'script' => 'gen_jev_docx.js', 'paper' => 'A4', 'orientation' => 'landscape', 'font' => 'Helvetica'],
        'FTLA' => ['service' => FtlaPrintService::class, 'script' => 'gen_ftla_docx.js', 'paper' => 'A4', 'font' => 'Helvetica'],
        'FTLAREQ' => ['service' => FtlaRequestPrintService::class, 'script' => 'gen_laareq_docx.js', 'paper' => 'letter', 'font' => 'Helvetica'],   // the LAA release request's memo layout
        'CFA' => ['service' => CfaPrintService::class, 'script' => 'gen_laareq_docx.js', 'paper' => 'A4', 'font' => 'Helvetica'],   // funding-request memo, the shared memo layout
        'MEMO' => ['service' => MemoRequestPrintService::class, 'script' => 'gen_laareq_docx.js', 'paper' => 'A4', 'font' => 'Helvetica'],   // the originating unit's memo request (non-FMD), the shared memo layout        // PDF-only forms ('pdf_only'): an official PDF form filled by the service's own
        // pdf() — no HTML / DOCX layout and no letterhead (the form has its own).
        'DV'  => ['service' => DvPrintService::class, 'pdf_only' => true],
    ];

    public static function isDocument(string $doc): bool
    {
        return isset(self::DOCS[strtoupper($doc)]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Entry point
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Print one form from a payload: letterhead → model → html | pdf | docx.
     *
     * $doc     CBA | CAF | RFA | LAA (self::DOCS); payload fields are documented
     *          on each form's model() (CbaPrintService, CafPrintService, RfaPrintService,
     *          LaaPrintService).
     * Letterhead: payload `letterhead` (the header items + footer_text as edited in
     *          the print preview — THIS print only), else the saved default for the
     *          document (fims.letterhead_layout), else the built-in NHA banner.
     *
     * Returns ['success' => true, 'html'] for html, or
     * ['success' => true, 'filename', 'mime', 'data' => base64] for pdf / docx.
     */
    public function print(string $doc, array $payload, string $format): array
    {
        $doc    = strtoupper(trim($doc));
        $format = strtolower(trim($format));
        if (!self::isDocument($doc)) {
            return ['success' => false, 'message' => 'Unknown document: ' . $doc . '.', 'httpCode' => 404];
        }
        if (!in_array($format, self::FORMATS, true)) {
            return ['success' => false, 'message' => 'format must be html, pdf or docx.', 'httpCode' => 400];
        }

        $cfg = self::DOCS[$doc];
        if (!empty($cfg['pdf_only']) && $format !== 'pdf') {
            return ['success' => false, 'message' => 'The ' . $doc . ' prints as PDF only.', 'httpCode' => 400];
        }
        try {
            $model = $this->model($doc, $payload);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'httpCode' => 400];   // invalid letterhead
        }

        if (!empty($cfg['pdf_only'])) {
            $bytes = (new $cfg['service']())->pdf($model);

            return $bytes === null
                ? ['success' => false, 'message' => 'PDF generation failed.', 'httpCode' => 500]
                : ['success' => true, 'filename' => $model['file_base'] . '.pdf', 'mime' => self::MIME['pdf'], 'data' => base64_encode($bytes)];
        }

        if ($format === 'html') {
            return ['success' => true, 'html' => $this->html($doc, $model, 'screen')];
        }

        $bytes = $format === 'pdf'
            ? self::renderPdf($this->html($doc, $model, 'print'), $cfg['paper'], $cfg['font'], $cfg['orientation'] ?? 'portrait',
                $model['pdf_running_header'] ?? null)
            : self::renderDocx($cfg['script'], $model);
        if ($bytes === null) {
            return ['success' => false, 'message' => strtoupper($format) . ' generation failed.', 'httpCode' => 500];
        }

        return [
            'success'  => true,
            'filename' => $model['file_base'] . '.' . $format,
            'mime'     => self::MIME[$format],
            'data'     => base64_encode($bytes),
        ];
    }

    /**
     * The form's model with its letterhead: header_parts (null → the built-in
     * banner) and footer_text. This is also what the DOCX generator receives.
     * Throws InvalidArgumentException for an invalid payload `letterhead`.
     */
    public function model(string $doc, array $payload): array
    {
        $doc = strtoupper($doc);
        $cfg = self::DOCS[$doc];
        if (!empty($cfg['pdf_only'])) {
            return (new $cfg['service']())->model($payload);   // official form — no letterhead
        }

        $model = (new $cfg['service']())->model($payload);

        // Forms with their own printed title block (PO, IAR, LV, JEV — not in
        // LetterheadLayoutService::DOCUMENTS): no header, no footer, not editable.
        if (!LetterheadLayoutService::isDocument($doc)) {
            $model['header_parts'] = [];
            $model['footer_text']  = '';

            return $model;
        }

        $letterheads = new LetterheadLayoutService();
        $layout = is_array($payload['letterhead'] ?? null)
            ? $letterheads->normalize($payload['letterhead'])
            : $letterheads->saved($doc);

        // Rebuild the branding mirror from the DB if it's missing (fresh deploy).
        \DocumentBranding::ensurePublished(fn (string $sql) => App\ExecuteRows($sql, 'DB'));

        $model['header_parts'] = $layout ? $letterheads->toParts($layout) : null;
        $model['footer_text']  = LetterheadLayoutService::hasFooter($doc)
            ? ($layout['footer_text'] ?? LetterheadLayoutService::DEFAULT_FOOTER_TEXT)
            : '';

        return $model;
    }

    /** The form's HTML: $media 'screen' (preview sheet) or 'print' (the page Dompdf renders). */
    public function html(string $doc, array $model, string $media = 'screen'): string
    {
        $cfg = self::DOCS[strtoupper($doc)];

        // Typed text can hold a long run with no spaces (a reference, a URL, test input): the form's
        // textarea wraps it anywhere, but HTML / Dompdf only break at spaces, so it would run off the
        // page. Every form may break such a word at the edge (Word already does in the DOCX).
        return str_replace('</style>', self::WRAP_LONG_WORDS_CSS . '</style>', (new $cfg['service']())->html($model, $media));
    }

    private const WRAP_LONG_WORDS_CSS = 'body,p,div,td,th,span{word-wrap:break-word;overflow-wrap:break-word;}';

    // ═════════════════════════════════════════════════════════════════════════
    // Shared helpers — used by every form (CbaPrintService, RfaPrintService, …)
    // ═════════════════════════════════════════════════════════════════════════

    public static function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function text(mixed $v): string
    {
        return trim(preg_replace('/[\x00-\x09\x0B-\x1F\x7F]+/u', ' ', (string) ($v ?? '')) ?? '');
    }

    /** "2026-03-04" → "March 4, 2026"; anything else is printed as given; blank → today. */
    public static function longDate(mixed $v): string
    {
        $v = self::text($v);
        if ($v === '') {
            return date('F j, Y');
        }
        $d = \DateTime::createFromFormat('Y-m-d', substr($v, 0, 10));

        return $d && $d->format('Y-m-d') === substr($v, 0, 10) ? $d->format('F j, Y') : $v;
    }

    /** ['name', 'title_lines'[]] — "NAME — Title" / "NAME - Title", else the FMD Department Manager. */
    public static function signatory(mixed $raw): array
    {
        $raw   = trim((string) $raw);
        $name  = '';
        $title = self::DEFAULT_SIGNATORY_TITLE;

        if ($raw !== '' && !str_starts_with($raw, 'Select')) {
            foreach ([' — ', ' - '] as $sep) {
                $pos = mb_strpos($raw, $sep);
                if ($pos !== false) {
                    $name  = trim(mb_substr($raw, 0, $pos));
                    $title = trim(mb_substr($raw, $pos + mb_strlen($sep)));
                    break;
                }
            }
            if ($name === '') {
                $name = $raw;
            }
        }

        if ($name === '') {
            $name = self::resolveSignatoryName('FMD Department Manager') ?? self::DEFAULT_SIGNATORY_NAME;
        }

        return ['name' => $name, 'title_lines' => preg_split('/\R/', $title)];
    }

    public static function amount(mixed $v): float
    {
        return (float) str_replace(',', '', (string) ($v ?? ''));
    }

    public static function money(float $v): string
    {
        return number_format($v, 2);
    }

    /** "One Million Two Hundred Thousand and 50/100 Pesos" — same algorithm as the old generators. */
    public static function amountInWords(float $num): string
    {
        $ones  = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
            'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens  = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $tiers = ['', 'Thousand', 'Million', 'Billion'];

        $below = function (int $n) use (&$below, $ones, $tens): string {
            if ($n === 0) return '';
            if ($n < 20) return $ones[$n];
            if ($n < 100) return $tens[intdiv($n, 10)] . ($n % 10 ? ' ' . $ones[$n % 10] : '');
            return $ones[intdiv($n, 100)] . ' Hundred' . ($n % 100 ? ' ' . $below($n % 100) : '');
        };

        $totalCents = (int) round(abs($num) * 100);
        $n          = intdiv($totalCents, 100);
        $cents      = $totalCents % 100;

        $parts = [];
        $tier  = 0;
        if ($n === 0) {
            $parts[] = 'Zero';
        }
        while ($n > 0) {
            $chunk = $n % 1000;
            if ($chunk !== 0) {
                array_unshift($parts, $below($chunk) . ($tiers[$tier] ? ' ' . $tiers[$tier] : ''));
            }
            $n = intdiv($n, 1000);
            $tier++;
        }

        return implode(' ', $parts) . ($cents > 0 ? ' and ' . $cents . '/100' : '') . ' Pesos';
    }

    /** "Fourteen Thousand Fifty Pesos and Fifty Centavos Only" — the LV / FTLA wording (centavos in words). */
    public static function amountInWordsCentavos(float $amount): string
    {
        $totalCents = (int) round(abs($amount) * 100);
        $pesos      = intdiv($totalCents, 100);
        $cents      = $totalCents % 100;
        $words      = static fn (int $n) => preg_replace('/\s+Pesos$/', '', self::amountInWords((float) $n));

        return $words($pesos) . ' Pesos' . ($cents > 0 ? ' and ' . $words($cents) . ' Centavos' : '') . ' Only';
    }

    /**
     * First user (lowest user_id) holding the uac.user_levels role $roleName, as
     * "FIRST M. LAST[, SUFFIX]" in uppercase. Null when the role or a holder
     * can't be found. uac.users.user_level_id is a PHPMaker comma-separated
     * VARCHAR, so the id is matched as a whole token ('4' never matches '14').
     */
    public static function resolveSignatoryName(string $roleName): ?string
    {
        try {
            $levelId = App\ExecuteScalar(
                'SELECT user_level_id FROM uac.user_levels WHERE name = '
                    . App\QuotedValue($roleName, App\DataType::STRING, 'DB') . ' LIMIT 1',
                'DB'
            );
            if ($levelId === null || $levelId === false) {
                return null;
            }

            $user = App\ExecuteRow(
                'SELECT first_name, middle_name, last_name, suffix
                   FROM uac.users
                  WHERE ' . App\QuotedValue((string) (int) $levelId, App\DataType::STRING, 'DB') . " = ANY(string_to_array(user_level_id, ','))
                  ORDER BY user_id ASC
                  LIMIT 1",
                'DB'
            );
            if (!$user) {
                return null;
            }

            $parts = [];
            if (trim((string) $user['first_name']) !== '') {
                $parts[] = trim($user['first_name']);
            }
            if (trim((string) $user['middle_name']) !== '') {
                $parts[] = mb_substr(trim($user['middle_name']), 0, 1) . '.';
            }
            if (trim((string) $user['last_name']) !== '') {
                $parts[] = trim($user['last_name']);
            }
            $name = implode(' ', $parts);
            if ($name === '') {
                return null;
            }
            if (trim((string) $user['suffix']) !== '') {
                $name .= ', ' . trim($user['suffix']);
            }

            return mb_strtoupper($name);
        } catch (\Throwable $e) {
            App\LogError('PrintService::resolveSignatoryName ' . $e->getMessage());

            return null;
        }
    }

    private static function imageDataUri(string $file): ?string
    {
        $path = self::repoRoot() . '/_shared/images/' . $file;

        return is_file($path) ? 'data:image/png;base64,' . base64_encode((string) file_get_contents($path)) : null;
    }

    /**
     * The letterhead's header as HTML, $widthPt wide: the layout's items
     * (edited for this print, or the saved default — an empty list prints no
     * header), else the built-in banner (UAC Header Management → self::BANNER).
     */
    public static function headerHtml(array $m, float $widthPt, string $document): string
    {
        if ($m['header_parts'] !== null) {
            return \DocumentBranding::composeHeaderHtml(array_map(static function (array $p) {
                if (isset($p['base64'])) {
                    $p['src'] = 'data:image/png;base64,' . $p['base64'];
                    unset($p['base64']);
                }
                return $p;
            }, $m['header_parts']), $widthPt);
        }

        return \DocumentBranding::headerHtml($widthPt, self::imageDataUri(self::BANNER), self::BRAND_SYSTEM, $document);
    }

    /**
     * Printed height (pt) of the header at $widthPt: the tallest layout item,
     * else the built-in banner capped at $maxPt (as its CSS max-height does).
     */
    private static function headerHeightPt(array $m, float $widthPt, float $maxPt): float
    {
        if ($m['header_parts'] !== null && \DocumentBranding::isPlaced($m['header_parts'])) {
            $h = \DocumentBranding::placedHeight($m['header_parts']) * $widthPt / 630;

            return $h > 0 ? ceil($h + 2) : 0.0;
        }
        if ($m['header_parts'] !== null) {
            $k = $widthPt / 630;   // layout sizes are given for a 630 px letterhead
            $h = 0.0;
            foreach ($m['header_parts'] as $p) {
                if (($p['kind'] ?? '') === 'text') {
                    $t  = $p['text'] ?? [];
                    $th = 0.0;
                    foreach ([1, 2] as $i) {
                        if (($t["line{$i}"] ?? '') !== '') {
                            $th += (float) ($t["line{$i}_size_pt"] ?? 11) * $k * 1.15;
                        }
                    }
                    $h = max($h, $th);
                } else {
                    $h = max($h, (float) ($p['render']['height'] ?? 0) * $k);
                }
            }
            return $h > 0 ? ceil($h + 2) : 0.0;
        }
        $info = @getimagesize(self::repoRoot() . '/_shared/images/' . self::BANNER);

        return ceil(min($info ? $widthPt * $info[1] / $info[0] : 70.0, $maxPt));
    }

    /**
     * PDF only: the header is pinned to the top of EVERY page (Dompdf repeats
     * position:fixed elements), like a Word header — the DOCX header repeats too.
     * Returns [@page top margin (pt), CSS] — the margin reserves the header's
     * height + gap, so page 1 looks as before and later pages keep the letterhead.
     */
    public static function repeatingHeaderCss(array $m, float $widthPt, float $maxPt, float $topPt, float $gapPt): array
    {
        $h = self::headerHeightPt($m, $widthPt, $maxPt);
        if ($h <= 0) {
            return [$topPt, '.hdr{display:none;}'];
        }

        return [$topPt + $h + $gapPt,
            '.hdr{position:fixed;left:0;right:0;top:-' . ($h + $gapPt) . 'pt;height:' . $h . 'pt;margin:0 !important;overflow:hidden;}'];
    }

    /**
     * Page body open / close. For the PDF ('print') the header and footer go
     * straight into <body>: Dompdf repeats position:fixed elements on every page
     * only when they are not inside a block that spans pages. For the on-screen
     * preview they sit inside the sheet (the letterhead designer patches `.hdr`).
     */
    public static function bodyOpen(string $media, string $sheetClass, string $hdrDiv, string $ftrDiv): string
    {
        return $media === 'print'
            ? '<body>' . $hdrDiv . $ftrDiv . '<div class="' . $sheetClass . '">'
            : '<body><div class="' . $sheetClass . '">' . $hdrDiv;
    }

    public static function bodyClose(string $media, string $ftrDiv): string
    {
        return ($media === 'print' ? '' : $ftrDiv) . '</div></body></html>';
    }

    /**
     * $runningHeader (a model's pdf_running_header, optional): { lines: [text, …],
     * from_page (default 2), right_pt (the right text edge, default 72pt in),
     * page_first (default false), italic (default true) } — lines at the top right of
     * each page from from_page on, followed by "Page X of Y" (the CFA memo's page-2
     * header) — or led by it with page_first (the memo request's). Drawn after layout
     * (Dompdf page_script) because the page count is only known then.
     */
    private static function renderPdf(string $html, string $paper = 'A4', string $font = 'Times-Roman', string $orientation = 'portrait',
        ?array $runningHeader = null): ?string
    {
        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', false);   // images are inline data URIs
            $options->set('defaultMediaType', 'print');
            $options->set('defaultFont', $font);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper($paper, $orientation);
            $dompdf->render();

            if (is_array($runningHeader)) {   // "Page X of Y" prints even when there are no title lines
                $fromPage = (int) ($runningHeader['from_page'] ?? 2);
                $rightPt  = (float) ($runningHeader['right_pt'] ?? 72);
                $lines    = array_values($runningHeader['lines'] ?? []);
                $pageFirst = !empty($runningHeader['page_first']);
                $style    = ($runningHeader['italic'] ?? true) ? 'italic' : 'normal';
                $dompdf->getCanvas()->page_script(static function (int $page, int $pages, $canvas, $fontMetrics) use ($fromPage, $rightPt, $lines, $pageFirst, $style) {
                    if ($page < $fromPage) {
                        return;
                    }
                    $font = $fontMetrics->getFont('Helvetica', $style);
                    $size = 9;
                    $y    = 30;
                    $no   = ['Page ' . $page . ' of ' . $pages];
                    foreach ($pageFirst ? array_merge($no, $lines) : array_merge($lines, $no) as $text) {
                        $width = $fontMetrics->getTextWidth($text, $font, $size);
                        $canvas->text($canvas->get_width() - $rightPt - $width, $y, $text, $font, $size);
                        $y += $size + 2;
                    }
                });
            }

            return $dompdf->output();
        } catch (\Throwable $e) {
            App\LogError('PrintService::renderPdf ' . $e->getMessage());

            return null;
        }
    }

    private static function nodeBin(): string
    {
        foreach (['/usr/bin/node', '/usr/local/bin/node'] as $bin) {
            if (is_file($bin)) {
                return $bin;
            }
        }

        return 'node'; // PATH (local dev)
    }

    /** Runs `node scripts/<generator> <model.json> <out.docx>`; null on failure. */
    private static function renderDocx(string $generator, array $model): ?string
    {
        $script = self::repoRoot() . '/scripts/' . $generator;
        if (!is_file($script)) {
            App\LogError('PrintService: generator not found: ' . $script);

            return null;
        }

        $tmp      = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'print_' . bin2hex(random_bytes(8));
        $jsonPath = $tmp . '.json';
        $docxPath = $tmp . '.docx';

        try {
            if (file_put_contents($jsonPath, json_encode($model, JSON_UNESCAPED_UNICODE)) === false) {
                App\LogError('PrintService: cannot write ' . $jsonPath);

                return null;
            }

            $cmd = escapeshellcmd(self::nodeBin()) . ' ' . escapeshellarg($script)
                . ' ' . escapeshellarg($jsonPath) . ' ' . escapeshellarg($docxPath) . ' 2>&1';
            exec($cmd, $output, $exitCode);

            if ($exitCode !== 0 || !is_file($docxPath)) {
                App\LogError("PrintService: {$generator} exited {$exitCode}: " . implode("\n", $output));

                return null;
            }

            $bytes = file_get_contents($docxPath);

            return ($bytes === false || $bytes === '') ? null : $bytes;
        } finally {
            @unlink($jsonPath);
            @unlink($docxPath);
        }
    }
}
