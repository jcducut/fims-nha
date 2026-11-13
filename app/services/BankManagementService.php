<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Bank Account Management module (app/pages/bank-management-page.php),
 * ported from the TREASURY reference project's
 * _assets/TREASURY/app/pages/bankaccmanagement.php +
 * _assets/TREASURY/app/services/BankAccManServices.php.
 *
 * TREASURY's version joins against its own shared.fund_sources lookup table
 * and derives last_transaction_date live from treasury.soa_transactions —
 * neither exists in this project, so fund_source is a plain text field
 * (same convention as fims.disbursement_vouchers.fund_source) and
 * last_transaction_date is a manually-entered field rather than
 * auto-derived. Region/Office is always keyed by Responsibility Center
 * (fims.responsibility_code.rc_id), not uac.departments — same convention
 * already used by fims.budget_allocation.requesting_rc_id (see
 * "03 - alter_table/14 - budget_allocation_requesting_department_id_to_
 * requesting_rc_id.sql"). rc_id has no FK, like that column; this service
 * validates it exists before saving instead. Documents are Google-Drive
 * links (not uploads), matching the pattern already named but
 * unimplemented in CheckProcessingService::saveRegionDriveLink() — stored
 * in fims.bank_account_documents.
 *
 * `status` (ACTIVE/DORMANT/CLOSED) is new and is kept in sync with the
 * pre-existing `is_active` boolean so CheckProcessingService::
 * getBankAccounts()'s "WHERE is_active = TRUE" filter (used across the
 * Check Disbursement Voucher module's Bank Account dropdowns) keeps
 * working unmodified. "Near dormant" is a computed warning window (an
 * ACTIVE account whose last_transaction_date is 300–365 days old), not a
 * stored status — same distinction TREASURY's own KPI cards make.
 */
class BankManagementService
{
    private string $tableBankAccounts     = 'fims.bank_accounts';
    private string $tableDocuments        = 'fims.bank_account_documents';
    private string $tableAuditLog         = 'fims.bank_account_audit_log';
    private string $tableResponsibilityCode = 'fims.responsibility_code';

    // ── small SQL helpers (same shape as CheckProcessingService) ──

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function dateOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function bool(mixed $v): string
    {
        return filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE';
    }

    private function ok($data = [])
    {
        return json_encode(["success" => true, "data" => $data]);
    }

    private function dataTable($draw, array $rows)
    {
        return json_encode([
            "draw" => (int) $draw,
            "recordsTotal" => count($rows),
            "recordsFiltered" => count($rows),
            "data" => $rows,
        ]);
    }

    private const ACCOUNT_TYPES = ['CURRENT', 'SAVINGS', 'NON_DRAWING_SAVINGS', 'TIME_DEPOSIT', 'TRUST'];
    private const CLASSIFICATIONS = ['COLLECTION', 'DISBURSEMENT', 'COE_MOOE', 'CAPITAL_OUTLAY', 'EHAP', 'SPECIAL_PURPOSE', 'PROJECT', 'JOINT_VENTURE', 'TRUST'];
    private const STATUSES = ['ACTIVE', 'DORMANT', 'CLOSED'];

    // ── Stats (KPI cards) ──
    public function getStats()
    {
        $row = App\ExecuteRow(
            "SELECT
                COUNT(*) AS total,
                COUNT(*) FILTER (WHERE status = 'ACTIVE') AS active,
                COUNT(*) FILTER (WHERE status = 'DORMANT') AS dormant,
                COUNT(*) FILTER (WHERE status = 'CLOSED') AS closed,
                COUNT(*) FILTER (
                    WHERE status = 'ACTIVE'
                      AND last_transaction_date IS NOT NULL
                      AND last_transaction_date <= CURRENT_DATE - INTERVAL '300 days'
                      AND last_transaction_date >  CURRENT_DATE - INTERVAL '365 days'
                ) AS near_dormant
             FROM {$this->tableBankAccounts}",
            'DB'
        );

        return $this->ok([
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'dormant' => (int) ($row['dormant'] ?? 0),
            'closed' => (int) ($row['closed'] ?? 0),
            'near_dormant' => (int) ($row['near_dormant'] ?? 0),
        ]);
    }

    // ── List (DataTables-shaped, filterable) ──
    public function getList($params)
    {
        $where = ['1=1'];
        $status = strtoupper(trim((string) ($params['status'] ?? '')));
        if (in_array($status, self::STATUSES, true)) {
            $where[] = "ba.status = " . $this->str($status);
        }
        $classification = strtoupper(trim((string) ($params['classification'] ?? '')));
        if (in_array($classification, self::CLASSIFICATIONS, true)) {
            $where[] = "ba.account_classification = " . $this->str($classification);
        }
        $fundSource = trim((string) ($params['fund_source'] ?? ''));
        if ($fundSource !== '') {
            $where[] = "ba.fund_source = " . $this->str($fundSource);
        }
        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $needle = $this->str('%' . $search . '%');
            $where[] = "(ba.account_name ILIKE {$needle} OR ba.account_number ILIKE {$needle} OR ba.bank_name ILIKE {$needle})";
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.*, rc.rc_code, rc.rc_name
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN {$this->tableResponsibilityCode} rc ON rc.rc_id = ba.rc_id
             WHERE {$whereSql}
             ORDER BY ba.account_name",
            'DB'
        );

        return $this->dataTable($params['draw'] ?? 1, $rows);
    }

    // ── Single account (with documents) ──
    public function getAccount($id)
    {
        $account = $this->fetchAccount((int) $id);
        if (!$account) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }
        $account['documents'] = App\ExecuteRowsAssociative(
            "SELECT document_id, link_name, gdrive_link, added_at
             FROM {$this->tableDocuments}
             WHERE bank_account_id = " . (int) $id . "
             ORDER BY added_at DESC",
            'DB'
        );

        return $this->ok($account);
    }

    private function fetchAccount(int $id): ?array
    {
        return App\ExecuteRow(
            "SELECT ba.*, rc.rc_code, rc.rc_name
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN {$this->tableResponsibilityCode} rc ON rc.rc_id = ba.rc_id
             WHERE ba.bank_account_id = {$id}",
            'DB'
        ) ?: null;
    }

    // Validates a submitted Region/Office value against fims.responsibility_code
    // (rc_id has no FK — see this class's docblock). Empty is valid (Main Office).
    private function rcIdExists(string $rcId): bool
    {
        return $rcId === '' || (bool) App\ExecuteRow(
            "SELECT rc_id FROM {$this->tableResponsibilityCode} WHERE rc_id = " . $this->str($rcId),
            'DB'
        );
    }

    // ── Create ──
    public function store($data)
    {
        $accountName = trim((string) ($data['account_name'] ?? ''));
        $accountNumber = trim((string) ($data['account_number'] ?? ''));
        $classification = strtoupper(trim((string) ($data['account_classification'] ?? '')));
        $bankName = trim((string) ($data['bank_name'] ?? ''));
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));

        if ($accountName === '' || $accountNumber === '' || $bankName === '') {
            return json_encode(["success" => false, "message" => "Account Name, Account Number, and Bank Name are required."]);
        }
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            return json_encode(["success" => false, "message" => "Select a valid Purpose."]);
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'ACTIVE';
        }
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        if (!$this->rcIdExists($rcId)) {
            return json_encode(["success" => false, "message" => "Select a valid Region/Office."]);
        }
        if (App\ExecuteRow("SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE account_number = " . $this->str($accountNumber), 'DB')) {
            return json_encode(["success" => false, "message" => "An account with this Account Number already exists."]);
        }

        $accountType = strtoupper(trim((string) ($data['account_type'] ?? '')));
        if (!in_array($accountType, self::ACCOUNT_TYPES, true)) {
            $accountType = null;
        }
        $userId = (int) App\CurrentUserID();

        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableBankAccounts}
                (account_name, account_number, bank_name, bank_branch, account_type, account_classification,
                 fund_source, rc_id, is_main_depository, status, is_active, date_opened,
                 last_transaction_date, created_by)
             VALUES (
                " . $this->str($accountName) . ', ' . $this->str($accountNumber) . ', ' . $this->str($bankName) . ', '
                . $this->strOrNull($data['bank_branch'] ?? null) . ', ' . $this->strOrNull($accountType) . ', '
                . $this->str($classification) . ', ' . $this->strOrNull($data['fund_source'] ?? null) . ', '
                . $this->strOrNull($rcId) . ', ' . $this->bool($data['is_main_depository'] ?? false) . ', '
                . $this->str($status) . ', ' . ($status === 'ACTIVE' ? 'TRUE' : 'FALSE') . ', '
                . $this->dateOrNull($data['date_opened'] ?? null) . ', ' . $this->dateOrNull($data['last_transaction_date'] ?? null)
                . ", {$userId})
             RETURNING bank_account_id",
            'DB'
        );
        $accountId = (int) $row['bank_account_id'];

        $this->writeAuditLog($accountId, 'CREATE', null, $this->fetchAccount($accountId), $userId);

        return $this->ok(['bank_account_id' => $accountId]);
    }

    // ── Update ──
    public function update($id, $data)
    {
        $accountId = (int) $id;
        $before = $this->fetchAccount($accountId);
        if (!$before) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        $accountName = trim((string) ($data['account_name'] ?? ''));
        $accountNumber = trim((string) ($data['account_number'] ?? ''));
        $classification = strtoupper(trim((string) ($data['account_classification'] ?? '')));
        $bankName = trim((string) ($data['bank_name'] ?? ''));
        $status = strtoupper(trim((string) ($data['status'] ?? 'ACTIVE')));

        if ($accountName === '' || $accountNumber === '' || $bankName === '') {
            return json_encode(["success" => false, "message" => "Account Name, Account Number, and Bank Name are required."]);
        }
        if (!in_array($classification, self::CLASSIFICATIONS, true)) {
            return json_encode(["success" => false, "message" => "Select a valid Purpose."]);
        }
        if (!in_array($status, self::STATUSES, true)) {
            $status = 'ACTIVE';
        }
        $rcId = trim((string) ($data['rc_id'] ?? ''));
        if (!$this->rcIdExists($rcId)) {
            return json_encode(["success" => false, "message" => "Select a valid Region/Office."]);
        }
        $dupe = App\ExecuteRow(
            "SELECT bank_account_id FROM {$this->tableBankAccounts}
             WHERE account_number = " . $this->str($accountNumber) . " AND bank_account_id <> {$accountId}",
            'DB'
        );
        if ($dupe) {
            return json_encode(["success" => false, "message" => "Another account already uses this Account Number."]);
        }

        $accountType = strtoupper(trim((string) ($data['account_type'] ?? '')));
        if (!in_array($accountType, self::ACCOUNT_TYPES, true)) {
            $accountType = null;
        }
        $userId = (int) App\CurrentUserID();

        App\Execute(
            "UPDATE {$this->tableBankAccounts} SET
                account_name = " . $this->str($accountName) . ",
                account_number = " . $this->str($accountNumber) . ",
                bank_name = " . $this->str($bankName) . ",
                bank_branch = " . $this->strOrNull($data['bank_branch'] ?? null) . ",
                account_type = " . $this->strOrNull($accountType) . ",
                account_classification = " . $this->str($classification) . ",
                fund_source = " . $this->strOrNull($data['fund_source'] ?? null) . ",
                rc_id = " . $this->strOrNull($rcId) . ",
                is_main_depository = " . $this->bool($data['is_main_depository'] ?? false) . ",
                status = " . $this->str($status) . ",
                is_active = " . ($status === 'ACTIVE' ? 'TRUE' : 'FALSE') . ",
                date_opened = " . $this->dateOrNull($data['date_opened'] ?? null) . ",
                last_transaction_date = " . $this->dateOrNull($data['last_transaction_date'] ?? null) . ",
                updated_at = CURRENT_TIMESTAMP
             WHERE bank_account_id = {$accountId}",
            'DB'
        );

        $this->writeAuditLog($accountId, 'UPDATE', $before, $this->fetchAccount($accountId), $userId);

        return $this->ok(['bank_account_id' => $accountId]);
    }

    // ── Status change ──
    public function changeStatus($id, $data)
    {
        $accountId = (int) $id;
        $status = strtoupper(trim((string) ($data['status'] ?? '')));
        if (!in_array($status, self::STATUSES, true)) {
            return json_encode(["success" => false, "message" => "Invalid status."]);
        }
        $before = $this->fetchAccount($accountId);
        if (!$before) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        $userId = (int) App\CurrentUserID();
        App\Execute(
            "UPDATE {$this->tableBankAccounts}
             SET status = " . $this->str($status) . ", is_active = " . ($status === 'ACTIVE' ? 'TRUE' : 'FALSE') . ",
                 updated_at = CURRENT_TIMESTAMP
             WHERE bank_account_id = {$accountId}",
            'DB'
        );

        $this->writeAuditLog($accountId, 'STATUS_CHANGE', $before, $this->fetchAccount($accountId), $userId);

        return $this->ok(['bank_account_id' => $accountId, 'status' => $status]);
    }

    private function writeAuditLog(int $accountId, string $action, ?array $before, ?array $after, int $userId): void
    {
        App\Execute(
            "INSERT INTO {$this->tableAuditLog} (bank_account_id, action, before_data, after_data, performed_by)
             VALUES ({$accountId}, " . $this->str($action) . ', '
                . ($before !== null ? $this->str(json_encode($before)) . '::jsonb' : 'NULL') . ', '
                . ($after !== null ? $this->str(json_encode($after)) . '::jsonb' : 'NULL')
                . ", {$userId})",
            'DB'
        );
    }

    // ── Dormancy alerts ──
    public function getDormancyAlerts()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT bank_account_id, account_name, account_number, bank_name, last_transaction_date,
                    (CURRENT_DATE - last_transaction_date)::int AS days_inactive
             FROM {$this->tableBankAccounts}
             WHERE status = 'ACTIVE'
               AND last_transaction_date IS NOT NULL
               AND last_transaction_date <= CURRENT_DATE - INTERVAL '300 days'
               AND last_transaction_date >  CURRENT_DATE - INTERVAL '365 days'
             ORDER BY last_transaction_date",
            'DB'
        );

        return $this->ok($rows);
    }

    // ── Documents ──
    public function getDocuments($id)
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT document_id, link_name, gdrive_link, added_at
             FROM {$this->tableDocuments}
             WHERE bank_account_id = " . (int) $id . "
             ORDER BY added_at DESC",
            'DB'
        );

        return $this->ok($rows);
    }

    public function saveDocumentLink($id, $data)
    {
        $accountId = (int) $id;
        $linkName = trim((string) ($data['link_name'] ?? ''));
        $gdriveLink = trim((string) ($data['gdrive_link'] ?? ''));
        if ($linkName === '' || $gdriveLink === '') {
            return json_encode(["success" => false, "message" => "Link name and Google Drive URL are required."]);
        }
        if (!App\ExecuteRow("SELECT bank_account_id FROM {$this->tableBankAccounts} WHERE bank_account_id = {$accountId}", 'DB')) {
            return json_encode(["success" => false, "message" => "Bank account not found."]);
        }

        $userId = (int) App\CurrentUserID();
        $row = App\ExecuteRow(
            "INSERT INTO {$this->tableDocuments} (bank_account_id, link_name, gdrive_link, added_by)
             VALUES ({$accountId}, " . $this->str($linkName) . ', ' . $this->str($gdriveLink) . ", {$userId})
             RETURNING document_id",
            'DB'
        );

        return $this->ok(['document_id' => $row['document_id']]);
    }

    public function removeDocumentLink($id, $data)
    {
        $accountId = (int) $id;
        $documentId = (int) ($data['document_id'] ?? 0);
        if ($documentId <= 0) {
            return json_encode(["success" => false, "message" => "Document not specified."]);
        }
        App\Execute(
            "DELETE FROM {$this->tableDocuments} WHERE document_id = {$documentId} AND bank_account_id = {$accountId}",
            'DB'
        );

        return $this->ok(['document_id' => $documentId]);
    }

    // ── Lookups ──
    public function getResponsibilityCenters()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT rc_id, rc_code, rc_name
             FROM {$this->tableResponsibilityCode}
             WHERE status = 'Active'
             ORDER BY rc_code",
            'DB'
        );

        return $this->ok($rows);
    }

    public function getFundSources()
    {
        $rows = App\ExecuteRowsAssociative(
            "SELECT DISTINCT fund_source
             FROM {$this->tableBankAccounts}
             WHERE fund_source IS NOT NULL AND fund_source <> ''
             ORDER BY fund_source",
            'DB'
        );

        return $this->ok(array_map(fn ($r) => $r['fund_source'], $rows));
    }
}
