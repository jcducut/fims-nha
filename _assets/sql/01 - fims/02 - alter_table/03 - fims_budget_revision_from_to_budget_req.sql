-- ============================================================
-- ALTER: fims.budget_revision
--   * budget_req_id  -> RENAMED to from_budget_req_id
--                       (the budget request / COB the revision moves FROM)
--   * to_budget_req_id  — NEW column: the budget request / COB the revision
--                         moves TO. NULL = no target (a plain amount revision
--                         of the "from" request, which is how every existing
--                         row behaves).
--
-- Idempotent, so it is safe to run on any environment, any number of times:
--   - the rename only happens while the old column exists and the new one does not
--   - the new column uses ADD COLUMN IF NOT EXISTS
-- Existing rows keep their data: the rename carries every value over to
-- from_budget_req_id, and to_budget_req_id starts out NULL.
-- A fresh install already gets both columns from
-- "01 - table/05 - budget_rev.sql"; this file brings databases that were
-- created before the change up to date.
-- ============================================================

DO $$
BEGIN
    IF EXISTS (
            SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'fims' AND table_name = 'budget_revision'
               AND column_name = 'budget_req_id')
       AND NOT EXISTS (
            SELECT 1 FROM information_schema.columns
             WHERE table_schema = 'fims' AND table_name = 'budget_revision'
               AND column_name = 'from_budget_req_id')
    THEN
        ALTER TABLE fims.budget_revision RENAME COLUMN budget_req_id TO from_budget_req_id;
    END IF;
END
$$;

ALTER TABLE fims.budget_revision
    ADD COLUMN IF NOT EXISTS to_budget_req_id INTEGER NULL;

COMMENT ON COLUMN fims.budget_revision.from_budget_req_id IS
    'fims.budget_req.id the revision moves from (the request being revised).';
COMMENT ON COLUMN fims.budget_revision.to_budget_req_id IS
    'fims.budget_req.id the revision moves to. NULL when there is no target request.';
