-- Inspection and Acceptance Report (IAR) Module: ONE table.
--   fims.inspection_acceptance_report   one row per PO: po_number, user-entered iar_number (required, unique),
--                                       Sales Invoice # and date, and line_transaction (JSONB) holding the PO's
--                                       Delivery Receipts (DR).
-- The PO is picked from the Budget Allocation (an RFA's PO number) by the app; there is no DB FK to it.
-- A DR's total may not exceed the PO balance (PO amount - the totals of the PO's other DRs): checked by the app.
--
-- line_transaction is an ARRAY with one element per delivery receipt (the same idea as
-- fims.budget_allocation.line_transaction):
--   [ { "dr_number": "DR-001", "delivery_date": "2026-11-12", "total_amount": "800000.00",
--       "lines": [ { "account_code": "1-06-05-030", "account_title": "Information and Communications Technology Equipment",
--                    "amount": "600000.00" },
--                  { "account_code": "5-02-03-990", "account_title": "Other Supplies and Materials Expenses", "amount": "200000.00" } ],
--       "items": [ { "description": "...", "serial_no": "...", "amount": "...", "property_no": "..." } ] } ]
--   "total_amount" = the sum of the line amounts. "items" (any number, one line each) is present only when the total is >= 50,000
--   and at least one item was entered. A dr_number is unique within the PO and a serial_no is unique across all items of all
--   delivery receipts (both checked by the app).
--
-- If you created an earlier version of these tables locally, drop it first so this script recreates them:
--   DROP TABLE IF EXISTS fims.po_delivery_receipt_entry CASCADE;
--   DROP TABLE IF EXISTS fims.po_delivery_receipt CASCADE;
--   DROP TABLE IF EXISTS fims.iar_module CASCADE;
-- (older names, if they exist: fims.iar_items, fims.iar, fims.air_module_iar_items, fims.air_module_iar, fims.air_module_po)

CREATE TABLE IF NOT EXISTS fims.inspection_acceptance_report (
    iar_id           SERIAL PRIMARY KEY,
    iar_number       VARCHAR(50)  NOT NULL,             -- typed by the user (required, unique)
    po_number        VARCHAR(100) NOT NULL,             -- the RFA PO number picked from the Budget Allocation
    si_number        VARCHAR(100),                      -- Sales Invoice # (optional)
    si_date          DATE,                              -- Sales Invoice date (optional)
    line_transaction JSONB        NOT NULL DEFAULT '[]'::jsonb,   -- the PO's delivery receipts, see above
    created_by       INTEGER,
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by       INTEGER,
    updated_at       TIMESTAMPTZ,
    CONSTRAINT uq_inspection_acceptance_report_iar_number UNIQUE (iar_number),
    CONSTRAINT uq_inspection_acceptance_report_po_number  UNIQUE (po_number),
    CONSTRAINT chk_inspection_acceptance_report_lines_array CHECK (jsonb_typeof(line_transaction) = 'array')
);
COMMENT ON TABLE  fims.inspection_acceptance_report IS 'Inspection and Acceptance Report per PO number: IAR number, Sales Invoice details and the PO''s delivery receipts (line_transaction)';
COMMENT ON COLUMN fims.inspection_acceptance_report.iar_number IS 'IAR number entered by the user (unique)';
COMMENT ON COLUMN fims.inspection_acceptance_report.line_transaction IS 'Array of delivery receipts: {dr_number, delivery_date, total_amount, lines:[{account_code, account_title, amount}], items?:[{description, serial_no, amount, property_no, par_no}]}; items only when total_amount >= 50,000';

CREATE INDEX IF NOT EXISTS idx_iar_line_transaction ON fims.inspection_acceptance_report USING GIN (line_transaction);
