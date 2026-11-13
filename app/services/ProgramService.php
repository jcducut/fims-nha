<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class ProgramService
{
    private string $table = 'fims.program';

    private array $allowedFields = ['program_code', 'program_name'];

    public function fetchAlls(?int $page = null, int $limit = 10, string $search = ''): array
    {
        try {
            $where = '1=1';
            if ($search !== '') {
                $s     = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where = "(program_code ILIKE {$s} OR program_name ILIKE {$s} OR \"group\" ILIKE {$s})";
            }

            if ($page === null) {
                $sql  = "SELECT * FROM {$this->table} WHERE {$where} ORDER BY program_code ASC";
                $data = App\ExecuteRows($sql, 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE {$where}", 'DB');

            $sql  = "SELECT * FROM {$this->table}
                     WHERE {$where}
                     ORDER BY program_code ASC
                     LIMIT {$limit} OFFSET {$offset}";
            $data = App\ExecuteRows($sql, 'DB');

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

    public function fetchByCode(string $code): array
    {
        try {
            $sql  = "SELECT * FROM {$this->table} WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB');
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Program '{$code}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Creates a program. A 3-digit program_code in $body is saved as typed (must be unused);
     * when it is empty the next sequential code comes from GlobalService::generateProgramCode().
     * The code actually saved is returned in data.program_code.
     */
    public function create(array $body, ?int $userId = null): array
    {
        try {
            $name = trim((string) ($body['program_name'] ?? ''));

            if ($name === '') {
                return ['success' => false, 'message' => 'program_name is required.'];
            }

            // A code typed in the form is used as-is (3 digits, must be unused); empty -> next sequential code.
            $manual = trim((string) ($body['program_code'] ?? ''));
            if ($manual !== '') {
                if (!preg_match('/^[0-9]{3}$/', $manual)) {
                    return ['success' => false, 'message' => 'Program code must be exactly 3 digits (e.g. 200).'];
                }
                $code = $manual;
            } else {
                $code = GlobalService::generateProgramCode($userId);
            }
            $body['program_code'] = $code;
            $body['program_name'] = $name;

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Program code '{$code}' already exists."];
            }

            $fields = array_intersect_key($body, array_flip($this->allowedFields));

            $columns = [];
            $values  = [];
            foreach ($fields as $key => $value) {
                $columns[] = App\QuotedName($key, 'DB');
                $values[]  = App\QuotedValue($value, DataType::STRING, 'DB');
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Program {$code} created successfully.", 'data' => ['program_code' => $code]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(string $code, array $body): array
    {
        try {
            $check = $this->fetchByCode($code);
            if (!$check['success']) {
                return $check;
            }

            // program_code is the primary key — not editable here.
            $fields = array_intersect_key($body, array_flip(['program_name']));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . App\QuotedValue($value, DataType::STRING, 'DB');
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE program_code = ' . App\QuotedValue($code, DataType::STRING, 'DB');

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Program updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(string $code): array
    {
        try {
            $check = $this->fetchByCode($code);
            if (!$check['success']) {
                return $check;
            }

            $inUse = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM fims.project WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                'DB'
            );
            if ($inUse > 0) {
                return ['success' => false, 'message' => "Cannot delete '{$code}' — {$inUse} project(s) still reference this program."];
            }

            $sql = "DELETE FROM {$this->table} WHERE program_code = " . App\QuotedValue($code, DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Program '{$code}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
