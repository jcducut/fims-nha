<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Check Disbursement Voucher module (app/pages/check-disbursement-voucher-page.php),
 * ported from the TREASURY reference project's 7 check-processing pages
 * (check-preparation, check-registry, check-released-to-claimants,
 * check-released-regions, check-cancellation-replacements, check-inventory-raf).
 *
 * The tables this module needs exist in the `fims` schema
 * (_assets/sql/01 - fims/01 - table/26 through 33 — bank_accounts,
 * check_booklets, checks, check_cancellations, check_claimant_releases,
 * check_claimant_email_log, check_region_releases, check_region_email_log).
 *
 * Check Preparation (getBankAccounts/getDvsForPrep/getChecksList/saveCheck)
 * is wired to real queries, including the connection to
 * fims.disbursement_vouchers: only a DV whose status is FOR_TREASURY can
 * have a check prepared against it, and doing so hands the DV off to
 * DisbursementVoucherService::changeStatus() to flip it to RELEASED — the
 * same transition/authorization logic that service already enforces for
 * every other caller (Treasury RC or admin only).
 *
 * Released to Claimants (saveRelease/sendReleaseNotice/previewReleaseNotice)
 * and Released to Regions (saveRegionRelease/sendRegionReleaseNotice/
 * previewRegionReleaseNotice/resendRegionEmail) are wired too, including
 * real SMTP sending — same mechanism the TREASURY reference project used
 * (the global App\SendEmail() helper, a thin PHPMailer wrapper in
 * src/phpfn.php; see sendModuleEmail()), not PHPMaker's templated-file
 * Email::load() class, since these notices are per-check dynamic HTML
 * (and, for regions, bundle a variable number of checks into one email),
 * not a fixed single-recipient template. This project's own .env already
 * has real Gmail SMTP credentials configured (Config("SMTP.*")/
 * Config("SENDER_EMAIL")) — sending here will actually dispatch real email.
 * A claimant release only captures an email address when release_type =
 * EMAIL_NOTICE (claimant_email column); region releases always go through
 * a department (department_id, FK to uac.departments) since "Send Monthly
 * Notice" bundles every currently-PENDING release for that department into
 * one email. Email templates are PHP string builders
 * (buildClaimantEmailBody/buildRegionEmailBody), not separate template
 * files or DB rows — same approach TREASURY used.
 *
 * Check Registry (getRegistryList/getSeriesList/getBooklets/
 * addRegisterBatch/updateRegisterStatus/getRegistrySummary/getRegistryEntry)
 * is a faithful port of the TREASURY reference project's
 * CheckRegistryService.php, backed by its own standalone table,
 * fims.check_register (_assets/sql/01 - fims/01 - table/34) — deliberately
 * decoupled from fims.checks, exactly as TREASURY keeps treasury.
 * check_register separate from treasury.checks. One row per physical blank
 * check number, materialized in full the moment a batch is registered
 * (addRegisterBatch() does one bulk INSERT, never a range computed on the
 * fly), with its own independent entry_type (RECEIVED|ISSUED|CANCELLED|
 * VOIDED|SPOILED|STALE|REPLACED) and current_status (UNUSED|ISSUED|
 * ENCASHED|OUTSTANDING|CANCELLED|VOIDED|SPOILED|STALE) columns — a status
 * change (updateRegisterStatus()) is always an UPDATE-in-place on that same
 * row, never a new INSERT. check_register.check_id is a loose, unenforced
 * pointer to fims.checks for whenever this check number is actually
 * prepared against a DV (same as TREASURY) — Check Preparation's saveCheck()
 * does not read or write fims.check_register at all; the two are
 * intentionally independent ledgers.
 *
 * Cancellation and RAF are still stubs; the `accounting`/`shared`/
 * `workflow`/`fmd` schemas their source pages also joined against are still
 * out of scope. Every stub method here returns a structurally-correct but
 * empty response so the UI renders and behaves correctly (tables show "no
 * records", KPI cards show zero) without erroring.
 *
 * Print Agent (markPrinted/generatePrintAgentPackage) is a faithful port
 * of TREASURY's check-printing feature: browsers can't talk to a printer
 * directly or install local software, so a tiny local PHP HTTP server
 * (/print-agent at the project root — copied verbatim from TREASURY's own,
 * since it's already bank-agnostic Windows+PHP+PowerShell, nothing
 * NHA-specific) runs on the user's machine at http://127.0.0.1:9123 and
 * does the actual rendering (headless Chrome/Edge screenshot) + printing
 * (.NET PrintDocument via PowerShell). The page talks to it directly from
 * the browser; this service only (a) streams a ready-to-run installer zip
 * for it, and (b) records printed_at once a single-check print succeeds
 * (batch prints don't mark anything printed, same as TREASURY). The check
 * layout rendered client-side is a single generic template (MICR line with
 * real per-bank BRSTN routing codes, payee/amount/date, signatories) —
 * TREASURY's three pixel-tuned per-bank layouts (with barcode/QR codes)
 * aren't ported, since that tuning is calibrated against NHA's actual
 * pre-printed check stock, which can't be verified without the physical
 * forms in hand.
 */
class CheckProcessingService
{
    private string $tableChecks            = 'fims.checks';
    private string $tableBankAccounts      = 'fims.bank_accounts';
    private string $tableCheckRegister     = 'fims.check_register';
    private string $tableCashPosition      = 'fims.cash_position';
    private string $tableDv                = 'fims.disbursement_vouchers';
    private string $tableSettingsStatus    = 'fims.settings_status';
    private string $tableClaimantReleases  = 'fims.check_claimant_releases';
    private string $tableClaimantEmailLog  = 'fims.check_claimant_email_log';
    private string $tableRegionReleases    = 'fims.check_region_releases';
    private string $tableRegionEmailLog    = 'fims.check_region_email_log';
    private string $tableDepartments       = 'uac.departments';

    private const REGISTER_STATUSES   = ['UNUSED', 'ISSUED', 'ENCASHED', 'OUTSTANDING', 'CANCELLED', 'VOIDED', 'SPOILED', 'STALE'];
    private const REGISTER_ENTRY_TYPES = ['RECEIVED', 'ISSUED', 'CANCELLED', 'VOIDED', 'SPOILED', 'STALE', 'REPLACED'];
    private const REGISTER_DEPARTMENT_TYPES = ['MAIN_OFFICE', 'REGION', 'BRANCH', 'PROJECT', 'OTHER'];
    // TREASURY's own range cap (_assets/TREASURY/app/pages/check-registry.php's _buildRangeChecks()).
    private const MAX_REGISTER_BATCH_SIZE = 2000;

    // Per-booklet cap on how many unused check numbers getUnusedChecks() will
    // enumerate — a booklet's range is usually small (tens of checks), but
    // this guards against a bad/huge range turning one request into
    // thousands of select options.
    private const MAX_UNUSED_CHECKS_PER_BOOKLET = 500;

    // ── small SQL helpers (same shape as DisbursementVoucherService) ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function intOrNull(mixed $v): string
    {
        return ($v !== null && $v !== '' && ctype_digit((string) $v)) ? (string) (int) $v : 'NULL';
    }

    private function dateOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    // ── email (SMTP) ──

    // Thin wrapper around the project-wide App\SendEmail() helper (PHPMailer
    // under the hood, configured from Config("SMTP.*")/Config("SENDER_EMAIL")
    // — see this class's docblock for why these two features call it
    // directly instead of going through PHPMaker's Email::load() template
    // class. Returns a plain success/message array instead of SendEmail()'s
    // own true|string-error-message return, so callers don't need to know
    // that convention.
    private function sendModuleEmail(string $to, string $subject, string $htmlBody): array
    {
        $result = App\SendEmail(
            App\Config('SENDER_EMAIL'),
            $to,
            '',
            '',
            $subject,
            $htmlBody,
            'HTML',
            'utf-8',
            App\Config('SMTP.SECURE_OPTION')
        );
        if ($result === true) {
            return ['success' => true];
        }

        return ['success' => false, 'message' => is_string($result) && $result !== '' ? $result : 'Unknown SMTP error.'];
    }

    // Official "NOTICE OF AVAILABILITY OF CHECK FOR RELEASE" memo — ported
    // verbatim from the TREASURY reference project's
    // CheckReleaseService::_buildEmailBody() (field names adapted to this
    // project's schema: payee_name -> check_payee, voucher_number ->
    // dv_number, particulars kept as-is via the fims.disbursement_vouchers
    // join in fetchClaimantReleaseWithCheck()). Cites the real NHA
    // Memorandum Circular No. 2024-066 governing check release — this is
    // the actual document template, not placeholder copy, so the wording
    // is intentionally kept exactly as TREASURY had it rather than
    // simplified.
    private function buildClaimantEmailBody(array $release, string $extraMessage): string
    {
        $payee      = htmlspecialchars($release['check_payee'] ?? '');
        $checkNo    = htmlspecialchars($release['check_number'] ?? "\u{2014}");
        $dvNo       = htmlspecialchars($release['dv_number'] ?? "\u{2014}");
        $bankName   = htmlspecialchars($release['bank_name'] ?? '');
        $particulars = trim((string) ($release['particulars'] ?? ''));
        $natureOfPayment = $particulars !== '' ? htmlspecialchars($particulars) : str_repeat('&nbsp;', 30);
        $checkDate  = !empty($release['check_date']) ? date('m/d/Y', strtotime($release['check_date'])) : "\u{2014}";
        $today      = (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('F d, Y');
        $annexBlank   = str_repeat('&nbsp;', 6);
        $payableBlank = str_repeat('&nbsp;', 45);

        $extraNote = $extraMessage !== ''
            ? '<tr><td style="padding-bottom:14px;"><p style="margin:14px 0 0;font-size:13px;color:#374151;font-style:italic">' . nl2br(htmlspecialchars($extraMessage)) . '</p></td></tr>'
            : '';

        return '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
@media print {
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { margin: 0; padding: 0; background: white !important; }
  .outer-wrap { background: white !important; padding: 0 !important; }
  .inner-wrap { width: 100% !important; max-width: 100% !important; border: none !important; padding: 16px !important; box-shadow: none !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#111;">
<table class="outer-wrap" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:28px 0;">
  <tr><td align="center">
  <table class="inner-wrap" width="620" cellpadding="0" cellspacing="0" style="background:#fff;padding:36px 44px;border:1px solid #ccc;">

    <!-- Subject Bar -->
    <tr><td style="padding-bottom:4px;border-bottom:1px solid #000;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="font-size:12px;font-weight:bold;width:80px;vertical-align:top;padding-bottom:6px;">SUBJECT</td>
          <td style="font-size:12px;font-weight:bold;vertical-align:top;padding-bottom:6px;">:&nbsp;&nbsp;NOTICE OF AVAILABILITY OF CHECK FOR RELEASE IN FAVOR OF <u>' . $payee . '</u></td>
        </tr>
      </table>
    </td></tr>
    <tr><td style="padding-bottom:14px;"></td></tr>

    <!-- Date -->
    <tr><td style="padding-bottom:14px;">
      <span style="font-size:13px;">Date: <u>' . $today . '</u></span>
    </td></tr>

    <!-- Attention -->
    <tr><td style="padding-bottom:6px;">
      <span style="font-size:13px;">ATTENTION : <strong>Corporate Accountant</strong></span>
    </td></tr>

    <!-- Greeting -->
    <tr><td style="padding-bottom:14px;">
      <span style="font-size:13px;">Greetings!</span>
    </td></tr>

    <!-- Body paragraph -->
    <tr><td style="padding-bottom:14px;text-align:justify;line-height:1.7;">
      Please be informed that ' . ($bankName ?: 'the') . ' Check No. <strong><u>' . $checkNo . '</u></strong>
      dated <strong><u>' . $checkDate . '</u></strong> covered by DV#<strong><u>' . $dvNo . '</u></strong>
      in favor of <strong><u>' . $payee . '</u></strong> representing payment for
      <u>' . $natureOfPayment . '</u>
      is available for release and may be released thru Region.
    </td></tr>

    <!-- Documentary requirements paragraph -->
    <tr><td style="padding-bottom:14px;text-align:justify;line-height:1.7;">
      Release of the aforementioned check is subject to submission of documentary requirements in accordance
      with the Updated Guidelines and Procedures on the Release of Checks for Payment of Goods and Services to
      Stakeholders Representing Current Operating Expenses (COE)/Maintenance and Other Operating Expenses
      (MOOE) and Capital Expenditures per NHA Memorandum Circular No. 2024-066 dated October 08, 2024, kindly
      refer to the attached Annex&nbsp;<u>' . $annexBlank . '</u>&nbsp;re: Documentary Requirements Pertaining to Release of
      Check Payable to
      <u>' . $payableBlank . '</u>.
    </td></tr>

    <!-- Reminder -->
    <tr><td style="padding-bottom:6px;">
      Likewise, as a gentle reminder, please be guided of the following:
    </td></tr>
    <tr><td style="padding-bottom:14px;text-align:justify;line-height:1.7;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="width:22px;vertical-align:top;">a.</td>
          <td style="text-align:justify;line-height:1.7;">
            The said check shall become stale and no longer negotiable after one hundred eighty (180) days
            from the issue date. Thus, if an unclaimed/outstanding check has become stale despite the Notice
            of the Availability of check already sent either directly to the proper claimant/payee or through
            the initiating unit/Department/Regional/Sector Office, it shall be automatically cancelled together
            with its corresponding Disbursement Voucher/s, original copy of which shall be forwarded to the
            Accounting Department for their appropriate action.
          </td>
        </tr>
        <tr><td colspan="2" style="height:10px;"></td></tr>
        <tr>
          <td style="width:22px;vertical-align:top;">b.</td>
          <td style="text-align:justify;line-height:1.7;">
            A processing/service fee in the amount of P200.00 per check shall be charged for a replacement of
            a stale check or change of name of the proper claimant/payee upon request for a replacement of the
            stale check/change of name of payee. The said processing fee shall cover the cost of the check, and
            the manhours spent/incidental expenses in the reprocessing of a new Disbursement Voucher and the
            corresponding check.
          </td>
        </tr>
      </table>
    </td></tr>

    <!-- Call to action -->
    <tr><td style="padding-bottom:6px;">For information and request appropriate action.</td></tr>
    <tr><td style="padding-bottom:20px;">Kindly acknowledge receipt of this email.</td></tr>

    ' . $extraNote . '

    <!-- Signature -->
    <tr><td style="padding-bottom:4px;">Thank you.</td></tr>
    <tr><td style="padding-bottom:4px;">Regards,</td></tr>
    <tr><td style="padding-bottom:20px;"><strong>Treasury Department</strong></td></tr>

    <!-- Attachment notes -->
    <tr><td style="border-top:1px solid #ccc;padding-top:12px;font-size:12px;color:#555;line-height:1.6;">
      &bull; (Attachment of Annexes referring to the Annexes under NHA MC No. 2024-066 dtd. October 08, 2024)<br>
      &bull; (Attachment of duly approved DV)
    </td></tr>

  </table>
  </td></tr>
</table>
</body></html>';
    }

    // Bundled multi-check monthly notice to a region/department (Tab 4) —
    // ported verbatim from TREASURY's CheckRegionReleaseService::
    // buildEmailBody(): one notice paragraph per check, same official memo
    // wording/citation as the claimant version above.
    private function buildRegionEmailBody(array $releases, string $regionLabel, string $period, string $extraMessage): string
    {
        $region = htmlspecialchars($regionLabel);
        $annexBlank   = str_repeat('&nbsp;', 6);
        $payableBlank = str_repeat('&nbsp;', 45);
        $natureBlank  = str_repeat('&nbsp;', 30);

        $checkParas = '';
        foreach ($releases as $c) {
            $checkNo   = htmlspecialchars($c['check_number'] ?? "\u{2014}");
            $checkDate = !empty($c['check_date']) ? date('F d, Y', strtotime($c['check_date'])) : "\u{2014}";
            $dvNo      = htmlspecialchars($c['dv_number'] ?? "\u{2014}");
            $payee     = htmlspecialchars($c['payee'] ?? "\u{2014}");
            $bankName  = $c['bank_name'] ?? '';
            $particulars = trim((string) ($c['particulars'] ?? ''));
            $natureLine = $particulars !== '' ? '<strong>' . htmlspecialchars($particulars) . '</strong>' : '<u>' . $natureBlank . '</u>';
            $checkParas .= '
    <tr><td style="padding-bottom:14px;text-align:justify;line-height:1.7;">
      Please be informed that ' . ($bankName !== '' ? htmlspecialchars($bankName) : 'the') . ' Check No. <strong>' . $checkNo . '</strong> dated <strong>' . $checkDate . '</strong>
      covered by DV#<strong>' . $dvNo . '</strong> in favor of <strong>' . $payee . '</strong>
      representing payment for ' . $natureLine . '
      is available for release and may be released thru Region.
    </td></tr>';
        }

        $extraNote = $extraMessage !== ''
            ? '<tr><td style="padding-bottom:14px;font-size:13px;font-style:italic;">' . nl2br(htmlspecialchars($extraMessage)) . '</td></tr>'
            : '';

        return '<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<style>
@media print {
  * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
  body { margin: 0; padding: 0; background: white !important; }
  .outer-wrap { background: white !important; padding: 0 !important; }
  .inner-wrap { width: 100% !important; max-width: 100% !important; border: none !important; padding: 16px !important; box-shadow: none !important; }
}
</style>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#111;">
<table class="outer-wrap" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:28px 0;">
  <tr><td align="center">
  <table class="inner-wrap" width="620" cellpadding="0" cellspacing="0" style="background:#fff;padding:36px 44px;border:1px solid #ccc;">

    <!-- Subject Bar -->
    <tr><td style="padding-bottom:4px;border-bottom:1px solid #000;">
      <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
          <td style="font-size:12px;font-weight:bold;width:80px;vertical-align:top;padding-bottom:6px;">SUBJECT</td>
          <td style="font-size:12px;font-weight:bold;vertical-align:top;padding-bottom:6px;">:&nbsp;&nbsp;NOTICE OF AVAILABILITY OF CHECK FOR RELEASE IN FAVOR OF <u>' . $region . '</u></td>
        </tr>
      </table>
    </td></tr>
    <tr><td style="padding-bottom:14px;"></td></tr>

    <!-- Date -->
    <tr><td style="padding-bottom:14px;">
      <span style="font-size:13px;">Date: <u>' . (new \DateTime('now', new \DateTimeZone('Asia/Manila')))->format('F d, Y') . '</u></span>
    </td></tr>

    <!-- Attention -->
    <tr><td style="padding-bottom:6px;">
      <span style="font-size:13px;">ATTENTION: <strong>Corporate Accountant</strong></span>
    </td></tr>

    <!-- Greeting -->
    <tr><td style="padding-bottom:14px;">
      <span style="font-size:13px;">Greetings!</span>
    </td></tr>

    <!-- One paragraph per check -->
    ' . $checkParas . '

    <!-- Paragraph 2 -->
    <tr><td style="padding-bottom:14px;text-align:justify;line-height:1.7;">
      Release of the aforementioned check is subject to submission of documentary requirements in
      accordance with the Updated Guidelines and Procedures on the Release of Checks for Payment of
      Goods and Services to Stakeholders Representing Current Operating Expenses (COE)/Maintenance
      and Other Operating Expenses (MOOE) and Capital Expenditures per NHA Memorandum Circular No.
      2024-066 dated October 08, 2024, kindly refer to the attached Annex
      <u>' . $annexBlank . '</u>
      re: Documentary Requirements Pertaining to Release of Check Payable to
      <u>' . $payableBlank . '</u>
    </td></tr>

    <!-- Reminder items -->
    <tr><td style="padding-bottom:10px;line-height:1.7;">
      Likewise, as a gentle reminder, please be guided of the following:
    </td></tr>
    <tr><td style="padding-bottom:14px;text-align:justify;line-height:1.7;padding-left:20px;">
      a.&nbsp;&nbsp;The said check shall become stale and no longer negotiable after one hundred eighty (180)
      days from the issue date. Thus, if an unclaimed/outstanding check has become stale despite the
      Notice of the Availability of check already sent either directly to the proper claimant/payee or
      through the initiating unit/Department/Regional/Sector Office, it shall be automatically cancelled
      together with its corresponding Disbursement Vouchers, original copy of which shall be forwarded
      to the Accounting Department for their appropriate action.
    </td></tr>
    <tr><td style="padding-bottom:14px;text-align:justify;line-height:1.7;padding-left:20px;">
      b.&nbsp;&nbsp;A processing/service fee in the amount of P200.00 per check shall be charged for a
      replacement of a stale check or change of name of the proper claimant/payee and shall be
      shouldered by the proper claimant/payee upon request for a replacement of the stale
      check/change of name of payee. The said processing fee shall cover the cost of the check, and
      the manhours spent/incidental expenses in the reprocessing of a new Disbursement Voucher and
      the corresponding check.
    </td></tr>

    <tr><td style="padding-bottom:6px;">For information and request appropriate action.</td></tr>
    <tr><td style="padding-bottom:20px;">Kindly acknowledge receipt of this email.</td></tr>

    ' . $extraNote . '

    <tr><td style="padding-bottom:4px;">Thank you.</td></tr>
    <tr><td style="padding-bottom:4px;">Regards,</td></tr>
    <tr><td style="padding-bottom:20px;"><strong>Treasury Department</strong></td></tr>

    <tr><td style="border-top:1px solid #ccc;padding-top:12px;font-size:12px;color:#555;line-height:1.8;">
      &bull; (Attachment of Annexes referring to the Annexes under NHA MC No. 2024-066 dtd. October 08, 2024)<br>
      &bull; (Attachment of duly approved DV)
    </td></tr>

  </table>
  </td></tr>
</table>
</body></html>';
    }

    private function peso(float $n): string
    {
        return '₱' . number_format($n, 2);
    }

    // Shape expected by jQuery DataTables server-side processing.
    private function emptyDataTable($draw)
    {
        return json_encode([
            "draw" => (int)$draw,
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => [],
        ]);
    }

    private function dataTable($draw, array $rows)
    {
        return json_encode([
            "draw" => (int)$draw,
            "recordsTotal" => count($rows),
            "recordsFiltered" => count($rows),
            "data" => $rows,
        ]);
    }

    private function ok($data = [])
    {
        return json_encode(["success" => true, "data" => $data]);
    }

    // ── Check Preparation ──
    public function getBankAccounts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, account_number, bank_name, office_type
             FROM {$this->tableBankAccounts}
             WHERE is_active = TRUE
             ORDER BY account_name",
            'DB'
        );

        return $this->ok($rows);
    }

    public function getFundSources()
    {
        return $this->ok([]);
    }

    // Ported from TREASURY's CheckPreparationService::getChecks(): the
    // endpoint itself has no hardcoded status restriction — it returns
    // every status when $params['status'] is empty (the "All" tab) and
    // filters only when a status is actually supplied. "Default to
    // PREPARED-only" is a frontend concern (the status tabs default to
    // PREPARED), not something this method enforces.
    public function getChecksList($params)
    {
        $where = ['1=1'];
        $checkNumber = trim((string) ($params['check_number'] ?? ''));
        if ($checkNumber !== '') {
            $where[] = 'c.check_number ILIKE ' . $this->str('%' . $checkNumber . '%');
        }
        $dvNumber = trim((string) ($params['dv_number'] ?? ''));
        if ($dvNumber !== '') {
            $where[] = 'c.dv_number ILIKE ' . $this->str('%' . $dvNumber . '%');
        }
        $payee = trim((string) ($params['payee'] ?? ''));
        if ($payee !== '') {
            $where[] = 'c.payee ILIKE ' . $this->str('%' . $payee . '%');
        }
        $status = strtoupper(trim((string) ($params['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'c.status = ' . $this->str($status);
        }
        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        if ($bankAccountId > 0) {
            $where[] = "c.bank_account_id = {$bankAccountId}";
        }
        $dateFrom = trim((string) ($params['date_from'] ?? ''));
        if ($dateFrom !== '') {
            $where[] = 'c.check_date >= ' . $this->str($dateFrom);
        }
        $dateTo = trim((string) ($params['date_to'] ?? ''));
        if ($dateTo !== '') {
            $where[] = 'c.check_date <= ' . $this->str($dateTo);
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT c.check_id, c.check_number, c.check_date, c.dv_number, c.payee, c.amount, c.status, c.printed_at,
                    c.bank_account_id, ba.account_name, ba.account_number, ba.bank_name, ba.bank_branch
             FROM {$this->tableChecks} c
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = c.bank_account_id
             WHERE {$whereSql}
             ORDER BY c.check_date DESC, c.check_id DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // DVs eligible to have a check prepared against them — status FOR_TREASURY
    // (Accounting has processed them and Treasury is holding them) and not
    // already linked to a non-CANCELLED check — same rule TREASURY's
    // getApprovedDVsForPrep() uses ("NOT EXISTS ... status <> 'CANCELLED'"):
    // a cancelled check frees the DV up for re-preparation, a check in any
    // other status keeps it excluded.
    public function getDvsForPrep($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT dv.dv_id, dv.dv_no, dv.dv_date, dv.claimant_name, dv.net_amount
             FROM {$this->tableDv} dv
             JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
             WHERE st.status_for = 'disbursement_voucher' AND st.name = 'FOR_TREASURY'
               AND NOT EXISTS (SELECT 1 FROM {$this->tableChecks} c WHERE c.dv_id = dv.dv_id AND c.status <> 'CANCELLED')
             ORDER BY dv.dv_date",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // Not-yet-used check numbers for a bank account, sourced from the
    // standalone Check Register (fims.check_register — see this class's
    // docblock for why it's a separate table from fims.checks). Backs the
    // Prepare Check modal's Check Number dropdown, which only enables once
    // a Bank Account is picked.
    public function getUnusedChecks($bankAccountId)
    {
        $bankAccountId = (int) $bankAccountId;
        if ($bankAccountId <= 0) {
            return $this->ok([]);
        }

        $rows = App\ExecuteRowsAssociative(
            "SELECT check_number FROM {$this->tableCheckRegister}
             WHERE bank_account_id = {$bankAccountId} AND current_status = 'UNUSED'
             ORDER BY check_number
             LIMIT " . self::MAX_UNUSED_CHECKS_PER_BOOKLET,
            'DB'
        );

        return $this->ok(array_map(fn ($r) => $r['check_number'], $rows));
    }

    // Ported from TREASURY's CheckPreparationService::getCashAvailability():
    // one row per ACTIVE bank account (or just the one requested), LEFT
    // JOIN LATERAL to its single most-recent fims.cash_position row so an
    // account with no history yet still returns a row with
    // available_balance = 0 rather than being omitted. Purely informational
    // — backs the Prepare Check modal's cash-check alert; it is not what
    // blocks saveCheck() (see that method's own, separately-queried soft
    // check).
    public function getCashAvailability($bankAccountId)
    {
        $bankAccountId = (int) $bankAccountId;
        $andFilter = $bankAccountId > 0 ? "AND ba.bank_account_id = {$bankAccountId}" : '';

        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.bank_account_id, ba.account_number, ba.bank_name,
                    COALESCE(cp.available_balance, 0) AS available_balance,
                    COALESCE(cp.position_date::text, '') AS position_date
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN LATERAL (
                 SELECT available_balance, position_date
                 FROM fims.cash_position cp2
                 WHERE cp2.bank_account_id = ba.bank_account_id
                 ORDER BY cp2.position_date DESC
                 LIMIT 1
             ) cp ON TRUE
             WHERE ba.status = 'ACTIVE' {$andFilter}
             ORDER BY ba.bank_name",
            'DB'
        );

        return $this->ok($rows);
    }

    public function getCheck($id)
    {
        $row = App\ExecuteRow(
            "SELECT c.*, ba.account_name, ba.account_number, ba.bank_name, ba.bank_branch
             FROM {$this->tableChecks} c
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = c.bank_account_id
             WHERE c.check_id = " . (int) $id,
            'DB'
        );
        if (!$row) {
            return json_encode(["success" => false, "message" => "Check not found."]);
        }

        return $this->ok($row);
    }

    // ── Print Agent — see this class's docblock for the feature's
    // architecture. Ported from TREASURY's CheckPreparationService.
    public function markPrinted($id)
    {
        $id = (int) $id;
        if (!App\ExecuteRow("SELECT check_id FROM {$this->tableChecks} WHERE check_id = {$id}", 'DB')) {
            return json_encode(["success" => false, "message" => "Check not found."]);
        }
        App\Execute("UPDATE {$this->tableChecks} SET printed_at = CURRENT_TIMESTAMP WHERE check_id = {$id}", 'DB');

        return $this->ok(['check_id' => $id]);
    }

    // Zips up the local print agent (a tiny local PHP HTTP server — see
    // /print-agent, same self-contained PHP+PowerShell approach as
    // TREASURY's own, since browsers can't talk to a printer or install
    // local software directly) plus a generated one-click install.bat.
    public function generatePrintAgentPackage(): string
    {
        $srcDir = __DIR__ . '/../../print-agent';
        $tmpZip = tempnam(sys_get_temp_dir(), 'fims_pa_') . '.zip';

        $zip = new \ZipArchive();
        $zip->open($tmpZip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        foreach (['print-agent.php', 'print-image.ps1', 'start-print-agent.bat', 'start-print-agent-hidden.vbs', 'install-autostart.ps1'] as $f) {
            $path = $srcDir . '/' . $f;
            if (is_file($path)) {
                $zip->addFile($path, $f);
            }
        }
        $zip->addFromString('install.bat', $this->buildPrintAgentInstallBat());
        $zip->close();

        $bytes = file_get_contents($tmpZip);
        unlink($tmpZip);

        return $bytes;
    }

    private function buildPrintAgentInstallBat(): string
    {
        return <<<'BAT'
@echo off
setlocal
set "TARGET=%LOCALAPPDATA%\FIMS-CheckPrintAgent"
echo ==================================================
echo  FIMS Check Print Agent - One-time setup
echo ==================================================
echo.

where php >nul 2>nul
if errorlevel 1 (
    echo PHP was not found on this computer ^(checked PATH^).
    echo The print agent needs a local PHP install to run.
    echo Install PHP, make sure "php" works from a new Command Prompt, then run this file again.
    echo.
    pause
    exit /b 1
)

echo Installing to: %TARGET%
if not exist "%TARGET%" mkdir "%TARGET%"
copy /y "%~dp0print-agent.php" "%TARGET%\" >nul
copy /y "%~dp0print-image.ps1" "%TARGET%\" >nul
copy /y "%~dp0start-print-agent.bat" "%TARGET%\" >nul
copy /y "%~dp0start-print-agent-hidden.vbs" "%TARGET%\" >nul
copy /y "%~dp0install-autostart.ps1" "%TARGET%\" >nul

echo Registering auto-start at login...
powershell -NoProfile -ExecutionPolicy Bypass -File "%TARGET%\install-autostart.ps1"

echo Starting the print agent now...
start "" wscript.exe "%TARGET%\start-print-agent-hidden.vbs"

echo.
echo Done. Go back to FIMS and try printing again in a few seconds.
timeout /t 5 >nul
exit /b 0
BAT;
    }

    public function getCheckHistory($id)
    {
        return $this->ok([]);
    }

    /**
     * Prepares a check against a FOR_TREASURY disbursement voucher. Inserts
     * fims.checks (snapshotting the DV's dv_no/claimant_name/net_amount as
     * dv_number/payee/amount, same convention as
     * disbursement_vouchers.rfa_allocation_number snapshotting
     * fims.budget_allocation), then hands the DV to
     * DisbursementVoucherService::changeStatus() to move it FOR_TREASURY ->
     * RELEASED — that method enforces the real authorization rule (only
     * Treasury RC staff or an admin may do this), so a user without that
     * role gets a clear rejection and the check insert is rolled back.
     *
     * fims.checks and fims.check_register are independent ledgers (see this
     * class's docblock — same separation TREASURY keeps between treasury.
     * checks and treasury.check_register). If the chosen check_number has a
     * matching UNUSED row in the register, it's loosely updated (current_
     * status -> ISSUED, entry_type -> ISSUED, check_id -> this new check) as
     * a best-effort sync, same as TREASURY's unenforced check_register.
     * check_id pointer — but preparing a check never requires one to exist.
     */
    public function saveCheck($data)
    {
        $dvId          = (int) ($data['dv_id'] ?? 0);
        $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
        $checkNumber   = trim((string) ($data['check_number'] ?? ''));
        $checkDate     = trim((string) ($data['check_date'] ?? ''));

        if ($dvId <= 0) {
            return json_encode(["success" => false, "message" => "Select a DV to prepare a check against."]);
        }
        if ($bankAccountId <= 0) {
            return json_encode(["success" => false, "message" => "Select a bank account."]);
        }
        if ($checkNumber === '') {
            return json_encode(["success" => false, "message" => "Check number is required."]);
        }
        if ($checkDate === '') {
            return json_encode(["success" => false, "message" => "Check date is required."]);
        }

        $dv = App\ExecuteRow(
            "SELECT dv.dv_id, dv.dv_no, dv.claimant_name, dv.net_amount, st.name AS status
             FROM {$this->tableDv} dv
             JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
             WHERE dv.dv_id = {$dvId}",
            'DB'
        );
        if (!$dv) {
            return json_encode(["success" => false, "message" => "Disbursement Voucher #{$dvId} not found."]);
        }
        if ($dv['status'] !== 'FOR_TREASURY') {
            return json_encode(["success" => false, "message" => "DV {$dv['dv_no']} is {$dv['status']}, not FOR_TREASURY — it isn't ready for check preparation."]);
        }
        if ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableChecks} WHERE bank_account_id = {$bankAccountId} AND check_number = " . $this->str($checkNumber), 'DB') > 0) {
            return json_encode(["success" => false, "message" => "Check number {$checkNumber} already exists for this bank account."]);
        }

        // Cash sufficiency — unlike TREASURY's own soft check (which skips
        // validation entirely when no cash_position row exists), this
        // project blocks instead: no record at all means zero available
        // cash, not "unknown/unenforced" — a bank account must have a real
        // cash position before any check can be prepared against it.
        $required = (float) $dv['net_amount'];
        $cashRow = App\ExecuteRow(
            "SELECT COALESCE(available_balance, 0) AS available_balance
             FROM {$this->tableCashPosition}
             WHERE bank_account_id = {$bankAccountId}
             ORDER BY position_date DESC LIMIT 1",
            'DB'
        );
        $available = $cashRow ? (float) $cashRow['available_balance'] : 0.0;
        if ($available < $required) {
            $message = $cashRow
                ? sprintf('Insufficient cash: Available ₱%s, Required ₱%s', number_format($available, 2), number_format($required, 2))
                : 'No cash position on record for this bank account — register one before preparing checks against it.';

            return json_encode(["success" => false, "message" => $message]);
        }

        $userId = (int) App\CurrentUserID();

        App\Execute('BEGIN', 'DB');
        try {
            $check = App\ExecuteRow(
                "INSERT INTO {$this->tableChecks}
                    (bank_account_id, check_number, check_date, dv_id, dv_number, payee, amount, status,
                     signatory1_name, signatory1_designation, signatory2_name, signatory2_designation,
                     prepared_by, prepared_at)
                 VALUES (
                    {$bankAccountId}, " . $this->str($checkNumber) . ', ' . $this->str($checkDate) . ", {$dvId}, "
                    . $this->str($dv['dv_no']) . ', ' . $this->str($dv['claimant_name']) . ', ' . (float) $dv['net_amount'] . ", 'PREPARED', "
                    . $this->strOrNull($data['signatory1_name'] ?? null) . ', ' . $this->strOrNull($data['signatory1_designation'] ?? null) . ', '
                    . $this->strOrNull($data['signatory2_name'] ?? null) . ', ' . $this->strOrNull($data['signatory2_designation'] ?? null) . ', '
                    . "{$userId}, CURRENT_TIMESTAMP)
                 RETURNING check_id, check_number",
                'DB'
            );

            $dvService = new DisbursementVoucherService();
            $released  = $dvService->changeStatus($dvId, 'RELEASED', $userId);
            if (!$released['success']) {
                App\Execute('ROLLBACK', 'DB');

                return json_encode(["success" => false, "message" => $released['message']]);
            }

            App\Execute(
                "UPDATE {$this->tableCheckRegister}
                 SET current_status = 'ISSUED', entry_type = 'ISSUED', check_id = {$check['check_id']}, updated_at = CURRENT_TIMESTAMP
                 WHERE bank_account_id = {$bankAccountId} AND check_number = " . $this->str($checkNumber) . " AND current_status = 'UNUSED'",
                'DB'
            );

            // Deduct the check amount — always into today's cash_position
            // row, creating one (rolled forward from the prior day's
            // closing_balance, or 0 if there's no history at all) if one
            // doesn't exist yet. available_balance is a generated column
            // (closing_balance - restricted_amount), so only the base
            // columns feeding it are written — same as TREASURY's saveCheck().
            $todayPos = App\ExecuteRow(
                "SELECT cash_position_id FROM {$this->tableCashPosition}
                 WHERE bank_account_id = {$bankAccountId} AND position_date = CURRENT_DATE
                 LIMIT 1",
                'DB'
            );
            if ($todayPos) {
                App\Execute(
                    "UPDATE {$this->tableCashPosition}
                     SET total_disbursements = COALESCE(total_disbursements, 0) + {$required},
                         closing_balance = COALESCE(closing_balance, 0) - {$required}
                     WHERE cash_position_id = {$todayPos['cash_position_id']}",
                    'DB'
                );
            } else {
                $prevPos = App\ExecuteRow(
                    "SELECT closing_balance FROM {$this->tableCashPosition}
                     WHERE bank_account_id = {$bankAccountId} AND position_date < CURRENT_DATE
                     ORDER BY position_date DESC LIMIT 1",
                    'DB'
                );
                $opening = $prevPos ? (float) $prevPos['closing_balance'] : 0.0;
                $closing = $opening - $required;
                App\Execute(
                    "INSERT INTO {$this->tableCashPosition}
                        (bank_account_id, position_date, opening_balance, total_receipts, total_disbursements, closing_balance, restricted_amount, reconciled, created_by)
                     VALUES ({$bankAccountId}, CURRENT_DATE, {$opening}, 0, {$required}, {$closing}, 0, FALSE, {$userId})",
                    'DB'
                );
            }

            App\Execute('COMMIT', 'DB');

            return $this->ok(['check_id' => $check['check_id'], 'check_number' => $check['check_number']]);
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');
            App\LogError('CheckProcessingService::saveCheck ' . $e->getMessage());

            return json_encode(["success" => false, "message" => "Could not save check — a check number must be unique per bank account."]);
        }
    }

    public function getSubsidyReport($params)
    {
        return $this->ok([]);
    }

    // ── Check Registry (blank check stock) ──
    // Faithful port of the TREASURY reference project's
    // CheckRegistryService.php, operating on the standalone fims.
    // check_register table (see this class's docblock). Column names here
    // intentionally match TREASURY's exactly (register_entry_id,
    // current_status, booklet_no, department_type, etc.) rather than this
    // project's own fims.checks naming, since this *is* that reference
    // table ported over, not an adaptation of fims.checks.

    private function checkRegisterWhere($params): string
    {
        $where = ['1=1'];

        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        if ($bankAccountId > 0) {
            $where[] = "cr.bank_account_id = {$bankAccountId}";
        }
        $status = strtoupper(trim((string) ($params['status'] ?? '')));
        if ($status !== '') {
            $where[] = 'cr.current_status = ' . $this->str($status);
        }
        $entryType = strtoupper(trim((string) ($params['entry_type'] ?? '')));
        if ($entryType !== '') {
            $where[] = 'cr.entry_type = ' . $this->str($entryType);
        }
        $checkSeries = trim((string) ($params['check_series'] ?? ''));
        if ($checkSeries !== '') {
            $where[] = 'cr.check_series = ' . $this->str($checkSeries);
        }
        $dateFrom = trim((string) ($params['date_from'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $where[] = 'cr.entry_date >= ' . $this->str($dateFrom);
        }
        $dateTo = trim((string) ($params['date_to'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $where[] = 'cr.entry_date <= ' . $this->str($dateTo);
        }
        // "office_type" is the filter's param name (matching TREASURY's UI
        // label), but it maps to the department_type column — TREASURY has
        // no separate office_type column on check_register either.
        $officeType = strtoupper(trim((string) ($params['office_type'] ?? '')));
        if ($officeType !== '') {
            $where[] = 'cr.department_type = ' . $this->str($officeType);
        }
        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where[] = "(cr.check_number ILIKE {$needle} OR cr.check_series ILIKE {$needle} OR cr.booklet_no ILIKE {$needle})";
        }

        return implode(' AND ', $where);
    }

    public function getSeriesList()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT check_series, COUNT(*) AS total_checks
             FROM {$this->tableCheckRegister}
             WHERE check_series IS NOT NULL AND check_series <> ''
             GROUP BY check_series
             ORDER BY check_series",
            'DB'
        );

        return $this->ok($rows);
    }

    public function getRegistryList($params)
    {
        $whereSql = $this->checkRegisterWhere($params);

        $rows = App\ExecuteRowsAssociative(
            "SELECT cr.register_entry_id, cr.bank_account_id, cr.check_number, cr.check_series, cr.check_id,
                    cr.entry_date, cr.entry_type, cr.payee_name, cr.amount, cr.voucher_reference,
                    cr.current_status, cr.booklet_no, cr.department_type,
                    cr.cancellation_reason, cr.replacement_check_number,
                    cr.recorded_by, cr.recorded_at, cr.created_at, cr.updated_at,
                    ba.account_name, ba.account_number, ba.bank_name, ba.bank_branch
             FROM {$this->tableCheckRegister} cr
             LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = cr.bank_account_id
             WHERE {$whereSql}
             ORDER BY cr.entry_date DESC, cr.check_number ASC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    public function getRegistryEntry($id)
    {
        $row = App\ExecuteRow(
            "SELECT cr.*, ba.account_name, ba.account_number, ba.bank_name, ba.bank_branch
             FROM {$this->tableCheckRegister} cr
             LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = cr.bank_account_id
             WHERE cr.register_entry_id = " . (int) $id,
            'DB'
        );
        if (!$row) {
            return json_encode(["success" => false, "message" => "Register entry not found."]);
        }

        return $this->ok($row);
    }

    // Booklets grouped from the register itself (TREASURY has no separate
    // booklets table either — booklet_no is just a string column here).
    // `available` mirrors TREASURY's PHP-side rule: a booklet number can
    // only be reused once every check filed under it has moved off UNUSED.
    public function getBooklets($bankAccountId)
    {
        $bankAccountId = (int) $bankAccountId;
        if ($bankAccountId <= 0) {
            return $this->ok([]);
        }

        $rows = App\ExecuteRowsAssociative(
            "SELECT booklet_no, MAX(check_series) AS check_series, COUNT(*) AS total_checks,
                    COUNT(*) FILTER (WHERE current_status = 'UNUSED') AS unused_count
             FROM {$this->tableCheckRegister}
             WHERE bank_account_id = {$bankAccountId} AND booklet_no IS NOT NULL AND booklet_no <> ''
             GROUP BY booklet_no
             ORDER BY booklet_no",
            'DB'
        );
        foreach ($rows as &$row) {
            $row['unused_count'] = (int) $row['unused_count'];
            $row['total_checks'] = (int) $row['total_checks'];
            $row['available']    = $row['unused_count'] === 0;
        }

        return $this->ok($rows);
    }

    public function getRegistrySummary()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT current_status AS status, COUNT(*)::int AS total_count FROM {$this->tableCheckRegister} GROUP BY current_status",
            'DB'
        );
        $summary = [];
        foreach (self::REGISTER_STATUSES as $status) {
            $summary[$status] = 0;
        }
        foreach ($rows as $row) {
            $summary[$row['status']] = (int) $row['total_count'];
        }

        return $this->ok($summary);
    }

    // Registers a batch of received blank check stock — one bulk INSERT,
    // one row per check number, exactly mirroring TREASURY's addBatch():
    // $data['checks'] is an array of {check_number, entry_date, booklet_no}
    // (range-vs-individual expansion and check-number zero-padding/booklet
    // naming are both client-side concerns, same as TREASURY — see
    // check-disbursement-voucher-page.php's registry JS).
    public function addRegisterBatch($data)
    {
        $bankAccountId = (int) ($data['bank_account_id'] ?? 0);
        $checkSeries   = trim((string) ($data['check_series'] ?? ''));
        $deptType      = strtoupper(trim((string) ($data['department_type'] ?? '')));
        if (!in_array($deptType, self::REGISTER_DEPARTMENT_TYPES, true)) {
            $deptType = 'MAIN_OFFICE';
        }
        $checks = is_array($data['checks'] ?? null) ? $data['checks'] : [];

        if ($bankAccountId <= 0) {
            return json_encode(["success" => false, "message" => "Select a bank account."]);
        }
        if (empty($checks)) {
            return json_encode(["success" => false, "message" => "No checks to register."]);
        }
        if (count($checks) > self::MAX_REGISTER_BATCH_SIZE) {
            return json_encode(["success" => false, "message" => "A batch of " . count($checks) . " checks exceeds the " . self::MAX_REGISTER_BATCH_SIZE . "-check limit — split it into smaller batches."]);
        }
        $bank = App\ExecuteRow("SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId} AND is_active = TRUE", 'DB');
        if (!$bank) {
            return json_encode(["success" => false, "message" => "Bank account not found or inactive."]);
        }

        // Phase 1: pure validation, no DB — required fields + within-batch dupes.
        $seen = [];
        $validated = [];
        foreach ($checks as $i => $row) {
            $checkNumber = trim((string) ($row['check_number'] ?? ''));
            $entryDate   = trim((string) ($row['entry_date'] ?? ''));
            $bookletNo   = trim((string) ($row['booklet_no'] ?? ''));
            if ($checkNumber === '') {
                return json_encode(["success" => false, "message" => "Row " . ($i + 1) . ": check number is required."]);
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $entryDate)) {
                return json_encode(["success" => false, "message" => "Row " . ($i + 1) . ": a valid entry date is required."]);
            }
            $dupeKey = $checkNumber . '|' . $bookletNo;
            if (isset($seen[$dupeKey])) {
                return json_encode(["success" => false, "message" => "Check number {$checkNumber} is duplicated within this batch."]);
            }
            $seen[$dupeKey] = true;
            $validated[] = ['check_number' => $checkNumber, 'entry_date' => $entryDate, 'booklet_no' => $bookletNo];
        }

        // Phase 1.5: booklet reuse — a booklet number already on file for
        // this bank account may only be reused once every check filed
        // under it has moved off UNUSED.
        $bookletNos = array_values(array_unique(array_filter(array_map(fn ($v) => $v['booklet_no'], $validated), fn ($b) => $b !== '')));
        if (!empty($bookletNos)) {
            $inList = implode(', ', array_map(fn ($b) => $this->str($b), $bookletNos));
            $blocked = App\ExecuteRowsAssociative(
                "SELECT booklet_no, COUNT(*) FILTER (WHERE current_status = 'UNUSED') AS unused_count
                 FROM {$this->tableCheckRegister}
                 WHERE bank_account_id = {$bankAccountId} AND booklet_no IN ({$inList})
                 GROUP BY booklet_no
                 HAVING COUNT(*) FILTER (WHERE current_status = 'UNUSED') > 0",
                'DB'
            );
            if (!empty($blocked)) {
                $names = implode(', ', array_map(fn ($b) => "{$b['booklet_no']} ({$b['unused_count']} unused)", $blocked));

                return json_encode(["success" => false, "message" => "These booklets still have unused checks and can't be reused: {$names}."]);
            }
        }

        // Phase 2: bulk duplicate check — a (check_number, booklet_no) pair
        // that already exists for this bank account is rejected; the same
        // check_number under a *different* booklet_no is allowed to coexist.
        $numberList = implode(', ', array_map(fn ($v) => $this->str($v['check_number']), $validated));
        $existing = App\ExecuteRowsAssociative(
            "SELECT check_number, COALESCE(booklet_no, '') AS booklet_no
             FROM {$this->tableCheckRegister}
             WHERE bank_account_id = {$bankAccountId} AND check_number IN ({$numberList})",
            'DB'
        );
        $existingSet = [];
        foreach ($existing as $row) {
            $existingSet[$row['check_number'] . '|' . $row['booklet_no']] = true;
        }
        foreach ($validated as $v) {
            if (isset($existingSet[$v['check_number'] . '|' . $v['booklet_no']])) {
                return json_encode(["success" => false, "message" => "Check number {$v['check_number']} is already registered under this booklet."]);
            }
        }

        // Phase 3: single bulk INSERT, one tuple per check.
        $userId = (int) App\CurrentUserID();
        $valueRows = [];
        foreach ($validated as $v) {
            $valueRows[] = "({$bankAccountId}, " . $this->str($v['check_number']) . ', ' . $this->strOrNull($checkSeries) . ', '
                . $this->str($v['entry_date']) . ", 'RECEIVED', 'UNUSED', " . $this->strOrNull($v['booklet_no']) . ', '
                . $this->str($deptType) . ", {$userId}, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
        }
        App\Execute(
            "INSERT INTO {$this->tableCheckRegister}
                (bank_account_id, check_number, check_series, entry_date, entry_type, current_status,
                 booklet_no, department_type, recorded_by, recorded_at, created_at, updated_at)
             VALUES " . implode(', ', $valueRows),
            'DB'
        );

        return $this->ok(['inserted' => count($validated)]);
    }

    // Always an UPDATE-in-place on the existing register_entry_id — a
    // status change is never a new row (same as TREASURY's updateStatus()).
    public function updateRegisterStatus($data)
    {
        $entryId = (int) ($data['register_entry_id'] ?? 0);
        $status  = strtoupper(trim((string) ($data['status'] ?? '')));
        $date    = trim((string) ($data['date'] ?? ''));

        if ($entryId <= 0) {
            return json_encode(["success" => false, "message" => "Register entry is required."]);
        }
        if (!in_array($status, self::REGISTER_STATUSES, true)) {
            return json_encode(["success" => false, "message" => "Invalid status."]);
        }
        if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return json_encode(["success" => false, "message" => "Invalid date."]);
        }
        if (!App\ExecuteRow("SELECT register_entry_id FROM {$this->tableCheckRegister} WHERE register_entry_id = {$entryId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Register entry not found."]);
        }

        $reason      = trim((string) ($data['reason'] ?? ''));
        $replacement = trim((string) ($data['replacement_check_number'] ?? ''));

        $set = ['current_status = ' . $this->str($status), 'updated_at = CURRENT_TIMESTAMP'];
        switch ($status) {
            case 'ISSUED':
                $set[] = "entry_type = 'ISSUED'";
                break;
            case 'CANCELLED':
                $set[] = "entry_type = 'CANCELLED'";
                $set[] = 'cancellation_reason = ' . $this->strOrNull($reason);
                $set[] = 'replacement_check_number = ' . $this->strOrNull($replacement);
                break;
            case 'VOIDED':
                $set[] = "entry_type = 'VOIDED'";
                $set[] = 'cancellation_reason = ' . $this->strOrNull($reason);
                break;
            case 'SPOILED':
                $set[] = "entry_type = 'SPOILED'";
                $set[] = 'cancellation_reason = ' . $this->strOrNull($reason);
                break;
            case 'STALE':
                $set[] = "entry_type = 'STALE'";
                $set[] = 'cancellation_reason = ' . $this->strOrNull($reason !== '' ? $reason : 'Stale check');
                break;
            // ENCASHED / OUTSTANDING leave entry_type untouched — TREASURY's
            // updateStatus() has no case for either (no dedicated date
            // column exists on this table for them either).
        }

        App\Execute(
            "UPDATE {$this->tableCheckRegister} SET " . implode(', ', $set) . " WHERE register_entry_id = {$entryId}",
            'DB'
        );

        return $this->ok(['register_entry_id' => $entryId, 'status' => $status]);
    }

    // ── Check Released to Claimants ──
    public function getReleasesList($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT r.release_id, c.check_number, c.payee, c.amount,
                    r.claimant_name, r.claimant_email, r.id_type, r.id_number, r.release_type,
                    r.release_date, r.status, r.acknowledgment_date
             FROM {$this->tableClaimantReleases} r
             JOIN {$this->tableChecks} c ON c.check_id = r.check_id
             ORDER BY r.created_at DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // Structured (non-HTML) list of a department's currently-PENDING region
    // releases — backs the Send Monthly Notice modal's preview table. Thin
    // public wrapper so the frontend doesn't have to scrape
    // previewRegionReleaseNotice()'s HTML string for this.
    public function getPendingRegionReleases($departmentId)
    {
        return $this->ok($this->fetchPendingRegionReleases((int) $departmentId));
    }

    public function getUnclaimedReleases($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT r.release_id, c.check_number, c.payee, c.amount, r.claimant_name, r.status,
                    r.last_follow_up_date, r.follow_up_remarks,
                    EXTRACT(DAY FROM CURRENT_TIMESTAMP - c.prepared_at)::int AS days_since_prep
             FROM {$this->tableClaimantReleases} r
             JOIN {$this->tableChecks} c ON c.check_id = r.check_id
             WHERE r.status = 'UNCLAIMED'
             ORDER BY c.prepared_at",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    public function getEmailLog($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT l.log_id, c.check_number, l.recipient_email, l.sent_at, l.sent_by, l.status, l.error_message
             FROM {$this->tableClaimantEmailLog} l
             JOIN {$this->tableChecks} c ON c.check_id = l.check_id
             ORDER BY l.sent_at DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    public function getRelease($id)
    {
        $release = $this->fetchClaimantReleaseWithCheck((int) $id);
        if (!$release) {
            return json_encode(["success" => false, "message" => "Release not found."]);
        }

        return $this->ok($release);
    }

    private function fetchClaimantReleaseWithCheck(int $releaseId): ?array
    {
        return App\ExecuteRow(
            "SELECT r.*, c.check_number, c.check_date, c.dv_number, c.amount, c.payee AS check_payee,
                    ba.bank_name, dv.particulars
             FROM {$this->tableClaimantReleases} r
             JOIN {$this->tableChecks} c ON c.check_id = r.check_id
             LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = c.bank_account_id
             LEFT JOIN {$this->tableDv} dv ON dv.dv_no = c.dv_number
             WHERE r.release_id = {$releaseId}",
            'DB'
        ) ?: null;
    }

    // Records a release — DIRECT hand-off is immediately RELEASED; EMAIL_NOTICE
    // starts PENDING until sendReleaseNotice() actually sends and flips it to
    // NOTIFIED. $data['check_number'] is the Record Check Release modal's
    // check <select> value, which is populated with check_id (not the check's
    // printed number) — same naming the frontend already used before this was
    // wired, kept as-is rather than renaming the payload key.
    public function saveRelease($data)
    {
        $checkId     = (int) ($data['check_number'] ?? 0);
        $releaseType = strtoupper(trim((string) ($data['release_type'] ?? 'DIRECT')));
        if ($checkId <= 0) {
            return json_encode(["success" => false, "message" => "Select a check."]);
        }
        if (!in_array($releaseType, ['DIRECT', 'EMAIL_NOTICE'], true)) {
            return json_encode(["success" => false, "message" => "Invalid release type."]);
        }
        $claimantEmail = trim((string) ($data['claimant_email'] ?? ''));
        if ($releaseType === 'EMAIL_NOTICE' && $claimantEmail === '') {
            return json_encode(["success" => false, "message" => "Recipient email is required for an Email Notice release."]);
        }
        if (!App\ExecuteRow("SELECT check_id FROM {$this->tableChecks} WHERE check_id = {$checkId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Check not found."]);
        }

        $userId = (int) App\CurrentUserID();
        $status = $releaseType === 'DIRECT' ? 'RELEASED' : 'PENDING';

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableClaimantReleases}
                (check_id, release_type, claimant_type, claimant_name, claimant_email, status, release_date, remarks, created_by)
             VALUES (
                {$checkId}, " . $this->str($releaseType) . ', ' . $this->strOrNull($data['claimant_type'] ?? null) . ', '
                . $this->strOrNull($data['claimant_name'] ?? null) . ', ' . $this->strOrNull($claimantEmail) . ', '
                . $this->str($status) . ', ' . $this->dateOrNull($data['release_date'] ?? null) . ', '
                . $this->strOrNull($data['remarks'] ?? null) . ", {$userId})
             RETURNING release_id",
            'DB'
        );

        return $this->ok(['release_id' => $row['release_id']]);
    }

    public function previewReleaseNotice($id, $params)
    {
        $release = $this->fetchClaimantReleaseWithCheck((int) $id);
        if (!$release) {
            return $this->ok(['html' => '<p>Release not found.</p>']);
        }

        return $this->ok(['html' => $this->buildClaimantEmailBody($release, (string) ($params['message'] ?? ''))]);
    }

    // Sends (and logs) a real email via SendEmail() — see this class's
    // docblock and sendModuleEmail(). Flips the release to NOTIFIED on
    // success so it drops off the "pending notice" list the Send Email
    // Notice modal reads from.
    public function sendReleaseNotice($id, $data)
    {
        $releaseId = (int) $id;
        $release   = $this->fetchClaimantReleaseWithCheck($releaseId);
        if (!$release) {
            return json_encode(["success" => false, "message" => "Release not found."]);
        }
        $recipient = trim((string) ($data['recipient_email'] ?? $release['claimant_email'] ?? ''));
        if ($recipient === '') {
            return json_encode(["success" => false, "message" => "Recipient email is required."]);
        }
        $message = trim((string) ($data['message'] ?? ''));
        $subject = "Check Release Notice \u{2014} Check #{$release['check_number']}";
        $body    = $this->buildClaimantEmailBody($release, $message);
        $result  = $this->sendModuleEmail($recipient, $subject, $body);
        $userId  = (int) App\CurrentUserID();
        $checkId = (int) $release['check_id'];

        if ($result['success']) {
            App\Execute(
                "INSERT INTO {$this->tableClaimantEmailLog} (release_id, check_id, recipient_email, status, sent_by)
                 VALUES ({$releaseId}, {$checkId}, " . $this->str($recipient) . ", 'SENT', {$userId})",
                'DB'
            );
            App\Execute(
                "UPDATE {$this->tableClaimantReleases} SET status = 'NOTIFIED', updated_at = CURRENT_TIMESTAMP WHERE release_id = {$releaseId}",
                'DB'
            );

            return json_encode(["success" => true, "message" => "Email notice sent to {$recipient}."]);
        }

        App\Execute(
            "INSERT INTO {$this->tableClaimantEmailLog} (release_id, check_id, recipient_email, status, error_message, sent_by)
             VALUES ({$releaseId}, {$checkId}, " . $this->str($recipient) . ", 'FAILED', " . $this->str($result['message']) . ", {$userId})",
            'DB'
        );

        return json_encode(["success" => false, "message" => "Could not send email: {$result['message']}"]);
    }

    public function saveReleaseFollowUp($id, $data)
    {
        $releaseId = (int) $id;
        if (!App\ExecuteRow("SELECT release_id FROM {$this->tableClaimantReleases} WHERE release_id = {$releaseId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Release not found."]);
        }
        App\Execute(
            "UPDATE {$this->tableClaimantReleases}
             SET last_follow_up_date = CURRENT_DATE, follow_up_remarks = " . $this->strOrNull($data['remarks'] ?? null) . ", updated_at = CURRENT_TIMESTAMP
             WHERE release_id = {$releaseId}",
            'DB'
        );

        return $this->ok(['release_id' => $releaseId]);
    }

    public function acknowledgeRelease($id, $data)
    {
        $releaseId = (int) $id;
        if (!App\ExecuteRow("SELECT release_id FROM {$this->tableClaimantReleases} WHERE release_id = {$releaseId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Release not found."]);
        }
        App\Execute(
            "UPDATE {$this->tableClaimantReleases}
             SET status = 'ACKNOWLEDGED', acknowledgment_date = CURRENT_DATE, updated_at = CURRENT_TIMESTAMP
             WHERE release_id = {$releaseId}",
            'DB'
        );

        return $this->ok(['release_id' => $releaseId]);
    }

    public function updateReleaseStatus($id, $data)
    {
        $releaseId = (int) $id;
        $status    = strtoupper(trim((string) ($data['status'] ?? '')));
        if (!in_array($status, ['PENDING', 'NOTIFIED', 'RELEASED', 'ACKNOWLEDGED', 'UNCLAIMED'], true)) {
            return json_encode(["success" => false, "message" => "Invalid status."]);
        }
        if (!App\ExecuteRow("SELECT release_id FROM {$this->tableClaimantReleases} WHERE release_id = {$releaseId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Release not found."]);
        }
        App\Execute(
            "UPDATE {$this->tableClaimantReleases} SET status = " . $this->str($status) . ", updated_at = CURRENT_TIMESTAMP WHERE release_id = {$releaseId}",
            'DB'
        );

        return $this->ok(['release_id' => $releaseId]);
    }

    // Populates the check <select> on both the Record Check Release and New
    // Region Release modals.
    public function lookupChecks($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT check_id, check_number, payee, amount FROM {$this->tableChecks} ORDER BY check_date DESC",
            'DB'
        );

        return $this->ok($rows);
    }

    public function getCheckDepartmentEmails($checkId)
    {
        return $this->ok([]);
    }

    // Populates the Region/Department <select> on both the New Region
    // Release modal and the Send Monthly Notice modal.
    public function getDepartments()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT department_id, department_name, department_acronym
             FROM {$this->tableDepartments}
             WHERE is_active = TRUE
             ORDER BY department_name",
            'DB'
        );

        return $this->ok($rows);
    }

    // ── Check Released to Regions ──
    public function getRegionReleasesList($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rr.region_release_id, c.check_number, c.check_date, c.payee, c.amount,
                    rr.region_dept, rr.claimant_name, rr.release_date, rr.status,
                    (CURRENT_DATE - rr.release_date) AS days_outstanding
             FROM {$this->tableRegionReleases} rr
             JOIN {$this->tableChecks} c ON c.check_id = rr.check_id
             ORDER BY rr.created_at DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    public function getRegionAcknowledgmentList($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rr.region_release_id, c.check_number, c.payee, c.amount, rr.region_dept, rr.claimant_name,
                    rr.release_date, rr.acknowledgment_date, rr.id_submitted
             FROM {$this->tableRegionReleases} rr
             JOIN {$this->tableChecks} c ON c.check_id = rr.check_id
             WHERE rr.status = 'ACKNOWLEDGED'
             ORDER BY rr.acknowledgment_date DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    public function getRegionEmailLog($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT log_id, region_dept, recipient_email, checks_count, total_amount, status, sent_by, sent_at, error_message
             FROM {$this->tableRegionEmailLog}
             ORDER BY sent_at DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    public function getRegionDvMonitoring($params)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rr.region_release_id, c.check_number, rr.dv_reference, c.payee, c.amount, rr.region_dept,
                    rr.release_date, rr.dv_return_date,
                    (CURRENT_DATE - rr.release_date) AS days_pending, rr.status
             FROM {$this->tableRegionReleases} rr
             JOIN {$this->tableChecks} c ON c.check_id = rr.check_id
             WHERE rr.dv_returned = TRUE
             ORDER BY rr.dv_return_date DESC",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    public function getRegionRelease($id)
    {
        $row = App\ExecuteRow(
            "SELECT rr.*, c.check_number, c.check_date, c.payee, c.amount
             FROM {$this->tableRegionReleases} rr
             JOIN {$this->tableChecks} c ON c.check_id = rr.check_id
             WHERE rr.region_release_id = " . (int) $id,
            'DB'
        );
        if (!$row) {
            return json_encode(["success" => false, "message" => "Region release not found."]);
        }

        return $this->ok($row);
    }

    public function markRegionReleaseStale($id, $data)
    {
        $regionReleaseId = (int) $id;
        if (!App\ExecuteRow("SELECT region_release_id FROM {$this->tableRegionReleases} WHERE region_release_id = {$regionReleaseId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Region release not found."]);
        }
        App\Execute(
            "UPDATE {$this->tableRegionReleases} SET status = 'UNCLAIMED', updated_at = CURRENT_TIMESTAMP WHERE region_release_id = {$regionReleaseId}",
            'DB'
        );

        return $this->ok(['region_release_id' => $regionReleaseId]);
    }

    public function getRegionReleaseDocuments($id)
    {
        return $this->ok([]);
    }

    // Same release-type-free shape as saveRelease(), minus EMAIL_NOTICE vs
    // DIRECT — region releases always go through a department and are
    // notified later, in bulk, via sendRegionReleaseNotice() rather than at
    // save time. $data['check_number'] is again the <select>'s check_id
    // value (see saveRelease()'s comment on that naming).
    public function saveRegionRelease($data)
    {
        $checkId      = (int) ($data['check_number'] ?? 0);
        $departmentId = (int) ($data['department_id'] ?? 0);
        if ($checkId <= 0) {
            return json_encode(["success" => false, "message" => "Select a check."]);
        }
        if ($departmentId <= 0) {
            return json_encode(["success" => false, "message" => "Select a region/department."]);
        }
        if (!App\ExecuteRow("SELECT check_id FROM {$this->tableChecks} WHERE check_id = {$checkId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Check not found."]);
        }
        $dept = App\ExecuteRow("SELECT department_name, department_acronym FROM {$this->tableDepartments} WHERE department_id = {$departmentId}", 'DB');
        if (!$dept) {
            return json_encode(["success" => false, "message" => "Department not found."]);
        }
        $regionLabel = $dept['department_acronym'] ? "{$dept['department_acronym']} - {$dept['department_name']}" : $dept['department_name'];
        $userId      = (int) App\CurrentUserID();

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableRegionReleases}
                (check_id, department_id, region_dept, claimant_name, rdtf_no, status, release_date, remarks, created_by)
             VALUES (
                {$checkId}, {$departmentId}, " . $this->str($regionLabel) . ', '
                . $this->strOrNull($data['claimant_name'] ?? null) . ', ' . $this->strOrNull($data['rdtf_no'] ?? null) . ", "
                . "'PENDING', " . $this->dateOrNull($data['release_date'] ?? null) . ', '
                . $this->strOrNull($data['remarks'] ?? null) . ", {$userId})
             RETURNING region_release_id",
            'DB'
        );

        return $this->ok(['region_release_id' => $row['region_release_id']]);
    }

    public function acknowledgeRegionRelease($id, $data)
    {
        $regionReleaseId = (int) $id;
        if (!App\ExecuteRow("SELECT region_release_id FROM {$this->tableRegionReleases} WHERE region_release_id = {$regionReleaseId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Region release not found."]);
        }
        App\Execute(
            "UPDATE {$this->tableRegionReleases}
             SET status = 'ACKNOWLEDGED', acknowledgment_date = CURRENT_DATE, id_submitted = " . $this->strOrNull($data['id_submitted'] ?? null) . ", updated_at = CURRENT_TIMESTAMP
             WHERE region_release_id = {$regionReleaseId}",
            'DB'
        );

        return $this->ok(['region_release_id' => $regionReleaseId]);
    }

    public function recordRegionDvReturn($id, $data)
    {
        $regionReleaseId = (int) $id;
        if (!App\ExecuteRow("SELECT region_release_id FROM {$this->tableRegionReleases} WHERE region_release_id = {$regionReleaseId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Region release not found."]);
        }
        App\Execute(
            "UPDATE {$this->tableRegionReleases}
             SET dv_returned = TRUE, dv_return_date = CURRENT_DATE, dv_reference = " . $this->strOrNull($data['dv_reference'] ?? null) . ", updated_at = CURRENT_TIMESTAMP
             WHERE region_release_id = {$regionReleaseId}",
            'DB'
        );

        return $this->ok(['region_release_id' => $regionReleaseId]);
    }

    // Every region release currently PENDING for a department — the set
    // sendRegionReleaseNotice()/previewRegionReleaseNotice() bundle into one
    // email, and resendRegionEmail() re-gathers via the log's stored
    // department_id (see that method's comment on why it re-sends fresh
    // rather than replaying stored content).
    private function fetchPendingRegionReleases(int $departmentId): array
    {
        return App\ExecuteRowsAssociative(
            "SELECT rr.region_release_id, c.check_id, c.check_number, c.check_date, c.payee, c.amount,
                    c.dv_number, ba.bank_name, dv.particulars
             FROM {$this->tableRegionReleases} rr
             JOIN {$this->tableChecks} c ON c.check_id = rr.check_id
             LEFT JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = c.bank_account_id
             LEFT JOIN {$this->tableDv} dv ON dv.dv_no = c.dv_number
             WHERE rr.department_id = {$departmentId} AND rr.status = 'PENDING'
             ORDER BY c.check_date",
            'DB'
        );
    }

    private function departmentLabel(int $departmentId): ?string
    {
        $dept = App\ExecuteRow("SELECT department_name, department_acronym FROM {$this->tableDepartments} WHERE department_id = {$departmentId}", 'DB');
        if (!$dept) {
            return null;
        }

        return $dept['department_acronym'] ? "{$dept['department_acronym']} - {$dept['department_name']}" : $dept['department_name'];
    }

    public function previewRegionReleaseNotice($params)
    {
        $departmentId = (int) ($params['department_id'] ?? 0);
        if ($departmentId <= 0) {
            return $this->ok(['html' => '<p>Select a region/department first.</p>']);
        }
        $label = $this->departmentLabel($departmentId) ?? '';
        $releases = $this->fetchPendingRegionReleases($departmentId);

        return $this->ok(['html' => $this->buildRegionEmailBody($releases, $label, (string) ($params['period'] ?? ''), (string) ($params['message'] ?? ''))]);
    }

    // Bundles every currently-PENDING release for one department into a
    // single email + log row, then flips them all to RELEASED. See this
    // class's docblock for why this calls App\SendEmail() directly.
    public function sendRegionReleaseNotice($data)
    {
        $departmentId = (int) ($data['department_id'] ?? 0);
        $recipient    = trim((string) ($data['recipient_email'] ?? ''));
        if ($departmentId <= 0) {
            return json_encode(["success" => false, "message" => "Select a region/department."]);
        }
        if ($recipient === '') {
            return json_encode(["success" => false, "message" => "Recipient email is required."]);
        }
        $releases = $this->fetchPendingRegionReleases($departmentId);
        if (!$releases) {
            return json_encode(["success" => false, "message" => "No pending releases for this region/department."]);
        }
        $label  = $this->departmentLabel($departmentId) ?? '';
        $period = trim((string) ($data['period'] ?? ''));

        return $this->dispatchRegionNotice($departmentId, $label, $releases, $period, trim((string) ($data['message'] ?? '')), $recipient);
    }

    // No stored subject/body/check-list snapshot on check_region_email_log
    // (see that table's own header comment) — "resend" means re-gather
    // whatever is currently PENDING for the log row's department and send a
    // fresh notice, not replay the original email verbatim.
    public function resendRegionEmail($logId)
    {
        $log = App\ExecuteRow("SELECT department_id, recipient_email FROM {$this->tableRegionEmailLog} WHERE log_id = " . (int) $logId, 'DB');
        if (!$log || !$log['department_id']) {
            return json_encode(["success" => false, "message" => "Log entry not found or has no department on record to resend to."]);
        }
        $departmentId = (int) $log['department_id'];
        $releases     = $this->fetchPendingRegionReleases($departmentId);
        if (!$releases) {
            return json_encode(["success" => false, "message" => "No pending releases remain for this region/department — nothing to resend."]);
        }
        $label = $this->departmentLabel($departmentId) ?? '';

        return $this->dispatchRegionNotice($departmentId, $label, $releases, '', '', $log['recipient_email']);
    }

    private function dispatchRegionNotice(int $departmentId, string $label, array $releases, string $period, string $message, string $recipient)
    {
        $subject = "Monthly Check Release Notice \u{2014} {$label}" . ($period !== '' ? " ({$period})" : '');
        $body    = $this->buildRegionEmailBody($releases, $label, $period, $message);
        $result  = $this->sendModuleEmail($recipient, $subject, $body);
        $userId  = (int) App\CurrentUserID();
        $count   = count($releases);
        $total   = array_sum(array_map(fn ($r) => (float) $r['amount'], $releases));

        if ($result['success']) {
            App\Execute(
                "INSERT INTO {$this->tableRegionEmailLog} (region_dept, department_id, recipient_email, checks_count, total_amount, status, sent_by)
                 VALUES (" . $this->str($label) . ", {$departmentId}, " . $this->str($recipient) . ", {$count}, {$total}, 'SENT', {$userId})",
                'DB'
            );
            $ids = implode(',', array_column($releases, 'region_release_id'));
            App\Execute(
                "UPDATE {$this->tableRegionReleases}
                 SET status = 'RELEASED', release_date = COALESCE(release_date, CURRENT_DATE), updated_at = CURRENT_TIMESTAMP
                 WHERE region_release_id IN ({$ids})",
                'DB'
            );

            return json_encode(["success" => true, "message" => "Monthly notice sent to {$recipient} ({$count} check(s))."]);
        }

        App\Execute(
            "INSERT INTO {$this->tableRegionEmailLog} (region_dept, department_id, recipient_email, checks_count, total_amount, status, error_message, sent_by)
             VALUES (" . $this->str($label) . ", {$departmentId}, " . $this->str($recipient) . ", {$count}, {$total}, 'FAILED', " . $this->str($result['message']) . ", {$userId})",
            'DB'
        );

        return json_encode(["success" => false, "message" => "Could not send email: {$result['message']}"]);
    }

    public function saveRegionDriveLink($data)
    {
        return json_encode(["success" => false, "message" => "Not yet wired — schema migration pending."]);
    }

    // ── Check Cancellation & Replacements ──
    public function getCancellationKpis()
    {
        return $this->ok([
            "total_cancelled" => 0,
            "with_replacement" => 0,
            "pending_replacement" => 0,
            "cancelled_amount" => 0,
        ]);
    }

    public function getCancellationLookups()
    {
        return $this->ok(["bank_accounts" => [], "eligible_checks" => []]);
    }

    public function getCancellationList($params)
    {
        return $this->emptyDataTable($params["draw"] ?? 1);
    }

    public function cancelCheck($data)
    {
        return json_encode(["success" => false, "message" => "Check cancellation is not yet wired — schema migration pending."]);
    }

    public function replaceCheck($data)
    {
        return json_encode(["success" => false, "message" => "Check replacement is not yet wired — schema migration pending."]);
    }

    public function getCancelledCheck($id)
    {
        return json_encode(["success" => false, "message" => "Not found (schema not migrated yet)"]);
    }

    public function getCancelledCheckHistory($id)
    {
        return $this->ok([]);
    }

    // ── Check Inventory / RAF (server-rendered, no AJAX — see page PHP) ──
}
