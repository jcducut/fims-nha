<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class ExpenseClassService
{
    private string $table = 'fims.expense_class';

    public function fetchAlls(?int $page = null, int $limit = 10, string $search = ''): array
    {
        try {
            $where = 'deleted_at IS NULL';
            if ($search !== '') {
                $s      = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where .= " AND (expense_class_code ILIKE {$s} OR expense_class_name ILIKE {$s})";
            }

            if ($page === null) {
                $sql  = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY expense_class_id ASC";
                $data = App\ExecuteRows($sql, 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", 'DB');
            $sql   = "SELECT * FROM {$this->table}
                      WHERE {$where}
                      ORDER BY expense_class_id ASC
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

    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id;
            $sql  = "SELECT * FROM {$this->table} WHERE expense_class_id = {$id} AND deleted_at IS NULL";
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Expense class #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code = trim((string) ($body['expense_class_code'] ?? ''));
            $name = trim((string) ($body['expense_class_name'] ?? ''));

            if ($code === '' || $name === '') {
                return ['success' => false, 'message' => 'expense_class_code and expense_class_name are required.'];
            }

            $sql = 'INSERT INTO ' . $this->table . ' (expense_class_code, expense_class_name)
                    VALUES (' . App\QuotedValue($code, DataType::STRING, 'DB') . ', ' . App\QuotedValue($name, DataType::STRING, 'DB') . ')
                    RETURNING expense_class_id';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success'          => true,
                'message'          => 'Expense class created successfully.',
                'expense_class_id' => $row['expense_class_id'] ?? null,
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

            $code = trim((string) ($body['expense_class_code'] ?? ''));
            $name = trim((string) ($body['expense_class_name'] ?? ''));

            $setParts = ['updated_at = CURRENT_TIMESTAMP'];
            if ($code !== '') {
                $setParts[] = 'expense_class_code = ' . App\QuotedValue($code, DataType::STRING, 'DB');
            }
            if ($name !== '') {
                $setParts[] = 'expense_class_name = ' . App\QuotedValue($name, DataType::STRING, 'DB');
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE expense_class_id = ' . (int) $id;

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Expense class updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            // Soft delete, matching shared.coe_category_type's deleted_at column.
            $sql = "UPDATE {$this->table} SET deleted_at = CURRENT_TIMESTAMP WHERE expense_class_id = " . (int) $id;
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Expense class #{$id} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
