<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ExpenseClassService;

$ExpenseClassService = new ExpenseClassService();

// Fetch all — no pagination (for dropdowns)
$app->get('/expense_class/all', function ($request, $response, $args) use ($ExpenseClassService) {
    try {
        $params = $request->getQueryParams();
        $result = $ExpenseClassService->fetchAlls(null, 10, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/expense_class/list', function ($request, $response, $args) use ($ExpenseClassService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ExpenseClassService->fetchAlls($page, $limit, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/expense_class/read/{id}', function ($request, $response, $args) use ($ExpenseClassService) {
    try {
        $result = $ExpenseClassService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/expense_class/add', function ($request, $response, $args) use ($ExpenseClassService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ExpenseClassService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/expense_class/update/{id}', function ($request, $response, $args) use ($ExpenseClassService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ExpenseClassService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/expense_class/delete/{id}', function ($request, $response, $args) use ($ExpenseClassService) {
    try {
        $result = $ExpenseClassService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
