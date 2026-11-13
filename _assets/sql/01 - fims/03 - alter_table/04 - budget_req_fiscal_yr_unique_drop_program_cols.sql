-- ALTER: fims.budget_req
-- 1) One budget_req header per fiscal year. Dev data only, no
--    preservation needed — collapse any pre-existing duplicate-fiscal-year
--    headers to one per year (lowest budget_reference_number wins) before
--    adding the UNIQUE constraint; this cascades away their
--    budget_req_project/budget_line_items children too.
-- 2) program_code / sub_program_code move to fims.budget_req_program.
DELETE FROM fims.budget_req br
USING (
    SELECT budget_reference_number,
           ROW_NUMBER() OVER (PARTITION BY fiscal_yr ORDER BY budget_reference_number) AS rn
    FROM fims.budget_req
) dup
WHERE br.budget_reference_number = dup.budget_reference_number
  AND dup.rn > 1;

ALTER TABLE fims.budget_req
    ADD CONSTRAINT uq_budget_req_fiscal_yr UNIQUE (fiscal_yr);

ALTER TABLE fims.budget_req
    DROP COLUMN IF EXISTS program_code,
    DROP COLUMN IF EXISTS sub_program_code;
