<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * FTLA (Fund Transfer Letter of Advice) print form — the letter to the bank
 * ("AUTHORITY TO DEBIT/CREDIT ACCOUNT"): model (every printed word) + HTML
 * (preview + PDF). One form for every page that printed it:
 *   TREASURY ftlafundtransfer.php ("Print FTLA" — single / multi-account),
 *   TREASURY ftla-preparation.php ("Print DRFTLA" — + the DV NO.s footer),
 *   TREASURY ftlasettlement.php (settlement subject + signatories),
 *   FMD fund-transfer-form.php / gen_fund_transfer_docx.js and
 *   FMD cross-access-readone.php (the same letter).
 * Their differences are options: the subject, the signatories, the DV footer.
 * The regional office's request memo to Treasury is a separate document
 * (FtlaRequestPrintService). There is no fund-transfer module in this repo yet,
 * so it prints from the payload.
 *
 * The body follows the accounts:
 *   single     one debit → one credit: the fill-in lines ("Please debit our
 *              Account … the amount of PESOS: … and credit … representing …");
 *   debit_list several debit accounts → one credit account: NUMBER / NAME /
 *              AMOUNT table + TOTAL, then "and credit the account of …";
 *   pairs      anything else: FROM / TO / AMOUNT table (a shared FROM account
 *              spans its rows) + TOTAL.
 *
 * A4 portrait, Arial 9.5pt; the NHA letterhead (editable), no footer line.
 * Printed through PrintService::print('FTLA', …); DOCX: scripts/gen_ftla_docx.js.
 */
class FtlaPrintService
{
    public const DEFAULT_SUBJECT = 'AUTHORITY TO DEBIT/CREDIT ACCOUNT';

    // The two signatories' default titles (TREASURY's FTLA).
    private const SIGNATORY_TITLES = [
        'left'  => "Officer-in-Charge\nTreasury Department",
        'right' => "Acting Group Manager\nFinancial Services Group",
    ];

    /**
     * FTLA model. Payload (all optional):
     *   ftla_number, date (or transfer_date), to_bank_name (default "LANDBANK OF
     *   THE PHILIPPINES"), to_bank_branch, subject (default "AUTHORITY TO
     *   DEBIT/CREDIT ACCOUNT" — e.g. "AUTHORITY TO TRANSFER FUNDS", "AUTHORITY TO
     *   TRANSFER SETTLEMENT FUNDS TO MAIN DEPOSITORY ACCOUNT"),
     *   one transfer — from_account_name, from_account_number, to_account_name,
     *     to_account_number, amount (or transfer_amount), purpose; or several —
     *     transfers [{ from_account_number, from_account_name, to_account_number,
     *     to_account_name, amount }] (rows pair a debit with a credit),
     *   sig_left_name / _title, sig_right_name / _title (titles: "\n" for a new
     *   line), dv_numbers (list or one per line) + fund_classification (e.g.
     *   "COE/MOOE", "EHAP") — the DRFTLA footer, bank_sig_verified,
     *   bank_checked, bank_approved, bank_posted ("FOR BANK USE ONLY").
     */
    public function model(array $p): array
    {
        $t = static fn (string $k) => PrintService::text($p[$k] ?? '');

        // Transfers: the list, or the single from / to fields as one row.
        $rows = [];
        foreach (is_array($p['transfers'] ?? null) ? $p['transfers'] : [] as $r) {
            $rows[] = [
                'from_no'   => PrintService::text($r['from_account_number'] ?? ''),
                'from_name' => PrintService::text($r['from_account_name'] ?? ''),
                'to_no'     => PrintService::text($r['to_account_number'] ?? ''),
                'to_name'   => PrintService::text($r['to_account_name'] ?? ''),
                'amount'    => PrintService::amount($r['amount'] ?? 0),
            ];
        }
        if (!$rows) {
            $rows[] = ['from_no' => $t('from_account_number'), 'from_name' => $t('from_account_name'),
                'to_no' => $t('to_account_number'), 'to_name' => $t('to_account_name'),
                'amount' => PrintService::amount($p['amount'] ?? $p['transfer_amount'] ?? 0)];
        }
        $total = array_sum(array_column($rows, 'amount'));
        $froms = array_unique(array_map(static fn ($r) => $r['from_no'] . '|' . $r['from_name'], $rows));
        $tos   = array_unique(array_map(static fn ($r) => $r['to_no'] . '|' . $r['to_name'], $rows));
        $layout = count($rows) === 1 ? 'single' : (count($tos) === 1 && count($froms) > 1 ? 'debit_list' : 'pairs');

        // pairs: group consecutive-or-not rows by their FROM account (the FROM cells span the group)
        $groups = [];
        if ($layout === 'pairs') {
            $index = [];
            foreach ($rows as $r) {
                $key = $r['from_no'] . '|' . $r['from_name'];
                if (!isset($index[$key])) {
                    $index[$key] = count($groups);
                    $groups[]    = ['from_no' => $r['from_no'], 'from_name' => $r['from_name'], 'rows' => []];
                }
                $groups[$index[$key]]['rows'][] = ['to_no' => $r['to_no'], 'to_name' => $r['to_name'], 'amount' => PrintService::money($r['amount'])];
            }
        }

        $dv = $p['dv_numbers'] ?? [];
        $dv = array_values(array_filter(array_map([PrintService::class, 'text'], is_array($dv) ? $dv : preg_split('/\R/', (string) $dv))));
        $ftlaNo = $t('ftla_number');
        $class  = $t('fund_classification');

        $sig = static fn (string $side) => [
            'name'  => $t("sig_{$side}_name"),
            'lines' => preg_split('/\R|\\\\n/', $t("sig_{$side}_title") ?: self::SIGNATORY_TITLES[$side]),
        ];

        return [
            'date'        => PrintService::longDate($p['date'] ?? $p['transfer_date'] ?? ''),
            'to_bank'     => mb_strtoupper($t('to_bank_name') ?: 'LANDBANK OF THE PHILIPPINES'),
            'to_branch'   => $t('to_bank_branch'),
            'subject'     => mb_strtoupper($t('subject') ?: self::DEFAULT_SUBJECT),
            'layout'      => $layout,
            'single'      => $rows[0] + ['amount_text' => PrintService::money($rows[0]['amount']),
                                          'words' => $rows[0]['amount'] > 0 ? PrintService::amountInWordsCentavos($rows[0]['amount']) : ''],
            'debits'      => $layout === 'debit_list'
                ? array_map(static fn ($r) => ['no' => $r['from_no'], 'name' => $r['from_name'], 'amount' => PrintService::money($r['amount'])], $rows)
                : [],
            'credit'      => ['no' => $rows[0]['to_no'], 'name' => $rows[0]['to_name']],
            'groups'      => $groups,
            'total'       => PrintService::money($total),
            'total_words' => $total > 0 ? PrintService::amountInWordsCentavos($total) : '',
            'purpose'     => trim((string) ($p['purpose'] ?? '')),
            'signatories' => [$sig('left'), $sig('right')],
            'dv_numbers'  => $dv,
            // "COE/MOOE FTLA-…" under the DV list — only with a DV list or a classification (the DRFTLA footer)
            'fund_line'   => ($dv || $class !== '') && $ftlaNo !== '' ? trim(($class ?: 'COE/MOOE') . ' ' . $ftlaNo) : '',
            'bank'        => [
                ['Signature Verified by:', $t('bank_sig_verified')],
                ['Checked by:', $t('bank_checked')],
                ['Approved by:', $t('bank_approved')],
                ['Posted by:', $t('bank_posted')],
            ],
            'header_parts' => null,
            'footer_text'  => '',
            'file_base'    => 'FTLA-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', preg_replace('/^FTLA-/i', '', $ftlaNo)) ?: 'DRAFT'),
        ];
    }

    /**
     * A4 portrait (595 × 842pt), 15mm sides / 12mm top-bottom (510pt wide), Arial 9.5pt.
     * Mirrors scripts/gen_ftla_docx.js. $media as CbaPrintService::html().
     * Dompdf ignores <colgroup> widths, so they sit on the first row's cells.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nb  = static fn (string $v) => $v !== '' ? htmlspecialchars($v, ENT_QUOTES, 'UTF-8') : '&nbsp;';
        $w   = static fn (float $pct, string $css = '') => ' style="width:' . $pct . '%;' . $css . '"';   // one style attribute per cell
        $amt = static fn (string $v, bool $bold = false) => '<td class="amt"><table class="in"><tr><td' . $w(12) . '>P</td>'
            . '<td class="r"' . $w(88) . '>' . ($bold ? '<b>' . $v . '</b>' : $v) . '</td></tr></table></td>';
        // first row of a grid: the column widths only (Dompdf takes widths from row 1, and the header rows are spanned)
        $widths = static fn (array $pcts) => '<tr class="widths">' . implode('', array_map(static fn ($p) => '<td style="width:' . $p . '%;"></td>', $pcts)) . '</tr>';

        if ($m['layout'] === 'single') {
            $s    = $m['single'];
            $body = '<table class="fill">'
                . '<tr><td class="lbl"' . $w(24) . '>Please debit our Account</td><td colspan="2" class="ul c"' . $w(76) . '>' . $nb($s['from_name']) . '</td></tr>'
                . '<tr><td class="lbl">Account No.</td><td class="ul c"' . $w(46) . '>' . $nb($s['from_no']) . '</td><td class="lbl2"' . $w(30) . '>the amount of PESOS:</td></tr>'
                . '<tr><td></td><td colspan="2" class="ul c">' . $nb($s['words']) . '</td></tr>'
                . '<tr><td></td><td colspan="2">Php <u>' . $e($s['amount_text']) . '</u></td></tr>'
                . '<tr><td class="lbl">and credit</td><td colspan="2" class="ul c">' . $nb($s['to_name']) . '</td></tr>'
                . '<tr><td class="lbl">Account No.:</td><td class="ul c">' . $nb($s['to_no']) . '</td><td class="lbl2">representing</td></tr>'
                . '<tr><td colspan="3" class="ul">' . $nb($m['purpose']) . '</td></tr></table>';
        } elseif ($m['layout'] === 'debit_list') {
            $rows = '';
            foreach ($m['debits'] as $d) {
                $rows .= '<tr><td class="c">' . $nb($d['no']) . '</td><td>' . $nb($d['name']) . '</td>' . $amt($e($d['amount'])) . '</tr>';
            }
            $body = '<p class="lead">Please <u><b>debit</b></u> the following depository/disbursing accounts:</p>'
                . '<table class="grid">' . $widths([26, 48, 26])
                . '<tr><th colspan="3">BANK ACCOUNT</th></tr><tr><th>NUMBER</th><th>NAME</th><th>AMOUNT</th></tr>'
                . $rows . '<tr><td colspan="2" class="b">TOTAL</td>' . $amt($e($m['total']), true) . '</tr></table>'
                . '<p class="j">and <u><b>credit</b></u> the account of <u>(' . $e($m['credit']['no']) . ') (' . $e($m['credit']['name']) . ')</u> '
                . 'in the total amount of <u>' . $e($m['total_words']) . ' (Php ' . $e($m['total']) . ')</u>.</p>';
        } else {
            $rows = '';
            foreach ($m['groups'] as $g) {
                foreach ($g['rows'] as $i => $r) {
                    $rows .= '<tr>';
                    if ($i === 0) {   // the FROM account spans its group's rows
                        $span  = count($g['rows']) > 1 ? ' rowspan="' . count($g['rows']) . '"' : '';
                        $rows .= '<td class="c"' . $span . '>' . $nb($g['from_no']) . '</td><td' . $span . '>' . $nb($g['from_name']) . '</td>';
                    }
                    $rows .= '<td class="c">' . $nb($r['to_no']) . '</td><td>' . $nb($r['to_name']) . '</td>' . $amt($e($r['amount'])) . '</tr>';
                }
            }
            $body = '<p class="lead">Please effect the fund transfer as follows:</p>'
                . '<table class="grid">' . $widths([14, 24, 14, 24, 24])
                . '<tr><th colspan="2">FROM</th><th colspan="2">TO</th><th rowspan="2">AMOUNT</th></tr>'
                . '<tr><th>Account No.</th><th>Account Name</th><th>Account No.</th><th>Account Name</th></tr>'
                . $rows . '<tr><td colspan="4" class="b r">TOTAL</td>' . $amt($e($m['total']), true) . '</tr></table>';
        }

        $sigs = '';
        foreach ($m['signatories'] as $i => $s) {
            $sigs .= '<td' . $w(48, $i === 1 ? 'text-align:right;' : '') . '><div class="b">' . $nb($s['name']) . '</div>'
                . implode('', array_map(static fn ($l) => '<div>' . htmlspecialchars($l, ENT_QUOTES, 'UTF-8') . '</div>', $s['lines'])) . '</td>'
                . ($i === 0 ? '<td' . $w(4) . '></td>' : '');
        }

        $footer = '';
        if ($m['dv_numbers'] || $m['fund_line'] !== '') {
            $footer = '<div class="dvs">' . ($m['dv_numbers'] ? '<div>* DV NO.s:</div>'
                . implode('', array_map(static fn ($d) => '<div class="dv">' . htmlspecialchars($d, ENT_QUOTES, 'UTF-8') . '</div>', $m['dv_numbers'])) : '')
                . ($m['fund_line'] !== '' ? '<div class="b" style="margin-top:6pt;">' . $e($m['fund_line']) . '</div>' : '') . '</div>';
        }

        $bankLabels = $bankNames = '';
        foreach ($m['bank'] as [$label, $name]) {
            $bankLabels .= '<td' . $w(25) . '>' . $e($label) . '</td>';
            $bankNames  .= '<td class="bn">' . ($name !== '' ? '<u>' . $e($name) . '</u>' : '&nbsp;') . '</td>';
        }

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 510, 90, 34, 8);
        $pageCss = $media === 'print'
            ? '@page{size:A4 portrait;margin:' . $top . 'pt 42.5pt 34pt 42.5pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:595.28pt;min-height:841.89pt;margin:0 auto;padding:34pt 42.5pt;box-sizing:border-box;'
              . 'background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:9.5pt;color:#000;line-height:1.35;}'
            . 'p{margin:0;}table{width:100%;border-collapse:collapse;table-layout:fixed;}td,th{padding:0;vertical-align:bottom;}'
            . '.hdr{text-align:center;margin-bottom:8pt;}.hdr>img{width:auto !important;height:auto;max-width:510pt;max-height:90pt;}'
            . '.b{font-weight:bold;}.c{text-align:center;}.r{text-align:right;}'
            . '.date{margin:0 0 8pt;}'
            . '.to td{padding:0 0 3pt;vertical-align:top;}'
            . '.fill{margin:0 0 8pt;}.fill td{padding:0 0 4pt;}.fill td.lbl{white-space:nowrap;padding-right:6pt;}'
            . '.fill td.lbl2{white-space:nowrap;padding-left:8pt;}.fill td.ul{border-bottom:.75pt solid #000;}'
            . '.lead{margin:0 0 6pt;}.j{text-align:justify;margin:0 0 8pt;}'
            . '.grid{margin:0 0 10pt;font-size:9pt;}.grid th,.grid td{border:.75pt solid #000;padding:3pt 6pt;vertical-align:middle;}'
            . '.grid th{font-weight:bold;text-align:center;}'
            . '.grid tr.widths td{border:none;padding:0;height:0;}'
            . 'table.in td{border:none !important;padding:0 !important;}'
            . '.decl{text-align:justify;margin:0 0 6pt;}.copy{text-align:justify;margin:0 0 28pt;font-size:10.5pt;}'
            . '.sigs td{vertical-align:top;}'
            . '.dvs{margin-top:10pt;font-size:9pt;}.dvs .dv{padding-left:2pt;line-height:1.6;}'
            . '.bank{border-top:1.2pt solid #000;margin-top:16pt;padding-top:8pt;font-size:10.5pt;}'
            . '.bank table{font-size:9pt;}.bank td{padding:0 12pt 4pt 0;}.bank td.bn{border-top:.75pt solid #000;padding:4pt 12pt 0 0;font-size:9.5pt;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 510, 'FTLA') . '</div>', '')
            . '<p class="date">' . $e($m['date']) . '</p>'
            . '<table class="to" style="margin-bottom:10pt;"><tr><td' . $w(13) . '>TO</td><td' . $w(3) . '>:</td>'
            . '<td' . $w(84) . '><div class="b">' . $e($m['to_bank']) . '</div>' . ($m['to_branch'] !== '' ? '<div>' . $e($m['to_branch']) . '</div>' : '') . '</td></tr>'
            . '<tr><td>SUBJECT</td><td>:</td><td class="b">' . $e($m['subject']) . '</td></tr></table>'
            . $body
            . '<p class="decl">We declare under penalty of perjury that our co-depositors are still living. This also authorizes you to debit our account for the corresponding service charge.</p>'
            . '<p class="copy">Kindly provide us with a certified true copy of duly validated Fund Transfer Letter Advice for our record purposes.</p>'
            . '<table class="sigs"><tr>' . $sigs . '</tr></table>'
            . $footer
            . '<div class="bank"><p class="b" style="margin-bottom:2pt;">FOR BANK USE ONLY</p>'
            . '<p style="margin-bottom:14pt;">We have debited/credited above accounts as instructed:</p>'
            . '<table><tr>' . $bankLabels . '</tr><tr>' . $bankNames . '</tr></table></div>'
            . PrintService::bodyClose($media, '');
    }
}
