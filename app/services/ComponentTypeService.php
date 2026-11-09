<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * CRUD for fims.component_type (CAPITAL OUTLAY, OTHER-CAPITAL OUTLAY, COE).
 * Soft-deleted via deleted_at, same as ExpenseClassService.
 */
class ComponentTypeService
{
    private string $table = 'fims.component_type';

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /component_type/all, e.g. dropdowns)
    // Fetch with pagination (for /component_type/list) when $page is provided
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10, string $search = ''): array
    {
        try {
            $where = 'deleted_at IS NULL';
            if ($search !== '') {
                $s      = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where .= " AND name ILIKE {$s}";
            }

            if ($page === null) {
                $sql  = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY component_type_id ASC";
                $data = App\ExecuteRows($sql, 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", 'DB');
            $sql   = "SELECT * FROM {$this->table}
                      WHERE {$where}
                      ORDER BY component_type_id ASC
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

    // -----------------------------------------------------------------------
    // Fetch one by ID — /component_type/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id; // safe cast — no QuotedValue needed for integers
            $sql  = "SELECT * FROM {$this->table} WHERE component_type_id = {$id} AND deleted_at IS NULL";
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Component type #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Create — /component_type/add
    // -----------------------------------------------------------------------
    public function create(array $body): array
    {
        try {
            $name = trim((string) ($body['name'] ?? ''));

            $err = $this->validateName($name);
            if ($err) {
                return ['success' => false, 'message' => $err];
            }

            $sql = 'INSERT INTO ' . $this->table . ' (name)
                    VALUES (' . App\QuotedValue($name, DataType::STRING, 'DB') . ')
                    RETURNING component_type_id';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success'           => true,
                'message'           => 'Component type created successfully.',
                'component_type_id' => $row['component_type_id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /component_type/update/{id}
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $name = trim((string) ($body['name'] ?? ''));

            $err = $this->validateName($name, (int) $id);
            if ($err) {
                return ['success' => false, 'message' => $err];
            }

            $sql = "UPDATE {$this->table}
                    SET name = " . App\QuotedValue($name, DataType::STRING, 'DB') . ',
                        updated_at = CURRENT_TIMESTAMP
                    WHERE component_type_id = ' . (int) $id;

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Component type updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Delete (soft) — /component_type/delete/{id}
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $sql = "UPDATE {$this->table} SET deleted_at = CURRENT_TIMESTAMP WHERE component_type_id = " . (int) $id;
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Component type #{$id} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * name is required, max 50 chars (the column is VARCHAR(50)), and must not
     * already be used by another non-deleted row (the table has no UNIQUE
     * constraint, so duplicates are blocked here instead). $ignoreId excludes
     * the row being updated from the duplicate check.
     */
    private function validateName(string $name, ?int $ignoreId = null): ?string
    {
        if ($name === '') {
            return 'name is required.';
        }
        if (mb_strlen($name) > 50) {
            return 'name must be 50 characters or fewer.';
        }

        $sql = "SELECT component_type_id FROM {$this->table}
                WHERE deleted_at IS NULL AND LOWER(name) = LOWER(" . App\QuotedValue($name, DataType::STRING, 'DB') . ')'
            . ($ignoreId !== null ? " AND component_type_id <> {$ignoreId}" : '');

        if (App\ExecuteRow($sql, 'DB')) {
            return "A component type named '{$name}' already exists.";
        }

        return null;
    }
}
