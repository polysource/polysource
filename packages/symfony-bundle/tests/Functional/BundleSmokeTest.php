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
    public function indexRouteRespondsWithSerialisedPage(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/flags', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $payload = self::decodePayload($response->getContent());
        self::assertSame('flags', self::nestedString($payload, 'resource.name'));
        self::assertSame('Feature flags', self::nestedString($payload, 'resource.label'));
        self::assertSame(2, self::nestedInt($payload, 'page.total'));
        self::assertCount(2, self::nestedArray($payload, 'page.items'));
    }

    #[Test]
    public function detailRouteResolvesIndividualRecord(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/flags/1', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $payload = self::decodePayload($response->getContent());
        self::assertSame('1', self::nestedString($payload, 'record.id'));
        self::assertSame('flag-a', self::nestedString($payload, 'record.properties.name'));
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

    /**
     * @return array<string, mixed>
     */
    private static function decodePayload(string|false $content): array
    {
        self::assertIsString($content);
        $payload = json_decode($content, true);
        self::assertIsArray($payload);

        /** @var array<string, mixed> $payload */
        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function nestedString(array $payload, string $path): string
    {
        $value = self::nestedValue($payload, $path);
        self::assertIsString($value);

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function nestedInt(array $payload, string $path): int
    {
        $value = self::nestedValue($payload, $path);
        self::assertIsInt($value);

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    private static function nestedArray(array $payload, string $path): array
    {
        $value = self::nestedValue($payload, $path);
        self::assertIsArray($value);

        return $value;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function nestedValue(array $payload, string $path): mixed
    {
        $cursor = $payload;
        foreach (explode('.', $path) as $segment) {
            self::assertIsArray($cursor, \sprintf('Path "%s" is not navigable.', $path));
            self::assertArrayHasKey($segment, $cursor, \sprintf('Path "%s" is missing key "%s".', $path, $segment));
            $cursor = $cursor[$segment];
        }

        return $cursor;
    }
}
