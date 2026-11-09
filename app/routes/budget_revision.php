<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\BudgetRevisionService;

$BudgetRevisionService = new BudgetRevisionService();

// Fetch all — no pagination
$app->get('/budget_revision/all', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $params = $request->getQueryParams();
        $result = $BudgetRevisionService->fetchAlls(null, 10, $params['fiscal_yr'] ?? '', $params['budget_req_id'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/budget_revision/list', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $BudgetRevisionService->fetchAlls($page, $limit, $params['fiscal_yr'] ?? '', $params['budget_req_id'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/budget_revision/read/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $result = $BudgetRevisionService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add — the service reads the current user itself (CurrentUserID()), not the form
$app->post('/budget_revision/add', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetRevisionService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/budget_revision/update/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetRevisionService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Review — body: { "status": "APPROVED" | "DECLINED" }
$app->put('/budget_revision/review/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $body   = $request->getParsedBody();
        $result = $BudgetRevisionService->review($args['id'], (string) ($body['status'] ?? ''));

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete (soft)
$app->delete('/budget_revision/delete/{id}', function ($request, $response, $args) use ($BudgetRevisionService) {
    try {
        $result = $BudgetRevisionService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
