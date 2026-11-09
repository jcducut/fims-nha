CREATE TABLE fims.budget_revision (
  id                INTEGER GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  from_budget_req_id INTEGER       NOT NULL,   -- budget request (COB) the revision moves from
  to_budget_req_id   INTEGER       NULL,       -- budget request (COB) the revision moves to; NULL = none
  from_amount       NUMERIC(18,2)  NOT NULL,
  to_amount         NUMERIC(18,2)  NOT NULL,
  reason            TEXT           NULL,
  user_id           INTEGER        NOT NULL,
  status_id         INTEGER        NOT NULL,
  created_at        TIMESTAMP      NOT NULL DEFAULT NOW(),
  update_at         TIMESTAMP      NOT NULL DEFAULT NOW(),
  deleted_at        TIMESTAMP      NULL
);
