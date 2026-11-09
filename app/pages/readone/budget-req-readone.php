<?php
/**
 * budget-req-readone.php
 *
 * Read-only "View" modal for one Program (fims.budget_req_program) within
 * a budget request. Lists that Program's Projects as an exclusive
 * accordion — expanding one Project panel auto-collapses whichever was
 * open before — each panel revealing that Project's chart-of-accounts
 * line items on expand.
 *
 * Trigger: bgrv_openModal(requestContext, program) — called from the list
 * page's "View" row action, where:
 *   requestContext = { budget_reference_number, fiscal_yr, status_name, status_color, is_draft }
 *   program        = { budget_req_program_id, program_code, program_name, sub_program_name, projects[] }
 * Both come straight from the already-fetched list data — this modal does
 * NOT re-fetch anything itself.
 *
 * Included directly into budget-req-page.php rather than registered as its
 * own routed page — this is a modal, not a separate page. Read-only: use
 * the list row's own "Edit" action to edit a Program.
 *
 * Depends on bgr_esc, bgr_fmtMoney, and bgr_statusBadge, all defined in
 * form/budget-req-form.php, which must be included on the same page.
 */
?>

<!-- ══════════════════════════════════════════════════════════
     PROGRAM VIEW MODAL   JS namespace: bgrv_
══════════════════════════════════════════════════════════ -->
<style>
    #bgrv-overlay { display:none;position:fixed;inset:0;z-index:9100;
        background:rgba(15,32,69,.6);align-items:center;justify-content:center;padding:16px;backdrop-filter:blur(3px); }
    #bgrv-overlay.open { display:flex; }
    #bgrv-card { background:var(--afims-surface, #FFFFFF);border-radius:16px;width:100%;max-width:820px;
        max-height:92vh;display:flex;flex-direction:column;overflow:hidden;
        box-shadow:0 32px 80px rgba(0,0,0,.28);font-family:'IBM Plex Sans',sans-serif; }

    #bgrv-header { display:flex;align-items:center;justify-content:space-between;
        padding:8px 14px;background:var(--afims-heading-fill, #000E3E);flex-shrink:0; }
    #bgrv-header-info { display:flex;align-items:center;gap:7px;min-width:0; }
    #bgrv-header-info svg { width:12px;height:12px;stroke:var(--afims-text-disabled, #A9AAB8);stroke-width:1.8;flex-shrink:0; }
    .bgrv-hdr-tag { font-size:7px;color:var(--afims-text-disabled, #A9AAB8);margin:0 0 1px;letter-spacing:.06em;text-transform:uppercase; }
    .bgrv-hdr-title { font-size:10px;font-weight:700;color:var(--afims-pure-white, #FFFFFF);margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
    #bgrv-header-actions { display:flex;align-items:center;gap:8px;flex-shrink:0; }
    #bgrv-close { border:none;background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 12%, transparent);cursor:pointer;
        width:22px;height:22px;border-radius:6px;display:flex;align-items:center;justify-content:center;
        color:var(--afims-text-disabled, #A9AAB8);font-size:14px;line-height:1;transition:background .15s; }
    #bgrv-close:hover { background:color-mix(in srgb, var(--afims-surface, #FFFFFF) 22%, transparent);color:var(--afims-pure-white, #FFFFFF); }

    #bgrv-body { flex:1;min-height:0;overflow-y:auto;padding:14px; }

    #bgrv-summary { display:grid;grid-template-columns:repeat(4, 1fr);gap:7px;margin-bottom:12px; }
    .bgrv-sum-card { border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;padding:6px 8px;background:var(--afims-surface-subtle, #F4F7FA); }
    .bgrv-sum-label { font-size:8px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px; }
    .bgrv-sum-val { font-size:10px;font-weight:700;color:var(--afims-text, #1A1B21); }
    @media (max-width: 640px) { #bgrv-summary { grid-template-columns:repeat(2, 1fr); } }

    .bgrv-sec-hd { font-size:8px;font-weight:700;color:var(--afims-text, #1A1B21);
        text-transform:uppercase;letter-spacing:.05em;margin:0 0 6px; }

    #bgrv-panels { display:flex;flex-direction:column;gap:6px; }
    .bgrv-panel { border:1px solid var(--afims-border, #E4ECF2);border-radius:8px;overflow:hidden;
        transition:box-shadow .15s,border-color .15s; }
    .bgrv-panel.open { box-shadow:0 2px 8px rgba(15,32,69,.08);border-color:color-mix(in srgb, var(--afims-heading-fill, #000E3E) 25%, var(--afims-border, #E4ECF2)); }
    .bgrv-panel-hd { display:flex;align-items:center;justify-content:space-between;gap:8px;
        padding:6px 8px;background:var(--afims-surface-subtle, #F4F7FA);cursor:pointer;transition:background .15s; }
    .bgrv-panel-hd:hover { background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 60%, var(--afims-border, #E4ECF2)); }
    .bgrv-panel-hd-main { display:flex;align-items:center;gap:6px;min-width:0; }
    .bgrv-panel-chevron { flex-shrink:0;width:10px;height:10px;color:var(--afims-heading-fill, #000E3E);transition:transform .15s; }
    .bgrv-panel.open .bgrv-panel-chevron { transform:rotate(90deg); }
    .bgrv-panel-title { font-size:10px;font-weight:700;color:var(--afims-text, #1A1B21); }
    .bgrv-panel-hd-meta { display:flex;align-items:center;gap:8px;flex-shrink:0; }
    .bgrv-panel-meta-item { font-size:8px;color:var(--afims-text-disabled, #A9AAB8);white-space:nowrap; }
    .bgrv-panel-meta-amt { font-size:9px;font-weight:700;color:var(--afims-text, #1A1B21);white-space:nowrap;font-family:'IBM Plex Mono',monospace; }

    .bgrv-panel-body { display:none;padding:0 8px 8px; }
    .bgrv-panel.open .bgrv-panel-body { display:block; }

    .bgrv-li-tbl { width:100%;border-collapse:collapse;border:1px solid var(--afims-border, #E4ECF2);border-radius:6px;overflow:hidden; }
    .bgrv-li-tbl th { font-size:6.5px;font-weight:700;color:var(--afims-text-disabled, #A9AAB8);
        text-transform:uppercase;letter-spacing:.03em;text-align:left;padding:4px 6px;line-height:1.3;
        background:var(--afims-surface-subtle, #F4F7FA);border-bottom:1px solid var(--afims-border, #E4ECF2);white-space:nowrap; }
    .bgrv-li-tbl td { font-size:7.5px;line-height:1.3;padding:4px 6px;border-bottom:1px solid var(--afims-border, #E4ECF2);color:var(--afims-text, #1A1B21); }
    .bgrv-li-tbl tbody tr:last-child td { border-bottom:none; }
    .bgrv-li-tbl tbody tr:nth-child(even) { background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 45%, transparent); }
    .bgrv-li-tbl tbody tr:hover { background:color-mix(in srgb, var(--afims-surface-subtle, #F4F7FA) 85%, transparent); }
    .bgrv-li-tbl .text-end { text-align:right;font-family:'IBM Plex Mono',monospace;font-weight:600; }

    #bgrv-empty { padding:18px 0;text-align:center;color:var(--afims-text-disabled, #A9AAB8);font-size:10px; }
</style>

<div id="bgrv-overlay">
    <div id="bgrv-card">
        <div id="bgrv-header">
            <div id="bgrv-header-info">
                <svg fill="none" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <div style="min-width:0;">
                    <p class="bgrv-hdr-tag">Program</p>
                    <p class="bgrv-hdr-title" id="bgrv-modal-title">Program</p>
                </div>
            </div>
            <div id="bgrv-header-actions">
                <button type="button" id="bgrv-close">&times;</button>
            </div>
        </div>

        <div id="bgrv-body">
            <div id="bgrv-summary">
                <div class="bgrv-sum-card">
                    <div class="bgrv-sum-label">Fiscal Year</div>
                    <div class="bgrv-sum-val" id="bgrv-sum-fiscal-yr">—</div>
                </div>
                <div class="bgrv-sum-card">
                    <div class="bgrv-sum-label">Status</div>
                    <div class="bgrv-sum-val" id="bgrv-sum-status">—</div>
                </div>
                <div class="bgrv-sum-card">
                    <div class="bgrv-sum-label"># Projects</div>
                    <div class="bgrv-sum-val" id="bgrv-sum-projects">—</div>
                </div>
                <div class="bgrv-sum-card">
                    <div class="bgrv-sum-label">Total Proposed Amount</div>
                    <div class="bgrv-sum-val" id="bgrv-sum-total">—</div>
                </div>
            </div>

            <p class="bgrv-sec-hd">Projects</p>
            <div id="bgrv-panels"></div>
            <div id="bgrv-empty" style="display:none;">No projects have been added to this program yet.</div>
        </div>
    </div>
</div>

<script>
/* ══════════════════════════════════════════════════════════════
   PROGRAM VIEW MODAL   prefix: bgrv_
   Depends on bgr_esc / bgr_fmtMoney / bgr_statusBadge / bgr_openModal from
   form/budget-req-form.php, included on the same page. Takes already-
   fetched data — no fetch of its own.
═══════════════════════════════════════════════════════════════ */

const $bgrv = id => document.getElementById(id);

document.addEventListener('DOMContentLoaded', () => {
    $bgrv('bgrv-close')?.addEventListener('click', bgrv_closeModal);
});

function bgrv_openModal(requestContext, program) {
    bgrv_render(requestContext, program);
    $bgrv('bgrv-overlay').classList.add('open');
}

function bgrv_closeModal() {
    $bgrv('bgrv-overlay').classList.remove('open');
}

function bgrv_render(requestContext, program) {
    $bgrv('bgrv-modal-title').textContent = `${program.program_code} — ${program.program_name}`;
    $bgrv('bgrv-sum-fiscal-yr').textContent = requestContext.fiscal_yr ?? '—';
    $bgrv('bgrv-sum-status').innerHTML = bgr_statusBadge(requestContext.status_name, requestContext.status_color);

    const projects = program.projects ?? [];
    let grandTotal = 0;

    const panelsHtml = projects.map((project, idx) => {
        const lineItems = project.line_items ?? [];

        const rowsHtml = lineItems.map(li => `<tr>
            <td>${bgr_esc(li.account_code)}</td>
            <td>${bgr_esc(li.account_title)}</td>
            <td>${bgr_esc(li.particulars)}</td>
            <td class="text-end">${bgr_fmtMoney(li.proposed_amount)}</td>
        </tr>`).join('');

        const subtotal = lineItems.reduce((sum, li) => sum + Number(li.proposed_amount || 0), 0);
        grandTotal += subtotal;

        return `<div class="bgrv-panel${idx === 0 ? ' open' : ''}" data-panel-id="${project.budget_req_project_id}">
            <div class="bgrv-panel-hd" onclick="bgrv_togglePanel(${project.budget_req_project_id})">
                <div class="bgrv-panel-hd-main">
                    <svg class="bgrv-panel-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    <div class="bgrv-panel-title">${bgr_esc(project.project_code)} — ${bgr_esc(project.project_name)}</div>
                </div>
                <div class="bgrv-panel-hd-meta">
                    <span class="bgrv-panel-meta-item">${lineItems.length} line item(s)</span>
                    <span class="bgrv-panel-meta-amt">${bgr_fmtMoney(subtotal)}</span>
                </div>
            </div>
            <div class="bgrv-panel-body">
                <table class="bgrv-li-tbl">
                    <thead><tr><th>Account Code</th><th>Account Name</th><th>Particulars</th><th>Proposed Amount</th></tr></thead>
                    <tbody>${rowsHtml}</tbody>
                </table>
            </div>
        </div>`;
    }).join('');

    $bgrv('bgrv-panels').innerHTML = panelsHtml;
    $bgrv('bgrv-panels').style.display = projects.length ? '' : 'none';
    $bgrv('bgrv-empty').style.display = projects.length ? 'none' : '';
    $bgrv('bgrv-sum-projects').textContent = projects.length;
    $bgrv('bgrv-sum-total').textContent = bgr_fmtMoney(grandTotal);
}

// Exclusive accordion — expanding one panel auto-collapses whichever was open.
function bgrv_togglePanel(panelId) {
    document.querySelectorAll('#bgrv-panels .bgrv-panel').forEach(p => {
        if (p.dataset.panelId === String(panelId)) {
            p.classList.toggle('open');
        } else {
            p.classList.remove('open');
        }
    });
}
</script>
