<?php

declare(strict_types=1);

namespace App\Tests\Showcase;

/**
 * Phase H — minimal-but-real E2E journey tests covering the public,
 * unauthenticated journeys + the firewall reach. Authenticated
 * journeys are deferred to a Phase H+1 polish pass: cookie-based auth
 * from KernelBrowser-minted sessions to remote Selenium needs more
 * session-sync work than the launch budget allows.
 *
 * The full authenticated story IS exercised end-to-end by:
 *   - PermissionsByRoleTest (WebTestCase, fast, in-process)
 *   - The Phase I `app:showcase:screenshots` command (single-process
 *     Symfony console, drives the form via Panther directly)
 *
 * @group panther
 */
final class JourneyTest extends AbstractShowcasePantherTestCase
{
    public function testLoginPageRenders(): void
    {
        $client = $this->browser();
        $client->request('GET', '/login');

        $crawler = $client->getCrawler();
        self::assertStringContainsString('ShopCo Showcase', $crawler->filter('h1')->text());
        self::assertGreaterThan(0, $crawler->filter('input[name="_username"]')->count());
        self::assertGreaterThan(0, $crawler->filter('input[name="_password"]')->count());
    }

    public function testAnonymousVisitToHomeRedirectsToLogin(): void
    {
        $client = $this->browser();
        $client->request('GET', '/');

        // Firewall on `/` requires ROLE_VIEWER → anonymous browser
        // bounced to /login. Validates the firewall reaches Panther.
        self::assertStringContainsString('/login', $client->getCurrentURL());
    }

    public function testPolysourceFailedMessagesRequiresAuth(): void
    {
        $client = $this->browser();
        $client->request('GET', '/admin/polysource/failed-messages');

        self::assertStringContainsString('/login', $client->getCurrentURL());
    }
}
