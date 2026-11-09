<?php

namespace PHPMaker2024\FMD\Service;

use PHPMaker2024\FMD as App;

class CRUDServices
{
    private string $table = 'shared.programs';

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /crud/all)
    // Fetch with pagination (for /crud/list) when $page is provided
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10): array
    {
        try {
            if ($page === null) {
                $sql = "SELECT * FROM {$this->table} ORDER BY created_at ASC";
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
                     ORDER BY created_at ASC
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
    // Fetch one by ID — /crud/read/{id}
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id): array
    {
        try {
            $id = (int) $id; // safe cast — no QuotedValue needed for integers
            $sql = "SELECT * FROM {$this->table} WHERE program_id = {$id}";

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Program with ID {$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Create — /crud/add
    // -----------------------------------------------------------------------
    public function create(array $body): array
    {
        try {
            $allowedFields = [
                'program_code',
                'program_name',
                'program_category',
                'description',
                'start_date',
                'end_date',
                'status',
                'is_special_program',
                'created_by',
            ];

            $fields = array_intersect_key($body, array_flip($allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided.'];
            }

            // Separate boolean/numeric fields from string fields
            $boolFields = ['is_special_program'];
            $intFields = ['created_by'];

            $columns = [];
            $placeholders = [];

            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');

                if (in_array($key, $boolFields)) {
                    $placeholders[] = $value ? 'TRUE' : 'FALSE';
                } elseif (in_array($key, $intFields)) {
                    $placeholders[] = App\QuotedValue($value, App\DATATYPE_NUMBER, 'DB');
                } else {
                    $placeholders[] = App\QuotedValue($value, App\DATATYPE_STRING, 'DB');
                }
            }

            $colList = implode(', ', $columns);
            $valList = implode(', ', $placeholders);

            $sql = "INSERT INTO {$this->table} ({$colList}) VALUES ({$valList})";

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Program created successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Update — /crud/update/{id}
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $allowedFields = [
                'program_code',
                'program_name',
                'program_category',
                'description',
                'start_date',
                'end_date',
                'status',
                'is_special_program',
            ];

            $fields = array_intersect_key($body, array_flip($allowedFields));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $boolFields = ['is_special_program'];
            $setParts = [];

            foreach ($fields as $key => $value) {
                $col = App\QuotedName($key, 'DB');

                if (in_array($key, $boolFields)) {
                    $setParts[] = "{$col} = ".($value ? 'TRUE' : 'FALSE');
                } else {
                    $setParts[] = "{$col} = ".App\QuotedValue($value, App\DATATYPE_STRING, 'DB');
                }
            }

            $setClause = implode(', ', $setParts);

            $sql = "UPDATE {$this->table}
                    SET {$setClause}
                    WHERE program_id = ".App\QuotedValue($id, App\DATATYPE_NUMBER, 'DB');

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Program updated successfully.',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Delete — /crud/delete/{id}
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $sql = "DELETE FROM {$this->table}
                    WHERE program_id = ".App\QuotedValue($id, App\DATATYPE_NUMBER, 'DB');

            App\Execute($sql, 'DB');

            return [
                'success' => true,
                'message' => "Program with ID {$id} deleted successfully.",
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function test(): array
    {
        $result = sendNotification(
            type: 'info',
            subject: 'Test Notification from FMD',
            body: 'This is a test notification triggered from the FMD service layer.',
            link: '/FMD/',
            target: null, // null = broadcast, or put a specific user_id
            from_system: 'FMD'
        );

        if (isset($result['error'])) {
            return [
                'success' => false,
                'message' => 'Notification failed',
                'error' => $result['error'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Notification sent successfully!',
            'result' => $result,
        ];
    }
}
