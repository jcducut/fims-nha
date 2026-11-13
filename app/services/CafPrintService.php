<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * CAF (Certificate as to Availability of Funds) print form — the model (every
 * printed word). The CAF is the same one-page certificate as the CBA, so html()
 * reuses CbaPrintService::html() and the DOCX is scripts/gen_cba_docx.js; the
 * model's kind = CAF picks the CAF's styles there.
 *
 * Printed through PrintService::print('CAF', …) — see PrintService::DOCS.
 * Wording ported from the old FMD gen_caf_docx.js / buildCafHtml()
 * ("CAF_if_for_LGU" template).
 */
class CafPrintService
{
    /**
     * CAF model. Payload (all optional): caf_number, allocation_number (or
     * rfa_number — the linked RFA, printed "per RFA# …"), caf_amount (or amount),
     * purpose, payee_name ("name (CODE)" → name), payee_title (e.g. "represented
     * by its Mayor, Hon. …"), payee_office_address, fund_source_name, date (Y-m-d
     * or text; blank → today), approved_by_name (blank → the FMD Department Manager)
     */
    public function model(array $p): array
    {
        if (strtoupper(trim((string) ($p['issuing_office'] ?? ''))) === 'REGIONAL') {
            return $this->regionalModel($p);
        }
        $cafNumber = PrintService::text($p['caf_number'] ?? '');
        $rfaNumber = PrintService::text($p['allocation_number'] ?? $p['rfa_number'] ?? '');
        $amount    = PrintService::amount($p['caf_amount'] ?? $p['amount'] ?? null);
        // Dropdown labels arrive as "name (CODE)" — print the plain name.
        $stripCode = static fn (string $v): string => preg_replace('/\s*\([A-Z0-9\-]{1,12}\)\s*$/', '', $v);
        $fund      = $stripCode(PrintService::text($p['fund_source_name'] ?? ''));
        $payee     = $stripCode(PrintService::text($p['payee_name'] ?? ''));
        $payeeRep  = PrintService::text($p['payee_title'] ?? '');           // e.g. "represented by its Mayor, Hon. …"
        $address   = PrintService::text($p['payee_office_address'] ?? '');
        $purpose   = PrintService::text($p['purpose'] ?? '');

        // "ONE MILLION & 50/100 PESOS (1,000,000.50)" — the CAF writes centavos with "&".
        $words = $amount > 0
            ? strtoupper(preg_replace('/ and (\d+\/100)/', ' & $1', PrintService::amountInWords($amount))) . ' (' . PrintService::money($amount) . ')'
            : '';

        $body = [['text' => 'This is to certify that the amount of ', 'bold' => false]];
        if ($words !== '') {
            $body[] = ['text' => $words, 'bold' => true];
        }
        $body = array_merge($body, $this->ordinalRuns(' necessary to cover ' . $purpose));
        if ($payee !== '') {
            $body[] = ['text' => ' to be released to ', 'bold' => false];
            $body[] = ['text' => $payee . ($payeeRep !== '' ? ' ' . $payeeRep : ''), 'bold' => true];
            if ($address !== '') {
                $body[] = ['text' => ', with principal office at ' . $address, 'bold' => false];
            }
        }
        $body[] = ['text' => ' is available from ' . ($fund !== '' ? $fund : 'the appropriate fund source')
            . ($rfaNumber !== '' ? ' per RFA# ' . $rfaNumber : '') . '.', 'bold' => false];

        $footnote = $fund !== '' ? [['label' => 'Source of Fund: ', 'value' => $fund]] : [];

        return [
            'kind'              => 'CAF',
            'brand_document'    => 'CAF',
            'allocation_number' => $rfaNumber !== '' ? 'RFA# ' . $rfaNumber : '',   // footnote line 1
            'doc_title'         => $cafNumber !== '' ? $cafNumber : 'CAF',
            'date'              => PrintService::longDate($p['date'] ?? ''),
            'title'             => 'CERTIFICATE AS TO AVAILABILITY OF FUNDS',
            // The CAF template: 12pt, title 14pt bold italic, date upright, a 0.5in tab,
            // 1.5 lines, name bold italic, footnote 12pt italic "Source of Fund: <bold>…".
            'style'             => CbaPrintService::style([
                'size' => 12, 'title_size' => 14, 'title_italic' => true, 'date_italic' => false,
                'indent_pt' => 36, 'line_height' => 1.5, 'name_italic' => true,
                'foot_size' => 12, 'foot_italic' => true, 'foot_bold' => 'value',
            ]),
            'body'              => $body,
            'validity'          => '',
            'signatory'         => PrintService::signatory($p['approved_by_name'] ?? ''),
            'footnote'          => $footnote,
            'reminders'         => ['heading' => '', 'items' => []],
            'footer_text'       => LetterheadLayoutService::DEFAULT_FOOTER_TEXT,
            'header_parts'      => null,
            'file_base'         => preg_match('/^CAF/i', $cafNumber)
                ? preg_replace('/[^A-Za-z0-9\-]/', '_', $cafNumber)
                : 'CAF-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $cafNumber) ?: 'DRAFT'),
        ];
    }

    // Regional CAF body wordings (old REGIONAL gen_caf_docx.js / cafServices::detectCafBodyVariant()).
    public const REGIONAL_BODY_VARIANTS = ['entered_into_with', 'in_favor_of', 'in_favor_of_petty'];

    /**
     * The regional office's CAF (issuing_office REGIONAL) — ported from the old
     * REGIONAL gen_caf_docx.js / cafServices::buildPrintPayload(). Payload:
     *   caf_number, date, caf_amount (or amount), purpose (or description),
     *   payee_name, payee_office_address (principal office), fund_source_name
     *   (printed as given, e.g. "Corporate Receipts (CR)"), allocation_number (or
     *   rfa_number / laa_number) + ref_type ('RFA' default | 'LAA'),
     *   certified_name, certified_title (default "Chief Corporate Accountant A"),
     *   certified_office, body_variant:
     *     entered_into_with (default) "… necessary to cover the <purpose> entered into
     *                       with <PAYEE> with principal office at <address> is available
     *                       from <fund> per RFA# <no>."
     *     in_favor_of        "… in favor of <PAYEE> necessary to cover the <purpose> is
     *                       available from <fund> per RFA# <no>." (e.g. EHAP)
     *     in_favor_of_petty  "… in favor of <PAYEE>, <purpose> is available from <fund>."
     *                       — the title then reads "… AVAILABILITY OF FUND".
     * The old module guessed the variant from the purpose text; here the caller
     * sends it.
     */
    private function regionalModel(array $p): array
    {
        $cafNumber = PrintService::text($p['caf_number'] ?? '');
        $amount    = PrintService::amount($p['caf_amount'] ?? $p['amount'] ?? null);
        $purpose   = PrintService::text($p['purpose'] ?? $p['description'] ?? '');
        $payee     = mb_strtoupper(PrintService::text($p['payee_name'] ?? ''));
        $address   = PrintService::text($p['payee_office_address'] ?? '');
        $fund      = PrintService::text($p['fund_source_name'] ?? '');
        $refNo     = PrintService::text($p['allocation_number'] ?? $p['rfa_number'] ?? $p['laa_number'] ?? '');
        $ref       = $refNo !== '' ? ' per ' . (strtoupper(PrintService::text($p['ref_type'] ?? '')) === 'LAA' ? 'LAA#' : 'RFA#') . ' ' . $refNo : '';
        $variant   = in_array($p['body_variant'] ?? '', self::REGIONAL_BODY_VARIANTS, true) ? $p['body_variant'] : 'entered_into_with';

        $body = [
            ['text' => 'This is to certify that the amount of ', 'bold' => false],
            // "P" + non-breaking space, so the amount never wraps away from its sign
            ['text' => $this->regionalWords($amount) . " (P\u{00A0}" . PrintService::money($amount) . ') ', 'bold' => true],
        ];
        if ($variant === 'in_favor_of_petty') {
            $body[] = ['text' => 'in favor of ', 'bold' => false];
            $body[] = ['text' => $payee, 'bold' => true];
            $body[] = ['text' => ', ' . $purpose . ' is available from ' . $fund . '.', 'bold' => false];
        } elseif ($variant === 'in_favor_of') {
            $body[] = ['text' => 'in favor of ', 'bold' => false];
            $body[] = ['text' => $payee, 'bold' => true];
            $body[] = ['text' => ' necessary to cover the ' . $purpose . ' is available from ' . $fund . $ref . '.', 'bold' => false];
        } else {
            $body[] = ['text' => 'necessary to cover the ' . $purpose . ' entered into with ', 'bold' => false];
            $body[] = ['text' => $payee, 'bold' => true];
            $body[] = ['text' => ($address !== '' ? ' with principal office at ' . $address : '')
                . ' is available from ' . $fund . $ref . '.', 'bold' => false];
        }

        $date = PrintService::text($p['date'] ?? '');
        $d    = \DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));

        return [
            'kind'              => 'CAF',
            'issuing_office'    => 'REGIONAL',
            'brand_document'    => 'CAF',
            'allocation_number' => '',   // no footnote lines on the regional form
            'doc_title'         => $cafNumber !== '' ? $cafNumber : 'CAF',
            'date'              => $date === '' ? date('F d, Y') : ($d && $d->format('Y-m-d') === substr($date, 0, 10) ? $d->format('F d, Y') : $date),
            'title'             => $variant === 'in_favor_of_petty'
                ? 'CERTIFICATE AS TO AVAILABILITY OF FUND'
                : 'CERTIFICATE AS TO AVAILABILITY OF FUNDS',
            'style'             => CbaPrintService::style([
                'size' => 12, 'title_size' => 12, 'title_italic' => true, 'date_align' => 'left',
                'indent_pt' => 72, 'sig_layout' => 'certified',
            ]),
            'body'              => $body,
            'validity'          => '',
            'signatory'         => [
                'name'        => PrintService::text($p['certified_name'] ?? ''),
                'title_lines' => [
                    PrintService::text($p['certified_title'] ?? '') ?: 'Chief Corporate Accountant A',
                    PrintService::text($p['certified_office'] ?? ''),
                ],
            ],
            'footnote'          => [],
            'reminders'         => ['heading' => '', 'items' => []],
            'footer_text'       => LetterheadLayoutService::DEFAULT_FOOTER_TEXT,
            'header_parts'      => null,
            'file_base'         => preg_match('/^CAF/i', $cafNumber)
                ? preg_replace('/[^A-Za-z0-9\-]/', '_', $cafNumber)
                : 'CAF-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $cafNumber) ?: 'DRAFT'),
        ];
    }

    /**
     * Regional CAF amount in words: "ONE THOUSAND PESOS ONLY", "… PESOS & 50/100 ONLY"
     * (whole tens of centavos), else "… PESOS AND TWENTY-FIVE CENTAVOS ONLY".
     */
    private function regionalWords(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);
        $words = preg_replace('/\s+Pesos$/', '', PrintService::amountInWords((float) intdiv($cents, 100)));
        $c     = $cents % 100;
        if ($c === 0) {
            return mb_strtoupper($words . ' Pesos Only');
        }
        if ($c % 10 === 0) {
            return mb_strtoupper($words . ' Pesos & ' . $c . '/100 Only');
        }
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
        $centWords = $c < 20 ? $ones[$c] : $tens[intdiv($c, 10)] . ($c % 10 ? '-' . $ones[$c % 10] : '');

        return mb_strtoupper($words . ' Pesos and ' . $centWords . ' Centavos Only');
    }

    /** The CBA's certificate layout, in the CAF's styles (model kind = CAF). */
    public function html(array $m, string $media = 'screen'): string
    {
        return (new CbaPrintService())->html($m, $media);
    }

    /** Plain text → body runs, with "1st" / "2nd" / … suffixes as superscript (the CAF template). */
    private function ordinalRuns(string $text): array
    {
        $runs = [];
        $parts = preg_split('/\b(\d+)(st|nd|rd|th)\b/i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        for ($i = 0; $i < count($parts); $i += 3) {
            if ($parts[$i] !== '') {
                $runs[] = ['text' => $parts[$i], 'bold' => false];
            }
            if (isset($parts[$i + 1])) {
                $runs[] = ['text' => $parts[$i + 1], 'bold' => false];
                $runs[] = ['text' => $parts[$i + 2], 'bold' => false, 'sup' => true];
            }
        }

        return $runs;
    }
}
