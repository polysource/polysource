<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * E2E coverage for the v0.5.0 + v0.4.0 per-column helpers wired in
 * the showcase index `table_head` + `table_body_row` overrides:
 *   - polysource_frozen_column()       (v0.5.0 #2)
 *   - polysource_column_reorder_buttons() (v0.5.0 #1)
 *   - polysource_quick_filter_row()    (v0.4.0 #17)
 *   - polysource_row_class()           (v0.3.0 #14)
 *   - polysource_cell_filter_menu()    (v0.4.0 #16)
 *
 * @group panther
 * @group v050
 */
final class V050TableHelpersTest extends AbstractShowcasePantherTestCase
{
    public function testFrozenColumnEmitsStickyStyleOnFirstHeader(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('thead tr:first-child th[data-column]'),
            ),
        );

        $firstTh = $client->findElement(WebDriverBy::cssSelector('thead tr:first-child th[data-column]'));
        $style = (string) $firstTh->getAttribute('style');
        $class = (string) $firstTh->getAttribute('class');
        self::assertStringContainsString('position: sticky', $style, 'first column must be position: sticky');
        self::assertStringContainsString('left: 0px', $style, 'first column pinned to left edge');
        self::assertStringContainsString('polysource-frozen-column', $class, 'frozen class hook present');
    }

    public function testColumnReorderButtonsRenderForEachHeader(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-column-reorder'),
            ),
        );

        $reorderGroups = $client->findElements(WebDriverBy::cssSelector('.polysource-column-reorder'));
        self::assertGreaterThan(0, \count($reorderGroups), 'reorder buttons render on column headers');

        // First column's left arrow must be disabled (it's already first).
        $firstReorder = $reorderGroups[0];
        $leftAnchor = $firstReorder->findElement(WebDriverBy::cssSelector('a[aria-label="Move left"]'));
        self::assertStringContainsString('disabled', (string) $leftAnchor->getAttribute('class'));
    }

    public function testQuickFilterRowRendersInputsBelowHeaders(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('tr.polysource-quick-filter-row'),
            ),
        );

        $row = $client->findElement(WebDriverBy::cssSelector('tr.polysource-quick-filter-row'));
        $inputs = $row->findElements(WebDriverBy::cssSelector('input[name^="filters["]'));
        self::assertGreaterThan(0, \count($inputs), 'quick filter inputs render');
    }

    public function testRowClassColoursOrdersByStatus(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        // Filter to paid orders so we know what class to expect.
        $client->request(
            'GET',
            '/admin/order?filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=paid',
        );

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('tbody tr[data-id]'),
            ),
        );

        $rows = $client->findElements(WebDriverBy::cssSelector('tbody tr[data-id]'));
        self::assertGreaterThan(0, \count($rows), 'paid orders must render at least one row');

        $coloured = array_filter(
            $rows,
            static fn ($r) => str_contains((string) $r->getAttribute('class'), 'table-info'),
        );
        self::assertGreaterThan(0, \count($coloured), 'at least one paid-status row must get the table-info class');
    }

    public function testCellFilterMenuRendersDropdownOnStatusColumn(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-cell-filter-menu'),
            ),
        );

        // The cell-filter dropdown is rendered next to scalar cells
        // on the status + reference columns of Order.
        $menus = $client->findElements(WebDriverBy::cssSelector('.polysource-cell-filter-menu'));
        self::assertGreaterThan(0, \count($menus));

        // Check that the "Filter where ... = this" anchor carries a
        // ?filters[status] query slice.
        $firstMenu = $menus[0];
        $filterAnchor = $firstMenu->findElement(WebDriverBy::cssSelector('a.dropdown-item'));
        $href = (string) $filterAnchor->getAttribute('href');
        self::assertStringContainsString('filters', $href, 'filter-where anchor builds the filter URL slice');
    }
}
