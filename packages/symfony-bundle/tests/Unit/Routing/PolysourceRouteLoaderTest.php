<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Bundle\Routing\PolysourceRouteLoader;
use Polysource\Bundle\Tests\Fixture\FakeResource;

#[CoversClass(PolysourceRouteLoader::class)]
final class PolysourceRouteLoaderTest extends TestCase
{
    #[Test]
    public function supportsTheCanonicalType(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([]));

        self::assertTrue($loader->supports('.', 'polysource'));
        self::assertFalse($loader->supports('.', 'yaml'));
    }

    #[Test]
    public function generatesFourRoutesPerResource(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([
            new FakeResource('flags'),
            new FakeResource('failed-messages'),
        ]));

        $collection = $loader->load('.', 'polysource');

        self::assertCount(8, $collection);
        self::assertNotNull($collection->get('polysource_flags_index'));
        self::assertNotNull($collection->get('polysource_flags_detail'));
        self::assertNotNull($collection->get('polysource_flags_action'));
        self::assertNotNull($collection->get('polysource_flags_bulk_action'));
        self::assertNotNull($collection->get('polysource_failed_messages_index'));
    }

    #[Test]
    public function indexRouteCarriesResourceNameDefault(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([new FakeResource('flags')]));
        $collection = $loader->load('.', 'polysource');

        $route = $collection->get('polysource_flags_index');
        self::assertNotNull($route);
        self::assertSame('/admin/flags', $route->getPath());
        self::assertSame(['GET'], $route->getMethods());
        self::assertSame('flags', $route->getDefault('resourceName'));
        self::assertSame('index', $route->getDefault(PolysourceRouteLoader::ATTR_ACTION));
    }

    #[Test]
    public function detailRouteHasIdPlaceholder(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([new FakeResource('flags')]));
        $collection = $loader->load('.', 'polysource');

        $route = $collection->get('polysource_flags_detail');
        self::assertNotNull($route);
        self::assertSame('/admin/flags/{id}', $route->getPath());
    }

    #[Test]
    public function actionRouteIsPostOnly(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([new FakeResource('flags')]));
        $collection = $loader->load('.', 'polysource');

        $route = $collection->get('polysource_flags_action');
        self::assertNotNull($route);
        self::assertSame('/admin/flags/{id}/{action}', $route->getPath());
        self::assertSame(['POST'], $route->getMethods());
    }

    #[Test]
    public function customUrlPrefixIsRespected(): void
    {
        $loader = new PolysourceRouteLoader(
            new ResourceRegistry([new FakeResource('flags')]),
            urlPrefix: '/backoffice',
        );

        $route = $loader->load('.', 'polysource')->get('polysource_flags_index');
        self::assertNotNull($route);
        self::assertSame('/backoffice/flags', $route->getPath());
    }
}
