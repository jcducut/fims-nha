<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * DR (Delivery Receipt) Module — 1 PO number → many DR lines, in 1 table:
 *   fims.po_delivery_receipt  (see _assets/sql/01 - fims/01 - table/17 - fims_po_delivery_receipt.sql)
 *
 * A PO is addressed by its po_number (free text). Lines sharing a dr_number belong to the same delivery receipt.
 * Line: dr_number*, delivery_date*, description*, quantity* (whole), unit*, unit_price*, amount (= quantity x unit_price,
 * computed here), account_code (required from 50,000), serial_no (optional, unique when given), property_no / par_no (optional).
 *
 * 2 tables: fims.iar_module is the PO's IAR header (iar_number auto-generated, po_number, si_number, si_date);
 * every delivery receipt line carries that iar_number (fims.po_delivery_receipt.iar_number).
 *
 * Save payload:
 *   { po_number, si_number?, si_date?, lines: [ { dr_line_id?, dr_number, delivery_date, account_code?, description, quantity,
 *                                                  unit, unit_price, serial_no?, property_no?, par_no? } ] }
 */
class PoDrService
{
    private string $t = 'fims.po_delivery_receipt';   // delivery receipt lines
    private string $h = 'fims.iar_module';            // IAR header: po_number, iar_number, si_number, si_date

    /** Line amount (quantity x unit price) at or above which a Property & Equipment Category is required (below it the category is not used). */
    private const CATEGORY_MIN_PRICE = 50000;

    /** Pro-forma JEV accounts: expense for uncategorised items, accounts payable for the DR total. */
    private const EXPENSE_ACCOUNT = '5-02-03-990';
    private const PAYABLE_ACCOUNT = '2-01-01-010';

    /** Allowed Property & Equipment accounts: account code => name. Only the code is stored; keep in sync with dr-module-form.php. */
    private const ACCOUNTS = [
        '1-06-05-020' => 'Office Equipment',
        '1-06-05-030' => 'Information and Communication Technology Equipment',
        '1-06-05-070' => 'Communication Equipment',
        '1-06-05-080' => 'Construction and Heavy Equipment',
        '1-06-05-110' => 'Medical Equipment',
        '1-06-05-130' => 'Sports Equipment',
        '1-06-05-990' => 'Other Machinery and Equipment',
        '1-06-06-010' => 'Motor Vehicles',
        '1-06-07-010' => 'Furniture & Fixtures',
        '1-06-98-990' => 'Other Property, Plant and Equipment',
    ];

    private function q($value): string
    {
        return App\QuotedValue($value, DataType::STRING, 'DB');
    }

    private function nullable($value): ?string
    {
        $v = trim((string) ($value ?? ''));

        return $v === '' ? null : $v;
    }

    private function sqlOrNull(?string $value): string
    {
        return $value === null ? 'NULL' : $this->q($value);
    }

    private function idOrNull($v): ?int
    {
        return $v !== null && $v !== '' ? (int) $v : null;
    }

    private function validDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);

        return $dt && $dt->format('Y-m-d') === $d;
    }

    /** Validate + normalise. Returns [po_number, lines[], errorMessage]. */
    private function clean(array $body): array
    {
        $po = trim((string) ($body['po_number'] ?? ''));
        if ($po === '') {
            return [null, null, 'PO number is required.'];
        }
        if (mb_strlen($po) > 100) {
            return [null, null, 'PO number must be 100 characters or fewer.'];
        }

        $raw = $body['lines'] ?? [];
        if (!is_array($raw) || count($raw) === 0) {
            return [null, null, 'Add at least one delivery receipt line.'];
        }

        $lines   = [];
        $serials = [];
        foreach (array_values($raw) as $i => $ln) {
            $n    = $i + 1;
            $dr   = trim((string) ($ln['dr_number'] ?? ''));
            $dd   = trim((string) ($ln['delivery_date'] ?? ''));
            $desc = trim((string) ($ln['description'] ?? ''));
            $unit = trim((string) ($ln['unit'] ?? ''));
            $cat  = trim((string) ($ln['account_code'] ?? ''));
            $model = $this->nullable($ln['model_no'] ?? null);
            $ser  = $this->nullable($ln['serial_no'] ?? null);
            $prop = $this->nullable($ln['property_no'] ?? null);
            $par  = $this->nullable($ln['par_no'] ?? null);

            if ($dr === '')   return [null, null, "Line {$n}: delivery receipt no. is required."];
            if ($dd === '' || !$this->validDate($dd)) return [null, null, "Line {$n}: delivery date is required and must be a valid date."];
            if ($desc === '') return [null, null, "Line {$n}: description is required."];
            if ($unit === '') return [null, null, "Line {$n}: unit is required."];

            if (mb_strlen($dr) > 100 || ($model !== null && mb_strlen($model) > 100) || ($ser !== null && mb_strlen($ser) > 100)
                || ($prop !== null && mb_strlen($prop) > 100) || ($par !== null && mb_strlen($par) > 100)) {
                return [null, null, "Line {$n}: DR no., model no., serial no., property no. and PAR no. must be 100 characters or fewer."];
            }
            if (mb_strlen($unit) > 50) return [null, null, "Line {$n}: unit must be 50 characters or fewer."];

            $qtyRaw = str_replace(',', '', trim((string) ($ln['quantity'] ?? '')));
            if ($qtyRaw === '' || !ctype_digit($qtyRaw) || (int) $qtyRaw <= 0 || (int) $qtyRaw > 1000000000) {
                return [null, null, "Line {$n}: quantity is required and must be a whole number greater than 0."];
            }
            $priceRaw = str_replace(',', '', trim((string) ($ln['unit_price'] ?? '')));
            if ($priceRaw === '' || !is_numeric($priceRaw) || (float) $priceRaw < 0 || (float) $priceRaw >= 1e11) {
                return [null, null, "Line {$n}: unit price is required and must be a valid number."];
            }
            $quantity  = (string) (int) $qtyRaw;
            $unitPrice = number_format((float) $priceRaw, 2, '.', '');
            $amount    = number_format(round((float) $quantity * (float) $unitPrice, 2), 2, '.', '');

            // Property & Equipment Category applies only when the line amount is CATEGORY_MIN_PRICE or more
            if ((float) $amount >= self::CATEGORY_MIN_PRICE) {
                if (!isset(self::ACCOUNTS[$cat])) {
                    return [null, null, "Line {$n}: please choose a Property & Equipment Category (required when the amount is " . number_format(self::CATEGORY_MIN_PRICE) . ' or more).'];
                }
            } else {
                $cat = '';
            }

            if ($ser !== null) {
                if (isset($serials[mb_strtolower($ser)])) {
                    return [null, null, "Serial no. '{$ser}' is entered more than once."];
                }
                $serials[mb_strtolower($ser)] = true;
            }

            $lines[] = [
                'id'            => $this->idOrNull($ln['dr_line_id'] ?? null),
                'dr_number'     => $dr,
                'delivery_date' => $dd,
                'account_code'  => $cat === '' ? null : $cat,
                'description'   => $desc,
                'quantity'      => $quantity,
                'unit'          => $unit,
                'unit_price'    => $unitPrice,
                'amount'        => $amount,
                'model_no'      => $model,
                'serial_no'     => $ser,
                'property_no'   => $prop,
                'par_no'        => $par,
            ];
        }

        // Sales Invoice # / date belong to the PO's IAR header (fims.iar_module), not to the lines.
        $si  = $this->nullable($body['si_number'] ?? null);
        $sid = $this->nullable($body['si_date'] ?? null);
        if ($si !== null && mb_strlen($si) > 100) {
            return [null, null, 'Sales invoice no. must be 100 characters or fewer.'];
        }
        if ($sid !== null && !$this->validDate($sid)) {
            return [null, null, 'Sales invoice date must be a valid date.'];
        }

        return [$po, $lines, null, ['si_number' => $si, 'si_date' => $sid]];
    }

    /** Next IAR number for the current year: IAR-YYYY-0001, IAR-YYYY-0002, … */
    private function nextIarNumber(): string
    {
        $prefix = 'IAR-' . date('Y') . '-';
        $max    = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(iar_number FROM LENGTH({$this->q($prefix)}) + 1) AS INTEGER)), 0)
             FROM {$this->h} WHERE iar_number LIKE " . $this->q($prefix . '%'),
            'DB'
        );

        return $prefix . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Error message when a serial already belongs to another line, else null. */
    private function serialConflict(array $lines): ?string
    {
        foreach ($lines as $ln) {
            if ($ln['serial_no'] === null) {
                continue;
            }
            $sql = "SELECT COUNT(*) FROM {$this->t} WHERE serial_no = " . $this->q($ln['serial_no']);
            if ($ln['id'] !== null) {
                $sql .= ' AND dr_line_id <> ' . (int) $ln['id'];
            }
            if ((int) App\ExecuteScalar($sql, 'DB') > 0) {
                return "Serial no. '{$ln['serial_no']}' is already registered.";
            }
        }

        return null;
    }

    private function linesFor(array $poNumbers): array
    {
        if (!$poNumbers) {
            return [];
        }
        $in   = implode(',', array_map(fn($p) => $this->q($p), $poNumbers));
        $rows = App\ExecuteRows(
            "SELECT l.dr_line_id, l.po_number, l.iar_number, l.dr_number, TO_CHAR(l.delivery_date, 'YYYY-MM-DD') AS delivery_date,
                    l.description, l.account_code, l.quantity, l.unit, l.unit_price, l.amount,
                    i.si_number, TO_CHAR(i.si_date, 'YYYY-MM-DD') AS si_date,
                    l.model_no, l.serial_no, l.property_no, l.par_no
             FROM {$this->t} l JOIN {$this->h} i ON i.iar_number = l.iar_number
             WHERE l.po_number IN ({$in}) ORDER BY l.dr_line_id",
            'DB'
        ) ?: [];

        $by = [];
        foreach ($rows as $r) {
            $by[$r['po_number']][] = $r;
        }

        return $by;
    }

    /** One entry per PO number: { po_number, lines: [ … ] } (paginated). */
    public function fetchAlls(int $page = 1, int $limit = 10, string $search = ''): array
    {
        try {
            $where = '1=1';
            if ($search !== '') {
                $s     = $this->q('%' . $search . '%');
                $where = "(i.po_number ILIKE {$s} OR i.iar_number ILIKE {$s} OR i.si_number ILIKE {$s} OR EXISTS (
                              SELECT 1 FROM {$this->t} l WHERE l.iar_number = i.iar_number
                                AND (l.dr_number ILIKE {$s} OR l.description ILIKE {$s} OR l.account_code ILIKE {$s}
                                     OR l.model_no ILIKE {$s} OR l.serial_no ILIKE {$s} OR l.property_no ILIKE {$s} OR l.par_no ILIKE {$s})))";
            }

            $limit  = max(1, $limit);
            $page   = max(1, $page);
            $offset = ($page - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->h} i WHERE {$where}", 'DB');

            $pos = App\ExecuteRows(
                "SELECT i.po_number, i.iar_number, i.si_number, TO_CHAR(i.si_date, 'YYYY-MM-DD') AS si_date
                 FROM {$this->h} i WHERE {$where} ORDER BY i.iar_id DESC LIMIT {$limit} OFFSET {$offset}",
                'DB'
            ) ?: [];

            $lines = $this->linesFor(array_column($pos, 'po_number'));
            $data  = [];
            foreach ($pos as $p) {
                $data[] = [
                    'po_number'  => $p['po_number'],
                    'iar_number' => $p['iar_number'],
                    'si_number'  => $p['si_number'],
                    'si_date'    => $p['si_date'],
                    'lines'      => $lines[$p['po_number']] ?? [],
                ];
            }

            return [
                'success'    => true,
                'data'       => $data,
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

    public function fetchByPo(string $poNumber): array
    {
        try {
            $hdr = App\ExecuteRows(
                "SELECT iar_number, si_number, TO_CHAR(si_date, 'YYYY-MM-DD') AS si_date FROM {$this->h} WHERE po_number = " . $this->q($poNumber),
                'DB'
            );
            if (!$hdr) {
                return ['success' => false, 'message' => "PO '{$poNumber}' not found."];
            }
            $lines = $this->linesFor([$poNumber])[$poNumber] ?? [];

            return ['success' => true, 'data' => [
                'po_number'  => $poNumber,
                'iar_number' => $hdr[0]['iar_number'],
                'si_number'  => $hdr[0]['si_number'],
                'si_date'    => $hdr[0]['si_date'],
                'lines'      => $lines,
            ]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pro-forma JEV of every delivery receipt of a PO (one entry per DR number):
     *   Debit  5-02-03-990  lines that have no account code (expense)
     *   Debit  <account code> lines that have one (one row per code, e.g. 1-06-05-030, 1-06-05-020)
     *   Credit 2-01-01-010  the total amount of the delivery receipt (Accounts Payable)
     * Account titles come from fims.chart_of_accounts (falls back to the built-in names).
     */
    public function proforma(string $poNumber): array
    {
        try {
            $lines = $this->linesFor([$poNumber])[$poNumber] ?? [];
            if (!$lines) {
                return ['success' => false, 'message' => "PO '{$poNumber}' not found."];
            }

            $codes = array_merge([self::EXPENSE_ACCOUNT, self::PAYABLE_ACCOUNT], array_keys(self::ACCOUNTS));
            $in    = implode(',', array_map(fn($c) => $this->q($c), $codes));
            $titles = [];
            foreach (App\ExecuteRows("SELECT account_code, account_title FROM fims.chart_of_accounts WHERE account_code IN ({$in})", 'DB') ?: [] as $r) {
                $titles[$r['account_code']] = $r['account_title'];
            }
            $titleOf = fn(string $code) => $titles[$code]
                ?? self::ACCOUNTS[$code]
                ?? ($code === self::EXPENSE_ACCOUNT ? 'Other Supplies and Materials Expenses' : 'Accounts Payable');

            $byDr = [];
            foreach ($lines as $ln) {
                $byDr[$ln['dr_number']][] = $ln;
            }

            $receipts = [];
            foreach ($byDr as $dr => $drLines) {
                // The GL amount of each account is the sum of its subsidiary-ledger items (the DR's item lines).
                $noCode  = [];
                $perCode = [];
                $all     = [];
                $total   = 0.0;
                foreach ($drLines as $ln) {
                    $amt  = (float) $ln['amount'];
                    $item = ['description' => $ln['description'], 'amount' => round($amt, 2)];
                    $total += $amt;
                    $all[] = $item;
                    if (($ln['account_code'] ?? '') === '' || $ln['account_code'] === null) {
                        $noCode[] = $item;
                    } else {
                        $perCode[$ln['account_code']][] = $item;
                    }
                }
                ksort($perCode);
                $sum = fn(array $items) => round(array_sum(array_column($items, 'amount')), 2);

                $rows = [];
                if ($noCode) {
                    $rows[] = ['account_title' => $titleOf(self::EXPENSE_ACCOUNT), 'account_code' => self::EXPENSE_ACCOUNT, 'debit' => $sum($noCode), 'credit' => 0, 'items' => $noCode];
                }
                foreach ($perCode as $code => $items) {
                    $rows[] = ['account_title' => $titleOf($code), 'account_code' => $code, 'debit' => $sum($items), 'credit' => 0, 'items' => $items];
                }
                $rows[] = ['account_title' => $titleOf(self::PAYABLE_ACCOUNT), 'account_code' => self::PAYABLE_ACCOUNT, 'debit' => 0, 'credit' => round($total, 2), 'items' => $all];

                $receipts[] = ['dr_number' => (string) $dr, 'rows' => $rows, 'total' => round($total, 2)];
            }

            return ['success' => true, 'data' => ['po_number' => $poNumber, 'receipts' => $receipts]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function create(array $body, ?int $userId = null): array
    {
        try {
            [$po, $lines, $err, $hdr] = $this->clean($body);
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            // Many DRs can share one PO number: adding to an existing PO number simply appends the new lines to it.
            $existing = App\ExecuteRows("SELECT iar_number FROM {$this->h} WHERE po_number = " . $this->q($po), 'DB');
            $existed  = !empty($existing);
            foreach ($lines as &$ln) {            // everything is new on create
                $ln['id'] = null;
            }
            unset($ln);
            if ($msg = $this->serialConflict($lines)) {
                return ['success' => false, 'message' => $msg];
            }

            $by = $userId ? (int) $userId : 'NULL';
            App\Execute('BEGIN', 'DB');
            try {
                if ($existed) {
                    // the PO already has its IAR: keep the IAR number, only fill in the invoice details that were given
                    $iar = $existing[0]['iar_number'];
                    $set = [];
                    if ($hdr['si_number'] !== null) $set[] = 'si_number = ' . $this->q($hdr['si_number']);
                    if ($hdr['si_date'] !== null)   $set[] = 'si_date = ' . $this->q($hdr['si_date']);
                    if ($set) {
                        App\Execute("UPDATE {$this->h} SET " . implode(', ', $set) . ", updated_by = {$by}, updated_at = CURRENT_TIMESTAMP WHERE iar_number = " . $this->q($iar), 'DB');
                    }
                } else {
                    $iar = $this->nextIarNumber();
                    App\Execute(
                        "INSERT INTO {$this->h} (iar_number, po_number, si_number, si_date, created_by) VALUES ("
                            . $this->q($iar) . ', ' . $this->q($po) . ', ' . $this->sqlOrNull($hdr['si_number']) . ', '
                            . $this->sqlOrNull($hdr['si_date']) . ", {$by})",
                        'DB'
                    );
                }
                foreach ($lines as $ln) {
                    $this->insertLine($po, $iar, $ln, $userId);   // every delivery receipt line carries the PO's IAR number
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => $existed ? "Delivery receipt lines added to existing PO {$po} ({$iar})." : "PO {$po} added successfully ({$iar})."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Saves the whole PO (addressed by its current po_number). Lines carrying an id are updated, those without
     * are inserted, existing ones missing from the payload are deleted. The PO number itself may be renamed.
     */
    public function update(string $oldPo, array $body, ?int $userId = null): array
    {
        try {
            $check = $this->fetchByPo($oldPo);
            if (!$check['success']) {
                return $check;
            }

            [$po, $lines, $err, $hdr] = $this->clean($body);
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            $iar = $check['data']['iar_number'];
            if ($po !== $oldPo && (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->h} WHERE po_number = " . $this->q($po), 'DB') > 0) {
                return ['success' => false, 'message' => "PO number '{$po}' already exists."];
            }

            // ids that don't belong to this PO are treated as new lines
            $owned = array_map('intval', array_column($check['data']['lines'], 'dr_line_id'));
            foreach ($lines as &$ln) {
                if ($ln['id'] !== null && !in_array($ln['id'], $owned, true)) {
                    $ln['id'] = null;
                }
            }
            unset($ln);

            if ($msg = $this->serialConflict($lines)) {
                return ['success' => false, 'message' => $msg];
            }

            $keep = [];
            foreach ($lines as $ln) {
                if ($ln['id'] !== null) {
                    $keep[] = $ln['id'];
                }
            }

            App\Execute('BEGIN', 'DB');
            try {
                // 0) the IAR header: PO number + invoice details (the IAR number itself never changes)
                $hby = $userId ? (int) $userId : 'NULL';
                App\Execute("UPDATE {$this->h} SET po_number = " . $this->q($po) . ', si_number = ' . $this->sqlOrNull($hdr['si_number'])
                    . ', si_date = ' . $this->sqlOrNull($hdr['si_date']) . ", updated_by = {$hby}, updated_at = CURRENT_TIMESTAMP WHERE iar_number = " . $this->q($iar), 'DB');

                // 1) delete lines removed in the form
                $del = "DELETE FROM {$this->t} WHERE iar_number = " . $this->q($iar);
                if ($keep) {
                    $del .= ' AND dr_line_id NOT IN (' . implode(',', $keep) . ')';
                }
                App\Execute($del, 'DB');

                // 2) park kept lines on temporary serials so swapping serials can't trip the unique key
                foreach ($keep as $id) {
                    App\Execute("UPDATE {$this->t} SET serial_no = " . $this->q('__tmp_' . $id . '_' . uniqid()) . ' WHERE dr_line_id = ' . (int) $id, 'DB');
                }

                // 3) final values
                $by = $userId ? (int) $userId : 'NULL';
                foreach ($lines as $ln) {
                    if ($ln['id'] !== null) {
                        App\Execute("UPDATE {$this->t} SET
                                po_number = " . $this->q($po) . ',
                                dr_number = ' . $this->q($ln['dr_number']) . ',
                                delivery_date = ' . $this->q($ln['delivery_date']) . ',
                                account_code = ' . $this->sqlOrNull($ln['account_code']) . ',
                                description = ' . $this->q($ln['description']) . ',
                                quantity = ' . $ln['quantity'] . ',
                                unit = ' . $this->q($ln['unit']) . ',
                                unit_price = ' . $ln['unit_price'] . ',
                                amount = ' . $ln['amount'] . ',
                                model_no = ' . $this->sqlOrNull($ln['model_no']) . ',
                                serial_no = ' . $this->sqlOrNull($ln['serial_no']) . ',
                                property_no = ' . $this->sqlOrNull($ln['property_no']) . ',
                                par_no = ' . $this->sqlOrNull($ln['par_no']) . ",
                                updated_by = {$by}, updated_at = CURRENT_TIMESTAMP
                            WHERE dr_line_id = " . (int) $ln['id'], 'DB');
                    } else {
                        $this->insertLine($po, $iar, $ln, $userId);
                    }
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => "PO {$po} updated successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    private function insertLine(string $po, string $iar, array $ln, ?int $userId): void
    {
        $by = $userId ? (int) $userId : 'NULL';
        App\Execute(
            "INSERT INTO {$this->t} (po_number, iar_number, dr_number, delivery_date, account_code, description, quantity, unit, unit_price, amount,
                                     model_no, serial_no, property_no, par_no, created_by)
             VALUES (" . $this->q($po) . ', ' . $this->q($iar) . ', ' . $this->q($ln['dr_number']) . ', ' . $this->q($ln['delivery_date']) . ', '
                . $this->sqlOrNull($ln['account_code']) . ', ' . $this->q($ln['description']) . ', ' . $ln['quantity'] . ', ' . $this->q($ln['unit']) . ', '
                . $ln['unit_price'] . ', ' . $ln['amount'] . ', '
                . $this->sqlOrNull($ln['model_no']) . ', ' . $this->sqlOrNull($ln['serial_no']) . ', ' . $this->sqlOrNull($ln['property_no']) . ', '
                . $this->sqlOrNull($ln['par_no']) . ", {$by})",
            'DB'
        );
    }

    /** Deletes the PO number with all its delivery receipt lines. */
    public function delete(string $poNumber): array
    {
        try {
            $check = $this->fetchByPo($poNumber);
            if (!$check['success']) {
                return $check;
            }
            App\Execute("DELETE FROM {$this->t} WHERE iar_number = " . $this->q($check['data']['iar_number']), 'DB');
            App\Execute("DELETE FROM {$this->h} WHERE iar_number = " . $this->q($check['data']['iar_number']), 'DB');

            return ['success' => true, 'message' => "PO {$poNumber} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
