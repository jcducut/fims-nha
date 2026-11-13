<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * DV (Disbursement Voucher — General Form No. 1, revised November 3, 1992)
 * print form. PDF ONLY: the official form's PDF is filled with pdftk (no HTML /
 * DOCX layout of its own), ported from the old REGIONAL
 * DisbursementService::buildDvFieldMap() / fillDvTemplate().
 *
 * Two papers (payload `paper`):
 *   plain      (default) — _shared/template/dv_form.pdf (dv.pdf's fields moved onto
 *              the boxes of the scanned form) filled, flattened, laid over
 *              _shared/template/dv_background.pdf page 1 (the cleaned scan of the
 *              official form), plus page 2 of it — the back: Instructions +
 *              Checklist (`back_page`, default true).
 *   preprinted — _shared/template/dv.pdf (REGIONAL's field layout for the
 *              physical green forms) filled and flattened, text only, to print
 *              onto pre-printed DV stock. No back page.
 * The background and the aligned field layout were built from the scan in
 * ACCOUNTING/app/pdf-forms/templates/DV-10-9-2026.pdf — see
 * system_docs/modules/PRINT-FORMS.md "DV".
 *
 * Printed through PrintService::print('DV', …, 'pdf') — see PrintService::DOCS.
 */
class DvPrintService
{
    private const FORM_PLAIN      = 'dv_form.pdf';
    private const FORM_PREPRINTED = 'dv.pdf';
    private const BACKGROUND      = 'dv_background.pdf';   // page 1 = front, page 2 = back

    // How many rows the form has.
    private const ENTRY_ROWS     = 11;
    private const DEDUCTION_ROWS = 12;

    /**
     * DV model. Payload (all optional):
     *   voucher_number, route ('MAIN' default | 'REGIONAL' — who signs which box,
     *   and the paying agency's "- MAIN OFFICE" / "- REGIONAL OFFICE"),
     *   paying_agency (overrides the agency line), payee_name, payee_address,
     *   payee_tin, particulars, gross_amount (or amount), deductions [{ description,
     *   amount }] (up to 12 lines + the total), total_deductions (default their sum),
     *   accounting_entries [{ obligation, fpa, object_code, account_code, debit,
     *   credit }] (up to 11 rows),
     *   Box A: cert_officer1_name / _title (one signer), + cert_officer2_name /
     *          _title (a second co-signer, e.g. the Regional Manager),
     *   Box B (accountant's certification): MAIN — fmd_officer_name / _title
     *          (blank → the current FMD Department Manager) + accountant_name /
     *          _title; REGIONAL — accountant_name / _title alone,
     *   Box C: approving_officer / approver_title (REGIONAL route; MAIN leaves it blank),
     *   paper ('plain' | 'preprinted'), back_page (plain paper only; default true).
     */
    public function model(array $p): array
    {
        $t      = static fn (string $k) => PrintService::text($p[$k] ?? '');
        $fmt    = static fn (float $n) => number_format($n, 2);
        $fmtOpt = static fn (float $n) => $n > 0 ? number_format($n, 2) : '';
        $isMain = strtoupper($t('route') ?: 'MAIN') !== 'REGIONAL';
        $gross  = PrintService::amount($p['gross_amount'] ?? $p['amount'] ?? null);
        $two    = $t('cert_officer2_name') !== '';   // Box A with two co-signers

        $payee = $t('payee_name') . ($t('payee_address') !== '' ? ', ' . $t('payee_address') : '');

        $fmdName = $t('fmd_officer_name');
        if ($isMain && $fmdName === '') {
            $fmdName = PrintService::resolveSignatoryName('FMD Department Manager') ?? '';
        }

        $fields = [
            'voucher_id'   => $t('voucher_number'),
            'AGENCY'       => $t('paying_agency') ?: 'NATIONAL HOUSING AUTHORITY - ' . ($isMain ? 'MAIN OFFICE' : 'REGIONAL OFFICE'),
            'PAYEE'        => $payee,
            'TIN'          => $t('payee_tin'),
            'PARTICULARS'  => trim((string) ($p['particulars'] ?? '')),
            'AMOUNT'       => $gross > 0 ? $fmt($gross) : '',
            'AMOUNT_WORDS' => $gross > 0 ? $this->amountInWords($gross) : '',
            // Box A — one signer centred (SIGN_1), or two side by side (SIGN_7 / SIGN_8)
            'SIGN_1' => $two ? '' : $t('cert_officer1_name'), 'POS_1' => $two ? '' : $t('cert_officer1_title'),
            'SIGN_7' => $two ? $t('cert_officer1_name') : '', 'POS_7' => $two ? $t('cert_officer1_title') : '',
            'SIGN_8' => $two ? $t('cert_officer2_name') : '', 'POS_8' => $two ? $t('cert_officer2_title') : '',
            // Box B certification — MAIN: FMD officer + accountant; REGIONAL: the accountant (wider SIGN_6)
            'SIGN_2' => $isMain ? $fmdName : '', 'POS_2' => $isMain ? ($t('fmd_officer_title') ?: ($fmdName !== '' ? 'Department Manager, FMD' : '')) : '',
            'SIGN_3' => $isMain ? $t('accountant_name') : '', 'POS_3' => $isMain ? $t('accountant_title') : '',
            'SIGN_6' => $isMain ? '' : $t('accountant_name'), 'POS_6' => $isMain ? '' : $t('accountant_title'),
            // Box C — approving officer (REGIONAL route)
            'SIGN_4' => $isMain ? '' : $t('approving_officer'), 'POS_4' => $isMain ? '' : $t('approver_title'),
        ];

        $entries = array_values(is_array($p['accounting_entries'] ?? null) ? $p['accounting_entries'] : []);
        for ($i = 0; $i < self::ENTRY_ROWS; $i++) {
            $n = $i + 1;
            $e = $entries[$i] ?? [];
            $fields["OBLG_{$n}"]             = PrintService::text($e['obligation'] ?? '');
            $fields["OBLG_FPA_{$n}"]         = PrintService::text($e['fpa'] ?? '');
            $fields["OBJ_CODE_{$n}"]         = PrintService::text($e['object_code'] ?? '');
            $fields["acct_code_{$n}"]        = PrintService::text($e['account_code'] ?? '');
            $fields["acct_code_{$n}_debit"]  = $fmtOpt(PrintService::amount($e['debit'] ?? $e['debit_amount'] ?? null));
            $fields["acct_code_{$n}_credit"] = $fmtOpt(PrintService::amount($e['credit'] ?? $e['credit_amount'] ?? null));
        }

        $deductions = array_values(array_filter(array_map(static fn ($d) => [
            'label'  => PrintService::text($d['description'] ?? $d['label'] ?? '') ?: 'Other Deduction',
            'amount' => PrintService::amount($d['amount'] ?? null),
        ], is_array($p['deductions'] ?? null) ? $p['deductions'] : []), static fn ($d) => $d['amount'] > 0));
        foreach (array_slice($deductions, 0, self::DEDUCTION_ROWS) as $i => $d) {
            $fields['DEDUC_' . ($i + 1)]     = $d['label'];
            $fields['DEDUC_AMT_' . ($i + 1)] = $fmt($d['amount']);
        }
        if ($deductions) {
            $total = isset($p['total_deductions']) && $p['total_deductions'] !== ''
                ? PrintService::amount($p['total_deductions'])
                : array_sum(array_column($deductions, 'amount'));
            $fields['DEDUC_13']    = 'Total Deductions';
            $fields['DEDUC_TOTAL'] = $fmt($total);
        }

        $paper  = strtolower($t('paper')) === 'preprinted' ? 'preprinted' : 'plain';
        $number = $t('voucher_number');

        return [
            'fields'    => $fields,
            'paper'     => $paper,
            'back_page' => $paper === 'plain' && !in_array($p['back_page'] ?? true, [false, 'false', 0, '0'], true),
            'file_base' => $number !== '' ? preg_replace('/[^A-Za-z0-9\-]/', '_', $number) : 'DV-DRAFT',
        ];
    }

    /** The filled DV as PDF bytes, or null (logged) when pdftk fails. */
    public function pdf(array $m): ?string
    {
        $dir  = PrintService::repoRoot() . '/_shared/template/';
        $form = $dir . ($m['paper'] === 'preprinted' ? self::FORM_PREPRINTED : self::FORM_PLAIN);
        if (!is_file($form) || ($m['paper'] === 'plain' && !is_file($dir . self::BACKGROUND))) {
            App\LogError('DvPrintService: template missing in ' . $dir);

            return null;
        }

        $tmp   = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dv_' . bin2hex(random_bytes(8));
        $files = ['fdf' => $tmp . '.fdf', 'filled' => $tmp . '_filled.pdf', 'front' => $tmp . '_front.pdf', 'out' => $tmp . '_out.pdf',
                  'form' => $tmp . '_form.pdf', 'background' => $tmp . '_bg.pdf'];
        try {
            // pdftk reads its inputs in many small reads — painfully slow on a mounted
            // folder (20 s+ per call in the Docker dev setup); local temp copies take < 1 s.
            if (!copy($form, $files['form']) || ($m['paper'] === 'plain' && !copy($dir . self::BACKGROUND, $files['background']))) {
                App\LogError('DvPrintService: could not copy the templates to ' . sys_get_temp_dir());

                return null;
            }
            file_put_contents($files['fdf'], $this->fdf($m['fields']));
            if (!$this->pdftk([$files['form'], 'fill_form', $files['fdf'], 'output', $files['filled'], 'flatten'])) {
                return null;
            }
            $result = $files['filled'];

            if ($m['paper'] === 'plain') {
                // the official form behind the values, then (optionally) its back page
                if (!$this->pdftk([$files['filled'], 'multibackground', $files['background'], 'output', $files['front']])) {
                    return null;
                }
                $result = $files['front'];
                if ($m['back_page']) {
                    if (!$this->pdftk(['A=' . $files['front'], 'B=' . $files['background'], 'cat', 'A1', 'B2', 'output', $files['out']])) {
                        return null;
                    }
                    $result = $files['out'];
                }
            }

            $bytes = file_get_contents($result);

            return ($bytes === false || $bytes === '') ? null : $bytes;
        } finally {
            foreach ($files as $f) {
                @unlink($f);
            }
        }
    }

    /** Runs pdftk with the given arguments; false (logged) on failure. */
    private function pdftk(array $args): bool
    {
        $bin = getenv('PDFTK_PATH') ?: (strncasecmp(PHP_OS, 'WIN', 3) === 0 ? 'C:/Program Files (x86)/PDFtk Server/bin/pdftk.exe' : 'pdftk');
        exec(escapeshellarg($bin) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1', $out, $code);
        if ($code !== 0) {
            App\LogError('DvPrintService: pdftk ' . ($args[1] ?? '') . ' failed: ' . implode(' ', $out));

            return false;
        }

        return true;
    }

    /**
     * FDF for pdftk fill_form — plain "(…)" strings in PDFDocEncoding, as the old
     * REGIONAL / ACCOUNTING generators (UTF-16 hex strings leave pdftk-java's
     * multiline fields blank). Text is converted to Windows-1252; "₱" → "P".
     */
    private function fdf(array $fields): string
    {
        $lines = [];
        foreach ($fields as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $value = str_replace('₱', 'P', (string) $value);
            $value = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $value) ?: $value;
            $esc   = static fn ($v) => str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $v);
            $lines[] = '<< /T (' . $esc($name) . ') /V (' . $esc($value) . ') >>';
        }

        return "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [\n" . implode("\n", $lines)
            . "\n] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }

    /** "EIGHTY THOUSAND PESOS ONLY." / "… PESOS AND TWENTY-FIVE/100 ONLY." — the old REGIONAL DV wording. */
    private function amountInWords(float $amount): string
    {
        $cents = (int) round(abs($amount) * 100);
        $pesos = intdiv($cents, 100);
        $words = preg_replace('/\s+Pesos$/', '', PrintService::amountInWords((float) $pesos));
        $c     = $cents % 100;
        $centWords = '';
        if ($c > 0) {
            $centWords = preg_replace('/\s+Pesos$/', '', PrintService::amountInWords((float) $c));
        }

        return strtoupper($words . ' Peso' . ($pesos !== 1 ? 's' : '') . ($c > 0 ? ' and ' . $centWords . '/100' : '') . ' Only.');
    }
}
