<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\FileUploadServices;

// Supporting Documents of the CBA / Memo Request form (app/pages/form/budget-allocation-form.php):
//   POST /upload/file          the file itself -> object storage, returns its key as "link" (+ a preview_url)
//   POST /upload/multi-upload  saves the documents (document_name + link) in fims.ip_filemeta, grouped by
//                              fims.file_attachments under a new multiple_node_id that the caller stores on its record
//   POST /upload/single-upload saves one document row
// Tables: fims.ip_filemeta, fims.file_attachments. The uploader (created_by) is the verified JWT user,
// never taken from the request body.
$FileUploadServices = new FileUploadServices();

/** The verified user's id from the JWT middleware, or null. */
$uploadUserId = static function ($request): ?int {
    $userId = $request->getAttribute('user_id');

    return $userId !== null && $userId !== '' ? (int) $userId : null;
};

// Add (one document row)
$app->post('/upload/single-upload', function ($request, $response, $args) use ($FileUploadServices, $uploadUserId) {
    try {
        $result = $FileUploadServices->singleUpload($request->getParsedBody() ?: [], $uploadUserId($request));

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /upload/single-upload ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($jwtMiddleware);

// Add (several document rows, one multiple_node_id)
$app->post('/upload/multi-upload', function ($request, $response, $args) use ($FileUploadServices, $uploadUserId) {
    try {
        $result = $FileUploadServices->multiUpload($request->getParsedBody() ?: [], $uploadUserId($request));

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /upload/multi-upload ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($jwtMiddleware);

// Streams an actual file (multipart/form-data, field name "file") straight
// to MinIO/S3 and returns the object key to store as the document's "link".
$app->post('/upload/file', function ($request, $response, $args) use ($FileUploadServices) {
    try {
        $uploadedFiles = $request->getUploadedFiles();
        $result        = $FileUploadServices->uploadFileToStorage($uploadedFiles['file'] ?? null);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        LogError('POST /upload/file ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Internal server error'], 500);
    }
})->add($jwtMiddleware);
