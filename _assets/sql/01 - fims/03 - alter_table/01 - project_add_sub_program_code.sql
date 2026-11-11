-- ============================================================
-- ALTER: fims.project
-- Adds sub_program_code — projects can now optionally belong to a
-- sub-program under their program, matching the Budget Request form's
-- Program -> Sub-Program -> Project cascade. There is no longer a
-- standalone "required" Project page, so this is the main place
-- sub_program_code gets populated going forward.
--
-- Nullable: pre-existing projects and projects created without picking
-- a sub-program remain valid.
-- ============================================================

ALTER TABLE fims.project
    ADD COLUMN IF NOT EXISTS sub_program_code VARCHAR(20) REFERENCES fims.sub_program(sub_program_code);

COMMENT ON COLUMN fims.project.sub_program_code IS
    'Optional sub-program this project belongs to. Must belong to the same program_code when set (enforced in ProjectService, not a DB constraint).';

CREATE INDEX IF NOT EXISTS idx_fims_project_sub_program_code ON fims.project (sub_program_code);
