<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;
use PHPMaker2024\FIMS\DataType;

/**
 * Supporting Documents (CBA / Memo Request form): uploading a file to object storage and
 * recording it in the database.
 *
 *   uploadFileToStorage()  POST /upload/file          the file itself -> MinIO / S3, returns its object key
 *   multiUpload()          POST /upload/multi-upload  one fims.ip_filemeta row per file + one
 *                                                     fims.file_attachments row each, all sharing a new
 *                                                     multiple_node_id (the caller stores it on its record)
 *   singleUpload()         POST /upload/single-upload one fims.ip_filemeta row
 *   attachmentsFor()       (BudgetAllocationService)  the documents of one multiple_node_id, for the read API
 *
 * Tables: "01 - table/17 - fims_ip_filemeta.sql" and "18 - fims_file_attachments.sql".
 * created_by is never taken from the request body: the route passes the verified (JWT) user.
 */
class FileUploadServices
{
    private string $table                = 'fims.ip_filemeta';
    private string $tableFileAttachments = 'fims.file_attachments';

    /** Object-key prefix of every uploaded file in storage (the rest is year/month/random_name.ext). */
    public const STORAGE_PREFIX = 'FIMS/supporting-documents/';

    /** fims.ip_filemeta.document_name is VARCHAR(225). */
    public const NAME_MAX_LENGTH = 225;

    /** Most files accepted in one /upload/multi-upload call. */
    public const MAX_FILES_PER_BATCH = 50;

    private int $maxUploadBytes = 26214400; // 25 MB
    private array $allowedUploadExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'];

    private const VALIDATION_MESSAGE = 'Validation failed. Please fix the following errors before submitting.';

    // =====================================================================
    //  HELPERS
    // =====================================================================

    /** SQL literal for the uploader: the verified user's id, or NULL. */
    private function createdBySql(?int $createdBy): string
    {
        return ($createdBy !== null && $createdBy > 0) ? (string) $createdBy : 'NULL';
    }

    private function q(string $value): string
    {
        return App\QuotedValue($value, DataType::STRING, 'DB');
    }

    /** Validation errors for one {document_name, link} entry ($label prefixes each message, e.g. "file_data[0]."). */
    private function validateFile(mixed $file, string $label): array
    {
        if (!is_array($file)) {
            return [rtrim($label, '.') . ' must be an object with document_name and link.'];
        }

        $errors = [];
        $name   = isset($file['document_name']) && is_scalar($file['document_name']) ? trim((string) $file['document_name']) : '';
        $link   = isset($file['link']) && is_scalar($file['link']) ? trim((string) $file['link']) : '';

        if ($name === '') {
            $errors[] = "{$label}document_name is required.";
        } elseif (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            $errors[] = "{$label}document_name must be " . self::NAME_MAX_LENGTH . ' characters or fewer.';
        }
        if ($link === '') {
            $errors[] = "{$label}link is required.";
        }

        return $errors;
    }

    /**
     * Where the browser can open a stored document: a short-lived presigned URL for a file we uploaded
     * (its link is a storage key, not a URL), the link itself when it already is a web address (a typed
     * Google Drive link, …), otherwise null (free-text reference).
     */
    public function viewUrlFor(?string $link): ?string
    {
        $link = trim((string) $link);
        if ($link === '') {
            return null;
        }
        if (str_starts_with($link, self::STORAGE_PREFIX)) {
            try {
                return \function_exists('GetSignedUrl') ? \GetSignedUrl($link, '+1 hour') : null;
            } catch (\Throwable $e) {
                App\LogError('FileUploadServices::viewUrlFor ' . $e->getMessage());

                return null;
            }
        }

        return preg_match('#^https?://#i', $link) ? $link : null;
    }

    // -----------------------------------------------------------------------
    // Single record — /upload/single-upload
    //
    // Input:  document_name  string  required (<= 225 characters)
    //         link           string  required
    // Returns the new file's node_id as file_node_id.
    // -----------------------------------------------------------------------
    public function singleUpload(array $body, ?int $createdBy = null): array
    {
        try {
            $errors = $this->validateFile($body, '');
            if ($errors) {
                return ['success' => false, 'message' => self::VALIDATION_MESSAGE, 'errors' => $errors];
            }

            $row = App\ExecuteRow(
                "INSERT INTO {$this->table} (document_name, link, created_by)
                 VALUES (" . $this->q(trim((string) $body['document_name'])) . ', ' . $this->q(trim((string) $body['link'])) . ', ' . $this->createdBySql($createdBy) . ')
                 RETURNING id, node_id',
                'DB'
            );

            if (empty($row['id'])) {
                return ['success' => false, 'message' => 'Insert succeeded but could not retrieve new record ID.'];
            }

            return [
                'success'      => true,
                'message'      => 'File metadata saved successfully.',
                'file_node_id' => $row['node_id'],
            ];
        } catch (\Throwable $e) {
            App\LogError('FileUploadServices::singleUpload ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to save file metadata.'];
        }
    }

    // -----------------------------------------------------------------------
    // Real file upload — /upload/file
    //
    // Streams the uploaded file to object storage (MinIO / S3, via the shared $s3client from
    // _shared/storage.php) and returns the object key to store as the "link" — a stable
    // reference; the presigned preview_url expires after an hour, so view URLs are generated
    // again whenever a record is read (see attachmentsFor()).
    //
    // The 25MB limit and the extension allow-list are re-checked here although the form
    // already gates on both.
    // -----------------------------------------------------------------------
    public function uploadFileToStorage($uploadedFile): array
    {
        if (!$uploadedFile instanceof \Psr\Http\Message\UploadedFileInterface) {
            return ['success' => false, 'message' => 'No file was received.'];
        }

        if ($uploadedFile->getError() !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'Upload failed (error code ' . $uploadedFile->getError() . ').'];
        }

        if ($uploadedFile->getSize() > $this->maxUploadBytes) {
            return ['success' => false, 'message' => 'File exceeds the 25MB size limit.'];
        }

        $originalName = $uploadedFile->getClientFilename() ?: 'document';
        $ext          = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($ext, $this->allowedUploadExt, true)) {
            return ['success' => false, 'message' => "File type '.{$ext}' is not allowed."];
        }

        // Storage is configured through DO_SPACES_* in .env (see _shared/storage.php). Say so plainly when it is not,
        // instead of letting every upload die with an opaque S3 error.
        if (trim((string) env('DO_SPACES_BUCKET', '')) === '' || trim((string) env('DO_SPACES_KEY', '')) === '' || trim((string) env('DO_SPACES_SECRET', '')) === '') {
            return ['success' => false, 'message' => 'File storage is not configured on this server (DO_SPACES_* in .env). Please attach a link instead.'];
        }

        $baseName  = preg_replace('/[^A-Za-z0-9_-]+/', '_', pathinfo($originalName, PATHINFO_FILENAME));
        $objectKey = self::STORAGE_PREFIX . date('Y/m') . '/' . bin2hex(random_bytes(8)) . '_' . $baseName . '.' . $ext;

        global $s3client;
        if ($s3client === null) {
            return ['success' => false, 'message' => 'File storage is not available on this server. Please attach a link instead.'];
        }

        try {
            $s3client->putObject([
                'Bucket'      => env('DO_SPACES_BUCKET', ''),
                'Key'         => $objectKey,
                'Body'        => $uploadedFile->getStream()->detach(),
                'ContentType' => $uploadedFile->getClientMediaType() ?: 'application/octet-stream',
            ]);
        } catch (\Throwable $e) {
            App\LogError('FileUploadServices::uploadFileToStorage ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to store the file. Please try again or attach a link instead.'];
        }

        return [
            'success'       => true,
            'message'       => 'File uploaded successfully.',
            'document_name' => pathinfo($originalName, PATHINFO_FILENAME),
            'link'          => $objectKey,
            'preview_url'   => $this->viewUrlFor($objectKey),
            'size'          => $uploadedFile->getSize(),
        ];
    }

    // -----------------------------------------------------------------------
    // Several files at once — /upload/multi-upload
    //
    // Input:  file_data  array  required, 1..50 entries, each:
    //           document_name  string  required (<= 225 characters)
    //           link           string  required (the object key from /upload/file, or a typed link)
    //
    // 1. one new multiple_node_id (UUID) for the whole batch;
    // 2. per file: an INSERT into fims.ip_filemeta (-> its node_id) and an INSERT into
    //    fims.file_attachments (multiple_node_id, file_node_id = that node_id);
    // all in one transaction, so a failure part-way leaves nothing behind.
    // Returns multiple_node_id — the caller stores it on its record.
    // -----------------------------------------------------------------------
    public function multiUpload(array $body, ?int $createdBy = null): array
    {
        $files = $body['file_data'] ?? [];
        if (empty($files) || !is_array($files)) {
            return ['success' => false, 'message' => "Field 'file_data' is required and must be a non-empty array."];
        }
        if (count($files) > self::MAX_FILES_PER_BATCH) {
            return ['success' => false, 'message' => 'At most ' . self::MAX_FILES_PER_BATCH . ' files can be saved at once.'];
        }

        // Every entry is checked before the database is touched.
        $errors = [];
        foreach ($files as $index => $file) {
            array_push($errors, ...$this->validateFile($file, "file_data[{$index}]."));
        }
        if ($errors) {
            return ['success' => false, 'message' => self::VALIDATION_MESSAGE, 'errors' => $errors];
        }

        $by = $this->createdBySql($createdBy);

        try {
            App\Execute('BEGIN', 'DB');

            $multipleNodeId = (string) App\ExecuteScalar('SELECT gen_random_uuid()::TEXT', 'DB');

            $saved = [];
            foreach ($files as $file) {
                $fileRow = App\ExecuteRow(
                    "INSERT INTO {$this->table} (document_name, link, created_by)
                     VALUES (" . $this->q(trim((string) $file['document_name'])) . ', ' . $this->q(trim((string) $file['link'])) . ", {$by})
                     RETURNING id, node_id",
                    'DB'
                );
                if (empty($fileRow['id'])) {
                    throw new \RuntimeException('Failed to insert a file into ip_filemeta.');
                }

                $fileNodeId = (string) $fileRow['node_id'];
                App\Execute(
                    "INSERT INTO {$this->tableFileAttachments} (multiple_node_id, file_node_id, created_by)
                     VALUES (" . $this->q($multipleNodeId) . '::uuid, ' . $this->q($fileNodeId) . "::uuid, {$by})",
                    'DB'
                );

                $saved[] = ['id' => (int) $fileRow['id'], 'node_id' => $fileNodeId];
            }

            App\Execute('COMMIT', 'DB');

            return [
                'success'          => true,
                'message'          => count($saved) . ' file(s) saved successfully.',
                'multiple_node_id' => $multipleNodeId,
            ];
        } catch (\Throwable $e) {
            try {
                App\Execute('ROLLBACK', 'DB');
            } catch (\Throwable $ignored) {
                // nothing more to undo
            }
            App\LogError('FileUploadServices::multiUpload ' . $e->getMessage());

            return ['success' => false, 'message' => 'Failed to save the files.'];
        }
    }

    // -----------------------------------------------------------------------
    // The documents of one upload batch — what GET /budget_allocation/read/{id} returns as
    // file_attachments[]. Only active rows (neither the attachment nor its file soft-deleted), oldest first:
    //   [ { id, multiple_node_id, file_node_id, created_by, created_at, deleted_at,
    //       file_meta: { id, node_id, document_name, link, view_url, deleted_at } }, ... ]
    // view_url is a fresh presigned URL for an uploaded file (see viewUrlFor()).
    // -----------------------------------------------------------------------
    public function attachmentsFor(?string $multipleNodeId): array
    {
        $multipleNodeId = trim((string) $multipleNodeId);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $multipleNodeId)) {
            return [];
        }

        try {
            $rows = App\ExecuteRows(
                "SELECT fa.id, fa.multiple_node_id, fa.file_node_id, fa.created_by, fa.created_at, fa.deleted_at,
                        fm.id AS file_id, fm.node_id AS file_node, fm.document_name, fm.link, fm.deleted_at AS file_deleted_at
                 FROM {$this->tableFileAttachments} fa
                 JOIN {$this->table} fm ON fm.node_id = fa.file_node_id
                 WHERE fa.multiple_node_id = " . $this->q(strtolower($multipleNodeId)) . '::uuid
                   AND fa.deleted_at IS NULL AND fm.deleted_at IS NULL
                 ORDER BY fa.id',
                'DB'
            ) ?: [];
        } catch (\Throwable $e) {
            App\LogError('FileUploadServices::attachmentsFor ' . $e->getMessage());

            return [];
        }

        return array_map(fn (array $r) => [
            'id'               => (int) $r['id'],
            'multiple_node_id' => $r['multiple_node_id'],
            'file_node_id'     => $r['file_node_id'],
            'created_by'       => $r['created_by'] !== null ? (int) $r['created_by'] : null,
            'created_at'       => $r['created_at'],
            'deleted_at'       => $r['deleted_at'],
            'file_meta'        => [
                'id'            => (int) $r['file_id'],
                'node_id'       => $r['file_node'],
                'document_name' => $r['document_name'],
                'link'          => $r['link'],
                'view_url'      => $this->viewUrlFor($r['link']),
                'deleted_at'    => $r['file_deleted_at'],
            ],
        ], $rows);
    }
}
