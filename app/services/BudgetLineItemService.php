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
                "SELECT budget_reference_number FROM {$this->tableBudgetReq} WHERE budget_reference_number = " . (int) $budgetReferenceNumber,
                'DB'
            );
            if (!$header) {
                return ['success' => false, 'message' => "Budget request #{$budgetReferenceNumber} does not exist."];
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
                    VALUES (" . (int) $budgetReferenceNumber . ', '
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
     * Budget Processing: applies type_of_fund_id / component_id / suggestion_amount
     * to line items of one budget request. Does NOT open its own transaction — the caller
     * (BudgetReqService::endorse) wraps it together with its status change.
     * Returns an error message, or null once every item has been applied.
     *
     * @param array $items [['budget_line_item_id' => .., 'type_of_fund_id' => .., 'component_id' => .., 'suggestion_amount' => ..], ...]
     * @param bool  $requireAll when true, every item must carry a type of fund and a component (suggestion_amount stays optional)
     */
    public function applyProcessing(array $items, int $budgetReferenceNumber, bool $requireAll = false): ?string
    {
        if (empty($items)) {
            return 'No line items provided.';
        }

        // Each distinct lookup id is verified once (and must not be soft-deleted).
        $validFunds      = [];
        $validComponents = [];

        foreach ($items as $i => $item) {
            $n  = $i + 1;
            $id = (int) ($item['budget_line_item_id'] ?? 0);

            if ($id <= 0) {
                return "Item {$n}: budget_line_item_id is required.";
            }

            // Must be a line item of THIS budget request, not someone else's.
            $exists = App\ExecuteRow(
                "SELECT budget_line_item_id FROM {$this->table}
                 WHERE budget_line_item_id = {$id} AND budget_reference_number = {$budgetReferenceNumber}",
                'DB'
            );
            if (!$exists) {
                return "Line item #{$id} does not belong to budget request #{$budgetReferenceNumber}.";
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

            // suggestion_amount is OPTIONAL: NUMERIC(18,2), 0 up to 9,999,999,999,999,999.99.
            //   key absent        -> left unchanged
            //   key present, blank -> 0 (the column is NOT NULL DEFAULT 0, so "no suggestion" = 0)
            //   key present, number -> that number
            $suggestion = $item['suggestion_amount'] ?? null;
            if ($suggestion === null || $suggestion === '') {
                $suggestion = array_key_exists('suggestion_amount', $item) ? '0' : null;
            } elseif (!is_numeric($suggestion) || (float) $suggestion < 0 || (float) $suggestion >= 1e16) {
                return "Line item #{$id}: suggestion_amount must be a number from 0 to 9,999,999,999,999,999.99.";
            }

            if ($requireAll && ($fundId === null || $componentId === null)) {
                return 'Type of Fund and Component are required for every line item.';
            }

            $setParts = [
                'type_of_fund_id = ' . ($fundId ?? 'NULL'),
                'component_id = ' . ($componentId ?? 'NULL'),
            ];
            if ($suggestion !== null) {
                $setParts[] = 'suggestion_amount = ' . App\QuotedValue($suggestion, DataType::NUMBER, 'DB');
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
