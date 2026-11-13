<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

class NatureOfFundServices
{
    private string $table = 'fims.nature_of_fund';

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /nature-of-fund/all)
    // Fetch with pagination (for /nature-of-fund/list) when $page is provided
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10): array
    {
        try {
            if ($page === null) {
                $sql = "SELECT * FROM {$this->table} ORDER BY nature_of_fund_id ASC";
                $data = App\ExecuteRows($sql, 'DB');

                return [
                    'success' => true,
                    'data' => $data ?: [],
                ];
            }

            $offset = ($page - 1) * $limit;

            $countSql = "SELECT COUNT(*) FROM {$this->table}";
            $total = (int) App\ExecuteScalar($countSql, 'DB');

            $sql = "SELECT * FROM {$this->table}
                     ORDER BY nature_of_fund_id ASC
                     LIMIT {$limit} OFFSET {$offset}";
            $data = App\ExecuteRows($sql, 'DB');

            return [
                'success' => true,
                'data' => $data ?: [],
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Fetch one by ID — /nature-of-fund/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id = (int) $id;
            $sql = "SELECT * FROM {$this->table} WHERE nature_of_fund_id = {$id}";

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Nature of Fund with ID {$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Create — /nature-of-fund/add
    // -----------------------------------------------------------------------
    public function create(array $body): array
    {
        try {
            $allowedFields = [
                'name',
                'is_active',
            ];

            $fields = array_intersect_key($body, array_flip($allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided.'];
            }

            if (empty($fields['name'])) {
                return ['success' => false, 'message' => 'The "name" field is required.'];
            }

            $boolFields = ['is_active'];

            $columns = [];
            $placeholders = [];

            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');

                if (in_array($key, $boolFields)) {
                    $placeholders[] = $value ? 'TRUE' : 'FALSE';
                } else {
                    // String field — manual escaping (safe in service sub-namespace)
                    $placeholders[] = "'".str_replace("'", "''", (string) $value)."'";
                }
            }

            $colList = implode(', ', $columns);
            $valList = implode(', ', $placeholders);

            $sql = "INSERT INTO {$this->table} ({$colList}) VALUES ({$valList})";

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Nature of Fund created successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /nature-of-fund/update/{id}
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $allowedFields = [
                'name',
                'is_active',
            ];

            $fields = array_intersect_key($body, array_flip($allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $boolFields = ['is_active'];
            $setParts = [];

            foreach ($fields as $key => $value) {
                $col = App\QuotedName($key, 'DB');

                if (in_array($key, $boolFields)) {
                    $setParts[] = "{$col} = ".($value ? 'TRUE' : 'FALSE');
                } else {
                    $setParts[] = "{$col} = '".str_replace("'", "''", (string) $value)."'";
                }
            }

            $setClause = implode(', ', $setParts);
            $idInt = (int) $id;

            $sql = "UPDATE {$this->table}
                    SET {$setClause}
                    WHERE nature_of_fund_id = {$idInt}";

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Nature of Fund updated successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Delete — /nature-of-fund/delete/{id}
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $idInt = (int) $id;

            $sql = "DELETE FROM {$this->table} WHERE nature_of_fund_id = {$idInt}";

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => "Nature of Fund with ID {$id} deleted successfully.",
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
