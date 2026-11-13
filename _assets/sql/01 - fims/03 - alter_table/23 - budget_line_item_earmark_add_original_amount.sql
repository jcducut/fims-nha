-- Adds original_amount to fims.budget_line_item_earmark: the earmark's amount exactly as first created,
-- kept unchanged even as `amount` itself shrinks when an RFA/LAA later converts part of it (see
-- BudgetLineItemService::convertEarmarkForRfaApproval(), which reduces a CBA/CFA's own active row's
-- `amount` on every partial draw but never touches `original_amount`). Needed so "Where this COB's
-- balance is held" can show a CBA/CFA's ORIGINALLY approved amount against a COB, not its current
-- remaining balance — see BudgetLineItemService::fetchEarmarks().
ALTER TABLE fims.budget_line_item_earmark
    ADD COLUMN IF NOT EXISTS original_amount NUMERIC(18,2);

-- Backfill: the best available approximation for any row that already exists is its current `amount`.
-- This is only wrong for an 'active' row that had already been partially drawn down before this column
-- existed — there is no way to recover its true original figure after the fact from existing data alone.
UPDATE fims.budget_line_item_earmark SET original_amount = amount WHERE original_amount IS NULL;

ALTER TABLE fims.budget_line_item_earmark
    ALTER COLUMN original_amount SET NOT NULL;

COMMENT ON COLUMN fims.budget_line_item_earmark.original_amount IS 'The amount this earmark row was created with — fixed, never reduced by a later RFA/LAA draw (unlike `amount`, which shrinks). Used to show a CBA/CFA''s originally approved amount against a COB.';
