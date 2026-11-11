-- ============================================================
-- ALTER: fims.budget_req — add submitted_at
-- The "Date Submitted" column of the Financial Management Department's Budget Requirements list.
-- BudgetReqService stamps it with NOW() on every save of the request's content — creating the request
-- or adding a Program to it (addProgram()), editing a Program (updateProgram()) — and when a Draft
-- request is submitted for review (submitForReview()). So it is the date of the LATEST save / submission.
-- It stays NULL for requests that existed before this column did, whose real date was never recorded
-- and is not guessed at here — so the list shows "—" for those.
-- Idempotent: safe to run any number of times.
-- ============================================================
ALTER TABLE fims.budget_req
    ADD COLUMN IF NOT EXISTS submitted_at TIMESTAMPTZ;

COMMENT ON COLUMN fims.budget_req.submitted_at IS
    'When the request was last saved / submitted: stamped on create, on adding or editing a Program, and on submit for review. NULL = it existed before this column did.';
