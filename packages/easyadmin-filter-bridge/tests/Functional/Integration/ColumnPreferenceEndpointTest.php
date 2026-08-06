<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration;

use Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App\Entity\TestItem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * End-to-end wiring for `POST /admin/polysource/column-preferences/{resource}`
 * through the auto-imported route (Bundle::boot() + BundleRouteLoader).
 *
 * The controller's behavioural matrix (hidden-set inversion, referer
 * handling, normalisation) is covered by the unit suite; these tests
 * lock what only a booted kernel can prove: the route resolves with
 * an FQCN path segment, dispatches to the DI-registered controller,
 * and the CSRF gate actually fires with the real token manager.
 */
final class ColumnPreferenceEndpointTest extends BridgeIntegrationTestCase
{
    private function endpoint(): string
    {
        return '/admin/polysource/column-preferences/' . $this->encodeResource(TestItem::class);
    }

    public function testPostWithoutCsrfTokenIsForbidden(): void
    {
        $response = $this->kernel->handle(Request::create($this->endpoint(), 'POST', [
            'columns' => ['name', 'status'],
            'visible' => ['name'],
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPostWithForgedCsrfTokenIsForbidden(): void
    {
        $response = $this->kernel->handle(Request::create($this->endpoint(), 'POST', [
            '_token' => 'forged-token-value',
            'columns' => ['name', 'status'],
            'visible' => ['name'],
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testGetIsMethodNotAllowed(): void
    {
        $response = $this->request('GET', $this->endpoint());

        self::assertSame(Response::HTTP_METHOD_NOT_ALLOWED, $response->getStatusCode());
    }
}
