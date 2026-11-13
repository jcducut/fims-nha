-- BIR Alphanumeric Tax Codes (ATC) — a small representative set of the expanded
-- withholding tax / VAT-withholding codes a government agency's Accounting office
-- actually applies to supplier/contractor payments (not the full BIR table).
-- Referenced by fims.disbursement_voucher_atc_lines.atc_code (one row per withholding
-- classification a DV carries — see "22 - fims_disbursement_voucher_atc_lines.sql"),
-- set when Accounting prepares the voucher's Journal Entry Voucher (see
-- "18 - fims_journal_entry_vouchers.sql"). Originally a single
-- fims.disbursement_vouchers.atc_code column; made multi-row in
-- "03 - alter_table/17 - disbursement_vouchers_drop_tax_fields_add_sl.sql" since one DV
-- can withhold under more than one ATC. tax_rate is a reference default only — each
-- ATC line's own rate is what's actually applied; the two can differ if BIR's published
-- rate changed.
CREATE TABLE IF NOT EXISTS fims.atc_codes (
    atc_code    VARCHAR(10) PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    tax_rate    NUMERIC(5,2),
    is_active   BOOLEAN NOT NULL DEFAULT TRUE
);

INSERT INTO fims.atc_codes (atc_code, description, tax_rate) VALUES
    ('WB010', 'Income payments on purchase of goods',                 1.00),
    ('WB020', 'Income payments on purchase of services',              2.00),
    ('WC010', 'Professional fees - talent fees (individual payee)',  10.00),
    ('WC100', 'Professional fees (non-individual/corporate payee)',  15.00),
    ('WI010', 'Rental of real/personal property',                     5.00),
    ('WI150', 'Income payments to certain contractors',                2.00),
    ('WV010', 'VAT withholding - purchase of goods (government)',     5.00),
    ('WV020', 'VAT withholding - purchase of services (government)', 5.00)
ON CONFLICT (atc_code) DO UPDATE SET
    description = EXCLUDED.description,
    tax_rate = EXCLUDED.tax_rate;
