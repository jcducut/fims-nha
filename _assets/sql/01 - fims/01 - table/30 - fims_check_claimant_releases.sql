-- ============================================================================
-- Check Claimant Releases — backs Tab 3 (Released to Claimants): the "Check
-- Release List" and "Unclaimed/Unreleased" sub-tabs both read this same
-- table, just filtered by status (status = 'UNCLAIMED' for the latter).
-- "Days Since Prep." (Unclaimed sub-tab) is computed from the related
-- fims.checks.prepared_at, not stored here.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_claimant_releases();
ALTER TABLE fims.check_claimant_releases
ADD COLUMN IF NOT EXISTS release_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS check_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS release_type VARCHAR(20) NOT NULL DEFAULT 'DIRECT', -- DIRECT | EMAIL_NOTICE
ADD COLUMN IF NOT EXISTS claimant_type VARCHAR(20) DEFAULT 'PAYEE', -- PAYEE | AUTHORIZED_REP | ORIGINATING_UNIT
ADD COLUMN IF NOT EXISTS claimant_name VARCHAR(255),
ADD COLUMN IF NOT EXISTS id_type VARCHAR(50),
ADD COLUMN IF NOT EXISTS id_number VARCHAR(50),
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'PENDING', -- PENDING|NOTIFIED|RELEASED|ACKNOWLEDGED|UNCLAIMED
ADD COLUMN IF NOT EXISTS release_date DATE,
ADD COLUMN IF NOT EXISTS acknowledgment_date DATE,
ADD COLUMN IF NOT EXISTS last_follow_up_date DATE,
ADD COLUMN IF NOT EXISTS follow_up_remarks TEXT,
ADD COLUMN IF NOT EXISTS remarks TEXT,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_claimant_releases IS 'Direct/email-notice releases of checks to their payee or an authorized representative — backs Tab 3 (Released to Claimants), both its Check Release List and Unclaimed/Unreleased sub-tabs';

CREATE INDEX IF NOT EXISTS idx_check_claimant_releases_check_id ON fims.check_claimant_releases (check_id);
CREATE INDEX IF NOT EXISTS idx_check_claimant_releases_status   ON fims.check_claimant_releases (status);
