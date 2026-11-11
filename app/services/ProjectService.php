<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class ProjectService
{
    private string $table           = 'fims.project';
    private string $tableProgram    = 'fims.program';
    private string $tableSubProgram = 'fims.sub_program';

    private array $allowedFields = ['project_code', 'project_name', 'program_code', 'sub_program_code'];

    public function fetchAlls(?int $page = null, int $limit = 10, string $search = '', string $programCode = '', string $subProgramCode = ''): array
    {
        try {
            $where = ['1=1'];

            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(p.project_code ILIKE {$s} OR p.project_name ILIKE {$s})";
            }
            if ($programCode !== '') {
                $where[] = 'p.program_code = ' . App\QuotedValue($programCode, App\DataType::STRING, 'DB');
            }
            if ($subProgramCode !== '') {
                $where[] = 'p.sub_program_code = ' . App\QuotedValue($subProgramCode, App\DataType::STRING, 'DB');
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT p.*, pg.program_name, sp.sub_program_name
                        FROM {$this->table} p
                        LEFT JOIN {$this->tableProgram} pg ON pg.program_code = p.program_code
                        LEFT JOIN {$this->tableSubProgram} sp ON sp.sub_program_code = p.sub_program_code
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql . ' ORDER BY p.project_code ASC', 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} p WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows($joinSql . " ORDER BY p.project_code ASC LIMIT {$limit} OFFSET {$offset}", 'DB');

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
            $sql = "SELECT p.*, pg.program_name, sp.sub_program_name
                    FROM {$this->table} p
                    LEFT JOIN {$this->tableProgram} pg ON pg.program_code = p.program_code
                    LEFT JOIN {$this->tableSubProgram} sp ON sp.sub_program_code = p.sub_program_code
                    WHERE p.project_code = " . App\QuotedValue($code, DataType::STRING, 'DB');

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Project '{$code}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $code        = trim((string) ($body['project_code'] ?? ''));
            $name        = trim((string) ($body['project_name'] ?? ''));
            $program     = trim((string) ($body['program_code'] ?? ''));
            $subProgram  = trim((string) ($body['sub_program_code'] ?? ''));
            $fiscalYr    = isset($body['fiscal_yr']) && $body['fiscal_yr'] !== '' ? (int) $body['fiscal_yr'] : null;

            if ($name === '' || $program === '') {
                return ['success' => false, 'message' => 'project_name and program_code are required.'];
            }

            $programExists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($program, DataType::STRING, 'DB'),
                'DB'
            );
            if ($programExists === 0) {
                return ['success' => false, 'message' => "Program code '{$program}' does not exist."];
            }

            if ($subProgram !== '') {
                $subProgramRow = App\ExecuteRow(
                    "SELECT program_code FROM {$this->tableSubProgram} WHERE sub_program_code = " . App\QuotedValue($subProgram, DataType::STRING, 'DB'),
                    'DB'
                );
                if (!$subProgramRow) {
                    return ['success' => false, 'message' => "Sub-program '{$subProgram}' does not exist."];
                }
                if ($subProgramRow['program_code'] !== $program) {
                    return ['success' => false, 'message' => "Sub-program '{$subProgram}' does not belong to program '{$program}'."];
                }
            }

            if ($code === '') {
                $code = GlobalService::generateProjectCode($program, $subProgram, null, $fiscalYr);
            } else {
                $exists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->table} WHERE project_code = " . App\QuotedValue($code, DataType::STRING, 'DB'),
                    'DB'
                );
                if ($exists > 0) {
                    return ['success' => false, 'message' => "Project code '{$code}' already exists."];
                }
            }

            $fields = array_intersect_key(array_merge($body, ['project_code' => $code]), array_flip($this->allowedFields));

            $columns = [];
            $values  = [];
            foreach ($fields as $key => $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }
                $columns[] = App\QuotedName($key, 'DB');
                $values[]  = App\QuotedValue($value, DataType::STRING, 'DB');
            }

            $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ')
                    VALUES (' . implode(', ', $values) . ')';

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Project created successfully.', 'project_code' => $code];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Resolves a typed project name to a project_code — reuses an existing
     * project under the same program if one already has that name (exact,
     * case-insensitive), otherwise creates a new one. Used by the Budget
     * Request form's "type a name, search-or-create" project picker, so a
     * project can be created inline as part of saving the whole request
     * instead of a separate manual "add project" step.
     */
    public function findOrCreateByName(string $name, string $programCode, string $subProgramCode = '', ?int $fiscalYr = null): array
    {
        $name = trim($name);
        if ($name === '') {
            return ['success' => false, 'message' => 'Project name is required.'];
        }

        $existing = App\ExecuteRow(
            "SELECT project_code FROM {$this->table}
             WHERE program_code = " . App\QuotedValue($programCode, DataType::STRING, 'DB') . "
               AND LOWER(project_name) = LOWER(" . App\QuotedValue($name, DataType::STRING, 'DB') . ')',
            'DB'
        );
        if ($existing) {
            return ['success' => true, 'project_code' => $existing['project_code'], 'created' => false];
        }

        $result = $this->create([
            'project_name'     => $name,
            'program_code'     => $programCode,
            'sub_program_code' => $subProgramCode,
            'fiscal_yr'        => $fiscalYr,
        ]);
        if (!$result['success']) {
            return $result;
        }

        return ['success' => true, 'project_code' => $result['project_code'], 'created' => true];
    }

    public function update(string $code, array $body): array
    {
        try {
            $check = $this->fetchByCode($code);
            if (!$check['success']) {
                return $check;
            }

            // project_code is the primary key — not editable here.
            $fields = array_intersect_key($body, array_flip(['project_name', 'program_code', 'sub_program_code']));

            if (empty($fields)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $programCode = $fields['program_code'] ?? $check['data']['program_code'];

            if (!empty($fields['program_code'])) {
                $programExists = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . App\QuotedValue($fields['program_code'], DataType::STRING, 'DB'),
                    'DB'
                );
                if ($programExists === 0) {
                    return ['success' => false, 'message' => "Program code '{$fields['program_code']}' does not exist."];
                }
            }

            if (!empty($fields['sub_program_code'])) {
                $subProgramRow = App\ExecuteRow(
                    "SELECT program_code FROM {$this->tableSubProgram} WHERE sub_program_code = " . App\QuotedValue($fields['sub_program_code'], DataType::STRING, 'DB'),
                    'DB'
                );
                if (!$subProgramRow) {
                    return ['success' => false, 'message' => "Sub-program '{$fields['sub_program_code']}' does not exist."];
                }
                if ($subProgramRow['program_code'] !== $programCode) {
                    return ['success' => false, 'message' => "Sub-program '{$fields['sub_program_code']}' does not belong to program '{$programCode}'."];
                }
            }

            $setParts = [];
            foreach ($fields as $key => $value) {
                $setParts[] = App\QuotedName($key, 'DB') . ' = ' . App\QuotedValue($value, DataType::STRING, 'DB');
            }

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE project_code = ' . App\QuotedValue($code, DataType::STRING, 'DB');

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Project updated successfully.'];
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

            $sql = "DELETE FROM {$this->table} WHERE project_code = " . App\QuotedValue($code, DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Project '{$code}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
