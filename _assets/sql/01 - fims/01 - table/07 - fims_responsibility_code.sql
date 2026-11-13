CREATE TABLE IF NOT EXISTS fims.responsibility_code (
    rc_id VARCHAR(10) PRIMARY KEY,
    rc_code VARCHAR(30) NOT NULL UNIQUE,
    rc_name VARCHAR(255) NOT NULL,
    parent_rc_id VARCHAR(10) REFERENCES fims.responsibility_code(rc_id),
    office_type VARCHAR(50),
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    source_document VARCHAR(255),
    is_budget_holding_rc BOOLEAN NOT NULL DEFAULT FALSE
);
