-- One row = one chart-of-accounts allocation under a PS/MOOE budget request
-- header (fims.budget_req). A header can carry several line items — this is
-- the "+ Add Line Item" repeatable detail the Budget Request form builds.
CREATE TABLE IF NOT EXISTS fims.budget_line_items (
    budget_line_item_id SERIAL PRIMARY KEY,
    budget_reference_number VARCHAR(20) NOT NULL REFERENCES fims.budget_req(budget_reference_number) ON DELETE CASCADE,
    account_id VARCHAR(10) NOT NULL REFERENCES fims.chart_of_accounts(account_id),
    requested_amount NUMERIC(15, 2) NOT NULL,
    particulars TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS idx_fims_budget_line_items_budget_reference_number ON fims.budget_line_items (budget_reference_number);
