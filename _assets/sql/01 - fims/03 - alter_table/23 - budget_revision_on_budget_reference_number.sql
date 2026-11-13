-- ============================================================
-- ALTER: fims.budget_revision
-- Budget revisions on BUDGET LINES (fims.budget_line_items).
--
-- A budget request is now a header keyed by budget_reference_number, with its Programs, Projects and line items in child tables
-- (03 - alter_table 02, 04, 10, 15). It has no id and no amount of its own, so a revision can no longer point at "a budget
-- request": it revises BUDGET LINES — the chart-of-accounts line items — moving funds between two of them, or changing one.
--
--   * fims.budget_revision.from_budget_line_item_id  INTEGER — NEW: the line being revised (fims.budget_line_items.budget_line_item_id)
--   * fims.budget_revision.to_budget_line_item_id    INTEGER — NEW: the line that receives the funds. NULL = none (the From line's amount
--       alone changes).
--
-- Approving a revision changes the lines' fims.budget_line_items.adjustment ("net adjustments applied to the proposed amount"):
--   From line  adjustment := adjustment + (to_amount - the line's current amount)        -> its current amount becomes to_amount
--   To line    adjustment := adjustment + (the From line's current amount - to_amount)   -> it receives the difference
-- where a line's CURRENT amount is  proposed_amount + adjustment.  proposed_amount itself is never touched.
--
-- The earlier from_budget_req_id / to_budget_req_id columns held a budget REQUEST's id, which no longer exists, so they are left
-- exactly as they are, unused. They are not reinterpreted as line ids — that would point old revisions at unrelated lines, and
-- approving one would change the wrong line. A revision filed before this change has no budget line: it still lists (with the line's
-- details blank) but cannot be approved. from_budget_req_id was NOT NULL, so that is relaxed for the revisions filed from now on.
--
-- Idempotent (IF NOT EXISTS / IF EXISTS guards): safe to run any number of times, on any environment.
-- ============================================================

ALTER TABLE fims.budget_revision
    ADD COLUMN IF NOT EXISTS from_budget_line_item_id INTEGER,
    ADD COLUMN IF NOT EXISTS to_budget_line_item_id   INTEGER;

DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM information_schema.columns
         WHERE table_schema = 'fims' AND table_name = 'budget_revision'
           AND column_name = 'from_budget_req_id' AND is_nullable = 'NO'
    ) THEN
        ALTER TABLE fims.budget_revision ALTER COLUMN from_budget_req_id DROP NOT NULL;
    END IF;
END
$$;

CREATE INDEX IF NOT EXISTS idx_fims_budget_revision_from_line ON fims.budget_revision (from_budget_line_item_id);
CREATE INDEX IF NOT EXISTS idx_fims_budget_revision_to_line   ON fims.budget_revision (to_budget_line_item_id);

COMMENT ON COLUMN fims.budget_revision.from_budget_line_item_id IS
    'fims.budget_line_items.budget_line_item_id the revision changes (the line being revised). No FK: a line is deleted and re-created whenever a Draft request''s Program is edited.';
COMMENT ON COLUMN fims.budget_revision.to_budget_line_item_id IS
    'fims.budget_line_items.budget_line_item_id that receives the funds the From line gives up. NULL when there is none.';
COMMENT ON COLUMN fims.budget_revision.from_budget_req_id IS
    'LEGACY, unused: the id of a budget request, before budget requests became headers keyed by budget_reference_number. Superseded by from_budget_line_item_id.';
COMMENT ON COLUMN fims.budget_revision.to_budget_req_id IS
    'LEGACY, unused: see from_budget_req_id. Superseded by to_budget_line_item_id.';
