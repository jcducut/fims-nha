-- Suppliers, contractors, consultants - ported from shared.vendors.
-- Differences from the shared version: vendor_code is UNIQUE, vendor_type is a
-- real FK to fims.vendor_types, and an updated_at column is added.
CREATE TABLE IF NOT EXISTS fims.vendors (
    vendor_id           SERIAL PRIMARY KEY,
    vendor_code         VARCHAR(50)  NOT NULL UNIQUE,
    vendor_name         VARCHAR(500) NOT NULL,
    vendor_type         VARCHAR(20)  REFERENCES fims.vendor_types(vendor_type),
    tin                 VARCHAR(50),
    address             TEXT,
    contact_person      VARCHAR(255),
    contact_number      VARCHAR(50),
    email               VARCHAR(255),
    bank_name           VARCHAR(255),
    bank_account_number VARCHAR(100),
    tax_classification  VARCHAR(50),
    is_vat_registered   BOOLEAN DEFAULT FALSE,
    status              VARCHAR(20) DEFAULT 'ACTIVE',
    created_at          TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE fims.vendors IS 'Suppliers, contractors, consultants - referenced by purchase orders';

-- vendors indexes (vendor_code is already indexed by its UNIQUE constraint)
CREATE INDEX IF NOT EXISTS idx_vendors_vendor_type       ON fims.vendors (vendor_type);
CREATE INDEX IF NOT EXISTS idx_vendors_status            ON fims.vendors (status);
CREATE INDEX IF NOT EXISTS idx_vendors_tin               ON fims.vendors (tin);
CREATE INDEX IF NOT EXISTS idx_vendors_is_vat_registered ON fims.vendors (is_vat_registered);
