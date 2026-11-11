-- Master list of valid vendor categories (payees / service providers).
-- Ported from shared.vendor_types; vendor_type is UNIQUE because fims.vendors
-- references it by value (FK), the same way shared.vendors stores the code.
CREATE TABLE IF NOT EXISTS fims.vendor_types (
    vendor_type_id SERIAL PRIMARY KEY,
    vendor_type    VARCHAR(20) NOT NULL UNIQUE,
    description    TEXT,
    is_active      BOOLEAN NOT NULL DEFAULT TRUE,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

COMMENT ON TABLE  fims.vendor_types IS 'Master table for valid vendor types - defines categories of payees and service providers';
COMMENT ON COLUMN fims.vendor_types.vendor_type IS 'Vendor type code - SUPPLIER, CONTRACTOR, CONSULTANT, SERVICE_PROVIDER or EMPLOYEE';
COMMENT ON COLUMN fims.vendor_types.is_active IS 'Whether this vendor type can currently be assigned to a vendor';

-- Seed: the five codes used by shared.vendor_types. Idempotent.
INSERT INTO fims.vendor_types (vendor_type, description) VALUES
    ('SUPPLIER',         'Supplies goods and materials'),
    ('CONTRACTOR',       'Construction and infrastructure contractors'),
    ('CONSULTANT',       'Consulting and advisory services'),
    ('SERVICE_PROVIDER', 'Non-consulting services (maintenance, security, utilities, etc.)'),
    ('EMPLOYEE',         'Employees paid as payees (reimbursements, cash advances)')
ON CONFLICT (vendor_type) DO NOTHING;
