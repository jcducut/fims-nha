-- Lets an admin rename the display caption, or hide/restore a module/page row,
-- in the custom "Permissions Management" UI (app/pages/user-access.php) without
-- touching PHPMaker's generated $USER_LEVEL_TABLES registry (src/userlevelsettings.php).
-- Hiding a module only removes it from this list - it does not delete any
-- underlying uac.user_level_permissions rows, and can be reversed.
-- table_name is stored in its plain (unprefixed) form, matching normalizeTableKey()
-- in app/pages/user-access.php.
CREATE TABLE IF NOT EXISTS uac.permission_module_captions (
    table_name varchar(100) NOT NULL PRIMARY KEY,
    caption varchar(150) NOT NULL,
    is_hidden boolean NOT NULL DEFAULT FALSE,
    updated_at timestamptz(6) NOT NULL DEFAULT CURRENT_TIMESTAMP
);
ALTER TABLE uac.permission_module_captions ADD COLUMN IF NOT EXISTS is_hidden boolean NOT NULL DEFAULT FALSE;
