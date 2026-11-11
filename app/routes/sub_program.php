<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\GlobalService;
use PHPMaker2024\FIMS\Service\SubProgramService;

$SubProgramService = new SubProgramService();

// Suggested sub-program code sequence for a program: ?program_code=...
// -> { prefix: "001", next: 1, next_code: "001-001" }  (client counts up from `next` for extra line items)
$app->get('/sub_program/next-code', function ($request, $response, $args) {
    try {
        $programCode = trim((string) ($request->getQueryParams()['program_code'] ?? ''));
        if ($programCode === '') {
            return $response->withJson(['success' => false, 'message' => 'program_code is required.'], 400);
        }

        $seq = GlobalService::nextSubProgramNumber($programCode);
        $seq['next_code'] = GlobalService::formatSubProgramCode($seq['prefix'], $seq['next']);

        return $response->withJson(['success' => true, 'data' => $seq]);
    } catch (\InvalidArgumentException $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    } catch (\Throwable $e) {
        LogError('GET /sub_program/next-code ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not generate a sub-program code.'], 500);
    }
})->add($jwtMiddleware);

// Fetch all — no pagination (for dropdowns)
$app->get('/sub_program/all', function ($request, $response, $args) use ($SubProgramService) {
    try {
        $params = $request->getQueryParams();
        $result = $SubProgramService->fetchAlls(null, 10, $params['search'] ?? '', $params['program_code'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/sub_program/list', function ($request, $response, $args) use ($SubProgramService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;

        $result = $SubProgramService->fetchAlls($page, $limit, $params['search'] ?? '', $params['program_code'] ?? '');

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/sub_program/read/{code}', function ($request, $response, $args) use ($SubProgramService) {
    try {
        $result = $SubProgramService->fetchByCode($args['code']);

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add (single)
$app->post('/sub_program/add', function ($request, $response, $args) use ($SubProgramService) {
    try {
        $body   = $request->getParsedBody();
        $result = $SubProgramService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Create several sub-programs under one program together —
// body: { program_code, sub_programs: [{ sub_program_code, sub_program_name }, ...] }
$app->post('/sub_program/add-with-items', function ($request, $response, $args) use ($SubProgramService) {
    try {
        $body        = $request->getParsedBody();
        $programCode = $body['program_code'] ?? '';
        $subPrograms = $body['sub_programs'] ?? [];

        if (!is_array($subPrograms) || empty($subPrograms)) {
            return $response->withJson(['success' => false, 'message' => 'sub_programs is required and must be a non-empty array.'], 400);
        }

        $result = $SubProgramService->createBatch($programCode, $subPrograms);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/sub_program/update/{code}', function ($request, $response, $args) use ($SubProgramService) {
    try {
        $body   = $request->getParsedBody();
        $result = $SubProgramService->update($args['code'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/sub_program/delete/{code}', function ($request, $response, $args) use ($SubProgramService) {
    try {
        $result = $SubProgramService->delete($args['code']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
