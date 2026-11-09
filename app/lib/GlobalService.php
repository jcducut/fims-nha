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
 * - getUserRcId() / getUserRcScope() / getRcIdsInScope()  RC (Responsibility Center)
 *   helpers for fims.budget_req row-level scoping, keyed on uac.users.rc_id. Same
 *   "reads the database, lives here by request" carve-out as generateProgramCode() —
 *   shared by BudgetReqService and its route layer.
 */
class GlobalService
{
    /**
     * Builds the next program code: "<year>-<rc_code>-<NNN>", e.g. 2026-35-074-0000001-000-101.
     *
     *  - year    : the current year, or $year when given.
     *  - rc_code : fims.responsibility_code.rc_code for the logged-in user's uac.users.rc_id
     *              ($userId, else the current user).
     *  - NNN     : last 3 digits of the highest existing fims.program.program_code that ends in
     *              "-NNN", plus one, zero-padded to 3 digits. Codes that don't end in "-NNN"
     *              (e.g. "SAMPLE-1") are ignored, and an empty table starts at 001.
     *
     * The number is a suggestion read at call time — nothing is reserved. Two callers asking at
     * the same moment get the same value; program_code's primary key makes the second insert fail.
     *
     * @throws \InvalidArgumentException when no user is logged in, the user has no rc_id, or
     *                                   the 3-digit sequence is exhausted (999).
     */
    public static function generateProgramCode(?int $userId = null, ?int $year = null): string
    {
        $userId = $userId ?: (int) App\CurrentUserID();
        if ($userId <= 0) {
            throw new \InvalidArgumentException('No logged-in user — cannot build the RC part of the program code.');
        }

        $rcCode = trim((string) App\ExecuteScalar(
            'SELECT rc.rc_code FROM uac.users u ' .
            'JOIN fims.responsibility_code rc ON rc.rc_id = u.rc_id ' .
            'WHERE u.user_id = ' . $userId,
            'DB'
        ));
        if ($rcCode === '') {
            throw new \InvalidArgumentException('Your user account has no RC code (users.rc_id). Ask an administrator to set it.');
        }

        $last = (int) App\ExecuteScalar(
            "SELECT COALESCE(MAX(CAST(SUBSTRING(program_code FROM '-([0-9]{3})$') AS INTEGER)), 0) FROM fims.program",
            'DB'
        );
        if ($last >= 999) {
            throw new \InvalidArgumentException('The 3-digit program sequence is exhausted (999).');
        }

        return sprintf('%d-%s-%03d', $year ?: (int) date('Y'), $rcCode, $last + 1);
    }

    /**
     * The RC (Responsibility Center) $userId belongs to, or null if unset.
     * Unconditional — no superadmin bypass — since every creating user, admin
     * included, must have their own rc_id to create a fims.budget_req (there
     * is no manual RC picker; the backend auto-detects it from this column).
     *
     * Takes the caller's JWT-attribute user id, same as generateProgramCode()
     * and getUserRcScope() — falls back to App\CurrentUserID() only when
     * $userId is falsy, since routes here are JWT-authenticated and
     * CurrentUserID()'s session-backed Security() singleton is never
     * populated on its own.
     *
     * @throws \InvalidArgumentException when no user is logged in.
     */
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
}
