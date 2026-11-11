<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * CRUD for fims.budget_category (Infrastructure, Goods and Services, Consultancy, …).
 * Soft-deleted via deleted_at, same as ComponentTypeService. Same method set and
 * response shape as the CRUDServices reference.
 */
class BudgetCategoryService
{
    private string $table = 'fims.budget_category';

    private const NAME_MAX = 50; // the column is VARCHAR(50)

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /budget_category/all, e.g. dropdowns)
    // Fetch with pagination (for /budget_category/list) when $page is provided
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10, string $search = ''): array
    {
        try {
            $where  = 'deleted_at IS NULL';
            $search = trim($search);
            if ($search !== '') {
                $s      = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where .= " AND name ILIKE {$s}";
            }

            if ($page === null) {
                $data = App\ExecuteRows("SELECT * FROM {$this->table} WHERE {$where} ORDER BY budget_category_id ASC", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $limit  = min(200, max(1, $limit));
            $page   = max(1, $page);
            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", 'DB');
            $data  = App\ExecuteRows(
                "SELECT * FROM {$this->table}
                 WHERE {$where}
                 ORDER BY budget_category_id ASC
                 LIMIT {$limit} OFFSET {$offset}",
                'DB'
            );

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
            App\LogError('BudgetCategoryService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load budget categories.'];
        }
    }

    // -----------------------------------------------------------------------
    // Fetch one by ID — /budget_category/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id; // safe cast — no QuotedValue needed for integers
            $data = App\ExecuteRow("SELECT * FROM {$this->table} WHERE budget_category_id = {$id} AND deleted_at IS NULL", 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Budget category #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('BudgetCategoryService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load budget category.'];
        }
    }

    // -----------------------------------------------------------------------
    // Create — /budget_category/add
    // -----------------------------------------------------------------------
    public function create(array $body): array
    {
        try {
            $name = $this->cleanName($body['name'] ?? null);

            if ($err = $this->validateName($name)) {
                return ['success' => false, 'message' => $err];
            }

            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (name) VALUES (" . App\QuotedValue($name, DataType::STRING, 'DB') . ') RETURNING budget_category_id',
                'DB'
            );

            return [
                'success'            => true,
                'message'            => 'Budget category created successfully.',
                'budget_category_id' => isset($row['budget_category_id']) ? (int) $row['budget_category_id'] : null,
            ];
        } catch (\Exception $e) {
            App\LogError('BudgetCategoryService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create budget category.'];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /budget_category/update/{id}   (name is the only writable field)
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
    {
        try {
            $id    = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            if (!array_key_exists('name', $body)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }
            $name = $this->cleanName($body['name']);

            if ($err = $this->validateName($name, $id)) {
                return ['success' => false, 'message' => $err];
            }

            App\Execute(
                "UPDATE {$this->table}
                 SET name = " . App\QuotedValue($name, DataType::STRING, 'DB') . ",
                     updated_at = CURRENT_TIMESTAMP
                 WHERE budget_category_id = {$id} AND deleted_at IS NULL",
                'DB'
            );

            return ['success' => true, 'message' => 'Budget category updated successfully.'];
        } catch (\Exception $e) {
            App\LogError('BudgetCategoryService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update budget category.'];
        }
    }

    // -----------------------------------------------------------------------
    // Delete (soft) — /budget_category/delete/{id}
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $id    = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            App\Execute(
                "UPDATE {$this->table} SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE budget_category_id = {$id}",
                'DB'
            );

            return ['success' => true, 'message' => "Budget category #{$id} deleted successfully."];
        } catch (\Exception $e) {
            App\LogError('BudgetCategoryService::delete ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete budget category.'];
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    /** Trimmed string; anything that is not a plain scalar (array, object) becomes ''. */
    private function cleanName(mixed $raw): string
    {
        return is_scalar($raw) ? trim((string) $raw) : '';
    }

    /**
     * name is required, max 50 chars, and must not already be used by another
     * non-deleted row (the table has no UNIQUE constraint — a deleted category's
     * name stays reusable — so duplicates are blocked here instead, ignoring
     * case). $ignoreId excludes the row being updated from the duplicate check.
     *
     * @return ?string error message, or null when the name is acceptable
     */
    private function validateName(string $name, ?int $ignoreId = null): ?string
    {
        if ($name === '') {
            return 'name is required.';
        }
        if (mb_strlen($name) > self::NAME_MAX) {
            return 'name must be ' . self::NAME_MAX . ' characters or fewer.';
        }

        $sql = "SELECT budget_category_id FROM {$this->table}
                WHERE deleted_at IS NULL AND LOWER(name) = LOWER(" . App\QuotedValue($name, DataType::STRING, 'DB') . ')'
            . ($ignoreId !== null ? " AND budget_category_id <> {$ignoreId}" : '');

        if (App\ExecuteRow($sql, 'DB')) {
            return "A budget category named '{$name}' already exists.";
        }

        return null;
    }
}
