-- One sample user per role from the Budget Preparation approval chain
-- (see "02 - budget_approval_roles.sql"), so a budget request can be walked
-- through the full chain end to end.
--
-- All sample users share the password: password123
-- Hash is bcrypt of the LOWERCASED password (CASE_SENSITIVE_PASSWORD = false,
-- see src/config.php / src/phpfn.php ComparePassword()).

INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, department_id, is_active
) VALUES
    ('fmd_fundsourcing',  'fmd.fundsourcing@nha.gov.ph',  '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Fe',     'Santos',   '1', (SELECT department_id FROM uac.departments WHERE department_code = 'FMD'), true),
    ('budget_officer',    'budget.officer@nha.gov.ph',    '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Juan',    'Dela Cruz','2', (SELECT department_id FROM uac.departments WHERE department_code = 'FMD'), true),
    ('fmd_divmanager',    'fmd.divmanager@nha.gov.ph',    '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Maria',   'Reyes',    '3', (SELECT department_id FROM uac.departments WHERE department_code = 'FMD'), true),
    ('fmd_deptmanager',   'fmd.deptmanager@nha.gov.ph',   '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Ramon',   'Garcia',   '4', (SELECT department_id FROM uac.departments WHERE department_code = 'FMD'), true),
    ('fsg_groupmanager',  'fsg.groupmanager@nha.gov.ph',  '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Carmela', 'Villanueva','5', NULL, true),
    ('general_manager',   'general.manager@nha.gov.ph',   '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Roberto', 'Mendoza',  '6', NULL, true),
    ('nha_board',         'nha.board@nha.gov.ph',         '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Board',   'Member',   '7', NULL, true),
    ('budget_head',       'budget.head@nha.gov.ph',       '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Lourdes', 'Bautista', '8', (SELECT department_id FROM uac.departments WHERE department_code = 'FMD'), true)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    department_id = EXCLUDED.department_id,
    is_active = true;
