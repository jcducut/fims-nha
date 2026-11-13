<?php
/**
 * Bank Account Management — Treasury module.
 *
 * Ported from the TREASURY reference project's
 * _assets/TREASURY/app/pages/bankaccmanagement.php, adapted to this
 * project's schema (see app/services/BankManagementService.php's docblock
 * for the field-level mapping: no shared.fund_sources lookup table here —
 * fund_source is plain text; region/office is always keyed by
 * Responsibility Center (fims.responsibility_code), not uac.departments;
 * documents are Google-Drive links stored in fims.bank_account_documents,
 * not file uploads).
 *
 * Unlike TREASURY's version (jQuery DataTables, server-side processing),
 * this page follows the same lightweight fetch + manual render + client
 * pagination convention already established in
 * check-disbursement-voucher-page.php, so the module doesn't pull in a new
 * jQuery/DataTables dependency.
 *
 * Markup/CSS classes (fims-page-header, fims-stat-grid, fims-table-panel,
 * fims-modal-overlay, etc.) match app/fims-css/fims.css.
 */

namespace PHPMaker2024\FIMS;

$bamCssVer = @filemtime($_SERVER['DOCUMENT_ROOT'] . '/app/fims-css/fims.css');
?>
<link rel="stylesheet" href="/app/fims-css/fims.css?v=<?= $bamCssVer ?>" />

<div class="fims-wrap" id="bamModule">

    <div class="fims-page-header">
        <div class="fims-page-header-bubbles"><span></span><span></span><span></span><span></span><span></span></div>
        <div class="fims-page-header-left">
            <div class="fims-page-header-icon"><i class="fas fa-landmark"></i></div>
            <h1>Bank Account Management</h1>
            <p>Register, update, and monitor all treasury bank accounts</p>
        </div>
        <div class="fims-page-header-right" style="display:flex;gap:8px;align-items:flex-start;">
            <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="bamDormancyBtn">
                <i class="fas fa-bell mr-1"></i>Dormancy Alerts
                <span class="fims-badge fims-badge-red" id="bamDormancyBadge" style="display:none;margin-left:6px;">0</span>
            </button>
            <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="bamRegisterBtn">
                <i class="fas fa-plus mr-1"></i>Register Account
            </button>
        </div>
    </div>

    <div class="fims-stat-grid" id="bamStats" style="grid-template-columns:repeat(5,1fr)">
        <div class="fims-stat-card fims-card-blue">
            <div class="fims-stat-top"><span class="fims-stat-label">Total Accounts</span><span class="fims-stat-icon"><i class="fas fa-university"></i></span></div>
            <div class="fims-stat-value" data-kpi="total">0</div>
        </div>
        <div class="fims-stat-card fims-card-green">
            <div class="fims-stat-top"><span class="fims-stat-label">Active</span><span class="fims-stat-icon"><i class="fas fa-check-circle"></i></span></div>
            <div class="fims-stat-value" data-kpi="active">0</div>
        </div>
        <div class="fims-stat-card fims-card-amber">
            <div class="fims-stat-top"><span class="fims-stat-label">Dormant</span><span class="fims-stat-icon"><i class="fas fa-clock"></i></span></div>
            <div class="fims-stat-value" data-kpi="dormant">0</div>
            <div class="fims-stat-sub">No transaction &ge; 1 year</div>
        </div>
        <div class="fims-stat-card fims-card-gray">
            <div class="fims-stat-top"><span class="fims-stat-label">Closed</span><span class="fims-stat-icon"><i class="fas fa-times-circle"></i></span></div>
            <div class="fims-stat-value" data-kpi="closed">0</div>
        </div>
        <div class="fims-stat-card fims-card-orange">
            <div class="fims-stat-top"><span class="fims-stat-label">Near Dormant</span><span class="fims-stat-icon"><i class="fas fa-exclamation-circle"></i></span></div>
            <div class="fims-stat-value" data-kpi="near_dormant">0</div>
            <div class="fims-stat-sub">Within 2-month warning window</div>
        </div>
    </div>

    <div class="fims-table-panel" id="bamDormancyPanel" style="display:none;">
        <div class="fims-panel-header">
            <div class="fims-panel-title"><i class="fas fa-bell mr-1"></i>Dormancy Alerts</div>
            <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="bamDormancyCloseBtn">&times;</button>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table">
                <thead><tr><th>Account Name</th><th>Account Number</th><th>Bank</th><th>Last Transaction</th><th class="text-right">Days Inactive</th></tr></thead>
                <tbody id="bamDormancyBody"><tr class="fims-empty"><td colspan="5">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:var(--fims-radius-lg) var(--fims-radius-lg) 0 0;border-bottom:none;margin-bottom:0">
        <div class="fims-toolbar">
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                <select class="fims-select fims-input-sm" id="bamFilterStatus" style="width:140px">
                    <option value="">All Statuses</option>
                    <option value="ACTIVE">Active</option>
                    <option value="DORMANT">Dormant</option>
                    <option value="CLOSED">Closed</option>
                </select>
                <select class="fims-select fims-input-sm" id="bamFilterClassification" style="width:190px">
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
                <select class="fims-select fims-input-sm" id="bamFilterFundSource" style="width:160px">
                    <option value="">All Fund Sources</option>
                </select>
                <input type="text" class="fims-input fims-input-sm" id="bamSearch" placeholder="Search name, number, bank…" style="width:220px">
                <button type="button" class="fims-btn fims-btn-primary fims-btn-sm" id="bamFilterBtn"><i class="fas fa-filter mr-1"></i>Filter</button>
                <button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" id="bamResetBtn">Reset</button>
            </div>
        </div>
    </div>

    <div class="fims-table-panel" style="border-radius:0">
        <div class="fims-panel-header">
            <div class="fims-panel-title" id="bamTableSubtitle">Bank Accounts</div>
        </div>
        <div class="fims-table-wrap">
            <table class="fims-table" id="bamTable">
                <thead>
                    <tr>
                        <th style="width:36px">#</th>
                        <th>Account Name</th>
                        <th>Account Number</th>
                        <th>Bank / Branch</th>
                        <th>Purpose</th>
                        <th>Fund Source</th>
                        <th>Region / Office</th>
                        <th class="text-center">Main</th>
                        <th class="text-center">Status</th>
                        <th>Date Opened</th>
                        <th>Last Transaction</th>
                        <th class="text-center" style="width:110px">Actions</th>
                    </tr>
                </thead>
                <tbody id="bamTableBody"><tr class="fims-empty"><td colspan="12">Loading…</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: Register / Edit Account
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-bam-account" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:760px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div><div class="fims-modal-title" id="bamModalTitle">Register Account</div></div>
                    <button class="fims-modal-close" data-close="m-bam-account">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <input type="hidden" id="field-bank-account-id">

                    <div class="bam-step-indicator" id="bamStepIndicator">
                        <div class="bam-step-pill active" data-step-pill="1"><span class="bam-step-num">1</span>Account Info</div>
                        <div class="bam-step-line"></div>
                        <div class="bam-step-pill" data-step-pill="2"><span class="bam-step-num">2</span>Bank Info</div>
                        <div class="bam-step-line"></div>
                        <div class="bam-step-pill" data-step-pill="3"><span class="bam-step-num">3</span>Documents</div>
                    </div>

                    <div class="bam-step" data-step="1">
                        <div class="fims-form-section-title">Account Information</div>
                        <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="fims-form-group">
                                <label class="fims-label">Account Name *</label>
                                <input type="text" class="fims-input" id="field-account-name">
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Account Number *</label>
                                <input type="text" class="fims-input" id="field-account-number">
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Account Type</label>
                                <select class="fims-select" id="field-account-type">
                                    <option value="">— Select —</option>
                                    <option value="CURRENT">Current</option>
                                    <option value="SAVINGS">Savings</option>
                                    <option value="NON_DRAWING_SAVINGS">Non-Drawing Savings</option>
                                    <option value="TIME_DEPOSIT">Time Deposit</option>
                                    <option value="TRUST">Trust</option>
                                </select>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Purpose *</label>
                                <select class="fims-select" id="field-account-classification">
                                    <option value="">— Select —</option>
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
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Fund Source</label>
                                <input type="text" class="fims-input" id="field-fund-source" placeholder="e.g. General Fund">
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Region / Office (Responsibility Center)</label>
                                <select class="fims-select" id="field-rc-id">
                                    <option value="">— Main Office —</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="bam-step" data-step="2" style="display:none;">
                        <div class="fims-form-section-title">Bank Information</div>
                        <div class="fims-form-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                            <div class="fims-form-group">
                                <label class="fims-label">Bank Name *</label>
                                <select class="fims-select" id="field-bank-name-select">
                                    <option value="">— Select Bank —</option>
                                    <option>Land Bank of the Philippines</option>
                                    <option>Development Bank of the Philippines</option>
                                    <option>Philippine National Bank</option>
                                    <option>Bangko Sentral ng Pilipinas</option>
                                    <option>Banco De Oro</option>
                                    <option>Metropolitan Bank and Trust Company</option>
                                    <option>Bank of the Philippine Islands</option>
                                    <option>Union Bank of the Philippines</option>
                                    <option>Philippine Veterans Bank</option>
                                    <option value="__other__">Other…</option>
                                </select>
                                <input type="text" class="fims-input mt-1" id="field-bank-name" placeholder="Bank name" style="display:none;">
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Bank Branch</label>
                                <input type="text" class="fims-input" id="field-bank-branch">
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Status *</label>
                                <select class="fims-select" id="field-status">
                                    <option value="ACTIVE">Active</option>
                                    <option value="DORMANT">Dormant</option>
                                    <option value="CLOSED">Closed</option>
                                </select>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">&nbsp;</label>
                                <label style="display:flex;align-items:center;gap:6px;height:38px;">
                                    <input type="checkbox" id="field-is-main-depository"> Main Depository
                                </label>
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Date Opened</label>
                                <input type="date" class="fims-input" id="field-date-opened">
                            </div>
                            <div class="fims-form-group">
                                <label class="fims-label">Date of Last Transaction</label>
                                <input type="date" class="fims-input" id="field-last-transaction-date">
                            </div>
                        </div>
                    </div>

                    <div class="bam-step" data-step="3" style="display:none;">
                        <div class="fims-form-section-title">Bank Documents</div>
                        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                            <div class="fims-form-group" style="flex:1;min-width:160px;">
                                <label class="fims-label">Link Name</label>
                                <input type="text" class="fims-input" id="field-doc-link-name" placeholder="e.g. Signature Card">
                            </div>
                            <div class="fims-form-group" style="flex:2;min-width:220px;">
                                <label class="fims-label">Google Drive URL</label>
                                <input type="text" class="fims-input" id="field-doc-link-url" placeholder="https://drive.google.com/…">
                            </div>
                            <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="bamAddLinkBtn" style="height:38px;">Add</button>
                        </div>
                        <ul id="bamModalDocList" style="list-style:none;padding:0;margin:10px 0 0;"></ul>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-bam-account">Cancel</button>
                    <button type="button" class="fims-btn fims-btn-outline" id="bamBackBtn" style="display:none;">Back</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="bamNextBtn">Next</button>
                    <button type="button" class="fims-btn fims-btn-primary" id="bamSaveBtn" style="display:none;">Save Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════
         MODAL: View Documents
    ══════════════════════════════════════════════ -->
    <div class="fims-modal-overlay" id="m-bam-docs" style="display:none;">
        <div class="fims-modal-dialog" style="max-width:560px;width:95vw;">
            <div class="fims-modal-content">
                <div class="fims-modal-header">
                    <div>
                        <div class="fims-modal-title">Bank Documents</div>
                        <div style="font-size:12px;color:var(--fims-text-subtle)" id="bamDocsAccountName"></div>
                    </div>
                    <button class="fims-modal-close" data-close="m-bam-docs">&times;</button>
                </div>
                <div class="fims-modal-body">
                    <ul id="bamDocsList" style="list-style:none;padding:0;margin:0 0 14px;"></ul>
                    <div class="fims-form-section-title">Add Google Drive Link</div>
                    <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                        <div class="fims-form-group" style="flex:1;min-width:140px;">
                            <label class="fims-label">Link Name</label>
                            <input type="text" class="fims-input" id="bamDocsLinkName">
                        </div>
                        <div class="fims-form-group" style="flex:2;min-width:200px;">
                            <label class="fims-label">Google Drive URL</label>
                            <input type="text" class="fims-input" id="bamDocsLinkUrl">
                        </div>
                        <button type="button" class="fims-btn fims-btn-outline fims-btn-sm" id="bamDocsAddBtn" style="height:38px;">Add</button>
                    </div>
                </div>
                <div class="fims-modal-footer">
                    <button type="button" class="fims-btn fims-btn-ghost" data-close="m-bam-docs">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div id="bamToast" style="display:none;position:fixed;bottom:20px;right:20px;padding:12px 18px;border-radius:8px;color:#fff;font-size:14px;z-index:9999;"></div>
</div>

<style>
/* Dark-blue theme, scoped to this module only — same convention used by
   #cpModule in check-disbursement-voucher-page.php, so every treasury-ish
   module in this app shares one accent instead of the shared rainbow
   .fims-card-* palette. */
#bamModule .fims-card-blue,
#bamModule .fims-card-green,
#bamModule .fims-card-amber,
#bamModule .fims-card-orange,
#bamModule .fims-card-gray { --fims-card-color: #000E3E; }

#bamModule .fims-btn-primary { background:#000E3E; color:#fff; }
#bamModule .fims-btn-primary:hover { background:#000a2e; color:#fff; }
#bamModule .fims-btn-outline { color:#000E3E; border-color:#000E3E; }
#bamModule .fims-btn-outline:hover { background:#000E3E; color:#fff; }

.fims-form-section-title { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.04em; color:var(--fims-text-subtle); margin-bottom:8px; }
.bam-doc-row { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:8px 10px; border:1px solid var(--fims-border); border-radius:6px; margin-bottom:6px; font-size:13px; }
.bam-doc-row a { color: #000E3E; text-decoration: underline; }

/* Register/Edit Account step indicator */
.bam-step-indicator { display:flex; align-items:center; margin-bottom:18px; }
.bam-step-pill { display:flex; align-items:center; gap:6px; font-size:12px; font-weight:600; color:var(--fims-text-subtle); white-space:nowrap; }
.bam-step-pill .bam-step-num { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; border-radius:50%; background:var(--fims-border); color:var(--fims-text-subtle); font-size:12px; }
.bam-step-pill.active { color:#000E3E; }
.bam-step-pill.active .bam-step-num { background:#000E3E; color:#fff; }
.bam-step-pill.done .bam-step-num { background:#000E3E; color:#fff; }
.bam-step-line { flex:1; height:2px; background:var(--fims-border); margin:0 10px; }
</style>

<script>
(function () {
    const API = '/api/bank-management';
    const JWT = (typeof ew !== 'undefined' && ew.API_JWT_TOKEN) ? ew.API_JWT_TOKEN : '';

    function authHeaders(extra) {
        return Object.assign({ 'X-Requested-With': 'XMLHttpRequest', 'X-Authorization': 'Bearer ' + JWT }, extra || {});
    }
    function jsonHeaders(extra) {
        return authHeaders(Object.assign({ 'Content-Type': 'application/json' }, extra || {}));
    }

    function toast(msg, isError) {
        const el = document.getElementById('bamToast');
        if (!el) return;
        el.textContent = msg;
        el.style.background = isError ? '#dc2626' : '#16a34a';
        el.style.display = 'block';
        setTimeout(() => { el.style.display = 'none'; }, 3500);
    }

    function escapeHtml(str) {
        return String(str ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

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
    document.querySelectorAll('#bamModule .fims-modal-overlay').forEach(function (overlay) {
        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) overlay.style.display = 'none';
        });
    });

    const CLASS_LABELS = {
        COLLECTION: 'Collection', DISBURSEMENT: 'Disbursement', COE_MOOE: 'COE / MOOE',
        CAPITAL_OUTLAY: 'Capital Outlay', EHAP: 'EHAP', SPECIAL_PURPOSE: 'Special Purpose',
        PROJECT: 'Project', JOINT_VENTURE: 'Joint Venture', TRUST: 'Trust',
    };
    function classLabel(c) { return CLASS_LABELS[c] || (c || '—'); }

    function statusBadge(status) {
        if (status === 'ACTIVE') return '<span class="fims-badge fims-badge-green">Active</span>';
        if (status === 'DORMANT') return '<span class="fims-badge fims-badge-amber">Dormant</span>';
        if (status === 'CLOSED') return '<span class="fims-badge fims-badge-gray">Closed</span>';
        return '<span class="fims-badge fims-badge-gray">' + escapeHtml(status || '—') + '</span>';
    }

    let pendingLinks = []; // {link_name, gdrive_link} added before the account itself is saved
    let existingDocs = []; // documents already saved against the account being edited ({document_id, link_name, gdrive_link})
    let responsibilityCentersById = {};

    // ── Stats ──
    function loadStats() {
        fetch(API + '/stats', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const d = (json && json.data) ? json.data : {};
                document.querySelectorAll('#bamStats [data-kpi]').forEach(function (el) {
                    el.textContent = d[el.dataset.kpi] ?? 0;
                });
                const badge = document.getElementById('bamDormancyBadge');
                if (d.near_dormant > 0) {
                    badge.style.display = '';
                    badge.textContent = d.near_dormant;
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(() => {});
    }

    // ── Dormancy panel ──
    document.getElementById('bamDormancyBtn').addEventListener('click', function () {
        const panel = document.getElementById('bamDormancyPanel');
        const show = panel.style.display === 'none';
        panel.style.display = show ? '' : 'none';
        if (show) loadDormancyAlerts();
    });
    document.getElementById('bamDormancyCloseBtn').addEventListener('click', function () {
        document.getElementById('bamDormancyPanel').style.display = 'none';
    });

    function loadDormancyAlerts() {
        const tbody = document.getElementById('bamDormancyBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">Loading…</td></tr>';
        fetch(API + '/dormancy-alerts', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">No accounts within the warning window.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r) {
                    return '<tr>' +
                        '<td>' + escapeHtml(r.account_name) + '</td>' +
                        '<td>' + escapeHtml(r.account_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.bank_name || '') + '</td>' +
                        '<td>' + escapeHtml(r.last_transaction_date || '') + '</td>' +
                        '<td class="text-right">' + escapeHtml(r.days_inactive) + '</td>' +
                        '</tr>';
                }).join('');
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="5">Could not reach the server.</td></tr>'; });
    }

    // ── Lookups ──
    function loadResponsibilityCenters() {
        fetch(API + '/lookup/responsibility-centers', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const sel = document.getElementById('field-rc-id');
                sel.innerHTML = '<option value="">— Main Office —</option>';
                rows.forEach(function (rc) {
                    responsibilityCentersById[rc.rc_id] = rc;
                    const opt = document.createElement('option');
                    opt.value = rc.rc_id;
                    opt.textContent = rc.rc_code + ' — ' + rc.rc_name;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    function loadFundSources() {
        fetch(API + '/lookup/fund-sources', { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                const sel = document.getElementById('bamFilterFundSource');
                sel.innerHTML = '<option value="">All Fund Sources</option>';
                rows.forEach(function (fs) {
                    const opt = document.createElement('option');
                    opt.value = fs;
                    opt.textContent = fs;
                    sel.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    // ── Bank name select <-> free-text sync ──
    const bankSelect = document.getElementById('field-bank-name-select');
    const bankInput = document.getElementById('field-bank-name');
    bankSelect.addEventListener('change', function () {
        if (this.value === '__other__') {
            bankInput.style.display = '';
            bankInput.value = '';
            bankInput.focus();
        } else {
            bankInput.style.display = 'none';
            bankInput.value = this.value;
        }
    });

    // ── Table ──
    function buildListParams() {
        const params = new URLSearchParams();
        const status = document.getElementById('bamFilterStatus').value;
        const classification = document.getElementById('bamFilterClassification').value;
        const fundSource = document.getElementById('bamFilterFundSource').value;
        const search = document.getElementById('bamSearch').value.trim();
        if (status) params.set('status', status);
        if (classification) params.set('classification', classification);
        if (fundSource) params.set('fund_source', fundSource);
        if (search) params.set('search', search);
        return params;
    }

    function loadList() {
        const tbody = document.getElementById('bamTableBody');
        tbody.innerHTML = '<tr class="fims-empty"><td colspan="12">Loading…</td></tr>';
        fetch(API + '/list?' + buildListParams().toString(), { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                const rows = (json && json.data) ? json.data : [];
                document.getElementById('bamTableSubtitle').textContent = 'Bank Accounts (' + rows.length + ')';
                if (!rows.length) {
                    tbody.innerHTML = '<tr class="fims-empty"><td colspan="12">No records.</td></tr>';
                    return;
                }
                tbody.innerHTML = rows.map(function (r, i) {
                    const regionLabel = r.rc_code
                        ? escapeHtml(r.rc_code + ' — ' + r.rc_name)
                        : 'Main Office';
                    return '<tr>' +
                        '<td>' + (i + 1) + '</td>' +
                        '<td>' + escapeHtml(r.account_name) + (r.is_main_depository ? ' <span class="fims-badge fims-badge-blue">Main Depository</span>' : '') + '</td>' +
                        '<td class="fims-col-mono">' + escapeHtml(r.account_number || '') + '</td>' +
                        '<td>' + escapeHtml(r.bank_name || '') + (r.bank_branch ? '<br><span style="font-size:11px;color:var(--fims-text-subtle)">' + escapeHtml(r.bank_branch) + '</span>' : '') + '</td>' +
                        '<td>' + classLabel(r.account_classification) + '</td>' +
                        '<td>' + escapeHtml(r.fund_source || '—') + '</td>' +
                        '<td>' + regionLabel + '</td>' +
                        '<td class="text-center">' + (r.is_main_depository ? '<i class="fas fa-check" style="color:#000E3E"></i>' : '—') + '</td>' +
                        '<td class="text-center">' + statusBadge(r.status) + '</td>' +
                        '<td>' + escapeHtml(r.date_opened || '—') + '</td>' +
                        '<td>' + escapeHtml(r.last_transaction_date || '—') + '</td>' +
                        '<td class="text-center">' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-edit="' + r.bank_account_id + '" title="Edit"><i class="fas fa-pen"></i></button> ' +
                            '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-docs="' + r.bank_account_id + '" data-docs-name="' + escapeHtml(r.account_name) + '" title="Documents"><i class="fas fa-file-alt"></i></button>' +
                        '</td>' +
                        '</tr>';
                }).join('');
                tbody.querySelectorAll('[data-edit]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openAccountModal(this.dataset.edit); });
                });
                tbody.querySelectorAll('[data-docs]').forEach(function (btn) {
                    btn.addEventListener('click', function () { openDocsModal(this.dataset.docs, this.dataset.docsName); });
                });
            })
            .catch(() => { tbody.innerHTML = '<tr class="fims-empty"><td colspan="12">Could not reach the server.</td></tr>'; });
    }

    document.getElementById('bamFilterBtn').addEventListener('click', loadList);
    document.getElementById('bamResetBtn').addEventListener('click', function () {
        document.getElementById('bamFilterStatus').value = '';
        document.getElementById('bamFilterClassification').value = '';
        document.getElementById('bamFilterFundSource').value = '';
        document.getElementById('bamSearch').value = '';
        loadList();
    });
    document.getElementById('bamSearch').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') loadList();
    });

    // ── Register/Edit modal ──
    function clearAccountModal() {
        document.getElementById('field-bank-account-id').value = '';
        document.getElementById('field-account-name').value = '';
        document.getElementById('field-account-number').value = '';
        document.getElementById('field-account-type').value = '';
        document.getElementById('field-account-classification').value = '';
        document.getElementById('field-fund-source').value = '';
        document.getElementById('field-rc-id').value = '';
        bankSelect.value = '';
        bankInput.value = '';
        bankInput.style.display = 'none';
        document.getElementById('field-bank-branch').value = '';
        document.getElementById('field-status').value = 'ACTIVE';
        document.getElementById('field-is-main-depository').checked = false;
        document.getElementById('field-date-opened').value = '';
        document.getElementById('field-last-transaction-date').value = '';
        document.getElementById('field-doc-link-name').value = '';
        document.getElementById('field-doc-link-url').value = '';
        pendingLinks = [];
        existingDocs = [];
        renderModalDocs();
        goToStep(1);
    }

    // ── 3-step wizard (Account Info -> Bank Info -> Documents) ──
    const BAM_TOTAL_STEPS = 3;
    let bamCurrentStep = 1;

    function validateStep(step) {
        if (step === 1) {
            if (!document.getElementById('field-account-name').value.trim()) return 'Account Name is required.';
            if (!document.getElementById('field-account-number').value.trim()) return 'Account Number is required.';
            if (!document.getElementById('field-account-classification').value) return 'Purpose is required.';
        } else if (step === 2) {
            if (!getBankNameValue()) return 'Bank Name is required.';
        }
        return null;
    }

    function goToStep(step) {
        bamCurrentStep = step;
        document.querySelectorAll('#m-bam-account .bam-step').forEach(function (el) {
            el.style.display = (parseInt(el.dataset.step, 10) === step) ? '' : 'none';
        });
        document.querySelectorAll('#bamStepIndicator .bam-step-pill').forEach(function (pill) {
            const n = parseInt(pill.dataset.stepPill, 10);
            pill.classList.toggle('active', n === step);
            pill.classList.toggle('done', n < step);
        });
        document.getElementById('bamBackBtn').style.display = step > 1 ? '' : 'none';
        document.getElementById('bamNextBtn').style.display = step < BAM_TOTAL_STEPS ? '' : 'none';
        document.getElementById('bamSaveBtn').style.display = step === BAM_TOTAL_STEPS ? '' : 'none';
    }

    document.getElementById('bamNextBtn').addEventListener('click', function () {
        const err = validateStep(bamCurrentStep);
        if (err) { toast(err, true); return; }
        goToStep(Math.min(bamCurrentStep + 1, BAM_TOTAL_STEPS));
    });
    document.getElementById('bamBackBtn').addEventListener('click', function () {
        goToStep(Math.max(bamCurrentStep - 1, 1));
    });
    document.querySelectorAll('#bamStepIndicator .bam-step-pill').forEach(function (pill) {
        pill.style.cursor = 'pointer';
        pill.addEventListener('click', function () {
            const target = parseInt(this.dataset.stepPill, 10);
            if (target <= bamCurrentStep) { goToStep(target); return; }
            for (let s = bamCurrentStep; s < target; s++) {
                const err = validateStep(s);
                if (err) { toast(err, true); return; }
            }
            goToStep(target);
        });
    });

    function setBankNameValue(name) {
        const match = Array.from(bankSelect.options).find(o => o.value === name);
        if (match) {
            bankSelect.value = name;
            bankInput.style.display = 'none';
            bankInput.value = name;
        } else if (name) {
            bankSelect.value = '__other__';
            bankInput.style.display = '';
            bankInput.value = name;
        }
    }

    function getBankNameValue() {
        return bankSelect.value === '__other__' ? bankInput.value.trim() : bankSelect.value;
    }

    // Renders both already-saved documents (existingDocs — fetched when
    // editing, removed immediately via the real API) and not-yet-saved
    // links (pendingLinks — only uploaded once the account itself is saved,
    // since a brand-new account has no bank_account_id yet to attach to).
    function renderModalDocs() {
        const ul = document.getElementById('bamModalDocList');
        const savedRows = existingDocs.map(function (d) {
            return '<li class="bam-doc-row">' +
                '<a href="' + escapeHtml(d.gdrive_link) + '" target="_blank" rel="noopener"><i class="fas fa-external-link-alt mr-1"></i>' + escapeHtml(d.link_name) + '</a>' +
                '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-existing="' + d.document_id + '"><i class="fas fa-trash"></i></button></li>';
        });
        const pendingRows = pendingLinks.map(function (l, idx) {
            return '<li class="bam-doc-row"><span><i class="fas fa-link mr-1"></i>' + escapeHtml(l.link_name) + ' <em style="color:var(--fims-text-subtle)">(unsaved)</em></span>' +
                '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-pending="' + idx + '"><i class="fas fa-trash"></i></button></li>';
        });
        const rows = savedRows.concat(pendingRows);
        ul.innerHTML = rows.length ? rows.join('') : '<li style="color:var(--fims-text-subtle);font-size:13px;">No documents yet.</li>';
        ul.querySelectorAll('[data-remove-pending]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                pendingLinks.splice(parseInt(this.dataset.removePending, 10), 1);
                renderModalDocs();
            });
        });
        ul.querySelectorAll('[data-remove-existing]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const documentId = this.dataset.removeExisting;
                const accountId = document.getElementById('field-bank-account-id').value;
                fetch(API + '/' + accountId + '/documents/remove', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ document_id: documentId }) })
                    .then(r => r.json())
                    .then(function (json) {
                        if (!json || !json.success) { toast((json && json.message) || 'Could not remove link.', true); return; }
                        existingDocs = existingDocs.filter(d => String(d.document_id) !== String(documentId));
                        renderModalDocs();
                    })
                    .catch(() => toast('Could not reach the server.', true));
            });
        });
    }

    document.getElementById('bamAddLinkBtn').addEventListener('click', function () {
        const name = document.getElementById('field-doc-link-name').value.trim();
        const url = document.getElementById('field-doc-link-url').value.trim();
        if (!name || !url) { toast('Link name and URL are required.', true); return; }
        pendingLinks.push({ link_name: name, gdrive_link: url });
        document.getElementById('field-doc-link-name').value = '';
        document.getElementById('field-doc-link-url').value = '';
        renderModalDocs();
    });

    function openAccountModal(id) {
        clearAccountModal();
        if (!id) {
            document.getElementById('bamModalTitle').textContent = 'Register Account';
            openModal('m-bam-account');
            return;
        }
        document.getElementById('bamModalTitle').textContent = 'Edit Account';
        fetch(API + '/' + id, { headers: authHeaders() })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not load account.', true); return; }
                const d = json.data;
                document.getElementById('field-bank-account-id').value = d.bank_account_id;
                document.getElementById('field-account-name').value = d.account_name || '';
                document.getElementById('field-account-number').value = d.account_number || '';
                document.getElementById('field-account-type').value = d.account_type || '';
                document.getElementById('field-account-classification').value = d.account_classification || '';
                document.getElementById('field-fund-source').value = d.fund_source || '';
                document.getElementById('field-rc-id').value = d.rc_id || '';
                setBankNameValue(d.bank_name || '');
                document.getElementById('field-bank-branch').value = d.bank_branch || '';
                document.getElementById('field-status').value = d.status || 'ACTIVE';
                document.getElementById('field-is-main-depository').checked = !!d.is_main_depository;
                document.getElementById('field-date-opened').value = d.date_opened || '';
                document.getElementById('field-last-transaction-date').value = d.last_transaction_date || '';
                existingDocs = d.documents || [];
                renderModalDocs();
                openModal('m-bam-account');
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    document.getElementById('bamRegisterBtn').addEventListener('click', function () { openAccountModal(null); });

    function validateAccountModal() {
        return validateStep(1) || validateStep(2);
    }

    document.getElementById('bamSaveBtn').addEventListener('click', function () {
        const err = validateAccountModal();
        if (err) { toast(err, true); return; }

        const id = document.getElementById('field-bank-account-id').value;
        const payload = {
            account_name: document.getElementById('field-account-name').value.trim(),
            account_number: document.getElementById('field-account-number').value.trim(),
            account_type: document.getElementById('field-account-type').value,
            account_classification: document.getElementById('field-account-classification').value,
            fund_source: document.getElementById('field-fund-source').value.trim(),
            rc_id: document.getElementById('field-rc-id').value,
            bank_name: getBankNameValue(),
            bank_branch: document.getElementById('field-bank-branch').value.trim(),
            status: document.getElementById('field-status').value,
            is_main_depository: document.getElementById('field-is-main-depository').checked,
            date_opened: document.getElementById('field-date-opened').value,
            last_transaction_date: document.getElementById('field-last-transaction-date').value,
        };
        const url = id ? (API + '/' + id + '/update') : (API + '/store');
        this.disabled = true;
        fetch(url, { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(payload) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Save failed.', true); return; }
                const savedId = id || json.data.bank_account_id;
                const linkCalls = pendingLinks.map(function (l) {
                    return fetch(API + '/' + savedId + '/documents/link', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify(l) });
                });
                Promise.all(linkCalls).finally(function () {
                    toast('Bank account saved.');
                    closeModal('m-bam-account');
                    loadList();
                    loadStats();
                    loadFundSources();
                });
            })
            .catch(() => toast('Could not reach the server.', true))
            .finally(() => { document.getElementById('bamSaveBtn').disabled = false; });
    });

    // ── Documents modal ──
    let docsAccountId = null;
    function openDocsModal(id, name) {
        docsAccountId = id;
        document.getElementById('bamDocsAccountName').textContent = name || '';
        document.getElementById('bamDocsLinkName').value = '';
        document.getElementById('bamDocsLinkUrl').value = '';
        loadDocsList();
        openModal('m-bam-docs');
    }

    function renderDocsList(rows) {
        const ul = document.getElementById('bamDocsList');
        if (!rows.length) {
            ul.innerHTML = '<li style="color:var(--fims-text-subtle);font-size:13px;">No documents yet.</li>';
            return;
        }
        ul.innerHTML = rows.map(function (d) {
            return '<li class="bam-doc-row">' +
                '<a href="' + escapeHtml(d.gdrive_link) + '" target="_blank" rel="noopener"><i class="fas fa-external-link-alt mr-1"></i>' + escapeHtml(d.link_name) + '</a>' +
                '<button type="button" class="fims-btn fims-btn-ghost fims-btn-sm" data-remove-doc="' + d.document_id + '"><i class="fas fa-trash"></i></button></li>';
        }).join('');
        ul.querySelectorAll('[data-remove-doc]').forEach(function (btn) {
            btn.addEventListener('click', function () { removeDoc(this.dataset.removeDoc); });
        });
    }

    function loadDocsList() {
        fetch(API + '/' + docsAccountId + '/documents', { headers: authHeaders() })
            .then(r => r.json())
            .then(json => renderDocsList((json && json.data) || []))
            .catch(() => { document.getElementById('bamDocsList').innerHTML = '<li>Could not reach the server.</li>'; });
    }

    document.getElementById('bamDocsAddBtn').addEventListener('click', function () {
        const name = document.getElementById('bamDocsLinkName').value.trim();
        const url = document.getElementById('bamDocsLinkUrl').value.trim();
        if (!name || !url) { toast('Link name and URL are required.', true); return; }
        fetch(API + '/' + docsAccountId + '/documents/link', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ link_name: name, gdrive_link: url }) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not add link.', true); return; }
                document.getElementById('bamDocsLinkName').value = '';
                document.getElementById('bamDocsLinkUrl').value = '';
                loadDocsList();
            })
            .catch(() => toast('Could not reach the server.', true));
    });

    function removeDoc(documentId) {
        fetch(API + '/' + docsAccountId + '/documents/remove', { method: 'POST', headers: jsonHeaders(), body: JSON.stringify({ document_id: documentId }) })
            .then(r => r.json())
            .then(function (json) {
                if (!json || !json.success) { toast((json && json.message) || 'Could not remove link.', true); return; }
                loadDocsList();
            })
            .catch(() => toast('Could not reach the server.', true));
    }

    // ── Boot ──
    loadStats();
    loadList();
    loadResponsibilityCenters();
    loadFundSources();
})();
</script>
