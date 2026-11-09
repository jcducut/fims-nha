<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\TypeOfFundService;

$TypeOfFundService = new TypeOfFundService();

// Fetch all — no pagination (for dropdowns)
$app->get('/type_of_fund/all', function ($request, $response, $args) use ($TypeOfFundService) {
    try {
        $params = $request->getQueryParams();
        $result = $TypeOfFundService->fetchAlls(null, 10, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/type_of_fund/list', function ($request, $response, $args) use ($TypeOfFundService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $TypeOfFundService->fetchAlls($page, $limit, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/type_of_fund/read/{id}', function ($request, $response, $args) use ($TypeOfFundService) {
    try {
        $result = $TypeOfFundService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/type_of_fund/add', function ($request, $response, $args) use ($TypeOfFundService) {
    try {
        $body   = $request->getParsedBody();
        $result = $TypeOfFundService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/type_of_fund/update/{id}', function ($request, $response, $args) use ($TypeOfFundService) {
    try {
        $body   = $request->getParsedBody();
        $result = $TypeOfFundService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete (soft)
$app->delete('/type_of_fund/delete/{id}', function ($request, $response, $args) use ($TypeOfFundService) {
    try {
        $result = $TypeOfFundService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
