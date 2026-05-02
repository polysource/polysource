<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Bundle\Context\AdminContextProvider;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Bundle\Tests\Functional\App\TestKernel;
use Polysource\Bundle\Tests\Functional\App\TestResource;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * End-to-end smoke test for the Polysource bundle.
 *
 * Verifies that:
 *   - the bundle boots in a minimal Symfony kernel
 *   - the route loader generates routes for tagged resources
 *   - HTTP `GET /admin/flags` reaches IndexController and returns 200 JSON
 *   - HTTP `GET /admin/flags/1` reaches DetailController and returns the record
 *   - the registry collects services tagged `polysource.resource`
 */
final class BundleSmokeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): \Symfony\Component\HttpKernel\KernelInterface
    {
        return parent::createKernel($options + ['debug' => false]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Symfony installs an exception handler during kernel boot that is
        // not popped on shutdown. Restoring once leaves the stack as
        // PHPUnit found it. Cf. symfony/phpunit-bridge for the canonical
        // long-term fix (using its `SymfonyTestsListener` extension).
        restore_exception_handler();
    }

    #[Test]
    public function bundleBootsAndExposesRegistry(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(ResourceRegistry::class));
        $registry = $container->get(ResourceRegistry::class);
        self::assertInstanceOf(ResourceRegistry::class, $registry);
        self::assertTrue($registry->has('flags'));
        self::assertInstanceOf(TestResource::class, $registry->get('flags'));
    }

    #[Test]
    public function indexRouteRendersHtmlListing(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/flags', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringContainsString('<html', $body);
        self::assertStringContainsString('Feature flags', $body);
        self::assertStringContainsString('data-polysource-resource="flags"', $body);
        self::assertStringContainsString('/admin/flags/1', $body);
        self::assertStringContainsString('/admin/flags/2', $body);
    }

    #[Test]
    public function detailRouteRendersIndividualRecordHtml(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/flags/1', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringContainsString('<html', $body);
        self::assertStringContainsString('Feature flags', $body);
        self::assertStringContainsString('data-polysource-record="1"', $body);
    }

    #[Test]
    public function adminContextProviderHoldsTheCurrentContextDuringRequest(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $kernel->handle(Request::create('/admin/flags', 'GET'));

        $provider = self::getContainer()->get(AdminContextProvider::class);
        \assert($provider instanceof AdminContextProvider);
        $context = $provider->getContext();
        self::assertNotNull($context);
        self::assertSame('flags', $context->resource->getName());
        self::assertSame('index', $context->action);
    }
}
