<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * IAR (Inspection and Acceptance Report) Module — ONE table
 * (see _assets/sql/01 - fims/01 - table/25 - fims_inspection_acceptance_report.sql):
 *   fims.inspection_acceptance_report   one row per PO: po_number, iar_number (typed by the user, unique), si_number, si_date and
 *                                       line_transaction (JSONB) = the PO's delivery receipts (DR):
 *   [ { dr_number, delivery_date, total_amount,
 *       lines: [ { account_code, account_title, amount } ],
 *       items?: [ { description, serial_no, amount, property_no } ] } ]   // items only when the total is >= 50,000
 *
 * The PO is picked from the Budget Allocation (the po_number of an RFA row — see poSource()). One save handles ONE DR. A DR is
 * identified inside its PO by its dr_number (unique within the PO); a serial number is unique across all DRs.
 * The DR total may not exceed the PO balance (PO amount - the totals of the PO's other DRs).
 *
 * Save payload:
 *   { po_number, iar_number, si_number?, si_date?,
 *     dr: { dr_ref? (edit: the dr_number the DR had when it was loaded), dr_number, delivery_date,
 *           entries: [ { account_code, amount } ],                           // account codes from fims.chart_of_accounts
 *           items?: [ { description?, serial_no?, amount?, property_no? } ] } }   // many items, one line each; kept only when the total is >= 50,000
 *
 * API shape of a delivery receipt (read / list):
 *   { dr_number, delivery_date, total_amount, entries: [ { account_code, account_title, amount } ],
 *     items: [ { description, serial_no, amount, property_no } ] }     // items is [] when none were added
 */
class PoDrService
{
    private string $h = 'fims.inspection_acceptance_report';   // po_number, iar_number, si_number, si_date, line_transaction

    /** DR total at or above which the optional items (description, serial, amount, property no. — one line each) apply. */
    private const ITEM_MIN_AMOUNT = 50000;

    /** Pro-forma JEV: the DR total is credited to Accounts Payable. */
    private const PAYABLE_ACCOUNT = '2-01-01-010';

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

    private function validDate(string $d): bool
    {
        $dt = \DateTime::createFromFormat('Y-m-d', $d);

        return $dt && $dt->format('Y-m-d') === $d;
    }

    /** Money text ("1,500.50") → number with 2 decimals, or null when it isn't a valid non-negative amount. */
    private function money($v): ?string
    {
        $s = str_replace(',', '', trim((string) ($v ?? '')));
        if ($s === '') {
            return '0.00';
        }
        if (!is_numeric($s) || (float) $s < 0 || (float) $s >= 1e14) {
            return null;
        }

        return number_format((float) $s, 2, '.', '');
    }

    /** SQL literal for a JSONB value. */
    private function jsonSql(array $value): string
    {
        return $this->q(json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '::jsonb';
    }

    /** The delivery receipts stored in line_transaction (a JSON string from the database, or an array). */
    private function drsFrom($json): array
    {
        $a = is_string($json) ? json_decode($json, true) : $json;

        return is_array($a) ? array_values(array_filter($a, 'is_array')) : [];
    }

    /** One stored delivery receipt → the API shape. */
    private function shape(array $d): array
    {
        return [
            'dr_number'     => (string) ($d['dr_number'] ?? ''),
            'delivery_date' => $d['delivery_date'] ?? null,
            'total_amount'  => $d['total_amount'] ?? '0.00',
            'entries'       => array_values(array_filter($d['lines'] ?? [], 'is_array')),
            'items'         => array_values(array_filter($d['items'] ?? [], 'is_array')),
        ];
    }

    /** A cleaned DR → the element stored in line_transaction. */
    private function stored(array $dr): array
    {
        $out = [
            'dr_number'     => $dr['dr_number'],
            'delivery_date' => $dr['delivery_date'],
            'total_amount'  => $dr['total'],
            'lines'         => $dr['entries'],
        ];
        if ($dr['items']) {
            $out['items'] = $dr['items'];
        }

        return $out;
    }

    /**
     * Validate + normalise. Returns [po_number, dr[], errorMessage, header[]]:
     *   dr = { ref, dr_number, delivery_date, total, entries[{account_code, account_title, amount}], items[] }
     */
    private function clean(array $body): array
    {
        $po = trim((string) ($body['po_number'] ?? ''));
        if ($po === '') {
            return [null, null, 'PO number is required.'];
        }
        if (mb_strlen($po) > 100) {
            return [null, null, 'PO number must be 100 characters or fewer.'];
        }

        $iar = trim((string) ($body['iar_number'] ?? ''));
        if ($iar === '') {
            return [null, null, 'IAR number is required.'];
        }
        if (mb_strlen($iar) > 50) {
            return [null, null, 'IAR number must be 50 characters or fewer.'];
        }

        // Sales Invoice # / date belong to the PO's IAR header
        $si  = $this->nullable($body['si_number'] ?? null);
        $sid = $this->nullable($body['si_date'] ?? null);
        if ($si !== null && mb_strlen($si) > 100) {
            return [null, null, 'Sales invoice no. must be 100 characters or fewer.'];
        }
        if ($sid !== null && !$this->validDate($sid)) {
            return [null, null, 'Sales invoice date must be a valid date.'];
        }

        $d = $body['dr'] ?? null;
        if (!is_array($d)) {
            return [null, null, 'Delivery receipt details are required.'];
        }
        $dr = trim((string) ($d['dr_number'] ?? ''));
        $dd = trim((string) ($d['delivery_date'] ?? ''));
        if ($dr === '') {
            return [null, null, 'Delivery receipt no. is required.'];
        }
        if (mb_strlen($dr) > 100) {
            return [null, null, 'Delivery receipt no. must be 100 characters or fewer.'];
        }
        if ($dd === '' || !$this->validDate($dd)) {
            return [null, null, 'Delivery date is required and must be a valid date.'];
        }

        // Account rows: an account code + an amount; the DR total is the sum of the amounts. There is no debit/credit to
        // balance here — the only limit is the PO balance (checked in drError()).
        $raw = $d['entries'] ?? [];
        if (!is_array($raw) || count($raw) < 1) {
            return [null, null, 'Add at least one account row.'];
        }
        $entries    = [];
        $totalCents = 0;   // in cents, so the sum is exact
        foreach (array_values($raw) as $i => $en) {
            $n    = $i + 1;
            $code = trim((string) ($en['account_code'] ?? ''));
            $amt  = $this->money($en['amount'] ?? null);
            if ($code === '') {
                return [null, null, "Row {$n}: please choose an account code."];
            }
            if ($amt === null) {
                return [null, null, "Row {$n}: amount must be a valid amount."];
            }
            if ((float) $amt <= 0) {
                return [null, null, "Row {$n}: enter an amount greater than 0."];
            }
            $totalCents += (int) round((float) $amt * 100);
            $entries[] = ['account_code' => $code, 'account_title' => '', 'amount' => $amt];
        }
        $total = number_format($totalCents / 100, 2, '.', '');

        // every account code must exist in the chart of accounts; its title is stored with the row
        $codes  = array_values(array_unique(array_column($entries, 'account_code')));
        $in     = implode(',', array_map(fn($c) => $this->q($c), $codes));
        $titles = [];
        foreach (App\ExecuteRows("SELECT account_code, account_title FROM fims.chart_of_accounts WHERE account_code IN ({$in})", 'DB') ?: [] as $r) {
            $titles[$r['account_code']] = (string) $r['account_title'];
        }
        foreach ($entries as &$en) {
            if (!isset($titles[$en['account_code']])) {
                return [null, null, "Account code '{$en['account_code']}' was not found in the chart of accounts."];
            }
            $en['account_title'] = $titles[$en['account_code']];
        }
        unset($en);

        // The optional items only apply from ITEM_MIN_AMOUNT (below it there are none): one line per item, any number of lines.
        // A line with nothing entered is skipped; every serial number must be different.
        $items = [];
        if ((float) $total >= self::ITEM_MIN_AMOUNT) {
            $serials = [];
            foreach (array_values(is_array($d['items'] ?? null) ? $d['items'] : []) as $i => $it) {
                if (!is_array($it)) {
                    continue;
                }
                $n   = $i + 1;
                $row = [
                    'description' => $this->nullable($it['description'] ?? null),
                    'serial_no'   => $this->nullable($it['serial_no'] ?? null),
                    'amount'      => null,
                    'property_no' => $this->nullable($it['property_no'] ?? null),
                ];
                $amtRaw = trim((string) ($it['amount'] ?? ''));
                if ($amtRaw !== '') {
                    $amt = $this->money($amtRaw);
                    if ($amt === null) {
                        return [null, null, "Item {$n}: amount must be a valid amount."];
                    }
                    $row['amount'] = $amt;
                }
                foreach (['serial_no' => 'Serial no.', 'property_no' => 'Property no.'] as $k => $label) {
                    if ($row[$k] !== null && mb_strlen($row[$k]) > 100) {
                        return [null, null, "Item {$n}: {$label} must be 100 characters or fewer."];
                    }
                }
                $row = array_filter($row, fn($v) => $v !== null);   // only what was entered is stored
                if (!$row) {
                    continue;
                }
                if (isset($row['serial_no'])) {
                    $key = mb_strtolower($row['serial_no']);
                    if (isset($serials[$key])) {
                        return [null, null, "Serial no. '{$row['serial_no']}' is entered more than once."];
                    }
                    $serials[$key] = true;
                }
                $items[] = $row;
            }
        }

        return [$po, [
            'ref'           => $this->nullable($d['dr_ref'] ?? null),
            'dr_number'     => $dr,
            'delivery_date' => $dd,
            'total'         => $total,
            'entries'       => $entries,
            'items'         => $items,
        ], null, ['iar_number' => $iar, 'si_number' => $si, 'si_date' => $sid]];
    }

    /** True when the IAR number already belongs to another PO's IAR ($exceptPo = that PO is not counted). */
    private function iarTaken(string $iar, ?string $exceptPo = null): bool
    {
        $sql = "SELECT COUNT(*) FROM {$this->h} WHERE iar_number = " . $this->q($iar);
        if ($exceptPo !== null) {
            $sql .= ' AND po_number <> ' . $this->q($exceptPo);
        }

        return (int) App\ExecuteScalar($sql, 'DB') > 0;
    }

    /**
     * Checks a DR against the PO's other DRs. $drs = the PO's stored DRs, $ref = the dr_number of the DR being edited (null on
     * add) — it is left out of every check. $storedPo = the PO number the DRs are stored under. Returns an error message or null:
     *   - the DR number is already used by another DR of this PO
     *   - a serial number of an item belongs to a DR anywhere
     *   - the DR total would exceed the PO balance (PO amount - the other DRs' totals); a PO that isn't an RFA PO has no limit
     */
    private function drError(string $storedPo, array $dr, array $drs, ?string $ref, ?array $info): ?string
    {
        $others = array_values(array_filter($drs, fn($d) => $ref === null || (string) ($d['dr_number'] ?? '') !== $ref));
        foreach ($others as $d) {
            if ((string) ($d['dr_number'] ?? '') === $dr['dr_number']) {
                return "Delivery receipt '{$dr['dr_number']}' is already recorded for this PO.";
            }
        }

        foreach ($dr['items'] as $it) {
            $ser = $it['serial_no'] ?? null;
            if ($ser === null) {
                continue;
            }
            $sql = "SELECT COUNT(*) FROM {$this->h} i, jsonb_array_elements(i.line_transaction) AS d(value),
                           jsonb_array_elements(COALESCE(d.value -> 'items', '[]'::jsonb)) AS it(value)
                    WHERE it.value ->> 'serial_no' = " . $this->q($ser);
            if ($ref !== null) {
                $sql .= ' AND NOT (i.po_number = ' . $this->q($storedPo) . " AND d.value ->> 'dr_number' = " . $this->q($ref) . ')';
            }
            if ((int) App\ExecuteScalar($sql, 'DB') > 0) {
                return "Serial no. '{$ser}' is already registered.";
            }
        }

        if ($info) {
            $delivered    = array_sum(array_map(fn($d) => (float) ($d['total_amount'] ?? 0), $others));
            $balanceCents = (int) round(((float) $info['po_amount'] - $delivered) * 100);
            if ((int) round((float) $dr['total'] * 100) > $balanceCents) {
                return 'The delivery receipt total (' . number_format((float) $dr['total'], 2) . ') exceeds the PO balance ('
                    . number_format(max(0, $balanceCents) / 100, 2) . ' of ' . number_format((float) $info['po_amount'], 2) . ').';
            }
        }

        return null;
    }

    /**
     * SQL for the sum of "requested_amount" over the lines of fims.budget_allocation.line_transaction (jsonb: an array of
     * lines, or one line object). A blank / non-numeric amount counts as 0. Constant SQL, no user input.
     */
    private function requestedTotalSql(): string
    {
        return "COALESCE((
                    SELECT SUM(CASE
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'number' THEN (ln.value->>'requested_amount')::numeric
                                 WHEN jsonb_typeof(ln.value->'requested_amount') = 'string'
                                      AND REPLACE(ln.value->>'requested_amount', ',', '') ~ '^-?[0-9]+([.][0-9]+)?\$'
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

    /** The budget allocation's line_transaction → [ {account_code, account_title, amount (= requested_amount)} ] for pre-filling the form. */
    private function allocationLines($raw): array
    {
        $a = is_string($raw) ? json_decode($raw, true) : $raw;
        if (is_array($a) && $a && array_keys($a) !== range(0, count($a) - 1)) {
            $a = [$a];                                   // a single line object
        }
        $out = [];
        foreach (is_array($a) ? $a : [] as $ln) {
            if (!is_array($ln)) {
                continue;
            }
            $code = trim((string) ($ln['account_code'] ?? ''));
            $amt  = str_replace(',', '', trim((string) ($ln['requested_amount'] ?? '')));
            if ($code === '' || !is_numeric($amt) || (float) $amt <= 0) {
                continue;
            }
            $out[] = ['account_code' => $code, 'account_title' => (string) ($ln['account_title'] ?? ''), 'amount' => number_format((float) $amt, 2, '.', '')];
        }

        return $out;
    }

    /**
     * The POs the IAR can be recorded against: the PO numbers of the Budget Allocation's RFA rows (allocation_type = 'RFA').
     * One row per PO number: { po_number, po_date, requesting_rc_id, requesting_office, vendor_id, vendor_name, po_amount, dr_total, lines }.
     *   po_date          = the RFA's creation date (budget_allocation has no PO date column of its own)
     *   requesting_office = fims.responsibility_code.rc_name of requesting_rc_id
     *   po_amount        = sum of line_transaction[].requested_amount
     *   dr_total         = total of the delivery receipts already recorded for the PO (PO balance = po_amount - dr_total)
     *   lines            = the RFA's line_transaction as account_code / account_title / amount, to pre-fill the form
     * $exact: PO numbers to look up; $search: ILIKE filter on the PO number; both empty = every PO.
     * $onlyWithBalance: leave out the POs that have no balance left (po_amount - dr_total <= 0) — used by the PO picker.
     */
    private function poSource(?array $exact = null, string $search = '', int $limit = 0, bool $onlyWithBalance = false): array
    {
        $where = "UPPER(COALESCE(a.allocation_type, '')) = 'RFA' AND a.deleted_at IS NULL AND COALESCE(TRIM(a.po_number), '') <> ''";
        if ($exact !== null) {
            if (!$exact) {
                return [];
            }
            $where .= ' AND a.po_number IN (' . implode(',', array_map(fn($p) => $this->q($p), $exact)) . ')';
        }
        if ($search !== '') {
            $where .= ' AND a.po_number ILIKE ' . $this->q('%' . $search . '%');
        }
        $sql = "SELECT * FROM (
                    SELECT DISTINCT ON (a.po_number)
                           a.po_number, TO_CHAR(a.created_at, 'YYYY-MM-DD') AS po_date, a.created_at,
                           a.requesting_rc_id, rc.rc_name AS requesting_office,
                           a.vendor_id, v.vendor_name,
                           " . $this->requestedTotalSql() . " AS po_amount,
                           (SELECT COALESCE(SUM((d.value ->> 'total_amount')::numeric), 0)
                              FROM {$this->h} i, jsonb_array_elements(i.line_transaction) AS d(value)
                             WHERE i.po_number = a.po_number) AS dr_total,
                           a.line_transaction AS allocation_lines
                    FROM fims.budget_allocation a
                    LEFT JOIN fims.responsibility_code rc ON rc.rc_id = a.requesting_rc_id
                    LEFT JOIN fims.vendors v ON v.vendor_id = a.vendor_id
                    WHERE {$where}
                    ORDER BY a.po_number, a.created_at DESC, a.allocation_id DESC
                ) po" . ($onlyWithBalance ? ' WHERE po.po_amount - po.dr_total > 0' : '') . " ORDER BY po.created_at DESC, po.po_number" . ($limit > 0 ? ' LIMIT ' . (int) $limit : '');
        $rows = App\ExecuteRows($sql, 'DB') ?: [];
        foreach ($rows as &$r) {
            $r['lines'] = $this->allocationLines($r['allocation_lines']);
            unset($r['created_at'], $r['allocation_lines']);
        }
        unset($r);

        return $rows;
    }

    /** Search for the PO picker (RFA POs only; a PO with no balance left is not offered). */
    public function searchPos(string $search = ''): array
    {
        try {
            return ['success' => true, 'data' => $this->poSource(null, trim($search), 30, true)];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Search for the Account Code picker: the whole chart of accounts (postable, active), by code or title. */
    public function searchAccounts(string $search = ''): array
    {
        try {
            $where = "COALESCE(posting_allowed, TRUE) AND COALESCE(status, 'Active') ILIKE 'active'";
            if (trim($search) !== '') {
                $s      = $this->q('%' . trim($search) . '%');
                $where .= " AND (account_code ILIKE {$s} OR account_title ILIKE {$s})";
            }
            $rows = App\ExecuteRows(
                "SELECT DISTINCT ON (account_code) account_code, account_title FROM fims.chart_of_accounts WHERE {$where}
                 ORDER BY account_code LIMIT 30",
                'DB'
            ) ?: [];

            return ['success' => true, 'data' => $rows];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** po_number => PO details, for the given PO numbers. */
    private function poInfo(array $poNumbers): array
    {
        $by = [];
        foreach ($this->poSource(array_values(array_unique($poNumbers))) as $r) {
            $by[$r['po_number']] = $r;
        }

        return $by;
    }

    /** The PO's details + what has been delivered so far: balance = PO amount - total of its delivery receipts. */
    private function withPo(array $row, ?array $info, array $receipts): array
    {
        $drTotal = round(array_sum(array_map(fn($r) => (float) $r['total_amount'], $receipts)), 2);
        $poAmt   = $info ? round((float) $info['po_amount'], 2) : null;

        return $row + [
            'po_date'           => $info['po_date'] ?? null,
            'requesting_rc_id'  => $info['requesting_rc_id'] ?? null,
            'requesting_office' => $info['requesting_office'] ?? null,
            'vendor_id'         => $info['vendor_id'] ?? null,
            'vendor_name'       => $info['vendor_name'] ?? null,
            'po_amount'         => $poAmt,
            'dr_total'          => $drTotal,
            'balance'           => $poAmt === null ? null : round($poAmt - $drTotal, 2),
        ];
    }

    /** IAR row → { po_number, iar_number, si_number, si_date, receipts[] } (receipts in the API shape). */
    private function shapeRow(array $r): array
    {
        return [
            'po_number'  => $r['po_number'],
            'iar_number' => $r['iar_number'],
            'si_number'  => $r['si_number'],
            'si_date'    => $r['si_date'],
            'receipts'   => array_map(fn($d) => $this->shape($d), $this->drsFrom($r['line_transaction'])),
        ];
    }

    private const COLS = "i.po_number, i.iar_number, i.si_number, TO_CHAR(i.si_date, 'YYYY-MM-DD') AS si_date, i.line_transaction";

    /** One entry per PO number: { po_number, iar_number, …, receipts: [ … ] } (paginated). */
    public function fetchAlls(int $page = 1, int $limit = 10, string $search = ''): array
    {
        try {
            $where = '1=1';
            if ($search !== '') {
                // the delivery receipts live in line_transaction: matching its text covers DR no., account codes / titles,
                // description, serial and property numbers
                $s     = $this->q('%' . $search . '%');
                $where = "(i.po_number ILIKE {$s} OR i.iar_number ILIKE {$s} OR i.si_number ILIKE {$s} OR i.line_transaction::text ILIKE {$s})";
            }

            $limit  = max(1, $limit);
            $page   = max(1, $page);
            $offset = ($page - 1) * $limit;
            $total  = (int) App\ExecuteScalar("SELECT COUNT(*) FROM {$this->h} i WHERE {$where}", 'DB');

            $rows = App\ExecuteRows(
                'SELECT ' . self::COLS . " FROM {$this->h} i WHERE {$where} ORDER BY i.iar_id DESC LIMIT {$limit} OFFSET {$offset}",
                'DB'
            ) ?: [];

            $info = $this->poInfo(array_column($rows, 'po_number'));
            $data = [];
            foreach ($rows as $r) {
                $row    = $this->shapeRow($r);
                $data[] = $this->withPo($row, $info[$row['po_number']] ?? null, $row['receipts']);
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
            $rows = App\ExecuteRows('SELECT ' . self::COLS . " FROM {$this->h} i WHERE i.po_number = " . $this->q($poNumber), 'DB');
            if (!$rows) {
                return ['success' => false, 'message' => "PO '{$poNumber}' not found."];
            }
            $row = $this->shapeRow($rows[0]);

            return ['success' => true, 'data' => $this->withPo($row, $this->poInfo([$poNumber])[$poNumber] ?? null, $row['receipts'])];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Pro-forma JEV of every delivery receipt of a PO (one entry per DR): each stored account row is a debit, and the
     * DR total is credited to Accounts Payable (2-01-01-010). Titles are the ones stored with the rows.
     */
    public function proforma(string $poNumber): array
    {
        try {
            $res = $this->fetchByPo($poNumber);
            if (!$res['success'] || !$res['data']['receipts']) {
                return ['success' => false, 'message' => "No delivery receipts found for PO '{$poNumber}'."];
            }

            $payableTitle = (string) (App\ExecuteScalar('SELECT account_title FROM fims.chart_of_accounts WHERE account_code = ' . $this->q(self::PAYABLE_ACCOUNT) . ' LIMIT 1', 'DB') ?: 'Accounts Payable');
            $receipts = [];
            foreach ($res['data']['receipts'] as $dr) {
                $rows = [];
                foreach ($dr['entries'] as $en) {
                    $rows[] = [
                        'account_title' => $en['account_title'] ?? '',
                        'account_code'  => $en['account_code'],
                        'debit'         => round((float) $en['amount'], 2),
                        'credit'        => 0,
                    ];
                }
                $rows[] = [
                    'account_title' => $payableTitle,
                    'account_code'  => self::PAYABLE_ACCOUNT,
                    'debit'         => 0,
                    'credit'        => round((float) $dr['total_amount'], 2),
                ];
                $receipts[] = ['dr_number' => (string) $dr['dr_number'], 'rows' => $rows, 'total' => round((float) $dr['total_amount'], 2)];
            }

            return ['success' => true, 'data' => ['po_number' => $poNumber, 'iar_number' => $res['data']['iar_number'], 'receipts' => $receipts]];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Adds ONE delivery receipt to the PO (creating the PO's IAR row when the PO has none yet). */
    public function create(array $body, ?int $userId = null): array
    {
        try {
            [$po, $dr, $err, $hdr] = $this->clean($body) + [null, null, null, null];   // an error result has only 3 items
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            // The PO must be picked from the Budget Allocation (an RFA's PO number), not typed freely.
            $poInfo = $this->poInfo([$po])[$po] ?? null;
            if (!$poInfo) {
                return ['success' => false, 'message' => "PO '{$po}' was not found in the Budget Allocation (RFA). Please select a PO from the list."];
            }
            $iar = $hdr['iar_number'];
            $by  = $userId ? (int) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                $cur = App\ExecuteRows("SELECT iar_number, line_transaction FROM {$this->h} WHERE po_number = " . $this->q($po) . ' FOR UPDATE', 'DB');
                if ($cur && $cur[0]['iar_number'] !== $iar) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => "PO '{$po}' already has IAR number '{$cur[0]['iar_number']}'. Use that IAR number, or edit the PO to change it."];
                }
                if (!$cur && $this->iarTaken($iar)) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => "IAR number '{$iar}' is already used by another PO."];
                }
                $drs = $cur ? $this->drsFrom($cur[0]['line_transaction']) : [];
                if ($msg = $this->drError($po, $dr, $drs, null, $poInfo)) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $msg];
                }
                $drs[] = $this->stored($dr);

                if ($cur) {
                    // the PO already has its IAR (same number, checked above): add the DR, fill in the invoice details that were given
                    $set = ['line_transaction = ' . $this->jsonSql($drs)];
                    if ($hdr['si_number'] !== null) $set[] = 'si_number = ' . $this->q($hdr['si_number']);
                    if ($hdr['si_date'] !== null)   $set[] = 'si_date = ' . $this->q($hdr['si_date']);
                    App\Execute("UPDATE {$this->h} SET " . implode(', ', $set) . ", updated_by = {$by}, updated_at = CURRENT_TIMESTAMP WHERE po_number = " . $this->q($po), 'DB');
                } else {
                    App\Execute(
                        "INSERT INTO {$this->h} (iar_number, po_number, si_number, si_date, line_transaction, created_by) VALUES ("
                            . $this->q($iar) . ', ' . $this->q($po) . ', ' . $this->sqlOrNull($hdr['si_number']) . ', '
                            . $this->sqlOrNull($hdr['si_date']) . ', ' . $this->jsonSql($drs) . ", {$by})",
                        'DB'
                    );
                }
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => $cur ? "Delivery receipt {$dr['dr_number']} added to PO {$po} ({$iar})." : "PO {$po} added successfully ({$iar})."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * EDIT — deliberately limited. What can be changed: the IAR number, the Sales Invoice # / date, the DR number and delivery
     * date of ONE delivery receipt (payload dr.dr_ref = the dr_number it had when loaded) and that DR's optional items (when
     * its total is 50,000 or more; items can be added, changed and removed). What can NOT be changed here: the PO number, and the
     * account rows / amounts / total of the DR (whatever is sent for them is ignored — the stored ones are kept), and no
     * other DR can be added (that is the Add form). So the PO balance cannot be affected by an edit.
     */
    public function update(string $oldPo, array $body, ?int $userId = null): array
    {
        try {
            $check = $this->fetchByPo($oldPo);
            if (!$check['success']) {
                return $check;
            }

            // the PO of an IAR cannot be changed
            if (trim((string) ($body['po_number'] ?? '')) !== $oldPo) {
                return ['success' => false, 'message' => 'The PO number cannot be changed. Add a new record for the other PO.'];
            }

            // the DR being edited (by the dr_number it had when loaded) — its stored account rows are kept as they are:
            // they are put into the payload only so that the usual validation of the whole DR runs on the unchanged total
            $ref    = trim((string) ($body['dr']['dr_ref'] ?? ''));
            $stored = null;
            foreach ($check['data']['receipts'] as $r) {
                if ($ref !== '' && (string) $r['dr_number'] === $ref) {
                    $stored = $r;
                }
            }
            if ($stored === null) {
                return ['success' => false, 'message' => 'Select the delivery receipt to edit.'];
            }
            $body['dr']['entries'] = array_map(fn($e) => ['account_code' => $e['account_code'], 'amount' => $e['amount']], $stored['entries']);

            [$po, $dr, $err, $hdr] = $this->clean($body) + [null, null, null, null];   // an error result has only 3 items
            if ($err) {
                return ['success' => false, 'message' => $err];
            }
            $oldIar = $check['data']['iar_number'];
            $iar    = $hdr['iar_number'];                       // the IAR number can be changed by the user
            if ($iar !== $oldIar && $this->iarTaken($iar, $oldPo)) {
                return ['success' => false, 'message' => "IAR number '{$iar}' is already used by another PO."];
            }
            $by         = $userId ? (int) $userId : 'NULL';

            App\Execute('BEGIN', 'DB');
            try {
                $cur = App\ExecuteRows("SELECT line_transaction FROM {$this->h} WHERE po_number = " . $this->q($oldPo) . ' FOR UPDATE', 'DB');
                $drs = $cur ? $this->drsFrom($cur[0]['line_transaction']) : [];
                $pos = null;
                foreach ($drs as $i => $d) {
                    if ($dr['ref'] !== null && (string) ($d['dr_number'] ?? '') === $dr['ref']) {
                        $pos = $i;
                    }
                }
                if ($pos === null) {
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => 'Select the delivery receipt to edit.'];
                }
                if ($msg = $this->drError($oldPo, $dr, $drs, $dr['ref'], null)) {     // DR number + serial checks; no balance check: the total is unchanged
                    App\Execute('ROLLBACK', 'DB');

                    return ['success' => false, 'message' => $msg];
                }
                // keep the stored DR as it is (its account rows, titles and total) and replace only what may be edited
                $new                  = $drs[$pos];
                $new['dr_number']     = $dr['dr_number'];
                $new['delivery_date'] = $dr['delivery_date'];
                unset($new['items']);
                if ($dr['items']) {
                    $new['items'] = $dr['items'];
                }
                $drs[$pos] = $new;

                App\Execute("UPDATE {$this->h} SET iar_number = " . $this->q($iar) . ', po_number = ' . $this->q($oldPo) . ', si_number = ' . $this->sqlOrNull($hdr['si_number'])
                    . ', si_date = ' . $this->sqlOrNull($hdr['si_date']) . ', line_transaction = ' . $this->jsonSql($drs)
                    . ", updated_by = {$by}, updated_at = CURRENT_TIMESTAMP WHERE po_number = " . $this->q($oldPo), 'DB');
                App\Execute('COMMIT', 'DB');
            } catch (\Throwable $e) {
                App\Execute('ROLLBACK', 'DB');
                throw $e;
            }

            return ['success' => true, 'message' => "Delivery receipt {$dr['dr_number']} of PO {$po} updated successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Deletes the PO's IAR with all its delivery receipts. */
    public function delete(string $poNumber): array
    {
        try {
            $check = $this->fetchByPo($poNumber);
            if (!$check['success']) {
                return $check;
            }
            App\Execute("DELETE FROM {$this->h} WHERE po_number = " . $this->q($poNumber), 'DB');

            return ['success' => true, 'message' => "PO {$poNumber} deleted successfully."];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
