<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Routing\BundleRouteLoader;

/**
 * Locks the route surface assembled by {@see BundleRouteLoader}
 * (consumed by `Bundle::boot()` for the v0.5.4 auto-import):
 *
 * - every shipped endpoint is present under its stable name,
 * - every route carries a populated `_controller` default —
 *   regression for the v0.5.4 prototype where an empty
 *   `configureRoute()` loaded the patterns but left `_controller`
 *   unset, so every endpoint 404'd despite showing in debug:router,
 * - the paths stay under `/admin/` (the hard-coded prefix the C2
 *   multi-tenant opt-out — `auto_register_routes: false` — exists
 *   to work around; if it ever changes, that documentation and the
 *   opt-out guidance must move with it).
 */
#[CoversClass(BundleRouteLoader::class)]
final class BundleRouteLoaderTest extends TestCase
{
    private const EXPECTED_ROUTES = [
        'polysource_saved_view_create',
        'polysource_saved_view_delete',
        'polysource_saved_view_toggle_default',
        'polysource_column_preferences_update',
        'polysource_export',
        'polysource_matching_count',
        'polysource_column_order_move',
        'polysource_filter_url_token_resolve',
        'polysource_row_detail',
    ];

    #[Test]
    public function loadsEveryShippedEndpointUnderItsStableName(): void
    {
        $collection = (new BundleRouteLoader())->loadAll();

        $names = array_keys($collection->all());
        sort($names);
        $expected = self::EXPECTED_ROUTES;
        sort($expected);

        self::assertSame($expected, $names);
    }

    #[Test]
    public function everyRouteHasAControllerDefault(): void
    {
        foreach ((new BundleRouteLoader())->loadAll() as $name => $route) {
            $controller = $route->getDefault('_controller');

            self::assertIsString($controller, \sprintf('Route "%s" has no _controller default.', $name));
            self::assertStringContainsString('::', $controller, \sprintf(
                'Route "%s" default "%s" is not a Class::method reference.',
                $name,
                $controller,
            ));
            self::assertStringStartsWith('Polysource\EasyAdminFilterBridge\Controller\\', $controller);
        }
    }

    #[Test]
    public function everyPathLivesUnderTheAdminPrefix(): void
    {
        foreach ((new BundleRouteLoader())->loadAll() as $name => $route) {
            self::assertStringStartsWith('/admin/', $route->getPath(), \sprintf(
                'Route "%s" escapes the hard-coded /admin/ prefix the C2 opt-out documents.',
                $name,
            ));
        }
    }

    #[Test]
    public function loadingTwiceYieldsTheSameSurface(): void
    {
        $loader = new BundleRouteLoader();

        self::assertSame(
            array_keys($loader->loadAll()->all()),
            array_keys($loader->loadAll()->all()),
        );
    }
}
