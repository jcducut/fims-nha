<?php
/**
 * chart-of-accounts-form.php
 *
 * Add / Edit modal for fims.chart_of_accounts.
 *
 * Trigger:
 *   coa_openModal('add')         — new account
 *   coa_openModal('edit', row)   — edit existing account (account_code locked)
 *
 * Self-contained styling (scoped under #coa-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     CHART OF ACCOUNTS FORM MODAL   JS namespace: coa_
══════════════════════════════════════════════════════════ -->
<style>
    #coa-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #coa-overlay.open { display:flex; }
    #coa-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:640px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:coaIn .2s ease; }
    @keyframes coaIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #coa-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #coa-header-info { display:flex;align-items:center;gap:10px; }
    #coa-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .coa-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .coa-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #coa-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #coa-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #coa-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #coa-body { flex:1;overflow-y:auto;padding:22px; }
    .coa-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    .coa-group { display:flex;flex-direction:column;gap:5px; }
    .coa-group.full { margin-bottom:16px; }
    .coa-group:last-child { margin-bottom:0; }
    .coa-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .coa-label .req { color:var(--afims-danger, #DC3545); }
    .coa-input, .coa-select { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .coa-input:focus, .coa-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .coa-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .coa-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    .coa-check-row { display:flex;align-items:center;gap:8px; }
    .coa-check-row input[type="checkbox"] { width:16px;height:16px; }
    .coa-check-row label { font-size:13px;color:var(--afims-text, #1A1B21);margin:0; }

    #coa-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #coa-footer .coa-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #coa-footer .coa-btn:disabled { opacity:.5;cursor:not-allowed; }
    #coa-footer .coa-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #coa-footer .coa-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #coa-footer .coa-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #coa-footer .coa-btn-navy:hover:not(:disabled) { opacity:.92; }

    @media (max-width: 560px) { .coa-row { grid-template-columns:1fr; } }
</style>

<div id="coa-overlay">
    <div id="coa-card">

        <div id="coa-header">
            <div id="coa-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m-6 4h6m-6 4h4M5 3h14a1 1 0 011 1v16a1 1 0 01-1 1H5a1 1 0 01-1-1V4a1 1 0 011-1z"/>
                </svg>
                <div>
                    <p class="coa-hdr-tag">Chart of Accounts</p>
                    <p class="coa-hdr-title" id="coa-modal-title">Add Account</p>
                </div>
            </div>
            <button type="button" id="coa-modal-close">&times;</button>
        </div>

        <div id="coa-err"></div>

        <form id="coa-form">
            <div id="coa-body">
                <input type="hidden" id="coa-mode" value="add">
                <input type="hidden" id="coa-account-id" value="">

                <div class="coa-row">
                    <div class="coa-group">
                        <label class="coa-label">Account Code <span class="req">*</span></label>
                        <input type="text" class="coa-input" id="coa-account-code" required maxlength="30" placeholder="e.g. 1-01-02-030">
                        <p class="coa-hint" id="coa-code-hint">Cannot be changed after creation.</p>
                    </div>
                    <div class="coa-group">
                        <label class="coa-label">Account Type</label>
                        <select class="coa-select" id="coa-account-type">
                            <option value="">— Select type —</option>
                            <option value="Asset">Asset</option>
                            <option value="Liability">Liability</option>
                            <option value="Equity">Equity</option>
                            <option value="Income">Income</option>
                            <option value="Expense">Expense</option>
                        </select>
                    </div>
                </div>

                <div class="coa-group full">
                    <label class="coa-label">Account Title <span class="req">*</span></label>
                    <input type="text" class="coa-input" id="coa-account-title" required maxlength="255" placeholder="e.g. Cash in Bank-Local Currency, Savings Account">
                </div>

                <div class="coa-row">
                    <div class="coa-group">
                        <label class="coa-label">Region (RCA Code)</label>
                        <select class="coa-select" id="coa-region-code">
                            <option value="">— None (national/HO) —</option>
                        </select>
                    </div>
                    <div class="coa-group">
                        <label class="coa-label">Project</label>
                        <select class="coa-select" id="coa-project-code">
                            <option value="">— None —</option>
                        </select>
                    </div>
                </div>

                <div class="coa-row">
                    <div class="coa-group">
                        <label class="coa-label">Parent Account</label>
                        <select class="coa-select" id="coa-parent-account-id">
                            <option value="">— No parent (top-level) —</option>
                        </select>
                    </div>
                    <div class="coa-group">
                        <label class="coa-label">Source Document</label>
                        <input type="text" class="coa-input" id="coa-source-document" maxlength="255" placeholder="e.g. UACS Manual 2020">
                    </div>
                </div>

                <div class="coa-group full">
                    <div class="coa-check-row">
                        <input type="checkbox" id="coa-posting-allowed">
                        <label for="coa-posting-allowed">Posting Allowed — transactions can post directly to this account</label>
                    </div>
                </div>
            </div>
            <div id="coa-footer">
                <button type="button" class="coa-btn coa-btn-ghost" id="coa-cancel">Cancel</button>
                <button type="submit" class="coa-btn coa-btn-navy" id="coa-save">Save Account</button>
            </div>
        </form>
    </div>
</div>
