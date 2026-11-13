<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

// =============================================================================
// Service : FundTrackingTypeServices
// Table   : fims.fund_tracking_type
// Purpose : Lookup table for fund tracking document classifications
//           (SARO, NCA, TRUST, OR). Referenced by
//           fims.fund_tracking.tracking_type_id -> fund_tracking_type_id.
// Notes   : Soft-delete via deleted_at — no hard DELETE. Uses
//           str_replace("'", "''", ...) for string escaping rather than
//           App\QuotedValue().
// =============================================================================
class FundTrackingTypeServices
{
    private string $table = 'fims.fund_tracking_type';

    private array $allowedFields = [
        'fund_tracking_code',
        'fund_tracking_name',
    ];

    private array $requiredFields = [
        'fund_tracking_code',
        'fund_tracking_name',
    ];

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /fund-tracking-type/all)
    // Fetch with pagination (for /fund-tracking-type/list) when $page is provided
    // Excludes soft-deleted rows unless $includeDeleted = true
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10, bool $includeDeleted = false): array
    {
        try {
            $where = $includeDeleted ? '' : 'WHERE deleted_at IS NULL';

            if ($page === null) {
                $sql = "SELECT * FROM {$this->table} {$where} ORDER BY created_at ASC, fund_tracking_type_id ASC";
                $data = App\ExecuteRows($sql, 'DB');

                return [
                    'success' => true,
                    'data' => $data ?: [],
                ];
            }

            $offset = ($page - 1) * $limit;

            $countSql = "SELECT COUNT(*) FROM {$this->table} {$where}";
            $total = (int) App\ExecuteScalar($countSql, 'DB');

            $sql = "SELECT * FROM {$this->table} {$where}
                     ORDER BY created_at ASC, fund_tracking_type_id ASC
                     LIMIT {$limit} OFFSET {$offset}";
            $data = App\ExecuteRows($sql, 'DB');

            return [
                'success' => true,
                'data' => $data ?: [],
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / max($limit, 1)),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Fetch one by ID — /fund-tracking-type/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id = (int) $id;
            $sql = "SELECT * FROM {$this->table}
                    WHERE fund_tracking_type_id = {$id} AND deleted_at IS NULL";

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return [
                    'success' => false,
                    'message' => "Fund Tracking Type with ID {$id} not found.",
                    'httpCode' => 404,
                ];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Create — /fund-tracking-type/add
    // -----------------------------------------------------------------------
    public function create(array $body): array
    {
        try {
            $errors = $this->validateRequired($body);
            if (!empty($errors)) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors, 'httpCode' => 422];
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided.'];
            }

            // Uniqueness check on fund_tracking_code
            $dupErrors = $this->validateUniqueCode($fields);
            if (!empty($dupErrors)) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $dupErrors, 'httpCode' => 422];
            }

            [$columns, $placeholders] = $this->buildInsertParts($fields);

            $colList = implode(', ', $columns);
            $valList = implode(', ', $placeholders);

            $sql = "INSERT INTO {$this->table} ({$colList}) VALUES ({$valList}) RETURNING fund_tracking_type_id";
            $newId = App\ExecuteScalar($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Fund Tracking Type created successfully.',
                'fund_tracking_type_id' => $newId,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /fund-tracking-type/update/{id}
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
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

            $dupErrors = $this->validateUniqueCode($fields, (int) $id);
            if (!empty($dupErrors)) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $dupErrors, 'httpCode' => 422];
            }

            $setParts = $this->buildUpdateParts($fields);
            $setParts[] = '"updated_at" = CURRENT_TIMESTAMP';
            $setClause = implode(', ', $setParts);

            $id = (int) $id;
            $sql = "UPDATE {$this->table} SET {$setClause} WHERE fund_tracking_type_id = {$id}";

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Fund Tracking Type updated successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Delete (soft) — /fund-tracking-type/delete/{id}
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $id = (int) $id;
            $sql = "UPDATE {$this->table}
                    SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP
                    WHERE fund_tracking_type_id = {$id}";

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => "Fund Tracking Type with ID {$id} deleted successfully.",
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Restore a soft-deleted row — /fund-tracking-type/restore/{id}
    // -----------------------------------------------------------------------
    public function restore(int|string $id): array
    {
        try {
            $id = (int) $id;
            $sql = "SELECT * FROM {$this->table} WHERE fund_tracking_type_id = {$id} AND deleted_at IS NOT NULL";
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return [
                    'success' => false,
                    'message' => "No deleted Fund Tracking Type found with ID {$id}.",
                    'httpCode' => 404,
                ];
            }

            $updateSql = "UPDATE {$this->table}
                          SET deleted_at = NULL, updated_at = CURRENT_TIMESTAMP
                          WHERE fund_tracking_type_id = {$id}";
            App\Execute($updateSql, 'DB');

            return [
                'success' => true,
                'message' => "Fund Tracking Type with ID {$id} restored successfully.",
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // =========================================================================
    //  VALIDATION HELPERS
    // =========================================================================

    private function validateRequired(array $body): array
    {
        $errors = [];

        foreach ($this->requiredFields as $field) {
            if (!isset($body[$field]) || trim((string) $body[$field]) === '') {
                $errors[] = "Field '{$field}' is required.";
            }
        }

        return $errors;
    }

    /**
     * Ensure fund_tracking_code is unique among non-deleted rows.
     * $excludeId — current record ID to exclude when updating.
     */
    private function validateUniqueCode(array $fields, ?int $excludeId = null): array
    {
        $errors = [];

        if (!empty($fields['fund_tracking_code'])) {
            $code = str_replace("'", "''", $fields['fund_tracking_code']);
            $sql = "SELECT COUNT(*) FROM {$this->table}
                    WHERE fund_tracking_code = '{$code}' AND deleted_at IS NULL";

            if ($excludeId !== null) {
                $sql .= " AND fund_tracking_type_id <> {$excludeId}";
            }

            if ((int) App\ExecuteScalar($sql, 'DB') > 0) {
                $errors[] = "Fund Tracking Code '{$fields['fund_tracking_code']}' already exists.";
            }
        }

        return $errors;
    }

    // =========================================================================
    //  SQL BUILDER HELPERS
    // =========================================================================

    private function buildInsertParts(array $fields): array
    {
        $columns = [];
        $placeholders = [];

        foreach ($fields as $key => $value) {
            $columns[] = App\QuotedName($key, 'DB');
            $placeholders[] = "'".str_replace("'", "''", (string) $value)."'";
        }

        return [$columns, $placeholders];
    }

    private function buildUpdateParts(array $fields): array
    {
        $setParts = [];

        foreach ($fields as $key => $value) {
            $col = App\QuotedName($key, 'DB');
            $setParts[] = "{$col} = '".str_replace("'", "''", (string) $value)."'";
        }

        return $setParts;
    }
}
