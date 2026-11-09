-- One row = one Program (optionally scoped to a Sub-Program) added to a
-- budget request. Mirrors fims.budget_req_project's shape one level up —
-- a budget_req header (now one per fiscal year) can carry several of
-- these; fims.budget_req_project nests under this table instead of
-- directly under budget_req (see the 03 - alter_table scripts).
CREATE TABLE IF NOT EXISTS fims.budget_req_program (
    budget_req_program_id SERIAL PRIMARY KEY,
    budget_reference_number INTEGER NOT NULL REFERENCES fims.budget_req(budget_reference_number) ON DELETE CASCADE,
    program_code VARCHAR(20) NOT NULL REFERENCES fims.program(program_code),
    sub_program_code VARCHAR(20) REFERENCES fims.sub_program(sub_program_code)
);

CREATE INDEX IF NOT EXISTS idx_fims_budget_req_program_budget_reference_number ON fims.budget_req_program (budget_reference_number);
CREATE INDEX IF NOT EXISTS idx_fims_budget_req_program_program_code ON fims.budget_req_program (program_code);
