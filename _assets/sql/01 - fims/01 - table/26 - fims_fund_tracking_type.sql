-- Lookup of fund tracking document classifications:
--   SARO  (Special Allotment Release Order)
--   NCA   (Notice of Cash Allocation)
--   TRUST (Trust Fund)
--   OR    (Obligations Request)
-- Referenced by fims.fund_tracking.tracking_type_id.
-- GAAAO and SUPPLEMENTAL are intentionally NOT tracking types — they are
-- natures of fund (see fims.nature_of_fund).
-- Soft-deleted via deleted_at, same as fims.type_of_fund.
CREATE TABLE IF NOT EXISTS fims.fund_tracking_type (
    fund_tracking_type_id SERIAL PRIMARY KEY,
    fund_tracking_code VARCHAR(50) NOT NULL,
    fund_tracking_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMPTZ
);

-- fund_tracking_type indexes
CREATE INDEX IF NOT EXISTS idx_fund_tracking_type_fund_tracking_code ON fims.fund_tracking_type (fund_tracking_code);
CREATE INDEX IF NOT EXISTS idx_fund_tracking_type_deleted_at ON fims.fund_tracking_type (deleted_at);

-- Seed: guarded so re-running this file doesn't duplicate rows, since
-- fund_tracking_code has no unique constraint. ORDER BY ord makes the rows go
-- in exactly this order, so a fresh table gets ids 1-4 (SARO=1, NCA=2,
-- TRUST=3, OR=4) like the original shared.fund_tracking_type seed — without
-- it Postgres is free to insert them (and hand out the serial ids) in any order.
INSERT INTO fims.fund_tracking_type (fund_tracking_code, fund_tracking_name)
SELECT v.code, v.name
FROM (VALUES
    (1, 'SARO',  'Special Allotment Release Order'),
    (2, 'NCA',   'Notice of Cash Allocation'),
    (3, 'TRUST FUND', 'Trust Fund'),
    (4, 'OR',    'Official Receipt')
) AS v(ord, code, name)
WHERE NOT EXISTS (SELECT 1 FROM fims.fund_tracking_type t WHERE t.fund_tracking_code = v.code)
ORDER BY v.ord;
