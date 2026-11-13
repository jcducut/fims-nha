-- ============================================================================
-- Cash Position Activity Log — ported from TREASURY's getActivityLog()
-- (CashPosUpServices.php), backing Position Updates' "Recent Update
-- Activity" panel. One row per manual-entry create/update action.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.cash_position_activity_log();
ALTER TABLE fims.cash_position_activity_log
ADD COLUMN IF NOT EXISTS activity_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS cash_position_id INTEGER,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS position_date DATE NOT NULL,
ADD COLUMN IF NOT EXISTS action VARCHAR(20) NOT NULL, -- CREATE | UPDATE
ADD COLUMN IF NOT EXISTS details TEXT,
ADD COLUMN IF NOT EXISTS performed_by INTEGER,
ADD COLUMN IF NOT EXISTS performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.cash_position_activity_log IS 'Audit trail of manual Cash Position entries — backs Position Updates'' Recent Update Activity panel';

CREATE INDEX IF NOT EXISTS idx_cash_position_activity_log_bank_account ON fims.cash_position_activity_log (bank_account_id);
CREATE INDEX IF NOT EXISTS idx_cash_position_activity_log_performed_at ON fims.cash_position_activity_log (performed_at DESC);
