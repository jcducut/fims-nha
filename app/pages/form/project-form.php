<?php
/**
 * project-form.php
 *
 * Add / Edit modal for fims.project.
 *
 * Trigger:
 *   proj_openModal('add')         — new project
 *   proj_openModal('edit', row)   — edit existing project (project_code locked)
 *
 * Self-contained styling (scoped under #proj-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     PROJECT FORM MODAL   JS namespace: proj_
══════════════════════════════════════════════════════════ -->
<style>
    #proj-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #proj-overlay.open { display:flex; }
    #proj-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:560px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:projIn .2s ease; }
    @keyframes projIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #proj-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #proj-header-info { display:flex;align-items:center;gap:10px; }
    #proj-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .proj-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .proj-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #proj-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #proj-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #proj-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #proj-body { flex:1;overflow-y:auto;padding:22px; }
    .proj-row { display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px; }
    .proj-group { display:flex;flex-direction:column;gap:5px; }
    .proj-group.full { margin-bottom:16px; }
    .proj-group:last-child { margin-bottom:0; }
    .proj-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .proj-label .req { color:var(--afims-danger, #DC3545); }
    .proj-input, .proj-select { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .proj-input:focus, .proj-select:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .proj-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .proj-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    #proj-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #proj-footer .proj-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #proj-footer .proj-btn:disabled { opacity:.5;cursor:not-allowed; }
    #proj-footer .proj-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #proj-footer .proj-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #proj-footer .proj-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #proj-footer .proj-btn-navy:hover:not(:disabled) { opacity:.92; }

    @media (max-width: 520px) { .proj-row { grid-template-columns:1fr; } }
</style>

<div id="proj-overlay">
    <div id="proj-card">

        <div id="proj-header">
            <div id="proj-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                </svg>
                <div>
                    <p class="proj-hdr-tag">Project Master Data</p>
                    <p class="proj-hdr-title" id="proj-modal-title">Add Project</p>
                </div>
            </div>
            <button type="button" id="proj-modal-close">&times;</button>
        </div>

        <div id="proj-err"></div>

        <form id="proj-form">
            <div id="proj-body">
                <input type="hidden" id="proj-mode" value="add">

                <div class="proj-row">
                    <div class="proj-group">
                        <label class="proj-label">Project Code</label>
                        <input type="text" class="proj-input" id="proj-project-code" maxlength="20" placeholder="Leave blank to auto-generate (e.g. PRJ-0001)">
                        <p class="proj-hint" id="proj-code-hint">Cannot be changed after creation.</p>
                    </div>
                    <div class="proj-group">
                        <label class="proj-label">Program <span class="req">*</span></label>
                        <select class="proj-select" id="proj-program-code" required>
                            <option value="">— Select program —</option>
                        </select>
                    </div>
                </div>
                <div class="proj-row">
                    <div class="proj-group">
                        <label class="proj-label">Sub-Program</label>
                        <select class="proj-select" id="proj-sub-program-code">
                            <option value="">— None —</option>
                        </select>
                    </div>
                </div>

                <div class="proj-group full">
                    <label class="proj-label">Project Name <span class="req">*</span></label>
                    <input type="text" class="proj-input" id="proj-project-name" required maxlength="255" placeholder="e.g. Core Shelter Assistance — Region IV-A">
                </div>
            </div>
            <div id="proj-footer">
                <button type="button" class="proj-btn proj-btn-ghost" id="proj-cancel">Cancel</button>
                <button type="submit" class="proj-btn proj-btn-navy" id="proj-save">Save Project</button>
            </div>
        </form>
    </div>
</div>
