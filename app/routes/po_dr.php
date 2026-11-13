<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PoDrService;

$PoDrService = new PoDrService();

// A PO is addressed by its po_number, passed as ?po_number=… (PO numbers may contain "/" or "-").

// List — one entry per PO number, each with its delivery receipt lines (paginated)
$app->get('/po-dr/list', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->fetchAlls(
        isset($params['page']) ? (int) $params['page'] : 1,
        isset($params['limit']) ? (int) $params['limit'] : 10,
        $params['search'] ?? ''
    ));
})->add($jwtMiddleware);

// PO picker: RFA POs of the Budget Allocation (?search= on the PO number) with PO date, office, vendor and PO amount
$app->get('/po-dr/pos', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->searchPos((string) ($params['search'] ?? '')));
})->add($jwtMiddleware);

// Account Code picker: the chart of accounts (?search= on the code or title)
$app->get('/po-dr/accounts', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->searchAccounts((string) ($params['search'] ?? '')));
})->add($jwtMiddleware);

// Read one PO with its delivery receipts
$app->get('/po-dr/read', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->fetchByPo((string) ($params['po_number'] ?? '')));
})->add($jwtMiddleware);

// Pro-forma JEV of each delivery receipt of a PO
$app->get('/po-dr/proforma', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();

    return $response->withJson($PoDrService->proforma((string) ($params['po_number'] ?? '')));
})->add($jwtMiddleware);

// Add (po_number + lines[])
$app->post('/po-dr/add', function ($request, $response, $args) use ($PoDrService) {
    $result = $PoDrService->create($request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['success'] ? 201 : 400);
})->add($jwtMiddleware);

// Update (?po_number=<current PO number>; same payload as add, dr_line_id marks lines to update)
$app->put('/po-dr/update', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();
    $result = $PoDrService->update((string) ($params['po_number'] ?? ''), $request->getParsedBody() ?: [], (int) $request->getAttribute('user_id'));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Delete a PO number with all its lines
$app->delete('/po-dr/delete', function ($request, $response, $args) use ($PoDrService) {
    $params = $request->getQueryParams();
    $result = $PoDrService->delete((string) ($params['po_number'] ?? ''));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);
