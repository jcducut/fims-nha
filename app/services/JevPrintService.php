<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * JEV (Journal Entry Voucher) print form — the model (every printed word) and
 * the HTML (preview + PDF). One form for every department: the NHA paper JEV,
 * from the old GLOBAL JevServices::renderPrintHtml() (itself the merge of
 * ACCOUNTING's /jev-listing/print (GAD) and /jev-listing/dcd-print (DCD)
 * templates; DCD's check-db-journal.php / jev-form.js previews and PCDS's
 * Excel export are copies of the same form). Department differences are
 * options: the office box (book name / "MAIN OFFICE" / a regional office), the
 * third signatory's label ("Certified Correct:" / "Approved by:") and the
 * responsibility center column. It prints from the payload — the DV module's JEV
 * (DisbursementVoucherService, journal-entry-voucher.php) and the DR module's
 * pro-forma JEV build it through the preview's prp_jevFromDv() /
 * prp_jevFromProforma(); a line sent without its account title gets it from
 * fims.chart_of_accounts (accountTitles()).
 *
 * A4 landscape, Arial 7.5–8pt; the form's own title box, no letterhead.
 * Printed through PrintService::print('JEV', …); DOCX: scripts/gen_jev_docx.js.
 */
class JevPrintService
{
    // Entry rows the form always shows (lines + their description / SL rows).
    public const MIN_ENTRY_ROWS = 8;

    // Signatory boxes: [payload key, label, default title].
    private const SIGNATORIES = [
        ['prepared_by', 'Prepared by:', 'Chief Corporate Accountant'],
        ['reviewed_by', 'Reviewed by:', 'Division Manager'],
        ['approved_by', 'Certified Correct:', 'Department Manager'],
    ];

    /**
     * JEV model. Payload (all optional):
     *   jev_number (blank or "TEMP-…" → the No. stays blank and the temporary
     *   reference prints under the form, as the old form), jev_date, office
     *   (default "MAIN OFFICE"), responsibility_center (default for every line),
     *   lines [{ account_title, account_code, description?, sl_code?, sl_path?,
     *   debit, credit, responsibility_center? }] — a line with sl_code / sl_path
     *   also prints its description and an indented SL row carrying the SL
     *   debit / credit,
     *   reference_no (DV#), payee_name, particulars, rfa_number, saro_number,
     *   prepared_by_name / _title, reviewed_by_name / _title, approved_by_name /
     *   _title, approved_by_label ("Certified Correct:" default, e.g.
     *   "Approved by:"), footer_remarks.
     */
    public function model(array $p): array
    {
        $t     = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $money = static fn (float $v) => $v != 0 ? PrintService::money(abs($v)) : '';
        $total = static fn (float $v) => $v != 0 ? PrintService::money($v) : '-';

        $rc     = $t('responsibility_center');
        $rows   = [];
        $slD    = $slC = $glD = $glC = 0.0;
        $lines  = array_values(is_array($p['lines'] ?? null) ? $p['lines'] : []);
        $titles = $this->accountTitles($lines);
        foreach ($lines as $i => $l) {
            $d      = PrintService::amount($l['debit'] ?? $l['debit_amount'] ?? 0);
            $c      = PrintService::amount($l['credit'] ?? $l['credit_amount'] ?? 0);
            $slCode = PrintService::text($l['sl_code'] ?? '');
            $slPath = PrintService::text($l['sl_path'] ?? $l['sl_detail_path'] ?? '');
            if ($slCode === '' && $slPath !== '') {
                $parts  = array_values(array_filter(array_map('trim', preg_split('/›|>/u', $slPath))));
                $slCode = $parts ? end($parts) : '';
            }
            $hasSl = $slCode !== '' || $slPath !== '';
            $glD += $d;
            $glC += $c;
            if ($hasSl) {
                $slD += $d;
                $slC += $c;
            }
            // the GL line: title, account code, GL amounts ("P" marks the first posting)
            $rows[] = ['kind' => 'gl', 'rc' => PrintService::text($l['responsibility_center'] ?? '') ?: $rc,
                'text' => PrintService::text($l['account_title'] ?? '') ?: ($titles[PrintService::text($l['account_code'] ?? '')] ?? ''),
                'code' => PrintService::text($l['account_code'] ?? ''),
                'p' => $i === 0 ? 'P' : '', 'gl_d' => $money($d), 'gl_c' => $money($c)];
            if ($hasSl) {
                $desc = trim((string) ($l['description'] ?? ''));
                if ($desc !== '') {
                    $rows[] = ['kind' => 'desc', 'text' => $desc];
                }
                $rows[] = ['kind' => 'sl', 'text' => $slPath !== '' ? $slPath : $slCode, 'sl' => $slCode, 'sl_d' => $money($d), 'sl_c' => $money($c)];
            }
        }

        $number = $t('jev_number');
        $temp   = $number === '' || stripos($number, 'TEMP') === 0;

        $signatories = [];
        foreach (self::SIGNATORIES as [$key, $label, $title]) {
            $signatories[] = [
                'label' => $key === 'approved_by' ? ($t('approved_by_label') ?: $label) : $label,
                'name'  => $t("{$key}_name"),
                'title' => $t("{$key}_title") ?: $title,
            ];
        }

        return [
            'jev_number'   => $temp ? '' : $number,
            'temp_number'  => $temp ? $number : '',
            'date'         => $t('jev_date') === '' ? '' : PrintService::longDate($t('jev_date')),
            'office'       => mb_strtoupper($t('office') ?: 'MAIN OFFICE'),
            'rows'         => $rows,
            'min_rows'     => self::MIN_ENTRY_ROWS,
            'reference_no' => $t('reference_no'),
            'payee'        => $t('payee_name'),
            'particulars'  => trim((string) ($p['particulars'] ?? '')),
            'rfa_number'   => $t('rfa_number'),
            'saro_number'  => $t('saro_number'),
            'totals'       => ['sl_d' => $total($slD), 'sl_c' => $total($slC), 'gl_d' => $total($glD), 'gl_c' => $total($glC)],
            'signatories'  => $signatories,
            'footer'       => $t('footer_remarks'),
            'header_parts' => null,
            'footer_text'  => '',
            'file_base'    => 'JEV-' . (preg_replace('/[^A-Za-z0-9\-]/', '_', preg_replace('/^JEV-/i', '', $number)) ?: 'DRAFT'),
        ];
    }

    /**
     * Account titles from fims.chart_of_accounts for the lines sent without one
     * (account code => title). One query per print; [] when the table can't be read
     * — the title then just stays blank.
     */
    private function accountTitles(array $lines): array
    {
        $codes = [];
        foreach ($lines as $l) {
            $code = PrintService::text($l['account_code'] ?? '');
            if ($code !== '' && PrintService::text($l['account_title'] ?? '') === '') {
                $codes[$code] = App\QuotedValue($code, App\DataType::STRING, 'DB');
            }
        }
        if (!$codes) {
            return [];
        }
        try {
            $titles = [];
            foreach (App\ExecuteRows(
                'SELECT account_code, account_title FROM fims.chart_of_accounts WHERE account_code IN (' . implode(',', $codes) . ')',
                'DB'
            ) ?: [] as $r) {
                $titles[$r['account_code']] = $r['account_title'];
            }

            return $titles;
        } catch (\Throwable $e) {
            App\LogError('JevPrintService::accountTitles ' . $e->getMessage());

            return [];
        }
    }

    /**
     * A4 landscape (842 × 595pt), 12mm sides / 9mm top-bottom (774pt wide), Arial.
     * Mirrors scripts/gen_jev_docx.js. $media as CbaPrintService::html().
     * Dompdf ignores <colgroup> widths, so the first row carries them.
     */
    public function html(array $m, string $media = 'screen'): string
    {
        $e   = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $nl  = static fn ($v) => nl2br(htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'), false);
        $w   = static fn (float $pct, string $css = '') => ' style="width:' . $pct . '%;' . $css . '"';   // one style attribute per cell
        $php = '<span class="sym">₱</span>&nbsp;';   // ₱ — not in Helvetica for Dompdf
        // 9 columns: RC | Accounts and Explanation | SL Code | SL Dr | SL Cr | Acct Code | P | GL Dr | GL Cr
        $widths = [6.6, 43, 6.9, 8.3, 8.3, 8.3, 1.9, 8.3, 8.4];
        $row = static function (array $c): string {   // $c: 9 cells as [html, class]
            return '<tr>' . implode('', array_map(static fn ($x) => '<td' . ($x[1] ? ' class="' . $x[1] . '"' : '') . '>' . $x[0] . '</td>', $c)) . '</tr>';
        };
        $text = static fn (string $html, string $cls = 'ac') => $row([['', 'rc'], [$html, $cls], ['', ''], ['', ''], ['', ''], ['', ''], ['', ''], ['', ''], ['', '']]);

        $body  = '';
        $count = 0;
        foreach ($m['rows'] as $r) {
            $count++;
            if ($r['kind'] === 'gl') {
                $body .= $row([[$e($r['rc']), 'rc'], [$e($r['text']), 'ac'], ['', 'sl'], ['', 'am'], ['', 'am'],
                    [$e($r['code']), 'gc'], [$r['p'], 'p'], [$e($r['gl_d']), 'am'], [$e($r['gl_c']), 'am']]);
            } elseif ($r['kind'] === 'desc') {
                $body .= $text($nl($r['text']), 'ac in1');
            } else {
                $body .= $row([['', 'rc'], [$e($r['text']), 'ac in2'], [$e($r['sl']), 'sl'], [$e($r['sl_d']), 'am'], [$e($r['sl_c']), 'am'],
                    ['', ''], ['', ''], ['', ''], ['', '']]);
            }
        }
        for (; $count < $m['min_rows']; $count++) {
            $body .= $text('&nbsp;');
        }
        $body .= $text('Reference:', 'ac ref')
            . $text('DV# &nbsp;' . $e($m['reference_no']), 'ac in1')
            . $text($e($m['payee']) ?: '&nbsp;', 'ac in1')
            . $text('Particulars', 'ac b')
            . $text($nl($m['particulars']) ?: '&nbsp;', 'ac part')
            . $text('RFA No. &nbsp;' . $e($m['rfa_number']))
            . $text('SARO &nbsp;' . $e($m['saro_number']));

        $tt   = $m['totals'];
        $sigs = '';
        foreach ($m['signatories'] as $s) {
            $sigs .= '<td' . $w(33.33) . '><div class="sig-lbl">' . $e($s['label']) . '</div>'
                . '<div class="sig-name' . ($s['name'] === '' ? ' blank' : '') . '">' . ($e($s['name']) ?: '&nbsp;') . '</div><div class="sig-title">' . $e($s['title']) . '</div></td>';
        }

        [$top, $hdrCss] = PrintService::repeatingHeaderCss($m, 774, 80, 25.5, 6);
        $pageCss = $media === 'print'
            ? '@page{size:A4 landscape;margin:' . $top . 'pt 34pt 25.5pt 34pt;}body{margin:0;}' . $hdrCss
            : 'html{background:#eceff1;}body{margin:0;padding:16px 0;}'
              . '.sheet{width:842pt;min-height:595pt;margin:0 auto;padding:25.5pt 34pt;box-sizing:border-box;'
              . 'background:#fff;box-shadow:0 1px 4px rgba(0,0,0,.18);}';

        $widthRow = '<tr class="widths">' . implode('', array_map(static fn ($p) => '<td style="width:' . $p . '%;"></td>', $widths)) . '</tr>';

        return '<!doctype html><html><head><meta charset="utf-8"><title>' . $e($m['file_base']) . '</title><style>'
            . $pageCss
            . 'body,table{font-family:Arial,Helvetica,sans-serif;font-size:8pt;color:#000;line-height:1.25;}'
            . 'p{margin:0;}table{width:100%;border-collapse:collapse;table-layout:fixed;}'
            . '.hdr{text-align:center;margin-bottom:6pt;}.hdr>img{width:auto !important;height:auto;max-width:774pt;max-height:80pt;}'
            . '.sym{font-family:"DejaVu Sans",sans-serif;font-size:7pt;font-weight:bold;}'
            . '.b{font-weight:bold;}'
            . '.head{border:1.2pt solid #000;}.head td{vertical-align:middle;}'
            . '.head .agency{border-right:1.2pt solid #000;text-align:center;padding:5pt 8pt;}'
            . '.form-title{font-size:16pt;font-weight:bold;letter-spacing:.5pt;}'
            . '.agency-nm{font-family:"Times New Roman",Times,serif;font-size:12pt;font-weight:bold;text-decoration:underline;margin-top:3pt;}'
            . '.office{padding:0;}.office div{padding:3pt 5pt;}'
            . '.office .off-title{font-size:10pt;font-weight:bold;text-align:center;border-bottom:1.2pt solid #000;}'
            . '.office .off-no{font-size:8.5pt;border-top:.75pt solid #000;}.office .off-no.first{border-top:none;}'
            . '.main{border:1.2pt solid #000;margin-top:-1pt;}'
            . '.main th,.main td{border:.75pt solid #000;padding:1.5pt 3pt;font-size:7.5pt;vertical-align:middle;}'
            . '.main tr.widths td{border:none;padding:0;height:0;}'
            . '.main th{font-weight:bold;text-align:center;}.main th.entries{font-size:8.5pt;}'
            . '.main td.rc{text-align:center;font-size:7pt;}.main td.sl{text-align:center;}'
            . '.main td.am{text-align:right;font-family:"Courier New",Courier,monospace;white-space:nowrap;}'
            . '.main td.gc{text-align:center;font-family:"Courier New",Courier,monospace;font-weight:bold;}'
            . '.main td.p{text-align:center;font-weight:bold;padding:1.5pt 1pt;}'
            . '.main td.in1{padding-left:12pt;}.main td.in2{padding-left:20pt;font-size:7pt;color:#333;}'
            . '.main td.ref{color:#444;}.main td.part{padding:3pt;line-height:1.5;}'
            . '.main tr.total td{font-weight:bold;background:#f6f6f6;border-top:1.5pt solid #000;}'
            . '.sigs{border:1.2pt solid #000;border-top:none;}'
            . '.sigs td{border:.75pt solid #000;text-align:center;vertical-align:top;padding:5pt 6pt 6pt;font-size:8pt;}'
            . '.sig-lbl{text-align:left;}.sig-name{font-weight:bold;font-size:8.5pt;text-decoration:underline;margin-top:18pt;}'
            . '.sig-name.blank{text-decoration:none;}.sig-title{font-size:7.5pt;margin-top:1pt;}'
            . '.foot{font-size:8pt;font-style:italic;font-weight:bold;margin-top:4pt;}'
            . '.temp{text-align:right;font-size:7.5pt;color:#555;margin-top:4pt;font-family:"Courier New",Courier,monospace;}'
            . '</style></head>'
            . PrintService::bodyOpen($media, 'sheet', '<div class="hdr">' . PrintService::headerHtml($m, 774, 'JEV') . '</div>', '')
            . '<table class="head"><tr><td class="agency"' . $w(78) . '><div class="form-title">JOURNAL ENTRY VOUCHER</div>'
            . '<div class="agency-nm">NATIONAL HOUSING AUTHORITY</div></td>'
            . '<td class="office"' . $w(22) . '><div class="off-title">' . $e($m['office']) . '</div>'
            . '<div class="off-no first">JEV No. : &nbsp;' . $e($m['jev_number']) . '</div><div class="off-no">Date : &nbsp;' . $e($m['date']) . '</div></td></tr></table>'
            . '<table class="main">' . $widthRow
            . '<tr><th rowspan="3" style="font-size:6pt;">Responsibility<br>Center</th><th rowspan="3">Accounts and Explanation</th><th rowspan="3" style="font-size:6.5pt;">SL<br>Code</th>'
            . '<th colspan="3" class="entries">ACCOUNTING ENTRIES</th><th rowspan="3"></th><th colspan="2" style="border-top:none;border-right:none;"></th></tr>'
            . '<tr><th colspan="2">Subsidiary Ledger</th><th rowspan="2">Account Code</th><th colspan="2">General Ledger</th></tr>'
            . '<tr><th>Debit</th><th>Credit</th><th>Debit</th><th>Credit</th></tr>'
            . $body
            . '<tr class="total"><td colspan="3" style="text-align:center;">TOTAL</td>'
            . '<td class="am">' . $php . $e($tt['sl_d']) . '</td><td class="am">' . $php . $e($tt['sl_c']) . '</td><td></td><td></td>'
            . '<td class="am">' . $php . $e($tt['gl_d']) . '</td><td class="am">' . $php . $e($tt['gl_c']) . '</td></tr></table>'
            . '<table class="sigs"><tr>' . $sigs . '</tr></table>'
            . ($m['footer'] !== '' ? '<div class="foot">' . $e($m['footer']) . '</div>' : '')
            . ($m['temp_number'] !== '' ? '<div class="temp">Temporary Reference No.: <b>' . $e($m['temp_number']) . '</b></div>' : '')
            . PrintService::bodyClose($media, '');
    }
}
