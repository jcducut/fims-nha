<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * RFA (Request for Fund Allotment) print form — model + HTML (legal paper).
 * Printed through PrintService::print('RFA', …); DOCX: scripts/gen_rfa_docx.js.
 * Ported from the old FMD gen_rfa_docx.js (RFA_Form_No_* variants 1a–2d).
 */
class RfaPrintService
{
    // The physical RFA forms (RFA_Form_No_*): who fills in the number + the type line.
    private const RFA_VARIANTS = [
        '1a'  => ['filled_out_by' => 'FMD',                          'type' => 'GOODS AND SERVICES – Php 30 Million and below (STAFF)'],
        '1a2' => ['filled_out_by' => 'FMD',                          'type' => 'GOODS AND SERVICES – Php 30 Million and below (STAFF)'],
        '1b'  => ['filled_out_by' => 'FMD',                          'type' => 'INFRASTRUCTURE (STAFF)'],
        '2a'  => ['filled_out_by' => 'Regional Offices',             'type' => 'GOODS AND SERVICES – Php 30 Million and below (REGIONAL OFFICE)'],
        '2b'  => ['filled_out_by' => 'FMD',                          'type' => 'GOODS AND SERVICES – ABOVE Php 30 Million (REGIONAL OFFICE)'],
        '2c'  => ['filled_out_by' => 'Regional Office/Finance Unit', 'type' => 'INFRASTRUCTURE – Php 50 Million and below (REGIONAL OFFICE)'],
        '2d'  => ['filled_out_by' => 'FMD',                          'type' => 'INFRASTRUCTURE – ABOVE Php 50 Million (REGIONAL OFFICE)'],
    ];

    // PDF: longest last RFA particulars line (chars) kept on the amount's row.
    private const RFA_AMOUNT_ROW_MAX = 300;

    /** Requesting office's uac.departments.department_type (MAIN → STAFF form). */
    private function departmentType(array $p): string
    {
        if (trim((string) ($p['requesting_department_type'] ?? '')) !== '') {
            return strtoupper(trim((string) $p['requesting_department_type']));
        }
        $id = (int) ($p['requesting_department_id'] ?? 0) ?: (int) ($p['requesting_region_id'] ?? 0);
        if ($id <= 0) {
            return '';
        }
        try {
            return strtoupper((string) App\ExecuteScalar('SELECT department_type FROM uac.departments WHERE department_id = ' . $id, 'DB'));
        } catch (\Throwable $e) {
            App\LogError('RfaPrintService::departmentType ' . $e->getMessage());

            return '';
        }
    }

    /**
     * RFA model. Payload (all optional):
     *   allocation_number (or rfa_no), date, payee_name (requesting office),
     *   requesting_department_type ('MAIN' → STAFF form) or requesting_department_id /
     *   requesting_region_id (type looked up in uac.departments), form_variant
     *   (1a 1a2 1b 2a 2b 2c 2d), filled_out_by / rfa_type (override the variant),
     *   particulars (or description / project_description; one line per row), amount,
     *   cert_correct_name / _title / _office (Part I signatory),
     *   project_name, total_estimated_cost, total_funds_available, total_funded_to_date,
     *   balance, payments_outstanding, this_request, total_payments,
     *   total_contract_cost, adjustment_additive, adjustment_deductive, total_allotment,
     *   funding_source { saro_no, saro_date, or_no, or_date, corporate_receipts,
     *                    subsidy, equity, agency_name },
     *   certified_funds_name / _title, recommending_name (blank → the current FMD
     *   Department Manager), approved_name
     */
    public function model(array $p): array
    {
        $rfaNo   = PrintService::text($p['allocation_number'] ?? $p['rfa_no'] ?? '');
        $isMain  = $this->departmentType($p) === 'MAIN';
        $key     = isset(self::RFA_VARIANTS[$p['form_variant'] ?? '']) ? $p['form_variant'] : ($isMain ? '1a' : '2a');
        $variant = self::RFA_VARIANTS[$key];
        $isStaff = in_array($key, ['1a', '1a2', '1b'], true);

        $amount = PrintService::amount($p['amount'] ?? $p['requested_amount'] ?? null);
        $num    = fn (string $k, float $default = 0.0) => ($v = PrintService::amount($p[$k] ?? null)) > 0 ? $v : $default;
        // Money shown on the form, or '' (the form then prints a blank line).
        $show   = fn (float $v) => $v > 0 ? PrintService::money($v) : '';

        $avail     = $num('total_funds_available');
        $funded    = $num('total_funded_to_date');
        $balance   = $num('balance', max(0, $avail - $funded));
        $payments  = $num('payments_outstanding');
        $thisReq   = $num('this_request', $amount);
        $totalPaid = $num('total_payments', $payments + $thisReq);

        $particulars = array_values(array_filter(array_map(
            fn ($l) => PrintService::text($l),
            preg_split('/\R/', (string) ($p['particulars'] ?? $p['description'] ?? $p['project_description'] ?? ''))
        ), fn ($l) => $l !== ''));

        // Regional office's RFA (issuing_office REGIONAL — old REGIONAL gen_rfa_docx.js):
        // "RFA Form No. <variant>" top right, a bold "Request for Fund Allotment" line
        // above the particulars, which print bold with "Re:" on the first.
        $regional = strtoupper(PrintService::text($p['issuing_office'] ?? '')) === 'REGIONAL';
        if ($regional && $particulars) {
            $particulars[0] = 'Re:  ' . $particulars[0];
        }

        $fs  = is_array($p['funding_source'] ?? null) ? $p['funding_source'] : [];
        $flag = fn (string $k) => !in_array($fs[$k] ?? false, [false, 'false', 0, '0', '', null], true);

        // Part II rows: ['text', label] or ['field', label, value, prefix].
        $partA = [
            ['text', 'a. Total estimated cost for the'],
            ['project', PrintService::text($p['project_name'] ?? '')],
            ['field', '   Project', $show($num('total_estimated_cost')), 'P'],
            ['text', 'b. Total funds made available'],
            ['field', '   for the year amount to', $show($avail), ''],
            ['text', 'c. To date, contracted and/or'],
            ['text', '   planned works had been'],
            ['field', '   funded in the amount of', $show($funded), ''],
            ['text', 'd. Balance of funds allocated'],
            ['field', '   (b – c) .', $show($balance), ''],
            ['text', 'e. Payments & outstanding'],
            ['field', '   Payable to date .', $show($payments), ''],
            ['field', 'f. This request, if approved', $show($thisReq), ''],
            ['text', 'g. Total payments for project'],
            ['field', '   to date (e + f)', $show($totalPaid), 'P'],
        ];
        $partB = [
            ['text', 'h. Total Contract Cost/RIV/PO'],
            ['field', '   Included in ( c )', $show($num('total_contract_cost', $amount)), 'P'],
            ['text', 'i. Adjustment'],
            ['field', '   ● Additive', $show($num('adjustment_additive')), ''],
            ['field', '   ● Deductive', $show($num('adjustment_deductive')), ''],
            ['text', 'j. Total allotment for this'],
            ['text', '   Contract/RIV/PO'],
            ['field', '   ', $show($num('total_allotment', $amount)), ''],
        ];

        return [
            'rfa_no'        => $rfaNo,
            'issuing_office'   => $regional ? 'REGIONAL' : 'FMD',
            'form_no'          => $regional ? 'RFA Form No. ' . $key : '',
            'desc_heading'     => $regional ? 'Request for Fund Allotment' : '',
            'particulars_bold' => $regional,
            'filled_out_by' => PrintService::text($p['filled_out_by'] ?? '') ?: $variant['filled_out_by'],
            'rfa_type'      => PrintService::text($p['rfa_type'] ?? '') ?: $variant['type'],
            'payee'         => preg_replace('/\s*\([^)]*\)\s*$/', '', PrintService::text($p['payee_name'] ?? '')),
            'date'          => PrintService::text($p['date'] ?? '') ?: date('F d, Y'),
            'particulars'   => $particulars,
            'amount'        => PrintService::money($amount),
            'cert'          => [
                'name'   => PrintService::text($p['cert_correct_name'] ?? ''),
                'role'   => $isStaff ? '(Group/ Department Manager)' : '(Regional/ /District Manager)',
                'title'  => PrintService::text($p['cert_correct_title'] ?? ''),
                'office' => PrintService::text($p['cert_correct_office'] ?? ''),
            ],
            'part2_by'      => $isStaff ? 'Financial Management Department' : 'Finance Unit',
            'part_a'        => $partA,
            'part_b'        => $partB,
            'funding'       => [
                'subsidy'    => $flag('subsidy'),
                'equity'     => $flag('equity'),
                'corporate'  => $flag('corporate_receipts'),
                'saro_no'    => PrintService::text($fs['saro_no'] ?? ''),
                'saro_date'  => PrintService::text($fs['saro_date'] ?? ''),
                'or_no'      => PrintService::text($fs['or_no'] ?? ''),
                'or_date'    => PrintService::text($fs['or_date'] ?? ''),
                'agency'     => PrintService::text($fs['agency_name'] ?? ''),
            ],
            'cert_funds'    => [
                'name'  => PrintService::text($p['certified_funds_name'] ?? ''),
                'title' => PrintService::text($p['certified_funds_title'] ?? '') ?: 'Chief Corporate Accountant',
            ],
            // As on the old FMD form: the auto-assigned FMD Department Manager
            // prints under RECOMMENDING APPROVAL; APPROVED stays blank unless sent.
            // A regional office's RFA is recommended by its own Finance Unit Head —
            // only recommending_name, never the FMD Department Manager.
            'recommending'  => PrintService::text($p['recommending_name'] ?? '')
                ?: ($regional ? '' : (PrintService::resolveSignatoryName('FMD Department Manager') ?? '')),
            'approved'      => PrintService::text($p['approved_name'] ?? ''),
            'file_base'     => preg_match('/^RFA/i', $rfaNo)
                ? preg_replace('/[^A-Za-z0-9\-]/', '_', $rfaNo)
                : 'RFA-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', $rfaNo) ?: 'DRAFT'),
        ];
    }

    /**
     * RFA on legal paper (612 × 1008 pt, 0.5in sides → 540pt text width).
     * $media as CbaPrintService::html(). Mirrors scripts/gen_rfa_docx.js.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $sym = static fn (string $s) => '<span class="sym">' . $s . '</span>';   // ₱ ☐ ☒ — not in Helvetica
        $chk = static fn (bool $on) => '<span class="sym">' . ($on ? '☒' : '☐') . '</span>';
        $blank = static fn (string $v, int $n = 20) => $v !== '' ? $v : str_repeat('_', $n);

        // Part II rows — a field's value is a 14-character underlined slot.
        $rows = function (array $list) use ($e): string {
            $out = '';
            foreach ($list as $row) {
                if ($row[0] === 'text') {
                    $out .= '<div class="r">' . $e($row[1]) . '</div>';
                } elseif ($row[0] === 'project') {
                    $out .= '<div class="r">   <u>' . $e($row[1] !== '' ? $row[1] : str_repeat('_', 12)) . '</u></div>';
                } else {
                    [, $label, $value, $prefix] = $row;
                    $slot  = $value === '' ? str_repeat('_', 14) : str_repeat('_', max(0, 14 - strlen($value))) . $value;
                    $small = $value !== '' && strlen(str_replace(',', '', $value)) > 12;
                    $label = str_replace('●', '<span class="sym">●</span>', $e($label));   // ● not in Helvetica
                    $out  .= '<table class="fld"><tr><td class="l">' . $label . '</td><td class="v">' . $e($prefix)
                        . '<u' . ($small ? ' class="sm"' : '') . '>' . $e($slot) . '</u></td></tr></table>';
                }
            }
            return $out;
        };

        // Particulars: a table row can't split across PDF pages, so every line
        // but the last is a plain (splittable) block; the last line shares a row
        // with the amount — unless it is very long, then the amount gets its own row.
        $amountCell = '<b>' . $sym('₱') . ' ' . $e($m['amount']) . '</b>';
        $desc  = '<table class="desc"><tr><td>DESCRIPTION/NATURE OF REQUEST</td><td class="amt"><b>AMOUNT</b></td></tr></table>';
        if (($m['desc_heading'] ?? '') !== '') {
            $desc .= '<div class="dline"><b>' . $e($m['desc_heading']) . '</b></div>';   // regional form
        }
        $ln    = static fn (string $v) => !empty($m['particulars_bold']) ? '<b>' . $e($v) . '</b>' : $e($v);
        $lines = $m['particulars'];
        if (!$lines) {
            $desc .= '<table class="desc">' . str_repeat('<tr><td>&nbsp;</td><td></td></tr>', 4) . '</table>';
        } else {
            $last = array_pop($lines);
            foreach ($lines as $line) {
                $desc .= '<div class="dline">' . $ln($line) . '</div>';
            }
            $desc .= mb_strlen($last) > self::RFA_AMOUNT_ROW_MAX
                ? '<div class="dline">' . $ln($last) . '</div><table class="desc"><tr><td></td><td class="amt">' . $amountCell . '</td></tr></table>'
                : '<table class="desc"><tr><td>' . $ln($last) . '</td><td class="amt">' . $amountCell . '</td></tr></table>';
        }

        $c  = $m['cert'];
        $fs = $m['funding'];
        $nameLine = static fn (string $name) => $name !== ''
            ? '<b><u>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</u></b>'
            : str_repeat('_', 20);

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 540, 100, 21.6, 6);
        $pageCss = $media === 'print'
            ? '@page{size:legal portrait;margin:' . $top . 'pt 36pt 21.6pt 36pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:1008pt;margin:0 auto;padding:21.6pt 36pt;box-sizing:border-box;'
              . 'background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['rfa_no'] ?: 'RFA') . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:11pt;color:#000;line-height:1.12;}'
            . 'p{margin:0;}table{border-collapse:collapse;}td{padding:0;vertical-align:top;}'
            . '.sym{font-family:"DejaVu Sans",sans-serif;line-height:1;}'
            . '.hdr{text-align:center;margin-bottom:6pt;}'
            . '.hdr>img{width:auto !important;height:auto;max-width:540pt;max-height:100pt;}'
            . '.ul{text-decoration:underline;}.j{text-align:justify;}'
            . '.type{font-weight:bold;border-bottom:.6pt solid #000;padding:4pt 0 3pt;margin:4pt 0 4pt;text-align:justify;white-space:pre-wrap;}'
            . '.rule{border-bottom:.6pt solid #000;margin:0 0 5pt;}'
            . '.dbl{border-bottom:2.2pt double #000;margin:0 0 5pt;}'
            . '.desc{width:432pt;}.desc td{text-align:justify;vertical-align:bottom;}.desc td.amt{text-align:right;white-space:nowrap;padding-left:10pt;}'
            . '.dline{width:345pt;text-align:justify;}'
            . '.cc td{text-align:justify;}.cc td.lbl{width:108pt;text-align:left;white-space:nowrap;padding-right:6pt;}'
            . '.half{width:100%;}.half td{width:50%;text-align:center;}'
            . '.p2{width:532pt;}.p2 h4{font-size:11pt;font-weight:normal;text-decoration:underline;text-align:center;margin:4pt 0 5pt;}'
            . '.r{white-space:pre;}'
            . '.fld{width:232.5pt;margin-bottom:2pt;}.fld td.l{white-space:pre;}.fld td.v{text-align:right;white-space:nowrap;}'
            . '.sm{font-size:9pt;}'
            . '.fs{line-height:1.3;}.fs td{white-space:nowrap;}.c{text-align:center;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 540, 'RFA') . '</div>', '')
            . (($m['form_no'] ?? '') !== '' ? '<p style="text-align:right;font-weight:bold;">' . $e($m['form_no']) . '</p>' : '')
            . '<p class="ul j">Part I: To be accomplished by Requesting Unit</p>'
            . '<p class="j" style="margin-top:12pt;">REQUEST FOR FUND ALLOTMENT NO. (To be filled-out by ' . $e($m['filled_out_by']) . ') '
            . ($m['rfa_no'] !== '' ? '<b><u>' . $e($m['rfa_no']) . '</u></b>' : '____________') . '</p>'
            . '<p class="j">(For Job Orders, Contracts, Loan Agreements, Project Proposals,</p>'
            . '<p class="j"> Change Orders, Price Escalations, etc.)</p>'
            . '<p class="type">   ' . $e($m['rfa_type']) . '</p>'
            . '<table style="width:100%;margin:12pt 0 6pt;"><tr>'
            . '<td style="width:360pt;">ALLOTMENT TO BE MADE IN FAVOR OF:&nbsp; ' . ($m['payee'] !== '' ? '<b><u>' . $e($m['payee']) . '</u></b>' : str_repeat('_', 20)) . '</td>'
            . '<td>DATE:&nbsp; <b><u>' . $e($m['date']) . '</u></b></td></tr></table>'
            . '<div class="rule"></div>'
            . $desc
            . '<p style="margin-bottom:4pt;">&nbsp;</p>'
            . '<div class="rule"></div>'
            . '<table class="cc"><tr><td class="lbl">CERTIFIED CORRECT:</td><td>Allotment/Obligation item necessary, lawful and incurred under my direct supervision chargeable against my regional/department/district budget, prices just and reasonable and not in excess of current rates locality.</td></tr></table>'
            . '<p style="margin-bottom:6pt;">&nbsp;</p>'
            . '<table class="half"><tr><td></td><td>' . $nameLine($c['name'])
            . '<div>' . $e($c['role']) . '</div><div>' . $e($c['title']) . '</div><div>' . $e($c['office']) . '</div></td></tr></table>'
            . '<p>&nbsp;</p><div class="dbl"></div>'
            . '<p class="ul" style="margin-bottom:6pt;">Part II: To be accomplished by the ' . $e($m['part2_by']) . '</p>'
            . '<table class="p2"><tr>'
            . '<td style="width:243pt;"><h4>A.  Program/Project Reference</h4>' . $rows($m['part_a']) . '</td>'
            . '<td style="width:13.5pt;"></td>'
            . '<td style="width:275.5pt;"><h4>B.  Allotment Reference</h4>' . $rows($m['part_b']) . '</td>'
            . '</tr></table>'
            . '<p class="ul" style="margin:6pt 0 4pt 36.7pt;">C. Funding Source:</p>'
            . '<table class="fs">'
            . '<tr><td style="width:36.7pt;"></td><td style="width:148.3pt;">NG Support</td><td style="width:145pt;"></td><td style="width:210pt;">Others:</td></tr>'
            . '<tr><td></td><td></td><td></td><td>Name of Agency: ' . $e($blank($fs['agency'], 10)) . '</td></tr>'
            . '<tr><td></td><td>Subsidy&nbsp; ' . $chk($fs['subsidy']) . '</td><td></td><td>Equity&nbsp; ' . $chk($fs['equity']) . '</td></tr>'
            . '<tr><td></td><td class="c ul">SARO</td><td class="c ul">OR</td><td class="c ul">Corporate Receipts</td></tr>'
            . '<tr><td></td><td>No.: <u>' . $e($blank($fs['saro_no'], 14)) . '</u></td><td>No.: ' . $e($blank($fs['or_no'], 14)) . '</td><td class="c">' . $chk($fs['corporate']) . '</td></tr>'
            . '<tr><td></td><td>Date: <u>' . $e($blank($fs['saro_date'], 14)) . '</u></td><td>Date: ' . $e($blank($fs['or_date'], 14)) . '</td><td></td></tr>'
            . '</table>'
            . '<div class="c" style="margin-top:8pt;margin-bottom:10pt;">CERTIFIED FUNDS AVAILABLE:</div>'
            . '<div class="c"><b>_______________________</b></div>'
            . '<div class="c">' . $e($m['cert_funds']['name']) . '&nbsp;</div>'
            . '<div class="c" style="margin-bottom:6pt;">' . $e($m['cert_funds']['title']) . '</div>'
            . '<div class="dbl" style="margin-top:6pt;"></div>'
            . '<table class="half"><tr>'
            . '<td><div style="margin-bottom:22pt;"><b>RECOMMENDING APPROVAL:</b></div>'
            . $nameLine($m['recommending']) . '<div>(Finance Unit Head)</div></td>'
            . '<td><div style="margin-bottom:22pt;"><b>APPROVED:</b></div>'
            . $nameLine($m['approved']) . '<div>(Regional Manager)</div></td>'
            . '</tr></table>'
            . PrintService::bodyClose($media, '');
    }
}
