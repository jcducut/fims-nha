<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

class ResponsibilityCenterService
{
    private string $table = 'fims.responsibility_code';

    private array $allowedFields = [
        'rc_code', 'rc_name', 'parent_rc_id', 'office_type',
        'status', 'source_document', 'is_budget_holding_rc',
    ];

    public function fetchAlls(
        ?int $page = null,
        int $limit = 10,
        string $search = '',
        string $officeType = '',
        string $status = ''
    ): array {
        try {
            $where = ['1=1'];

            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(c.rc_code ILIKE {$s} OR c.rc_name ILIKE {$s})";
            }
            if ($officeType !== '') {
                $where[] = 'c.office_type = ' . App\QuotedValue($officeType, App\DataType::STRING, 'DB');
            }
            if ($status !== '') {
                $where[] = 'c.status = ' . App\QuotedValue($status, App\DataType::STRING, 'DB');
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT c.*,
                               p.rc_code AS parent_rc_code, p.rc_name AS parent_rc_name
                        FROM {$this->table} c
                        LEFT JOIN {$this->table} p ON p.rc_id = c.parent_rc_id
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY c.rc_code ASC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} c WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY c.rc_code ASC LIMIT {$limit} OFFSET {$offset}", 'DB');

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

    public function fetchById(string $id): array
    {
        try {
            $sql = "SELECT c.*,
                           p.rc_code AS parent_rc_code, p.rc_name AS parent_rc_name
                    FROM {$this->table} c
                    LEFT JOIN {$this->table} p ON p.rc_id = c.parent_rc_id
                    WHERE c.rc_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB');

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Responsibility center '{$id}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code = trim((string) ($body['rc_code'] ?? ''));
            $name = trim((string) ($body['rc_name'] ?? ''));

            if ($code === '' || $name === '') {
                return ['success' => false, 'message' => 'rc_code and rc_name are required.'];
            }

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE rc_code = " . App\QuotedValue($code, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "RC code '{$code}' already exists."];
            }

            $parentId = trim((string) ($body['parent_rc_id'] ?? ''));
            if ($parentId !== '') {
                $parentExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->table} WHERE rc_id = " . App\QuotedValue($parentId, App\DataType::STRING, 'DB'),
                    'DB'
                );
                if ($parentExists === 0) {
                    return ['success' => false, 'message' => "Parent responsibility center '{$parentId}' does not exist."];
                }
            }

            $rcId = $this->generateRcId();

            $fields = array_intersect_key($body, array_flip($this->allowedFields));
            $fields['rc_id'] = $rcId;
            foreach (['parent_rc_id', 'office_type', 'source_document'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }
            if (array_key_exists('is_budget_holding_rc', $fields)) {
                $fields['is_budget_holding_rc'] = App\ConvertToBool($fields['is_budget_holding_rc']) ? 'true' : 'false';
            }

            $columns = [];
            $values  = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[]  = $value === null
                    ? 'NULL'
                    : ($key === 'is_budget_holding_rc' ? $value : App\QuotedValue($value, App\DataType::STRING, 'DB'));
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Responsibility center created successfully.', 'data' => ['rc_id' => $rcId]];
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

            if (array_key_exists('parent_rc_id', $fields)) {
                $parentId = trim((string) $fields['parent_rc_id']);
                if ($parentId === $id) {
                    return ['success' => false, 'message' => 'A responsibility center cannot be its own parent.'];
                }
                if ($parentId !== '') {
                    $parentExists = (int) App\ExecuteScalar(
                        "SELECT COUNT(*) FROM {$this->table} WHERE rc_id = " . App\QuotedValue($parentId, App\DataType::STRING, 'DB'),
                        'DB'
                    );
                    if ($parentExists === 0) {
                        return ['success' => false, 'message' => "Parent responsibility center '{$parentId}' does not exist."];
                    }
                }
            }

            foreach (['parent_rc_id', 'office_type', 'source_document'] as $k) {
                if (array_key_exists($k, $fields) && trim((string) $fields[$k]) === '') {
                    $fields[$k] = null;
                }
            }
            if (array_key_exists('is_budget_holding_rc', $fields)) {
                $fields['is_budget_holding_rc'] = App\ConvertToBool($fields['is_budget_holding_rc']) ? 'true' : 'false';
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . ($value === null
                    ? 'NULL'
                    : ($key === 'is_budget_holding_rc' ? $value : App\QuotedValue($value, App\DataType::STRING, 'DB')));
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE rc_id = ' . App\QuotedValue($id, App\DataType::STRING, 'DB');

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Responsibility center updated successfully.'];
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
                ' WHERE rc_id = ' . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );

            return ['success' => true, 'message' => "Responsibility center status set to {$newStatus}.", 'data' => ['status' => $newStatus]];
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

            $childCount = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE parent_rc_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB'),
                'DB'
            );
            if ($childCount > 0) {
                return ['success' => false, 'message' => 'Cannot delete: this responsibility center has child offices under it.'];
            }

            $sql = "DELETE FROM {$this->table} WHERE rc_id = " . App\QuotedValue($id, App\DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Responsibility center '{$id}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function generateRcId(): string
    {
        $next = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(rc_id FROM 4) AS INTEGER)), -1) + 1
             FROM {$this->table} WHERE rc_id ~ '^RC-[0-9]+$'",
            'DB'
        );

        return 'RC-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}
