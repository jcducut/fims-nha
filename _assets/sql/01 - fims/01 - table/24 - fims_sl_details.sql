-- Detail breakdown within one Subsidiary Ledger master (see "23 - fims_sl_masters.sql") — the
-- General Form No. 1's "SL Detail" column. E.g. a vendor's AP master split by PO/contract, or a
-- cash master split by sub-account.
CREATE TABLE IF NOT EXISTS fims.sl_details (
    sl_detail_id SERIAL PRIMARY KEY,
    sl_code      VARCHAR(30) NOT NULL REFERENCES fims.sl_masters(sl_code) ON DELETE CASCADE,
    detail_code  VARCHAR(30) NOT NULL,
    detail_name  VARCHAR(255) NOT NULL,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    UNIQUE (sl_code, detail_code)
);

COMMENT ON TABLE fims.sl_details IS 'Detail breakdown within one Subsidiary Ledger master, e.g. a vendor AP master split by PO/contract';

-- INSERT INTO fims.sl_details (sl_code, detail_code, detail_name) VALUES
--     ('SL-AP-DVDEMO01',  'PO-GSD-2023-09-103', 'PO-GSD-2023-09-103 — Toyota Innova Spare Parts'),
--     ('SL-AP-DVDEMO01',  'PO-GSD-2024-01-050', 'PO-GSD-2024-01-050 — Vehicle Repair Services'),
--     ('SL-AP-GENERAL',   'WALKIN',             'Walk-in Vendors'),
--     ('SL-AP-GENERAL',   'PETTY',              'Petty Vendors'),
--     ('SL-CASH-MDS01',   'REGULAR',            'Regular MDS Sub-Account'),
--     ('SL-CASH-MDS01',   'TRUST',              'Trust Fund Sub-Account'),
--     ('SL-DUEBIR-WTAX',  'EWT',                'Expanded Withholding Tax'),
--     ('SL-DUEBIR-WTAX',  'VAT-WH',             'VAT Withholding'),
--     ('SL-RETENTION-01', 'RET-10',             '10% Retention — Civil Works'),
--     ('SL-RETENTION-01', 'RET-05',             '5% Retention — Supply Contracts')
-- ON CONFLICT (sl_code, detail_code) DO UPDATE SET
--     detail_name = EXCLUDED.detail_name;

CREATE INDEX IF NOT EXISTS idx_sl_details_sl_code ON fims.sl_details (sl_code);
