<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * UserAccessService
 * Backs the custom UAC "User Access" page (app/pages/user-access.php):
 * creates users and maintains user-level (role) permissions directly,
 * without going through the PHPMaker-generated Add/Edit pages.
 */
class UserAccessService
{
    public function createUser($data)
    {
        try {
            if (!App\AllowAdd("users")) {
                throw new \InvalidArgumentException("You do not have permission to add users");
            }

            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = (string)($data['password'] ?? '');
            $employeeId = trim($data['employee_id'] ?? '') ?: null;
            $firstName = trim($data['first_name'] ?? '') ?: null;
            $middleName = trim($data['middle_name'] ?? '') ?: null;
            $lastName = trim($data['last_name'] ?? '') ?: null;
            $suffix = trim($data['suffix'] ?? '') ?: null;
            $mobileNumber = trim($data['mobile_number'] ?? '') ?: null;
            $rcId = trim($data['rc_id'] ?? '') ?: null;
            $positionId = empty($data['position_id']) ? null : $data['position_id'];
            $isActive = array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true;

            if ($username === '' || $email === '' || $password === '') {
                throw new \InvalidArgumentException("Username, email and password are required");
            }
            if (strlen($password) < 6) {
                throw new \InvalidArgumentException("Password must be at least 6 characters");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid email address");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE username = " . App\QuotedValue($username, App\DataType::STRING, "DB") .
                " OR email = " . App\QuotedValue($email, App\DataType::STRING, "DB"),
                "DB"
            );
            if ($exists > 0) {
                throw new \InvalidArgumentException("Username or email already exists");
            }

            if ($rcId !== null) {
                $rcIdExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM fims.responsibility_code WHERE rc_id = " . App\QuotedValue($rcId, App\DataType::STRING, "DB"),
                    "DB"
                );
                if (!$rcIdExists) {
                    throw new \InvalidArgumentException("Selected RC code was not found");
                }
            }

            if ($positionId !== null) {
                $positionExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM positions WHERE position_id = " . App\QuotedValue($positionId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$positionExists) {
                    throw new \InvalidArgumentException("Selected position was not found");
                }
            }

            $passwordHash = App\EncryptPassword($password);

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO users (
                        username, email, password_hash, employee_id,
                        first_name, middle_name, last_name, suffix, mobile_number,
                        is_active, rc_id, position_id, date_created
                    ) VALUES (" .
                    App\QuotedValue($username, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($email, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($passwordHash, App\DataType::STRING, "DB") . ", " .
                    $nullableString($employeeId) . ", " .
                    $nullableString($firstName) . ", " .
                    $nullableString($middleName) . ", " .
                    $nullableString($lastName) . ", " .
                    $nullableString($suffix) . ", " .
                    $nullableString($mobileNumber) . ", " .
                    App\QuotedValue($isActive ? "1" : "0", App\DataType::BOOLEAN, "DB") . ", " .
                    $nullableString($rcId) . ", " .
                    $nullableNumber($positionId) .
                    ", CURRENT_TIMESTAMP)";
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'User created successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::createUser: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function updateUser($userId, $data)
    {
        try {
            if (!App\AllowEdit("users")) {
                throw new \InvalidArgumentException("You do not have permission to edit users");
            }
            if (empty($userId) || !is_numeric($userId)) {
                throw new \InvalidArgumentException("A valid user is required");
            }

            $userExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$userExists) {
                throw new \InvalidArgumentException("User not found");
            }

            $username = trim($data['username'] ?? '');
            $email = trim($data['email'] ?? '');
            $password = (string)($data['password'] ?? '');
            $employeeId = trim($data['employee_id'] ?? '') ?: null;
            $firstName = trim($data['first_name'] ?? '') ?: null;
            $middleName = trim($data['middle_name'] ?? '') ?: null;
            $lastName = trim($data['last_name'] ?? '') ?: null;
            $suffix = trim($data['suffix'] ?? '') ?: null;
            $mobileNumber = trim($data['mobile_number'] ?? '') ?: null;
            $rcId = trim($data['rc_id'] ?? '') ?: null;
            $positionId = empty($data['position_id']) ? null : $data['position_id'];
            $isActive = array_key_exists('is_active', $data) ? App\ConvertToBool($data['is_active']) : true;

            if ($username === '' || $email === '') {
                throw new \InvalidArgumentException("Username and email are required");
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException("Invalid email address");
            }
            if ($password !== '' && strlen($password) < 6) {
                throw new \InvalidArgumentException("Password must be at least 6 characters");
            }

            $duplicate = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE (username = " . App\QuotedValue($username, App\DataType::STRING, "DB") .
                " OR email = " . App\QuotedValue($email, App\DataType::STRING, "DB") . ") AND user_id <> " .
                App\QuotedValue($userId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if ($duplicate > 0) {
                throw new \InvalidArgumentException("Username or email already in use by another user");
            }

            if ($rcId !== null) {
                $rcIdExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM fims.responsibility_code WHERE rc_id = " . App\QuotedValue($rcId, App\DataType::STRING, "DB"),
                    "DB"
                );
                if (!$rcIdExists) {
                    throw new \InvalidArgumentException("Selected RC code was not found");
                }
            }

            if ($positionId !== null) {
                $positionExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM positions WHERE position_id = " . App\QuotedValue($positionId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$positionExists) {
                    throw new \InvalidArgumentException("Selected position was not found");
                }
            }

            $nullableString = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::STRING, "DB") : "NULL";
            };
            $nullableNumber = function ($value) {
                return $value !== null ? App\QuotedValue($value, App\DataType::NUMBER, "DB") : "NULL";
            };

            $setClauses = [
                "username = " . App\QuotedValue($username, App\DataType::STRING, "DB"),
                "email = " . App\QuotedValue($email, App\DataType::STRING, "DB"),
                "employee_id = " . $nullableString($employeeId),
                "first_name = " . $nullableString($firstName),
                "middle_name = " . $nullableString($middleName),
                "last_name = " . $nullableString($lastName),
                "suffix = " . $nullableString($suffix),
                "mobile_number = " . $nullableString($mobileNumber),
                "is_active = " . App\QuotedValue($isActive ? "1" : "0", App\DataType::BOOLEAN, "DB"),
                "rc_id = " . $nullableString($rcId),
                "position_id = " . $nullableNumber($positionId),
            ];
            if ($password !== '') {
                $setClauses[] = "password_hash = " . App\QuotedValue(App\EncryptPassword($password), App\DataType::STRING, "DB");
            }

            App\Execute("BEGIN", "DB");
            try {
                $sql = "UPDATE users SET " . implode(", ", $setClauses) .
                    " WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB");
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'User updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::updateUser: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function savePermissions($userLevelId, $permissions)
    {
        try {
            if (!App\AllowAdd("user_level_permissions") && !App\AllowEdit("user_level_permissions")) {
                throw new \InvalidArgumentException("You do not have permission to manage permissions");
            }
            if (empty($userLevelId) || !is_numeric($userLevelId)) {
                throw new \InvalidArgumentException("A valid user level is required");
            }
            if (!is_array($permissions) || empty($permissions)) {
                throw new \InvalidArgumentException("No permissions supplied");
            }

            $levelExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$levelExists) {
                throw new \InvalidArgumentException("User level not found");
            }

            App\Execute("BEGIN", "DB");
            try {
                foreach ($permissions as $perm) {
                    $tableName = trim($perm['table_name'] ?? '');
                    $fullKey = trim($perm['full_key'] ?? '') ?: $tableName;
                    if ($tableName === '') {
                        continue;
                    }
                    // Bitmask per PHPMaker's Allow enum (src/Allow.php):
                    // ADD=1, DELETE=2, EDIT=4, LIST=8, VIEW=32, SEARCH=64, LOOKUP=256, EXPORT=1024
                    $bitmask = (!empty($perm['list']) ? 8 : 0)
                        | (!empty($perm['view']) ? 32 : 0)
                        | (!empty($perm['add']) ? 1 : 0)
                        | (!empty($perm['edit']) ? 4 : 0)
                        | (!empty($perm['delete']) ? 2 : 0)
                        | (!empty($perm['search']) ? 64 : 0)
                        | (!empty($perm['export']) ? 1024 : 0)
                        | (!empty($perm['lookup']) ? 256 : 0);

                    // PHPMaker may have stored this row under the plain name or its own
                    // "{ProjectID}name" key (see models/UserLevelsAdd.php) - find whichever
                    // already exists so we UPDATE it instead of inserting a duplicate.
                    $existingKey = App\ExecuteScalar(
                        "SELECT table_name FROM user_level_permissions WHERE user_level_id = " .
                        App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") . " AND table_name IN (" .
                        App\QuotedValue($tableName, App\DataType::STRING, "DB") . ", " .
                        App\QuotedValue($fullKey, App\DataType::STRING, "DB") . ") LIMIT 1",
                        "DB"
                    );
                    $keyToWrite = $existingKey ?: $fullKey;

                    $sql = "INSERT INTO user_level_permissions (user_level_id, table_name, permission) VALUES (" .
                        App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") . ", " .
                        App\QuotedValue($keyToWrite, App\DataType::STRING, "DB") . ", " .
                        App\QuotedValue($bitmask, App\DataType::NUMBER, "DB") . ")" .
                        " ON CONFLICT (user_level_id, table_name) DO UPDATE SET permission = EXCLUDED.permission";
                    App\Execute($sql, "DB");
                }
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Permissions saved successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::savePermissions: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function syncPermissionModules()
    {
        try {
            if (!App\AllowAdd("user_level_permissions") && !App\AllowEdit("user_level_permissions")) {
                throw new \InvalidArgumentException("You do not have permission to manage permissions");
            }

            $registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];
            if (empty($registryTables)) {
                throw new \InvalidArgumentException("PHPMaker table registry is unavailable");
            }

            $userLevels = App\ExecuteRowsAssociative("SELECT user_level_id FROM user_levels", "DB");
            $existingRows = App\ExecuteRowsAssociative("SELECT user_level_id, table_name FROM user_level_permissions", "DB");

            $existingKeys = [];
            foreach ($existingRows as $row) {
                $existingKeys[$row['user_level_id'] . '|' . $row['table_name']] = true;
            }

            $inserted = 0;
            App\Execute("BEGIN", "DB");
            try {
                foreach ($userLevels as $level) {
                    $levelId = $level['user_level_id'];
                    foreach ($registryTables as $table) {
                        $plainKey = $table[0];
                        $fullKey = $table[4] . $table[0];
                        $alreadyExists = isset($existingKeys[$levelId . '|' . $plainKey])
                            || isset($existingKeys[$levelId . '|' . $fullKey]);
                        if ($alreadyExists) {
                            continue;
                        }

                        $sql = "INSERT INTO user_level_permissions (user_level_id, table_name, permission) VALUES (" .
                            App\QuotedValue($levelId, App\DataType::NUMBER, "DB") . ", " .
                            App\QuotedValue($fullKey, App\DataType::STRING, "DB") . ", 0)" .
                            " ON CONFLICT (user_level_id, table_name) DO NOTHING";
                        App\Execute($sql, "DB");
                        $inserted++;
                    }
                }
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            $message = $inserted > 0
                ? "Sync complete. {$inserted} missing module permission row(s) added."
                : "Already in sync — no missing modules found.";
            return json_encode(['success' => true, 'message' => $message, 'inserted' => $inserted]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::syncPermissionModules: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function renameModuleCaption($data)
    {
        try {
            if (!App\AllowAdd("user_level_permissions") && !App\AllowEdit("user_level_permissions")) {
                throw new \InvalidArgumentException("You do not have permission to manage permissions");
            }

            $tableName = trim($data['table_name'] ?? '');
            $caption = trim($data['caption'] ?? '');

            if ($tableName === '') {
                throw new \InvalidArgumentException("A module is required");
            }
            if ($caption === '') {
                throw new \InvalidArgumentException("Caption is required");
            }
            if (strlen($caption) > 150) {
                throw new \InvalidArgumentException("Caption must be 150 characters or fewer");
            }

            App\Execute("BEGIN", "DB");
            try {
                $this->ensureModuleCaptionsTable();

                $sql = "INSERT INTO permission_module_captions (table_name, caption) VALUES (" .
                    App\QuotedValue($tableName, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($caption, App\DataType::STRING, "DB") . ")" .
                    " ON CONFLICT (table_name) DO UPDATE SET caption = EXCLUDED.caption, updated_at = CURRENT_TIMESTAMP";
                App\Execute($sql, "DB");

                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            // Best-effort: also relabel the live sidebar entry, if this module
            // has one. Separate from the DB write above on purpose — a module
            // with no sidebar item (most child/detail tables) should still get
            // its Permissions-matrix caption renamed even though there's no
            // menu entry to sync.
            $menuSynced = $this->syncMenuCaption($tableName, $caption);

            return json_encode([
                'success' => true,
                'message' => $menuSynced
                    ? 'Module caption updated successfully'
                    : 'Module caption updated successfully (no matching sidebar item — matrix label only)',
            ]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::renameModuleCaption: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    // Finds the sidebar menu id whose AllowListMenu('{FIMS}...') security key
    // (in views/menu.php) matches this module's table_name, then patches that
    // menu id's MenuText phrase directly in lang/FIMS.en-US.xml — the actual
    // source views/menu.php reads via $Language->menuPhrase() to build the
    // sidebar. Language::$UseCache is off in this project, so the change is
    // picked up on the very next request with no cache to clear. Returns
    // false (without throwing) if there's no matching sidebar item, or if the
    // menu/language files can't be read/parsed — this sync is a bonus on top
    // of the permission_module_captions rename, never a reason to fail it.
    // Wrapped in its own try/catch so any failure here (malformed XML,
    // unreadable files, a DOM exception) can never bubble up and make
    // renameModuleCaption() report failure after its DB commit already
    // succeeded — this sync is strictly a bonus on top of that rename.
    private function syncMenuCaption($tableName, $caption)
    {
        try {
            $menuFile = __DIR__ . '/../../views/menu.php';
            if (!is_file($menuFile)) {
                return false;
            }
            $source = file_get_contents($menuFile);
            if ($source === false || !preg_match_all("/addMenuItem\\((\\d+),.*?AllowListMenu\\('([^']+)'\\)/", $source, $matches, PREG_SET_ORDER)) {
                return false;
            }
            // menu.php's AllowListMenu() keys carry the registry's "{FIMS}" project
            // prefix (table[4] . table[0] in $USER_LEVEL_TABLES), but the
            // table_name this method receives is always the bare page/table name
            // (allModules in user-access.php builds it from table[0] alone — see
            // that file's $allModules loop) — strip any leading "{...}" before
            // comparing, rather than hardcoding the "{FIMS}" prefix here.
            $menuId = null;
            foreach ($matches as $m) {
                $bareKey = preg_replace('/^\{[^}]*\}/', '', $m[2]);
                if ($bareKey === $tableName) {
                    $menuId = (int) $m[1];
                    break;
                }
            }
            if ($menuId === null) {
                return false;
            }

            $langFile = rtrim(App\Config("LANGUAGE_FOLDER"), '/\\') . '/FIMS.en-US.xml';
            if (!is_file($langFile) || !is_writable($langFile)) {
                return false;
            }

            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = true;
            $dom->formatOutput = false;
            if (!@$dom->load($langFile)) {
                return false;
            }
            $xpath = new \DOMXPath($dom);
            $phraseNode = $xpath->query("/ew-language/project/menu[@id='{$menuId}']/phrase[@id='menutext']")->item(0);
            if (!$phraseNode) {
                return false;
            }
            $phraseNode->setAttribute('value', $caption);

            // saveXML($dom->documentElement) rather than $dom->save($langFile) —
            // the latter always prepends an XML prolog line that the original
            // generated file doesn't have, which would turn every rename into
            // a whole-file diff instead of a single changed attribute.
            $xml = $dom->saveXML($dom->documentElement);

            return $xml !== false && file_put_contents($langFile, $xml) !== false;
        } catch (\Throwable $e) {
            App\LogError("UserAccessService::syncMenuCaption (non-fatal) failed: " . $e->getMessage());
            return false;
        }
    }

    // Defensive: create/upgrade the table if the migration (_assets/sql/00 - uac/02 - alter_table/
    // 04 - permission_module_captions.sql) hasn't been run yet. Must be called inside a transaction.
    private function ensureModuleCaptionsTable()
    {
        App\Execute(
            "CREATE TABLE IF NOT EXISTS permission_module_captions (
                table_name varchar(100) NOT NULL PRIMARY KEY,
                caption varchar(150) NOT NULL,
                is_hidden boolean NOT NULL DEFAULT FALSE,
                updated_at timestamptz(6) NOT NULL DEFAULT CURRENT_TIMESTAMP
            )",
            "DB"
        );
        App\Execute("ALTER TABLE permission_module_captions ADD COLUMN IF NOT EXISTS is_hidden boolean NOT NULL DEFAULT FALSE", "DB");
    }

    public function setModuleHidden($data)
    {
        try {
            if (!App\AllowAdd("user_level_permissions") && !App\AllowEdit("user_level_permissions")) {
                throw new \InvalidArgumentException("You do not have permission to manage permissions");
            }

            $tableName = trim($data['table_name'] ?? '');
            $caption = trim($data['caption'] ?? '');
            $isHidden = App\ConvertToBool($data['is_hidden'] ?? false);

            if ($tableName === '') {
                throw new \InvalidArgumentException("A module is required");
            }
            if ($caption === '') {
                throw new \InvalidArgumentException("Caption is required");
            }

            App\Execute("BEGIN", "DB");
            try {
                $this->ensureModuleCaptionsTable();

                $sql = "INSERT INTO permission_module_captions (table_name, caption, is_hidden) VALUES (" .
                    App\QuotedValue($tableName, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($caption, App\DataType::STRING, "DB") . ", " .
                    App\QuotedValue($isHidden ? "1" : "0", App\DataType::BOOLEAN, "DB") . ")" .
                    " ON CONFLICT (table_name) DO UPDATE SET is_hidden = EXCLUDED.is_hidden, updated_at = CURRENT_TIMESTAMP";
                App\Execute($sql, "DB");

                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode([
                'success' => true,
                'message' => $isHidden ? 'Module removed from the list' : 'Module restored',
            ]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::setModuleHidden: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function createUserLevel($data)
    {
        try {
            if (!App\AllowAdd("user_levels")) {
                throw new \InvalidArgumentException("You do not have permission to create user levels");
            }

            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '') ?: null;
            $systemId = empty($data['system_id']) ? null : $data['system_id'];

            if ($name === '') {
                throw new \InvalidArgumentException("Name is required");
            }
            if (strlen($name) > 50) {
                throw new \InvalidArgumentException("Name must be 50 characters or fewer");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE LOWER(name) = LOWER(" .
                App\QuotedValue($name, App\DataType::STRING, "DB") . ")",
                "DB"
            );
            if ($exists > 0) {
                throw new \InvalidArgumentException("A user level with this name already exists");
            }

            if ($systemId !== null) {
                $systemExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM systems WHERE system_id = " . App\QuotedValue($systemId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$systemExists) {
                    throw new \InvalidArgumentException("Selected system was not found");
                }
            }

            // user_levels has no ID sequence (PHPMaker treats -2/-1/0 as reserved:
            // Anonymous/Administrator/Default) — pick the next free positive ID ourselves.
            $newLevelId = (int)App\ExecuteScalar(
                "SELECT GREATEST(1, COALESCE(MAX(user_level_id), 0) + 1) FROM user_levels",
                "DB"
            );

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO user_levels (user_level_id, name, description, system_id) VALUES (" .
                    App\QuotedValue($newLevelId, App\DataType::NUMBER, "DB") . ", " .
                    App\QuotedValue($name, App\DataType::STRING, "DB") . ", " .
                    ($description !== null ? App\QuotedValue($description, App\DataType::STRING, "DB") : "NULL") . ", " .
                    ($systemId !== null ? App\QuotedValue($systemId, App\DataType::NUMBER, "DB") : "NULL") .
                    ")";
                App\Execute($sql, "DB");

                // Seed a zero-permission row for every known module, mirroring PHPMaker's
                // own UserLevelsAdd behavior (models/UserLevelsAdd.php) so the new role
                // shows up fully in the Permissions Management matrix ready to configure.
                $registryTables = $GLOBALS["USER_LEVEL_TABLES"] ?? [];
                foreach ($registryTables as $table) {
                    $fullKey = $table[4] . $table[0];
                    $sql = "INSERT INTO user_level_permissions (user_level_id, table_name, permission) VALUES (" .
                        App\QuotedValue($newLevelId, App\DataType::NUMBER, "DB") . ", " .
                        App\QuotedValue($fullKey, App\DataType::STRING, "DB") . ", 0)" .
                        " ON CONFLICT (user_level_id, table_name) DO NOTHING";
                    App\Execute($sql, "DB");
                }

                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'User level created successfully', 'user_level_id' => $newLevelId]);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::createUserLevel: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function updateUserLevel($userLevelId, $data)
    {
        try {
            if (!App\AllowEdit("user_levels")) {
                throw new \InvalidArgumentException("You do not have permission to edit user levels");
            }
            if (empty($userLevelId) || !is_numeric($userLevelId)) {
                throw new \InvalidArgumentException("A valid user level is required");
            }

            $exists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$exists) {
                throw new \InvalidArgumentException("User level not found");
            }

            $name = trim($data['name'] ?? '');
            $description = trim($data['description'] ?? '') ?: null;
            $systemId = empty($data['system_id']) ? null : $data['system_id'];

            if ($name === '') {
                throw new \InvalidArgumentException("Name is required");
            }
            if (strlen($name) > 50) {
                throw new \InvalidArgumentException("Name must be 50 characters or fewer");
            }

            $duplicate = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE LOWER(name) = LOWER(" .
                App\QuotedValue($name, App\DataType::STRING, "DB") . ") AND user_level_id <> " .
                App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if ($duplicate > 0) {
                throw new \InvalidArgumentException("A user level with this name already exists");
            }

            if ($systemId !== null) {
                $systemExists = App\ExecuteScalar(
                    "SELECT COUNT(*) FROM systems WHERE system_id = " . App\QuotedValue($systemId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                if (!$systemExists) {
                    throw new \InvalidArgumentException("Selected system was not found");
                }
            }

            App\Execute("BEGIN", "DB");
            try {
                $sql = "UPDATE user_levels SET " .
                    "name = " . App\QuotedValue($name, App\DataType::STRING, "DB") . ", " .
                    "description = " . ($description !== null ? App\QuotedValue($description, App\DataType::STRING, "DB") : "NULL") . ", " .
                    "system_id = " . ($systemId !== null ? App\QuotedValue($systemId, App\DataType::NUMBER, "DB") : "NULL") .
                    " WHERE user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB");
                App\Execute($sql, "DB");
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'User level updated successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::updateUserLevel: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }

    public function assignUserLevel($data)
    {
        try {
            if (!App\AllowAdd("user_level_assignments")) {
                throw new \InvalidArgumentException("You do not have permission to assign access");
            }

            $userId = $data['user_id'] ?? null;
            $userLevelId = $data['user_level_id'] ?? null;
            $systemId = empty($data['system_id']) ? null : $data['system_id'];

            if (empty($userId) || !is_numeric($userId)) {
                throw new \InvalidArgumentException("A valid user is required");
            }
            if ($userLevelId === null || $userLevelId === '' || !is_numeric($userLevelId)) {
                throw new \InvalidArgumentException("A valid user level is required");
            }

            $userExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM users WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$userExists) {
                throw new \InvalidArgumentException("User not found");
            }

            $levelExists = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_levels WHERE user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if (!$levelExists) {
                throw new \InvalidArgumentException("User level not found");
            }

            $alreadyAssigned = App\ExecuteScalar(
                "SELECT COUNT(*) FROM user_level_assignments WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB") .
                " AND user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB"),
                "DB"
            );
            if ($alreadyAssigned > 0) {
                throw new \InvalidArgumentException("This user already has that role assigned");
            }

            $assignedBy = App\CurrentUserID() ?: null;

            App\Execute("BEGIN", "DB");
            try {
                $sql = "INSERT INTO user_level_assignments (system_id, user_id, user_level_id, assigned_by, created_at) VALUES (" .
                    ($systemId !== null ? App\QuotedValue($systemId, App\DataType::NUMBER, "DB") : "NULL") . ", " .
                    App\QuotedValue($userId, App\DataType::NUMBER, "DB") . ", " .
                    App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") . ", " .
                    ($assignedBy !== null ? App\QuotedValue($assignedBy, App\DataType::NUMBER, "DB") : "NULL") .
                    ", CURRENT_TIMESTAMP)";
                App\Execute($sql, "DB");

                // PHPMaker's core login/permission engine (AdvancedSecurity::setupUserLevelEx(),
                // via Config("USER_LEVEL_FIELD_NAME") = "user_level_id") reads this single column
                // on uac.users directly — it has no knowledge of user_level_assignments. Without
                // this, a freshly-assigned user still gets "no permission" everywhere on login.
                App\Execute(
                    "UPDATE users SET user_level_id = " . App\QuotedValue($userLevelId, App\DataType::NUMBER, "DB") .
                    " WHERE user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, "DB"),
                    "DB"
                );
                App\Execute("COMMIT", "DB");
            } catch (\Exception $e) {
                App\Execute("ROLLBACK", "DB");
                throw $e;
            }

            return json_encode(['success' => true, 'message' => 'Access assigned successfully']);
        } catch (\InvalidArgumentException $e) {
            return json_encode(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            App\LogError("Error in UserAccessService::assignUserLevel: " . $e->getMessage());
            return json_encode(['success' => false, 'message' => 'Internal server error']);
        }
    }
}
