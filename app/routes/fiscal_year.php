<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\FiscalYearService;

$FiscalYearService = new FiscalYearService();

// Fetch all — no pagination (for dropdowns)
$app->get('/fiscal_year/all', function ($request, $response, $args) use ($FiscalYearService) {
    try {
        $result = $FiscalYearService->fetchAlls();

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/fiscal_year/list', function ($request, $response, $args) use ($FiscalYearService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $FiscalYearService->fetchAlls($page, $limit);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/fiscal_year/read/{fiscal_yr}', function ($request, $response, $args) use ($FiscalYearService) {
    try {
        $result = $FiscalYearService->fetchByYear($args['fiscal_yr']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/fiscal_year/add', function ($request, $response, $args) use ($FiscalYearService) {
    try {
        $body   = $request->getParsedBody();
        $result = $FiscalYearService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/fiscal_year/delete/{fiscal_yr}', function ($request, $response, $args) use ($FiscalYearService) {
    try {
        $result = $FiscalYearService->delete($args['fiscal_yr']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
