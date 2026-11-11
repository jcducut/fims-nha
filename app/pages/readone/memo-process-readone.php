<?php
/**
 * "Process" modal of the Memo Request list (memo-req-page.php) — Financial Management Department (FMD) users only: the page
 * includes this file only for them, and the API refuses anyone else (PATCH /budget_allocation/process/{id} answers 403).
 *
 * Opened by mpr_open(id) from a row's Actions menu. It reads the request (GET /budget_allocation/read/{id}) and shows its
 * details read-only: the request itself, Program / Sub-Program / Project, vendor and PR / PO, the purpose, the budget
 * allocation lines and the supporting documents.
 *
 * The review is a chain of three steps, each acted on by one FMD user level (BudgetAllocationService::REVIEW_STEPS):
 *   For Preview (FMD Section Chief) -> For Division Manager (FMD Division Manager)
 *   -> For Department Manager (FMD Department Manager) -> Approved
 * The read answers with data.review { in_review, actor, next_status, is_final, can_decide } for the logged-in user.
 *
 * Footer — Return and Endorse / Approve appear only when review.can_decide: the request is at a step of the chain AND the
 * user holds that step's user level (the API enforces both again); otherwise the modal is a read-only view with Close,
 * which says who the request is waiting for:
 *   Endorse  PATCH /budget_allocation/process/{id} { decision: "approve" } at the first two steps: status -> the next one.
 *   Approve  the same call at the last step: status -> "Approved", and the request is given its ALLOCATION NUMBER, by
 *            allocation type and document type (CBA / RFA / LAA; RFA 57 for COE, 87 for CO), in the same step. The
 *            number is not shown before that: it is only generated then.
 *   Return   { decision: "return" } at any step, ONE step back: the Department Manager's goes to "Returned to Division
 *            Manager" and the Division Manager's to "Returned to Section Chief" — those statuses are steps of the chain
 *            too, so that user sees Endorse / Return again; the Section Chief's goes to "Returned", back to the
 *            requesting office. No number.
 * Both ask for a confirmation first. After either the modal closes, a message says what happened (with the number, for an
 * approval) and the list reloads.
 *
 * Self-contained styling (mpr-*): it does not depend on the shared stylesheet, so a missing or older cached copy of it can
 * never turn this into an inline block (the same safeguard the memo form has). Depends on these from memo-req-page.php:
 * mrq_request, mrq_esc, mrq_money, mrq_statusBadge, mrq_statusColor, mrq_truthy, mrq_refOf, mrq_toast, mrq_load and MRQ_API.
 */
?>

<style>
.mpr-overlay { display:none;position:fixed;inset:0;z-index:9060;padding:16px;box-sizing:border-box;align-items:center;justify-content:center;
    background:rgba(15,32,69,.55);font-family:'IBM Plex Sans',sans-serif; }
.mpr-overlay.open { display:flex; }
.mpr-modal { display:flex;flex-direction:column;width:100%;max-width:920px;max-height:100%;overflow:hidden;border-radius:14px;
    background:var(--afims-surface-subtle, #F4F7FA);color:var(--afims-text, #1A1B21);box-shadow:0 24px 64px rgba(0,0,0,.35); }
.mpr-hdr { display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 20px;
    background:var(--afims-heading-fill, #000E3E);color:#fff; }
.mpr-title { margin:0;font-size:15px;font-weight:600;color:inherit; }
.mpr-ref { margin-left:8px;font-family:'IBM Plex Mono',monospace;font-weight:600;letter-spacing:.02em;color:#C7D4F5; }
.mpr-status { margin-left:12px;vertical-align:1px; }
.mpr-close { width:30px;height:30px;font-size:20px;line-height:1;color:#fff;cursor:pointer;border-radius:8px;
    background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25); }
.mpr-close:hover { background:rgba(255,255,255,.22); }
.mpr-body { padding:18px 20px 6px;overflow-y:auto; }
.mpr-card { margin-bottom:14px;padding:14px 18px;background:var(--afims-surface, #FFFFFF);
    border:1px solid var(--afims-border, #E4ECF2);border-radius:12px; }
.mpr-card-title { margin:0 0 10px;font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
    color:var(--afims-text-secondary, #475569); }
.mpr-row { display:grid;grid-template-columns:190px minmax(0,1fr);gap:12px;align-items:baseline;padding:4px 0;font-size:13px; }
.mpr-k { font-weight:600;color:var(--afims-text-secondary, #475569); }
.mpr-v { overflow-wrap:anywhere; }
.mpr-muted { color:#8A94A6; }
.mpr-code { display:inline-block;padding:2px 7px;font:600 11px/1.4 'IBM Plex Mono',monospace;color:#1D4ED8;
    background:var(--afims-surface, #FFFFFF);border:1px solid #E6EBF6;border-radius:4px; }
.mpr-num { font:700 13px/1.4 'IBM Plex Mono',monospace;letter-spacing:.02em; }
.mpr-sub { margin-top:2px;font-size:11.5px;color:#8A94A6; }
.mpr-desc { white-space:pre-wrap;font-size:13px;line-height:1.55; }
.mpr-tbl-wrap { overflow-x:auto;border:1px solid var(--afims-border, #E4ECF2);border-radius:10px; }
.mpr-tbl { width:100%;border-collapse:collapse;font-size:12.5px; }
.mpr-tbl th { padding:9px 12px;text-align:left;font-size:11px;letter-spacing:.04em;text-transform:uppercase;white-space:nowrap;
    color:var(--afims-text-secondary, #475569);background:var(--afims-surface-subtle, #F4F7FA); }
.mpr-tbl td { padding:9px 12px;vertical-align:top;border-top:1px solid var(--afims-border, #E4ECF2); }
.mpr-tbl .mpr-amt { text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums; }
.mpr-tbl tfoot td { font-weight:700;background:var(--afims-surface-subtle, #F4F7FA); }
.mpr-empty, .mpr-loading { padding:14px;text-align:center;font-size:12.5px;color:#8A94A6; }
.mpr-docs { margin:0;padding-left:18px;font-size:13px; }
.mpr-docs a { color:#1D4ED8;text-decoration:none; }
.mpr-docs a:hover { text-decoration:underline; }
.mpr-err { margin:0 0 12px;padding:8px 10px;font-size:12.5px;border-radius:7px;background:#FAE1E3;color:#8F222D; }
.mpr-err[hidden] { display:none; }
.mpr-foot { display:flex;justify-content:flex-end;gap:10px;padding:12px 20px;background:var(--afims-surface, #FFFFFF);
    border-top:1px solid var(--afims-border, #E4ECF2); }
.mpr-btn { padding:8px 18px;font-family:inherit;font-size:13px;font-weight:600;cursor:pointer;border-radius:8px;border:1px solid transparent; }
.mpr-btn[hidden] { display:none; }
.mpr-btn[disabled] { opacity:.6;cursor:not-allowed; }
.mpr-btn-ghost { color:var(--afims-text, #1A1B21);background:var(--afims-surface, #FFFFFF);border-color:var(--afims-border, #E4ECF2); }
.mpr-btn-ghost:hover:not([disabled]) { background:var(--afims-surface-subtle, #F4F7FA); }
/* Return / Approve are tinted with the colours of the statuses they give (settings_status.color), set per button as --mpr-c */
.mpr-btn-return { color:var(--mpr-c, #B45309);background:var(--afims-surface, #FFFFFF);border-color:var(--mpr-c, #F59E0B); }
.mpr-btn-return:hover:not([disabled]) { background:color-mix(in srgb, var(--mpr-c, #F59E0B) 10%, #fff); }
.mpr-btn-approve { color:#fff;background:var(--mpr-c, #16A34A);border-color:var(--mpr-c, #16A34A); }
.mpr-btn-approve:hover:not([disabled]) { opacity:.92; }
@media (max-width:640px) { .mpr-row { grid-template-columns:1fr;gap:2px; } }
</style>

<!-- ── MEMO REQUEST: PROCESS MODAL ───────────────────────── -->
<div id="mpr-overlay" class="mpr-overlay">
    <div class="mpr-modal" role="dialog" aria-modal="true" aria-labelledby="mpr-title">
        <div class="mpr-hdr">
            <h3 class="mpr-title" id="mpr-title">Process Memo Request<span class="mpr-ref" id="mpr-ref"></span><span class="mpr-status" id="mpr-status"></span></h3>
            <button type="button" class="mpr-close" id="mpr-close" aria-label="Close">&times;</button>
        </div>
        <div class="mpr-body" id="mpr-body"></div>
        <div class="mpr-body" style="padding-top:0;overflow:visible;"><div class="mpr-err" id="mpr-err" hidden></div></div>
        <div class="mpr-foot">
            <button type="button" class="mpr-btn mpr-btn-ghost" id="mpr-cancel">Close</button>
            <button type="button" class="mpr-btn mpr-btn-return" id="mpr-return" hidden>Return</button>
            <button type="button" class="mpr-btn mpr-btn-approve" id="mpr-approve" hidden>Approve</button>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   MEMO REQUEST PROCESS MODAL   prefix: mpr_   (see the notes at the top of this file)
═══════════════════════════════════════════════════════════════ */

// Names of the memo-request statuses the two buttons give (settings_status, status_for 'budget_allocation'); only used
// to tint the buttons with their colours — the API looks the statuses up itself.
const MPR_APPROVED = 'Approved';
const MPR_RETURNED = 'Returned';

const mpr_$ = id => document.getElementById(id);

let mpr_row    = null;    // the request shown: the full record from /budget_allocation/read/{id}
let mpr_seq    = 0;       // bumped on every open / close, so a slow answer for a request that was closed meanwhile is dropped
let mpr_busy   = false;   // an Approve / Return is in flight
let mpr_opener = null;    // what had the focus before the modal opened

/* ── Open / close ──────────────────────────────────────────── */
async function mpr_open(id) {
    const seq = ++mpr_seq;
    mpr_row    = null;
    mpr_busy   = false;
    mpr_opener = document.activeElement;
    mpr_$('mpr-ref').textContent = '';
    mpr_$('mpr-status').innerHTML = '';
    mpr_$('mpr-body').innerHTML = '<div class="mpr-loading">Loading…</div>';
    mpr_showErr('');
    mpr_setButtons(false);
    mpr_$('mpr-overlay').classList.add('open');
    mpr_$('mpr-cancel').focus();

    try {
        const json = await mrq_request(`${MRQ_API}/read/${encodeURIComponent(id)}`);
        if (seq !== mpr_seq) return;
        mpr_row = json.data;
        mpr_render(mpr_row);
    } catch (e) {
        if (seq !== mpr_seq) return;
        mpr_$('mpr-body').innerHTML = `<div class="mpr-err">${mrq_esc(e.message)}</div>`;
    }
}

function mpr_close() {
    if (mpr_busy) return;                    // not while an Approve / Return is being saved
    mpr_seq++;
    mpr_row = null;
    mpr_$('mpr-overlay').classList.remove('open');
    if (mpr_opener && typeof mpr_opener.focus === 'function') mpr_opener.focus();
}

function mpr_showErr(msg) {
    const box = mpr_$('mpr-err');
    box.textContent = msg || '';
    box.hidden = !msg;
}

function mpr_setBusy(busy) {
    mpr_busy = busy;
    ['mpr-return', 'mpr-approve', 'mpr-cancel', 'mpr-close'].forEach(id => { mpr_$(id).disabled = busy; });
}

// The step the request shown is at (data.review of the read), or {} when there is none.
function mpr_review() { return (mpr_row && mpr_row.review) || {}; }

// Return and Endorse / Approve only when this user may decide the current step; tinted with the colours of the statuses
// they give when those are known. The second button reads "Approve" at the last step and "Endorse" before it.
function mpr_setButtons(canDecide) {
    const ret = mpr_$('mpr-return'), app = mpr_$('mpr-approve');
    const rv  = mpr_review();
    ret.hidden = app.hidden = !canDecide;
    app.textContent = (!canDecide || rv.is_final) ? 'Approve' : 'Endorse';
    app.title = canDecide && rv.next_status ? `Its status becomes ${rv.next_status}` : '';
    // "Return to FMD Section Chief" when it goes one step back, plain "Return" when it goes to the requesting office
    ret.textContent = canDecide && rv.return_actor ? `Return to ${rv.return_actor}` : 'Return';
    ret.title = canDecide && rv.return_status ? `Its status becomes ${rv.return_status}` : '';
    [[app, (canDecide && rv.next_status) || MPR_APPROVED], [ret, (canDecide && rv.return_status) || MPR_RETURNED]].forEach(([btn, status]) => {
        const c = mrq_statusColor(status);
        if (c) btn.style.setProperty('--mpr-c', c); else btn.style.removeProperty('--mpr-c');
    });
}

/* ── Decide ────────────────────────────────────────────────── */
async function mpr_decide(action) {
    if (!mpr_row || mpr_busy) return;
    const approve = action === 'approve';
    const ref = mrq_refOf(mpr_row) ? `"${mrq_refOf(mpr_row)}"` : `#${mpr_row.allocation_id}`;
    const rv  = mpr_review();
    const ask = !approve
        ? `Return memo request ${ref} to ${rv.return_actor || 'the requesting office'}? Its status will change to ${rv.return_status || MPR_RETURNED}.`
        : (rv.is_final || !rv.next_status)
            ? `Approve memo request ${ref}? It will be given its allocation number and its status will change to ${MPR_APPROVED}.`
            : `Endorse memo request ${ref}? Its status will change to ${rv.next_status}.`;
    if (!confirm(ask)) return;

    const seq = mpr_seq;
    mpr_setBusy(true);
    mpr_showErr('');
    try {
        const json = await mrq_request(`${MRQ_API}/process/${encodeURIComponent(mpr_row.allocation_id)}`, {
            method : 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body   : JSON.stringify({ decision: action }),
        });
        mpr_setBusy(false);
        mrq_toast(json.message || (approve ? 'Memo request processed.' : 'Memo request returned.'), 'success');
        mpr_close();
        mrq_load();
    } catch (e) {
        mpr_setBusy(false);
        if (seq === mpr_seq) mpr_showErr(e.message);   // stays open: the reason is shown where the buttons are
    }
}

/* ── Render ────────────────────────────────────────────────── */
// Postgres timestamps arrive as "2026-11-10 08:30:00.123456+08": make that ISO enough for every browser, show "Nov 10, 2026".
function mpr_fmtDate(value) {
    if (!value) return '';
    const iso = String(value).trim().replace(' ', 'T').replace(/(\.\d{3})\d+/, '$1').replace(/([+-]\d{2})$/, '$1:00');
    const d = new Date(iso);
    return isNaN(d) ? String(value) : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
}

// A link from the database is only ever followed when it is http(s): never javascript:, data: and the like.
function mpr_safeUrl(u) {
    try {
        const url = new URL(String(u || ''), window.location.href);
        return (url.protocol === 'http:' || url.protocol === 'https:') ? url.href : '';
    } catch (_) { return ''; }
}

const mpr_dash   = '<span class="mpr-muted">—</span>';
const mpr_row_   = (k, vHtml) => `<div class="mpr-row"><div class="mpr-k">${mrq_esc(k)}</div><div class="mpr-v">${vHtml || mpr_dash}</div></div>`;
const mpr_card   = (title, inner) => `<div class="mpr-card"><h4 class="mpr-card-title">${mrq_esc(title)}</h4>${inner}</div>`;
const mpr_chip   = text => text ? `<code class="mpr-code">${mrq_esc(text)}</code>` : '';
// "<name> <code>" for a Program / Sub-Program / Project / office
const mpr_named  = (name, code) => (name || code) ? `${mrq_esc(name || '')}${code ? ` ${mpr_chip(code)}` : ''}` : '';
const mpr_docRow = (label, number, amount) => mpr_row_(label, (number || (amount !== null && amount !== undefined && amount !== ''))
    ? `${number ? mpr_chip(number) : ''}${amount !== null && amount !== undefined && amount !== '' ? `<div class="mpr-sub">${mrq_money(amount)}</div>` : ''}` : '');

function mpr_linesHtml(lines) {
    lines = Array.isArray(lines) ? lines : [];
    if (!lines.length) return '<div class="mpr-empty">No budget allocation lines.</div>';
    let requested = 0, allocated = 0;
    const body = lines.map(l => {
        requested += Number(l.requested_amount || 0);
        allocated += Number(l.allocated_amount || 0);
        return `<tr>
            <td>${mrq_esc(l.type_of_fund || '') || mpr_dash}</td>
            <td>${l.account_code ? mpr_chip(l.account_code) : ''}${l.account_title ? `<div class="mpr-sub">${mrq_esc(l.account_title)}</div>` : (l.account_code ? '' : mpr_dash)}</td>
            <td class="mpr-amt">${mrq_money(l.proposed_amount)}</td>
            <td class="mpr-amt">${mrq_money(l.requested_amount)}</td>
            <td class="mpr-amt">${mrq_money(l.allocated_amount)}</td>
        </tr>`;
    }).join('');
    return `<div class="mpr-tbl-wrap"><table class="mpr-tbl">
        <thead><tr><th>Type of Fund</th><th>Account</th><th class="mpr-amt">Proposed</th><th class="mpr-amt">Requested</th><th class="mpr-amt">Allocated</th></tr></thead>
        <tbody>${body}</tbody>
        <tfoot><tr><td colspan="3">Total</td><td class="mpr-amt">${mrq_money(requested)}</td><td class="mpr-amt">${mrq_money(allocated)}</td></tr></tfoot>
    </table></div>`;
}

function mpr_docsHtml(files) {
    files = Array.isArray(files) ? files : [];
    if (!files.length) return '<div class="mpr-empty">No supporting documents.</div>';
    return `<ul class="mpr-docs">${files.map(f => {
        const m    = (f && f.file_meta) || {};
        const name = m.document_name || 'Document';
        const href = mpr_safeUrl(m.view_url || m.link);
        return `<li>${href ? `<a href="${mrq_esc(href)}" target="_blank" rel="noopener noreferrer">${mrq_esc(name)}</a>` : mrq_esc(name)}</li>`;
    }).join('')}</ul>`;
}

function mpr_render(r) {
    const rv        = r.review || {};
    const canDecide = rv.can_decide === true;
    mpr_$('mpr-ref').textContent  = mrq_refOf(r);
    mpr_$('mpr-status').innerHTML = mrq_statusBadge(r.status_name, r.status_color);

    // the allocation number is only generated by the final approval (the FMD Department Manager's)
    const number = r.allocation_number
        ? `<span class="mpr-num">${mrq_esc(r.allocation_number)}</span>`
        : (rv.in_review ? '<span class="mpr-muted">Not assigned yet — it is generated, from the allocation type and document type, on the final approval.</span>' : '');
    // who the request is waiting for at this step, and where an approval sends it
    const step = !rv.in_review ? '' : mpr_row_('Review Step',
        `${mrq_esc(rv.actor || '')}${canDecide ? ' <span class="mpr-muted">(you)</span>' : ''}` +
        `<div class="mpr-sub">${canDecide ? '' : 'Waiting for this user level to act. '}${rv.is_returned ? 'It was returned to this step. ' : ''}` +
        `Endorse: ${mrq_esc(rv.next_status || '')} · Return: ${mrq_esc(rv.return_status || '')}</div>`);
    const types  = [r.allocation_type ? `<span class="nha-bdg nha-bdg-blue">${mrq_esc(r.allocation_type)}</span>` : '',
                    r.document_type   ? `<span class="nha-bdg nha-bdg-gray">${mrq_esc(r.document_type)}</span>` : '']
                    .filter(Boolean).join(' ');
    const office = (r.requesting_rc_name || r.requesting_rc_code)
        ? `${mpr_named(r.requesting_rc_name, r.requesting_rc_code)}${r.requesting_district_name ? `<div class="mpr-sub">${mrq_esc(r.requesting_district_name)}</div>` : ''}`
        : (r.requesting_district_name ? mrq_esc(r.requesting_district_name) : '');

    mpr_$('mpr-body').innerHTML = [
        mpr_card('Request',
            mpr_row_('Request Reference Number', mpr_chip(mrq_refOf(r))) +
            step +
            mpr_row_('Allocation Number', number) +
            mpr_row_('Allocation Type', types + (mrq_truthy(r.is_bidding) ? ' <span class="nha-bdg nha-bdg-purple">Bidding</span>' : '')) +
            mpr_row_('Fiscal Year', mrq_esc(r.fiscal_year)) +
            mpr_row_('Requesting Office', office) +
            (r.parent_request_referrence_no ? mpr_row_('Parent Request', mpr_chip(r.parent_request_referrence_no)) : '') +
            mpr_row_('Date Created', mrq_esc(mpr_fmtDate(r.created_at)))),
        mpr_card('Program, Sub-Program and Project',
            mpr_row_('Program', mpr_named(r.program_name, r.program_code)) +
            mpr_row_('Sub-Program', mpr_named(r.sub_program_name, r.sub_program_code)) +
            mpr_row_('Project', mpr_named(r.project_name, r.project_code))),
        mpr_card('Vendor and Purchase Documents',
            mpr_row_('Vendor', mrq_esc(r.vendor_name || '')) +
            mpr_docRow('Purchase Request (PR)', r.pr_number, r.pr_amount) +
            mpr_docRow('Purchase Order (PO)', r.po_number, r.po_amount)),
        mpr_card('Purpose / Description', r.description ? `<div class="mpr-desc">${mrq_esc(r.description)}</div>` : '<div class="mpr-empty">No description.</div>'),
        mpr_card('Budget Allocation Lines', mpr_linesHtml(r.line_transaction)),
        mpr_card('Supporting Documents', mpr_docsHtml(r.file_attachments)),
    ].join('');
    mpr_setButtons(canDecide);
}

/* ── Wiring ────────────────────────────────────────────────── */
mpr_$('mpr-close').addEventListener('click', mpr_close);
mpr_$('mpr-cancel').addEventListener('click', mpr_close);
mpr_$('mpr-approve').addEventListener('click', () => mpr_decide('approve'));
mpr_$('mpr-return').addEventListener('click', () => mpr_decide('return'));
// a click on the dark backdrop (not on the box) closes it
mpr_$('mpr-overlay').addEventListener('mousedown', e => { if (e.target === mpr_$('mpr-overlay')) mpr_close(); });
document.addEventListener('keydown', e => {
    if (!mpr_$('mpr-overlay').classList.contains('open')) return;
    if (e.key === 'Escape') { e.preventDefault(); mpr_close(); return; }
    if (e.key === 'Tab') {   // keep the focus inside the modal
        const els = [...mpr_$('mpr-overlay').querySelectorAll('button:not([hidden]):not([disabled]), a[href]')];
        if (!els.length) return;
        const first = els[0], last = els[els.length - 1];
        if (!mpr_$('mpr-overlay').contains(document.activeElement) || (e.shiftKey && document.activeElement === first)) { e.preventDefault(); (e.shiftKey ? last : first).focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
});
</script>
