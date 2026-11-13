<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\VendorService;

$VendorService = new VendorService();

// Active vendor types (dropdowns)
$app->get('/vendors/types', function ($request, $response, $args) use ($VendorService) {
    try {
        $result = $VendorService->fetchTypes();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /vendors/types ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all — no pagination (for dropdowns). ?active=1 hides non-ACTIVE vendors.
// Optional: search, type
$app->get('/vendors/all', function ($request, $response, $args) use ($VendorService) {
    try {
        $p      = $request->getQueryParams();
        $result = $VendorService->fetchAlls(null, 10, (string) ($p['search'] ?? ''), (string) ($p['type'] ?? ''), !empty($p['active']));

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /vendors/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + summary). Optional: page, limit, search, type, active, status (ACTIVE|INACTIVE|…)
$app->get('/vendors/list', function ($request, $response, $args) use ($VendorService) {
    try {
        $p      = $request->getQueryParams();
        $result = $VendorService->fetchAlls(
            isset($p['page']) ? (int) $p['page'] : 1,
            isset($p['limit']) ? (int) $p['limit'] : 10,
            (string) ($p['search'] ?? ''),
            (string) ($p['type'] ?? ''),
            !empty($p['active']),
            (string) ($p['status'] ?? '')
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /vendors/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one
$app->get('/vendors/read/{id}', function ($request, $response, $args) use ($VendorService) {
    try {
        $result = $VendorService->fetchById($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        LogError('GET /vendors/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add
$app->post('/vendors/add', function ($request, $response, $args) use ($VendorService) {
    try {
        $result = $VendorService->create($request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /vendors/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update
$app->put('/vendors/update/{id}', function ($request, $response, $args) use ($VendorService) {
    try {
        $result = $VendorService->update($args['id'], $request->getParsedBody() ?: []);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('PUT /vendors/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
