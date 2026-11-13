CREATE TABLE IF NOT EXISTS fims.chart_of_accounts (
    account_id VARCHAR(10) PRIMARY KEY,
    account_code VARCHAR(30) NOT NULL UNIQUE,
    account_title VARCHAR(255) NOT NULL,
    account_type VARCHAR(50),
    parent_account_id VARCHAR(10) REFERENCES fims.chart_of_accounts(account_id),
    posting_allowed BOOLEAN NOT NULL DEFAULT FALSE,
    status VARCHAR(20) NOT NULL DEFAULT 'Active',
    source_document VARCHAR(255)
);
