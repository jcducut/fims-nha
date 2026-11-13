<?php
// __BASE__/app/routes/check-processing.php
// Backs app/pages/check-disbursement-voucher-page.php — see app/services/CheckProcessingService.php
// for the STUB IMPLEMENTATION note (treasury/accounting/shared/workflow/fmd schemas
// don't exist in this project's database yet; every endpoint below returns an
// empty-but-structurally-correct response until that migration happens).
namespace PHPMaker2024\FIMS;

// ── Check Preparation ──
$app->get("/check-processing/preparation/bank-accounts", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBankAccounts());
});

$app->get("/check-processing/preparation/fund-sources", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getFundSources());
});

$app->get("/check-processing/preparation/checks", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getChecksList($request->getQueryParams()));
});

$app->get("/check-processing/preparation/dvs-for-prep", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDvsForPrep($request->getQueryParams()));
});

$app->get("/check-processing/preparation/unused-checks/{bankId}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getUnusedChecks($args['bankId']));
});

$app->get("/check-processing/preparation/cash-availability", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    $bankAccountId = $request->getQueryParams()['bank_account_id'] ?? null;
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCashAvailability($bankAccountId));
});

$app->get("/check-processing/preparation/check/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCheck($args['id']));
});

$app->get("/check-processing/preparation/check/{id}/history", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCheckHistory($args['id']));
});

$app->post("/check-processing/preparation/check/{id}/mark-printed", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->markPrinted($args['id']));
});

// Streams the print-agent installer zip — not JSON, so this bypasses the
// json-write convention the rest of this file uses.
$app->get("/check-processing/print-agent-package", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    $response->getBody()->write($service->generatePrintAgentPackage());
    return $response
        ->withHeader('Content-Type', 'application/zip')
        ->withHeader('Content-Disposition', 'attachment; filename="fims-print-agent-setup.zip"');
});

$app->post("/check-processing/preparation/save", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveCheck($data));
});

$app->get("/check-processing/preparation/subsidy-report", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getSubsidyReport($request->getQueryParams()));
});

// ── Check Registry (blank check stock) ──
$app->get("/check-processing/registry/series-list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getSeriesList());
});

$app->get("/check-processing/registry/list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegistryList($request->getQueryParams()));
});

$app->get("/check-processing/registry/booklets", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    $bankAccountId = $request->getQueryParams()['bank_account_id'] ?? null;
    return $response->withHeader('Content-Type', 'application/json')->write($service->getBooklets($bankAccountId));
});

$app->post("/check-processing/registry/update-status", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->updateRegisterStatus($data));
});

$app->post("/check-processing/registry/add-batch", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->addRegisterBatch($data));
});

$app->get("/check-processing/registry/summary", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegistrySummary());
});

// Must stay registered after the static /registry/* routes above — FastRoute
// errors ("shadowed by previously defined variable route") if a dynamic
// route at the same path depth is registered before a static one.
$app->get("/check-processing/registry/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegistryEntry($args['id']));
});

// ── Check Released to Claimants ──
$app->get("/check-processing/releases", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getReleasesList($request->getQueryParams()));
});

$app->get("/check-processing/releases/unclaimed", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getUnclaimedReleases($request->getQueryParams()));
});

$app->get("/check-processing/releases/email-log", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getEmailLog($request->getQueryParams()));
});

$app->get("/check-processing/releases/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRelease($args['id']));
});

$app->post("/check-processing/releases/save", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveRelease($data));
});

$app->get("/check-processing/releases/{id}/preview-notice", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->previewReleaseNotice($args['id'], $request->getQueryParams()));
});

$app->post("/check-processing/releases/{id}/send-notice", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->sendReleaseNotice($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/follow-up", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveReleaseFollowUp($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/acknowledge", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->acknowledgeRelease($args['id'], $data));
});

$app->post("/check-processing/releases/{id}/update-status", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->updateReleaseStatus($args['id'], $data));
});

$app->get("/check-processing/checks/lookup", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->lookupChecks($request->getQueryParams()));
});

$app->get("/check-processing/checks/{id}/department-emails", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCheckDepartmentEmails($args['id']));
});

// ── Check Released to Regions/Departments ──
$app->get("/check-processing/region-release/departments", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getDepartments());
});

$app->get("/check-processing/region-release/pending/{deptId}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getPendingRegionReleases($args['deptId']));
});

$app->get("/check-processing/region-release/list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegionReleasesList($request->getQueryParams()));
});

$app->get("/check-processing/region-release/acknowledgment-list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegionAcknowledgmentList($request->getQueryParams()));
});

$app->get("/check-processing/region-release/email-log", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegionEmailLog($request->getQueryParams()));
});

$app->get("/check-processing/region-release/dv-monitoring", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegionDvMonitoring($request->getQueryParams()));
});

// Must stay registered before the GET /region-release/{id} catch-all below —
// FastRoute errors ("shadowed by previously defined variable route") if a
// static route at the same path depth is registered after a dynamic one.
$app->get("/check-processing/region-release/preview-notice", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->previewRegionReleaseNotice($request->getQueryParams()));
});

$app->get("/check-processing/region-release/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegionRelease($args['id']));
});

$app->post("/check-processing/region-release/{id}/mark-stale", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->markRegionReleaseStale($args['id'], $data));
});

$app->get("/check-processing/region-release/{id}/documents", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getRegionReleaseDocuments($args['id']));
});

$app->post("/check-processing/region-release/save", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveRegionRelease($data));
});

$app->post("/check-processing/region-release/{id}/acknowledge", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->acknowledgeRegionRelease($args['id'], $data));
});

$app->post("/check-processing/region-release/{id}/record-dv-return", function ($request, $response, $args) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->recordRegionDvReturn($args['id'], $data));
});

$app->post("/check-processing/region-release/send-notice", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->sendRegionReleaseNotice($data));
});

$app->post("/check-processing/region-release/resend-email/{logId}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->resendRegionEmail($args['logId']));
});

$app->post("/check-processing/region-release/save-drive-link", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->saveRegionDriveLink($data));
});

// ── Check Cancellation & Replacements ──
$app->get("/check-processing/cancellation/kpis", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancellationKpis());
});

$app->get("/check-processing/cancellation/lookups", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancellationLookups());
});

$app->get("/check-processing/cancellation/list", function ($request, $response) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancellationList($request->getQueryParams()));
});

$app->post("/check-processing/cancellation/cancel", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->cancelCheck($data));
});

$app->post("/check-processing/cancellation/replace", function ($request, $response) {
    $data = $request->getParsedBody();
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->replaceCheck($data));
});

$app->get("/check-processing/cancellation/{id}", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancelledCheck($args['id']));
});

$app->get("/check-processing/cancellation/{id}/history", function ($request, $response, $args) {
    $service = new Service\CheckProcessingService();
    return $response->withHeader('Content-Type', 'application/json')->write($service->getCancelledCheckHistory($args['id']));
});
