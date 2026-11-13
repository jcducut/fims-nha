<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * IAR (Inspection and Acceptance Report) print form — the model (every printed
 * word) and the HTML (preview + PDF). Ported from the old ACCOUNTING (PIS)
 * IarPdfService and the browser-print route in PmsClearingAccounts.php (the same
 * form; no other department printed an IAR). There is no inspection module in
 * this repo yet, so it prints from the payload — the old
 * PmsClearingAccountsService::getIarData() shape.
 *
 * Letter paper, Times New Roman: INSPECTION AND ACCEPTANCE REPORT; Supplier + IAR
 * No.; P.O. No. + date, S.I. No. + date; Requisitioning Office/Dept.; items (Stock
 * No., Unit Price, Description, Quantity — "*** Nothing Follows ***", at least
 * MIN_ITEM_ROWS rows); the two "Date Inspected" checks + complete / partial
 * delivery; three signatories. No letterhead by default (the old form had none),
 * no footer line.
 *
 * Printed through PrintService::print('IAR', …); DOCX: scripts/gen_iar_docx.js.
 */
class IarPrintService
{
    // The old form always shows at least this many item rows.
    public const MIN_ITEM_ROWS = 15;

    // The three signatories and their default labels (the old ACCOUNTING form).
    private const SIGNATORIES = [
        'inspector'    => 'Inspector, Accounting Rep.',
        'div_manager'  => 'Div. Mgr., MMD-GSD',
        'dept_manager' => 'Dept. Manager, GSD',
    ];

    /**
     * IAR model. Payload (all optional):
     *   iar_number, supplier_name, po_number, po_date, si_number, si_date,
     *   requesting_department, items [{ stock_no?, unit_cost, description,
     *   quantity }], date_inspected, delivery ('complete' default | 'partial') or
     *   is_complete_delivery (bool), inspector_name / _label,
     *   div_manager_name / _label, dept_manager_name / _label.
     * Complete delivery checks "Inspected, accepted … specification" (its date)
     * and "Complete delivery"; partial checks "Inspected, verified … quantity"
     * (its date) and "Partial delivery".
     */
    public function model(array $p): array
    {
        $t    = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $date = static fn (string $k) => $t($k) === '' ? '' : PrintService::longDate($t($k));

        $items = [];
        foreach (array_values(is_array($p['items'] ?? null) ? $p['items'] : []) as $it) {
            $qty  = PrintService::amount($it['quantity'] ?? $it['quantity_delivered'] ?? null);
            $cost = PrintService::amount($it['unit_cost'] ?? null);
            $items[] = [
                'stock_no'  => PrintService::text($it['stock_no'] ?? ''),
                'unit_cost' => $cost > 0 ? PrintService::money($cost) : '',
                'desc'      => trim((string) ($it['description'] ?? $it['item_description'] ?? '')),
                'qty'       => $qty > 0 ? rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.') : '',
            ];
        }

        $complete = isset($p['is_complete_delivery'])
            ? !in_array($p['is_complete_delivery'], [false, 'false', 0, '0', ''], true)
            : strtolower($t('delivery')) !== 'partial';
        $inspected = $date('date_inspected');

        $signatories = [];
        foreach (self::SIGNATORIES as $key => $label) {
            $signatories[] = [
                'name'  => $t("{$key}_name"),
                'label' => $t("{$key}_label") ?: $label,
            ];
        }

        $iarNumber = $t('iar_number');

        return [
            'iar_number'   => $iarNumber,
            'supplier'     => $t('supplier_name'),
            'po_number'    => $t('po_number'),
            'po_date'      => $date('po_date'),
            'si_number'    => $t('si_number'),
            'si_date'      => $date('si_date'),
            'department'   => $t('requesting_department'),
            'items'        => $items,
            'min_rows'     => self::MIN_ITEM_ROWS,
            'complete'     => $complete,
            // each "Date Inspected" line carries the date only when its box is checked
            'date_verified' => $complete ? '' : $inspected,
            'date_accepted' => $complete ? $inspected : '',
            'signatories'  => $signatories,
            'header_parts' => null,
            'footer_text'  => '',
            'file_base'    => 'IAR-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', preg_replace('/^IAR-/i', '', $iarNumber !== '' ? $iarNumber : $t('po_number'))) ?: 'DRAFT'),
        ];
    }

    /**
     * Letter paper (612 × 792pt), 0.55in margins (532.8pt), Times New Roman.
     * Mirrors scripts/gen_iar_docx.js. $media as CbaPrintService::html().
     * Dompdf ignores <colgroup> widths, so they sit on the first row's cells.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nl  = static fn ($v) => nl2br(htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), false);
        $w   = static fn (float $pct, string $css = '') => ' style="width:' . $pct . '%;' . $css . '"';   // one style attribute per cell
        $val = static fn (string $v) => $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : '&nbsp;';
        // ☒ / ☐ — not in Times-Roman for Dompdf; DejaVu Sans has both
        $box = static fn (bool $on) => '<span class="box">' . ($on ? '&#9746;' : '&#9744;') . '</span>';

        $rows = '';
        foreach ($m['items'] as $it) {
            $rows .= '<tr><td class="c">' . $e($it['stock_no']) . '</td><td class="r">' . $e($it['unit_cost']) . '</td>'
                . '<td>' . $nl($it['desc']) . '</td><td class="c">' . $e($it['qty']) . '</td></tr>';
        }
        $filled = count($m['items']);
        if ($filled > 0) {
            $rows .= '<tr><td></td><td></td><td class="c i">*** Nothing Follows ***</td><td></td></tr>';
            $filled++;
        }
        $rows .= str_repeat('<tr>' . str_repeat('<td>&nbsp;</td>', 4) . '</tr>', max(0, $m['min_rows'] - $filled));

        $sigs = '';
        foreach ($m['signatories'] as $s) {
            $sigs .= '<td' . $w(33.33) . '><div class="sig-name">' . $val($s['name']) . '</div><div class="sig-label">' . $e($s['label']) . '</div></td>';
        }

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 532.8, 100, 39.6, 6);
        $pageCss = $media === 'print'
            ? '@page{size:letter portrait;margin:' . $top . 'pt 39.6pt 39.6pt 39.6pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:792pt;margin:0 auto;padding:39.6pt;box-sizing:border-box;'
              . 'background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:"Times New Roman",Times,serif;font-size:10pt;color:#000;line-height:1.3;}'
            . 'p{margin:0;}table{width:100%;border-collapse:collapse;table-layout:fixed;}td,th{padding:2pt 4pt;vertical-align:top;}'
            . '.hdr{text-align:center;margin-bottom:6pt;}.hdr>img{width:auto !important;height:auto;max-width:532.8pt;max-height:100pt;}'
            . '.c{text-align:center;}.r{text-align:right;}.b{font-weight:bold;}.i{font-style:italic;}'
            . '.title{text-align:center;font-size:15pt;font-weight:bold;margin:0 0 16pt;}'
            . '.info td{padding:3pt 2pt 1pt;vertical-align:bottom;white-space:nowrap;}'
            . '.info td.v{border-bottom:.75pt solid #000;white-space:normal;}'
            . '.items{border:.75pt solid #000;margin:12pt 0;}'
            . '.items th{border:.75pt solid #000;font-size:11pt;font-weight:bold;text-align:center;padding:3pt;}'
            . '.items td{border-left:.75pt solid #000;border-right:.75pt solid #000;font-size:10pt;}'
            . '.checks td{font-size:9.5pt;padding:2pt 6pt;}'
            . '.box{font-family:"DejaVu Sans",sans-serif;font-size:12pt;padding-right:4pt;}'
            . '.uline{border-bottom:.75pt solid #000;display:inline-block;min-width:90pt;}'
            . '.sigs td{text-align:center;padding:30pt 6pt 0;font-size:10pt;}'
            . '.sig-name{border-bottom:.75pt solid #000;font-weight:bold;padding-bottom:2pt;}'
            . '.sig-label{font-size:8.5pt;padding-top:2pt;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 532.8, 'IAR') . '</div>', '')
            . '<p class="title">INSPECTION AND ACCEPTANCE REPORT</p>'
            . '<table class="info"><tr><td class="b"' . $w(9) . '>Supplier:</td><td class="v"' . $w(53) . '>' . $val($m['supplier']) . '</td>'
            . '<td' . $w(3) . '></td><td class="b"' . $w(10) . '>IAR No.:</td><td class="v"' . $w(25) . '>' . $val($m['iar_number']) . '</td></tr></table>'
            . '<table class="info"><tr><td class="b"' . $w(9) . '>P.O. No.</td><td class="v"' . $w(19) . '>' . $val($m['po_number']) . '</td>'
            . '<td class="b"' . $w(6) . '>&nbsp;Date:</td><td class="v"' . $w(18) . '>' . $val($m['po_date']) . '</td>'
            . '<td class="b"' . $w(8) . '>&nbsp;S.I. No.</td><td class="v"' . $w(16) . '>' . $val($m['si_number']) . '</td>'
            . '<td class="b"' . $w(6) . '>&nbsp;Date:</td><td class="v"' . $w(18) . '>' . $val($m['si_date']) . '</td></tr></table>'
            . '<table class="info" style="margin-bottom:6pt;"><tr><td class="b"' . $w(25) . '>Requisitioning Office/Dept.:</td>'
            . '<td class="v"' . $w(45) . '>' . $val($m['department']) . '</td><td' . $w(30) . '></td></tr></table>'
            . '<table class="items"><tr><th' . $w(14) . '>STOCK NO.</th><th' . $w(18) . '>UNIT PRICE</th><th' . $w(54) . '>DESCRIPTION</th><th' . $w(14) . '>QUANTITY</th></tr>'
            . $rows . '</table>'
            . '<table class="checks"><tr>'
            . '<td' . $w(36) . '><b>Date Inspected:</b> <span class="uline">' . $val($m['date_verified']) . '</span><br><br>'
            . $box(!$m['complete']) . 'Inspected, verified and found in order as to quantity.</td>'
            . '<td' . $w(36) . '><b>Date Inspected:</b> <span class="uline">' . $val($m['date_accepted']) . '</span><br><br>'
            . $box($m['complete']) . 'Inspected, accepted and found to be in accordance with the specification.</td>'
            . '<td' . $w(28) . '><br><br>' . $box($m['complete']) . 'Complete delivery<br>' . $box(!$m['complete']) . 'Partial delivery</td>'
            . '</tr></table>'
            . '<table class="sigs"><tr>' . $sigs . '</tr></table>'
            . PrintService::bodyClose($media, '');
    }
}
