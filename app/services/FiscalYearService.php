<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

class FiscalYearService
{
    private string $table = 'fims.fiscal_year';

    public function fetchAlls(?int $page = null, int $limit = 10): array
    {
        try {
            if ($page === null) {
                $sql  = "SELECT * FROM {$this->table} ORDER BY fiscal_yr DESC";
                $data = App\ExecuteRows($sql, 'DB');

                return ['success' => true, 'data' => $data ?: []];
            }

            $offset = ($page - 1) * $limit;

            $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->table}", 'DB');
            $sql   = "SELECT * FROM {$this->table}
                      ORDER BY fiscal_yr DESC
                      LIMIT {$limit} OFFSET {$offset}";
            $data  = App\ExecuteRows($sql, 'DB');

            return [
                'success'    => true,
                'data'       => $data ?: [],
                'pagination' => [
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

    public function fetchByYear(int|string $fiscalYr): array
    {
        try {
            $sql  = "SELECT * FROM {$this->table} WHERE fiscal_yr = " . App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB');
            $data = App\ExecuteRow($sql, 'DB');

            if (!$data) {
                return ['success' => false, 'message' => "Fiscal year '{$fiscalYr}' not found."];
            }

            return ['success' => true, 'data' => $data];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body): array
    {
        try {
            $fiscalYr = trim((string) ($body['fiscal_yr'] ?? ''));

            if ($fiscalYr === '' || !is_numeric($fiscalYr)) {
                return ['success' => false, 'message' => 'A numeric fiscal_yr is required.'];
            }

            $exists = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} WHERE fiscal_yr = " . App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB'),
                'DB'
            );
            if ($exists > 0) {
                return ['success' => false, 'message' => "Fiscal year '{$fiscalYr}' already exists."];
            }

            $sql = "INSERT INTO {$this->table} (fiscal_yr) VALUES (" . App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB') . ')';
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Fiscal year '{$fiscalYr}' created successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function delete(int|string $fiscalYr): array
    {
        try {
            $check = $this->fetchByYear($fiscalYr);
            if (!$check['success']) {
                return $check;
            }

            $inUse = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM fims.budget_req WHERE fiscal_yr = " . App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB'),
                'DB'
            );
            if ($inUse > 0) {
                return ['success' => false, 'message' => "Cannot delete '{$fiscalYr}' — {$inUse} budget request(s) still reference this fiscal year."];
            }

            $sql = "DELETE FROM {$this->table} WHERE fiscal_yr = " . App\QuotedValue($fiscalYr, DataType::NUMBER, 'DB');
            App\Execute($sql, 'DB');

            return ['success' => true, 'message' => "Fiscal year '{$fiscalYr}' deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
