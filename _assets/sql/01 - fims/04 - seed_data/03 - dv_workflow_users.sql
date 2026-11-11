-- DV-workflow sample users. Lives in the fims seed folder (not uac) because it needs
-- uac.users.rc_id, which is added by "03 - alter_table/09 - users_replace_rc_code_with_rc_id.sql"
-- and references fims.responsibility_code. Roles 10/11 are created in
-- "00 - uac/04 - seed_data/04 - dv_workflow_departments_users.sql".
-- 012 Accounting, 013 FMD, 014 Treasury; see DisbursementVoucherService::STAGE_RC.

-- Sample users, same convention as "03 - budget_approval_sample_users.sql":
-- password is "password123" (bcrypt of the lowercased password,
-- CASE_SENSITIVE_PASSWORD = false).
INSERT INTO uac.users (
    username, email, password_hash, first_name, last_name,
    user_level_id, rc_id, is_active
) VALUES
    ('accounting_officer', 'accounting.officer@nha.gov.ph', '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Lea',  'Fernandez', '10', '012', true),
    ('treasury_officer',   'treasury.officer@nha.gov.ph',   '$2y$10$OzcU5YBDp7QspsUHFIC9XehdBZnif3zglhRpWF3WEeFkful7223Qm', 'Noel', 'Ramirez',   '11', '014', true)
ON CONFLICT (username) DO UPDATE SET
    password_hash = EXCLUDED.password_hash,
    user_level_id = EXCLUDED.user_level_id,
    rc_id = EXCLUDED.rc_id,
    is_active = true;

-- fmd_divmanager (from "03 - budget_approval_sample_users.sql") already exists for FMD
-- budget-approval testing; tag it with the FMD RC too so it doubles as the DV workflow's
-- FMD-stage test user.
UPDATE uac.users SET rc_id = '013' WHERE username = 'fmd_divmanager';
