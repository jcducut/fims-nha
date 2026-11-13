<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * Memo Request — the originating unit's MEMORANDUM to the General Manager asking for a
 * budget (FOR / THRU / SUBJECT, the request with its backgrounder, cost table and
 * supporting documents), which FMD certifies with "Certified Budget Allocation: Php …"
 * and "CBA# … / <fund>"; then the requesting head and FMD's certifying officer side by
 * side, "Recommending Approval:", and "Approved by:" (centered). Page 2 on carries
 * "Page X of Y" over a running title. Printed from the Memo Request form by every unit
 * except FMD (FMD prints the CBA / RFA / LAA / CFA).
 *
 * Model only: it prints through the shared memo layout (LaaRequestPrintService::html()
 * / scripts/gen_laareq_docx.js) with its optional parts — A4, the letterhead on page 1
 * only, item / table blocks, the certification, the signature rows — and the PDF's
 * running header (PrintService pdf_running_header).
 *
 * Printed through PrintService::print('MEMO', …).
 */
class MemoRequestPrintService
{
    // Section numbers ("I.", "II.", …) that start a heading line.
    private const ROMAN = '(?:XV|XIV|XIII|XII|XI|X|IX|VIII|VII|VI|V|IV|III|II|I)';

    /**
     * Model. Fixed on the form: its labels and wording (MEMORANDUM, FOR / THRU / SUBJECT,
     * "Certified Budget Allocation: Php", "CBA#", "Recommending Approval:", "Approved by:",
     * "Page X of Y"). Everything else prints only as entered — no defaults: the FOR / THRU /
     * SUBJECT rows always print, an empty value prints blank.
     * Payload (all optional):
     *   date (Y-m-d or text; blank → today, the print date), for_name / for_title,
     *   thru_name / thru_title, subject,
     *   body — the whole request as typed (see bodyBlocks()),
     *   amount (the certified budget allocation — blank → a blank line for FMD to fill),
     *   cba_number, fund_source_name (the certification's second line "CBA#… / <fund>";
     *     left out when both are blank),
     *   requested_by_name / _title (the originating unit's head — left signature),
     *   certified_by_name / _title (FMD's certifying officer — right signature),
     *   recommending [{ name, title }] ("Recommending Approval:"; none → left out),
     *   approved_by_name / _title ("Approved by:", centered),
     *   running_title (the header of page 2 on, under "Page X of Y"),
     *   memo_number (file name only). Titles: "\n" starts a new line.
     */
    public function model(array $p): array
    {
        $t      = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $run    = static fn (string $text, bool $bold = false, bool $underline = false) => ['text' => $text, 'bold' => $bold] + ($underline ? ['underline' => true] : []);
        $lines  = static fn ($v) => array_values(array_filter(array_map('trim', preg_split('/\R|\\\\n/', (string) ($v ?? ''))), 'strlen'));
        $signer = static fn (string $name, $title) => ['name' => PrintService::text($name), 'title_lines' => $lines($title)];
        $amount = PrintService::amount($p['amount'] ?? null);

        // FMD's certification: the fixed line always prints (blank → a line FMD fills in);
        // "CBA#… / <fund>" only as entered.
        $certification = [[
            $run('Certified Budget Allocation: Php '),
            $amount > 0 ? $run(PrintService::money($amount), false, true) : $run(str_repeat('_', 9)),   // fits after "Php" in the right half
        ]];
        $ref = implode(' / ', array_filter([$t('cba_number') !== '' ? 'CBA#' . $t('cba_number') : '', $t('fund_source_name')]));
        if ($ref !== '') {
            $certification[] = [$run($ref)];
        }

        // Signatures (as entered): requesting head + FMD's certifying officer; Recommending Approval; Approved by.
        $rows = [['label' => '', 'signers' => [
            $signer($t('requested_by_name'), $p['requested_by_title'] ?? ''),
            $signer($t('certified_by_name'), $p['certified_by_title'] ?? ''),
        ]]];
        $recommending = array_values(array_filter(
            array_map(static fn ($r) => $signer((string) ($r['name'] ?? ''), $r['title'] ?? ''), is_array($p['recommending'] ?? null) ? $p['recommending'] : []),
            static fn ($s) => $s['name'] !== '' || $s['title_lines']
        ));
        if ($recommending) {
            $rows[] = ['label' => 'Recommending Approval:', 'signers' => $recommending];
        }
        $rows[] = ['label' => 'Approved by:', 'center' => true, 'signers' => [$signer($t('approved_by_name'), $p['approved_by_title'] ?? '')]];

        $memoNo = $t('memo_number');

        return [
            'date'       => PrintService::longDate($p['date'] ?? ''),
            // FOR and THRU are fixed labels: both rows always print, their values only as entered.
            'rows'       => [
                ['label' => 'FOR',  'name' => $t('for_name'),  'title' => $t('for_title')],
                ['label' => 'THRU', 'name' => $t('thru_name'), 'title' => $t('thru_title')],
            ],
            'subject'    => mb_strtoupper($t('subject')),
            'paragraphs' => self::bodyBlocks((string) ($p['body'] ?? '')),
            'closing'    => '',
            'signatory'  => ['name' => '', 'title' => '', 'office' => ''],   // replaced by signature_rows
            'certification'  => $certification,
            'signature_rows' => $rows,
            'paper'          => 'A4',
            'letterhead_first_page_only' => true,
            // page 2 on: "Page X of Y" first, then the running title (upright, as on the form)
            'pdf_running_header' => ['lines' => $lines($p['running_title'] ?? ''), 'from_page' => 2, 'page_first' => true, 'italic' => false],
            'brand_document' => 'MEMO',
            'header_parts'   => null,
            'footer_text'    => '',
            'file_base'      => 'MEMO' . ($memoNo !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $memoNo) : ''),
        ];
    }

    /**
     * The typed request → the memo layout's blocks. One line = one block (blank lines only
     * separate):
     *   "I. Backgrounder"            a section heading (bold; the text at 0.5in)
     *   "a. The first-year …"        a lettered item (letter at 0.5in, text at 0.75in)
     *   "    This amendment …"       a line starting with spaces continues at the last
     *                                item's text indent
     *   "1. Summary | – Annex "A""   a numbered item; " | " puts the rest in a right column
     *   "| No. | Areas | … |"        consecutive lines starting with "|" are a table: the first
     *                                line is its header; a one-cell line spans the table; a
     *                                line with fewer cells is a total (its label spans the rest)
     *   anything else               a justified paragraph
     * "**text**" prints bold anywhere.
     */
    public static function bodyBlocks(string $typed): array
    {
        $src  = preg_split('/\R/u', str_replace("\t", '    ', $typed));
        $out  = [];
        $last = 0;   // the last item's text indent (pt), for continuation lines
        for ($i = 0, $n = count($src); $i < $n; $i++) {
            $raw  = rtrim($src[$i]);
            $line = PrintService::text($raw);
            if ($line === '') {
                continue;
            }
            if ($line[0] === '|') {   // a table: this and the following "|" lines
                $tbl = [];
                for (; $i < $n && str_starts_with(ltrim($src[$i]), '|'); $i++) {
                    $tbl[] = PrintService::text($src[$i]);
                }
                $i--;
                $out[] = self::table($tbl);
                $last  = 0;
                continue;
            }
            if (preg_match('/^(' . self::ROMAN . ')\.\s+(.+)$/u', $line, $m)) {
                $out[] = self::item($m[1] . '.', 0, 36, self::runs($m[2], true), true);
                $last  = 36;
            } elseif (preg_match('/^([a-z])\.\s+(.+)$/u', $line, $m)) {
                $out[] = self::item($m[1] . '.', 36, 54, self::runs($m[2]));
                $last  = 54;
            } elseif (preg_match('/^(\d{1,2})\.\s+(.+)$/u', $line, $m)) {
                [$text, $aside] = array_pad(array_map('trim', explode(' | ', $m[2], 2)), 2, '');
                $out[] = self::item($m[1] . '.', 9, 27, self::runs($text)) + ['aside' => $aside, 'aside_at' => 306, 'numbered' => true];
                $last  = 27;
            } elseif (preg_match('/^\s/u', $raw) && $last > 0) {
                $out[] = self::item('', $last, $last, self::runs($line));
            } else {
                $out[] = self::runs($line);
                $last  = 0;
            }
        }
        // A numbered list prints tight: only its last item keeps the paragraph space.
        foreach ($out as $k => $b) {
            if (!empty($b['numbered']) && !empty($out[$k + 1]['numbered'])) {
                $out[$k]['after'] = 3;
            }
        }

        return $out;
    }

    /** "a **b** c" → runs [a][b bold][c]. */
    private static function runs(string $text, bool $bold = false): array
    {
        $runs = [];
        foreach (explode('**', $text) as $k => $part) {
            if ($part !== '') {
                $runs[] = ['text' => $part, 'bold' => $bold || $k % 2 === 1];
            }
        }

        return $runs ?: [['text' => '', 'bold' => $bold]];
    }

    /** A hanging-indent block: label at label_at pt, the text at text_at pt. */
    private static function item(string $label, float $labelAt, float $textAt, array $runs, bool $labelBold = false): array
    {
        return ['block' => 'item', 'label' => $label, 'label_bold' => $labelBold, 'label_at' => $labelAt, 'text_at' => $textAt,
            'runs' => $runs, 'after' => 14];
    }

    /** "|"-lines → a table block: header, rows (row / group / total), column widths and alignment. */
    private static function table(array $lines): array
    {
        $cells  = static fn (string $l) => array_map('trim', explode('|', trim(trim($l), '|')));
        $header = $cells(array_shift($lines));
        $cols   = count($header);
        $rows   = [];
        foreach ($lines as $l) {
            $c = $cells($l);
            $k = count($c);
            if ($k >= $cols) {
                $rows[] = ['kind' => 'row', 'cells' => array_slice($c, 0, $cols)];
            } elseif ($k === 1) {
                $rows[] = ['kind' => 'group', 'cells' => $c];               // spans the table
            } else {
                $rows[] = ['kind' => 'total', 'cells' => $c, 'span' => $cols - $k + 1];   // label spans the rest
            }
        }

        // Alignment from the data cells: whole numbers centered, amounts right, text left.
        $align  = [];
        $weight = [];
        for ($j = 0; $j < $cols; $j++) {
            $vals = array_values(array_filter(array_map(static fn ($r) => $r['kind'] === 'row' ? str_replace('**', '', $r['cells'][$j]) : '', $rows), 'strlen'));
            $align[$j] = $vals && !array_filter($vals, static fn ($v) => !preg_match('/^\d+$/', $v)) ? 'center'
                : ($vals && !array_filter($vals, static fn ($v) => !preg_match('/^[\d,]+(\.\d+)?$/', $v)) ? 'right' : 'left');
            // width ~ the longest value (a header wraps, so only its longest word counts)
            $words      = preg_split('/\s+/', str_replace('**', '', $header[$j]));
            $weight[$j] = max(3, max(array_map('mb_strlen', $words)) + 1, ...array_map('mb_strlen', $vals ?: ['']));
        }
        $sum = array_sum($weight);

        return ['block' => 'table', 'header' => array_map([self::class, 'runs'], $header),
            'rows' => array_map(static fn ($r) => ['kind' => $r['kind'], 'span' => $r['span'] ?? 1,
                'cells' => array_map([self::class, 'runs'], $r['cells'])], $rows),
            'widths' => array_map(static fn ($w) => round($w / $sum, 4), $weight), 'align' => $align, 'after' => 14];
    }

    /** The shared memo layout. */
    public function html(array $m, string $media = 'screen'): string
    {
        return (new LaaRequestPrintService())->html($m, $media);
    }
}
