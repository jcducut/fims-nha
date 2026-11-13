<?php
// __BASE__/app/routes/bank-management.php
// Backs app/pages/bank-management-page.php — see app/services/BankManagementService.php.
namespace PHPMaker2024\FIMS;

// Static routes must stay registered before the GET /{id} catch-all below —
// FastRoute errors ("shadowed by previously defined variable route") if a
// static route at the same path depth is registered after a dynamic one
// (same gotcha documented in app/routes/check-processing.php).
$app->get("/bank-management/stats", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getStats());
});

$app->get("/bank-management/list", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getList($request->getQueryParams()));
});

$app->get("/bank-management/dormancy-alerts", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDormancyAlerts());
});

$app->get("/bank-management/lookup/responsibility-centers", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getResponsibilityCenters());
});

$app->get("/bank-management/lookup/fund-sources", function ($request, $response) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getFundSources());
});

$app->post("/bank-management/store", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->store($data));
});

$app->get("/bank-management/{id}", function ($request, $response, $args) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAccount($args['id']));
});

$app->post("/bank-management/{id}/update", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->update($args['id'], $data));
});

$app->post("/bank-management/{id}/status", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->changeStatus($args['id'], $data));
});

$app->get("/bank-management/{id}/documents", function ($request, $response, $args) {
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDocuments($args['id']));
});

$app->post("/bank-management/{id}/documents/link", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveDocumentLink($args['id'], $data));
});

$app->post("/bank-management/{id}/documents/remove", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\BankManagementService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->removeDocumentLink($args['id'], $data));
});
