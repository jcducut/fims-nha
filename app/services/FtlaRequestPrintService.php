<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * FTLA request — the regional office's memorandum to Treasury asking for a fund
 * transfer (MEMORANDUM / FOR / THRU / SUBJECT, "We humbly request the transfer
 * of fund to cover …", funded under LAA / RFA, the account to credit, "provide
 * us with a copy of the FTLA once approved"). A separate document from the FTLA
 * itself (FtlaPrintService — the letter to the bank). Ported from the old
 * REGIONAL gen_ftla_docx.js / FtlaPdfService.
 *
 * Model only: it prints through the LAA release request's memo layout
 * (LaaRequestPrintService::html() / scripts/gen_laareq_docx.js) — letter paper,
 * Arial 11pt, the NHA letterhead, no footer line.
 *
 * Printed through PrintService::print('FTLAREQ', …).
 */
class FtlaRequestPrintService
{
    /**
     * Model. Payload (all optional): date (Y-m-d or text; blank → today),
     * for_name / for_title, thru_name / thru_title (each row prints only with a
     * name), subject (default "Request for Fund Transfer"), particulars (what
     * the transfer covers), amount, amount_words (default from amount),
     * laa_number, laa_department, fiscal_year, rfa_number, rfa_date,
     * bank_account_line (the account to credit), sig_name / sig_title /
     * sig_office (the requesting officer; signatory_name / _title / _office are
     * accepted too), ftla_number (file name only).
     * DV numbers are never printed on the memo (as the old form).
     */
    public function model(array $p): array
    {
        $t      = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $run    = static fn (string $text, bool $bold = false) => ['text' => $text, 'bold' => $bold];
        $amount = PrintService::amount($p['amount'] ?? null);
        $words  = $t('amount_words') !== '' ? mb_strtoupper($t('amount_words')) : mb_strtoupper(PrintService::amountInWords($amount));

        // 1. the request: what it covers + the amount
        $request = [
            $run('We humbly request the transfer of fund to cover the ' . $t('particulars') . ' in the amount of '),
            $run($words . ' (Php ' . PrintService::money($amount) . ').', true),   // "Php" — the memo font has no ₱ in Dompdf
        ];

        // 2. what funds it (LAA and / or RFA — each optional) + the account to credit
        $funding = [];
        if ($t('laa_number') !== '') {
            $funding[] = $run('LAA No. ');
            $funding[] = $run($t('laa_number') . ($t('laa_department') !== '' ? ' (' . $t('laa_department') . ')' : ''), true);
            if ($t('fiscal_year') !== '') {
                $funding[] = $run(' CY ' . $t('fiscal_year'));
            }
        }
        if ($t('rfa_number') !== '') {
            if ($funding) {
                $funding[] = $run(' & ');
            }
            $funding[] = $run('RFA No. ');
            $funding[] = $run($t('rfa_number'), true);
            if ($t('rfa_date') !== '') {
                $funding[] = $run(' dated ' . PrintService::longDate($t('rfa_date')));
            }
        }
        $transfer = $funding
            ? array_merge([$run('The above-mentioned amount is funded under the ')], $funding, [$run('. Kindly transfer said amount to ')])
            : [$run('Kindly transfer said amount to ')];
        $transfer[] = $run($t('bank_account_line') . '.', true);

        $ftlaNo = $t('ftla_number');

        return [
            'date'       => PrintService::longDate($p['date'] ?? ''),
            'rows'       => LaaRequestPrintService::memoRows($p),
            'subject'    => $t('subject') ?: 'Request for Fund Transfer',
            'paragraphs' => [
                $request,
                $transfer,
                [$run('Kindly inform us and provide us with a copy of Fund Transfer Letter Advice (FTLA) once it is approved.')],
            ],
            'closing'    => 'For your consideration.',
            'signatory'  => [
                'name'   => $t('sig_name') ?: $t('signatory_name'),
                'title'  => $t('sig_title') ?: $t('signatory_title'),
                'office' => $t('sig_office') ?: $t('signatory_office'),
            ],
            'header_parts' => null,
            'footer_text'  => '',
            'file_base'    => 'FTLA-REQUEST' . ($ftlaNo !== '' ? '-' . preg_replace('/[^A-Za-z0-9\-]/', '_', $ftlaNo) : ''),
        ];
    }

    /** The LAA release request's memo layout. */
    public function html(array $m, string $media = 'screen'): string
    {
        return (new LaaRequestPrintService())->html($m, $media);
    }
}
