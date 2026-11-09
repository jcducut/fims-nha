-- Region 4 Finance user.
-- uac.users.rc_code (see "00 - uac/02 - alter_table/03 -
-- users_add_position_employee_mustchange.sql") is set to Region 4's RC code
-- so this account can generate budget program codes
-- (GlobalService::generateProgramCode() requires users.rc_code to be set).
-- uac.users.rc_id (see "03 - alter_table/09 - users_add_rc_id.sql") is set to
-- Region 4's RC id ('RC-031') so this account can create/list its own
-- fims.budget_req rows (GlobalService::getUserRcId() / getUserRcScope()) --
-- a third, independent RC-ish column from rc_code/region_code.
-- region_code is intentionally left NULL, so this user is NOT row-restricted
-- on the Chart of Accounts page (ChartOfAccountsService).
-- The 'Region Finance' user_level (same one used by r3.finance) carries no
-- special permission wiring of its own -- it exists only so this account is
-- not treated as superadmin (user_level_id = '-1' bypasses
-- rc_code/region_code/rc_id scoping app-wide).
--
-- Lives here (fims phase, after 02 - sample_data) rather than in
-- "00 - uac/04 - seed_data" because fims.responsibility_code doesn't exist
-- yet when the uac phase runs -- the uac phase runs entirely before the fims
-- phase in the installer.
--
-- Password: 123456 (bcrypt of the LOWERCASED password, per
-- CASE_SENSITIVE_PASSWORD = false -- see src/config.php / src/phpfn.php
-- ComparePassword()).

INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, department_id, rc_code, rc_id, is_active
) VALUES (
    'r4.finance',
    'r4.finance@nha.gov.ph',
    '$2y$10$.3hCaZZ/e0QROvj1bmVpe.5tEvnsaGTWvToJW9pDH6v6jLZ6p2VsK', -- 123456
    'Region 4',
    'Finance',
    '9',
    NULL,
    (SELECT rc_code FROM fims.responsibility_code WHERE rc_id = 'RC-031'),
    'RC-031',
    true
)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    rc_code = EXCLUDED.rc_code,
    rc_id = EXCLUDED.rc_id,
    is_active = true;
