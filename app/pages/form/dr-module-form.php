<?php
/**
 * dr-module-form.php
 *
 * Add / Edit modal + read-only "view a DR" modal for the DR (Delivery Receipt) Module:
 * 1 PO number → many delivery receipt lines (fims.po_delivery_receipt).
 *
 * Trigger:
 *   amp_openModal('add')                — new PO with one blank line
 *   amp_openModal('edit', poWithLines)  — edit a PO and its lines
 *
 * Included from iar-module.php (which defines the amp_* list/save logic).
 */
$_ampf_api = $_amp_api ?? \PHPMaker2024\FIMS\Config('API.FIMS_URL');
?>

<style>
    /* list page: stacked DR lines + clickable DR number */
    .amp-line { min-height:30px;display:flex;align-items:center; }
    .amp-iar-link { font-weight:700;color:var(--afims-primary-text, #0035A9);text-decoration:underline;cursor:pointer; }
    .amp-iar-link:hover { opacity:.8; }

    #amp-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:flex-start;justify-content:center;padding:16px;backdrop-filter:blur(3px);overflow-y:auto; }
    #amp-overlay.open { display:flex; }
    #amp-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:1500px;
        max-height:calc(100vh - 32px);min-height:60vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;animation:ampIn .2s ease; }
    @keyframes ampIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #amp-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    .amp-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .amp-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0; }
    #amp-modal-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1; }
    #amp-modal-close:hover { color:var(--afims-pure-white, #FFFFFF); }

    #amp-err { display:none;margin:18px 22px -4px;background:var(--afims-danger-soft, #FAE1E3);
        border:1px solid var(--afims-danger-soft, #FAE1E3);border-radius:8px;padding:10px 14px;
        font-size:12px;color:var(--afims-danger, #DC3545); }

    #amp-form { display:flex;flex-direction:column;min-height:0;flex:1; }
    #amp-body { flex:1;overflow-y:auto;padding:28px 32px; }
    .amp-group { display:flex;flex-direction:column;gap:5px;margin-bottom:14px; }
    .amp-label { font-size:11px;font-weight:700;color:var(--afims-text, #1A1B21);text-transform:uppercase;letter-spacing:.05em; }
    .amp-label .req { color:var(--afims-danger, #DC3545); }
    .amp-label .opt { font-weight:500;text-transform:none;color:var(--afims-text-disabled, #A9AAB8);letter-spacing:0; }
    .amp-input { width:100%;padding:9px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;
        font-size:13px;color:var(--afims-text, #1A1B21);font-family:'IBM Plex Sans',sans-serif;
        background:var(--afims-surface, #FFFFFF);box-sizing:border-box; }
    .amp-input:focus { outline:none;border-color:var(--afims-heading-fill, #000E3E);box-shadow:0 0 0 3px rgba(15,32,69,.08); }
    .amp-input[readonly] { background:var(--afims-surface-subtle, #F4F7FA); }
    textarea.amp-input { resize:vertical;min-height:64px; }
    .amp-row { display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px; }
    .amp-group { min-width:0; }
    @media (max-width:760px) { .amp-row, .amp-row[style] { grid-template-columns:minmax(0,1fr) minmax(0,1fr) !important; } }

    /* searchable category dropdown */
    .amp-combo { position:relative; }
    .amp-combo-list { display:none;position:absolute;left:0;right:0;top:100%;margin-top:2px;z-index:20;max-height:220px;overflow-y:auto;
        background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;box-shadow:0 8px 24px rgba(0,0,0,.14); }
    .amp-combo.open .amp-combo-list { display:block; }
    .amp-combo-opt { padding:8px 12px;font-size:13px;cursor:pointer;color:var(--afims-text, #1A1B21); }
    .amp-combo-opt:hover, .amp-combo-opt.active { background:var(--afims-surface-subtle, #F4F7FA); }
    .amp-combo-empty { padding:8px 12px;font-size:12px;color:var(--afims-text-disabled, #A9AAB8); }

    .amp-sec-head { display:flex;align-items:center;justify-content:space-between;margin:6px 0 12px; }
    .amp-sec-title { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text, #1A1B21); }

    /* DR line card */
    .amp-item { border:1px solid var(--afims-heading-fill, #000E3E);border-radius:14px;padding:16px;margin-bottom:16px; }
    .amp-item-top { display:flex;align-items:center;justify-content:space-between;margin-bottom:12px; }
    .amp-item-no { font-size:12px;font-weight:700;color:var(--afims-primary-text, #0035A9);text-transform:uppercase;letter-spacing:.05em; }
    .amp-item .amp-group { margin-bottom:10px; }
    .amp-item .amp-row:last-child .amp-group { margin-bottom:0; }

    .amp-link-danger { border:none;background:none;cursor:pointer;color:var(--afims-danger, #DC3545);font-size:12px;font-weight:600;white-space:nowrap; }
    .amp-link-danger:disabled { opacity:.35;cursor:not-allowed; }
    .amp-add-btn { border:1px dashed var(--afims-heading-fill, #000E3E);background:transparent;color:var(--afims-heading-fill, #000E3E);
        border-radius:8px;height:34px;padding:0 14px;font-size:12px;font-weight:600;cursor:pointer;font-family:'IBM Plex Sans',sans-serif; }
    .amp-add-btn:hover { background:rgba(15,32,69,.05); }

    #amp-footer { display:flex;align-items:center;justify-content:flex-end;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #amp-footer .amp-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    #amp-footer .amp-btn:disabled { opacity:.5;cursor:not-allowed; }
    #amp-footer .amp-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #amp-footer .amp-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
</style>

<div id="amp-overlay">
    <div id="amp-card">
        <div id="amp-header">
            <div>
                <p class="amp-hdr-tag">Inspection and Acceptance Report Module</p>
                <p class="amp-hdr-title" id="amp-modal-title">Add PO Record</p>
            </div>
            <button type="button" id="amp-modal-close">&times;</button>
        </div>

        <div id="amp-err"></div>

        <form id="amp-form" autocomplete="off">
            <div id="amp-body">
                <input type="hidden" id="amp-mode" value="add">
                <input type="hidden" id="amp-orig-po" value="">

                <div class="amp-row">
                    <div class="amp-group">
                        <label class="amp-label" for="amp-po-number">PO Number <span class="req">*</span></label>
                        <input type="text" class="amp-input" id="amp-po-number" required maxlength="100" placeholder="e.g. 2026-05-0123">
                    </div>
                    <div class="amp-group">
                        <label class="amp-label" for="amp-iar-number">IAR Number</label>
                        <input type="text" class="amp-input" id="amp-iar-number" readonly tabindex="-1" placeholder="Auto-generated on save">
                    </div>
                    <div class="amp-group">
                        <label class="amp-label" for="amp-si-number">Sales Invoice # <span class="opt">(optional)</span></label>
                        <input type="text" class="amp-input" id="amp-si-number" maxlength="100">
                    </div>
                    <div class="amp-group">
                        <label class="amp-label" for="amp-si-date">Sales Invoice Date <span class="opt">(optional)</span></label>
                        <input type="date" class="amp-input" id="amp-si-date">
                    </div>
                </div>

                <div class="amp-sec-head">
                    <span class="amp-sec-title">Delivery Receipts</span>
                    <button type="button" class="amp-add-btn" id="amp-add-line">+ Add line</button>
                </div>
                <div id="amp-lines"></div>
            </div>
            <div id="amp-footer">
                <button type="button" class="amp-btn amp-btn-ghost" id="amp-cancel">Cancel</button>
                <button type="submit" class="amp-btn amp-btn-navy" id="amp-save">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Read-only view of one DR (opened by clicking a DR number in the list) -->
<style>
    #amp-view-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:flex-start;justify-content:center;padding:16px;backdrop-filter:blur(3px);overflow-y:auto; }
    #amp-view-overlay.open { display:flex; }
    #amp-view-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:1500px;
        max-height:calc(100vh - 32px);display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;animation:ampIn .2s ease; }
    #amp-view-body { flex:1;overflow:auto;padding:24px 32px; }
    .amp-v-meta { display:flex;gap:32px;flex-wrap:wrap;margin-bottom:18px; }
    .amp-v-meta .k { font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-disabled, #A9AAB8); }
    .amp-v-meta .v { font-size:14px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .amp-v-table { width:100%;border-collapse:collapse;font-size:13px; }
    .amp-v-table th { text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.05em;padding:8px 10px;white-space:nowrap;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-v-table td { padding:9px 10px;border-bottom:1px solid var(--afims-border, #E4ECF2);vertical-align:top; }
    .amp-tabs { display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-tab { border:none;background:none;cursor:pointer;padding:9px 16px;font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;
        color:var(--afims-text-disabled, #A9AAB8);border-bottom:2px solid transparent;margin-bottom:-1px; }
    .amp-tab:hover { color:var(--afims-text, #1A1B21); }
    .amp-tab.active { color:var(--afims-primary-text, #0035A9);border-bottom-color:var(--afims-primary-text, #0035A9); }
    /* pro-forma JEV (grid layout, not a table) */
    .amp-jev { border:1px solid var(--afims-border, #E4ECF2);border-radius:12px;overflow:hidden;background:var(--afims-surface-subtle, #F4F7FA);font-size:13px;
        --amp-jev-cols:minmax(0,3.2fr) 90px minmax(0,1.3fr) minmax(0,1.3fr) 120px minmax(0,1.3fr) minmax(0,1.3fr); }
    .amp-jev-head, .amp-jev-row { display:grid;grid-template-columns:var(--amp-jev-cols);align-items:center; }
    .amp-jev-h { padding:10px 12px;font-size:12px;font-weight:700;color:var(--afims-text-secondary, #5B5E73);line-height:1.25; }
    .amp-jev-grp { border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-jev-head { border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-jev-row { padding:0;border-bottom:1px solid var(--afims-border, #E4ECF2);background:var(--afims-surface, #FFFFFF); }
    .amp-jev-row > div { padding:10px 12px; }
    .amp-jev-ctr { text-align:center; }
    .amp-jev-num { text-align:right;white-space:nowrap; }
    .amp-jev-sum { display:flex;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-top:16px;padding:14px;border-radius:12px;background:var(--afims-surface-subtle, #F4F7FA); }
    .amp-jev-card { min-width:150px;padding:12px 16px;background:var(--afims-surface, #FFFFFF);border:1px solid var(--afims-border, #E4ECF2);border-radius:10px; }
    .amp-jev-card .k { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--afims-text-secondary, #5B5E73);margin-bottom:4px; }
    .amp-jev-card .v { font-size:18px;font-weight:700; }
    .amp-jev-status { margin-left:auto;text-align:right; }
    .amp-jev-pill { display:inline-block;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700; }
    .amp-jev-pill.ok  { background:#D8F3E3;color:#16A34A; }
    .amp-jev-pill.bad { background:var(--afims-danger-soft, #FAE1E3);color:var(--afims-danger, #DC3545); }
    .amp-jev-hint { margin-top:6px;font-size:11px;color:var(--afims-text-secondary, #5B5E73); }
    .amp-jev-actions { display:flex;justify-content:flex-start;margin-top:14px; }
    .amp-jev-btn { height:38px;padding:0 24px;border-radius:8px;border:1px solid var(--afims-heading-fill, #000E3E);background:var(--afims-heading-fill, #000E3E);
        color:var(--afims-pure-white, #FFFFFF);font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer; }
    .amp-jev-btn:hover:not(:disabled) { opacity:.9; }
    .amp-jev-btn:disabled { opacity:.5;cursor:not-allowed; }
    .amp-jev-row { border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .amp-jev-row:last-child { border-bottom:none; }
    .amp-jev-row > div { border-left:1px solid var(--afims-border, #E4ECF2);min-height:42px;display:flex;align-items:center; }
    .amp-jev-row > div:first-child { border-left:none; }
    .amp-jev-c1, .amp-jev-code { font-weight:700;color:var(--afims-text, #1A1B21); }
    .amp-jev-code { justify-content:center;text-align:center; }
    .amp-jev-amt { justify-content:space-between; }
    .amp-jev-row > .amp-jev-amt:nth-child(6), .amp-jev-row > .amp-jev-amt:nth-child(7) { font-weight:700;color:var(--afims-text, #1A1B21); }
    .amp-jev-row > .amp-jev-amt { font-variant-numeric:tabular-nums; }
    .amp-jev-empty { padding:16px;text-align:center;color:var(--afims-text-secondary, #5B5E73); }
    @media (max-width:900px) { .amp-jev { overflow-x:auto; } .amp-jev-head, .amp-jev-row { min-width:820px; } }
    .amp-v-table tfoot td { font-weight:700;border-bottom:none; }
</style>

<div id="amp-view-overlay">
    <div id="amp-view-card">
        <div id="amp-header">
            <div>
                <p class="amp-hdr-tag">Inspection and Acceptance Report Module</p>
                <p class="amp-hdr-title" id="amp-view-title">Actual Delivery</p>
            </div>
            <button type="button" id="amp-view-close" style="border:none;background:rgba(255,255,255,.12);cursor:pointer;width:30px;height:30px;border-radius:8px;color:#A9AAB8;font-size:18px;line-height:1;">&times;</button>
        </div>
        <div id="amp-view-body"></div>
        <div id="amp-footer">
            <button type="button" class="amp-btn amp-btn-ghost" id="amp-view-done">Close</button>
        </div>
    </div>
</div>

<script>
document.getElementById('amp-view-close')?.addEventListener('click', () => document.getElementById('amp-view-overlay').classList.remove('open'));
document.getElementById('amp-view-done')?.addEventListener('click', () => document.getElementById('amp-view-overlay').classList.remove('open'));

const AMP_FORM_API = '<?php echo $_ampf_api; ?>' + '/po-dr';

function amp_authHeaders(extra = {}) {
    const t = window.ew && window.ew.API_JWT_TOKEN;
    return t ? { ...extra, 'X-Authorization': 'Bearer ' + t } : extra;
}

function amp_attrEsc(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}
function amp_textEsc(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;');
}

/* ── Number formatting (thousands separators) ──────────────── */
function amp_unfmt(v) { return String(v ?? '').replace(/,/g, '').trim(); }
function amp_fmtInt(v) { const n = parseInt(amp_unfmt(v), 10); return isNaN(n) ? '' : n.toLocaleString('en-US'); }
function amp_fmtMoney(v) {
    const n = parseFloat(amp_unfmt(v));
    return isNaN(n) ? '' : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function amp_liveFmt(v, decimals) {
    let s = amp_unfmt(v).replace(decimals ? /[^0-9.]/g : /[^0-9]/g, '');
    let dec = '';
    if (decimals) {
        const i = s.indexOf('.');
        if (i !== -1) { dec = '.' + s.slice(i + 1).replace(/\./g, '').slice(0, 2); s = s.slice(0, i); }
    }
    s = s.replace(/^0+(?=\d)/, '');
    return s.replace(/\B(?=(\d{3})+(?!\d))/g, ',') + dec;
}

/* Today's date (local) as YYYY-MM-DD — default Delivery Date for a new line */
function amp_today() {
    const d = new Date();
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}

/* ── Categories (searchable dropdown) — keep in sync with PoDrService::ACCOUNTS ── */
const AMP_CATEGORY_MIN_PRICE = 50000;   // line amount (qty × price) at/above which the category is required
const AMP_ACCOUNTS = [   // [account code, name] — only the code is saved; the full "code - name" is shown
    ['1-06-05-020', 'Office Equipment'],
    ['1-06-05-030', 'Information and Communication Technology Equipment'],
    ['1-06-05-070', 'Communication Equipment'],
    ['1-06-05-080', 'Construction and Heavy Equipment'],
    ['1-06-05-110', 'Medical Equipment'],
    ['1-06-05-130', 'Sports Equipment'],
    ['1-06-05-990', 'Other Machinery and Equipment'],
    ['1-06-06-010', 'Motor Vehicles'],
    ['1-06-07-010', 'Furniture & Fixtures'],
    ['1-06-98-990', 'Other Property, Plant and Equipment'],
];
const AMP_CATEGORIES = AMP_ACCOUNTS.map(a => a[0] + ' - ' + a[1]);          // labels shown in the dropdown
const amp_accountLabel = code => { const a = AMP_ACCOUNTS.find(x => x[0] === code); return a ? a[0] + ' - ' + a[1] : ''; };
const amp_accountCode  = label => { const a = AMP_ACCOUNTS.find(x => x[0] + ' - ' + x[1] === label); return a ? a[0] : ''; };

function amp_initCombo(wrap) {
    const input = wrap.querySelector('.amp-f-cat'), list = wrap.querySelector('.amp-combo-list');
    let typed = false, lastValid = AMP_CATEGORIES.includes(input.value) ? input.value : '';
    const render = () => {
        // filter only while the user is typing; otherwise (e.g. a value is already selected) show every option
        const q = typed ? input.value.trim().toLowerCase() : '';
        const hits = AMP_CATEGORIES.filter(c => c.toLowerCase().includes(q));
        list.innerHTML = hits.length
            ? hits.map(c => '<div class="amp-combo-opt' + (c === lastValid ? ' active' : '') + '" data-v="' + amp_attrEsc(c) + '">' + amp_textEsc(c) + '</div>').join('')
            : '<div class="amp-combo-empty">No matching category</div>';
    };
    const open = () => { render(); wrap.classList.add('open'); };
    input.addEventListener('focus', () => { typed = false; input.select(); open(); });
    input.addEventListener('click', () => { if (!typed) open(); });
    input.addEventListener('input', () => { typed = true; open(); });
    // mousedown (not click) so the choice lands before the input's blur fires
    list.addEventListener('mousedown', e => {
        const opt = e.target.closest('.amp-combo-opt');
        if (!opt) return;
        e.preventDefault();
        input.value = lastValid = opt.dataset.v;
        typed = false;
        wrap.classList.remove('open');
    });
    // only a value picked from the list is kept
    input.addEventListener('blur', () => {
        wrap.classList.remove('open');
        // keep only a value picked from the list: a half-typed search goes back to the last selection
        if (!AMP_CATEGORIES.includes(input.value)) input.value = input.value.trim() === '' ? '' : lastValid;
        lastValid = AMP_CATEGORIES.includes(input.value) ? input.value : '';
        typed = false;
    });
    input.addEventListener('keydown', e => {
        if (e.key === 'Escape') wrap.classList.remove('open');
        if (e.key === 'Enter' && wrap.classList.contains('open')) {
            e.preventDefault();
            const first = list.querySelector('.amp-combo-opt');
            if (first) { input.value = lastValid = first.dataset.v; typed = false; wrap.classList.remove('open'); }
        }
    });
}

/* ── DR line cards ─────────────────────────────────────────── */
function amp_addLine(line = {}) {
    const box  = document.getElementById('amp-lines');
    const prev = box.querySelector('.amp-item:last-child');          // a new line starts from the previous line's DR no. / date
    const dr   = line.dr_number ?? prev?.querySelector('.amp-f-dr')?.value ?? '';
    const dd   = line.delivery_date || prev?.querySelector('.amp-f-ddate')?.value || amp_today();

    const el = document.createElement('div');
    el.className = 'amp-item';
    el.dataset.id = line.dr_line_id || '';
    el.innerHTML = `
        <div class="amp-item-top">
            <span class="amp-item-no"></span>
            <button type="button" class="amp-link-danger amp-item-remove">Remove line</button>
        </div>
        <div class="amp-row" style="grid-template-columns:repeat(2,minmax(0,1fr));">
            <div class="amp-group">
                <label class="amp-label">Delivery Receipt <span class="req">*</span></label>
                <input type="text" class="amp-input amp-f-dr" required maxlength="100" value="${amp_attrEsc(dr)}">
            </div>
            <div class="amp-group">
                <label class="amp-label">Delivery Date <span class="req">*</span></label>
                <input type="date" class="amp-input amp-f-ddate" required value="${amp_attrEsc(dd)}">
            </div>
        </div>
        <div class="amp-group">
            <label class="amp-label">Property & Equipment Category <span class="opt amp-cat-note">(required when amount is 50,000 or more)</span></label>
            <div class="amp-combo">
                <input type="text" class="amp-input amp-f-cat" placeholder="Search or choose a category" value="${amp_attrEsc(amp_accountLabel(line.account_code))}">
                <div class="amp-combo-list"></div>
            </div>
        </div>
        <div class="amp-group">
            <label class="amp-label">Description <span class="req">*</span></label>
            <textarea class="amp-input amp-f-desc" required placeholder="Item description">${amp_textEsc(line.description)}</textarea>
        </div>
        <div class="amp-row">
            <div class="amp-group">
                <label class="amp-label">Quantity <span class="req">*</span></label>
                <input type="text" inputmode="numeric" class="amp-input amp-f-qty" required value="${amp_attrEsc(amp_fmtInt(line.quantity ?? 1))}">
            </div>
            <div class="amp-group">
                <label class="amp-label">Unit <span class="req">*</span></label>
                <input type="text" class="amp-input amp-f-unit" required maxlength="50" placeholder="e.g. pc, box, set" value="${amp_attrEsc(line.unit)}">
            </div>
            <div class="amp-group">
                <label class="amp-label">Unit Price <span class="req">*</span></label>
                <input type="text" inputmode="decimal" class="amp-input amp-f-price" required placeholder="0.00" value="${amp_attrEsc(amp_fmtMoney(line.unit_price))}">
            </div>
            <div class="amp-group">
                <label class="amp-label">Amount <span class="opt">(qty × price)</span></label>
                <input type="text" class="amp-input amp-f-amount" readonly tabindex="-1" value="${amp_attrEsc(amp_fmtMoney(line.amount))}">
            </div>
        </div>
        <div class="amp-row">
            <div class="amp-group">
                <label class="amp-label">Model No. <span class="opt">(optional)</span></label>
                <input type="text" class="amp-input amp-f-model" maxlength="100" value="${amp_attrEsc(line.model_no)}">
            </div>
            <div class="amp-group">
                <label class="amp-label">Serial No. <span class="opt">(optional)</span></label>
                <input type="text" class="amp-input amp-f-serial" maxlength="100" value="${amp_attrEsc(line.serial_no)}">
            </div>
            <div class="amp-group">
                <label class="amp-label">Property No. <span class="opt">(optional)</span></label>
                <input type="text" class="amp-input amp-f-prop" maxlength="100" value="${amp_attrEsc(line.property_no)}">
            </div>
            <div class="amp-group">
                <label class="amp-label">PAR No. <span class="opt">(optional)</span></label>
                <input type="text" class="amp-input amp-f-par" maxlength="100" value="${amp_attrEsc(line.par_no)}">
            </div>
        </div>`;

    amp_initCombo(el.querySelector('.amp-combo'));
    const qtyEl = el.querySelector('.amp-f-qty'), priceEl = el.querySelector('.amp-f-price');
    const calc = () => {
        const q = parseFloat(amp_unfmt(qtyEl.value)), p = parseFloat(amp_unfmt(priceEl.value));
        el.querySelector('.amp-f-amount').value = (isNaN(q) || isNaN(p)) ? '' : amp_fmtMoney(Math.round(q * p * 100) / 100);
    };
    // live thousands separators while typing; quantity = whole numbers only, price = up to 2 decimals
    qtyEl.addEventListener('input', () => { qtyEl.value = amp_liveFmt(qtyEl.value, false); calc(); });
    priceEl.addEventListener('input', () => { priceEl.value = amp_liveFmt(priceEl.value, true); calc(); });
    priceEl.addEventListener('blur', () => { if (priceEl.value !== '') { priceEl.value = amp_fmtMoney(amp_unfmt(priceEl.value)); calc(); } });
    // category only applies when the line amount (qty × price) is 50,000 or more
    const catEl = el.querySelector('.amp-f-cat');
    const syncCat = () => {
        const need = (parseFloat(amp_unfmt(qtyEl.value)) || 0) * (parseFloat(amp_unfmt(priceEl.value)) || 0) >= AMP_CATEGORY_MIN_PRICE;
        catEl.required = need;
        catEl.disabled = !need;
        if (!need) catEl.value = '';
        catEl.placeholder = need ? 'Search or choose a category' : 'Not required (amount below 50,000)';
    };
    qtyEl.addEventListener('input', syncCat);
    priceEl.addEventListener('input', syncCat);
    priceEl.addEventListener('blur', syncCat);
    syncCat();
    calc();

    el.querySelector('.amp-item-remove').addEventListener('click', () => { el.remove(); amp_renumberLines(); });
    box.appendChild(el);
    amp_renumberLines();
    return el;
}

function amp_renumberLines() {
    const rows = document.querySelectorAll('#amp-lines .amp-item');
    rows.forEach((r, i) => {
        r.querySelector('.amp-item-no').textContent = 'Line ' + (i + 1);
        r.querySelector('.amp-item-remove').disabled = rows.length === 1;   // keep at least one line
    });
}

/* ── Collect the form into the API payload ─────────────────── */
function amp_collectLines() {
    return [...document.querySelectorAll('#amp-lines .amp-item')].map(row => ({
        dr_line_id    : row.dataset.id || null,
        dr_number     : row.querySelector('.amp-f-dr').value.trim(),
        delivery_date : row.querySelector('.amp-f-ddate').value,
        account_code  : amp_accountCode(row.querySelector('.amp-f-cat').value),
        description   : row.querySelector('.amp-f-desc').value.trim(),
        quantity      : amp_unfmt(row.querySelector('.amp-f-qty').value),
        unit          : row.querySelector('.amp-f-unit').value.trim(),
        unit_price    : amp_unfmt(row.querySelector('.amp-f-price').value),
        model_no      : row.querySelector('.amp-f-model').value.trim(),
        serial_no     : row.querySelector('.amp-f-serial').value.trim(),
        property_no   : row.querySelector('.amp-f-prop').value.trim(),
        par_no        : row.querySelector('.amp-f-par').value.trim(),
    }));
}

document.getElementById('amp-add-line')?.addEventListener('click', () => {
    const row = amp_addLine();
    row.querySelector('.amp-f-dr').focus();
    row.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
});
</script>
