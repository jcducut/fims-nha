<?php
namespace PHPMaker2024\FIMS;
@session_start();

// Purchase Order detail view (?id=<po_id>). Ported from GLOBAL/includes/inc.po-readone.php,
// trimmed to the `fims` schema: no PR / allocation block, workflow engine, delivery receipts,
// payment workflow or DV panel. Status moves through POST /purchase-order/{id}/status.
// API.FIMS_URL is the site root; the REST routes live under /api (added unless the env value already ends in it).
$_pod_api  = rtrim((string) Config('API.FIMS_URL'), '/');
$_pod_api  = preg_match('~/api$~', $_pod_api) ? $_pod_api : $_pod_api . '/api';
$_pod_id   = (int) ($_GET['id'] ?? 0);
// The list page route. Editing is a modal on that page, so "Edit" links to <list>?edit=<po_id>.
$_pod_list = 'PurchaseOrder';
$_pod_form = $_pod_list;
?>

<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=IBM+Plex+Mono:wght@400;600&display=swap" rel="stylesheet" />
<link rel="stylesheet" href="/_shared/css/nha-fims.css?v=<?= @filemtime($_SERVER['DOCUMENT_ROOT'] . '/_shared/css/nha-fims.css') ?>" />

<style>
#pod-root .pod-body { max-width: 1100px; margin: 0 auto; padding: 16px 0 32px; }
#pod-root .pod-card { background: var(--afims-surface, #fff); border: 1px solid var(--afims-border, #E4ECF2); border-radius: 12px; padding: 18px 20px; margin-bottom: 16px; }
#pod-root .pod-card-title { font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--afims-text-muted, #62657E); margin: 0 0 12px; }
#pod-root .pod-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px 28px; }
#pod-root .pod-span2 { grid-column: span 2; }
#pod-root .pod-span3 { grid-column: span 3; }
#pod-root .pod-f label { display: block; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: var(--afims-text-disabled, #A9AAB8); margin-bottom: 3px; }
#pod-root .pod-f span  { font-size: 13px; font-weight: 500; color: var(--afims-text, #1A1B21); white-space: pre-wrap; }
#pod-root .pod-mono { font-family: "IBM Plex Mono", monospace; }

#pod-root .pod-items { width: 100%; border-collapse: collapse; font-size: 12.5px; }
#pod-root .pod-items th { background: var(--afims-surface-subtle, #F4F7FA); color: var(--afims-text-muted, #62657E); font-size: 10px; font-weight: 700; text-transform: uppercase;
    letter-spacing: .05em; padding: 8px 12px; text-align: left; border-bottom: 1px solid var(--afims-border, #E4ECF2); }
#pod-root .pod-items td { padding: 9px 12px; border-bottom: 1px solid var(--afims-border, #E4ECF2); vertical-align: top; }
#pod-root .pod-items tfoot td { background: var(--afims-surface-subtle, #F4F7FA); font-weight: 700; border-top: 2px solid var(--afims-border, #E4ECF2); border-bottom: none; }
#pod-root .pod-items .r { text-align: right; }

#pod-root .pod-actions { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
#pod-root .pod-tl { list-style: none; margin: 0; padding: 0; }
#pod-root .pod-tl li { display: flex; gap: 12px; padding-bottom: 14px; position: relative; }
#pod-root .pod-tl li:not(:last-child)::before { content: ""; position: absolute; left: 6px; top: 16px; bottom: 0; width: 2px; background: var(--afims-border, #E4ECF2); }
#pod-root .pod-tl .dot { width: 14px; height: 14px; border-radius: 50%; margin-top: 2px; flex-shrink: 0; background: var(--afims-text-disabled, #A9AAB8); }
#pod-root .pod-tl .dot.green { background: var(--afims-success, #11A752); }
#pod-root .pod-tl .dot.red   { background: var(--afims-danger, #DC3545); }
#pod-root .pod-tl .dot.blue  { background: var(--afims-primary, #0035A9); }
#pod-root .pod-tl .what { font-size: 13px; font-weight: 600; }
#pod-root .pod-tl .meta { font-size: 11px; color: var(--afims-text-muted, #62657E); }
#pod-root .pod-tl .note { font-size: 12px; color: var(--afims-danger-text, #8F222D); margin-top: 2px; white-space: pre-wrap; }

@media (max-width: 768px) {
    #pod-root .pod-grid { grid-template-columns: 1fr; }
    #pod-root .pod-span2, #pod-root .pod-span3 { grid-column: span 1; }
}
@media print { #pod-root .no-print { display: none !important; } }
</style>

<div id="pod-root" class="nha-root">

    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="nha-hdr no-print">
        <div class="nha-hdr-l">
            <div class="nha-hdr-ico">
                <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="#fff" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
            </div>
            <div>
                <div class="nha-hdr-tag">National Housing Authority · FIMS</div>
                <div class="nha-hdr-title" id="pod-title">Purchase Order — Detail</div>
            </div>
        </div>
        <div class="nha-hdr-r">
            <a id="pod-btn-edit" href="#" class="nha-btn-hdr" style="display:none;">Edit</a>
            <a class="nha-btn-hdr" href="<?= htmlspecialchars($_pod_list, ENT_QUOTES) ?>">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                Back
            </a>
            <!-- the official PO form (PurchaseOrderPrintService loads the saved PO from po_id) -->
            <button type="button" class="nha-btn-hdr" onclick="prp_open({ po_id: POD_ID }, { type: 'PO' })">Print</button>
        </div>
    </div>

    <div id="pod-error" class="nha-error" style="display:none;">
        <svg fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        <span id="pod-error-msg">Failed to load record.</span>
    </div>

    <div id="pod-loading"><div class="nha-loader" style="padding:60px 0;"><div class="nha-spinner-sm"></div> Loading purchase order…</div></div>

    <div id="pod-content" class="pod-body" style="display:none;">

        <!-- Summary cards -->
        <div class="nha-summary">
            <div class="nha-sc nha-sc-navy">
                <div class="nha-sc-top"><div><div class="nha-sc-label">Total Amount</div><div class="nha-sc-val" id="pod-c-total">—</div></div></div>
                <p class="nha-kpi-sub" id="pod-c-total-sub">—</p>
            </div>
            <div class="nha-sc nha-sc-teal">
                <div class="nha-sc-top"><div><div class="nha-sc-label">Outstanding</div><div class="nha-sc-val" id="pod-c-out">—</div></div></div>
                <p class="nha-kpi-sub">Balance not yet delivered / paid</p>
            </div>
            <div class="nha-sc nha-sc-blue">
                <div class="nha-sc-top"><div><div class="nha-sc-label">Status</div><div class="nha-sc-val" style="font-size:16px;" id="pod-c-status">—</div></div></div>
                <p class="nha-kpi-sub" id="pod-c-status-sub">—</p>
            </div>
            <div class="nha-sc nha-sc-purple">
                <div class="nha-sc-top"><div><div class="nha-sc-label">Vendor</div><div class="nha-sc-val" style="font-size:14px;line-height:1.3;" id="pod-c-vendor">—</div></div></div>
                <p class="nha-kpi-sub" id="pod-c-vendor-sub">—</p>
            </div>
        </div>

        <!-- Status actions -->
        <div class="pod-card no-print" id="pod-actions-card" style="margin-top:16px;display:none;">
            <p class="pod-card-title">Actions</p>
            <div class="pod-actions" id="pod-actions"></div>
        </div>

        <!-- Order identity -->
        <div class="pod-card" style="margin-top:16px;">
            <p class="pod-card-title">Order Identity</p>
            <div class="pod-grid">
                <div class="pod-f pod-span2"><label>PO Number</label><span class="pod-mono" id="pod-po-number" style="font-size:16px;font-weight:700;color:var(--afims-primary-text,#0035A9);">—</span></div>
                <div class="pod-f"><label>PO Date</label><span class="pod-mono" id="pod-po-date">—</span></div>
                <div class="pod-f"><label>Fiscal Year</label><span class="pod-mono" id="pod-fiscal-yr">—</span></div>
                <div class="pod-f"><label>Expense Class</label><span id="pod-expense-class">—</span></div>
                <div class="pod-f"><label>Contract Number</label><span class="pod-mono" id="pod-contract">—</span></div>
                <div class="pod-f pod-span2"><label>Program</label><span id="pod-program">—</span></div>
                <div class="pod-f"><label>Project</label><span id="pod-project">—</span></div>
                <div class="pod-f pod-span3"><label>Particulars</label><span id="pod-particulars">—</span></div>
                <div class="pod-f pod-span3"><label>Remarks</label><span id="pod-remarks">—</span></div>
            </div>
        </div>

        <!-- Vendor & delivery -->
        <div class="pod-card">
            <p class="pod-card-title">Vendor &amp; Delivery Terms <span style="font-weight:400;text-transform:none;">(vendor details as saved on this PO)</span></p>
            <div class="pod-grid">
                <div class="pod-f pod-span2"><label>Vendor Name</label><span id="pod-vendor-name" style="font-weight:600;">—</span></div>
                <div class="pod-f"><label>Vendor TIN</label><span class="pod-mono" id="pod-vendor-tin">—</span></div>
                <div class="pod-f pod-span3"><label>Vendor Address</label><span id="pod-vendor-address">—</span></div>
                <div class="pod-f pod-span2"><label>Delivery Place</label><span id="pod-delivery-place">—</span></div>
                <div class="pod-f"><label>Delivery Date Required</label><span class="pod-mono" id="pod-delivery-date">—</span></div>
                <div class="pod-f pod-span2"><label>Delivery Terms</label><span id="pod-delivery-terms">—</span></div>
                <div class="pod-f"><label>Payment Terms</label><span id="pod-payment-terms">—</span></div>
            </div>
        </div>

        <!-- Line items -->
        <div class="pod-card">
            <p class="pod-card-title">Line Items <span class="nha-bdg nha-bdg-blue" id="pod-item-count" style="margin-left:6px;">— items</span></p>
            <div class="nha-tbl-scroll">
                <table class="pod-items">
                    <thead>
                        <tr>
                            <th style="width:40px;">#</th><th>Description</th><th>Unit</th>
                            <th class="r">Qty</th><th class="r">Unit Cost</th><th class="r">Total Cost</th>
                            <th>Account Code</th><th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody id="pod-items-body"><tr><td colspan="8" style="text-align:center;padding:24px;">Loading items…</td></tr></tbody>
                    <tfoot>
                        <tr><td colspan="5" class="r" style="font-size:11px;">Total Amount</td><td class="r pod-mono" id="pod-total-amount">—</td><td colspan="2"></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- History -->
        <div class="pod-card">
            <p class="pod-card-title">Status History</p>
            <ul class="pod-tl" id="pod-timeline"></ul>
        </div>

    </div>

    <!-- Reject / cancel reason modal -->
    <div id="pod-modal" class="nha-overlay">
        <div class="nha-modal nha-modal-sm">
            <div class="nha-modal-hdr">
                <h3 class="nha-modal-title" id="pod-modal-title">Reject Purchase Order</h3>
                <button type="button" class="nha-modal-close" onclick="pod_closeModal()">&times;</button>
            </div>
            <div class="nha-modal-body">
                <div class="nha-fg">
                    <label id="pod-modal-label">Reason <span class="nha-req">*</span></label>
                    <textarea id="pod-modal-notes" rows="4" placeholder="Explain why…"></textarea>
                    <span id="pod-modal-err" style="display:none;font-size:11px;color:var(--afims-danger,#DC3545);">A reason is required.</span>
                </div>
            </div>
            <div class="nha-modal-foot">
                <button type="button" class="nha-btn nha-btn-ghost" onclick="pod_closeModal()">Cancel</button>
                <button type="button" class="nha-btn nha-btn-primary" id="pod-modal-ok" onclick="pod_confirmModal()">Confirm</button>
            </div>
        </div>
    </div>

</div><!-- /#pod-root -->

<script>
/* ══════════════════════════════════════════════════════════════
   PURCHASE ORDER DETAIL   prefix: pod_
═══════════════════════════════════════════════════════════════ */

const POD_API  = <?= json_encode($_pod_api . '/purchase-order') ?>;
const POD_ID   = <?= (int) $_pod_id ?>;
const POD_FORM = <?= json_encode($_pod_form) ?>;

const $pod = id => document.getElementById(id);
let pod_record = null;
let pod_pending = null;     // {status} waiting for the reason modal
let pod_busy = false;

/* Action buttons per target status. `reason`: required (REJECTED) / optional / none. */
const POD_ACTIONS = {
    SUBMITTED : { label: 'Submit for Approval', cls: 'nha-btn-primary', confirm: 'Submit this purchase order for approval?' },
    APPROVED  : { label: 'Approve',             cls: 'nha-btn-success', confirm: 'Approve this purchase order?' },
    REJECTED  : { label: 'Reject',              cls: 'nha-btn-danger',  reason: 'required' },
    CANCELLED : { label: 'Cancel PO',           cls: 'nha-btn-outline', reason: 'optional' },
};

function pod_authFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}) };
    const t = window.ew && window.ew.API_JWT_TOKEN;
    if (t) headers['X-Authorization'] = 'Bearer ' + t;
    return fetch(url, { credentials: 'same-origin', ...opts, headers });
}

async function pod_load() {
    if (!POD_ID) { pod_showError('No purchase order specified (missing ?id=).'); return; }
    window.PagePreloader?.show();
    try {
        const res  = await pod_authFetch(`${POD_API}/read/${POD_ID}`);
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load purchase order.');
        pod_populate(json.data);
        $pod('pod-loading').style.display = 'none';
        $pod('pod-content').style.display = '';
    } catch (e) {
        $pod('pod-loading').style.display = 'none';
        pod_showError(e.message);
    } finally {
        window.PagePreloader?.hide();
    }
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', pod_load);
else pod_load();

/* ── Render ────────────────────────────────────────────────── */
function pod_set(id, v) {
    const el = $pod(id);
    if (el) el.textContent = (v !== null && v !== undefined && v !== '') ? v : '—';
}

function pod_populate(r) {
    pod_record = r;
    pod_set('pod-title', `Purchase Order — ${r.po_number}`);

    pod_set('pod-c-total', pod_peso(r.total_amount));
    pod_set('pod-c-total-sub', `${(r.items || []).length} line item${(r.items || []).length !== 1 ? 's' : ''} · ${r.expense_class || '—'}`);
    pod_set('pod-c-out', pod_peso(r.outstanding_amount));
    $pod('pod-c-status').innerHTML = pod_statusBadge(r.status);
    pod_set('pod-c-status-sub', r.po_date ? 'Dated ' + pod_fmtDate(r.po_date) : '');
    pod_set('pod-c-vendor', r.vendor_name);
    pod_set('pod-c-vendor-sub', r.vendor_tin ? 'TIN ' + r.vendor_tin : '');

    pod_set('pod-po-number', r.po_number);
    pod_set('pod-po-date', pod_fmtDate(r.po_date));
    pod_set('pod-fiscal-yr', r.fiscal_yr ? 'FY ' + r.fiscal_yr : null);
    $pod('pod-expense-class').innerHTML = r.expense_class ? pod_classBadge(r.expense_class) : '—';
    pod_set('pod-contract', r.contract_number);
    pod_set('pod-program', r.program_code ? `${r.program_code}${r.program_name ? ' — ' + r.program_name : ''}` : null);
    pod_set('pod-project', r.project_code ? `${r.project_code}${r.project_name ? ' — ' + r.project_name : ''}` : null);
    pod_set('pod-particulars', r.particulars);
    pod_set('pod-remarks', r.remarks);

    pod_set('pod-vendor-name', r.vendor_name);
    pod_set('pod-vendor-tin', r.vendor_tin);
    pod_set('pod-vendor-address', r.vendor_address);
    pod_set('pod-delivery-place', r.delivery_place);
    pod_set('pod-delivery-date', pod_fmtDate(r.delivery_date_required));
    pod_set('pod-delivery-terms', r.delivery_terms);
    pod_set('pod-payment-terms', r.payment_terms);

    pod_renderItems(r.items || []);
    pod_set('pod-total-amount', pod_peso(r.total_amount));
    pod_renderActions(r);
    pod_renderTimeline(r);

    const edit = $pod('pod-btn-edit');
    edit.style.display = r.is_editable ? '' : 'none';
    edit.href = `${POD_FORM}?edit=${encodeURIComponent(r.po_id)}`;   // opens the edit modal on the list page
}

function pod_renderItems(items) {
    pod_set('pod-item-count', `${items.length} item${items.length !== 1 ? 's' : ''}`);
    if (!items.length) {
        $pod('pod-items-body').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:24px;">No line items found.</td></tr>';
        return;
    }
    $pod('pod-items-body').innerHTML = items.map((it, i) => `<tr>
        <td class="pod-mono">${pod_esc(it.line_number || i + 1)}</td>
        <td style="font-weight:500;">${pod_esc(it.item_description) || '—'}</td>
        <td>${pod_esc(it.unit_of_measure) || '—'}</td>
        <td class="r pod-mono">${Number(it.quantity_ordered || 0).toLocaleString('en-PH', { maximumFractionDigits: 4 })}</td>
        <td class="r pod-mono">${pod_peso(it.unit_cost)}</td>
        <td class="r pod-mono" style="font-weight:600;">${pod_peso(it.total_cost)}</td>
        <td class="pod-mono">${pod_esc(it.account_code) || '—'}</td>
        <td>${pod_esc(it.remarks) || '—'}</td>
    </tr>`).join('');
}

/* ── Status actions ────────────────────────────────────────── */
function pod_renderActions(r) {
    const box  = $pod('pod-actions');
    const next = r.allowed_transitions || [];
    box.innerHTML = '';
    $pod('pod-actions-card').style.display = next.length ? '' : 'none';

    // Approving needs a submitted PO; a REJECTED one must be edited and re-saved first.
    if (r.status === 'REJECTED') {
        const hint = document.createElement('span');
        hint.style.cssText = 'font-size:12px;color:var(--afims-text-muted,#62657E);';
        hint.textContent = 'This PO was rejected. Edit it and save to resubmit, or cancel it.';
        box.appendChild(hint);
    }
    next.forEach(st => {
        const def = POD_ACTIONS[st];
        if (!def) return;
        const b = document.createElement('button');
        b.type = 'button';
        b.className = 'nha-btn ' + def.cls;
        b.textContent = def.label;
        b.onclick = () => pod_doAction(st);
        box.appendChild(b);
    });
}

function pod_doAction(status) {
    const def = POD_ACTIONS[status];
    if (!def || pod_busy) return;
    if (def.reason) {
        pod_pending = { status };
        $pod('pod-modal-title').textContent = def.label;
        $pod('pod-modal-label').innerHTML   = def.reason === 'required' ? 'Reason <span class="nha-req">*</span>' : 'Reason (optional)';
        $pod('pod-modal-notes').value = '';
        $pod('pod-modal-err').style.display = 'none';
        $pod('pod-modal').classList.add('open');
        $pod('pod-modal-notes').focus();
        return;
    }
    if (!confirm(def.confirm || `${def.label}?`)) return;
    pod_sendStatus(status, null);
}

function pod_closeModal() { $pod('pod-modal').classList.remove('open'); pod_pending = null; }

function pod_confirmModal() {
    if (!pod_pending) return;
    const notes = $pod('pod-modal-notes').value.trim();
    if (POD_ACTIONS[pod_pending.status].reason === 'required' && !notes) {
        $pod('pod-modal-err').style.display = 'block';
        return;
    }
    const status = pod_pending.status;
    pod_closeModal();
    pod_sendStatus(status, notes || null);
}

async function pod_sendStatus(status, notes) {
    if (pod_busy) return;
    pod_busy = true;
    window.PagePreloader?.show();
    try {
        const res  = await pod_authFetch(`${POD_API}/${POD_ID}/status`, {
            method  : 'POST',
            headers : { 'Content-Type': 'application/json' },
            body    : JSON.stringify({ status, notes }),
        });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to change status.');
        pod_toast(json.message || 'Status updated.', 'success');
        await pod_load();
    } catch (e) {
        pod_toast(e.message, 'error');
    } finally {
        pod_busy = false;
        window.PagePreloader?.hide();
    }
}

/* ── History (built from the PO's own timestamps) ──────────── */
function pod_renderTimeline(r) {
    const ev = [];
    if (r.created_at)   ev.push({ at: r.created_at,   what: 'Created',   who: r.created_by_name,   dot: 'blue' });
    if (r.submitted_at) ev.push({ at: r.submitted_at, what: 'Submitted for approval', who: r.submitted_by_name, dot: 'blue' });
    if (r.approved_at)  ev.push({ at: r.approved_at,  what: 'Approved',  who: r.approved_by_name,  dot: 'green' });
    if (r.rejected_at)  ev.push({ at: r.rejected_at,  what: 'Rejected',  who: r.rejected_by_name,  dot: 'red', note: r.rejection_reason });
    if (r.status === 'CANCELLED' && r.updated_at) ev.push({ at: r.updated_at, what: 'Cancelled', who: null, dot: 'red' });
    ev.sort((a, b) => new Date(a.at) - new Date(b.at));

    const ul = $pod('pod-timeline');
    ul.innerHTML = '';
    ev.forEach(e => {
        const li = document.createElement('li');
        const dot = Object.assign(document.createElement('span'), { className: 'dot ' + e.dot });
        const box = document.createElement('div');
        const what = Object.assign(document.createElement('div'), { className: 'what', textContent: e.what });
        const meta = Object.assign(document.createElement('div'), { className: 'meta', textContent: pod_fmtDateTime(e.at) + (e.who ? ' · ' + e.who : '') });
        box.append(what, meta);
        if (e.note) box.append(Object.assign(document.createElement('div'), { className: 'note', textContent: 'Reason: ' + e.note }));
        li.append(dot, box);
        ul.appendChild(li);
    });
}

/* ── Helpers ───────────────────────────────────────────────── */
function pod_statusBadge(s) {
    const map = { DRAFT: 'nha-bdg-gray', SUBMITTED: 'nha-bdg-amber', APPROVED: 'nha-bdg-green', REJECTED: 'nha-bdg-red', CANCELLED: 'nha-bdg-orange' };
    return `<span class="nha-bdg ${map[s] || 'nha-bdg-gray'}">${pod_esc(s || '—')}</span>`;
}
function pod_classBadge(c) {
    const map = { PS: ['nha-bdg-blue', 'PS'], MOOE: ['nha-bdg-teal', 'MOOE'], CAPITAL_OUTLAY: ['nha-bdg-purple', 'Capital Outlay'], CO: ['nha-bdg-purple', 'CO'], OTHER: ['nha-bdg-gray', 'Other'] };
    const [cls, label] = map[c] || ['nha-bdg-gray', c];
    return `<span class="nha-bdg ${cls}">${pod_esc(label)}</span>`;
}
function pod_peso(v) {
    return '₱ ' + Number(v || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function pod_fmtDate(s) {
    if (!s) return null;
    const d = new Date(String(s).slice(0, 10) + 'T00:00:00');
    return isNaN(d) ? null : d.toLocaleDateString('en-PH', { month: 'short', day: '2-digit', year: 'numeric' });
}
function pod_fmtDateTime(s) {
    const d = new Date(s);
    return isNaN(d) ? '' : d.toLocaleString('en-PH', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
function pod_esc(text) {
    if (text === null || text === undefined) return '';
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML.replace(/"/g, '&quot;');
}
function pod_showError(msg) { $pod('pod-error').style.display = 'flex'; $pod('pod-error-msg').textContent = msg; }

function pod_toast(msg, type = 'info') {
    const colors = { success: '#16a34a', error: '#dc2626', warning: '#d97706', info: '#2563eb' };
    let wrap = document.getElementById('nha-toast-wrap');
    if (!wrap) {
        wrap = document.createElement('div');
        wrap.id = 'nha-toast-wrap';
        wrap.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px;pointer-events:none;';
        document.body.appendChild(wrap);
    }
    const color = colors[type] || colors.info;
    const t = document.createElement('div');
    t.style.cssText = `display:flex;align-items:flex-start;gap:10px;padding:12px 14px;background:#fff;border-left:4px solid ${color};border-radius:8px;box-shadow:0 4px 16px rgba(0,0,0,.14);font-size:13px;color:#1e293b;min-width:260px;max-width:340px;pointer-events:auto;`;
    const span = Object.assign(document.createElement('span'), { textContent: msg });
    span.style.flex = '1';
    const close = Object.assign(document.createElement('button'), { innerHTML: '&times;' });
    close.style.cssText = 'border:none;background:none;cursor:pointer;color:#A9AAB8;font-size:18px;line-height:1;padding:0 2px;flex-shrink:0';
    close.onclick = () => t.remove();
    t.append(span, close);
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}
</script>
<?php
// Print → the official PO form in the shared print preview (prp_open; PDF / DOCX).
include_once __DIR__ . '/../readone/print-preview-readone.php';
?>
