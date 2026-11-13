-- Seed data for fims.settings_status, status_for = 'budget_allocation' (fims.budget_allocation — the Memo Request page).
--
-- 'Draft' (the starting status of every memo request) is seeded in "01 - fims_settings_status.sql". This file adds the
-- status a request takes when it is SUBMITTED FOR PREVIEW: the Memo Request form's "Submit Request" button opens a
-- pop-up that offers "Submit for Preview" (status 'For Preview') or "Save as Draft" (status 'Draft'), and the form
-- sends the id of the chosen status. It is looked up BY NAME, so the name below must stay 'For Preview' (the form
-- shows a clear message, and saves nothing, when the status does not exist).
--
-- It is a separate file (not an edit of 01) because the installer remembers which files it has already run, by path:
-- a change to an already-run file never reaches an existing database, a new file does. Guarded with WHERE NOT EXISTS
-- (settings_status has no unique constraint on (name, status_for) to key an ON CONFLICT off of), so it is safe to re-run.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'For Preview', 'budget_allocation', '#2563EB'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'For Preview' AND status_for = 'budget_allocation');
