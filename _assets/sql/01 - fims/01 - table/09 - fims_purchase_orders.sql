-- Purchase orders (standalone port of fmd.purchase_orders / fmd.purchase_order_items).
-- Not ported on purpose: parent purchase request, budget allocation, fund source,
-- fiscal period, workflow engine, payment workflow, delivery receipts. A PO here
-- links to fims.fiscal_year and, optionally, fims.program / fims.project by code.
-- Status is a plain column driven by PurchaseOrderService (no workflow engine).
CREATE TABLE IF NOT EXISTS fims.purchase_orders (
    po_id                  SERIAL PRIMARY KEY,
    po_number              VARCHAR(100) NOT NULL UNIQUE,
    po_date                DATE NOT NULL,
    fiscal_yr              SMALLINT NOT NULL REFERENCES fims.fiscal_year(fiscal_yr),
    program_code           VARCHAR(20) REFERENCES fims.program(program_code),
    project_code           VARCHAR(20) REFERENCES fims.project(project_code),
    expense_class          VARCHAR(20),
    vendor_id              INTEGER NOT NULL REFERENCES fims.vendors(vendor_id),
    -- vendor snapshot at time of PO (so later vendor edits don't rewrite history)
    vendor_name            VARCHAR(500),
    vendor_address         TEXT,
    vendor_tin             VARCHAR(50),
    contract_number        VARCHAR(100),
    delivery_place         TEXT,
    delivery_date_required DATE,
    delivery_terms         TEXT,
    payment_terms          TEXT,
    total_amount           NUMERIC(18, 2) NOT NULL DEFAULT 0,
    outstanding_amount     NUMERIC(18, 2) NOT NULL DEFAULT 0,
    particulars            TEXT,
    remarks                TEXT,
    status                 VARCHAR(20) NOT NULL DEFAULT 'DRAFT',
    rejection_reason       TEXT,
    submitted_at           TIMESTAMPTZ,
    submitted_by           INTEGER,
    approved_at            TIMESTAMPTZ,
    approved_by            INTEGER,
    rejected_at            TIMESTAMPTZ,
    rejected_by            INTEGER,
    created_by             INTEGER,
    created_at             TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_by             INTEGER,
    updated_at             TIMESTAMPTZ,
    CONSTRAINT chk_purchase_orders_status
        CHECK (status IN ('DRAFT', 'SUBMITTED', 'APPROVED', 'REJECTED', 'CANCELLED')),
    CONSTRAINT chk_purchase_orders_expense_class
        CHECK (expense_class IS NULL OR expense_class IN ('PS', 'MOOE', 'CAPITAL_OUTLAY', 'CO', 'OTHER')),
    CONSTRAINT chk_purchase_orders_total_amount
        CHECK (total_amount >= 0)
);

COMMENT ON TABLE  fims.purchase_orders IS 'Purchase orders raised against a vendor';
COMMENT ON COLUMN fims.purchase_orders.status IS 'DRAFT -> SUBMITTED -> APPROVED | REJECTED; DRAFT/SUBMITTED/REJECTED can be CANCELLED';
COMMENT ON COLUMN fims.purchase_orders.vendor_name IS 'Snapshot of fims.vendors.vendor_name when the PO was saved';

CREATE INDEX IF NOT EXISTS idx_purchase_orders_po_date      ON fims.purchase_orders (po_date);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_fiscal_yr    ON fims.purchase_orders (fiscal_yr);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_vendor_id    ON fims.purchase_orders (vendor_id);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_program_code ON fims.purchase_orders (program_code);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_project_code ON fims.purchase_orders (project_code);
CREATE INDEX IF NOT EXISTS idx_purchase_orders_status       ON fims.purchase_orders (status);

CREATE TABLE IF NOT EXISTS fims.purchase_order_items (
    po_item_id       SERIAL PRIMARY KEY,
    po_id            INTEGER NOT NULL REFERENCES fims.purchase_orders(po_id) ON DELETE CASCADE,
    line_number      INTEGER NOT NULL,
    item_description TEXT NOT NULL,
    unit_of_measure  VARCHAR(50) NOT NULL,
    quantity_ordered NUMERIC(18, 4) NOT NULL,
    unit_cost        NUMERIC(18, 2) NOT NULL,
    total_cost       NUMERIC(18, 2) GENERATED ALWAYS AS (ROUND(quantity_ordered * unit_cost, 2)) STORED,
    account_code     VARCHAR(30) REFERENCES fims.chart_of_accounts(account_code),
    remarks          TEXT,
    CONSTRAINT uq_purchase_order_items_line UNIQUE (po_id, line_number),
    CONSTRAINT chk_purchase_order_items_qty  CHECK (quantity_ordered > 0),
    CONSTRAINT chk_purchase_order_items_cost CHECK (unit_cost >= 0)
);

COMMENT ON TABLE fims.purchase_order_items IS 'Line items of a purchase order; total_cost is generated (qty x unit cost)';

CREATE INDEX IF NOT EXISTS idx_purchase_order_items_po_id ON fims.purchase_order_items (po_id);
