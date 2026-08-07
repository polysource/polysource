<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Routing;

use LogicException;
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
    public function generatesFiveRoutesPerResource(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([
            new FakeResource('flags'),
            new FakeResource('failed-messages'),
        ]));

        $collection = $loader->load('.', 'polysource');

        self::assertCount(10, $collection);
        self::assertNotNull($collection->get('polysource_flags_index'));
        self::assertNotNull($collection->get('polysource_flags_detail'));
        self::assertNotNull($collection->get('polysource_flags_detail_panel'));
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

    #[Test]
    public function actionRouteRejectsBatchAsId(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([new FakeResource('flags')]));
        $collection = $loader->load('.', 'polysource');

        $route = $collection->get('polysource_flags_action');
        self::assertNotNull($route);
        self::assertSame('(?!batch$)[^/]+', $route->getRequirement('id'));
    }

    #[Test]
    public function bulkRouteIsRegisteredBeforeParameterisedActionRoute(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([new FakeResource('flags')]));
        $collection = $loader->load('.', 'polysource');

        $names = array_keys($collection->all());
        $bulkPos = array_search('polysource_flags_bulk_action', $names, true);
        $actionPos = array_search('polysource_flags_action', $names, true);
        self::assertIsInt($bulkPos);
        self::assertIsInt($actionPos);
        self::assertLessThan($actionPos, $bulkPos, 'Bulk route must precede action route in the collection.');
    }

    #[Test]
    public function detectsRouteKeyCollisionsAfterNormalisation(): void
    {
        $loader = new PolysourceRouteLoader(new ResourceRegistry([
            new FakeResource('my-resource'),
            new FakeResource('my_resource'),
        ]));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('route key collision');
        $loader->load('.', 'polysource');
    }
}
