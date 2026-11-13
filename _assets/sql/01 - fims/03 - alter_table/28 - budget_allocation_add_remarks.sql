-- ============================================================
-- ALTER: fims.budget_allocation (the Memo Request page)
-- Adds remarks TEXT (nullable) — the reason given when the request was
-- last Returned by an FMD reviewer (the Memo Request "Process" modal asks
-- for it: a Return at any step of the review chain needs remarks).
-- NULL until a request has been returned; existing rows stay NULL. Only
-- BudgetAllocationService::process() writes it (a Return sets it; an
-- Endorse / Approve leaves the last return's remarks as they were).
-- Safe to re-run.
-- ============================================================

ALTER TABLE fims.budget_allocation
    ADD COLUMN IF NOT EXISTS remarks TEXT;

COMMENT ON COLUMN fims.budget_allocation.remarks IS 'Remarks entered when the memo request was last Returned (required by the Return action of the FMD review).';
