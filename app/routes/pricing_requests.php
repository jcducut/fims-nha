<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\PricingRequestService;

$PricingRequestService = new PricingRequestService();

// A save that carries new supporting-document files arrives as multipart FormData:
// the normal payload is a JSON string in the 'data' field and the files are
// doc_file_0, doc_file_1, ... A plain JSON POST/PUT (no new files) decodes directly.
$pricingRequestParseBody = function ($request): array {
    $raw = $request->getParsedBody();
    if (is_array($raw) && isset($raw['data']) && is_string($raw['data'])) {
        $decoded = json_decode($raw['data'], true);

        return is_array($decoded) ? $decoded : [];
    }

    return is_array($raw) ? $raw : [];
};

// Status of a service result: its own 'http' when it failed, otherwise $ok.
$pricingRequestStatus = function (array $result, int $ok = 200): int {
    return !empty($result['success']) ? $ok : (int) ($result['http'] ?? 400);
};

// Fetch all — no pagination
$app->get('/pricing_requests/all', function ($request, $response, $args) use ($PricingRequestService, $pricingRequestStatus) {
    try {
        $result = $PricingRequestService->fetchAlls($request->getQueryParams());

        return $response->withJson($result, $pricingRequestStatus($result));
    } catch (\Throwable $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/pricing_requests/list', function ($request, $response, $args) use ($PricingRequestService, $pricingRequestStatus) {
    try {
        $result = $PricingRequestService->fetchList($request->getQueryParams());

        return $response->withJson($result, $pricingRequestStatus($result));
    } catch (\Throwable $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one (with its supporting documents)
$app->get('/pricing_requests/read/{id}', function ($request, $response, $args) use ($PricingRequestService, $pricingRequestStatus) {
    try {
        $result = $PricingRequestService->fetchById($args['id']);

        return $response->withJson($result, $pricingRequestStatus($result));
    } catch (\Throwable $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Create. body: the form fields (project_code required) + supporting_documents:
//   [ { document_name, link } | { document_name, file_index } | { attachment_id, document_name } ]
$app->post('/pricing_requests/add', function ($request, $response, $args) use ($PricingRequestService, $pricingRequestParseBody, $pricingRequestStatus) {
    try {
        $body = $pricingRequestParseBody($request);
        if (empty($body)) {
            return $response->withJson(['success' => false, 'message' => 'Request body is empty or not valid JSON.'], 422);
        }

        $result = $PricingRequestService->create($body, $request->getUploadedFiles());

        return $response->withJson($result, $pricingRequestStatus($result, 201));
    } catch (\Throwable $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update. PUT (plain JSON) — or POST when new files are attached, because PHP only
// fills $_FILES / multipart bodies on POST (the form switches method for that).
$app->map(['PUT', 'POST', 'PATCH'], '/pricing_requests/update/{id}', function ($request, $response, $args) use ($PricingRequestService, $pricingRequestParseBody, $pricingRequestStatus) {
    try {
        $body = $pricingRequestParseBody($request);
        if (empty($body)) {
            return $response->withJson(['success' => false, 'message' => 'Request body is empty or not valid JSON.'], 422);
        }

        $result = $PricingRequestService->update($args['id'], $body, $request->getUploadedFiles());

        return $response->withJson($result, $pricingRequestStatus($result));
    } catch (\Throwable $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// A request's supporting documents (each with a presigned view_url for an upload, or its external_link)
$app->get('/pricing_request_attachments/list', function ($request, $response, $args) use ($PricingRequestService, $pricingRequestStatus) {
    try {
        $id = (int) ($request->getQueryParams()['pricing_request_id'] ?? 0);
        if ($id <= 0) {
            return $response->withJson(['success' => false, 'message' => 'pricing_request_id is required.'], 400);
        }
        $result = $PricingRequestService->fetchAttachments($id);

        return $response->withJson($result, $pricingRequestStatus($result));
    } catch (\Throwable $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete one supporting document (soft delete)
$app->delete('/pricing_request_attachments/delete/{id}', function ($request, $response, $args) use ($PricingRequestService, $pricingRequestStatus) {
    try {
        $result = $PricingRequestService->deleteAttachment((int) $args['id']);

        return $response->withJson($result, $pricingRequestStatus($result));
    } catch (\Throwable $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
