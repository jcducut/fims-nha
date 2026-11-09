<?php
/**
 * sub-program-form.php
 *
 * Add / Edit modal for fims.sub_program.
 *
 * Trigger:
 *   sp_openModal('add')         — new batch: pick one Program, add multiple
 *                                 Sub-Program line items, saved together in
 *                                 one call (/sub_program/add-with-items)
 *   sp_openModal('edit', row)   — edit a single existing sub-program
 *                                 (sub_program_code locked)
 *
 * Self-contained styling (scoped under #sp-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 *
 * Line items are laid out as flex rows (not a <table>) with only two narrow
 * text inputs each, so the row always fits the modal width with no
 * horizontal scrolling — sidesteps the sticky/scroll-gap alignment issues
 * hit on the Budget Request line-items table.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     SUB-PROGRAM FORM MODAL   JS namespace: sp_
══════════════════════════════════════════════════════════ -->
<style>
    #sp-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #sp-overlay.open { display:flex; }
    #sp-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:680px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:spIn .2s ease; }
    @keyframes spIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #sp-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #sp-header-info { display:flex;align-items:center;gap:10px; }
    #sp-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .sp-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .sp-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #sp-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #sp-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #sp-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #sp-body { flex:1;overflow-y:auto;padding:22px; }
    .sp-group { display:flex;flex-direction:column;gap:5px;margin-bottom:16px; }
    .sp-group:last-child { margin-bottom:0; }
    .sp-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .sp-label .req { color:var(--afims-danger, #DC3545); }
    .sp-input, .sp-select { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .sp-input:focus, .sp-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .sp-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .sp-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    /* ── Line items (Add mode only) ────────────────────────── */
    #sp-li-section { margin-top:4px; }
    .sp-li-head { display:flex;align-items:center;justify-content:space-between;margin-bottom:8px; }
    .sp-li-title { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    #sp-li-add { display:flex;align-items:center;gap:6px;border:1px dashed var(--afims-heading-fill, #000E3E);
        background:var(--afims-surface, #FFFFFF);color:var(--afims-heading-fill, #000E3E);
        border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;
        font-family:'IBM Plex Sans',sans-serif;transition:background .15s; }
    #sp-li-add:hover { background:var(--afims-surface-subtle, #F4F7FA); }
    #sp-li-add svg { width:12px;height:12px; }

    .sp-li-cols { display:flex;gap:10px;padding:0 0 6px 0; }
    .sp-li-cols span { font-size:10px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.05em; }
    .sp-li-cols .sp-li-col-code { flex:0 0 160px; }
    .sp-li-cols .sp-li-col-name { flex:1 1 auto; }
    .sp-li-cols .sp-li-col-rm   { flex:0 0 28px; }

    #sp-li-rows { display:flex;flex-direction:column;gap:8px; }
    .sp-li-row { display:flex;gap:10px;align-items:center; }
    .sp-li-row .sp-li-code { flex:0 0 160px; }
    .sp-li-row .sp-li-name { flex:1 1 auto; }
    .sp-li-row .sp-li-rm {
        flex:0 0 28px;width:28px;height:34px;border:none;background:none;cursor:pointer;
        display:flex;align-items:center;justify-content:center;border-radius:6px;
        color:var(--afims-text-disabled, #A9AAB8);transition:background .15s,color .15s;
    }
    .sp-li-row .sp-li-rm:hover { background:var(--afims-danger-soft, #FAE1E3);color:var(--afims-danger, #DC3545); }
    .sp-li-row .sp-li-rm svg { width:14px;height:14px; }
    .sp-li-row .sp-li-rm:disabled { opacity:.3;cursor:not-allowed; }
    .sp-li-row .sp-li-rm:disabled:hover { background:none;color:var(--afims-text-disabled, #A9AAB8); }

    #sp-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #sp-footer .sp-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #sp-footer .sp-btn:disabled { opacity:.5;cursor:not-allowed; }
    #sp-footer .sp-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #sp-footer .sp-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #sp-footer .sp-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #sp-footer .sp-btn-navy:hover:not(:disabled) { opacity:.92; }

    @media (max-width: 560px) {
        .sp-li-row { flex-wrap:wrap; }
        .sp-li-row .sp-li-code, .sp-li-row .sp-li-name { flex:1 1 100%; }
        .sp-li-cols { display:none; }
    }
</style>

<div id="sp-overlay">
    <div id="sp-card">

        <div id="sp-header">
            <div id="sp-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div>
                    <p class="sp-hdr-tag">Sub-Program Master Data</p>
                    <p class="sp-hdr-title" id="sp-modal-title">Add Sub-Program</p>
                </div>
            </div>
            <button type="button" id="sp-modal-close">&times;</button>
        </div>

        <div id="sp-err"></div>

        <form id="sp-form">
            <div id="sp-body">
                <input type="hidden" id="sp-mode" value="add">

                <div class="sp-group">
                    <label class="sp-label">Program <span class="req">*</span></label>
                    <select class="sp-select" id="sp-program-code" required>
                        <option value="">— Select program —</option>
                    </select>
                </div>

                <!-- ── Edit mode: single sub-program fields ──────────── -->
                <div id="sp-edit-fields" style="display:none;">
                    <div class="sp-group">
                        <label class="sp-label">Sub-Program Code <span class="req">*</span></label>
                        <input type="text" class="sp-input" id="sp-sub-program-code" maxlength="20" placeholder="e.g. SUBPRG-001">
                        <p class="sp-hint">Cannot be changed after creation.</p>
                    </div>
                    <div class="sp-group">
                        <label class="sp-label">Sub-Program Name <span class="req">*</span></label>
                        <input type="text" class="sp-input" id="sp-sub-program-name" maxlength="255" placeholder="e.g. Core Shelter Assistance">
                    </div>
                </div>

                <!-- ── Add mode: repeatable line items ───────────────── -->
                <div id="sp-li-section">
                    <div class="sp-li-head">
                        <span class="sp-li-title">Sub-Programs</span>
                        <button type="button" id="sp-li-add">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                            Add Line Item
                        </button>
                    </div>
                    <div class="sp-li-cols">
                        <span class="sp-li-col-code">Code</span>
                        <span class="sp-li-col-name">Name</span>
                        <span class="sp-li-col-rm"></span>
                    </div>
                    <div id="sp-li-rows"></div>
                </div>
            </div>
            <div id="sp-footer">
                <button type="button" class="sp-btn sp-btn-ghost" id="sp-cancel">Cancel</button>
                <button type="submit" class="sp-btn sp-btn-navy" id="sp-save">Save Sub-Program</button>
            </div>
        </form>
    </div>
</div>
