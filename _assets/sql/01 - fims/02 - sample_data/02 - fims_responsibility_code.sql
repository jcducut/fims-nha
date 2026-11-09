-- Seed data: NHA Responsibility Center (RC) Listing.
-- Idempotent via ON CONFLICT on rc_id (PK). Rows are inserted parent-first
-- so the parent_rc_id self-reference always resolves.
INSERT INTO fims.responsibility_code (rc_id, rc_code, rc_name, parent_rc_id, office_type, status, source_document, is_budget_holding_rc) VALUES
('RC-000', '35-074-0000000-000', 'National Housing Authority', NULL, 'Agency', 'Active', 'NHA RC Listing', TRUE),

('RC-001', '35-074-0000000-010', 'Office of the General Manager', 'RC-000', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-002', '35-074-0000000-011', 'Office of the Assistant General Manager', 'RC-000', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-003', '35-074-0000000-012', 'Office of the Corporate Secretary (OCS)', 'RC-000', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-004', '35-074-0000000-013', 'Legal Department', 'RC-000', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-005', '35-074-0000000-014', 'Internal Audit Department', 'RC-000', 'Department', 'Active', 'NHA RC Listing', TRUE),

('RC-006', '35-074-0000000-020', 'Management Services Group', 'RC-000', 'Group', 'Active', 'NHA RC Listing', TRUE),
('RC-007', '35-074-0000000-021', 'Corporate Planning Department (CPD)', 'RC-006', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-008', '35-074-0000000-022', 'Corporate Operations and Systems Development Department (COSDD)', 'RC-006', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-009', '35-074-0000000-023', 'Human Resource Management Department (HRMD)', 'RC-006', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-010', '35-074-0000000-024', 'General Services Department (GSD)', 'RC-006', 'Department', 'Active', 'NHA RC Listing', TRUE),

('RC-011', '35-074-0000000-030', 'Financial Services Group (FSG)', 'RC-000', 'Group', 'Active', 'NHA RC Listing', TRUE),
('RC-012', '35-074-0000000-031', 'Accounting Department', 'RC-011', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-013', '35-074-0000000-032', 'Financial Management Department (FMD)', 'RC-011', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-014', '35-074-0000000-033', 'Treasury Department', 'RC-011', 'Department', 'Active', 'NHA RC Listing', TRUE),

('RC-015', '35-074-0000000-040', 'Housing Support Services Group (HSSG)', 'RC-000', 'Group', 'Active', 'NHA RC Listing', TRUE),
('RC-016', '35-074-0000000-041', 'Community Support Services Department (CSSD)', 'RC-015', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-017', '35-074-0000000-042', 'Resettlement and Development Services Department (RDSD)', 'RC-015', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-018', '35-074-0000000-043', 'Estate Management Department (EMD)', 'RC-015', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-019', '35-074-0000000-044', 'Commercial and Industrial Estates Department (CIED)', 'RC-015', 'Department', 'Active', 'NHA RC Listing', TRUE),
('RC-020', '35-074-0000000-045', 'Project Operations Support Services Department (POSSD)', 'RC-015', 'Department', 'Active', 'NHA RC Listing', TRUE),

('RC-021', '35-074-0000001-000', 'Region 1 & CAR 1 Regional Office', 'RC-000', 'Regional', 'Active', 'NHA RC Listing', TRUE),
('RC-022', '35-074-0000001-001', 'Region 1 - Abra/La Union/Ilocos Norte/Ilocos Sur District Office', 'RC-021', 'District', 'Active', 'NHA RC Listing', TRUE),
('RC-023', '35-074-0000001-002', 'Region 1 - Mt. Province/Benguet/Pangasinan District Office', 'RC-021', 'District', 'Active', 'NHA RC Listing', TRUE),

('RC-024', '35-074-0000002-000', 'Region 2 & CAR 2 Regional Office', 'RC-000', 'Regional', 'Active', 'NHA RC Listing', TRUE),
('RC-025', '35-074-0000002-001', 'Ifugao/Nueva Vizcaya/Quirino/Isabela District Office', 'RC-024', 'District', 'Active', 'NHA RC Listing', TRUE),
('RC-026', '35-074-0000002-002', 'Region 2 - Cagayan/Apayao/Kalinga/Batanes District Office', 'RC-024', 'District', 'Active', 'NHA RC Listing', TRUE),

('RC-027', '35-074-0000003-000', 'Region 3 Regional Office', 'RC-000', 'Regional', 'Active', 'NHA RC Listing', TRUE),
('RC-028', '35-074-0000003-001', 'Region 3 - Pampanga/Bataan/Zambales District Office', 'RC-027', 'District', 'Active', 'NHA RC Listing', TRUE),
('RC-029', '35-074-0000003-002', 'Region 3 - Bulacan District Office', 'RC-027', 'District', 'Active', 'NHA RC Listing', TRUE),
('RC-030', '35-074-0000003-003', 'Region 3 - Tarlac/Nueva Ecija/Aurora District Office', 'RC-027', 'District', 'Active', 'NHA RC Listing', TRUE),

('RC-031', '35-074-0000004-000', 'Region 4 Regional Office', 'RC-000', 'Regional', 'Active', 'NHA RC Listing', TRUE),
('RC-032', '35-074-0000004-001', 'Region 4 - Rizal District Office', 'RC-031', 'District', 'Active', 'NHA RC Listing', TRUE),
('RC-033', '35-074-0000004-002', 'Region 4 - Laguna District Office', 'RC-031', 'District', 'Active', 'NHA RC Listing', TRUE),
('RC-034', '35-074-0000004-003', 'Region 4 - Cavite District Office', 'RC-031', 'District', 'Active', 'NHA RC Listing', TRUE),
('RC-035', '35-074-0000004-004', 'Region 4 - Batangas/Quezon District Office', 'RC-031', 'District', 'Active', 'NHA RC Listing', TRUE)

ON CONFLICT (rc_id) DO NOTHING;
