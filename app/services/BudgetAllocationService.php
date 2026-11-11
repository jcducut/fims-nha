<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * CRUD for fims.budget_allocation — the Memo Request page (memo / PR / PO
 * tracking per fiscal year, requesting RC and district).
 *
 * Soft-deleted via deleted_at, same as ComponentTypeService. A new memo request
 * starts as the 'Draft' status (fims.settings_status, status_for =
 * 'budget_allocation'). Program / Sub-Program / Project are referenced by code.
 * requesting_district_id points at uac.departments and requesting_rc_id at
 * fims.responsibility_code (rc_id); neither carries a DB foreign key (see
 * "08 - budget_allocation_add_fk.sql"), so they are validated here instead.
 *
 * user_id (the creator) and requesting_rc_id (the creator's Responsibility Center,
 * uac.users.rc_id) are set on create only and are never taken from the body.
 */
class BudgetAllocationService
{
    private string $table = 'fims.budget_allocation';
    private string $tableFiscalYear = 'fims.fiscal_year';
    private string $tableVendor = 'fims.vendors';
    private string $tableStatus = 'fims.settings_status';
    private string $tableProgram = 'fims.program';
    private string $tableSubProgram = 'fims.sub_program';
    private string $tableProject = 'fims.project';
    private string $tableDepartment = 'uac.departments';
    private string $tableUser = 'uac.users';
    private string $tableRc = 'fims.responsibility_code';

    /** settings_status.status_for value that scopes this table's statuses. */
    private const STATUS_FOR = 'budget_allocation';

    /**
     * The statuses of the review step, by NAME (their ids differ per install, so they are looked up — see
     * getStatusIdByName()). A request is "For Preview" once its requester submits it; the Financial Management
     * Department then moves it step by step to Approved, or to Returned (process(), REVIEW_STEPS). Seeded by
     * sample_data/06 and 07 and seed_data/04.
     */
    private const STATUS_FOR_PREVIEW = 'For Preview';
    private const STATUS_FOR_DIVISION_MANAGER = 'For Division Manager';
    private const STATUS_FOR_DEPARTMENT_MANAGER = 'For Department Manager';
    private const STATUS_APPROVED = 'Approved';
    private const STATUS_RETURNED = 'Returned';
    private const STATUS_RETURNED_TO_SECTION_CHIEF = 'Returned to Section Chief';
    private const STATUS_RETURNED_TO_DIVISION_MANAGER = 'Returned to Division Manager';

    /**
     * The review chain inside FMD, one step per status: who acts on a request in that status (the NAME of a
     * uac.user_levels row — its id differs per install), the status an approval ('next') moves it to and the status a
     * Return ('return') moves it to. Only the last approval ('Approved') gives the request its allocation number.
     *
     *   For Preview -> FMD Section Chief -> For Division Manager -> FMD Division Manager
     *               -> For Department Manager -> FMD Department Manager -> Approved
     *
     * A Return goes back ONE step, to the user who endorsed it, who then sees it in their own status and can endorse
     * it again or return it further; only the Section Chief's Return reaches the requesting office ('Returned'):
     *   Department Manager returns -> 'Returned to Division Manager' (acted on by the Division Manager)
     *   Division Manager returns   -> 'Returned to Section Chief'    (acted on by the Section Chief)
     *   Section Chief returns      -> 'Returned'                     (back to the requesting office)
     */
    private const REVIEW_STEPS = [
        self::STATUS_FOR_PREVIEW => [
            'actor' => 'FMD Section Chief',      'next' => self::STATUS_FOR_DIVISION_MANAGER,   'return' => self::STATUS_RETURNED,
        ],
        self::STATUS_FOR_DIVISION_MANAGER => [
            'actor' => 'FMD Division Manager',   'next' => self::STATUS_FOR_DEPARTMENT_MANAGER, 'return' => self::STATUS_RETURNED_TO_SECTION_CHIEF,
        ],
        self::STATUS_FOR_DEPARTMENT_MANAGER => [
            'actor' => 'FMD Department Manager', 'next' => self::STATUS_APPROVED,               'return' => self::STATUS_RETURNED_TO_DIVISION_MANAGER,
        ],
        self::STATUS_RETURNED_TO_SECTION_CHIEF => [
            'actor' => 'FMD Section Chief',      'next' => self::STATUS_FOR_DIVISION_MANAGER,   'return' => self::STATUS_RETURNED,
        ],
        self::STATUS_RETURNED_TO_DIVISION_MANAGER => [
            'actor' => 'FMD Division Manager',   'next' => self::STATUS_FOR_DEPARTMENT_MANAGER, 'return' => self::STATUS_RETURNED_TO_SECTION_CHIEF,
        ],
    ];

    /**
     * The clock an allocation number is stamped with. The number carries the year, quarter and month of the day it
     * is given, and the agency works in Philippine time — the server itself runs on UTC, which would date a number
     * wrongly for the first eight hours of a month.
     */
    private const ALLOCATION_NUMBER_TIMEZONE = 'Asia/Manila';

    /** VARCHAR columns → max length. */
    private const STRING_FIELDS = [
        'allocation_type' => 20,
        'allocation_number' => 100,
        'document_type' => 20,
        'pr_number' => 50,
        'po_number' => 50,
        'workflow_route' => 20,
        // program_code / sub_program_code are copies of fims.program.program_code / fims.sub_program.sub_program_code
        // (VARCHAR(64), generated "<year>-<rc_code>-<NNN>" codes run 27+ chars), so they share that width.
        // project_code is a copy of fims.project.project_code, VARCHAR(64) too once alter_table/15 is run
        // (generated "<year>-<rc_id>-<prog3>-<subprog4>-<series4>" codes are 22+ chars).
        'program_code' => 64,
        'sub_program_code' => 64,
        'project_code' => 64,
    ];

    private const INT_FIELDS = [
        'fiscal_year', 'requesting_district_id',
        'vendor_id', 'parent_id', 'category_id', 'status_id',
    ];

    /**
     * VARCHAR(50), the creator's fims.responsibility_code.rc_id. Deliberately NOT in STRING_FIELDS:
     * it is never read from the body — create() fills it from the verified user (see requestingRcOf()).
     */
    private const REQUESTING_RC_FIELD = 'requesting_rc_id';

    /**
     * request_referrence_no (VARCHAR(50), "RR-001", "RR-002", ...) is generated BY THE DATABASE: the column's DEFAULT
     * takes the next value of a sequence (alter_table/19). It is deliberately in none of the writable lists above, so
     * a body value is ignored: create() never names the column (the default always applies) and update() can never
     * change or blank it. Note the spelling — it is the column's name. List / read return it; create returns the
     * one just generated.
     */
    private const GENERATED_REFERENCE_FIELD = 'request_referrence_no';

    private const AMOUNT_FIELDS = ['pr_amount', 'po_amount'];

    /**
     * A Program / Sub-Program / Project that does not exist yet can be TYPED instead of picked: the body then carries
     * its name (and no code) and it is created here, together with the memo request — see resolveTypedEntities().
     * body key => level. The three name columns are VARCHAR(255).
     */
    private const TYPED_NAME_FIELDS = ['program' => 'program_name', 'sub_program' => 'sub_program_name', 'project' => 'project_name'];
    private const TYPED_NAME_MAX = 255;

    /** Largest amount accepted (NUMERIC(18,2) holds more; this keeps float math exact to the centavo). */
    private const MAX_AMOUNT = 9999999999999.99;

    /** Every column except line_transaction (jsonb, can be large) — used by list / all. */
    private const LIST_COLUMNS = 'a.allocation_id, a.request_referrence_no, a.allocation_type, a.allocation_number, a.fiscal_year,
        a.requesting_rc_id, a.requesting_district_id, a.program_code, a.sub_program_code, a.project_code,
        a.vendor_id, a.parent_id, a.document_type, a.description, a.pr_number, a.pr_amount, a.po_number, a.po_amount,
        a.workflow_route, a.category_id, a.is_bidding, a.multiple_node_id, a.status_id, a.user_id,
        a.created_at, a.updated_at';

    // -----------------------------------------------------------------------
    // Fetch all — no pagination (for /budget_allocation/all, e.g. the Parent picker)
    // Fetch with pagination (for /budget_allocation/list) when $page is provided
    //
    // $filters: search, fiscal_year, status_id, status (a status NAME), allocation_type, exclude_id,
    //           rc_id (the requesting office — see below)
    // -----------------------------------------------------------------------
    public function fetchAlls(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['a.deleted_at IS NULL'];

            $search = trim((string) ($filters['search'] ?? ''));
            if ($search !== '') {
                $s = App\QuotedValue('%'.$search.'%', DataType::STRING, 'DB');
                $where[] = "(a.request_referrence_no ILIKE {$s} OR a.allocation_number ILIKE {$s} OR a.pr_number ILIKE {$s}
                             OR a.po_number ILIKE {$s} OR a.description ILIKE {$s} OR v.vendor_name ILIKE {$s})";
            }
            if (($fy = $this->intFilter($filters, 'fiscal_year')) !== null) {
                $where[] = "a.fiscal_year = {$fy}";
            }
            if (($statusId = $this->intFilter($filters, 'status_id')) !== null) {
                $where[] = "a.status_id = {$statusId}";
            }
            // status: a status NAME (settings_status.name of a memo-request status, e.g. "Approved") for callers that
            // cannot know the id — it differs per install, which is also why Draft is looked up by name. A name that
            // matches no status matches no row: it never falls back to "everything".
            $statusName = trim((string) ($filters['status'] ?? ''));
            if ($statusName !== '') {
                $where[] = "a.status_id IN (SELECT s.id FROM {$this->tableStatus} s WHERE s.status_for = "
                    .App\QuotedValue(self::STATUS_FOR, DataType::STRING, 'DB')
                    .' AND LOWER(s.name) = LOWER('.App\QuotedValue($statusName, DataType::STRING, 'DB').'))';
            }
            if (($excludeId = $this->intFilter($filters, 'exclude_id')) !== null) {
                $where[] = "a.allocation_id <> {$excludeId}";
            }
            // rc_id: only the requests of this office (requesting_rc_id) AND of the offices under it — a parent office also
            // sees its child offices' requests (a Region 3 user sees Region 3's district offices too), exactly as
            // BudgetReqService::fetchAlls does. '' = every office. The routes fill this from the LOGGED-IN user, never
            // from what the client sent (see budget_allocation.php).
            $rcId = trim((string) ($filters['rc_id'] ?? ''));
            if ($rcId !== '') {
                $rcIds = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];
                $where[] = 'a.requesting_rc_id IN ('.implode(', ', array_map(
                    fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'),
                    $rcIds
                )).')';
            }
            $type = trim((string) ($filters['allocation_type'] ?? ''));
            if ($type !== '') {
                $where[] = 'UPPER(a.allocation_type) = UPPER('.App\QuotedValue($type, DataType::STRING, 'DB').')';
            }
            $rcId = trim((string) ($filters['rc_id'] ?? ''));
            if ($rcId !== '') {
                // The user's RC plus every descendant RC (a Region also sees its district offices) — not a flat equality.
                $rcIds = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];
                $quotedRcIds = implode(', ', array_map(
                    fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'),
                    $rcIds
                ));
                $where[] = "a.requesting_rc_id IN ({$quotedRcIds})";
            }
            $whereSql = implode(' AND ', $where);
            $order = 'ORDER BY a.created_at DESC, a.allocation_id DESC';

            if ($page === null) {
                $data = App\ExecuteRows($this->baseSelect(self::LIST_COLUMNS)." WHERE {$whereSql} {$order}", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $limit = min(200, max(1, $limit));
            $page = max(1, $page);
            $offset = ($page - 1) * $limit;

            // Count + totals cover every row matching the filters (not just this page) for the KPI cards.
            $core = "{$this->table} a LEFT JOIN {$this->tableVendor} v ON v.vendor_id = a.vendor_id";
            $summary = App\ExecuteRow(
                "SELECT COUNT(*) AS total,
                        COALESCE(SUM(a.pr_amount), 0) AS total_pr_amount,
                        COALESCE(SUM(a.po_amount), 0) AS total_po_amount
                 FROM {$core} WHERE {$whereSql}",
                'DB'
            );
            $total = (int) ($summary['total'] ?? 0);

            $data = App\ExecuteRows(
                $this->baseSelect(self::LIST_COLUMNS)." WHERE {$whereSql} {$order} LIMIT {$limit} OFFSET {$offset}",
                'DB'
            );

            return [
                'success' => true,
                'data' => $data ?: [],
                'summary' => $summary,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchAlls '.$e->getMessage());

            return $this->fail('Failed to load memo requests.');
        }
    }

    // -----------------------------------------------------------------------
    // Fetch one by ID — /budget_allocation/read/{id}
    //
    // $withFiles adds file_attachments[]: the Supporting Documents of its multiple_node_id
    // (fims.file_attachments -> fims.ip_filemeta, see FileUploadServices::attachmentsFor()).
    // Internal callers (update / delete) leave it off.
    // -----------------------------------------------------------------------
    public function fetchById(int|string $id, bool $withFiles = false): array
    {
        try {
            $id = (int) $id; // safe cast — no QuotedValue needed for integers
            $data = App\ExecuteRow($this->baseSelect('a.*')." WHERE a.allocation_id = {$id} AND a.deleted_at IS NULL", 'DB');

            if (!$data) {
                return $this->fail("Memo request #{$id} not found.");
            }
            // jsonb comes back as a string — hand the client the decoded structure.
            if (isset($data['line_transaction']) && is_string($data['line_transaction'])) {
                $data['line_transaction'] = json_decode($data['line_transaction'], true);
            }
            if ($withFiles) {
                $data['file_attachments'] = (new FileUploadServices())->attachmentsFor($data['multiple_node_id'] ?? null);
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchById '.$e->getMessage());

            return $this->fail('Failed to load memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Create — /budget_allocation/add
    //
    // Program / Sub-Program / Project: send the code of an existing one (program_code, ...) OR the name of a new one
    // (program_name, ...). A new one is created — found by name first, so typing an existing name never duplicates it —
    // in the SAME transaction as the memo request: if anything fails, nothing at all is saved.
    // -----------------------------------------------------------------------
    public function create(array $body, ?int $userId = null): array
    {
        $inTransaction = false;
        try {
            [$clean, $err] = $this->normalize($body);
            if ($err) {
                return $this->fail($err);
            }
            [$names, $err] = $this->typedNames($body);
            if ($err) {
                return $this->fail($err);
            }
            if (($clean['fiscal_year'] ?? null) === null) {
                return $this->fail('fiscal_year is required.');
            }
            // The requesting office is the creator's own Responsibility Center: uac.users.rc_id, looked up from
            // the verified user — whatever the body says about it is ignored. A user without an RC leaves it NULL.
            if ($userId !== null && $userId !== 0 && ($rcId = $this->requestingRcOf($userId)) !== null) {
                $clean[self::REQUESTING_RC_FIELD] = $rcId;
            }

            // Typed (new) Program / Sub-Program / Project are created first, inside the transaction that also saves the row.
            $created = [];
            if ($names) {
                App\Execute('BEGIN', 'DB');
                $inTransaction = true;
                [$created, $err] = $this->resolveTypedEntities($clean, $names, null, $userId);
                if ($err) {
                    App\Execute('ROLLBACK', 'DB');

                    return $this->fail($err);
                }
            }

            if ($err = $this->validateReferences($clean)) {
                if ($inTransaction) {
                    App\Execute('ROLLBACK', 'DB');
                }

                return $this->fail($err);
            }

            if (($clean['status_id'] ?? null) === null && ($draft = $this->getStatusIdByName('Draft')) !== null) {
                $clean['status_id'] = $draft;
            }

            $columns = [];
            $values = [];
            foreach ($clean as $field => $value) {
                $columns[] = App\QuotedName($field, 'DB');
                $values[] = $this->literal($field, $value);
            }
            if ($userId !== null && $userId !== 0) {
                $columns[] = App\QuotedName('user_id', 'DB');
                $values[] = (string) $userId;
            }

            // RETURNING also hands back the reference number the database generated for this row (RR-001, ...).
            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (".implode(', ', $columns).') VALUES ('.implode(', ', $values).') RETURNING allocation_id, '.self::GENERATED_REFERENCE_FIELD,
                'DB'
            );
            if ($inTransaction) {
                App\Execute('COMMIT', 'DB');
                $inTransaction = false;
            }

            $result = [
                'success' => true,
                'message' => 'Memo request created successfully.',
                'allocation_id' => isset($row['allocation_id']) ? (int) $row['allocation_id'] : null,
                self::GENERATED_REFERENCE_FIELD => $row[self::GENERATED_REFERENCE_FIELD] ?? null,
            ];
            if ($created) {
                $result['created'] = $created;   // the codes of the Program / Sub-Program / Project this call created
            }

            return $result;
        } catch (\Exception $e) {
            if ($inTransaction) {
                App\Execute('ROLLBACK', 'DB');
            }
            App\LogError('BudgetAllocationService::create '.$e->getMessage());

            return $this->fail('Failed to create memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Update — /budget_allocation/update/{id}   (PATCH: only the fields sent change)
    // -----------------------------------------------------------------------
    public function update(int|string $id, array $body): array
    {
        $inTransaction = false;
        try {
            $id = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            [$clean, $err] = $this->normalize($body);
            if ($err) {
                return $this->fail($err);
            }
            [$names, $err] = $this->typedNames($body);
            if ($err) {
                return $this->fail($err);
            }
            if (!$clean && !$names) {
                return $this->fail('No valid fields provided for update.');
            }
            if (array_key_exists('fiscal_year', $clean) && $clean['fiscal_year'] === null) {
                return $this->fail('fiscal_year cannot be empty.');
            }

            // Typed (new) Program / Sub-Program / Project: created first, inside the transaction that also saves the change.
            $created = [];
            if ($names) {
                App\Execute('BEGIN', 'DB');
                $inTransaction = true;
                [$created, $err] = $this->resolveTypedEntities($clean, $names, $check['data'], null);
                if ($err) {
                    App\Execute('ROLLBACK', 'DB');

                    return $this->fail($err);
                }
            }

            if ($err = $this->validateReferences($clean, $check['data'], $id)) {
                if ($inTransaction) {
                    App\Execute('ROLLBACK', 'DB');
                }

                return $this->fail($err);
            }

            $set = [];
            foreach ($clean as $field => $value) {
                $set[] = App\QuotedName($field, 'DB').' = '.$this->literal($field, $value);
            }
            $set[] = 'updated_at = CURRENT_TIMESTAMP';

            App\Execute("UPDATE {$this->table} SET ".implode(', ', $set)." WHERE allocation_id = {$id} AND deleted_at IS NULL", 'DB');
            if ($inTransaction) {
                App\Execute('COMMIT', 'DB');
                $inTransaction = false;
            }

            $result = ['success' => true, 'message' => 'Memo request updated successfully.'];
            if ($created) {
                $result['created'] = $created;
            }

            return $result;
        } catch (\Exception $e) {
            if ($inTransaction) {
                App\Execute('ROLLBACK', 'DB');
            }
            App\LogError('BudgetAllocationService::update '.$e->getMessage());

            return $this->fail('Failed to update memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Delete (soft) — /budget_allocation/delete/{id}
    // Blocked while other memo requests still have this one as their parent.
    // -----------------------------------------------------------------------
    public function delete(int|string $id): array
    {
        try {
            $id = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            $children = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE parent_id = {$id} AND deleted_at IS NULL",
                'DB'
            );
            if ($children > 0) {
                return $this->fail("Memo request #{$id} is the parent of {$children} other memo request(s) — delete or re-parent those first.");
            }

            App\Execute(
                "UPDATE {$this->table} SET deleted_at = CURRENT_TIMESTAMP, updated_at = CURRENT_TIMESTAMP WHERE allocation_id = {$id}",
                'DB'
            );

            return ['success' => true, 'message' => "Memo request #{$id} deleted successfully."];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::delete '.$e->getMessage());

            return $this->fail('Failed to delete memo request.');
        }
    }

    // -----------------------------------------------------------------------
    // Lookups for the Memo Request form
    // -----------------------------------------------------------------------

    /** Active departments (uac.departments) for the Requesting Department / District pickers. */
    public function fetchDepartments(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT department_id, department_name, department_code, department_acronym, department_type
                 FROM {$this->tableDepartment}
                 WHERE COALESCE(is_active, TRUE)
                 ORDER BY department_name",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchDepartments '.$e->getMessage());

            return $this->fail('Failed to load departments.');
        }
    }

    /** Statuses defined for memo requests (settings_status.status_for = 'budget_allocation'). */
    public function fetchStatuses(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT id, name, color FROM {$this->tableStatus}
                 WHERE status_for = ".App\QuotedValue(self::STATUS_FOR, DataType::STRING, 'DB').'
                 ORDER BY id',
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('BudgetAllocationService::fetchStatuses '.$e->getMessage());

            return $this->fail('Failed to load statuses.');
        }
    }

    // -----------------------------------------------------------------------
    // Internals
    // -----------------------------------------------------------------------

    private function fail(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    // -----------------------------------------------------------------------
    // Review — /budget_allocation/process/{id}
    //
    // The Financial Management Department (FMD) reviews a request that its office submitted for preview, in three steps
    // (REVIEW_STEPS): For Preview (FMD Section Chief) -> For Division Manager (FMD Division Manager) ->
    // For Department Manager (FMD Department Manager) -> Approved. At each step:
    //   approve -> the next status of the chain; the LAST approval gives 'Approved' and the ALLOCATION NUMBER (below);
    //   return  -> ONE step back, to the user who endorsed it (status 'Returned to Division Manager' / 'Returned to
    //              Section Chief', which that user acts on); the Section Chief's return gives 'Returned' (back to the
    //              requesting office). No number either way.
    // Only an FMD user may do this (the central review RC, GlobalService::isCentralReviewRc — read with getUserRcId, which
    // fails closed), only to a request that is in one of the three review statuses, and only when the user holds the
    // user level of that step. The status change and the number are written in ONE transaction, and the UPDATE itself
    // re-checks the status, so two reviewers acting at once cannot both succeed.
    //
    // Returns { success, message, status_name, allocation_number }; a refusal for the caller's office or user level also
    // carries `forbidden: true` (the route answers 403 for it).
    // -----------------------------------------------------------------------
    public function process(int|string $id, string $decision, ?int $userId = null): array
    {
        $inTransaction = false;
        try {
            $decision = strtolower(trim($decision));
            if (!in_array($decision, ['approve', 'return'], true)) {
                return $this->fail("decision must be 'approve' or 'return'.");
            }
            if (!$this->isReviewer($userId)) {
                return $this->fail('Only the Financial Management Department can process memo requests.') + ['forbidden' => true];
            }

            $id = (int) $id;
            $found = $this->fetchById($id);
            if (!$found['success']) {
                return $found;
            }
            $row = $found['data'];

            // Which step of the chain is it at, and is this user the one who acts on that step?
            $step = $this->reviewStepOf($row);
            if ($step === null) {
                return $this->fail('Only a memo request that is under review ('.implode(', ', array_keys(self::REVIEW_STEPS))
                    .') can be approved or returned — this one is '
                    .(($row['status_name'] ?? '') !== '' ? $row['status_name'] : 'without a status').'.');
            }
            if (!$this->hasUserLevel($userId, $step['actor'])) {
                return $this->fail("This memo request is {$step['status']}: only the {$step['actor']} can approve or return it at this step.")
                    + ['forbidden' => true];
            }

            // The status the decision gives. Missing = the seed was not run: say so, change nothing.
            $currentId = (int) ($row['status_id'] ?? 0);
            $targetName = $decision === 'approve' ? $step['next'] : $step['return'];
            $targetId = $this->getStatusIdByName($targetName);
            if ($targetId === null) {
                return $this->fail("The status \"{$targetName}\" is not set up in the database yet, so nothing was changed. "
                    .'Ask an administrator to run the SQL installer (node install_system.js --sql-only).');
            }
            $isFinal = $targetName === self::STATUS_APPROVED;

            App\Execute('BEGIN', 'DB');
            $inTransaction = true;

            $set = ["status_id = {$targetId}", 'updated_at = CURRENT_TIMESTAMP'];
            $number = trim((string) ($row['allocation_number'] ?? ''));
            if ($isFinal && $number === '') {
                // (one that already has a number keeps it — approving never renumbers)
                $number = $this->nextAllocationNumber((string) ($row['allocation_type'] ?? ''), $row['document_type'] ?? null);
                $set[] = 'allocation_number = '.App\QuotedValue($number, DataType::STRING, 'DB');
            }
            $done = App\ExecuteRow(
                "UPDATE {$this->table} SET ".implode(', ', $set)
                ." WHERE allocation_id = {$id} AND deleted_at IS NULL AND status_id = {$currentId} RETURNING allocation_id",
                'DB'
            );
            if (!$done) {   // somebody else got there first (or deleted it) between the check above and now
                App\Execute('ROLLBACK', 'DB');
                $inTransaction = false;

                return $this->fail('This memo request was changed by someone else in the meantime. Reload the list and try again.');
            }
            App\Execute('COMMIT', 'DB');
            $inTransaction = false;

            if ($decision === 'return') {
                $message = isset(self::REVIEW_STEPS[$targetName])
                    ? 'Memo request returned to the '.self::REVIEW_STEPS[$targetName]['actor']." (now {$targetName})."
                    : 'Memo request returned to the requesting office.';
            } elseif ($isFinal) {
                $message = 'Memo request approved.'.($number !== '' ? " Allocation number: {$number}." : '');
            } else {
                $message = "Memo request endorsed: it is now {$targetName} (".self::REVIEW_STEPS[$targetName]['actor'].').';
            }

            return [
                'success' => true,
                'message' => $message,
                'status_name' => $targetName,
                'allocation_number' => $number !== '' ? $number : null,
            ];
        } catch (\Exception $e) {
            if ($inTransaction) {
                try {
                    App\Execute('ROLLBACK', 'DB');
                } catch (\Throwable $ignored) {
                }
            }
            App\LogError('BudgetAllocationService::process '.$e->getMessage());

            return $this->fail('Failed to process the memo request.');
        }
    }

    /**
     * Where a request stands in the review chain, for the "Process" modal (GET /budget_allocation/read/{id} adds it as
     * data.review): in_review, the status it is in, who acts on it (actor, a user level name), the status an approval
     * gives (next_status), whether that is the final approval (is_final) and whether THIS user may decide it now
     * (can_decide: an FMD user holding the step's user level). process() enforces the same rules again.
     */
    public function reviewInfo(array $row, ?int $userId): array
    {
        $step = $this->reviewStepOf($row);
        if ($step === null) {
            return ['in_review' => false, 'can_decide' => false];
        }

        return [
            'in_review' => true,
            'status' => $step['status'],
            'actor' => $step['actor'],
            'next_status' => $step['next'],
            'is_final' => $step['next'] === self::STATUS_APPROVED,
            // where a Return sends it, and whom (the actor of that step; null = back to the requesting office)
            'return_status' => $step['return'],
            'return_actor' => self::REVIEW_STEPS[$step['return']]['actor'] ?? null,
            'is_returned' => in_array($step['status'], [self::STATUS_RETURNED_TO_SECTION_CHIEF, self::STATUS_RETURNED_TO_DIVISION_MANAGER], true),
            'can_decide' => $this->isReviewer($userId) && $this->hasUserLevel($userId, $step['actor']),
        ];
    }

    /** The REVIEW_STEPS entry (+ 'status') of the status this row is in, or null when it is not under review. */
    private function reviewStepOf(array $row): ?array
    {
        $name = strtolower(trim((string) ($row['status_name'] ?? '')));
        foreach (self::REVIEW_STEPS as $status => $step) {
            if ($name === strtolower($status)) {
                return $step + ['status' => $status];
            }
        }

        return null;
    }

    /**
     * Does this user hold the user level called $levelName? uac.users.user_level_id is a VARCHAR that may list several
     * ids ("3,10"); each is looked up in uac.user_levels and compared by NAME, ignoring case. A missing user, no level,
     * or a lookup failure is "no" — never "yes".
     */
    private function hasUserLevel(?int $userId, string $levelName): bool
    {
        try {
            $userId = (int) $userId;
            if ($userId <= 0) {
                return false;
            }
            $raw = (string) App\ExecuteScalar("SELECT user_level_id FROM {$this->tableUser} WHERE user_id = {$userId}", 'DB');
            $ids = [];
            foreach (explode(',', $raw) as $part) {
                if (preg_match('/^-?\d+$/', $part = trim($part))) {
                    $ids[] = (int) $part;
                }
            }
            if (!$ids) {
                return false;
            }

            return (int) App\ExecuteScalar(
                'SELECT COUNT(*) FROM uac.user_levels WHERE user_level_id IN ('.implode(', ', $ids).')'
                .' AND LOWER(TRIM(name)) = LOWER('.App\QuotedValue(trim($levelName), DataType::STRING, 'DB').')',
                'DB'
            ) > 0;
        } catch (\Throwable $e) {
            App\LogError('BudgetAllocationService::hasUserLevel '.$e->getMessage());

            return false;
        }
    }

    /** Is this user of the central review RC (FMD)? A missing user, no RC, or a lookup failure is "no" — never "yes". */
    private function isReviewer(?int $userId): bool
    {
        try {
            $userId = (int) $userId;

            return $userId > 0 && GlobalService::isCentralReviewRc(GlobalService::getUserRcId($userId));
        } catch (\Throwable $e) {
            App\LogError('BudgetAllocationService::isReviewer '.$e->getMessage());

            return false;
        }
    }

    // -----------------------------------------------------------------------
    // Allocation number templates — the ones the Memo Request form shows (buildAllocationNumber() in
    // form/budget-allocation-form.php), by allocation type and document type. With y = year, yy = its last two digits,
    // q = quarter (1-4), m = month within the quarter (1-3) of the day the number is given:
    //
    //   CBA   CBA <y>-<q><m>-86-<series>          e.g.  CBA 2026-42-86-01
    //   RFA   <yy><q><m>-<code>-<series>          e.g.  2642-87-01     code 57 for document type COE, 87 for CO
    //   LAA   LAA <y>-<q><m>-<series>             e.g.  LAA 2026-42-01
    //   other <TYPE> <y>-<q><m>-<series>
    //
    // <series> is two digits at least (01, 02, … 99, 100). It counts within one PREFIX (everything before the series),
    // so it starts again at 01 each month for each type — and never repeats within a prefix. Numbers of deleted
    // requests count too, so a number is never given twice.
    // -----------------------------------------------------------------------
    public static function allocationNumberPrefix(string $type, ?string $documentType, \DateTimeInterface $at): string
    {
        $type = strtoupper(trim($type));
        $type = $type === '' ? 'CBA' : $type;
        $y = $at->format('Y');
        $yy = substr($y, -2);
        $mo = (int) $at->format('n');
        $q = (int) ceil($mo / 3);
        $qm = $q.($mo - ($q - 1) * 3);

        return match ($type) {
            'CBA' => "CBA {$y}-{$qm}-86-",
            'RFA' => "{$yy}{$qm}-".(strtoupper(trim((string) $documentType)) === 'COE' ? '57' : '87').'-',
            'LAA' => "LAA {$y}-{$qm}-",
            default => "{$type} {$y}-{$qm}-",
        };
    }

    public static function formatAllocationNumber(string $prefix, int $series): string
    {
        return $prefix.str_pad((string) $series, 2, '0', STR_PAD_LEFT);
    }

    /**
     * The next free allocation number for this type / document type, today (Philippine time). Call it INSIDE the
     * transaction that stores the number: it takes a transaction-level advisory lock first, so two approvals running
     * at once are numbered one after the other and cannot read the same "last series" — the lock is released by the
     * COMMIT / ROLLBACK of that transaction.
     */
    private function nextAllocationNumber(string $type, ?string $documentType): string
    {
        App\ExecuteScalar("SELECT pg_advisory_xact_lock(hashtext('fims.budget_allocation.allocation_number'))", 'DB');

        $prefix = self::allocationNumberPrefix($type, $documentType, new \DateTimeImmutable('now', new \DateTimeZone(self::ALLOCATION_NUMBER_TIMEZONE)));
        $len = mb_strlen($prefix);
        $from = $len + 1;
        $last = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CASE WHEN SUBSTRING(allocation_number FROM {$from}) ~ '^[0-9]{1,6}\$'
                                      THEN CAST(SUBSTRING(allocation_number FROM {$from}) AS INTEGER) END), 0)
             FROM {$this->table}
             WHERE LEFT(allocation_number, {$len}) = ".App\QuotedValue($prefix, DataType::STRING, 'DB'),
            'DB'
        );

        return self::formatAllocationNumber($prefix, $last + 1);
    }

    /**
     * Looked up by name each time rather than hardcoding its id — the id a seed
     * row lands on depends on insert order (same approach as BudgetReqService).
     */
    private function getStatusIdByName(string $name): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM {$this->tableStatus} WHERE status_for = ".App\QuotedValue(self::STATUS_FOR, DataType::STRING, 'DB')
            .' AND name = '.App\QuotedValue($name, DataType::STRING, 'DB'),
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    /**
     * SQL for the sum of "requested_amount" over the lines of line_transaction (jsonb; an array of lines, or a single
     * line object). A line whose requested_amount is missing, blank or not a number counts as 0, so one bad line can
     * never fail the whole list. Commas in a text amount ("1,500,000.00") are ignored. Constant SQL, no user input.
     */
    private function requestedTotalSql(): string
    {
        return "COALESCE((
                    SELECT SUM(CASE
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'number' THEN (ln.value->>'requested_amount')::numeric
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'string'
                                      AND REPLACE(ln.value->>'requested_amount', ',', '') ~ '^-?[0-9]+([.][0-9]+)?$'
                                   THEN REPLACE(ln.value->>'requested_amount', ',', '')::numeric
                                 ELSE 0
                               END)
                    FROM jsonb_array_elements(CASE jsonb_typeof(a.line_transaction)
                                                WHEN 'array'  THEN a.line_transaction
                                                WHEN 'object' THEN jsonb_build_array(a.line_transaction)
                                                ELSE '[]'::jsonb
                                              END) AS ln(value)
                    WHERE jsonb_typeof(ln.value) = 'object'
                ), 0)";
    }

    /** SELECT + every join the list / read views need; callers append WHERE / ORDER BY. */
    private function baseSelect(string $columns): string
    {
        return "SELECT {$columns},
                       st.name  AS status_name,
                       st.color AS status_color,
                       v.vendor_name,
                       rc.rc_code     AS requesting_rc_code,
                       rc.rc_name     AS requesting_rc_name,
                       rc.office_type AS requesting_rc_office_type,
                       dd.department_name AS requesting_district_name,
                       pg.program_name,
                       sp.sub_program_name,
                       pj.project_name,
                       pa.request_referrence_no AS parent_request_referrence_no,
                       {$this->requestedTotalSql()} AS requested_total
                FROM {$this->table} a
                LEFT JOIN {$this->tableStatus} st     ON st.id = a.status_id
                LEFT JOIN {$this->tableVendor} v      ON v.vendor_id = a.vendor_id
                LEFT JOIN {$this->tableRc} rc         ON rc.rc_id = a.requesting_rc_id
                LEFT JOIN {$this->tableDepartment} dd ON dd.department_id = a.requesting_district_id
                LEFT JOIN {$this->tableProgram} pg    ON pg.program_code = a.program_code
                LEFT JOIN {$this->tableSubProgram} sp ON sp.sub_program_code = a.sub_program_code
                LEFT JOIN {$this->tableProject} pj    ON pj.project_code = a.project_code
                LEFT JOIN {$this->table} pa           ON pa.allocation_id = a.parent_id";
    }

    /** The Responsibility Center (rc_id) of a user: uac.users.rc_id, that column only. Null when the user has none. */
    private function requestingRcOf(int $userId): ?string
    {
        $rcId = trim((string) App\ExecuteScalar("SELECT rc_id FROM {$this->tableUser} WHERE user_id = {$userId}", 'DB'));

        return $rcId === '' ? null : $rcId;
    }

    private function intFilter(array $filters, string $key): ?int
    {
        $v = $filters[$key] ?? null;

        return ($v !== null && $v !== '' && is_numeric($v)) ? (int) $v : null;
    }

    /**
     * The names of Programs / Sub-Programs / Projects that were TYPED instead of picked: body program_name /
     * sub_program_name / project_name. Trimmed, inner whitespace collapsed; blank ones are dropped.
     *
     * @return array{0: array<string,string>, 1: ?string} [level => name (program | sub_program | project), error]
     */
    private function typedNames(array $body): array
    {
        $names = [];
        foreach (self::TYPED_NAME_FIELDS as $level => $key) {
            if (!array_key_exists($key, $body)) {
                continue;
            }
            $v = $body[$key];
            if ($v !== null && !is_scalar($v)) {
                return [[], "{$key} must be a plain value."];
            }
            $v = trim((string) preg_replace('/\s+/u', ' ', (string) ($v ?? '')));
            if ($v === '') {
                continue;
            }
            if (mb_strlen($v) > self::TYPED_NAME_MAX) {
                return [[], "{$key} must be ".self::TYPED_NAME_MAX.' characters or fewer.'];
            }
            $names[$level] = $v;
        }

        return [$names, null];
    }

    /**
     * Turns the typed names into codes, creating what does not exist yet — in the order Program, Sub-Program, Project,
     * because each belongs to the one before it. A level whose code was PICKED (present in $clean) is left alone: the
     * picked code wins over any name. Writes the resulting codes into $clean. Must run inside the caller's open
     * transaction (the caller rolls back on an error, so nothing created here survives a failed save).
     *
     * On update ($current = the stored row) a Program / Sub-Program that differs from the stored one clears the
     * levels below it unless the body supplies them: the old Sub-Program / Project cannot belong to it.
     *
     * @param array<string,mixed>  $clean normalized body (modified)
     * @param array<string,string> $names from typedNames()
     *
     * @return array{0: array<string,string>, 1: ?string} [codes created (program_code / sub_program_code / project_code), error]
     */
    private function resolveTypedEntities(array &$clean, array $names, ?array $current, ?int $userId): array
    {
        $created = [];
        // These read $clean BY REFERENCE: each level below must see the codes the levels above it have just resolved
        // (an arrow function would have copied $clean at this point, and never seen them).
        $effective = function (string $f) use (&$clean, $current) {
            return array_key_exists($f, $clean) ? $clean[$f] : ($current[$f] ?? null);
        };
        $hasCode = function (string $f) use (&$clean) {
            return ($clean[$f] ?? null) !== null;
        };

        if (isset($names['program']) && !$hasCode('program_code')) {
            [$code, $isNew, $err] = $this->findOrCreateProgram($names['program'], $userId);
            if ($err) {
                return [$created, $err];
            }
            $clean['program_code'] = $code;
            if ($isNew) {
                $created['program_code'] = $code;
            }
            if ($current !== null && ($current['program_code'] ?? null) !== $code) {
                foreach (['sub_program_code', 'project_code'] as $below) {
                    if (!array_key_exists($below, $clean)) {
                        $clean[$below] = null;
                    }
                }
            }
        }

        if (isset($names['sub_program']) && !$hasCode('sub_program_code')) {
            $program = $effective('program_code');
            if ($program === null) {
                return [$created, 'A new sub-program needs a program: pick or type the program first.'];
            }
            [$code, $isNew, $err] = $this->findOrCreateSubProgram($names['sub_program'], (string) $program);
            if ($err) {
                return [$created, $err];
            }
            $clean['sub_program_code'] = $code;
            if ($isNew) {
                $created['sub_program_code'] = $code;
            }
            if ($current !== null && ($current['sub_program_code'] ?? null) !== $code && !array_key_exists('project_code', $clean)) {
                $clean['project_code'] = null;
            }
        }

        if (isset($names['project']) && !$hasCode('project_code')) {
            $program = $effective('program_code');
            if ($program === null) {
                return [$created, 'A new project needs a program: pick or type the program first.'];
            }
            $sub = $effective('sub_program_code');
            $fiscalYr = $effective('fiscal_year');
            [$code, $isNew, $err] = $this->findOrCreateProject($names['project'], (string) $program, $sub === null ? null : (string) $sub, $fiscalYr === null ? null : (int) $fiscalYr);
            if ($err) {
                return [$created, $err];
            }
            $clean['project_code'] = $code;
            if ($isNew) {
                $created['project_code'] = $code;
            }
        }

        return [$created, null];
    }

    /** @return array{0: ?string, 1: bool, 2: ?string} [program_code, created?, error] — an existing program with that name (any case) is reused */
    private function findOrCreateProgram(string $name, ?int $userId): array
    {
        $row = App\ExecuteRow(
            "SELECT program_code FROM {$this->tableProgram} WHERE LOWER(program_name) = LOWER(".App\QuotedValue($name, DataType::STRING, 'DB').') ORDER BY program_code LIMIT 1',
            'DB'
        );
        if ($row) {
            return [(string) $row['program_code'], false, null];
        }

        $result = (new ProgramService())->create(['program_name' => $name], $userId);
        if (!($result['success'] ?? false)) {
            return [null, false, "Could not create the program \"{$name}\": ".($result['message'] ?? 'unknown error')];
        }

        return [(string) $result['data']['program_code'], true, null];
    }

    /** @return array{0: ?string, 1: bool, 2: ?string} [sub_program_code, created?, error] — reused when this program already has one with that name */
    private function findOrCreateSubProgram(string $name, string $programCode): array
    {
        $row = App\ExecuteRow(
            "SELECT sub_program_code FROM {$this->tableSubProgram} WHERE program_code = ".App\QuotedValue($programCode, DataType::STRING, 'DB')
            .' AND LOWER(sub_program_name) = LOWER('.App\QuotedValue($name, DataType::STRING, 'DB').') ORDER BY sub_program_code LIMIT 1',
            'DB'
        );
        if ($row) {
            return [(string) $row['sub_program_code'], false, null];
        }

        try {
            $next = GlobalService::nextSubProgramNumber($programCode);
            $code = GlobalService::formatSubProgramCode($next['prefix'], $next['next']);
        } catch (\InvalidArgumentException $e) {
            return [null, false, "Could not create the sub-program \"{$name}\": ".$e->getMessage()];
        }

        $result = (new SubProgramService())->create(['sub_program_code' => $code, 'sub_program_name' => $name, 'program_code' => $programCode]);
        if (!($result['success'] ?? false)) {
            return [null, false, "Could not create the sub-program \"{$name}\": ".($result['message'] ?? 'unknown error')];
        }

        return [$code, true, null];
    }

    /**
     * @return array{0: ?string, 1: bool, 2: ?string} [project_code, created?, error] — reused when the same program AND
     *                                                sub-program (or, with no sub-program, a project that has none) already has one with that name
     */
    private function findOrCreateProject(string $name, string $programCode, ?string $subProgramCode, ?int $fiscalYr): array
    {
        $subCondition = $subProgramCode === null
            ? 'sub_program_code IS NULL'
            : 'sub_program_code = '.App\QuotedValue($subProgramCode, DataType::STRING, 'DB');
        $row = App\ExecuteRow(
            "SELECT project_code FROM {$this->tableProject} WHERE program_code = ".App\QuotedValue($programCode, DataType::STRING, 'DB')
            ." AND {$subCondition} AND LOWER(project_name) = LOWER(".App\QuotedValue($name, DataType::STRING, 'DB').') ORDER BY project_code LIMIT 1',
            'DB'
        );
        if ($row) {
            return [(string) $row['project_code'], false, null];
        }

        $result = (new ProjectService())->create([
            'project_name' => $name,
            'program_code' => $programCode,
            'sub_program_code' => $subProgramCode ?? '',
            'fiscal_yr' => $fiscalYr,
        ]);
        if (!($result['success'] ?? false)) {
            return [null, false, "Could not create the project \"{$name}\": ".($result['message'] ?? 'unknown error')];
        }

        return [(string) $result['project_code'], true, null];
    }

    /**
     * Turns the request body into typed PHP values, one per writable column that
     * is present in the body ('' / null → null). Unknown keys are ignored, which
     * also keeps user_id / created_at / deleted_at / allocation_id out of reach.
     *
     * @return array{0: array<string, mixed>, 1: ?string} [values, error]
     */
    private function normalize(array $body): array
    {
        $clean = [];

        foreach (self::STRING_FIELDS as $field => $max) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            if ($body[$field] !== null && !is_scalar($body[$field])) {
                return [[], "{$field} must be a plain value."];
            }
            $v = trim((string) ($body[$field] ?? ''));
            if (mb_strlen($v) > $max) {
                return [[], "{$field} must be {$max} characters or fewer."];
            }
            $clean[$field] = $v === '' ? null : $v;
        }

        if (array_key_exists('description', $body)) {
            if ($body['description'] !== null && !is_scalar($body['description'])) {
                return [[], 'description must be plain text.'];
            }
            $v = trim((string) ($body['description'] ?? ''));
            $clean['description'] = $v === '' ? null : $v;
        }

        foreach (self::INT_FIELDS as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            if ($body[$field] !== null && !is_scalar($body[$field])) {
                return [[], "{$field} must be a whole number."];
            }
            $v = trim((string) ($body[$field] ?? ''));
            if ($v === '') {
                $clean[$field] = null;
            } elseif (!preg_match('/^\d+$/', $v) || (int) $v > 2147483647) {
                return [[], "{$field} must be a whole number."];
            } else {
                $clean[$field] = (int) $v;
            }
        }

        foreach (self::AMOUNT_FIELDS as $field) {
            if (!array_key_exists($field, $body)) {
                continue;
            }
            if ($body[$field] !== null && !is_scalar($body[$field])) {
                return [[], "{$field} must be a number."];
            }
            $v = str_replace(',', '', trim((string) ($body[$field] ?? '')));
            if ($v === '') {
                $clean[$field] = null;
            } elseif (!is_numeric($v) || (float) $v < 0 || (float) $v > self::MAX_AMOUNT) {
                return [[], "{$field} must be a number from 0 to 9,999,999,999,999.99."];
            } else {
                $clean[$field] = (float) $v;
            }
        }

        if (array_key_exists('is_bidding', $body)) {
            $b = $body['is_bidding'];
            $clean['is_bidding'] = ($b === null || $b === '') ? null : filter_var($b, FILTER_VALIDATE_BOOLEAN);
        }

        if (array_key_exists('line_transaction', $body)) {
            $lt = $body['line_transaction'];
            if ($lt === null || $lt === '') {
                $clean['line_transaction'] = null;
            } else {
                $decoded = $lt;
                if (is_string($lt)) {
                    $decoded = json_decode($lt, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return [[], 'line_transaction is not valid JSON.'];
                    }
                }
                if (!is_array($decoded)) {
                    return [[], 'line_transaction must be a JSON array or object.'];
                }
                $clean['line_transaction'] = $decoded;
            }
        }

        if (array_key_exists('multiple_node_id', $body)) {
            $v = trim((string) ($body['multiple_node_id'] ?? ''));
            if ($v === '') {
                $clean['multiple_node_id'] = null;
            } elseif (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v)) {
                return [[], 'multiple_node_id must be a UUID.'];
            } else {
                $clean['multiple_node_id'] = strtolower($v);
            }
        }

        return [$clean, null];
    }

    /** SQL literal for one normalized value. Everything user-supplied goes through QuotedValue or a strict cast. */
    private function literal(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        if (isset(self::STRING_FIELDS[$field]) || $field === 'description' || $field === self::REQUESTING_RC_FIELD) {
            return App\QuotedValue((string) $value, DataType::STRING, 'DB');
        }
        if (in_array($field, self::INT_FIELDS, true)) {
            return (string) (int) $value;
        }
        if (in_array($field, self::AMOUNT_FIELDS, true)) {
            return number_format((float) $value, 2, '.', '');
        }
        if ($field === 'is_bidding') {
            return $value ? 'TRUE' : 'FALSE';
        }
        if ($field === 'line_transaction') {
            return App\QuotedValue(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), DataType::STRING, 'DB').'::jsonb';
        }
        if ($field === 'multiple_node_id') {
            return App\QuotedValue((string) $value, DataType::STRING, 'DB').'::uuid';
        }

        throw new \LogicException("No SQL literal rule for field '{$field}'.");
    }

    private function exists(string $table, string $column, int|string $value, string $extraWhere = ''): bool
    {
        $lit = is_int($value) ? (string) $value : App\QuotedValue($value, DataType::STRING, 'DB');

        return (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$table} WHERE {$column} = {$lit}{$extraWhere}", 'DB') > 0;
    }

    /**
     * Friendly errors for bad references (the FKs would reject them too, but with
     * an opaque DB message). $current / $id are set on update: the program →
     * sub-program → project chain is checked against the *effective* values
     * (the body merged over the stored row), and the parent may not be the row
     * itself or one of its descendants.
     *
     * @return ?string error message, or null when everything checks out
     */
    private function validateReferences(array $clean, ?array $current = null, ?int $id = null): ?string
    {
        $checks = [
            ['fiscal_year',               $this->tableFiscalYear, 'fiscal_yr',     '',                                                   'Fiscal year'],
            ['vendor_id',                 $this->tableVendor,     'vendor_id',     '',                                                   'Vendor'],
            ['status_id',                 $this->tableStatus,     'id',            " AND status_for = '".self::STATUS_FOR."'",       'Status'],
            [self::REQUESTING_RC_FIELD,   $this->tableRc,         'rc_id',         '',                                                   'Requesting RC'],
            ['requesting_district_id',    $this->tableDepartment, 'department_id', '',                                                   'Requesting district'],
            ['program_code',              $this->tableProgram,    'program_code',  '',                                                   'Program'],
        ];
        foreach ($checks as [$field, $table, $column, $extra, $label]) {
            $value = $clean[$field] ?? null;
            if ($value !== null && !$this->exists($table, $column, $value, $extra)) {
                return "{$label} '{$value}' does not exist.";
            }
        }

        // ── Parent memo request ──────────────────────────────────────────────
        $parentId = $clean['parent_id'] ?? null;
        if ($parentId !== null) {
            if ($id !== null && $parentId === $id) {
                return 'A memo request cannot be its own parent.';
            }
            if (!$this->exists($this->table, 'allocation_id', $parentId, ' AND deleted_at IS NULL')) {
                return "Parent memo request #{$parentId} does not exist.";
            }
            if ($id !== null) {
                $cursor = $parentId;
                for ($guard = 0; $cursor !== null && $guard < 50; ++$guard) {
                    $row = App\ExecuteRow("SELECT parent_id FROM {$this->table} WHERE allocation_id = {$cursor}", 'DB');
                    $cursor = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
                    if ($cursor === $id) {
                        return 'That parent would create a loop — it already descends from this memo request.';
                    }
                }
            }
        }

        // ── Program → Sub-Program → Project must belong together ─────────────
        if (array_intersect(['program_code', 'sub_program_code', 'project_code'], array_keys($clean))) {
            $effective = static fn (string $f) => array_key_exists($f, $clean) ? $clean[$f] : ($current[$f] ?? null);
            $program = $effective('program_code');
            $sub = $effective('sub_program_code');
            $project = $effective('project_code');

            if ($sub !== null) {
                $row = App\ExecuteRow(
                    "SELECT program_code FROM {$this->tableSubProgram} WHERE sub_program_code = ".App\QuotedValue($sub, DataType::STRING, 'DB'),
                    'DB'
                );
                if (!$row) {
                    return "Sub-program '{$sub}' does not exist.";
                }
                if ($program === null) {
                    return 'Select a program for the sub-program.';
                }
                if ($row['program_code'] !== $program) {
                    return "Sub-program '{$sub}' does not belong to program '{$program}'.";
                }
            }

            if ($project !== null) {
                $row = App\ExecuteRow(
                    "SELECT program_code, sub_program_code FROM {$this->tableProject} WHERE project_code = ".App\QuotedValue($project, DataType::STRING, 'DB'),
                    'DB'
                );
                if (!$row) {
                    return "Project '{$project}' does not exist.";
                }
                if ($program === null) {
                    return 'Select a program for the project.';
                }
                if ($row['program_code'] !== $program) {
                    return "Project '{$project}' does not belong to program '{$program}'.";
                }
                if ($sub !== null && ($row['sub_program_code'] ?? null) !== null && $row['sub_program_code'] !== $sub) {
                    return "Project '{$project}' belongs to sub-program '{$row['sub_program_code']}', not '{$sub}'.";
                }
            }
        }

        return null;
    }
}
