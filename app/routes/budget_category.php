<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetCategoryService;

$BudgetCategoryService = new BudgetCategoryService();

// Fetch all — no pagination (for dropdowns). Optional: search
$app->get('/budget_category/all', function ($request, $response, $args) use ($BudgetCategoryService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetCategoryService->fetchAlls(null, 10, (string) ($params['search'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_category/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination). Optional: page, limit, search
$app->get('/budget_category/list', function ($request, $response, $args) use ($BudgetCategoryService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $BudgetCategoryService->fetchAlls($page, $limit, (string) ($params['search'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /budget_category/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/budget_category/read/{id}', function ($request, $response, $args) use ($BudgetCategoryService) {
    try {
        $result = $BudgetCategoryService->fetchById($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /budget_category/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add
$app->post('/budget_category/add', function ($request, $response, $args) use ($BudgetCategoryService) {
    try {
        $result = $BudgetCategoryService->create($request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /budget_category/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update (PATCH, as in crud.php)
$app->patch('/budget_category/update/{id}', function ($request, $response, $args) use ($BudgetCategoryService) {
    try {
        $result = $BudgetCategoryService->update($args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PATCH /budget_category/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Delete (soft)
$app->delete('/budget_category/delete/{id}', function ($request, $response, $args) use ($BudgetCategoryService) {
    try {
        $result = $BudgetCategoryService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('DELETE /budget_category/delete ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
