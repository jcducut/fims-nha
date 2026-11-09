<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ProjectService;

$ProjectService = new ProjectService();

// Fetch all — no pagination (for dropdowns)
$app->get('/project/all', function ($request, $response, $args) use ($ProjectService) {
    try {
        $params = $request->getQueryParams();
        $result = $ProjectService->fetchAlls(null, 10, $params['search'] ?? '', $params['program_code'] ?? '', $params['sub_program_code'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/project/list', function ($request, $response, $args) use ($ProjectService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ProjectService->fetchAlls($page, $limit, $params['search'] ?? '', $params['program_code'] ?? '', $params['sub_program_code'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/project/read/{code}', function ($request, $response, $args) use ($ProjectService) {
    try {
        $result = $ProjectService->fetchByCode($args['code']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/project/add', function ($request, $response, $args) use ($ProjectService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProjectService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/project/update/{code}', function ($request, $response, $args) use ($ProjectService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ProjectService->update($args['code'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/project/delete/{code}', function ($request, $response, $args) use ($ProjectService) {
    try {
        $result = $ProjectService->delete($args['code']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
