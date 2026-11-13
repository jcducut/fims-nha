<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

// =============================================================================
// AgencyServices
// Table   : fims.agency
// Columns : agency_id (PK), name (varchar, NOT NULL, UNIQUE), is_active (bool,
//           default true), created_at (timestamptz, default now — not
//           client-settable)
// =============================================================================

class AgencyServices
{
    private string $table = 'fims.agency';

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /agency/all)
    // Fetch with pagination (for /agency/list) when $page is provided
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10): array
    {
        try {
            if ($page === null) {
                $sql = "SELECT * FROM {$this->table} ORDER BY name ASC";
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
                     ORDER BY name ASC
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
    // Fetch one by ID — /agency/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id = (int) $id; // safe cast — no QuotedValue needed for integers
            $sql = "SELECT * FROM {$this->table} WHERE agency_id = {$id}";

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Agency with ID {$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Create — /agency/add
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

            if (!isset($fields['name']) || trim((string) $fields['name']) === '') {
                return ['success' => false, 'message' => 'Agency Name (name) is required.'];
            }

            // Separate boolean fields from string fields
            $boolFields = ['is_active'];

            $columns = [];
            $placeholders = [];

            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');

                if (in_array($key, $boolFields)) {
                    $placeholders[] = $value ? 'TRUE' : 'FALSE';
                } else {
                    $placeholders[] = App\QuotedValue($value, DataType::STRING, 'DB');
                }
            }

            $colList = implode(', ', $columns);
            $valList = implode(', ', $placeholders);

            $sql = "INSERT INTO {$this->table} ({$colList}) VALUES ({$valList})
                    RETURNING agency_id";

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Agency created successfully.',
                'agency_id' => $row['agency_id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /agency/update/{id}
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

            if (array_key_exists('name', $fields) && trim((string) $fields['name']) === '') {
                return ['success' => false, 'message' => 'Agency Name (name) cannot be empty.'];
            }

            $boolFields = ['is_active'];
            $setParts = [];

            foreach ($fields as $key => $value) {
                $col = App\QuotedName($key, 'DB');

                if (in_array($key, $boolFields)) {
                    $setParts[] = "{$col} = ".($value ? 'TRUE' : 'FALSE');
                } else {
                    $setParts[] = "{$col} = ".App\QuotedValue($value, DataType::STRING, 'DB');
                }
            }

            $setClause = implode(', ', $setParts);

            $sql = "UPDATE {$this->table}
                    SET {$setClause}
                    WHERE agency_id = ".App\QuotedValue($id, DataType::NUMBER, 'DB');

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Agency updated successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Delete — /agency/delete/{id}
    // Hard delete — fims.agency has no deleted_at column to soft-delete with.
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $sql = "DELETE FROM {$this->table}
                    WHERE agency_id = ".App\QuotedValue($id, DataType::NUMBER, 'DB');

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => "Agency with ID {$id} deleted successfully.",
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
