<?php
/**
 * responsibility-center-form.php
 *
 * Add / Edit modal for fims.responsibility_code.
 *
 * Trigger:
 *   rc_openModal('add')         — new RC
 *   rc_openModal('edit', row)   — edit existing RC (rc_code locked)
 *
 * Self-contained styling (scoped under #rc-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     RESPONSIBILITY CENTER FORM MODAL   JS namespace: rc_
══════════════════════════════════════════════════════════ -->
<style>
    #rc-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #rc-overlay.open { display:flex; }
    #rc-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:640px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:rcIn .2s ease; }
    @keyframes rcIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #rc-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #rc-header-info { display:flex;align-items:center;gap:10px; }
    #rc-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .rc-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .rc-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #rc-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #rc-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #rc-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #rc-body { flex:1;overflow-y:auto;padding:22px; }
    .rc-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    .rc-group { display:flex;flex-direction:column;gap:5px; }
    .rc-group.full { margin-bottom:16px; }
    .rc-group:last-child { margin-bottom:0; }
    .rc-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .rc-label .req { color:var(--afims-danger, #DC3545); }
    .rc-input, .rc-select { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .rc-input:focus, .rc-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .rc-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .rc-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    .rc-check-row { display:flex;align-items:center;gap:8px; }
    .rc-check-row input[type="checkbox"] { width:16px;height:16px; }
    .rc-check-row label { font-size:13px;color:var(--afims-text, #1A1B21);margin:0; }

    #rc-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #rc-footer .rc-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #rc-footer .rc-btn:disabled { opacity:.5;cursor:not-allowed; }
    #rc-footer .rc-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #rc-footer .rc-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #rc-footer .rc-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #rc-footer .rc-btn-navy:hover:not(:disabled) { opacity:.92; }

    @media (max-width: 560px) { .rc-row { grid-template-columns:1fr; } }
</style>

<div id="rc-overlay">
    <div id="rc-card">

        <div id="rc-header">
            <div id="rc-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4m-4 6h.01M9 14h.01M9 17h.01"/>
                </svg>
                <div>
                    <p class="rc-hdr-tag">Responsibility Center</p>
                    <p class="rc-hdr-title" id="rc-modal-title">Add Responsibility Center</p>
                </div>
            </div>
            <button type="button" id="rc-modal-close">&times;</button>
        </div>

        <div id="rc-err"></div>

        <form id="rc-form">
            <div id="rc-body">
                <input type="hidden" id="rc-mode" value="add">
                <input type="hidden" id="rc-rc-id" value="">

                <div class="rc-row">
                    <div class="rc-group">
                        <label class="rc-label">RC Code <span class="req">*</span></label>
                        <input type="text" class="rc-input" id="rc-rc-code" required maxlength="30" placeholder="e.g. 35-074-0000000-010">
                        <p class="rc-hint" id="rc-code-hint">Cannot be changed after creation.</p>
                    </div>
                    <div class="rc-group">
                        <label class="rc-label">Office Type</label>
                        <select class="rc-select" id="rc-office-type">
                            <option value="">— Select type —</option>
                            <option value="Agency">Agency</option>
                            <option value="Group">Group</option>
                            <option value="Department">Department</option>
                            <option value="Regional">Regional</option>
                            <option value="District">District</option>
                        </select>
                    </div>
                </div>

                <div class="rc-group full">
                    <label class="rc-label">RC Name <span class="req">*</span></label>
                    <input type="text" class="rc-input" id="rc-rc-name" required maxlength="255" placeholder="e.g. Office of the General Manager">
                </div>

                <div class="rc-row">
                    <div class="rc-group">
                        <label class="rc-label">Parent RC</label>
                        <select class="rc-select" id="rc-parent-rc-id">
                            <option value="">— No parent (top-level) —</option>
                        </select>
                    </div>
                    <div class="rc-group">
                        <label class="rc-label">Source Document</label>
                        <input type="text" class="rc-input" id="rc-source-document" maxlength="255" placeholder="e.g. NHA RC Listing">
                    </div>
                </div>

                <div class="rc-group full">
                    <div class="rc-check-row">
                        <input type="checkbox" id="rc-is-budget-holding">
                        <label for="rc-is-budget-holding">Budget-Holding RC — this office can hold a budget allocation directly</label>
                    </div>
                </div>
            </div>
            <div id="rc-footer">
                <button type="button" class="rc-btn rc-btn-ghost" id="rc-cancel">Cancel</button>
                <button type="submit" class="rc-btn rc-btn-navy" id="rc-save">Save Responsibility Center</button>
            </div>
        </form>
    </div>
</div>
