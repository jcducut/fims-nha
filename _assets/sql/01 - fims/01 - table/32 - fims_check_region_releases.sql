-- ============================================================================
-- Check Region Releases — backs Tab 4 (Released to Regions/Departments): the
-- "Check Tracking" and "Acknowledgment" sub-tabs both read this same table
-- (filtered/joined differently), and its dv_returned/dv_* columns back the
-- "Return Paid DVs" sub-tab. "Days Outstanding"/"Stale (>180 days)" are
-- computed from release_date, not stored.
--
-- region_dept is a free-text snapshot (e.g. "Region 3", "Region 4 Field
-- Office"), not an FK to uac.departments — regional field offices here don't
-- necessarily correspond 1:1 with that table's organizational departments
-- (same reasoning as fims.disbursement_vouchers.dept_project being plain
-- text rather than FK'd).
-- ============================================================================

CREATE TABLE IF NOT EXISTS fims.check_region_releases();
ALTER TABLE fims.check_region_releases
ADD COLUMN IF NOT EXISTS region_release_id SERIAL PRIMARY KEY,
ADD COLUMN IF NOT EXISTS check_id INTEGER NOT NULL,
ADD COLUMN IF NOT EXISTS region_dept VARCHAR(150) NOT NULL,
ADD COLUMN IF NOT EXISTS claimant_name VARCHAR(255), -- Authorized Representative
ADD COLUMN IF NOT EXISTS rdtf_no VARCHAR(50),
ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'PENDING', -- PENDING|RELEASED|ACKNOWLEDGED|UNCLAIMED
ADD COLUMN IF NOT EXISTS release_date DATE,
ADD COLUMN IF NOT EXISTS acknowledgment_date DATE,
ADD COLUMN IF NOT EXISTS id_submitted VARCHAR(100),
ADD COLUMN IF NOT EXISTS dv_returned BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS dv_reference VARCHAR(50),
ADD COLUMN IF NOT EXISTS dv_return_date DATE,
ADD COLUMN IF NOT EXISTS remarks TEXT,
ADD COLUMN IF NOT EXISTS created_by INTEGER,
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

COMMENT ON TABLE fims.check_region_releases IS 'Releases of checks to originating regions/departments — backs Tab 4 (Released to Regions/Departments), its Check Tracking, Acknowledgment, and Return Paid DVs sub-tabs';

CREATE INDEX IF NOT EXISTS idx_check_region_releases_check_id ON fims.check_region_releases (check_id);
CREATE INDEX IF NOT EXISTS idx_check_region_releases_status   ON fims.check_region_releases (status);
CREATE INDEX IF NOT EXISTS idx_check_region_releases_region   ON fims.check_region_releases (region_dept);
