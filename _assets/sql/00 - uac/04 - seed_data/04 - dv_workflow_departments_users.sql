-- DV-workflow roles only. The sample users (which need uac.users.rc_id, added by the
-- fims alter scripts that run AFTER this uac folder) are seeded in
-- "01 - fims/04 - seed_data/03 - dv_workflow_users.sql".
--
-- Assigns the office each DV-workflow stage belongs to via uac.users.rc_id
-- (Responsibility Center — fims.responsibility_code), the org-chart column this
-- project already uses elsewhere (fims.budget_req.rc_id), rather than
-- uac.departments. The NHA RC Listing (fims.responsibility_code, seeded by the
-- Financial Services Group hierarchy) already has the three offices the DV
-- workflow needs — no new org units to create:
--   013 Financial Management Department (FMD)  — FOR_FMD_REVIEW -> FOR_ACCOUNTING
--   012 Accounting Department                  — FOR_ACCOUNTING -> FOR_TREASURY
--   014 Treasury Department                     — FOR_TREASURY -> RELEASED
-- See DisbursementVoucherService::STAGE_RC.
--
-- Roles (uac.user_levels) — positive IDs; -2/-1/0 are reserved
INSERT INTO uac.user_levels (user_level_id, name, description, system_id) VALUES
    (10, 'Accounting Officer', 'Processes disbursement vouchers (Accounting stage of the DV workflow)', NULL),
    (11, 'Treasury Officer',   'Releases payment on disbursement vouchers (Treasury stage of the DV workflow)', NULL)
ON CONFLICT (user_level_id) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;
