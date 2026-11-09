<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class ChartOfAccountsService
{
    private string $table        = 'fims.chart_of_accounts';
    private string $tableRC      = 'fims.responsibility_code';
    private string $tableProject = 'fims.project';

    private array $allowedFields = [
        'account_code', 'account_title', 'account_type', 'parent_account_id',
        'posting_allowed', 'status', 'source_document', 'region_code', 'project_code',
    ];

    /**
     * Resolve the region (RCA code) a user is restricted to, for row-level
     * scoping of Chart of Accounts. Superadmin (user_level_id = -1) is
     * never scoped. A user with region_code = NULL is also unrestricted.
     */
    public function getUserScope(int $userId): array
    {
        try {
            $row = App\ExecuteRow(
                "SELECT u.user_level_id, u.region_code, rc.rc_name AS region_name
                 FROM uac.users u
                 LEFT JOIN {$this->tableRC} rc ON rc.rc_code = u.region_code
                 WHERE u.user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, 'DB'),
                'DB'
            );

            $isAdmin = !$row || App\SameText((string) $row['user_level_id'], '-1');

            return [
                'is_admin'    => $isAdmin,
                'region_code' => $isAdmin ? null : ($row['region_code'] ?? null),
                'region_name' => $isAdmin ? null : ($row['region_name'] ?? null),
            ];
        } catch (\Exception $e) {
            return ['is_admin' => true, 'region_code' => null, 'region_name' => null];
        }
    }

    public function fetchAlls(
        ?int $page = null,
        int $limit = 10,
        string $search = '',
        string $accountType = '',
        string $regionCode = '',
        string $projectCode = '',
        string $status = ''
    ): array {
        try {
            $where = ['1=1'];
            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(c.account_id ILIKE {$s} OR c.account_code ILIKE {$s} OR c.account_title ILIKE {$s})";
            }
            if ($accountType !== '') {
                $where[] = 'c.account_type = ' . App\QuotedValue($accountType, App\DataType::STRING, 'DB');
            }
            if ($regionCode !== '') {
                $where[] = 'c.region_code = ' . App\QuotedValue($regionCode, App\DataType::STRING, 'DB');
            }
            if ($projectCode !== '') {
                $where[] = 'c.project_code = ' . App\QuotedValue($projectCode, App\DataType::STRING, 'DB');
            }
            if ($status !== '') {
                $where[] = 'c.status = ' . App\QuotedValue($status, App\DataType::STRING, 'DB');
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT c.*, rc.rc_name AS region_name, pr.project_name,
                               p.account_code AS parent_account_code, p.account_title AS parent_account_title
                        FROM {$this->table} c
                        LEFT JOIN {$this->tableRC} rc ON rc.rc_code = c.region_code
                        LEFT JOIN {$this->tableProject} pr ON pr.project_code = c.project_code
                        LEFT JOIN {$this->table} p ON p.account_id = c.parent_account_id
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows("{$joinSql} ORDER BY c.account_code ASC", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $page   = max(1, $page);
            $limit  = max(1, $limit);
            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} c WHERE {$whereSql}", 'DB');
            $sql   = "{$joinSql}
                      ORDER BY c.account_code ASC
                      LIMIT {$limit} OFFSET {$offset}";
            $data  = App\ExecuteRows($sql, 'DB');

            return [
                'success'    => true,
                'data'       => $data ?: [],
                'pagination' => [
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

    /**
     * Postable accounts under a chart-of-accounts code prefix — e.g. '5-01' for
     * Personnel Services, '5-02' for Maintenance and Other Operating Expenses.
     * Used to populate the account picker on the Budget Request form once the
     * expense class resolves to a COA-driven allocation instead of a program/project.
     */
    public function fetchByPrefix(string $prefix): array
    {
        try {
            $p    = App\QuotedValue($prefix, DataType::STRING, 'DB');
            $pDash = App\QuotedValue($prefix . '-%', DataType::STRING, 'DB');

            $sql = "SELECT * FROM {$this->table}
                    WHERE (account_code = {$p} OR account_code LIKE {$pDash})
                      AND posting_allowed = TRUE
                      AND status = 'Active'
                    ORDER BY account_code ASC";

            $data = App\ExecuteRows($sql, 'DB');

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * All active, postable chart-of-accounts entries — used by the Budget
     * Request form's Account Code picker, which no longer restricts by
     * expense-class-derived prefix.
     */
    public function fetchPostable(string $search = ''): array
    {
        try {
            $where = "status = 'Active' AND posting_allowed = TRUE";
            if ($search !== '') {
                $s      = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where .= " AND (account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }

            $sql  = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY account_code ASC";
            $data = App\ExecuteRows($sql, 'DB');

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(string $id): array
    {
        try {
            $sql = "SELECT c.*, rc.rc_name AS region_name, pr.project_name,
                           p.account_code AS parent_account_code, p.account_title AS parent_account_title
                    FROM {$this->table} c
                    LEFT JOIN {$this->tableRC} rc ON rc.rc_code = c.region_code
                    LEFT JOIN {$this->tableProject} pr ON pr.project_code = c.project_code
                    LEFT JOIN {$this->table} p ON p.account_id = c.parent_account_id
                    WHERE c.account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB');

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Chart of accounts entry '{$id}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code  = trim((string) ($body['account_code'] ?? ''));
            $title = trim((string) ($body['account_title'] ?? ''));

            if ($code === '' || $title === '') {
                return ['success' => false, 'message' => 'account_code and account_title are required.'];
            }

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE account_code = " . App\QuotedValue($code, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Account code '{$code}' already exists."];
            }

            $parentId = trim((string) ($body['parent_account_id'] ?? ''));
            if ($parentId !== '') {
                $parentExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->table} WHERE account_id = " . App\QuotedValue($parentId, App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($parentExists === 0) {
                    return ['success' => false, 'message' => "Parent account '{$parentId}' does not exist."];
                }
            }

            $regionCode = trim((string) ($body['region_code'] ?? ''));
            if ($regionCode !== '') {
                $regionExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableRC} WHERE rc_code = " . App\QuotedValue($regionCode, App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($regionExists === 0) {
                    return ['success' => false, 'message' => "RCA code '{$regionCode}' does not exist."];
                }
            }

            $projectCode = trim((string) ($body['project_code'] ?? ''));
            if ($projectCode !== '') {
                $projectExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProject} WHERE project_code = " . App\QuotedValue($projectCode, App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($projectExists === 0) {
                    return ['success' => false, 'message' => "Project code '{$projectCode}' does not exist."];
                }
            }

            $accountId = $this->generateAccountId();

            $fields = array_intersect_key($body, array_flip($this->allowedFields));
            $fields['account_id'] = $accountId;
            // Normalize empty-string FKs/optional fields to NULL
            foreach (['parent_account_id', 'region_code', 'project_code', 'source_document'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }
            if (array_key_exists('posting_allowed', $fields)) {
                $fields['posting_allowed'] = App\ConvertToBool($fields['posting_allowed']) ? 'true' : 'false';
            }

            $columns = [];
            $values  = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[]  = $value === null
                    ? 'NULL'
                    : ($key === 'posting_allowed' ? $value : App\QuotedValue($value, App\DataType::STRING, 'DB'));
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Account created successfully.', 'data' => ['account_id' => $accountId]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            if (array_key_exists('parent_account_id', $fields)) {
                $parentId = trim((string) $fields['parent_account_id']);
                if ($parentId === $id) {
                    return ['success' => false, 'message' => 'An account cannot be its own parent.'];
                }
                if ($parentId !== '') {
                    $parentExists = (int) App\ExecuteScalar(
                        "SELECT COUNT(*) FROM {$this->table} WHERE account_id = " . App\QuotedValue($parentId, App\DataType::STRING, 'DB'),
                        'DB'
                    );
                    if ($parentExists === 0) {
                        return ['success' => false, 'message' => "Parent account '{$parentId}' does not exist."];
                    }
                }
            }

            if (!empty($fields['region_code'])) {
                $regionExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableRC} WHERE rc_code = " . App\QuotedValue($fields['region_code'], App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($regionExists === 0) {
                    return ['success' => false, 'message' => "RCA code '{$fields['region_code']}' does not exist."];
                }
            }

            if (!empty($fields['project_code'])) {
                $projectExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProject} WHERE project_code = " . App\QuotedValue($fields['project_code'], App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($projectExists === 0) {
                    return ['success' => false, 'message' => "Project code '{$fields['project_code']}' does not exist."];
                }
            }

            foreach (['parent_account_id', 'region_code', 'project_code', 'source_document'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }
            if (array_key_exists('posting_allowed', $fields)) {
                $fields['posting_allowed'] = App\ConvertToBool($fields['posting_allowed']) ? 'true' : 'false';
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . ($value === null
                    ? 'NULL'
                    : ($key === 'posting_allowed' ? $value : App\QuotedValue($value, App\DataType::STRING, 'DB')));
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE account_id = ' . App\QuotedValue($id, App\DataType::STRING, 'DB');

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Account updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function toggleStatus(string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $newStatus = App\SameText($check['data']['status'], 'Active') ? 'Inactive' : 'Active';

            App\Execute(
                "UPDATE {$this->table} SET status = " . App\QuotedValue($newStatus, App\DataType::STRING, 'DB') .
                ' WHERE account_id = ' . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );

            return ['success' => true, 'message' => "Account status set to {$newStatus}.", 'data' => ['status' => $newStatus]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $accountCode = $check['data']['account_code'];

            $childCount = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE parent_account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($childCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete: this account has child accounts under it.'];
            }

            $inUse = (int) App\ExecuteScalar(
                'SELECT COUNT(*) FROM fims.purchase_order_items WHERE account_code = ' . App\QuotedValue($accountCode, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($inUse > 0) {
                return ['success' => false, 'message' => 'Cannot delete: this account is referenced by existing purchase order items. Set it to Inactive instead.'];
            }

            $sql = "DELETE FROM {$this->table} WHERE account_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Account '{$id}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchRegions(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT rc_code AS region_code, rc_name AS region_name
                 FROM {$this->tableRC}
                 WHERE status = 'Active'
                 ORDER BY rc_name ASC",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateAccountId(): string
    {
        $next = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(account_id FROM 4) AS INTEGER)), 0) + 1
             FROM {$this->table} WHERE account_id ~ '^ACC[0-9]+$'",
            'DB'
        );

        return 'ACC' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
