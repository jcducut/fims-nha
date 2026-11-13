<?php

namespace PHPMaker2024\FMD;

use PHPMaker2024\FMD\Service\CRUDServices;

$Services = new CRUDServices();

$app->get('/crud/all', function ($request, $response, $args) use ($Services) {
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
$app->get('/crud/list', function ($request, $response, $args) use ($Services) {
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
$app->get('/crud/read/{id}', function ($request, $response, $args) use ($Services) {
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
$app->post('/crud/add', function ($request, $response, $args) use ($Services) {
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
$app->patch('/crud/update/{id}', function ($request, $response, $args) use ($Services) {
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

// Delete
$app->delete('/crud/delete/{id}', function ($request, $response, $args) use ($Services) {
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
