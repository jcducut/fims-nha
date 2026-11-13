<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * PO (Purchase Order) print form — the model (every printed word) and the HTML
 * (preview + PDF). One form for every department: merged from the old REGIONAL
 * gen_po_docx.js and the GSD / REGIONAL BudgetAllocationPdfService::buildPoHtml()
 * (FMD and GLOBAL only printed the screen).
 *
 * Legal paper, Times New Roman 9pt, everything inside one outlined box: the
 * "PURCHASE ORDER / NATIONAL HOUSING AUTHORITY / Agency" title block (with the
 * NHA logo, as GSD's), supplier / P.O. no. rows, "Gentlemen:", delivery and
 * payment terms, the items table (at least MIN_ITEM_ROWS rows), TOTAL LOT PRICE,
 * Purpose / Requisitioners / References / Warranty, total in words, penalty
 * clause, Conforme + approving signatories, Funds Available / ALOBS.
 * No NHA letterhead by default (LetterheadLayoutService::DOCUMENTS PO header =>
 * false) — the title block is the form's own; a header can still be designed.
 *
 * Printed through PrintService::print('PO', …); DOCX: scripts/gen_po_docx.js.
 */
class PurchaseOrderPrintService
{
    // GSD's form always shows at least this many item rows (blank ones below the items).
    public const MIN_ITEM_ROWS = 4;

    public const PENALTY = 'In case of failure to make full delivery within the time specified above, a penalty of one-tenth (1/10) of one percent for every day of delay shall be imposed.';

    /**
     * PO model. Payload (all optional):
     *   po_id (or id) — fill everything from the saved PO (fims.purchase_orders +
     *     items, via PurchaseOrderService::fetchById); any field below that is also
     *     sent overrides the saved value.
     *   po_number, po_date, vendor_name (or supplier_name), vendor_address (or
     *   supplier_address), vendor_tin, mode_of_procurement, delivery_place (or
     *   place_of_delivery), delivery_terms (or delivery_term), delivery_date_required
     *   (or date_of_delivery), payment_terms (or payment_term),
     *   items [{ item_description, unit_of_measure, quantity_ordered, unit_cost,
     *            total_cost? }], total_amount (default: the items' sum),
     *   purpose (default: the PO's particulars), requisitioners, references
     *   (default "Refer to P.R. No. <pr_number>" when pr_number is sent), warranty,
     *   approving_officer_name / _title (default "Chairperson, CBAC"),
     *   general_manager_name / _title (default "General Manager"),
     *   certified_by_name (blank → the current FMD Department Manager) / _title
     *   (default "Manager, FMD"), prepared_by_initials, funds_available (text,
     *   e.g. "P 42,500.00 yr. 2026"), alobs_no (or allocation_number),
     *   alobs_amount (text), alobs_adj (text)
     */
    public function model(array $p): array
    {
        $id = $p['po_id'] ?? $p['id'] ?? null;
        if ($id !== null && $id !== '') {
            $saved = (new PurchaseOrderService())->fetchById($id);
            if (!empty($saved['success'])) {
                $p = $this->overlay($saved['data'], $p);
            }
        }

        $t = static fn (string ...$keys): string => PrintService::text(
            array_reduce($keys, fn ($v, $k) => $v !== '' && $v !== null ? $v : ($p[$k] ?? ''), '')
        );

        $items = [];
        $sum   = 0.0;
        foreach (array_values(is_array($p['items'] ?? null) ? $p['items'] : []) as $i => $it) {
            $qty   = PrintService::amount($it['quantity_ordered'] ?? $it['quantity'] ?? null);
            $cost  = PrintService::amount($it['unit_cost'] ?? null);
            $total = isset($it['total_cost']) && $it['total_cost'] !== '' ? PrintService::amount($it['total_cost']) : round($qty * $cost, 2);
            $sum  += $total;
            $items[] = [
                'no'        => (string) ($it['line_number'] ?? ($i + 1)),
                'unit'      => PrintService::text($it['unit_of_measure'] ?? $it['unit'] ?? ''),
                'desc'      => trim((string) ($it['item_description'] ?? $it['description'] ?? '')),
                'qty'       => rtrim(rtrim(number_format($qty, 2, '.', ','), '0'), '.'),
                'unit_cost' => $cost > 0 ? PrintService::money($cost) : '',
                'amount'    => $total > 0 ? PrintService::money($total) : '',
            ];
        }
        $total = isset($p['total_amount']) && $p['total_amount'] !== '' ? PrintService::amount($p['total_amount']) : $sum;

        $prNumber = $t('pr_number');
        $info = array_values(array_filter([
            ['Purpose :', $t('purpose', 'particulars')],
            ['Requisitioners :', $t('requisitioners')],
            ['References :', $t('references') ?: ($prNumber !== '' ? 'Refer to P.R. No. ' . $prNumber : '')],
            ['Warranty :', $t('warranty')],
        ], fn ($r) => $r[1] !== ''));

        $poNumber = $t('po_number');

        return [
            'po_number'      => $poNumber,
            'date'           => $this->date($t('po_date')),
            'supplier'       => ['name' => $t('vendor_name', 'supplier_name'), 'address' => $t('vendor_address', 'supplier_address'), 'tin' => $t('vendor_tin', 'supplier_tin')],
            'mode'           => $t('mode_of_procurement'),
            'delivery_place' => $t('delivery_place', 'place_of_delivery'),
            'delivery_term'  => $t('delivery_terms', 'delivery_term'),
            'delivery_date'  => $this->date($t('delivery_date_required', 'date_of_delivery'), false),
            'payment_term'   => $t('payment_terms', 'payment_term'),
            'items'          => $items,
            'min_rows'       => self::MIN_ITEM_ROWS,
            'total'          => PrintService::money($total),
            'total_words'    => PrintService::amountInWords($total),
            'info'           => $info,
            'penalty'        => self::PENALTY,
            'approving'      => ['name' => $t('approving_officer_name', 'chairperson_name'), 'title' => $t('approving_officer_title') ?: 'Chairperson, CBAC'],
            'gm'             => ['name' => $t('general_manager_name'), 'title' => $t('general_manager_title') ?: 'General Manager'],
            'certified'      => [
                'name'  => $t('certified_by_name') ?: (PrintService::resolveSignatoryName('FMD Department Manager') ?? ''),
                'title' => $t('certified_by_title') ?: 'Manager, FMD',
            ],
            'prepared_initials' => $t('prepared_by_initials'),
            'funds_available'   => $t('funds_available'),
            'alobs'          => ['no' => $t('alobs_no', 'allocation_number'), 'amount' => $t('alobs_amount'), 'adj' => $t('alobs_adj')],
            'header_parts'   => null,
            'footer_text'    => '',
            'file_base'      => 'PO-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', preg_replace('/^PO-/i', '', $poNumber)) ?: 'DRAFT'),
        ];
    }

    /** The saved PO under the payload: payload fields win, empty ones don't. */
    private function overlay(array $saved, array $p): array
    {
        foreach ($p as $k => $v) {
            if ($v !== '' && $v !== null && $v !== []) {
                $saved[$k] = $v;
            }
        }

        return $saved;
    }

    /** "2026-11-10" → "November 10, 2026" ($long) / as given; blank → today ($long) or ''. */
    private function date(string $v, bool $todayWhenBlank = true): string
    {
        if ($v === '') {
            return $todayWhenBlank ? date('F d, Y') : '';
        }
        $d = \DateTime::createFromFormat('Y-m-d', substr($v, 0, 10));

        return $d && $d->format('Y-m-d') === substr($v, 0, 10) ? $d->format('F d, Y') : $v;
    }

    /**
     * Legal paper (612 × 1008pt), 0.55in sides (532.8pt). Mirrors
     * scripts/gen_po_docx.js. $media as CbaPrintService::html(). Every section is
     * a table inside .po (the outline); Dompdf can't split a row across pages, so
     * each item is its own row.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nl  = static fn ($v) => nl2br(htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), false);
        $php = '<span class="sym">₱</span>&nbsp;';   // ₱ — not in Times-Roman for Dompdf
        $logo = PrintService::repoRoot() . '/_shared/images/letterhead/nha-logo.png';
        $logoImg = is_file($logo) ? '<img class="logo" src="data:image/png;base64,' . base64_encode((string) file_get_contents($logo)) . '" alt="">' : '';

        $items = '';
        foreach ($m['items'] as $it) {
            $items .= '<tr><td class="c">' . $e($it['no']) . '</td><td class="c">' . $e($it['unit']) . '</td><td>' . $nl($it['desc']) . '</td>'
                . '<td class="c">' . $e($it['qty']) . '</td>'
                . '<td class="r">' . ($it['unit_cost'] !== '' ? $php . $e($it['unit_cost']) : '') . '</td>'
                . '<td class="r">' . ($it['amount'] !== '' ? $php . $e($it['amount']) : '') . '</td></tr>';
        }
        for ($i = count($m['items']); $i < $m['min_rows']; $i++) {
            $items .= '<tr>' . str_repeat('<td>&nbsp;</td>', 6) . '</tr>';   // a bare <td> collapses in Dompdf
        }
        $info = '';
        foreach ($m['info'] as [$label, $value]) {
            $info .= '<tr><td colspan="2" class="r b">' . $e($label) . '</td><td>' . $nl($value) . '</td><td></td><td></td><td></td></tr>';
        }

        // Dompdf ignores <colgroup> widths — they go on the first row's cells.
        $w = static fn (float $pct) => ' style="width:' . $pct . '%;"';

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 532.8, 100, 39.6, 6);
        $pageCss = $media === 'print'
            ? '@page{size:legal portrait;margin:' . $top . 'pt 39.6pt 39.6pt 39.6pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:612pt;min-height:1008pt;margin:0 auto;padding:39.6pt;box-sizing:border-box;'
              . 'background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:"Times New Roman",Times,serif;font-size:9pt;color:#000;line-height:1.2;}'
            . 'p{margin:0;}.hdr{text-align:center;margin-bottom:6pt;}'
            . '.hdr>img{width:auto !important;height:auto;max-width:532.8pt;max-height:100pt;}'
            . '.sym{font-family:"DejaVu Sans",sans-serif;font-size:8pt;}'
            . '.po{border:1pt solid #000;}'
            . '.po table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.po td,.po th{padding:2pt 4pt;vertical-align:top;}'
            . '.c{text-align:center;}.r{text-align:right;}.b{font-weight:bold;}.u{text-decoration:underline;}.i{font-style:italic;}'
            . '.bt{border-top:.5pt solid #000;}.lbl{white-space:nowrap;}'
            . '.title td{text-align:center;padding:8pt 4pt;border-bottom:.5pt solid #000;}'
            . '.title .logo{width:40pt;height:auto;}.title .t1{font-size:14pt;font-weight:bold;}.title .t2{font-size:11pt;font-weight:bold;}'
            . '.sup td.mid{border-left:.5pt solid #000;}'
            . '.items th{font-size:8pt;font-weight:bold;text-align:center;border:.5pt solid #000;border-top:none;}'
            . '.items th:first-child{border-left:none;}.items th:last-child{border-right:none;}'
            . '.items td{border-left:.5pt solid #000;}.items td:first-child{border-left:none;}'
            . '.sig td{vertical-align:top;padding:4pt 6pt;}.line{border-bottom:.5pt solid #000;height:30pt;margin:2pt 0;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 532.8, 'PO') . '</div>', '')
            . '<div class="po">'
            // Title block
            . '<table class="title"><tr><td>' . $logoImg
            . '<p class="t1">PURCHASE ORDER</p><p class="t2">NATIONAL HOUSING AUTHORITY</p><p class="u">Agency</p></td></tr></table>'
            // Supplier | P.O. No. / Date / Mode of Procurement
            . '<table class="sup">'
            . '<tr><td class="b"' . $w(11) . '>Supplier:</td><td class="b" style="width:43%;font-size:10pt;">' . $e($m['supplier']['name']) . '</td>'
            . '<td class="b mid lbl"' . $w(20) . '>P.O. No.:</td><td class="b u"' . $w(26) . '>' . $e($m['po_number']) . '</td></tr>'
            . '<tr><td class="b">Address:</td><td>' . $nl($m['supplier']['address']) . '</td><td class="b mid">Date:</td><td class="u">' . $e($m['date']) . '</td></tr>'
            . '<tr><td class="b">TIN:</td><td>' . $e($m['supplier']['tin']) . '</td><td class="b mid">Mode of Procurement:</td><td>' . $e($m['mode']) . '</td></tr></table>'
            // Gentlemen
            . '<table class="bt"><tr><td style="padding:5pt 6pt;"><span class="b" style="font-size:10pt;">Gentlemen:</span>'
            . '<p class="i c">Please furnish this Office the following articles subject to the terms and conditions contained herein:</p></td></tr></table>'
            // Delivery / payment
            . '<table class="bt sup">'
            . '<tr><td class="b lbl"' . $w(17) . '>Place of Delivery:</td><td' . $w(34) . '>' . $e($m['delivery_place']) . '</td>'
            . '<td class="b mid"' . $w(18) . '>Delivery Term:</td><td class="b u"' . $w(31) . '>' . $e($m['delivery_term']) . '</td></tr>'
            . '<tr><td class="b lbl">Date of Delivery:</td><td>' . $e($m['delivery_date']) . '</td>'
            . '<td class="b mid">Payment Term:</td><td class="b u">' . $e($m['payment_term']) . '</td></tr></table>'
            // Items
            . '<table class="items bt">'
            . '<tr><th' . $w(7) . '>Item No.</th><th' . $w(8) . '>Unit</th><th' . $w(48) . '>Description</th><th' . $w(9) . '>Quantity</th>'
            . '<th' . $w(14) . '>Unit Cost</th><th' . $w(14) . '>Amount</th></tr>'
            . $items
            . '<tr><td></td><td></td><td class="c b" style="padding-top:10pt;">TOTAL LOT PRICE:</td><td></td>'
            . '<td class="r b" style="padding-top:10pt;">' . $php . $e($m['total']) . '</td><td class="r b" style="padding-top:10pt;">' . $php . $e($m['total']) . '</td></tr>'
            . '<tr><td></td><td></td><td class="c i" style="padding-bottom:10pt;">X-X-X-X-X-X-X-X-X-X</td><td></td><td></td><td></td></tr>'
            . $info
            . '<tr style="height:40pt;"><td></td><td></td><td></td><td></td><td></td><td></td></tr></table>'
            // Total in words
            . '<table class="bt"><tr>'
            . '<td' . $w(75) . '><span class="b">Total Amount in Words: </span><span class="b u">' . $e($m['total_words']) . '.</span></td>'
            . '<td class="r b" style="width:25%;border-left:.5pt solid #000;">' . $php . $e($m['total']) . '</td></tr></table>'
            // Penalty
            . '<table class="bt"><tr><td class="i" style="font-size:8pt;">' . $e($m['penalty']) . '</td></tr></table>'
            // Conforme | approving signatories
            . '<table class="sig bt"><tr>'
            . '<td' . $w(54) . '><p class="b">Conforme:</p><div class="line"></div><p class="i c" style="font-size:8pt;">Signature over printed name of supplier</p>'
            . '<div class="line"></div><p class="b c">DATE</p></td>'
            . '<td class="c"' . $w(46) . '><div style="height:36pt;"></div><p class="b u">' . ($e($m['approving']['name']) ?: '&nbsp;') . '</p><p style="font-size:8pt;">' . $e($m['approving']['title']) . '</p>'
            . '<div style="height:26pt;"></div><p class="b u">' . ($e($m['gm']['name']) ?: '&nbsp;') . '</p><p style="font-size:8pt;">' . $e($m['gm']['title']) . '</p></td></tr>'
            . ($m['prepared_initials'] !== '' ? '<tr><td class="i" style="font-size:8pt;">' . $e($m['prepared_initials']) . '</td><td></td></tr>' : '')
            . '</table>'
            // Funds Available | ALOBS
            . '<table class="sig bt"><tr>'
            . '<td' . $w(52) . '><p class="b">Funds Available: <span style="font-weight:normal;">' . $e($m['funds_available']) . '</span></p><div class="line" style="height:18pt;"></div>'
            . '<div style="height:10pt;"></div><p class="b u c">' . ($e($m['certified']['name']) ?: '&nbsp;') . '</p><p class="c" style="font-size:8pt;">' . $e($m['certified']['title']) . '</p></td>'
            . '<td style="width:48%;border-left:.5pt solid #000;"><p class="b">ALOBS No.: <span class="u">' . $e($m['alobs']['no']) . '</span></p><div class="line" style="height:18pt;"></div>'
            . '<p class="b" style="margin-top:8pt;">Amount: <span style="font-weight:normal;">' . $e($m['alobs']['amount']) . '</span></p><div class="line" style="height:18pt;"></div>'
            . ($m['alobs']['adj'] !== '' ? '<p class="b" style="margin-top:4pt;">Adj: <span style="font-weight:normal;">' . $e($m['alobs']['adj']) . '</span></p>' : '')
            . '</td></tr></table>'
            . '</div>'
            . PrintService::bodyClose($media, '');
    }
}
