-- ============================================================
-- ALTER: fims.budget_line_items
-- Renames suggestion_amount -> suggested_amount (the column added by
-- "07 - budget_line_items_add_suggestion_amount.sql"), to match the
-- "Suggested Amount" label used on Budget Processing / Budget Preview.
--
-- Idempotent:
--   * suggestion_amount only      -> renamed (values and NOT NULL DEFAULT 0 are kept)
--   * suggested_amount only       -> nothing to do
--   * neither                     -> suggested_amount is created (NUMERIC(18,2) NOT NULL DEFAULT 0)
--   * both (partial manual fix)   -> left alone, with a NOTICE, so no data is ever dropped
-- ============================================================

DO $$
DECLARE
    has_old BOOLEAN;
    has_new BOOLEAN;
BEGIN
    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'budget_line_items' AND column_name = 'suggestion_amount'
    ) INTO has_old;
    SELECT EXISTS (
        SELECT 1 FROM information_schema.columns
        WHERE table_schema = 'fims' AND table_name = 'budget_line_items' AND column_name = 'suggested_amount'
    ) INTO has_new;

    IF has_old AND NOT has_new THEN
        ALTER TABLE fims.budget_line_items RENAME COLUMN suggestion_amount TO suggested_amount;
        RAISE NOTICE 'Renamed fims.budget_line_items.suggestion_amount to suggested_amount.';
    ELSIF NOT has_old AND NOT has_new THEN
        ALTER TABLE fims.budget_line_items ADD COLUMN suggested_amount NUMERIC(18, 2) NOT NULL DEFAULT 0;
        RAISE NOTICE 'Added fims.budget_line_items.suggested_amount.';
    ELSIF has_old AND has_new THEN
        RAISE NOTICE 'fims.budget_line_items has BOTH suggestion_amount and suggested_amount: left as is, merge them by hand.';
    END IF;
END $$;

COMMENT ON COLUMN fims.budget_line_items.suggested_amount IS 'Suggested amount for this line item (renamed from suggestion_amount).';
