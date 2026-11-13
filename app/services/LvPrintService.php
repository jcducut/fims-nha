<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * LV (Liquidation Voucher) print form — the model (every printed word) and the
 * HTML (preview + PDF). One form for every department, merged from:
 *   - the old REGIONAL gen_lv_docx.js + LiquidationVoucherServices::buildLvHtml()
 *     (the full form: title box, particulars, cash-advance breakdown, cash
 *     advance / refund lines, obligation + accounting entries, certifications
 *     A–C, and the "Summary of Petty Cash" second page), and
 *   - the old FMD gen_liquidation_docx.js (text for pre-printed LV stock; its own
 *     content is the obligation ledger — Dept/Proj, Source of Fund, Budget,
 *     Obligation, Balance, Payments, This Request, Total Payments — printed
 *     here in the OBLIGATION column).
 * ACCOUNTING's DCD pages print cash-advance registers, not the LV. There is no
 * LV module in this repo yet, so it prints from the payload.
 *
 * Legal paper, Times New Roman 9pt; the form's own title box, no letterhead.
 * Printed through PrintService::print('LV', …); DOCX: scripts/gen_lv_docx.js.
 */
class LvPrintService
{
    // Rows the old form always shows.
    public const PARTICULAR_ROWS = 15;
    public const ENTRY_ROWS      = 15;

    // Certification boxes: [letter, certification lines, default signatory title].
    private const CERTIFICATIONS = [
        'a' => ['A', 'Expenses are necessary lawful and incurred under my direct supervision', 'Regional Manager'],
        'b' => ['B', 'Adequate available funds/budgetary allotment in the amount of:', 'OIC of FMD'],
        'c' => ['C', 'Expenditure properly certified; supporting documents complete and proper; and account codes proper', 'OIC of Accounting'],
    ];

    /**
     * LV model. Payload (all optional):
     *   lv_number, disbursing_officer, department_region,
     *   particulars — text, or lines [{ description, amount, sub? }] (sub = indented
     *     VAT-type line); gross_amount (default the lines' sum), amount_in_words
     *     (default from gross_amount), chargeability (or fund_source_code /
     *     fund_source_name),
     *   cash advance — ca_dv_number, ca_amount, prior_liquidations [{ lv_number,
     *     amount }], tax_deposit_amount, cash_refund_deposit_amount: adds the
     *     breakdown block (Cash Advance thru DV No. / Less: / prior LVs / This Liq /
     *     Tax Deposit / Cash Refund Deposit) under the particulars,
     *   refund_or_number (prints "AMOUNT REFUNDED PER OR NO. … AND DTD"), refund_date,
     *     refund_amount (default cash_refund_deposit_amount),
     *   obligation (FMD ledger) { dept_proj, fund_source, budget_ref, budget,
     *     obligation_ref, obligation, balance, payments_ref, payments,
     *     this_request_ref, this_request, total_payments },
     *   accounting_entries [{ obligation?, account_code, debit, credit }],
     *   journalized_by_date, indexed_by_date,
     *   cert_a_name / _title, cert_b_name / _title, cert_b_amount (default
     *   gross_amount), cert_c_name / _title, jev_no,
     *   pc_summary [{ type: received|less|add|balance, label, amount }] — the
     *     "Summary of Petty Cash" second page.
     */
    public function model(array $p): array
    {
        $t     = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $amt   = static fn ($v) => PrintService::amount($v);
        $money = static fn ($v) => $v === null || $v === '' ? '' : PrintService::money(PrintService::amount($v));

        // Particulars: free text (one line, the gross amount) or itemised lines.
        $lines = [];
        if (is_array($p['particulars'] ?? null)) {
            foreach ($p['particulars'] as $l) {
                $lines[] = [
                    'desc'   => trim((string) ($l['description'] ?? '')),
                    'amount' => ($l['amount'] ?? '') !== '' && $amt($l['amount']) != 0 ? $money($l['amount']) : '',
                    'sub'    => !empty($l['sub']),
                ];
            }
        }
        $sum   = is_array($p['particulars'] ?? null)
            ? array_sum(array_map(static fn ($l) => empty($l['sub']) ? PrintService::amount($l['amount'] ?? 0) : 0, $p['particulars']))
            : 0.0;
        $gross = ($p['gross_amount'] ?? '') !== '' ? $amt($p['gross_amount']) : $sum;
        if (!$lines && ($t('particulars') !== '' || $gross > 0)) {
            $lines[] = ['desc' => trim((string) ($p['particulars'] ?? '')), 'amount' => $gross > 0 ? PrintService::money($gross) : '', 'sub' => false];
        }

        $chargeability = $t('chargeability');
        if ($chargeability === '') {
            $chargeability = implode(' — ', array_filter([$t('fund_source_code'), $t('fund_source_name')]));
        }

        $refund = ($p['refund_amount'] ?? '') !== '' ? $p['refund_amount'] : ($p['cash_refund_deposit_amount'] ?? null);

        return [
            'lv_number'       => $t('lv_number'),
            'disbursing_officer' => $t('disbursing_officer'),
            'department_region'  => $t('department_region'),
            'lines'           => $lines,
            'chargeability'   => $chargeability,
            'breakdown'       => $this->breakdown($p, $gross),
            'particular_rows' => self::PARTICULAR_ROWS,
            'amount_words'    => $t('amount_in_words') !== '' ? mb_strtoupper($t('amount_in_words')) : ($gross > 0 ? mb_strtoupper(PrintService::amountInWordsCentavos($gross)) : ''),   // "… PESOS AND … CENTAVOS ONLY"
            'ca_dv_number'    => $t('ca_dv_number'),
            'ca_amount'       => $amt($p['ca_amount'] ?? 0) > 0 ? $money($p['ca_amount']) : '',
            'refund_label'    => $t('refund_or_number') !== ''
                ? 'AMOUNT REFUNDED PER OR NO. ' . $t('refund_or_number') . ' AND DTD'
                : 'AMOUNT REFUNDED PER DEPOSIT DATED:',
            'refund_date'     => $t('refund_date') === '' ? '' : PrintService::longDate($t('refund_date')),
            'refund_amount'   => $amt($refund) > 0 ? $money($refund) : '',
            'obligation'      => $this->obligationLines(is_array($p['obligation'] ?? null) ? $p['obligation'] : []),
            'entries'         => array_map(static fn ($e) => [
                'obligation' => PrintService::text($e['obligation'] ?? ''),
                'code'       => PrintService::text($e['account_code'] ?? ''),
                'debit'      => PrintService::amount($e['debit'] ?? $e['debit_amount'] ?? 0) > 0 ? $money($e['debit'] ?? $e['debit_amount']) : '',
                'credit'     => PrintService::amount($e['credit'] ?? $e['credit_amount'] ?? 0) > 0 ? $money($e['credit'] ?? $e['credit_amount']) : '',
            ], array_values(is_array($p['accounting_entries'] ?? null) ? $p['accounting_entries'] : [])),
            'entry_rows'      => self::ENTRY_ROWS,
            'journalized'     => $t('journalized_by_date'),
            'indexed'         => $t('indexed_by_date'),
            'certs'           => array_map(fn ($key) => [
                'letter' => self::CERTIFICATIONS[$key][0],
                'text'   => self::CERTIFICATIONS[$key][1],
                'name'   => $t("cert_{$key}_name"),
                'title'  => $t("cert_{$key}_title") ?: self::CERTIFICATIONS[$key][2],
            ], array_keys(self::CERTIFICATIONS)),
            'cert_b_amount'   => ($p['cert_b_amount'] ?? '') !== '' ? $money($p['cert_b_amount']) : ($gross > 0 ? PrintService::money($gross) : ''),
            'jev_no'          => $t('jev_no'),
            'pc_summary'      => array_map(static fn ($r) => [
                'type'   => in_array($r['type'] ?? '', ['received', 'less', 'add', 'balance'], true) ? $r['type'] : 'less',
                'label'  => PrintService::text($r['label'] ?? ''),
                'amount' => PrintService::money(PrintService::amount($r['amount'] ?? 0)),
            ], array_values(is_array($p['pc_summary'] ?? null) ? $p['pc_summary'] : [])),
            'header_parts'    => null,
            'footer_text'     => '',
            'file_base'       => 'LV-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', preg_replace('/^LV-/i', '', $t('lv_number'))) ?: 'DRAFT'),
        ];
    }

    /**
     * The cash-advance breakdown rows (old REGIONAL caBreakdownToRows()): each
     * { desc, indent, a, b, c } — a = item amount, b = running subtotal, c = the
     * cash advance total. Empty without a cash advance.
     */
    private function breakdown(array $p, float $thisAmount): array
    {
        $caNo  = PrintService::text($p['ca_dv_number'] ?? '');
        $caAmt = PrintService::amount($p['ca_amount'] ?? 0);
        if ($caNo === '' && $caAmt <= 0) {
            return [];
        }
        $m = static fn (float $v) => PrintService::money($v);

        $prior = [];
        foreach (is_array($p['prior_liquidations'] ?? null) ? $p['prior_liquidations'] : [] as $pl) {
            $no = PrintService::text($pl['lv_number'] ?? '');
            $a  = PrintService::amount($pl['amount'] ?? 0);
            if ($no !== '' || $a > 0) {
                $prior[] = [$no, $a];
            }
        }
        $tax    = PrintService::amount($p['tax_deposit_amount'] ?? 0);
        $refund = PrintService::amount($p['cash_refund_deposit_amount'] ?? 0);
        $sub    = array_sum(array_column($prior, 1)) + $thisAmount;

        $lv = static fn (string $no) => preg_match('/^LV\b/i', $no) ? $no : 'LV ' . $no;   // "LV-2026-…" keeps its own prefix

        $rows   = [['desc' => 'Cash Advance thru DV No. ' . $caNo, 'indent' => false, 'a' => '', 'b' => '', 'c' => $m($caAmt)]];
        $rows[] = ['desc' => 'Less:', 'indent' => false, 'a' => '', 'b' => '', 'c' => ''];
        foreach ($prior as [$no, $a]) {
            $rows[] = ['desc' => $lv($no), 'indent' => true, 'a' => $m($a), 'b' => '', 'c' => ''];
        }
        $rows[] = ['desc' => 'This Liq:  ' . $lv(PrintService::text($p['lv_number'] ?? '')), 'indent' => false, 'a' => $m($thisAmount), 'b' => $m($sub), 'c' => ''];
        $rows[] = ['desc' => 'Tax Deposit', 'indent' => false, 'a' => $m($tax), 'b' => '', 'c' => ''];
        $rows[] = ['desc' => 'Cash Refund Deposit', 'indent' => false, 'a' => $m($refund), 'b' => $m($tax + $refund), 'c' => $m($caAmt)];

        return $rows;
    }

    /**
     * The FMD obligation ledger as OBLIGATION-column lines: [label, ref, amount,
     * underline amount]. Empty without ledger data.
     */
    private function obligationLines(array $o): array
    {
        $t = static fn (string $k) => PrintService::text($o[$k] ?? '');
        if (!array_filter(array_map(static fn ($v) => is_scalar($v) ? trim((string) $v) : '', $o))) {
            return [];
        }
        $peso = static fn (string $k) => ($o[$k] ?? '') === '' ? '' : PrintService::money(PrintService::amount($o[$k]));

        return [
            ['Dept/Proj:', $t('dept_proj'), '', false],
            ['Source of Fund:', $t('fund_source'), '', false],
            ['', '', '', false],
            ['Budget', $t('budget_ref'), $peso('budget'), false],
            ['Obligation', $t('obligation_ref'), $peso('obligation'), true],
            ['Balance', '', $peso('balance'), false],
            ['', '', '', false],
            ['Payments', $t('payments_ref'), $peso('payments'), false],
            ['This Request', $t('this_request_ref'), $peso('this_request'), true],
            ['Total Payments', '', $peso('total_payments'), false],
        ];
    }


    /**
     * Legal paper (612 × 1008pt), 0.5in margins (540pt), Times New Roman 9pt.
     * Mirrors scripts/gen_lv_docx.js. $media as CbaPrintService::html().
     * Dompdf ignores <colgroup> widths, so they sit on the first row's cells.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nl  = static fn ($v) => nl2br(htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), false);
        $w   = static fn (float $pct, string $css = '') => ' style="width:' . $pct . '%;' . $css . '"';   // one style attribute per cell
        $php = '<span class="sym">₱</span>&nbsp;';   // ₱ — not in Times-Roman for Dompdf
        $nb  = static fn (string $v) => $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : '&nbsp;';

        // Particulars box: lines, chargeability, breakdown; then blank rows.
        $rows = [];
        foreach ($m['lines'] as $l) {
            $rows[] = '<td' . ($l['sub'] ? ' style="padding-left:14pt;"' : '') . '>' . ($nl($l['desc']) ?: '&nbsp;') . '</td><td class="r b">' . $nb($l['amount']) . '</td>';
        }
        if ($m['chargeability'] !== '') {
            $rows[] = '<td class="b">Chargeability: ' . $e($m['chargeability']) . '</td><td>&nbsp;</td>';
        }
        foreach ($m['breakdown'] as $r) {
            $rows[] = '<td><table class="bd"><tr><td' . $w(52, $r['indent'] ? 'padding-left:14pt;' : '') . '>' . $e($r['desc']) . '</td>'
                . '<td class="r"' . $w(16) . '>' . $nb($r['a']) . '</td><td class="r"' . $w(16) . '>' . $nb($r['b']) . '</td>'
                . '<td class="r"' . $w(16) . '>' . $nb($r['c']) . '</td></tr></table></td><td>&nbsp;</td>';
        }
        while (count($rows) < $m['particular_rows']) {
            $rows[] = '<td>&nbsp;</td><td>&nbsp;</td>';
        }
        $partRows = '<tr>' . implode('</tr><tr>', $rows) . '</tr>';

        // OBLIGATION / ACCOUNTING ENTRIES: the ledger fills the OBLIGATION column first.
        $aeRows = '';
        $count  = max($m['entry_rows'], count($m['entries']), count($m['obligation']));
        for ($i = 0; $i < $count; $i++) {
            $en  = $m['entries'][$i] ?? ['obligation' => '', 'code' => '', 'debit' => '', 'credit' => ''];
            $ob  = $m['obligation'][$i] ?? null;
            $obl = $nb($en['obligation']);
            if ($ob && $ob[2] === '') {        // no amount: the text runs across the ref + amount columns
                $obl = '<table class="bd"><tr><td class="b"' . $w(34) . '>' . $nb($ob[0]) . '</td><td' . $w(66) . '>' . $nb($ob[1]) . '</td></tr></table>';
            } elseif ($ob) {
                $obl = '<table class="bd"><tr><td class="b"' . $w(34) . '>' . $nb($ob[0]) . '</td><td' . $w(36) . '>' . $nb($ob[1]) . '</td>'
                    . '<td class="r' . ($ob[3] ? ' u' : '') . '"' . $w(30) . '>' . $php . $e($ob[2]) . '</td></tr></table>';
            }
            $aeRows .= '<tr><td>' . $obl . '</td><td class="c">' . $nb($en['code']) . '</td><td class="r">' . $nb($en['debit']) . '</td><td class="r">' . $nb($en['credit']) . '</td></tr>';
        }

        $certs = '';
        foreach ($m['certs'] as $c) {
            $text = $e($c['text']) . ($c['letter'] === 'B' ? ' <b>' . $php . $e($m['cert_b_amount']) . '</b>' : '');
            // fixed-height text + a JEV line in every box (blank in A / B) keep the
            // names and the "Date:" lines level across the three boxes
            $certs .= '<td' . $w($c['letter'] === 'C' ? 33.34 : 33.33) . '><div class="txt"><b>' . $c['letter'] . '</b>&nbsp;&nbsp;Certified: ' . $text . '</div>'
                . '<div class="sig">' . ($e($c['name']) ?: '&nbsp;') . '</div><div class="c">' . $e($c['title']) . '</div>'
                . '<div class="jev">' . ($c['letter'] === 'C' ? 'JEV No.: ' . $e($m['jev_no']) : '&nbsp;') . '</div>'
                . '<div class="date">Date:</div></td>';
        }

        $summary = '';
        if ($m['pc_summary']) {
            $sRows = '';
            foreach ($m['pc_summary'] as $r) {
                $label = $r['type'] === 'received' || $r['type'] === 'balance' ? $r['label'] : ($r['type'] === 'add' ? 'Add: ' : 'Less: ') . $r['label'];
                $cls   = $r['type'] === 'balance' ? ' class="bal"' : '';
                $sRows .= '<tr' . $cls . '><td>' . $e($label) . '</td><td class="r"' . $w(25) . '>' . $php . $e($r['amount']) . '</td></tr>';
            }
            $summary = '<div style="page-break-before:always;"></div>'
                . '<p class="title" style="margin:0 0 10pt;">SUMMARY OF PETTY CASH</p><table class="pcs">' . $sRows . '</table>';
        }

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 540, 100, 36, 6);
        $pageCss = $media === 'print'
            ? '@page{size:legal portrait;margin:' . $top . 'pt 36pt 36pt 36pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:1008pt;margin:0 auto;padding:36pt;box-sizing:border-box;'
              . 'background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        $line = static fn (string $html) => '<table class="box"><tr><td>' . $html . '</td></tr></table>';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:"Times New Roman",Times,serif;font-size:9pt;color:#000;line-height:1.25;}'
            . 'p{margin:0;}table{width:100%;border-collapse:collapse;table-layout:fixed;}td,th{padding:2.5pt 4pt;vertical-align:middle;}'
            . '.hdr{text-align:center;margin-bottom:6pt;}.hdr>img{width:auto !important;height:auto;max-width:540pt;max-height:100pt;}'
            . '.sym{font-family:"DejaVu Sans",sans-serif;font-size:7.5pt;}'
            . '.c{text-align:center;}.r{text-align:right;}.b{font-weight:bold;}.u{text-decoration:underline;}'
            . '.title{text-align:center;font-size:13pt;font-weight:bold;}'
            . '.box td{border:.75pt solid #000;border-top:none;}'
            . '.head td{border:.75pt solid #000;}'
            . '.grid th{border:.75pt solid #000;font-weight:bold;text-align:center;}'
            . '.grid td{border-left:.75pt solid #000;border-right:.75pt solid #000;}'
            . '.grid tr:last-child td{border-bottom:.75pt solid #000;}'
            . '.ae td{border:.75pt solid #000;}.ae tr.widths td{border:none;padding:0;height:0;}'
            . '.arrow{font-family:"DejaVu Sans",sans-serif;font-size:8pt;}'
            . 'table.bd td{border:none !important;padding:0;}'
            . '.certs td{border:.75pt solid #000;border-top:none;vertical-align:top;height:120pt;padding:5pt;}'
            . '.certs .txt{height:36pt;}'
            . '.certs .sig{text-align:center;font-weight:bold;margin-top:12pt;}'
            . '.certs .jev{margin-top:6pt;}.certs .date{margin-top:16pt;}'
            . '.pcs td{padding:2pt 4pt;}.pcs tr.bal td{font-weight:bold;border-top:.75pt solid #000;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 540, 'LV') . '</div>', '')
            . '<table class="head"><tr><td class="title"' . $w(72, 'height:42pt;') . '>NATIONAL HOUSING AUTHORITY<br>LIQUIDATION VOUCHER</td>'
            . '<td' . $w(28) . '><b style="font-size:10pt;">LV No:</b> ' . $e($m['lv_number']) . '</td></tr></table>'
            . $line('<b>Disbursing Officer/Regional Bank Account:</b> ' . $e($m['disbursing_officer']))
            . $line('<b>Department/Region:</b> ' . $e($m['department_region']))
            . '<table class="grid"><tr><th' . $w(80) . '>PARTICULARS</th><th' . $w(20) . '>AMOUNT</th></tr>' . $partRows . '</table>'
            . $line('<b>AMOUNT IN WORDS: ' . $e($m['amount_words']) . '</b>')
            . '<table class="box"><tr><td' . $w(65) . '><b>AMOUNT OF CASH ADVANCE PER DV NO.</b> <span class="u">' . $e($m['ca_dv_number']) . '</span></td>'
            . '<td class="c arrow"' . $w(15) . '>────▶</td><td' . $w(20) . '>' . $php . $e($m['ca_amount']) . '</td></tr>'
            . '<tr><td><b>' . $e($m['refund_label']) . '</b> <span class="u">' . $e($m['refund_date']) . '</span></td>'
            . '<td class="c arrow">────▶</td><td>' . $php . $e($m['refund_amount']) . '</td></tr></table>'
            // first row: the column widths only (Dompdf takes widths from row 1, and rows 1–2 are spanned)
            . '<table class="ae"><tr class="widths"><td' . $w(42) . '></td><td' . $w(15) . '></td><td' . $w(21.5) . '></td><td' . $w(21.5) . '></td></tr>'
            . '<tr><td class="c b" rowspan="2">OBLIGATION</td><td class="c b" colspan="3">ACCOUNTING ENTRIES</td></tr>'
            . '<tr><td class="c b" style="font-size:8pt;">ACCOUNT CODE</td><td class="c b">DEBIT</td><td class="c b">CREDIT</td></tr>'
            . $aeRows . '</table>'
            . '<table class="box"><tr><td' . $w(50, 'height:34pt;vertical-align:top;') . '><b>Journalized by/date:</b> ' . $e($m['journalized']) . '</td>'
            . '<td' . $w(50, 'vertical-align:top;') . '><b>Indexed by/date:</b> ' . $e($m['indexed']) . '</td></tr></table>'
            . '<table class="certs"><tr>' . $certs . '</tr></table>'
            . $summary
            . PrintService::bodyClose($media, '');
    }
}
