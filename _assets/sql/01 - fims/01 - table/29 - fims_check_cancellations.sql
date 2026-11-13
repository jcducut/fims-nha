-- ============================================================================
-- Check Cancellations — backs Tab 5 (Cancellation & Replacements). One row
-- per cancelled check; replacement_check_id is set when "Issue a replacement
-- check immediately" was checked in the Cancel Check modal (soft self-
-- reference to another fims.checks row, the newly-prepared replacement).
-- The cancelled check's own fims.checks.status is separately flipped to
-- 'cancelled' by the service — this table holds the cancellation-specific
-- detail (reason, date, replacement) that fims.checks has no room for.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_cancellations();
ALTER TABLE fims.check_cancellations
ADD COLUMN IF NOT EXISTS cancellation_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS check_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS reason VARCHAR(30) NOT NULL, -- LOST | DAMAGED | ERROR | PAYEE_REQUEST | OTHER
ADD COLUMN IF NOT EXISTS reason_description TEXT,
ADD COLUMN IF NOT EXISTS cancelled_date DATE NOT NULL,
ADD COLUMN IF NOT EXISTS replacement_check_id INTEGER,
ADD COLUMN IF NOT EXISTS cancelled_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_cancellations IS 'Cancellation detail for checks whose fims.checks.status = ''cancelled'' — backs Tab 5 (Cancellation & Replacements)';
COMMENT ON COLUMN fims.check_cancellations.replacement_check_id IS 'Set when a replacement check was issued immediately — soft self-reference to the new fims.checks row';

CREATE UNIQUE INDEX IF NOT EXISTS uq_check_cancellations_check_id ON fims.check_cancellations (check_id);
CREATE INDEX IF NOT EXISTS idx_check_cancellations_cancelled_date ON fims.check_cancellations (cancelled_date);
