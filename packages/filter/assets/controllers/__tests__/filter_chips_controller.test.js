import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import FilterChipsController from '../filter_chips_controller.js';

let runningApp = null;

async function bootApp(html, url = 'http://localhost/admin/product?filters[name][value]=hat&filters[price][value]=50') {
    document.body.innerHTML = html;
    // Pin the URL so removeChip's URL surgery is deterministic.
    Object.defineProperty(window, 'location', {
        configurable: true,
        writable: true,
        value: {
            href: url,
            assign: vi.fn(),
        },
    });
    const app = Application.start();
    app.register('polysource--filter-chips', FilterChipsController);
    runningApp = app;
    await new Promise((resolve) => queueMicrotask(resolve));
    return app;
}

function chipsHtml({ chips = [], hasOverflow = false } = {}) {
    const visible = chips.slice(0, 7);
    const overflow = chips.slice(7);
    const visibleMarkup = visible.map(p =>
        `<span data-polysource--filter-chips-target="chip" data-property="${p}">
            <button data-action="polysource--filter-chips#removeChip" data-polysource--filter-chips-property-param="${p}"></button>
        </span>`
    ).join('');
    const overflowMarkup = (hasOverflow || overflow.length)
        ? `<button data-action="polysource--filter-chips#expandOverflow" data-polysource--filter-chips-target="overflowToggle"></button>
           <span class="d-none" data-polysource--filter-chips-target="overflow">
              ${overflow.map(p => `<span data-polysource--filter-chips-target="chip" data-property="${p}"><button data-action="polysource--filter-chips#removeChip" data-polysource--filter-chips-property-param="${p}"></button></span>`).join('')}
           </span>`
        : '';
    return `<div data-controller="polysource--filter-chips" data-polysource--filter-chips-collection-id-value="scope-1">
        ${visibleMarkup}
        ${overflowMarkup}
    </div>`;
}

describe('polysource--filter-chips Stimulus controller', () => {
    afterEach(() => {
        runningApp?.stop();
        runningApp = null;
        document.body.innerHTML = '';
    });

    describe('removeChip', () => {
        it('removes all `filters[<property>][...]` query params on click', async () => {
            await bootApp(chipsHtml({ chips: ['name', 'price'] }));
            const removeButton = document.querySelector('[data-polysource--filter-chips-property-param="name"]');

            removeButton.click();

            // window.location.assign was called with a URL stripped of name's filter.
            const calledWith = window.location.assign.mock.calls[0][0];
            expect(calledWith).not.toContain('filters%5Bname%5D');
            expect(calledWith).not.toContain('filters[name]');
            // Price filter still in the URL.
            expect(calledWith).toMatch(/filters(\[|%5B)price/);
        });

        it('handles multiple keys for the same property (between case)', async () => {
            await bootApp(
                chipsHtml({ chips: ['createdAt'] }),
                'http://localhost/admin/product?filters[createdAt][value]=2026-01-01&filters[createdAt][value2]=2026-12-31&filters[createdAt][comparison]=between',
            );
            const removeButton = document.querySelector('[data-polysource--filter-chips-property-param="createdAt"]');

            removeButton.click();

            const calledWith = window.location.assign.mock.calls[0][0];
            expect(calledWith).not.toMatch(/filters(\[|%5B)createdAt/);
        });
    });

    describe('expandOverflow', () => {
        it('toggles the overflow region visibility', async () => {
            await bootApp(chipsHtml({ chips: ['p1','p2','p3','p4','p5','p6','p7','p8','p9'] }));
            const overflow = document.querySelector('[data-polysource--filter-chips-target="overflow"]');
            const toggle = document.querySelector('[data-polysource--filter-chips-target="overflowToggle"]');

            expect(overflow.classList.contains('d-none')).toBe(true);

            toggle.click();

            expect(overflow.classList.contains('d-none')).toBe(false);
            expect(toggle.getAttribute('aria-expanded')).toBe('true');

            toggle.click();
            expect(overflow.classList.contains('d-none')).toBe(true);
            expect(toggle.getAttribute('aria-expanded')).toBe('false');
        });

        it('is a no-op when there is no overflow target', async () => {
            await bootApp(chipsHtml({ chips: ['only'] })); // 1 chip, no overflow
            // The controller is bound but expandOverflow has nothing to toggle —
            // it shouldn't throw if called.
            const ctl = runningApp.getControllerForElementAndIdentifier(
                document.querySelector('[data-controller="polysource--filter-chips"]'),
                'polysource--filter-chips',
            );
            expect(() => ctl.expandOverflow(new Event('click'))).not.toThrow();
        });
    });
});
