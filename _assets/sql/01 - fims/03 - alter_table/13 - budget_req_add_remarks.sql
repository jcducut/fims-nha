-- ============================================================
-- ALTER: fims.budget_req
-- Adds remarks TEXT (nullable) — the reason given when the request was
-- last Returned (Budget Processing / Budget Preview "Return" asks for it).
-- NULL until a request has been returned; existing rows stay NULL.
-- ============================================================

ALTER TABLE fims.budget_req
    ADD COLUMN IF NOT EXISTS remarks TEXT;

COMMENT ON COLUMN fims.budget_req.remarks IS 'Remarks entered when the budget request was last Returned (required by the Return action).';
