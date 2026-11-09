<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * PositionManagementService
 * Backs the custom UAC "Position Management" page (app/pages/position-management.php):
 * creates and updates position categories and position templates directly,
 * without going through the PHPMaker-generated Add/Edit pages.
 */
class PositionManagementService
{
    public function createCategory($data)
    {
        try {
            if (!App\AllowAdd("positions")) {
                throw new \InvalidArgumentException("You do not have permission to add position categories");
            }

            $name = trim($data['category_name'] ?? '');
            if ($name === '') {
                throw new \InvalidArgumentException("Category name is required");
            }
            if (strlen($name) > 100) {
                throw new \InvalidArgumentException("Category name must be 100 characters or fewer");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM position_categories WHERE LOWER(category_name) = LOWER(" .
                App\QuotedValue($name, App\DataType::STRING, "DB") . ")",
                "DB"
            );
            if ($exists > 0) {
                throw new \InvalidArgumentException("A category with this name already exists");
            }

            $description = trim($data['description'] ?? '') ?: null;
            $isActive = array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true;

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO position_categories (category_name, description, is_active) VALUES (" .
                    App\QuotedValue($name, App\DataType::STRING, "DB") . ", " .
                    ($description !== null ? App\QuotedValue($description, App\DataType::STRING, "DB") : "NULL") . ", " .
                    App\QuotedValue($isActive, App\DataType::BOOLEAN, "DB") .
                    ")";
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Category created successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in PositionManagementService::createCategory: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function updateCategory($categoryId, $data)
    {
        try {
            if (!App\AllowEdit("positions")) {
                throw new \InvalidArgumentException("You do not have permission to edit position categories");
            }
            if (empty($categoryId) || !is_numeric($categoryId)) {
                throw new \InvalidArgumentException("A valid category is required");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM position_categories WHERE position_category_id = " .
                App\QuotedValue($categoryId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$exists) {
                throw new \InvalidArgumentException("Category not found");
            }

            $name = trim($data['category_name'] ?? '');
            if ($name === '') {
                throw new \InvalidArgumentException("Category name is required");
            }
            if (strlen($name) > 100) {
                throw new \InvalidArgumentException("Category name must be 100 characters or fewer");
            }

            $duplicate = App\ExecuteScalar(
                "SELECT COUNT(*) FROM position_categories WHERE LOWER(category_name) = LOWER(" .
                App\QuotedValue($name, App\DataType::STRING, "DB") . ") AND position_category_id <> " .
                App\QuotedValue($categoryId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if ($duplicate > 0) {
                throw new \InvalidArgumentException("A category with this name already exists");
            }

            $description = trim($data['description'] ?? '') ?: null;
            $isActive = array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true;

            App\Execute("BEGIN", "DB");
            try {
                $sql = "UPDATE position_categories SET " .
                    "category_name = " . App\QuotedValue($name, App\DataType::STRING, "DB") . ", " .
                    "description = " . ($description !== null ? App\QuotedValue($description, App\DataType::STRING, "DB") : "NULL") . ", " .
                    "is_active = " . App\QuotedValue($isActive, App\DataType::BOOLEAN, "DB") .
                    " WHERE position_category_id = " . App\QuotedValue($categoryId, App\DataType::NUMBER, "DB");
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Category updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in PositionManagementService::updateCategory: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    private function validatePositionFields($data, $excludeId = null)
    {
        $code = trim($data['position_code'] ?? '');
        $title = trim($data['position_title'] ?? '');
        if ($code === '' || $title === '') {
            throw new \InvalidArgumentException("Position code and title are required");
        }
        if (strlen($code) > 50) {
            throw new \InvalidArgumentException("Position code must be 50 characters or fewer");
        }
        if (strlen($title) > 200) {
            throw new \InvalidArgumentException("Position title must be 200 characters or fewer");
        }

        $duplicateSql = "SELECT COUNT(*) FROM positions WHERE LOWER(position_code) = LOWER(" .
            App\QuotedValue($code, App\DataType::STRING, "DB") . ")";
        if ($excludeId !== null) {
            $duplicateSql .= " AND position_id <> " . App\QuotedValue($excludeId, App\DataType::NUMBER, "DB");
        }
        if (App\ExecuteScalar($duplicateSql, "DB") > 0) {
            throw new \InvalidArgumentException("A position with this code already exists");
        }

        $categoryId = empty($data['position_category_id']) ? null : $data['position_category_id'];
        if ($categoryId !== null) {
            $categoryExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM position_categories WHERE position_category_id = " .
                App\QuotedValue($categoryId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$categoryExists) {
                throw new \InvalidArgumentException("Selected category was not found");
            }
        }

        $level = (isset($data['position_level']) && $data['position_level'] !== '') ? (int)$data['position_level'] : null;
        $salaryGrade = (isset($data['salary_grade']) && $data['salary_grade'] !== '') ? (int)$data['salary_grade'] : null;
        if ($salaryGrade !== null && ($salaryGrade < 1 || $salaryGrade > 33)) {
            throw new \InvalidArgumentException("Salary grade must be between 1 and 33");
        }
        $stepIncrement = (isset($data['step_increment']) && $data['step_increment'] !== '') ? (int)$data['step_increment'] : 1;
        if ($stepIncrement < 1 || $stepIncrement > 8) {
            throw new \InvalidArgumentException("Step increment must be between 1 and 8");
        }

        return [
            'position_code' => $code,
            'position_title' => $title,
            'position_category_id' => $categoryId,
            'position_level' => $level,
            'salary_grade' => $salaryGrade,
            'step_increment' => $stepIncrement,
            'job_description' => trim($data['job_description'] ?? '') ?: null,
            'qualification_standards' => trim($data['qualification_standards'] ?? '') ?: null,
            'is_active' => array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true,
        ];
    }

    public function createPosition($data)
    {
        try {
            if (!App\AllowAdd("positions")) {
                throw new \InvalidArgumentException("You do not have permission to add positions");
            }

            $fields = $this->validatePositionFields($data);

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO positions (
                        position_category_id, position_code, position_title, position_level,
                        salary_grade, step_increment, job_description, qualification_standards,
                        is_active, created_by
                    ) VALUES (" .
                    $nullableNumber($fields['position_category_id']) . ", " .
                    App\QuotedValue($fields['position_code'], App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($fields['position_title'], App\DataType::STRING, "DB") . ", " .
                    $nullableNumber($fields['position_level']) . ", " .
                    $nullableNumber($fields['salary_grade']) . ", " .
                    App\QuotedValue($fields['step_increment'], App\DataType::NUMBER, "DB") . ", " .
                    $nullableString($fields['job_description']) . ", " .
                    $nullableString($fields['qualification_standards']) . ", " .
                    App\QuotedValue($fields['is_active'], App\DataType::BOOLEAN, "DB") . ", " .
                    $nullableNumber(App\CurrentUserID() ?: null) .
                    ")";
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Position created successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in PositionManagementService::createPosition: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function updatePosition($positionId, $data)
    {
        try {
            if (!App\AllowEdit("positions")) {
                throw new \InvalidArgumentException("You do not have permission to edit positions");
            }
            if (empty($positionId) || !is_numeric($positionId)) {
                throw new \InvalidArgumentException("A valid position is required");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM positions WHERE position_id = " . App\QuotedValue($positionId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$exists) {
                throw new \InvalidArgumentException("Position not found");
            }

            $fields = $this->validatePositionFields($data, $positionId);

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            $setClauses = [
                "position_category_id = " . $nullableNumber($fields['position_category_id']),
                "position_code = " . App\QuotedValue($fields['position_code'], App\DataType::STRING, "DB"),
                "position_title = " . App\QuotedValue($fields['position_title'], App\DataType::STRING, "DB"),
                "position_level = " . $nullableNumber($fields['position_level']),
                "salary_grade = " . $nullableNumber($fields['salary_grade']),
                "step_increment = " . App\QuotedValue($fields['step_increment'], App\DataType::NUMBER, "DB"),
                "job_description = " . $nullableString($fields['job_description']),
                "qualification_standards = " . $nullableString($fields['qualification_standards']),
                "is_active = " . App\QuotedValue($fields['is_active'], App\DataType::BOOLEAN, "DB"),
                "updated_at = CURRENT_TIMESTAMP",
            ];

            App\Execute("BEGIN", "DB");
            try {
                $sql = "UPDATE positions SET " . implode(", ", $setClauses) .
                    " WHERE position_id = " . App\QuotedValue($positionId, App\DataType::NUMBER, "DB");
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Position updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in PositionManagementService::updatePosition: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }
}
