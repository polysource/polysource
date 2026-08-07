<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * E2E coverage for v1.1.0 expandable row details on the order
 * listing (chevron rendered by RowDetailField, provider
 * OrderRowDetailProvider, lazy endpoint `polysource_row_detail`).
 *
 * Covers both ADR-027 paths:
 *  - enhanced: click → fetch → detail <tr> injected under the row;
 *  - baseline: the chevron's plain href renders the standalone
 *    detail page (asserted by direct navigation).
 *
 * @group panther
 * @group v110
 */
final class RowDetailExpandTest extends AbstractShowcasePantherTestCase
{
    public function testChevronRendersOnOrderIndexWithoutPreloadingDetails(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-row-detail-toggle'),
            ),
        );

        $chevrons = $client->findElements(WebDriverBy::cssSelector('.polysource-row-detail-toggle'));
        self::assertGreaterThan(0, \count($chevrons), 'Every order row should carry the expansion control');
        self::assertSame('false', $chevrons[0]->getAttribute('aria-expanded'));

        // Lazy contract: no detail content in the initial render.
        $details = $client->findElements(WebDriverBy::cssSelector('.polysource-row-detail-row'));
        self::assertCount(0, $details, 'Details must not be preloaded with the listing');
    }

    public function testClickExpandsRowAndInjectsLazyLoadedItems(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-row-detail-toggle'),
            ),
        );

        $chevron = $client->findElement(WebDriverBy::cssSelector('.polysource-row-detail-toggle'));
        $chevron->click();

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('tr.polysource-row-detail-row[data-polysource-row-detail-state="expanded"]'),
            ),
        );

        $detail = $client->findElement(WebDriverBy::cssSelector('tr.polysource-row-detail-row'));
        self::assertNotNull(
            $detail->findElement(WebDriverBy::cssSelector('[data-showcase-order-detail]')),
            'Injected panel must contain the provider template output',
        );

        // Collapse: second click removes the row.
        $client->findElement(WebDriverBy::cssSelector('.polysource-row-detail-toggle'))->click();
        $client->wait(8)->until(
            static fn ($driver) => 0 === \count($driver->findElements(
                WebDriverBy::cssSelector('tr.polysource-row-detail-row'),
            )),
        );
        $this->addToAssertionCount(1);
    }

    public function testChevronHrefServesTheStandalonePageAsNoJsBaseline(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-row-detail-toggle'),
            ),
        );

        $href = $client->findElement(WebDriverBy::cssSelector('.polysource-row-detail-toggle'))
            ->getAttribute('href');
        self::assertNotEmpty($href);
        self::assertStringContainsString('/admin/polysource/row-detail/', (string) $href);

        // Navigate the baseline URL directly — the server must render
        // the standalone wrapper page with the same panel content.
        $client->request('GET', (string) $href);
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('[data-showcase-order-detail]'),
            ),
        );

        $body = $client->getPageSource();
        self::assertStringContainsString('polysource-row-detail-page', $body, 'Standalone wrapper page expected');
    }
}
