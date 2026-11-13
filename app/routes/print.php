<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PrintService;

// Printed forms — one endpoint for every form (CBA, CAF, RFA, LAA, …), run by
// PrintService (the forms are registered in PrintService::DOCS).
// Preview modal: app/pages/readone/print-preview-readone.php (prp_open(payload, { type })).
$PrintService = new PrintService();

// Print a form from a payload. {doc} = cba | caf | rfa | laa
// Body: { format: html|pdf|docx, ...payload } — payload fields are documented on each
// form's model() (CbaPrintService, CafPrintService, RfaPrintService, LaaPrintService); `letterhead`
// = this print's header items + footer_text (see LetterheadLayoutService).
// html → { success, html };  pdf / docx → { success, filename, mime, data: base64 }
$app->post('/print/{doc:[A-Za-z]+}', function ($request, $response, $args) use ($PrintService) {
    try {
        $body = $request->getParsedBody();
        if (!is_array($body) || !$body) {
            $body = json_decode((string) $request->getBody(), true) ?: [];
        }
        $format = (string) ($body['format'] ?? 'html');
        unset($body['format']);

        $result = $PrintService->print($args['doc'], $body, $format);

        return $response->withJson($result, $result['httpCode'] ?? ($result['success'] ? 200 : 400));
    } catch (\Throwable $e) {
        LogError('POST /print/' . $args['doc'] . ' ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);

// Current holder of a signatory role: ?role=FMD%20Department%20Manager → { success, name|null }
$app->get('/print/resolve-signatory', function ($request, $response, $args) {
    try {
        $role = trim((string) ($request->getQueryParams()['role'] ?? ''));
        if (!in_array($role, PrintService::SIGNATORY_ROLES, true)) {
            return $response->withJson(['success' => false, 'message' => 'Unsupported role.'], 400);
        }

        return $response->withJson(['success' => true, 'name' => PrintService::resolveSignatoryName($role)]);
    } catch (\Throwable $e) {
        LogError('GET /print/resolve-signatory ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($accessMiddleware)->add($jwtMiddleware);
