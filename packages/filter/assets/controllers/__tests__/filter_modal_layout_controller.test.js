import { afterEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import FilterModalLayoutController from '../filter_modal_layout_controller.js';

let runningApp = null;

async function bootApp(html) {
    document.body.innerHTML = html;
    const app = Application.start();
    app.register('polysource--filter-modal-layout', FilterModalLayoutController);
    runningApp = app;
    await new Promise((resolve) => queueMicrotask(resolve));
    return app;
}

/**
 * Builds a minimal modal-filters DOM with a form already populated
 * (skipping EA's AJAX flow — we directly inject the .filter-field
 * children to test the reorganisation logic).
 */
function modalHtml(properties, treeJson) {
    const fieldsHtml = properties
        .map(
            (p) =>
                `<div class="filter-field" data-filter-property="${p}"><label>${p}</label></div>`,
        )
        .join('');
    return `
        <div id="modal-filters"
             data-controller="polysource--filter-modal-layout"
             data-polysource--filter-modal-layout-tree-value='${treeJson}'>
            <div class="modal-body">
                <form>${fieldsHtml}</form>
            </div>
        </div>
    `;
}

describe('polysource--filter-modal-layout Stimulus controller', () => {
    afterEach(() => {
        runningApp?.stop();
        runningApp = null;
        document.body.innerHTML = '';
    });

    it('leaves the form flat when the tree has only ungrouped properties', async () => {
        const tree = JSON.stringify({
            ungrouped: ['name', 'q'],
            groups: [],
            tabs: [],
        });
        await bootApp(modalHtml(['name', 'q'], tree));

        // Wait for MutationObserver to fire.
        await new Promise((r) => setTimeout(r, 20));

        const form = document.querySelector('form');
        const fields = form.querySelectorAll('.filter-field');
        expect(fields.length).toBe(2);
        expect(fields[0].dataset.filterProperty).toBe('name');
        expect(fields[1].dataset.filterProperty).toBe('q');
        expect(form.dataset.polysourceLayoutApplied).toBe('1');
    });

    it('builds <details> accordions for top-level groups', async () => {
        const tree = JSON.stringify({
            ungrouped: [],
            groups: [
                { label: 'Status', properties: ['isActive', 'isPublished'] },
            ],
            tabs: [],
        });
        await bootApp(modalHtml(['isActive', 'isPublished'], tree));
        await new Promise((r) => setTimeout(r, 20));

        const form = document.querySelector('form');
        const groups = form.querySelectorAll('details.polysource-filter-group');
        expect(groups.length).toBe(1);
        expect(groups[0].querySelector('summary strong').textContent).toBe('Status');
        expect(groups[0].hasAttribute('open')).toBe(true); // first group open
        expect(groups[0].querySelectorAll('.filter-field').length).toBe(2);
    });

    it('builds Bootstrap nav-tabs when tabs exist', async () => {
        const tree = JSON.stringify({
            ungrouped: [],
            groups: [],
            tabs: [
                {
                    label: 'Visibility',
                    ungrouped: ['flag1'],
                    groups: [{ label: 'Active state', properties: ['isVisible'] }],
                },
                {
                    label: 'Dates',
                    ungrouped: ['createdAt'],
                    groups: [],
                },
            ],
        });
        await bootApp(modalHtml(['flag1', 'isVisible', 'createdAt'], tree));
        await new Promise((r) => setTimeout(r, 20));

        const form = document.querySelector('form');
        const navTabs = form.querySelectorAll('ul.nav.nav-tabs li.nav-item');
        expect(navTabs.length).toBe(2);
        expect(navTabs[0].querySelector('button').textContent).toBe('Visibility');
        expect(navTabs[1].querySelector('button').textContent).toBe('Dates');

        const panes = form.querySelectorAll('.tab-pane');
        expect(panes.length).toBe(2);
        expect(panes[0].classList.contains('show')).toBe(true);
        expect(panes[0].classList.contains('active')).toBe(true);
        expect(panes[1].classList.contains('show')).toBe(false);

        // Visibility pane: 1 ungrouped + 1 accordion
        expect(panes[0].querySelectorAll(':scope > .filter-field').length).toBe(1);
        expect(panes[0].querySelectorAll('details.polysource-filter-group').length).toBe(1);

        // Dates pane: 1 ungrouped, 0 accordion
        expect(panes[1].querySelectorAll(':scope > .filter-field').length).toBe(1);
        expect(panes[1].querySelectorAll('details.polysource-filter-group').length).toBe(0);
    });

    it('preserves hidden inputs at the form root', async () => {
        const tree = JSON.stringify({
            ungrouped: ['name'],
            groups: [],
            tabs: [],
        });
        document.body.innerHTML = `
            <div id="modal-filters"
                 data-controller="polysource--filter-modal-layout"
                 data-polysource--filter-modal-layout-tree-value='${tree}'>
                <div class="modal-body">
                    <form>
                        <input type="hidden" name="csrf_token" value="abc">
                        <div class="filter-field" data-filter-property="name"><label>name</label></div>
                    </form>
                </div>
            </div>
        `;
        const app = Application.start();
        app.register('polysource--filter-modal-layout', FilterModalLayoutController);
        runningApp = app;
        await new Promise((r) => setTimeout(r, 20));

        const form = document.querySelector('form');
        expect(form.querySelector('input[type="hidden"][name="csrf_token"]')).toBeTruthy();
        expect(form.querySelector('.filter-field')).toBeTruthy();
    });

    it('is idempotent — does not reapply layout on subsequent mutations', async () => {
        const tree = JSON.stringify({
            ungrouped: ['name'],
            groups: [],
            tabs: [],
        });
        await bootApp(modalHtml(['name'], tree));
        await new Promise((r) => setTimeout(r, 20));

        const form = document.querySelector('form');
        expect(form.dataset.polysourceLayoutApplied).toBe('1');

        // Simulate a no-op DOM mutation.
        form.appendChild(document.createElement('span'));
        await new Promise((r) => setTimeout(r, 20));

        // Layout still marked applied — controller did NOT reshuffle.
        expect(form.dataset.polysourceLayoutApplied).toBe('1');
        // The span we added should still be there (not stripped by a re-layout).
        expect(form.querySelector('span')).toBeTruthy();
    });
});
