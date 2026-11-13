<?php
// __BASE__/app/routes/cash-position.php
// Backs app/pages/cash-position-page.php — see app/services/CashPositionService.php.
namespace PHPMaker2024\FIMS;

// ── Lookups ──
$app->get("/cash-position/lookup/fund-sources", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getFundSourcesLookup());
});

$app->get("/cash-position/lookup/regions", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegionsLookup());
});

$app->get("/cash-position/lookup/bank-accounts", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBankAccountsLookup());
});

// ── Dashboard ──
$app->get("/cash-position/dashboard/kpis", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDashboardKpis($request->getQueryParams()));
});

$app->get("/cash-position/dashboard/list", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDashboardList($request->getQueryParams()));
});

$app->get("/cash-position/dashboard/account-detail/{id}", function ($request, $response, $args) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAccountDetail($args['id'], $request->getQueryParams()));
});

// ── Position Updates ──
$app->get("/cash-position/updates/kpis", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getUpdatesKpis());
});

$app->get("/cash-position/updates/list", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getUpdatesList($request->getQueryParams()));
});

$app->get("/cash-position/updates/computed-balance", function ($request, $response) {
    $service = new Service\CashPositionService();
    $p = $request->getQueryParams();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getComputedBalance($p['bank_account_id'] ?? 0, $p['position_date'] ?? ''));
});

$app->post("/cash-position/updates/save", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveManualEntry($data));
});

// Must stay registered before the /updates/{id} catch-all below — FastRoute
// errors ("shadowed by previously defined variable route") if a static
// route at the same path depth is registered after a dynamic one.
$app->get("/cash-position/updates/activity-log", function ($request, $response) {
    $service = new Service\CashPositionService();
    $limit = $request->getQueryParams()['limit'] ?? 10;
    return $response->withHeader('Content-Type', 'application/json')->write($service->getActivityLog($limit));
});

$app->get("/cash-position/updates/{id}", function ($request, $response, $args) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCashPositionById($args['id']));
});

$app->post("/cash-position/updates/{id}/update", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->updateCashPosition($args['id'], $data));
});

// ── Available Cash Balances Tracking ──
$app->get("/cash-position/available-balances/list", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAvailableBalances($request->getQueryParams()));
});

$app->get("/cash-position/available-balances/alerts", function ($request, $response) {
    $service = new Service\CashPositionService();
    $threshold = $request->getQueryParams()['threshold'] ?? 500000;
    return $response->withHeader('Content-Type', 'application/json')->write($service->getLowBalanceAlerts($threshold));
});

$app->get("/cash-position/available-balances/grand-totals", function ($request, $response) {
    $service = new Service\CashPositionService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getGrandTotals());
});

// Query-param based (not a path segment) since fund_source is free text
// and may contain characters that don't round-trip cleanly through a URL
// path segment (slashes, etc.) — unlike TREASURY's integer fund_source_id.
$app->get("/cash-position/available-balances/accounts", function ($request, $response) {
    $service = new Service\CashPositionService();
    $fundSource = $request->getQueryParams()['fund_source'] ?? '';
    return $response->withHeader('Content-Type', 'application/json')->write($service->getAccountsByFundSource($fundSource));
});

$app->get("/cash-position/available-balances/history", function ($request, $response) {
    $service = new Service\CashPositionService();
    $p = $request->getQueryParams();
    return $response->withHeader('Content-Type', 'application/json')->write(
        $service->getBalanceHistory($p['fund_source'] ?? '', $p['date_from'] ?? '', $p['date_to'] ?? '', $p['limit'] ?? 30)
    );
});

// ── Reports — see app/services/CashPositionReportsService.php for which
// report types are real vs. structurally-correct stubs. ──
$app->get("/cash-position/reports/data", function ($request, $response) {
    $service = new Service\CashPositionReportsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getReportData($request->getQueryParams()));
});

$app->post("/cash-position/reports/fy-opening-balance", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CashPositionReportsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->prepareFYOpeningBalance($data));
});

$app->get("/cash-position/reports/lookup/bank-accounts", function ($request, $response) {
    $service = new Service\CashPositionReportsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBankAccounts());
});

$app->get("/cash-position/reports/lookup/fund-sources", function ($request, $response) {
    $service = new Service\CashPositionReportsService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getFundSources());
});

// ── SOA (bank Statement of Account) upload — see
// app/services/CashPositionSoaService.php for scope notes. ──
$app->post("/cash-position/upload-soa", function ($request, $response) {
    $service = new Service\CashPositionSoaService();
    $uploadedFiles = $request->getUploadedFiles();
    $result = $service->uploadSoa($uploadedFiles['soa_file'] ?? null, $request->getParsedBody() ?: []);
    return $response->withHeader('Content-Type', 'application/json')->write(json_encode($result));
});

$app->post("/cash-position/confirm-upload", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CashPositionSoaService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->confirmUpload($data));
});

$app->get("/cash-position/soa-comparison", function ($request, $response) {
    $service = new Service\CashPositionSoaService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getSoaComparison($request->getQueryParams()));
});

$app->get("/cash-position/soa-groups", function ($request, $response) {
    $service = new Service\CashPositionSoaService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getSoaGroups($request->getQueryParams()));
});

$app->get("/cash-position/soa-transactions", function ($request, $response) {
    $service = new Service\CashPositionSoaService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getSoaTransactions($request->getQueryParams()));
});

$app->get("/cash-position/soa-balance/{bankAccountId}", function ($request, $response, $args) {
    $service = new Service\CashPositionSoaService();
    $asOf = $request->getQueryParams()['as_of'] ?? null;
    return $response->withHeader('Content-Type', 'application/json')->write($service->getLatestSoaBalance($args['bankAccountId'], $asOf));
});
