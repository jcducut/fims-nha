<?php
/**
 * program-form.php
 *
 * Add / Edit modal for fims.program.
 *
 * Trigger:
 *   prg_openModal('add')         — new program
 *   prg_openModal('edit', row)   — edit existing program (program_code locked)
 *
 * Self-contained styling (scoped under #prg-card) so this modal renders
 * correctly even if the page's own stylesheet fails to load.
 *
 * It also carries its own API base (PRG_FORM_API) for the code preview, so it does not rely on
 * PRG_API from program-page.php. (Not named PRG_API on purpose: both scripts share one page, and a
 * second `const PRG_API` would be a redeclaration error.)
 */
// Included from program-page.php, which has already set $_prg_api; the fallback keeps this partial usable alone.
// (This file has no namespace of its own, hence the fully-qualified Config().)
$_prgf_api = $_prg_api ?? \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<!-- ══════════════════════════════════════════════════════════
     PROGRAM FORM MODAL   JS namespace: prg_
══════════════════════════════════════════════════════════ -->
<style>
    #prg-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #prg-overlay.open { display:flex; }
    #prg-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:520px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:prgIn .2s ease; }
    @keyframes prgIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #prg-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #prg-header-info { display:flex;align-items:center;gap:10px; }
    #prg-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .prg-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .prg-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #prg-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #prg-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #prg-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #prg-body { flex:1;overflow-y:auto;padding:22px; }
    .prg-group { display:flex;flex-direction:column;gap:5px;margin-bottom:16px; }
    .prg-group:last-child { margin-bottom:0; }
    .prg-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em; }
    .prg-label .req { color:var(--afims-danger, #DC3545); }
    .prg-input { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);transition:border-color .15s,box-shadow .15s;box-sizing:border-box; }
    .prg-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .prg-input:disabled { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text-disabled, #A9AAB8);cursor:not-allowed; }
    .prg-hint { font-size:11px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }
    .prg-code { font-family:'IBM Plex Mono',monospace;font-weight:600;letter-spacing:.02em; }
    .prg-input[readonly] { background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-primary-text, #0035A9);cursor:default; }
    .prg-input[readonly]:focus { border-color:var(--afims-border, #E4ECF2);box-shadow:none; }

    #prg-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #prg-footer .prg-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #prg-footer .prg-btn:disabled { opacity:.5;cursor:not-allowed; }
    #prg-footer .prg-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #prg-footer .prg-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #prg-footer .prg-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #prg-footer .prg-btn-navy:hover:not(:disabled) { opacity:.92; }
</style>

<div id="prg-overlay">
    <div id="prg-card">

        <div id="prg-header">
            <div id="prg-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <div>
                    <p class="prg-hdr-tag">Program Master Data</p>
                    <p class="prg-hdr-title" id="prg-modal-title">Add Program</p>
                </div>
            </div>
            <button type="button" id="prg-modal-close">&times;</button>
        </div>

        <div id="prg-err"></div>

        <form id="prg-form">
            <div id="prg-body">
                <input type="hidden" id="prg-mode" value="add">

                <div class="prg-group">
                    <label class="prg-label">Program Code <span class="req">*</span></label>
                    <!-- Add: read-only preview from GET /program/next-code (3-digit NNN, starting at 100); edit: locked. -->
                    <input type="text" class="prg-input prg-code" id="prg-program-code" maxlength="64" placeholder="Generating code…">
                    <p class="prg-hint" id="prg-code-hint">Auto-generated. Cannot be changed after creation.</p>
                </div>

                <div class="prg-group">
                    <label class="prg-label">Program Name <span class="req">*</span></label>
                    <input type="text" class="prg-input" id="prg-program-name" required maxlength="255" placeholder="e.g. Housing Finance Program">
                </div>
            </div>
            <div id="prg-footer">
                <button type="button" class="prg-btn prg-btn-ghost" id="prg-cancel">Cancel</button>
                <button type="submit" class="prg-btn prg-btn-navy" id="prg-save">Save Program</button>
            </div>
        </form>
    </div>
</div>

<script>
const PRG_FORM_API = '<?php echo $_prgf_api; ?>' + '/program';

function prg_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}

async function prg_loadCodePreview() {
    const input = document.getElementById('prg-program-code');
    const save  = document.getElementById('prg-save');
    input.value = '';
    input.placeholder = 'Generating code…';
    save.disabled = true;
    try {
        const res  = await fetch(`${PRG_FORM_API}/next-code`, { credentials: 'same-origin', headers: prg_authHeaders() });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Could not generate a program code.');
        if (document.getElementById('prg-mode').value !== 'add') return;   // modal was reopened for edit meanwhile
        input.value = json.data.program_code;

        save.disabled = false;
    } catch (e) {
        input.placeholder = 'Code unavailable';
        prg_showErr(e.message);
    }
}
</script>
