-- Dev/test superadmin account.
-- user_level_id = '-1' matches AdvancedSecurity::ADMIN_USER_LEVEL_ID, which bypasses
-- all permission checks app-wide (src/AdvancedSecurity.php).
-- Password hash below is bcrypt of the LOWERCASED password, because
-- CASE_SENSITIVE_PASSWORD is false (src/config.php) and ComparePassword()
-- verifies against strtolower($input) in that mode (src/phpfn.php).
-- Login: superadmin / superadmin123

INSERT INTO uac.user_levels (user_level_id, name, description, system_id)
VALUES (-1, 'System Administrator', 'Built-in full-access admin level (bypasses all permission checks)', NULL)
ON CONFLICT (user_level_id) DO NOTHING;

INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, is_active
) VALUES (
    'superadmin',
    'superadmin@nha.gov.ph',
    '$2y$10$VyDqyaMbuvOVxZQ/C0ntBuotovXpvrusiAFUYNk/LbTz/EKtgmTHe', -- superadmin123
    'Super',
    'Admin',
    '-1',
    true
)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    is_active = true;
