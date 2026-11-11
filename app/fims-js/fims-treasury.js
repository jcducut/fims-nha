(function () {
  function closeDds() {
    document.querySelectorAll('.fims-dd.open').forEach(d => d.classList.remove('open'));
  }

  // Bootstrap modals
  document.addEventListener('show.bs.modal', closeDds);

  // Custom modal overlays/backdrops – watch style attribute changes
  const styleObserver = new MutationObserver(mutations => {
    for (const m of mutations) {
      const el = m.target;
      if (el.style.display && el.style.display !== 'none') closeDds();
    }
  });

  function observeModals(root) {
    root.querySelectorAll('[class*="modal-overlay"],[class*="modal-backdrop"]').forEach(el => {
      styleObserver.observe(el, { attributes: true, attributeFilter: ['style'] });
    });
  }

  // Observe modals already in DOM + any added later
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('.content-header')?.style.setProperty('display', 'none', 'important');
    observeModals(document);
    new MutationObserver(mutations => {
      for (const m of mutations) {
        m.addedNodes.forEach(node => {
          if (node.nodeType !== 1) return;
          if (typeof node.className === 'string' &&
              (node.className.includes('modal-overlay') || node.className.includes('modal-backdrop'))) {
            styleObserver.observe(node, { attributes: true, attributeFilter: ['style'] });
          }
          observeModals(node);
        });
      }
    }).observe(document.body, { childList: true, subtree: true });
  });
})();
