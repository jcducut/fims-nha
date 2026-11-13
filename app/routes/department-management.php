<?php
// __BASE__/app/routes/department-management.php
namespace PHPMaker2024\FIMS;

// Create a department directly from the Department Management page
// (no redirect to DepartmentsAdd)
$app->post("/department-management/departments", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\DepartmentManagementService();
    $result = $service->createDepartment($data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});

// Update a department directly from the Department Management page
// (no redirect to DepartmentsEdit)
$app->put("/department-management/departments/{id}", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\DepartmentManagementService();
    $result = $service->updateDepartment($args['id'], $data);
    return $response->withHeader('Content-Type', 'application/json')->write($result);
});
