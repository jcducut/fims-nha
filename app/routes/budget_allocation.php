<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetAllocationService;
use PHPMaker2024\FIMS\Service\GlobalService;

$BudgetAllocationService = new BudgetAllocationService();

/**
 * Query-string filters shared by /all and /list.
 * search, fiscal_year, status_id, status (a status name, e.g. Approved), allocation_type, exclude_id.
 */
$budgetAllocationFilters = static function (array $p): array {
    return [
        'search' => (string) ($p['search'] ?? ''),
        'fiscal_year' => $p['fiscal_year'] ?? null,
        'status_id' => $p['status_id'] ?? null,
        'status' => (string) ($p['status'] ?? ''),
        'allocation_type' => (string) ($p['allocation_type'] ?? ''),
        'exclude_id' => $p['exclude_id'] ?? null,
    ];
};

/**
 * The filters of a LIST request, plus the OFFICE SCOPE — decided here, from the logged-in user, never by the client. It is
 * the rule the budget requests use (GlobalService::resolveBudgetReqRcFilter), applied to the request's requesting office:
 *  - a user with an office sees that office's requests and those of the offices under it (a client-sent rc_id is ignored);
 *  - a superadmin / a user without an office sees every office, and may narrow with ?rc_id=;
 *  - the central review office (FMD) sees every office, but only when the page says ?central=1 — the parameter alone grants
 *    nothing, the caller's own office is checked server-side.
 */
$budgetAllocationScopedFilters = static function ($request, array $p) use ($budgetAllocationFilters): array {
    $filters = $budgetAllocationFilters($p);
    $scope = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));
    $filters['rc_id'] = GlobalService::resolveBudgetReqRcFilter($scope, $p);

    return $filters;
};

// Departments for the Requesting Department / District pickers
$app->get('/budget_allocation/departments', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->fetchDepartments();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/departments '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Statuses defined for memo requests (Status filter / form select)
$app->get('/budget_allocation/statuses', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->fetchStatuses();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/statuses '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all — no pagination (for dropdowns, e.g. the Parent picker)
$app->get('/budget_allocation/all', function ($request, $response, $args) use ($BudgetAllocationService, $budgetAllocationScopedFilters) {
    try {
        $result = $BudgetAllocationService->fetchAlls(null, 10, $budgetAllocationScopedFilters($request, $request->getQueryParams()));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/all '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + summary). Optional: page, limit + the filters above
$app->get('/budget_allocation/list', function ($request, $response, $args) use ($BudgetAllocationService, $budgetAllocationFilters) {
    try {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;

        // Scope the list to the logged-in user's RC, same rule as /budget_req/list:
        //  - a Regional user sees its own memo requests plus its district offices';
        //  - a District user sees only its own;
        //  - FMD (the central review RC) sees every RC — central=1 is forced here because the page
        //    doesn't send it, and resolveBudgetReqRcFilter() still verifies the user's RC server-side,
        //    so it grants nothing to anyone else;
        //  - a superadmin / user without an RC is unrestricted.
        $scope = GlobalService::getUserRcScope((int) $request->getAttribute('user_id'));
        $filters = $budgetAllocationFilters($params);
        $filters['rc_id'] = GlobalService::resolveBudgetReqRcFilter($scope, $params + ['central' => '1']);

        $result = $BudgetAllocationService->fetchAlls($page, $limit, $filters);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/list '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/budget_allocation/read/{id}', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->fetchById($args['id'], true);   // + file_attachments[]
        if ($result['success']) {
            // where it stands in FMD's review chain, and whether the caller may decide it now (the Process modal's buttons)
            $result['data']['review'] = $BudgetAllocationService->reviewInfo($result['data'], (int) $request->getAttribute('user_id'));
        }

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /budget_allocation/read '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add — the creator (user_id) comes from the verified JWT, never from the body
$app->post('/budget_allocation/add', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $userId = $request->getAttribute('user_id');
        $result = $BudgetAllocationService->create(
            $request->getParsedBody() ?: [],
            $userId !== null ? (int) $userId : null
        );

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /budget_allocation/add '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update (PATCH — only the fields sent are changed)
$app->patch('/budget_allocation/update/{id}', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->update($args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PATCH /budget_allocation/update '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Delete (soft)
// Review — the Financial Management Department (FMD) approves or returns a request, one step at a time:
// For Preview (FMD Section Chief) -> For Division Manager (FMD Division Manager) -> For Department Manager
// (FMD Department Manager) -> Approved. Body: { "decision": "approve" | "return" }. "approve" moves it to the next status;
// the last approval gives the request its allocation number (by allocation type and document type). "return" at any step
// gives 'Returned'. Anyone outside FMD, or without the user level of the current step, gets 403 — see
// BudgetAllocationService::process().
$app->patch('/budget_allocation/process/{id}', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $body = $request->getParsedBody();
        $decision = is_array($body) && is_scalar($body['decision'] ?? null) ? (string) $body['decision'] : '';
        $result = $BudgetAllocationService->process($args['id'], $decision, (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['success'] ? 200 : (!empty($result['forbidden']) ? 403 : 400));
    } catch (\Exception $e) {
        LogError('PATCH /budget_allocation/process '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

$app->delete('/budget_allocation/delete/{id}', function ($request, $response, $args) use ($BudgetAllocationService) {
    try {
        $result = $BudgetAllocationService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('DELETE /budget_allocation/delete '.$e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
