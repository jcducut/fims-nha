<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * DepartmentManagementService
 * Backs the custom UAC "Department Management" page (app/pages/department-management.php):
 * creates and updates departments directly, without going through the
 * PHPMaker-generated Add/Edit pages.
 */
class DepartmentManagementService
{
    private function validateCommon($data, $excludeId = null)
    {
        $name = trim($data['department_name'] ?? '');
        if ($name === '') {
            throw new \InvalidArgumentException("Department name is required");
        }
        if (strlen($name) > 150) {
            throw new \InvalidArgumentException("Department name must be 150 characters or fewer");
        }

        $duplicateSql = "SELECT COUNT(*) FROM departments WHERE LOWER(department_name) = LOWER(" .
            App\QuotedValue($name, App\DataType::STRING, "DB") . ")";
        if ($excludeId !== null) {
            $duplicateSql .= " AND department_id <> " . App\QuotedValue($excludeId, App\DataType::NUMBER, "DB");
        }
        if (App\ExecuteScalar($duplicateSql, "DB") > 0) {
            throw new \InvalidArgumentException("A department with this name already exists");
        }

        $parentId = empty($data['parent_department_id']) ? null : $data['parent_department_id'];
        if ($parentId !== null) {
            if ($excludeId !== null && (int)$parentId === (int)$excludeId) {
                throw new \InvalidArgumentException("A department cannot be its own parent");
            }
            $parentExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM departments WHERE department_id = " . App\QuotedValue($parentId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$parentExists) {
                throw new \InvalidArgumentException("Selected parent department was not found");
            }
            if ($excludeId !== null) {
                // Reject if the chosen parent is actually a descendant of this department -
                // that would create a cycle in the hierarchy.
                $isDescendant = App\ExecuteScalar(
                    "WITH RECURSIVE descendants AS (
                        SELECT department_id FROM departments WHERE parent_department_id = " .
                        App\QuotedValue($excludeId, App\DataType::NUMBER, "DB") . "
                        UNION ALL
                        SELECT d.department_id FROM departments d
                        JOIN descendants ds ON d.parent_department_id = ds.department_id
                    )
                    SELECT COUNT(*) FROM descendants WHERE department_id = " .
                    App\QuotedValue($parentId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if ($isDescendant > 0) {
                    throw new \InvalidArgumentException("Cannot set a descendant department as the parent — this would create a cycle");
                }
            }
        }

        $departmentType = trim($data['department_type'] ?? '') ?: null;
        $validTypes = ['MAIN', 'DIVISION', 'SECTION', 'UNIT', 'REGIONAL', 'DISTRICT'];
        if ($departmentType !== null && !in_array($departmentType, $validTypes, true)) {
            throw new \InvalidArgumentException("Invalid department type");
        }

        $hierarchyLevel = 1;
        if ($parentId !== null) {
            $parentLevel = (int)App\ExecuteScalar(
                "SELECT hierarchy_level FROM departments WHERE department_id = " . App\QuotedValue($parentId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            $hierarchyLevel = $parentLevel + 1;
        }

        return [
            'department_name' => $name,
            'department_acronym' => trim($data['department_acronym'] ?? '') ?: null,
            'department_code' => trim($data['department_code'] ?? '') ?: null,
            'description' => trim($data['description'] ?? '') ?: null,
            'department_type' => $departmentType,
            'parent_department_id' => $parentId,
            'hierarchy_level' => $hierarchyLevel,
            'is_budget_unit' => App\ConvertToBool($data['is_budget_unit'] ?? false),
            'is_active' => array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true,
        ];
    }

    public function createDepartment($data)
    {
        try {
            if (!App\AllowAdd("departments")) {
                throw new \InvalidArgumentException("You do not have permission to add departments");
            }

            $fields = $this->validateCommon($data);

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO departments (
                        department_name, department_acronym, department_code, description,
                        department_type, parent_department_id, hierarchy_level, is_budget_unit, is_active
                    ) VALUES (" .
                    App\QuotedValue($fields['department_name'], App\DataType::STRING, "DB") . ", " .
                    $nullableString($fields['department_acronym']) . ", " .
                    $nullableString($fields['department_code']) . ", " .
                    $nullableString($fields['description']) . ", " .
                    $nullableString($fields['department_type']) . ", " .
                    $nullableNumber($fields['parent_department_id']) . ", " .
                    App\QuotedValue($fields['hierarchy_level'], App\DataType::NUMBER, "DB") . ", " .
                    App\QuotedValue($fields['is_budget_unit'], App\DataType::BOOLEAN, "DB") . ", " .
                    App\QuotedValue($fields['is_active'], App\DataType::BOOLEAN, "DB") .
                    ")";
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Department created successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in DepartmentManagementService::createDepartment: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function updateDepartment($departmentId, $data)
    {
        try {
            if (!App\AllowEdit("departments")) {
                throw new \InvalidArgumentException("You do not have permission to edit departments");
            }
            if (empty($departmentId) || !is_numeric($departmentId)) {
                throw new \InvalidArgumentException("A valid department is required");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM departments WHERE department_id = " . App\QuotedValue($departmentId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$exists) {
                throw new \InvalidArgumentException("Department not found");
            }

            $fields = $this->validateCommon($data, $departmentId);

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            $setClauses = [
                "department_name = " . App\QuotedValue($fields['department_name'], App\DataType::STRING, "DB"),
                "department_acronym = " . $nullableString($fields['department_acronym']),
                "department_code = " . $nullableString($fields['department_code']),
                "description = " . $nullableString($fields['description']),
                "department_type = " . $nullableString($fields['department_type']),
                "parent_department_id = " . $nullableNumber($fields['parent_department_id']),
                "hierarchy_level = " . App\QuotedValue($fields['hierarchy_level'], App\DataType::NUMBER, "DB"),
                "is_budget_unit = " . App\QuotedValue($fields['is_budget_unit'], App\DataType::BOOLEAN, "DB"),
                "is_active = " . App\QuotedValue($fields['is_active'], App\DataType::BOOLEAN, "DB"),
                "updated_at = CURRENT_TIMESTAMP",
            ];

            App\Execute("BEGIN", "DB");
            try {
                $sql = "UPDATE departments SET " . implode(", ", $setClauses) .
                    " WHERE department_id = " . App\QuotedValue($departmentId, App\DataType::NUMBER, "DB");
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Department updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in DepartmentManagementService::updateDepartment: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }
}
