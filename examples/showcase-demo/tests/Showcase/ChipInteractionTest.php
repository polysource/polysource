<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * E5/E6 of the manual test plan — chip remove individual + Clear all.
 *
 * Pinned scenarios:
 *   1. Apply 2 filters → 2 chips render → click X on first chip →
 *      only second chip + that single filter remains in URL.
 *   2. With multiple filters → click "Clear all" link → URL becomes
 *      clean (no filter params) and chips bar is gone.
 *
 * Server-driven fallback test: the X is an `<a href>` linking to a
 * URL that drops the chip's filter slice. Even without JavaScript
 * loading the Stimulus controller, the click must navigate properly.
 *
 * @group panther
 */
final class ChipInteractionTest extends AbstractShowcasePantherTestCase
{
    public function testClickingChipRemoveLinkDropsThatFilterFromTheUrl(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        // Visit URL with 2 filters applied.
        $client->request(
            'GET',
            '/admin/order'
            . '?filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=paid'
            . '&filters%5Breference%5D%5Bcomparison%5D=like&filters%5Breference%5D%5Bvalue%5D=ORD',
        );

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.ea-filter-chips-bar .ea-filter-chip'),
            ),
        );

        $chips = $client->findElements(WebDriverBy::cssSelector('.ea-filter-chip'));
        self::assertGreaterThanOrEqual(2, \count($chips), 'two filters must produce two chips');

        // Click the X on the status chip (server-driven <a href>, no JS needed).
        $statusChipRemove = $client->findElement(WebDriverBy::cssSelector(
            '.ea-filter-chip[data-property="status"] .ea-filter-chip__remove',
        ));
        $statusChipRemove->click();

        // After navigation, the URL must NOT contain status=paid anymore,
        // but must still contain reference=ORD.
        $client->wait(5)->until(
            WebDriverExpectedCondition::not(
                WebDriverExpectedCondition::urlContains('filters%5Bstatus%5D'),
            ),
        );

        $finalUrl = $client->getCurrentURL();
        self::assertStringNotContainsString('filters%5Bstatus%5D', $finalUrl, 'status chip removed from URL');
        self::assertStringContainsString('filters%5Breference%5D', $finalUrl, 'reference chip preserved');
    }

    public function testClearAllLinkRemovesEveryFilterAndChipsBar(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        $client->request(
            'GET',
            '/admin/order'
            . '?filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=paid'
            . '&filters%5Breference%5D%5Bcomparison%5D=like&filters%5Breference%5D%5Bvalue%5D=ORD',
        );

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.ea-filter-chips-bar__clear'),
            ),
        );

        $clearAll = $client->findElement(WebDriverBy::cssSelector('.ea-filter-chips-bar__clear'));
        $clearAll->click();

        // After click, the URL must be free of every filter param.
        $client->wait(5)->until(
            WebDriverExpectedCondition::not(
                WebDriverExpectedCondition::urlContains('filters%5B'),
            ),
        );

        $finalUrl = $client->getCurrentURL();
        self::assertStringNotContainsString('filters%5B', $finalUrl, 'every filter slice removed');

        // Chips bar must be gone (no filters → no bar).
        $remainingChips = $client->findElements(WebDriverBy::cssSelector('.ea-filter-chip'));
        self::assertCount(0, $remainingChips, 'no chip can survive Clear all');
    }
}
