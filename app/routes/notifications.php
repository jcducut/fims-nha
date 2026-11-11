<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\NotificationService;

/**
 * Notification hub API (schema uac) — see _shared/NOTIFICATION-IMPLEMENTATION-PLAN.md §4.
 *
 *   GET  /notifications/recent?limit=   { success, data:[…], unread_count }
 *   GET  /notifications/count           { success, unread_count }
 *   POST /notifications/mark-read       { id }            -> { success }
 *   POST /notifications/mark-all-read                     -> { success, updated }
 *   POST /notifications/send            superadmin only   -> { success, id }
 *
 * Every route is authenticated through $jwtMiddleware; the caller is the JWT's user_id (never a parameter).
 * The old /notifications/test route (hard-coded broadcast) was removed on purpose — plan §10.
 */
$NotificationService = new NotificationService();

$notifyUser = fn ($request): int => (int) $request->getAttribute('user_id');

$notifyUnauthorized = fn ($response) => $response->withJson(['success' => false, 'message' => 'Not authenticated.'], 401);

$app->get('/notifications/recent', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        $limit = (int) ($request->getQueryParams()['limit'] ?? 10);

        return $response->withJson($NotificationService->recent($uid, $limit));
    } catch (\Throwable $e) {
        LogError('GET /notifications/recent ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not load notifications.'], 500);
    }
})->add($jwtMiddleware);

$app->get('/notifications/count', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        return $response->withJson(['success' => true, 'unread_count' => $NotificationService->unreadCount($uid)]);
    } catch (\Throwable $e) {
        LogError('GET /notifications/count ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not load the count.'], 500);
    }
})->add($jwtMiddleware);

$app->post('/notifications/mark-read', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    $id = trim((string) (($request->getParsedBody() ?: [])['id'] ?? ''));
    if ($id === '') {
        return $response->withJson(['success' => false, 'message' => 'Notification ID required.'], 400);
    }
    try {
        return $response->withJson(['success' => $NotificationService->markRead($id, $uid)]);
    } catch (\Throwable $e) {
        LogError('POST /notifications/mark-read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not mark as read.'], 500);
    }
})->add($jwtMiddleware);

$app->post('/notifications/mark-all-read', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        return $response->withJson(['success' => true, 'updated' => $NotificationService->markAllRead($uid)]);
    } catch (\Throwable $e) {
        LogError('POST /notifications/mark-all-read ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not mark all as read.'], 500);
    }
})->add($jwtMiddleware);

// Privileged: only a superadmin may broadcast arbitrary notifications. Application code should call
// NotificationService::toUser() / toUserLevel() / … directly instead of going through HTTP.
$app->post('/notifications/send', function ($request, $response) use ($NotificationService, $notifyUser, $notifyUnauthorized) {
    $uid = $notifyUser($request);
    if ($uid <= 0) {
        return $notifyUnauthorized($response);
    }
    try {
        if (!$NotificationService->isSuperAdmin($uid)) {
            return $response->withJson(['success' => false, 'message' => 'Not allowed to send notifications.'], 403);
        }
        $result = $NotificationService->sendValidated($request->getParsedBody() ?: [], $uid);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Throwable $e) {
        LogError('POST /notifications/send ' . $e->getMessage());

        return $response->withJson(['success' => false, 'message' => 'Could not send the notification.'], 500);
    }
})->add($jwtMiddleware);
