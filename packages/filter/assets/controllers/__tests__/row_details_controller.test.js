import { afterEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import RowDetailsController from '../row_details_controller.js';

/**
 * polysource--row-details contract:
 *
 *  - no fetch during initial render (lazy by design);
 *  - expand: fetch the fragment URL, inject a detail <tr> under the
 *    host row, aria-expanded=true;
 *  - collapse: remove the row, aria-expanded=false;
 *  - re-expand: cached — NO second fetch (unless reload-value=true);
 *  - HTTP error: local error state + retry button, listing intact.
 */
let runningApp = null;

const ROW_HTML = `
    <table>
        <tbody>
            <tr id="host-row">
                <td>
                    <a href="/admin/polysource/row-detail/App%5CEntity%5COrder/1"
                       id="chevron"
                       aria-expanded="false"
                       data-controller="polysource--row-details"
                       data-polysource--row-details-url-value="/admin/polysource/row-detail/App%5CEntity%5COrder/1?fragment=1"
                       data-polysource--row-details-expand-label-value="Show details"
                       data-polysource--row-details-collapse-label-value="Hide details"
                       data-polysource--row-details-error-label-value="Failed to load details."
                       data-polysource--row-details-retry-label-value="Retry"
                       data-action="polysource--row-details#toggle">
                        <span data-polysource--row-details-target="icon">▸</span>
                    </a>
                </td>
                <td>Order #1</td>
                <td>ACTIVE</td>
            </tr>
        </tbody>
    </table>
`;

const RELOAD_ROW_HTML = ROW_HTML.replace(
    'data-polysource--row-details-url-value',
    'data-polysource--row-details-reload-value="true"\n data-polysource--row-details-url-value',
);

async function bootApp(html) {
    document.body.innerHTML = html;
    const app = Application.start();
    app.register('polysource--row-details', RowDetailsController);
    runningApp = app;
    await new Promise((resolve) => queueMicrotask(resolve));
    return app;
}

function okResponse(html) {
    return Promise.resolve({ ok: true, status: 200, text: () => Promise.resolve(html) });
}

function errorResponse(status = 500) {
    return Promise.resolve({ ok: false, status, text: () => Promise.resolve('') });
}

async function clickChevron() {
    document.querySelector('#chevron').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
    await vi.waitFor(() => {
        const row = document.querySelector('.polysource-row-detail-row');
        const state = row?.getAttribute('data-polysource-row-detail-state');
        if (row && state === 'loading') throw new Error('still loading');
    });
}

describe('polysource--row-details Stimulus controller', () => {
    afterEach(() => {
        runningApp?.stop();
        runningApp = null;
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('does not fetch during initial render', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        await bootApp(ROW_HTML);

        expect(fetchMock).not.toHaveBeenCalled();
        expect(document.querySelector('.polysource-row-detail-row')).toBeNull();
    });

    it('expands: fetches the fragment and injects a detail row spanning the host row', async () => {
        const fetchMock = vi.fn(() => okResponse('<p id="detail-content">details here</p>'));
        vi.stubGlobal('fetch', fetchMock);

        await bootApp(ROW_HTML);
        await clickChevron();

        expect(fetchMock).toHaveBeenCalledOnce();
        expect(fetchMock.mock.calls[0][0]).toContain('fragment=1');

        const detailRow = document.querySelector('#host-row + tr.polysource-row-detail-row');
        expect(detailRow).not.toBeNull();
        expect(detailRow.getAttribute('data-polysource-row-detail-state')).toBe('expanded');
        expect(detailRow.querySelector('td').colSpan).toBe(3);
        expect(document.querySelector('#detail-content')).not.toBeNull();
        expect(document.querySelector('#chevron').getAttribute('aria-expanded')).toBe('true');
    });

    it('collapses on second click and reuses the cache on the third (no refetch)', async () => {
        const fetchMock = vi.fn(() => okResponse('<p id="detail-content">cached</p>'));
        vi.stubGlobal('fetch', fetchMock);

        await bootApp(ROW_HTML);
        await clickChevron();

        // collapse
        document.querySelector('#chevron').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        expect(document.querySelector('.polysource-row-detail-row')).toBeNull();
        expect(document.querySelector('#chevron').getAttribute('aria-expanded')).toBe('false');

        // re-expand — from cache
        await clickChevron();
        expect(document.querySelector('#detail-content')).not.toBeNull();
        expect(fetchMock).toHaveBeenCalledOnce();
    });

    it('re-fetches on every open when reload-value is true', async () => {
        const fetchMock = vi.fn(() => okResponse('<p>fresh</p>'));
        vi.stubGlobal('fetch', fetchMock);

        await bootApp(RELOAD_ROW_HTML);
        await clickChevron();
        document.querySelector('#chevron').dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await clickChevron();

        expect(fetchMock).toHaveBeenCalledTimes(2);
    });

    it('intercepts embedded-listing pager links and refreshes the panel in place', async () => {
        const fetchMock = vi
            .fn()
            .mockImplementationOnce(() => okResponse(
                '<div id="embed-page-1">page one'
                + '<a data-polysource-embed-nav href="/admin/orders/1/detail-panel?fragment=1&rd_page=2">next</a>'
                + '</div>',
            ))
            .mockImplementationOnce(() => okResponse('<div id="embed-page-2">page two</div>'));
        vi.stubGlobal('fetch', fetchMock);

        await bootApp(ROW_HTML);
        await clickChevron();
        expect(document.querySelector('#embed-page-1')).not.toBeNull();

        document.querySelector('a[data-polysource-embed-nav]')
            .dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        await vi.waitFor(() => {
            if (!document.querySelector('#embed-page-2')) throw new Error('page 2 not loaded');
        });

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(fetchMock.mock.calls[1][0]).toContain('rd_page=2');
        // Still inside the same detail row — no navigation happened.
        expect(document.querySelector('#host-row + tr.polysource-row-detail-row #embed-page-2')).not.toBeNull();
    });

    it('shows a local error with retry on HTTP failure, then recovers', async () => {
        const fetchMock = vi
            .fn()
            .mockImplementationOnce(() => errorResponse(500))
            .mockImplementationOnce(() => okResponse('<p id="detail-content">recovered</p>'));
        vi.stubGlobal('fetch', fetchMock);

        await bootApp(ROW_HTML);
        await clickChevron();

        const detailRow = document.querySelector('.polysource-row-detail-row');
        expect(detailRow.getAttribute('data-polysource-row-detail-state')).toBe('error');
        expect(detailRow.textContent).toContain('Failed to load details.');
        // The listing row itself is untouched.
        expect(document.querySelector('#host-row')).not.toBeNull();

        const retry = detailRow.querySelector('.polysource-row-detail-retry');
        expect(retry).not.toBeNull();
        retry.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await vi.waitFor(() => {
            if (!document.querySelector('#detail-content')) throw new Error('not recovered yet');
        });

        expect(document.querySelector('.polysource-row-detail-row').getAttribute('data-polysource-row-detail-state')).toBe('expanded');
    });
});
