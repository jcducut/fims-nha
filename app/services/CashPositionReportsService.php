<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Cash Position module's Reports tab — a port of TREASURY's
 * cashposreports.php / CashPosRepServices.php (18 report types dispatched
 * off a single `report_type` param, same as TREASURY's getReportData()).
 *
 * Real, fully-wired against this project's actual schema: daily, the
 * period/classification reports (weekly/monthly/quarterly/annual/
 * by_fund_source/by_bank_account/by_classification), working paper,
 * individual ledger, and New FY Opening Balance — all built from
 * fims.cash_position + fims.bank_accounts + fims.checks (this project's
 * only transaction-level ledger).
 *
 * Intentionally STUBBED (structurally-correct, empty) — same convention
 * as Check Cancellation/RAF: cash_flow, investment, interest_earnings,
 * interest_earnings_memo, garnished, expropriation (+ its two annexes),
 * investment_schedule. Each of these needs an entire TREASURY subsystem
 * this project has never built (Collections, Investments, Fund Transfers/
 * FTLA, Garnished Accounts, Expropriation Accounts) — faithfully porting
 * them means designing those subsystems from scratch first, which is
 * its own separate scope of work, not a Cash Position reporting detail.
 *
 * Where a real report's SOURCE DATA is partially missing (daily report's
 * bank-deposit/FTLA columns, working paper's collections/FTLA legs,
 * individual ledger's collections/FTLA activity), the query still runs
 * for real against what DOES exist (checks) and the missing legs are
 * explicitly zeroed/labeled, not silently guessed.
 */
class CashPositionReportsService
{
    private string $tableCashPosition = 'fims.cash_position';
    private string $tableBankAccounts = 'fims.bank_accounts';
    private string $tableChecks = 'fims.checks';

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function ok($data = [])
    {
        return json_encode(["success" => true, "data" => $data]);
    }

    private function sanitizeDate(mixed $v, string $default): string
    {
        $v = trim((string) ($v ?? ''));

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : $default;
    }

    private const STUBBED_REPORT_TYPES = [
        'cash_flow', 'investment', 'interest_earnings', 'interest_earnings_memo',
        'garnished', 'expropriation', 'expropriation_report',
        'annex_b_below_minimum', 'annex_c_zero_balance', 'investment_schedule',
    ];

    // ── Dispatcher (mirrors TREASURY's getReportData()) ──
    public function getReportData($params)
    {
        $type = trim((string) ($params['report_type'] ?? ''));

        if ($type === 'daily') {
            return $this->getDailyReport($params);
        }
        if (in_array($type, ['weekly', 'monthly', 'quarterly', 'annual', 'by_fund_source', 'by_bank_account', 'by_classification'], true)) {
            return $this->getPeriodReport($params);
        }
        if ($type === 'working_paper') {
            return $this->getWorkingPaper($params);
        }
        if ($type === 'individual_ledger') {
            return $this->getIndividualLedger($params);
        }
        if (in_array($type, self::STUBBED_REPORT_TYPES, true)) {
            return json_encode([
                "success" => true,
                "message" => "This report needs a subsystem this project hasn't built yet (see CashPositionReportsService's docblock) — returning an empty-but-valid result so the UI renders cleanly.",
                "records" => [],
                "sections" => [],
            ]);
        }

        return json_encode(["success" => false, "message" => "Unknown report type.", "records" => []]);
    }

    // ── Daily Cash Position ──
    // Bank deposits / credit memo / debit memo / FTLA / interest / WHT
    // columns are zeroed (no collections/fund_transfers tables exist here)
    // — check disbursements/cancellations are real, from fims.checks.
    public function getDailyReport($params)
    {
        $dateTo = $this->sanitizeDate($params['date_to'] ?? null, date('Y-m-d'));
        $date = $this->str($dateTo);

        $rows = App\ExecuteRowsAssociative(
            "SELECT
                cp.bank_account_id, ba.account_name, ba.account_number, ba.bank_name, ba.account_classification,
                ba.fund_source,
                COALESCE(cp.opening_balance, 0) AS opening_balance,
                0 AS bank_deposits, 0 AS credit_memo, 0 AS debit_memo,
                COALESCE(chk.check_disbursements, 0) AS disbursement_thru_check,
                COALESCE(chk.cancelled_checks, 0) AS cancelled_staled_check,
                0 AS disbursement_thru_ftla, 0 AS cancelled_ftla,
                0 AS interest_earned, 0 AS withholding_tax,
                COALESCE(cp.closing_balance, 0) AS balance,
                COALESCE(cp.restricted_amount, 0) AS restricted_amount,
                COALESCE(cp.available_balance, 0) AS available_balance,
                COALESCE(cp.opening_balance, 0) AS previous_balance,
                cp.bank_statement_balance AS bal_per_ledger
             FROM {$this->tableCashPosition} cp
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = cp.bank_account_id
             LEFT JOIN (
                 SELECT bank_account_id,
                        SUM(CASE WHEN status NOT IN ('CANCELLED', 'STALE') THEN amount ELSE 0 END) AS check_disbursements,
                        SUM(CASE WHEN status IN ('CANCELLED', 'STALE') THEN amount ELSE 0 END) AS cancelled_checks
                 FROM {$this->tableChecks}
                 WHERE check_date = {$date}
                 GROUP BY bank_account_id
             ) chk ON chk.bank_account_id = cp.bank_account_id
             WHERE cp.position_date = {$date} AND ba.status = 'ACTIVE'
             ORDER BY ba.account_classification, ba.account_name",
            'DB'
        );

        return $this->ok(['as_of' => $dateTo, 'records' => $rows]);
    }

    // ── Period / Classification Report ──
    // Covers TREASURY's weekly/monthly/quarterly/annual (plain date-range
    // reports — the period length is a UI/date-picker concern, not a
    // different query) and by_fund_source/by_bank_account/by_classification
    // (same query, just pre-filtered). by_corporate/by_trust/by_subsidy
    // don't map cleanly onto this project's account_classification
    // vocabulary (COLLECTION/DISBURSEMENT/.../TRUST, not CORPORATE/
    // SUBSIDY/TRUST) — by_classification takes any classification value
    // instead of three hardcoded TREASURY-specific ones.
    public function getPeriodReport($params)
    {
        $dateFrom = $this->sanitizeDate($params['date_from'] ?? null, date('Y-01-01'));
        $dateTo = $this->sanitizeDate($params['date_to'] ?? null, date('Y-m-d'));
        $where = ['cp.position_date BETWEEN ' . $this->str($dateFrom) . ' AND ' . $this->str($dateTo)];

        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        if ($bankAccountId > 0) {
            $where[] = "cp.bank_account_id = {$bankAccountId}";
        }
        $fundSource = trim((string) ($params['fund_source'] ?? ''));
        if ($fundSource !== '') {
            $where[] = 'ba.fund_source = ' . $this->str($fundSource);
        }
        $classification = trim((string) ($params['classification'] ?? ''));
        if ($classification !== '') {
            $where[] = 'ba.account_classification = ' . $this->str($classification);
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT cp.cash_position_id, cp.position_date, ba.account_name, ba.account_number, ba.bank_name,
                    ba.fund_source, ba.account_classification,
                    cp.opening_balance, cp.total_receipts, cp.total_disbursements, cp.closing_balance,
                    cp.bank_statement_balance, cp.reconciled, cp.restricted_amount, cp.available_balance
             FROM {$this->tableCashPosition} cp
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = cp.bank_account_id
             WHERE {$whereSql}
             ORDER BY cp.position_date DESC, ba.account_name",
            'DB'
        );

        $totalsRow = App\ExecuteRow(
            "SELECT SUM(cp.opening_balance) AS opening, SUM(cp.total_receipts) AS receipts,
                    SUM(cp.total_disbursements) AS disbursements, SUM(cp.closing_balance) AS closing,
                    SUM(cp.restricted_amount) AS restricted, SUM(cp.available_balance) AS available
             FROM {$this->tableCashPosition} cp
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = cp.bank_account_id
             WHERE {$whereSql}",
            'DB'
        );

        return $this->ok([
            'records' => $rows,
            'totals' => [
                'opening' => $this->num($totalsRow['opening'] ?? 0),
                'receipts' => $this->num($totalsRow['receipts'] ?? 0),
                'disbursements' => $this->num($totalsRow['disbursements'] ?? 0),
                'closing' => $this->num($totalsRow['closing'] ?? 0),
                'restricted' => $this->num($totalsRow['restricted'] ?? 0),
                'available' => $this->num($totalsRow['available'] ?? 0),
            ],
        ]);
    }

    // ── Working Paper ── (checks leg only — no collections/FTLA/adjustments tables)
    public function getWorkingPaper($params)
    {
        $dateFrom = $this->sanitizeDate($params['date_from'] ?? null, date('Y-01-01'));
        $dateTo = $this->sanitizeDate($params['date_to'] ?? null, date('Y-m-d'));
        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        $acctFilter = $bankAccountId > 0 ? "AND p.bank_account_id = {$bankAccountId}" : '';

        $rows = App\ExecuteRowsAssociative(
            "SELECT p.check_date AS txn_date, p.check_number AS reference, 'PAYMENT' AS transaction_type,
                    ba.account_name, p.payee AS party_name, NULL::text AS description,
                    NULL::numeric AS debit, p.amount AS credit
             FROM {$this->tableChecks} p
             JOIN {$this->tableBankAccounts} ba ON ba.bank_account_id = p.bank_account_id
             WHERE p.check_date BETWEEN " . $this->str($dateFrom) . ' AND ' . $this->str($dateTo) . "
               {$acctFilter}
               AND p.status NOT IN ('CANCELLED', 'STALE')
             ORDER BY p.check_date DESC",
            'DB'
        );

        $summary = null;
        if ($bankAccountId > 0) {
            $beginning = App\ExecuteRow(
                "SELECT opening_balance, restricted_amount, available_balance FROM {$this->tableCashPosition}
                 WHERE bank_account_id = {$bankAccountId} AND position_date <= " . $this->str($dateFrom) . "
                 ORDER BY position_date DESC LIMIT 1",
                'DB'
            );
            $ending = App\ExecuteRow(
                "SELECT closing_balance, restricted_amount, available_balance FROM {$this->tableCashPosition}
                 WHERE bank_account_id = {$bankAccountId} AND position_date <= " . $this->str($dateTo) . "
                 ORDER BY position_date DESC LIMIT 1",
                'DB'
            );
            $summary = [
                'beginning_balance' => $this->num($beginning['opening_balance'] ?? 0),
                'ending_balance' => $this->num($ending['closing_balance'] ?? 0),
                'restricted_amount' => $this->num($ending['restricted_amount'] ?? 0),
                'available_balance' => $this->num($ending['available_balance'] ?? 0),
            ];
        }

        return $this->ok(['records' => $rows, 'summary' => $summary]);
    }

    // ── Individual Accounts Ledger ── (checks activity only)
    public function getIndividualLedger($params)
    {
        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        if ($bankAccountId <= 0) {
            return $this->ok(['account' => null, 'days' => []]);
        }
        $dateFrom = $this->sanitizeDate($params['date_from'] ?? null, date('Y-01-01'));
        $dateTo = $this->sanitizeDate($params['date_to'] ?? null, date('Y-m-d'));

        $account = App\ExecuteRow(
            "SELECT account_name, account_number, bank_name, account_classification
             FROM {$this->tableBankAccounts} WHERE bank_account_id = {$bankAccountId}",
            'DB'
        );
        if (!$account) {
            return $this->ok(['account' => null, 'days' => []]);
        }

        $balanceRows = App\ExecuteRowsAssociative(
            "SELECT position_date, bank_statement_balance AS soa_bal, available_balance AS avail_bal
             FROM {$this->tableCashPosition}
             WHERE bank_account_id = {$bankAccountId} AND position_date BETWEEN " . $this->str($dateFrom) . ' AND ' . $this->str($dateTo) . '
             ORDER BY position_date ASC',
            'DB'
        );
        $checkRows = App\ExecuteRowsAssociative(
            "SELECT check_date,
                    SUM(CASE WHEN status NOT IN ('CANCELLED', 'STALE', 'ENCASHED') THEN amount ELSE 0 END) AS disbursed,
                    SUM(CASE WHEN status = 'ENCASHED' THEN amount ELSE 0 END) AS encashed,
                    SUM(CASE WHEN status IN ('CANCELLED', 'STALE') THEN amount ELSE 0 END) AS cancelled
             FROM {$this->tableChecks}
             WHERE bank_account_id = {$bankAccountId} AND check_date BETWEEN " . $this->str($dateFrom) . ' AND ' . $this->str($dateTo) . '
             GROUP BY check_date
             ORDER BY check_date',
            'DB'
        );

        $days = [];
        foreach ($balanceRows as $row) {
            $days[] = ['is_balance' => true, 'date' => $row['position_date'], 'soa_balance' => $row['soa_bal'], 'available_balance' => $row['avail_bal']];
        }
        foreach ($checkRows as $row) {
            $days[] = [
                'is_balance' => false, 'date' => $row['check_date'],
                'checks' => ['disbursed' => $this->num($row['disbursed']), 'encashed' => $this->num($row['encashed']), 'cancelled' => $this->num($row['cancelled'])],
            ];
        }
        usort($days, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return $this->ok([
            'account' => ['name' => $account['account_name'], 'bank_name' => $account['bank_name'], 'account_number' => $account['account_number'], 'classification' => $account['account_classification']],
            'days' => $days,
        ]);
    }

    // ── New FY Opening Balance ── (rolls each selected account's latest
    // closing_balance forward as January 1 of the new year's opening row)
    public function prepareFYOpeningBalance($data)
    {
        $fromYear = (int) ($data['from_year'] ?? 0);
        $newYear = (int) ($data['new_year'] ?? 0);
        $accountIds = is_array($data['account_ids'] ?? null) ? array_map('intval', $data['account_ids']) : [];
        if ($newYear <= $fromYear) {
            return json_encode(["success" => false, "message" => "New fiscal year must be after the source year."]);
        }
        if (empty($accountIds)) {
            return json_encode(["success" => false, "message" => "Select at least one bank account."]);
        }

        $closingDate = $this->str("{$fromYear}-12-31");
        $openingDate = "{$newYear}-01-01";
        $userId = (int) App\CurrentUserID();
        $created = [];
        $skipped = [];

        foreach ($accountIds as $bankAccountId) {
            $prior = App\ExecuteRow(
                "SELECT closing_balance FROM {$this->tableCashPosition}
                 WHERE bank_account_id = {$bankAccountId} AND position_date <= {$closingDate}
                 ORDER BY position_date DESC LIMIT 1",
                'DB'
            );
            if (!$prior) {
                $skipped[] = $bankAccountId;
                continue;
            }
            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableCashPosition} WHERE bank_account_id = {$bankAccountId} AND position_date = " . $this->str($openingDate),
                'DB'
            );
            if ($exists > 0) {
                $skipped[] = $bankAccountId;
                continue;
            }
            $closingBalance = (float) $prior['closing_balance'];
            App\Execute(
                "INSERT INTO {$this->tableCashPosition}
                    (bank_account_id, position_date, opening_balance, total_receipts, total_disbursements, closing_balance, reconciled, restricted_amount, created_by)
                 VALUES ({$bankAccountId}, " . $this->str($openingDate) . ", {$closingBalance}, 0, 0, {$closingBalance}, FALSE, 0, {$userId})",
                'DB'
            );
            $created[] = $bankAccountId;
        }

        return $this->ok(['created' => $created, 'skipped' => $skipped]);
    }

    // ── Lookups (same data as CashPositionService's, kept here too for
    // API parity with TREASURY's cashposreports.php having its own copies) ──
    public function getBankAccounts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_number, account_name, account_type, bank_name, account_classification
             FROM {$this->tableBankAccounts} WHERE status = 'ACTIVE' ORDER BY bank_name, account_name",
            'DB'
        );

        return $this->ok($rows);
    }

    public function getFundSources()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT DISTINCT fund_source FROM {$this->tableBankAccounts}
             WHERE fund_source IS NOT NULL AND fund_source <> '' ORDER BY fund_source",
            'DB'
        );

        return $this->ok(array_map(fn ($r) => $r['fund_source'], $rows));
    }
}
