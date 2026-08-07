<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Tests\Functional\App\RowDetailTestKernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Nested listing as row detail (v1.1.0, `RowDetail::listing()`):
 *
 *  - the panel renders the child resource's listing scoped by the
 *    parent filters (parent 1 sees its 3 children, never parent 2's);
 *  - `rd_page` pages the EMBEDDED listing on the panel URL — no
 *    interaction with the outer listing's query params;
 *  - the embedded resource's own view permission is enforced;
 *  - the pager's links target the panel path with
 *    data-polysource-embed-nav for the JS interception.
 *
 * Fixtures: cf. ParentOrdersResource (pageSize 2), ChildItemsResource
 * (3 children under parent 1, 1 under parent 2), SecretItemsResource
 * (RECORD_OWNER-gated → always denied for a Resource subject).
 */
final class RowDetailNestedListingTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return RowDetailTestKernel::class;
    }

    /** @param array<mixed> $options */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel($options + ['debug' => false]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    #[Test]
    public function panelRendersChildListingScopedToParent(): void
    {
        $body = $this->body('GET', '/admin/parent-orders/1/detail-panel?fragment=1', 200);

        self::assertStringContainsString('child-one', $body);
        self::assertStringContainsString('child-two', $body);
        self::assertStringNotContainsString('child-four', $body, 'Parent 2 children must not leak into parent 1 panel');
        self::assertStringContainsString('data-polysource-embed="child-items"', $body);
    }

    #[Test]
    public function embeddedPaginationRidesOnRdPageParam(): void
    {
        $pageOne = $this->body('GET', '/admin/parent-orders/1/detail-panel?fragment=1', 200);

        self::assertStringNotContainsString('child-three', $pageOne, 'pageSize=2 → third child on page 2');
        self::assertStringContainsString('rd_page=2', $pageOne, 'Pager must link the next embedded page');
        self::assertStringContainsString('data-polysource-embed-nav', $pageOne);
        self::assertStringContainsString('fragment=1', $pageOne, 'Pager links must preserve fragment mode');

        $pageTwo = $this->body('GET', '/admin/parent-orders/1/detail-panel?fragment=1&rd_page=2', 200);

        self::assertStringContainsString('child-three', $pageTwo);
        self::assertStringNotContainsString('child-one', $pageTwo);
    }

    #[Test]
    public function scopingHoldsForTheOtherParent(): void
    {
        $body = $this->body('GET', '/admin/parent-orders/2/detail-panel?fragment=1', 200);

        self::assertStringContainsString('child-four', $body);
        self::assertStringNotContainsString('child-one', $body);
    }

    #[Test]
    public function embeddedResourcePermissionIsEnforced(): void
    {
        $this->body('GET', '/admin/parent-orders/9/detail-panel?fragment=1', 403);
    }

    #[Test]
    public function noJsPageModeWrapsTheEmbeddedListing(): void
    {
        $body = $this->body('GET', '/admin/parent-orders/1/detail-panel', 200);

        self::assertStringContainsString('child-one', $body);
        self::assertStringContainsString('href="/admin/parent-orders"', $body, 'Back link to the parent listing');
    }

    private function body(string $method, string $uri, int $expectedStatus): string
    {
        $kernel = self::bootKernel();

        $response = $kernel->handle(
            Request::create($uri, $method),
            HttpKernelInterface::MAIN_REQUEST,
            catch: true,
        );

        self::assertSame($expectedStatus, $response->getStatusCode(), \sprintf('%s %s', $method, $uri));

        return (string) $response->getContent();
    }
}
