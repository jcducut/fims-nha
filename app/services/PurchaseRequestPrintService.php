<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * PR (Purchase Requisition) print form — the model (every printed word) and the
 * HTML (preview + PDF). One form for every department: merged from the old
 * REGIONAL gen_pr_docx.js and the GSD / REGIONAL PurchaseRequestPdfService (the
 * same form; FMD and GLOBAL only printed the screen). There is no PR module in
 * this repo yet, so it prints from the payload.
 *
 * Legal paper, Times New Roman 9pt: PURCHASE REQUISITION + P.R. No.; "To the
 * Purchasing Officer" + date; items (Warehouse Stock on Hand, QTY, UNIT,
 * DESCRIPTION, Dept./Office Qty. on Hand, Estimated Cost per unit / total — at
 * least MIN_ITEM_ROWS rows), NOTE, Total; END USER/PURPOSE; CHARGE TO + requested
 * delivery date; the FMD part (A. Program/Project Reference a–g, B. Allotment
 * Reference h–n); Request for fund allotment No.; four signatories.
 * Uses the NHA letterhead (no footer line).
 *
 * Printed through PrintService::print('PR', …); DOCX: scripts/gen_pr_docx.js.
 */
class PurchaseRequestPrintService
{
    // The old form always shows at least this many item rows.
    public const MIN_ITEM_ROWS = 10;

    // The four signatory boxes and their default roles (the old GSD / REGIONAL form).
    private const SIGNATORY_ROLES = [
        1 => 'Transport Operations Supvr. GSD',
        2 => 'Officer-In-Charge, GSD',
        3 => 'Dept. Manager, FMD',
        4 => 'Group Manager, MSG',
    ];

    /**
     * PR model. Payload (all optional):
     *   pr_number, pr_date, purpose, department_name (or charge_to), delivery_date,
     *   items [{ item_description, unit_of_measure, quantity_requested, unit_cost,
     *            total_cost?, warehouse_stock?, dept_qty_on_hand? }], total_amount
     *   (default: the items' sum), note (default "Included Tax"),
     *   FMD part — project_description (or project_name), total_estimate_cost (a),
     *   total_funds_available (b), funded_to_date (c), fund_balance (d),
     *   outstanding_payable (e), request_if_approved (f), total_payments_project (g),
     *   total_contract_cost (h), additional_allotment (i), total_allotment (j),
     *   prior_payment (k), this_payment (l), total_payments (m),
     *   balance_to_complete (n) — a, h, j and n default to the total;
     *   fund_allotment_no (or allocation_number), sig1_name … sig4_name,
     *   sig1_role … sig4_role.
     */
    public function model(array $p): array
    {
        $items = [];
        $sum   = 0.0;
        foreach (array_values(is_array($p['items'] ?? null) ? $p['items'] : []) as $it) {
            $qty   = PrintService::amount($it['quantity_requested'] ?? $it['quantity'] ?? null);
            $cost  = PrintService::amount($it['unit_cost'] ?? null);
            $total = isset($it['total_cost']) && $it['total_cost'] !== '' ? PrintService::amount($it['total_cost']) : round($qty * $cost, 2);
            $sum  += $total;
            $items[] = [
                'warehouse' => PrintService::text($it['warehouse_stock'] ?? ''),
                'qty'       => $qty > 0 ? rtrim(rtrim(number_format($qty, 4, '.', ''), '0'), '.') : '',
                'unit'      => PrintService::text($it['unit_of_measure'] ?? $it['unit'] ?? ''),
                'desc'      => trim((string) ($it['item_description'] ?? $it['description'] ?? '')),
                'dept_qty'  => PrintService::text($it['dept_qty_on_hand'] ?? ''),
                'unit_cost' => $cost > 0 ? PrintService::money($cost) : '',
                'amount'    => $total > 0 ? PrintService::money($total) : '',
            ];
        }
        $total = isset($p['total_amount']) && $p['total_amount'] !== '' ? PrintService::amount($p['total_amount']) : $sum;

        // FMD part: [letter, label lines, value, with ₱] — blank values print an empty line.
        $v = static function (string $key, ?float $default = null) use ($p): float {
            $x = PrintService::amount($p[$key] ?? null);
            return $x > 0 ? $x : (float) ($default ?? 0);
        };
        $money = static fn (float $x) => $x > 0 ? PrintService::money($x) : '';
        $partA = [
            ['a.', ['Total estimate cost for'], $money($v('total_estimate_cost', $total)), true],
            ['b.', ['For this project total funds', 'made available for the year', 'amount to'], $money($v('total_funds_available')), true],
            ['c.', ['To date, contracted and/or', 'planned works had been', 'funded in the amount of'], $money($v('funded_to_date')), false],
            ['d.', ['Balance of funds allocated (b-c)'], $money($v('fund_balance')), false],
            ['e.', ['Payments and outstanding payable', 'to date on this project'], $money($v('outstanding_payable')), true],
            ['f.', ['This request if approved'], $money($v('request_if_approved')), true],
            ['g.', ['Total Payments for project to date', '(e+f)'], $money($v('total_payments_project')), true],
        ];
        $partB = [
            ['h.', ['Total contract cost/RIV/PO', 'include -in (c)'], $money($v('total_contract_cost', $total)), true],
            ['i.', ['This additional allotment', 'if approved'], $money($v('additional_allotment')), true],
            ['j.', ['Total allotment for this', 'contract/RIV/PO (h+i)'], $money($v('total_allotment', $total)), true],
            ['k.', ['Prior payment on this contract/', 'RIV/PO'], $money($v('prior_payment')), true],
            ['l.', ['This payment, if approved'], $money($v('this_payment')), true],
            ['m.', ['Total payments (k+l)'], $money($v('total_payments')), true],
            ['n.', ['Balance to complete (j-m)'], $money($v('balance_to_complete', $total)), true],
        ];

        $signatories = [];
        foreach (self::SIGNATORY_ROLES as $n => $role) {
            $signatories[] = [
                'name' => PrintService::text($p["sig{$n}_name"] ?? ''),
                'role' => PrintService::text($p["sig{$n}_role"] ?? '') ?: $role,
            ];
        }

        $prNumber = PrintService::text($p['pr_number'] ?? '');
        $date     = PrintService::text($p['pr_date'] ?? '');
        $d        = \DateTime::createFromFormat('Y-m-d', substr($date, 0, 10));

        return [
            'pr_number'    => $prNumber,
            'date'         => $date === '' ? '' : ($d && $d->format('Y-m-d') === substr($date, 0, 10) ? $d->format('F d, Y') : $date),
            'items'        => $items,
            'min_rows'     => self::MIN_ITEM_ROWS,
            'note'         => PrintService::text($p['note'] ?? '') ?: 'Included Tax',
            'total'        => $total > 0 ? PrintService::money($total) : '',
            'purpose'      => trim((string) ($p['purpose'] ?? '')),
            'charge_to'    => PrintService::text($p['department_name'] ?? $p['charge_to'] ?? ''),
            'delivery_date' => PrintService::text($p['delivery_date'] ?? ''),
            'project'      => PrintService::text($p['project_description'] ?? $p['project_name'] ?? ''),
            'part_a'       => $partA,
            'part_b'       => $partB,
            'fund_allotment_no' => PrintService::text($p['fund_allotment_no'] ?? $p['allocation_number'] ?? ''),
            'signatories'  => $signatories,
            'header_parts' => null,
            'footer_text'  => '',
            'file_base'    => 'PR-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', preg_replace('/^PR-/i', '', $prNumber)) ?: 'DRAFT'),
        ];
    }

    /**
     * Legal paper (612 × 1008pt), 0.55in sides (532.8pt), Times New Roman 9pt.
     * Mirrors scripts/gen_pr_docx.js. $media as CbaPrintService::html().
     * Dompdf ignores <colgroup> widths, so they sit on the first row's cells.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nl  = static fn ($v) => nl2br(htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), false);
        $php = '<span class="sym">₱</span>&nbsp;';   // ₱ — not in Times-Roman for Dompdf
        $w   = static fn (float $pct, string $css = '') => ' style="width:' . $pct . '%;' . $css . '"';   // one style attribute per cell

        $rows = '';
        foreach ($m['items'] as $it) {
            $rows .= '<tr><td class="c">' . $e($it['warehouse']) . '</td><td class="c">' . $e($it['qty']) . '</td><td class="c">' . $e($it['unit']) . '</td>'
                . '<td>' . $nl($it['desc']) . '</td><td class="c">' . $e($it['dept_qty']) . '</td>'
                . '<td class="r">' . $e($it['unit_cost']) . '</td><td class="r">' . $e($it['amount']) . '</td></tr>';
        }
        $blank = '<tr>' . str_repeat('<td>&nbsp;</td>', 7) . '</tr>';
        $rows .= str_repeat($blank, max(0, $m['min_rows'] - count($m['items'])));
        $rows .= '<tr><td></td><td></td><td></td><td class="c"><b><u>NOTE: </u></b><i>' . $e($m['note']) . '</i></td><td></td><td></td><td></td></tr>'
            . $blank . $blank
            . '<tr class="tot"><td colspan="4"></td><td class="r b" style="font-size:10pt;">Total:</td><td></td>'
            . '<td class="r b" style="font-size:10pt;">' . $e($m['total']) . '</td></tr>';

        $cellLines = static fn (string $letter, array $lines) => $letter . '&nbsp;&nbsp;&nbsp;' . implode('<br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;', array_map(
            static fn ($l) => htmlspecialchars($l, ENT_QUOTES, 'UTF-8'), $lines));
        $fmd = '<tr><td colspan="2" class="c b"' . $w(50) . '>A. Program/Project Reference</td><td colspan="2" class="c b"' . $w(50) . '>B. Allotment Reference</td></tr>';
        foreach ($m['part_a'] as $i => $a) {
            $b = $m['part_b'][$i];
            $aLabel = $cellLines($a[0], $a[1]) . ($i === 0 && $m['project'] !== '' ? '<br><i>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $e($m['project']) . '</i>' : '');
            $fmd .= '<tr><td' . ($i === 0 ? $w(32) : '') . '>' . $aLabel . '</td>'
                . '<td class="v"' . ($i === 0 ? $w(18) : '') . '>' . ($a[2] !== '' ? ($a[3] ? $php : '') . $e($a[2]) : '') . '</td>'
                . '<td' . ($i === 0 ? $w(32) : '') . '>' . $cellLines($b[0], $b[1]) . '</td>'
                . '<td class="v"' . ($i === 0 ? $w(18) : '') . '>' . ($b[2] !== '' ? ($b[3] ? $php : '') . $e($b[2]) : '') . '</td></tr>';
        }

        $sigNames = '';
        foreach ($m['signatories'] as $s) {
            $sigNames .= '<td' . $w(25) . '><div class="b' . ($s['name'] !== '' ? ' u' : '') . '">' . ($e($s['name']) ?: '&nbsp;') . '</div><div>' . $e($s['role']) . '</div></td>';
        }

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 532.8, 100, 39.6, 6);
        $pageCss = $media === 'print'
            ? '@page{size:legal portrait;margin:' . $top . 'pt 39.6pt 39.6pt 39.6pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:1008pt;margin:0 auto;padding:39.6pt;box-sizing:border-box;'
              . 'background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:"Times New Roman",Times,serif;font-size:9pt;color:#000;line-height:1.3;}'
            . 'p{margin:0;}table{width:100%;border-collapse:collapse;table-layout:fixed;}td,th{padding:2pt 3pt;vertical-align:top;}'
            . '.hdr{text-align:center;margin-bottom:6pt;}.hdr>img{width:auto !important;height:auto;max-width:532.8pt;max-height:100pt;}'
            . '.sym{font-family:"DejaVu Sans",sans-serif;font-size:7.5pt;}'
            . '.c{text-align:center;}.r{text-align:right;}.b{font-weight:bold;}.u{text-decoration:underline;}.i{font-style:italic;}'
            . '.title{text-align:center;font-size:13pt;font-weight:bold;text-decoration:underline;margin:2pt 0;}'
            . '.items th,.items td{border:.5pt solid #000;font-size:8pt;vertical-align:middle;}'
            . '.items th{font-weight:bold;text-align:center;}'
            . '.items tr > :first-child{border-left:none;}.items tr > :last-child{border-right:none;}'
            . '.items tr.tot td{border:none;}'
            . '.fmd td{font-size:8pt;}.fmd td.v{border-bottom:.5pt solid #000;text-align:right;vertical-align:bottom;}'
            . '.sigs td{border:.5pt solid #000;text-align:center;font-size:8pt;padding:3pt;}'
            . '.sigs tr > :first-child{border-left:none;}.sigs tr > :last-child{border-right:none;}'
            . '.sigs tr.space td{height:36pt;border-bottom:none;}.sigs tr.names td{border-top:none;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 532.8, 'PR') . '</div>', '')
            . '<p class="title">PURCHASE REQUISITION</p>'
            . '<table style="margin-bottom:2pt;"><tr><td' . $w(60) . '></td><td' . $w(40, 'font-size:10pt;') . '><b>P.R. NO.</b>&nbsp;&nbsp;&nbsp;<u>' . $e($m['pr_number']) . '</u></td></tr></table>'
            . '<table style="margin-bottom:4pt;"><tr><td' . $w(60) . '><div class="b" style="font-size:10pt;">To the Purchasing Officer</div>'
            . '<div class="i">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Please Purchase the following item(s)</div></td>'
            . '<td' . $w(40, 'font-size:10pt;') . '><b>Date</b>&nbsp;&nbsp;&nbsp;&nbsp;' . $e($m['date']) . '</td></tr></table>'
            . '<table class="items"><tr>'
            . '<th rowspan="2"' . $w(10.28) . '>Warehouse<br>Stock on<br>Hand</th><th rowspan="2"' . $w(5.99) . '>QTY</th><th rowspan="2"' . $w(5.99) . '>UNIT</th>'
            . '<th rowspan="2"' . $w(41.59) . '>DESCRIPTION</th><th rowspan="2"' . $w(10.65) . '>Dept./Office<br>Qty. on<br>Hand</th>'
            . '<th colspan="2"' . $w(25.5) . '>Estimated Cost</th></tr>'
            . '<tr><th' . $w(12.75) . '>Per<br>Unit</th><th' . $w(12.75) . '>Total<br>Amount</th></tr>'
            . $rows . '</table>'
            . '<p class="b" style="font-size:10pt;margin:4pt 0 2pt;">END USER/PURPOSE:</p>'
            . '<p style="padding:4pt 6pt;min-height:30pt;">' . ($nl($m['purpose']) ?: '&nbsp;') . '</p>'
            . '<table><tr><td' . $w(55) . '><div class="b" style="font-size:10pt;">CHARGE TO:</div>'
            . '<div class="b" style="font-size:10pt;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;' . $e($m['charge_to']) . '</div></td>'
            . '<td' . $w(45) . '><div>Requested</div><div>Delivery</div><div>Date</div><div>' . $e($m['delivery_date']) . '</div></td></tr></table>'
            . '<div style="border-bottom:.5pt solid #000;margin:2pt 0;"><i style="font-size:8pt;">To be accomplished by Financial Management Department</i></div>'
            . '<table class="fmd">' . $fmd . '</table>'
            . '<table style="margin:4pt 0;"><tr><td' . $w(72) . '></td><td' . $w(28, 'border:.5pt solid #000;font-size:7pt;') . '>'
            . '<div>Request for fund allotment</div><div class="b">No.&nbsp;&nbsp;' . $e($m['fund_allotment_no']) . '</div></td></tr></table>'
            . '<table class="sigs" style="margin-top:12pt;"><tr class="space">' . str_repeat('<td>&nbsp;</td>', 4) . '</tr>'
            . '<tr class="names">' . $sigNames . '</tr></table>'
            . PrintService::bodyClose($media, '');
    }
}
