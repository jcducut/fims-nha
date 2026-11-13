-- ============================================================================
-- Check Region Email Log — backs Tab 4's "Email Log" sub-tab. Unlike the
-- per-claimant email log (one check per email), a monthly region notice can
-- bundle several checks into one email — hence checks_count/total_amount
-- instead of a single check_id.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_region_email_log();
ALTER TABLE fims.check_region_email_log
ADD COLUMN IF NOT EXISTS log_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS region_dept VARCHAR(150) NOT NULL,
ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(150) NOT NULL,
ADD COLUMN IF NOT EXISTS checks_count INTEGER NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS total_amount NUMERIC(18,2) NOT NULL DEFAULT 0,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'SENT', -- SENT | FAILED
ADD COLUMN IF NOT EXISTS error_message TEXT,
ADD COLUMN IF NOT EXISTS sent_by INTEGER,
ADD COLUMN IF NOT EXISTS sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_region_email_log IS 'Monthly/notice email sends to regions/departments (can bundle multiple checks per email) — backs Tab 4''s Email Log sub-tab';

CREATE INDEX IF NOT EXISTS idx_check_region_email_log_region  ON fims.check_region_email_log (region_dept);
CREATE INDEX IF NOT EXISTS idx_check_region_email_log_sent_at ON fims.check_region_email_log (sent_at);
