-- Multiple sub-programs can belong to one program — added via the "+ Add Line
-- Item" repeatable builder on the Sub-Program form, submitted together in one
-- save (program_code picked once, several sub_program rows created at once).
CREATE TABLE IF NOT EXISTS fims.sub_program (
    sub_program_code VARCHAR(20) PRIMARY KEY,
    sub_program_name VARCHAR(255) NOT NULL,
    program_code VARCHAR(20) NOT NULL REFERENCES fims.program(program_code)
);
