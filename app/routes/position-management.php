<?php
// __BASE__/app/routes/position-management.php
namespace PHPMaker2024\FIMS;

// Create a position category (no redirect to PositionCategoriesAdd)
$app->post("/position-management/categories", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\PositionManagementService();
    $result = $service->createCategory($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Update a position category (no redirect to PositionCategoriesEdit)
$app->put("/position-management/categories/{id}", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\PositionManagementService();
    $result = $service->updateCategory($args['id'], $data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Create a position (no redirect to PositionsAdd)
$app->post("/position-management/positions", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\PositionManagementService();
    $result = $service->createPosition($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Update a position (no redirect to PositionsEdit)
$app->put("/position-management/positions/{id}", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\PositionManagementService();
    $result = $service->updatePosition($args['id'], $data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});
