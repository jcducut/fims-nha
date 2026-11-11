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
    private string $tableProgram          = 'fims.program';
    private string $tableSubProgram       = 'fims.sub_program';
    private string $tableStatus           = 'fims.settings_status';

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
                           li.proposed_amount, li.particulars, st.name AS status_name
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
     * Budget Processing: applies type_of_fund_id / component_id / suggested_amount / program_source_code /
     * program_uses to line items of one budget request. Does NOT open its own transaction — the caller
     * (BudgetReqService::endorse) wraps it together with its status change.
     * Returns an error message, or null once every item has been applied.
     *
     * program_source_code and program_uses are OPTIONAL codes, handled the same way (see optionalCode()):
     *   program_source_code is a fims.program.program_code         (a program source IS a program)
     *   program_uses        is a fims.sub_program.sub_program_code (a program use IS a sub-program)
     *   key absent         -> left unchanged
     *   key present, blank -> cleared (NULL)
     *   key present, code  -> must be an existing program / sub-program
     *
     * @param array $items [['budget_line_item_id' => .., 'type_of_fund_id' => .., 'component_id' => .., 'suggested_amount' => .., 'program_source_code' => .., 'program_uses' => ..], ...]
     * @param bool  $requireAll when true, every item must carry a type of fund and a component (suggested_amount, program_source_code and program_uses stay optional)
     */
    public function applyProcessing(array $items, string $budgetReferenceNumber, bool $requireAll = false): ?string
    {
        if (empty($items)) {
            return 'No line items provided.';
        }

        // Each distinct lookup id is verified once (and must not be soft-deleted).
        $validFunds      = [];
        $validComponents = [];
        $validSources    = [];
        $validUses       = [];

        foreach ($items as $i => $item) {
            $n  = $i + 1;
            $id = (int) ($item['budget_line_item_id'] ?? 0);

            if ($id <= 0) {
                return "Item {$n}: budget_line_item_id is required.";
            }

            // Must be a line item of THIS budget request, not someone else's.
            $exists = App\ExecuteRow(
                "SELECT budget_line_item_id FROM {$this->table}
                 WHERE budget_line_item_id = {$id} AND budget_reference_number = " . App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB'),
                'DB'
            );
            if (!$exists) {
                return "Line item #{$id} does not belong to budget request {$budgetReferenceNumber}.";
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

            // program_source_code / program_uses are OPTIONAL (see the method comment): each is only written when its key is present.
            [$hasSource, $sourceCode, $error] = $this->optionalCode(
                $item, 'program_source_code', 'program source', $this->tableProgram, 'program_code', $id, $validSources
            );
            if ($error !== null) {
                return $error;
            }
            [$hasUses, $usesCode, $error] = $this->optionalCode(
                $item, 'program_uses', 'program use', $this->tableSubProgram, 'sub_program_code', $id, $validUses
            );
            if ($error !== null) {
                return $error;
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
            if ($hasSource) {
                $setParts[] = 'program_source_code = ' . ($sourceCode !== '' ? App\QuotedValue($sourceCode, DataType::STRING, 'DB') : 'NULL');
            }
            if ($hasUses) {
                $setParts[] = 'program_uses = ' . ($usesCode !== '' ? App\QuotedValue($usesCode, DataType::STRING, 'DB') : 'NULL');
            }

            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $setParts) . " WHERE budget_line_item_id = {$id}",
                'DB'
            );
        }

        return null;
    }

    /**
     * One OPTIONAL code column of a Budget Processing item (program_source_code, program_uses): a VARCHAR(64)
     * that must be the primary key of a row of $table when it is given.
     *   key absent         -> not written
     *   key present, blank -> written as NULL (cleared)
     *   key present, code  -> trimmed, at most 64 characters, and it must exist in $table.$column
     * $valid caches the lookups, so each distinct code is checked once per request.
     *
     * @param array<string,bool> $valid
     * @return array{0: bool, 1: string, 2: ?string} [written?, trimmed code ('' = clear), error message or null]
     */
    private function optionalCode(array $item, string $key, string $what, string $table, string $column, int $id, array &$valid): array
    {
        if (!array_key_exists($key, $item)) {
            return [false, '', null];
        }
        $value = $item[$key];
        if ($value !== null && !is_scalar($value)) {
            return [true, '', "Line item #{$id}: invalid {$key}."];
        }

        $code = $value === null ? '' : trim((string) $value);
        if ($code === '') {
            return [true, '', null];
        }
        if (mb_strlen($code) > 64) {
            return [true, $code, "Line item #{$id}: {$key} must be 64 characters or fewer."];
        }
        if (!isset($valid[$code])) {
            $valid[$code] = (bool) App\ExecuteRow(
                "SELECT {$column} FROM {$table} WHERE {$column} = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                'DB'
            );
        }
        if (!$valid[$code]) {
            return [true, $code, "Line item #{$id}: {$what} '{$code}' does not exist."];
        }

        return [true, $code, null];
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
