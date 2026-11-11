<?php

namespace PHPMaker2024\FIMS;

use PHPMaker2024\FIMS\Service\ChartOfAccountsService;

$ChartOfAccountsService = new ChartOfAccountsService();

// Fetch all — no pagination (for dropdowns); ?prefix=5-01 scopes to postable
// accounts under that chart-of-accounts branch (legacy); ?postable_only=1
// returns all active+postable accounts regardless of branch — used by the
// Budget Request form's Account Code picker. ?for_budget_proposal=1 (only
// meaningful alongside postable_only=1) further narrows that to accounts
// flagged for_budget_proposal = TRUE.
$app->get('/chart_of_accounts/all', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $params                = $request->getQueryParams();
        $prefix                = $params['prefix'] ?? '';
        $postableOnly          = ($params['postable_only'] ?? '') === '1';
        $forBudgetProposalOnly = ($params['for_budget_proposal'] ?? '') === '1';

        if ($prefix !== '') {
            $result = $ChartOfAccountsService->fetchByPrefix($prefix);
        } elseif ($postableOnly) {
            $result = $ChartOfAccountsService->fetchPostable($params['search'] ?? '', $forBudgetProposalOnly);
        } else {
            $result = $ChartOfAccountsService->fetchAlls(null, 10, $params['search'] ?? '', '', '', '', 'Active');
        }

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Current user's region scope (Chart of Accounts page locks the Region filter with this)
$app->get('/chart-of-accounts/my-scope', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $scope = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));

        return $response->withJson(['success' => true, 'data' => $scope]);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all — no pagination, filtered and region-scoped (Chart of Accounts page tree + parent picker)
$app->get('/chart-of-accounts/all', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $params     = $request->getQueryParams();
        $scope      = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));
        $regionCode = $scope['region_code'] ?? ($params['region_code'] ?? '');

        $result = $ChartOfAccountsService->fetchAlls(
            null,
            10,
            $params['search'] ?? '',
            $params['account_type'] ?? '',
            $regionCode,
            $params['project_code'] ?? '',
            $params['status'] ?? ''
        );

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Regions lookup (for Region filter/dropdown)
$app->get('/chart-of-accounts/regions', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $result = $ChartOfAccountsService->fetchRegions();

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Fetch all (with pagination)
$app->get('/chart-of-accounts/list', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $params = $request->getQueryParams();
        $page   = isset($params['page']) ? (int) $params['page'] : 1;
        $limit  = isset($params['limit']) ? (int) $params['limit'] : 10;
        $scope  = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));
        $regionCode = $scope['region_code'] ?? ($params['region_code'] ?? '');

        $result = $ChartOfAccountsService->fetchAlls(
            $page,
            $limit,
            $params['search'] ?? '',
            $params['account_type'] ?? '',
            $regionCode,
            $params['project_code'] ?? '',
            $params['status'] ?? ''
        );

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Read one
$app->get('/chart-of-accounts/read/{id}', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $result = $ChartOfAccountsService->fetchById($args['id']);
        $scope  = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));

        if ($result['success'] && $scope['region_code'] && ($result['data']['region_code'] ?? null) !== $scope['region_code']) {
            return $response->withJson(['success' => false, 'message' => "Account '{$args['id']}' not found."], 404);
        }

        return $response->withJson($result);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Add
$app->post('/chart-of-accounts/add', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $body  = $request->getParsedBody();
        $scope = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));
        if ($scope['region_code']) {
            $body['region_code'] = $scope['region_code'];
        }

        $result = $ChartOfAccountsService->create($body);

        return $response->withJson($result, $result['success'] ? 201 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Update
$app->put('/chart-of-accounts/update/{id}', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $scope = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));

        if ($scope['region_code']) {
            $existing = $ChartOfAccountsService->fetchById($args['id']);
            if (!$existing['success'] || ($existing['data']['region_code'] ?? null) !== $scope['region_code']) {
                return $response->withJson(['success' => false, 'message' => "Account '{$args['id']}' not found."], 404);
            }
        }

        $body = $request->getParsedBody();
        if ($scope['region_code']) {
            $body['region_code'] = $scope['region_code'];
        }

        $result = $ChartOfAccountsService->update($args['id'], $body);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Toggle status (Active <-> Inactive)
$app->patch('/chart-of-accounts/{id}/toggle-status', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $scope = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));

        if ($scope['region_code']) {
            $existing = $ChartOfAccountsService->fetchById($args['id']);
            if (!$existing['success'] || ($existing['data']['region_code'] ?? null) !== $scope['region_code']) {
                return $response->withJson(['success' => false, 'message' => "Account '{$args['id']}' not found."], 404);
            }
        }

        $result = $ChartOfAccountsService->toggleStatus($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);

// Delete
$app->delete('/chart-of-accounts/delete/{id}', function ($request, $response, $args) use ($ChartOfAccountsService) {
    try {
        $scope = $ChartOfAccountsService->getUserScope((int) $request->getAttribute('user_id'));

        if ($scope['region_code']) {
            $existing = $ChartOfAccountsService->fetchById($args['id']);
            if (!$existing['success'] || ($existing['data']['region_code'] ?? null) !== $scope['region_code']) {
                return $response->withJson(['success' => false, 'message' => "Account '{$args['id']}' not found."], 404);
            }
        }

        $result = $ChartOfAccountsService->delete($args['id']);

        return $response->withJson($result, $result['success'] ? 200 : 400);
    } catch (\Exception $e) {
        return $response->withJson(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->add($jwtMiddleware);
