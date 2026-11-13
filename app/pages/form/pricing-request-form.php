<?php
/**
 * pricing-request-form.php
 *
 * Create modal for fims.pricing_requests (Fund Sourcing Division —
 * "Pricing Request — New Entry"). Sections: Request, Office,
 * Routing & Turnaround, Remarks.
 *
 * Trigger:
 *   prq_openModal()          — new pricing request
 *   prq_openModal(row)       — fill from an existing row (keys = DB columns)
 *
 * Hooks the host page can call to fill the selects:
 *   prq_setProjects([{project_code, project_name}, ...])
 *   prq_setRegionalOffices([{office_id, office_name}, ...])
 *   prq_setDistrictOffices([{office_id, office_name, parent_office_id}, ...])
 *     — the district list shows only the offices under the selected regional office.
 *
 * Save is delegated to prq_save() (defined in the host page, pricing-page.php).
 *
 * Self-contained styling (scoped under #prq-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     PRICING REQUEST FORM MODAL   JS namespace: prq_
══════════════════════════════════════════════════════════ -->
<style>
    #prq-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #prq-overlay.open { display:flex; }
    #prq-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:860px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:prqIn .2s ease; }
    @keyframes prqIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #prq-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #prq-header-info { display:flex;align-items:center;gap:10px; }
    #prq-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .prq-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .prq-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #prq-modal-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #prq-modal-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #prq-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #prq-form { display:flex;flex-direction:column;min-height:0;flex:1; }
    #prq-body { flex:1;overflow-y:auto;padding:22px; }
    .prq-section { font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);text-transform:uppercase;
        letter-spacing:.06em;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .prq-section:not(:first-child) { margin-top:22px; }
    .prq-note { font-size:11px;color:var(--afims-text-muted, #62657E);margin:-4px 0 14px; }
    .prq-row { display:grid;gap:14px;margin-bottom:14px; }
    .prq-row.c2 { grid-template-columns:1fr 1fr; }
    .prq-row.c3 { grid-template-columns:1fr 1fr 1fr; }
    .prq-group { display:flex;flex-direction:column;gap:5px;min-width:0; }
    .prq-group.full { margin-bottom:14px; }
    .prq-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .prq-label .req { color:var(--afims-danger, #DC3545); }
    .prq-input { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .prq-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .prq-input[readonly] { background:var(--afims-surface-subtle, #F4F7FA);cursor:default; }
    .prq-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    textarea.prq-input { resize:vertical;min-height:120px; }
    .prq-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    .prq-reminders { margin-top:22px;padding:12px 16px;border-radius:10px;
        background:var(--afims-info-soft, #E7F0FD);border:1px solid var(--afims-border, #E4ECF2); }
    .prq-reminders p { margin:0 0 6px;font-size:12px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .prq-reminders ul { margin:0;padding-left:18px;font-size:12px;color:var(--afims-text-muted, #62657E); }
    .prq-reminders li + li { margin-top:3px; }

    #prq-footer { display:flex;align-items:center;justify-content:space-between;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #prq-footer .prq-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #prq-footer .prq-btn:disabled { opacity:.5;cursor:not-allowed; }
    #prq-footer .prq-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #prq-footer .prq-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #prq-footer .prq-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #prq-footer .prq-btn-navy:hover:not(:disabled) { opacity:.92; }

    /* ── Supporting Documents (same look as the Budget Request modal) ──── */
    .prq-docs-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 10px; }
    .prq-doc-tbl { width:100%;border-collapse:collapse;margin-bottom:10px; }
    .prq-doc-tbl th { height:30px;box-sizing:border-box;font-size:10px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.04em;text-align:left;padding:0 6px;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:2px solid var(--afims-border, #E4ECF2); }
    .prq-doc-tbl td { padding:6px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:middle; }
    .prq-doc-actions { display:flex;gap:8px; }
    .prq-btn-sm { height:34px;padding:0 16px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21);font-size:12px;font-weight:600;
        font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    .prq-btn-sm:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    .prq-doc-panel { display:flex;align-items:center;gap:6px;min-width:0; }
    .prq-doc-upload-btn { display:inline-flex;align-items:center;gap:4px;font-size:11px;font-weight:600;
        color:var(--afims-text, #1A1B21);background:var(--afims-surface-subtle, #F4F7FA);
        border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;padding:6px 10px;
        cursor:pointer;white-space:nowrap;flex-shrink:0; }
    .prq-doc-upload-btn:hover { background:var(--afims-surface, #FFFFFF); }
    .prq-doc-uploaded-name { font-size:11px;color:var(--afims-text-muted, #62657E);flex:1;min-width:0;
        overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .prq-doc-open { font-size:11.5px;color:var(--afims-heading-fill, #000E3E);text-decoration:none;
        white-space:nowrap;flex-shrink:0;font-weight:600; }
    .prq-doc-remove { border:none;background:transparent;color:var(--afims-danger, #DC3545);
        font-size:16px;line-height:1;cursor:pointer;width:24px;height:24px;border-radius:6px;flex-shrink:0; }
    .prq-doc-remove:hover { background:var(--afims-danger-soft, #FAE1E3); }

    @media (max-width:720px) { .prq-row.c2, .prq-row.c3 { grid-template-columns:1fr; } }
</style>

<div id="prq-overlay">
    <div id="prq-card">

        <div id="prq-header">
            <div id="prq-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <div>
                    <p class="prq-hdr-tag">Fund Sourcing Division</p>
                    <p class="prq-hdr-title" id="prq-modal-title">Pricing Request — New Entry</p>
                </div>
            </div>
            <button type="button" id="prq-modal-close">&times;</button>
        </div>

        <div id="prq-err"></div>

        <form id="prq-form" novalidate>
            <div id="prq-body">
                <input type="hidden" id="prq-id" value="">

                <!-- ── REQUEST ─────────────────────────────────── -->
                <p class="prq-section">Request</p>
                <div class="prq-row c2">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-project-code">Project Name <span class="req">*</span></label>
                        <select class="prq-input" id="prq-project-code">
                            <option value="">— Select project —</option>
                        </select>
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-type-of-request">Type of Request</label>
                        <select class="prq-input" id="prq-type-of-request">
                            <option value="">— Select type —</option>
                            <option value="New Pricing">New Pricing</option>
                            <option value="Adjustment">Adjustment</option>
                            <option value="Retention">Retention</option>
                            <option value="Amortization">Amortization</option>
                            <option value="Lease Rates">Lease Rates</option>
                        </select>
                    </div>
                </div>
                <div class="prq-row c3">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-status">Status</label>
                        <select class="prq-input" id="prq-status">
                            <option value="">— Select status —</option>
                            <option value="In Process / Pending">In Process / Pending</option>
                            <option value="For approval">For approval</option>
                            <option value="Approved">Approved</option>
                            <option value="Returned">Returned</option>
                        </select>
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-signed-by">Signed By</label>
                        <input type="text" class="prq-input" id="prq-signed-by" maxlength="150">
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-assigned-to">Assigned</label>
                        <input type="text" class="prq-input" id="prq-assigned-to" maxlength="150">
                    </div>
                </div>
                <div class="prq-row c3">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-unsold-inventories">Unsold Inventories</label>
                        <input type="number" class="prq-input" id="prq-unsold-inventories" min="0" step="0.01">
                    </div>
                </div>

                <!-- ── OFFICE ──────────────────────────────────── -->
                <p class="prq-section">Office</p>
                <div class="prq-row c2" style="margin-bottom:0;">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-regional-office-id">Regional Office</label>
                        <select class="prq-input" id="prq-regional-office-id">
                            <option value="">— none —</option>
                        </select>
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-district-office-id">District Office</label>
                        <select class="prq-input" id="prq-district-office-id">
                            <option value="">— none —</option>
                        </select>
                        <p class="prq-hint">Lists only the districts under the selected Regional Office</p>
                    </div>
                </div>

                <!-- ── ROUTING & TURNAROUND ────────────────────── -->
                <p class="prq-section">Routing &amp; Turnaround</p>
                <p class="prq-note">Day counts are working days (Mon–Fri, excluding holidays) and are calculated automatically from the dates: (c) = (a) to (b), (e) = (b) to (d), (g) = (d) to (f), (h) = (c) + (e) + (g).</p>
                <div class="prq-row c3">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-date-received">Date Received</label>
                        <input type="date" class="prq-input" id="prq-date-received">
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-date-received-complete-docs">Date Received of Complete Documents (a)</label>
                        <input type="date" class="prq-input" id="prq-date-received-complete-docs">
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-date-forwarded-fsg">Date Forwarded to FSG (b)</label>
                        <input type="date" class="prq-input" id="prq-date-forwarded-fsg">
                    </div>
                </div>
                <div class="prq-row c3">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-days-with-fmd">Number of Days with FMD (c)</label>
                        <input type="number" class="prq-input" id="prq-days-with-fmd" min="0" step="1" readonly>
                        <p class="prq-hint">(a) to (b)</p>
                    </div>
                </div>
                <div class="prq-row c3">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-date-forwarded-ogm">Date Forwarded to OGM (d)</label>
                        <input type="date" class="prq-input" id="prq-date-forwarded-ogm">
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-days-with-fsg">Number of Days with FSG (e)</label>
                        <input type="number" class="prq-input" id="prq-days-with-fsg" min="0" step="1" readonly>
                        <p class="prq-hint">(b) to (d)</p>
                    </div>
                    <div class="prq-group">
                        <label class="prq-label" for="prq-date-approved-ogm">Date Approved by OGM (f)</label>
                        <input type="date" class="prq-input" id="prq-date-approved-ogm">
                    </div>
                </div>
                <div class="prq-row c3">
                    <div class="prq-group">
                        <label class="prq-label" for="prq-days-with-ogm">Number of Days with OGM (g)</label>
                        <input type="number" class="prq-input" id="prq-days-with-ogm" min="0" step="1" readonly>
                        <p class="prq-hint">(d) to (f)</p>
                    </div>
                </div>
                <div class="prq-group full">
                    <label class="prq-label" for="prq-total-days">Total No. of Days (h)</label>
                    <input type="number" class="prq-input" id="prq-total-days" min="0" step="1" readonly>
                    <p class="prq-hint">(c) + (e) + (g)</p>
                </div>

                <!-- ── REMARKS ─────────────────────────────────── -->
                <p class="prq-section">Remarks</p>
                <div class="prq-group full" style="margin-bottom:0;">
                    <label class="prq-label" for="prq-remarks">Remarks (Pending) / Memorandum &amp; MC No.</label>
                    <textarea class="prq-input" id="prq-remarks" rows="5"></textarea>
                </div>

                <!-- ── SUPPORTING DOCUMENTS ────────────────────── -->
                <p class="prq-section">Supporting Documents</p>
                <p class="prq-docs-hint">Upload a file (≤20 MB — PDF, Word, Excel, or image) or paste a link per document.</p>
                <table class="prq-doc-tbl" id="prq-doc-tbl" style="display:none;">
                    <thead><tr>
                        <th style="width:35%;">Document Name</th>
                        <th>Attachment</th>
                        <th style="width:26px;"></th>
                    </tr></thead>
                    <tbody id="prq-doc-tbody"></tbody>
                </table>
                <div class="prq-doc-actions">
                    <button type="button" class="prq-btn-sm" id="prq-doc-add-upload">+ Upload File</button>
                    <button type="button" class="prq-btn-sm" id="prq-doc-add-link">+ Paste Link</button>
                </div>

                <div class="prq-reminders">
                    <p>Important Reminders</p>
                    <ul>
                        <li>Project Name is required; every other field is optional</li>
                        <li>A request cannot have the same Project Name, Type of Request and Date Received as another record</li>
                        <li>Regional and District Office are chosen from the official list; the district list follows the selected region</li>
                        <li>Days with FMD / FSG / OGM and the total are calculated from the dates (weekends skipped) and update when you change a date</li>
                    </ul>
                </div>
            </div>
            <div id="prq-footer">
                <button type="button" class="prq-btn prq-btn-ghost" id="prq-cancel">Cancel</button>
                <button type="submit" class="prq-btn prq-btn-navy" id="prq-save">Save Record</button>
            </div>
        </form>
    </div>
</div>
