-- Seed data for fims.settings_status, status_for = 'budget_allocation' (fims.budget_allocation — the Memo Request page):
-- the statuses a request takes when an FMD reviewer RETURNS it to the previous reviewer instead of to the requesting office.
--
--   Division Manager returns   -> 'Returned to Section Chief'     acted on by the FMD Section Chief
--                                  (endorse again -> For Division Manager; return -> 'Returned', the requesting office)
--   Department Manager returns -> 'Returned to Division Manager'  acted on by the FMD Division Manager
--                                  (endorse again -> For Department Manager; return -> 'Returned to Section Chief')
--   Section Chief returns      -> 'Returned' (seeded in "02 - sample_data/07"): back to the requesting office.
--
-- BudgetAllocationService::REVIEW_STEPS looks the statuses up BY NAME, so the names below must stay as they are (the API
-- says so, and changes nothing, when one is missing). Guarded with WHERE NOT EXISTS, and the next_actors part only runs
-- when the column exists, so the file is safe to re-run. Run it after "04 - memo_request_review_chain_statuses.sql".
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Returned to Section Chief', 'budget_allocation', '#EA580C'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Returned to Section Chief' AND status_for = 'budget_allocation');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Returned to Division Manager', 'budget_allocation', '#D97706'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Returned to Division Manager' AND status_for = 'budget_allocation');

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'settings_status' AND column_name = 'next_actors'
    ) THEN
        UPDATE fims.settings_status s
        SET next_actors = jsonb_build_object('users', '[]'::jsonb, 'userLevel', v.user_level, 'department', 'Financial Management Department (FMD)')
        FROM (VALUES
            ('Returned to Section Chief',    'FMD Section Chief'),
            ('Returned to Division Manager', 'FMD Division Manager')
        ) AS v(name, user_level)
        WHERE s.status_for = 'budget_allocation' AND s.name = v.name;
    END IF;
END $$;
