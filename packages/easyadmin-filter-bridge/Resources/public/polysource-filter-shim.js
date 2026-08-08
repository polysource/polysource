/* polysource/easyadmin-filter-bridge — filter-button defensive shim.
 *
 * Published to `public/bundles/polysourceeasyadminfilterbridge/` by
 * `assets:install` and loaded from the bridge's
 * `crud/index.html.twig` override (`configured_body_contents`).
 *
 * Kicks in ONLY when EasyAdmin's bundled app.js didn't manage to
 * bind the filter handlers. That's the case in Turbo-enabled stacks
 * where navigation swaps `<body>` without re-firing
 * `DOMContentLoaded`, so `#createFilters()` never runs again, the
 * button stays `class="disabled"`, and clicking opens an empty
 * modal.
 *
 * TWO hard-earned rules govern this file:
 *
 * 1. NEVER race EasyAdmin's own init. EA initializes on
 *    `DOMContentLoaded`; this shim therefore decides only after the
 *    `load` event (plus a macrotask), when EA has provably had its
 *    chance. Deciding at script-parse time is a coin flip: when the
 *    shim won the race it consumed the button's `data-href`, EA's
 *    `#createFilters()` then read a null `data-href` and bailed out
 *    early — so EA never bound `#modal-apply-button` and the user's
 *    "Apply" click silently did nothing (2026-08 host regression).
 *    The detection signal is `data-href` itself: EA's init consumes
 *    it (moves it to `href`), so if it is still present after `load`,
 *    EA did not run.
 *
 * 2. If the shim takes over, it must take over the WHOLE contract:
 *    open+fetch on the filter button, auto-tick on value change, AND
 *    the Apply / Clear buttons. A partial takeover leaves dead
 *    buttons with no console error — the worst kind of failure.
 *    The handlers below mirror EA's `#createFilters()` semantics
 *    (same selectors, same remove-then-submit flow).
 */
(function () {
    // Mirrors EA's removeFilter closure: strip a filter's inputs from
    // the form (so an unticked filter is not submitted), then drop the
    // field block itself. Kept selector-identical to EA so behaviour
    // does not depend on which binder (EA or shim) is active.
    const removeFilter = (filterField) => {
        const form = filterField.closest('form');
        if (form) {
            form.querySelectorAll('input[name^="filters[' + filterField.dataset.filterProperty + ']"]')
                .forEach((filterFieldInput) => filterFieldInput.remove());
        }
        filterField.remove();
    };

    const takeOver = (btn) => {
        const target = btn.getAttribute('data-bs-target');
        const modal = target ? document.querySelector(target) : null;
        const dataHref = btn.getAttribute('data-href') || btn.getAttribute('href');
        if (!modal || !dataHref) return;

        btn.classList.remove('disabled');
        btn.setAttribute('href', dataHref);
        btn.removeAttribute('data-href');

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

        // Rule 2: the modal footer buttons are part of the contract.
        // Mirrors EA's `#createFilters()` apply/clear handlers.
        const applyButton = document.querySelector('#modal-apply-button');
        if (applyButton && applyButton.dataset.polysourceBound !== '1') {
            applyButton.dataset.polysourceBound = '1';
            applyButton.addEventListener('click', () => {
                modal.querySelectorAll('.filter-checkbox:not(:checked)').forEach((notAppliedFilter) => {
                    const field = notAppliedFilter.closest('.filter-field');
                    if (field) removeFilter(field);
                });
                const form = modal.querySelector('form');
                if (form) form.submit();
            });
        }

        const clearButton = document.querySelector('#modal-clear-button');
        if (clearButton && clearButton.dataset.polysourceBound !== '1') {
            clearButton.dataset.polysourceBound = '1';
            clearButton.addEventListener('click', () => {
                modal.querySelectorAll('.filter-field').forEach((filterField) => {
                    removeFilter(filterField);
                });
                const form = modal.querySelector('form');
                if (form) form.submit();
            });
        }
    };

    const init = () => {
        const btn = document.querySelector('.datagrid-filters .action-filters-button');
        if (!btn || btn.dataset.polysourceBound === '1') return;

        // Rule 1: EA's `#createFilters()` consumes `data-href` (moves it
        // to `href`) — its absence after `load` means EA bound the
        // button and the whole modal contract: yield unconditionally.
        // `window.EasyAdminApp` is checked as a secondary signal for
        // exotic hosts that inline the button without data-href.
        const eaWiredButton = !btn.hasAttribute('data-href')
            || (!btn.classList.contains('disabled') && typeof window !== 'undefined' && window.EasyAdminApp);
        if (eaWiredButton) {
            btn.dataset.polysourceBound = '1';
            return;
        }

        btn.dataset.polysourceBound = '1';
        takeOver(btn);
    };

    // Decide strictly AFTER EasyAdmin's DOMContentLoaded init had its
    // chance: wait for `load` + one macrotask. On Turbo navigations
    // `load` doesn't re-fire, hence the turbo:load hook (the original
    // audience of this shim).
    const scheduleInit = () => setTimeout(init, 0);
    if (document.readyState === 'complete') {
        scheduleInit();
    } else {
        window.addEventListener('load', scheduleInit, { once: true });
    }
    document.addEventListener('turbo:load', scheduleInit);
})();
