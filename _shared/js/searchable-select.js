/**
 * Turns a plain text input into a searchable select: typing filters a list of
 * {value, label} options already loaded in memory (no server round-trip), clicking a
 * match sets the input's value and fires onSelect(option, input). One function, reused
 * wherever a page needs "type to filter, click to pick" over a list it already has —
 * e.g. the DV module's Chart of Accounts / SL Code pickers (app/pages/voucher/form.php,
 * app/pages/voucher/readone.php).
 *
 * The dropdown is appended to <body> and positioned with `fixed` from the input's live
 * getBoundingClientRect() — not appended as a sibling of the input — so it's never clipped
 * by an ancestor `overflow-x:auto` wrapper (e.g. a horizontally-scrolling table) the way an
 * absolutely-positioned sibling would be, and it works even if the input isn't attached to
 * the document yet when this is called (its position is only read when the dropdown opens).
 *
 * Usage:
 *   nhaSearchableSelect(inputEl, [{value:'5-02-13-060', label:'Office Supplies Expense'}, ...]);
 *   // later, to refresh the option list on the same input:
 *   nhaSearchableSelect(inputEl, newOptions);
 */
function nhaSearchableSelect(input, options, onSelect) {
    input._nhaOptions = options || [];

    if (input._nhaBound) return;
    input._nhaBound = true;
    input.autocomplete = 'off';

    const box = document.createElement('div');
    box.className = 'nha-ss-dropdown';
    document.body.appendChild(box);

    const esc = s => String(s ?? '').replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

    function position() {
        const r = input.getBoundingClientRect();
        box.style.position = 'fixed';
        box.style.left  = r.left + 'px';
        box.style.top   = (r.bottom + 2) + 'px';
        box.style.width = Math.max(r.width, 220) + 'px';
    }

    function render(query) {
        const q = (query || '').trim().toLowerCase();
        const opts = input._nhaOptions || [];
        const matches = (q
            ? opts.filter(o => o.value.toLowerCase().includes(q) || o.label.toLowerCase().includes(q))
            : opts
        ).slice(0, 50);

        box.innerHTML = matches.length
            ? matches.map(o => `<div class="nha-ss-opt" data-value="${esc(o.value)}">
                   <span class="nha-ss-opt-code">${esc(o.value)}</span>
                   <span class="nha-ss-opt-label">${esc(o.label)}</span>
               </div>`).join('')
            : '<div class="nha-ss-empty">No matches</div>';
        position();
        box.style.display = 'block';
    }

    function hide() { box.style.display = 'none'; }

    input.addEventListener('input', () => render(input.value));
    input.addEventListener('focus', () => render(input.value));
    // capture:true so this fires on scroll inside ANY ancestor scroll container, not just window
    // (scroll events don't bubble, but they do propagate in the capture phase)
    window.addEventListener('scroll', () => { if (box.style.display === 'block') position(); }, true);
    window.addEventListener('resize', () => { if (box.style.display === 'block') position(); });
    box.addEventListener('mousedown', (e) => {
        const row = e.target.closest('.nha-ss-opt');
        if (!row) return;
        e.preventDefault();
        const value = row.dataset.value;
        input.value = value;
        hide();
        const opt = (input._nhaOptions || []).find(o => o.value === value);
        if (onSelect) onSelect(opt, input);
        input.dispatchEvent(new Event('change', { bubbles: true }));
    });
    document.addEventListener('click', (e) => {
        if (e.target !== input && !box.contains(e.target)) hide();
    });
}
