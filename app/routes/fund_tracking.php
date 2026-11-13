<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\FundTrackingServices;

// =============================================================================
// Route file : fund_tracking.php
// Service    : FundTrackingServices (app/services/fund_trackingServices.php)
// Table      : fims.fund_tracking — lookups fims.fund_tracking_type,
//              fims.nature_of_fund, fims.agency, fims.sub_program
// Soft-deleted via deleted_at. user_id is the verified (JWT) user — it is
// never read from the request body.
// =============================================================================

$Services = new FundTrackingServices();

// The verified (JWT) user's id, or null when the request carries none. Only the
// routes behind $jwtMiddleware have the `user_id` attribute.
$fundTrackingUserId = function ($request): ?int {
    $id = (int) $request->getAttribute('user_id');

    return $id > 0 ? $id : null;
};

// -----------------------------------------------------------------------
// GET /fund_tracking/all — full list, no pagination (public — no JWT)
// Query params: search — matched against reference_number (ILIKE, partial)
//               lean   — 1/true: skip the nested parent/children relations
//                        (pickers that only need the record itself)
// -----------------------------------------------------------------------
$app->get('/fund_tracking/all', function ($request, $response, $args) use ($Services) {
    try {
        $params = $request->getQueryParams();
        $search = $params['search'] ?? null;
        $lean = in_array(strtolower((string) ($params['lean'] ?? '')), ['1', 'true', 'yes'], true);
        $result = $Services->fetchAlls(null, 10, $search, $lean);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// -----------------------------------------------------------------------
// GET /fund_tracking/list — paginated + filtered list
// Query params: page, limit, tracking_type_id,
//               fiscal_year — one fiscal year (e.g. 2026),
//               fiscal_year_from / fiscal_year_to — inclusive year range
//               (e.g. 2010-2012), matched against fund_tracking.fiscal_year.
//               Independent of fiscal_year; both may be applied.
//               sub_program_code — a sub-program (fims.sub_program),
//               program_code — every record whose sub-program belongs to
//               that program,
//               status_id — a fims.settings_status id,
//               reference_number — ILIKE partial match, e.g.
//               ?page=1&limit=10&search=009911 (also accepts reference_number
//               or the legacy saro_reference_number as param names),
//               type (repeatable — e.g. ?type=SARO&type=NCA — filters to
//               only those tracking types, matched by code or id),
//               nature_of_fund — nature of fund id or name (e.g.
//               ?nature_of_fund=SUPPLEMENTAL), any tracking type,
//               exclude_nature_of_fund — the opposite: records of that nature
//               (id or name) are left out, as are such records among each row's
//               children; records with no nature of fund are kept,
//               tracking_group — with nature_of_fund: SARO_NCA or NO_SARO_NCA, keeps
//               only the records tied to a SARO/NCA (own tracking type or parent's) or not,
//               supplemental_status — with nature_of_fund: PENDING, APPROVED or
//               DENIED (PENDING = In Process / At NDRRMC / At OP),
//               root_type — with roots_only, only root rows of this tracking
//               type; `type` still decides which children come back, e.g.
//               ?type=TRUST&type=OR&roots_only=1&root_type=TRUST
//               lists Trust Funds (not parentless ORs), each with its ORs.
//               agency_or_program_search — free text against the agency name
//               and the sub-program / program name or code.
// -----------------------------------------------------------------------
$app->get('/fund_tracking/list', function ($request, $response, $args) use ($Services) {
    try {
        $params = $request->getQueryParams();

        // ?type=SARO&type=NCA — PHP's native query-string parsing collapses
        // repeated non-array keys down to the last value, so getQueryParams()
        // alone only ever sees "NCA". Parse the raw query string ourselves
        // to collect every occurrence of `type`.
        $types = $Services->parseRepeatedParam($request->getUri()->getQuery(), 'type');
        if (!empty($types)) {
            $params['type'] = $types;
        }

        $result = $Services->fetchFiltered($params);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// -----------------------------------------------------------------------
// GET /fund_tracking/read/{id} — single record with nested JOINs
// -----------------------------------------------------------------------
$app->get('/fund_tracking/read/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->fetchById($args['id']);
        $status = $result['success'] ? 200 : 404;

        return $response->withJson($result, $status);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// -----------------------------------------------------------------------
// POST /fund_tracking/add — create new record
// Body (JSON): tracking_type_id (required), fiscal_year, reference_number
//              (required unless the nature of fund is GAAAO, which generates
//              it), sub_program_code, nature_of_fund_id, name, date, amount,
//              unreleased, bank_account_no, agency_name (the agency's name as
//              text), remarks, purpose, multiple_node_id, status_id, parent_id,
//              and optionally `supplemental` {…}.
//              And, for a SARO only, optionally `ncas`: [{ reference_number
//              (required), name, date, amount, unreleased, bank_account_no,
//              fiscal_year, sub_program_code, nature_of_fund_id }, …]
//              — NCA records created under the new SARO (parent_id = the SARO).
//              fiscal_year, sub_program_code and nature_of_fund_id are the SARO's
//              unless the item has its own: a key left out inherits the SARO's
//              value, a value overrides it (and must exist), an explicit null
//              means none. The SARO and all its NCAs are saved in one transaction:
//              if any NCA is invalid, nothing is saved (400, the message names
//              "NCA #n").
//              And, for a Trust Fund only, optionally `ors`: [{ reference_number
//              (required), name, date, amount, unreleased, bank_account_no,
//              remarks, fiscal_year, sub_program_code, nature_of_fund_id,
//              agency_name }, …] — OR records created under the new Trust Fund
//              (parent_id = the Trust Fund), same rules as `ncas`: a key left out
//              inherits the Trust Fund's value (fiscal year, sub-program, nature
//              of fund and agency), one saved transaction, "OR #n" in the message.
// user_id is the JWT user. Returns { success, message, id, nca_ids, or_ids } —
// nca_ids / or_ids in the order the children were sent.
// -----------------------------------------------------------------------
$app->post('/fund_tracking/add', function ($request, $response, $args) use ($Services, $fundTrackingUserId) {
    try {
        $body = $request->getParsedBody() ?? [];
        $result = $Services->create($body, $fundTrackingUserId($request));
        $status = $result['success'] ? 201 : 400;

        return $response->withJson($result, $status);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// -----------------------------------------------------------------------
// PATCH /fund_tracking/update/{id} — update existing record
// Partial: only the fields sent change. For the optional ids / codes
// (sub_program_code, fiscal_year, parent_id, multiple_node_id,
// nature_of_fund_id, status_id) '' leaves the value alone and null clears it.
// -----------------------------------------------------------------------
$app->patch('/fund_tracking/update/{id}', function ($request, $response, $args) use ($Services, $fundTrackingUserId) {
    try {
        $body = $request->getParsedBody() ?? [];
        $result = $Services->update($args['id'], $body, $fundTrackingUserId($request));
        $status = $result['success'] ? 200 : 400;

        return $response->withJson($result, $status);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// -----------------------------------------------------------------------
// DELETE /fund_tracking/delete/{id} — soft delete (stamps deleted_at).
// 404 when the record doesn't exist; 409 while other records still have it
// as their parent_id.
// -----------------------------------------------------------------------
$app->delete('/fund_tracking/delete/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->delete($args['id']);
        $status = $result['success'] ? 200 : ($result['httpCode'] ?? 404);

        return $response->withJson($result, $status);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// -----------------------------------------------------------------------
// POST /fund_tracking/import — bulk import (SARO / NCA Registry Excel)
//
// Used by the browser import page (import-fund-tracking.php), which parses
// the Excel file entirely in-browser with SheetJS, maps the columns, then
// POSTs the mapped row array as JSON. No file upload — same pattern as
// COB V3's "PATH A" (application/json) import route.
//
// Body (JSON): { "rows": [ {...}, ... ] } — user_id of every imported record is
// the JWT user (a `created_by` in the body is ignored).
// Row keys (all optional except a fully-blank row, which is silently
// skipped): sub_program (a sub-program code or name; the legacy `program` key
// works too — required, see below), fiscal_year, nature_of_fund,
//           saro_reference_number (→ reference_number),
//           saro_name (→ name, falls back to nca_release_reference),
//           saro_date (→ date), amount, unreleased_saro (→ unreleased)
//           nca_release_reference, nca_amount, nca_date, unreleased_nca,
//           bank_account_no, saro_multiple_node_id, nca_multiple_node_id
//
// A row is skipped when its sub-program matches nothing in fims.sub_program
// (it can't be auto-created: a sub-program needs its program) — create it,
// then re-import; already-imported references are skipped as duplicates.
//
// "tracking_type_id" is intentionally NOT part of this import: it is resolved
// to SARO / NCA by code for the SARO / NCA side of each row.
//
// bank_account_no: raw "BANK ACCOUNT NUMBER" cell (e.g. "LBP#0702-1068-34")
// — stored on the NCA record only, stripped down to the part after the
// last "#" (e.g. "0702-1068-34") by extractBankAccountNo().
// saro_multiple_node_id / nca_multiple_node_id: resolved client-side by
// import-fund-tracking.php from the "SARO LINK" / "NCA LINK" columns via
// the same POST /upload/multi-upload call used by saro-processing-form.php
// — stored as each record's own multiple_node_id.
//
// Returns: { success, imported, duplicates, skipped, errors[], row_results[], message }
// -----------------------------------------------------------------------
$app->post('/fund_tracking/import', function ($request, $response, $args) use ($Services, $fundTrackingUserId) {
    try {
        // getParsedBody() may return null if the JWT middleware already
        // consumed php://input; fall back to re-reading the raw body stream.
        $body = $request->getParsedBody();
        if (empty($body)) {
            $raw = (string) $request->getBody();
            $body = $raw !== '' ? json_decode($raw, true) : null;
        }

        if (empty($body) || !is_array($body)) {
            return $response->withJson([
                'success' => false,
                'message' => 'Request body is empty or not valid JSON.',
            ], 422);
        }

        $rows = $body['rows'] ?? null;
        if (empty($rows) || !is_array($rows)) {
            return $response->withJson([
                'success' => false,
                'message' => '"rows" must be a non-empty array of fund tracking objects.',
            ], 422);
        }

        $result = $Services->importFromExcel($rows, $fundTrackingUserId($request));

        if ($result['success'] && !empty($result['errors'])) {
            return $response->withJson($result, 207);
        }

        return $response->withJson($result, $result['success'] ? 201 : 422);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// -----------------------------------------------------------------------
// POST /fund_tracking/import-trust-fund — bulk import (Trust Fund Registry)
//
// Used by the browser import page (import-trust-fund.php), same in-browser
// SheetJS parse + column-map + JSON POST pattern as /fund_tracking/import.
// Every row still lands in fims.fund_tracking (not a separate table) —
// tracking_type_id is always resolved to 'TRUST' server-side.
//
// Body (JSON): { "rows": [ {...}, ... ] } — user_id is the JWT user.
// Row keys: agency, sub_program (or the legacy `program`), reference_number,
// amount, date, remarks — see FundTrackingServices::importTrustFundFromExcel()
// for full field mapping, carry-forward, and parent-link rules.
//
// Returns: { success, imported, duplicates, skipped, errors[], row_results[], message }
// -----------------------------------------------------------------------
$app->post('/fund_tracking/import-trust-fund', function ($request, $response, $args) use ($Services, $fundTrackingUserId) {
    try {
        $body = $request->getParsedBody();
        if (empty($body)) {
            $raw = (string) $request->getBody();
            $body = $raw !== '' ? json_decode($raw, true) : null;
        }

        if (empty($body) || !is_array($body)) {
            return $response->withJson([
                'success' => false,
                'message' => 'Request body is empty or not valid JSON.',
            ], 422);
        }

        $rows = $body['rows'] ?? null;
        if (empty($rows) || !is_array($rows)) {
            return $response->withJson([
                'success' => false,
                'message' => '"rows" must be a non-empty array of trust fund row objects.',
            ], 422);
        }

        $result = $Services->importTrustFundFromExcel($rows, $fundTrackingUserId($request));

        if ($result['success'] && !empty($result['errors'])) {
            return $response->withJson($result, 207);
        }

        return $response->withJson($result, $result['success'] ? 201 : 422);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// -----------------------------------------------------------------------
// GET /fund_tracking/fund_sourcing_determination — SARO/NCA/Trust/Supplemental
// summary (public — no JWT, matches /fund_tracking/all)
// SRS Ref: 1.2.1.3.1 — SARO Allocations, NCA Received, Available Funds
//          1.2.1.3.2 — Trust Funds and Supplemental Funds
// Query params (all optional): fiscal_year, fiscal_year_from / fiscal_year_to
// (inclusive range, matched against fund_tracking.fiscal_year; independent of
// fiscal_year), sub_program_code, program_code (the program the record's
// sub-program belongs to), exclude_nature_of_fund (a nature of fund id or name,
// e.g. SUPPLEMENTAL — leaves that nature out of the SARO / NCA / Trust totals;
// the Supplemental total itself is not affected).
// -----------------------------------------------------------------------
$app->get('/fund_tracking/fund_sourcing_determination', function ($request, $response, $args) use ($Services) {
    try {
        $params = $request->getQueryParams();
        $fiscalYear = !empty($params['fiscal_year']) ? (int) $params['fiscal_year'] : null;
        $subProgramCode = !empty($params['sub_program_code']) ? (string) $params['sub_program_code'] : null;
        $programCode = !empty($params['program_code']) ? (string) $params['program_code'] : null;
        $fiscalYearFrom = isset($params['fiscal_year_from']) && $params['fiscal_year_from'] !== '' ? (int) $params['fiscal_year_from'] : null;
        $fiscalYearTo = isset($params['fiscal_year_to']) && $params['fiscal_year_to'] !== '' ? (int) $params['fiscal_year_to'] : null;
        $excludeNature = isset($params['exclude_nature_of_fund']) && is_string($params['exclude_nature_of_fund']) && $params['exclude_nature_of_fund'] !== ''
            ? $params['exclude_nature_of_fund'] : null;

        $result = $Services->fetchFundSourcingDetermination($fiscalYear, $subProgramCode, $programCode, $fiscalYearFrom, $fiscalYearTo, $excludeNature);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// -----------------------------------------------------------------------
// GET /fund_tracking/supplemental_status_summary — SUPPLEMENTAL records counted
// per status (IN_PROCESS / AT_NDRRMC / AT_OP / APPROVED / DENIED) for the
// FMD/SupplementalPage dashboard (public — no JWT, matches /fund_tracking/all)
// Query params (optional): fiscal_year, tracking_group (SARO_NCA | NO_SARO_NCA)
// Needs fmd.supplemental_fund_details, which has no SQL in this repo yet.
// -----------------------------------------------------------------------
$app->get('/fund_tracking/supplemental_status_summary', function ($request, $response, $args) use ($Services) {
    try {
        $params = $request->getQueryParams();
        $fiscalYear = !empty($params['fiscal_year']) ? (int) $params['fiscal_year'] : null;
        $trackingGroup = !empty($params['tracking_group']) ? (string) $params['tracking_group'] : null;

        return $response->withJson($Services->fetchSupplementalStatusSummary($fiscalYear, $trackingGroup));
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
});

// -----------------------------------------------------------------------
// GET /fund_tracking/dashboard — Fund Sourcing Dashboard (public — no JWT,
// matches /fund_tracking/all): SARO/NCA/Available Funds and Trust/Supplemental
// Funds in one payload (`government_funds`, `special_funds`).
// SRS Ref: 1.2.1.1.2 — SARO Allocations, NCA Received, Available Funds
//          1.2.1.1.3 — Trust Funds and Supplemental Funds
// Query params: fiscal_year, sub_program_code, program_code (all optional).
// The Cross-Access / Borrowing section (SRS 1.2.1.1.1) is not part of the
// payload: its source table (treasury.fund_transfers) does not exist here.
// -----------------------------------------------------------------------
$app->get('/fund_tracking/dashboard', function ($request, $response, $args) use ($Services) {
    try {
        $params = $request->getQueryParams();
        $fiscalYear = !empty($params['fiscal_year']) ? (int) $params['fiscal_year'] : null;
        $subProgramCode = !empty($params['sub_program_code']) ? (string) $params['sub_program_code'] : null;
        $programCode = !empty($params['program_code']) ? (string) $params['program_code'] : null;

        $result = $Services->fetchFundSourcingDashboard($fiscalYear, $subProgramCode, $programCode);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
});
