-- FMD approver users: Section Chief, Division Manager, Department Manager and Group Manager,
-- all assigned to rc_id '013' (Financial Management Department (FMD), rc_code 35-074-0000000-032 --
-- see "02 - sample_data/02 - fims_responsibility_code.sql").
--
-- rc_id '013' is the central review RC (GlobalService::CENTRAL_REVIEW_RC_CODE), so these accounts see
-- every requesting office's budget requests and can Approve / Return a Memo Request that is 'For Preview'
-- (BudgetAllocationService::isReviewer()).
--
-- Roles (uac.user_levels): this file creates all four itself, so it does not depend on
-- "00 - uac/04 - seed_data/02 - budget_approval_roles.sql" having run first.
--   FMD Section Chief      NEW -- no level for it existed anywhere else.
--   FMD Division Manager   already seeded as level 3 by budget_approval_roles.sql -- left alone when it exists.
--   FMD Department Manager already seeded as level 4 by budget_approval_roles.sql -- left alone when it exists.
--   FSG Group Manager      already seeded as level 5 by budget_approval_roles.sql -- left alone when it exists.
-- A level is created only when no level with that NAME (case-insensitive, as UserAccessService::createUserLevel()
-- checks it) exists yet, and gets the next free positive id (MAX + 1, same as createUserLevel() -- user_levels has
-- no id sequence). The users below then look their level up by NAME, so it works whatever id each level has.
--
-- No rows are written to uac.user_level_permissions (none of the seeded levels have any; they are configured on the
-- User Access > Permissions screen). Until that is done a level has no module access, so a new 'FMD Section Chief'
-- user can log in but sees no modules.
--
-- Usernames / emails are deliberately different from the older sample users in "00 - uac/04 - seed_data/03 -
-- budget_approval_sample_users.sql" (fmd_divmanager, fmd_deptmanager, fsg_groupmanager): uac.users.email is UNIQUE
-- and those rows already hold fmd.divmanager@ / fmd.deptmanager@, and these accounts must not overwrite them.
--
-- Lives here (fims phase, after 02 - sample_data) rather than in "00 - uac/04 - seed_data" because
-- fims.responsibility_code doesn't exist yet when the uac phase runs (the uac phase runs entirely before the fims
-- phase in the installer), and uac.users.rc_id is a FOREIGN KEY to it.
--
-- All four share the password: 123456 (bcrypt of the LOWERCASED password, per CASE_SENSITIVE_PASSWORD = false --
-- see src/config.php / src/phpfn.php ComparePassword()). Dev/sample accounts: change it on any real environment.
--
-- Idempotent: each level insert is guarded by name, the users upsert on username.

-- -----------------------------------------------------------------------------
-- Roles (uac.user_levels)
-- Only the levels still missing get a row; ROW_NUMBER() runs AFTER the NOT EXISTS filter, so the new ids are
-- consecutive (MAX + 1, MAX + 2, ...) with no gaps. The descriptions of the three existing levels match the ones
-- in budget_approval_roles.sql.
-- -----------------------------------------------------------------------------
INSERT INTO uac.user_levels (user_level_id, name, description, system_id)
SELECT
    base.max_id + ROW_NUMBER() OVER (ORDER BY v.ord),
    v.name,
    v.description,
    NULL
FROM (VALUES
    (1, 'FMD Section Chief',      'Heads a section within the Financial Management Department (FMD); sits below the FMD Division Manager'),
    (2, 'FMD Division Manager',   'Reviews the prepared budget at section/division level (FMD - Reviewer step)'),
    (3, 'FMD Department Manager', 'Approves the budget request at department level before escalation to NHA Management'),
    (4, 'FSG Group Manager',      'Authorized approver for budget requests within the FSG Group Manager threshold')
) AS v(ord, name, description)
CROSS JOIN (SELECT GREATEST(0, COALESCE(MAX(user_level_id), 0)) AS max_id FROM uac.user_levels) AS base
WHERE NOT EXISTS (SELECT 1 FROM uac.user_levels l WHERE LOWER(l.name) = LOWER(v.name));

-- -----------------------------------------------------------------------------
-- Users (uac.users) -- all on rc_id '013'
-- user_level_id is VARCHAR (PHPMaker stores comma-separated ids), hence the ::text on the looked-up id.
-- -----------------------------------------------------------------------------
INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, department_id, rc_id, is_active
) VALUES
    (
        'fmd.sectionchief',
        'fmd.sectionchief@nha.gov.ph',
        '$2y$10$.3hCaZZ/e0QROvj1bmVpe.5tEvnsaGTWvToJW9pDH6v6jLZ6p2VsK', -- 123456
        'FMD',
        'Section Chief',
        (SELECT user_level_id::text FROM uac.user_levels WHERE LOWER(name) = LOWER('FMD Section Chief')),
        NULL,
        '013',
        true
    ),
    (
        'fmd.divisionmanager',
        'fmd.divisionmanager@nha.gov.ph',
        '$2y$10$.3hCaZZ/e0QROvj1bmVpe.5tEvnsaGTWvToJW9pDH6v6jLZ6p2VsK', -- 123456
        'FMD',
        'Division Manager',
        (SELECT user_level_id::text FROM uac.user_levels WHERE LOWER(name) = LOWER('FMD Division Manager')),
        NULL,
        '013',
        true
    ),
    (
        'fmd.departmentmanager',
        'fmd.departmentmanager@nha.gov.ph',
        '$2y$10$.3hCaZZ/e0QROvj1bmVpe.5tEvnsaGTWvToJW9pDH6v6jLZ6p2VsK', -- 123456
        'FMD',
        'Department Manager',
        (SELECT user_level_id::text FROM uac.user_levels WHERE LOWER(name) = LOWER('FMD Department Manager')),
        NULL,
        '013',
        true
    ),
    (
        'fmd.groupmanager',
        'fmd.groupmanager@nha.gov.ph',
        '$2y$10$.3hCaZZ/e0QROvj1bmVpe.5tEvnsaGTWvToJW9pDH6v6jLZ6p2VsK', -- 123456
        'FMD',
        'Group Manager',
        (SELECT user_level_id::text FROM uac.user_levels WHERE LOWER(name) = LOWER('FSG Group Manager')),
        NULL,
        '013',
        true
    )
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    rc_id = EXCLUDED.rc_id,
    is_active = true;
