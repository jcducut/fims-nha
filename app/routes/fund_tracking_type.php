<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\FundTrackingTypeServices;

// =============================================================================
// Route file : fund_tracking_type.php
// Table      : fims.fund_tracking_type
// Base path  : /fund-tracking-type  (register in app bootstrap)
//
// Endpoints
// ─────────────────────────────────────────────────────────────────────────────
//  GET    /all             List all (no pagination)
//  GET    /list            Paginated list
//  GET    /read/{id}       Read one
//  POST   /add             Create
//  PATCH  /update/{id}     Update
//  DELETE /delete/{id}     Soft delete
//  PATCH  /restore/{id}    Restore soft-deleted row
// =============================================================================

$Services = new FundTrackingTypeServices();

// Fetch all — no pagination
$app->get('/fund-tracking-type/all', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->fetchAlls();

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
});

// Fetch all (with pagination)
$app->get('/fund-tracking-type/list', function ($request, $response, $args) use ($Services) {
    try {
        $params = $request->getQueryParams();
        $page = isset($params['page']) ? (int) $params['page'] : 1;
        $limit = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $Services->fetchAlls($page, $limit);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/fund-tracking-type/read/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->fetchById($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/fund-tracking-type/add', function ($request, $response, $args) use ($Services) {
    try {
        $body = $request->getParsedBody();
        $result = $Services->create($body);

        return $response->withJson($result, 201);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Update
$app->patch('/fund-tracking-type/update/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $body = $request->getParsedBody();
        $result = $Services->update($args['id'], $body);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Delete (soft delete — sets deleted_at)
$app->delete('/fund-tracking-type/delete/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->delete($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Restore a soft-deleted row
$app->patch('/fund-tracking-type/restore/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->restore($args['id']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);
