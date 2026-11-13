-- Seed data for fims.settings_status, status_for = 'budget_req' (fims.budget_req — the Budget Proposal / Budget Request pages):
-- the status that makes the Budget Officer -> Budget Head review a step-by-step chain, like the Memo Request's.
--
--   For Review                  acted on by the Budget Officer   endorse -> Endorsed
--                                                                 return  -> Returned (back to the originating office)
--   Endorsed                    acted on by the Budget Head      approve -> Approved
--                                                                 return  -> Returned to Budget Officer
--   Returned to Budget Officer  acted on by the Budget Officer   endorse -> Endorsed (again)
--                                                                 return  -> Returned (back to the originating office)
--
-- 'Draft', 'For Review', 'Endorsed', 'Approved' and 'Returned' are seeded in "02 - sample_data/01". BudgetReqService
-- (REVIEW_STEPS) looks all of them up BY NAME, and the actors by the NAME of their uac.user_levels row ('Budget Officer',
-- 'Budget Head' — "00 - uac/04 - seed_data/02"), so the names below must stay as they are (the API says so, and changes
-- nothing, when a status is missing).
--
-- Lives in 04 - seed_data (after 03 - alter_table) because it also fills next_actors, the column "03 - alter_table/21"
-- renames actors to. The code does not read next_actors — the chain is REVIEW_STEPS — it is filled so the table says who
-- acts on each status. Guarded with WHERE NOT EXISTS, and the next_actors part only runs when the column exists, so the
-- file is safe to re-run.
INSERT INTO fims.settings_status (name, status_for, color)
SELECT 'Returned to Budget Officer', 'budget_req', '#EA580C'
WHERE NOT EXISTS (SELECT 1 FROM fims.settings_status WHERE name = 'Returned to Budget Officer' AND status_for = 'budget_req');

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'settings_status' AND column_name = 'next_actors'
    ) THEN
        UPDATE fims.settings_status s
        SET next_actors = jsonb_build_object('users', '[]'::jsonb, 'userLevel', v.user_level, 'department', 'Financial Management Department (FMD)')
        FROM (VALUES
            ('For Review',                 'Budget Officer'),
            ('Endorsed',                   'Budget Head'),
            ('Returned to Budget Officer', 'Budget Officer')
        ) AS v(name, user_level)
        WHERE s.status_for = 'budget_req' AND s.name = v.name;
    END IF;
END $$;
