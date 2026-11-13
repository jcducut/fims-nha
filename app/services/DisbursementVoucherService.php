<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Disbursement Vouchers (fims.disbursement_vouchers).
 *
 * One generic voucher shape for any claimant/payment type - the paper General
 * Form No. 1 is itself a single layout used for vendor payments, payroll,
 * reimbursements, etc.; what differs is just what gets written in
 * Particulars/Amount, not the schema. A claimant is optionally a registered
 * vendor (vendor_id set, fields snapshotted from fims.vendors) or free text.
 *
 * net_amount = gross_amount - total deductions - total withholding tax, service-computed
 * (not a generated column — see "03 - alter_table/17 - disbursement_vouchers_drop_tax_fields_add_sl.sql").
 * Deductions (fims.disbursement_voucher_deductions, e.g. contractor retention) are entered by
 * Originating at create()/update() time, same stage as gross_amount itself. Withholding tax
 * (fims.disbursement_voucher_atc_lines, one row per BIR ATC classification — a DV can withhold
 * under more than one) is added later by Accounting, alongside the Journal Entry Voucher, in
 * saveJournalEntry(). Both recompute net_amount/balance_amount when they change.
 *
 * "RFA" has no dedicated table - fims.budget_allocation rows where
 * allocation_type = 'RFA' stand in for it. That table's own CRUD backend
 * doesn't exist yet (the CBA/RFA form is frontend-only), so this service only
 * adds a narrow, read-only lookup over it (fetchRfaOptions / fetchRfaDetail) -
 * no write path, no FK.
 *
 * Status is a plain column moved by changeStatus(), modeling the real four-office
 * review chain (Originating -> FMD -> Accounting -> Treasury) as a straight-line
 * pipeline, same state-machine shape as PurchaseOrderService otherwise:
 *   DRAFT            -> FOR_FMD_REVIEW | CANCELLED                 (Originating submits)
 *   FOR_FMD_REVIEW    -> FOR_ACCOUNTING | REJECTED | CANCELLED      (FMD checks the obligation, approves)
 *   FOR_ACCOUNTING    -> FOR_TREASURY | REJECTED | CANCELLED        (Accounting processes the DV)
 *   FOR_TREASURY      -> RELEASED | CANCELLED                       (Treasury releases payment)
 *   REJECTED          -> CANCELLED           (or edited + re-saved via update())
 *   RELEASED / CANCELLED are terminal.
 *
 * Accounting's stage requires preparing a Journal Entry Voucher (saveJournalEntry() -
 * fims.journal_entry_vouchers/_lines, a balanced single combined entry: Dr the DV's
 * object-of-expenditure account_code, Cr Due to BIR for any withholding tax, Cr
 * Accounts Payable for the net) - checked by changeStatus() before FOR_ACCOUNTING can
 * advance to FOR_TREASURY. ATC withholding lines are optional (0 or more) and saved in
 * the same call. Treasury's actual cash entry (AP -> Cash) isn't modeled; this project
 * treats the JEV as the single combined obligation+payment entry.
 *
 * changeStatus() enforces who may move a voucher into each stage (see
 * STAGE_HOLDER_RC / authorizeTransition()): the caller's uac.users.rc_id (Responsibility
 * Center — fims.responsibility_code, the org-chart column this project already
 * uses for fims.budget_req.rc_id) must match the office that owns the target
 * stage, unless they're a superadmin (uac.users.user_level_id = '-1' bypasses
 * everything — GlobalService::getUserRcScope()['is_admin'], matching the rest of
 * this project's convention; NOT App\IsSysAdmin(), which only tracks PHPMaker's
 * own hardcoded admin-login path and is never true for a DB-authenticated user).
 * Submitting to FMD (DRAFT -> FOR_FMD_REVIEW) is open to anyone, same as creating
 * the voucher in the first place — there's no "Originating office" concept to
 * gate against.
 */
class DisbursementVoucherService
{
    private string $table         = 'fims.disbursement_vouchers';
    private string $tableVendor   = 'fims.vendors';
    private string $tableRfa      = 'fims.budget_allocation';
    private string $tableUsers    = 'uac.users';
    private string $tableFiscal   = 'fims.fiscal_year';
    private string $tableCoa      = 'fims.chart_of_accounts';
    private string $tableAtc      = 'fims.atc_codes';
    private string $tableJev      = 'fims.journal_entry_vouchers';
    private string $tableJevLines = 'fims.journal_entry_voucher_lines';
    private string $tableRc              = 'fims.responsibility_code';
    private string $tableDeductionTypes  = 'fims.deduction_types';
    private string $tableDvDeductions    = 'fims.disbursement_voucher_deductions';
    private string $tableDvAtcLines      = 'fims.disbursement_voucher_atc_lines';
    private string $tableSettingsStatus  = 'fims.settings_status';
    private string $tableSlMasters       = 'fims.sl_masters';
    private string $tableSlDetails       = 'fims.sl_details';
    private string $tableBudgetCategory  = 'fims.budget_category';

    // Classification tag only (reporting/filtering) — same set fims.purchase_orders.expense_class
    // uses; it never changes which fields the voucher form shows.
    private const EXPENSE_CLASSES = ['MOOE', 'PS', 'CAPITAL_OUTLAY', 'OTHER'];

    private const EDITABLE      = ['DRAFT', 'REJECTED'];
    private const DELETABLE     = ['DRAFT', 'CANCELLED'];
    private const SAVE_STATUSES = ['DRAFT', 'FOR_FMD_REVIEW'];
    private const TRANSITIONS   = [
        'DRAFT'           => ['FOR_FMD_REVIEW', 'CANCELLED'],
        'FOR_FMD_REVIEW'  => ['FOR_ACCOUNTING', 'REJECTED', 'CANCELLED'],
        'FOR_ACCOUNTING'  => ['FOR_TREASURY', 'REJECTED', 'CANCELLED'],
        'FOR_TREASURY'    => ['RELEASED', 'CANCELLED'],
        'REJECTED'        => ['CANCELLED'],
        'RELEASED'        => [],
        'CANCELLED'       => [],
    ];

    // Responsibility Center (fims.responsibility_code.rc_id) that HOLDS the voucher while it sits
    // in this status — the only office allowed to move it forward, reject it, or (together with
    // the creator) cancel it from here. Keyed by the status itself, not by where it's headed: e.g.
    // STAGE_HOLDER_RC['FOR_FMD_REVIEW'] is FMD because FMD is who's holding it for review right
    // now, which is also, not coincidentally, who must approve moving it on to FOR_ACCOUNTING.
    // DRAFT has no entry (Originating holds it, open to anyone) — see authorizeTransition().
    private const STAGE_HOLDER_RC = [
        'FOR_FMD_REVIEW' => '013', // Financial Management Department (FMD)
        'FOR_ACCOUNTING' => '012', // Accounting Department
        'FOR_TREASURY'   => '014', // Treasury Department
    ];
    private const STAGE_RC_LABEL = [
        '013' => 'Financial Management Department (FMD)',
        '012' => 'Accounting Department',
        '014' => 'Treasury Department',
    ];

    // fims.settings_status.status_for for this voucher's pipeline status — see
    // "03 - alter_table/21 - disbursement_vouchers_status_to_settings_status.sql" and its seed rows
    // in "02 - sample_data/01 - fims_settings_status.sql". Same lookup-table convention
    // BudgetReqService uses for fims.budget_req.status_id; the ALL_CAPS_UNDERSCORE names above
    // (EDITABLE/DELETABLE/SAVE_STATUSES/TRANSITIONS/STAGE_HOLDER_RC) are unchanged — only where a
    // voucher's current status is stored moved off a free-text column onto this shared table.
    private const STATUS_FOR = 'disbursement_voucher';

    /**
     * Looked up by name each time rather than hardcoding its id — the id a seed row lands on
     * depends on insert order, which isn't guaranteed to be stable across environments or
     * re-seeds. Same convention as BudgetReqService::getStatusIdByName().
     */
    private function getStatusIdByName(string $name): ?int
    {
        $row = App\ExecuteRow(
            "SELECT id FROM {$this->tableSettingsStatus} WHERE status_for = " . $this->str(self::STATUS_FOR) . ' AND name = ' . $this->str($name),
            'DB'
        );

        return isset($row['id']) ? (int) $row['id'] : null;
    }

    private function getDefaultStatusId(): ?int
    {
        return $this->getStatusIdByName('DRAFT');
    }

    // ── small SQL helpers (same shape as PurchaseOrderService) ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function numOrNull(mixed $v): string
    {
        return is_numeric($v) ? (string) (float) $v : 'NULL';
    }

    private function intOrNull(mixed $v): string
    {
        return ($v !== null && $v !== '' && ctype_digit((string) $v)) ? (string) (int) $v : 'NULL';
    }

    private function validDate(mixed $v): bool
    {
        if (!is_string($v)) {
            return false;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $v);

        return $d !== false && $d->format('Y-m-d') === $v;
    }

    // ── validation ──

    /**
     * Validates the form payload shared by create() and update().
     * Returns [errors[], fields[]].
     */
    private function prepare(array $body): array
    {
        $errors = [];
        $fields = [];

        // DV date
        $dvDate = trim((string) ($body['dv_date'] ?? ''));
        if ($dvDate === '') {
            $errors[] = 'DV Date (dv_date) is required.';
        } elseif (!$this->validDate($dvDate)) {
            $errors[] = 'DV Date must be a valid date (YYYY-MM-DD).';
        }
        $fields['dv_date'] = $dvDate;

        // Fiscal year
        $fy = $body['fiscal_yr'] ?? '';
        if ($fy === '' || $fy === null || !ctype_digit((string) $fy)) {
            $errors[] = 'Fiscal Year (fiscal_yr) is required.';
        } elseif ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = " . (int) $fy, 'DB') === 0) {
            $errors[] = "Fiscal year {$fy} does not exist.";
        }
        $fields['fiscal_yr'] = (int) $fy;

        // Expense class (optional) — classification tag only, never branches validation/fields
        $expenseClass = strtoupper(trim((string) ($body['expense_class'] ?? '')));
        if ($expenseClass !== '' && !in_array($expenseClass, self::EXPENSE_CLASSES, true)) {
            $errors[] = 'expense_class must be one of: ' . implode(', ', self::EXPENSE_CLASSES);
        }
        $fields['expense_class'] = $expenseClass;

        // RFA — soft reference into fims.budget_allocation (allocation_type = 'RFA'); at least one is
        // required, same as the paper General Form No. 1 always citing the allotment(s) it's drawn
        // against. The link lives entirely on the RFA SIDE (budget_allocation.dv_id — see
        // "03 - alter_table/23 - budget_allocation_add_dv_id.sql") — this table carries no RFA
        // column of its own at all (the old single rfa_allocation_id/rfa_allocation_number snapshot
        // was dropped in "03 - alter_table/26 - ...-drop_rfa_allocation_columns.sql" once nothing
        // read it anymore). The actual linking (budget_allocation.dv_id set/cleared for every
        // selected/deselected RFA) happens in create()/update() after this voucher's own row is
        // written — see linkRfas().
        $rfaIdsRaw = $body['rfa_allocation_ids'] ?? ($body['rfa_allocation_id'] ?? []);
        if (!is_array($rfaIdsRaw)) {
            $rfaIdsRaw = [$rfaIdsRaw];
        }
        $rfaIdsRaw = array_values(array_unique(array_filter(
            array_map('strval', $rfaIdsRaw),
            fn ($v) => trim($v) !== ''
        )));

        $rfas = [];
        if (!$rfaIdsRaw) {
            $errors[] = 'At least one RFA is required.';
        } else {
            foreach ($rfaIdsRaw as $rfaId) {
                if (!ctype_digit($rfaId)) {
                    $errors[] = "rfa_allocation_ids: '{$rfaId}' must be numeric.";
                    continue;
                }
                $rfa = App\ExecuteRow(
                    "SELECT allocation_id, allocation_number, requesting_rc_id, dv_id
                     FROM {$this->tableRfa} WHERE allocation_id = " . (int) $rfaId . " AND allocation_type = 'RFA'",
                    'DB'
                );
                if (!$rfa) {
                    $errors[] = "RFA #{$rfaId} does not exist.";
                    continue;
                }
                $rfas[] = $rfa;
            }
        }
        $fields['requesting_department_id']  = null; // fims.budget_allocation now keys its requesting office by requesting_rc_id (a Responsibility Center), which doesn't fit this INTEGER column — see "14 - budget_allocation_requesting_department_id_to_requesting_rc_id.sql"
        $fields['_rfaRows'] = $rfas; // internal only — never persisted as a DV column, see linkRfas()

        $fields['dept_project'] = trim((string) ($body['dept_project'] ?? ''));
        $fields['fund_source']  = trim((string) ($body['fund_source'] ?? ''));

        // Account code is required, must exist in the chart of accounts, and must be
        // posting_allowed (same check saveJournalEntry() applies to each JEV line's account_code)
        // — so the Journal Entry's auto-filled Dr line (see readone.php's vje_syncAutoLines) is
        // never blank by the time a DV reaches Accounting.
        $acctCode = trim((string) ($body['account_code'] ?? ''));
        if ($acctCode === '') {
            $errors[] = 'Account Code is required.';
        } else {
            $account = App\ExecuteRow(
                'SELECT posting_allowed FROM ' . $this->tableCoa . ' WHERE account_code = ' . $this->str($acctCode),
                'DB'
            );
            if (!$account) {
                $errors[] = "Account code '{$acctCode}' does not exist in the chart of accounts.";
            } elseif (!$account['posting_allowed']) {
                $errors[] = "Account code '{$acctCode}' is not postable (it's a summary/header account).";
            }
        }
        $fields['account_code'] = $acctCode;

        $fields['remarks'] = trim((string) ($body['remarks'] ?? ''));

        // Ledger figures — budget_amount/obligation_amount can be client-supplied (there is no
        // authoritative allotment-release system yet); payments_prior_amount and balance_amount
        // are always server-computed in create()/update() (see priorPaymentsForRfa()), never
        // trusted from the client.
        $fields['budget_amount']     = is_numeric($body['budget_amount'] ?? null) ? (float) $body['budget_amount'] : 0.0;
        $fields['obligation_amount'] = is_numeric($body['obligation_amount'] ?? null) ? (float) $body['obligation_amount'] : $fields['budget_amount'];

        // Claimant — an optional registered vendor (snapshot wins over manually-typed fields so
        // the claimant record always matches the vendor on file), or free text otherwise.
        $vendor   = null;
        $vendorId = $body['vendor_id'] ?? '';
        if ($vendorId !== '' && $vendorId !== null) {
            if (!ctype_digit((string) $vendorId)) {
                $errors[] = 'vendor_id must be numeric.';
            } else {
                $vendor = App\ExecuteRow(
                    "SELECT vendor_id, vendor_name, address, tin, COALESCE(status, 'ACTIVE') AS status FROM {$this->tableVendor} WHERE vendor_id = " . (int) $vendorId,
                    'DB'
                );
                if (!$vendor) {
                    $errors[] = "Vendor with ID {$vendorId} does not exist.";
                } elseif ($vendor['status'] !== 'ACTIVE') {
                    $errors[] = "Vendor '{$vendor['vendor_name']}' is not active.";
                }
            }
        }
        $fields['vendor_id']         = $vendor ? (int) $vendor['vendor_id'] : null;
        $fields['claimant_name']     = $vendor['vendor_name'] ?? trim((string) ($body['claimant_name'] ?? ''));
        $fields['claimant_address']  = $vendor['address'] ?? trim((string) ($body['claimant_address'] ?? ''));
        $fields['employee_no_tin']   = $vendor['tin'] ?? trim((string) ($body['employee_no_tin'] ?? ''));

        if ($fields['claimant_name'] === '') {
            $errors[] = 'Claimant Name (or a selected Vendor) is required.';
        }

        $particulars = trim((string) ($body['particulars'] ?? ''));
        if ($particulars === '') {
            $errors[] = 'Particulars is required.';
        }
        $fields['particulars'] = $particulars;

        $gross = $body['gross_amount'] ?? null;
        if (!is_numeric($gross) || (float) $gross <= 0) {
            $errors[] = 'Amount must be greater than zero.';
        }
        $gross = is_numeric($gross) ? (float) $gross : 0.0;

        $fields['gross_amount'] = $gross;

        // Deductions (optional) — non-tax amounts withheld from gross (e.g. contractor
        // retention), entered by Originating alongside the rest of this form. Withholding tax
        // is separate — added later by Accounting via saveJournalEntry(), not here.
        [$deductionErrors, $deductionLines] = $this->prepareDeductions($body['deductions'] ?? [], $gross);
        $errors            = array_merge($errors, $deductionErrors);
        $fields['deductions'] = $deductionLines;
        $totalDeductions   = array_sum(array_column($deductionLines, 'deduction_amount'));
        $fields['net_amount'] = round($gross - $totalDeductions, 2);

        $fields['po_number'] = trim((string) ($body['po_number'] ?? ''));
        $fields['po_amount'] = is_numeric($body['po_amount'] ?? null) ? (float) $body['po_amount'] : null;

        // ATC Code and Accounting Entries (both optional) — normally Accounting's job during
        // FOR_ACCOUNTING via saveJournalEntry(), but Originating can pre-fill either here too; full
        // validation happens in persistJournalEntry(), not here (same "parse now, validate at the
        // point of use" split as `deductions` above).
        $fields['atc_lines'] = is_array($body['atc_lines'] ?? null) ? $body['atc_lines'] : [];
        $fields['lines']     = is_array($body['lines'] ?? null) ? $body['lines'] : [];

        // Supporting Documents (optional) — a batch already uploaded client-side via
        // POST /upload/multi-upload (same fims.file_attachments convention as
        // fims.budget_allocation; see BudgetAllocationService's identical check).
        $nodeId = trim((string) ($body['multiple_node_id'] ?? ''));
        if ($nodeId !== '' && !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $nodeId)) {
            $errors[] = 'multiple_node_id must be a UUID.';
            $nodeId   = '';
        }
        $fields['multiple_node_id'] = $nodeId !== '' ? strtolower($nodeId) : null;

        // Status the user is saving as — stored as fims.settings_status.id (status_for =
        // 'disbursement_voucher'), looked up by name; see getStatusIdByName()'s doc comment.
        $status = strtoupper(trim((string) ($body['status'] ?? 'DRAFT')));
        if (!in_array($status, self::SAVE_STATUSES, true)) {
            $errors[] = 'status must be DRAFT or FOR_FMD_REVIEW when saving; use the status action to move it through FMD/Accounting/Treasury, reject, or cancel.';
        }
        $fields['status']    = $status;
        $fields['status_id'] = $this->getStatusIdByName($status);
        if ($fields['status_id'] === null && !$errors) {
            $errors[] = "Status '{$status}' is not configured in fims.settings_status for disbursement vouchers.";
        }

        return [$errors, $fields];
    }

    /**
     * Validates the `deductions` array from create()/update()'s body. Each row needs a known,
     * active deduction_type_id and a gross_amount; deduction_amount is either typed directly or
     * computed from rate (rate NULL means "typed directly", same optionality the paper form's
     * blank Rate column allows). Returns [errors[], rows[]] — rows are ready to insert, in order.
     */
    private function prepareDeductions(mixed $rows, float $dvGrossAmount): array
    {
        $errors = [];
        $out    = [];
        if (!is_array($rows)) {
            return [$errors, $out];
        }

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $n = $i + 1;

            $typeId = $row['deduction_type_id'] ?? '';
            if (!ctype_digit((string) $typeId)) {
                $errors[] = "Deduction line {$n}: deduction_type_id is required.";
                continue;
            }
            $type = App\ExecuteRow(
                "SELECT deduction_type_id FROM {$this->tableDeductionTypes} WHERE deduction_type_id = " . (int) $typeId . ' AND is_active = TRUE',
                'DB'
            );
            if (!$type) {
                $errors[] = "Deduction line {$n}: deduction type #{$typeId} does not exist or is inactive.";
                continue;
            }

            $gross = is_numeric($row['gross_amount'] ?? null) ? (float) $row['gross_amount'] : $dvGrossAmount;
            if ($gross < 0) {
                $errors[] = "Deduction line {$n}: gross_amount cannot be negative.";
            }

            $rateRaw = $row['rate'] ?? null;
            $rate    = is_numeric($rateRaw) ? (float) $rateRaw : null;
            if ($rate !== null) {
                $amount = round($gross * $rate / 100, 2);
            } else {
                $amount = is_numeric($row['deduction_amount'] ?? null) ? round((float) $row['deduction_amount'], 2) : null;
                if ($amount === null) {
                    $errors[] = "Deduction line {$n}: either rate or deduction_amount is required.";
                    continue;
                }
            }
            if ($amount < 0) {
                $errors[] = "Deduction line {$n}: deduction_amount cannot be negative.";
            }

            $out[] = [
                'deduction_type_id' => (int) $typeId,
                'rate'              => $rate,
                'gross_amount'      => $gross,
                'deduction_amount'  => $amount,
            ];
        }

        return [$errors, $out];
    }

    /** Full replace of a DV's deduction lines — same upsert shape as the JEV lines. */
    private function replaceDeductions(int $dvId, array $deductions): void
    {
        App\Execute("DELETE FROM {$this->tableDvDeductions} WHERE dv_id = {$dvId}", 'DB');
        foreach ($deductions as $idx => $d) {
            App\Execute(
                "INSERT INTO {$this->tableDvDeductions} (dv_id, line_number, deduction_type_id, rate, gross_amount, deduction_amount)
                 VALUES ({$dvId}, " . ($idx + 1) . ', ' . (int) $d['deduction_type_id'] . ', '
                . $this->numOrNull($d['rate']) . ', ' . (float) $d['gross_amount'] . ', ' . (float) $d['deduction_amount'] . ')',
                'DB'
            );
        }
    }

    // ── Deduction types (read-only lookup) ──

    public function fetchDeductionTypes(): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT deduction_type_id, code, name, description, account_code FROM {$this->tableDeductionTypes} WHERE is_active = TRUE ORDER BY name",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchDeductionTypes ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load deduction types.'];
        }
    }

    // ── Subsidiary Ledger lookup (read-only; fims.sl_masters/sl_details have no CRUD backend of their own) ──

    public function fetchSlCodes(string $search = ''): array
    {
        try {
            $where = ['is_active = TRUE'];
            if ($search !== '') {
                $kw      = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(sl_code ILIKE {$kw} OR sl_name ILIKE {$kw})";
            }
            $rows = App\ExecuteRows(
                'SELECT sl_code, sl_name, account_code FROM ' . $this->tableSlMasters . ' WHERE ' . implode(' AND ', $where) . ' ORDER BY sl_code LIMIT 50',
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchSlCodes ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load SL codes.'];
        }
    }

    public function fetchSlDetails(string $slCode): array
    {
        try {
            $rows = App\ExecuteRows(
                'SELECT sl_detail_id, detail_code, detail_name FROM ' . $this->tableSlDetails
                . ' WHERE sl_code = ' . $this->str($slCode) . ' AND is_active = TRUE ORDER BY detail_code',
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchSlDetails ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load SL details.'];
        }
    }

    // ── RFA lookup (read-only; fims.budget_allocation has no CRUD backend of its own) ──

    /**
     * net_amount of the OTHER voucher currently linked to this RFA (fims.budget_allocation.dv_id —
     * see linkRfas()), if any and still active (not CANCELLED/REJECTED) — "Payments" on the paper
     * form's Box B. Since one RFA only ever points at one DV at a time, this is at most a single
     * voucher's own net_amount, not a sum. $excludeDvId leaves the voucher being edited out of its
     * own total (it's always the one currently linked to its own selected RFAs). Null $rfaId (no
     * RFA linked) or no RFA at all means 0 — there's nothing to accumulate against. This table has
     * no RFA column of its own to query by — see "03 - alter_table/26 -
     * ...-drop_rfa_allocation_columns.sql".
     */
    private function priorPaymentsForRfa(?int $rfaId, ?int $excludeDvId): float
    {
        if (!$rfaId) {
            return 0.0;
        }
        $linkedDvId = (int) (App\ExecuteScalar("SELECT dv_id FROM {$this->tableRfa} WHERE allocation_id = {$rfaId}", 'DB') ?? 0);
        if (!$linkedDvId || $linkedDvId === $excludeDvId) {
            return 0.0;
        }
        $row = App\ExecuteRow(
            "SELECT dv.net_amount, st.name AS status FROM {$this->table} dv
             LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
             WHERE dv.dv_id = {$linkedDvId}",
            'DB'
        );
        if (!$row || in_array($row['status'], ['CANCELLED', 'REJECTED'], true)) {
            return 0.0;
        }

        return (float) $row['net_amount'];
    }

    /** Same as priorPaymentsForRfa(), summed across every RFA a DV cites. */
    private function priorPaymentsForRfas(array $rfaIds, ?int $excludeDvId): float
    {
        $total = 0.0;
        foreach ($rfaIds as $rfaId) {
            $total += $this->priorPaymentsForRfa((int) $rfaId, $excludeDvId);
        }

        return round($total, 2);
    }

    /**
     * Points every RFA in $rfaRows (prepare()'s `_rfaRows`) at $dvId — fims.budget_allocation.dv_id
     * — and clears dv_id back to NULL on any RFA that USED to point at $dvId but isn't part of this
     * selection anymore (an unchecked chip in the form). One RFA only ever points at one DV at a
     * time (see "03 - alter_table/23 - budget_allocation_add_dv_id.sql"), so this also refuses to
     * steal an RFA that's currently linked to a DIFFERENT, still-active (non-CANCELLED/REJECTED) DV
     * — returns that error message in that case, null otherwise. Called from inside create()/
     * update()'s own transaction, after this voucher's own row has been written (so $dvId is known) —
     * a non-null return should trigger a ROLLBACK, same as a persistJournalEntry() failure.
     */
    private function linkRfas(int $dvId, array $rfaRows): ?string
    {
        $newIds = array_map(fn ($r) => (int) $r['allocation_id'], $rfaRows);

        foreach ($rfaRows as $rfa) {
            $otherDvId = $rfa['dv_id'] !== null ? (int) $rfa['dv_id'] : null;
            if ($otherDvId === null || $otherDvId === $dvId) {
                continue;
            }
            $other = App\ExecuteRow(
                "SELECT dv.dv_no, st.name AS status FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$otherDvId}",
                'DB'
            );
            if ($other && !in_array($other['status'], ['CANCELLED', 'REJECTED'], true)) {
                return "RFA '{$rfa['allocation_number']}' is already linked to Disbursement Voucher {$other['dv_no']} and cannot be selected here.";
            }
        }

        $keep = $newIds ? implode(',', $newIds) : '0';
        App\Execute("UPDATE {$this->tableRfa} SET dv_id = NULL WHERE dv_id = {$dvId} AND allocation_id NOT IN ({$keep})", 'DB');
        if ($newIds) {
            App\Execute("UPDATE {$this->tableRfa} SET dv_id = {$dvId} WHERE allocation_id IN ({$keep})", 'DB');
        }

        return null;
    }

    /**
     * Stamps budget_amount (ceiling, from po_amount or summed line_transaction) plus paid_amount /
     * remaining_balance onto an RFA row. paid_amount is usually 0 now that one RFA only ever points
     * at one DV at a time (budget_allocation.dv_id — see linkRfas()); it stays non-zero only for a
     * historical RFA whose prior DV was CANCELLED/REJECTED (priorPaymentsForRfa() excludes those
     * from "active", so they don't block re-linking, but their own net_amount still counted here
     * while they held it).
     */
    private function withRfaBudget(array $rfa, ?int $excludeDvId = null): array
    {
        $budgetAmount = is_numeric($rfa['po_amount']) && (float) $rfa['po_amount'] > 0 ? (float) $rfa['po_amount'] : 0.0;
        if ($budgetAmount <= 0) {
            $lines = json_decode((string) ($rfa['line_transaction'] ?? '[]'), true) ?: [];
            foreach ($lines as $line) {
                // RFA allocations carry their amount in requested_amount — allocated_amount
                // ("Cash Requirements") is hidden/unused for this allocation_type (see
                // budget-allocation-form.php's baaApplyCashRequirementsVisibility()), so summing
                // allocated_amount here always landed on 0.
                $budgetAmount += (float) ($line['requested_amount'] ?? $line['allocated_amount'] ?? 0);
            }
        }
        $rfa['budget_amount']      = round($budgetAmount, 2);
        $rfa['paid_amount']        = round($this->priorPaymentsForRfa((int) $rfa['allocation_id'], $excludeDvId), 2);
        $rfa['remaining_balance']  = round($rfa['budget_amount'] - $rfa['paid_amount'], 2);

        return $rfa;
    }

    /**
     * WHERE fragment: this RFA is free to pick — unlinked, already linked to the DV currently being
     * edited, or linked to a DV that's since been CANCELLED/REJECTED (same "not blocking" rule
     * linkRfas()'s own exclusivity check uses, so an RFA doesn't get stuck unavailable forever just
     * because nothing else explicitly clears budget_allocation.dv_id when a DV is cancelled).
     */
    private function rfaAvailableSql(?int $excludeDvId): string
    {
        $exclude = $excludeDvId !== null ? " OR a.dv_id = {$excludeDvId}" : '';

        return "(a.dv_id IS NULL{$exclude} OR a.dv_id IN (
            SELECT dv.dv_id FROM {$this->table} dv
            LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
            WHERE st.name IN ('CANCELLED', 'REJECTED')
        ))";
    }

    /**
     * RFA picker options. One RFA backs only one DV at a time (budget_allocation.dv_id — see
     * linkRfas()), so an RFA already linked to a DIFFERENT, still-active DV is left out entirely;
     * $excludeDvId is the DV currently being edited, so its own already-linked RFAs keep showing
     * (as "already yours", not "taken by someone else").
     */
    public function fetchRfaOptions(array $filters = [], ?int $excludeDvId = null): array
    {
        try {
            $where = ["a.allocation_type = 'RFA'", $this->rfaAvailableSql($excludeDvId)];
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'a.fiscal_year = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(a.allocation_number ILIKE {$kw} OR a.request_referrence_no ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $rows = App\ExecuteRows(
                "SELECT a.allocation_id, a.allocation_number, a.request_referrence_no, a.fiscal_year,
                        a.requesting_rc_id, rc.rc_name AS department_name,
                        a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                        a.po_number, a.po_amount, a.line_transaction, a.dv_id,
                        a.category_id, bc.name AS category_name
                 FROM {$this->tableRfa} a
                 LEFT JOIN {$this->tableRc}             rc ON rc.rc_id = a.requesting_rc_id
                 LEFT JOIN {$this->tableVendor}         v  ON v.vendor_id = a.vendor_id
                 LEFT JOIN {$this->tableBudgetCategory} bc ON bc.budget_category_id = a.category_id
                 WHERE {$whereSql}
                 ORDER BY a.allocation_id DESC",
                'DB'
            ) ?: [];

            $rows = array_map(fn (array $r) => $this->withRfaBudget($r, $excludeDvId), $rows);

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchRfaOptions ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load RFAs.'];
        }
    }

    /** One RFA plus its budget ceiling and paid_amount/remaining_balance so far (progress payments). */
    public function fetchRfaDetail(int|string $allocationId, ?int $excludeDvId = null): array
    {
        try {
            $id  = (int) $allocationId;
            $rfa = App\ExecuteRow(
                "SELECT a.allocation_id, a.allocation_number, a.request_referrence_no, a.fiscal_year,
                        a.requesting_rc_id, rc.rc_name AS department_name,
                        a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                        a.po_number, a.po_amount, a.line_transaction,
                        a.category_id, bc.name AS category_name
                 FROM {$this->tableRfa} a
                 LEFT JOIN {$this->tableRc}             rc ON rc.rc_id = a.requesting_rc_id
                 LEFT JOIN {$this->tableVendor}         v  ON v.vendor_id = a.vendor_id
                 LEFT JOIN {$this->tableBudgetCategory} bc ON bc.budget_category_id = a.category_id
                 WHERE a.allocation_id = {$id} AND a.allocation_type = 'RFA'",
                'DB'
            );
            if (!$rfa) {
                return ['success' => false, 'message' => "RFA #{$id} not found.", 'httpCode' => 404];
            }

            return ['success' => true, 'data' => $this->withRfaBudget($rfa, $excludeDvId)];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchRfaDetail ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load RFA.'];
        }
    }

    // ── queries ──

    public function fetchAlls(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $from = "{$this->table} dv LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id";

            $where = ['1=1'];
            if (!empty($filters['status'])) {
                $where[] = 'st.name = ' . $this->str(strtoupper($filters['status']));
            }
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'dv.fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['expense_class'])) {
                $where[] = 'dv.expense_class = ' . $this->str(strtoupper($filters['expense_class']));
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                // RFA no longer has a column on this table (see linkRfas()) — matched via the
                // reverse link (budget_allocation.dv_id) instead.
                $where[] = "(dv.dv_no ILIKE {$kw} OR dv.claimant_name ILIKE {$kw}
                             OR EXISTS (SELECT 1 FROM {$this->tableRfa} a WHERE a.dv_id = dv.dv_id AND a.allocation_number ILIKE {$kw}))";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT dv.*, st.name AS status, st.color AS status_color FROM {$from} WHERE {$whereSql} ORDER BY dv.created_at DESC, dv.dv_id DESC";

            if ($page === null) {
                return ['success' => true, 'data' => App\ExecuteRows($select, 'DB') ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$from} WHERE {$whereSql}", 'DB');

            $summary = App\ExecuteRow(
                "SELECT COALESCE(SUM(dv.net_amount), 0) AS total_amount,
                        COUNT(*) FILTER (WHERE st.name = 'RELEASED')                              AS approved,
                        COUNT(*) FILTER (WHERE st.name IN ('FOR_FMD_REVIEW','FOR_ACCOUNTING','FOR_TREASURY')) AS pending,
                        COUNT(*) FILTER (WHERE st.name IN ('REJECTED','CANCELLED'))                AS rejected
                 FROM {$from} WHERE {$whereSql}",
                'DB'
            );

            return [
                'success'    => true,
                'data'       => App\ExecuteRows($select . " LIMIT {$limit} OFFSET {$offset}", 'DB') ?: [],
                'summary'    => $summary,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load disbursement vouchers.'];
        }
    }

    public function fetchFiscalYears(): array
    {
        try {
            $rows = App\ExecuteRows("SELECT fiscal_yr FROM {$this->tableFiscal} ORDER BY fiscal_yr DESC", 'DB') ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchFiscalYears ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load fiscal years.'];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $dvId = (int) $id;
            $row  = App\ExecuteRow(
                "SELECT dv.*, st.name AS status, st.color AS status_color
                 FROM {$this->table} dv LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$row) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }

            $row['allowed_transitions'] = self::TRANSITIONS[$row['status']] ?? [];
            $row['is_editable']        = in_array($row['status'], self::EDITABLE, true);
            $row['jev']                = $this->fetchJev($dvId);
            $row['deductions']         = $this->fetchDeductions($dvId);
            $row['atc_lines']          = $this->fetchAtcLines($dvId);

            // Every RFA currently linked to this DV — the link lives on the RFA side
            // (budget_allocation.dv_id, see linkRfas()), so this is a plain reverse lookup, not a
            // column to parse. What the form's multi-select repopulates its chips from, and what the
            // view shows.
            $row['rfas'] = array_values(array_filter(array_map(
                fn (array $r) => ($this->withRfaBudget($r, $dvId)),
                App\ExecuteRows(
                    "SELECT a.allocation_id, a.allocation_number, a.request_referrence_no, a.fiscal_year,
                            a.requesting_rc_id, rc.rc_name AS department_name,
                            a.vendor_id, v.vendor_name, v.address AS vendor_address, v.tin AS vendor_tin,
                            a.po_number, a.po_amount, a.line_transaction, a.dv_id,
                            a.category_id, bc.name AS category_name
                     FROM {$this->tableRfa} a
                     LEFT JOIN {$this->tableRc}             rc ON rc.rc_id = a.requesting_rc_id
                     LEFT JOIN {$this->tableVendor}         v  ON v.vendor_id = a.vendor_id
                     LEFT JOIN {$this->tableBudgetCategory} bc ON bc.budget_category_id = a.category_id
                     WHERE a.allocation_type = 'RFA' AND a.dv_id = {$dvId}
                     ORDER BY a.allocation_id",
                    'DB'
                ) ?: []
            )));

            // Supporting Documents attached to this voucher, if any (same attachment convention as
            // fims.budget_allocation — see FileUploadServices::attachmentsFor()).
            $row['file_attachments'] = (new FileUploadServices())->attachmentsFor($row['multiple_node_id'] ?? null);

            // account_title/account_posting_allowed let the view show the account's name (same
            // Chart of Accounts lookup the searchable-select pickers use elsewhere) and flag it if
            // it's somehow not posting_allowed (e.g. a legacy voucher saved before that check
            // existed, or the account was reclassified after the fact) — null/false if account_code
            // is blank or no longer exists in the chart of accounts.
            if ($row['account_code']) {
                $account = App\ExecuteRow(
                    'SELECT account_title, posting_allowed FROM ' . $this->tableCoa . ' WHERE account_code = ' . $this->str($row['account_code']),
                    'DB'
                );
                $row['account_title']           = $account['account_title'] ?? null;
                $row['account_posting_allowed'] = $account ? (bool) $account['posting_allowed'] : false;
            } else {
                $row['account_title']           = null;
                $row['account_posting_allowed'] = null;
            }

            return ['success' => true, 'data' => $row];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load disbursement voucher.'];
        }
    }

    /**
     * This DV's deduction lines (e.g. retention), set by Originating at creation. account_code is
     * the deduction type's default posting account (fims.deduction_types.account_code), if any —
     * what the Accounting Entries grid auto-fills a credit line from.
     */
    private function fetchDeductions(int $dvId): array
    {
        return App\ExecuteRows(
            "SELECT dvd.dv_deduction_id, dvd.line_number, dvd.deduction_type_id, dt.code, dt.name, dt.account_code,
                    dvd.rate, dvd.gross_amount, dvd.deduction_amount
             FROM {$this->tableDvDeductions} dvd
             JOIN {$this->tableDeductionTypes} dt ON dt.deduction_type_id = dvd.deduction_type_id
             WHERE dvd.dv_id = {$dvId} ORDER BY dvd.line_number",
            'DB'
        ) ?: [];
    }

    /**
     * This DV's BIR withholding tax classification lines, set by Accounting with the JEV.
     * account_code is the ATC's default posting account (fims.atc_codes.account_code), if any —
     * what the Accounting Entries grid auto-fills a credit line from.
     */
    private function fetchAtcLines(int $dvId): array
    {
        return App\ExecuteRows(
            "SELECT dval.dv_atc_line_id, dval.line_number, dval.atc_code, ac.account_code,
                    dval.rate, dval.tax_base, dval.withholding_tax, dval.chargeability
             FROM {$this->tableDvAtcLines} dval
             JOIN {$this->tableAtc} ac ON ac.atc_code = dval.atc_code
             WHERE dval.dv_id = {$dvId} ORDER BY dval.line_number",
            'DB'
        ) ?: [];
    }

    // ── Journal Entry Voucher (Accounting stage) ──

    /** The JEV for a DV (header + lines, with any SL breakdown), or null if not prepared yet. */
    private function fetchJev(int $dvId): ?array
    {
        $jev = App\ExecuteRow("SELECT * FROM {$this->tableJev} WHERE dv_id = {$dvId}", 'DB');
        if (!$jev) {
            return null;
        }

        $jev['lines'] = App\ExecuteRows(
            "SELECT jevl.jevl_id, jevl.line_number, jevl.account_code, ac.account_title, jevl.debit, jevl.credit, jevl.description,
                    jevl.sl_code, sm.sl_name, jevl.sl_detail_id, sd.detail_name AS sl_detail_name,
                    jevl.rc_id, rc.rc_name
             FROM {$this->tableJevLines} jevl
             LEFT JOIN {$this->tableCoa}       ac ON ac.account_code = jevl.account_code
             LEFT JOIN {$this->tableSlMasters} sm ON sm.sl_code = jevl.sl_code
             LEFT JOIN {$this->tableSlDetails} sd ON sd.sl_detail_id = jevl.sl_detail_id
             LEFT JOIN {$this->tableRc}        rc ON rc.rc_id = jevl.rc_id
             WHERE jevl.jev_id = " . (int) $jev['jev_id'] . ' ORDER BY jevl.line_number',
            'DB'
        ) ?: [];

        return $jev;
    }

    /**
     * JEV register — every Journal Entry Voucher prepared so far, joined with its DV, for the
     * standalone Journal Entry Voucher page (read-only; a JEV is only ever created/edited from
     * within its DV's Accounting stage, see saveJournalEntry()).
     */
    public function fetchJevList(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['1=1'];
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'j.fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(j.jev_no ILIKE {$kw} OR dv.dv_no ILIKE {$kw} OR dv.claimant_name ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT j.jev_id, j.jev_no, j.jev_date, j.fiscal_yr, j.total_debit, j.total_credit, j.created_at,
                              dv.dv_id, dv.dv_no, dv.claimant_name, st.name AS dv_status
                       FROM {$this->tableJev} j
                       JOIN {$this->table} dv ON dv.dv_id = j.dv_id
                       LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                       WHERE {$whereSql}
                       ORDER BY j.created_at DESC, j.jev_id DESC";

            if ($page === null) {
                return ['success' => true, 'data' => App\ExecuteRows($select, 'DB') ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableJev} j JOIN {$this->table} dv ON dv.dv_id = j.dv_id WHERE {$whereSql}", 'DB');

            return [
                'success'    => true,
                'data'       => App\ExecuteRows($select . " LIMIT {$limit} OFFSET {$offset}", 'DB') ?: [],
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchJevList ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load journal entry vouchers.'];
        }
    }

    /** Active ATC (BIR Alphanumeric Tax Code) options for the Accounting stage's dropdown. */
    public function fetchAtcCodes(): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT atc_code, description, tax_rate, account_code FROM {$this->tableAtc} WHERE is_active = TRUE ORDER BY atc_code",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchAtcCodes ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load ATC codes.'];
        }
    }

    /** Next free "JEV-{YY}-{MM}-{NNNN}" for the month of $jevDate. */
    private function nextJevNumber(string $jevDate): string
    {
        $prefix = 'JEV-' . substr($jevDate, 2, 2) . '-' . substr($jevDate, 5, 2) . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(jev_no FROM '[0-9]+$') AS INTEGER)), 0)
             FROM {$this->tableJev}
             WHERE jev_no LIKE " . App\QuotedValue($prefix . '%', App\DataType::STRING, 'DB') . " AND jev_no ~ '-[0-9]+$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validates the `atc_lines` array from saveJournalEntry()'s body — withholding tax is optional
     * (0 or more classifications; a DV can withhold under more than one ATC at once).
     * withholding_tax is always server-computed from tax_base/rate, never trusted from the client,
     * same convention as deduction_amount. Returns [errors[], rows[], totalWithholding].
     */
    private function prepareAtcLines(mixed $rows): array
    {
        $errors = [];
        $out    = [];
        $total  = 0.0;
        if (!is_array($rows)) {
            return [$errors, $out, $total];
        }

        foreach ($rows as $i => $row) {
            if (!is_array($row)) {
                continue;
            }
            $n          = $i + 1;
            $atcCode    = trim((string) ($row['atc_code'] ?? ''));
            $rate       = is_numeric($row['rate'] ?? null) ? (float) $row['rate'] : 0.0;
            $taxBase    = is_numeric($row['tax_base'] ?? null) ? (float) $row['tax_base'] : 0.0;
            $chargeability = trim((string) ($row['chargeability'] ?? ''));
            if ($atcCode === '' && $taxBase <= 0) {
                continue; // a fully blank trailing row
            }
            if ($atcCode === '') {
                $errors[] = "ATC line {$n}: ATC code is required.";
            } elseif ((int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableAtc . ' WHERE atc_code = ' . $this->str($atcCode) . ' AND is_active = TRUE', 'DB') === 0) {
                $errors[] = "ATC line {$n}: ATC code '{$atcCode}' does not exist or is inactive.";
            }
            if ($taxBase <= 0) {
                $errors[] = "ATC line {$n}: tax base must be greater than zero.";
            }
            if ($chargeability !== '' && (int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableRc . ' WHERE rc_id = ' . $this->str($chargeability), 'DB') === 0) {
                $errors[] = "ATC line {$n}: chargeability '{$chargeability}' is not a known Responsibility Center.";
            }

            $withholdingTax = round($taxBase * $rate / 100, 2);
            $total += $withholdingTax;
            $out[] = [
                'atc_code'        => $atcCode,
                'rate'            => $rate,
                'tax_base'        => $taxBase,
                'withholding_tax' => $withholdingTax,
                'chargeability'   => $chargeability !== '' ? $chargeability : null,
            ];
        }

        return [$errors, $out, round($total, 2)];
    }

    /**
     * Accounting prepares (or edits) the Journal Entry Voucher — and its ATC withholding
     * classification lines — for a DV while it's at the FOR_ACCOUNTING stage; a prerequisite
     * changeStatus() checks before allowing FOR_ACCOUNTING -> FOR_TREASURY (see there). Owns its
     * own transaction (persistJournalEntry() doesn't) since this is a standalone call, not nested
     * inside create()/update()'s.
     */
    public function saveJournalEntry(int|string $dvId, array $atcLines, array $lines, int $userId): array
    {
        try {
            $dvId   = (int) $dvId;
            $status = App\ExecuteScalar(
                "SELECT st.name FROM {$this->table} dv LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if ($status === false || $status === null) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if ($status !== 'FOR_ACCOUNTING') {
                return ['success' => false, 'message' => "A Journal Entry can only be prepared while the voucher is at the Accounting stage (current status: {$status})."];
            }
            if (!GlobalService::getUserRcScope()['is_admin'] && $this->currentUserRcId() !== self::STAGE_HOLDER_RC['FOR_ACCOUNTING']) {
                return ['success' => false, 'message' => 'Only Accounting Department staff can prepare this Journal Entry.', 'httpCode' => 403];
            }

            App\Execute('BEGIN', 'DB');
            try {
                $result = $this->persistJournalEntry($dvId, $atcLines, $lines, $userId, true);
                if (!$result['success']) {
                    App\Execute('ROLLBACK', 'DB');

                    return $result;
                }
                App\Execute('COMMIT', 'DB');

                return $result;
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::saveJournalEntry ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to save the Journal Entry.'];
        }
    }

    /**
     * Core validate+save for a DV's ATC withholding + Journal Entry lines. No status/RC check and
     * no transaction management here — the caller owns both:
     *   - saveJournalEntry() (Accounting, FOR_ACCOUNTING stage only) checks status+RC, then wraps
     *     this in its own BEGIN/COMMIT.
     *   - create()/update() (Originating, optional pre-fill at any editable stage — "ATC Code" and
     *     "Accounting Entries" are optional sections on the DV form) call this from inside their
     *     own already-open transaction, so a validation failure here rolls back the whole DV save.
     *     $requireBalancedEntry is false there: 0 lines/0 ATC rows is a valid "not filled in yet"
     *     state, not an error — Accounting still has to prepare a real one later regardless.
     * Full replace of both atc_lines/lines, same upsert shape as PurchaseOrderService's items.
     * Recomputes the DV's net_amount/balance_amount either way.
     */
    private function persistJournalEntry(int $dvId, array $atcLines, array $lines, int $userId, bool $requireBalancedEntry): array
    {
        $dv = App\ExecuteRow(
            "SELECT dv_id, fiscal_yr, gross_amount, obligation_amount, payments_prior_amount
             FROM {$this->table} WHERE dv_id = {$dvId}",
            'DB'
        );
        if (!$dv) {
            return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
        }

        $errors = [];

        [$atcErrors, $cleanAtc, $totalWithholding] = $this->prepareAtcLines($atcLines);
        $errors = array_merge($errors, $atcErrors);

        $clean = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        foreach (array_values($lines) as $i => $line) {
            $n           = $i + 1;
            $accountCode = trim((string) ($line['account_code'] ?? ''));
            $debit       = is_numeric($line['debit'] ?? null) ? (float) $line['debit'] : 0.0;
            $credit      = is_numeric($line['credit'] ?? null) ? (float) $line['credit'] : 0.0;
            $slCode      = trim((string) ($line['sl_code'] ?? ''));
            $slDetailId  = $line['sl_detail_id'] ?? '';
            $rcId        = trim((string) ($line['rc_id'] ?? ''));
            if ($accountCode === '' && $debit <= 0 && $credit <= 0) {
                continue; // a fully blank trailing row
            }
            if ($accountCode === '') {
                $errors[] = "Line {$n}: account code is required.";
            } else {
                $account = App\ExecuteRow(
                    "SELECT posting_allowed FROM {$this->tableCoa} WHERE account_code = " . $this->str($accountCode),
                    'DB'
                );
                if (!$account) {
                    $errors[] = "Line {$n}: account code '{$accountCode}' does not exist in the chart of accounts.";
                } elseif (!$account['posting_allowed']) {
                    $errors[] = "Line {$n}: account code '{$accountCode}' is not postable (it's a summary/header account).";
                }
            }
            if (($debit > 0) === ($credit > 0)) {
                $errors[] = "Line {$n}: enter either a debit or a credit amount, not both or neither.";
            }
            if ($slCode !== '' && (int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableSlMasters . ' WHERE sl_code = ' . $this->str($slCode) . ' AND is_active = TRUE', 'DB') === 0) {
                $errors[] = "Line {$n}: SL code '{$slCode}' does not exist or is inactive.";
            }
            if (ctype_digit((string) $slDetailId) && (int) $slDetailId > 0) {
                $detail = App\ExecuteRow('SELECT sl_code FROM ' . $this->tableSlDetails . ' WHERE sl_detail_id = ' . (int) $slDetailId, 'DB');
                if (!$detail) {
                    $errors[] = "Line {$n}: SL detail #{$slDetailId} does not exist.";
                } elseif ($slCode !== '' && $detail['sl_code'] !== $slCode) {
                    $errors[] = "Line {$n}: SL detail #{$slDetailId} does not belong to SL code '{$slCode}'.";
                }
                $slDetailId = (int) $slDetailId;
            } else {
                $slDetailId = null;
            }
            if ($rcId !== '' && (int) App\ExecuteScalar('SELECT COUNT(*) FROM ' . $this->tableRc . ' WHERE rc_id = ' . $this->str($rcId), 'DB') === 0) {
                $errors[] = "Line {$n}: Resp Center '{$rcId}' is not a known Responsibility Center.";
            }
            $totalDebit  += $debit;
            $totalCredit += $credit;
            $clean[] = [
                'account_code' => $accountCode, 'debit' => $debit, 'credit' => $credit,
                'description'  => trim((string) ($line['description'] ?? '')),
                'sl_code'      => $slCode !== '' ? $slCode : null,
                'sl_detail_id' => $slDetailId,
                'rc_id'        => $rcId !== '' ? $rcId : null,
            ];
        }
        // 0 lines is only an error when a complete entry is required (the dedicated Accounting
        // save); as an optional pre-fill, "nothing entered yet" isn't a validation failure, just
        // nothing to save. 1 line is always wrong either way — a JEV can't have only one side.
        if (count($clean) === 1 || ($requireBalancedEntry && count($clean) === 0)) {
            $errors[] = 'A Journal Entry needs at least two lines (one debit, one credit).';
        }
        $totalDebit  = round($totalDebit, 2);
        $totalCredit = round($totalCredit, 2);
        if (count($clean) >= 2 && !$errors && ($totalDebit <= 0 || $totalDebit !== $totalCredit)) {
            $errors[] = "Total debits (" . number_format($totalDebit, 2) . ") must equal total credits (" . number_format($totalCredit, 2) . ') and be greater than zero.';
        }
        if ($errors) {
            return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
        }

        $totalDeductions = (float) (App\ExecuteScalar("SELECT COALESCE(SUM(deduction_amount), 0) FROM {$this->tableDvDeductions} WHERE dv_id = {$dvId}", 'DB') ?? 0);
        $netAmount       = round((float) $dv['gross_amount'] - $totalDeductions - $totalWithholding, 2);
        $balance         = round((float) $dv['obligation_amount'] - ((float) $dv['payments_prior_amount'] + $netAmount), 2);

        $uid = $userId > 0 ? (string) $userId : 'NULL';

        if (count($clean) > 0) {
            $existing = App\ExecuteRow("SELECT jev_id FROM {$this->tableJev} WHERE dv_id = {$dvId}", 'DB');
            if ($existing) {
                $jevId = (int) $existing['jev_id'];
                App\Execute(
                    "UPDATE {$this->tableJev} SET
                        total_debit = {$totalDebit}, total_credit = {$totalCredit},
                        updated_by = {$uid}, updated_at = CURRENT_TIMESTAMP
                     WHERE jev_id = {$jevId}",
                    'DB'
                );
                App\Execute("DELETE FROM {$this->tableJevLines} WHERE jev_id = {$jevId}", 'DB');
            } else {
                $jevNo = $this->nextJevNumber(date('Y-m-d'));
                $row = App\ExecuteRow(
                    "INSERT INTO {$this->tableJev} (jev_no, dv_id, fiscal_yr, total_debit, total_credit, created_by)
                     VALUES (" . $this->str($jevNo) . ", {$dvId}, " . (int) $dv['fiscal_yr'] . ", {$totalDebit}, {$totalCredit}, {$uid})
                     RETURNING jev_id",
                    'DB'
                );
                $jevId = (int) $row['jev_id'];
            }

            foreach ($clean as $idx => $line) {
                App\Execute(
                    "INSERT INTO {$this->tableJevLines} (jev_id, line_number, account_code, debit, credit, description, sl_code, sl_detail_id, rc_id)
                     VALUES ({$jevId}, " . ($idx + 1) . ', ' . $this->str($line['account_code']) . ', '
                    . (float) $line['debit'] . ', ' . (float) $line['credit'] . ', ' . $this->strOrNull($line['description']) . ', '
                    . $this->strOrNull($line['sl_code']) . ', ' . $this->intOrNull($line['sl_detail_id']) . ', '
                    . $this->strOrNull($line['rc_id']) . ')',
                    'DB'
                );
            }
        } else {
            // Explicit "no lines" submission — clears a previously-saved JEV too (full-replace
            // semantics, same as everything else on this form), cascades to its lines.
            App\Execute("DELETE FROM {$this->tableJev} WHERE dv_id = {$dvId}", 'DB');
        }

        App\Execute("DELETE FROM {$this->tableDvAtcLines} WHERE dv_id = {$dvId}", 'DB');
        foreach ($cleanAtc as $idx => $atc) {
            App\Execute(
                "INSERT INTO {$this->tableDvAtcLines} (dv_id, line_number, atc_code, rate, tax_base, withholding_tax, chargeability)
                 VALUES ({$dvId}, " . ($idx + 1) . ', ' . $this->str($atc['atc_code']) . ', '
                . (float) $atc['rate'] . ', ' . (float) $atc['tax_base'] . ', ' . (float) $atc['withholding_tax'] . ', '
                . $this->strOrNull($atc['chargeability']) . ')',
                'DB'
            );
        }

        App\Execute(
            "UPDATE {$this->table} SET
                net_amount = {$netAmount}, balance_amount = {$balance},
                updated_by = {$uid}, updated_at = CURRENT_TIMESTAMP
             WHERE dv_id = {$dvId}",
            'DB'
        );

        return ['success' => true, 'message' => 'Journal Entry saved.', 'data' => ['dv_id' => $dvId, 'net_amount' => $netAmount, 'balance_amount' => $balance]];
    }

    /** Next free "{YY}-{MM}-{NNNN}" for the month of $dvDate (uses MAX, so deletes can't cause a clash). */
    public function fetchDvNumber(string $dvDate): array
    {
        try {
            if (!$this->validDate($dvDate)) {
                return ['success' => false, 'message' => 'dv_date must be a valid date (YYYY-MM-DD).'];
            }

            return ['success' => true, 'data' => ['dv_no' => $this->nextDvNumber($dvDate)]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::fetchDvNumber ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to generate DV number.'];
        }
    }

    private function nextDvNumber(string $dvDate): string
    {
        $prefix = substr($dvDate, 2, 2) . '-' . substr($dvDate, 5, 2) . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(dv_no FROM '[0-9]+$') AS INTEGER)), 0)
             FROM {$this->table}
             WHERE dv_no LIKE " . App\QuotedValue($prefix . '%', App\DataType::STRING, 'DB') . " AND dv_no ~ '-[0-9]+$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    // ── writes ──

    public function create(array $body, int $userId): array
    {
        try {
            [$errors, $f] = $this->prepare($body);

            $dvNo = trim((string) ($body['dv_no'] ?? ''));
            if ($dvNo !== '' && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE dv_no = " . $this->str($dvNo), 'DB') > 0) {
                $errors[] = "DV Number '{$dvNo}' already exists.";
            }
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }
            if ($dvNo === '') {
                $dvNo = $this->nextDvNumber($f['dv_date']);
            }

            $paymentsPrior = $this->priorPaymentsForRfas(array_column($f['_rfaRows'], 'allocation_id'), null);
            $balance       = round($f['obligation_amount'] - ($paymentsPrior + $f['net_amount']), 2);

            $submitted = $f['status'] === 'FOR_FMD_REVIEW';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                $row = App\ExecuteRow(
                    "INSERT INTO {$this->table}
                        (dv_no, dv_date, fiscal_yr, expense_class, status_id,
                         requesting_department_id, dept_project, fund_source,
                         vendor_id, claimant_name, claimant_address, employee_no_tin,
                         particulars, gross_amount, net_amount,
                         po_number, po_amount,
                         account_code, budget_amount, obligation_amount, payments_prior_amount, balance_amount,
                         remarks, multiple_node_id, submitted_at, submitted_by, created_by)
                     VALUES ("
                    . $this->str($dvNo) . ', ' . $this->str($f['dv_date']) . ', ' . $f['fiscal_yr'] . ', ' . $this->strOrNull($f['expense_class']) . ', ' . (int) $f['status_id'] . ', '
                    . $this->intOrNull($f['requesting_department_id']) . ', ' . $this->strOrNull($f['dept_project']) . ', '
                    . $this->strOrNull($f['fund_source']) . ', '
                    . $this->intOrNull($f['vendor_id']) . ', '
                    . $this->strOrNull($f['claimant_name']) . ', ' . $this->strOrNull($f['claimant_address']) . ', '
                    . $this->strOrNull($f['employee_no_tin']) . ', '
                    . $this->strOrNull($f['particulars']) . ', ' . (float) $f['gross_amount'] . ', ' . (float) $f['net_amount'] . ', '
                    . $this->strOrNull($f['po_number']) . ', ' . $this->numOrNull($f['po_amount']) . ', '
                    . $this->strOrNull($f['account_code']) . ', ' . (float) $f['budget_amount'] . ', ' . (float) $f['obligation_amount'] . ', '
                    . (float) $paymentsPrior . ', ' . (float) $balance . ', '
                    . $this->strOrNull($f['remarks']) . ', ' . $this->strOrNull($f['multiple_node_id']) . ', '
                    . ($submitted ? "CURRENT_TIMESTAMP, {$uid}" : 'NULL, NULL') . ', '
                    . $uid . ')
                     RETURNING dv_id, dv_no',
                    'DB'
                );

                $this->replaceDeductions((int) $row['dv_id'], $f['deductions']);

                $rfaError = $this->linkRfas((int) $row['dv_id'], $f['_rfaRows']);
                if ($rfaError !== null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $rfaError];
                }

                // Optional ATC Code / Accounting Entries pre-fill — see persistJournalEntry()'s
                // doc comment. A validation failure here rolls back the DV creation too.
                if ($f['atc_lines'] || $f['lines']) {
                    $jevResult = $this->persistJournalEntry((int) $row['dv_id'], $f['atc_lines'], $f['lines'], $userId, false);
                    if (!$jevResult['success']) {
                        App\Execute('ROLLBACK', 'DB');

                        return $jevResult;
                    }
                }

                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return [
                'success'  => true,
                'message'  => 'Disbursement Voucher created successfully.',
                'data'     => ['dv_id' => (int) $row['dv_id'], 'dv_no' => $row['dv_no'], 'status' => $f['status']],
                'httpCode' => 201,
            ];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create disbursement voucher.'];
        }
    }

    /** Full replace of the editable fields; DRAFT / REJECTED only. dv_no never changes. */
    public function update(int|string $id, array $body, int $userId): array
    {
        try {
            $dvId = (int) $id;
            $cur  = App\ExecuteRow(
                "SELECT dv.dv_id, st.name AS status FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::EDITABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or REJECTED disbursement vouchers can be edited (current status: {$cur['status']})."];
            }

            [$errors, $f] = $this->prepare($body);
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $paymentsPrior = $this->priorPaymentsForRfas(array_column($f['_rfaRows'], 'allocation_id'), $dvId);
            $balance       = round($f['obligation_amount'] - ($paymentsPrior + $f['net_amount']), 2);

            $submitted = $f['status'] === 'FOR_FMD_REVIEW';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                App\Execute(
                    "UPDATE {$this->table} SET
                        dv_date = " . $this->str($f['dv_date']) . ',
                        fiscal_yr = ' . $f['fiscal_yr'] . ',
                        expense_class = ' . $this->strOrNull($f['expense_class']) . ',
                        requesting_department_id = ' . $this->intOrNull($f['requesting_department_id']) . ',
                        dept_project = ' . $this->strOrNull($f['dept_project']) . ',
                        fund_source = ' . $this->strOrNull($f['fund_source']) . ',
                        vendor_id = ' . $this->intOrNull($f['vendor_id']) . ',
                        claimant_name = ' . $this->strOrNull($f['claimant_name']) . ',
                        claimant_address = ' . $this->strOrNull($f['claimant_address']) . ',
                        employee_no_tin = ' . $this->strOrNull($f['employee_no_tin']) . ',
                        particulars = ' . $this->strOrNull($f['particulars']) . ',
                        gross_amount = ' . (float) $f['gross_amount'] . ',
                        net_amount = ' . (float) $f['net_amount'] . ',
                        po_number = ' . $this->strOrNull($f['po_number']) . ',
                        po_amount = ' . $this->numOrNull($f['po_amount']) . ',
                        account_code = ' . $this->strOrNull($f['account_code']) . ',
                        budget_amount = ' . (float) $f['budget_amount'] . ',
                        obligation_amount = ' . (float) $f['obligation_amount'] . ',
                        payments_prior_amount = ' . (float) $paymentsPrior . ',
                        balance_amount = ' . (float) $balance . ',
                        remarks = ' . $this->strOrNull($f['remarks']) . ',
                        multiple_node_id = ' . $this->strOrNull($f['multiple_node_id']) . ',
                        status_id = ' . (int) $f['status_id'] . ',
                        submitted_at = ' . ($submitted ? 'CURRENT_TIMESTAMP' : 'NULL') . ',
                        submitted_by = ' . ($submitted ? $uid : 'NULL') . ',
                        rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL,
                        updated_by = ' . $uid . ",
                        updated_at = CURRENT_TIMESTAMP
                     WHERE dv_id = {$dvId}",
                    'DB'
                );

                $this->replaceDeductions($dvId, $f['deductions']);

                $rfaError = $this->linkRfas($dvId, $f['_rfaRows']);
                if ($rfaError !== null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $rfaError];
                }

                // Optional ATC Code / Accounting Entries pre-fill — see persistJournalEntry()'s
                // doc comment. A validation failure here rolls back the whole update too.
                if ($f['atc_lines'] || $f['lines']) {
                    $jevResult = $this->persistJournalEntry($dvId, $f['atc_lines'], $f['lines'], $userId, false);
                    if (!$jevResult['success']) {
                        App\Execute('ROLLBACK', 'DB');

                        return $jevResult;
                    }
                }

                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => 'Disbursement Voucher updated successfully.', 'data' => ['dv_id' => $dvId, 'status' => $f['status']]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update disbursement voucher.'];
        }
    }

    /** The Responsibility Center (uac.users.rc_id) the current user's account belongs to, if any. */
    private function currentUserRcId(): ?string
    {
        $userId = (int) App\CurrentUserID();
        if ($userId <= 0) {
            return null;
        }

        return App\ExecuteScalar("SELECT rc_id FROM {$this->tableUsers} WHERE user_id = {$userId}", 'DB') ?: null;
    }

    /**
     * Null if the current user may move $cur['status'] -> $new, otherwise the error message to
     * return. Superadmin always passes (GlobalService::getUserRcScope()['is_admin'] bypass,
     * project-wide convention — see this class's docblock). Whoever holds $cur['status']
     * (STAGE_HOLDER_RC) may move it forward or reject it; CANCELLED is allowed by that same
     * holder OR the voucher's own creator. DRAFT (Originating holds it, no entry in
     * STAGE_HOLDER_RC) is open to anyone authenticated for both submitting and cancelling their own
     * draft — "their own" is enforced by the creator check in the CANCELLED branch below.
     */
    private function authorizeTransition(array $cur, string $new): ?string
    {
        if (GlobalService::getUserRcScope()['is_admin']) {
            return null;
        }

        $holderRc = self::STAGE_HOLDER_RC[$cur['status']] ?? null; // null while DRAFT
        $userRc   = $this->currentUserRcId();

        if ($new === 'CANCELLED') {
            $isCreator = (int) ($cur['created_by'] ?? 0) > 0 && (int) $cur['created_by'] === (int) App\CurrentUserID();
            $isHolder  = $holderRc !== null && $userRc === $holderRc;

            return ($isCreator || $isHolder) ? null : 'Only the voucher\'s creator or the office currently holding it can cancel it.';
        }
        if ($holderRc === null) {
            return null; // DRAFT -> FOR_FMD_REVIEW: Originating submits, open to anyone authenticated.
        }
        $label = self::STAGE_RC_LABEL[$holderRc] ?? $holderRc;

        return $userRc === $holderRc ? null : "Only {$label} staff can do that.";
    }

    /** Moves a DV along TRANSITIONS. REJECTED needs a reason; $notes is stored as rejection_reason. */
    public function changeStatus(int|string $id, string $newStatus, int $userId, ?string $notes = null): array
    {
        try {
            $dvId = (int) $id;
            $new  = strtoupper(trim($newStatus));
            $cur  = App\ExecuteRow(
                "SELECT dv.dv_id, dv.status_id, st.name AS status, dv.created_by FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            $newId = $this->getStatusIdByName($new);
            if ($newId === null) {
                return ['success' => false, 'message' => "Status '{$new}' is not configured in fims.settings_status for disbursement vouchers."];
            }

            $allowed = self::TRANSITIONS[$cur['status']] ?? [];
            if (!in_array($new, $allowed, true)) {
                return ['success' => false, 'message' => "Cannot change a {$cur['status']} disbursement voucher to {$new}."];
            }
            $authError = $this->authorizeTransition($cur, $new);
            if ($authError !== null) {
                return ['success' => false, 'message' => $authError, 'httpCode' => 403];
            }
            if ($new === 'FOR_TREASURY') {
                $jev = $this->fetchJev($dvId);
                if (!$jev || (float) $jev['total_debit'] <= 0 || (float) $jev['total_debit'] !== (float) $jev['total_credit']) {
                    return ['success' => false, 'message' => 'Accounting must save a balanced Journal Entry (debits = credits) before this voucher can go to Treasury.'];
                }
            }
            $notes = trim((string) $notes);
            if ($new === 'REJECTED' && $notes === '') {
                return ['success' => false, 'message' => 'A reason is required when rejecting a disbursement voucher.'];
            }

            $uid = $userId > 0 ? (string) $userId : 'NULL';
            $set = ["status_id = {$newId}", 'updated_at = CURRENT_TIMESTAMP', "updated_by = {$uid}"];
            if ($new === 'FOR_FMD_REVIEW') {
                $set[] = "submitted_at = CURRENT_TIMESTAMP, submitted_by = {$uid}";
                $set[] = 'rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL';
            } elseif ($new === 'FOR_ACCOUNTING') {
                $set[] = "fmd_approved_at = CURRENT_TIMESTAMP, fmd_approved_by = {$uid}";
            } elseif ($new === 'FOR_TREASURY') {
                $set[] = "accounting_processed_at = CURRENT_TIMESTAMP, accounting_processed_by = {$uid}";
            } elseif ($new === 'RELEASED') {
                $set[] = "treasury_released_at = CURRENT_TIMESTAMP, treasury_released_by = {$uid}";
            } elseif ($new === 'REJECTED') {
                $set[] = "rejected_at = CURRENT_TIMESTAMP, rejected_by = {$uid}, rejection_reason = " . $this->str($notes);
            }

            // Guard on the status we read so two concurrent actions can't both win.
            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE dv_id = {$dvId} AND status_id = " . (int) $cur['status_id'],
                'DB'
            );
            $now = App\ExecuteScalar("SELECT status_id FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');
            if ((int) $now !== $newId) {
                return ['success' => false, 'message' => 'The disbursement voucher was changed by someone else. Please reload and try again.'];
            }

            return ['success' => true, 'message' => "Disbursement Voucher is now {$new}.", 'data' => ['dv_id' => $dvId, 'status' => $new]];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::changeStatus ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to change disbursement voucher status.'];
        }
    }

    public function delete(int|string $id): array
    {
        try {
            $dvId = (int) $id;
            $cur  = App\ExecuteRow(
                "SELECT dv.dv_no, st.name AS status FROM {$this->table} dv
                 LEFT JOIN {$this->tableSettingsStatus} st ON st.id = dv.status_id
                 WHERE dv.dv_id = {$dvId}",
                'DB'
            );
            if (!$cur) {
                return ['success' => false, 'message' => "Disbursement Voucher #{$dvId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::DELETABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or CANCELLED disbursement vouchers can be deleted (current status: {$cur['status']})."];
            }

            // Release any RFA still linked to this voucher (budget_allocation.dv_id) BEFORE deleting
            // it — otherwise dv_id would dangle, and rfaAvailableSql()'s "my cancelled/rejected DV
            // doesn't block the RFA" check can't match a dv_id whose row no longer exists at all.
            App\Execute("UPDATE {$this->tableRfa} SET dv_id = NULL WHERE dv_id = {$dvId}", 'DB');
            App\Execute("DELETE FROM {$this->table} WHERE dv_id = {$dvId}", 'DB');

            return ['success' => true, 'message' => "Disbursement Voucher {$cur['dv_no']} deleted successfully."];
        } catch (\Exception $e) {
            App\LogError('DisbursementVoucherService::delete ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete disbursement voucher.'];
        }
    }
}
