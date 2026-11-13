<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * One line item = one chart-of-accounts allocation under a Project "line
 * item" (fims.budget_req_project), which itself belongs to a budget_req
 * header. Used both to add individual line items and to edit/delete one
 * in isolation.
 */
class BudgetLineItemService
{
    private string $table                 = 'fims.budget_line_items';
    private string $tableBudgetReq        = 'fims.budget_req';
    private string $tableBudgetReqProject = 'fims.budget_req_project';
    private string $tableChartOfAccounts  = 'fims.chart_of_accounts';
    private string $tableProject          = 'fims.project';
    private string $tableTypeOfFund       = 'fims.type_of_fund';
    private string $tableComponentType    = 'fims.component_type';
    private string $tableBudgetReqProgram = 'fims.budget_req_program';
    private string $tableStatus           = 'fims.settings_status';
    private string $tableEarmark          = 'fims.budget_line_item_earmark';
    private string $tableBudgetAllocation = 'fims.budget_allocation';
    private string $tableRc               = 'fims.responsibility_code';

    public function fetchAlls(?int $page = null, int $limit = 10, string $fiscalYr = ''): array
    {
        try {
            $where = ['1=1'];
            if ($fiscalYr !== '') {
                $where[] = 'br.fiscal_yr = ' . (int) $fiscalYr;
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT li.*, br.fiscal_yr,
                               brp.project_code, pj.project_name,
                               coa.account_code, coa.account_title,
                               st.name AS status_name, st.color AS status_color
                        FROM {$this->table} li
                        JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                        LEFT JOIN {$this->tableBudgetReqProject} brp ON brp.budget_req_project_id = li.budget_req_project_id
                        LEFT JOIN {$this->tableProject} pj ON pj.project_code = brp.project_code
                        LEFT JOIN {$this->tableChartOfAccounts} coa ON coa.account_id = li.account_id
                        LEFT JOIN fims.settings_status st ON st.id = br.status_id
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY br.fiscal_yr DESC, li.budget_line_item_id DESC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $countSql = "SELECT COUNT(*) FROM {$this->table} li
                         JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                         WHERE {$whereSql}";
            $total = (int) App\ExecuteScalar($countSql, 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY br.fiscal_yr DESC, li.budget_line_item_id DESC LIMIT {$limit} OFFSET {$offset}", 'DB');

            $sumSql = "SELECT COALESCE(SUM(li.proposed_amount), 0) FROM {$this->table} li
                       JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                       WHERE {$whereSql}";
            $totalAmount = (float) App\ExecuteScalar($sumSql, 'DB');

            return [
                'success'      => true,
                'data'         => $data ?: [],
                'total_amount' => $totalAmount,
                'pagination'   => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), DataType::STRING, 'DB');
    }

    /**
     * Line items of APPROVED budget requests — the Memo Request form's COB list, ordered by Account Code. Every row
     * carries its Type of Fund and Account Code. Each optional filter narrows the list to what is attached to that Program /
     * Sub-Program / Project (and, with $typeOfFundId, to line items of that Type of Fund): a line item hangs off
     * budget_line_items -> budget_req_project (project_code) -> budget_req_program (program_code, sub_program_code).
     * Only a budget_req whose status is 'Approved' (fims.settings_status, status_for = 'budget_req') qualifies.
     * Line items of an older request that never got its program block (budget_req_program_id IS NULL) have no
     * program to match, so they appear only while no program / sub-program is chosen.
     */
    public function fetchApproved(string $programCode = '', string $subProgramCode = '', string $projectCode = '', string $fiscalYr = '', string $typeOfFundId = ''): array
    {
        try {
            $where = $this->approvedConditions($programCode, $subProgramCode, $projectCode, $fiscalYr, $typeOfFundId);

            $sql = "SELECT li.budget_line_item_id, li.budget_reference_number, br.fiscal_yr,
                           brpr.program_code, brpr.sub_program_code, brp.project_code, pj.project_name,
                           li.type_of_fund_id, tf.name AS type_of_fund,
                           li.account_id, coa.account_code, coa.account_title,
                           li.proposed_amount, li.suggested_amount, li.adjustment, li.obligation,
                           (li.suggested_amount + li.adjustment - li.obligation - COALESCE((
                               SELECT SUM(e.amount) FROM {$this->tableEarmark} e
                               WHERE e.budget_line_item_id = li.budget_line_item_id AND e.status = 'active'
                           ), 0)) AS available_amount,
                           li.particulars, st.name AS status_name
                    FROM {$this->table} li
                    JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                    JOIN {$this->tableStatus} st ON st.id = br.status_id
                    LEFT JOIN {$this->tableBudgetReqProject} brp ON brp.budget_req_project_id = li.budget_req_project_id
                    LEFT JOIN {$this->tableBudgetReqProgram} brpr ON brpr.budget_req_program_id = brp.budget_req_program_id
                    LEFT JOIN {$this->tableProject} pj ON pj.project_code = brp.project_code
                    LEFT JOIN {$this->tableTypeOfFund} tf ON tf.type_of_fund_id = li.type_of_fund_id
                    LEFT JOIN {$this->tableChartOfAccounts} coa ON coa.account_id = li.account_id
                    WHERE " . implode(' AND ', $where) . '
                    ORDER BY coa.account_code ASC NULLS LAST, li.budget_line_item_id ASC';

            return ['success' => true, 'data' => App\ExecuteRows($sql, 'DB') ?: []];
        } catch (\Exception $e) {
            App\LogError('BudgetLineItemService::fetchApproved ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load line items.'];
        }
    }

    /**
     * WHERE conditions of "an approved line item", narrowed by whichever of the filters are given. Shared by the COB
     * list and by the list of available types of fund, so the two always agree.
     *
     * @return string[]
     */
    private function approvedConditions(string $programCode, string $subProgramCode, string $projectCode, string $fiscalYr, string $typeOfFundId = ''): array
    {
        $where = ["st.status_for = 'budget_req'", "LOWER(st.name) = 'approved'"];
        if (trim($programCode) !== '') {
            $where[] = 'brpr.program_code = ' . $this->str($programCode);
        }
        if (trim($subProgramCode) !== '') {
            $where[] = 'brpr.sub_program_code = ' . $this->str($subProgramCode);
        }
        if (trim($projectCode) !== '') {
            $where[] = 'brp.project_code = ' . $this->str($projectCode);
        }
        if (trim($fiscalYr) !== '') {
            $where[] = 'br.fiscal_yr = ' . (int) $fiscalYr;
        }
        if (trim($typeOfFundId) !== '') {
            $where[] = 'li.type_of_fund_id = ' . (int) $typeOfFundId;
        }

        return $where;
    }

    /**
     * The types of fund that exist on approved line items — the options of the Memo Request form's Type of Fund
     * dropdown. Narrowed by the same Program / Sub-Program / Project / fiscal year filters as the COB list, but not by
     * type of fund itself (it is what is being chosen). A type of fund no approved line item uses is not offered, and
     * neither are line items with no type of fund.
     */
    public function fetchApprovedTypesOfFund(string $programCode = '', string $subProgramCode = '', string $projectCode = '', string $fiscalYr = ''): array
    {
        try {
            $where = $this->approvedConditions($programCode, $subProgramCode, $projectCode, $fiscalYr);
            $where[] = 'tf.deleted_at IS NULL';

            $sql = "SELECT DISTINCT tf.type_of_fund_id, tf.name
                    FROM {$this->table} li
                    JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                    JOIN {$this->tableStatus} st ON st.id = br.status_id
                    JOIN {$this->tableTypeOfFund} tf ON tf.type_of_fund_id = li.type_of_fund_id
                    LEFT JOIN {$this->tableBudgetReqProject} brp ON brp.budget_req_project_id = li.budget_req_project_id
                    LEFT JOIN {$this->tableBudgetReqProgram} brpr ON brpr.budget_req_program_id = brp.budget_req_program_id
                    WHERE " . implode(' AND ', $where) . '
                    ORDER BY tf.name ASC, tf.type_of_fund_id ASC';

            return ['success' => true, 'data' => App\ExecuteRows($sql, 'DB') ?: []];
        } catch (\Exception $e) {
            App\LogError('BudgetLineItemService::fetchApprovedTypesOfFund ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load types of fund.'];
        }
    }

    // -----------------------------------------------------------------------
    // Earmarks — fims.budget_line_item_earmark. One row per Approved budget_allocation (CBA / RFA / LAA /
    // CFA) line reserving an amount against a line item's balance — ceiling is suggested_amount (what
    // FMD actually approved for this line item during Budget Processing), NOT proposed_amount (what the
    // requesting office originally asked for). Created by createForApproval() — called from
    // BudgetAllocationService::process() the moment a request reaches Approved — and released by
    // releaseForAllocation() — called from BudgetAllocationService::delete(). Every method here runs
    // INSIDE the caller's own open transaction (BudgetAllocationService already wraps its status change
    // in BEGIN/COMMIT); none of these open, commit or roll one back themselves. See the table's own SQL
    // file for the full column/lifecycle comment: "_assets/sql/01 - fims/01 - table/26 -
    // fims_budget_line_item_earmark.sql".
    // -----------------------------------------------------------------------

    /**
     * Normalizes a decoded line_transaction value — an array of line objects, a single line object (an
     * associative array, not a list), or null/empty — into a flat list of
     * ['budget_line_item_id' => int, 'requested_amount' => float] pairs, one per entry that actually
     * names a line item with a positive requested amount. Mirrors
     * BudgetAllocationService::requestedTotalSql()'s tolerance for comma-formatted amount strings, so
     * the two always agree on what a line "is worth".
     */
    public function parseLineTransaction(mixed $lineTransaction): array
    {
        if (is_string($lineTransaction)) {
            $lineTransaction = json_decode($lineTransaction, true);
        }
        if (!is_array($lineTransaction) || $lineTransaction === []) {
            return [];
        }
        // A single line comes back as an associative array (string keys), not a list.
        $entries = array_is_list($lineTransaction) ? $lineTransaction : [$lineTransaction];

        $parsed = [];
        foreach ($entries as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $lineItemId = (int) ($entry['budget_line_item_id'] ?? 0);
            if ($lineItemId <= 0) {
                continue;
            }
            $amount = $this->parseAmount($entry['requested_amount'] ?? null);
            if ($amount <= 0) {
                continue;
            }
            $parsed[] = ['budget_line_item_id' => $lineItemId, 'requested_amount' => $amount];
        }

        return $parsed;
    }

    private function parseAmount(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $clean = str_replace(',', '', trim($value));
            if (preg_match('/^-?[0-9]+(\.[0-9]+)?$/', $clean)) {
                return (float) $clean;
            }
        }

        return 0.0;
    }

    /**
     * The balance still available to earmark against one line item: suggested_amount + adjustment -
     * obligation - SUM(active earmarks). Locks the row (SELECT ... FOR UPDATE) so two concurrent
     * approvals against the same line item can't both pass the check before either commits — the
     * caller must already be inside an open transaction. Returns null when the line item no longer
     * exists.
     */
    public function lockAndGetAvailable(int $budgetLineItemId): ?float
    {
        $row = App\ExecuteRow(
            "SELECT suggested_amount, adjustment, obligation FROM {$this->table}
             WHERE budget_line_item_id = {$budgetLineItemId} FOR UPDATE",
            'DB'
        );
        if (!$row) {
            return null;
        }

        $earmarked = (float) App\ExecuteScalar(
            "SELECT COALESCE(SUM(amount), 0) FROM {$this->tableEarmark}
             WHERE budget_line_item_id = {$budgetLineItemId} AND status = 'active'",
            'DB'
        );

        return (float) $row['suggested_amount'] + (float) $row['adjustment'] - (float) $row['obligation'] - $earmarked;
    }

    /**
     * Validates every COB of a decoded (or still-JSON) line_transaction against its available balance
     * (lines against the same COB are totalled together before checking, so neither looks small enough
     * on its own) and, only if every one has enough room, inserts one 'active' earmark row PER DISTINCT
     * COB (not one per raw line_transaction entry — two lines against the same COB become one combined
     * row). That keeps exactly one active row per (allocation, COB) pair, which
     * convertEarmarkForRfaApproval() below relies on to shrink cleanly as RFAs/LAAs draw it down.
     * Returns ['success' => true] (including when there is nothing to reserve) or
     * ['success' => false, 'message' => ...]; on failure nothing is inserted — the caller
     * (mid-transaction) is expected to roll back.
     */
    public function createForApproval(int $allocationId, mixed $lineTransaction): array
    {
        $lines = $this->parseLineTransaction($lineTransaction);
        if (!$lines) {
            return ['success' => true];
        }

        $totals = [];
        foreach ($lines as $line) {
            $id = $line['budget_line_item_id'];
            $totals[$id] = ($totals[$id] ?? 0) + $line['requested_amount'];
        }

        foreach ($totals as $lineItemId => $requested) {
            $available = $this->lockAndGetAvailable($lineItemId);
            if ($available === null) {
                return ['success' => false, 'message' => "Budget line item #{$lineItemId} no longer exists."];
            }
            if ($requested > $available) {
                return ['success' => false, 'message' =>
                    'Requested amount ('.number_format($requested, 2).
                    ') exceeds the remaining available balance ('.number_format($available, 2).
                    ") for budget line item #{$lineItemId}."];
            }
        }

        foreach ($totals as $lineItemId => $requested) {
            $amount = App\QuotedValue($requested, DataType::NUMBER, 'DB');
            App\Execute(
                "INSERT INTO {$this->tableEarmark} (budget_line_item_id, allocation_id, amount, original_amount, status)
                 VALUES ({$lineItemId}, {$allocationId}, {$amount}, {$amount}, 'active')",
                'DB'
            );
        }

        return ['success' => true];
    }

    /**
     * Converts (part of) a parent CBA's earmark into a real obligation — called when an RFA/LAA
     * referencing that CBA (its parent_id) reaches Approved. For each distinct COB in the RFA/LAA's own
     * line_transaction (lines against the same COB totalled together first):
     *   - the parent CBA's active earmark row for that COB is reduced by the drawn amount — fully drawn
     *     -> flips to 'converted' (amount zeroed); partially drawn -> stays 'active' with the smaller
     *     remaining amount, so a later RFA/LAA can still draw down what's left;
     *   - a new 'converted' row is inserted, attributed to this RFA/LAA's OWN allocation_id, so what
     *     each RFA/LAA actually converted stays traceable independently of what the CBA still has left;
     *   - budget_line_items.obligation is increased by the same amount — the real deduction.
     * Refuses (nothing written, caller should roll back) if the CBA has no active earmark for a COB at
     * all, or if the drawn amount for any COB exceeds what's still actively earmarked there.
     */
    public function convertEarmarkForRfaApproval(int $allocationId, int $parentAllocationId, mixed $lineTransaction): array
    {
        $lines = $this->parseLineTransaction($lineTransaction);
        if (!$lines) {
            return ['success' => true];
        }

        $totals = [];
        foreach ($lines as $line) {
            $id = $line['budget_line_item_id'];
            $totals[$id] = ($totals[$id] ?? 0) + $line['requested_amount'];
        }

        // Validate every COB first (locking its budget_line_items row and the parent's earmark row for it) —
        // nothing is written until every COB in this request has enough still actively earmarked.
        $earmarkRows = [];
        foreach ($totals as $lineItemId => $requested) {
            App\ExecuteRow("SELECT 1 FROM {$this->table} WHERE budget_line_item_id = {$lineItemId} FOR UPDATE", 'DB');

            $earmark = App\ExecuteRow(
                "SELECT earmark_id, amount FROM {$this->tableEarmark}
                 WHERE allocation_id = {$parentAllocationId} AND budget_line_item_id = {$lineItemId} AND status = 'active'
                 FOR UPDATE",
                'DB'
            );
            if (!$earmark) {
                return ['success' => false, 'message' =>
                    "Parent CBA #{$parentAllocationId} has no active earmark for budget line item #{$lineItemId}."];
            }
            if ($requested > (float) $earmark['amount']) {
                return ['success' => false, 'message' =>
                    'Requested amount ('.number_format($requested, 2).
                    ') exceeds what the parent CBA still has actively earmarked ('.number_format((float) $earmark['amount'], 2).
                    ") for budget line item #{$lineItemId}."];
            }
            $earmarkRows[$lineItemId] = $earmark;
        }

        foreach ($totals as $lineItemId => $requested) {
            $earmark = $earmarkRows[$lineItemId];
            $remaining = round((float) $earmark['amount'] - $requested, 2);

            if ($remaining <= 0) {
                App\Execute(
                    "UPDATE {$this->tableEarmark} SET amount = 0, status = 'converted', released_at = CURRENT_TIMESTAMP
                     WHERE earmark_id = {$earmark['earmark_id']}",
                    'DB'
                );
            } else {
                App\Execute(
                    "UPDATE {$this->tableEarmark} SET amount = ".App\QuotedValue($remaining, DataType::NUMBER, 'DB')."
                     WHERE earmark_id = {$earmark['earmark_id']}",
                    'DB'
                );
            }

            $amount = App\QuotedValue($requested, DataType::NUMBER, 'DB');
            App\Execute(
                "INSERT INTO {$this->tableEarmark} (budget_line_item_id, allocation_id, amount, original_amount, status, released_at)
                 VALUES ({$lineItemId}, {$allocationId}, {$amount}, {$amount}, 'converted', CURRENT_TIMESTAMP)",
                'DB'
            );

            App\Execute(
                "UPDATE {$this->table} SET obligation = obligation + ".App\QuotedValue($requested, DataType::NUMBER, 'DB')."
                 WHERE budget_line_item_id = {$lineItemId}",
                'DB'
            );
        }

        return ['success' => true];
    }

    /**
     * Read-only sanity check for an RFA / LAA being created or edited (NOT yet Approved — no locking, no
     * writes): is each COB's Requested Amount within what the parent CBA currently still has actively
     * earmarked there? Mirrors the grouping and the ceiling convertEarmarkForRfaApproval() enforces at
     * Approval time, so a request that passes this can't later be rejected at Approval purely over an
     * amount that was already wrong when it was submitted. Returns ['success' => true] (including when
     * there is nothing to check) or ['success' => false, 'message' => ...].
     */
    public function validateRfaAmounts(int $parentAllocationId, mixed $lineTransaction): array
    {
        $lines = $this->parseLineTransaction($lineTransaction);
        if (!$lines) {
            return ['success' => true];
        }

        $totals = [];
        foreach ($lines as $line) {
            $id = $line['budget_line_item_id'];
            $totals[$id] = ($totals[$id] ?? 0) + $line['requested_amount'];
        }

        foreach ($totals as $lineItemId => $requested) {
            $earmark = App\ExecuteRow(
                "SELECT amount FROM {$this->tableEarmark}
                 WHERE allocation_id = {$parentAllocationId} AND budget_line_item_id = {$lineItemId} AND status = 'active'",
                'DB'
            );
            if (!$earmark) {
                return ['success' => false, 'message' =>
                    "Parent CBA #{$parentAllocationId} has no active earmark for budget line item #{$lineItemId}."];
            }
            if ($requested > (float) $earmark['amount']) {
                return ['success' => false, 'message' =>
                    'Requested amount ('.number_format($requested, 2).
                    ') exceeds the parent CBA\'s available balance ('.number_format((float) $earmark['amount'], 2).
                    ") for budget line item #{$lineItemId}."];
            }
        }

        return ['success' => true];
    }

    /** Frees every still-active earmark an allocation is holding (e.g. it was deleted after being Approved). */
    public function releaseForAllocation(int $allocationId): void
    {
        App\Execute(
            "UPDATE {$this->tableEarmark} SET status = 'released', released_at = CURRENT_TIMESTAMP
             WHERE allocation_id = {$allocationId} AND status = 'active'",
            'DB'
        );
    }

    /**
     * Every CBA/CFA earmark row against one budget line item, oldest first. Two callers:
     *   - "Where this COB's balance is held" drill-down (baaToggleEarmarks()): pure CBA/CFA only (status
     *     'active'/'released') — an RFA/LAA's own 'converted' row
     *     (see BudgetLineItemService::convertEarmarkForRfaApproval()) is deliberately excluded here; what it
     *     drew is already reflected in the parent's own (unchanged) original_amount vs. its now-smaller
     *     amount. Displays original_amount — what the CBA/CFA was originally approved for against this
     *     COB, not its current remaining balance, so the figure shown doesn't shrink as RFAs/LAAs draw
     *     against it.
     *   - An RFA/LAA's "CBA Available Balance" (baaRefreshCbaAmount()): looks up its own parent CBA's row
     *     here by allocation_id + status = 'active' and uses its `amount` (NOT original_amount) — the real,
     *     currently-remaining ceiling that lookup needs, unaffected by the CBA/CFA-only filter since a
     *     parent is always a CBA.
     * Carries each row's own allocation's reference number, type and current status so the UI can show
     * "RR-001 (CBA, Approved) — ₱50,000.00, active" per row without a second lookup.
     */
    public function fetchEarmarks(int $budgetLineItemId): array
    {
        try {
            $sql = "SELECT e.earmark_id, e.budget_line_item_id, e.allocation_id, e.amount, e.original_amount,
                           e.status AS earmark_status, e.created_at, e.released_at,
                           a.request_referrence_no, a.allocation_number, a.allocation_type,
                           st.name AS allocation_status_name, st.color AS allocation_status_color,
                           rc.rc_name AS requesting_rc_name
                    FROM {$this->tableEarmark} e
                    JOIN {$this->tableBudgetAllocation} a ON a.allocation_id = e.allocation_id
                    LEFT JOIN {$this->tableStatus} st ON st.id = a.status_id
                    LEFT JOIN {$this->tableRc} rc ON rc.rc_id = a.requesting_rc_id
                    WHERE e.budget_line_item_id = " . (int) $budgetLineItemId . "
                      AND UPPER(a.allocation_type) IN ('CBA', 'CFA')
                    ORDER BY e.created_at ASC, e.earmark_id ASC";

            return ['success' => true, 'data' => App\ExecuteRows($sql, 'DB') ?: []];
        } catch (\Exception $e) {
            App\LogError('BudgetLineItemService::fetchEarmarks ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load earmarks.'];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $sql = "SELECT li.*, br.fiscal_yr,
                           brp.project_code, pj.project_name,
                           coa.account_code, coa.account_title,
                           st.name AS status_name, st.color AS status_color
                    FROM {$this->table} li
                    JOIN {$this->tableBudgetReq} br ON br.budget_reference_number = li.budget_reference_number
                    LEFT JOIN {$this->tableBudgetReqProject} brp ON brp.budget_req_project_id = li.budget_req_project_id
                    LEFT JOIN {$this->tableProject} pj ON pj.project_code = brp.project_code
                    LEFT JOIN {$this->tableChartOfAccounts} coa ON coa.account_id = li.account_id
                    LEFT JOIN fims.settings_status st ON st.id = br.status_id
                    WHERE li.budget_line_item_id = " . (int) $id;

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Line item #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * @param array $body budget_reference_number, budget_req_project_id, account_id, proposed_amount, particulars
     */
    public function create(array $body): array
    {
        try {
            $budgetReferenceNumber = trim((string) ($body['budget_reference_number'] ?? ''));
            $budgetReqProjectId    = trim((string) ($body['budget_req_project_id'] ?? ''));
            $accountId             = trim((string) ($body['account_id'] ?? ''));
            $amount                = $body['proposed_amount'] ?? $body['amount'] ?? '';
            $particulars           = trim((string) ($body['particulars'] ?? ''));

            if ($budgetReferenceNumber === '' || $budgetReqProjectId === '' || $accountId === '' || $amount === '' || !is_numeric($amount) || $particulars === '') {
                return ['success' => false, 'message' => 'budget_reference_number, budget_req_project_id, account_id, a numeric proposed_amount, and particulars are required.'];
            }

            $header = App\ExecuteRow(
                "SELECT budget_reference_number FROM {$this->tableBudgetReq} WHERE budget_reference_number = " . App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB'),
                'DB'
            );
            if (!$header) {
                return ['success' => false, 'message' => "Budget request {$budgetReferenceNumber} does not exist."];
            }

            $project = App\ExecuteRow(
                "SELECT budget_req_project_id FROM {$this->tableBudgetReqProject} WHERE budget_req_project_id = " . (int) $budgetReqProjectId,
                'DB'
            );
            if (!$project) {
                return ['success' => false, 'message' => "Project line item #{$budgetReqProjectId} does not exist."];
            }

            $account = App\ExecuteRow(
                "SELECT account_id FROM {$this->tableChartOfAccounts}
                 WHERE account_id = " . App\QuotedValue($accountId, DataType::STRING, 'DB') . "
                   AND status = 'Active' AND posting_allowed = TRUE",
                'DB'
            );
            if (!$account) {
                return ['success' => false, 'message' => "Chart of accounts entry '{$accountId}' does not exist or is not postable."];
            }

            $sql = "INSERT INTO {$this->table} (budget_reference_number, budget_req_project_id, account_id, proposed_amount, particulars)
                    VALUES (" . App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB') . ', '
                        . (int) $budgetReqProjectId . ', '
                        . App\QuotedValue($accountId, DataType::STRING, 'DB') . ', '
                        . App\QuotedValue($amount, DataType::NUMBER, 'DB') . ', '
                        . App\QuotedValue($particulars, DataType::STRING, 'DB') . ')
                    RETURNING budget_line_item_id';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success'             => true,
                'message'             => 'Line item created successfully.',
                'budget_line_item_id' => $row['budget_line_item_id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $accountId   = trim((string) ($body['account_id'] ?? ''));
            $amount      = $body['proposed_amount'] ?? $body['amount'] ?? null;
            $particulars = trim((string) ($body['particulars'] ?? ''));

            $setParts = [];

            if ($accountId !== '') {
                $account = App\ExecuteRow(
                    "SELECT account_id FROM {$this->tableChartOfAccounts}
                     WHERE account_id = " . App\QuotedValue($accountId, DataType::STRING, 'DB') . "
                       AND status = 'Active' AND posting_allowed = TRUE",
                    'DB'
                );
                if (!$account) {
                    return ['success' => false, 'message' => "Chart of accounts entry '{$accountId}' does not exist or is not postable."];
                }
                $setParts[] = 'account_id = ' . App\QuotedValue($accountId, DataType::STRING, 'DB');
            }
            if ($amount !== null && $amount !== '' && is_numeric($amount)) {
                $setParts[] = 'proposed_amount = ' . App\QuotedValue($amount, DataType::NUMBER, 'DB');
            }
            if ($particulars !== '') {
                $setParts[] = 'particulars = ' . App\QuotedValue($particulars, DataType::STRING, 'DB');
            }

            if (empty($setParts)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $setParts) . ' WHERE budget_line_item_id = ' . (int) $id,
                'DB'
            );

            return ['success' => true, 'message' => 'Line item updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Budget Processing: applies type_of_fund_id / component_id / suggested_amount / account_id to line items of
     * one budget request. Does NOT open its own transaction — the caller (BudgetReqService::endorse) wraps it
     * together with its status change.
     * Returns an error message, or null once every item has been applied.
     *
     * account_id is OPTIONAL: the Budget Officer may re-code a line to another chart-of-accounts entry.
     *   key absent             -> left unchanged
     *   key present, blank     -> rejected (fims.budget_line_items.account_id is NOT NULL)
     *   key present, same as the line's current account -> left unchanged (nothing to check)
     *   key present, other id  -> must be an Active, postable fims.chart_of_accounts entry (same rule as update())
     *
     * @param array $items [['budget_line_item_id' => .., 'type_of_fund_id' => .., 'component_id' => .., 'suggested_amount' => .., 'account_id' => ..], ...]
     * @param bool  $requireAll when true, every item must carry a type of fund and a component (suggested_amount and account_id stay optional)
     */
    public function applyProcessing(array $items, string $budgetReferenceNumber, bool $requireAll = false): ?string
    {
        if (empty($items)) {
            return 'No line items provided.';
        }

        // Each distinct lookup id is verified once (and must not be soft-deleted).
        $validFunds      = [];
        $validComponents = [];
        $validAccounts   = [];

        foreach ($items as $i => $item) {
            $n  = $i + 1;
            $id = (int) ($item['budget_line_item_id'] ?? 0);

            if ($id <= 0) {
                return "Item {$n}: budget_line_item_id is required.";
            }

            // Must be a line item of THIS budget request, not someone else's.
            $exists = App\ExecuteRow(
                "SELECT budget_line_item_id, account_id FROM {$this->table}
                 WHERE budget_line_item_id = {$id} AND budget_reference_number = " . App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB'),
                'DB'
            );
            if (!$exists) {
                return "Line item #{$id} does not belong to budget request {$budgetReferenceNumber}.";
            }

            // account_id (see the method comment): null = leave the line's account as it is.
            $accountId = null;
            if (array_key_exists('account_id', $item)) {
                $value     = $item['account_id'];
                $accountId = is_scalar($value) ? trim((string) $value) : '';
                if ($accountId === '') {
                    return "Line item #{$id}: account_id cannot be blank.";
                }
                if ($accountId === (string) ($exists['account_id'] ?? '')) {
                    $accountId = null;   // unchanged — nothing to check or write
                } else {
                    if (!isset($validAccounts[$accountId])) {
                        $validAccounts[$accountId] = (bool) App\ExecuteRow(
                            "SELECT account_id FROM {$this->tableChartOfAccounts}
                             WHERE account_id = " . App\QuotedValue($accountId, DataType::STRING, 'DB') . "
                               AND status = 'Active' AND posting_allowed = TRUE",
                            'DB'
                        );
                    }
                    if (!$validAccounts[$accountId]) {
                        return "Line item #{$id}: chart of accounts entry '{$accountId}' does not exist or is not postable.";
                    }
                }
            }

            $fundId      = $item['type_of_fund_id'] ?? null;
            $componentId = $item['component_id'] ?? null;

            if ($fundId !== null && $fundId !== '') {
                if (!is_numeric($fundId)) {
                    return "Line item #{$id}: invalid type_of_fund_id.";
                }
                $fundId = (int) $fundId;
                if (!isset($validFunds[$fundId])) {
                    $validFunds[$fundId] = (bool) App\ExecuteRow(
                        "SELECT type_of_fund_id FROM {$this->tableTypeOfFund} WHERE type_of_fund_id = {$fundId} AND deleted_at IS NULL",
                        'DB'
                    );
                }
                if (!$validFunds[$fundId]) {
                    return "Line item #{$id}: type of fund #{$fundId} does not exist.";
                }
            } else {
                $fundId = null;
            }

            if ($componentId !== null && $componentId !== '') {
                if (!is_numeric($componentId)) {
                    return "Line item #{$id}: invalid component_id.";
                }
                $componentId = (int) $componentId;
                if (!isset($validComponents[$componentId])) {
                    $validComponents[$componentId] = (bool) App\ExecuteRow(
                        "SELECT component_type_id FROM {$this->tableComponentType} WHERE component_type_id = {$componentId} AND deleted_at IS NULL",
                        'DB'
                    );
                }
                if (!$validComponents[$componentId]) {
                    return "Line item #{$id}: component #{$componentId} does not exist.";
                }
            } else {
                $componentId = null;
            }

            // suggested_amount is OPTIONAL: NUMERIC(18,2), 0 up to 9,999,999,999,999,999.99.
            //   key absent        -> left unchanged
            //   key present, blank -> 0 (the column is NOT NULL DEFAULT 0, so "no suggested amount" = 0)
            //   key present, number -> that number
            $suggested = $item['suggested_amount'] ?? null;
            if ($suggested === null || $suggested === '') {
                $suggested = array_key_exists('suggested_amount', $item) ? '0' : null;
            } elseif (!is_numeric($suggested) || (float) $suggested < 0 || (float) $suggested >= 1e16) {
                return "Line item #{$id}: suggested_amount must be a number from 0 to 9,999,999,999,999,999.99.";
            }

            if ($requireAll && ($fundId === null || $componentId === null)) {
                return 'Type of Fund and Component are required for every line item.';
            }

            $setParts = [
                'type_of_fund_id = ' . ($fundId ?? 'NULL'),
                'component_id = ' . ($componentId ?? 'NULL'),
            ];
            if ($suggested !== null) {
                $setParts[] = 'suggested_amount = ' . App\QuotedValue($suggested, DataType::NUMBER, 'DB');
            }
            if ($accountId !== null) {
                $setParts[] = 'account_id = ' . App\QuotedValue($accountId, DataType::STRING, 'DB');
            }

            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE budget_line_item_id = {$id}",
                'DB'
            );
        }

        return null;
    }

    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            App\Execute("DELETE FROM {$this->table} WHERE budget_line_item_id = " . (int) $id, 'DB');

            return ['success' => true, 'message' => "Line item #{$id} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
