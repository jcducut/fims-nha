-- One row = one Approved budget_allocation (CBA / RFA / LAA / CFA) line "holding" an amount against a
-- fims.budget_line_items row's balance, the moment that request reaches Approved — reserved only; it
-- does NOT touch budget_line_items.obligation / disbursement / account_payable (a later, harder
-- commitment stage), and it does NOT reduce budget_line_items.suggested_amount itself.
--
-- The ceiling is suggested_amount, NOT proposed_amount: proposed_amount is only what the requesting
-- office originally asked for, while suggested_amount is what FMD actually approved for this line item
-- during Budget Processing (see app/pages/form/budget-processing-form.php) — the real, spendable amount.
--
-- A line item's balance still available to earmark = suggested_amount + adjustment - obligation
--                                                      - SUM(amount) WHERE status = 'active'
--
-- status: 'active' (reserved, default) while a CBA/CFA stays Approved; 'released' once that's no longer
-- true (the approved request was deleted); 'converted' once an RFA/LAA referencing this row's allocation
-- as its parent_id is itself Approved — see BudgetLineItemService::convertEarmarkForRfaApproval(), which
-- shrinks (or fully flips) the CBA's row by the RFA/LAA's drawn amount, inserts a SEPARATE 'converted'
-- row attributed to the RFA/LAA's own allocation_id (so what each one converted stays traceable on its
-- own), and increases budget_line_items.obligation by that same amount — the real deduction.
CREATE TABLE IF NOT EXISTS fims.budget_line_item_earmark (
    earmark_id          SERIAL PRIMARY KEY,
    budget_line_item_id INTEGER NOT NULL,
    allocation_id       INTEGER NOT NULL,
    amount              NUMERIC(18,2) NOT NULL,
    status              VARCHAR(20) NOT NULL DEFAULT 'active',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    released_at         TIMESTAMP,
    CONSTRAINT fk_budget_line_item_earmark_line_item FOREIGN KEY (budget_line_item_id)
        REFERENCES fims.budget_line_items(budget_line_item_id) ON DELETE CASCADE,
    CONSTRAINT fk_budget_line_item_earmark_allocation FOREIGN KEY (allocation_id)
        REFERENCES fims.budget_allocation(allocation_id) ON DELETE CASCADE,
    CONSTRAINT chk_budget_line_item_earmark_status CHECK (status IN ('active', 'released', 'converted'))
);

COMMENT ON TABLE fims.budget_line_item_earmark IS 'One row = one Approved budget_allocation line reserving an amount against a budget_line_items balance (ceiling = suggested_amount, the FMD-approved figure — not proposed_amount), without touching suggested_amount/obligation directly.';
COMMENT ON COLUMN fims.budget_line_item_earmark.amount IS 'The amount reserved (the line_transaction entry''s requested_amount at the moment of approval).';
COMMENT ON COLUMN fims.budget_line_item_earmark.status IS 'active = currently reserved; released = freed back (the approved CBA/CFA was deleted); converted = drawn down by an Approved RFA/LAA into a real obligation (budget_line_items.obligation).';
COMMENT ON COLUMN fims.budget_line_item_earmark.released_at IS 'When status moved away from active. NULL while active.';

CREATE INDEX IF NOT EXISTS idx_budget_line_item_earmark_budget_line_item_id ON fims.budget_line_item_earmark (budget_line_item_id);
CREATE INDEX IF NOT EXISTS idx_budget_line_item_earmark_allocation_id ON fims.budget_line_item_earmark (allocation_id);
CREATE INDEX IF NOT EXISTS idx_budget_line_item_earmark_active ON fims.budget_line_item_earmark (budget_line_item_id) WHERE status = 'active';
