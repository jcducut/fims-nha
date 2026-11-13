<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Fund Sourcing Division pricing requests (fims.pricing_requests) and their
 * supporting documents (fims.pricing_request_attachments).
 *
 * Mirrors the Pricing Request modal (app/pages/form/pricing-request-form.php):
 *   - project_code is the only required field; everything else is optional ('' = NULL).
 *   - The day counts (c), (e), (g) and the total (h) are calculated by the form and
 *     saved as sent (see the column comments in 34 - fims_pricing_requests.sql).
 *   - A request must not repeat another record's project + type of request + date received.
 *   - Documents are an uploaded file (object storage key) or a pasted link. Saving never
 *     deletes a document that is missing from the list — removing one is an immediate
 *     DELETE /pricing_request_attachments/delete/{id} (soft delete).
 */
class PricingRequestService
{
    private string $table = 'fims.pricing_requests';
    private string $tableAttachment = 'fims.pricing_request_attachments';
    private string $tableProject = 'fims.project';
    private string $tableOffices = 'uac.offices';

    private const ATTACHMENT_MAX_BYTES = 20 * 1024 * 1024; // 20 MB
    private const ATTACHMENT_ALLOWED_EXT = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'];
    private const ATTACHMENT_KEY_PREFIX = 'pricing-request-attachments/';

    // field => type, in table column order
    private const FIELDS = [
        'project_code' => 'string',
        'type_of_request' => 'string',
        'status' => 'string',
        'signed_by' => 'string',
        'assigned_to' => 'string',
        'unsold_inventories' => 'numeric',
        'regional_office_id' => 'int',
        'district_office_id' => 'int',
        'date_received' => 'date',
        'date_received_complete_docs' => 'date',
        'date_forwarded_fsg' => 'date',
        'days_with_fmd' => 'int',
        'date_forwarded_ogm' => 'date',
        'days_with_fsg' => 'int',
        'date_approved_ogm' => 'date',
        'days_with_ogm' => 'int',
        'total_days' => 'int',
        'remarks' => 'text',
    ];

    // Max lengths of the VARCHAR columns (longer text is rejected, not truncated)
    private const MAX_LEN = [
        'project_code' => 30,
        'type_of_request' => 100,
        'status' => 50,
        'signed_by' => 150,
        'assigned_to' => 150,
    ];

    // Columns the list may be sorted by (anything else falls back to the default)
    private const SORTABLE = [
        'id', 'project_code', 'type_of_request', 'status', 'signed_by', 'assigned_to',
        'unsold_inventories', 'date_received', 'date_received_complete_docs', 'date_forwarded_fsg',
        'date_forwarded_ogm', 'date_approved_ogm', 'days_with_fmd', 'days_with_fsg',
        'days_with_ogm', 'total_days', 'created_at',
    ];

    /** SQL literal for a validated value ($v is null or already validated by prepareRow()). */
    private function lit(mixed $v, string $type): string
    {
        if ($v === null) {
            return 'NULL';
        }

        return match ($type) {
            'int' => (string) (int) $v,
            'numeric' => sprintf('%.2f', (float) $v),
            'date' => App\QuotedValue((string) $v, DataType::STRING, 'DB').'::date',
            default => App\QuotedValue((string) $v, DataType::STRING, 'DB'),
        };
    }

    // -----------------------------------------------------------------------
    // Validate + convert the body. Returns [array $values, string[] $errors];
    // $values is keyed by column (null for blanks).
    // $only — when given, only these fields are processed (partial update).
    // -----------------------------------------------------------------------
    private function prepareRow(array $body, ?array $only = null): array
    {
        $values = [];
        $errors = [];

        foreach (self::FIELDS as $field => $type) {
            if ($only !== null && !in_array($field, $only, true)) {
                continue;
            }
            $raw = $body[$field] ?? null;
            if (is_string($raw)) {
                $raw = trim($raw);
            }
            if ($raw === '' || $raw === null) {
                $values[$field] = null;
                continue;
            }

            switch ($type) {
                case 'string':
                case 'text':
                    if (!is_scalar($raw)) {
                        $errors[] = "\"{$field}\" must be text.";
                        $values[$field] = null;
                        break;
                    }
                    $raw = (string) $raw;
                    if (isset(self::MAX_LEN[$field]) && mb_strlen($raw) > self::MAX_LEN[$field]) {
                        $errors[] = "\"{$field}\" is longer than ".self::MAX_LEN[$field].' characters.';
                    }
                    $values[$field] = $raw;
                    break;

                case 'int':
                    if (!is_numeric($raw) || (float) $raw != (int) $raw || (int) $raw < 0) {
                        $errors[] = "\"{$field}\" must be a whole number (0 or more).";
                        $values[$field] = null;
                    } else {
                        $values[$field] = (int) $raw;
                    }
                    break;

                case 'numeric':
                    if (!is_numeric($raw) || (float) $raw < 0 || (float) $raw >= 1e16) {
                        $errors[] = "\"{$field}\" must be a number (0 or more).";
                        $values[$field] = null;
                    } else {
                        $values[$field] = (float) $raw;
                    }
                    break;

                case 'date':
                    if (
                        !is_string($raw)
                        || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $raw, $m)
                        || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                    ) {
                        $errors[] = "\"{$field}\" must be a valid date (YYYY-MM-DD).";
                        $values[$field] = null;
                    } else {
                        $values[$field] = $raw;
                    }
                    break;
            }
        }

        if (($only === null || in_array('project_code', $only, true)) && ($values['project_code'] ?? null) === null) {
            $errors[] = 'Project Name is required.';
        }

        return [$values, $errors];
    }

    /**
     * Checks the references the DB would otherwise reject with an opaque FK error:
     * the project exists, the offices exist, and the district sits under the region.
     * $v is the record as it will look after the save (merged with the stored row on update).
     */
    private function checkReferences(array $v): array
    {
        $errors = [];

        if (($v['project_code'] ?? null) !== null) {
            $found = App\ExecuteScalar(
                "SELECT 1 FROM {$this->tableProject} WHERE project_code = ".$this->lit($v['project_code'], 'string'),
                'DB'
            );
            if (!$found) {
                $errors[] = 'The selected project does not exist.';
            }
        }

        $regionId = $v['regional_office_id'] ?? null;
        $districtId = $v['district_office_id'] ?? null;
        $regionOk = true;

        if ($regionId !== null) {
            $regionOk = (bool) App\ExecuteScalar("SELECT 1 FROM {$this->tableOffices} WHERE office_id = ".(int) $regionId, 'DB');
            if (!$regionOk) {
                $errors[] = 'The selected Regional Office does not exist.';
            }
        }
        if ($districtId !== null) {
            $district = App\ExecuteRow("SELECT parent_office_id FROM {$this->tableOffices} WHERE office_id = ".(int) $districtId, 'DB');
            if (!$district) {
                $errors[] = 'The selected District Office does not exist.';
            } elseif ($regionId !== null && $regionOk && (int) ($district['parent_office_id'] ?? 0) !== (int) $regionId) {
                $errors[] = 'The selected District Office is not under the selected Regional Office.';
            }
        }

        return $errors;
    }

    // Duplicate = same project_code + type_of_request + date_received (matches the unique index,
    // so a record with no type or no date is never a duplicate). $excludeId — the record being edited.
    private function isDuplicate(array $v, ?int $excludeId = null): bool
    {
        if (($v['type_of_request'] ?? null) === null || ($v['date_received'] ?? null) === null) {
            return false;
        }

        return (int) App\ExecuteScalar(
            "SELECT COUNT(*) FROM {$this->table}
              WHERE project_code = ".$this->lit($v['project_code'], 'string').'
                AND type_of_request = '.$this->lit($v['type_of_request'], 'string').'
                AND date_received = '.$this->lit($v['date_received'], 'date')
                .($excludeId !== null ? ' AND id <> '.(int) $excludeId : ''),
            'DB'
        ) > 0;
    }

    private const DUPLICATE_MESSAGE = 'A pricing request with the same Project Name, Type of Request and Date Received already exists.';

    // -----------------------------------------------------------------------
    // Reads
    // -----------------------------------------------------------------------
    private function selectSql(): string
    {
        return "SELECT pr.*, p.project_name,
                       ro.office_name AS regional_office_name,
                       dof.office_name AS district_office_name
                  FROM {$this->table} pr
                  LEFT JOIN {$this->tableProject} p ON p.project_code = pr.project_code
                  LEFT JOIN {$this->tableOffices} ro ON ro.office_id = pr.regional_office_id
                  LEFT JOIN {$this->tableOffices} dof ON dof.office_id = pr.district_office_id";
    }

    /** Filters shared by fetchAlls() / fetchList(): search, status, type_of_request, project_code, pending, sort, dir. */
    private function buildFilters(array $params): array
    {
        $where = ['pr.is_active = TRUE'];

        $search = trim((string) ($params['search'] ?? ''));
        if ($search !== '') {
            $like = $this->lit('%'.addcslashes($search, '%_\\').'%', 'string');
            $where[] = "(p.project_name ILIKE {$like} OR pr.project_code ILIKE {$like} OR pr.remarks ILIKE {$like} OR pr.assigned_to ILIKE {$like})";
        }
        foreach (['status', 'type_of_request', 'project_code', 'signed_by', 'assigned_to'] as $col) {
            $val = trim((string) ($params[$col] ?? ''));
            if ($val !== '') {
                $where[] = "UPPER(pr.{$col}) = UPPER(".$this->lit($val, 'string').')';
            }
        }
        if (in_array(strtolower((string) ($params['pending'] ?? '')), ['1', 'true', 'yes'], true)) {
            $where[] = 'pr.date_approved_ogm IS NULL';
        }

        $sort = (string) ($params['sort'] ?? '');
        if (in_array($sort, self::SORTABLE, true)) {
            $dir = strtolower((string) ($params['dir'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $orderBy = "pr.{$sort} {$dir} NULLS LAST, pr.id DESC";
        } else {
            $orderBy = 'pr.id DESC'; // default: latest first
        }

        return ['WHERE '.implode(' AND ', $where), $orderBy];
    }

    public function fetchAlls(array $params = []): array
    {
        try {
            [$where, $orderBy] = $this->buildFilters($params);

            return ['success' => true, 'data' => App\ExecuteRows($this->selectSql()." {$where} ORDER BY {$orderBy}", 'DB') ?: []];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function fetchList(array $params = []): array
    {
        try {
            $page = max(1, (int) ($params['page'] ?? 1));
            $limit = (int) ($params['limit'] ?? 10);
            $limit = $limit < 1 ? 10 : min($limit, 200);
            $offset = ($page - 1) * $limit;

            [$where, $orderBy] = $this->buildFilters($params);

            $total = (int) App\ExecuteScalar(
                "SELECT COUNT(*) FROM {$this->table} pr LEFT JOIN {$this->tableProject} p ON p.project_code = pr.project_code {$where}",
                'DB'
            );
            $rows = App\ExecuteRows($this->selectSql()." {$where} ORDER BY {$orderBy} LIMIT {$limit} OFFSET {$offset}", 'DB') ?: [];

            return [
                'success' => true,
                'data' => $rows,
                'pagination' => [
                    'total' => $total,
                    'per_page' => $limit,
                    'current_page' => $page,
                    'last_page' => (int) ceil($total / $limit),
                ],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Every office (for the Regional / District selects). The page splits them by parent_office_id. */
    public function fetchOffices(): array
    {
        try {
            return [
                'success' => true,
                'data' => App\ExecuteRows(
                    "SELECT office_id, office_name, office_type, parent_office_id FROM {$this->tableOffices} ORDER BY office_name",
                    'DB'
                ) ?: [],
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** One record with its attachments. Returns ['success'=>false,'http'=>404|422,...] when it can't be read. */
    public function fetchById(int|string $id): array
    {
        try {
            if (!is_numeric($id) || (int) $id != $id || (int) $id < 1) {
                return ['success' => false, 'message' => 'Pricing request ID must be a positive whole number.', 'http' => 422];
            }

            $row = App\ExecuteRow($this->selectSql().' WHERE pr.id = '.(int) $id.' AND pr.is_active = TRUE', 'DB');
            if (!$row) {
                return ['success' => false, 'message' => "Pricing request #{$id} not found.", 'http' => 404];
            }
            $row['attachments'] = $this->fetchAttachments((int) $id)['data'] ?? [];

            return ['success' => true, 'data' => $row];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // -----------------------------------------------------------------------
    // Create / update. $uploadedFiles = $request->getUploadedFiles() (doc_file_N parts).
    // Returns ['success'=>true, 'message'=>..., 'data'=>row] or
    // ['success'=>false, 'message'=>..., 'http'=>404|422|400] (the route maps 'http').
    // -----------------------------------------------------------------------
    public function create(array $body, array $uploadedFiles = []): array
    {
        try {
            [$values, $errors] = $this->prepareRow($body);
            if (!$errors) {
                $errors = $this->checkReferences($values);
            }
            if ($errors) {
                return ['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors, 'http' => 422];
            }
            if ($this->isDuplicate($values)) {
                return ['success' => false, 'message' => self::DUPLICATE_MESSAGE, 'http' => 422];
            }

            // Upload first: a rejected file must not leave a half-saved request behind.
            $docs = $this->uploadSupportingDocFiles($body['supporting_documents'] ?? [], $uploadedFiles, (string) $values['project_code']);

            $cols = array_keys(self::FIELDS);
            $vals = array_map(fn ($c) => $this->lit($values[$c], self::FIELDS[$c]), $cols);
            $row = App\ExecuteRow(
                'INSERT INTO '.$this->table.' ('.implode(', ', $cols).', created_by)
                 VALUES ('.implode(', ', $vals).', '.(int) App\CurrentUserID().')
                 RETURNING id',
                'DB'
            );
            $id = (int) $row['id'];

            $this->persistAttachments($id, $docs);

            return ['success' => true, 'message' => 'Pricing request created successfully.', 'id' => $id, 'data' => $this->fetchById($id)['data'] ?? null];
        } catch (\Throwable $e) {
            return $this->failure($e);
        }
    }

    public function update(int|string $id, array $body, array $uploadedFiles = []): array
    {
        try {
            $existing = $this->fetchById($id);
            if (!$existing['success']) {
                return $existing;
            }
            $stored = $existing['data'];

            $fields = array_intersect_key($body, self::FIELDS);
            $only = array_keys($fields);

            $values = [];
            if ($only) {
                [$values, $errors] = $this->prepareRow($fields, $only);
                if (!$errors) {
                    $errors = $this->checkReferences(array_merge($stored, $values));
                }
                if ($errors) {
                    return ['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors, 'http' => 422];
                }
                if (array_intersect($only, ['project_code', 'type_of_request', 'date_received']) && $this->isDuplicate(array_merge($stored, $values), (int) $id)) {
                    return ['success' => false, 'message' => self::DUPLICATE_MESSAGE, 'http' => 422];
                }
            }

            $projectCode = (string) ($values['project_code'] ?? $stored['project_code']);
            $docs = $this->uploadSupportingDocFiles($body['supporting_documents'] ?? [], $uploadedFiles, $projectCode);

            if ($only) {
                $set = implode(', ', array_map(fn ($c) => "{$c} = ".$this->lit($values[$c], self::FIELDS[$c]), $only));
                App\Execute("UPDATE {$this->table} SET {$set}, updated_at = NOW() WHERE id = ".(int) $id, 'DB');
            }
            $this->persistAttachments((int) $id, $docs);

            return ['success' => true, 'message' => 'Pricing request updated successfully.', 'data' => $this->fetchById($id)['data'] ?? null];
        } catch (\Throwable $e) {
            return $this->failure($e);
        }
    }

    /**
     * Deletes a request for good. Its attachment rows go with it (ON DELETE CASCADE), and so do the
     * uploaded files in object storage — removed best-effort once the rows are gone, so a storage hiccup
     * never fails the delete (a stray object is harmless). Hard delete, not is_active = FALSE: the unique
     * index on (project_code, type_of_request, date_received) would otherwise keep blocking that combination.
     */
    public function delete(int|string $id): array
    {
        try {
            $existing = $this->fetchById($id);
            if (!$existing['success']) {
                return $existing;
            }

            $keys = array_column(
                App\ExecuteRows(
                    "SELECT storage_key FROM {$this->tableAttachment} WHERE pricing_request_id = ".(int) $id.' AND storage_key IS NOT NULL',
                    'DB'
                ) ?: [],
                'storage_key'
            );

            App\Execute("DELETE FROM {$this->table} WHERE id = ".(int) $id, 'DB');

            $s3 = $GLOBALS['s3client'] ?? null;
            if ($s3 && $keys) {
                foreach ($keys as $key) {
                    try {
                        $s3->deleteObject(['Bucket' => \env('DO_SPACES_BUCKET', ''), 'Key' => $key]);
                    } catch (\Throwable $e) {
                        // best-effort — the DB rows are already gone
                    }
                }
            }

            return ['success' => true, 'message' => 'Pricing request deleted successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage(), 'http' => 400];
        }
    }

    /** Turns the unique-index violation (a race past isDuplicate()) into the same friendly message. */
    private function failure(\Throwable $e): array
    {
        if (str_contains($e->getMessage(), 'uq_pricing_requests_project_type_received')) {
            return ['success' => false, 'message' => self::DUPLICATE_MESSAGE, 'http' => 422];
        }

        return ['success' => false, 'message' => $e->getMessage(), 'http' => $e instanceof \InvalidArgumentException ? 422 : 400];
    }

    // -----------------------------------------------------------------------
    // Supporting documents
    // -----------------------------------------------------------------------

    /**
     * Resolves each supporting_documents[*].file_index against the flat doc_file_N parts,
     * uploading the file to object storage and replacing file_index with storage_key.
     * Entries carrying a link or an existing attachment_id pass through. Throws on a
     * validation/upload failure — the caller must not save anything then.
     */
    private function uploadSupportingDocFiles(mixed $docs, array $uploadedFiles, string $projectCode): array
    {
        if (!is_array($docs)) {
            return [];
        }

        foreach ($docs as &$doc) {
            if (!is_array($doc) || !isset($doc['file_index'])) {
                continue;
            }
            $file = $uploadedFiles['doc_file_'.$doc['file_index']] ?? null;
            unset($doc['file_index']);
            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                continue;
            }

            if (!isset($GLOBALS['s3client']) || trim((string) \env('DO_SPACES_BUCKET', '')) === '') {
                throw new \InvalidArgumentException('File storage is not configured on this server. Please attach a link instead.');
            }

            $doc['storage_key'] = GlobalService::uploadFileToStorage(
                $file,
                self::ATTACHMENT_KEY_PREFIX.preg_replace('/[^A-Za-z0-9_-]+/', '_', $projectCode),
                self::ATTACHMENT_ALLOWED_EXT,
                self::ATTACHMENT_MAX_BYTES
            );
            if (empty($doc['document_name'])) {
                $doc['document_name'] = pathinfo((string) $file->getClientFilename(), PATHINFO_FILENAME);
            }
        }
        unset($doc);

        return $docs;
    }

    /** Inserts new documents (storage_key from an upload, or a pasted link) and renames existing ones. */
    private function persistAttachments(int $pricingRequestId, array $docs): void
    {
        $userId = (int) App\CurrentUserID();

        foreach ($docs as $doc) {
            if (!is_array($doc)) {
                continue;
            }
            $name = trim((string) ($doc['document_name'] ?? ''));

            // Already-saved document: only its name can change.
            if (!empty($doc['attachment_id'])) {
                if ($name !== '') {
                    App\Execute(
                        "UPDATE {$this->tableAttachment} SET document_name = ".$this->lit(mb_substr($name, 0, 255), 'string').'
                          WHERE attachment_id = '.(int) $doc['attachment_id'].' AND pricing_request_id = '.$pricingRequestId.' AND deleted_at IS NULL',
                        'DB'
                    );
                }
                continue;
            }

            $storageKey = trim((string) ($doc['storage_key'] ?? ''));
            $link = trim((string) ($doc['link'] ?? ''));
            if ($storageKey === '' && $link === '') {
                continue;
            }
            if (mb_strlen($link) > 1000) {
                throw new \InvalidArgumentException('A document link is longer than 1000 characters.');
            }
            if ($name === '') {
                $name = $storageKey !== '' ? pathinfo($storageKey, PATHINFO_FILENAME) : $link;
            }

            App\Execute(
                "INSERT INTO {$this->tableAttachment} (pricing_request_id, document_name, storage_key, external_link, uploaded_by)
                 VALUES ({$pricingRequestId}, ".$this->lit(mb_substr($name, 0, 255), 'string').', '
                    .($storageKey !== '' ? $this->lit($storageKey, 'string') : 'NULL').', '
                    .($link !== '' ? $this->lit($link, 'string') : 'NULL').", {$userId})",
                'DB'
            );
        }
    }

    /** A request's active documents, each with a presigned view_url for an upload (or the raw external_link). */
    public function fetchAttachments(int $pricingRequestId): array
    {
        try {
            $rows = App\ExecuteRows(
                "SELECT attachment_id, pricing_request_id, document_name, storage_key, external_link, uploaded_at
                   FROM {$this->tableAttachment}
                  WHERE pricing_request_id = {$pricingRequestId} AND deleted_at IS NULL
                  ORDER BY uploaded_at ASC, attachment_id ASC",
                'DB'
            ) ?: [];

            foreach ($rows as &$row) {
                $row['view_url'] = $row['external_link'];
                if (!empty($row['storage_key'])) {
                    try {
                        $row['view_url'] = \GetSignedUrl($row['storage_key']);
                    } catch (\Throwable $e) {
                        $row['view_url'] = null; // storage unavailable — the row still lists
                    }
                }
            }
            unset($row);

            return ['success' => true, 'data' => $rows];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Soft-deletes one document (the stored object is kept). */
    public function deleteAttachment(int $attachmentId): array
    {
        try {
            $found = App\ExecuteScalar("SELECT 1 FROM {$this->tableAttachment} WHERE attachment_id = {$attachmentId} AND deleted_at IS NULL", 'DB');
            if (!$found) {
                return ['success' => false, 'message' => "Attachment #{$attachmentId} not found.", 'http' => 404];
            }
            App\Execute("UPDATE {$this->tableAttachment} SET deleted_at = NOW() WHERE attachment_id = {$attachmentId}", 'DB');

            return ['success' => true, 'message' => 'Attachment deleted successfully.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
