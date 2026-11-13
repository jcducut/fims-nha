
INSERT INTO fims.program (program_code, program_name) VALUES
    ('100', 'RESETTLEMENT'),
    ('101', 'HOUSING ASSISTANCE FOR CALAMITY VICTIMS')
ON CONFLICT (program_code) DO NOTHING;

INSERT INTO fims.sub_program (sub_program_code, sub_program_name, program_code) VALUES
    ('100-001', 'FOR ISFs Affected by Infrastructure', '100'),
    ('100-002', 'FOR ISFs Affected by the Supreme Court''s Mandamus to Clean-up the Manila Bay Area', '100'),
    ('101-001', 'Taal Eruption', '101')
ON CONFLICT (sub_program_code) DO NOTHING;

INSERT INTO fims.project (project_code, project_name, program_code, sub_program_code) VALUES
    ('2026-R03-100-0001-0001', 'Casa Teodora Phase 1, Brgy. Langkiwa Biñan City, Laguna', '100', '100-001'),
    ('2026-R03-100-0001-0002', 'Casa Teodora Phase 2, Brgy. Langkiwa Biñan City, Laguna', '100', '100-001'),
    ('2026-R03-100-0001-0003', 'Casa Crisostomo Resettlement Project Phase 2, Brgy. Banlic, Calamba City, Laguna', '100', '100-001'),
    ('2026-R03-100-0002-0001', 'Ciudad Kaunlaran Phase 2C, Bacoor Cavite', '100', '100-002'),
    ('2026-R03-100-0002-0002', 'Glori-Mor Housing Project 2, Naic, Cavite', '100', '100-002'),
    ('2026-R03-100-0002-0003', 'Naic View Residences Phase 2, Brgy. Calubcob, Naic, Cavite', '100', '100-002'),
    ('2026-R03-101-0001-0001', 'New Direction Neighbourhood, Brgy. Aya, Talisay, Batangas (Talisay Residences, Brgy. Trance, Talisay, Batangas)', '101', '101-001')
ON CONFLICT (project_code) DO NOTHING;
