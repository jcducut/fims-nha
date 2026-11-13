<?php
// __BASE__/app/routes/user-access.php
namespace PHPMaker2024\FIMS;

// Create a user directly from the UserAccess page (no redirect to UsersAdd)
$app->post("/user-access/users", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->createUser($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Update a user directly from the UserAccess page (no redirect to UsersEdit)
$app->put("/user-access/users/{id}", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->updateUser($args['id'], $data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Save a user level's module permissions from the UserAccess page
// (no redirect to UserLevelPermissionsList)
$app->post("/user-access/permissions", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->savePermissions($data['user_level_id'] ?? null, $data['permissions'] ?? []);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Reconcile uac.user_level_permissions with PHPMaker's own table/page registry
// ($USER_LEVEL_TABLES in src/userlevelsettings.php) — adds zero-permission rows
// for any module not yet listed for a user level, for every user level.
$app->post("/user-access/sync-modules", function ($request, $response) {
    $service = new Service\UserAccessService();
    $result = $service->syncPermissionModules();
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Rename a module/page row's display caption in the Permissions Management matrix
// (stored in permission_module_captions; does not touch PHPMaker's generated registry)
$app->post("/user-access/module-caption", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->renameModuleCaption($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Remove (hide) or restore a module/page row in the Permissions Management matrix.
// Removing only excludes it from the list - underlying permission rows are untouched
// and it can be restored via "Show hidden modules".
$app->post("/user-access/module-visibility", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->setModuleHidden($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Create a user level (role) directly from the UserAccess page (no redirect to UserLevelsAdd)
$app->post("/user-access/user-levels", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->createUserLevel($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Update a user level (role) directly from the UserAccess page (no redirect to UserLevelsEdit)
$app->put("/user-access/user-levels/{id}", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->updateUserLevel($args['id'], $data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Assign a user level (role) to a user directly from the UserAccess page
// (no redirect to UserLevelAssignmentsAdd)
$app->post("/user-access/assignments", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\UserAccessService();
    $result = $service->assignUserLevel($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});
