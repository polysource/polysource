/* polysource/easyadmin-filter-bridge — filter-button defensive shim.
 *
 * Published to `public/bundles/polysourceeasyadminfilterbridge/` by
 * `assets:install` and loaded from the bridge's
 * `crud/index.html.twig` override (`configured_body_contents`).
 *
 * Kicks in ONLY when EasyAdmin's bundled app.js didn't manage to
 * bind the filter-button handler. That's the case in Turbo-enabled
 * stacks where navigation swaps `<body>` without re-firing
 * `DOMContentLoaded`, so `#createFilters()` never runs again, the
 * button stays `class="disabled"`, and clicking opens an empty
 * modal.
 *
 * If EA's app DID init (`window.EasyAdminApp` is defined and the
 * button is no longer disabled), we yield to it. Earlier versions
 * of this script bound a second click handler unconditionally,
 * racing with EA's: both fetches ran and the second clobbered the
 * modal AFTER `#createFilterToggles()` had already attached the
 * auto-tick `change` listener — the user saw filters silently fail
 * to apply because their checkbox never auto-ticked.
 */
(function () {
    const init = () => {
        const btn = document.querySelector('.datagrid-filters .action-filters-button');
        if (!btn || btn.dataset.polysourceBound === '1') return;

        // Yield to EA's app.js when it's already wired the
        // button. Two signals (either is sufficient): the
        // `disabled` class is removed by `#createFilters()`,
        // and `window.EasyAdminApp` is the App instance.
        const eaWiredButton = !btn.classList.contains('disabled')
            || (typeof window !== 'undefined' && window.EasyAdminApp);
        if (eaWiredButton) {
            btn.dataset.polysourceBound = '1';
            return;
        }

        const target = btn.getAttribute('data-bs-target');
        const modal = target ? document.querySelector(target) : null;
        const dataHref = btn.getAttribute('data-href') || btn.getAttribute('href');
        if (!modal || !dataHref) return;

        btn.classList.remove('disabled');
        btn.setAttribute('href', dataHref);
        btn.removeAttribute('data-href');
        btn.dataset.polysourceBound = '1';

        btn.addEventListener('click', (event) => {
            const body = modal.querySelector('.modal-body');
            if (!body) return;
            body.innerHTML = '<div class="text-center p-4 text-muted"><i class="fas fa-spinner fa-spin"></i></div>';
            fetch(dataHref, { credentials: 'same-origin' })
                .then((r) => r.text())
                .then((html) => {
                    body.innerHTML = html;
                    // Re-bind EA's auto-tick listener so users
                    // typing in a value field tick the matching
                    // checkbox automatically. Mirrors EA's
                    // `#createFilterToggles` body since we
                    // can't call the private method directly.
                    body.querySelectorAll('form[data-ea-filters-form-id]').forEach((form) => {
                        if (form.dataset.polysourceAutotick === '1') return;
                        form.dataset.polysourceAutotick = '1';
                        form.addEventListener('change', (e) => {
                            const t = e.target;
                            if (!t || t.classList.contains('filter-checkbox')) return;
                            const field = t.closest('.filter-field');
                            if (!field) return;
                            const cb = field.querySelector('.filter-checkbox');
                            if (cb && !cb.checked) cb.checked = true;
                        });
                    });
                })
                .catch((err) => {
                    body.innerHTML = '<div class="alert alert-danger m-3">Failed to load filters: ' + (err && err.message ? err.message : 'unknown error') + '</div>';
                });
            event.preventDefault();
        });
    };
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    document.addEventListener('turbo:load', init);
})();
