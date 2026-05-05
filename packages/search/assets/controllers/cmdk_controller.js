/**
 * polysource/search — Cmd+K palette controller.
 *
 * Stimulus controller mounted on the palette overlay. Listens
 * globally for Cmd+K / Ctrl+K / "/" to open the dialog. Debounces
 * the input, fetches `/admin/search?q=…`, groups results per
 * resource, navigates to `result.href` on Enter or click.
 */
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['overlay', 'dialog', 'input', 'results', 'empty'];
    static values = {
        endpoint: { type: String, default: '/admin/search' },
        debounce: { type: Number, default: 150 },
    };

    connect() {
        this.handleGlobalKeydown = this.handleGlobalKeydown.bind(this);
        document.addEventListener('keydown', this.handleGlobalKeydown);
        this.activeIndex = -1;
        this.results = [];
        this.debounceTimer = null;
    }

    disconnect() {
        document.removeEventListener('keydown', this.handleGlobalKeydown);
    }

    handleGlobalKeydown(event) {
        if ((event.metaKey || event.ctrlKey) && event.key === 'k') {
            event.preventDefault();
            this.open();
            return;
        }
        if (event.key === '/' && !this.isTypingTarget(event.target)) {
            event.preventDefault();
            this.open();
        }
    }

    isTypingTarget(el) {
        if (!el) return false;
        const tag = (el.tagName || '').toUpperCase();
        return tag === 'INPUT' || tag === 'TEXTAREA' || el.isContentEditable;
    }

    open() {
        this.overlayTarget.hidden = false;
        this.dialogTarget.hidden = false;
        this.inputTarget.value = '';
        this.results = [];
        this.activeIndex = -1;
        this.renderResults();
        requestAnimationFrame(() => this.inputTarget.focus());
    }

    close() {
        this.overlayTarget.hidden = true;
        this.dialogTarget.hidden = true;
    }

    queryChanged() {
        clearTimeout(this.debounceTimer);
        this.debounceTimer = setTimeout(() => this.fetch(), this.debounceValue);
    }

    keydown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.close();
            return;
        }
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            this.move(1);
            return;
        }
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            this.move(-1);
            return;
        }
        if (event.key === 'Enter') {
            event.preventDefault();
            this.activate();
        }
    }

    async fetch() {
        const q = this.inputTarget.value.trim();
        if (!q) {
            this.results = [];
            this.activeIndex = -1;
            this.renderResults();
            return;
        }

        try {
            const url = `${this.endpointValue}?q=${encodeURIComponent(q)}`;
            const res = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!res.ok) {
                this.results = [];
                this.renderResults();
                return;
            }
            const data = await res.json();
            this.results = Array.isArray(data.results) ? data.results : [];
            this.activeIndex = this.results.length > 0 ? 0 : -1;
            this.renderResults();
        } catch (_) {
            this.results = [];
            this.renderResults();
        }
    }

    move(delta) {
        if (this.results.length === 0) return;
        this.activeIndex = (this.activeIndex + delta + this.results.length) % this.results.length;
        this.renderResults();
    }

    activate() {
        if (this.activeIndex < 0 || this.activeIndex >= this.results.length) return;
        const target = this.results[this.activeIndex];
        if (target && target.href) {
            window.location.assign(target.href);
        }
    }

    renderResults() {
        this.resultsTarget.innerHTML = '';
        if (this.results.length === 0) {
            this.emptyTarget.hidden = false;
            return;
        }
        this.emptyTarget.hidden = true;

        let lastResource = null;
        this.results.forEach((r, idx) => {
            if (r.resource !== lastResource) {
                const header = document.createElement('li');
                header.className = 'polysource-search-palette-group';
                header.textContent = r.resource;
                this.resultsTarget.appendChild(header);
                lastResource = r.resource;
            }

            const li = document.createElement('li');
            li.className = 'polysource-search-palette-row';
            if (idx === this.activeIndex) li.classList.add('active');
            li.dataset.index = String(idx);
            li.innerHTML = `<a href="${this.escape(r.href)}">${this.escape(r.label)}</a>`;
            li.addEventListener('mouseenter', () => {
                this.activeIndex = idx;
                this.renderResults();
            });
            li.addEventListener('click', () => {
                this.activeIndex = idx;
                this.activate();
            });
            this.resultsTarget.appendChild(li);
        });
    }

    escape(s) {
        return String(s).replace(/[&<>"']/g, (c) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        }[c]));
    }
}
