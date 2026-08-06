<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration;

use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;
use Polysource\Filter\FilterUrlToken\FilterUrlTokenService;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end round trip for `GET /admin/polysource/f/{token}` through
 * the auto-imported route: mint a token with the container's REAL
 * Doctrine-backed FilterUrlTokenService, resolve it over HTTP, follow
 * the redirect contract.
 *
 * Also locks the route-level `[a-f0-9]{12}` requirement — malformed
 * tokens must 404 in ROUTING, before the controller runs.
 */
final class FilterUrlTokenEndpointTest extends BridgeIntegrationTestCase
{
    private function service(): FilterUrlTokenService
    {
        $service = $this->kernel->getContainer()->get(FilterUrlTokenService::class);
        \assert($service instanceof FilterUrlTokenService);

        return $service;
    }

    public function testMalformedTokenIsRejectedByTheRouteRequirement(): void
    {
        // 11 chars, uppercase, non-hex — all outside `[a-f0-9]{12}`.
        foreach (['abc', 'ABCDEFABCDEF', 'zzzzzzzzzzzz', 'aaaaaaaaaaa'] as $malformed) {
            $response = $this->request('GET', '/admin/polysource/f/' . $malformed . '?index=/admin');

            self::assertSame(
                Response::HTTP_NOT_FOUND,
                $response->getStatusCode(),
                \sprintf('Token "%s" must be rejected by routing.', $malformed),
            );
        }
    }

    public function testUnknownWellFormedTokenIs404(): void
    {
        $response = $this->request('GET', '/admin/polysource/f/abcdef012345?index=/admin');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testMintedTokenRedirectsToTheIndexWithTheFiltersSlice(): void
    {
        $token = $this->service()->tokenize(
            TestItem::class,
            ['status' => ['value' => 'published', 'comparison' => '=']],
        );
        self::assertNotNull($token);

        $response = $this->request(
            'GET',
            '/admin/polysource/f/' . $token->token . '?index=' . rawurlencode('/admin/items?page=2'),
        );

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('/admin/items?', $location);

        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        self::assertSame('2', $query['page'] ?? null);
        self::assertSame(
            ['status' => ['value' => 'published', 'comparison' => '=']],
            $query['filters'] ?? null,
        );
    }

    public function testProtocolRelativeIndexTargetIs404(): void
    {
        $token = $this->service()->tokenize(TestItem::class, ['status' => ['value' => 'draft']]);
        self::assertNotNull($token);

        $response = $this->request(
            'GET',
            '/admin/polysource/f/' . $token->token . '?index=' . rawurlencode('//evil.example/admin'),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }
}
