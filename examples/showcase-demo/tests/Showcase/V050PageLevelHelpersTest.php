<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * E2E coverage for the v0.5.0 page-level helpers wired in the
 * showcase index override:
 *   - polysource_row_density_toggle()       (v0.5.0 #3)
 *   - polysource_toasts()                   (v0.5.0 #4)
 *   - polysource_keyboard_shortcuts_help()  (v0.5.0 #5)
 *   - polysource_filter_share_button()      (v0.5.0 #7)
 *
 * Each test exercises the server-rendered baseline (no Stimulus
 * required) — per ADR-027.
 *
 * @group panther
 * @group v050
 */
final class V050PageLevelHelpersTest extends AbstractShowcasePantherTestCase
{
    public function testRowDensityToggleRendersBothAnchors(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-row-density-toggle'),
            ),
        );

        $anchors = $client->findElements(WebDriverBy::cssSelector('.polysource-row-density-toggle a'));
        self::assertCount(2, $anchors, 'compact + normal anchor pair must render');
        $hrefs = array_map(static fn ($a) => $a->getAttribute('href'), $anchors);
        self::assertTrue(
            array_filter($hrefs, static fn ($h) => str_contains((string) $h, 'density=compact')) !== [],
            'one anchor must point at ?density=compact',
        );
        self::assertTrue(
            array_filter($hrefs, static fn ($h) => str_contains((string) $h, 'density=normal')) !== [],
            'one anchor must point at ?density=normal',
        );
    }

    public function testCompactDensityAppliesTableSmClass(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order?density=compact');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('table'),
            ),
        );

        $tableClass = (string) $client->findElement(WebDriverBy::cssSelector('table.table'))->getAttribute('class');
        self::assertStringContainsString('table-sm', $tableClass, '?density=compact must add table-sm to <table>');
    }

    public function testKeyboardShortcutsCheatSheetRendersAsDetailsElement(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/order');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-keyboard-shortcuts'),
            ),
        );

        $details = $client->findElement(WebDriverBy::cssSelector('details.polysource-keyboard-shortcuts'));
        $summary = $details->findElement(WebDriverBy::tagName('summary'));
        self::assertStringContainsString('Keyboard shortcuts', $summary->getText());

        // Native <details> opens server-side when `open` attribute
        // is set — we test the closed-by-default state here.
        self::assertNull($details->getAttribute('open'), 'closed by default — user opens via <summary>');
    }

    public function testFilterShareButtonRendersOnlyWhenFiltersAreActive(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        // No filters → button must NOT render.
        $client->request('GET', '/admin/order');
        $client->wait(6)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('table')),
        );
        $buttons = $client->findElements(WebDriverBy::cssSelector('.polysource-filter-share'));
        self::assertCount(0, $buttons, 'no filters → empty helper output (no button)');

        // With a filter → button renders, href points at the bridge's
        // short-token redirect endpoint.
        $client->request(
            'GET',
            '/admin/order?filters%5Bstatus%5D%5Bcomparison%5D=%3D&filters%5Bstatus%5D%5Bvalue%5D%5B0%5D=paid',
        );
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-filter-share'),
            ),
        );
        $button = $client->findElement(WebDriverBy::cssSelector('.polysource-filter-share'));
        $href = (string) $button->getAttribute('href');
        self::assertStringContainsString('/admin/polysource/f/', $href, 'href must point at the short-token route');
        self::assertMatchesRegularExpression('#/f/[a-f0-9]{12}#', $href, 'href must contain a 12-hex token');
    }
}
