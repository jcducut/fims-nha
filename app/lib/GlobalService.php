<?php

namespace PHPMaker2024\FIMS\Service;

use PHPMaker2024\FIMS as App;

/**
 * GlobalService
 *
 * Holds ONLY reusable helper / utility functions that are likely to be used by
 * multiple services (e.g. formatting, date handling, generic validation).
 *
 * Rules:
 * - Add a function here only when it is (or will clearly be) shared by 2+ services.
 * - Feature-specific logic and database operations belong in that feature's own
 *   service file, not here.
 * - Keep functions generic and stateless; do not put business rules here.
 * - Code shared across modules (all FIMS apps) belongs in `_shared/`, not here.
 *
 * Functions:
 * - generateProgramCode()  next program code "YYYY-<rc_code>-NNN" (see below).
 *   NOTE: this one reads the database, which the rules above would normally keep in
 *   the feature's own service (ProgramService). It lives here by request, so it can
 *   be called from any service or route: GlobalService::generateProgramCode().
 * - generateProjectCode()  next project code "YYYY-<rc_id>-<prog3>-<subprog4>-<series4>"
 *   (see below). Same "reads the database, lives here by request" carve-out as
 *   generateProgramCode() — could otherwise live in ProjectService.
 * - getUserRcId() / getUserRcScope() / getRcIdsInScope()  RC (Responsibility Center)
 *   helpers for fims.budget_req row-level scoping, keyed on uac.users.rc_id. Same
 *   "reads the database, lives here by request" carve-out as generateProgramCode() —
 *   shared by BudgetReqService and its route layer.
 * - generateBudgetReferenceNumber()  next budget request id "BP-NNN" (see below).
 *   fims.budget_req.budget_reference_number is this string itself now (no more
 *   SERIAL auto-increment — see "15 - budget_req_reference_number_to_string.sql"),
 *   so this must be called inside BudgetReqService's open transaction before
 *   every new header INSERT. Same "lives here by request" carve-out as the
 *   other generate*Code() functions.
 * - uploadFileToStorage()  validates and uploads ONE file to object storage
 *   (DO Spaces / MinIO, via the $s3client global _shared/storage.php wires
 *   into every app/ page/route) and returns its object key. Deliberately
 *   ignorant of WHY a caller is uploading something (no "project"/
 *   "attachment" concept here) — a feature-specific service (e.g.
 *   BudgetReqService::uploadSupportingDocFiles()) decides the key prefix,
 *   loops over its own files, and persists whatever metadata it needs
 *   against the returned key. Lives here by request, as the one shared
 *   upload primitive every future feature should call instead of each
 *   reimplementing its own S3Client validation/putObject logic.
 */
class GlobalService
{
    /**
     * Builds the next program code: a plain 3-digit sequence, starting at "100", then "101", "102", …
     *
     * It is the highest existing fims.program.program_code that is exactly 3 digits, plus one. Codes in
     * any other shape (e.g. "PROG-001", or older "2026-35-074-…-001" codes) are ignored, and the
     * sequence never starts below 100.
     *
     * $userId and $year are no longer used (the code has no RC or year part); the parameters stay only
     * so existing callers (ProgramService::create, GET /program/next-code) keep working.
     *
     * The number is a suggestion read at call time — nothing is reserved. Two callers asking at
     * the same moment get the same value; program_code's primary key makes the second insert fail.
     *
     * @throws \InvalidArgumentException when the 3-digit sequence is exhausted (999).
     */
    public static function generateProgramCode(?int $userId = null, ?int $year = null): string
    {
        $last = max(99, (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(program_code AS INTEGER)), 0) FROM fims.program WHERE program_code ~ '^[0-9]{3}$'",
            'DB'
        ));
        if ($last >= 999) {
            throw new \InvalidArgumentException('The 3-digit program sequence is exhausted (999).');
        }

        return sprintf('%03d', $last + 1);
    }

    /**
     * Builds the next project code:
     * "<year>-<rc_id>-<prog3>-<subprog4>-<series4>", e.g. 2026-RC-000-001-0001-0001.
     *
     *  - year     : the current year, or $year when given.
     *  - rc_id    : the logged-in user's uac.users.rc_id ($userId, else the current
     *               user) taken as-is — e.g. "RC-000" — via getUserRcId(), not a
     *               digit extraction like the other two segments.
     *  - prog3    : last 3 digits of the trailing numeric run in $programCode
     *               (the Program this project belongs to) — e.g. "PROG-001" -> "001".
     *  - subprog4 : last 4 digits of the trailing numeric run in $subProgramCode
     *               (the Sub-Program this project belongs to).
     *  - series4  : 4-digit sequence — the Nth project under that exact
     *               "<year>-<rc_id>-<prog3>-<subprog4>-" combination, not a
     *               table-wide counter (mirrors generateProgramCode()'s
     *               "number is a suggestion, nothing reserved" caveat).
     *
     * Neither $programCode nor $subProgramCode is required to end in exactly
     * 3/4 digits today (see trailingDigits()) — a shorter trailing run is
     * zero-padded, a longer one truncated to its rightmost digits, and a
     * code with no trailing digits at all degrades to all zeros for that
     * segment. This builds a suggestion, not a validation gate.
     *
     * @throws \InvalidArgumentException when no user is logged in, the user has no
     *                                    rc_id, or the 4-digit series is exhausted (9999).
     */
    public static function generateProjectCode(string $programCode, string $subProgramCode, ?int $userId = null, ?int $year = null): string
    {
        $userId = $userId ?: (int) App\CurrentUserID();
        $rcId   = self::getUserRcId($userId);
        if ($rcId === null) {
            throw new \InvalidArgumentException('Your user account has no RC id (users.rc_id). Ask an administrator to set it.');
        }

        $prefix = sprintf(
            '%d-%s-%s-%s-',
            $year ?: (int) date('Y'),
            $rcId,
            self::trailingDigits($programCode, 3),
            self::trailingDigits($subProgramCode, 4)
        );

        $last = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(project_code FROM '-([0-9]{4})$') AS INTEGER)), 0) " .
            'FROM fims.project WHERE project_code LIKE ' . App\QuotedValue($prefix . '%', App\DataType::STRING, 'DB'),
            'DB'
        );
        if ($last >= 9999) {
            throw new \InvalidArgumentException('The 4-digit project sequence is exhausted (9999) for this program/sub-program this year.');
        }

        return $prefix . sprintf('%04d', $last + 1);
    }

    /**
     * The trailing contiguous run of digits in $code, right-aligned to exactly
     * $width characters: zero-padded on the left when shorter, truncated to
     * the rightmost $width digits when longer, or $width zeros when $code has
     * no trailing digits at all.
     */
    private static function trailingDigits(string $code, int $width): string
    {
        if (!preg_match('/([0-9]+)$/', $code, $m)) {
            return str_repeat('0', $width);
        }
        $digits = $m[1];
        if (strlen($digits) > $width) {
            $digits = substr($digits, -$width);
        }

        return str_pad($digits, $width, '0', STR_PAD_LEFT);
    }

    /**
     * Builds the next budget request id: "BP-NNN", e.g. "BP-007". NNN is the
     * highest existing fims.budget_req.budget_reference_number matching
     * "^BP-[0-9]+$", plus one, zero-padded to 3 digits — widens rather than
     * truncating once the count outgrows 999 (e.g. "BP-1000"), same
     * philosophy as generateProjectCode()'s series segment, since this
     * counter runs for the life of the whole app, not scoped to one
     * program/fiscal year.
     *
     * Must be called inside the caller's own open transaction, immediately
     * before the fims.budget_req INSERT that uses its result — same
     * "suggestion read at call time, nothing reserved" caveat as every other
     * generate*Code() function here (the column's PRIMARY KEY is what
     * actually prevents two concurrent callers from both succeeding with
     * the same value).
     */
    public static function generateBudgetReferenceNumber(): string
    {
        $next = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(budget_reference_number FROM 4) AS INTEGER)), 0) + 1
             FROM fims.budget_req WHERE budget_reference_number ~ '^BP-[0-9]+$'",
            'DB'
        );

        return 'BP-' . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Where sub-program codes for a program start: ['prefix' => '100', 'next' => 3].
     *
     *  - prefix : the selected program code itself (program "100" -> sub-programs "100-001", "100-002", …).
     *  - next   : highest existing fims.sub_program.sub_program_code of the form "<prefix>-NNN", plus one
     *             (1 when there is none, so every program starts again at 001). sub_program_code is the
     *             table's primary key and is built from the program code, so looking up by prefix also
     *             keeps one program's numbers from colliding with another's.
     *
     * Codes are formatted "<prefix>-<NNN>" — see formatSubProgramCode(). A caller adding several line
     * items takes `next` once and counts up itself. Nothing is reserved.
     *
     * @throws \InvalidArgumentException when the program code is empty, too long to leave room for "-NNN"
     *                                   in the 64-character sub_program_code, or the sequence is exhausted.
     */
    public static function nextSubProgramNumber(string $programCode): array
    {
        $prefix = trim($programCode);
        if ($prefix === '') {
            throw new \InvalidArgumentException('program_code is required.');
        }
        // fims.sub_program.sub_program_code is VARCHAR(64): the prefix plus "-NNN" (4 characters) must fit. (The limit used
        // to be 16, from when the column was 20 wide — which refused every program whose code is a long
        // generated one, e.g. "2026-35-074-0000000-032-003".)
        if (strlen($prefix) > 60) {
            throw new \InvalidArgumentException("Program code '{$prefix}' is too long to build a sub-program code from (max 60 characters).");
        }

        $last = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(sub_program_code FROM '-([0-9]{3})$') AS INTEGER)), 0)
             FROM fims.sub_program
             WHERE sub_program_code ~ " . App\QuotedValue('^' . preg_quote($prefix, '/') . '-[0-9]{3}$', App\DataType::STRING, 'DB'),
            'DB'
        );
        if ($last >= 999) {
            throw new \InvalidArgumentException("The 3-digit sub-program sequence for program {$prefix} is exhausted (999).");
        }

        return ['prefix' => $prefix, 'next' => $last + 1];
    }

    public static function formatSubProgramCode(string $prefix, int $number): string
    {
        return sprintf('%s-%03d', $prefix, $number);
    }
    
    /** The RC (Responsibility Center) id of $userId (uac.users.rc_id), or null if unset. No superadmin bypass. */
    public static function getUserRcId(int $userId = 0): ?string
    {
        $userId = $userId ?: (int) App\CurrentUserID();
        if ($userId <= 0) {
            throw new \InvalidArgumentException('No logged-in user — cannot determine RC.');
        }
        $rcId = trim((string) App\ExecuteScalar(
            'SELECT rc_id FROM uac.users WHERE user_id = ' . $userId,
            'DB'
        ));

        return $rcId !== '' ? $rcId : null;
    }

    /**
     * Resolves the RC a user is restricted to, for row-level scoping of
     * fims.budget_req when LISTING (as opposed to getUserRcId(), used when
     * creating). Superadmin (user_level_id = -1), a missing user row, or any
     * DB error all mean is_admin = true, rc_id = null (unrestricted/sees-all).
     * A non-admin user whose rc_id is NULL is also unrestricted — mirrors the
     * region_code convention already used by ChartOfAccountsService::getUserScope().
     *
     * Falls back to App\CurrentUserID() when $userId is falsy, same as
     * getUserRcId() — otherwise an unresolved user id here fails OPEN (reads
     * as "no row found" => is_admin = true => sees every RC), which is the
     * wrong direction for a security check to fail in.
     */
    public static function getUserRcScope(int $userId = 0): array
    {
        $userId = $userId ?: (int) App\CurrentUserID();

        try {
            $row = App\ExecuteRow(
                "SELECT u.user_level_id, u.rc_id, rc.rc_name
                 FROM uac.users u
                 LEFT JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id
                 WHERE u.user_id = " . App\QuotedValue($userId, App\DataType::NUMBER, 'DB'),
                'DB'
            );

            $isAdmin = !$row || App\SameText((string) $row['user_level_id'], '-1');

            return [
                'is_admin' => $isAdmin,
                'rc_id'    => $isAdmin ? null : ($row['rc_id'] ?? null),
                'rc_name'  => $isAdmin ? null : ($row['rc_name'] ?? null),
            ];
        } catch (\Exception $e) {
            return ['is_admin' => true, 'rc_id' => null, 'rc_name' => null];
        }
    }

    /**
     * Expands one RC id into itself plus every descendant's id (a parent RC
     * sees its children's rows too — e.g. a Region 3 user also sees Region
     * 3's district offices), via a recursive walk over
     * fims.responsibility_code.parent_rc_id.
     */
    public static function getRcIdsInScope(string $rcId): array
    {
        $rows = App\ExecuteRows(
            "WITH RECURSIVE rc_tree AS (
                SELECT rc_id FROM fims.responsibility_code WHERE rc_id = " . App\QuotedValue($rcId, App\DataType::STRING, 'DB') . '
                UNION ALL
                SELECT child.rc_id FROM fims.responsibility_code child
                JOIN rc_tree ON child.parent_rc_id = rc_tree.rc_id
            )
            SELECT rc_id FROM rc_tree',
            'DB'
        ) ?: [];

        return array_map(fn ($r) => (string) $r['rc_id'], $rows);
    }

    /**
     * Validates and uploads one file to object storage, returning its
     * resulting object key (caller stores that key — NOT the file itself —
     * against whatever record it belongs to).
     *
     * @param array $allowedExtensions lowercase, no leading dot
     * @throws \InvalidArgumentException on a disallowed extension or an oversized file
     *                                    (caller's own try/catch turns this into a 4xx)
     * @throws \Aws\Exception\AwsException on an actual storage failure (network/credentials/etc.)
     */
    public static function uploadFileToStorage(
        \Psr\Http\Message\UploadedFileInterface $file,
        string $keyPrefix,
        array $allowedExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif'],
        int $maxBytes = 20 * 1024 * 1024
    ): string {
        global $s3client;

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Upload failed (error code ' . $file->getError() . ').');
        }
        if ($file->getSize() > $maxBytes) {
            throw new \InvalidArgumentException('File "' . $file->getClientFilename() . '" exceeds the ' . round($maxBytes / 1024 / 1024) . ' MB limit.');
        }

        $clientName = $file->getClientFilename();
        $ext        = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('File type not allowed: .' . $ext);
        }

        $safeName  = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $clientName);
        $objectKey = rtrim($keyPrefix, '/') . '/' . $safeName;

        $s3client->putObject([
            'Bucket'      => \env('DO_SPACES_BUCKET', ''),
            'Key'         => $objectKey,
            'Body'        => $file->getStream()->getContents(),
            'ContentType' => $file->getClientMediaType() ?: 'application/octet-stream',
        ]);

        return $objectKey;
    } 
    /*     * The one Responsibility Center that REVIEWS every other RC's budget request:
     * FMD, the Financial Management Department. It is a central department with no
     * child RCs, so the usual "my RC + its descendants" scoping would leave its list
     * empty. Its users get the list of all the Regional / District requests — see
     * resolveBudgetReqRcFilter() and GET /fmd/budget_req/list.
     *
     * FMD is identified by its rc_code — the organisation's own code for it — and NOT by its
     * rc_id. The id is a surrogate key whose format differs between datasets ("013" in the sample
     * data, "RC-013" in others), and comparing it with a literal silently stopped recognising FMD:
     * its users got the plain list, no central results, and a 403 from the FMD route.
     */
    public const CENTRAL_REVIEW_RC_CODE = '35-074-0000000-032';

    /**
     * The Regional and District offices — the requesters the central review RC reviews: every RC whose
     * rc_code sorts at or after this value (the central departments, FMD included, sort before it).
     * Used as the `rc_code_from` filter of BudgetReqService::fetchAlls(), e.g. by GET /fmd/budget_req/list.
     */
    public const CENTRAL_REVIEW_RC_CODE_FROM = '35-074-0000001-000';

    /**
     * Is this RC (by rc_id) the central review RC, i.e. is its rc_code CENTRAL_REVIEW_RC_CODE?
     * Looked up once per rc_id per request. null / '' / an unknown id is false, and so is a failed lookup
     * (logged, not cached): when we can't tell, we don't grant the central view.
     */
    public static function isCentralReviewRc(?string $rcId): bool
    {
        if ($rcId === null || trim($rcId) === '') {
            return false;
        }

        static $known = [];
        if (!array_key_exists($rcId, $known)) {
            try {
                $code = App\ExecuteScalar(
                    'SELECT rc_code FROM fims.responsibility_code WHERE rc_id = ' . App\QuotedValue($rcId, App\DataType::STRING, 'DB'),
                    'DB'
                );
                $known[$rcId] = trim((string) $code) === self::CENTRAL_REVIEW_RC_CODE;
            } catch (\Throwable $e) {
                App\LogError('GlobalService::isCentralReviewRc could not look up rc_id ' . $rcId . ' — ' . $e->getMessage());

                return false;
            }
        }

        return $known[$rcId];
    }

    /**
     * The rc_id to filter a fims.budget_req LIST by ('' = every RC).
     *
     *  - a user with an RC is limited to it (and its descendants) — a client-supplied rc_id is ignored;
     *  - a superadmin / user without an RC may narrow by the rc_id query parameter;
     *  - the central review RC (isCentralReviewRc) sees every RC, but only when the request says
     *    central=1 (the FMD list). The parameter alone grants nothing: the caller's own RC is
     *    checked here, server-side, so any other user who sends it stays scoped.
     */
    public static function resolveBudgetReqRcFilter(array $scope, array $params): string
    {
        $scopeRcId = $scope['rc_id'] ?? null;

        if (self::isCentralReviewRc($scopeRcId) && (string) ($params['central'] ?? '') === '1') {
            return '';
        }

        return (string) ($scopeRcId ?? ($params['rc_id'] ?? ''));
    }
}
