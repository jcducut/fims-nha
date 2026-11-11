<?php

namespace PHPMaker2024\FIMS\Service;

/**
 * Backs the Check Disbursement Voucher module (app/pages/check-disbursement-voucher.php),
 * ported from the TREASURY reference project's 7 check-processing pages
 * (check-preparation, check-registry, check-released-to-claimants,
 * check-released-regions, check-cancellation-replacements, check-inventory-raf).
 *
 * STUB IMPLEMENTATION: the `treasury` schema (checks, check_register,
 * check_releases, check_release_claimants, check_release_documents,
 * check_release_email_log, cash_position) does not exist in this project's
 * database yet, nor do the `accounting`/`shared`/`workflow`/`fmd` schemas
 * several of these features also join against in the source project. Every
 * method here returns a structurally-correct but empty response so the UI
 * renders and behaves correctly (tables show "no records", KPI cards show
 * zero) without erroring. Replace each method's body with real queries once
 * the schema is migrated.
 */
class CheckProcessingService
{
    // Shape expected by jQuery DataTables server-side processing.
    private function emptyDataTable($draw)
    {
        return json_encode([
            "draw" => (int)$draw,
            "recordsTotal" => 0,
            "recordsFiltered" => 0,
            "data" => [],
        ]);
    }

    private function ok($data = [])
    {
        return json_encode(["success" => true, "data" => $data]);
    }

    // ── Check Preparation ──
    public function getBankAccounts()
    {
        return $this->ok([]);
    }

    public function getFundSources()
    {
        return $this->ok([]);
    }

    public function getChecksList($params)
    {
        return $this->emptyDataTable($params["draw"] ?? 1);
    }

    public function getDvsForPrep($params)
    {
        return $this->emptyDataTable($params["draw"] ?? 1);
    }

    public function getUnusedChecks($bankAccountId)
    {
        return $this->ok([]);
    }

    public function getCashAvailability($bankAccountId)
    {
        return $this->ok(["available" => true, "balance" => 0]);
    }

    public function getCheck($id)
    {
        return json_encode(["success" => false, "message" => "Not found (schema not migrated yet)"]);
    }

    public function getCheckHistory($id)
    {
        return $this->ok([]);
    }

    public function saveCheck($data)
    {
        return json_encode(["success" => false, "message" => "Check preparation is not yet wired to a database — schema migration pending."]);
    }

    public function getSubsidyReport($params)
    {
        return $this->ok([]);
    }

    // ── Check Registry (blank check stock) ──
    public function getSeriesList()
    {
        return $this->ok([]);
    }

    public function getRegistryList($params)
    {
        return $this->ok(["records" => [], "total" => 0]);
    }

    public function getBooklets($bankAccountId)
    {
        return $this->ok([]);
    }

    public function updateRegisterStatus($data)
    {
        return json_encode(["success" => false, "message" => "Check registry is not yet wired to a database — schema migration pending."]);
    }

    public function addRegisterBatch($data)
    {
        return json_encode(["success" => false, "message" => "Check registry is not yet wired to a database — schema migration pending."]);
    }

    public function getRegistrySummary()
    {
        return $this->ok(["UNUSED" => 0, "ISSUED" => 0, "CANCELLED" => 0]);
    }

    // ── Check Released to Claimants ──
    public function getReleasesList($params)
    {
        return $this->emptyDataTable($params["draw"] ?? 1);
    }

    public function getUnclaimedReleases($params)
    {
        return $this->ok([]);
    }

    public function getEmailLog($params)
    {
        return $this->emptyDataTable($params["draw"] ?? 1);
    }

    public function getRelease($id)
    {
        return json_encode(["success" => false, "message" => "Not found (schema not migrated yet)"]);
    }

    public function saveRelease($data)
    {
        return json_encode(["success" => false, "message" => "Check release is not yet wired to a database — schema migration pending."]);
    }

    public function previewReleaseNotice($id, $params)
    {
        return $this->ok(["html" => "<p>Email preview unavailable until the schema is migrated.</p>"]);
    }

    public function sendReleaseNotice($id, $data)
    {
        return json_encode(["success" => false, "message" => "Email notice sending is not yet wired — schema migration pending."]);
    }

    public function saveReleaseFollowUp($id, $data)
    {
        return json_encode(["success" => false, "message" => "Follow-up tracking is not yet wired — schema migration pending."]);
    }

    public function acknowledgeRelease($id, $data)
    {
        return json_encode(["success" => false, "message" => "Acknowledgment is not yet wired — schema migration pending."]);
    }

    public function updateReleaseStatus($id, $data)
    {
        return json_encode(["success" => false, "message" => "Status update is not yet wired — schema migration pending."]);
    }

    public function lookupChecks($params)
    {
        return $this->ok([]);
    }

    public function getCheckDepartmentEmails($checkId)
    {
        return $this->ok([]);
    }

    // ── Check Released to Regions ──
    public function getRegionReleasesList($params)
    {
        return $this->ok(["records" => [], "total" => 0]);
    }

    public function getRegionAcknowledgmentList($params)
    {
        return $this->ok(["records" => [], "total" => 0]);
    }

    public function getRegionEmailLog($params)
    {
        return $this->ok(["records" => [], "total" => 0]);
    }

    public function getRegionDvMonitoring($params)
    {
        return $this->ok(["records" => [], "total" => 0]);
    }

    public function getRegionRelease($id)
    {
        return json_encode(["success" => false, "message" => "Not found (schema not migrated yet)"]);
    }

    public function markRegionReleaseStale($id, $data)
    {
        return json_encode(["success" => false, "message" => "Not yet wired — schema migration pending."]);
    }

    public function getRegionReleaseDocuments($id)
    {
        return $this->ok([]);
    }

    public function saveRegionRelease($data)
    {
        return json_encode(["success" => false, "message" => "Region release is not yet wired — schema migration pending."]);
    }

    public function acknowledgeRegionRelease($id, $data)
    {
        return json_encode(["success" => false, "message" => "Acknowledgment is not yet wired — schema migration pending."]);
    }

    public function recordRegionDvReturn($id, $data)
    {
        return json_encode(["success" => false, "message" => "DV return tracking is not yet wired — schema migration pending."]);
    }

    public function previewRegionReleaseNotice($params)
    {
        return $this->ok(["html" => "<p>Email preview unavailable until the schema is migrated.</p>"]);
    }

    public function sendRegionReleaseNotice($data)
    {
        return json_encode(["success" => false, "message" => "Email notice sending is not yet wired — schema migration pending."]);
    }

    public function resendRegionEmail($logId)
    {
        return json_encode(["success" => false, "message" => "Not yet wired — schema migration pending."]);
    }

    public function saveRegionDriveLink($data)
    {
        return json_encode(["success" => false, "message" => "Not yet wired — schema migration pending."]);
    }

    // ── Check Cancellation & Replacements ──
    public function getCancellationKpis()
    {
        return $this->ok([
            "total_cancelled" => 0,
            "with_replacement" => 0,
            "pending_replacement" => 0,
            "cancelled_amount" => 0,
        ]);
    }

    public function getCancellationLookups()
    {
        return $this->ok(["bank_accounts" => [], "eligible_checks" => []]);
    }

    public function getCancellationList($params)
    {
        return $this->emptyDataTable($params["draw"] ?? 1);
    }

    public function cancelCheck($data)
    {
        return json_encode(["success" => false, "message" => "Check cancellation is not yet wired — schema migration pending."]);
    }

    public function replaceCheck($data)
    {
        return json_encode(["success" => false, "message" => "Check replacement is not yet wired — schema migration pending."]);
    }

    public function getCancelledCheck($id)
    {
        return json_encode(["success" => false, "message" => "Not found (schema not migrated yet)"]);
    }

    public function getCancelledCheckHistory($id)
    {
        return $this->ok([]);
    }

    // ── Check Inventory / RAF (server-rendered, no AJAX — see page PHP) ──
}
