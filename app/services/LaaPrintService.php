<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * LAA (Letter of Advice of Allotment) print form, CO and COE (document_type COE) —
 * model + HTML (A4). Printed through PrintService::print('LAA', …);
 * DOCX: scripts/gen_laa_docx.js. Ported from the old FMD gen_laa_docx.js /
 * gen_coe_laa_docx.js.
 */
class LaaPrintService
{
    // Printed when nobody holds the closing role (old FMD default).
    private const DEFAULT_LAA_CLOSE_NAME = 'LORNA S. DIOLOLA';

    // PDF: longest LAA description (chars) kept beside the amount block in the
    // details sheet; longer ones flow below it (a table row can't split pages).
    private const LAA_SIDE_BY_SIDE_MAX = 600;

    /** Requesting office's name (uac.departments), for the title / addressee / transfer text. */
    private function officeName(array $p): string
    {
        if (PrintService::text($p['office_name'] ?? '') !== '') {
            return PrintService::text($p['office_name']);
        }
        $id = (int) ($p['requesting_department_id'] ?? 0) ?: (int) ($p['requesting_region_id'] ?? 0);
        if ($id <= 0) {
            return '';
        }
        try {
            return PrintService::text(App\ExecuteScalar('SELECT department_name FROM uac.departments WHERE department_id = ' . $id, 'DB'));
        } catch (\Throwable $e) {
            App\LogError('LaaPrintService::officeName ' . $e->getMessage());

            return '';
        }
    }

    /**
     * LAA model, CO by default or COE with document_type COE. Payload (all optional):
     *   document_type, allocation_number (or laa_number), date (Y-m-d or text),
     *   office_name or requesting_department_id / requesting_region_id (name from
     *   uac.departments), project_description, amount (else allocated_amount /
     *   requested_amount), fund_source_name (default "Corporate Receipts"),
     *   program_of_cob, fiscal_year_label (or a 4-digit fiscal_year), to_name /
     *   to_title / to_org / to_address / greeting (addressee; CO default: the office,
     *   COE: the named Regional Manager), cert_name / _title / _department
     *   ("Certified as to Availability of Allotment"; blank → the current FMD
     *   Department Manager), close_name / _title / _department (closing signatory;
     *   blank → the current FSG Group Manager)
     */
    public function model(array $p): array
    {
        $laaNo  = PrintService::text($p['allocation_number'] ?? $p['laa_number'] ?? '');
        $office = $this->officeName($p);
        $amount = PrintService::amount($p['amount'] ?? $p['allocated_amount'] ?? $p['requested_amount'] ?? null);
        // Plain fund name — the dropdown supplies "name (CODE)".
        $fund   = preg_replace('/\s*\([^)]*\)\s*$/', '', PrintService::text($p['fund_source_name'] ?? $p['source_of_fund'] ?? '')) ?: 'Corporate Receipts';

        // Title: "LAA 2026-41-95 (NCR-East Sector) CY 2026" — office without its code.
        $titleOffice = preg_replace('/\s*\([^)]*\)\s*$/', '', $office);
        // "CY <year>": the 4-digit year in fiscal_year_label ("FY 2026" → 2026),
        // else fiscal_year when it is a 4-digit year (not a period id).
        $year = preg_match('/\b(\d{4})\b/', PrintService::text($p['fiscal_year_label'] ?? ''), $y) ? $y[1] : '';
        if ($year === '' && preg_match('/^\d{4}$/', (string) ($p['fiscal_year'] ?? ''))) {
            $year = (string) $p['fiscal_year'];
        }
        $titleDetail = trim($laaNo . ($titleOffice !== '' ? ' (' . $titleOffice . ')' : '') . ($year !== '' ? ' CY ' . $year : ''));

        $desc  = PrintService::text($p['project_description'] ?? $p['description'] ?? '');
        // COE LAA (document_type COE — old FMD gen_coe_laa_docx.js): same letter,
        // addressed to a named Regional Manager, one flowing transfer sentence,
        // "… PESOS & 4/100" amount wording, no fund notes bottom-left.
        $isCoe = strtoupper(PrintService::text($p['document_type'] ?? '')) === 'COE';

        if ($isCoe) {
            // "To transfer obligation made to <office> to cover the payment for the <description>"
            // (the office without its trailing code, as in the title)
            $transfer = 'To transfer obligation made to'
                . ($titleOffice !== '' ? ' ' . $titleOffice : '')
                . ($desc !== '' ? ($titleOffice !== '' ? ' to cover the payment for ' . (preg_match('/^the\s/i', $desc) ? '' : 'the ') : ' ') . $desc : '');
        } elseif ($office === '') {
            // CO: details sheet text under "To transfer obligation partially made to".
            $transfer = $desc;
        } elseif ($desc === '') {
            $transfer = $office;
        } elseif (preg_match('/^to\s+cover\b/i', $desc)) {
            $transfer = $office . ' ' . $desc;
        } else {
            $transfer = $office . ' to cover ' . (preg_match('/^the\s/i', $desc) ? '' : 'the ') . $desc;
        }

        $closeName = PrintService::text($p['close_name'] ?? '')
            ?: (PrintService::resolveSignatoryName('FSG Group Manager') ?? PrintService::resolveSignatoryName('Group Manager') ?? self::DEFAULT_LAA_CLOSE_NAME);

        if ($isCoe) {
            // Addressee: the Regional Manager by name (to_name), e.g. "ENGR. JOVITA G. PANOPIO".
            $toName = PrintService::text($p['to_name'] ?? '');
            $to = array_values(array_filter([
                ['text' => $toName, 'bold' => true],
                ['text' => PrintService::text($p['to_title'] ?? '') ?: 'Regional Manager', 'bold' => false],
                ['text' => PrintService::text($p['to_org'] ?? '') ?: 'National Housing Authority', 'bold' => false],
                ['text' => PrintService::text($p['to_address'] ?? '') ?: 'Elliptical Road, Diliman, Q.C.', 'bold' => false],
            ], fn ($l) => $l['text'] !== ''));
            $greeting = PrintService::text($p['greeting'] ?? '') ?: $this->salutation($toName);
        } else {
            // Addressee: the record stores no person, so the office takes the name line.
            $to = array_values(array_filter([
                ['text' => PrintService::text($p['to_name'] ?? '') ?: $office, 'bold' => true],
                ['text' => PrintService::text($p['to_title'] ?? ''), 'bold' => false],
                ['text' => PrintService::text($p['to_org'] ?? '') ?: 'National Housing Authority', 'bold' => false],
                ['text' => PrintService::text($p['to_address'] ?? '') ?: 'Elliptical Rd., Diliman, Quezon City', 'bold' => false],
            ], fn ($l) => $l['text'] !== ''));
            $greeting = PrintService::text($p['greeting'] ?? '') ?: 'Dear Sir/Madam:';
        }

        return [
            'variant'      => $isCoe ? 'COE' : 'CO',
            'laa_no'       => $laaNo,
            'date'         => PrintService::longDate($p['date'] ?? ''),
            'title_detail' => $titleDetail,
            'to'           => $to,
            'greeting'     => $greeting,
            'intro'        => 'This is to transfer the Obligational Authority to cover the funds for the implementation of hereunder listed project out of the allotment from '
                . $fund . ' and Other Working Capital Funds of the Authority. Obligations and Disbursements must be in accordance with generally accepted accounting and auditing rules and regulations and the sole responsibility of the implementing office. A monthly certification of disbursement made out of this cash advance received shall be submitted to this Department.',
            // COE: no label — the transfer text is one sentence starting with it.
            'transfer_label' => $isCoe ? '' : 'To transfer obligation partially made to',
            'transfer'     => $transfer,
            'amount'       => PrintService::money($amount),
            'amount_words' => '** ' . ($isCoe ? $this->pesosAndCentavos($amount) : strtoupper(PrintService::amountInWords($amount))) . ' **',
            'fund'         => $fund,
            'program_of_cob' => PrintService::text($p['program_of_cob'] ?? ''),
            // CO prints "Source of Fund: … / <program>" bottom-left of the sheet; COE doesn't.
            'fund_notes'   => !$isCoe,
            'cert'         => [
                'name'  => PrintService::text($p['cert_name'] ?? '') ?: (PrintService::resolveSignatoryName('FMD Department Manager') ?? ''),
                'title' => PrintService::text($p['cert_title'] ?? '') ?: 'Department Manager',
                'dept'  => PrintService::text($p['cert_department'] ?? '') ?: 'Financial Management Department',
            ],
            'closing'      => 'No amount herein authorized shall be spent for any purpose other than for which has been appropriated and released.',
            'close'        => [
                'name'  => $closeName,
                'title' => PrintService::text($p['close_title'] ?? '') ?: 'Group Manager',
                'dept'  => PrintService::text($p['close_department'] ?? '') ?: 'Financial Services Group',
            ],
            'file_base'    => ($isCoe ? 'COE-' : '') . (preg_match('/^LAA/i', $laaNo)
                ? preg_replace('/[^A-Za-z0-9\-]/', '_', $laaNo)
                : 'LAA-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $laaNo) ?: 'DRAFT')),
        ];
    }

    /**
     * "ENGR. JOVITA G. PANOPIO" → "Dear Engr. Panopio," (title + surname, as in
     * the COE LAA template). Needs a title (Engr., Atty., Ar., Arch., Dr., Mr.,
     * Ms., Mrs.) to know how to address the person; otherwise, or blank →
     * "Dear Sir/Madam:".
     */
    private function salutation(string $name): string
    {
        if (!preg_match('/^(ENGR|ATTY|AR|ARCH|DR|MR|MS|MRS)\.?\s+(.+)$/i', trim($name), $m)) {
            return 'Dear Sir/Madam:';
        }
        $words = preg_split('/\s+/', trim(preg_replace('/,.*$/', '', $m[2])));   // drop ", Jr." / ", CESO III"
        $last  = end($words);   // "PANOPIO" → "Panopio" below
        $title = ucfirst(strtolower($m[1])) . '.';

        return 'Dear ' . $title . ' ' . mb_convert_case(mb_strtolower($last), MB_CASE_TITLE) . ',';
    }

    /** COE LAA amount wording: "ONE MILLION … PESOS & 50/100" (centavos only when non-zero). */
    private function pesosAndCentavos(float $num): string
    {
        $cents = (int) round(abs($num) * 100);
        $words = preg_replace('/\s+Pesos$/i', '', PrintService::amountInWords((float) intdiv($cents, 100)));

        return strtoupper($words . ' Pesos' . ($cents % 100 ? ' & ' . ($cents % 100) . '/100' : ''));
    }

    /**
     * LAA (CO) on A4 with 0.8in sides (480pt text width). Mirrors
     * scripts/gen_laa_docx.js: date 12pt set in at 5", title 12pt, body 11.5pt,
     * details sheet 10pt (column grid of the template's embedded sheet), footer 9pt.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nl = static fn (string $v) => nl2br(htmlspecialchars($v, ENT_QUOTES, 'UTF-8'), false);

        $to = '';
        foreach ($m['to'] as $line) {
            $to .= '<p>' . ($line['bold'] ? '<b>' . $e($line['text']) . '</b>' : $e($line['text'])) . '</p>';
        }

        // Details sheet — the template's sheet columns A..H (pt):
        //   A 20.5 | B 187.15 | C 62.4 | D 75.8 | E 15 | F 15 | G 85.3 | H 18.95  (= 480)
        // Built from simple fixed-width tables (no row spans) — Dompdf mis-sizes
        // a merged grid; the DOCX keeps the template's exact merged grid.
        $c   = $m['cert'];
        $row = static fn (array $cells) => '<table class="sheet"><tr>' . implode('', array_map(
            static fn ($cell) => '<td style="width:' . $cell[0] . 'pt;"' . (isset($cell[2]) ? ' class="' . $cell[2] . '"' : '') . '>' . ($cell[1] ?? '') . '</td>',
            $cells
        )) . '</tr></table>';
        $right = '<table class="sheet"><tr><td style="width:153.2pt;"></td><td class="r dbl" style="width:15pt;">P</td>'
            . '<td class="r dbl" style="width:85.3pt;">' . $e($m['amount']) . '</td><td style="width:18.95pt;"></td></tr></table>'
            . '<div class="c b" style="width:253.5pt;margin-top:3pt;">' . $e($m['amount_words']) . '</div>'
            . '<div style="height:34pt;"></div>'
            . $row([[62.4, ''], [90.8, 'Source of Fund :'], [119.15, $e($m['fund'])]]);
        // A table row can't split across PDF pages, so a long description
        // (> LAA_SIDE_BY_SIDE_MAX chars) flows as a paragraph under the amount
        // block instead of beside it; normal ones keep the template's layout.
        $long = mb_strlen($m['transfer']) > self::LAA_SIDE_BY_SIDE_MAX;
        // CO: label line, then the text; COE: the text is one sentence, no label.
        $label = $m['transfer_label'] !== '' ? $e($m['transfer_label']) : '';
        $sheet = '<div style="height:9pt;"></div>'
            . ($long
                ? $row([[20.5, ''], [187.15, $label], [272.35, $right]])
                    . '<div class="longdesc">' . $nl($m['transfer']) . '</div>'
                : $row([[20.5, ''], [187.15, ($label !== '' ? $label . '<br>' : '') . $nl($m['transfer'])], [272.35, $right]]))
            . '<div style="height:26pt;"></div>'
            . $row([[270.05, ''], [209.95, 'Certified as to Availability of Allotment:']])
            . '<div style="height:26pt;"></div>'
            . $row([[20.5, ''], [249.55, ''], [191.1, $e($c['name']) ?: '&nbsp;', 'c b'], [18.95, '']])
            . $row([[20.5, ''], [249.55, $m['fund_notes'] ? 'Source of Fund: ' . $e($m['fund']) : '', 'bi'], [191.1, $e($c['title']), 'c'], [18.95, '']])
            . $row([[20.5, ''], [249.55, ($m['fund_notes'] ? $e($m['program_of_cob']) : '') ?: '&nbsp;', 'bi'], [191.1, $e($c['dept']), 'c'], [18.95, '']])
            . '<div style="height:6.75pt;"></div>';

        $close = $m['close'];
        $laaFooter = $m['footer_text'] !== '' ? '<div class="ftr"><div class="ftr-text">' . $e($m['footer_text']) . '</div></div>' : '';
        // Page box: A4 (595.3 × 841.9pt), 0.8in sides → 480pt text width.
        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 480, 90, 28, 4);
        $pageCss = $media === 'print'
            ? '@page{size:A4 portrait;margin:' . $top . 'pt 57.6pt 40pt 57.6pt;}body{margin:0;}' . $hdrCss
              . '.ftr{position:fixed;left:0;right:0;bottom:-26pt;text-align:center;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet-page{position:relative;width:595.3pt;min-height:841.9pt;margin:0 auto;padding:28pt 57.6pt 60pt;'
              . 'box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}'
              . '.ftr{position:absolute;left:57.6pt;right:57.6pt;bottom:14pt;text-align:center;}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['laa_no'] ?: 'LAA') . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:11.5pt;color:#000;line-height:1.15;}'
            . 'p{margin:0;}'
            . '.hdr{text-align:center;margin-bottom:4pt;}'
            . '.hdr>img{width:auto !important;height:auto;max-width:480pt;max-height:90pt;}'
            . '.h12{font-size:12pt;}.bl12{height:13.8pt;}.bl{height:13.2pt;}'
            . '.date{margin-left:360pt;}.title{text-align:center;}.j{text-align:justify;}'
            . '.rule{height:13.8pt;border-bottom:1pt solid #000;margin-right:13pt;}'
            . 'table.sheet{width:480pt;border-collapse:collapse;font-size:10pt;line-height:1.15;}'
            . 'table.sheet td{padding:0 2pt;vertical-align:top;}'
            . 'table.sheet td table.sheet{width:100%;}table.sheet td table.sheet td{padding:0;}'
            . '.r{text-align:right;font-weight:bold;vertical-align:bottom !important;}'
            . '.dbl{border-bottom:2.2pt double #000;}'
            . '.c{text-align:center;}.b{font-weight:bold;}.bi{font-weight:bold;font-style:italic;}'
            . 'div.c,div.b{font-size:10pt;}'
            . '.longdesc{margin:6pt 0 0 22.5pt;font-size:10pt;line-height:1.15;text-align:justify;}'
            . '.ftr-text{font-family:Arial,Helvetica,sans-serif;font-size:9pt;color:#000;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet-page', '<div class="hdr">' . PrintService::headerHtml($m, 480, 'LAA') . '</div>', $laaFooter)
            . '<div class="bl12"></div>'
            . '<p class="h12 date">' . $e($m['date']) . '</p>'
            . '<div class="bl12"></div><div class="bl12"></div>'
            . '<p class="h12 title">LETTER OF ADVICE OF ALLOTMENT No. <b><u>' . $e($m['title_detail']) . '</u></b></p>'
            . '<div class="bl12"></div><div class="bl12"></div>'
            . $to
            . '<div class="bl"></div><p>' . $e($m['greeting']) . '</p><div class="bl"></div>'
            . '<p class="j">' . $e($m['intro']) . '</p>'
            . '<div class="rule"></div>'
            . $sheet
            . '<div class="rule"></div><div class="bl12"></div>'
            . '<p class="j">' . $e($m['closing']) . '</p>'
            . '<div class="bl"></div><p>Very truly yours,</p>'
            . '<div class="bl"></div><div class="bl"></div><div class="bl"></div>'
            . '<p><b>' . $e($close['name']) . '</b></p><p>' . $e($close['title']) . '</p><p>' . $e($close['dept']) . '</p>'
            . PrintService::bodyClose($media, $laaFooter);
    }
}
