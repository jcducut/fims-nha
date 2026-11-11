<?php
/**
 * Check Disbursement Voucher — Check Processing module.
 *
 * Consolidates the 6 check-processing workflow pages from the TREASURY
 * reference project (_assets/TREASURY/app/pages/) into one tabbed page:
 *   1. Check Preparation           — issue checks against approved DVs
 *   2. Check Registry              — blank check stock (booklets) inventory
 *   3. Released to Claimants       — direct/email release to payees
 *   4. Released to Regions/Depts   — release to originating offices
 *   5. Cancellation & Replacements — cancel/replace issued checks
 *   6. Inventory (RAF)             — Report of Accountable Forms
 *
 * NOT ported: check_disbursement_ftla_dashboard.php — it's a combined
 * check+FTLA reporting dashboard, not a check-processing workflow page, and
 * half its widgets depend on the separate FTLA/fund-transfer module.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-tabs-underline, fims-modal-overlay, etc.) match the real classes
 * defined in app/fims-css/fims.css (merged in from the TREASURY reference
 * project's own fims-treasury.css), verified against actual usage in its
 * source pages rather than guessed.
 *
 * STATUS: UI shell only. The `treasury` schema (checks, check_register,
 * check_releases, etc.) and the `accounting`/`shared`/`workflow`/`fmd`
 * schemas several source pages also join against don't exist in this
 * project's database yet. Every backend call here hits
 * app/routes/check-processing.php, which currently returns
 * empty-but-valid responses via app/services/CheckProcessingService.php
 * (see that file's header comment) — tables render correctly with "no
 * records" until that schema migration happens and the stub methods are
 * replaced with real queries.
 */

namespace PHPMaker2024\FIMS;

$cpCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
$cpJsVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-js/fims-treasury.js');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $cpCssVer ?>" />
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js" defer></script>
<script src="/app/fims-js/fims-treasury.js?v=<?= $cpJsVer ?>" defer></script>

<div class="fims-wrap" id="cpModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-money-check-alt"></i></div>
            <h1>Check Disbursement Voucher</h1>
            <p>Check preparation, registry, release, and cancellation — Disbursing Section</p>
        </div>
    </div>

    <div class="fims-tabs-underline mb-3" id="cpTabNav">
        <button class="fims-tab-underline active" data-cp-tab="prep">Check Preparation</button>
        <button class="fims-tab-underline" data-cp-tab="registry">Check Registry</button>
        <button class="fims-tab-underline" data-cp-tab="claimants">Released to Claimants</button>
        <button class="fims-tab-underline" data-cp-tab="regions">Released to Regions</button>
        <button class="fims-tab-underline" data-cp-tab="cancel">Cancellation &amp; Replacements</button>
        <button class="fims-tab-underline" data-cp-tab="raf">Inventory (RAF)</button>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 1: CHECK PREPARATION
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content active" id="cpPrep">
        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <input type="text" class="fims-input fims-input-sm" id="prepFilterCheckNo" placeholder="Check Number" style="width:140px">
                    <input type="text" class="fims-input fims-input-sm" id="prepFilterDvNo" placeholder="DV Number" style="width:140px">
                    <input type="text" class="fims-input fims-input-sm" id="prepFilterPayee" placeholder="Payee" style="width:160px">
                    <select class="fims-select fims-input-sm" id="prepFilterBank" style="width:180px"><option value="">All Banks</option></select>
                    <input type="date" class="fims-input fims-input-sm" id="prepFilterDateFrom">
                    <span style="font-size:11px;color:var(--fims-text-subtle)">–</span>
                    <input type="date" class="fims-input fims-input-sm" id="prepFilterDateTo">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="prepFilterBtn"><i class="fas fa-filter mr-1"></i>Filter</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="prepResetBtn">Reset</button>
                </div>
            </div>
        </div>

        <div class="cp-status-tabs" id="prepStatusTabs">
            <?php foreach (["" => "All", "prepared" => "Prepared", "released" => "Released", "encashed" => "Encashed", "outstanding" => "Outstanding", "stale" => "Stale", "cancelled" => "Cancelled", "voided" => "Voided"] as $val => $label): ?>
            <button type="button" class="fims-badge cp-status-tab<?= $val === "" ? " active" : "" ?>" data-status="<?= $val ?>"><?= $label ?></button>
            <?php endforeach; ?>
        </div>

        <div class="fims-table-panel" style="border-radius:0">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Checks</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="prepBatchPrintBtn"><i class="fas fa-print mr-1"></i>Batch Print</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="prepSubsidyReportBtn"><i class="fas fa-file-invoice-dollar mr-1"></i>Subsidy Report</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="prepPrepareBtn"><i class="fas fa-plus mr-1"></i>Prepare Check</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="prepChecksTable">
                    <thead>
                        <tr>
                            <th style="width:32px"><input type="checkbox" id="prepSelectAll"></th>
                            <th style="width:36px">#</th>
                            <th>Check No.</th>
                            <th>Check Date</th>
                            <th>DV Number</th>
                            <th>Payee</th>
                            <th>Bank / Account</th>
                            <th class="text-right">Amount</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="width:70px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="10">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 2: CHECK REGISTRY
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpRegistry" style="display:none">
        <div class="fims-stat-grid" id="registryKpiCards" style="grid-template-columns:repeat(3,1fr)">
            <div class="fims-stat-card fims-card-blue">
                <div class="fims-stat-top"><span class="fims-stat-label">Unused / Available</span></div>
                <div class="fims-stat-value" data-kpi="UNUSED">0</div>
            </div>
            <div class="fims-stat-card fims-card-green">
                <div class="fims-stat-top"><span class="fims-stat-label">Issued</span></div>
                <div class="fims-stat-value" data-kpi="ISSUED">0</div>
            </div>
            <div class="fims-stat-card fims-card-red">
                <div class="fims-stat-top"><span class="fims-stat-label">Cancelled</span></div>
                <div class="fims-stat-value" data-kpi="CANCELLED">0</div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="regFilterOfficeType" style="width:150px">
                        <option value="">Office Type: All</option>
                        <option value="main">Main Office</option>
                        <option value="region">Region</option>
                    </select>
                    <select class="fims-select fims-input-sm" id="regFilterBank" style="width:170px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="regFilterSeries" style="width:150px"><option value="">All Series</option></select>
                    <select class="fims-select fims-input-sm" id="regFilterStatus" style="width:150px">
                        <option value="">Status: All</option>
                        <option value="UNUSED">Unused</option>
                        <option value="ISSUED">Issued</option>
                        <option value="ENCASHED">Encashed</option>
                        <option value="OUTSTANDING">Outstanding</option>
                        <option value="CANCELLED">Cancelled</option>
                        <option value="VOIDED">Voided</option>
                        <option value="SPOILED">Spoiled</option>
                        <option value="STALE">Stale</option>
                    </select>
                    <input type="date" class="fims-input fims-input-sm" id="regFilterDateFrom">
                    <input type="date" class="fims-input fims-input-sm" id="regFilterDateTo">
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="regFilterSearch" placeholder="Check # or Series or RAF" style="width:200px">
                    </div>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regSearchBtn"><i class="fas fa-search mr-1"></i>Search</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="regClearBtn">Clear</button>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Check Register</div>
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regAddBatchBtn"><i class="fas fa-plus mr-1"></i>Add Batch</button>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="registryTable">
                    <thead>
                        <tr>
                            <th>#</th><th>Check No.</th><th>Booklet No.</th><th>Entry Date</th><th>Entry Type</th>
                            <th>Account Name</th><th>Account No.</th><th class="text-center">Status</th><th>Series</th>
                            <th>Dept. Type</th><th class="text-center" style="width:70px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="11">No records.</td></tr></tbody>
                </table>
            </div>
            <div class="fims-pagination" id="registryPagination"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 3: RELEASED TO CLAIMANTS
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpClaimants" style="display:none">
        <div class="fims-stat-grid" id="claimantsKpiCards" style="grid-template-columns:repeat(5,1fr)">
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Pending</span></div><div class="fims-stat-value" data-kpi="PENDING">0</div></div>
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Notified</span></div><div class="fims-stat-value" data-kpi="NOTIFIED">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Released</span></div><div class="fims-stat-value" data-kpi="RELEASED">0</div></div>
            <div class="fims-stat-card fims-card-indigo"><div class="fims-stat-top"><span class="fims-stat-label">Acknowledged</span></div><div class="fims-stat-value" data-kpi="ACKNOWLEDGED">0</div></div>
            <div class="fims-stat-card fims-card-red"><div class="fims-stat-top"><span class="fims-stat-label">Unclaimed</span></div><div class="fims-stat-value" data-kpi="UNCLAIMED">0</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="claimFilterSearch" placeholder="Check No. or Payee" style="width:190px">
                    </div>
                    <select class="fims-select fims-input-sm" id="claimFilterStatus" style="width:160px">
                        <option value="">Status: All</option>
                        <option value="PENDING">Pending</option>
                        <option value="NOTIFIED">Notified</option>
                        <option value="RELEASED">Released</option>
                        <option value="ACKNOWLEDGED">Acknowledged</option>
                        <option value="UNCLAIMED">Unclaimed</option>
                    </select>
                    <select class="fims-select fims-input-sm" id="claimFilterType" style="width:150px">
                        <option value="">Type: All</option>
                        <option value="DIRECT">Direct</option>
                        <option value="EMAIL_NOTICE">Email Notice</option>
                    </select>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="claimRecordReleaseBtn"><i class="fas fa-plus mr-1"></i>Record Release</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="claimEmailNoticeBtn"><i class="fas fa-envelope mr-1"></i>Send Email Notice</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="claimFollowUpBtn"><i class="fas fa-bell mr-1"></i>Follow-up Unclaimed</button>
                </div>
            </div>
        </div>

        <div class="fims-tabs-underline mb-0" id="claimSubTabs" style="border-radius:0;border-top:none;">
            <button class="fims-tab-underline active" data-sub-target="claimListPane">Check Release List</button>
            <button class="fims-tab-underline" data-sub-target="claimUnclaimedPane">Unclaimed / Unreleased</button>
            <button class="fims-tab-underline" data-sub-target="claimEmailLogPane">Email Log</button>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-tab-underline-content active" id="claimListPane">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="claimantsTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>Payee / Amount</th><th>Claimant</th>
                                <th>ID Type / No.</th><th>Release Type</th><th>Release Date</th>
                                <th class="text-center">Status</th><th>Acknowledgment</th><th class="text-center" style="width:70px">Actions</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="10">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="claimUnclaimedPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="claimUnclaimedTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>Payee / Amount</th><th>Claimant</th>
                                <th class="text-center">Status</th><th>Days Since Prep.</th><th>Last Follow-up</th>
                                <th>Follow-up Remarks</th><th class="text-center" style="width:70px">Actions</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="9">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="claimEmailLogPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="claimEmailLogTable">
                        <thead>
                            <tr><th>#</th><th>Check No.</th><th>Recipient Email</th><th>Sent At</th><th>Sent By</th><th class="text-center">Status</th><th>Error</th></tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="7">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 4: RELEASED TO REGIONS/DEPARTMENTS
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpRegions" style="display:none">
        <div class="fims-stat-grid" id="regionsKpiCards" style="grid-template-columns:repeat(5,1fr)">
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Pending Release</span></div><div class="fims-stat-value" data-kpi="PENDING">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Released</span></div><div class="fims-stat-value" data-kpi="RELEASED">0</div></div>
            <div class="fims-stat-card fims-card-indigo"><div class="fims-stat-top"><span class="fims-stat-label">Acknowledged</span></div><div class="fims-stat-value" data-kpi="ACKNOWLEDGED">0</div></div>
            <div class="fims-stat-card fims-card-amber"><div class="fims-stat-top"><span class="fims-stat-label">Stale (&gt;180 days)</span></div><div class="fims-stat-value" data-kpi="STALE">0</div></div>
            <div class="fims-stat-card fims-card-violet"><div class="fims-stat-top"><span class="fims-stat-label">Return Paid DVs</span></div><div class="fims-stat-value" data-kpi="DV_RETURNED">0</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="regionFilterDept" style="width:200px"><option value="">All Regions/Depts</option></select>
                    <select class="fims-select fims-input-sm" id="regionFilterStatus" style="width:150px">
                        <option value="">Status: All</option>
                        <option value="PENDING">Pending</option>
                        <option value="RELEASED">Released</option>
                        <option value="UNCLAIMED">Unclaimed</option>
                    </select>
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="regionFilterSearch" placeholder="Check No. or Payee" style="width:190px">
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regionNewReleaseBtn"><i class="fas fa-plus mr-1"></i>New Release</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="regionMonthlyNoticeBtn"><i class="fas fa-envelope mr-1"></i>Send Monthly Notice</button>
                </div>
            </div>
        </div>

        <div class="fims-tabs-underline mb-0" id="regionSubTabs" style="border-radius:0;border-top:none;">
            <button class="fims-tab-underline active" data-sub-target="regionTrackingPane">Check Tracking</button>
            <button class="fims-tab-underline" data-sub-target="regionAckPane">Acknowledgment</button>
            <button class="fims-tab-underline" data-sub-target="regionEmailLogPane">Email Log</button>
            <button class="fims-tab-underline" data-sub-target="regionDvPane">Return Paid DVs</button>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-tab-underline-content active" id="regionTrackingPane">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="regionTrackingTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>Check Date</th><th>Payee</th><th class="text-right">Amount</th>
                                <th>Region/Dept</th><th>Claimant</th><th>Release Date</th><th class="text-center">Status</th>
                                <th>Days Outstanding</th><th class="text-center" style="width:70px">Actions</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="11">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="regionAckPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="regionAckTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>Payee</th><th class="text-right">Amount</th><th>Region/Dept</th>
                                <th>Claimant</th><th>Release Date</th><th>Acknowledgment Date</th><th>ID Submitted</th><th class="text-center" style="width:70px">Actions</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="10">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="regionEmailLogPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="regionEmailLogTable">
                        <thead>
                            <tr><th>#</th><th>Sent Date</th><th>Region/Unit</th><th>Recipient Email</th><th>Checks</th><th class="text-right">Total Amount</th><th>Sent By</th><th class="text-center">Status</th><th class="text-center" style="width:70px">Actions</th></tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="9">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="fims-tab-underline-content" id="regionDvPane" style="display:none">
                <div class="fims-table-wrap">
                    <table class="fims-table" id="regionDvTable">
                        <thead>
                            <tr>
                                <th>#</th><th>Check No.</th><th>DV Reference</th><th>Payee</th><th class="text-right">Amount</th>
                                <th>Region/Dept</th><th>Release Date</th><th>DV Return Date</th><th>Days Pending</th><th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="10">No records.</td></tr></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 5: CANCELLATION & REPLACEMENTS
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpCancel" style="display:none">
        <div class="fims-stat-grid" id="cancelKpiCards" style="grid-template-columns:repeat(4,1fr)">
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Total Cancelled</span></div><div class="fims-stat-value" data-kpi="total_cancelled">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">With Replacement</span></div><div class="fims-stat-value" data-kpi="with_replacement">0</div></div>
            <div class="fims-stat-card fims-card-amber"><div class="fims-stat-top"><span class="fims-stat-label">Pending Replacement</span></div><div class="fims-stat-value" data-kpi="pending_replacement">0</div></div>
            <div class="fims-stat-card fims-card-red"><div class="fims-stat-top"><span class="fims-stat-label">Cancelled Amount</span></div><div class="fims-stat-value" data-kpi="cancelled_amount">₱0.00</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="cancelFilterSearch" placeholder="Check no., payee…" style="width:186px">
                    </div>
                    <select class="fims-select fims-input-sm" id="cancelFilterBank" style="width:200px"><option value="">All Banks</option></select>
                    <input type="date" class="fims-input fims-input-sm" id="cancelFilterDateFrom">
                    <input type="date" class="fims-input fims-input-sm" id="cancelFilterDateTo">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="cancelSearchBtn"><i class="fas fa-search mr-1"></i>Search</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="cancelClearBtn" style="display:none">Clear</button>
                </div>
                <div id="cancelListMeta" style="font-size:11px;color:var(--fims-text-subtle)">No records.</div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Cancelled Checks</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="cancelReportsBtn"><i class="fas fa-file-alt mr-1"></i>Reports</button>
                    <button type="button" class="fims-btn fims-btn-danger fims-btn-sm" id="cancelCheckBtn"><i class="fas fa-ban mr-1"></i>Cancel Check</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="cancelTable">
                    <thead>
                        <tr>
                            <th>#</th><th>Check No.</th><th>Bank / Account</th><th>Payee</th><th class="text-right">Amount</th>
                            <th>Check Date</th><th>Cancelled Date</th><th>Reason</th><th class="text-center">Status</th><th class="text-center" style="width:70px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="10">No records.</td></tr></tbody>
                </table>
            </div>
            <div class="fims-pagination" id="cancelPagination"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 6: INVENTORY (RAF)
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpRaf" style="display:none">
        <div class="fims-stat-grid" id="rafKpiCards" style="grid-template-columns:repeat(4,1fr)">
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Check Booklets</span></div><div class="fims-stat-value" data-kpi="booklets">0</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Remaining Checks</span></div><div class="fims-stat-value" data-kpi="remaining">0</div></div>
            <div class="fims-stat-card fims-card-indigo"><div class="fims-stat-top"><span class="fims-stat-label">Checks Issued</span></div><div class="fims-stat-value" data-kpi="issued">0</div></div>
            <div class="fims-stat-card fims-card-red"><div class="fims-stat-top"><span class="fims-stat-label">Low Stock Accounts</span></div><div class="fims-stat-value" data-kpi="low_stock">0</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="rafFilterYear" style="width:140px"><option value="">Fiscal Year: All</option></select>
                    <select class="fims-select fims-input-sm" id="rafFilterBank" style="width:170px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="rafFilterLowStock" style="width:140px">
                        <option value="">All</option>
                        <option value="1">Low Stock Only</option>
                    </select>
                    <div class="fims-search-wrap">
                        <svg class="fims-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        <input type="text" class="fims-input fims-input-sm" id="rafFilterSearch" placeholder="Check Series / Booklet No." style="width:200px">
                    </div>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Inventory of Checks for Issuance</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="rafPreviewBtn"><i class="fas fa-eye mr-1"></i>Preview</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="rafPrintBtn"><i class="fas fa-print mr-1"></i>Print</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="rafDownloadBtn"><i class="fas fa-file-pdf mr-1"></i>Download PDF</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="rafTable">
                    <thead>
                        <tr>
                            <th>Booklet No.</th><th>Series</th><th>Fiscal Year</th><th>Check Range</th>
                            <th>Received</th><th>Issued</th><th>Cancelled</th><th>Remaining</th>
                            <th>Last Entry</th><th class="text-center">Stock Status</th><th class="text-center" style="width:70px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="11">No records — check register has no booklets yet.</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Prepare Check (tab 1 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-prepare-check" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:760px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Prepare Check</div></div>
                    <button class="fims-modal-close" data-close="m-prepare-check">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-alert fims-alert-info">
                        <div class="fims-alert-body">Check preparation requires the <code>treasury</code> schema (checks, cash_position) and the approved-DV pipeline (<code>accounting.vouchers</code>, <code>workflow.*</code>), none of which exist in this database yet. This form is a UI placeholder.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">DV Number</label><input type="text" class="fims-input" disabled placeholder="Select a DV to continue"></div>
                        <div class="col-md-6"><label class="fims-label">Payee</label><input type="text" class="fims-input" disabled></div>
                        <div class="col-md-6"><label class="fims-label">Bank Account <span class="text-danger">*</span></label><select class="fims-select" disabled><option>Select bank account...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Check Date <span class="text-danger">*</span></label><input type="date" class="fims-input" disabled></div>
                        <div class="col-md-6"><label class="fims-label">Check Number <span class="text-danger">*</span></label><select class="fims-select" disabled><option>Select bank account first...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Net Amount</label><input type="text" class="fims-input" disabled></div>
                        <div class="col-md-6"><label class="fims-label">Signatory 1 Name</label><input type="text" class="fims-input" disabled></div>
                        <div class="col-md-6"><label class="fims-label">Signatory 1 Designation</label><input type="text" class="fims-input" disabled></div>
                        <div class="col-md-6"><label class="fims-label">Signatory 2 Name</label><input type="text" class="fims-input" disabled></div>
                        <div class="col-md-6"><label class="fims-label">Signatory 2 Designation</label><input type="text" class="fims-input" disabled></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-prepare-check">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" disabled title="Schema not migrated yet">Save &amp; Print</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Add Check Batch (tab 2 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-add-batch" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:600px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Register Check Batch</div></div>
                    <button class="fims-modal-close" data-close="m-add-batch">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Bank Account <span class="text-danger">*</span></label><select class="fims-select" id="batchBankAccount"><option value="">Select bank account...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Batch Series <span class="text-danger">*</span></label><input type="text" class="fims-input" id="batchSeries"></div>
                        <div class="col-md-12"><label class="fims-label">Department Type <span class="text-danger">*</span></label>
                            <select class="fims-select" id="batchDeptType">
                                <option value="main">Main Office</option>
                                <option value="region">Region</option>
                                <option value="branch">Branch</option>
                                <option value="project">Project</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Start Check No.</label><input type="text" class="fims-input" id="batchStartCheck"></div>
                        <div class="col-md-6"><label class="fims-label">End Check No.</label><input type="text" class="fims-input" id="batchEndCheck"></div>
                        <div class="col-md-12"><label class="fims-label">Date Received</label><input type="date" class="fims-input" id="batchDateReceived"></div>
                    </div>
                    <div id="batchPreviewText" class="text-muted small mt-2"></div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-add-batch">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="batchSaveBtn">Save Batch</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Record Release (tab 3 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-record-release" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:720px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Record Check Release</div></div>
                    <button class="fims-modal-close" data-close="m-record-release">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Check Number <span class="text-danger">*</span></label><select class="fims-select" id="releaseCheckNumber"><option value="">Select check...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Amount</label><input type="text" class="fims-input" id="releaseAmount" readonly></div>
                        <div class="col-md-6"><label class="fims-label">Release Type <span class="text-danger">*</span></label>
                            <select class="fims-select" id="releaseType">
                                <option value="DIRECT">Direct</option>
                                <option value="EMAIL_NOTICE">Email Notice</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Claimant Type</label>
                            <select class="fims-select" id="releaseClaimantType">
                                <option value="PAYEE">Payee</option>
                                <option value="AUTHORIZED_REP">Authorized Representative</option>
                                <option value="ORIGINATING_UNIT">Originating Unit</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Claimant Name</label><input type="text" class="fims-input" id="releaseClaimantName"></div>
                        <div class="col-md-6"><label class="fims-label">Release Date</label><input type="date" class="fims-input" id="releaseDate"></div>
                        <div class="col-md-12"><label class="fims-label">Remarks</label><textarea class="fims-input" id="releaseRemarks" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-record-release">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="releaseSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: New Region Release (tab 4 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-region-release" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:720px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">New Release to Region/Department</div></div>
                    <button class="fims-modal-close" data-close="m-region-release">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Check Number <span class="text-danger">*</span></label><select class="fims-select" id="regionReleaseCheck"><option value="">Select check...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Region/Department <span class="text-danger">*</span></label><select class="fims-select" id="regionReleaseDept"><option value="">Select...</option></select></div>
                        <div class="col-md-6"><label class="fims-label">Release Date</label><input type="date" class="fims-input" id="regionReleaseDate"></div>
                        <div class="col-md-6"><label class="fims-label">Authorized Representative</label><input type="text" class="fims-input" id="regionReleaseClaimant"></div>
                        <div class="col-md-6"><label class="fims-label">RDTF No.</label><input type="text" class="fims-input" id="regionReleaseRdtf"></div>
                        <div class="col-md-12"><label class="fims-label">Remarks</label><textarea class="fims-input" id="regionReleaseRemarks" rows="2"></textarea></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-region-release">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="regionReleaseSaveBtn">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Cancel Check (tab 5 primary action)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-cancel-check" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:560px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Cancel Check</div></div>
                    <button class="fims-modal-close" data-close="m-cancel-check">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="mb-2"><label class="fims-label">Check <span class="text-danger">*</span></label><select class="fims-select" id="cancelCheckSelect"><option value="">Select a check to cancel...</option></select></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="fims-label">Reason <span class="text-danger">*</span></label>
                            <select class="fims-select" id="cancelReason">
                                <option value="">Select reason...</option>
                                <option value="LOST">Lost</option>
                                <option value="DAMAGED">Damaged</option>
                                <option value="ERROR">Error in Check</option>
                                <option value="PAYEE_REQUEST">Payee Request</option>
                                <option value="OTHER">Other</option>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="fims-label">Cancelled Date <span class="text-danger">*</span></label><input type="date" class="fims-input" id="cancelDate"></div>
                        <div class="col-md-12"><label class="fims-label">Reason Description</label><textarea class="fims-input" id="cancelDescription" rows="2"></textarea></div>
                        <div class="col-md-12">
                            <label style="display:flex;align-items:center;gap:6px;font-size:13px;cursor:pointer;">
                                <input type="checkbox" id="cancelIssueReplacement"> Issue a replacement check immediately
                            </label>
                        </div>
                    </div>
                    <div id="cancelReplacementBox" class="row g-2 mt-1" style="display:none;">
                        <div class="col-md-6"><label class="fims-label">New Check No.</label><input type="text" class="fims-input" id="cancelNewCheckNo"></div>
                        <div class="col-md-6"><label class="fims-label">Check Date</label><input type="date" class="fims-input" id="cancelNewCheckDate"></div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-cancel-check">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-danger fims-btn-sm" id="cancelProcessBtn">Process Cancellation</button>
                </div>
            </div>
        </div>
    </div>

    <div id="cpToast" style="display:none;position:fixed;bottom:20px;right:20px;z-index:2000;padding:10px 16px;border-radius:8px;color:#fff;font-size:13px;"></div>
</div>

<style>
/* Classes local to this page (not part of the shared fims-treasury.css
   design system) — tab buttons reuse the real .fims-tab-underline look, but
   need no-border/background reset since <button> isn't styled by default
   the way the source project's own buttons already were via browser UA
   reset + Bootstrap. The cp-status-tabs is a small quick-filter row unique
   to Check Preparation (status tabs), not present elsewhere in the design
   system. */
.fims-tab-underline { background: none; border: none; cursor: pointer; }
.cp-status-tabs { display: flex; gap: 6px; flex-wrap: wrap; margin: 10px 0; }
.cp-status-tab { cursor: pointer; opacity: .55; border: none; }
.cp-status-tab.active { opacity: 1; }

/* Dark-blue theme: this module's KPI cards reuse the shared .fims-card-*
   classes (which are red/green/amber/violet elsewhere in the app), but here
   they're just visual groupings, not meaningful status colors — so override
   their accent to the brand blue-shade family for consistency with the rest
   of this module (scoped to #cpModule only; other pages keep the rainbow). */
#cpModule .fims-card-blue,
#cpModule .fims-card-green,
#cpModule .fims-card-amber,
#cpModule .fims-card-red,
#cpModule .fims-card-gray,
#cpModule .fims-card-indigo,
#cpModule .fims-card-violet { --fims-card-color: #000E3E; }

/* Buttons: same single dark blue, scoped to this module only (the shared
   .fims-btn-primary/-outline classes stay the lighter brand blue everywhere
   else in the app). .fims-btn-danger is left red on purpose — it marks a
   destructive action (Cancel Check), and that warning color is functional,
   not decorative. */
#cpModule .fims-btn-primary { background:#000E3E; color:#fff; }
#cpModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#cpModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#cpModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }
</style>

<script>
(function () {
    const API = '/api/check-processing';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }

    function toast(msg, isError) {
        const el = document.getElementById('cpToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    function peso(n) {
        n = Number(n) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    // ── Modal open/close (real fims-modal-overlay pattern: display:flex/none) ──
    function openModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'flex';
    }
    function closeModal(id) {
        const m = document.getElementById(id);
        if (m) m.style.display = 'none';
    }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('.fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.style.display = 'none';
        });
    });

    // ── Top-level module tabs ──
    const initialized = {};
    const tabPaneIds = { prep: 'cpPrep', registry: 'cpRegistry', claimants: 'cpClaimants', regions: 'cpRegions', cancel: 'cpCancel', raf: 'cpRaf' };
    document.querySelectorAll('#cpTabNav [data-cp-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#cpTabNav .fims-tab-underline').forEach(b => b.classList.remove('active'));
            Object.values(tabPaneIds).forEach(id => { document.getElementById(id).style.display = 'none'; document.getElementById(id).classList.remove('active'); });
            this.classList.add('active');
            const tab = this.dataset.cpTab;
            const pane = document.getElementById(tabPaneIds[tab]);
            pane.style.display = '';
            pane.classList.add('active');
            if (!initialized[tab]) { initialized[tab] = true; initTab(tab); }
        });
    });

    // ── Sub-tabs (within Claimants / Regions tabs) ──
    document.querySelectorAll('[id$="SubTabs"].fims-tabs-underline').forEach(function (nav) {
        const panesContainer = nav.nextElementSibling; // the .fims-table-panel holding this nav's panes
        nav.querySelectorAll('[data-sub-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                nav.querySelectorAll('.fims-tab-underline').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                panesContainer.querySelectorAll('.fims-tab-underline-content').forEach(p => {
                    p.style.display = 'none';
                    p.classList.remove('active');
                });
                const target = document.getElementById(this.dataset.subTarget);
                if (target) { target.style.display = ''; target.classList.add('active'); }
            });
        });
    });

    function initTab(tab) {
        if (tab === 'prep') initPrep();
        else if (tab === 'registry') initRegistry();
        else if (tab === 'claimants') initClaimants();
        else if (tab === 'regions') initRegions();
        else if (tab === 'cancel') initCancel();
        else if (tab === 'raf') initRaf();
    }

    // Status tabs (Check Preparation)
    document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#prepStatusTabs .cp-status-tab').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            loadPrepChecks();
        });
    });
    document.getElementById('prepFilterBtn')?.addEventListener('click', loadPrepChecks);
    document.getElementById('prepResetBtn')?.addEventListener('click', function () {
        ['prepFilterCheckNo', 'prepFilterDvNo', 'prepFilterPayee', 'prepFilterBank', 'prepFilterDateFrom', 'prepFilterDateTo'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        loadPrepChecks();
    });
    document.getElementById('prepPrepareBtn')?.addEventListener('click', function () { openModal('m-prepare-check'); });

    function renderEmptyNotice(tbody, json, colspan) {
        const rows = (json && json.data) ? json.data : [];
        if (!rows.length) {
            tbody.innerHTML = '<tr class="fims-empty"><td colspan="' + colspan + '">No records.</td></tr>';
        } else {
            tbody.innerHTML = '';
        }
    }

    function loadPrepChecks() {
        const tbody = document.querySelector('#prepChecksTable tbody');
        fetch(API + '/preparation/checks?draw=1', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => renderEmptyNotice(tbody, json, 10))
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Could not reach the server.</td></tr>'; });
    }

    function fillSelect(selector, items, placeholder) {
        const sel = document.querySelector(selector);
        if (!sel) return;
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        (items || []).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id ?? item.bank_account_id ?? '';
            opt.textContent = item.name ?? item.account_name ?? '';
            sel.appendChild(opt);
        });
    }

    function initPrep() {
        loadPrepChecks();
        fetch(API + '/preparation/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#prepFilterBank', json.data, 'All Banks')).catch(() => {});
    }

    // ── Registry ──
    document.getElementById('regSearchBtn')?.addEventListener('click', loadRegistry);
    document.getElementById('regClearBtn')?.addEventListener('click', function () {
        ['regFilterOfficeType', 'regFilterBank', 'regFilterSeries', 'regFilterStatus', 'regFilterDateFrom', 'regFilterDateTo', 'regFilterSearch'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
        loadRegistry();
    });
    document.getElementById('regAddBatchBtn')?.addEventListener('click', function () { openModal('m-add-batch'); });
    document.getElementById('batchSaveBtn')?.addEventListener('click', function () {
        const payload = {
            bank_account_id: document.getElementById('batchBankAccount').value,
            check_series: document.getElementById('batchSeries').value,
            department_type: document.getElementById('batchDeptType').value,
            start_check: document.getElementById('batchStartCheck').value,
            end_check: document.getElementById('batchEndCheck').value,
            date_received: document.getElementById('batchDateReceived').value,
        };
        fetch(API + '/registry/add-batch', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(json => { toast(json.message || (json.success ? 'Batch saved.' : 'Could not save batch.'), !json.success); if (json.success) { closeModal('m-add-batch'); loadRegistry(); } });
    });

    function loadRegistry() {
        fetch(API + '/registry/summary', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#registryKpiCards [data-kpi]').forEach(el => { el.textContent = d[el.dataset.kpi] ?? 0; });
            }).catch(() => {});
        fetch(API + '/registry/list', { headers: authHeaders() }).then(r => r.json()).catch(() => {});
    }
    function initRegistry() { loadRegistry(); }

    // ── Claimants ──
    document.getElementById('claimRecordReleaseBtn')?.addEventListener('click', function () { openModal('m-record-release'); });
    document.getElementById('releaseSaveBtn')?.addEventListener('click', function () {
        const payload = {
            check_number: document.getElementById('releaseCheckNumber').value,
            release_type: document.getElementById('releaseType').value,
            claimant_type: document.getElementById('releaseClaimantType').value,
            claimant_name: document.getElementById('releaseClaimantName').value,
            release_date: document.getElementById('releaseDate').value,
            remarks: document.getElementById('releaseRemarks').value,
        };
        fetch(API + '/releases/save', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(json => { toast(json.message || (json.success ? 'Saved.' : 'Could not save.'), !json.success); if (json.success) { closeModal('m-record-release'); loadClaimants(); } });
    });

    function loadClaimants() {
        fetch(API + '/releases', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => renderEmptyNotice(document.querySelector('#claimantsTable tbody'), json, 10))
            .catch(() => {});
    }
    function initClaimants() { loadClaimants(); }

    // ── Regions ──
    document.getElementById('regionNewReleaseBtn')?.addEventListener('click', function () { openModal('m-region-release'); });
    document.getElementById('regionReleaseSaveBtn')?.addEventListener('click', function () {
        const payload = {
            check_number: document.getElementById('regionReleaseCheck').value,
            department_id: document.getElementById('regionReleaseDept').value,
            release_date: document.getElementById('regionReleaseDate').value,
            claimant_name: document.getElementById('regionReleaseClaimant').value,
            rdtf_no: document.getElementById('regionReleaseRdtf').value,
            remarks: document.getElementById('regionReleaseRemarks').value,
        };
        fetch(API + '/region-release/save', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(json => { toast(json.message || (json.success ? 'Saved.' : 'Could not save.'), !json.success); if (json.success) { closeModal('m-region-release'); initRegions(); } });
    });

    function initRegions() {
        fetch(API + '/region-release/list', { headers: authHeaders() }).then(r => r.json()).catch(() => {});
    }

    // ── Cancellation ──
    document.getElementById('cancelCheckBtn')?.addEventListener('click', function () { openModal('m-cancel-check'); });
    document.getElementById('cancelIssueReplacement')?.addEventListener('change', function () {
        document.getElementById('cancelReplacementBox').style.display = this.checked ? 'flex' : 'none';
    });
    document.getElementById('cancelProcessBtn')?.addEventListener('click', function () {
        const payload = {
            check_id: document.getElementById('cancelCheckSelect').value,
            reason: document.getElementById('cancelReason').value,
            cancelled_date: document.getElementById('cancelDate').value,
            description: document.getElementById('cancelDescription').value,
            issue_replacement: document.getElementById('cancelIssueReplacement').checked,
            new_check_no: document.getElementById('cancelNewCheckNo').value,
            new_check_date: document.getElementById('cancelNewCheckDate').value,
        };
        fetch(API + '/cancellation/cancel', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(json => { toast(json.message || (json.success ? 'Cancelled.' : 'Could not process.'), !json.success); if (json.success) { closeModal('m-cancel-check'); initCancel(); } });
    });

    function initCancel() {
        fetch(API + '/cancellation/kpis', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#cancelKpiCards [data-kpi]').forEach(el => {
                    const key = el.dataset.kpi;
                    el.textContent = key === 'cancelled_amount' ? peso(d[key]) : (d[key] ?? 0);
                });
            }).catch(() => {});
        fetch(API + '/cancellation/list', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => renderEmptyNotice(document.querySelector('#cancelTable tbody'), json, 10))
            .catch(() => {});
    }

    function initRaf() {
        // check-inventory-raf.php is server-rendered with no AJAX in the source
        // project (all data injected as JSON on load). With no treasury schema
        // to query yet, the table already shows its static "no records" row.
    }

    // First tab is active by default on page load.
    initTab('prep');
})();
</script>
