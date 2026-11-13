<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Budget revisions: a request to change a BUDGET LINE's amount — a chart-of-accounts line item of a budget request
 * (fims.budget_line_items) — optionally moving the difference to a second budget line (the To line).
 *
 * A line's CURRENT amount is  proposed_amount + adjustment.  Approving a revision only ever moves `adjustment`
 * ("net adjustments applied to the proposed amount"); proposed_amount stays exactly as the office filed it:
 *   From line  adjustment := adjustment + (to_amount − its current amount)       → its current amount becomes to_amount
 *   To line    adjustment := adjustment + (the From line's current amount − to_amount)   → it receives the difference
 * so a transfer deducts from one line and adds to the other by the same amount.
 * See _assets/sql/01 - fims/03 - alter_table/23 - budget_revision_on_budget_reference_number.sql.
 */
class BudgetRevisionService
{
    // status_id values (there is no status lookup table). status_id is NOT NULL and is not
    // on the form, so a newly filed revision starts as PENDING; review() then moves it to
    // APPROVED or DECLINED.
    private const STATUS_PENDING  = 1;
    private const STATUS_APPROVED = 2;
    private const STATUS_DECLINED = 3;

    // A line's current amount is kept below 10^13 (proposed_amount is NUMERIC(15,2)).
    private const MAX_LINE_AMOUNT = 10000000000000;

    private string $table          = 'fims.budget_revision';
    private string $tableLines     = 'fims.budget_line_items';
    private string $tableReq       = 'fims.budget_req';
    private string $tableReqProj   = 'fims.budget_req_project';
    private string $tableReqProg   = 'fims.budget_req_program';
    private string $tableProgram   = 'fims.program';
    private string $tableProject   = 'fims.project';
    private string $tableAccounts  = 'fims.chart_of_accounts';
    private string $tableRc        = 'fims.responsibility_code';
    private string $tableStatus    = 'fims.settings_status';
    private string $tableUser      = 'uac.users';

    // The JOINs that turn a revision's line id (rv.$col) into the line and everything shown with it, under the alias prefix $p:
    // {p}l the line, {p}b its budget request (+ {p}rc its office, {p}st its status), {p}bp its project (+ {p}bg its program row,
    // {p}pg / {p}pj their names), {p}coa its account. All LEFT JOINs: a line that no longer exists leaves everything NULL.
    private function lineJoins(string $p, string $col): string
    {
        return "LEFT JOIN {$this->tableLines} {$p}l ON {$p}l.budget_line_item_id = rv.{$col}
                LEFT JOIN {$this->tableReq} {$p}b ON {$p}b.budget_reference_number = {$p}l.budget_reference_number
                LEFT JOIN {$this->tableRc} {$p}rc ON {$p}rc.rc_id = {$p}b.rc_id
                LEFT JOIN {$this->tableStatus} {$p}st ON {$p}st.id = {$p}b.status_id
                LEFT JOIN {$this->tableReqProj} {$p}bp ON {$p}bp.budget_req_project_id = {$p}l.budget_req_project_id
                LEFT JOIN {$this->tableReqProg} {$p}bg ON {$p}bg.budget_req_program_id = {$p}bp.budget_req_program_id
                LEFT JOIN {$this->tableProgram} {$p}pg ON {$p}pg.program_code = {$p}bg.program_code
                LEFT JOIN {$this->tableProject} {$p}pj ON {$p}pj.project_code = {$p}bp.project_code
                LEFT JOIN {$this->tableAccounts} {$p}coa ON {$p}coa.account_id = {$p}l.account_id";
    }

    // The columns of one line, named with the prefix $pre ('' for the From line, 'to_' for the To line).
    private function lineSelect(string $p, string $pre): string
    {
        return "{$p}b.budget_reference_number AS {$pre}budget_reference_number, {$p}b.fiscal_yr AS {$pre}fiscal_yr,
                       {$p}rc.rc_code AS {$pre}rc_code, {$p}rc.rc_name AS {$pre}rc_name,
                       {$p}st.name AS {$pre}budget_req_status_name, {$p}st.color AS {$pre}budget_req_status_color,
                       {$p}bg.program_code AS {$pre}program_code, {$p}pg.program_name AS {$pre}program_name,
                       {$p}bp.project_code AS {$pre}project_code, {$p}pj.project_name AS {$pre}project_name,
                       {$p}l.account_id AS {$pre}account_id, {$p}coa.account_code AS {$pre}account_code,
                       {$p}coa.account_title AS {$pre}account_title, {$p}l.particulars AS {$pre}particulars,
                       {$p}l.proposed_amount AS {$pre}proposed_amount, {$p}l.adjustment AS {$pre}line_adjustment,
                       ({$p}l.proposed_amount + {$p}l.adjustment) AS {$pre}line_amount";
    }

    // FROM + JOIN part shared by the list, count, sum and read queries.
    // fl… = the From line (from_budget_line_item_id) and what hangs off it; tl… = the optional To line.
    private function joinSql(): string
    {
        return "FROM {$this->table} rv
                " . $this->lineJoins('f', 'from_budget_line_item_id') . "
                " . $this->lineJoins('t', 'to_budget_line_item_id') . "
                LEFT JOIN {$this->tableUser} us ON us.user_id = rv.user_id";
    }

    // `status` is the readable form of status_id; requested_by_* is who filed it
    // (both NULL for the built-in administrator, whose user_id -1 has no users row).
    // The From line comes back as budget_reference_number / fiscal_yr / rc_* / budget_req_status_* (its request), program_* / project_*,
    // account_* / particulars, proposed_amount / line_adjustment / line_amount (its CURRENT amount); the To line (all NULL when
    // there is none) as the to_* equivalents. A line that no longer exists leaves its fields NULL — the revision still lists.
    private function selectSql(): string
    {
        return "SELECT rv.*,
                       " . $this->lineSelect('f', '') . ",
                       " . $this->lineSelect('t', 'to_') . ",
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

    private function lineExists(int $id): bool
    {
        return (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM {$this->tableLines} WHERE budget_line_item_id = " . App\QuotedValue($id, App\DataType::NUMBER, 'DB'),
            'DB'
        ) > 0;
    }

    // The CURRENT amount of one line (proposed_amount + adjustment).
    private function currentAmountOf(int $id): float
    {
        return round((float) App\ExecuteScalar(
            "SELECT proposed_amount + adjustment FROM {$this->tableLines} WHERE budget_line_item_id = "
            . App\QuotedValue($id, App\DataType::NUMBER, 'DB'),
            'DB'
        ), 2);
    }

    private function isAmount(mixed $value): bool
    {
        return is_numeric($value) && (float) $value >= 0;
    }

    // A budget line id: null when none was sent (null / ''), false when what was sent is not a usable id, otherwise the id.
    private function parseId(mixed $raw): int|null|false
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $id = filter_var($raw, FILTER_VALIDATE_INT);

        return ($id === false || $id <= 0) ? false : $id;
    }

    // A revision with a To line MOVES funds: the From line goes down to To Amount and the To line goes up by the difference.
    // So the To line must exist, be a different line, and To Amount must be lower than From Amount (an increase would mean taking
    // funds out of the To line). Returns an error message, or null when the combination is fine.
    private function transferError(int $fromId, int $toId, mixed $fromAmount, mixed $toAmount): ?string
    {
        if ($toId === $fromId) {
            return 'to_budget_line_item_id must be different from from_budget_line_item_id.';
        }
        if (!$this->lineExists($toId)) {
            return 'to_budget_line_item_id does not exist.';
        }
        if (round((float) $toAmount, 2) >= round((float) $fromAmount, 2)) {
            return 'To Amount must be lower than From Amount to move funds to the To budget line.';
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

    // A failed statement, as the response. A database that has not had the revision migration yet fails in a recognisable way —
    // the from_ / to_budget_line_item_id columns do not exist — and for that the message says what to run; the database's own
    // message is kept after it.
    private function failure(\Throwable $e): array
    {
        $message = $e->getMessage();
        if (preg_match('/_budget_line_item_id.{0,80}does not exist/is', $message)) {
            $message = 'Budget revisions need a database update that has not been applied yet. Run '
                . '"_assets/sql/01 - fims/03 - alter_table/23 - budget_revision_on_budget_reference_number.sql" on the database, then reload. '
                . '(' . $message . ')';
        }

        return ['success' => false, 'message' => $message];
    }

    // $budgetReference: a budget reference number ("BP-001") — the list keeps the revisions that touch a line of that budget request,
    // as the From line or the To line.
    public function fetchAlls(?int $page = null, int $limit = 10, string $fiscalYr = '', string $budgetReference = ''): array
    {
        try {
            $where = ['rv.deleted_at IS NULL'];

            if ($fiscalYr !== '') {
                $where[] = 'fb.fiscal_yr = ' . (int) $fiscalYr;
            }
            if (trim($budgetReference) !== '') {
                // a revision touches both of its lines, so it belongs to either one's budget request
                $ref     = App\QuotedValue(trim($budgetReference), App\DataType::STRING, 'DB');
                $where[] = "(fl.budget_reference_number = {$ref} OR tl.budget_reference_number = {$ref})";
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
            return $this->failure($e);
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
            return $this->failure($e);
        }
    }

    public function create(array $body): array
    {
        try {
            // The filing user is whoever is logged in (from the session / JWT), never the request body.
            $userId = App\CurrentUserID();

            $fromId     = $this->parseId($body['from_budget_line_item_id'] ?? null);
            $fromAmount = $body['from_amount'] ?? '';
            $toAmount   = $body['to_amount'] ?? '';

            if ($fromId === null || $fromId === false) {
                return ['success' => false, 'message' => 'from_budget_line_item_id is required.'];
            }
            if (!$this->isAmount($fromAmount) || !$this->isAmount($toAmount)) {
                return ['success' => false, 'message' => 'from_amount and to_amount must be non-negative numbers.'];
            }
            if (!$this->lineExists($fromId)) {
                return ['success' => false, 'message' => "Budget line #{$fromId} does not exist."];
            }
            // optional To line: when set, the revision moves funds from the From line to it
            $toId = $this->parseId($body['to_budget_line_item_id'] ?? null);
            if ($toId === false) {
                return ['success' => false, 'message' => 'to_budget_line_item_id does not exist.'];
            }
            if ($toId !== null) {
                $error = $this->transferError($fromId, $toId, $fromAmount, $toAmount);
                if ($error !== null) {
                    return ['success' => false, 'message' => $error];
                }
            }
            // PHPMaker gives the built-in administrator (superadmin) the id -1, so a negative id
            // is a valid user. Only an empty / non-numeric / 0 id means nobody is logged in.
            if (!is_numeric($userId) || (int) $userId === 0) {
                return ['success' => false, 'message' => 'Unable to determine the current user.'];
            }

            $sql = "INSERT INTO {$this->table} (from_budget_line_item_id, to_budget_line_item_id, from_amount, to_amount, reason, user_id, status_id)
                    VALUES ("
                . App\QuotedValue($fromId, App\DataType::NUMBER, 'DB') . ', '
                . ($toId === null ? 'NULL' : App\QuotedValue($toId, App\DataType::NUMBER, 'DB')) . ', '
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
            return $this->failure($e);
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            // An approved revision has already been applied to its lines' adjustments, so
            // changing it afterwards would leave the two out of step.
            if ((int) $check['data']['status_id'] === self::STATUS_APPROVED) {
                return ['success' => false, 'message' => "Budget revision request #{$id} is already approved and can no longer be edited."];
            }

            $setParts = [];

            // The values the revision will have after this update (the stored ones unless the
            // body changes them) — the From/To line rule below is checked against these.
            $row     = $check['data'];
            $fromId  = $row['from_budget_line_item_id'] === null ? null : (int) $row['from_budget_line_item_id'];
            $toId    = $row['to_budget_line_item_id'] === null ? null : (int) $row['to_budget_line_item_id'];
            $fromAmt = $row['from_amount'];
            $toAmt   = $row['to_amount'];

            $newFrom = $this->parseId($body['from_budget_line_item_id'] ?? null);
            if ($newFrom === false || ($newFrom !== null && !$this->lineExists($newFrom))) {
                return ['success' => false, 'message' => 'from_budget_line_item_id does not exist.'];
            }
            if ($newFrom !== null) {
                $fromId     = $newFrom;
                $setParts[] = 'from_budget_line_item_id = ' . App\QuotedValue($newFrom, App\DataType::NUMBER, 'DB');
            }

            // The To line can be cleared, so it is applied whenever the key is sent ('' = none).
            if (array_key_exists('to_budget_line_item_id', $body)) {
                $toId = $this->parseId($body['to_budget_line_item_id']);
                if ($toId === false) {
                    return ['success' => false, 'message' => 'to_budget_line_item_id does not exist.'];
                }
                $setParts[] = 'to_budget_line_item_id = ' . ($toId === null ? 'NULL' : App\QuotedValue($toId, App\DataType::NUMBER, 'DB'));
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

            // A revision filed before budget lines were revised has no From line; it can be edited only by giving it one.
            if ($fromId === null) {
                return ['success' => false, 'message' => 'from_budget_line_item_id is required: this revision was filed before budget lines could be revised, so pick its budget line.'];
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
            return $this->failure($e);
        }
    }

    // Approve or decline a PENDING revision. $status is 'APPROVED' or 'DECLINED'.
    // DECLINED only records the decision. APPROVED also applies the change to the budget line(s)
    // (their adjustment) — see approve().
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

            // DECLINED — only the status changes; the budget lines are left alone.
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
            return $this->failure($e);
        }
    }

    // Approve = record the decision AND apply it to the budget line(s), as ONE statement so
    // either everything happens or nothing does. Only the lines' adjustment moves (never proposed_amount):
    //   From line (from_budget_line_item_id) — always:
    //     adjustment := adjustment + (to_amount − the amount the line has right now)   → its current amount becomes to_amount
    //   To line (to_budget_line_item_id) — only when the revision has one: it receives what the From line gave up,
    //     adjustment := adjustment + (the From line's current amount − to_amount)
    // so the two move by the same amount in opposite directions. The change is measured against the live amount, not
    // from_amount: from_amount can be stale if another revision was approved after this one was filed.
    private function approve(int $id, array $rev): array
    {
        $fromId = $rev['from_budget_line_item_id'] === null ? null : (int) $rev['from_budget_line_item_id'];
        $toId   = $rev['to_budget_line_item_id'] === null ? null : (int) $rev['to_budget_line_item_id'];
        $newAmt = round((float) $rev['to_amount'], 2);

        if ($newAmt >= self::MAX_LINE_AMOUNT) {
            return ['success' => false, 'message' => 'To Amount is too large for a budget line (maximum 9,999,999,999,999.99).'];
        }
        if ($fromId === null || !$this->lineExists($fromId)) {
            return ['success' => false, 'message' => "The From budget line for revision #{$id} no longer exists."];
        }

        $moved = 0.0;
        $toNow = 0.0;
        if ($toId !== null) {
            if ($toId === $fromId) {
                return ['success' => false, 'message' => "Revision #{$id} has the same budget line as its From and To line."];
            }
            if (!$this->lineExists($toId)) {
                return ['success' => false, 'message' => "The To budget line for revision #{$id} no longer exists."];
            }
            // What the From line has right now decides how much moves; it must really go down.
            $fromNow = $this->currentAmountOf($fromId);
            $toNow   = $this->currentAmountOf($toId);
            $moved   = round($fromNow - $newAmt, 2);
            if ($moved <= 0) {
                return ['success' => false, 'message' => 'To Amount ' . number_format($newAmt, 2) . ' is not lower than the From budget line\'s current amount '
                    . number_format($fromNow, 2) . ', so no funds can be moved to the To budget line.'];
            }
            if ($toNow + $moved >= self::MAX_LINE_AMOUNT) {
                return ['success' => false, 'message' => 'The To budget line cannot hold that much (maximum 9,999,999,999,999.99).'];
            }
        }

        // The three statements below are data-modifying CTEs: they all see the same snapshot, so
        // in from_upd the current amount is the OLD one (adjustment gets the real change), and in to_upd
        // the From line's amount is the one before it was lowered. The WHERE guards in `approved`
        // keep a double approval from applying twice and stop a transfer whose lines changed under us.
        $row = App\ExecuteRow(
            "WITH approved AS (
                 UPDATE {$this->table} rv
                 SET status_id = " . self::STATUS_APPROVED . ", update_at = NOW()
                 WHERE rv.id = {$id} AND rv.deleted_at IS NULL AND rv.status_id = " . self::STATUS_PENDING . "
                   AND EXISTS (SELECT 1 FROM {$this->tableLines} x WHERE x.budget_line_item_id = rv.from_budget_line_item_id)
                   AND (rv.to_budget_line_item_id IS NULL
                        OR (rv.to_budget_line_item_id <> rv.from_budget_line_item_id
                            AND EXISTS (SELECT 1 FROM {$this->tableLines} t WHERE t.budget_line_item_id = rv.to_budget_line_item_id)
                            AND rv.to_amount < (SELECT f.proposed_amount + f.adjustment FROM {$this->tableLines} f
                                                WHERE f.budget_line_item_id = rv.from_budget_line_item_id)))
                 RETURNING rv.from_budget_line_item_id, rv.to_budget_line_item_id, rv.to_amount
             ),
             from_upd AS (
                 UPDATE {$this->tableLines} fl
                 SET adjustment = fl.adjustment + (approved.to_amount - (fl.proposed_amount + fl.adjustment))
                 FROM approved
                 WHERE fl.budget_line_item_id = approved.from_budget_line_item_id
                 RETURNING fl.budget_line_item_id, fl.adjustment
             ),
             to_upd AS (
                 UPDATE {$this->tableLines} tl
                 SET adjustment = tl.adjustment + ((fr.proposed_amount + fr.adjustment) - approved.to_amount)
                 FROM approved
                 JOIN {$this->tableLines} fr ON fr.budget_line_item_id = approved.from_budget_line_item_id
                 WHERE tl.budget_line_item_id = approved.to_budget_line_item_id
                 RETURNING tl.budget_line_item_id, tl.adjustment
             )
             SELECT f.budget_line_item_id AS from_id, f.adjustment AS from_adjustment,
                    t.budget_line_item_id AS to_id,   t.adjustment AS to_adjustment
             FROM from_upd f
             LEFT JOIN to_upd t ON TRUE",
            'DB'
        );

        if (!$row) {
            return ['success' => false, 'message' => "Request #{$id} could not be approved — it was already reviewed by someone else, or its budget lines changed in the meantime."];
        }

        $result = [
            'success'     => true,
            'message'     => $toId === null
                ? "Budget revision request #{$id} approved and applied to the budget line."
                : "Budget revision request #{$id} approved: " . number_format($moved, 2) . ' moved from the From budget line to the To budget line.',
            'status'      => 'APPROVED',
            // the From line: its id, its new current amount, its new adjustment
            'budget_line' => ['budget_line_item_id' => (int) $row['from_id'], 'amount' => $newAmt, 'adjustment' => $row['from_adjustment']],
        ];
        if ($row['to_id'] !== null) {
            $result['to_budget_line'] = ['budget_line_item_id' => (int) $row['to_id'], 'amount' => round($toNow + $moved, 2), 'adjustment' => $row['to_adjustment']];
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
            return $this->failure($e);
        }
    }
}
