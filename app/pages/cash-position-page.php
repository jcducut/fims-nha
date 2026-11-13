<?php
/**
 * Cash Position — Treasury module.
 *
 * Consolidates 3 of the 4 TREASURY reference project cash-position pages
 * into one tabbed page:
 *   1. Dashboard                  — cashpositiondashboard.php
 *   2. Position Updates           — cashpositionupdates.php
 *   3. Available Cash Balances    — availablecashbalancestracking.php
 *
 * NOT ported: cashposreports.php (18 report types) — it depends on ~10
 * tables this project has no equivalent for (collections, fund_transfers/
 * FTLA, investments, garnished_accounts, expropriation_accounts,
 * interest_earnings_memo_*, etc.); porting it would mean designing a whole
 * separate subsystem nobody has asked for. See
 * app/services/CashPositionService.php's docblock for the full schema
 * adaptation notes (no shared.fund_sources — fund_source is plain text on
 * fims.bank_accounts; "Region/Office" is fims.responsibility_code, not
 * uac.departments; no transaction ledger to auto-sum receipts/
 * disbursements, so those are plain editable fields; no WEEKLY
 * aggregation).
 *
 * Markup/CSS classes match app/fims-css/fims.css, same dark-blue scoped
 * theme convention as check-disbursement-voucher-page.php (#cpModule) and
 * bank-management-page.php (#bamModule).
 */

namespace PHPMaker2024\FIMS;

$cpsCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $cpsCssVer ?>" />

<div class="fims-wrap" id="cashPositionModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-wallet"></i></div>
            <h1>Cash Position</h1>
            <p>Daily cash position, manual updates, and available-balance monitoring — Treasury Department</p>
        </div>
    </div>

    <div class="fims-tabs-underline mb-3" id="cpsTabNav">
        <button class="fims-tab-underline active" data-cps-tab="dashboard">Dashboard</button>
        <button class="fims-tab-underline" data-cps-tab="updates">Position Updates</button>
        <button class="fims-tab-underline" data-cps-tab="available">Available Cash Balances</button>
        <button class="fims-tab-underline" data-cps-tab="reports">Reports</button>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 1: DASHBOARD
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content active" id="cpsDashboard">
        <div class="fims-stat-grid" id="cpsDashKpis" style="grid-template-columns:repeat(6,1fr)">
            <div class="fims-stat-card fims-card-blue">
                <div class="fims-stat-top"><span class="fims-stat-label">Beginning Cash Balance</span></div>
                <div class="fims-stat-value" data-kpi="total_opening">₱0.00</div>
                <div class="fims-stat-sub">Beginning balance for period</div>
            </div>
            <div class="fims-stat-card fims-card-indigo">
                <div class="fims-stat-top"><span class="fims-stat-label">Net Cash Change</span></div>
                <div class="fims-stat-value" data-kpi="net_change">₱0.00</div>
                <div class="fims-stat-sub" data-kpi-sub="net_change">No net movement</div>
            </div>
            <div class="fims-stat-card fims-card-green">
                <div class="fims-stat-top"><span class="fims-stat-label">Ending Cash Balance</span></div>
                <div class="fims-stat-value" data-kpi="total_closing">₱0.00</div>
                <div class="fims-stat-sub">Ending balance for period</div>
            </div>
            <div class="fims-stat-card fims-card-amber">
                <div class="fims-stat-top"><span class="fims-stat-label">Restricted Funds</span></div>
                <div class="fims-stat-value" data-kpi="total_restricted">₱0.00</div>
                <div class="fims-stat-sub">Not freely available</div>
            </div>
            <div class="fims-stat-card fims-card-green">
                <div class="fims-stat-top"><span class="fims-stat-label">Total Available Cash</span></div>
                <div class="fims-stat-value" data-kpi="total_available">₱0.00</div>
                <div class="fims-stat-sub" data-kpi-sub="total_available">Excluding restricted</div>
            </div>
            <div class="fims-stat-card fims-card-gray">
                <div class="fims-stat-top"><span class="fims-stat-label">Active Accounts</span></div>
                <div class="fims-stat-value" data-kpi="active_accounts">0</div>
                <div class="fims-stat-sub">Bank accounts on record</div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <input type="date" class="fims-input fims-input-sm" id="dashFilterDate" title="Position Date (blank = each account's latest)">
                    <select class="fims-select fims-input-sm" id="dashFilterFundSource" style="width:170px"><option value="">All Fund Sources</option></select>
                    <select class="fims-select fims-input-sm" id="dashFilterRegion" style="width:200px"><option value="">All Regions/Offices</option></select>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="dashFilterBtn"><i class="fas fa-filter mr-1"></i>Apply</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="dashResetBtn">Reset</button>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title" id="dashTableTitle">Cash Balance by Fund Source &amp; Bank Account</div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <select class="fims-select fims-input-sm" id="dashGroupBy" style="width:150px">
                        <option value="fund_source">Group: Fund Source</option>
                        <option value="account_classification">Group: Classification</option>
                    </select>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="dashExportBtn"><i class="fas fa-file-csv mr-1"></i>Export CSV</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="dashPrintBtn"><i class="fas fa-print mr-1"></i>Print</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="dashTable">
                    <thead>
                        <tr>
                            <th>Account Name</th><th>Account No.</th><th>Bank / Branch</th>
                            <th class="text-right">Ending Balance</th><th class="text-right">Restricted</th>
                            <th class="text-right">Available</th><th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="7">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>

        <div class="fims-table-panel" style="margin-top:14px;">
            <div class="fims-panel-header" id="wpPanelHeader" style="cursor:pointer;">
                <div class="fims-panel-title"><i class="fas fa-caret-right mr-1" id="wpCaret"></i>Cash Position Summary (Working Paper)</div>
            </div>
            <div class="fims-table-wrap" id="wpPanelBody" style="display:none;">
                <table class="fims-table" id="wpTable">
                    <thead>
                        <tr>
                            <th>Bank Account</th><th>Fund Source</th>
                            <th class="text-right">Beginning</th><th class="text-right">+ Receipts</th><th class="text-right">− Disbursements</th>
                            <th class="text-right">Ending</th><th class="text-right">Bank Balance</th><th class="text-center">Recon.</th>
                            <th class="text-right">Restricted</th><th class="text-right">Available</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="10">Expand to load…</td></tr></tbody>
                    <tfoot id="wpTotalsRow"></tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 2: POSITION UPDATES
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpsUpdates" style="display:none">
        <div class="fims-stat-grid" id="updKpis" style="grid-template-columns:repeat(4,1fr)">
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Total Ending Balance</span></div><div class="fims-stat-value" data-kpi="total_ending">₱0.00</div></div>
            <div class="fims-stat-card fims-card-amber"><div class="fims-stat-top"><span class="fims-stat-label">Total Restricted Funds</span></div><div class="fims-stat-value" data-kpi="total_restricted">₱0.00</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Total Available Balance</span></div><div class="fims-stat-value" data-kpi="total_available">₱0.00</div></div>
            <div class="fims-stat-card fims-card-gray"><div class="fims-stat-top"><span class="fims-stat-label">Active Bank Accounts</span></div><div class="fims-stat-value" data-kpi="total_accounts">0</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="updFilterBank" style="width:190px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="updFilterFundSource" style="width:170px"><option value="">All Fund Sources</option></select>
                    <input type="date" class="fims-input fims-input-sm" id="updFilterDate" title="Position Date (blank = each account's latest)">
                    <input type="text" class="fims-input fims-input-sm" id="updFilterSearch" placeholder="Account name / number / bank" style="width:220px">
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="updFilterBtn"><i class="fas fa-filter mr-1"></i>Apply</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="updResetBtn">Reset</button>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Cash Position Records</div>
                <div style="display:flex;gap:8px;">
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="updUploadSoaBtn"><i class="fas fa-file-upload mr-1"></i>Upload SOA</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="updBatchUploadBtn"><i class="fas fa-layer-group mr-1"></i>Batch Upload</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="updAddBtn"><i class="fas fa-plus mr-1"></i>Manual Entry</button>
                </div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="updTable">
                    <thead>
                        <tr>
                            <th>Bank Account</th><th>Fund Source / Date</th>
                            <th class="text-right">Beginning</th><th class="text-right">+ Receipts</th><th class="text-right">− Disbursements</th>
                            <th class="text-right">Ending</th><th class="text-right">Restricted</th><th class="text-right">Available</th>
                            <th class="text-center">Status</th><th class="text-center" style="width:70px">Actions</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="10">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>

        <div style="display:flex;gap:14px;margin-top:14px;align-items:stretch;">
            <div class="fims-table-panel" style="flex:2 1 0;min-width:0;margin-top:0;display:flex;flex-direction:column;">
                <div class="fims-panel-header">
                    <div class="fims-panel-title">SOA vs Available Balance Comparison</div>
                </div>
                <div class="fims-table-wrap" style="flex:1;max-height:320px;overflow-y:auto;">
                    <table class="fims-table" id="soaCmpTable">
                        <thead>
                            <tr><th>Account</th><th class="text-right">Latest SOA Balance</th><th class="text-right">Available Balance</th>
                                <th class="text-right">Difference</th><th class="text-center">Status</th></tr>
                        </thead>
                        <tbody><tr class="fims-empty"><td colspan="5">Loading…</td></tr></tbody>
                    </table>
                </div>
            </div>

            <div class="fims-table-panel" style="flex:1 1 0;min-width:0;margin-top:0;display:flex;flex-direction:column;">
                <div class="fims-panel-header">
                    <div class="fims-panel-title">Recent Update Activity</div>
                </div>
                <ul id="updActivityList" style="list-style:none;padding:12px 16px;margin:0;font-size:13px;flex:1;max-height:320px;overflow-y:auto;"></ul>
            </div>
        </div>

        <div class="fims-table-panel" style="margin-top:14px;">
            <div class="fims-panel-header">
                <div class="fims-panel-title">SOA Transactions (by Upload)</div>
            </div>
            <div id="soaGroupsList" style="padding:0;"></div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 3: AVAILABLE CASH BALANCES TRACKING
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpsAvailable" style="display:none">
        <div class="fims-stat-grid" id="availKpis" style="grid-template-columns:repeat(4,1fr)">
            <div class="fims-stat-card fims-card-blue"><div class="fims-stat-top"><span class="fims-stat-label">Total Ending (Book) Balance</span></div><div class="fims-stat-value" data-kpi="book">₱0.00</div></div>
            <div class="fims-stat-card fims-card-green"><div class="fims-stat-top"><span class="fims-stat-label">Total Available Balance</span></div><div class="fims-stat-value" data-kpi="available">₱0.00</div></div>
            <div class="fims-stat-card fims-card-indigo"><div class="fims-stat-top"><span class="fims-stat-label">Total Usable Balance</span></div><div class="fims-stat-value" data-kpi="usable">₱0.00</div></div>
            <div class="fims-stat-card fims-card-red"><div class="fims-stat-top"><span class="fims-stat-label">Low Balance Alerts</span></div><div class="fims-stat-value" data-kpi="alerts">0</div></div>
        </div>

        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="availFilterFundSource" style="width:170px"><option value="">All Fund Sources</option></select>
                    <label style="font-size:12px;color:var(--fims-text-subtle);display:flex;align-items:center;gap:4px;">Low-balance threshold
                        <input type="number" class="fims-input fims-input-sm" id="availThreshold" value="500000" style="width:120px">
                    </label>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="availFilterBtn"><i class="fas fa-filter mr-1"></i>Apply</button>
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="availResetBtn">Reset</button>
                </div>
            </div>
        </div>

        <div id="availAlertsBanner" class="fims-alert fims-alert-danger" style="display:none;margin-bottom:10px;">
            <div class="fims-alert-body"></div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title">Book → Available → Usable, by Fund Source</div>
            </div>
            <div class="fims-table-wrap">
                <table class="fims-table" id="availTable">
                    <thead>
                        <tr>
                            <th>Account</th><th class="text-right">Ending (Book)</th><th class="text-right">Restricted</th>
                            <th class="text-right">Available</th><th class="text-right">Outstanding Checks</th>
                            <th class="text-right">Usable</th><th class="text-center">Status</th><th class="text-center">Position Date</th>
                        </tr>
                    </thead>
                    <tbody><tr class="fims-empty"><td colspan="8">Loading…</td></tr></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         TAB 4: REPORTS — ported from TREASURY's cashposreports.php.
         Report types needing Collections/Investments/FTLA/Garnished/
         Expropriation Accounts (subsystems this project doesn't have)
         are structurally-correct stubs — see
         CashPositionReportsService's docblock.
    ══════════════════════════════════════════════ -->
    <div class="fims-tab-underline-content" id="cpsReports" style="display:none">
        <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
            <div class="fims-toolbar">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <select class="fims-select fims-input-sm" id="repReportType" style="width:220px">
                        <optgroup label="Period Reports">
                            <option value="daily">Daily Cash Position</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annual">Annual</option>
                        </optgroup>
                        <optgroup label="By Classification">
                            <option value="by_fund_source">By Fund Source</option>
                            <option value="by_bank_account">By Bank Account</option>
                            <option value="by_classification">By Classification</option>
                        </optgroup>
                        <optgroup label="Special Reports">
                            <option value="working_paper">Working Paper</option>
                            <option value="individual_ledger">Individual Accounts Ledger</option>
                            <option value="cash_flow">Cash Flow Statement</option>
                            <option value="investment">Cash Position + Investments</option>
                            <option value="interest_earnings">Interest Earnings</option>
                            <option value="garnished">Garnished Accounts</option>
                            <option value="expropriation">Expropriation Accounts</option>
                            <option value="investment_schedule">Schedule of Investments</option>
                        </optgroup>
                    </select>
                    <input type="date" class="fims-input fims-input-sm" id="repDateFrom">
                    <input type="date" class="fims-input fims-input-sm" id="repDateTo">
                    <select class="fims-select fims-input-sm" id="repBankAccount" style="width:170px"><option value="">All Banks</option></select>
                    <select class="fims-select fims-input-sm" id="repFundSource" style="width:150px"><option value="">All Fund Sources</option></select>
                    <select class="fims-select fims-input-sm" id="repClassification" style="width:160px">
                        <option value="">All Classifications</option>
                        <option value="COLLECTION">Collection</option>
                        <option value="DISBURSEMENT">Disbursement</option>
                        <option value="COE_MOOE">COE / MOOE</option>
                        <option value="CAPITAL_OUTLAY">Capital Outlay</option>
                        <option value="EHAP">EHAP</option>
                        <option value="SPECIAL_PURPOSE">Special Purpose</option>
                        <option value="PROJECT">Project</option>
                        <option value="JOINT_VENTURE">Joint Venture</option>
                        <option value="TRUST">Trust</option>
                    </select>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="repRunBtn"><i class="fas fa-play mr-1"></i>Run Report</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="repFyBtn"><i class="fas fa-calendar-plus mr-1"></i>New FY Opening Balance</button>
                </div>
            </div>
        </div>

        <div class="fims-table-panel" style="border-radius:0 0 var(--fims-radius-lg) var(--fims-radius-lg)">
            <div class="fims-panel-header">
                <div class="fims-panel-title" id="repResultTitle">Report Results</div>
                <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="repExportBtn"><i class="fas fa-file-csv mr-1"></i>Export CSV</button>
            </div>
            <div id="repResultArea" style="padding:14px;">
                <div class="fims-empty" style="padding:30px;text-align:center;color:var(--fims-text-subtle);">Choose a report and click Run Report.</div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: New FY Opening Balance
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-fy-balance" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:520px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">New FY Opening Balance</div></div>
                    <button class="fims-modal-close" data-close="m-fy-balance">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div class="fims-form-group"><label class="fims-label">From Fiscal Year *</label><input type="number" class="fims-input" id="fyFromYear"></div>
                        <div class="fims-form-group"><label class="fims-label">New Fiscal Year *</label><input type="number" class="fims-input" id="fyNewYear"></div>
                    </div>
                    <div class="fims-form-section-title" style="margin-top:10px;">Bank Accounts</div>
                    <div id="fyAccountList" style="max-height:220px;overflow-y:auto;border:1px solid var(--fims-border);border-radius:6px;padding:8px;"></div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-fy-balance">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="fySaveBtn">Roll Forward</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Upload SOA — ported from TREASURY's single-account
         #modal-bulk-upload flow.
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-upload-soa" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:800px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Upload SOA (Bank Statement)</div></div>
                    <button class="fims-modal-close" data-close="m-upload-soa">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-alert fims-alert-info"><div class="fims-alert-body">Upload an .xlsx export of the bank statement. Columns are detected from the header row (Date, Description, Debit, Credit, Balance, Branch, Check No., Other Details — any order).</div></div>
                    <div class="fims-form-group" style="max-width:320px;">
                        <label class="fims-label">Bank Account *</label>
                        <select class="fims-select" id="soaBankAccount"><option value="">Select bank account...</option></select>
                    </div>
                    <div id="soaDropzone" style="border:2px dashed var(--fims-border);border-radius:8px;padding:24px;text-align:center;margin-top:10px;cursor:pointer;">
                        <i class="fas fa-file-excel" style="font-size:28px;color:var(--fims-text-subtle);"></i>
                        <p style="margin:8px 0 0;" id="soaDropzoneText">Click to choose an .xlsx file, or drag it here</p>
                        <input type="file" id="soaFileInput" accept=".xlsx" style="display:none;">
                    </div>
                    <div id="soaPreviewArea" style="margin-top:14px;display:none;">
                        <div class="fims-alert fims-alert-info" id="soaPreviewSummary"></div>
                        <div class="fims-table-wrap" style="max-height:280px;overflow-y:auto;">
                            <table class="fims-table">
                                <thead><tr><th>Date</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th><th>Check No.</th><th></th></tr></thead>
                                <tbody id="soaPreviewBody"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-upload-soa">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="soaParseBtn">Parse File</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="soaConfirmBtn" style="display:none;">Confirm &amp; Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Batch Upload SOA — ported from TREASURY's
         #modal-batch-upload flow: one row per bank account, each parsed
         and confirmed sequentially (not concurrently) through the same
         /upload-soa and /confirm-upload endpoints the single-account
         modal uses — there is no separate batch backend, by design (same
         as TREASURY, where this is purely a client-side orchestration
         layer over the per-account flow).
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-batch-upload-soa" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:920px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title">Batch Upload SOA</div></div>
                    <button class="fims-modal-close" data-close="m-batch-upload-soa">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div class="fims-alert fims-alert-info"><div class="fims-alert-body">Pre-select the bank accounts you're uploading statements for, or add blank rows and pick accounts one at a time. Rows are processed one at a time.</div></div>

                    <div class="fims-form-section-title">Pre-select Bank Accounts</div>
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;">
                        <input type="text" class="fims-input fims-input-sm" id="batchSoaAccountSearch" placeholder="Search accounts..." style="max-width:260px;">
                        <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="batchSoaAddSelectedBtn"><i class="fas fa-plus mr-1"></i>Add Selected</button>
                    </div>
                    <div id="batchSoaAccountPicker" style="max-height:140px;overflow-y:auto;border:1px solid var(--fims-border);border-radius:6px;padding:8px;margin-bottom:14px;"></div>

                    <div class="fims-form-section-title">Upload Rows</div>
                    <div class="fims-table-wrap">
                        <table class="fims-table">
                            <thead><tr><th style="width:220px">Bank Account</th><th>File</th><th style="width:220px">Status</th><th style="width:40px"></th></tr></thead>
                            <tbody id="batchSoaRows"></tbody>
                        </table>
                    </div>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="batchSoaAddRowBtn" style="margin-top:8px;"><i class="fas fa-plus mr-1"></i>Add Blank Row</button>
                    <div id="batchSoaSummary" class="fims-alert fims-alert-info" style="margin-top:12px;display:none;"></div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-batch-upload-soa">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="batchSoaParseAllBtn">Parse All</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="batchSoaConfirmAllBtn" disabled>Confirm &amp; Save All</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Manual Cash Position Entry
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-manual-entry" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:720px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="meModalTitle">Manual Cash Position Entry</div></div>
                    <button class="fims-modal-close" data-close="m-manual-entry">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="meCashPositionId">

                    <div class="cps-step-indicator" id="meStepIndicator">
                        <div class="cps-step-pill active" data-step-pill="1"><span class="cps-step-num">1</span>Account &amp; Period</div>
                        <div class="cps-step-line"></div>
                        <div class="cps-step-pill" data-step-pill="2"><span class="cps-step-num">2</span>Balances</div>
                        <div class="cps-step-line"></div>
                        <div class="cps-step-pill" data-step-pill="3"><span class="cps-step-num">3</span>Restricted Funds</div>
                        <div class="cps-step-line"></div>
                        <div class="cps-step-pill" data-step-pill="4"><span class="cps-step-num">4</span>Review</div>
                    </div>

                    <div class="cps-me-step" data-step="1">
                        <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="fims-form-group">
                                <label class="fims-label">Bank Account *</label>
                                <select class="fims-select" id="meBankAccount"><option value="">Select bank account...</option></select>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Position Date *</label>
                                <input type="date" class="fims-input" id="mePositionDate">
                            </div>
                            <div class="fims-form-group" style="grid-column:span 2;">
                                <label class="fims-label">Update Type *</label>
                                <select class="fims-select" id="meUpdateType">
                                    <option value="">— Select —</option>
                                    <option value="RECEIPT">Receipt</option>
                                    <option value="DISBURSEMENT">Disbursement</option>
                                    <option value="TRANSFER">Transfer</option>
                                    <option value="ADJUSTMENT">Adjustment</option>
                                    <option value="COLLECTION">Collection</option>
                                    <option value="SOA_UPLOAD">SOA Upload</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="cps-me-step" data-step="2" style="display:none;">
                        <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="fims-form-group">
                                <label class="fims-label">Beginning Balance *</label>
                                <input type="number" step="0.01" class="fims-input" id="meOpeningBalance">
                                <span style="font-size:11px;color:var(--fims-text-subtle)" id="meOpeningHint"></span>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Bank Statement Balance</label>
                                <input type="number" step="0.01" class="fims-input" id="meBankStatementBalance">
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Total Receipts</label>
                                <input type="number" step="0.01" class="fims-input" id="meTotalReceipts" value="0">
                                <span style="font-size:11px;color:var(--fims-text-subtle)" id="meReceiptsHint"></span>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Total Disbursements</label>
                                <input type="number" step="0.01" class="fims-input" id="meTotalDisbursements" value="0" readonly>
                                <span style="font-size:11px;color:var(--fims-text-subtle)">Auto-computed from posted bank transactions for this date.</span>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Adjustment Direction</label>
                                <select class="fims-select" id="meAdjustmentDirection">
                                    <option value="">— None —</option>
                                    <option value="ADDITION">Addition</option>
                                    <option value="DEDUCTION">Deduction</option>
                                </select>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Adjustment Amount</label>
                                <input type="number" step="0.01" class="fims-input" id="meAdjustmentAmount" value="0">
                            </div>
                            <div class="fims-form-group" style="grid-column:span 2;">
                                <label class="fims-label">Adjustment Details</label>
                                <input type="text" class="fims-input" id="meAdjustmentDetails">
                            </div>
                        </div>
                    </div>

                    <div class="cps-me-step" data-step="3" style="display:none;">
                        <div class="fims-form-section-title">Restricted Funds (itemized — leave empty to use a flat amount)</div>
                        <table class="fims-table" id="meRestrictedTable">
                            <thead><tr><th>Label</th><th style="width:150px">Amount</th><th style="width:40px"></th></tr></thead>
                            <tbody id="meRestrictedBody"></tbody>
                        </table>
                        <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="meAddRestrictedRowBtn"><i class="fas fa-plus mr-1"></i>Add Line</button>
                        <div class="fims-form-group" style="margin-top:8px;max-width:240px;">
                            <label class="fims-label">Flat Restricted Amount</label>
                            <input type="number" step="0.01" class="fims-input" id="meRestrictedAmount" value="0">
                        </div>
                    </div>

                    <div class="cps-me-step" data-step="4" style="display:none;">
                        <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="fims-form-group">
                                <label class="fims-label">Reference Number</label>
                                <input type="text" class="fims-input" id="meReferenceNumber">
                            </div>
                            <div class="fims-form-group">
                                <label style="display:flex;align-items:center;gap:6px;height:38px;margin-top:18px;">
                                    <input type="checkbox" id="meReconciled"> Mark as Reconciled with bank statement
                                </label>
                            </div>
                            <div class="fims-form-group" style="grid-column:span 2;">
                                <label class="fims-label">Remarks</label>
                                <textarea class="fims-input" id="meRemarks" rows="2"></textarea>
                            </div>
                        </div>

                        <div class="fims-alert fims-alert-info" style="margin-top:14px;">
                            <div class="fims-alert-body">
                                Ending Balance = Beginning + Receipts − Disbursements ± Adjustment: <strong id="meComputedClosing">₱0.00</strong><br>
                                Available Balance = Ending − Restricted: <strong id="meComputedAvailable">₱0.00</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-manual-entry">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="meBackBtn" style="display:none;">Back</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="meNextBtn">Next</button>
                    <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="meSaveBtn" style="display:none;">Save</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Account Detail (Dashboard)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-account-detail" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:520px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="adAccountName">Account Detail</div></div>
                    <button class="fims-modal-close" data-close="m-account-detail">&times;</button>
                </div>
                <div class="fims-modal-body" id="adBody"></div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-account-detail">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Balance Trend (Available Cash Balances)
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-balance-trend" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:640px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="trendFundSource">Balance Trend</div></div>
                    <button class="fims-modal-close" data-close="m-balance-trend">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px;">
                        <input type="date" class="fims-input fims-input-sm" id="trendDateFrom">
                        <input type="date" class="fims-input fims-input-sm" id="trendDateTo">
                        <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="trendApplyBtn">Apply</button>
                    </div>
                    <table class="fims-table">
                        <thead><tr><th>Date</th><th class="text-right">Book</th><th class="text-right">Available</th><th class="text-right">Restricted</th></tr></thead>
                        <tbody id="trendBody"><tr class="fims-empty"><td colspan="4">Loading…</td></tr></tbody>
                    </table>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-close="m-balance-trend">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="cpsToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>
</div>

<style>
#cashPositionModule .fims-card-blue,
#cashPositionModule .fims-card-green,
#cashPositionModule .fims-card-amber,
#cashPositionModule .fims-card-red,
#cashPositionModule .fims-card-gray,
#cashPositionModule .fims-card-indigo { --fims-card-color: #000E3E; }

#cashPositionModule .fims-btn-primary { background:#000E3E; color:#fff; }
#cashPositionModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#cashPositionModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#cashPositionModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

.fims-form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--fims-text-subtle); margin-bottom:8px; }
.cps-group-header { cursor:pointer; background:var(--fims-table-head-bg, #f4f6fb); }

/* Manual Cash Position Entry step indicator */
.cps-step-indicator { display:flex; align-items:center; margin-bottom:18px; }
.cps-step-pill { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--fims-text-subtle); white-space:nowrap; }
.cps-step-pill .cps-step-num { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:var(--fims-border); color:var(--fims-text-subtle); font-size:12px; }
.cps-step-pill.active { color:#000E3E; }
.cps-step-pill.active .cps-step-num { background:#000E3E; color:#fff; }
.cps-step-pill.done .cps-step-num { background:#000E3E; color:#fff; }
.cps-step-line { flex:1; height:2px; background:var(--fims-border); margin:0 10px; }
</style>

<script>
(function () {
    const API = '/api/cash-position';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function toast(msg, isError) {
        const el = document.getElementById('cpsToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }
    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
    function peso(n) {
        n = Number(n) || 0;
        return '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function openModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
    function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }
    document.querySelectorAll('[data-close]').forEach(function (btn) {
        btn.addEventListener('click', function () { closeModal(this.dataset.close); });
    });
    document.querySelectorAll('#cashPositionModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) { if (e.target === overlay) overlay.style.display = 'none'; });
    });
    function fillSelect(selector, items, placeholder, valueKey, labelFn) {
        const sel = document.querySelector(selector);
        if (!sel) return;
        const current = sel.value;
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        (items || []).forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item[valueKey];
            opt.textContent = labelFn(item);
            sel.appendChild(opt);
        });
        sel.value = current;
    }

    // ── Top-level module tabs ──
    const initialized = {};
    const tabPaneIds = { dashboard: 'cpsDashboard', updates: 'cpsUpdates', available: 'cpsAvailable', reports: 'cpsReports' };
    document.querySelectorAll('#cpsTabNav [data-cps-tab]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.querySelectorAll('#cpsTabNav .fims-tab-underline').forEach(b => b.classList.remove('active'));
            Object.values(tabPaneIds).forEach(id => { document.getElementById(id).style.display = 'none'; document.getElementById(id).classList.remove('active'); });
            this.classList.add('active');
            const tab = this.dataset.cpsTab;
            const pane = document.getElementById(tabPaneIds[tab]);
            pane.style.display = '';
            pane.classList.add('active');
            if (!initialized[tab]) { initialized[tab] = true; initTab(tab); }
        });
    });
    function initTab(tab) {
        if (tab === 'dashboard') initDashboard();
        else if (tab === 'updates') initUpdates();
        else if (tab === 'available') initAvailable();
        else if (tab === 'reports') initReports();
    }

    // Groups flat rows by fund_source — same client-side grouping pattern
    // used for Check Registry's booklet grouping.
    function groupByFundSource(rows, key) {
        key = key || 'fund_source';
        const groups = [];
        const index = {};
        rows.forEach(function (r) {
            const k = r[key] || '';
            if (!index[k]) {
                index[k] = { fund_source: r[key] || 'Unassigned', rows: [] };
                groups.push(index[k]);
            }
            index[k].rows.push(r);
        });
        return groups;
    }
    // Exports whatever is currently rendered in a <table> to a CSV file —
    // same client-side-from-DOM technique TREASURY itself uses
    // (exportTableCSV() in cashpositiondashboard.php/cashposreports.php).
    function exportTableCSV(tableId, filename) {
        const table = document.getElementById(tableId);
        if (!table) return;
        const rows = Array.from(table.querySelectorAll('tr')).map(function (tr) {
            return Array.from(tr.querySelectorAll('th,td')).map(function (cell) {
                return '"' + cell.innerText.replace(/"/g, '""').replace(/₱\s*/g, '') + '"';
            }).join(',');
        });
        const blob = new Blob([rows.join('\n')], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = filename + '_' + new Date().toISOString().slice(0, 10) + '.csv';
        a.click();
        URL.revokeObjectURL(a.href);
    }
    function statusBadgeFor(status) {
        if (status === 'ACTIVE') return '<span class="fims-badge fims-badge-green">Active</span>';
        if (status === 'DORMANT') return '<span class="fims-badge fims-badge-amber">Dormant</span>';
        if (status === 'CLOSED') return '<span class="fims-badge fims-badge-gray">Closed</span>';
        return '<span class="fims-badge fims-badge-gray">' + escapeHtml(status || '—') + '</span>';
    }

    // ══════════════════════ DASHBOARD ══════════════════════
    function loadDashboardFilters() {
        fetch(API + '/lookup/fund-sources', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#dashFilterFundSource', (json.data || []).map(f => ({ v: f })), 'All Fund Sources', 'v', i => i.v)).catch(() => {});
        fetch(API + '/lookup/regions', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#dashFilterRegion', json.data, 'All Regions/Offices', 'rc_id', i => i.rc_code + ' — ' + i.rc_name)).catch(() => {});
    }
    function buildDashParams() {
        const params = new URLSearchParams();
        const date = document.getElementById('dashFilterDate').value;
        const fs = document.getElementById('dashFilterFundSource').value;
        const rc = document.getElementById('dashFilterRegion').value;
        if (date) params.set('position_date', date);
        if (fs) params.set('fund_source', fs);
        if (rc) params.set('rc_id', rc);
        return params;
    }
    let dashRowsById = {};
    function renderDashboardTable(rows) {
        const tbody = document.querySelector('#dashTable tbody');
        if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">No records.</td></tr>'; return; }
        const groupKey = document.getElementById('dashGroupBy').value;
        document.getElementById('dashTableTitle').textContent = groupKey === 'account_classification'
            ? 'Cash Balance by Classification & Bank Account' : 'Cash Balance by Fund Source & Bank Account';
        const groups = groupByFundSource(rows, groupKey);
        tbody.innerHTML = groups.map(function (g, gi) {
            const groupClass = 'dash-group-' + gi;
            const totalEnding = g.rows.reduce((s, r) => s + Number(r.closing_balance || 0), 0);
            const totalRestricted = g.rows.reduce((s, r) => s + Number(r.restricted_amount || 0), 0);
            const totalAvailable = g.rows.reduce((s, r) => s + Number(r.available_balance || 0), 0);
            const header = '<tr class="cps-group-header" data-group-toggle="' + groupClass + '">' +
                '<td colspan="3"><i class="fas fa-caret-right mr-1"></i><strong>' + escapeHtml(g.fund_source) + '</strong> <span class="fims-badge">' + g.rows.length + ' account(s)</span></td>' +
                '<td class="text-right"><strong>' + peso(totalEnding) + '</strong></td>' +
                '<td class="text-right"><strong>' + peso(totalRestricted) + '</strong></td>' +
                '<td class="text-right"><strong>' + peso(totalAvailable) + '</strong></td>' +
                '<td></td></tr>';
            const leaves = g.rows.map(function (r) {
                return '<tr class="' + groupClass + '" style="display:none;">' +
                    '<td style="padding-left:24px;"><a href="#" data-account-detail="' + r.bank_account_id + '">' + escapeHtml(r.account_name) + '</a>' + (r.is_main_depository ? ' <span class="fims-badge fims-badge-blue">Main</span>' : '') + '</td>' +
                    '<td class="fims-col-mono">' + escapeHtml(r.account_number || '') + '</td>' +
                    '<td>' + escapeHtml(r.bank_name || '') + (r.bank_branch ? '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_branch) + '</span>' : '') + '</td>' +
                    '<td class="text-right">' + peso(r.closing_balance) + '</td>' +
                    '<td class="text-right">' + peso(r.restricted_amount) + '</td>' +
                    '<td class="text-right" style="color:#16a34a;font-weight:600;">' + peso(r.available_balance) + '</td>' +
                    '<td class="text-center">' + statusBadgeFor(r.status) + '</td>' +
                    '</tr>';
            }).join('');
            return header + leaves;
        }).join('');
        tbody.querySelectorAll('.cps-group-header').forEach(function (header) {
            header.addEventListener('click', function () {
                const cls = this.dataset.groupToggle;
                const caret = this.querySelector('.fa-caret-right, .fa-caret-down');
                const leaves = tbody.querySelectorAll('tr.' + cls);
                const willShow = leaves.length && leaves[0].style.display === 'none';
                leaves.forEach(function (tr) { tr.style.display = willShow ? '' : 'none'; });
                if (caret) caret.className = 'fas ' + (willShow ? 'fa-caret-down' : 'fa-caret-right') + ' mr-1';
            });
        });
        tbody.querySelectorAll('[data-account-detail]').forEach(function (link) {
            link.addEventListener('click', function (e) {
                e.preventDefault();
                openAccountDetail(this.dataset.accountDetail);
            });
        });
    }
    function openAccountDetail(bankAccountId) {
        const r = dashRowsById[bankAccountId];
        if (!r) return;
        document.getElementById('adAccountName').textContent = r.account_name;
        document.getElementById('adBody').innerHTML =
            '<p><strong>' + escapeHtml(r.account_number || '') + '</strong> · ' + escapeHtml(r.bank_name || '') + (r.bank_branch ? ' (' + escapeHtml(r.bank_branch) + ')' : '') + '<br>' +
            'Classification: ' + escapeHtml(r.account_classification || '—') + ' · Fund Source: ' + escapeHtml(r.fund_source || '—') + (r.is_main_depository ? ' · <span class="fims-badge fims-badge-blue">Main Depository</span>' : '') + '</p>' +
            '<table class="fims-table"><tbody>' +
            '<tr><td>Beginning Balance</td><td class="text-right">' + peso(r.opening_balance) + '</td></tr>' +
            '<tr><td>+ Total Receipts</td><td class="text-right">' + peso(r.total_receipts) + '</td></tr>' +
            '<tr><td>− Total Disbursements</td><td class="text-right">' + peso(r.total_disbursements) + '</td></tr>' +
            '<tr><td><strong>Ending Balance</strong></td><td class="text-right"><strong>' + peso(r.closing_balance) + '</strong></td></tr>' +
            (r.bank_statement_balance != null ? '<tr><td>Bank Balance</td><td class="text-right">' + peso(r.bank_statement_balance) + '</td></tr>' : '') +
            '<tr><td>Restricted Amount</td><td class="text-right">' + peso(r.restricted_amount) + '</td></tr>' +
            '<tr><td style="color:#16a34a;"><strong>Available Balance</strong></td><td class="text-right" style="color:#16a34a;"><strong>' + peso(r.available_balance) + '</strong></td></tr>' +
            '</tbody></table>' +
            '<p style="font-size:11px;color:var(--fims-text-subtle);">As of ' + escapeHtml(r.position_date || '—') + '</p>';
        openModal('m-account-detail');
    }
    function loadDashboard() {
        fetch(API + '/dashboard/kpis?' + buildDashParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = json.data || {};
                document.querySelectorAll('#cpsDashKpis [data-kpi]').forEach(function (el) {
                    const key = el.dataset.kpi;
                    el.textContent = key === 'active_accounts' ? (d[key] ?? 0) : peso(d[key] ?? 0);
                });
                const netEl = document.querySelector('[data-kpi="net_change"]');
                const netSub = document.querySelector('[data-kpi-sub="net_change"]');
                const net = d.net_change ?? 0;
                netEl.style.color = net > 0 ? '#16a34a' : (net < 0 ? '#dc2626' : '');
                netSub.textContent = net > 0 ? 'Cash inflows exceed outflows' : (net < 0 ? 'Cash outflows exceed inflows' : 'No net movement');
                document.querySelector('[data-kpi-sub="total_available"]').textContent = 'Excluding ' + peso(d.total_restricted ?? 0) + ' restricted';
            }).catch(() => {});

        const tbody = document.querySelector('#dashTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Loading…</td></tr>';
        fetch(API + '/dashboard/list?' + buildDashParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                dashRowsById = {};
                rows.forEach(function (r) { dashRowsById[r.bank_account_id] = r; });
                renderDashboardTable(rows);
                renderWorkingPaper(rows);
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="7">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('dashFilterBtn')?.addEventListener('click', loadDashboard);
    document.getElementById('dashResetBtn')?.addEventListener('click', function () {
        document.getElementById('dashFilterDate').value = '';
        document.getElementById('dashFilterFundSource').value = '';
        document.getElementById('dashFilterRegion').value = '';
        loadDashboard();
    });
    document.getElementById('dashGroupBy')?.addEventListener('change', function () { renderDashboardTable(Object.values(dashRowsById)); });
    document.getElementById('dashExportBtn')?.addEventListener('click', function () { exportTableCSV('dashTable', 'cash-balance-by-fund-source'); });
    document.getElementById('dashPrintBtn')?.addEventListener('click', function () { window.print(); });

    // ── Working Paper (collapsed by default, same underlying rows as Table 1) ──
    document.getElementById('wpPanelHeader')?.addEventListener('click', function () {
        const body = document.getElementById('wpPanelBody');
        const caret = document.getElementById('wpCaret');
        const show = body.style.display === 'none';
        body.style.display = show ? '' : 'none';
        caret.className = 'fas ' + (show ? 'fa-caret-down' : 'fa-caret-right') + ' mr-1';
    });
    function renderWorkingPaper(rows) {
        const tbody = document.querySelector('#wpTable tbody');
        if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">No records.</td></tr>'; document.getElementById('wpTotalsRow').innerHTML = ''; return; }
        tbody.innerHTML = rows.map(function (r) {
            const recon = r.reconciled ? '<i class="fas fa-check" style="color:#16a34a"></i>' : '<i class="fas fa-times" style="color:#dc2626"></i>';
            return '<tr>' +
                '<td>' + escapeHtml(r.account_name) + '</td>' +
                '<td>' + escapeHtml(r.fund_source || 'General') + '</td>' +
                '<td class="text-right">' + peso(r.opening_balance) + '</td>' +
                '<td class="text-right">' + peso(r.total_receipts) + '</td>' +
                '<td class="text-right">' + peso(r.total_disbursements) + '</td>' +
                '<td class="text-right"><strong>' + peso(r.closing_balance) + '</strong></td>' +
                '<td class="text-right">' + (r.bank_statement_balance != null ? peso(r.bank_statement_balance) : '—') + '</td>' +
                '<td class="text-center">' + recon + '</td>' +
                '<td class="text-right">' + peso(r.restricted_amount) + '</td>' +
                '<td class="text-right" style="color:#16a34a;">' + peso(r.available_balance) + '</td>' +
                '</tr>';
        }).join('');
        const totals = rows.reduce(function (acc, r) {
            acc.opening += Number(r.opening_balance || 0); acc.receipts += Number(r.total_receipts || 0);
            acc.disbursements += Number(r.total_disbursements || 0); acc.closing += Number(r.closing_balance || 0);
            acc.restricted += Number(r.restricted_amount || 0); acc.available += Number(r.available_balance || 0);
            return acc;
        }, { opening: 0, receipts: 0, disbursements: 0, closing: 0, restricted: 0, available: 0 });
        document.getElementById('wpTotalsRow').innerHTML = '<tr><td colspan="2"><strong>TOTAL</strong></td>' +
            '<td class="text-right"><strong>' + peso(totals.opening) + '</strong></td>' +
            '<td class="text-right"><strong>' + peso(totals.receipts) + '</strong></td>' +
            '<td class="text-right"><strong>' + peso(totals.disbursements) + '</strong></td>' +
            '<td class="text-right"><strong>' + peso(totals.closing) + '</strong></td>' +
            '<td></td><td></td>' +
            '<td class="text-right"><strong>' + peso(totals.restricted) + '</strong></td>' +
            '<td class="text-right"><strong>' + peso(totals.available) + '</strong></td></tr>';
    }
    function initDashboard() { loadDashboardFilters(); loadDashboard(); }

    // ══════════════════════ POSITION UPDATES ══════════════════════
    let bankAccountsById = {};
    function loadUpdatesFilters() {
        fetch(API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                (json.data || []).forEach(function (b) { bankAccountsById[b.bank_account_id] = b; });
                fillSelect('#updFilterBank', json.data, 'All Banks', 'bank_account_id', i => i.account_name);
                fillSelect('#meBankAccount', json.data, 'Select bank account...', 'bank_account_id', i => i.account_name + ' — ' + (i.bank_name || ''));
            }).catch(() => {});
        fetch(API + '/lookup/fund-sources', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#updFilterFundSource', (json.data || []).map(f => ({ v: f })), 'All Fund Sources', 'v', i => i.v)).catch(() => {});
    }
    function buildUpdParams() {
        const params = new URLSearchParams();
        params.set('draw', '1');
        const bank = document.getElementById('updFilterBank').value;
        const fs = document.getElementById('updFilterFundSource').value;
        const date = document.getElementById('updFilterDate').value;
        const search = document.getElementById('updFilterSearch').value.trim();
        if (bank) params.set('bank_account_id', bank);
        if (fs) params.set('fund_source', fs);
        if (date) params.set('position_date', date);
        if (search) params.set('search', search);
        return params;
    }
    function loadUpdatesKpis() {
        fetch(API + '/updates/kpis', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => {
                const d = json.data || {};
                document.querySelectorAll('#updKpis [data-kpi]').forEach(function (el) {
                    const key = el.dataset.kpi;
                    el.textContent = key === 'total_accounts' ? (d[key] ?? 0) : peso(d[key] ?? 0);
                });
            }).catch(() => {});
    }
    function loadUpdatesList() {
        const tbody = document.querySelector('#updTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Loading…</td></tr>';
        fetch(API + '/updates/list?' + buildUpdParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">No records.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    const statusBadge = r.reconciled
                        ? '<span class="fims-badge fims-badge-green">Reconciled</span>'
                        : '<span class="fims-badge fims-badge-amber">Pending</span>';
                    return '<tr>' +
                        '<td>' + escapeHtml(r.account_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_name || '') + ' · ' + escapeHtml(r.account_number || '') + '</span></td>' +
                        '<td>' + escapeHtml(r.fund_source || 'General') + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.position_date || '') + '</span></td>' +
                        '<td class="text-right">' + peso(r.opening_balance) + '</td>' +
                        '<td class="text-right">' + peso(r.total_receipts) + '</td>' +
                        '<td class="text-right">' + peso(r.total_disbursements) + '</td>' +
                        '<td class="text-right"><strong>' + peso(r.closing_balance) + '</strong></td>' +
                        '<td class="text-right">' + peso(r.restricted_amount) + '</td>' +
                        '<td class="text-right" style="color:#16a34a;font-weight:600;">' + peso(r.available_balance) + '</td>' +
                        '<td class="text-center">' + statusBadge + '</td>' +
                        '<td class="text-center"><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-edit-entry="' + r.bank_account_id + '" data-position-date="' + (r.position_date || '') + '" data-entry-id="' + (r.cash_position_id || '') + '" title="Edit"><i class="fas fa-pen"></i></button></td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-edit-entry]').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        openManualEntryModal(this.dataset.editEntry, this.dataset.positionDate, this.dataset.entryId || null);
                    });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="10">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('updFilterBtn')?.addEventListener('click', loadUpdatesList);
    document.getElementById('updResetBtn')?.addEventListener('click', function () {
        document.getElementById('updFilterBank').value = '';
        document.getElementById('updFilterFundSource').value = '';
        document.getElementById('updFilterDate').value = '';
        document.getElementById('updFilterSearch').value = '';
        loadUpdatesList();
    });
    document.getElementById('updAddBtn')?.addEventListener('click', function () { openManualEntryModal(null, null, null); });
    function loadActivityLog() {
        const list = document.getElementById('updActivityList');
        fetch(API + '/updates/activity-log?limit=10', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { list.innerHTML = '<li style="color:var(--fims-text-subtle)">No recent activity.</li>'; return; }
                list.innerHTML = rows.map(function (a) {
                    const badge = a.action === 'CREATE' ? 'fims-badge-green' : 'fims-badge-blue';
                    return '<li style="padding:6px 0;border-bottom:1px solid var(--fims-border);">' +
                        '<span class="fims-badge ' + badge + '">' + escapeHtml(a.action) + '</span> ' +
                        '<strong>' + escapeHtml(a.account_name) + '</strong> (' + escapeHtml(a.bank_name || '') + ') — ' + escapeHtml(a.position_date) + '<br>' +
                        '<span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(a.details || '') + ' · ' + escapeHtml(a.performed_at) + '</span>' +
                        '</li>';
                }).join('');
            })
            .catch(() => { list.innerHTML = '<li>Could not reach the server.</li>'; });
    }
    // ── SOA Comparison ──
    function loadSoaComparison() {
        const tbody = document.querySelector('#soaCmpTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">Loading…</td></tr>';
        fetch(API + '/soa-comparison', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">No SOA data uploaded yet.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    const hasSoa = r.latest_soa_balance != null;
                    const hasPos = r.available_balance != null;
                    const matched = hasSoa && hasPos && Math.abs(Number(r.latest_soa_balance) - Number(r.available_balance)) < 0.01;
                    const statusBadge = !hasSoa ? '<span class="fims-badge fims-badge-gray">No SOA</span>'
                        : !hasPos ? '<span class="fims-badge fims-badge-gray">No Position</span>'
                        : matched ? '<span class="fims-badge fims-badge-green">Matched</span>'
                        : '<span class="fims-badge fims-badge-red">Mismatch</span>';
                    return '<tr>' +
                        '<td>' + escapeHtml(r.account_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_name || '') + '</span></td>' +
                        '<td class="text-right">' + (hasSoa ? peso(r.latest_soa_balance) : '—') + '</td>' +
                        '<td class="text-right">' + (hasPos ? peso(r.available_balance) : '—') + '</td>' +
                        '<td class="text-right">' + (hasSoa && hasPos ? peso(r.difference) : '—') + '</td>' +
                        '<td class="text-center">' + statusBadge + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">Could not reach the server.</td></tr>'; });
    }

    // ── SOA Transactions grouped-by-upload accordion ──
    function loadSoaGroups() {
        const list = document.getElementById('soaGroupsList');
        list.innerHTML = '<div class="fims-empty" style="padding:16px;">Loading…</div>';
        fetch(API + '/soa-groups?limit=10', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const groups = (json && json.data) ? json.data : [];
                if (!groups.length) { list.innerHTML = '<div class="fims-empty" style="padding:16px;">No SOA uploads yet.</div>'; return; }
                list.innerHTML = groups.map(function (g, gi) {
                    return '<div style="border-bottom:1px solid var(--fims-border);">' +
                        '<div class="cps-group-header" data-soa-toggle="soa-grp-' + gi + '" data-batch="' + escapeHtml(g.upload_batch_id) + '" data-bank="' + g.bank_account_id + '" data-loaded="0" style="padding:10px 14px;cursor:pointer;display:flex;justify-content:space-between;align-items:center;">' +
                        '<div><i class="fas fa-caret-right mr-1"></i><strong>' + escapeHtml(g.account_name) + '</strong> — ' + escapeHtml(g.bank_name || '') + ' <span class="fims-badge">' + g.transaction_count + ' line(s)</span></div>' +
                        '<div style="font-size:12px;color:var(--fims-text-subtle);">Uploaded ' + escapeHtml(g.upload_datetime || '') + ' · Balance: <strong>' + peso(g.latest_balance) + '</strong></div>' +
                        '</div>' +
                        '<div id="soa-grp-' + gi + '" style="display:none;padding:0 14px 10px;"></div>' +
                        '</div>';
                }).join('');
                list.querySelectorAll('[data-soa-toggle]').forEach(function (header) {
                    header.addEventListener('click', function () {
                        const targetId = this.dataset.soaToggle;
                        const target = document.getElementById(targetId);
                        const willShow = target.style.display === 'none';
                        target.style.display = willShow ? '' : 'none';
                        const caret = this.querySelector('.fa-caret-right, .fa-caret-down');
                        if (caret) caret.className = 'fas ' + (willShow ? 'fa-caret-down' : 'fa-caret-right') + ' mr-1';
                        if (willShow && this.dataset.loaded === '0') {
                            this.dataset.loaded = '1';
                            loadSoaGroupRows(target, this.dataset.bank, this.dataset.batch);
                        }
                    });
                });
            })
            .catch(() => { list.innerHTML = '<div class="fims-empty" style="padding:16px;">Could not reach the server.</div>'; });
    }
    function loadSoaGroupRows(container, bankAccountId, batchId) {
        container.innerHTML = '<div class="fims-empty" style="padding:10px;">Loading…</div>';
        const params = new URLSearchParams({ bank_account_id: bankAccountId, upload_batch_id: batchId, limit: 500 });
        fetch(API + '/soa-transactions?' + params.toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) { container.innerHTML = '<div class="fims-empty" style="padding:10px;">No lines.</div>'; return; }
                container.innerHTML = '<table class="fims-table"><thead><tr><th>Date</th><th>Description</th><th class="text-right">Debit</th><th class="text-right">Credit</th><th class="text-right">Balance</th><th>Check No.</th></tr></thead><tbody>' +
                    rows.map(function (r) {
                        return '<tr><td>' + escapeHtml(r.transaction_date) + '</td><td>' + escapeHtml(r.description || '') + '</td>' +
                            '<td class="text-right">' + (Number(r.debit_amount) ? peso(r.debit_amount) : '—') + '</td>' +
                            '<td class="text-right">' + (Number(r.credit_amount) ? peso(r.credit_amount) : '—') + '</td>' +
                            '<td class="text-right">' + (r.balance != null ? peso(r.balance) : '—') + '</td>' +
                            '<td>' + escapeHtml(r.check_no || '') + '</td></tr>';
                    }).join('') + '</tbody></table>';
            })
            .catch(() => { container.innerHTML = '<div class="fims-empty" style="padding:10px;">Could not reach the server.</div>'; });
    }

    // ── Upload SOA modal ──
    let soaParsedRows = [];
    document.getElementById('updUploadSoaBtn')?.addEventListener('click', function () {
        fetch(API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#soaBankAccount', json.data, 'Select bank account...', 'bank_account_id', i => i.account_name)).catch(() => {});
        document.getElementById('soaFileInput').value = '';
        document.getElementById('soaDropzoneText').textContent = 'Click to choose an .xlsx file, or drag it here';
        document.getElementById('soaPreviewArea').style.display = 'none';
        document.getElementById('soaConfirmBtn').style.display = 'none';
        soaParsedRows = [];
        openModal('m-upload-soa');
    });
    const soaDropzone = document.getElementById('soaDropzone');
    const soaFileInput = document.getElementById('soaFileInput');
    soaDropzone?.addEventListener('click', function () { soaFileInput.click(); });
    soaDropzone?.addEventListener('dragover', function (e) { e.preventDefault(); soaDropzone.style.borderColor = '#000E3E'; });
    soaDropzone?.addEventListener('dragleave', function () { soaDropzone.style.borderColor = ''; });
    soaDropzone?.addEventListener('drop', function (e) {
        e.preventDefault();
        soaDropzone.style.borderColor = '';
        if (e.dataTransfer.files.length) { soaFileInput.files = e.dataTransfer.files; updateSoaFileName(); }
    });
    soaFileInput?.addEventListener('change', updateSoaFileName);
    function updateSoaFileName() {
        document.getElementById('soaDropzoneText').textContent = soaFileInput.files.length ? soaFileInput.files[0].name : 'Click to choose an .xlsx file, or drag it here';
    }

    document.getElementById('soaParseBtn')?.addEventListener('click', function () {
        const bankAccountId = document.getElementById('soaBankAccount').value;
        if (!bankAccountId) { toast('Select a bank account.', true); return; }
        if (!soaFileInput.files.length) { toast('Choose a file first.', true); return; }
        const formData = new FormData();
        formData.append('bank_account_id', bankAccountId);
        formData.append('soa_file', soaFileInput.files[0]);
        const btn = this;
        btn.disabled = true;
        fetch(API + '/upload-soa', { method: 'POST', headers: authHeaders(), body: formData })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) { toast(json.message || 'Could not parse file.', true); return; }
                soaParsedRows = json.parsed_rows || [];
                document.getElementById('soaPreviewArea').style.display = '';
                document.getElementById('soaPreviewSummary').innerHTML = escapeHtml(json.message);
                document.getElementById('soaPreviewBody').innerHTML = soaParsedRows.map(function (r) {
                    const style = r.is_duplicate ? 'opacity:.45;' : '';
                    const badge = r.is_duplicate ? ' <span class="fims-badge fims-badge-gray">Exists</span>' : (r.is_processed ? ' <span class="fims-badge fims-badge-amber">Processed</span>' : ' <span class="fims-badge fims-badge-green">New</span>');
                    return '<tr style="' + style + '"><td>' + escapeHtml(r.date) + '</td><td>' + escapeHtml(r.description) + '</td>' +
                        '<td class="text-right">' + (r.debit ? peso(r.debit) : '—') + '</td><td class="text-right">' + (r.credit ? peso(r.credit) : '—') + '</td>' +
                        '<td class="text-right">' + peso(r.balance) + '</td><td>' + escapeHtml(r.check_no || '') + '</td><td>' + badge + '</td></tr>';
                }).join('');
                document.getElementById('soaConfirmBtn').style.display = json.new_count > 0 ? '' : 'none';
                document.getElementById('soaConfirmBtn').textContent = 'Confirm & Save (' + json.new_count + ' new)';
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    document.getElementById('soaConfirmBtn')?.addEventListener('click', function () {
        const bankAccountId = document.getElementById('soaBankAccount').value;
        const btn = this;
        btn.disabled = true;
        fetch(API + '/confirm-upload', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ bank_account_id: bankAccountId, parsed_rows: soaParsedRows }) })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? 'Saved.' : 'Could not save.'), !json.success);
                if (json.success) {
                    closeModal('m-upload-soa');
                    loadUpdatesKpis();
                    loadUpdatesList();
                    loadActivityLog();
                    loadSoaComparison();
                    loadSoaGroups();
                }
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    // ── Batch Upload SOA modal ──
    // Each row holds its own {bankAccountId, file, parsedRows, newCount, status} state,
    // keyed by a row id. "Parse All"/"Confirm All" walk the rows sequentially
    // (one fetch at a time, via Array.reduce chaining) — same as TREASURY,
    // not concurrently, so the per-row status updates stay readable and any
    // one row's failure doesn't race with the others.
    let batchSoaRowId = 0;
    const batchSoaJobs = {};
    let batchSoaBankAccounts = [];

    function addBatchSoaRow(preselectBankAccountId) {
        const id = ++batchSoaRowId;
        batchSoaJobs[id] = { bankAccountId: preselectBankAccountId || null, file: null, parsedRows: [], newCount: 0, status: 'pending' };
        const tr = document.createElement('tr');
        tr.dataset.rowId = id;
        tr.innerHTML =
            '<td><select class="fims-select fims-input-sm batch-soa-bank"><option value="">Select bank account...</option>' +
                batchSoaBankAccounts.map(b => '<option value="' + b.bank_account_id + '">' + escapeHtml(b.account_name) + '</option>').join('') +
            '</select></td>' +
            '<td><input type="file" class="batch-soa-file" accept=".xlsx" style="font-size:12px;"></td>' +
            '<td class="batch-soa-status" style="font-size:12px;color:var(--fims-text-subtle);">Not parsed</td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-batch-row><i class="fas fa-trash"></i></button></td>';
        if (preselectBankAccountId) tr.querySelector('.batch-soa-bank').value = preselectBankAccountId;
        tr.querySelector('.batch-soa-bank').addEventListener('change', function () { batchSoaJobs[id].bankAccountId = this.value; });
        tr.querySelector('.batch-soa-file').addEventListener('change', function () { batchSoaJobs[id].file = this.files[0] || null; });
        tr.querySelector('[data-remove-batch-row]').addEventListener('click', function () { delete batchSoaJobs[id]; tr.remove(); });
        document.getElementById('batchSoaRows').appendChild(tr);
        return id;
    }

    function renderBatchSoaAccountPicker(filterText) {
        const picker = document.getElementById('batchSoaAccountPicker');
        const needle = (filterText || '').toLowerCase().trim();
        const list = batchSoaBankAccounts.filter(b => !needle || b.account_name.toLowerCase().includes(needle) || (b.bank_name || '').toLowerCase().includes(needle));
        picker.innerHTML = list.length ? list.map(function (b) {
            return '<label style="display:block;padding:3px 0;">' +
                '<input type="checkbox" class="batch-soa-picker-cb" value="' + b.bank_account_id + '"> ' +
                escapeHtml(b.account_name) + ' <span style="color:var(--fims-text-subtle);font-size:11px;">— ' + escapeHtml(b.bank_name || '') + '</span>' +
                '</label>';
        }).join('') : '<span style="color:var(--fims-text-subtle);font-size:12px;">No matching accounts.</span>';
    }
    document.getElementById('batchSoaAccountSearch')?.addEventListener('input', function () { renderBatchSoaAccountPicker(this.value); });

    document.getElementById('batchSoaAddSelectedBtn')?.addEventListener('click', function () {
        const checked = Array.from(document.querySelectorAll('.batch-soa-picker-cb:checked')).map(cb => cb.value);
        if (!checked.length) { toast('Select at least one bank account.', true); return; }
        const alreadyAdded = new Set(Object.values(batchSoaJobs).map(j => String(j.bankAccountId)));
        let added = 0;
        checked.forEach(function (bankAccountId) {
            if (alreadyAdded.has(bankAccountId)) return;
            addBatchSoaRow(bankAccountId);
            alreadyAdded.add(bankAccountId);
            added++;
        });
        document.querySelectorAll('.batch-soa-picker-cb:checked').forEach(cb => { cb.checked = false; });
        if (added) toast('Added ' + added + ' row(s).');
        else toast('Those accounts are already in the row list.', true);
    });

    document.getElementById('updBatchUploadBtn')?.addEventListener('click', function () {
        document.getElementById('batchSoaRows').innerHTML = '';
        document.getElementById('batchSoaSummary').style.display = 'none';
        document.getElementById('batchSoaConfirmAllBtn').disabled = true;
        document.getElementById('batchSoaAccountSearch').value = '';
        Object.keys(batchSoaJobs).forEach(k => delete batchSoaJobs[k]);
        fetch(API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                batchSoaBankAccounts = json.data || [];
                renderBatchSoaAccountPicker('');
            })
            .catch(() => { renderBatchSoaAccountPicker(''); });
        openModal('m-batch-upload-soa');
    });
    document.getElementById('batchSoaAddRowBtn')?.addEventListener('click', function () { addBatchSoaRow(null); });

    function parseOneBatchRow(id) {
        const job = batchSoaJobs[id];
        const tr = document.querySelector('#batchSoaRows tr[data-row-id="' + id + '"]');
        const statusCell = tr.querySelector('.batch-soa-status');
        if (!job.bankAccountId || !job.file) {
            statusCell.textContent = 'Skipped (missing bank account or file)';
            return Promise.resolve();
        }
        statusCell.textContent = 'Parsing…';
        const formData = new FormData();
        formData.append('bank_account_id', job.bankAccountId);
        formData.append('soa_file', job.file);
        return fetch(API + '/upload-soa', { method: 'POST', headers: authHeaders(), body: formData })
            .then(r => r.json())
            .then(function (json) {
                if (!json.success) { statusCell.textContent = 'Parse failed: ' + (json.message || ''); job.status = 'failed'; return; }
                job.parsedRows = json.parsed_rows || [];
                job.newCount = json.new_count || 0;
                job.status = 'parsed';
                statusCell.textContent = job.newCount + ' new, ' + json.duplicate_count + ' duplicate(s)';
            })
            .catch(() => { statusCell.textContent = 'Could not reach the server.'; job.status = 'failed'; });
    }

    document.getElementById('batchSoaParseAllBtn')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        const ids = Object.keys(batchSoaJobs);
        ids.reduce(function (chain, id) { return chain.then(() => parseOneBatchRow(id)); }, Promise.resolve())
            .then(function () {
                const anyNew = ids.some(id => batchSoaJobs[id].newCount > 0);
                document.getElementById('batchSoaConfirmAllBtn').disabled = !anyNew;
            })
            .finally(() => { btn.disabled = false; });
    });

    function confirmOneBatchRow(id) {
        const job = batchSoaJobs[id];
        const tr = document.querySelector('#batchSoaRows tr[data-row-id="' + id + '"]');
        const statusCell = tr.querySelector('.batch-soa-status');
        if (job.status !== 'parsed' || !job.parsedRows.length) {
            return Promise.resolve({ saved: 0 });
        }
        statusCell.textContent = 'Saving…';
        return fetch(API + '/confirm-upload', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ bank_account_id: job.bankAccountId, parsed_rows: job.parsedRows }) })
            .then(r => r.json())
            .then(function (json) {
                statusCell.textContent = json.success ? ('Saved ' + json.saved + ' line(s)') : ('Failed: ' + (json.message || ''));
                return json;
            })
            .catch(function () {
                statusCell.textContent = 'Could not reach the server.';
                return { saved: 0 };
            });
    }

    document.getElementById('batchSoaConfirmAllBtn')?.addEventListener('click', function () {
        const btn = this;
        btn.disabled = true;
        const ids = Object.keys(batchSoaJobs);
        let totalSaved = 0;
        let totalFailed = 0;
        ids.reduce(function (chain, id) {
            return chain.then(() => confirmOneBatchRow(id)).then(function (result) {
                if (result && result.success) totalSaved += result.saved || 0;
                else if (batchSoaJobs[id].status === 'parsed') totalFailed++;
            });
        }, Promise.resolve())
            .then(function () {
                const summary = document.getElementById('batchSoaSummary');
                summary.style.display = '';
                summary.innerHTML = '<div class="fims-alert-body">Batch complete — ' + totalSaved + ' line(s) saved across ' + ids.length + ' row(s), ' + totalFailed + ' failure(s).</div>';
                loadUpdatesKpis();
                loadUpdatesList();
                loadActivityLog();
                loadSoaComparison();
                loadSoaGroups();
            })
            .finally(() => { btn.disabled = false; });
    });

    function initUpdates() { loadUpdatesFilters(); loadUpdatesKpis(); loadUpdatesList(); loadActivityLog(); loadSoaComparison(); loadSoaGroups(); }

    // ── Manual Entry modal ──
    function renderRestrictedRows(rows) {
        const tbody = document.getElementById('meRestrictedBody');
        tbody.innerHTML = (rows || []).map(function (item, idx) {
            return '<tr><td><input type="text" class="fims-input fims-input-sm me-rf-label" value="' + escapeHtml(item.label || '') + '"></td>' +
                '<td><input type="number" step="0.01" class="fims-input fims-input-sm me-rf-amount" value="' + (item.amount || 0) + '"></td>' +
                '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-rf="' + idx + '"><i class="fas fa-trash"></i></button></td></tr>';
        }).join('');
        tbody.querySelectorAll('[data-remove-rf]').forEach(function (btn) {
            btn.addEventListener('click', function () { btn.closest('tr').remove(); recomputeManualEntry(); });
        });
        tbody.querySelectorAll('.me-rf-amount, .me-rf-label').forEach(function (inp) {
            inp.addEventListener('input', recomputeManualEntry);
        });
    }
    document.getElementById('meAddRestrictedRowBtn')?.addEventListener('click', function () {
        const tbody = document.getElementById('meRestrictedBody');
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" class="fims-input fims-input-sm me-rf-label"></td>' +
            '<td><input type="number" step="0.01" class="fims-input fims-input-sm me-rf-amount" value="0"></td>' +
            '<td><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-rf><i class="fas fa-trash"></i></button></td>';
        tr.querySelector('[data-remove-rf]').addEventListener('click', function () { tr.remove(); recomputeManualEntry(); });
        tr.querySelectorAll('.me-rf-amount, .me-rf-label').forEach(function (inp) { inp.addEventListener('input', recomputeManualEntry); });
        tbody.appendChild(tr);
    });
    function getRestrictedBreakdown() {
        return Array.from(document.querySelectorAll('#meRestrictedBody tr')).map(function (tr) {
            return { label: tr.querySelector('.me-rf-label').value.trim(), amount: parseFloat(tr.querySelector('.me-rf-amount').value) || 0 };
        }).filter(item => item.amount > 0);
    }
    function recomputeManualEntry() {
        const opening = parseFloat(document.getElementById('meOpeningBalance').value) || 0;
        const receipts = parseFloat(document.getElementById('meTotalReceipts').value) || 0;
        const disbursements = parseFloat(document.getElementById('meTotalDisbursements').value) || 0;
        const adjDirection = document.getElementById('meAdjustmentDirection').value;
        const adjAmount = parseFloat(document.getElementById('meAdjustmentAmount').value) || 0;
        const signedAdj = adjDirection === 'DEDUCTION' ? -adjAmount : adjAmount;
        const closing = opening + receipts - disbursements + signedAdj;
        const breakdown = getRestrictedBreakdown();
        const restricted = breakdown.length ? breakdown.reduce((s, i) => s + i.amount, 0) : (parseFloat(document.getElementById('meRestrictedAmount').value) || 0);
        document.getElementById('meComputedClosing').textContent = peso(closing);
        document.getElementById('meComputedAvailable').textContent = peso(closing - restricted);
    }
    ['meOpeningBalance', 'meTotalReceipts', 'meTotalDisbursements', 'meAdjustmentDirection', 'meAdjustmentAmount', 'meRestrictedAmount'].forEach(function (id) {
        document.getElementById(id)?.addEventListener('input', recomputeManualEntry);
        document.getElementById(id)?.addEventListener('change', recomputeManualEntry);
    });

    function clearManualEntryModal() {
        document.getElementById('meCashPositionId').value = '';
        document.getElementById('meBankAccount').value = '';
        document.getElementById('mePositionDate').value = new Date().toISOString().slice(0, 10);
        document.getElementById('meOpeningBalance').value = '';
        document.getElementById('meOpeningBalance').readOnly = false;
        document.getElementById('meOpeningHint').textContent = '';
        document.getElementById('meUpdateType').value = '';
        document.getElementById('meBankStatementBalance').value = '';
        document.getElementById('meTotalReceipts').value = 0;
        document.getElementById('meTotalReceipts').readOnly = true;
        document.getElementById('meReceiptsHint').textContent = 'Auto-computed from posted bank transactions for this date.';
        document.getElementById('meTotalDisbursements').value = 0;
        document.getElementById('meAdjustmentDirection').value = '';
        document.getElementById('meAdjustmentAmount').value = 0;
        document.getElementById('meAdjustmentDetails').value = '';
        document.getElementById('meRestrictedAmount').value = 0;
        document.getElementById('meReferenceNumber').value = '';
        document.getElementById('meReconciled').checked = false;
        document.getElementById('meRemarks').value = '';
        renderRestrictedRows([]);
        recomputeManualEntry();
        goToMeStep(1);
    }

    // ── Manual Entry 4-step wizard (Account & Period -> Balances ->
    // Restricted Funds -> Review) ──
    const ME_TOTAL_STEPS = 4;
    let meCurrentStep = 1;

    function validateMeStep(step) {
        if (step === 1) {
            if (!document.getElementById('meBankAccount').value) return 'Select a bank account.';
            if (!document.getElementById('mePositionDate').value) return 'Position date is required.';
            if (!document.getElementById('meUpdateType').value) return 'Select an Update Type.';
        } else if (step === 2) {
            if (document.getElementById('meOpeningBalance').value === '') return 'Beginning balance is required.';
        }
        return null;
    }

    function goToMeStep(step) {
        meCurrentStep = step;
        document.querySelectorAll('#m-manual-entry .cps-me-step').forEach(function (el) {
            el.style.display = (parseInt(el.dataset.step, 10) === step) ? '' : 'none';
        });
        document.querySelectorAll('#meStepIndicator .cps-step-pill').forEach(function (pill) {
            const n = parseInt(pill.dataset.stepPill, 10);
            pill.classList.toggle('active', n === step);
            pill.classList.toggle('done', n < step);
        });
        document.getElementById('meBackBtn').style.display = step > 1 ? '' : 'none';
        document.getElementById('meNextBtn').style.display = step < ME_TOTAL_STEPS ? '' : 'none';
        document.getElementById('meSaveBtn').style.display = step === ME_TOTAL_STEPS ? '' : 'none';
        if (step === ME_TOTAL_STEPS) recomputeManualEntry();
    }

    document.getElementById('meNextBtn')?.addEventListener('click', function () {
        const err = validateMeStep(meCurrentStep);
        if (err) { toast(err, true); return; }
        goToMeStep(Math.min(meCurrentStep + 1, ME_TOTAL_STEPS));
    });
    document.getElementById('meBackBtn')?.addEventListener('click', function () {
        goToMeStep(Math.max(meCurrentStep - 1, 1));
    });
    document.querySelectorAll('#meStepIndicator .cps-step-pill').forEach(function (pill) {
        pill.style.cursor = 'pointer';
        pill.addEventListener('click', function () {
            const target = parseInt(this.dataset.stepPill, 10);
            if (target <= meCurrentStep) { goToMeStep(target); return; }
            for (let s = meCurrentStep; s < target; s++) {
                const err = validateMeStep(s);
                if (err) { toast(err, true); return; }
            }
            goToMeStep(target);
        });
    });

    function refreshComputedBalance() {
        const bankAccountId = document.getElementById('meBankAccount').value;
        const positionDate = document.getElementById('mePositionDate').value;
        const hint = document.getElementById('meOpeningHint');
        const field = document.getElementById('meOpeningBalance');
        if (!bankAccountId || !positionDate) return;
        fetch(API + '/updates/computed-balance?bank_account_id=' + bankAccountId + '&position_date=' + positionDate, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = json.data || {};
                if (d.has_prior_balance) {
                    field.value = d.opening_balance;
                    field.readOnly = true;
                    hint.textContent = 'Auto-pulled from the prior day\'s ending balance.';
                } else {
                    field.readOnly = false;
                    hint.textContent = 'No prior record found — enter the starting balance for this account.';
                }
                // Total Disbursements always comes from the ledger. Total
                // Receipts does too, UNLESS Update Type = COLLECTION, which
                // leaves it editable (TREASURY's exact field-locking rule).
                document.getElementById('meTotalDisbursements').value = d.total_disbursements || 0;
                if (document.getElementById('meUpdateType').value !== 'COLLECTION') {
                    document.getElementById('meTotalReceipts').value = d.total_receipts || 0;
                }
                recomputeManualEntry();
            })
            .catch(() => {});

        // Auto-pull the bank statement balance from the latest uploaded
        // SOA for this account (TREASURY's auto-pull behavior) — only
        // when the field is still blank, so it never clobbers a value
        // the user already typed or one loaded from an existing entry.
        if (!document.getElementById('meBankStatementBalance').value) {
            fetch(API + '/soa-balance/' + bankAccountId + '?as_of=' + positionDate, { headers: authHeaders() })
                .then(r => r.json())
                .then(function (json) {
                    const soa = (json && json.data) ? json.data : {};
                    if (soa.balance != null && !document.getElementById('meBankStatementBalance').value) {
                        document.getElementById('meBankStatementBalance').value = soa.balance;
                    }
                })
                .catch(() => {});
        }
    }
    document.getElementById('meBankAccount')?.addEventListener('change', refreshComputedBalance);
    document.getElementById('mePositionDate')?.addEventListener('change', refreshComputedBalance);
    document.getElementById('meUpdateType')?.addEventListener('change', function () {
        const receipts = document.getElementById('meTotalReceipts');
        const hint = document.getElementById('meReceiptsHint');
        if (this.value === 'COLLECTION') {
            receipts.readOnly = false;
            hint.textContent = 'Editable for Collection entries.';
        } else {
            receipts.readOnly = true;
            hint.textContent = 'Auto-computed from posted bank transactions for this date.';
            refreshComputedBalance();
        }
    });

    function openManualEntryModal(bankAccountId, positionDate, entryId) {
        clearManualEntryModal();
        if (!bankAccountId) {
            document.getElementById('meModalTitle').textContent = 'Manual Cash Position Entry';
            openModal('m-manual-entry');
            return;
        }
        document.getElementById('meModalTitle').textContent = 'Edit Cash Position Entry';
        document.getElementById('meBankAccount').value = bankAccountId;
        if (!entryId) {
            document.getElementById('mePositionDate').value = positionDate || new Date().toISOString().slice(0, 10);
            refreshComputedBalance();
            openModal('m-manual-entry');
            return;
        }
        fetch(API + '/updates/' + entryId, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load entry.', true); return; }
                const d = json.data;
                document.getElementById('meCashPositionId').value = d.cash_position_id;
                document.getElementById('mePositionDate').value = d.position_date;
                document.getElementById('meOpeningBalance').value = d.opening_balance;
                document.getElementById('meOpeningBalance').readOnly = true;
                document.getElementById('meBankStatementBalance').value = d.bank_statement_balance || '';
                document.getElementById('meTotalReceipts').value = d.total_receipts;
                document.getElementById('meTotalDisbursements').value = d.total_disbursements;
                document.getElementById('meAdjustmentDirection').value = d.adjustment_direction || '';
                document.getElementById('meAdjustmentAmount').value = d.adjustment_amount || 0;
                document.getElementById('meAdjustmentDetails').value = d.adjustment_details || '';
                document.getElementById('meRestrictedAmount').value = d.restricted_amount || 0;
                document.getElementById('meReferenceNumber').value = d.reference_number || '';
                document.getElementById('meReconciled').checked = !!d.reconciled;
                document.getElementById('meRemarks').value = d.remarks || '';
                renderRestrictedRows(d.restricted_breakdown || []);
                recomputeManualEntry();
                openModal('m-manual-entry');
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    document.getElementById('meSaveBtn')?.addEventListener('click', function () {
        const bankAccountId = document.getElementById('meBankAccount').value;
        const positionDate = document.getElementById('mePositionDate').value;
        const updateType = document.getElementById('meUpdateType').value;
        if (!bankAccountId) { toast('Select a bank account.', true); return; }
        if (!positionDate) { toast('Position date is required.', true); return; }
        if (!updateType) { toast('Select an Update Type.', true); return; }
        if (document.getElementById('meOpeningBalance').value === '') { toast('Beginning balance is required.', true); return; }

        const entryId = document.getElementById('meCashPositionId').value;
        const payload = {
            bank_account_id: bankAccountId,
            position_date: positionDate,
            update_type: updateType,
            opening_balance: parseFloat(document.getElementById('meOpeningBalance').value) || 0,
            total_receipts: parseFloat(document.getElementById('meTotalReceipts').value) || 0,
            total_disbursements: parseFloat(document.getElementById('meTotalDisbursements').value) || 0,
            bank_statement_balance: document.getElementById('meBankStatementBalance').value,
            restricted_amount: parseFloat(document.getElementById('meRestrictedAmount').value) || 0,
            restricted_breakdown: getRestrictedBreakdown(),
            adjustment_amount: parseFloat(document.getElementById('meAdjustmentAmount').value) || 0,
            adjustment_direction: document.getElementById('meAdjustmentDirection').value,
            adjustment_details: document.getElementById('meAdjustmentDetails').value.trim(),
            reference_number: document.getElementById('meReferenceNumber').value.trim(),
            reconciled: document.getElementById('meReconciled').checked,
            remarks: document.getElementById('meRemarks').value.trim(),
        };
        const btn = this;
        btn.disabled = true;
        const url = entryId ? (API + '/updates/' + entryId + '/update') : (API + '/updates/save');
        fetch(url, { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? 'Cash position saved.' : 'Could not save.'), !json.success);
                if (json.success) {
                    closeModal('m-manual-entry');
                    loadUpdatesKpis();
                    loadUpdatesList();
                    loadActivityLog();
                }
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    // ══════════════════════ AVAILABLE CASH BALANCES ══════════════════════
    function loadAvailableFilters() {
        fetch(API + '/lookup/fund-sources', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#availFilterFundSource', (json.data || []).map(f => ({ v: f })), 'All Fund Sources', 'v', i => i.v)).catch(() => {});
    }
    function loadAvailable() {
        const tbody = document.querySelector('#availTable tbody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Loading…</td></tr>';
        const params = new URLSearchParams();
        const fs = document.getElementById('availFilterFundSource').value;
        if (fs) params.set('fund_source', fs);
        fetch(API + '/available-balances/list?' + params.toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const threshold = parseFloat(document.getElementById('availThreshold').value) || 500000;

                let grandBook = 0, grandAvailable = 0, grandUsable = 0;
                rows.forEach(r => { grandBook += r.closing_balance; grandAvailable += r.available_balance; grandUsable += r.usable_balance; });
                document.querySelector('#availKpis [data-kpi="book"]').textContent = peso(grandBook);
                document.querySelector('#availKpis [data-kpi="available"]').textContent = peso(grandAvailable);
                document.querySelector('#availKpis [data-kpi="usable"]').textContent = peso(grandUsable);

                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">No records.</td></tr>'; document.querySelector('#availKpis [data-kpi="alerts"]').textContent = 0; return; }

                const groups = groupByFundSource(rows);
                let alertCount = 0;
                tbody.innerHTML = groups.map(function (g, gi) {
                    const groupClass = 'avail-group-' + gi;
                    const totals = g.rows.reduce((acc, r) => {
                        acc.book += r.closing_balance; acc.restricted += r.restricted_amount; acc.available += r.available_balance;
                        acc.outstanding += r.outstanding_checks; acc.usable += r.usable_balance;
                        return acc;
                    }, { book: 0, restricted: 0, available: 0, outstanding: 0, usable: 0 });
                    const groupLow = totals.usable < threshold;
                    if (groupLow) alertCount++;
                    const header = '<tr class="cps-group-header" data-group-toggle="' + groupClass + '">' +
                        '<td><i class="fas fa-caret-right mr-1"></i><strong>' + escapeHtml(g.fund_source) + '</strong> <span class="fims-badge">' + g.rows.length + ' account(s)</span></td>' +
                        '<td class="text-right"><strong>' + peso(totals.book) + '</strong></td>' +
                        '<td class="text-right"><strong>' + peso(totals.restricted) + '</strong></td>' +
                        '<td class="text-right"><strong>' + peso(totals.available) + '</strong></td>' +
                        '<td class="text-right"><strong>' + peso(totals.outstanding) + '</strong></td>' +
                        '<td class="text-right"><strong>' + peso(totals.usable) + '</strong></td>' +
                        '<td class="text-center">' + (groupLow ? '<span class="fims-badge fims-badge-red">Low</span>' : '<span class="fims-badge fims-badge-green">Normal</span>') + '</td>' +
                        '<td class="text-center"><button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-trend="' + escapeHtml(g.fund_source) + '" title="View Trend" onclick="event.stopPropagation()"><i class="fas fa-chart-line"></i></button></td></tr>';
                    const leaves = g.rows.map(function (r) {
                        const low = r.usable_balance < threshold;
                        return '<tr class="' + groupClass + '" style="display:none;">' +
                            '<td style="padding-left:24px;">' + escapeHtml(r.account_name) + '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_name || '') + ' · ' + escapeHtml(r.account_number || '') + '</span></td>' +
                            '<td class="text-right">' + peso(r.closing_balance) + '</td>' +
                            '<td class="text-right">' + peso(r.restricted_amount) + '</td>' +
                            '<td class="text-right">' + peso(r.available_balance) + '</td>' +
                            '<td class="text-right">' + peso(r.outstanding_checks) + '</td>' +
                            '<td class="text-right" style="font-weight:600;">' + peso(r.usable_balance) + '</td>' +
                            '<td class="text-center">' + (low ? '<span class="fims-badge fims-badge-red">Low</span>' : '<span class="fims-badge fims-badge-green">Normal</span>') + '</td>' +
                            '<td class="text-center">' + escapeHtml(r.position_date || '—') + '</td>' +
                            '</tr>';
                    }).join('');
                    return header + leaves;
                }).join('');
                document.querySelector('#availKpis [data-kpi="alerts"]').textContent = alertCount;
                tbody.querySelectorAll('.cps-group-header').forEach(function (header) {
                    header.addEventListener('click', function () {
                        const cls = this.dataset.groupToggle;
                        const caret = this.querySelector('.fa-caret-right, .fa-caret-down');
                        const leaves = tbody.querySelectorAll('tr.' + cls);
                        const willShow = leaves.length && leaves[0].style.display === 'none';
                        leaves.forEach(function (tr) { tr.style.display = willShow ? '' : 'none'; });
                        if (caret) caret.className = 'fas ' + (willShow ? 'fa-caret-down' : 'fa-caret-right') + ' mr-1';
                    });
                });
                tbody.querySelectorAll('[data-trend]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openTrendModal(this.dataset.trend); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="8">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('availFilterBtn')?.addEventListener('click', function () { loadAvailable(); loadAlertsBanner(); });
    document.getElementById('availResetBtn')?.addEventListener('click', function () {
        document.getElementById('availFilterFundSource').value = '';
        document.getElementById('availThreshold').value = 500000;
        loadAvailable();
        loadAlertsBanner();
    });

    // Real backend-driven alert banner (GET /available-balances/alerts),
    // independent of the client-computed per-group "Low" badges above —
    // gives the fund-source-level threshold check a dedicated source of
    // truth matching TREASURY's getLowBalanceAlerts().
    function loadAlertsBanner() {
        const threshold = parseFloat(document.getElementById('availThreshold').value) || 500000;
        fetch(API + '/available-balances/alerts?threshold=' + threshold, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const banner = document.getElementById('availAlertsBanner');
                const d = json.data || {};
                if (!d.count) { banner.style.display = 'none'; return; }
                banner.style.display = '';
                banner.querySelector('.fims-alert-body').innerHTML = '<strong>' + d.count + ' fund source(s) below the ' + peso(threshold) + ' threshold:</strong> ' +
                    (d.alerts || []).map(a => escapeHtml(a.fund_source) + ' (' + peso(a.usable_balance) + ')').join(', ');
            })
            .catch(() => {});
    }

    // ── Balance Trend modal ──
    function openTrendModal(fundSource) {
        document.getElementById('trendFundSource').textContent = 'Balance Trend — ' + fundSource;
        document.getElementById('trendDateTo').value = new Date().toISOString().slice(0, 10);
        document.getElementById('trendDateFrom').value = new Date(Date.now() - 29 * 86400000).toISOString().slice(0, 10);
        document.getElementById('m-balance-trend').dataset.fundSource = fundSource;
        loadTrend(fundSource);
        openModal('m-balance-trend');
    }
    function loadTrend(fundSource) {
        const tbody = document.getElementById('trendBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="4">Loading…</td></tr>';
        const params = new URLSearchParams({
            fund_source: fundSource,
            date_from: document.getElementById('trendDateFrom').value,
            date_to: document.getElementById('trendDateTo').value,
        });
        fetch(API + '/available-balances/history?' + params.toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data && json.data.history) ? json.data.history : [];
                if (!rows.length) { tbody.innerHTML = '<tr class="fims-empty"><td colspan="4">No history in this range.</td></tr>'; return; }
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr><td>' + escapeHtml(r.position_date) + '</td>' +
                        '<td class="text-right">' + peso(r.book_balance) + '</td>' +
                        '<td class="text-right">' + peso(r.available_balance) + '</td>' +
                        '<td class="text-right">' + peso(r.restricted_amount) + '</td></tr>';
                }).join('');
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="4">Could not reach the server.</td></tr>'; });
    }
    document.getElementById('trendApplyBtn')?.addEventListener('click', function () {
        loadTrend(document.getElementById('m-balance-trend').dataset.fundSource);
    });

    function initAvailable() { loadAvailableFilters(); loadAvailable(); loadAlertsBanner(); }

    // ══════════════════════ REPORTS ══════════════════════
    const REPORTS_API = API + '/reports';
    let repFyAccounts = [];

    function initReports() {
        document.getElementById('repDateFrom').value = new Date().getFullYear() + '-01-01';
        document.getElementById('repDateTo').value = new Date().toISOString().slice(0, 10);
        fetch(REPORTS_API + '/lookup/bank-accounts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                repFyAccounts = json.data || [];
                fillSelect('#repBankAccount', repFyAccounts, 'All Banks', 'bank_account_id', i => i.account_name);
            }).catch(() => {});
        fetch(REPORTS_API + '/lookup/fund-sources', { headers: authHeaders() })
            .then(r => r.json()).then(json => fillSelect('#repFundSource', (json.data || []).map(f => ({ v: f })), 'All Fund Sources', 'v', i => i.v)).catch(() => {});
    }

    function buildReportParams() {
        const params = new URLSearchParams();
        params.set('report_type', document.getElementById('repReportType').value);
        params.set('date_from', document.getElementById('repDateFrom').value);
        params.set('date_to', document.getElementById('repDateTo').value);
        const bank = document.getElementById('repBankAccount').value;
        const fs = document.getElementById('repFundSource').value;
        const cls = document.getElementById('repClassification').value;
        if (bank) params.set('bank_account_id', bank);
        if (fs) params.set('fund_source', fs);
        if (cls) params.set('classification', cls);
        return params;
    }

    document.getElementById('repRunBtn')?.addEventListener('click', function () {
        const type = document.getElementById('repReportType').value;
        const area = document.getElementById('repResultArea');
        area.innerHTML = '<div class="fims-empty" style="padding:30px;text-align:center;">Loading…</div>';
        fetch(REPORTS_API + '/data?' + buildReportParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                renderReport(type, json);
            })
            .catch(() => { area.innerHTML = '<div class="fims-empty" style="padding:30px;text-align:center;">Could not reach the server.</div>'; });
    });

    function renderReport(type, json) {
        const area = document.getElementById('repResultArea');
        if (!json || !json.success) {
            area.innerHTML = '<div class="fims-alert fims-alert-danger"><div class="fims-alert-body">' + escapeHtml((json && json.message) || 'Could not run this report.') + '</div></div>';
            return;
        }
        if (json.records && !json.records.length && !json.data) {
            area.innerHTML = '<div class="fims-alert fims-alert-info"><div class="fims-alert-body">' + escapeHtml(json.message || 'No data.') + '</div></div>';
            return;
        }
        const d = json.data || {};

        if (type === 'daily') {
            const rows = d.records || [];
            area.innerHTML = reportTable(
                ['Account Name', 'Account No.', 'Beginning', 'Check Disb.', 'Cancelled/Stale', 'Ending', 'Restricted', 'Available'],
                rows.map(r => [
                    escapeHtml(r.account_name), escapeHtml(r.account_number || ''),
                    peso(r.opening_balance), peso(r.disbursement_thru_check), peso(r.cancelled_staled_check),
                    peso(r.balance), peso(r.restricted_amount), peso(r.available_balance),
                ])
            );
            return;
        }

        if (['weekly', 'monthly', 'quarterly', 'annual', 'by_fund_source', 'by_bank_account', 'by_classification'].includes(type)) {
            const rows = d.records || [];
            const totals = d.totals || {};
            let html = reportTable(
                ['Date', 'Account', 'Bank', 'Fund Source', 'Beginning', '+ Receipts', '− Disbursements', 'Ending', 'Restricted', 'Available', 'Recon.'],
                rows.map(r => [
                    escapeHtml(r.position_date), escapeHtml(r.account_name), escapeHtml(r.bank_name || ''), escapeHtml(r.fund_source || 'General'),
                    peso(r.opening_balance), peso(r.total_receipts), peso(r.total_disbursements), peso(r.closing_balance),
                    peso(r.restricted_amount), peso(r.available_balance), r.reconciled ? 'Yes' : 'No',
                ])
            );
            html += '<div class="fims-alert fims-alert-info" style="margin-top:10px;"><div class="fims-alert-body">' +
                'TOTAL — Beginning: <strong>' + peso(totals.opening) + '</strong>, Receipts: <strong>' + peso(totals.receipts) + '</strong>, ' +
                'Disbursements: <strong>' + peso(totals.disbursements) + '</strong>, Ending: <strong>' + peso(totals.closing) + '</strong>, ' +
                'Restricted: <strong>' + peso(totals.restricted) + '</strong>, Available: <strong>' + peso(totals.available) + '</strong></div></div>';
            area.innerHTML = html;
            return;
        }

        if (type === 'working_paper') {
            const rows = d.records || [];
            let html = '';
            if (d.summary) {
                html += '<div class="fims-alert fims-alert-info"><div class="fims-alert-body">' +
                    'Beginning: <strong>' + peso(d.summary.beginning_balance) + '</strong> · Ending: <strong>' + peso(d.summary.ending_balance) + '</strong> · ' +
                    'Restricted: <strong>' + peso(d.summary.restricted_amount) + '</strong> · Available: <strong>' + peso(d.summary.available_balance) + '</strong></div></div>';
            }
            html += reportTable(
                ['Date', 'Ref #', 'Type', 'Account', 'Payee', 'Debit', 'Credit'],
                rows.map(r => [escapeHtml(r.txn_date), escapeHtml(r.reference || ''), escapeHtml(r.transaction_type), escapeHtml(r.account_name), escapeHtml(r.party_name || ''), r.debit != null ? peso(r.debit) : '—', r.credit != null ? peso(r.credit) : '—'])
            );
            area.innerHTML = html;
            return;
        }

        if (type === 'individual_ledger') {
            if (!d.account) { area.innerHTML = '<div class="fims-alert fims-alert-info"><div class="fims-alert-body">Select a bank account to view its ledger.</div></div>'; return; }
            const rows = (d.days || []).map(function (day) {
                if (day.is_balance) return [escapeHtml(day.date), 'Balance', '—', peso(day.soa_balance), peso(day.available_balance)];
                return [escapeHtml(day.date), 'Checks', peso(day.checks.disbursed), peso(day.checks.encashed), peso(day.checks.cancelled)];
            });
            area.innerHTML = '<p><strong>' + escapeHtml(d.account.name) + '</strong> — ' + escapeHtml(d.account.bank_name) + ' · ' + escapeHtml(d.account.account_number) + '</p>' +
                reportTable(['Date', 'Type', 'Disbursed/SOA', 'Encashed/Available', 'Cancelled'], rows);
            return;
        }

        // Stubbed report types.
        area.innerHTML = '<div class="fims-alert fims-alert-warning"><div class="fims-alert-body">' + escapeHtml(json.message || 'This report is not available yet.') + '</div></div>';
    }

    function reportTable(headers, rows) {
        if (!rows.length) return '<div class="fims-empty" style="padding:20px;text-align:center;">No records.</div>';
        return '<table class="fims-table" id="repResultTable"><thead><tr>' +
            headers.map(h => '<th>' + h + '</th>').join('') + '</tr></thead><tbody>' +
            rows.map(row => '<tr>' + row.map(c => '<td>' + c + '</td>').join('') + '</tr>').join('') +
            '</tbody></table>';
    }

    document.getElementById('repExportBtn')?.addEventListener('click', function () {
        if (!document.getElementById('repResultTable')) { toast('Run a report first.', true); return; }
        exportTableCSV('repResultTable', document.getElementById('repReportType').value + '-report');
    });

    // ── New FY Opening Balance modal ──
    document.getElementById('repFyBtn')?.addEventListener('click', function () {
        const thisYear = new Date().getFullYear();
        document.getElementById('fyFromYear').value = thisYear;
        document.getElementById('fyNewYear').value = thisYear + 1;
        document.getElementById('fyAccountList').innerHTML = repFyAccounts.map(function (a) {
            return '<label style="display:block;padding:3px 0;"><input type="checkbox" class="fy-account-cb" value="' + a.bank_account_id + '"> ' + escapeHtml(a.account_name) + '</label>';
        }).join('') || '<span style="color:var(--fims-text-subtle)">No active bank accounts.</span>';
        openModal('m-fy-balance');
    });
    document.getElementById('fySaveBtn')?.addEventListener('click', function () {
        const fromYear = parseInt(document.getElementById('fyFromYear').value, 10);
        const newYear = parseInt(document.getElementById('fyNewYear').value, 10);
        const accountIds = Array.from(document.querySelectorAll('.fy-account-cb:checked')).map(cb => parseInt(cb.value, 10));
        if (!accountIds.length) { toast('Select at least one bank account.', true); return; }
        const btn = this;
        btn.disabled = true;
        fetch(REPORTS_API + '/fy-opening-balance', { method: 'POST', headers: authHeaders({ 'Content-Type': 'application/json' }), body: JSON.stringify({ from_year: fromYear, new_year: newYear, account_ids: accountIds }) })
            .then(r => r.json())
            .then(function (json) {
                toast(json.message || (json.success ? ('Created ' + json.data.created.length + ', skipped ' + json.data.skipped.length + '.') : 'Could not roll forward.'), !json.success);
                if (json.success) closeModal('m-fy-balance');
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { btn.disabled = false; });
    });

    // ── Boot ──
    initDashboard();
    initialized.dashboard = true;
})();
</script>
