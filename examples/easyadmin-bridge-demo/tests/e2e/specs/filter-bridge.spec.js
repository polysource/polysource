import { test, expect } from '@playwright/test';

/**
 * End-to-end smoke tests for the polysource/easyadmin-filter-bridge
 * Stimulus interactions, against the live demo app.
 *
 * Each spec:
 *   1. Opens the filter modal on /admin/product
 *   2. Checks the relevant filter checkbox (which expands the widget)
 *   3. Triggers a preset / quick-range / clear button
 *   4. Asserts the resulting form state (comparison + value(s))
 *
 * These specs validate the integration from theme → Stimulus →
 * upstream value2 toggle. Vitest covers the controller logic in
 * isolation; this layer covers "the buttons render, they bind,
 * upstream toggles fire".
 */

const PRODUCT_INDEX = '/admin/product';

/**
 * EasyAdmin renders each filter row with a checkbox and a collapse
 * trigger. The checkbox doesn't have an associated <label>, so we
 * locate it via the row's `data-filter-property`. Checking the box
 * activates the filter; clicking the collapse trigger expands the
 * widget content.
 */
async function openFilter(page, property) {
    const row = page.locator(`[data-filter-property="${property}"]`);
    await row.locator('.filter-checkbox').check();
    await row.locator('a[data-bs-toggle="collapse"]').click();
    // Wait for the bridge wrapper to be visible inside the expanded content.
    await row.locator('.polysource-filter').first().waitFor({ state: 'visible' });
}

test.describe('filter bridge — DateTime presets', () => {
    test('opens the filter modal and renders the createdAt presets', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);

        await page.locator('.action-filters-button').click();

        // Expand the createdAt filter checkbox in the modal.
        await openFilter(page, 'createdAt');

        // The bridge wrapper is present with the right data-controller.
        const wrapper = page.locator('[data-filter-property="createdAt"] .polysource-filter--datetime');
        await expect(wrapper).toBeVisible();
        await expect(wrapper).toHaveAttribute('data-controller', 'polysource--filter');

        // All 5 configured presets are rendered + a Clear button.
        await expect(wrapper.getByRole('button', { name: 'Today' })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: 'Last 7 days' })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: 'Last 30 days' })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: 'This month' })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: /custom/i })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: 'Clear' })).toBeVisible();
    });

    test('Today preset → comparison `=`, value = today, value2 hidden', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'createdAt');

        const wrapper = page.locator('[data-filter-property="createdAt"] .polysource-filter--datetime');
        await wrapper.getByRole('button', { name: 'Today' }).click();

        await expect(wrapper.locator('select[name$="[comparison]"]')).toHaveValue('=');
        const value1 = await wrapper.locator('input[name$="[value]"]').inputValue();
        expect(value1).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
        // value2 wrapper stays hidden because comparison ≠ between.
        await expect(
            wrapper.locator('[data-ea-value2-of-comparison-id]')
        ).toHaveClass(/d-none/);
    });

    test('Last 7 days preset → comparison `between`, both dates filled, value2 revealed', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'createdAt');

        const wrapper = page.locator('[data-filter-property="createdAt"] .polysource-filter--datetime');
        await wrapper.getByRole('button', { name: 'Last 7 days' }).click();

        await expect(wrapper.locator('select[name$="[comparison]"]')).toHaveValue('between');

        const value1 = await wrapper.locator('input[name$="[value]"]').inputValue();
        const value2 = await wrapper.locator('input[name$="[value2]"]').inputValue();
        expect(value1).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);
        expect(value2).toMatch(/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/);

        // value2 wrapper now visible (upstream JS removed `d-none` after
        // the bridge dispatched the change event).
        await expect(
            wrapper.locator('[data-ea-value2-of-comparison-id]')
        ).not.toHaveClass(/d-none/);

        // value2 should be after value (today >= 7 days ago).
        expect(new Date(value2).getTime()).toBeGreaterThanOrEqual(new Date(value1).getTime());
    });

    test('Clear button empties both date inputs', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'createdAt');

        const wrapper = page.locator('[data-filter-property="createdAt"] .polysource-filter--datetime');
        await wrapper.getByRole('button', { name: 'Last 7 days' }).click();
        await wrapper.getByRole('button', { name: 'Clear' }).click();

        await expect(wrapper.locator('input[name$="[value]"]')).toHaveValue('');
        await expect(wrapper.locator('input[name$="[value2]"]')).toHaveValue('');
    });
});

test.describe('filter bridge — Numeric quick-ranges', () => {
    test('renders all 4 calibrated price ranges', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'price');

        const wrapper = page.locator('[data-filter-property="price"] .polysource-filter--numeric');
        await expect(wrapper.getByRole('button', { name: '< 50€' })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: '50–200€' })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: '200–400€' })).toBeVisible();
        await expect(wrapper.getByRole('button', { name: '> 400€' })).toBeVisible();
    });

    test('50–200€ → comparison `between`, value=50, value2=200', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'price');

        const wrapper = page.locator('[data-filter-property="price"] .polysource-filter--numeric');
        await wrapper.getByRole('button', { name: '50–200€' }).click();

        await expect(wrapper.locator('select[name$="[comparison]"]')).toHaveValue('between');
        await expect(wrapper.locator('input[name$="[value]"]')).toHaveValue('50');
        await expect(wrapper.locator('input[name$="[value2]"]')).toHaveValue('200');
    });

    test('< 50€ → comparison `<=`, value=50, value2 cleared', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'price');

        const wrapper = page.locator('[data-filter-property="price"] .polysource-filter--numeric');
        await wrapper.getByRole('button', { name: '< 50€' }).click();

        await expect(wrapper.locator('select[name$="[comparison]"]')).toHaveValue('<=');
        await expect(wrapper.locator('input[name$="[value]"]')).toHaveValue('50');
        await expect(wrapper.locator('input[name$="[value2]"]')).toHaveValue('');
    });
});

test.describe('filter bridge — Comparison whitelist', () => {
    test('stock filter only exposes whitelisted operators in dropdown', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'stock');

        const select = page.locator('[data-filter-property="stock"] select[name$="[comparison]"]');
        const options = await select.locator('option').allTextContents();

        // Configured: ['=', '>=', '<='] → 3 options, no `between`,
        // no `>`, no `<`, no `!=`.
        expect(options).toHaveLength(3);
        expect(options.join('|')).toMatch(/equal/);
        expect(options.join('|')).toMatch(/greater than or equal/);
        expect(options.join('|')).toMatch(/less than or equal/);
        expect(options.join('|')).not.toMatch(/between/);
    });
});

test.describe('filter bridge — End-to-end submit + session persistence', () => {
    test('applying a filter persists it after navigating away and back', async ({ page }) => {
        await page.goto(PRODUCT_INDEX);

        await page.locator('.action-filters-button').click();
        await openFilter(page, 'price');
        const wrapper = page.locator('[data-filter-property="price"] .polysource-filter--numeric');
        await wrapper.getByRole('button', { name: '50–200€' }).click();

        await page.getByRole('button', { name: /apply/i }).click();
        await page.waitForURL(/filters%5Bprice%5D/);

        const filteredUrl = page.url();
        expect(filteredUrl).toMatch(/filters%5Bprice%5D%5Bvalue%5D=50/);
        expect(filteredUrl).toMatch(/filters%5Bprice%5D%5Bvalue2%5D=200/);
        expect(filteredUrl).toMatch(/filters%5Bprice%5D%5Bcomparison%5D=between/);

        // Navigate away (dashboard) then come back to /admin/product
        // *without* the filter query string. Bridge subscriber should
        // restore the filter from session.
        await page.getByRole('link', { name: 'Dashboard' }).click();
        await page.goto(PRODUCT_INDEX);

        // Final URL should now include the restored filter.
        await expect(page).toHaveURL(/filters%5Bprice%5D%5Bvalue%5D=50/);
    });

    test('clicking the X reset link clears the saved filter for good', async ({ page }) => {
        // Apply a filter and save it to the session.
        await page.goto(PRODUCT_INDEX);
        await page.locator('.action-filters-button').click();
        await openFilter(page, 'price');
        await page
            .locator('[data-filter-property="price"] .polysource-filter--numeric')
            .getByRole('button', { name: '50–200€' })
            .click();
        await page.getByRole('button', { name: /apply/i }).click();
        await page.waitForURL(/filters%5Bprice%5D/);

        // Click the X reset link (which goes to /admin/product without filters).
        // Same-path Referer + no target filters → bridge clears the session.
        await page.locator('.action-filters-reset').click();
        await expect(page).toHaveURL(/\/admin\/product$/);

        // Now navigate away and come back — the session is empty so the
        // bridge must NOT re-attach the filter.
        await page.getByRole('link', { name: 'Dashboard' }).click();
        await page.goto(PRODUCT_INDEX);

        await expect(page).not.toHaveURL(/filters%5Bprice%5D/);
    });
});
