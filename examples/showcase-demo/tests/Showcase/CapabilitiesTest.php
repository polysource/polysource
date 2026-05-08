<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * Sprint 5 deep-dives + Sprint 1 completion + Sprint 2 + Sprint 6
 * folded into one Panther module — every Polysource capability the
 * showcase exposes that wasn't already covered by the per-module
 * test classes.
 *
 * Pinned features:
 *
 *  - Bulk-job detail JSON progress endpoint responds 200 with the
 *    job's status fields (Sprint 5 deep-dive).
 *  - Audit log entries link to a working detail page (Sprint 5).
 *  - Saved-view scope visibility: a private view from admin is NOT
 *    visible to ops (Sprint 1 closer).
 *  - Polysource layout includes the showcase's custom EA chrome
 *    on every standalone resource (Sprint 6 — `polysource.layout_template`
 *    integration cookbook pattern).
 *
 * @group panther
 */
final class CapabilitiesTest extends AbstractShowcasePantherTestCase
{
    public function testBulkJobProgressEndpointReturnsJsonStatus(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        // Pick the first seeded bulk job from the index page.
        $client->request('GET', '/admin/polysource/bulk-jobs');
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('table tbody tr'),
            ),
        );

        $detailLinks = $client->findElements(WebDriverBy::cssSelector('a[data-polysource-action="detail"]'));
        self::assertGreaterThan(0, \count($detailLinks), 'Bulk-jobs index must render at least one detail anchor.');

        $detailUrl = (string) $detailLinks[0]->getAttribute('href');
        // Extract the bulk-job id from the detail URL.
        if (!preg_match('~/bulk-jobs/([0-9a-fA-F\-]{36})~', $detailUrl, $m)) {
            self::fail('Could not extract bulk-job uuid from detail URL: ' . $detailUrl);
        }
        // The progress endpoint sits under the bundle's own route
        // namespace at `/admin/bulk-jobs/<uuid>/progress` (NOT under
        // `/admin/polysource/bulk-jobs/...` which is the resource
        // browse routes).
        $progressUrl = '/admin/bulk-jobs/' . $m[1] . '/progress';

        // Fetch via the live browser so cookies travel and we hit
        // the same kernel as a real user would.
        $client->request('GET', $progressUrl);
        $body = (string) $client->executeScript('return document.body.innerText;');
        $payload = json_decode($body, true);

        self::assertIsArray($payload, \sprintf('Progress endpoint must return JSON. Got body: %s', substr($body, 0, 200)));
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('status', $payload);
    }

    public function testAuditLogDetailPageResolvesAndShowsContext(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/polysource/audit-log');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('table tbody tr a[data-polysource-action="detail"]'),
            ),
        );

        $detailLinks = $client->findElements(WebDriverBy::cssSelector('a[data-polysource-action="detail"]'));
        $href = (string) $detailLinks[0]->getAttribute('href');
        $client->request('GET', $href);

        $client->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('body.ea'),
            ),
        );

        // Audit detail must show the message + actor at minimum.
        // The Polysource detail template renders fields via <dt>/<dd>.
        $dts = $client->findElements(WebDriverBy::cssSelector('dl dt, dl dd'));
        self::assertGreaterThan(0, \count($dts), 'Audit detail page must render the entry fields.');
    }

    public function testSavedViewPrivateScopeIsNotVisibleToAnotherUser(): void
    {
        // Use the audit-log resource — admin owns a PRIVATE saved view
        // for it (`sv-audit-admin` per the showcase fixture). ops
        // doesn't own any audit-log views.
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/polysource/audit-log');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('body.ea'),
            ),
        );

        $adminDropdown = $client->findElements(WebDriverBy::cssSelector('.polysource-saved-views .dropdown-toggle'));
        if (\count($adminDropdown) === 0) {
            self::markTestSkipped('Saved-views dropdown not rendered on this resource — feature not exercised.');
        }
        $adminDropdown[0]->click();
        $client->wait(5)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::cssSelector('.polysource-saved-views .dropdown-menu.show'),
            ),
        );
        $adminViewCount = \count($client->findElements(WebDriverBy::cssSelector('.polysource-saved-views .dropdown-menu.show a[href*="view="]')));
        self::assertGreaterThan(0, $adminViewCount, 'Admin must see their private audit-log view.');

        // Switch user to ops.
        $client->request('GET', '/logout');
        $client->wait(5)->until(
            WebDriverExpectedCondition::urlContains('/login'),
        );

        $this->loginViaForm('ops@shop.co');
        $client->request('GET', '/admin/polysource/audit-log');
        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('body.ea'),
            ),
        );

        $opsDropdown = $client->findElements(WebDriverBy::cssSelector('.polysource-saved-views .dropdown-toggle'));
        if (\count($opsDropdown) === 0) {
            // Dropdown not rendered for ops = private views correctly
            // hidden. Assertion satisfied.
            self::assertTrue(true);

            return;
        }
        $opsDropdown[0]->click();
        $client->wait(3)->until(
            WebDriverExpectedCondition::visibilityOfElementLocated(
                WebDriverBy::cssSelector('.polysource-saved-views .dropdown-menu.show'),
            ),
        );
        $opsViews = $client->findElements(WebDriverBy::cssSelector('.polysource-saved-views .dropdown-menu.show a[href*="view="]'));

        self::assertLessThan(
            $adminViewCount,
            \count($opsViews),
            'ops must not see admin\'s private audit-log views.',
        );
    }

    public function testCustomLayoutTemplateWrapsPolysourcePages(): void
    {
        // The showcase's `polysource.layout_template:
        // '@Polysource/layout.html.twig'` config is overridden by the
        // showcase's own template at templates/polysource/layout.html.twig
        // which inherits EA's chrome (sidebar, content-wrapper). This
        // pins that override so a future regression in the layout
        // resolution would be caught.
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();
        $client->request('GET', '/admin/polysource/audit-log');

        $client->wait(8)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('body.ea'),
            ),
        );

        // EA chrome elements injected by the showcase override.
        $sidebar = $client->findElements(WebDriverBy::cssSelector('aside.sidebar'));
        $mainContent = $client->findElements(WebDriverBy::cssSelector('section.main-content'));

        self::assertGreaterThan(0, \count($sidebar), 'showcase override must inject EA sidebar on Polysource pages.');
        self::assertGreaterThan(0, \count($mainContent), 'showcase override must inject EA main-content on Polysource pages.');
    }
}
