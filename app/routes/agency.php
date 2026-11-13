<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\AgencyServices;

// =============================================================================
// Route file : agency.php
// Table      : fims.agency
// Base path  : /agency
//
// Endpoints
// ─────────────────────────────────────────────────────────────────────────────
//  GET    /agency/all          List all (no pagination, public — no JWT)
//  GET    /agency/list         Paginated list
//  GET    /agency/read/{id}    Read one
//  POST   /agency/add          Create
//  PATCH  /agency/update/{id}  Update
//  DELETE /agency/delete/{id}  Delete (hard delete — no deleted_at column)
// =============================================================================

$Services = new AgencyServices();

// Fetch all — no pagination
$app->get('/agency/all', function ($request, $response, $args) use ($Services) {
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
$app->get('/agency/list', function ($request, $response, $args) use ($Services) {
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
$app->get('/agency/read/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->fetchById($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/agency/add', function ($request, $response, $args) use ($Services) {
    try {
        $body = $request->getParsedBody();
        $result = $Services->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 422);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Update
$app->patch('/agency/update/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $body = $request->getParsedBody();
        $result = $Services->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 422);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/agency/delete/{id}', function ($request, $response, $args) use ($Services) {
    try {
        $result = $Services->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 404);
    } catch (\Exception $e) {
        return $response->withJson([
            'success' => false,
            'message' => $e->getMessage(),
        ], 400);
    }
})->add($jwtMiddleware);
