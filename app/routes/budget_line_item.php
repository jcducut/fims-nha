<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetLineItemService;

$BudgetLineItemService = new BudgetLineItemService();

// Fetch all — no pagination
$app->get('/budget_line_item/all', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetLineItemService->fetchAlls(null, 10, $params['fiscal_yr'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination) — used by the Chart of Accounts filtered list view
$app->get('/budget_line_item/list', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $BudgetLineItemService->fetchAlls($page, $limit, $params['fiscal_yr'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Line items of APPROVED budget requests — the Memo Request form's COB list (Type of Fund + Account Code).
// Optional: program_code, sub_program_code, project_code, fiscal_yr, type_of_fund_id — each narrows the list to what is attached to it.
$app->get('/budget_line_item/approved', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetLineItemService->fetchApproved(
            (string) ($params['program_code'] ?? ''),
            (string) ($params['sub_program_code'] ?? ''),
            (string) ($params['project_code'] ?? ''),
            (string) ($params['fiscal_yr'] ?? ''),
            (string) ($params['type_of_fund_id'] ?? '')
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_line_item/approved ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Types of fund that exist on APPROVED line items — the Memo Request form's Type of Fund dropdown. Optional:
// program_code, sub_program_code, project_code, fiscal_yr — the same filters as /budget_line_item/approved (but no type_of_fund_id).
$app->get('/budget_line_item/approved/type_of_fund', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetLineItemService->fetchApprovedTypesOfFund(
            (string) ($params['program_code'] ?? ''),
            (string) ($params['sub_program_code'] ?? ''),
            (string) ($params['project_code'] ?? ''),
            (string) ($params['fiscal_yr'] ?? '')
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_line_item/approved/type_of_fund ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Earmarks against one line item — the "why is the available balance lower than suggested_amount"
// drill-down: one row per Approved CBA/CFA reservation (active/released) or RFA/LAA conversion
// (converted), each carrying its own allocation's reference number, type and status.
$app->get('/budget_line_item/{id}/earmarks', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $result = $BudgetLineItemService->fetchEarmarks((int) $args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_line_item/{id}/earmarks ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/budget_line_item/read/{id}', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $result = $BudgetLineItemService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add — attach one more line item to an existing header
$app->post('/budget_line_item/add', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetLineItemService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/budget_line_item/update/{id}', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetLineItemService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/budget_line_item/delete/{id}', function ($request, $response, $args) use ($BudgetLineItemService) {
    try {
        $result = $BudgetLineItemService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
