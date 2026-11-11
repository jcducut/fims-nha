-- Dummy Program / Sub-Program data for exercising the Budget Request flow
-- (BudgetReqService::addProgram() requires an existing program_code, and
-- optionally a sub_program_code belonging to it).
INSERT INTO fims.program (program_code, program_name) VALUES
    ('PROG-001', 'Sample Housing Program'),
    ('PROG-002', 'Sample Resettlement Program')
ON CONFLICT (program_code) DO NOTHING;

INSERT INTO fims.sub_program (sub_program_code, sub_program_name, program_code) VALUES
    ('SUBPROG-001', 'Sample Housing Sub-Program A', 'PROG-001'),
    ('SUBPROG-002', 'Sample Housing Sub-Program B', 'PROG-001'),
    ('SUBPROG-003', 'Sample Resettlement Sub-Program A', 'PROG-002')
ON CONFLICT (sub_program_code) DO NOTHING;
