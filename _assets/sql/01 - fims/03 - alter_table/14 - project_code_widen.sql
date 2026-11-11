-- ALTER: project_code columns, widened VARCHAR(20) -> VARCHAR(30)
--
-- GlobalService::generateProjectCode() builds
-- "<year>-<rc_id>-<prog3>-<subprog4>-<series4>" (e.g. "2026-R04-001-0002-0001",
-- 22 chars) -- worst case with a 4-char rc_id (e.g. "R04A", "NSSB") is 23
-- chars, already over the old VARCHAR(20) limit. Every column that stores or
-- references fims.project.project_code is widened together so none of them
-- truncate/reject what the others accept.
--
-- Widening a VARCHAR's length is a metadata-only change in Postgres (no table
-- rewrite, no data loss) -- safe to run any number of times.
--
-- fims.budget_req.project_code is NOT included here -- it was already
-- dropped by "02 - budget_req_remove_expense_class_flow.sql" (the old
-- expense-class-driven CO/PS/MOOE flow), so it no longer exists.
ALTER TABLE fims.project              ALTER COLUMN project_code TYPE VARCHAR(30);
ALTER TABLE fims.budget_req_project   ALTER COLUMN project_code TYPE VARCHAR(30);
ALTER TABLE fims.chart_of_accounts    ALTER COLUMN project_code TYPE VARCHAR(30);
