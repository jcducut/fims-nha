<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * CBA (Certificate of Budget Allocation, CO / COE) print form — the model (every
 * printed word) and the HTML (preview + PDF). The CAF (CafPrintService) is the
 * same one-page certificate, so it reuses html() and scripts/gen_cba_docx.js
 * (model kind = CAF picks its styles).
 *
 * Printed through PrintService::print('CBA', …) — see PrintService::DOCS.
 * Wording ported from the old FMD generators (gen_cba_docx.js, gen_coe_cba_docx.js).
 */
class CbaPrintService
{
    /**
     * How the certificate looks — the CBA's own look. A form or office variant
     * changes only the keys it needs (style()); html() and gen_cba_docx.js read
     * them. Sizes in pt; foot_bold = which part of a footnote line is bold
     * ('label' | 'value' | 'none'); indent_pt = first-line indent of the paragraphs;
     * sig_layout = 'right' (name / title lines centred in the right half) or
     * 'certified' ("Certified:", then the name and title on one line, the office
     * below — the regional CAF).
     */
    public const STYLE = [
        'size'          => 11,      'title_size'  => 11,    'title_bold' => true,  'title_italic' => false,
        'date_italic'   => true,    'indent_pt'   => 0,     'line_height' => null, 'name_italic'  => false,
        'foot_size'     => 10,      'foot_italic' => false, 'foot_bold'  => 'label', 'allocnum_bold' => true,
        'date_align'    => 'right', 'sig_layout'  => 'right',
    ];

    /** self::STYLE with the given keys changed. */
    public static function style(array $changes = []): array
    {
        return $changes + self::STYLE;
    }

    /**
     * CBA model. Payload (all optional):
     *   allocation_number, document_type (CO | COE), project_description,
     *   requested_amount, allocated_amount, fiscal_year, fiscal_year_label,
     *   fund_source_name, program_of_cob, category_name, is_bidding, date,
     *   approved_by_name ("NAME" or "NAME — Title"; blank → the FMD Department Manager),
     *   issuing_office ('REGIONAL' → the regional office's CBA, see regionalModel())
     */
    public function model(array $p): array
    {
        if (strtoupper(trim((string) ($p['issuing_office'] ?? ''))) === 'REGIONAL') {
            return $this->regionalModel($p);
        }
        $isCoe = strtoupper(trim((string) ($p['document_type'] ?? ''))) === 'COE';

        $allocationNumber = trim((string) ($p['allocation_number'] ?? ''));
        $projectDesc      = trim((string) ($p['project_description'] ?? ''));
        $requested        = PrintService::amount($p['requested_amount'] ?? null);
        $allocated        = PrintService::amount($p['allocated_amount'] ?? null);
        $primary          = $requested ?: $allocated;   // requested first, else allocated
        $cash             = $allocated;                 // "Cash Requirement" whenever allocated is set

        $fiscalYear = substr(preg_replace('/\D/', '', (string) ($p['fiscal_year_label'] ?? '')), 0, 4);
        if ($fiscalYear === '') {
            $fiscalYear = preg_replace('/\D/', '', (string) ($p['fiscal_year'] ?? '')) ?: date('Y');
        }

        // ── Certify paragraph: amount phrases bold ──────────────────────────
        $body = [
            ['text' => 'This is to certify that funds necessary to cover the ' . $projectDesc . ' in the amount of ', 'bold' => false],
            ['text' => strtoupper(PrintService::amountInWords($primary)) . ' (P' . PrintService::money($primary) . ')', 'bold' => true],
        ];
        if ($cash > 0) {
            $body[] = ['text' => ' with a Cash Requirement in the amount of ', 'bold' => false];
            $body[] = ['text' => strtoupper(PrintService::amountInWords($cash)) . ' (P' . PrintService::money($cash) . ')', 'bold' => true];
        }
        $body[] = ['text' => ' are provided for in the Approved Corporate Operating Budget for CY ' . $fiscalYear . '.', 'bold' => false];

        // ── Validity: CO depends on is_bidding (default true); COE never bidding
        $isBidding = !in_array($p['is_bidding'] ?? true, [false, 'false', '0', 0], true);
        $validity  = (!$isCoe && $isBidding)
            ? 'This certification is being issued for bidding purposes and valid up to December 31, ' . $fiscalYear . ' only.'
            : 'This certification is being issued for whatever legal purpose it may serve and valid up to December 31, ' . $fiscalYear . ' only.';

        // ── Footnote lines under the allocation number ──────────────────────
        $footnote   = [];
        $fundSource = trim((string) ($p['fund_source_name'] ?? ''));
        if ($fundSource !== '') {
            if (!$isCoe) {
                // CO: drop the dropdown's trailing " (CODE)", add "(Program of COB)".
                $fundSource = preg_replace('/\s*\([^)]*\)\s*$/', '', $fundSource);
                $programOfCob = trim((string) ($p['program_of_cob'] ?? ''));
                if ($programOfCob !== '') {
                    $fundSource .= ' (' . $programOfCob . ')';
                }
            }
            $footnote[] = ['label' => 'Sources of Fund: ', 'value' => $fundSource];
        }
        $category = trim((string) ($p['category_name'] ?? ''));
        if (!$isCoe && $category !== '' && $cash > 0) {
            $footnote[] = ['label' => 'Program: ', 'value' => $category];
        }

        $rfaReminder = 'If an RFA is not secured during the budget year, the project must again be included in your Proposed Work Program and Budget for the ensuing year.';
        $reminders = $isCoe
            ? ['heading' => 'Reminder:', 'items' => [$rfaReminder]]
            : ['heading' => 'Reminders:', 'items' => [
                'If the project is not bid out or a MOA (with developer and Community Association for CBIA Project or with LGU for LGU - implemented project) is not entered into during the year, this CBA shall be deemed null and void and a new CBA should be secured from FMD.',
                $rfaReminder,
            ]];

        return [
            'kind'              => 'CBA',     // html() / gen_cba_docx.js also lay out the CAF
            'document_type'     => $isCoe ? 'COE' : 'CO',
            'issuing_office'    => 'FMD',
            'allocation_number' => $allocationNumber,
            'date'              => trim((string) ($p['date'] ?? '')) ?: date('F d, Y'),
            'title'             => 'CERTIFICATE OF BUDGET ALLOCATION',
            // The COE template's title is not bold, and it indents both paragraphs.
            'style'             => self::style(['title_bold' => !$isCoe, 'indent_pt' => $isCoe ? 22 : 0]),
            'body'              => $body,
            'validity'          => $validity,
            'signatory'         => PrintService::signatory($p['approved_by_name'] ?? ''),
            'footnote'          => $footnote,
            'reminders'         => $reminders,
            'footer_text'       => LetterheadLayoutService::DEFAULT_FOOTER_TEXT,   // set by PrintService::model()
            'header_parts'      => null,   // set by PrintService::model() from the letterhead layout
            'file_base'         => preg_replace('/[^A-Za-z0-9\-]/', '_', $allocationNumber) ?: 'CBA',
        ];
    }

    /**
     * The regional office's CBA (issuing_office REGIONAL) — ported from the old
     * REGIONAL gen_cba_docx.js / BudgetAllocationPdfService::buildCbaHtml(): one
     * italic paragraph "budgetary requirement … is provided for", always "whatever
     * legal purpose", the regional signatory (name, title, office), "Source of
     * Funds:", no reminders; 12pt at 1.5 lines, title 15pt.
     * Payload: allocation_number (or cba_no), date, project_description (or
     * description), amount (else allocated_amount / requested_amount),
     * fiscal_year_label (or a 4-digit fiscal_year), fund_source_name (or
     * source_of_funds), signatory_name (or approved_by_name), signatory_title,
     * signatory_office.
     */
    private function regionalModel(array $p): array
    {
        $allocationNumber = PrintService::text($p['allocation_number'] ?? $p['cba_no'] ?? '');
        $desc   = PrintService::text($p['project_description'] ?? $p['description'] ?? '');
        $amount = PrintService::amount($p['amount'] ?? $p['allocated_amount'] ?? $p['requested_amount'] ?? null);
        $year   = preg_match('/\b(\d{4})\b/', PrintService::text($p['fiscal_year_label'] ?? $p['fiscal_year'] ?? ''), $y) ? $y[1] : '';
        $fund   = PrintService::text($p['fund_source_name'] ?? $p['source_of_funds'] ?? '');

        // "One Thousand and 00/100 Pesos" — the regional form always writes the centavos.
        $cents = (int) round(abs($amount) * 100);
        $words = preg_replace('/\s+Pesos$/', '', PrintService::amountInWords((float) intdiv($cents, 100)))
            . sprintf(' and %02d/100 Pesos', $cents % 100);

        $body = [['text' => 'This is to certify that budgetary requirement necessary to cover the ' . $desc
            . ' in the amount of ' . $words . ' (P' . PrintService::money($amount) . ')'
            . ' is provided for in the Approved Corporate Operating Budget' . ($year !== '' ? ' for CY ' . $year : '') . '.', 'bold' => false]];

        $signatory = [
            'name'        => PrintService::text($p['signatory_name'] ?? $p['approved_by_name'] ?? ''),
            'title_lines' => array_values(array_filter([
                PrintService::text($p['signatory_title'] ?? ''),
                PrintService::text($p['signatory_office'] ?? ''),
            ], fn ($l) => $l !== '')),
        ];

        return [
            'kind'              => 'CBA',
            'document_type'     => 'CO',
            'issuing_office'    => 'REGIONAL',
            'allocation_number' => $allocationNumber,
            'date'              => PrintService::text($p['date'] ?? '') ?: date('F d, Y'),
            'title'             => 'CERTIFICATE OF BUDGET ALLOCATION',
            'style'             => self::style([
                'size' => 12, 'title_size' => 15, 'indent_pt' => 72, 'line_height' => 1.5, 'name_italic' => true,
                'foot_size' => 12, 'foot_italic' => true, 'foot_bold' => 'none', 'allocnum_bold' => false,
            ]),
            'body'              => $body,
            'validity'          => 'This certification is being issued for whatever legal purpose it may serve'
                . ($year !== '' ? ' and valid up to December 31, ' . $year : '') . '.',
            'signatory'         => $signatory,
            'footnote'          => $fund !== '' ? [['label' => 'Source of Funds: ', 'value' => $fund]] : [],
            'reminders'         => ['heading' => '', 'items' => []],
            'footer_text'       => LetterheadLayoutService::DEFAULT_FOOTER_TEXT,
            'header_parts'      => null,
            'file_base'         => preg_replace('/[^A-Za-z0-9\-]/', '_', $allocationNumber) ?: 'CBA',
        ];
    }

    /**
     * A4 certificate (CBA, and the CAF — CafPrintService::html() calls this). $media = 'screen' wraps it in a sheet for the
     * preview iframe; 'print' is the bare page Dompdf renders. Tables, not
     * flexbox — Dompdf doesn't support flexbox.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        // Also lays out the CAF and the regional CBA — same certificate, their own style.
        $s = self::style($m['style'] ?? []);

        $header = PrintService::headerHtml($m, 468, $m['brand_document'] ?? 'CBA');
        $footer = '<div class="ftr-text">' . $e($m['footer_text']) . '</div>';

        $body = '';
        foreach ($m['body'] as $run) {
            $text  = !empty($run['sup']) ? '<sup>' . $e($run['text']) . '</sup>' : $e($run['text']);
            $body .= $run['bold'] ? '<b>' . $text . '</b>' : $text;
        }

        $sigTitle = '';
        foreach ($m['signatory']['title_lines'] as $line) {
            $sigTitle .= '<div class="sig-title">' . $e($line) . '</div>';
        }

        $items = '';
        foreach ($m['reminders']['items'] as $i => $text) {
            $items .= '<tr><td class="rem-no">' . ($i + 1) . '.</td><td class="rem-text">' . $e($text) . '</td></tr>';
        }
        $reminders = $items !== ''
            ? '<div class="rem"><div class="rem-h">' . $e($m['reminders']['heading']) . '</div><table>' . $items . '</table></div>'
            : '';

        // Style rules that differ from the CBA's own look (self::STYLE) — none for the CBA.
        $d = self::STYLE;
        $styleCss = ($s['size'] != $d['size'] ? 'body,table{font-size:' . $s['size'] . 'pt;}' : '')
            . ($s['date_italic'] !== $d['date_italic'] ? '.date{font-style:' . ($s['date_italic'] ? 'italic' : 'normal') . ';}' : '')
            . ($s['title_size'] != $s['size'] || $s['title_italic']
                ? '.title{' . ($s['title_size'] != $s['size'] ? 'font-size:' . $s['title_size'] . 'pt;' : '')
                  . ($s['title_italic'] ? 'font-style:italic;' : '') . '}'
                : '')
            . ($s['line_height'] ? '.cert{line-height:' . $s['line_height'] . ';}' : '')
            . ($s['name_italic'] ? '.sig-name{font-style:italic;}' : '')
            . ($s['foot_size'] != $d['foot_size'] || $s['foot_italic']
                ? '.foot{' . ($s['foot_size'] != $d['foot_size'] ? 'font-size:' . $s['foot_size'] . 'pt;' : '')
                  . ($s['foot_italic'] ? 'font-style:italic;' : '') . '}'
                : '')
            . ($s['foot_italic'] ? '.foot div{margin-bottom:4pt;}' : '')
            . (!$s['allocnum_bold'] ? '.allocnum{font-weight:normal;}' : '')
            . ($s['date_align'] !== $d['date_align'] ? '.date{text-align:' . $s['date_align'] . ';}' : '')
            // "Certified:" block: name at 1in, title at the 225pt tab (the template's tab stops).
            . ($s['sig_layout'] === 'certified'
                ? '.sig-lbl{font-style:italic;margin:24pt 0 54pt;}.sig-c{border-collapse:collapse;margin-left:72pt;}'
                  . '.sig-c td{padding:0;vertical-align:top;}.sig-c .n{font-weight:bold;width:153pt;}.sig-c .t{font-style:italic;}'
                : '');
        if ($styleCss !== '') {
            $styleCss .= 'sup{font-size:.65em;line-height:0;}';
        }

        $foot = '';
        if ($m['allocation_number'] !== '') {
            $foot .= '<div class="allocnum">' . $e($m['allocation_number']) . '</div>';
        }
        foreach ($m['footnote'] as $line) {
            $label = $e($line['label']);
            $value = $e($line['value']);
            $foot .= '<div>' . ($s['foot_bold'] === 'label' ? '<b>' . $label . '</b>' : $label)
                . ($s['foot_bold'] === 'value' ? '<b>' . $value . '</b>' : $value) . '</div>';
        }

        $indent = $s['indent_pt'] ? 'text-indent:' . $s['indent_pt'] . 'pt;' : '';
        $title  = $s['title_bold'] ? '<b>' . $e($m['title']) . '</b>' : $e($m['title']);

        // Page box: A4 (595 × 842pt) with the DOCX's 1in side margins (468pt content).
        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 468, 90, 36, 14);
        $pageCss = $media === 'print'
            ? '@page{size:A4 portrait;margin:' . $top . 'pt 63.5pt 64pt 63.5pt;}body{margin:0;}' . $hdrCss
              . '.ftr{position:fixed;left:0;right:0;bottom:-40pt;text-align:center;}'
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{position:relative;width:595pt;min-height:842pt;margin:0 auto;padding:36pt 63.5pt 90pt;'
              . 'box-sizing:border-box;background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}'
              . '.ftr{position:absolute;left:0;right:0;bottom:24pt;text-align:center;}';

        // No validity paragraph (CAF) — the certify paragraph takes its gap.
        $certs = $m['validity'] !== ''
            ? '<p class="cert">' . $body . '</p><p class="cert last">' . $e($m['validity']) . '</p>'
            : '<p class="cert last">' . $body . '</p>';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e(($m['doc_title'] ?? '') ?: ($m['allocation_number'] ?: 'CBA')) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:"Times New Roman",Times,serif;font-size:11pt;color:#000;line-height:1.3;}'
            . '.hdr{text-align:center;margin-bottom:14pt;}'
            // Any header PNG keeps its aspect ratio: full width, but never taller
            // than 90pt (overrides the width DocumentBranding inlines).
            . '.hdr>img{width:auto !important;height:auto;max-width:468pt;max-height:90pt;}'
            . '.ftr-text{font-family:Arial,Helvetica,sans-serif;font-size:8.5pt;letter-spacing:.6pt;color:#333;}'
            // Screen sheet: keep the footer inside the 1in side margins (print:
            // the @page margins already do that).
            . ($media === 'print' ? '' : '.ftr-text{padding:0 63.5pt;}')
            . '.date{text-align:right;font-style:italic;margin:0 0 16pt;}'
            . '.title{text-align:center;margin:0 0 15pt;}'
            . '.cert{text-align:justify;font-style:italic;margin:0 0 13pt;' . $indent . '}'
            . '.cert.last{margin-bottom:45pt;}'
            . '.sig{width:100%;border-collapse:collapse;}.sig td{width:50%;padding:0;vertical-align:top;text-align:center;}'
            . '.sig-name{font-weight:bold;}.sig-title{font-style:italic;}'
            . '.foot{font-size:10pt;margin-top:40pt;}.allocnum{font-weight:bold;font-style:italic;}'
            . '.rem{font-size:10pt;margin-top:12pt;}.rem-h{font-weight:bold;margin-bottom:4pt;}'
            . '.rem table{border-collapse:collapse;width:100%;font-size:10pt;}'
            . '.rem-no{width:18pt;vertical-align:top;font-style:italic;padding:0 0 6pt;}'
            . '.rem-text{text-align:justify;font-style:italic;vertical-align:top;padding:0 0 6pt;}'
            . $styleCss
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . $header . '</div>', '<div class="ftr">' . $footer . '</div>')
            . '<p class="date">' . $e($m['date']) . '</p>'
            . '<p class="title">' . $title . '</p>'
            . $certs
            . ($s['sig_layout'] === 'certified'
                ? '<p class="sig-lbl">Certified:</p><table class="sig-c"><tr><td class="n">' . $e($m['signatory']['name']) . '</td>'
                  . '<td class="t">' . $e($m['signatory']['title_lines'][0] ?? '') . '</td></tr>'
                  . (isset($m['signatory']['title_lines'][1]) ? '<tr><td class="t" colspan="2">' . $e($m['signatory']['title_lines'][1]) . '</td></tr>' : '')
                  . '</table>'
                : '<table class="sig"><tr><td></td><td><div class="sig-name">' . $e($m['signatory']['name']) . '</div>' . $sigTitle . '</td></tr></table>')
            . '<div class="foot">' . $foot . '</div>'
            . $reminders
            . PrintService::bodyClose($media, '<div class="ftr">' . $footer . '</div>');
    }
}
