<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Vendors (fims.vendors) and their types (fims.vendor_types).
 * Ported from the shared.vendors lookup that GLOBAL's PO form reads.
 */
class VendorService
{
    private string $table     = 'fims.vendors';
    private string $tableType = 'fims.vendor_types';

    private array $textFields = [
        'vendor_code', 'vendor_name', 'vendor_type', 'tin', 'address', 'contact_person',
        'contact_number', 'email', 'bank_name', 'bank_account_number', 'tax_classification', 'status',
    ];

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function validateType(?string $type): ?array
    {
        if ($type === null || $type === '') {
            return null;
        }
        $exists = (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM {$this->tableType} WHERE is_active AND vendor_type = " . $this->str($type),
            'DB'
        );

        return $exists === 0
            ? ['success' => false, 'message' => "Vendor type '{$type}' does not exist or is inactive."]
            : null;
    }

    /** Active vendor types, for dropdowns. */
    public function fetchTypes(): array
    {
        try {
            $data = App\ExecuteRows(
                "SELECT vendor_type_id, vendor_type, description FROM {$this->tableType} WHERE is_active ORDER BY vendor_type",
                'DB'
            );

            return ['success' => true, 'data' => $data ?: []];
        } catch (\Exception $e) {
            App\LogError('VendorService::fetchTypes ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load vendor types.'];
        }
    }

    /**
     * $page === null returns every match (dropdowns); otherwise paginates.
     * $activeOnly hides vendors whose status is not ACTIVE (a NULL status counts as active).
     */
    public function fetchAlls(?int $page = null, int $limit = 10, string $search = '', string $type = '', bool $activeOnly = false, string $status = ''): array
    {
        try {
            $where = ['1=1'];
            if ($status !== '') {
                $where[] = "COALESCE(v.status, 'ACTIVE') = " . $this->str(strtoupper($status));
            }
            if ($search !== '') {
                $s       = App\QuotedValue('%' . $search . '%', App\DataType::STRING, 'DB');
                $where[] = "(v.vendor_code ILIKE {$s} OR v.vendor_name ILIKE {$s} OR v.tin ILIKE {$s})";
            }
            if ($type !== '') {
                $where[] = 'v.vendor_type = ' . $this->str($type);
            }
            if ($activeOnly) {
                $where[] = "COALESCE(v.status, 'ACTIVE') = 'ACTIVE'";
            }
            $whereSql = implode(' AND ', $where);

            if ($page === null) {
                $data = App\ExecuteRows("SELECT v.* FROM {$this->table} v WHERE {$whereSql} ORDER BY v.vendor_name ASC", 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $limit  = max(1, $limit);
            $offset = (max(1, $page) - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} v WHERE {$whereSql}", 'DB');
            $data   = App\ExecuteRows(
                "SELECT v.* FROM {$this->table} v WHERE {$whereSql} ORDER BY v.vendor_name ASC LIMIT {$limit} OFFSET {$offset}",
                'DB'
            );

            // Counts over every row matching the filters (not just this page) for the KPI cards.
            $summary = App\ExecuteRow(
                "SELECT COUNT(*) FILTER (WHERE COALESCE(v.status, 'ACTIVE') = 'ACTIVE')  AS active,
                        COUNT(*) FILTER (WHERE COALESCE(v.status, 'ACTIVE') <> 'ACTIVE') AS inactive
                 FROM {$this->table} v WHERE {$whereSql}",
                'DB'
            );

            return [
                'success'    => true,
                'data'       => $data ?: [],
                'summary'    => $summary,
                'pagination' => [
                    'total'        => $total,
                    'per_page'     => $limit,
                    'current_page' => $page,
                    'last_page'    => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Exception $e) {
            App\LogError('VendorService::fetchAlls ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load vendors.'];
        }
    }

    public function fetchById(int|string $id): array
    {
        try {
            $id   = (int) $id;
            $data = App\ExecuteRow("SELECT * FROM {$this->table} WHERE vendor_id = {$id}", 'DB');

            return $data
                ? ['success' => true, 'data' => $data]
                : ['success' => false, 'message' => "Vendor #{$id} not found."];
        } catch (\Exception $e) {
            App\LogError('VendorService::fetchById ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to load vendor.'];
        }
    }

    public function create(array $body): array
    {
        try {
            $code = trim((string) ($body['vendor_code'] ?? ''));
            $name = trim((string) ($body['vendor_name'] ?? ''));
            if ($code === '' || $name === '') {
                return ['success' => false, 'message' => 'vendor_code and vendor_name are required.'];
            }

            $dup = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table} WHERE vendor_code = " . $this->str($code), 'DB');
            if ($dup > 0) {
                return ['success' => false, 'message' => "Vendor code '{$code}' already exists."];
            }
            if ($err = $this->validateType($body['vendor_type'] ?? null)) {
                return $err;
            }

            $columns = [];
            $values  = [];
            foreach ($this->textFields as $f) {
                if (!array_key_exists($f, $body) || trim((string) $body[$f]) === '') {
                    continue;
                }
                $columns[] = App\QuotedName($f, 'DB');
                $values[]  = $this->str($body[$f]);
            }
            if (array_key_exists('is_vat_registered', $body)) {
                $columns[] = 'is_vat_registered';
                $values[]  = filter_var($body['is_vat_registered'], FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE';
            }

            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (" . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ') RETURNING vendor_id',
                'DB'
            );

            return ['success' => true, 'message' => 'Vendor created successfully.', 'data' => ['vendor_id' => (int) $row['vendor_id']]];
        } catch (\Exception $e) {
            App\LogError('VendorService::create ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to create vendor.'];
        }
    }

    public function update(int|string $id, array $body): array
    {
        try {
            $id    = (int) $id;
            $check = $this->fetchById($id);
            if (!$check['success']) {
                return $check;
            }

            if (isset($body['vendor_code']) && trim((string) $body['vendor_code']) !== '') {
                $dup = (int) App\ExecuteScalar(
                    "SELECT COUNT(*) FROM {$this->table} WHERE vendor_code = " . $this->str($body['vendor_code']) . " AND vendor_id <> {$id}",
                    'DB'
                );
                if ($dup > 0) {
                    return ['success' => false, 'message' => "Vendor code '{$body['vendor_code']}' already exists."];
                }
            }
            if ($err = $this->validateType($body['vendor_type'] ?? null)) {
                return $err;
            }

            $set = [];
            foreach ($this->textFields as $f) {
                if (!array_key_exists($f, $body)) {
                    continue;
                }
                $isRequired = in_array($f, ['vendor_code', 'vendor_name'], true);
                $v          = trim((string) $body[$f]);
                if ($v === '' && $isRequired) {
                    return ['success' => false, 'message' => "{$f} cannot be empty."];
                }
                $set[] = App\QuotedName($f, 'DB') . ' = ' . ($v === '' ? 'NULL' : $this->str($v));
            }
            if (array_key_exists('is_vat_registered', $body)) {
                $set[] = 'is_vat_registered = ' . (filter_var($body['is_vat_registered'], FILTER_VALIDATE_BOOLEAN) ? 'TRUE' : 'FALSE');
            }
            if (!$set) {
                return ['success' => false, 'message' => 'No valid fields provided for update.'];
            }
            $set[] = 'updated_at = CURRENT_TIMESTAMP';

            App\Execute("UPDATE {$this->table} SET " . implode(', ', $set) . " WHERE vendor_id = {$id}", 'DB');

            return ['success' => true, 'message' => 'Vendor updated successfully.'];
        } catch (\Exception $e) {
            App\LogError('VendorService::update ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to update vendor.'];
        }
    }
}
