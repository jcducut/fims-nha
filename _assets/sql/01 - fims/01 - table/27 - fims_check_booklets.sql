-- ============================================================================
-- Check Booklets — the Check Registry tab's unit of blank-stock tracking
-- (one row per received booklet/batch of pre-numbered check forms), and the
-- source for Tab 6 "Inventory (RAF)"'s rows (Report of Accountable Forms).
-- "Received"/"Issued"/"Cancelled"/"Remaining"/"Last Entry"/"Stock Status" in
-- that report are all computed from fims.checks rows whose check_number
-- falls in [start_check_no, end_check_no] for this booklet — not stored here
-- — so CheckProcessingService recomputes them per request rather than this
-- table drifting out of sync with actual check statuses.
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_booklets();
ALTER TABLE fims.check_booklets
ADD COLUMN IF NOT EXISTS booklet_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS bank_account_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS series VARCHAR(50) NOT NULL,
ADD COLUMN IF NOT EXISTS fiscal_year SMALLINT,
ADD COLUMN IF NOT EXISTS department_type VARCHAR(20), -- main | region | branch | project | other
ADD COLUMN IF NOT EXISTS start_check_no VARCHAR(50) NOT NULL,
ADD COLUMN IF NOT EXISTS end_check_no VARCHAR(50) NOT NULL,
ADD COLUMN IF NOT EXISTS date_received DATE,
ADD COLUMN IF NOT EXISTS low_stock_threshold INTEGER NOT NULL DEFAULT 10, -- "Stock Status" flips to Low Stock when remaining checks <= this
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_booklets IS 'Received batches of blank check stock — backs Check Registry''s "Add Batch" and the RAF inventory tab';
COMMENT ON COLUMN fims.check_booklets.low_stock_threshold IS 'Remaining-checks count at/below which the RAF tab flags this booklet as Low Stock';

CREATE INDEX IF NOT EXISTS idx_check_booklets_bank_account ON fims.check_booklets (bank_account_id);
CREATE INDEX IF NOT EXISTS idx_check_booklets_series       ON fims.check_booklets (series);
CREATE INDEX IF NOT EXISTS idx_check_booklets_fiscal_year   ON fims.check_booklets (fiscal_year);
