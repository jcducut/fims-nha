<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ComponentTypeService;

$ComponentTypeService = new ComponentTypeService();

// Fetch all — no pagination (for dropdowns)
$app->get('/component_type/all', function ($request, $response, $args) use ($ComponentTypeService) {
    try {
        $params = $request->getQueryParams();
        $result = $ComponentTypeService->fetchAlls(null, 10, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/component_type/list', function ($request, $response, $args) use ($ComponentTypeService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ComponentTypeService->fetchAlls($page, $limit, $params['search'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/component_type/read/{id}', function ($request, $response, $args) use ($ComponentTypeService) {
    try {
        $result = $ComponentTypeService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/component_type/add', function ($request, $response, $args) use ($ComponentTypeService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ComponentTypeService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/component_type/update/{id}', function ($request, $response, $args) use ($ComponentTypeService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ComponentTypeService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete (soft)
$app->delete('/component_type/delete/{id}', function ($request, $response, $args) use ($ComponentTypeService) {
    try {
        $result = $ComponentTypeService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
