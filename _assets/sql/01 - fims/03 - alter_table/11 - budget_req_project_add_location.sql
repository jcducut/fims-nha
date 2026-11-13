-- ALTER: fims.budget_req_project
-- Each Project block within a budget request can now optionally record a
-- Location (free text) alongside its project_code — e.g. the site/address a
-- project applies to, independent of the Project's own master-data name.
ALTER TABLE fims.budget_req_project
    ADD COLUMN IF NOT EXISTS location VARCHAR(255);
