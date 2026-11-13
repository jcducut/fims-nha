-- ============================================================================
-- Check Claimant Email Log — backs Tab 3's "Email Log" sub-tab (one row per
-- email-notice send attempt for a claimant release).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_claimant_email_log();
ALTER TABLE fims.check_claimant_email_log
ADD COLUMN IF NOT EXISTS log_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS release_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS check_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS recipient_email VARCHAR(150) NOT NULL,
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'SENT', -- SENT | FAILED
ADD COLUMN IF NOT EXISTS error_message TEXT,
ADD COLUMN IF NOT EXISTS sent_by INTEGER,
ADD COLUMN IF NOT EXISTS sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_claimant_email_log IS 'Email-notice send attempts for Tab 3 (Released to Claimants) releases — backs its Email Log sub-tab';

CREATE INDEX IF NOT EXISTS idx_check_claimant_email_log_release_id ON fims.check_claimant_email_log (release_id);
CREATE INDEX IF NOT EXISTS idx_check_claimant_email_log_check_id   ON fims.check_claimant_email_log (check_id);
