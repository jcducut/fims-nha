<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * Backs the Cash Position Updates tab's SOA (bank Statement of Account)
 * upload flow — a faithful port of TREASURY's CashPosUpServices.php SOA
 * methods (uploadSOA/confirmUpload/_parseXlsx/getSoaComparison/
 * getSoaGroups/getSoaTransactions/getLatestSoaBalance), including its
 * pure-PHP xlsx parser (ZipArchive + SimpleXML, no PhpSpreadsheet —
 * ported verbatim since this project has no Excel library installed
 * either).
 *
 * Deliberately NOT ported (TREASURY itself never implemented these, or
 * they depend on subsystems out of scope — see CashPositionService's
 * docblock):
 *   - Batch upload across multiple bank accounts in one modal — it's a
 *     client-side loop over the same single-account parse/confirm flow,
 *     so there is nothing additional to port server-side.
 *   - Auto-sync/force-sync toggle — needs a Collections/FTLA ledger this
 *     project doesn't have.
 *   - Download Template / Export — TREASURY's own implementation is an
 *     empty stub (HTTP headers only, body literally empty, marked
 *     "// TODO: generate with PhpSpreadsheet" in the source) — there is
 *     no real logic to port.
 *
 * Staging between upload (parse) and confirm (save) is entirely
 * client-side, same as TREASURY: the browser holds the parsed rows in
 * memory and POSTs them back verbatim to confirmUpload() — the server
 * never stages anything in a session or temp table.
 */
class CashPositionSoaService
{
    private string $tableSoaTransactions = 'fims.soa_transactions';
    private string $tableBankTransactions = 'fims.bank_transactions';
    private string $tableCashPosition = 'fims.cash_position';
    private string $tableBankAccounts = 'fims.bank_accounts';

    private function str(mixed $v): string
    {
        return App\QuotedValue(trim((string) $v), App\DataType::STRING, 'DB');
    }

    private function strOrNull(mixed $v): string
    {
        $v = trim((string) ($v ?? ''));

        return $v === '' ? 'NULL' : $this->str($v);
    }

    private function num(mixed $v): float
    {
        return is_numeric($v) ? (float) $v : 0.0;
    }

    private function ok($data = [])
    {
        return json_encode(array_merge(["success" => true], $data));
    }

    // ══════════════════════════════════════════════
    // Pure-PHP xlsx parser — ported from TREASURY's _parseXlsx(), no
    // PhpSpreadsheet dependency. Reads shared strings + the first
    // worksheet via ZipArchive + SimpleXML, header-detects columns so
    // field order in the bank's export doesn't matter.
    // ══════════════════════════════════════════════

    private function colLetterToIndex(string $col): int
    {
        $index = 0;
        foreach (str_split($col) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }

        return $index - 1;
    }

    private function toFloat(string $value): float
    {
        $clean = preg_replace('/[^\d.\-]/', '', $value);

        return $clean !== '' ? (float) $clean : 0.0;
    }

    private function excelDateToComponents(string $value): array
    {
        $none = ['date' => null, 'time' => null];
        if ($value === '') {
            return $none;
        }

        if (!is_numeric($value)) {
            $formats = ['n/j/Y H:i', 'n/j/Y H:i:s', 'n/j/y H:i', 'n/j/y H:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i', 'n/j/Y', 'n/j/y', 'Y-m-d'];
            $dt = null;
            foreach ($formats as $fmt) {
                $dt = \DateTime::createFromFormat($fmt, trim($value));
                if ($dt) {
                    break;
                }
            }
            if (!$dt) {
                $ts = strtotime($value);
                if (!$ts) {
                    return $none;
                }
                $dt = new \DateTime('@' . $ts);
            }
            $t = $dt->format('H:i:s');

            return ['date' => $dt->format('Y-m-d'), 'time' => $t !== '00:00:00' ? $t : null];
        }

        $serial = (float) $value;
        if ($serial < 1) {
            return $none;
        }
        if ($serial >= 60) {
            $serial--;
        }
        $dayPart = (int) floor($serial);
        $timeFrac = $serial - $dayPart;
        $timeSec = (int) round($timeFrac * 86400);
        $timeStr = $timeSec > 0
            ? sprintf('%02d:%02d:%02d', intdiv($timeSec, 3600), intdiv($timeSec % 3600, 60), $timeSec % 60)
            : null;
        $ts = mktime(0, 0, 0, 1, 1, 1900) + ($dayPart - 1) * 86400;

        return ['date' => date('Y-m-d', $ts), 'time' => $timeStr];
    }

    private function parseXlsx(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \Exception('Cannot open uploaded file. Make sure it is a valid .xlsx file.');
        }

        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ss = simplexml_load_string($ssXml);
            foreach ($ss->si as $si) {
                $text = '';
                foreach ($si->r ?? [$si] as $node) {
                    $t = isset($node->t) ? (string) $node->t : (string) $si->t;
                    $text .= $t;
                }
                $sharedStrings[] = $text;
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        if (!$sheetXml) {
            throw new \Exception('Cannot read worksheet. The file may be corrupted or use an unsupported format.');
        }

        $sheet = simplexml_load_string($sheetXml);
        $colMap = [];
        $rows = [];
        $isFirst = true;

        foreach ($sheet->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $cell) {
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);
                $col = $this->colLetterToIndex($m[1]);
                $type = (string) $cell['t'];
                $value = (string) $cell->v;
                if ($type === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = isset($cell->is->t) ? (string) $cell->is->t : $value;
                }
                $cells[$col] = $value;
            }

            if ($isFirst) {
                foreach ($cells as $idx => $header) {
                    $key = strtolower(trim(preg_replace('/\s+/', ' ', $header)));
                    $colMap[$key] = $idx;
                }
                $isFirst = false;
                continue;
            }
            if (empty(array_filter($cells, fn ($v) => $v !== ''))) {
                continue;
            }

            $iDate = $colMap['date'] ?? $colMap['transaction date'] ?? 0;
            $iDesc = $colMap['description'] ?? $colMap['particulars'] ?? 1;
            $iDebit = $colMap['debit'] ?? $colMap['withdrawal'] ?? 2;
            $iCredit = $colMap['credit'] ?? $colMap['deposit'] ?? 3;
            $iBalance = $colMap['balance'] ?? $colMap['running balance'] ?? 4;
            $iBranch = $colMap['branch'] ?? $colMap['branch name'] ?? 5;
            $iCheckNo = $colMap['check no'] ?? $colMap['check number'] ?? $colMap['check no.'] ?? $colMap['chk no'] ?? 6;
            $iOther = $colMap['other details'] ?? $colMap['remarks'] ?? $colMap['reference'] ?? 7;

            $rawDate = $cells[$iDate] ?? '';
            $dtParts = $rawDate !== '' ? $this->excelDateToComponents($rawDate) : ['date' => null, 'time' => null];
            $date = $dtParts['date'];
            if (!$date) {
                continue;
            }

            $iTime = $colMap['time'] ?? $colMap['transaction time'] ?? null;
            $rawTime = $iTime !== null ? trim($cells[$iTime] ?? '') : '';
            $txnTime = $dtParts['time'];
            if ($rawTime !== '' && $txnTime === null) {
                $ts2 = strtotime($rawTime);
                if ($ts2) {
                    $txnTime = date('H:i:s', $ts2);
                }
            }

            $rawCheck = trim($cells[$iCheckNo] ?? '');
            if (is_numeric($rawCheck) && str_contains($rawCheck, '.')) {
                $rawCheck = (string) (int) ((float) $rawCheck);
            }

            $rows[] = [
                'date' => $date,
                'time' => $txnTime,
                'description' => trim($cells[$iDesc] ?? ''),
                'debit' => $this->toFloat($cells[$iDebit] ?? ''),
                'credit' => $this->toFloat($cells[$iCredit] ?? ''),
                'balance' => $this->toFloat($cells[$iBalance] ?? ''),
                'branch' => trim($cells[$iBranch] ?? ''),
                'check_no' => $rawCheck,
                'other_details' => trim($cells[$iOther] ?? ''),
            ];
        }

        return $rows;
    }

    // ══════════════════════════════════════════════
    // Upload (parse + preview, no DB writes)
    // ══════════════════════════════════════════════

    private function dupKey(array $row): string
    {
        return implode('|', [
            $row['date'] ?? '',
            strtolower(trim($row['description'] ?? '')),
            number_format($this->num($row['debit'] ?? 0), 2, '.', ''),
            number_format($this->num($row['credit'] ?? 0), 2, '.', ''),
            strtolower(trim($row['check_no'] ?? '')),
        ]);
    }

    private function findDuplicateKeys(array $rows, int $bankAccountId): array
    {
        if (empty($rows)) {
            return [];
        }
        $dates = array_filter(array_column($rows, 'date'));
        if (empty($dates)) {
            return [];
        }
        $minDate = min($dates);
        $maxDate = max($dates);
        $existing = App\ExecuteRowsAssociative(
            "SELECT transaction_date, description, debit_amount, credit_amount, check_no
             FROM {$this->tableSoaTransactions}
             WHERE bank_account_id = {$bankAccountId} AND transaction_date BETWEEN " . $this->str($minDate) . ' AND ' . $this->str($maxDate),
            'DB'
        );
        $keys = [];
        foreach ($existing as $row) {
            $key = $this->dupKey([
                'date' => $row['transaction_date'], 'description' => $row['description'],
                'debit' => $row['debit_amount'], 'credit' => $row['credit_amount'], 'check_no' => $row['check_no'],
            ]);
            $keys[$key] = true;
        }

        return $keys;
    }

    private function mapBankTxnType(array $row): array
    {
        $desc = strtoupper(trim($row['description'] ?? ''));
        $debit = $this->num($row['debit'] ?? 0);
        $credit = $this->num($row['credit'] ?? 0);
        $checkNo = trim($row['check_no'] ?? '');

        if (str_contains($desc, 'DMTRA') || str_contains($desc, 'CMTRA')) {
            return ['type' => 'FTLA', 'direction' => 'OUTFLOW', 'amount' => $debit];
        }
        if (str_contains($desc, 'TAX WITHHELD')) {
            return ['type' => 'INTEREST', 'direction' => 'OUTFLOW', 'amount' => $debit];
        }
        if (str_contains($desc, 'INTEREST')) {
            return ['type' => 'INTEREST', 'direction' => 'INFLOW', 'amount' => $credit];
        }
        if ($checkNo !== '' || str_contains($desc, 'CHECK') || str_contains($desc, 'DONUS')) {
            return ['type' => 'CHECK', 'direction' => 'OUTFLOW', 'amount' => $debit > 0 ? $debit : $credit];
        }
        if ($credit > 0) {
            return ['type' => 'COLLECTION', 'direction' => 'INFLOW', 'amount' => $credit];
        }

        return ['type' => 'FTLA', 'direction' => 'OUTFLOW', 'amount' => $debit];
    }

    private function bankTxnId(array $row, array $mapped): string
    {
        $date = $row['date'] ?? '';
        $time = $row['time'] ?? '23:59:59';
        $stamp = date('Ymd-His', strtotime("{$date} {$time}"));
        $hash = strtoupper(substr(md5(implode('|', [
            $date, $time, $mapped['type'], $mapped['direction'],
            number_format($mapped['amount'], 2, '.', ''),
            strtolower(trim($row['description'] ?? '')),
            strtolower(trim($row['check_no'] ?? '')),
        ])), 0, 8));

        return "SOA-{$stamp}-{$hash}";
    }

    private function findProcessedBankTxnIds(array $rows, int $bankAccountId): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $mapped = $this->mapBankTxnType($row);
            $ids[$this->bankTxnId($row, $mapped)] = true;
        }
        if (empty($ids)) {
            return [];
        }
        $idList = implode(', ', array_map(fn ($id) => $this->str($id), array_keys($ids)));
        $existing = App\ExecuteRowsAssociative(
            "SELECT transaction_id FROM {$this->tableBankTransactions} WHERE bank_account_id = {$bankAccountId} AND transaction_id IN ({$idList})",
            'DB'
        );
        $found = [];
        foreach ($existing as $row) {
            $found[$row['transaction_id']] = true;
        }

        return $found;
    }

    public function uploadSoa($uploadedFile, $data)
    {
        try {
            if (!$uploadedFile) {
                return ['success' => false, 'message' => 'No SOA file uploaded.'];
            }
            if (empty($data['bank_account_id'])) {
                return ['success' => false, 'message' => 'Bank account is required.'];
            }
            if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'Upload failed (error code ' . $uploadedFile->getError() . ').'];
            }

            $fileName = $uploadedFile->getClientFilename() ?: 'soa.xlsx';
            $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            if ($ext !== 'xlsx') {
                return ['success' => false, 'message' => 'Only .xlsx files are supported. Please save the file as Excel Workbook (.xlsx).'];
            }

            $tmpPath = $uploadedFile->getStream()->getMetadata('uri');
            $parsedRows = $this->parseXlsx($tmpPath);
            $bankAccountId = (int) $data['bank_account_id'];

            $dupKeys = $this->findDuplicateKeys($parsedRows, $bankAccountId);
            $processedIds = $this->findProcessedBankTxnIds($parsedRows, $bankAccountId);
            foreach ($parsedRows as &$row) {
                $row['is_duplicate'] = isset($dupKeys[$this->dupKey($row)]);
                $mapped = $this->mapBankTxnType($row);
                $row['is_processed'] = isset($processedIds[$this->bankTxnId($row, $mapped)]);
            }
            unset($row);

            $newCount = count(array_filter($parsedRows, fn ($r) => empty($r['is_duplicate'])));
            $dupCount = count($parsedRows) - $newCount;
            $processedCount = count(array_filter($parsedRows, fn ($r) => !empty($r['is_processed'])));

            return [
                'success' => true,
                'message' => "Parsed " . count($parsedRows) . " row(s): {$newCount} new, {$dupCount} duplicate(s), {$processedCount} already processed.",
                'parsed_rows' => $parsedRows,
                'new_count' => $newCount,
                'duplicate_count' => $dupCount,
                'processed_count' => $processedCount,
                'bank_account_id' => $data['bank_account_id'],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ══════════════════════════════════════════════
    // Confirm (the actual DB writes — parsed_rows is held client-side
    // between upload and confirm, same as TREASURY)
    // ══════════════════════════════════════════════

    public function confirmUpload($data)
    {
        try {
            if (empty($data['parsed_rows']) || empty($data['bank_account_id'])) {
                return json_encode(["success" => false, "message" => "Missing required upload data."]);
            }
            $bankAccountId = (int) $data['bank_account_id'];
            $userId = (int) App\CurrentUserID();
            $rows = $data['parsed_rows'];

            $dupKeys = $this->findDuplicateKeys($rows, $bankAccountId);
            $newRows = array_values(array_filter($rows, fn ($r) => !isset($dupKeys[$this->dupKey($r)])));
            $skipped = count($rows) - count($newRows);

            $batchId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            $lineCount = 0;
            foreach ($newRows as $row) {
                if (empty($row['date'])) {
                    continue;
                }
                App\Execute(
                    "INSERT INTO {$this->tableSoaTransactions}
                        (bank_account_id, transaction_date, transaction_time, description, debit_amount, credit_amount,
                         balance, branch, check_no, other_details, uploaded_by, upload_batch_id)
                     VALUES ({$bankAccountId}, " . $this->str($row['date']) . ', ' . $this->strOrNull($row['time'] ?? null) . '::time, '
                        . $this->strOrNull($row['description'] ?? null) . ', ' . $this->num($row['debit'] ?? 0) . ', ' . $this->num($row['credit'] ?? 0) . ', '
                        . (isset($row['balance']) && $row['balance'] !== '' ? $this->num($row['balance']) : 'NULL') . ', '
                        . $this->strOrNull($row['branch'] ?? null) . ', ' . $this->strOrNull($row['check_no'] ?? null) . ', '
                        . $this->strOrNull($row['other_details'] ?? null) . ", {$userId}, " . $this->str($batchId) . '::uuid)',
                    'DB'
                );
                $lineCount++;
            }

            $bankTxnSaved = 0;
            $bankTxnProcessed = 0;
            foreach ($newRows as $row) {
                if (empty($row['date'])) {
                    continue;
                }
                $mapped = $this->mapBankTxnType($row);
                if ($mapped['amount'] <= 0) {
                    continue;
                }
                $txnId = $this->bankTxnId($row, $mapped);
                $checkNo = trim($row['check_no'] ?? '');
                $referenceNumber = $checkNo !== '' ? $checkNo : null;
                $sourceTs = trim(($row['date'] ?? '') . ' ' . ($row['time'] ?? ''));
                $inserted = App\ExecuteRow(
                    "INSERT INTO {$this->tableBankTransactions}
                        (transaction_type, transaction_id, reference_number, transaction_date, bank_account_id,
                         amount, description, status, counterpart, direction, source_created_at)
                     VALUES (" . $this->str($mapped['type']) . ', ' . $this->str($txnId) . ', ' . $this->strOrNull($referenceNumber) . ', '
                        . $this->str($row['date']) . ", {$bankAccountId}, " . $mapped['amount'] . ', '
                        . $this->strOrNull($row['description'] ?? null) . ", 'VALIDATED', " . $this->strOrNull($row['branch'] ?? null) . ', '
                        . $this->str($mapped['direction']) . ', ' . $this->str($sourceTs) . "::timestamp)
                     ON CONFLICT (transaction_id) DO NOTHING
                     RETURNING transaction_id",
                    'DB'
                );
                if ($inserted) {
                    $bankTxnSaved++;
                } else {
                    $bankTxnProcessed++;
                }
            }

            $positionsUpdated = $this->syncCashPositionFromSoa($newRows, $bankAccountId, $userId);

            return json_encode([
                "success" => true,
                "message" => "Saved {$lineCount} SOA line(s), skipped {$skipped} duplicate(s). Posted {$bankTxnSaved} ledger entries ({$bankTxnProcessed} already existed). Synced {$positionsUpdated} day(s) of cash position.",
                "saved" => $lineCount,
                "skipped" => $skipped,
                "bank_txn_saved" => $bankTxnSaved,
                "bank_txn_processed" => $bankTxnProcessed,
                "positions_synced" => $positionsUpdated,
            ]);
        } catch (\Exception $e) {
            return json_encode(["success" => false, "message" => $e->getMessage()]);
        }
    }

    // Recomputes each touched date's cash_position snapshot from the real
    // fims.bank_transactions ledger (not just this batch), same as
    // TREASURY's _syncCashPositionFromSoa().
    private function syncCashPositionFromSoa(array $rows, int $bankAccountId, int $userId): int
    {
        $dates = array_unique(array_filter(array_column($rows, 'date')));
        sort($dates);
        $updated = 0;

        foreach ($dates as $date) {
            $prior = App\ExecuteRow(
                "SELECT closing_balance FROM {$this->tableCashPosition}
                 WHERE bank_account_id = {$bankAccountId} AND position_date < " . $this->str($date) . '
                 ORDER BY position_date DESC LIMIT 1',
                'DB'
            );
            $dayRows = array_values(array_filter($rows, fn ($r) => ($r['date'] ?? null) === $date));
            if ($prior) {
                $opening = (float) $prior['closing_balance'];
            } else {
                $first = $dayRows[0] ?? null;
                $opening = $first ? ($this->num($first['balance'] ?? 0) - $this->num($first['credit'] ?? 0) + $this->num($first['debit'] ?? 0)) : 0.0;
            }

            $sums = App\ExecuteRow(
                "SELECT COALESCE(SUM(amount) FILTER (WHERE direction = 'INFLOW'), 0) AS receipts,
                        COALESCE(SUM(amount) FILTER (WHERE direction != 'INFLOW'), 0) AS disbursements
                 FROM {$this->tableBankTransactions}
                 WHERE bank_account_id = {$bankAccountId} AND transaction_date = " . $this->str($date),
                'DB'
            );
            $receipts = $this->num($sums['receipts'] ?? 0);
            $disbursements = $this->num($sums['disbursements'] ?? 0);
            $closing = $opening + $receipts - $disbursements;

            $lastBalance = null;
            foreach ($dayRows as $row) {
                if (isset($row['balance']) && $row['balance'] !== '' && $row['balance'] !== null) {
                    $lastBalance = $this->num($row['balance']);
                }
            }
            $reconciled = $lastBalance !== null && abs($closing - $lastBalance) < 0.01;

            $existing = App\ExecuteRow(
                "SELECT cash_position_id FROM {$this->tableCashPosition} WHERE bank_account_id = {$bankAccountId} AND position_date = " . $this->str($date),
                'DB'
            );
            if ($existing) {
                App\Execute(
                    "UPDATE {$this->tableCashPosition} SET
                        opening_balance = {$opening}, total_receipts = {$receipts}, total_disbursements = {$disbursements},
                        closing_balance = {$closing},
                        bank_statement_balance = " . ($lastBalance !== null ? $lastBalance : 'NULL') . ",
                        reconciled = " . ($reconciled ? 'TRUE' : 'FALSE') . "
                     WHERE cash_position_id = {$existing['cash_position_id']}",
                    'DB'
                );
            } else {
                App\Execute(
                    "INSERT INTO {$this->tableCashPosition}
                        (bank_account_id, position_date, opening_balance, total_receipts, total_disbursements, closing_balance,
                         bank_statement_balance, reconciled, restricted_amount, created_by)
                     VALUES ({$bankAccountId}, " . $this->str($date) . ", {$opening}, {$receipts}, {$disbursements}, {$closing}, "
                        . ($lastBalance !== null ? $lastBalance : 'NULL') . ', ' . ($reconciled ? 'TRUE' : 'FALSE') . ", 0, {$userId})",
                    'DB'
                );
            }
            $updated++;
        }

        return $updated;
    }

    // ══════════════════════════════════════════════
    // Reads: latest SOA balance, comparison, groups, transactions
    // ══════════════════════════════════════════════

    // "Latest" = the first-inserted row of the most-recently-created
    // upload batch — same convention TREASURY uses everywhere it surfaces
    // "the" SOA balance for an account.
    public function getLatestSoaBalance($bankAccountId, $asOf = null)
    {
        $bankAccountId = (int) $bankAccountId;
        $asOfFilter = trim((string) $asOf) !== '' ? ('AND transaction_date <= ' . $this->str($asOf)) : '';

        $row = App\ExecuteRow(
            "SELECT st.balance, st.transaction_date
             FROM {$this->tableSoaTransactions} st
             WHERE st.bank_account_id = {$bankAccountId} AND st.balance IS NOT NULL {$asOfFilter}
               AND st.upload_batch_id = (
                   SELECT s2.upload_batch_id FROM {$this->tableSoaTransactions} s2
                   WHERE s2.bank_account_id = {$bankAccountId} AND s2.upload_batch_id IS NOT NULL {$asOfFilter}
                   ORDER BY s2.soa_transaction_id DESC LIMIT 1
               )
             ORDER BY st.soa_transaction_id ASC LIMIT 1",
            'DB'
        );

        return $this->ok(['data' => ['balance' => $row ? (float) $row['balance'] : null, 'as_of' => $row['transaction_date'] ?? null]]);
    }

    // Match = the account's latest SOA balance vs. its latest cash_position
    // available_balance, within a ±0.01 tolerance (no date-matching —
    // same as TREASURY's getSoaComparison()).
    public function getSoaComparison($params)
    {
        $status = trim((string) ($params['status'] ?? ''));
        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        $where = ["ba.status = 'ACTIVE'"];
        if ($bankAccountId > 0) {
            $where[] = "ba.bank_account_id = {$bankAccountId}";
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT ba.bank_account_id, ba.account_name, ba.account_number, ba.bank_name,
                    soa.latest_soa_balance, soa.latest_soa_date,
                    cp.available_balance, cp.closing_balance, cp.position_date,
                    ROUND(COALESCE(soa.latest_soa_balance, 0) - COALESCE(cp.available_balance, 0), 2) AS difference
             FROM {$this->tableBankAccounts} ba
             LEFT JOIN LATERAL (
                 SELECT st.balance AS latest_soa_balance, st.transaction_date AS latest_soa_date
                 FROM {$this->tableSoaTransactions} st
                 WHERE st.bank_account_id = ba.bank_account_id AND st.balance IS NOT NULL
                   AND st.upload_batch_id = (
                       SELECT s2.upload_batch_id FROM {$this->tableSoaTransactions} s2
                       WHERE s2.bank_account_id = ba.bank_account_id AND s2.upload_batch_id IS NOT NULL
                       ORDER BY s2.soa_transaction_id DESC LIMIT 1
                   )
                 ORDER BY st.soa_transaction_id ASC LIMIT 1
             ) soa ON TRUE
             LEFT JOIN LATERAL (
                 SELECT available_balance, closing_balance, position_date FROM {$this->tableCashPosition} cp2
                 WHERE cp2.bank_account_id = ba.bank_account_id ORDER BY cp2.position_date DESC LIMIT 1
             ) cp ON TRUE
             WHERE {$whereSql} AND (soa.latest_soa_balance IS NOT NULL OR cp.available_balance IS NOT NULL)
             ORDER BY ba.account_name",
            'DB'
        );

        $filtered = array_filter($rows, function ($r) use ($status) {
            $hasSoa = $r['latest_soa_balance'] !== null;
            $hasPos = $r['available_balance'] !== null;
            $matched = $hasSoa && $hasPos && abs($this->num($r['latest_soa_balance']) - $this->num($r['available_balance'])) < 0.01;
            switch ($status) {
                case 'matched': return $matched;
                case 'mismatch': return $hasSoa && $hasPos && !$matched;
                case 'no_soa': return !$hasSoa;
                case 'no_position': return !$hasPos;
                default: return true;
            }
        });

        return $this->ok(['data' => array_values($filtered)]);
    }

    // One row per upload_batch_id — backs the SOA Transactions accordion.
    public function getSoaGroups($params)
    {
        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        $limit = max(1, min(100, (int) ($params['limit'] ?? 10)));
        $offset = max(0, (int) ($params['offset'] ?? 0));
        $where = ['upload_batch_id IS NOT NULL'];
        if ($bankAccountId > 0) {
            $where[] = "bank_account_id = {$bankAccountId}";
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT upload_batch_id, bank_account_id, MIN(soa_transaction_id) AS first_txn_id,
                    MAX(uploaded_at) AS upload_datetime, COUNT(*) AS transaction_count,
                    MAX(upload_type) AS upload_type, MIN(period_start) AS period_start, MAX(period_end) AS period_end
             FROM {$this->tableSoaTransactions}
             WHERE {$whereSql}
             GROUP BY upload_batch_id, bank_account_id
             ORDER BY first_txn_id DESC
             LIMIT {$limit} OFFSET {$offset}",
            'DB'
        );
        foreach ($rows as &$row) {
            $ba = App\ExecuteRow("SELECT account_name, account_number, bank_name FROM {$this->tableBankAccounts} WHERE bank_account_id = {$row['bank_account_id']}", 'DB');
            $row['account_name'] = $ba['account_name'] ?? null;
            $row['account_number'] = $ba['account_number'] ?? null;
            $row['bank_name'] = $ba['bank_name'] ?? null;
            $latest = App\ExecuteRow(
                "SELECT balance, debit_amount, credit_amount, transaction_date
                 FROM {$this->tableSoaTransactions}
                 WHERE upload_batch_id = " . $this->str($row['upload_batch_id']) . "::uuid AND balance IS NOT NULL
                 ORDER BY soa_transaction_id ASC LIMIT 1",
                'DB'
            );
            $row['latest_balance'] = $latest['balance'] ?? null;
            $row['latest_debit'] = $latest['debit_amount'] ?? null;
            $row['latest_credit'] = $latest['credit_amount'] ?? null;
            $row['latest_date'] = $latest['transaction_date'] ?? null;
            $row['transaction_count'] = (int) $row['transaction_count'];
        }

        $total = (int) App\ExecuteScalar(
            "SELECT COUNT(DISTINCT upload_batch_id) FROM {$this->tableSoaTransactions} WHERE {$whereSql}",
            'DB'
        );

        return $this->ok(['data' => $rows, 'total' => $total]);
    }

    public function getSoaTransactions($params)
    {
        $bankAccountId = (int) ($params['bank_account_id'] ?? 0);
        $batchId = trim((string) ($params['upload_batch_id'] ?? ''));
        $limit = max(1, min(500, (int) ($params['limit'] ?? 100)));
        $offset = max(0, (int) ($params['offset'] ?? 0));
        $where = ['1=1'];
        if ($bankAccountId > 0) {
            $where[] = "bank_account_id = {$bankAccountId}";
        }
        if ($batchId !== '') {
            $where[] = 'upload_batch_id = ' . $this->str($batchId) . '::uuid';
        }
        $whereSql = implode(' AND ', $where);

        $rows = App\ExecuteRowsAssociative(
            "SELECT soa_transaction_id, transaction_date, transaction_time, description, debit_amount, credit_amount,
                    balance, branch, check_no, other_details
             FROM {$this->tableSoaTransactions}
             WHERE {$whereSql}
             ORDER BY soa_transaction_id ASC
             LIMIT {$limit} OFFSET {$offset}",
            'DB'
        );
        $total = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->tableSoaTransactions} WHERE {$whereSql}", 'DB');

        return $this->ok(['data' => $rows, 'total' => $total]);
    }
}
