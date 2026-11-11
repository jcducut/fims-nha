<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Purchase orders (fims.purchase_orders + fims.purchase_order_items).
 *
 * Standalone port of GLOBAL\PurchaseOrderServices. Deliberately NOT ported: parent
 * purchase request, budget allocation / fund source, fiscal periods, the workflow
 * engine, payment workflow and delivery receipts - none of those exist in the
 * `fims` schema. Status is a plain column moved by changeStatus():
 *
 *   DRAFT     -> SUBMITTED | CANCELLED
 *   SUBMITTED -> APPROVED | REJECTED | CANCELLED
 *   REJECTED  -> CANCELLED            (or edited + re-saved via update())
 *   APPROVED / CANCELLED are terminal.
 */
class PurchaseOrderService
{
    private string $table        = 'fims.purchase_orders';
    private string $tableItems   = 'fims.purchase_order_items';
    private string $tableVendor  = 'fims.vendors';
    private string $tableProgram = 'fims.program';
    private string $tableProject = 'fims.project';
    private string $tableFiscal  = 'fims.fiscal_year';

    private const EXPENSE_CLASSES = ['PS', 'MOOE', 'CAPITAL_OUTLAY', 'CO', 'OTHER'];
    private const EDITABLE        = ['DRAFT', 'REJECTED'];
    private const DELETABLE       = ['DRAFT', 'CANCELLED'];
    private const SAVE_STATUSES   = ['DRAFT', 'SUBMITTED'];
    private const TRANSITIONS     = [
        'DRAFT'     => ['SUBMITTED', 'CANCELLED'],
        'SUBMITTED' => ['APPROVED', 'REJECTED', 'CANCELLED'],
        'REJECTED'  => ['CANCELLED'],
        'APPROVED'  => [],
        'CANCELLED' => [],
    ];

    // Optional free-text columns copied straight from the request body.
    private const TEXT_FIELDS = [
        'contract_number', 'delivery_place', 'delivery_terms', 'payment_terms', 'particulars', 'remarks',
    ];

    // ── small SQL helpers ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
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
     * Returns [errors[], fields[], items[], vendor row|null].
     */
    private function prepare(array $body): array
    {
        $errors = [];
        $fields = [];

        // PO date
        $poDate = trim((string) ($body['po_date'] ?? ''));
        if ($poDate === '') {
            $errors[] = 'PO Date (po_date) is required.';
        } elseif (!$this->validDate($poDate)) {
            $errors[] = 'PO Date must be a valid date (YYYY-MM-DD).';
        }
        $fields['po_date'] = $poDate;

        // Fiscal year
        $fy = $body['fiscal_yr'] ?? '';
        if ($fy === '' || $fy === null || !ctype_digit((string) $fy)) {
            $errors[] = 'Fiscal Year (fiscal_yr) is required.';
        } elseif ((int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableFiscal} WHERE fiscal_yr = " . (int) $fy, 'DB') === 0) {
            $errors[] = "Fiscal year {$fy} does not exist.";
        }
        $fields['fiscal_yr'] = (int) $fy;

        // Program / project (both optional; a project implies its program)
        $programCode = trim((string) ($body['program_code'] ?? ''));
        $projectCode = trim((string) ($body['project_code'] ?? ''));
        if ($projectCode !== '') {
            $proj = App\ExecuteRow("SELECT program_code FROM {$this->tableProject} WHERE project_code = " . $this->str($projectCode), 'DB');
            if (!$proj) {
                $errors[] = "Project '{$projectCode}' does not exist.";
            } elseif ($programCode !== '' && $proj['program_code'] !== $programCode) {
                $errors[] = "Project '{$projectCode}' does not belong to program '{$programCode}'.";
            } else {
                $programCode = $proj['program_code'];
            }
        }
        if ($programCode !== '' && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableProgram} WHERE program_code = " . $this->str($programCode), 'DB') === 0) {
            $errors[] = "Program '{$programCode}' does not exist.";
        }
        $fields['program_code'] = $programCode;
        $fields['project_code'] = $projectCode;

        // Expense class
        $ec = strtoupper(trim((string) ($body['expense_class'] ?? '')));
        if ($ec !== '' && !in_array($ec, self::EXPENSE_CLASSES, true)) {
            $errors[] = "Invalid expense_class '{$ec}'. Allowed: " . implode(', ', self::EXPENSE_CLASSES);
        }
        $fields['expense_class'] = $ec;

        // Vendor — must exist and be active; its snapshot comes from the DB, never the client.
        $vendor   = null;
        $vendorId = $body['vendor_id'] ?? '';
        if ($vendorId === '' || $vendorId === null || !ctype_digit((string) $vendorId) || (int) $vendorId <= 0) {
            $errors[] = 'Vendor (vendor_id) is required.';
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

        // Delivery date
        $dd = trim((string) ($body['delivery_date_required'] ?? ''));
        if ($dd !== '' && !$this->validDate($dd)) {
            $errors[] = 'Delivery Date Required must be a valid date (YYYY-MM-DD).';
        }
        $fields['delivery_date_required'] = $dd;

        foreach (self::TEXT_FIELDS as $f) {
            $fields[$f] = trim((string) ($body[$f] ?? ''));
        }

        // Status the user is saving as
        $status = strtoupper(trim((string) ($body['status'] ?? 'DRAFT')));
        if (!in_array($status, self::SAVE_STATUSES, true)) {
            $errors[] = 'status must be DRAFT or SUBMITTED when saving; use the status action to approve, reject or cancel.';
        }
        $fields['status'] = $status;

        // Items
        $items = isset($body['items']) && is_array($body['items']) ? array_values($body['items']) : [];
        if (!$items) {
            $errors[] = 'At least one line item is required.';
        }
        $clean = [];
        $total = 0.0;
        foreach ($items as $i => $it) {
            $n    = $i + 1;
            $desc = trim((string) ($it['item_description'] ?? ''));
            $uom  = trim((string) ($it['unit_of_measure'] ?? ''));
            $qty  = $it['quantity_ordered'] ?? null;
            $cost = $it['unit_cost'] ?? null;
            if ($desc === '') {
                $errors[] = "Line {$n}: item_description is required.";
            }
            if ($uom === '') {
                $errors[] = "Line {$n}: unit_of_measure is required.";
            }
            if (!is_numeric($qty) || (float) $qty <= 0) {
                $errors[] = "Line {$n}: quantity_ordered must be greater than zero.";
            }
            if (!is_numeric($cost) || (float) $cost < 0) {
                $errors[] = "Line {$n}: unit_cost must be zero or more.";
            }
            if (is_numeric($qty) && is_numeric($cost)) {
                $total += round((float) $qty * (float) $cost, 2);
            }
            $clean[] = [
                'item_description' => $desc,
                'unit_of_measure'  => $uom,
                'quantity_ordered' => is_numeric($qty) ? (float) $qty : 0,
                'unit_cost'        => is_numeric($cost) ? (float) $cost : 0,
                'account_code'     => trim((string) ($it['account_code'] ?? '')),
                'remarks'          => trim((string) ($it['remarks'] ?? '')),
            ];
        }
        if ($items && !$errors && $total <= 0) {
            $errors[] = 'Total amount must be greater than zero.';
        }

        // account_code must exist in the chart of accounts
        foreach (array_unique(array_filter(array_column($clean, 'account_code'))) as $code) {
            if ((int) App\ExecuteScalar('SELECT COUNT(*) FROM fims.chart_of_accounts WHERE account_code = ' . $this->str($code), 'DB') === 0) {
                $errors[] = "Account code '{$code}' does not exist in the chart of accounts.";
            }
        }

        return [$errors, $fields, $clean, $vendor];
    }

    private function insertItems(int $poId, array $items): void
    {
        foreach ($items as $idx => $it) {
            App\Execute(
                "INSERT INTO {$this->tableItems}
                    (po_id, line_number, item_description, unit_of_measure, quantity_ordered, unit_cost, account_code, remarks)
                 VALUES ({$poId}, " . ($idx + 1) . ', '
                . $this->str($it['item_description']) . ', '
                . $this->str($it['unit_of_measure']) . ', '
                . (float) $it['quantity_ordered'] . ', '
                . (float) $it['unit_cost'] . ', '
                . $this->strOrNull($it['account_code']) . ', '
                . $this->strOrNull($it['remarks']) . ')',
                'DB'
            );
        }
    }

    /** total_amount / outstanding_amount always come from the stored (generated) line totals. */
    private function recalcTotals(int $poId): void
    {
        App\Execute(
            "UPDATE {$this->table} SET
                total_amount = s.t, outstanding_amount = s.t
             FROM (SELECT COALESCE(SUM(total_cost), 0) AS t FROM {$this->tableItems} WHERE po_id = {$poId}) s
             WHERE po_id = {$poId}",
            'DB'
        );
    }

    // ── queries ──

    public function fetchAlls(?int $page = null, int $limit = 10, array $filters = []): array
    {
        try {
            $where = ['1=1'];
            if (!empty($filters['status'])) {
                $where[] = 'po.status = ' . $this->str(strtoupper($filters['status']));
            }
            if (!empty($filters['fiscal_yr'])) {
                $where[] = 'po.fiscal_yr = ' . (int) $filters['fiscal_yr'];
            }
            if (!empty($filters['expense_class'])) {
                $where[] = 'po.expense_class = ' . $this->str(strtoupper($filters['expense_class']));
            }
            if (!empty($filters['vendor_id'])) {
                $where[] = 'po.vendor_id = ' . (int) $filters['vendor_id'];
            }
            if (!empty($filters['search'])) {
                $kw      = App\QuotedValue('%' . $filters['search'] . '%', App\DataType::STRING, 'DB');
                $where[] = "(po.po_number ILIKE {$kw} OR po.vendor_name ILIKE {$kw} OR po.contract_number ILIKE {$kw})";
            }
            $whereSql = implode(' AND ', $where);

            $select = "SELECT po.*, v.vendor_code, pg.program_name, pj.project_name, u.username AS created_by_name
                       FROM {$this->table} po
                       LEFT JOIN {$this->tableVendor}  v  ON v.vendor_id = po.vendor_id
                       LEFT JOIN {$this->tableProgram} pg ON pg.program_code = po.program_code
                       LEFT JOIN {$this->tableProject} pj ON pj.project_code = po.project_code
                       LEFT JOIN uac.users u ON u.user_id = po.created_by
                       WHERE {$whereSql}
                       ORDER BY po.created_at DESC, po.po_id DESC";

            if ($page === null) {
                return ['success' => true, 'data' => App\ExecuteRows($select, 'DB') ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} po WHERE {$whereSql}", 'DB');

            // Summary covers every row matching the filters, not just the current page.
            $summary = App\ExecuteRow(
                "SELECT COALESCE(SUM(po.total_amount), 0)       AS total_amount,
                        COALESCE(SUM(po.outstanding_amount), 0) AS outstanding_amount,
                        COUNT(*) FILTER (WHERE po.status = 'APPROVED')                AS approved,
                        COUNT(*) FILTER (WHERE po.status = 'SUBMITTED')               AS pending,
                        COUNT(*) FILTER (WHERE po.status IN ('REJECTED','CANCELLED')) AS rejected
                 FROM {$this->table} po WHERE {$whereSql}",
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
            App\LogError('PurchaseOrderService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load purchase orders.'];
        }
    }

    /** Fiscal years for the PO form / list filter dropdowns (newest first). */
    public function fetchFiscalYears(): array
    {
        try {
            $rows = App\ExecuteRows("SELECT fiscal_yr FROM {$this->tableFiscal} ORDER BY fiscal_yr DESC", 'DB') ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            App\LogError('PurchaseOrderService::fetchFiscalYears ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load fiscal years.'];
        }
    }

    /** $id is a numeric po_id or a po_number. */
    public function fetchById(int|string $id): array
    {
        try {
            $where = ctype_digit((string) $id)
                ? 'po.po_id = ' . (int) $id
                : 'po.po_number = ' . $this->str($id);

            $row = App\ExecuteRow(
                "SELECT po.*, v.vendor_code, v.vendor_type, pg.program_name, pj.project_name,
                        uc.username AS created_by_name,  us.username AS submitted_by_name,
                        ua.username AS approved_by_name, ur.username AS rejected_by_name
                 FROM {$this->table} po
                 LEFT JOIN {$this->tableVendor}  v  ON v.vendor_id = po.vendor_id
                 LEFT JOIN {$this->tableProgram} pg ON pg.program_code = po.program_code
                 LEFT JOIN {$this->tableProject} pj ON pj.project_code = po.project_code
                 LEFT JOIN uac.users uc ON uc.user_id = po.created_by
                 LEFT JOIN uac.users us ON us.user_id = po.submitted_by
                 LEFT JOIN uac.users ua ON ua.user_id = po.approved_by
                 LEFT JOIN uac.users ur ON ur.user_id = po.rejected_by
                 WHERE {$where} LIMIT 1",
                'DB'
            );
            if (!$row) {
                return ['success' => false, 'message' => "Purchase Order '{$id}' not found.", 'httpCode' => 404];
            }

            $row['items'] = App\ExecuteRows(
                "SELECT * FROM {$this->tableItems} WHERE po_id = " . (int) $row['po_id'] . ' ORDER BY line_number ASC',
                'DB'
            ) ?: [];
            $row['allowed_transitions'] = self::TRANSITIONS[$row['status']] ?? [];
            $row['is_editable']         = in_array($row['status'], self::EDITABLE, true);

            return ['success' => true, 'data' => $row];
        } catch (\Exception $e) {
            App\LogError('PurchaseOrderService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load purchase order.'];
        }
    }

    /** Next free "PO-YYYY-MM-NNNN" for the month of $poDate (uses MAX, so deletes can't cause a clash). */
    public function fetchPoNumber(string $poDate): array
    {
        try {
            if (!$this->validDate($poDate)) {
                return ['success' => false, 'message' => 'po_date must be a valid date (YYYY-MM-DD).'];
            }

            return ['success' => true, 'data' => ['po_number' => $this->nextPoNumber($poDate)]];
        } catch (\Exception $e) {
            App\LogError('PurchaseOrderService::fetchPoNumber ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to generate PO number.'];
        }
    }

    private function nextPoNumber(string $poDate): string
    {
        $prefix = 'PO-' . substr($poDate, 0, 4) . '-' . substr($poDate, 5, 2) . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(po_number FROM '[0-9]+$') AS INTEGER)), 0)
             FROM {$this->table}
             WHERE po_number LIKE " . App\QuotedValue($prefix . '%', App\DataType::STRING, 'DB') . " AND po_number ~ '-[0-9]+$'",
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    // ── writes ──

    public function create(array $body, int $userId): array
    {
        try {
            [$errors, $f, $items, $vendor] = $this->prepare($body);

            $poNumber = trim((string) ($body['po_number'] ?? ''));
            if ($poNumber !== '' && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE po_number = " . $this->str($poNumber), 'DB') > 0) {
                $errors[] = "PO Number '{$poNumber}' already exists.";
            }
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }
            if ($poNumber === '') {
                $poNumber = $this->nextPoNumber($f['po_date']);
            }

            $submitted = $f['status'] === 'SUBMITTED';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                $row = App\ExecuteRow(
                    "INSERT INTO {$this->table}
                        (po_number, po_date, fiscal_yr, program_code, project_code, expense_class,
                         vendor_id, vendor_name, vendor_address, vendor_tin,
                         contract_number, delivery_place, delivery_date_required, delivery_terms, payment_terms,
                         particulars, remarks, status, submitted_at, submitted_by, created_by)
                     VALUES ("
                    . $this->str($poNumber) . ', ' . $this->str($f['po_date']) . ', ' . $f['fiscal_yr'] . ', '
                    . $this->strOrNull($f['program_code']) . ', ' . $this->strOrNull($f['project_code']) . ', ' . $this->strOrNull($f['expense_class']) . ', '
                    . (int) $vendor['vendor_id'] . ', ' . $this->strOrNull($vendor['vendor_name']) . ', '
                    . $this->strOrNull($vendor['address']) . ', ' . $this->strOrNull($vendor['tin']) . ', '
                    . $this->strOrNull($f['contract_number']) . ', ' . $this->strOrNull($f['delivery_place']) . ', '
                    . $this->strOrNull($f['delivery_date_required']) . ', ' . $this->strOrNull($f['delivery_terms']) . ', '
                    . $this->strOrNull($f['payment_terms']) . ', ' . $this->strOrNull($f['particulars']) . ', '
                    . $this->strOrNull($f['remarks']) . ', ' . $this->str($f['status']) . ', '
                    . ($submitted ? "CURRENT_TIMESTAMP, {$uid}" : 'NULL, NULL') . ', '
                    . $uid . ')
                     RETURNING po_id, po_number',
                    'DB'
                );
                $poId = (int) $row['po_id'];
                $this->insertItems($poId, $items);
                $this->recalcTotals($poId);
                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return [
                'success'  => true,
                'message'  => 'Purchase Order created successfully.',
                'data'     => ['po_id' => $poId, 'po_number' => $row['po_number'], 'status' => $f['status']],
                'httpCode' => 201,
            ];
        } catch (\Exception $e) {
            App\LogError('PurchaseOrderService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create purchase order.'];
        }
    }

    /** Full replace of the editable fields + line items; DRAFT / REJECTED only. po_number never changes. */
    public function update(int|string $id, array $body, int $userId): array
    {
        try {
            $poId = (int) $id;
            $cur  = App\ExecuteRow("SELECT po_id, status FROM {$this->table} WHERE po_id = {$poId}", 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "Purchase Order #{$poId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::EDITABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or REJECTED purchase orders can be edited (current status: {$cur['status']})."];
            }

            [$errors, $f, $items, $vendor] = $this->prepare($body);
            if ($errors) {
                return ['success' => false, 'message' => 'Validation failed.', 'errors' => $errors];
            }

            $submitted = $f['status'] === 'SUBMITTED';
            $uid       = $userId > 0 ? (string) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                App\Execute(
                    "UPDATE {$this->table} SET
                        po_date = " . $this->str($f['po_date']) . ',
                        fiscal_yr = ' . $f['fiscal_yr'] . ',
                        program_code = ' . $this->strOrNull($f['program_code']) . ',
                        project_code = ' . $this->strOrNull($f['project_code']) . ',
                        expense_class = ' . $this->strOrNull($f['expense_class']) . ',
                        vendor_id = ' . (int) $vendor['vendor_id'] . ',
                        vendor_name = ' . $this->strOrNull($vendor['vendor_name']) . ',
                        vendor_address = ' . $this->strOrNull($vendor['address']) . ',
                        vendor_tin = ' . $this->strOrNull($vendor['tin']) . ',
                        contract_number = ' . $this->strOrNull($f['contract_number']) . ',
                        delivery_place = ' . $this->strOrNull($f['delivery_place']) . ',
                        delivery_date_required = ' . $this->strOrNull($f['delivery_date_required']) . ',
                        delivery_terms = ' . $this->strOrNull($f['delivery_terms']) . ',
                        payment_terms = ' . $this->strOrNull($f['payment_terms']) . ',
                        particulars = ' . $this->strOrNull($f['particulars']) . ',
                        remarks = ' . $this->strOrNull($f['remarks']) . ',
                        status = ' . $this->str($f['status']) . ',
                        submitted_at = ' . ($submitted ? 'CURRENT_TIMESTAMP' : 'NULL') . ',
                        submitted_by = ' . ($submitted ? $uid : 'NULL') . ',
                        rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL,
                        updated_by = ' . $uid . ",
                        updated_at = CURRENT_TIMESTAMP
                     WHERE po_id = {$poId}",
                    'DB'
                );
                App\Execute("DELETE FROM {$this->tableItems} WHERE po_id = {$poId}", 'DB');
                $this->insertItems($poId, $items);
                $this->recalcTotals($poId);
                App\Execute('COMMIT', 'DB');
            } catch (\Exception $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => 'Purchase Order updated successfully.', 'data' => ['po_id' => $poId, 'status' => $f['status']]];
        } catch (\Exception $e) {
            App\LogError('PurchaseOrderService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update purchase order.'];
        }
    }

    /** Moves a PO along TRANSITIONS. REJECTED needs a reason; $notes is stored as rejection_reason. */
    public function changeStatus(int|string $id, string $newStatus, int $userId, ?string $notes = null): array
    {
        try {
            $poId = (int) $id;
            $new  = strtoupper(trim($newStatus));
            $cur  = App\ExecuteRow("SELECT po_id, status FROM {$this->table} WHERE po_id = {$poId}", 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "Purchase Order #{$poId} not found.", 'httpCode' => 404];
            }

            $allowed = self::TRANSITIONS[$cur['status']] ?? [];
            if (!in_array($new, $allowed, true)) {
                return ['success' => false, 'message' => "Cannot change a {$cur['status']} purchase order to {$new}."];
            }
            $notes = trim((string) $notes);
            if ($new === 'REJECTED' && $notes === '') {
                return ['success' => false, 'message' => 'A reason is required when rejecting a purchase order.'];
            }

            $uid = $userId > 0 ? (string) $userId : 'NULL';
            $set = ['status = ' . $this->str($new), 'updated_at = CURRENT_TIMESTAMP', "updated_by = {$uid}"];
            if ($new === 'SUBMITTED') {
                $set[] = "submitted_at = CURRENT_TIMESTAMP, submitted_by = {$uid}";
                $set[] = 'rejected_at = NULL, rejected_by = NULL, rejection_reason = NULL';
            } elseif ($new === 'APPROVED') {
                $set[] = "approved_at = CURRENT_TIMESTAMP, approved_by = {$uid}";
            } elseif ($new === 'REJECTED') {
                $set[] = "rejected_at = CURRENT_TIMESTAMP, rejected_by = {$uid}, rejection_reason = " . $this->str($notes);
            }

            // Guard on the status we read so two concurrent actions can't both win.
            App\Execute(
                "UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE po_id = {$poId} AND status = " . $this->str($cur['status']),
                'DB'
            );
            $now = App\ExecuteScalar("SELECT status FROM {$this->table} WHERE po_id = {$poId}", 'DB');
            if ($now !== $new) {
                return ['success' => false, 'message' => 'The purchase order was changed by someone else. Please reload and try again.'];
            }

            return ['success' => true, 'message' => "Purchase Order is now {$new}.", 'data' => ['po_id' => $poId, 'status' => $new]];
        } catch (\Exception $e) {
            App\LogError('PurchaseOrderService::changeStatus ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to change purchase order status.'];
        }
    }

    public function delete(int|string $id): array
    {
        try {
            $poId = (int) $id;
            $cur  = App\ExecuteRow("SELECT po_number, status FROM {$this->table} WHERE po_id = {$poId}", 'DB');
            if (!$cur) {
                return ['success' => false, 'message' => "Purchase Order #{$poId} not found.", 'httpCode' => 404];
            }
            if (!in_array($cur['status'], self::DELETABLE, true)) {
                return ['success' => false, 'message' => "Only DRAFT or CANCELLED purchase orders can be deleted (current status: {$cur['status']})."];
            }

            App\Execute("DELETE FROM {$this->table} WHERE po_id = {$poId}", 'DB'); // items cascade

            return ['success' => true, 'message' => "Purchase Order {$cur['po_number']} deleted successfully."];
        } catch (\Exception $e) {
            App\LogError('PurchaseOrderService::delete ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to delete purchase order.'];
        }
    }
}
