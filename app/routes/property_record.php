<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PropertyRecordService;

$PropertyRecordService = new PropertyRecordService();

// Property Records. The user searches a PO number in the Property Inventory System (PIS), picks items and saves them to
// fims.property_records (one row per PO, the picked items as JSONB).

// Search: the PO's items (status 1-4) from PIS + which are already saved + the PO's ONE responsibility center
// GET /property-record/search?po_number=…   → { success, data: { items[], rc{}, summary{}, saved_count, hidden_by_status, … } }
$app->get('/property-record/search', function ($request, $response, $args) use ($PropertyRecordService) {
    $params = $request->getQueryParams();
    $result = $PropertyRecordService->search((string) ($params['po_number'] ?? ''));

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);

// Save the SELECTED items: { po_number, inv_ids: [51422, …] }  (PIS is asked again; only the saved PIS fields are kept)
$app->post('/property-record/save', function ($request, $response, $args) use ($PropertyRecordService) {
    $body   = $request->getParsedBody() ?: [];
    $result = $PropertyRecordService->save(
        (string) ($body['po_number'] ?? ''),
        is_array($body['inv_ids'] ?? null) ? $body['inv_ids'] : [],
        (int) $request->getAttribute('user_id')
    );

    return $response->withJson($result, $result['success'] ? 201 : 400);
})->add($jwtMiddleware);

// Everything that is saved (items of every PO, with the PO's responsibility center) + the numbers for the cards
// GET /property-record/list   → { success, data: { items[], po_count, summary{} } }
$app->get('/property-record/list', function ($request, $response, $args) use ($PropertyRecordService) {
    $result = $PropertyRecordService->listSaved();

    return $response->withJson($result, $result['success'] ? 200 : 400);
})->add($jwtMiddleware);
