<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

class BudgetRevisionService
{
    // status_id values (there is no status lookup table). status_id is NOT NULL and is not
    // on the form, so a newly filed revision starts as PENDING; review() then moves it to
    // APPROVED or DECLINED.
    private const STATUS_PENDING  = 1;
    private const STATUS_APPROVED = 2;
    private const STATUS_DECLINED = 3;

    // fims.budget_req.amount is NUMERIC(15,2), so it cannot hold 10^13 or more.
    private const MAX_BUDGET_REQ_AMOUNT = 10000000000000;

    private string $table        = 'fims.budget_revision';
    private string $tableReq     = 'fims.budget_req';
    private string $tableProgram = 'fims.program';
    private string $tableProject = 'fims.project';
    private string $tableUser    = 'uac.users';

    // FROM + JOIN part shared by the list, count, sum and read queries.
    // br/pg/pj = the From COB (from_budget_req_id); tbr/tpg/tpj = the optional To COB.
    private function joinSql(): string
    {
        return "FROM {$this->table} rv
                LEFT JOIN {$this->tableReq} br ON br.id = rv.from_budget_req_id
                LEFT JOIN {$this->tableProgram} pg ON pg.program_code = br.program_code
                LEFT JOIN {$this->tableProject} pj ON pj.project_code = br.project_code
                LEFT JOIN {$this->tableReq} tbr ON tbr.id = rv.to_budget_req_id
                LEFT JOIN {$this->tableProgram} tpg ON tpg.program_code = tbr.program_code
                LEFT JOIN {$this->tableProject} tpj ON tpj.project_code = tbr.project_code
                LEFT JOIN {$this->tableUser} us ON us.user_id = rv.user_id";
    }

    // `status` is the readable form of status_id; requested_by_* is who filed it
    // (both NULL for the built-in administrator, whose user_id -1 has no users row).
    // The From COB comes back as fiscal_yr / program_* / project_* / budget_req_amount,
    // the To COB (all NULL when there is none) as the to_* equivalents.
    private function selectSql(): string
    {
        return "SELECT rv.*, br.fiscal_yr, br.program_code, br.project_code,
                       br.amount AS budget_req_amount, pg.program_name, pj.project_name,
                       tbr.fiscal_yr AS to_fiscal_yr, tbr.program_code AS to_program_code,
                       tbr.project_code AS to_project_code, tbr.amount AS to_budget_req_amount,
                       tpg.program_name AS to_program_name, tpj.project_name AS to_project_name,
                       CASE rv.status_id
                            WHEN " . self::STATUS_PENDING . " THEN 'PENDING'
                            WHEN " . self::STATUS_APPROVED . " THEN 'APPROVED'
                            WHEN " . self::STATUS_DECLINED . " THEN 'DECLINED'
                            ELSE 'UNKNOWN'
                       END AS status,
                       us.username AS requested_by_username,
                       NULLIF(TRIM(CONCAT_WS(' ', us.first_name, us.last_name)), '') AS requested_by_name "
            . $this->joinSql();
    }

    private function budgetReqExists(int $id): bool
    {
        return (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM {$this->tableReq} WHERE id = " . App\QuotedValue($id, App\DataType::NUMBER, 'DB'),
            'DB'
        ) > 0;
    }

    private function isAmount(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= 0;
    }

    // The optional To COB (to_budget_req_id): null when none was sent (null / ''),
    // false when what was sent is not a usable id, otherwise the id.
    private function parseToId(mixed $raw): int|null|false
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = filter_var($raw, FILTER_VALIDATE_INT);

        return ($id === false || $id <= 0) ? false : $id;
    }

    // A revision with a To COB MOVES funds: the From COB goes down to To Amount and the To COB
    // goes up by the difference. So the To COB must exist, be a different request, and To Amount
    // must be lower than From Amount (an increase would mean taking funds out of the To COB).
    // Returns an error message, or null when the combination is fine.
    private function transferError(int $fromId, int $toId, mixed $fromAmount, mixed $toAmount): ?string
    {
        if ($toId === $fromId) {
            return 'to_budget_req_id must be different from from_budget_req_id.';
        }
        if (!$this->budgetReqExists($toId)) {
            return 'to_budget_req_id does not exist.';
        }
        if (round((float) $toAmount, 2) >= round((float) $fromAmount, 2)) {
            return 'To Amount must be lower than From Amount to move funds to the To COB.';
        }

        return null;
    }

    // reason is optional long text — empty string is stored as NULL.
    // Quoted through the connection rather than App\QuotedValue(): that helper turns
    // line breaks and backslashes into literal "\n" / "\\" text, which PostgreSQL
    // would store as-is and corrupt a multi-line reason.
    private function reasonSql(array $body): string
    {
        $reason = trim((string) ($body['reason'] ?? ''));

        return $reason === '' ? 'NULL' : App\Conn('DB')->quote($reason);
    }

    public function fetchAlls(?int $page = null, int $limit = 10, string $fiscalYr = '', string $budgetReqId = ''): array
    {
        try {
            $where = ['rv.deleted_at IS NULL'];

            if ($fiscalYr !== '') {
                $where[] = 'br.fiscal_yr = ' . (int) $fiscalYr;
            }
            if ($budgetReqId !== '') {
                // a revision touches both of its COBs, so it belongs to either one's list
                $where[] = '(rv.from_budget_req_id = ' . (int) $budgetReqId
                         . ' OR rv.to_budget_req_id = ' . (int) $budgetReqId . ')';
            }
            $whereSql = implode(' AND ', $where);

            if ($page === null) {
                $data = App\ExecuteRows($this->selectSql() . " WHERE {$whereSql} ORDER BY rv.id DESC", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $page   = max(1, $page);
            $limit  = max(1, $limit);
            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) {$this->joinSql()} WHERE {$whereSql}", 'DB');
            $data  = App\ExecuteRows(
                $this->selectSql() . " WHERE {$whereSql} ORDER BY rv.id DESC LIMIT {$limit} OFFSET {$offset}",
                'DB'
            );

            $totalChange = (float) App\ExecuteScalar(
                "SELECT COALESCE(SUM(rv.to_amount - rv.from_amount), 0) {$this->joinSql()} WHERE {$whereSql}",
                'DB'
            );

            return [
                'success'      => true,
                'data'         => $data ?: [],
                'total_change' => $totalChange,
                'pagination'   => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id;
            $data = App\ExecuteRow($this->selectSql() . " WHERE rv.id = {$id} AND rv.deleted_at IS NULL", 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Budget revision request #{$id} not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            // The filing user is whoever is logged in (from the session / JWT), never the request body.
            $userId = App\CurrentUserID();

            $budgetReqId = filter_var($body['from_budget_req_id'] ?? '', FILTER_VALIDATE_INT);
            $fromAmount  = $body['from_amount'] ?? '';
            $toAmount    = $body['to_amount'] ?? '';

            if ($budgetReqId === false || $budgetReqId <= 0) {
                return ['success' => false, 'message' => 'from_budget_req_id is required.'];
            }
            if (!$this->isAmount($fromAmount) || !$this->isAmount($toAmount)) {
                return ['success' => false, 'message' => 'from_amount and to_amount must be non-negative numbers.'];
            }
            if (!$this->budgetReqExists($budgetReqId)) {
                return ['success' => false, 'message' => "Budget request #{$budgetReqId} does not exist."];
            }
            // optional To COB: when set, the revision moves funds from the From COB to it
            $toBudgetReqId = $this->parseToId($body['to_budget_req_id'] ?? null);
            if ($toBudgetReqId === false) {
                return ['success' => false, 'message' => 'to_budget_req_id does not exist.'];
            }
            if ($toBudgetReqId !== null) {
                $error = $this->transferError($budgetReqId, $toBudgetReqId, $fromAmount, $toAmount);
                if ($error !== null) {
                    return ['success' => false, 'message' => $error];
                }
            }
            // PHPMaker gives the built-in administrator (superadmin) the id -1, so a negative id
            // is a valid user. Only an empty / non-numeric / 0 id means nobody is logged in.
            if (!is_numeric($userId) || (int) $userId === 0) {
                return ['success' => false, 'message' => 'Unable to determine the current user.'];
            }

            $sql = "INSERT INTO {$this->table} (from_budget_req_id, to_budget_req_id, from_amount, to_amount, reason, user_id, status_id)
                    VALUES ("
                . App\QuotedValue($budgetReqId, App\DataType::NUMBER, 'DB') . ', '
                . ($toBudgetReqId === null ? 'NULL' : App\QuotedValue($toBudgetReqId, App\DataType::NUMBER, 'DB')) . ', '
                . App\QuotedValue($fromAmount, App\DataType::NUMBER, 'DB') . ', '
                . App\QuotedValue($toAmount, App\DataType::NUMBER, 'DB') . ', '
                . $this->reasonSql($body) . ', '
                . App\QuotedValue((int) $userId, App\DataType::NUMBER, 'DB') . ', '
                . self::STATUS_PENDING . ')
                    RETURNING id';

            $row = App\ExecuteRow($sql, 'DB');

            return [
                'success' => true,
                'message' => 'Budget revision request created successfully.',
                'id'      => $row['id'] ?? null,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            // An approved revision has already been applied to its budget request (amount +
            // adjustment), so changing it afterwards would leave the two out of step.
            if ((int) $check['data']['status_id'] === self::STATUS_APPROVED) {
                return ['success' => false, 'message' => "Budget revision request #{$id} is already approved and can no longer be edited."];
            }

            $setParts = [];

            // The values the revision will have after this update (the stored ones unless the
            // body changes them) — the From/To COB rule below is checked against these.
            $row      = $check['data'];
            $fromId   = (int) $row['from_budget_req_id'];
            $toId     = $row['to_budget_req_id'] === null ? null : (int) $row['to_budget_req_id'];
            $fromAmt  = $row['from_amount'];
            $toAmt    = $row['to_amount'];

            $budgetReqId = $body['from_budget_req_id'] ?? null;
            if ($budgetReqId !== null && $budgetReqId !== '') {
                $budgetReqId = filter_var($budgetReqId, FILTER_VALIDATE_INT);
                if ($budgetReqId === false || $budgetReqId <= 0 || !$this->budgetReqExists($budgetReqId)) {
                    return ['success' => false, 'message' => 'from_budget_req_id does not exist.'];
                }
                $fromId     = $budgetReqId;
                $setParts[] = 'from_budget_req_id = ' . App\QuotedValue($budgetReqId, App\DataType::NUMBER, 'DB');
            }

            // The To COB can be cleared, so it is applied whenever the key is sent ('' = none).
            if (array_key_exists('to_budget_req_id', $body)) {
                $toId = $this->parseToId($body['to_budget_req_id']);
                if ($toId === false) {
                    return ['success' => false, 'message' => 'to_budget_req_id does not exist.'];
                }
                $setParts[] = 'to_budget_req_id = ' . ($toId === null ? 'NULL' : App\QuotedValue($toId, App\DataType::NUMBER, 'DB'));
            }

            foreach (['from_amount', 'to_amount'] as $field) {
                $value = $body[$field] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                if (!$this->isAmount($value)) {
                    return ['success' => false, 'message' => "{$field} must be a non-negative number."];
                }
                if ($field === 'from_amount') {
                    $fromAmt = $value;
                } else {
                    $toAmt = $value;
                }
                $setParts[] = "{$field} = " . App\QuotedValue($value, App\DataType::NUMBER, 'DB');
            }

            if ($toId !== null) {
                $error = $this->transferError($fromId, $toId, $fromAmt, $toAmt);
                if ($error !== null) {
                    return ['success' => false, 'message' => $error];
                }
            }

            // reason can be cleared, so it is applied whenever the key is sent.
            if (array_key_exists('reason', $body)) {
                $setParts[] = 'reason = ' . $this->reasonSql($body);
            }

            if (empty($setParts)) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }

            $setParts[] = 'update_at = NOW()';

            $sql = "UPDATE {$this->table}
                    SET " . implode(', ', $setParts) . '
                    WHERE id = ' . (int) $id;

            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => 'Budget revision request updated successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Approve or decline a PENDING revision. $status is 'APPROVED' or 'DECLINED'.
    // DECLINED only records the decision. APPROVED also applies the change to the budget
    // request (amount + adjustment) — see approve().
    public function review(int|string $id, string $status): array
    {
        try {
            $target = [
                'APPROVED' => self::STATUS_APPROVED,
                'DECLINED' => self::STATUS_DECLINED,
            ][strtoupper(trim($status))] ?? null;

            if ($target === null) {
                return ['success' => false, 'message' => "status must be 'APPROVED' or 'DECLINED'."];
            }

            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            if ((int) $check['data']['status_id'] !== self::STATUS_PENDING) {
                $current = ucfirst(strtolower((string) $check['data']['status']));

                return ['success' => false, 'message' => "Only pending requests can be reviewed — request #{$id} is already {$current}."];
            }

            if ($target === self::STATUS_APPROVED) {
                return $this->approve((int) $id, $check['data']);
            }

            // DECLINED — only the status changes; the budget request is left alone.
            // The status_id guard makes the change atomic: if someone reviewed it
            // between the read above and this UPDATE, no row matches.
            $affected = App\Execute(
                "UPDATE {$this->table}
                 SET status_id = {$target}, update_at = NOW()
                 WHERE id = " . (int) $id . ' AND deleted_at IS NULL AND status_id = ' . self::STATUS_PENDING,
                'DB'
            );
            if (!$affected) {
                return ['success' => false, 'message' => "Request #{$id} was already reviewed by someone else."];
            }

            return [
                'success' => true,
                'message' => "Budget revision request #{$id} declined.",
                'status'  => 'DECLINED',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Approve = record the decision AND apply it to the budget request(s), as ONE statement so
    // either everything happens or nothing does.
    //
    // From COB (from_budget_req_id) — always:
    //   amount     := the revision's to_amount
    //   adjustment := adjustment + (to_amount − the amount the request has right now)
    // To COB (to_budget_req_id) — only when the revision has one: it receives what the From COB
    // gave up, so the two move by the same amount in opposite directions:
    //   amount     := amount     + (the From COB's current amount − to_amount)
    //   adjustment := adjustment + (the From COB's current amount − to_amount)
    // The change is measured against the live amount, not from_amount: from_amount can be stale
    // if another revision was approved after this one was filed. Measured this way,
    // amount − adjustment always stays equal to each request's original amount.
    private function approve(int $id, array $rev): array
    {
        $fromId = (int) $rev['from_budget_req_id'];
        $toId   = $rev['to_budget_req_id'] === null ? null : (int) $rev['to_budget_req_id'];
        $newAmt = round((float) $rev['to_amount'], 2);

        if ($newAmt >= self::MAX_BUDGET_REQ_AMOUNT) {
            return ['success' => false, 'message' => 'To Amount is too large for a budget request (maximum 9,999,999,999,999.99).'];
        }
        if (!$this->budgetReqExists($fromId)) {
            return ['success' => false, 'message' => "The From COB for revision #{$id} no longer exists."];
        }

        if ($toId !== null) {
            if ($toId === $fromId) {
                return ['success' => false, 'message' => "Revision #{$id} has the same COB as its From and To COB."];
            }
            if (!$this->budgetReqExists($toId)) {
                return ['success' => false, 'message' => "The To COB for revision #{$id} no longer exists."];
            }
            // What the From COB has right now decides how much moves; it must really go down.
            $fromNow = round((float) App\ExecuteScalar("SELECT amount FROM {$this->tableReq} WHERE id = {$fromId}", 'DB'), 2);
            $toNow   = round((float) App\ExecuteScalar("SELECT amount FROM {$this->tableReq} WHERE id = {$toId}", 'DB'), 2);
            $moved   = round($fromNow - $newAmt, 2);
            if ($moved <= 0) {
                return ['success' => false, 'message' => 'To Amount ' . number_format($newAmt, 2) . ' is not lower than the From COB\'s current amount '
                    . number_format($fromNow, 2) . ', so no funds can be moved to the To COB.'];
            }
            if ($toNow + $moved >= self::MAX_BUDGET_REQ_AMOUNT) {
                return ['success' => false, 'message' => 'The To COB cannot hold that much (maximum 9,999,999,999,999.99).'];
            }
        }

        // The three statements below are data-modifying CTEs: they all see the same snapshot, so
        // in from_upd br.amount is the OLD amount (adjustment gets the real change), and in to_upd
        // fr.amount is the From COB's amount before it was lowered. The WHERE guards in `approved`
        // keep a double approval from applying twice and stop a transfer whose COBs changed under us.
        $row = App\ExecuteRow(
            "WITH approved AS (
                 UPDATE {$this->table} rv
                 SET status_id = " . self::STATUS_APPROVED . ", update_at = NOW()
                 WHERE rv.id = {$id} AND rv.deleted_at IS NULL AND rv.status_id = " . self::STATUS_PENDING . "
                   AND EXISTS (SELECT 1 FROM {$this->tableReq} x WHERE x.id = rv.from_budget_req_id)
                   AND (rv.to_budget_req_id IS NULL
                        OR (rv.to_budget_req_id <> rv.from_budget_req_id
                            AND EXISTS (SELECT 1 FROM {$this->tableReq} t WHERE t.id = rv.to_budget_req_id)
                            AND rv.to_amount < (SELECT f.amount FROM {$this->tableReq} f WHERE f.id = rv.from_budget_req_id)))
                 RETURNING rv.from_budget_req_id, rv.to_budget_req_id, rv.to_amount
             ),
             from_upd AS (
                 UPDATE {$this->tableReq} br
                 SET adjustment = br.adjustment + (approved.to_amount - br.amount),
                     amount     = approved.to_amount
                 FROM approved
                 WHERE br.id = approved.from_budget_req_id
                 RETURNING br.id, br.amount, br.adjustment
             ),
             to_upd AS (
                 UPDATE {$this->tableReq} tr
                 SET amount     = tr.amount     + (fr.amount - approved.to_amount),
                     adjustment = tr.adjustment + (fr.amount - approved.to_amount)
                 FROM approved
                 JOIN {$this->tableReq} fr ON fr.id = approved.from_budget_req_id
                 WHERE tr.id = approved.to_budget_req_id
                 RETURNING tr.id, tr.amount, tr.adjustment
             )
             SELECT f.id AS from_id, f.amount AS from_amount, f.adjustment AS from_adjustment,
                    t.id AS to_id,   t.amount AS to_amount,   t.adjustment AS to_adjustment
             FROM from_upd f
             LEFT JOIN to_upd t ON TRUE",
            'DB'
        );

        if (!$row) {
            return ['success' => false, 'message' => "Request #{$id} could not be approved — it was already reviewed by someone else, or its COBs changed in the meantime."];
        }

        $result = [
            'success'    => true,
            'message'    => $toId === null
                ? "Budget revision request #{$id} approved and applied to the budget request."
                : "Budget revision request #{$id} approved: " . number_format($moved, 2) . ' moved from the From COB to the To COB.',
            'status'     => 'APPROVED',
            // the From COB: id, new amount, new adjustment
            'budget_req' => ['id' => $row['from_id'], 'amount' => $row['from_amount'], 'adjustment' => $row['from_adjustment']],
        ];
        if ($row['to_id'] !== null) {
            $result['to_budget_req'] = ['id' => $row['to_id'], 'amount' => $row['to_amount'], 'adjustment' => $row['to_adjustment']];
        }

        return $result;
    }

    // Soft delete — the table carries deleted_at, so the row is kept.
    public function delete(int|string $id): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $sql = "UPDATE {$this->table}
                    SET deleted_at = NOW(), update_at = NOW()
                    WHERE id = " . (int) $id;
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Budget revision request #{$id} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
