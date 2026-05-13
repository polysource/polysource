import { afterEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import PolysourceFilterController from '../polysource_filter_controller.js';

/**
 * Since v0.2.0 the bridge's `polysource--filter` Stimulus controller
 * is value-only — no action methods. The tests pin that contract:
 *
 *  1. The controller connects without error to a wrapper that carries
 *     the documented `data-polysource--filter-*-value` attributes.
 *  2. None of the legacy v0.1.x action names (`applyPreset`,
 *     `applyQuickRange`, `clearValues`) appear on the class — they were
 *     removed along with the `presets`, `quick_ranges`, and `show_clear`
 *     form options.
 *
 * Earlier v0.1.x action-suite tests (preset arithmetic, quick-range
 * fan-out, clear-values behaviour) were removed in the same commit as
 * the form options they exercised — those features are no longer part
 * of the bridge's surface.
 */
let runningApp = null;

async function bootApp(html) {
    document.body.innerHTML = html;
    const app = Application.start();
    app.register('polysource--filter', PolysourceFilterController);
    runningApp = app;
    await new Promise((resolve) => queueMicrotask(resolve));
    return app;
}

describe('polysource--filter Stimulus controller', () => {
    afterEach(() => {
        runningApp?.stop();
        runningApp = null;
        document.body.innerHTML = '';
    });

    it('connects without error to a wrapper carrying typed data values', async () => {
        await bootApp(`
            <div data-controller="polysource--filter"
                 data-polysource--filter-step-value="0.5"
                 data-polysource--filter-min-length-value="3"
                 data-polysource--filter-include-null-value="true"
                 data-polysource--filter-comparisons-value='["=","&gt;="]'>
            </div>
        `);

        // No assertion to make about behaviour — the contract is "this
        // page renders without a Stimulus error". If `bootApp` resolved
        // and no exception bubbled, we're good.
        expect(document.querySelector('[data-controller="polysource--filter"]')).not.toBeNull();
    });

    it('does not expose v0.1.x action handlers', () => {
        const proto = PolysourceFilterController.prototype;
        expect(proto.applyPreset).toBeUndefined();
        expect(proto.applyQuickRange).toBeUndefined();
        expect(proto.clearValues).toBeUndefined();
    });
});
