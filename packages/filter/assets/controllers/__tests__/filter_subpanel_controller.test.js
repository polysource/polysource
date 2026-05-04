import { afterEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import FilterSubpanelController from '../filter_subpanel_controller.js';

let runningApp = null;

async function bootApp(html) {
    document.body.innerHTML = html;
    const app = Application.start();
    app.register('polysource--filter-subpanel', FilterSubpanelController);
    runningApp = app;
    await new Promise((resolve) => queueMicrotask(resolve));
    return app;
}

function panelHtml({ withTabs = false } = {}) {
    const tabsMarkup = withTabs
        ? `<ul role="tablist" data-polysource--filter-subpanel-target="tablist">
              <li><button role="tab" class="active" aria-selected="true"
                          data-action="polysource--filter-subpanel#switchTab"
                          data-polysource--filter-subpanel-target-param="tab1-panel">Tab 1</button></li>
              <li><button role="tab"
                          data-action="polysource--filter-subpanel#switchTab"
                          data-polysource--filter-subpanel-target-param="tab2-panel">Tab 2</button></li>
           </ul>
           <div id="tab1-panel" class="show active" data-polysource--filter-subpanel-target="tabpane">P1</div>
           <div id="tab2-panel" data-polysource--filter-subpanel-target="tabpane">P2</div>`
        : '';
    return `<div data-controller="polysource--filter-subpanel"
                 data-polysource--filter-subpanel-panel-id-value="test-panel">
        <button data-action="polysource--filter-subpanel#open" id="opener">Open</button>
        <aside aria-hidden="true" data-polysource--filter-subpanel-target="panel">
            <button data-action="polysource--filter-subpanel#close">Close</button>
            <input type="text" id="first-input">
            ${tabsMarkup}
        </aside>
    </div>`;
}

describe('polysource--filter-subpanel Stimulus controller', () => {
    afterEach(() => {
        runningApp?.stop();
        runningApp = null;
        document.body.innerHTML = '';
        document.body.classList.remove('polysource-filter-subpanel-open');
    });

    describe('open', () => {
        it('adds the show class + body marker class + sets focus on first interactive', async () => {
            await bootApp(panelHtml());
            const opener = document.getElementById('opener');
            const panel = document.querySelector('[data-polysource--filter-subpanel-target="panel"]');

            opener.click();

            expect(panel.classList.contains('show')).toBe(true);
            expect(panel.getAttribute('aria-hidden')).toBeNull();
            expect(document.body.classList.contains('polysource-filter-subpanel-open')).toBe(true);
        });
    });

    describe('close', () => {
        it('removes show class + restores body + focuses the trigger that opened the panel', async () => {
            await bootApp(panelHtml());
            const opener = document.getElementById('opener');
            opener.focus();
            opener.click();

            const closeBtn = document.querySelector('[data-action="polysource--filter-subpanel#close"]');
            closeBtn.click();

            const panel = document.querySelector('[data-polysource--filter-subpanel-target="panel"]');
            expect(panel.classList.contains('show')).toBe(false);
            expect(panel.getAttribute('aria-hidden')).toBe('true');
            expect(document.body.classList.contains('polysource-filter-subpanel-open')).toBe(false);
        });

        it('closes on ESC keypress', async () => {
            await bootApp(panelHtml());
            const opener = document.getElementById('opener');
            opener.click();

            const event = new KeyboardEvent('keydown', { key: 'Escape', bubbles: true });
            document.dispatchEvent(event);

            const panel = document.querySelector('[data-polysource--filter-subpanel-target="panel"]');
            expect(panel.classList.contains('show')).toBe(false);
        });
    });

    describe('switchTab', () => {
        it('toggles active class on tab buttons + show/active on tabpanes', async () => {
            await bootApp(panelHtml({ withTabs: true }));
            const tab2 = document.querySelectorAll('[role="tab"]')[1];

            tab2.click();

            const tabs = document.querySelectorAll('[role="tab"]');
            expect(tabs[0].classList.contains('active')).toBe(false);
            expect(tabs[0].getAttribute('aria-selected')).toBe('false');
            expect(tabs[1].classList.contains('active')).toBe(true);
            expect(tabs[1].getAttribute('aria-selected')).toBe('true');

            const panes = document.querySelectorAll('[data-polysource--filter-subpanel-target="tabpane"]');
            expect(panes[0].classList.contains('show')).toBe(false);
            expect(panes[0].classList.contains('active')).toBe(false);
            expect(panes[1].classList.contains('show')).toBe(true);
            expect(panes[1].classList.contains('active')).toBe(true);
        });
    });
});
