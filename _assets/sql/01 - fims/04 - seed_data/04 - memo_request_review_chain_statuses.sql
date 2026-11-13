-- Seed data for fims.settings_status, status_for = 'budget_allocation' (fims.budget_allocation — the Memo Request page):
-- the two in-between statuses of the Financial Management Department's (FMD) review chain.
--
--   For Preview             acted on by the FMD Section Chief       approve -> For Division Manager
--   For Division Manager    acted on by the FMD Division Manager    approve -> For Department Manager
--   For Department Manager  acted on by the FMD Department Manager  approve -> Approved (+ the allocation number)
--   a Return at any of the three steps -> Returned
--
-- 'Draft' is seeded in "02 - sample_data/01", 'For Preview' in "02 - sample_data/06", 'Approved' / 'Returned' in
-- "02 - sample_data/07". BudgetAllocationService (REVIEW_STEPS) looks all of them up BY NAME, and the actors by the NAME
-- of their uac.user_levels row, so the names below must stay as they are (the API says so, and changes nothing, when a
-- status is missing).
--
-- Lives in 04 - seed_data (after 03 - alter_table) because it also fills next_actors, the column "03 - alter_table/21"
-- renames actors to. The code does not read next_actors — the chain is REVIEW_STEPS — it is filled so the table says
-- who acts on each status. Guarded with WHERE NOT EXISTS, and the next_actors part only runs when the column exists,
-- so the file is safe to re-run.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'For Division Manager', 'budget_allocation', '#7C3AED'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'For Division Manager' AND status_for = 'budget_allocation');

INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'For Department Manager', 'budget_allocation', '#0891B2'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'For Department Manager' AND status_for = 'budget_allocation');

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'settings_status' AND column_name = 'next_actors'
    ) THEN
        UPDATE fims.settings_status s
        SET next_actors = jsonb_build_object('users', '[]'::jsonb, 'userLevel', v.user_level, 'department', 'Financial Management Department (FMD)')
        FROM (VALUES
            ('For Preview',            'FMD Section Chief'),
            ('For Division Manager',   'FMD Division Manager'),
            ('For Department Manager', 'FMD Department Manager')
        ) AS v(name, user_level)
        WHERE s.status_for = 'budget_allocation' AND s.name = v.name;
    END IF;
END $$;
