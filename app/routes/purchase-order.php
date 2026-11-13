<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PurchaseOrderService;

$PoService = new PurchaseOrderService();

$poFilterKeys = ['status', 'fiscal_yr', 'expense_class', 'vendor_id', 'search'];

// Fetch all — no pagination. Optional: status, fiscal_yr, expense_class, vendor_id, search
$app->get('/purchase-order/all', function ($request, $response, $args) use ($PoService, $poFilterKeys) {
    try {
        $filters = array_intersect_key($request->getQueryParams(), array_flip($poFilterKeys));
        $result  = $PoService->fetchAlls(null, 10, $filters);

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /purchase-order/all ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fetch all (with pagination + summary). Optional: page, limit + the filters above
$app->get('/purchase-order/list', function ($request, $response, $args) use ($PoService, $poFilterKeys) {
    try {
        $params  = $request->getQueryParams();
        $filters = array_intersect_key($params, array_flip($poFilterKeys));
        $result  = $PoService->fetchAlls(
            isset($params['page']) ? (int) $params['page'] : 1,
            isset($params['limit']) ? (int) $params['limit'] : 10,
            $filters
        );

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /purchase-order/list ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Fiscal years (newest first) for dropdowns
$app->get('/purchase-order/fiscal-years', function ($request, $response, $args) use ($PoService) {
    try {
        $result = $PoService->fetchFiscalYears();

        return $response->withJson($result, $result['success'] ? 200 : 500);
    } catch (\Exception $e) {
        LogError('GET /purchase-order/fiscal-years ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Next free PO number for a date: ?po_date=YYYY-MM-DD (defaults to today)
$app->get('/purchase-order/po-number', function ($request, $response, $args) use ($PoService) {
    try {
        $date   = $request->getQueryParams()['po_date'] ?? date('Y-m-d');
        $result = $PoService->fetchPoNumber((string) $date);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        LogError('GET /purchase-order/po-number ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Read one — {id} is a numeric po_id or a po_number; includes items[]
$app->get('/purchase-order/read/{id}', function ($request, $response, $args) use ($PoService) {
    try {
        $result = $PoService->fetchById($args['id']);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 500));
    } catch (\Exception $e) {
        LogError('GET /purchase-order/read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Add (PO + items). body.status: DRAFT (default) | SUBMITTED
$app->post('/purchase-order/add', function ($request, $response, $args) use ($PoService) {
    try {
        $result = $PoService->create($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 201 : 400));
    } catch (\Exception $e) {
        LogError('POST /purchase-order/add ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Update (full replace of fields + items). DRAFT / REJECTED only
$app->put('/purchase-order/update/{id}', function ($request, $response, $args) use ($PoService) {
    try {
        $result = $PoService->update($args['id'], $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('PUT /purchase-order/update ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Change status — body: { status: SUBMITTED|APPROVED|REJECTED|CANCELLED, notes?: string }
// REJECTED requires notes (stored as rejection_reason).
$app->post('/purchase-order/{id}/status', function ($request, $response, $args) use ($PoService) {
    try {
        $body   = $request->getParsedBody() ?: [];
        $status = trim((string) ($body['status'] ?? ''));
        if ($status === '') {
            return $response->withJson(['success' => false, 'message' => 'status is required.'], 400);
        }
        $result = $PoService->changeStatus($args['id'], $status, (int) $request->getAttribute('user_id'), isset($body['notes']) ? (string) $body['notes'] : null);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('POST /purchase-order/status ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Delete — DRAFT or CANCELLED only; items cascade
$app->delete('/purchase-order/delete/{id}', function ($request, $response, $args) use ($PoService) {
    try {
        $result = $PoService->delete($args['id']);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Exception $e) {
        LogError('DELETE /purchase-order/delete ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
