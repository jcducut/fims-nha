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
