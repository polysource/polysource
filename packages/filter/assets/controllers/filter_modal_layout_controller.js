import { Controller } from '@hotwired/stimulus';

/**
 * polysource--filter-modal-layout — reorganises EA's AJAX-loaded
 * filter form into tabs + groups according to a server-built
 * tree.
 *
 * Bound to `#modal-filters`. EA loads the form HTML via
 * `fetch().then(text => modalBody.innerHTML = text)` AFTER the
 * modal is shown, so we use a MutationObserver on `.modal-body`
 * to detect when the form's `.filter-field[data-filter-property]`
 * elements appear, then reshuffle them.
 *
 * The tree shape:
 *   {
 *     ungrouped: ["q", "name"],
 *     groups: [{label: "Status", properties: ["isActive"]}],
 *     tabs: [{
 *       label: "Visibility",
 *       ungrouped: [],
 *       groups: [{label: "Active state", properties: ["isVisible"]}]
 *     }]
 *   }
 *
 * Render order in the modal body:
 *   1. Top-level ungrouped (flat)
 *   2. Top-level groups (<details> accordions)
 *   3. Tabs (Bootstrap nav-tabs strip + tab-content with tab-panes)
 *      - Each tab pane: tab's ungrouped (flat) + tab's groups (accordions)
 *
 * Idempotent: if the form is reloaded, the controller detects
 * already-grouped DOM and skips re-shuffling.
 */
export default class extends Controller {
    static values = {
        tree: Object,
    };

    connect() {
        this.observer = new MutationObserver(this.onMutation.bind(this));
        // Watch the inner .modal-body — that's where EA injects the
        // form HTML on demand.
        const target = this.element.querySelector('.modal-body');
        if (target) {
            this.observer.observe(target, { childList: true, subtree: true });
            this.maybeApplyLayout();
        }
    }

    disconnect() {
        if (this.observer) {
            this.observer.disconnect();
            this.observer = null;
        }
    }

    onMutation() {
        this.maybeApplyLayout();
    }

    maybeApplyLayout() {
        const form = this.element.querySelector('.modal-body form');
        if (!form) return;
        if (form.dataset.polysourceLayoutApplied === '1') return;

        const filterFields = form.querySelectorAll(':scope > .filter-field[data-filter-property]');
        if (filterFields.length === 0) return;

        // Index filter rows by property for O(1) lookup.
        const byProperty = {};
        filterFields.forEach((node) => {
            byProperty[node.dataset.filterProperty] = node;
        });

        const tree = this.treeValue || { ungrouped: [], groups: [], tabs: [] };

        // Build the new layout in a documentFragment, then swap.
        const layout = document.createDocumentFragment();

        // 1. Top-level ungrouped — flat.
        (tree.ungrouped || []).forEach((property) => {
            const node = byProperty[property];
            if (node) layout.appendChild(node);
        });

        // 2. Top-level groups — <details> accordions.
        (tree.groups || []).forEach((group, idx) => {
            layout.appendChild(this.buildGroupAccordion(group, byProperty, idx === 0));
        });

        // 3. Tabs — Bootstrap nav-tabs.
        const tabs = tree.tabs || [];
        if (tabs.length > 0) {
            layout.appendChild(this.buildTabs(tabs, byProperty));
        }

        // Replace form contents (preserving hidden inputs).
        const hiddenInputs = Array.from(form.querySelectorAll(':scope > input[type="hidden"]'));
        // Preserve any non-filter-field non-hidden direct children
        // (action buttons, etc.) — append after our layout.
        const otherNodes = Array.from(form.children).filter(
            (n) => !n.matches('input[type="hidden"]') && !n.matches('.filter-field'),
        );

        form.innerHTML = '';
        hiddenInputs.forEach((n) => form.appendChild(n));
        form.appendChild(layout);
        otherNodes.forEach((n) => form.appendChild(n));

        form.dataset.polysourceLayoutApplied = '1';
    }

    buildGroupAccordion(group, byProperty, openByDefault) {
        const details = document.createElement('details');
        details.classList.add('polysource-filter-group');
        if (openByDefault) details.setAttribute('open', '');

        const summary = document.createElement('summary');
        summary.classList.add('polysource-filter-group__summary');

        const label = document.createElement('strong');
        label.textContent = group.label;
        summary.appendChild(label);

        const badge = document.createElement('span');
        badge.classList.add('badge', 'bg-secondary', 'ms-2');
        badge.textContent = String((group.properties || []).length);
        summary.appendChild(badge);

        details.appendChild(summary);

        const body = document.createElement('div');
        body.classList.add('polysource-filter-group__body');
        (group.properties || []).forEach((property) => {
            const node = byProperty[property];
            if (node) body.appendChild(node);
        });
        details.appendChild(body);

        return details;
    }

    buildTabs(tabs, byProperty) {
        const wrapper = document.createElement('div');
        wrapper.classList.add('polysource-filter-tabs');

        // <ul class="nav nav-tabs">
        const navList = document.createElement('ul');
        navList.classList.add('nav', 'nav-tabs', 'mb-3');
        navList.setAttribute('role', 'tablist');

        // Tab content container.
        const content = document.createElement('div');
        content.classList.add('tab-content');

        const uid = `polysource-tab-${Date.now()}-${Math.floor(Math.random() * 1000)}`;

        tabs.forEach((tab, idx) => {
            const tabId = `${uid}-${idx}`;
            const isActive = idx === 0;

            // Nav item
            const li = document.createElement('li');
            li.classList.add('nav-item');
            li.setAttribute('role', 'presentation');

            const button = document.createElement('button');
            button.type = 'button';
            button.classList.add('nav-link');
            if (isActive) button.classList.add('active');
            button.setAttribute('id', `${tabId}-tab`);
            button.setAttribute('data-bs-toggle', 'tab');
            button.setAttribute('data-bs-target', `#${tabId}-pane`);
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-controls', `${tabId}-pane`);
            button.setAttribute('aria-selected', isActive ? 'true' : 'false');
            button.textContent = tab.label;
            li.appendChild(button);
            navList.appendChild(li);

            // Tab pane
            const pane = document.createElement('div');
            pane.classList.add('tab-pane', 'fade');
            if (isActive) pane.classList.add('show', 'active');
            pane.setAttribute('id', `${tabId}-pane`);
            pane.setAttribute('role', 'tabpanel');
            pane.setAttribute('aria-labelledby', `${tabId}-tab`);

            // Pane content: tab.ungrouped (flat) + tab.groups (accordions)
            (tab.ungrouped || []).forEach((property) => {
                const node = byProperty[property];
                if (node) pane.appendChild(node);
            });
            (tab.groups || []).forEach((group, gIdx) => {
                pane.appendChild(this.buildGroupAccordion(group, byProperty, gIdx === 0));
            });

            content.appendChild(pane);
        });

        wrapper.appendChild(navList);
        wrapper.appendChild(content);
        return wrapper;
    }
}
