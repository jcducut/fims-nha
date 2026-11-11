CREATE TABLE IF NOT EXISTS fims.project (
    project_code VARCHAR(30) PRIMARY KEY,
    project_name VARCHAR(255) NOT NULL,
    program_code VARCHAR(20) NOT NULL REFERENCES fims.program(program_code)
);
