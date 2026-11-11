-- Grants page access so the DV-workflow test users (see "04 - dv_workflow_...")
-- can actually log in and use the Voucher page — none of the budget-approval-chain
-- sample users (file 03) had any uac.user_level_permissions rows either, so without
-- this every one of them gets a 401 on login (a pre-existing gap, not introduced here).
--
-- Permission bitmask (see _shared/PagePermission.php): 1135 = ADD(1) + DELETE(2) +
-- EDIT(4) + LIST(8) + VIEW(32) + SEARCH(64) + EXPORT(1024) — full page CRUD, no ADMIN/
-- IMPORT/LOOKUP/PUSH. Department-level restrictions on *which* workflow stage a user
-- may move a voucher to are enforced separately, server-side, in
-- DisbursementVoucherService::authorizeTransition() — this only grants access to the
-- Voucher page itself.
--
-- Levels covered: 3 (FMD Division Manager), 5 (FSG Group Manager — no RC assigned,
-- used as a negative control), 10 (Accounting Officer), 11 (Treasury Officer).
INSERT INTO uac.user_level_permissions (user_level_id, table_name, permission)
SELECT lvl, pg, 1135
FROM unnest(ARRAY[3, 5, 10, 11]) AS lvl
CROSS JOIN unnest(ARRAY['MainDashboard.php', 'Voucher.php']) AS pg
ON CONFLICT (user_level_id, table_name) DO UPDATE SET permission = EXCLUDED.permission;
