<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\PolysourceEasyAdminFilterBridgeBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Router;

/**
 * Pins the bundle's `boot()` route auto-import behaviour:
 *
 *   - On non-EA kernels (C1, v0.5.7) → no routes spliced.
 *   - On EA kernels with default config → 8 polysource_* routes spliced.
 *   - On EA kernels with `auto_register_routes: false` (C2, v0.5.7) →
 *     no routes spliced, host imports them manually via routes.yaml.
 *   - On EA kernels with the routes already loaded (idempotency) →
 *     no double-splice.
 */
final class PolysourceEasyAdminFilterBridgeBundleTest extends TestCase
{
    /** @var list<string> The 8 routes the bridge ships and auto-loads */
    private const EXPECTED_ROUTES = [
        'polysource_saved_view_create',
        'polysource_saved_view_delete',
        'polysource_saved_view_toggle_default',
        'polysource_column_preferences_update',
        'polysource_export',
        'polysource_matching_count',
        'polysource_column_order_move',
        'polysource_filter_url_token_resolve',
    ];

    public function testBootSplicesRoutesOnEaKernelWithDefaultConfig(): void
    {
        $container = $this->buildContainer(easyAdminLoaded: true);
        $router = $this->makeEmptyRouter();
        $container->set('router', $router);

        $bundle = new PolysourceEasyAdminFilterBridgeBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        $registered = array_keys(iterator_to_array($router->getRouteCollection()));
        foreach (self::EXPECTED_ROUTES as $expected) {
            self::assertContains(
                $expected,
                $registered,
                \sprintf('Route "%s" must be auto-registered by Bundle::boot() on EA kernels', $expected),
            );
        }
    }

    public function testBootIsANoopOnNonEaKernel(): void
    {
        $container = $this->buildContainer(easyAdminLoaded: false);
        $router = $this->makeEmptyRouter();
        $container->set('router', $router);

        $bundle = new PolysourceEasyAdminFilterBridgeBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        self::assertCount(
            0,
            $router->getRouteCollection(),
            'No polysource routes must be auto-registered on non-EA kernels — multi-kernel apps registering the bridge globally must stay safe (C1, v0.5.7)',
        );
    }

    public function testBootIsANoopWhenAutoRegisterRoutesDisabled(): void
    {
        $container = $this->buildContainer(easyAdminLoaded: true, autoRegisterRoutes: false);
        $router = $this->makeEmptyRouter();
        $container->set('router', $router);

        $bundle = new PolysourceEasyAdminFilterBridgeBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        self::assertCount(
            0,
            $router->getRouteCollection(),
            'Multi-tenant hosts must be able to opt out of auto-registration via the auto_register_routes config flag (C2, v0.5.7)',
        );
    }

    public function testBootIsIdempotentWhenRoutesAlreadyImported(): void
    {
        $container = $this->buildContainer(easyAdminLoaded: true);

        // Simulate the host having already imported the bridge's
        // routes via routes.yaml — `polysource_export` is the probe
        // name Bundle::boot() checks.
        $router = $this->makeRouterWithExistingPolysourceRoutes();
        $container->set('router', $router);

        $countBefore = \count($router->getRouteCollection());

        $bundle = new PolysourceEasyAdminFilterBridgeBundle();
        $bundle->setContainer($container);
        $bundle->boot();

        self::assertCount(
            $countBefore,
            $router->getRouteCollection(),
            'Bundle::boot() must skip auto-loading when routes are already in the collection — host import path stays usable',
        );
    }

    /**
     * @param array<string, string> $extraBundles
     */
    private function buildContainer(
        bool $easyAdminLoaded,
        bool $autoRegisterRoutes = true,
        array $extraBundles = [],
    ): ContainerBuilder {
        $container = new ContainerBuilder();
        $bundles = $extraBundles;
        if ($easyAdminLoaded) {
            $bundles['EasyAdminBundle'] = 'EasyCorp\\Bundle\\EasyAdminBundle\\EasyAdminBundle';
        }
        $container->setParameter('kernel.bundles', $bundles);
        $container->setParameter(
            'polysource_easyadmin_filter_bridge.auto_register_routes',
            $autoRegisterRoutes,
        );

        return $container;
    }

    private function makeEmptyRouter(): Router
    {
        return new Router(new InMemoryCollectionLoader(new RouteCollection()), 'noop');
    }

    private function makeRouterWithExistingPolysourceRoutes(): Router
    {
        // Pre-populate with `polysource_export` — the probe Bundle::boot()
        // uses for idempotency. Test cares about the probe behaviour,
        // not the exact other routes that would be loaded.
        $collection = new RouteCollection();
        $collection->add('polysource_export', new Route('/admin/polysource/export/{resource}.{format}'));

        return new Router(new InMemoryCollectionLoader($collection), 'noop');
    }
}

/**
 * Minimal loader that hands back a pre-built RouteCollection. Avoids
 * pulling FrameworkBundle + a real attribute scanner into a Unit test.
 */
final class InMemoryCollectionLoader implements LoaderInterface
{
    public function __construct(private readonly RouteCollection $collection)
    {
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        return $this->collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return true;
    }

    public function getResolver(): LoaderResolverInterface
    {
        return new LoaderResolver();
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
    }
}
