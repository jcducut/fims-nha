-- Seeds the offices/roles that appear in the Budget Preparation approval flow:
-- CPD (Planning) -> Originating Office -> FMD Fund Sourcing -> FMD Budget
-- Preparation (Budget Officer) -> FMD Reviewer (Division Manager) -> FMD
-- Department Manager -> NHA Management / Authorized Approver -> FMD Records (FIMS).
--
-- "Departments / Regional Offices / District Offices (Originating Offices)" and
-- "FMD - Records / System (FIMS)" are NOT seeded as rows here: the former is any
-- already-existing requesting department (not a new org unit), and the latter is
-- the system of record itself (FIMS), not a human office.

-- -----------------------------------------------------------------------------
-- Offices (uac.departments)
-- -----------------------------------------------------------------------------
INSERT INTO uac.departments (department_name, department_code, department_acronym, department_type, hierarchy_level, is_budget_unit, description)
SELECT 'Planning Division', 'CPD', 'CPD', 'Division', 1, false, 'Originates/plans projects and programs that feed budget requests'
WHERE NOT EXISTS (SELECT 1 FROM uac.departments WHERE department_code = 'CPD');

INSERT INTO uac.departments (department_name, department_code, department_acronym, department_type, hierarchy_level, is_budget_unit, description)
SELECT 'Fund Management Division', 'FMD', 'FMD', 'Division', 1, true, 'Handles fund sourcing, budget preparation, review, and approval routing'
WHERE NOT EXISTS (SELECT 1 FROM uac.departments WHERE department_code = 'FMD');

-- -----------------------------------------------------------------------------
-- Roles (uac.user_levels) — positive IDs; -2/-1/0 are reserved
-- (AdvancedSecurity::ANONYMOUS_USER_LEVEL_ID / ADMIN_USER_LEVEL_ID / DEFAULT_USER_LEVEL_ID)
-- -----------------------------------------------------------------------------
INSERT INTO uac.user_levels (user_level_id, name, description, system_id) VALUES
    (1, 'FMD Fund Sourcing Officer', 'Identifies/certifies the available fund source for a budget request (FMD - Fund Sourcing step)', NULL),
    (2, 'Budget Officer',            'Prepares the budget request (FMD - Budget Preparation step)', NULL),
    (3, 'FMD Division Manager',      'Reviews the prepared budget at section/division level (FMD - Reviewer step)', NULL),
    (4, 'FMD Department Manager',    'Approves the budget request at department level before escalation to NHA Management', NULL),
    (5, 'FSG Group Manager',         'Authorized approver for budget requests within the FSG Group Manager threshold', NULL),
    (6, 'General Manager',           'Authorized approver for budget requests exceeding the FSG Group Manager threshold', NULL),
    (7, 'NHA Board',                 'Authorized approver (Board) for budget requests exceeding the General Manager threshold', NULL),
    (8, 'Budget Head',               'Heads the budget function within the Fund Management Division (FMD)', NULL)
ON CONFLICT (user_level_id) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;
