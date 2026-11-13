<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class SubProgramService
{
    private string $table        = 'fims.sub_program';
    private string $tableProgram = 'fims.program';

    public function fetchAlls(?int $page = null, int $limit = 10, string $search = '', string $programCode = ''): array
    {
        try {
            $where = ['1=1'];

            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', DataType::STRING, 'DB');
                $where[] = "(sp.sub_program_code ILIKE {$s} OR sp.sub_program_name ILIKE {$s})";
            }
            if ($programCode !== '') {
                $where[] = 'sp.program_code = ' . App\QuotedValue($programCode, DataType::STRING, 'DB');
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT sp.*, pg.program_name
                        FROM {$this->table} sp
                        LEFT JOIN {$this->tableProgram} pg ON pg.program_code = sp.program_code
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY sp.sub_program_code ASC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} sp WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY sp.sub_program_code ASC LIMIT {$limit} OFFSET {$offset}", 'DB');

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
            $sql = "SELECT sp.*, pg.program_name
                    FROM {$this->table} sp
                    LEFT JOIN {$this->tableProgram} pg ON pg.program_code = sp.program_code
                    WHERE sp.sub_program_code = " . App\QuotedValue($code, DataType::STRING, 'DB');

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Sub-program '{$code}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code    = trim((string) ($body['sub_program_code'] ?? ''));
            $name    = trim((string) ($body['sub_program_name'] ?? ''));
            $program = trim((string) ($body['program_code'] ?? ''));

            if ($code === '' || $name === '' || $program === '') {
                return ['success' => false, 'message' => 'sub_program_code, sub_program_name and program_code are required.'];
            }

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE sub_program_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Sub-program code '{$code}' already exists."];
            }

            $programExists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($program, DataType::STRING, 'DB'),
                'DB'
            );
            if ($programExists === 0) {
                return ['success' => false, 'message' => "Program code '{$program}' does not exist."];
            }

            $sql = 'INSERT INTO ' . $this->table . ' (sub_program_code, sub_program_name, program_code)
                    VALUES (' . App\QuotedValue($code, DataType::STRING, 'DB') . ', '
                        . App\QuotedValue($name, DataType::STRING, 'DB') . ', '
                        . App\QuotedValue($program, DataType::STRING, 'DB') . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Sub-program created successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Several sub-programs sharing one program_code, created together in one
     * call — the "+ Add Line Item" flow. Validates every line item before
     * inserting anything.
     */
    public function createBatch(string $programCode, array $subPrograms): array
    {
        try {
            $programCode = trim($programCode);
            if ($programCode === '') {
                return ['success' => false, 'message' => 'program_code is required.'];
            }
            if (empty($subPrograms)) {
                return ['success' => false, 'message' => 'At least one sub-program is required.'];
            }

            $programExists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($programCode, DataType::STRING, 'DB'),
                'DB'
            );
            if ($programExists === 0) {
                return ['success' => false, 'message' => "Program code '{$programCode}' does not exist."];
            }

            // Validate every row (including uniqueness among themselves) before inserting anything.
            $seenCodes = [];
            foreach ($subPrograms as $i => $row) {
                $label = 'Line item ' . ($i + 1) . ': ';
                $code  = trim((string) ($row['sub_program_code'] ?? ''));
                $name  = trim((string) ($row['sub_program_name'] ?? ''));

                if ($code === '') {
                    return ['success' => false, 'message' => $label . 'sub-program code is required.'];
                }
                if ($name === '') {
                    return ['success' => false, 'message' => $label . 'sub-program name is required.'];
                }
                if (isset($seenCodes[$code])) {
                    return ['success' => false, 'message' => $label . "sub-program code '{$code}' is repeated in this batch."];
                }
                $seenCodes[$code] = true;

                $exists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->table} WHERE sub_program_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                    'DB'
                );
                if ($exists > 0) {
                    return ['success' => false, 'message' => $label . "sub-program code '{$code}' already exists."];
                }
            }

            $createdCodes = [];
            foreach ($subPrograms as $row) {
                $result = $this->create([
                    'sub_program_code' => $row['sub_program_code'],
                    'sub_program_name' => $row['sub_program_name'],
                    'program_code'     => $programCode,
                ]);
                if (!$result['success']) {
                    return $result;
                }
                $createdCodes[] = trim((string) $row['sub_program_code']);
            }

            return [
                'success' => true,
                'message' => count($createdCodes) . ' sub-program(s) created successfully.',
                'created' => $createdCodes,
            ];
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

            // sub_program_code is the primary key — not editable here.
            $fields = array_intersect_key($body, array_flip(['sub_program_name', 'program_code']));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            if (!empty($fields['program_code'])) {
                $programExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($fields['program_code'], DataType::STRING, 'DB'),
                    'DB'
                );
                if ($programExists === 0) {
                    return ['success' => false, 'message' => "Program code '{$fields['program_code']}' does not exist."];
                }
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . App\QuotedValue($value, DataType::STRING, 'DB');
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE sub_program_code = ' . App\QuotedValue($code, DataType::STRING, 'DB');

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Sub-program updated successfully.'];
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

            $sql = "DELETE FROM {$this->table} WHERE sub_program_code = " . App\QuotedValue($code, DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Sub-program '{$code}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
