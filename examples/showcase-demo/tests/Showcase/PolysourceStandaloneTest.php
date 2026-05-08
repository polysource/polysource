<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * E2E coverage for Polysource standalone resources — the 4 adapter
 * resources the showcase exposes alongside EA's Doctrine CRUD.
 *
 * Each resource is backed by a different infrastructure (Redis,
 * MinIO/S3, WireMock/HTTP, Meilisearch). The tests assert the
 * rendered admin works for each:
 *
 *   - Index renders + has rows
 *   - Detail page resolves by id
 *   - Cursor pagination works (Redis SCAN, Meilisearch offset)
 *   - Empty state renders cleanly when filters return nothing
 *   - Custom layout (showcase wraps Polysource pages in EA chrome)
 *
 * @group panther
 */
final class PolysourceStandaloneTest extends AbstractShowcasePantherTest
{
    /**
     * @return iterable<string, array{0: string, 1: int}>
     */
    public static function adapterResourceProvider(): iterable
    {
        // path → minimum expected rows on first page.
        yield 'Redis cache keys' => ['/admin/polysource/cache-keys', 1];
        yield 'S3 files' => ['/admin/polysource/s3-files', 1];
        yield 'WireMock microservices' => ['/admin/polysource/microservices', 1];
        yield 'Meilisearch index' => ['/admin/polysource/search-index', 1];
        yield 'Audit log (Doctrine-backed)' => ['/admin/polysource/audit-log', 5];
        yield 'Login attempts (Doctrine-backed)' => ['/admin/polysource/login-attempts', 5];
        yield 'Failed messages (Messenger)' => ['/admin/polysource/failed-messages', 1];
    }

    /**
     * @dataProvider adapterResourceProvider
     */
    public function testIndexRendersWithRowsForEveryAdapterBackend(string $path, int $minRows): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', $path);

        // Polysource layout must apply (`body.ea` from the showcase
        // override) — proves the host's `polysource.layout_template`
        // override is wired AND the page didn't bounce to /login.
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('body.ea'),
            ),
        );
        // Index table must render rows.
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('table tbody tr'),
            ),
        );

        $rows = $client->findElements(WebDriverBy::cssSelector('table tbody tr'));
        self::assertGreaterThanOrEqual($minRows, \count($rows), \sprintf('%s must show at least %d row(s).', $path, $minRows));
    }

    public function testDetailPageResolvesById(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        $client->request('GET', '/admin/polysource/audit-log');
        // Wait for the table to render — `tbody tr` mirrors the
        // populated-listing assertion that already passed.
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('table tbody tr'),
            ),
        );

        // Find the first detail anchor. Use a CSS selector that
        // matches the rendered markup: every detail link has
        // `data-polysource-action="detail"` and an href like
        // `/admin/polysource/audit-log/<uuid>`.
        $detailLinks = $client->findElements(WebDriverBy::cssSelector('a[data-polysource-action="detail"]'));
        self::assertGreaterThan(0, \count($detailLinks), 'Audit-log index must render at least one detail anchor.');

        $href = (string) $detailLinks[0]->getAttribute('href');
        // Navigate directly via the href rather than clicking — the
        // click can be intercepted by Bootstrap modals or other
        // listeners and races with Panther; a GET is deterministic.
        $client->request('GET', $href);

        // Detail page must render the resource's fields.
        $client->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('h1'),
            ),
        );
        self::assertStringContainsString('/audit-log/', $client->getCurrentURL());
    }

    public function testPaginationNavRendersOnLargeCollections(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        // Meilisearch is seeded with 200 docs → page-number pagination
        // kicks in at any reasonable page size. Page-number nav
        // (sources that return a `total`) and cursor nav (sources
        // that don't) are BOTH valid implementations of the
        // Polysource paginator partial — assert one of them exists.
        $client->request('GET', '/admin/polysource/search-index?pageSize=20');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('table tbody tr'),
            ),
        );

        $pageNumberNav = $client->findElements(WebDriverBy::cssSelector('nav[aria-label="Pagination"]'));
        $cursorNav = $client->findElements(WebDriverBy::cssSelector('nav[aria-label*="ursor"]'));
        $nextPageLinks = $client->findElements(WebDriverBy::cssSelector('a[href*="page=2"], a[href*="cursor="]'));

        self::assertTrue(
            \count($pageNumberNav) > 0 || \count($cursorNav) > 0 || \count($nextPageLinks) > 0,
            'Search-index (200 seeded docs) must render a pagination nav at pageSize=20.',
        );
    }

    public function testEmptyResultStateRendersWithoutCrash(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        // Search for a token that won't match anything in the cache-keys
        // backend. The Polysource search box flows through the data
        // source's `searchText` filter — Redis client-side filtering
        // returns no rows when the substring doesn't match.
        $client->request('GET', '/admin/polysource/cache-keys?q=' . urlencode('zzz-nonexistent-' . random_int(10000, 99999)));

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('body.ea'),
            ),
        );

        // Either an empty <tbody> OR a "No records." cell.
        $rows = $client->findElements(WebDriverBy::cssSelector('table tbody tr'));
        $emptyMarker = $client->findElements(WebDriverBy::cssSelector('table tbody tr td.text-center'));

        self::assertTrue(
            \count($rows) === 0 || \count($emptyMarker) > 0,
            'Empty result must render either an empty tbody or the "No records" cell — got ' . \count($rows) . ' rows.',
        );
    }
}
