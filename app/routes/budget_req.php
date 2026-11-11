<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetReqService;
use PHPMaker2024\FIMS\Service\GlobalService;

$BudgetReqService = new BudgetReqService();

// FormData submissions (new supporting-document files attached) send the
// normal payload as a JSON string in the 'data' field, with the files
// themselves as doc_file_0, doc_file_1, ... (one flat index across every
// project in the request); a plain JSON POST/PUT (no new files) decodes
// directly. Only when files are actually present does
// uploadSupportingDocFiles() run — a save with no documents at all is
// unaffected either way.
$budgetReqParseBody = function ($request) use ($BudgetReqService) {
    $raw  = $request->getParsedBody();
    $body = isset($raw['data']) ? (json_decode($raw['data'], true) ?? []) : ($raw ?? []);

    $uploadedFiles = $request->getUploadedFiles();
    if (!empty($uploadedFiles) && !empty($body['projects']) && is_array($body['projects'])) {
        $body['projects'] = $BudgetReqService->uploadSupportingDocFiles(
            $body['projects'],
            $uploadedFiles,
            (string) ($body['budget_reference_number'] ?? $body['fiscal_yr'] ?? '')
        );
    }

    return $body;
};

// Fetch all — no pagination
$app->get('/budget_req/all', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $params = $request->getQueryParams();
        $scope  = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));
        $rcId   = GlobalService::resolveBudgetReqRcFilter($scope, $params);
        $result = $BudgetReqService->fetchAlls(null, 10, $params['fiscal_yr'] ?? '', $params['program_code'] ?? '', $rcId, $params['rc_code_from'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Grand total — SUM(proposed_amount) across every fiscal year/program/status,
// not just whatever the list's current filters narrow it down to. Used by
// the "View as COB" overview. Same RC-scoping as /budget_req/all.
$app->get('/budget_req/grand-total', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $params = $request->getQueryParams();
        $scope  = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));
        $rcId   = $scope['rc_id'] ?? ($params['rc_id'] ?? '');
        $result = $BudgetReqService->fetchGrandTotalAmount($rcId);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/budget_req/list', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $scope  = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));
        $rcId   = GlobalService::resolveBudgetReqRcFilter($scope, $params);

        $result = $BudgetReqService->fetchAlls($page, $limit, $params['fiscal_yr'] ?? '', $params['program_code'] ?? '', $rcId, $params['rc_code_from'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/budget_req/read/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add one Program (+ its project blocks + line items) to the budget
// request for the given fiscal year — creates that request (status Draft)
// if it doesn't exist yet, or appends to it if it does and is still Draft.
// Rejected if that fiscal year's request has moved past Draft.
// body: { fiscal_yr, program_code, sub_program_code, projects: [ { project_code, line_items: [{ account_id, particulars, proposed_amount }, ...] }, ... ] }
$app->post('/budget_req/add', function ($request, $response, $args) use ($BudgetReqService, $budgetReqParseBody) {
    try {
        $body     = $budgetReqParseBody($request);
        $projects = $body['projects'] ?? [];

        if (!is_array($projects) || empty($projects)) {
            return $response->withJson(['success' => false, 'message' => 'projects is required and must be a non-empty array.'], 400);
        }

        // rc_id is auto-detected from the caller's own account — no manual RC
        // picker. Every creating user, admin included, must have one set.
        // getUserRcId() resolves the session-backed current user itself and
        // throws if none is logged in (caught below, surfaced as a 400).
        $rcId = GlobalService::getUserRcId();
        if ($rcId === null) {
            return $response->withJson(['success' => false, 'message' =>
                'Your account has no RC assigned (uac.users.rc_id). Ask an administrator to set it.'], 400);
        }
        $body['rc_id'] = $rcId;

        $result = $BudgetReqService->addProgram($body, $projects);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update one Program's project blocks + line items (fully replaced).
// Sibling programs under the same budget request are untouched. Rejected
// once that request has moved past Draft.
// body: { program_code, sub_program_code, projects: [...] }
// Accepts PUT (plain JSON) or POST (FormData with new attachment files) —
// PHP only populates $_FILES/multipart bodies on POST, so a save with new
// documents to upload must come in as POST; everything else still works as PUT.
$app->map(['PUT', 'POST'], '/budget_req/program/update/{budget_req_program_id}', function ($request, $response, $args) use ($BudgetReqService, $budgetReqParseBody) {
    try {
        $body     = $budgetReqParseBody($request);
        $projects = $body['projects'] ?? [];

        if (!is_array($projects) || empty($projects)) {
            return $response->withJson(['success' => false, 'message' => 'projects is required and must be a non-empty array.'], 400);
        }

        $result = $BudgetReqService->updateProgram($args['budget_req_program_id'], $body, $projects);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Transition a Draft budget request to "For Review" — locks it from
// further program additions/edits.
$app->put('/budget_req/submit-for-review/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->submitForReview($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Budget Processing "Endorse" — saves each line item's type_of_fund_id /
// component_id (both required) and moves the request to "Endorsed".
$app->put('/budget_req/endorse/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $body   = $request->getParsedBody();
        $items  = is_array($body['items'] ?? null) ? $body['items'] : [];
        $result = $BudgetReqService->endorse($args['id'], $items);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Budget Preview "Approve" — moves an Endorsed request to "Approved".
$app->put('/budget_req/approve/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->approve($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Budget Processing / Budget Preview "Return" — moves the request to "Returned" and saves
// the (required) reason in budget_req.remarks.   body: { remarks }
$app->put('/budget_req/return/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetReqService->returnRequest($args['id'], is_array($body) ? ($body['remarks'] ?? null) : null);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/budget_req/delete/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Lists one project's supporting documents (each with a presigned view_url
// for an upload, or its raw external_link). Scoped by (budget_req_program_id,
// project_code) — see fims_budget_req_project_attachment.sql for why.
$app->get('/budget_req_project_attachment/list', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $params             = $request->getQueryParams();
        $budgetReqProgramId = (int) ($params['budget_req_program_id'] ?? 0);
        $projectCode        = trim((string) ($params['project_code'] ?? ''));

        if ($budgetReqProgramId <= 0 || $projectCode === '') {
            return $response->withJson(['success' => false, 'message' => 'budget_req_program_id and project_code are required.'], 400);
        }

        $result = $BudgetReqService->fetchAttachmentsForProject($budgetReqProgramId, $projectCode);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Deletes one attachment — rejected unless its parent request is still Draft.
$app->delete('/budget_req_project_attachment/delete/{id}', function ($request, $response, $args) use ($BudgetReqService) {
    try {
        $result = $BudgetReqService->deleteAttachment((int) $args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
