<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\LetterheadLayoutService;

// Letterhead layout (header items + footer text) for printed documents.
// {doc} = document code (LetterheadLayoutService::DOCUMENTS — CBA, RFA, LAA, CAF, LAAREQ, PO, PR).
$LetterheadLayoutService = new LetterheadLayoutService();

// What the print preview's letterhead editor starts from:
// { success, data: { layout, saved, can_save, factory, assets } }
$app->get('/letterhead/{doc:[A-Za-z]+}', function ($request, $response, $args) use ($LetterheadLayoutService) {
    try {
        $doc = strtoupper($args['doc']);
        if (!LetterheadLayoutService::isDocument($doc)) {
            return $response->withJson(['success' => false, 'message' => 'Unknown document.'], 404);
        }

        return $response->withJson($LetterheadLayoutService->info($doc));
    } catch (\Throwable $e) {
        LogError('GET /letterhead ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// "Save as default header": body { layout: { items, footer_text } }. Saver roles only.
$app->post('/letterhead/{doc:[A-Za-z]+}', function ($request, $response, $args) use ($LetterheadLayoutService) {
    try {
        $doc = strtoupper($args['doc']);
        if (!LetterheadLayoutService::isDocument($doc)) {
            return $response->withJson(['success' => false, 'message' => 'Unknown document.'], 404);
        }
        if (!LetterheadLayoutService::canSave()) {
            return $response->withJson(['success' => false, 'message' => 'You are not allowed to change the default header.'], 403);
        }
        $body = $request->getParsedBody();
        if (!is_array($body) || !$body) {
            $body = json_decode((string) $request->getBody(), true) ?: [];
        }
        if (!is_array($body['layout'] ?? null)) {
            return $response->withJson(['success' => false, 'message' => 'layout is required.'], 400);
        }
        $result = $LetterheadLayoutService->save($doc, $body['layout'], (int) $request->getAttribute('user_id'));

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Throwable $e) {
        LogError('POST /letterhead ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// "Restore original": drop the saved default. Saver roles only.
$app->delete('/letterhead/{doc:[A-Za-z]+}', function ($request, $response, $args) use ($LetterheadLayoutService) {
    try {
        $doc = strtoupper($args['doc']);
        if (!LetterheadLayoutService::isDocument($doc)) {
            return $response->withJson(['success' => false, 'message' => 'Unknown document.'], 404);
        }
        if (!LetterheadLayoutService::canSave()) {
            return $response->withJson(['success' => false, 'message' => 'You are not allowed to change the default header.'], 403);
        }
        $result = $LetterheadLayoutService->reset($doc);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Throwable $e) {
        LogError('DELETE /letterhead ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
