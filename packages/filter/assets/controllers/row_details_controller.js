import { Controller } from '@hotwired/stimulus';

/**
 * polysource--row-details — lazy expandable row details (v1.1.0).
 *
 * Bound to the chevron `<a>` rendered by the RowDetailField cell
 * template. Without this controller the anchor is a working link to
 * the standalone detail page (ADR-027 baseline); with it, the click
 * is intercepted and the detail is fetched from `urlValue` (the
 * `?fragment=1` endpoint) and injected as a `<tr>` directly under
 * the row.
 *
 * States (mirrored on the injected row's
 * `data-polysource-row-detail-state` attribute for styling/tests):
 *
 *   collapsed → loading → expanded
 *                  ↘ error (local message + retry, listing intact)
 *
 * First successful response is kept and re-shown on subsequent
 * expansions; `reloadValue: true` (RowDetailField::reloadOnOpen())
 * re-fetches on every open. Several rows can be open at once — each
 * chevron owns exactly one detail row.
 */
export default class extends Controller {
    static targets = ['icon'];

    static values = {
        url: String,
        reload: { type: Boolean, default: false },
        expandLabel: String,
        collapseLabel: String,
        loadingLabel: { type: String, default: 'Loading…' },
        errorLabel: { type: String, default: 'Failed to load details.' },
        retryLabel: { type: String, default: 'Retry' },
    };

    connect() {
        this.state = 'collapsed';
        this.detailRow = null;
        this.cachedHtml = null;
        this.abortController = null;
    }

    disconnect() {
        this.abortController?.abort();
        this.detailRow?.remove();
        this.detailRow = null;
    }

    /**
     * Click handler for the chevron. stopPropagation keeps EA's
     * clickable-row / row-action handlers from also firing.
     * @param {Event} event
     */
    toggle(event) {
        event.preventDefault();
        event.stopPropagation();

        if (this.state === 'loading') return;

        if (this.state === 'expanded' || this.state === 'error') {
            this.collapse();
            return;
        }

        if (this.cachedHtml !== null && !this.reloadValue) {
            this.show(this.cachedHtml);
            return;
        }

        this.load();
    }

    async load(url = null) {
        this.setState('loading');
        this.ensureDetailRow();
        this.detailCell().innerHTML = '';
        this.detailCell().append(this.buildNotice(this.loadingLabelValue));

        this.abortController?.abort();
        this.abortController = new AbortController();

        try {
            const response = await fetch(url ?? this.urlValue, {
                headers: { Accept: 'text/html' },
                signal: this.abortController.signal,
            });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            const html = await response.text();
            this.cachedHtml = html;
            this.show(html);
        } catch (error) {
            if (error.name === 'AbortError') return;
            this.showError();
        }
    }

    show(html) {
        this.ensureDetailRow();
        this.detailCell().innerHTML = html;
        this.setState('expanded');
    }

    showError() {
        this.ensureDetailRow();
        const cell = this.detailCell();
        cell.innerHTML = '';

        const notice = this.buildNotice(this.errorLabelValue);
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'btn btn-sm btn-outline-secondary ms-2 polysource-row-detail-retry';
        retry.textContent = this.retryLabelValue;
        retry.addEventListener('click', () => this.load(), { once: true });
        notice.append(retry);
        cell.append(notice);

        this.setState('error');
    }

    collapse() {
        // hide, don't destroy — the cached HTML survives for the
        // next expansion (unless reloadValue re-fetches).
        this.detailRow?.remove();
        this.detailRow = null;
        this.setState('collapsed');
    }

    setState(state) {
        this.state = state;
        const open = state === 'expanded' || state === 'loading' || state === 'error';
        this.element.setAttribute('aria-expanded', open ? 'true' : 'false');
        const label = open ? this.collapseLabelValue : this.expandLabelValue;
        if (label) this.element.setAttribute('aria-label', label);
        if (this.hasIconTarget) {
            this.iconTarget.textContent = open ? '▾' : '▸';
        }
        this.detailRow?.setAttribute('data-polysource-row-detail-state', state);
    }

    ensureDetailRow() {
        if (this.detailRow) return;

        const hostRow = this.element.closest('tr');
        if (!hostRow) return;

        const row = document.createElement('tr');
        row.className = 'polysource-row-detail-row';
        const cell = document.createElement('td');
        cell.colSpan = hostRow.cells.length;
        cell.className = 'polysource-row-detail-content';
        // Embedded-listing pagination: pager links inside the panel
        // (marked data-polysource-embed-nav) refresh the panel in
        // place instead of navigating. Delegated on the cell so it
        // survives innerHTML swaps; plain navigation remains the
        // no-JS baseline.
        cell.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-polysource-embed-nav]');
            if (!link) return;
            event.preventDefault();
            event.stopPropagation();
            this.load(link.href);
        });
        row.append(cell);
        hostRow.after(row);
        this.detailRow = row;
    }

    detailCell() {
        return this.detailRow.querySelector('td');
    }

    buildNotice(text) {
        const p = document.createElement('p');
        p.className = 'text-body-secondary mb-0 py-2 polysource-row-detail-notice';
        p.textContent = text;
        return p;
    }
}
