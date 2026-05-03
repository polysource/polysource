import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import PolysourceFilterController from '../polysource_filter_controller.js';

/**
 * Boot a Stimulus application that registers the controller under its
 * canonical name and waits one microtask for connect() to fire.
 */
// Set by the describe-block beforeEach so bootApp can register the
// running Application for cleanup.
let runningApp = null;

async function bootApp(html) {
    document.body.innerHTML = html;
    const app = Application.start();
    app.register('polysource--filter', PolysourceFilterController);
    runningApp = app;
    // Stimulus connects asynchronously via MutationObserver; flush.
    await new Promise((resolve) => queueMicrotask(resolve));
    return app;
}

/**
 * Render a wrapper that mimics the bridge form theme: the
 * comparison/value/value2 inputs as the upstream EA blocks would
 * produce them, plus the data-controller and trigger buttons.
 */
function wrapperHtml({ withValue2 = true, presetButtons = [], quickRangeButtons = [], inputType = 'datetime-local', showClear = false } = {}) {
    const presetMarkup = presetButtons.map(p =>
        `<button type="button" data-action="polysource--filter#applyPreset" data-polysource--filter-preset-param="${p}">${p}</button>`
    ).join('');
    const quickRangeMarkup = quickRangeButtons.map(({ min, max }) => {
        const minAttr = min === null || min === undefined ? '' : String(min);
        const maxAttr = max === null || max === undefined ? '' : String(max);
        return `<button type="button" data-action="polysource--filter#applyQuickRange" data-polysource--filter-min-param="${minAttr}" data-polysource--filter-max-param="${maxAttr}">${min ?? ''}-${max ?? ''}</button>`;
    }).join('');
    const clearMarkup = showClear
        ? `<button type="button" data-action="polysource--filter#clearValues">Clear</button>`
        : '';
    const value2Markup = withValue2
        ? `<input type="${inputType}" name="filters[stub][value2]" id="value2">`
        : '';

    return `
        <div data-controller="polysource--filter">
            <select name="filters[stub][comparison]" id="cmp">
                <option value="=">=</option>
                <option value="&gt;=">&gt;=</option>
                <option value="&lt;=">&lt;=</option>
                <option value="between">between</option>
            </select>
            <input type="${inputType}" name="filters[stub][value]" id="value">
            ${value2Markup}
            ${presetMarkup}
            ${quickRangeMarkup}
            ${clearMarkup}
        </div>
    `;
}

describe('polysource--filter Stimulus controller', () => {
    beforeEach(() => {
        // Pin the clock so date presets are deterministic.
        // 2026-05-15 (Friday) — middle of May, same year as the rest
        // of the project's "current date" convention.
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 4, 15, 12, 0, 0)); // months are 0-indexed
    });

    afterEach(() => {
        // Stop the Stimulus Application or its MutationObserver keeps
        // observing the document — when the next test inserts a
        // wrapper with `data-controller`, *every* leftover app
        // re-connects a controller, causing duplicate event handlers
        // and inflated call counts.
        runningApp?.stop();
        runningApp = null;
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    describe('applyPreset', () => {
        it('today → comparison `=`, value = today', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['today'], inputType: 'date' }));

            document.querySelector('[data-polysource--filter-preset-param="today"]').click();

            expect(document.querySelector('#cmp').value).toBe('=');
            expect(document.querySelector('#value').value).toBe('2026-05-15');
            expect(document.querySelector('#value2').value).toBe('');
        });

        it('last_7_days → comparison `between`, value = today-6, value2 = today', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['last_7_days'], inputType: 'date' }));

            document.querySelector('[data-polysource--filter-preset-param="last_7_days"]').click();

            expect(document.querySelector('#cmp').value).toBe('between');
            expect(document.querySelector('#value').value).toBe('2026-05-09');
            expect(document.querySelector('#value2').value).toBe('2026-05-15');
        });

        it('this_month → from 1st of month, to today', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['this_month'], inputType: 'date' }));

            document.querySelector('[data-polysource--filter-preset-param="this_month"]').click();

            expect(document.querySelector('#cmp').value).toBe('between');
            expect(document.querySelector('#value').value).toBe('2026-05-01');
            expect(document.querySelector('#value2').value).toBe('2026-05-15');
        });

        it('last_month → from 1st previous month, to last day previous month', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['last_month'], inputType: 'date' }));

            document.querySelector('[data-polysource--filter-preset-param="last_month"]').click();

            expect(document.querySelector('#cmp').value).toBe('between');
            expect(document.querySelector('#value').value).toBe('2026-04-01');
            expect(document.querySelector('#value2').value).toBe('2026-04-30');
        });

        it('unknown preset (e.g. "custom") → no-op', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['custom'], inputType: 'date' }));

            const before = {
                cmp: document.querySelector('#cmp').value,
                value: document.querySelector('#value').value,
                value2: document.querySelector('#value2').value,
            };
            document.querySelector('[data-polysource--filter-preset-param="custom"]').click();

            expect(document.querySelector('#cmp').value).toBe(before.cmp);
            expect(document.querySelector('#value').value).toBe(before.value);
            expect(document.querySelector('#value2').value).toBe(before.value2);
        });

        it('formats datetime-local inputs with the right precision', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['today'], inputType: 'datetime-local' }));

            document.querySelector('[data-polysource--filter-preset-param="today"]').click();

            // datetime-local format: YYYY-MM-DDTHH:mm — `startOfDay` resets the
            // time to 00:00 so we expect midnight.
            expect(document.querySelector('#value').value).toBe('2026-05-15T00:00');
        });
    });

    describe('applyQuickRange', () => {
        it('both bounds set → comparison `between`, value=min, value2=max', async () => {
            await bootApp(wrapperHtml({
                quickRangeButtons: [{ min: 50, max: 200 }],
                inputType: 'number',
            }));

            document.querySelector('[data-polysource--filter-min-param="50"]').click();

            expect(document.querySelector('#cmp').value).toBe('between');
            expect(document.querySelector('#value').value).toBe('50');
            expect(document.querySelector('#value2').value).toBe('200');
        });

        it('only max set → comparison `<=`, value=max, value2 cleared', async () => {
            await bootApp(wrapperHtml({
                quickRangeButtons: [{ min: null, max: 50 }],
                inputType: 'number',
            }));

            document.querySelector('[data-polysource--filter-max-param="50"]').click();

            expect(document.querySelector('#cmp').value).toBe('<=');
            expect(document.querySelector('#value').value).toBe('50');
            expect(document.querySelector('#value2').value).toBe('');
        });

        it('only min set → comparison `>=`, value=min, value2 cleared', async () => {
            await bootApp(wrapperHtml({
                quickRangeButtons: [{ min: 200, max: null }],
                inputType: 'number',
            }));

            document.querySelector('[data-polysource--filter-min-param="200"]').click();

            expect(document.querySelector('#cmp').value).toBe('>=');
            expect(document.querySelector('#value').value).toBe('200');
            expect(document.querySelector('#value2').value).toBe('');
        });

        it('no value2 input present → only fills `value`', async () => {
            await bootApp(wrapperHtml({
                quickRangeButtons: [{ min: 50, max: 200 }],
                inputType: 'number',
                withValue2: false,
            }));

            document.querySelector('[data-polysource--filter-min-param="50"]').click();

            // With no value2, even "between" semantics fall back to `>=`.
            expect(document.querySelector('#cmp').value).toBe('>=');
            expect(document.querySelector('#value').value).toBe('50');
            expect(document.querySelector('#value2')).toBeNull();
        });
    });

    describe('clearValues', () => {
        it('empties value and value2, keeps comparison untouched', async () => {
            await bootApp(wrapperHtml({ showClear: true, inputType: 'date' }));
            document.querySelector('#cmp').value = 'between';
            document.querySelector('#value').value = '2026-05-01';
            document.querySelector('#value2').value = '2026-05-15';

            document.querySelector('[data-action="polysource--filter#clearValues"]').click();

            expect(document.querySelector('#cmp').value).toBe('between'); // unchanged
            expect(document.querySelector('#value').value).toBe('');
            expect(document.querySelector('#value2').value).toBe('');
        });
    });

    describe('change event propagation', () => {
        it('dispatches a `change` event on the comparison select after applying a preset', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['today'], inputType: 'date' }));
            const cmp = document.querySelector('#cmp');
            const onChange = vi.fn();
            cmp.addEventListener('change', onChange);

            document.querySelector('[data-polysource--filter-preset-param="today"]').click();

            // Two events: `setSelectValue` does NOT emit change directly,
            // but the controller dispatches one explicitly at the end so
            // upstream EA's `data-ea-value2-of-comparison-id` handler can
            // toggle visibility.
            expect(onChange).toHaveBeenCalled();
        });

        it('dispatches `input` and `change` events on each filled input', async () => {
            await bootApp(wrapperHtml({ presetButtons: ['today'], inputType: 'date' }));
            const value = document.querySelector('#value');
            const inputHandler = vi.fn();
            const changeHandler = vi.fn();
            value.addEventListener('input', inputHandler);
            value.addEventListener('change', changeHandler);

            document.querySelector('[data-polysource--filter-preset-param="today"]').click();

            expect(inputHandler).toHaveBeenCalledTimes(1);
            expect(changeHandler).toHaveBeenCalledTimes(1);
        });
    });
});
