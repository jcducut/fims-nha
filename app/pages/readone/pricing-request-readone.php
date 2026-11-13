<?php
/**
 * pricing-request-readone.php
 *
 * Read-only "View" modal for one pricing request (fims.pricing_requests).
 * JS namespace: prqv_
 *
 *   prqv_open(id)   — fetches GET /pricing_requests/read/{id} (the record with its
 *                     project name, office names and supporting documents) and shows
 *                     it in the same sections as the entry form: Request, Office,
 *                     Routing & Turnaround, Remarks, Supporting Documents.
 *
 * The footer's Edit button closes this modal and hands the record to prq_edit()
 * (the entry form), so nothing is editable here. Delete calls prq_delete() (which
 * asks for confirmation) and closes this modal only once the record is gone.
 *
 * Included directly into pricing-page.php rather than registered as its own
 * routed page. Depends on prq_esc, prq_toast, prq_edit, prq_delete and the PRQ_API constant,
 * all defined in pricing-page.php.
 *
 * Self-contained styling (scoped under #prqv-card) so it renders correctly even if
 * the page's own stylesheet fails to load.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     PRICING REQUEST VIEW MODAL   JS namespace: prqv_
══════════════════════════════════════════════════════════ -->
<style>
    #prqv-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #prqv-overlay.open { display:flex; }
    #prqv-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:860px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif;
        animation:prqvIn .2s ease; }
    @keyframes prqvIn { from{opacity:0;transform:translateY(16px) scale(.98);} to{opacity:1;transform:none;} }

    #prqv-header { display:flex;align-items:center;justify-content:space-between;
        padding:16px 22px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #prqv-header-info { display:flex;align-items:center;gap:10px;min-width:0; }
    #prqv-header-info svg { width:18px;height:18px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .prqv-hdr-tag { font-size:10px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 2px;letter-spacing:.06em;text-transform:uppercase; }
    .prqv-hdr-title { font-size:14px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0;
        white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    #prqv-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
        color:var(--afims-text-disabled, #A9AAB8);font-size:18px;line-height:1;transition:background .15s; }
    #prqv-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #prqv-body { flex:1;min-height:0;overflow-y:auto;padding:22px; }
    #prqv-state { padding:28px 0;text-align:center;color:var(--afims-text-muted, #62657E);font-size:13px; }

    .prqv-section { font-size:11px;font-weight:700;color:var(--afims-text-muted, #62657E);text-transform:uppercase;
        letter-spacing:.06em;margin:0 0 12px;padding-bottom:6px;border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .prqv-section:not(:first-child) { margin-top:22px; }
    .prqv-grid { display:grid;gap:14px;grid-template-columns:repeat(3, 1fr); }
    .prqv-grid.c2 { grid-template-columns:repeat(2, 1fr); }
    .prqv-field { min-width:0; }
    .prqv-label { font-size:10px;font-weight:700;color:var(--afims-text-muted, #62657E);
        text-transform:uppercase;letter-spacing:.05em;margin:0 0 3px; }
    .prqv-val { font-size:13px;color:var(--afims-text, #1A1B21);margin:0;word-break:break-word; }
    .prqv-val.empty { color:var(--afims-text-disabled, #A9AAB8); }
    .prqv-val.mono { font-family:'IBM Plex Mono',monospace; }
    .prqv-sub { font-size:11px;color:var(--afims-text-muted, #62657E);margin:2px 0 0; }
    .prqv-remarks { font-size:13px;color:var(--afims-text, #1A1B21);margin:0;white-space:pre-wrap;word-break:break-word;
        padding:10px 12px;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;background:var(--afims-surface-subtle, #F4F7FA); }

    /* Computed day counts: same boxed look as the (disabled) fields in the entry form */
    .prqv-days { border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;padding:9px 12px;
        background:var(--afims-surface-subtle, #F4F7FA); }
    .prqv-days.total { border-color:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 30%, var(--afims-border, #E4ECF2)); }
    .prqv-days .prqv-val { font-weight:700;font-family:'IBM Plex Mono',monospace; }

    .prqv-doc-list { display:flex;flex-direction:column;border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden; }
    .prqv-doc-row { display:flex;align-items:center;gap:10px;padding:9px 12px;font-size:12.5px;
        border-bottom:1px solid var(--afims-border, #E4ECF2); }
    .prqv-doc-row:last-child { border-bottom:none; }
    .prqv-doc-name { flex:1;min-width:0;font-weight:600;color:var(--afims-text, #1A1B21);overflow:hidden;text-overflow:ellipsis;white-space:nowrap; }
    .prqv-doc-kind { font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;flex-shrink:0;
        color:var(--afims-text-muted, #62657E);background:var(--afims-surface-subtle, #F4F7FA);
        border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;padding:2px 7px; }
    .prqv-doc-open { font-size:11.5px;font-weight:600;color:var(--afims-heading-fill, #000E3E);text-decoration:none;white-space:nowrap;flex-shrink:0; }
    .prqv-doc-open:hover { text-decoration:underline; }
    .prqv-doc-none { font-size:12px;color:var(--afims-text-disabled, #A9AAB8);margin:0; }

    #prqv-footer { display:flex;align-items:center;justify-content:space-between;gap:8px;
        padding:14px 22px;background:var(--afims-surface-subtle, #F4F7FA);border-top:1px solid var(--afims-border, #E4ECF2);flex-shrink:0; }
    #prqv-footer .prqv-btn { height:36px;padding:0 20px;border-radius:8px;border:1px solid var(--afims-border, #E4ECF2);
        font-size:13px;font-weight:600;font-family:'IBM Plex Sans',sans-serif;cursor:pointer;transition:background .15s; }
    #prqv-footer .prqv-btn:disabled { opacity:.5;cursor:not-allowed; }
    #prqv-footer .prqv-btn-ghost { background:var(--afims-surface, #FFFFFF);color:var(--afims-text, #1A1B21); }
    #prqv-footer .prqv-btn-ghost:hover:not(:disabled) { background:var(--afims-surface-subtle, #F4F7FA); }
    #prqv-footer .prqv-btn-navy { background:var(--afims-heading-fill, #000E3E);border-color:var(--afims-heading-fill, #000E3E);color:var(--afims-pure-white, #FFFFFF); }
    #prqv-footer .prqv-btn-navy:hover:not(:disabled) { opacity:.92; }
    #prqv-footer .prqv-btn-danger { background:var(--afims-surface, #FFFFFF);border-color:var(--afims-danger, #DC3545);color:var(--afims-danger, #DC3545); }
    #prqv-footer .prqv-btn-danger:hover:not(:disabled) { background:var(--afims-danger-soft, #FAE1E3); }

    @media (max-width:720px) { .prqv-grid, .prqv-grid.c2 { grid-template-columns:1fr; } }
</style>

<div id="prqv-overlay">
    <div id="prqv-card">

        <div id="prqv-header">
            <div id="prqv-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div style="min-width:0;">
                    <p class="prqv-hdr-tag">Fund Sourcing Division — Pricing Request</p>
                    <p class="prqv-hdr-title" id="prqv-title">Pricing Request</p>
                </div>
            </div>
            <button type="button" id="prqv-close">&times;</button>
        </div>

        <div id="prqv-body">
            <div id="prqv-state">Loading…</div>
            <div id="prqv-content" style="display:none;"></div>
        </div>

        <div id="prqv-footer">
            <button type="button" class="prqv-btn prqv-btn-danger" id="prqv-btn-delete" disabled>Delete</button>
            <div style="display:flex;gap:8px;">
                <button type="button" class="prqv-btn prqv-btn-ghost" id="prqv-btn-close">Close</button>
                <button type="button" class="prqv-btn prqv-btn-navy" id="prqv-btn-edit" disabled>Edit</button>
            </div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   PRICING REQUEST VIEW MODAL   prefix: prqv_
═══════════════════════════════════════════════════════════════ */

const $prqv = id => document.getElementById(id);
let prqv_currentId = null;

(function () {
    const bind = () => {
        $prqv('prqv-close')?.addEventListener('click', prqv_close);
        $prqv('prqv-btn-close')?.addEventListener('click', prqv_close);
        $prqv('prqv-btn-edit')?.addEventListener('click', () => {
            const id = prqv_currentId;
            prqv_close();
            if (id) prq_edit(id);
        });
        $prqv('prqv-btn-delete')?.addEventListener('click', async () => {
            const id = prqv_currentId;
            if (!id) return;
            // prq_delete() asks for confirmation; keep the view open if it is declined or fails.
            if (await prq_delete(id)) prqv_close();
        });
        // Click on the dimmed backdrop (not the card) closes it
        $prqv('prqv-overlay')?.addEventListener('mousedown', ev => {
            if (ev.target === $prqv('prqv-overlay')) prqv_close();
        });
        document.addEventListener('keydown', ev => {
            if (ev.key === 'Escape' && $prqv('prqv-overlay')?.classList.contains('open')) prqv_close();
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bind);
    else bind();
})();

function prqv_close() {
    $prqv('prqv-overlay').classList.remove('open');
    prqv_currentId = null;
}

async function prqv_open(id) {
    prqv_currentId = null;
    $prqv('prqv-title').textContent = 'Pricing Request';
    $prqv('prqv-content').style.display = 'none';
    $prqv('prqv-content').innerHTML = '';
    $prqv('prqv-state').style.display = '';
    $prqv('prqv-state').textContent = 'Loading…';
    $prqv('prqv-btn-edit').disabled = true;
    $prqv('prqv-btn-delete').disabled = true;
    $prqv('prqv-overlay').classList.add('open');

    try {
        const res  = await fetch(`${PRQ_API}/read/${encodeURIComponent(id)}`, { credentials: 'same-origin' });
        const json = await res.json();
        if (!json.success) throw new Error(json.message || 'Failed to load the pricing request.');

        // The user may have closed the modal (or opened another record) while this was loading.
        if (!$prqv('prqv-overlay').classList.contains('open')) return;

        prqv_render(json.data);
        prqv_currentId = json.data.id;
        $prqv('prqv-btn-edit').disabled = false;
        $prqv('prqv-btn-delete').disabled = false;
    } catch (e) {
        $prqv('prqv-state').textContent = e.message;
        prq_toast(e.message, 'error');
    }
}

/* ── Formatting ────────────────────────────────────────────── */

// A field: label + value; blank values show a dim em dash.
function prqv_field(label, value, opts = {}) {
    const blank = value === null || value === undefined || value === '';
    const text  = blank ? '—' : prq_esc(value);
    const cls   = 'prqv-val' + (blank ? ' empty' : '') + (opts.mono && !blank ? ' mono' : '');
    return `<div class="prqv-field">
        <p class="prqv-label">${prq_esc(label)}</p>
        <p class="${cls}">${text}</p>
        ${opts.sub ? `<p class="prqv-sub">${prq_esc(opts.sub)}</p>` : ''}
    </div>`;
}

// YYYY-MM-DD -> "Nov 12, 2026" (parsed as a local date so the day never shifts by timezone)
function prqv_date(v) {
    if (!v) return '';
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(v));
    if (!m) return String(v);
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return isNaN(d) ? String(v) : d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function prqv_days(label, value, sub, total = false) {
    const blank = value === null || value === undefined || value === '';
    return `<div class="prqv-days${total ? ' total' : ''}">
        <p class="prqv-label">${prq_esc(label)}</p>
        <p class="prqv-val${blank ? ' empty' : ''}">${blank ? '—' : prq_esc(value) + (Number(value) === 1 ? ' day' : ' days')}</p>
        <p class="prqv-sub">${prq_esc(sub)}</p>
    </div>`;
}

function prqv_money(v) {
    if (v === null || v === undefined || v === '') return '';
    const n = Number(v);
    return isNaN(n) ? String(v) : n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Only http(s) links are made clickable — a pasted link is free text.
function prqv_safeUrl(u) {
    return /^https?:\/\//i.test(String(u || '').trim()) ? String(u).trim() : '';
}

function prqv_docsHtml(docs) {
    if (!docs || !docs.length) return '<p class="prqv-doc-none">No supporting documents attached.</p>';
    return `<div class="prqv-doc-list">${docs.map(a => {
        const isFile = !!a.storage_key;
        const url    = prqv_safeUrl(a.view_url);
        return `<div class="prqv-doc-row">
            <span class="prqv-doc-name" title="${prq_esc(a.document_name)}">${prq_esc(a.document_name) || '—'}</span>
            <span class="prqv-doc-kind">${isFile ? 'File' : 'Link'}</span>
            ${url ? `<a class="prqv-doc-open" href="${prq_esc(url)}" target="_blank" rel="noopener">Open ↗</a>` : ''}
        </div>`;
    }).join('')}</div>`;
}

/* ── Render ────────────────────────────────────────────────── */
function prqv_render(r) {
    $prqv('prqv-title').textContent = r.project_name || r.project_code || 'Pricing Request';

    const office = (name, id) => name || (id ? `Office #${id}` : '');

    $prqv('prqv-content').innerHTML = `
        <p class="prqv-section">Request</p>
        <div class="prqv-grid c2" style="margin-bottom:14px;">
            ${prqv_field('Project Name', r.project_name || r.project_code, { sub: r.project_name ? r.project_code : '' })}
            ${prqv_field('Type of Request', r.type_of_request)}
        </div>
        <div class="prqv-grid" style="margin-bottom:14px;">
            ${prqv_field('Status', r.status)}
            ${prqv_field('Signed By', r.signed_by)}
            ${prqv_field('Assigned', r.assigned_to)}
        </div>
        <div class="prqv-grid">
            ${prqv_field('Unsold Inventories', prqv_money(r.unsold_inventories), { mono: true })}
        </div>

        <p class="prqv-section">Office</p>
        <div class="prqv-grid c2">
            ${prqv_field('Regional Office', office(r.regional_office_name, r.regional_office_id))}
            ${prqv_field('District Office', office(r.district_office_name, r.district_office_id))}
        </div>

        <p class="prqv-section">Routing &amp; Turnaround</p>
        <div class="prqv-grid" style="margin-bottom:14px;">
            ${prqv_field('Date Received', prqv_date(r.date_received))}
            ${prqv_field('Date Received of Complete Documents (a)', prqv_date(r.date_received_complete_docs))}
            ${prqv_field('Date Forwarded to FSG (b)', prqv_date(r.date_forwarded_fsg))}
        </div>
        <div class="prqv-grid" style="margin-bottom:14px;">
            ${prqv_field('Date Forwarded to OGM (d)', prqv_date(r.date_forwarded_ogm))}
            ${prqv_field('Date Approved by OGM (f)', prqv_date(r.date_approved_ogm))}
            <div></div>
        </div>
        <div class="prqv-grid">
            ${prqv_days('Days with FMD (c)', r.days_with_fmd, '(a) to (b)')}
            ${prqv_days('Days with FSG (e)', r.days_with_fsg, '(b) to (d)')}
            ${prqv_days('Days with OGM (g)', r.days_with_ogm, '(d) to (f)')}
        </div>
        <div class="prqv-grid" style="margin-top:14px;">
            ${prqv_days('Total No. of Days (h)', r.total_days, '(c) + (e) + (g)', true)}
        </div>

        <p class="prqv-section">Remarks</p>
        ${r.remarks
            ? `<p class="prqv-remarks">${prq_esc(r.remarks)}</p>`
            : '<p class="prqv-val empty">—</p>'}

        <p class="prqv-section">Supporting Documents</p>
        ${prqv_docsHtml(r.attachments)}
    `;

    $prqv('prqv-state').style.display = 'none';
    $prqv('prqv-content').style.display = '';
}
</script>
