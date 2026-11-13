-- Fund tracking records (SARO / NCA / TRUST / OR ...), one row per document.
-- parent_id links a record to the record it was released/derived from
-- (self-reference); multiple_node_id groups rows that were saved together.
--   tracking_type_id   -> fims.fund_tracking_type.fund_tracking_type_id
--   nature_of_fund_id  -> fims.nature_of_fund.nature_of_fund_id
--   status_id          -> fims.settings_status.id
-- No FOREIGN KEY constraints are declared here (none were specified); add them
-- in a later "alter_table" file if wanted.
-- Soft-deleted via deleted_at.
CREATE TABLE IF NOT EXISTS fims.fund_tracking (
    id SERIAL PRIMARY KEY,
    fiscal_year INT4,
    tracking_type_id INT4,
    reference_number VARCHAR(225),
    sub_program_code VARCHAR(225),
    nature_of_fund_id INT4,
    name VARCHAR(225),
    date DATE,
    amount NUMERIC(18,2),
    unreleased NUMERIC(18,2),
    bank_account_no VARCHAR(225),
    agency_name VARCHAR(225),
    remarks TEXT,
    purpose TEXT,
    multiple_node_id UUID,
    status_id INT4,
    parent_id INT4,
    user_id INT4,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP
);

-- fund_tracking indexes
CREATE INDEX IF NOT EXISTS idx_fund_tracking_fiscal_year ON fims.fund_tracking (fiscal_year);
CREATE INDEX IF NOT EXISTS idx_fund_tracking_tracking_type_id ON fims.fund_tracking (tracking_type_id);
CREATE INDEX IF NOT EXISTS idx_fund_tracking_nature_of_fund_id ON fims.fund_tracking (nature_of_fund_id);
CREATE INDEX IF NOT EXISTS idx_fund_tracking_parent_id ON fims.fund_tracking (parent_id);
CREATE INDEX IF NOT EXISTS idx_fund_tracking_multiple_node_id ON fims.fund_tracking (multiple_node_id);
CREATE INDEX IF NOT EXISTS idx_fund_tracking_deleted_at ON fims.fund_tracking (deleted_at);
