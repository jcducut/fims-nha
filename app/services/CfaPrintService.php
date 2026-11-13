<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * CFA (Certified Funds Available) — the requesting office's funding-request
 * MEMORANDUM that FMD certifies: FOR / THRU / SUBJECT, the request (free text),
 * "Certified Funds Available: Php …" + "RFA# … / <fund source>", the requesting
 * department manager and the FMD Department Manager side by side, and on the next
 * page "Recommending Approval:" and "Approved:" (the General Manager), under a
 * running title with "Page X of Y". A separate document from the CAF (Certificate
 * as to Availability of Funds, CafPrintService). Printed from the Memo Request
 * form (allocation type CFA, COE only).
 *
 * Model only: it prints through the shared memo layout (LaaRequestPrintService::html()
 * / scripts/gen_laareq_docx.js) with its optional parts — A4, the letterhead on page 1
 * only, the indented list, the certification, the signature rows — and the PDF's
 * running header (PrintService pdf_running_header).
 *
 * Printed through PrintService::print('CFA', …).
 */
class CfaPrintService
{
    /**
     * Model. Fixed on the form: its labels and wording (MEMORANDUM, FOR / THRU / SUBJECT,
     * the "Main Office :" / "Regional Offices :" list, "Certified Funds Available:",
     * "RFA# …", "Recommending Approval:", "Approved:", "Page X of Y"). Everything else
     * prints only as entered — no defaults: the FOR / THRU / SUBJECT rows and the Main
     * Office / Regional Offices list always print, an empty value prints blank.
     * Payload (all optional):
     *   date (Y-m-d or text; blank → today, the print date), for_name / for_title,
     *   thru_name / thru_title, subject,
     *   body — the request before the list, as typed: every line is a paragraph (blank
     *     lines only separate); other consecutive "Label : value" lines print as an
     *     indented list too; or paragraphs [text, …],
     *   main_office, regional_offices — the values of the fixed list (who prepares /
     *     processes / releases it),
     *   body_after — the paragraphs after the list (e.g. the cost and the closing),
     *     typed the same way,
     *   amount (the certified funds available), rfa_number, fund_source_name (the
     *     certification's second line "RFA# … / <fund source>"),
     *   requested_by_name / _title (the requesting office's head — left signature),
     *   certified_by_name / _title (FMD's certifying officer — right signature),
     *   recommending [{ name, title }] ("Recommending Approval:"; none → left out),
     *   approved_by_name / _title,
     *   running_title (the header of page 2 on — only "Page X of Y" when blank),
     *   cfa_number (file name only). Titles: "\n" starts a new line.
     */
    public function model(array $p): array
    {
        $t      = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $run    = static fn (string $text, bool $bold = false) => ['text' => $text, 'bold' => $bold];
        $lines  = static fn ($v) => array_values(array_filter(array_map('trim', preg_split('/\R|\\\\n/', (string) ($v ?? ''))), 'strlen'));
        $signer = static fn (string $name, $title) => ['name' => PrintService::text($name), 'title_lines' => $lines($title)];
        $amount = PrintService::amount($p['amount'] ?? null);

        // Typed text → paragraphs: every line is its own paragraph (blank lines only separate), and
        // consecutive "Label : value" lines print together as an indented list.
        $toParagraphs = static function (string $typed) use ($lines, $run): array {
            $out   = [];
            $pairs = [];
            foreach ($lines($typed) as $line) {
                if (preg_match('/^(.+?)\s+:\s+(.+)$/u', $line, $mm)) {
                    $pairs[] = [trim($mm[1]), trim($mm[2])];
                    continue;
                }
                if ($pairs) {
                    $out[] = ['list' => $pairs];
                    $pairs = [];
                }
                $out[] = [$run($line)];
            }
            if ($pairs) {
                $out[] = ['list' => $pairs];
            }

            return $out;
        };
        // The request: the body, the fixed Main Office / Regional Offices list (labels fixed, values
        // as entered), then the paragraphs after it.
        $paragraphs = array_merge(
            $toParagraphs(is_array($p['paragraphs'] ?? null) ? implode("\n", $p['paragraphs']) : (string) ($p['body'] ?? '')),
            [['list' => [['Main Office', $t('main_office')], ['Regional Offices', $t('regional_offices')]]]],
            $toParagraphs((string) ($p['body_after'] ?? ''))
        );

        // FMD's certification under the request.
        $certification = [];
        if ($amount > 0) {
            $certification[] = [$run('Certified Funds Available: '), $run('Php ' . PrintService::money($amount), true)];
            $ref = implode(' / ', array_filter([$t('rfa_number') !== '' ? 'RFA# ' . $t('rfa_number') : '', mb_strtoupper($t('fund_source_name'))]));
            if ($ref !== '') {
                $certification[] = [$run($ref)];
            }
        }

        // Signatures (as entered): requesting head + FMD's certifying officer; Recommending Approval; Approved.
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
        $rows[] = ['label' => 'Approved:', 'signers' => [$signer($t('approved_by_name'), $p['approved_by_title'] ?? '')]];

        $subject = mb_strtoupper($t('subject'));
        $running = $lines($p['running_title'] ?? '');   // blank → page 2 on shows only "Page X of Y"
        $cfaNo   = $t('cfa_number');

        return [
            'date'       => PrintService::longDate($p['date'] ?? ''),
            // FOR and THRU are fixed labels: both rows always print, their values only as entered.
            'rows'       => [
                ['label' => 'FOR',  'name' => $t('for_name'),  'title' => $t('for_title')],
                ['label' => 'THRU', 'name' => $t('thru_name'), 'title' => $t('thru_title')],
            ],
            'subject'    => $subject,
            'paragraphs' => $paragraphs,
            'closing'    => '',
            'signatory'  => ['name' => '', 'title' => '', 'office' => ''],   // replaced by signature_rows
            'certification'  => $certification,
            'signature_rows' => $rows,
            'paper'          => 'A4',
            'letterhead_first_page_only' => true,
            'pdf_running_header' => ['lines' => $running, 'from_page' => 2],
            'brand_document' => 'CFA',
            'header_parts'   => null,
            'footer_text'    => '',
            'file_base'      => 'CFA' . ($cfaNo !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', preg_replace('/^CFA[\s\-]*/i', '', $cfaNo)) : ''),
        ];
    }

    /** The shared memo layout. */
    public function html(array $m, string $media = 'screen'): string
    {
        return (new LaaRequestPrintService())->html($m, $media);
    }
}
