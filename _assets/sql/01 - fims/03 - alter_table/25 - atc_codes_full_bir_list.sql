-- ============================================================================
-- Replaces the small "representative set" of fims.atc_codes (see that table's
-- own doc comment in "01 - table/17 - fims_atc_codes.sql") with the full
-- official BIR Alphanumeric Tax Code list the client supplied. Upsert, not a
-- wipe-and-reload: a handful of codes (WB010/WB020, the original placeholder
-- generic goods/services rates) aren't in the official list and are left
-- alone rather than deleted — WB010 is referenced by existing demo data
-- (fims.disbursement_voucher_atc_lines), and deleting it would violate that
-- FK anyway. Where a code IS in both (e.g. WC010, WI150, WV010/WV020), this
-- overwrites the old placeholder description/rate with the official one.
--
-- tax_type (new) is the BIR grouping this list came tagged with: EWT
-- (Expanded Withholding Tax), OPT (Other Percentage Tax), WG (Withholding VAT
-- on Goods/Services). description is widened to TEXT — several of the
-- official descriptions (e.g. WC680, WC630) run well past the old VARCHAR(255).
-- ============================================================================
ALTER TABLE fims.atc_codes
ALTER COLUMN description TYPE TEXT;

ALTER TABLE fims.atc_codes
ADD COLUMN IF NOT EXISTS tax_type VARCHAR(10);

COMMENT ON COLUMN fims.atc_codes.tax_type IS 'BIR grouping this ATC code belongs to: EWT (Expanded Withholding Tax), OPT (Other Percentage Tax), WG (Withholding VAT on Goods/Services)';

INSERT INTO fims.atc_codes (atc_code, description, tax_rate, tax_type) VALUES
    ('WI010', 'Professional fees - individual (gross income did not exceed P3M for current year)', 5.00, 'EWT'),
    ('WI011', 'Professional fees - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC010', 'Professional fees - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC011', 'Professional fees - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI020', 'Professional entertainers - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI021', 'Professional entertainers - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC020', 'Professional entertainers - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC021', 'Professional entertainers - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI030', 'Professional athletes - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI031', 'Professional athletes - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC030', 'Professional athletes - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC031', 'Professional athletes - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI040', 'Directors/producers (movies, stage, radio, TV, musical) - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI041', 'Directors/producers (movies, stage, radio, TV, musical) - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC040', 'Directors/producers (movies, stage, radio, TV, musical) - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC041', 'Directors/producers (movies, stage, radio, TV, musical) - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI050', 'Management/technical consultants - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI051', 'Management/technical consultants - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC050', 'Management/technical consultants - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC051', 'Management/technical consultants - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI060', 'Business/bookkeeping agents and agencies - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI061', 'Business/bookkeeping agents and agencies - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC060', 'Business/bookkeeping agents and agencies - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC061', 'Business/bookkeeping agents and agencies - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI070', 'Insurance agents and adjusters - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI071', 'Insurance agents and adjusters - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC070', 'Insurance agents and adjusters - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC071', 'Insurance agents and adjusters - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI080', 'Other recipients of talent fees - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI081', 'Other recipients of talent fees - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC080', 'Other recipients of talent fees - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC081', 'Other recipients of talent fees - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI090', 'Director fees (non-employee) - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI091', 'Director fees (non-employee) - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WI100', 'Rentals on real/personal property - individual', 5.00, 'EWT'),
    ('WC100', 'Rentals on real/personal property - corporation', 5.00, 'EWT'),
    ('WI110', 'Cinematographic film rentals and other payments to resident individuals', 5.00, 'EWT'),
    ('WC110', 'Cinematographic film rentals and other payments to resident corporations', 5.00, 'EWT'),
    ('WI120', 'Income payments to certain contractors - individual', 2.00, 'EWT'),
    ('WC120', 'Income payments to certain contractors - corporation', 2.00, 'EWT'),
    ('WI130', 'Income distribution to beneficiaries of estates and trusts', 15.00, 'EWT'),
    ('WI139', 'Gross commissions/service fees of real estate service practitioners - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI140', 'Gross commissions/service fees of real estate service practitioners - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC139', 'Gross commissions/service fees of real estate service practitioners - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC140', 'Gross commissions/service fees of real estate service practitioners - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI151', 'Professional fees to medical practitioners paid by hospitals/clinics/HMOs - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WI150', 'Professional fees to medical practitioners paid by hospitals/clinics/HMOs - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC151', 'Professional fees to medical practitioners paid by hospitals/clinics/HMOs - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC150', 'Professional fees to medical practitioners paid by hospitals/clinics/HMOs - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI152', 'Payment by General Professional Partnerships (GPPs) to partners - gross income did not exceed P720,000', 5.00, 'EWT'),
    ('WI153', 'Payment by General Professional Partnerships (GPPs) to partners - gross income exceeds P720,000', 10.00, 'EWT'),
    ('WI156', 'Income payments made by credit card companies - individual', 1.00, 'EWT'),
    ('WC156', 'Income payments made by credit card companies - corporation', 1.00, 'EWT'),
    ('WC840', 'Income payments by GOCCs to local/resident suppliers of goods other than those covered by other rates of withholding tax', 1.00, 'EWT'),
    ('WC157', 'Income payments by GOCCs to local/resident suppliers of services other than those covered by other rates of withholding tax', 2.00, 'EWT'),
    ('WC158', 'Income payment by top withholding agents to local/resident supplier of goods other than those covered by other rates of withholding tax', 1.00, 'EWT'),
    ('WC160', 'Income payment by top withholding agents to local/resident supplier of services other than those covered by other rates of withholding tax', 2.00, 'EWT'),
    ('WI515', 'Commissions to independent/exclusive sales representatives and marketing agents - individual (gross income did not exceed P3M)', 5.00, 'EWT'),
    ('WB201', 'Tax on shares of stock sold or exchanged through initial and secondary public offering - not over 25% - 4%', 4.00, 'OPT'),
    ('WI516', 'Commissions to independent/exclusive sales representatives and marketing agents - individual (gross income is more than P3M or VAT-registered)', 10.00, 'EWT'),
    ('WC515', 'Commissions to independent/exclusive sales representatives and sub-agents - corporation (gross income did not exceed P720,000)', 10.00, 'EWT'),
    ('WC516', 'Commissions to independent/exclusive sales representatives and sub-agents - corporation (gross income exceeds P720,000)', 15.00, 'EWT'),
    ('WI530', 'Gross payments to embalmers by funeral parlors', 1.00, 'EWT'),
    ('WC535', 'Payments made by pre-need companies to funeral parlors', 1.00, 'EWT'),
    ('WI540', 'Tolling fees paid to refineries - individual', 5.00, 'EWT'),
    ('WC540', 'Tolling fees paid to refineries - corporation', 5.00, 'EWT'),
    ('WC610', 'Income payments to suppliers of agricultural products in excess of cumulative amount of P300,000 within the same taxable year', 1.00, 'EWT'),
    ('WC630', 'Income payments on purchases of minerals, mineral products and quarry (silver, gold, marble, granite, gravel, sand, boulders and other mineral products except purchases by Bangko Sentral ng Pilipinas)', 5.00, 'EWT'),
    ('WC632', 'Income payments on purchases of minerals and mineral products resources by Bangko Sentral ng Pilipinas (BSP) from gold miners/suppliers under PD 1899, as amended by RA No. 7076', 5.00, 'EWT'),
    ('WC650', 'Gross amount of refund given by MERALCO to customers with active contracts as classified by MERALCO', 2.00, 'EWT'),
    ('WC651', 'Gross amount of refund given by MERALCO to customers with terminated contracts as classified by MERALCO', 2.00, 'EWT'),
    ('WC660', 'Gross amount of interest on refund of meter deposit paid to Residential and General Service customers whose monthly electricity consumption does not exceed 200 kwh as classified by MERALCO', 6.00, 'EWT'),
    ('WC661', 'Gross amount of interest on refund of meter deposit paid to Non-Residential customers whose monthly electricity consumption exceeds 200 kwh as classified by MERALCO', 6.00, 'EWT'),
    ('WC662', 'Gross amount of interest on refund of meter deposit paid to Residential and General Service customers whose monthly electricity consumption does not exceed 200 kwh as classified by other electric Distribution Utilities (DU)', 6.00, 'EWT'),
    ('WC663', 'Gross amount of interest on refund of meter deposit paid to Non-Residential customers whose monthly electricity consumption exceeds 200 kwh as classified by other electric Distribution Utilities (DU)', 6.00, 'EWT'),
    ('WC680', 'Income payments by political parties and candidates of local and national elections on all their purchases of goods and services related to campaign expenditures, and income payments made by individuals or juridical persons for their purchases of goods and services intended to be given as campaign contributions to political parties and candidates', 5.00, 'EWT'),
    ('WC690', 'Income payments received by Real Estate Investment Trust (REIT)', 1.00, 'EWT'),
    ('WC710', 'Interest income derived from any other debt instruments not within the coverage of deposit substitutes and Revenue Regulations No. 14-2012', 20.00, 'EWT'),
    ('WI720', 'Income payments on locally produced raw sugar - individual', 0.00, 'EWT'),
    ('WC720', 'Income payments on locally produced raw sugar - corporation', 0.00, 'EWT'),
    ('WI555', 'Sale of Real Property (Ordinary Asset) - 1.50%', 1.50, 'EWT'),
    ('WI556', 'Sale of Real Property (Ordinary Asset) - 3%', 3.00, 'EWT'),
    ('WI557', 'Sale of Real Property (Ordinary Asset) - 5%', 5.00, 'EWT'),
    ('WI558', 'Sale of Real Property (Ordinary Asset) - 6%', 6.00, 'EWT'),
    ('WC555', 'Sale of Real Property (Ordinary Asset) - corporation 1.50%', 1.50, 'EWT'),
    ('WC556', 'Sale of Real Property (Ordinary Asset) - corporation 3%', 3.00, 'EWT'),
    ('WC557', 'Sale of Real Property (Ordinary Asset) - corporation 5%', 5.00, 'EWT'),
    ('WC558', 'Sale of Real Property (Ordinary Asset) - corporation 6%', 6.00, 'EWT'),
    ('WB080', 'Persons Exempt from VAT under Sec. 109BB (creditable) - Government Withholding Agent', 3.00, 'EWT'),
    ('WB082', 'Persons Exempt from VAT under Sec. 109BB (creditable) - Private Withholding Agent', 3.00, 'EWT'),
    ('WV010', 'Final Withholding VAT (FWVAT) on payments for purchases of Goods', 5.00, 'WG'),
    ('WV020', 'Final Withholding VAT (FWVAT) on payments for purchases of Services', 5.00, 'WG'),
    ('WB030', 'Tax on Carriers and Keepers of Garages', 3.00, 'OPT'),
    ('WB040', 'Franchise Tax on Gas and Utilities', 2.00, 'OPT'),
    ('WB050', 'Franchise Tax on radio & TV broadcasting companies whose annual gross receipts do not exceed P10M and who are not VAT registered taxpayers', 3.00, 'OPT'),
    ('WB070', 'Tax on Life Insurance Premiums', 5.00, 'OPT'),
    ('WB090', 'Tax on Overseas Dispatch, Message or Conversation from the Philippines', 10.00, 'OPT'),
    ('WB301', 'Tax on Banks and Non-Bank Financial Intermediaries Performing Quasi-Banking Functions - interest/commissions/discounts - maturity period is five years or less - 5%', 5.00, 'OPT'),
    ('WB303', 'Tax on Banks and Non-Bank Financial Intermediaries Performing Quasi-Banking Functions - dividends and equity shares and net income of subsidiaries - 0%', 0.00, 'OPT'),
    ('WB103', 'Tax on Banks and Non-Bank Financial Intermediaries Performing Quasi-Banking Functions - royalties, rentals of property, real or personal, profits from exchange and all other items treated as gross income under the Code - 7%', 7.00, 'OPT'),
    ('WB104', 'Tax on Banks and Non-Bank Financial Intermediaries Performing Quasi-Banking Functions - net trading gains within the taxable year on foreign currency, debt securities, derivatives and similar financial instruments - 7%', 7.00, 'OPT'),
    ('WB108', 'Tax on Other Non-Bank Financial Intermediaries not Performing Quasi-Banking Functions - interest/commissions/discounts - maturity period is five years or less - 5%', 5.00, 'OPT'),
    ('WB109', 'Tax on Other Non-Bank Financial Intermediaries not Performing Quasi-Banking Functions - maturity period is more than five years - 1%', 1.00, 'OPT'),
    ('WB110', 'Tax on Other Non-Bank Financial Intermediaries not Performing Quasi-Banking Functions - all other items treated as gross income under the code - 5%', 5.00, 'OPT'),
    ('WB120', 'Business Tax on Agents of Foreign Insurance Companies - Insurance Age', 5.00, 'OPT'),
    ('WB121', 'Business Tax on Agents of Foreign Insurance Companies - Owner of the Property', 5.00, 'OPT'),
    ('WB130', 'Tax on International Carriers', 2.50, 'OPT'),
    ('WB140', 'Tax on Cockpits', 18.00, 'OPT'),
    ('WB150', 'Tax on amusement places such as cabarets, night and day clubs, videoke bars, karaoke bars, karaoke television, karaoke boxes, music lounges and other similar establishments', 18.00, 'OPT'),
    ('WB160', 'Tax on Boxing exhibitions', 10.00, 'OPT'),
    ('WB170', 'Tax on Professional Basketball games', 15.00, 'OPT'),
    ('WB180', 'Tax on jai-alai and race tracks', 30.00, 'OPT'),
    ('WB200', 'Tax on sale, barter or exchange of stocks listed and traded through Local Stock Exchange', 0.60, 'OPT'),
    ('WB202', 'Tax on shares of stock sold or exchanged through initial and secondary public offering - over 25% but not exceeding 33 1/3% - 2%', 2.00, 'OPT'),
    ('WB203', 'Tax on shares of stock sold or exchanged through initial and secondary public offering - over 33 1/3% - 1%', 1.00, 'OPT')
ON CONFLICT (atc_code) DO UPDATE SET
    description = EXCLUDED.description,
    tax_rate    = EXCLUDED.tax_rate,
    tax_type    = EXCLUDED.tax_type;

-- Same default this project's atc_codes have used since "18 - deduction_types_atc_codes_add_account_code.sql"
-- (all withholding here posts to Due to BIR until Accounting reclassifies it).
UPDATE fims.atc_codes SET account_code = '2-02-01-010' WHERE account_code IS NULL;
