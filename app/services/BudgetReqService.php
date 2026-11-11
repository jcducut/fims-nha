<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Header row for a budget request: one per Fiscal Year. The Programs
 * themselves — and everything below them — live two levels below the
 * header:
 *   fims.budget_req_program  — one row per Program (+ optional Sub-Program)
 *                              added to the request (via addProgram()/
 *                              updateProgram())
 *   fims.budget_req_project  — one row per Project "line item" added to a
 *                              Program block
 *   fims.budget_line_items   — one row per chart-of-accounts allocation
 *                              under a budget_req_project row.
 *
 * A request starts life as Draft. While Draft, submitting the form for a
 * fiscal year that already has a request appends another Program to it
 * (addProgram()); once it is submitted for review (submitForReview()) it
 * is locked — no more Programs can be added, and existing ones can no
 * longer be edited (updateProgram()) or the header deleted (delete()).
 *
 * budget_req.submitted_at (the "Date Submitted" of the lists) is stamped with NOW() by every
 * save of the request's content — creating it (addProgram(), new header), adding a Program to
 * it (addProgram(), existing Draft header), editing a Program (updateProgram()) — and again by
 * submitForReview(). So it always holds the date of the office's LATEST submission / save.
 */
class BudgetReqService
{
    /** Longest "Return" remarks accepted (characters); the Return pop-up's textarea uses the same limit. */
    public const REMARKS_MAX_LENGTH = 2000;

    private string $table = 'fims.budget_req';
    private string $tableBudgetReqProgram = 'fims.budget_req_program';
    private string $tableBudgetReqProject = 'fims.budget_req_project';
    private string $tableLineItems = 'fims.budget_line_items';
    private string $tableTypeOfFund = 'fims.type_of_fund';
    private string $tableComponentType = 'fims.component_type';
    private string $tableProgram = 'fims.program';
    private string $tableSubProgram = 'fims.sub_program';
    private string $tableProject = 'fims.project';
    private string $tableChartOfAccounts = 'fims.chart_of_accounts';
    private string $tableAttachment = 'fims.budget_req_project_attachment';

    // Supporting-document uploads (see uploadSupportingDocFiles()/persistAttachments())
    private const ATTACHMENT_MAX_BYTES = 20 * 1024 * 1024; // 20 MB
    private const ATTACHMENT_ALLOWED_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
    private const ATTACHMENT_KEY_PREFIX = 'budget-req-attachments/';

    /**
     * Looked up by name each time rather than hardcoding its id — the id a seed
     * row lands on depends on insert order, which isn't guaranteed to be stable
     * across environments or re-seeds.
     */
    private function getStatusIdByName(string $name): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM fims.settings_status WHERE status_for = 'budget_req' AND name = ".App\QuotedValue($name, DataType::STRING, 'DB'),
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    private function getDefaultStatusId(): ?int
    {
        return $this->getStatusIdByName('Draft');
    }

    /**
     * Stamps budget_req.submitted_at with NOW() — the date of the request's latest save / submission.
     * Run it inside the caller's open transaction, so the stamp commits (or rolls back) with the change itself.
     */
    private function touchSubmittedAt(string $budgetReferenceNumber): void
    {
        App\Execute(
            "UPDATE {$this->table} SET submitted_at = NOW() WHERE budget_reference_number = ".App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB'),
            'DB'
        );
    }

    private function validateLineItem(array $lineItem, int $projectIndex, int $index): ?string
    {
        $accountId = trim((string) ($lineItem['account_id'] ?? ''));
        $amount = $lineItem['proposed_amount'] ?? $lineItem['amount'] ?? '';
        $particulars = trim((string) ($lineItem['particulars'] ?? ''));
        $label = 'Project '.($projectIndex + 1).', line item '.($index + 1).': ';

        if ($accountId === '') {
            return $label.'select a chart of accounts entry.';
        }
        if ($amount === '' || !is_numeric($amount) || (float) $amount <= 0) {
            return $label.'a numeric proposed amount greater than zero is required.';
        }
        if ($particulars === '') {
            return $label.'particulars is required.';
        }

        $account = App\ExecuteRow(
            "SELECT account_id FROM {$this->tableChartOfAccounts}
             WHERE account_id = ".App\QuotedValue($accountId, DataType::STRING, 'DB')."
               AND status = 'Active' AND posting_allowed = TRUE",
            'DB'
        );
        if (!$account) {
            return $label."chart of accounts entry '{$accountId}' does not exist or is not postable.";
        }

        return null;
    }

    /**
     * Validates one Program block — Program, optional Sub-Program, and
     * every Project block + line item under it — before anything is
     * inserted. Returns an error message, or null when valid. Shared by
     * addProgram() (which also validates fiscal_yr via
     * validateHeaderAndProjects()) and updateProgram() (which never
     * touches fiscal_yr, so it calls this directly).
     */
    private function validateProgramBlock(string $programCode, string $subProgramCode, array $projects): ?string
    {
        if ($programCode === '') {
            return 'program_code is required.';
        }

        $programExists = (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = ".App\QuotedValue($programCode, DataType::STRING, 'DB'),
            'DB'
        );
        if ($programExists === 0) {
            return "Program code '{$programCode}' does not exist.";
        }

        if ($subProgramCode !== '') {
            $subProgram = App\ExecuteRow(
                "SELECT program_code FROM {$this->tableSubProgram} WHERE sub_program_code = ".App\QuotedValue($subProgramCode, DataType::STRING, 'DB'),
                'DB'
            );
            if (!$subProgram) {
                return "Sub-program '{$subProgramCode}' does not exist.";
            }
            if ($subProgram['program_code'] !== $programCode) {
                return "Sub-program '{$subProgramCode}' does not belong to program '{$programCode}'.";
            }
        }

        if (empty($projects)) {
            return 'At least one project with at least one line item is required.';
        }

        foreach ($projects as $pi => $project) {
            // A block identifies its project either by an existing project_code
            // (picked from search) or a project_name (typed with no match —
            // resolved to an existing or newly-created project_code at save time,
            // see resolveProjectCode()).
            $projectCode = trim((string) ($project['project_code'] ?? ''));
            $projectName = trim((string) ($project['project_name'] ?? ''));
            $lineItems = $project['line_items'] ?? [];

            if ($projectCode === '' && $projectName === '') {
                return 'Project '.($pi + 1).': select or enter a project.';
            }

            if ($projectCode !== '') {
                $projectRow = App\ExecuteRow(
                    "SELECT program_code FROM {$this->tableProject} WHERE project_code = ".App\QuotedValue($projectCode, DataType::STRING, 'DB'),
                    'DB'
                );
                if (!$projectRow) {
                    return "Project '{$projectCode}' does not exist.";
                }
                if ($projectRow['program_code'] !== $programCode) {
                    return "Project '{$projectCode}' does not belong to program '{$programCode}'.";
                }
            }

            if (empty($lineItems)) {
                return 'Project '.($pi + 1).': at least one line item is required.';
            }

            foreach ($lineItems as $li => $lineItem) {
                $err = $this->validateLineItem($lineItem, $pi, $li);
                if ($err) {
                    return $err;
                }
            }
        }

        return null;
    }

    private function validateHeaderAndProjects(string $fiscalYr, string $programCode, string $subProgramCode, array $projects): ?string
    {
        if ($fiscalYr === '') {
            return 'fiscal_yr is required.';
        }

        return $this->validateProgramBlock($programCode, $subProgramCode, $projects);
    }

    /**
     * Resolves one project block's project_code — either the one already
     * picked, or (when only a typed project_name was given) an existing
     * project with that name under $programCode, or a newly-created one.
     * Must run inside the caller's open transaction, since a new project row
     * may be inserted as part of it.
     */
    private function resolveProjectCode(array $project, string $programCode, string $subProgramCode, string $fiscalYr): string
    {
        $projectCode = trim((string) ($project['project_code'] ?? ''));
        if ($projectCode !== '') {
            return $projectCode;
        }

        $projectName = trim((string) ($project['project_name'] ?? ''));
        $result = (new ProjectService())->findOrCreateByName($projectName, $programCode, $subProgramCode, (int) $fiscalYr);
        if (!$result['success']) {
            throw new \Exception($result['message'] ?? "Failed to resolve project '{$projectName}'.");
        }

        return $result['project_code'];
    }

    /**
     * Inserts every Project block (and its line items) for one Program,
     * under the given header/program ids. Shared by addProgram() (a brand
     * new Program) and updateProgram() (a Program's children fully
     * replaced). Must run inside the caller's open transaction.
     */
    private function insertProjectsAndLineItems(string $budgetReferenceNumber, int $budgetReqProgramId, string $programCode, string $subProgramCode, array $projects, string $fiscalYr): void
    {
        $lineItemService = new BudgetLineItemService();

        foreach ($projects as $project) {
            $projectCode = $this->resolveProjectCode($project, $programCode, $subProgramCode, $fiscalYr);
            $location = trim((string) ($project['location'] ?? ''));

            $projectRow = App\ExecuteRow(
                "INSERT INTO {$this->tableBudgetReqProject} (budget_reference_number, budget_req_program_id, project_code, location)
                 VALUES (".App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB').", {$budgetReqProgramId}, ".App\QuotedValue($projectCode, DataType::STRING, 'DB').', '
                    .($location !== '' ? App\QuotedValue($location, DataType::STRING, 'DB') : 'NULL').')
                 RETURNING budget_req_project_id',
                'DB'
            );
            $budgetReqProjectId = $projectRow['budget_req_project_id'];

            foreach ($project['line_items'] as $lineItem) {
                $lineItemService->create(array_merge($lineItem, [
                    'budget_reference_number' => $budgetReferenceNumber,
                    'budget_req_project_id' => $budgetReqProjectId,
                ]));
            }

            $this->persistAttachments($budgetReferenceNumber, $budgetReqProgramId, $projectCode, $project['supporting_documents'] ?? []);
        }
    }

    private function fetchHeaderByFiscalYearAndRc(string $fiscalYr, string $rcId): ?array
    {
        return App\ExecuteRow(
            "SELECT br.budget_reference_number, br.status_id, br.rc_id, st.name AS status_name
             FROM {$this->table} br
             LEFT JOIN fims.settings_status st ON st.id = br.status_id
             WHERE br.fiscal_yr = ".App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB').'
               AND br.rc_id = '.App\QuotedValue($rcId, DataType::STRING, 'DB'),
            'DB'
        ) ?: null;
    }

    /**
     * Attaches the full nested shape — programs[] (each with projects[],
     * each with line_items[]) and is_draft — onto every header row in
     * $headerRows, each of which must have a budget_reference_number. Used
     * by both fetchById() (a single-row set) and fetchAlls() (a page of
     * rows), batching the three child queries with one IN (...) each
     * rather than querying per header.
     */
    private function attachProgramsToHeaders(array $headerRows): array
    {
        if (empty($headerRows)) {
            return [];
        }

        $ids = array_map(fn ($row) => (string) $row['budget_reference_number'], $headerRows);
        $idList = implode(', ', array_map(fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'), $ids));

        $programRows = App\ExecuteRows(
            "SELECT brp.*, pg.program_name, sp.sub_program_name
             FROM {$this->tableBudgetReqProgram} brp
             LEFT JOIN {$this->tableProgram} pg ON pg.program_code = brp.program_code
             LEFT JOIN {$this->tableSubProgram} sp ON sp.sub_program_code = brp.sub_program_code
             WHERE brp.budget_reference_number IN ({$idList})
             ORDER BY brp.budget_req_program_id ASC",
            'DB'
        ) ?: [];

        $projectRows = App\ExecuteRows(
            "SELECT brp2.budget_req_project_id, brp2.budget_req_program_id, brp2.budget_reference_number, brp2.project_code, brp2.location, pj.project_name
             FROM {$this->tableBudgetReqProject} brp2
             LEFT JOIN {$this->tableProject} pj ON pj.project_code = brp2.project_code
             WHERE brp2.budget_reference_number IN ({$idList})
             ORDER BY brp2.budget_req_project_id ASC",
            'DB'
        ) ?: [];

        $lineItemRows = App\ExecuteRows(
            "SELECT li.*, coa.account_code, coa.account_title,
                    tof.name AS type_of_fund_name, ct.name AS component_name
             FROM {$this->tableLineItems} li
             LEFT JOIN {$this->tableChartOfAccounts} coa ON coa.account_id = li.account_id
             LEFT JOIN {$this->tableTypeOfFund} tof ON tof.type_of_fund_id = li.type_of_fund_id
             LEFT JOIN {$this->tableComponentType} ct ON ct.component_type_id = li.component_id
             WHERE li.budget_reference_number IN ({$idList})
             ORDER BY li.budget_req_project_id ASC, li.budget_line_item_id ASC",
            'DB'
        ) ?: [];

        foreach ($headerRows as &$header) {
            $brn = (string) $header['budget_reference_number'];

            $programs = [];
            foreach (array_filter($programRows, fn ($p) => (string) $p['budget_reference_number'] === $brn) as $programRow) {
                $brpId = (int) $programRow['budget_req_program_id'];

                $projects = [];
                foreach (array_filter($projectRows, fn ($p) => (int) $p['budget_req_program_id'] === $brpId) as $projectRow) {
                    $bprjId = (int) $projectRow['budget_req_project_id'];
                    $projectRow['line_items'] = array_values(array_filter(
                        $lineItemRows,
                        fn ($li) => (int) $li['budget_req_project_id'] === $bprjId
                    ));
                    $projects[] = $projectRow;
                }

                $programRow['projects'] = array_values($projects);
                $programs[] = $programRow;
            }

            $header['programs'] = array_values($programs);
            $header['is_draft'] = ($header['status_name'] ?? '') === 'Draft';
        }
        unset($header);

        return $headerRows;
    }

    /**
     * @param string $rcCodeFrom only requests of RCs whose rc_code sorts at or after this value (e.g.
     *                           "35-074-0000001-000" = the Regional and District offices, which come after
     *                           the central departments). Each row also carries its RC (rc_code, rc_name,
     *                           rc_office_type) and that RC's parent (rc_parent_id / _code / _name), which
     *                           the tree view of the budget request list groups by.
     */
    public function fetchAlls(?int $page = null, int $limit = 10, string $fiscalYr = '', string $programCode = '', string $rcId = '', string $rcCodeFrom = ''): array
    {
        try {
            $where = ['1=1'];

            if ($fiscalYr !== '') {
                $where[] = 'br.fiscal_yr = '.(int) $fiscalYr;
            }
            if ($programCode !== '') {
                $where[] = "EXISTS (SELECT 1 FROM {$this->tableBudgetReqProgram} brp
                                     WHERE brp.budget_reference_number = br.budget_reference_number
                                       AND brp.program_code = ".App\QuotedValue($programCode, DataType::STRING, 'DB').')';
            }
            if ($rcId !== '') {
                // A parent RC also sees its descendants' rows (e.g. a Region 3 user
                // sees Region 3's district offices too) — not a flat equality.
                $rcIds = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];
                $quotedRcIds = implode(', ', array_map(
                    fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'),
                    $rcIds
                ));
                $where[] = "br.rc_id IN ({$quotedRcIds})";
            }
            if ($rcCodeFrom !== '') {
                $where[] = 'br.rc_id IN (SELECT rc_id FROM fims.responsibility_code WHERE rc_code >= '
                    .App\QuotedValue($rcCodeFrom, DataType::STRING, 'DB').')';
            }
            $whereSql = implode(' AND ', $where);

            $joinSql = "SELECT br.*, st.name AS status_name, st.color AS status_color,
                               rc.rc_code, rc.rc_name, rc.office_type AS rc_office_type,
                               rc.parent_rc_id AS rc_parent_id, prc.rc_code AS rc_parent_code, prc.rc_name AS rc_parent_name,
                               COALESCE(prg.program_count, 0) AS program_count,
                               COALESCE(proj.project_count, 0) AS project_count,
                               COALESCE(li.li_count, 0) AS li_count,
                               COALESCE(li.li_total, 0) AS li_total
                        FROM {$this->table} br
                        LEFT JOIN fims.settings_status st ON st.id = br.status_id
                        LEFT JOIN fims.responsibility_code rc ON rc.rc_id = br.rc_id
                        LEFT JOIN fims.responsibility_code prc ON prc.rc_id = rc.parent_rc_id
                        LEFT JOIN (
                            SELECT budget_reference_number, COUNT(*) AS program_count
                            FROM {$this->tableBudgetReqProgram}
                            GROUP BY budget_reference_number
                        ) prg ON prg.budget_reference_number = br.budget_reference_number
                        LEFT JOIN (
                            SELECT budget_reference_number, COUNT(*) AS project_count
                            FROM {$this->tableBudgetReqProject}
                            GROUP BY budget_reference_number
                        ) proj ON proj.budget_reference_number = br.budget_reference_number
                        LEFT JOIN (
                            SELECT budget_reference_number, COUNT(*) AS li_count, SUM(proposed_amount) AS li_total
                            FROM {$this->tableLineItems}
                            GROUP BY budget_reference_number
                        ) li ON li.budget_reference_number = br.budget_reference_number
                        WHERE {$whereSql}";

            if ($page === null) {
                $data = App\ExecuteRows($joinSql.' ORDER BY br.fiscal_yr DESC, br.budget_reference_number DESC', 'DB') ?: [];

                return ['success' => true, 'data' => $this->attachProgramsToHeaders($data)];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} br WHERE {$whereSql}", 'DB');
            $data = App\ExecuteRows($joinSql." ORDER BY br.fiscal_yr DESC, br.budget_reference_number DESC LIMIT {$limit} OFFSET {$offset}", 'DB') ?: [];
            $data = $this->attachProgramsToHeaders($data);

            $totalAmount = (float) App\ExecuteScalar(
                "SELECT COALESCE(SUM(li.proposed_amount), 0) FROM {$this->tableLineItems} li
                 JOIN {$this->table} br ON br.budget_reference_number = li.budget_reference_number
                 WHERE {$whereSql}",
                'DB'
            );

            return [
                'success' => true,
                'data' => $data ?: [],
                'total_amount' => $totalAmount,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * SUM(proposed_amount) across every budget line item, every fiscal year,
     * every program/status — an unconditional grand total, not scoped by any
     * of fetchAlls()'s filters. $rcId applies the same RC-scoping convention
     * as fetchAlls() (empty = unrestricted/admin; otherwise that RC plus its
     * descendants), since callers here are the same RC-scoped list callers.
     */
    public function fetchGrandTotalAmount(string $rcId = ''): array
    {
        try {
            $whereSql = '1=1';
            if ($rcId !== '') {
                $rcIds = GlobalService::getRcIdsInScope($rcId) ?: [$rcId];
                $quotedRcIds = implode(', ', array_map(
                    fn ($id) => App\QuotedValue($id, DataType::STRING, 'DB'),
                    $rcIds
                ));
                $whereSql = "br.rc_id IN ({$quotedRcIds})";
            }

            $totalAmount = (float) App\ExecuteScalar(
                "SELECT COALESCE(SUM(li.proposed_amount), 0) FROM {$this->tableLineItems} li
                 JOIN {$this->table} br ON br.budget_reference_number = li.budget_reference_number
                 WHERE {$whereSql}",
                'DB'
            );

            return ['success' => true, 'data' => ['total_amount' => $totalAmount]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchById(string $budgetReferenceNumber): array
    {
        try {
            $id = $budgetReferenceNumber;
            $sql = "SELECT br.*, st.name AS status_name, st.color AS status_color
                    FROM {$this->table} br
                    LEFT JOIN fims.settings_status st ON st.id = br.status_id
                    WHERE br.budget_reference_number = ".App\QuotedValue($id, DataType::STRING, 'DB');

            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Budget request {$id} not found."];
            }

            [$data] = $this->attachProgramsToHeaders([$data]);

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Adds one Program (with its project blocks and chart-of-accounts line
     * items) to the budget request for $header['fiscal_yr'] — creating that
     * request (status Draft) if it doesn't exist yet, or appending to it if
     * it does and is still Draft. Rejected if a request for that fiscal
     * year already exists and has moved past Draft. Either way the request's
     * submitted_at is stamped with NOW() (set on creation, refreshed on append). $projects shape:
     *   [ ['project_code' => ..., 'line_items' => [['account_id'=>.., 'particulars'=>.., 'proposed_amount'=>..], ...]], ... ].
     */
    public function addProgram(array $header, array $projects): array
    {
        $fiscalYr = trim((string) ($header['fiscal_yr'] ?? ''));
        $programCode = trim((string) ($header['program_code'] ?? ''));
        $subProgramCode = trim((string) ($header['sub_program_code'] ?? ''));
        $rcId = trim((string) ($header['rc_id'] ?? ''));

        $err = $this->validateHeaderAndProjects($fiscalYr, $programCode, $subProgramCode, $projects);
        if ($err) {
            return ['success' => false, 'message' => $err];
        }
        if ($rcId === '') {
            // Defense in depth — the route is expected to have already resolved this
            // from the caller's own uac.users.rc_id (GlobalService::getUserRcId()).
            return ['success' => false, 'message' => 'rc_id is required.'];
        }

        try {
            App\Execute('BEGIN', 'DB');

            $existingHeader = $this->fetchHeaderByFiscalYearAndRc($fiscalYr, $rcId);
            $createdNewHeader = false;

            if ($existingHeader) {
                if (($existingHeader['status_name'] ?? '') !== 'Draft') {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => "The budget request for fiscal year {$fiscalYr} is already '{$existingHeader['status_name']}' and can no longer accept new programs."];
                }
                $budgetReferenceNumber = (string) $existingHeader['budget_reference_number'];
                $this->touchSubmittedAt($budgetReferenceNumber);   // appending a Program = a new save of this request
            } else {
                $defaultStatusId = $this->getDefaultStatusId();
                // Must be generated inside this same open transaction, immediately
                // before the INSERT that uses it — see GlobalService::
                // generateBudgetReferenceNumber()'s own "suggestion, nothing
                // reserved" caveat (the PRIMARY KEY is what actually stops two
                // concurrent callers from both succeeding with the same value).
                $budgetReferenceNumber = GlobalService::generateBudgetReferenceNumber();
                $headerRow = App\ExecuteRow(
                    "INSERT INTO {$this->table} (budget_reference_number, fiscal_yr, status_id, rc_id, submitted_at)
                     VALUES (".App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB').', '
                        .App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB').', '
                        .($defaultStatusId !== null ? $defaultStatusId : 'NULL').', '
                        .App\QuotedValue($rcId, DataType::STRING, 'DB').', NOW())
                     RETURNING budget_reference_number',
                    'DB'
                );
                $budgetReferenceNumber = (string) $headerRow['budget_reference_number'];
                $createdNewHeader = true;
            }

            $programRow = App\ExecuteRow(
                "INSERT INTO {$this->tableBudgetReqProgram} (budget_reference_number, program_code, sub_program_code)
                 VALUES (".App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB').', '
                    .App\QuotedValue($programCode, DataType::STRING, 'DB').', '
                    .($subProgramCode === '' ? 'NULL' : App\QuotedValue($subProgramCode, DataType::STRING, 'DB')).')
                 RETURNING budget_req_program_id',
                'DB'
            );
            $budgetReqProgramId = (int) $programRow['budget_req_program_id'];

            $this->insertProjectsAndLineItems($budgetReferenceNumber, $budgetReqProgramId, $programCode, $subProgramCode, $projects, $fiscalYr);

            App\Execute('COMMIT', 'DB');

            return [
                'success' => true,
                'message' => $createdNewHeader
                    ? 'Budget request created successfully.'
                    : 'Program added to the existing budget request.',
                'budget_reference_number' => $budgetReferenceNumber,
                'budget_req_program_id' => $budgetReqProgramId,
                'created_new_header' => $createdNewHeader,
            ];
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Fully replaces one Program's project blocks + line items with the
     * submitted set (simplest-correct approach — project/line-item ids do
     * not stay stable across an edit). Sibling Programs under the same
     * header are untouched. Rejected once the header has left Draft.
     * The request's submitted_at is refreshed (NOW()) — this is a new save of it.
     */
    public function updateProgram(int|string $budgetReqProgramId, array $header, array $projects): array
    {
        $id = (int) $budgetReqProgramId;

        $programRow = App\ExecuteRow(
            "SELECT brp.budget_req_program_id, brp.budget_reference_number, br.fiscal_yr, st.name AS status_name
             FROM {$this->tableBudgetReqProgram} brp
             JOIN {$this->table} br ON br.budget_reference_number = brp.budget_reference_number
             LEFT JOIN fims.settings_status st ON st.id = br.status_id
             WHERE brp.budget_req_program_id = {$id}",
            'DB'
        );
        if (!$programRow) {
            return ['success' => false, 'message' => "Program #{$id} not found."];
        }
        if (($programRow['status_name'] ?? '') !== 'Draft') {
            return ['success' => false, 'message' => "This budget request is '{$programRow['status_name']}' and its programs can no longer be edited."];
        }

        $programCode = trim((string) ($header['program_code'] ?? ''));
        $subProgramCode = trim((string) ($header['sub_program_code'] ?? ''));

        $err = $this->validateProgramBlock($programCode, $subProgramCode, $projects);
        if ($err) {
            return ['success' => false, 'message' => $err];
        }

        try {
            App\Execute('BEGIN', 'DB');

            App\Execute(
                "UPDATE {$this->tableBudgetReqProgram} SET
                    program_code = ".App\QuotedValue($programCode, DataType::STRING, 'DB').',
                    sub_program_code = '.($subProgramCode === '' ? 'NULL' : App\QuotedValue($subProgramCode, DataType::STRING, 'DB'))."
                 WHERE budget_req_program_id = {$id}",
                'DB'
            );

            // Full replace of this program's own project/line-item tree only —
            // ON DELETE CASCADE on budget_req_project.budget_req_program_id
            // takes its budget_line_items with it; sibling programs under the
            // same header are untouched.
            App\Execute("DELETE FROM {$this->tableBudgetReqProject} WHERE budget_req_program_id = {$id}", 'DB');

            $this->insertProjectsAndLineItems((string) $programRow['budget_reference_number'], $id, $programCode, $subProgramCode, $projects, (string) $programRow['fiscal_yr']);
            $this->touchSubmittedAt((string) $programRow['budget_reference_number']);

            App\Execute('COMMIT', 'DB');

            return ['success' => true, 'message' => 'Program updated successfully.'];
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Transitions a Draft budget request to "For Review". Locks it from further Program additions/edits. */
    public function submitForReview(string $budgetReferenceNumber): array
    {
        $id = $budgetReferenceNumber;

        $check = $this->fetchById($id);
        if (!$check['success']) {
            return $check;
        }
        if (($check['data']['status_name'] ?? '') !== 'Draft') {
            return ['success' => false, 'message' => "Budget request {$id} is not in Draft status."];
        }
        if (empty($check['data']['programs'])) {
            return ['success' => false, 'message' => 'Add at least one program before submitting for review.'];
        }

        $forReviewId = $this->getStatusIdByName('For Review');
        if ($forReviewId === null) {
            return ['success' => false, 'message' => "'For Review' status is not configured."];
        }

        // submitted_at = the "Date Submitted" shown on the FMD's Budget Requirements list (alter_table/16).
        App\Execute("UPDATE {$this->table} SET status_id = {$forReviewId}, submitted_at = NOW() WHERE budget_reference_number = ".App\QuotedValue($id, DataType::STRING, 'DB'), 'DB');

        return ['success' => true, 'message' => 'Budget request submitted for review.'];
    }

    /**
     * Budget Processing "Endorse": saves each line item's Type of Fund and
     * Component (both required for every item) plus its optional Suggested
     * Amount (blank = 0), Program Source and Program Uses, and moves the request to
     * "Endorsed", all in one transaction — if any item is invalid, nothing is
     * saved and the status is unchanged. Rejected for a Cancelled,
     * already-Endorsed or Approved request. The status lives on the request header, so it
     * applies to the whole request, not just the Program the items came from.
     *
     * @param array $items [['budget_line_item_id' => .., 'type_of_fund_id' => .., 'component_id' => .., 'suggested_amount' => .., 'program_source_code' => .., 'program_uses' => ..], ...]
     *                     (the last three are optional — see BudgetLineItemService::applyProcessing())
     */
    public function endorse(string $budgetReferenceNumber, array $items): array
    {
        $id = $budgetReferenceNumber;

        $check = $this->fetchById($id);
        if (!$check['success']) {
            return $check;
        }

        $current = $check['data']['status_name'] ?? '';
        if (in_array($current, ['Cancelled', 'Endorsed', 'Approved'], true)) {
            return ['success' => false, 'message' => "Budget request {$id} is already '{$current}' and cannot be endorsed."];
        }

        $endorsedId = $this->getStatusIdByName('Endorsed');
        if ($endorsedId === null) {
            return ['success' => false, 'message' => "'Endorsed' status is not configured."];
        }

        try {
            App\Execute('BEGIN', 'DB');

            $error = (new BudgetLineItemService())->applyProcessing($items, $id, true);
            if ($error !== null) {
                App\Execute('ROLLBACK', 'DB');

                return ['success' => false, 'message' => $error];
            }

            App\Execute("UPDATE {$this->table} SET status_id = {$endorsedId} WHERE budget_reference_number = ".App\QuotedValue($id, DataType::STRING, 'DB'), 'DB');

            App\Execute('COMMIT', 'DB');

            return ['success' => true, 'message' => 'Budget request endorsed successfully.'];
        } catch (\Exception $e) {
            App\Execute('ROLLBACK', 'DB');

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Budget Processing / Budget Preview "Return": moves the request to
     * "Returned" without touching its line items, and saves the reason in
     * budget_req.remarks. The remarks are REQUIRED (blank / whitespace-only is
     * rejected, so is anything over REMARKS_MAX_LENGTH characters). Rejected for a
     * Cancelled, already-Returned or Approved request. Like endorse(), it
     * applies to the whole request.
     */
    public function returnRequest(string $budgetReferenceNumber): array
    {
        $id = $budgetReferenceNumber;

        $remarks = is_string($remarks) ? trim($remarks) : '';
        if ($remarks === '') {
            return ['success' => false, 'message' => 'Remarks are required to return a budget request.'];
        }
        if (mb_strlen($remarks) > self::REMARKS_MAX_LENGTH) {
            return ['success' => false, 'message' => 'Remarks must be at most '.self::REMARKS_MAX_LENGTH.' characters.'];
        }

        $check = $this->fetchById($id);
        if (!$check['success']) {
            return $check;
        }

        $current = $check['data']['status_name'] ?? '';
        if (in_array($current, ['Cancelled', 'Returned', 'Approved'], true)) {
            return ['success' => false, 'message' => "Budget request {$id} is already '{$current}' and cannot be returned."];
        }

        $returnedId = $this->getStatusIdByName('Returned');
        if ($returnedId === null) {
            return ['success' => false, 'message' => "'Returned' status is not configured."];
        }

        App\Execute(
            "UPDATE {$this->table} SET status_id = {$returnedId}, remarks = ".App\QuotedValue($remarks, DataType::STRING, 'DB')
            ." WHERE budget_reference_number = {$id}",
            'DB'
        );

        return ['success' => true, 'message' => 'Budget request returned.'];
    }

    /**
     * Budget Preview "Approve": moves an Endorsed request to "Approved". Only
     * an Endorsed request can be approved — it has to have been through Budget
     * Processing first. Applies to the whole request.
     */
    public function approve(string $budgetReferenceNumber): array
    {
        $id = $budgetReferenceNumber;

        $check = $this->fetchById($id);
        if (!$check['success']) {
            return $check;
        }

        $current = $check['data']['status_name'] ?? '';
        if ($current !== 'Endorsed') {
            return ['success' => false, 'message' => "Only an Endorsed budget request can be approved (budget request {$id} is '".($current ?: 'no status')."')."];
        }

        $approvedId = $this->getStatusIdByName('Approved');
        if ($approvedId === null) {
            return ['success' => false, 'message' => "'Approved' status is not configured."];
        }

        App\Execute("UPDATE {$this->table} SET status_id = {$approvedId} WHERE budget_reference_number = ".App\QuotedValue($id, DataType::STRING, 'DB'), 'DB');

        return ['success' => true, 'message' => 'Budget request approved successfully.'];
    }

    /** Deletes the header — fims.budget_req_program (and via it, fims.budget_req_project/fims.budget_line_items) cascades via ON DELETE CASCADE. */
    public function delete(string $budgetReferenceNumber): array
    {
        try {
            $check = $this->fetchById($budgetReferenceNumber);
            if (!$check['success']) {
                return $check;
            }
            if (($check['data']['status_name'] ?? '') !== 'Draft') {
                return ['success' => false, 'message' => 'Only Draft budget requests can be deleted.'];
            }

            $sql = "DELETE FROM {$this->table} WHERE budget_reference_number = ".App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Budget request {$budgetReferenceNumber} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /* ── Per-project supporting-document attachments ─────────────────────
       Uploaded to object storage (DO Spaces, via the $s3client/GetSignedUrl()
       globals _shared/storage.php already wires into every app/ page/route)
       or pasted as an external link. Keyed off (budget_req_program_id,
       project_code) rather than budget_req_project_id — updateProgram()
       above deletes and reinserts every project row under a program on each
       save, so an attachment FK'd to that surrogate would be cascaded away
       on the very next unrelated edit. budget_req_program_id (the program
       row itself) and project_code (a real, stable entity) both survive
       that churn. */

    /**
     * Resolves every project's supporting_documents[*].file_index against a
     * single flat $uploadedFiles list ("doc_file_0", "doc_file_1", ... — one
     * running index across the WHOLE request, not per project), uploading
     * each new file to object storage and replacing file_index with the
     * resulting storage_key in place. Entries already carrying a link or an
     * existing attachment_id pass through untouched. Returns the (possibly
     * mutated) $projects array, or throws on a validation/upload failure —
     * the caller must not proceed to create/update on a thrown error.
     *
     * @param array $projects      the request body's own "projects" array
     * @param array $uploadedFiles $request->getUploadedFiles()
     */
    public function uploadSupportingDocFiles(array $projects, array $uploadedFiles, string $budgetReferenceNumber): array
    {
        foreach ($projects as &$project) {
            $docs = $project['supporting_documents'] ?? [];
            if (!is_array($docs) || empty($docs)) {
                continue;
            }

            $projectCode = trim((string) ($project['project_code'] ?? ''));
            $keyPrefix   = self::ATTACHMENT_KEY_PREFIX . $budgetReferenceNumber . ($projectCode !== '' ? '/' . $projectCode : '');

            foreach ($docs as &$doc) {
                if (!isset($doc['file_index'])) {
                    continue;
                }

                $fileKey = 'doc_file_'.$doc['file_index'];
                unset($doc['file_index']);
                $file = $uploadedFiles[$fileKey] ?? null;
                if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                    continue;
                }

                $doc['storage_key'] = GlobalService::uploadFileToStorage(
                    $file,
                    $keyPrefix,
                    self::ATTACHMENT_ALLOWED_EXT,
                    self::ATTACHMENT_MAX_BYTES
                );
                if (empty($doc['document_name'])) {
                    $doc['document_name'] = pathinfo($file->getClientFilename(), PATHINFO_FILENAME);
                }
            }
            unset($doc);

            $project['supporting_documents'] = $docs;
        }
        unset($project);

        return $projects;
    }

    /**
     * Inserts one row per already-resolved doc (storage_key from an upload,
     * or an external_link pasted by the user). Must run inside the caller's
     * open transaction — called from insertProjectsAndLineItems() right
     * after a project's own row is inserted, where $budgetReqProgramId and
     * the resolved $projectCode are both already known.
     *
     * @param array $docs [{document_name, storage_key?, link?}, ...]
     */
    private function persistAttachments(string $budgetReferenceNumber, int $budgetReqProgramId, string $projectCode, array $docs): void
    {
        if (empty($docs)) {
            return;
        }

        $userId = (int) App\CurrentUserID();

        foreach ($docs as $doc) {
            // Already-saved attachments carried along unchanged from an edit
            // prefill (identified by attachment_id) need no new INSERT.
            if (!empty($doc['attachment_id'])) {
                continue;
            }

            $storageKey = trim((string) ($doc['storage_key'] ?? ''));
            $link = trim((string) ($doc['link'] ?? ''));
            $name = trim((string) ($doc['document_name'] ?? ''));
            if ($storageKey === '' && $link === '') {
                continue;
            }
            if ($name === '') {
                $name = $storageKey !== '' ? pathinfo($storageKey, PATHINFO_FILENAME) : $link;
            }

            App\Execute(
                "INSERT INTO {$this->tableAttachment} (budget_reference_number, budget_req_program_id, project_code, document_name, storage_key, external_link, uploaded_by)
                 VALUES (".App\QuotedValue($budgetReferenceNumber, DataType::STRING, 'DB').', '
                    .$budgetReqProgramId.', '
                    .App\QuotedValue($projectCode, DataType::STRING, 'DB').', '
                    .App\QuotedValue($name, DataType::STRING, 'DB').', '
                    .($storageKey !== '' ? App\QuotedValue($storageKey, DataType::STRING, 'DB') : 'NULL').', '
                    .($link !== '' ? App\QuotedValue($link, DataType::STRING, 'DB') : 'NULL').', '
                    .$userId.')',
                'DB'
            );
        }
    }

    /** Lists a project's attachments, each with a presigned view_url for an upload (or the raw external_link). */
    public function fetchAttachmentsForProject(int $budgetReqProgramId, string $projectCode): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT attachment_id, document_name, storage_key, external_link, uploaded_at
                 FROM {$this->tableAttachment}
                 WHERE budget_req_program_id = {$budgetReqProgramId}
                   AND project_code = ".App\QuotedValue($projectCode, DataType::STRING, 'DB').'
                 ORDER BY uploaded_at ASC',
                'DB'
            ) ?: [];

            foreach ($rows as &$row) {
                $row['view_url'] = $row['storage_key'] ? \GetSignedUrl($row['storage_key']) : $row['external_link'];
            }
            unset($row);

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Deletes one attachment — rejected unless its parent request is still Draft. */
    public function deleteAttachment(int $attachmentId): array
    {
        global $s3client;

        try {
            $row = App\ExecuteRow(
                "SELECT a.storage_key, st.name AS status_name
                 FROM {$this->tableAttachment} a
                 JOIN {$this->table} br ON br.budget_reference_number = a.budget_reference_number
                 LEFT JOIN fims.settings_status st ON st.id = br.status_id
                 WHERE a.attachment_id = {$attachmentId}",
                'DB'
            );
            if (!$row) {
                return ['success' => false, 'message' => "Attachment #{$attachmentId} not found."];
            }
            if (($row['status_name'] ?? '') !== 'Draft') {
                return ['success' => false, 'message' => 'Only a Draft budget request\'s attachments can be removed.'];
            }

            App\Execute("DELETE FROM {$this->tableAttachment} WHERE attachment_id = {$attachmentId}", 'DB');

            if (!empty($row['storage_key'])) {
                try {
                    $s3client->deleteObject(['Bucket' => \env('DO_SPACES_BUCKET', ''), 'Key' => $row['storage_key']]);
                } catch (\Throwable $e) {
                    // Best-effort — the DB row is already gone; a stray S3 object isn't worth failing the request over.
                }
            }

            return ['success' => true, 'message' => 'Attachment deleted successfully.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
