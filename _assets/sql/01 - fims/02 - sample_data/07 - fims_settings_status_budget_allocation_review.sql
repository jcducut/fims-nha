-- Seed data for fims.settings_status, status_for = 'budget_allocation' (fims.budget_allocation — the Memo Request page):
-- the two outcomes of the Financial Management Department's (FMD) review of a request that is 'For Preview'.
--
--   Approved  set by the Memo Request list's "Process" modal (Approve): the request also gets its allocation number.
--   Returned  set by the same modal (Return): the request goes back to the requesting office, no number is given.
--
-- 'Draft' is seeded in "01 - fims_settings_status.sql" and 'For Preview' in "06 - fims_settings_status_budget_allocation.sql".
-- BudgetAllocationService looks all of them up BY NAME, so the names below must stay as they are (the API says so, and
-- changes nothing, when one is missing). 'Approved' is also what the Parent CBA picker of the memo form offers.
--
-- A separate file (not an edit of 06) because the installer remembers which files it has already run, by path: a change
-- to an already-run file never reaches an existing database, a new file does. Guarded with WHERE NOT EXISTS
-- (settings_status has no unique constraint on (name, status_for) to key an ON CONFLICT off of), so it is safe to re-run.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Approved', 'budget_allocation', '#16A34A'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Approved' AND status_for = 'budget_allocation');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Returned', 'budget_allocation', '#F97316'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Returned' AND status_for = 'budget_allocation');
