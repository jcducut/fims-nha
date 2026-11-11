-- Lookup list of non-tax deductions Accounting can withhold from a disbursement voucher's gross
-- amount (e.g. contractor retention) — distinct from BIR withholding tax, which is its own
-- concept (see "17 - fims_atc_codes.sql" / "22 - fims_disbursement_voucher_atc_lines.sql"). One
-- row per deduction line references this by code (see
-- "21 - fims_disbursement_voucher_deductions.sql"). Only "Retention" is seeded for now; more
-- types can be added later without a schema change.
CREATE TABLE IF NOT EXISTS fims.deduction_types (
    deduction_type_id SERIAL PRIMARY KEY,
    code               VARCHAR(30) NOT NULL UNIQUE,
    name               VARCHAR(100) NOT NULL,
    description        VARCHAR(255),
    is_active          BOOLEAN NOT NULL DEFAULT TRUE
);

COMMENT ON TABLE fims.deduction_types IS 'Non-tax deductions a DV''s gross amount can be reduced by (e.g. contractor retention); separate from BIR withholding tax (fims.atc_codes)';

INSERT INTO fims.deduction_types (code, name, description) VALUES
    ('RETENTION', 'Retention', 'Withheld from contractor — released upon completion of work')
ON CONFLICT (code) DO UPDATE SET
    name        = EXCLUDED.name,
    description = EXCLUDED.description;
