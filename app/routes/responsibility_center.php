<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ResponsibilityCenterService;

$ResponsibilityCenterService = new ResponsibilityCenterService();

// Fetch all — no pagination (for dropdowns, e.g. parent RC picker)
$app->get('/responsibility-center/all', function ($request, $response, $args) use ($ResponsibilityCenterService) {
    try {
        $params = $request->getQueryParams();
        $result = $ResponsibilityCenterService->fetchAlls(
            null,
            10,
            $params['search'] ?? '',
            $params['office_type'] ?? '',
            $params['status'] ?? ''
        );

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/responsibility-center/list', function ($request, $response, $args) use ($ResponsibilityCenterService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $ResponsibilityCenterService->fetchAlls(
            $page,
            $limit,
            $params['search'] ?? '',
            $params['office_type'] ?? '',
            $params['status'] ?? ''
        );

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/responsibility-center/read/{id}', function ($request, $response, $args) use ($ResponsibilityCenterService) {
    try {
        $result = $ResponsibilityCenterService->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/responsibility-center/add', function ($request, $response, $args) use ($ResponsibilityCenterService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ResponsibilityCenterService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/responsibility-center/update/{id}', function ($request, $response, $args) use ($ResponsibilityCenterService) {
    try {
        $body   = $request->getParsedBody();
        $result = $ResponsibilityCenterService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Toggle status (Active <-> Inactive)
$app->patch('/responsibility-center/{id}/toggle-status', function ($request, $response, $args) use ($ResponsibilityCenterService) {
    try {
        $result = $ResponsibilityCenterService->toggleStatus($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/responsibility-center/delete/{id}', function ($request, $response, $args) use ($ResponsibilityCenterService) {
    try {
        $result = $ResponsibilityCenterService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
