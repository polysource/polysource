<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Doctrine\DataSource\DoctrineDataSource;
use Polysource\Adapter\Doctrine\Tests\Fixture\Product;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Polysource\Core\Query\Pagination;
use Polysource\Core\Query\SortDirection;
use RuntimeException;

/**
 * End-to-end exercise of {@see DoctrineDataSource} against a SQLite
 * EntityManager — covers every supported filter operator, sort,
 * pagination, and the full WritableDataSourceInterface surface.
 */
final class DoctrineDataSourceTest extends TestCase
{
    private EntityManager $em;
    private DoctrineDataSource $source;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__) . '/Fixture'],
            isDevMode: true,
        );
        // Doctrine ORM 3.x native lazy objects gated on PHP 8.4 — see audit tests.
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $tool = new SchemaTool($this->em);
        $tool->createSchema([$this->em->getClassMetadata(Product::class)]);

        $this->source = new DoctrineDataSource(
            em: $this->em,
            entityClass: Product::class,
            allowedFilters: [
                'name' => 'name',
                'sku' => 'sku',
                'priceCents' => 'priceCents',
                'createdAt' => 'createdAt',
            ],
            searchProperty: 'name',
        );

        $this->seed();
    }

    public function testSearchReturnsAllRecordsWithExactCount(): void
    {
        $page = $this->source->search(new DataQuery('products'));

        self::assertSame(4, $page->total);
        self::assertCount(4, $page->asArray());
    }

    public function testFindByIdReturnsDataRecord(): void
    {
        $page = $this->source->search(new DataQuery('products'));
        $first = $page->asArray()[0];

        $record = $this->source->find((string) $first->identifier);
        self::assertNotNull($record);
        self::assertSame($first->properties['name'], $record->properties['name']);
    }

    public function testFindReturnsNullForUnknownId(): void
    {
        self::assertNull($this->source->find('999999'));
    }

    public function testRejectsUnknownSearchPropertyAtConstruction(): void
    {
        // Defence against compromised/typo'd wiring: searchProperty must
        // be a real mapped field, otherwise it would flow unchecked into
        // an SQL fragment via sprintf('r.%s LIKE :search', ...).
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/searchProperty .* is not a mapped field/');

        new DoctrineDataSource(
            em: $this->em,
            entityClass: Product::class,
            searchProperty: 'doesNotExist; DROP TABLE products; --',
        );
    }

    public function testAcceptsKnownSearchPropertyAtConstruction(): void
    {
        // Sanity: a valid mapped field constructs without raising. The
        // setUp() already builds the canonical case ($searchProperty='name')
        // — this assertion is here so a future refactor of the validation
        // doesn't accidentally over-tighten and reject legitimate usage.
        new DoctrineDataSource(
            em: $this->em,
            entityClass: Product::class,
            searchProperty: 'sku',
        );

        // Construction without raising is the contract under test. PHPUnit
        // requires an assertion to mark the test as exercised.
        self::expectNotToPerformAssertions();
    }

    public function testFilterEqRestrictsResults(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('sku', new FilterCriterion('sku', FilterOperator::Eq, 'WIDGET-1'));

        $items = $this->source->search($query)->asArray();
        self::assertCount(1, $items);
        self::assertSame('Blue widget', $items[0]->properties['name']);
    }

    public function testFilterInRestrictsResults(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('sku', new FilterCriterion('sku', FilterOperator::In, ['WIDGET-1', 'GADGET-1']));

        self::assertSame(2, $this->source->count($query));
    }

    public function testFilterBetweenOnInteger(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('priceCents', new FilterCriterion('priceCents', FilterOperator::Between, [1000, 5000]));

        $items = $this->source->search($query)->asArray();
        self::assertCount(2, $items);
        $names = array_map(
            static fn ($r): string => \is_string($r->properties['name']) ? $r->properties['name'] : '',
            $items,
        );
        self::assertContains('Blue widget', $names);
        self::assertContains('Red widget', $names);
    }

    public function testFilterLikeWrapsValue(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('name', new FilterCriterion('name', FilterOperator::Like, 'widget'));

        self::assertSame(2, $this->source->count($query));
    }

    public function testSearchTextLikeWildcard(): void
    {
        $query = (new DataQuery('products'))
            ->withSearchText('Gadget');

        self::assertSame(1, $this->source->count($query));
    }

    public function testUnknownFilterPropertyIsSilentlySkipped(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('secret', new FilterCriterion('secret', FilterOperator::Eq, 'whatever'));

        self::assertSame(4, $this->source->count($query), 'Unknown filter property must not constrain.');
    }

    public function testSortDescendingByPriceCents(): void
    {
        $query = (new DataQuery('products'))
            ->withSort('priceCents', SortDirection::DESC);

        $items = $this->source->search($query)->asArray();
        $prices = array_map(
            static fn ($r): int => \is_scalar($r->properties['priceCents']) ? (int) $r->properties['priceCents'] : 0,
            $items,
        );
        self::assertSame([10000, 5000, 1000, 500], $prices);
    }

    public function testPaginationOffsetLimit(): void
    {
        $query = (new DataQuery('products'))
            ->withSort('priceCents', SortDirection::ASC)
            ->withPagination(new Pagination(offset: 1, limit: 2));

        $items = $this->source->search($query)->asArray();
        self::assertCount(2, $items);
        self::assertSame(1000, (int) (\is_scalar($items[0]->properties['priceCents']) ? $items[0]->properties['priceCents'] : 0));
        self::assertSame(5000, (int) (\is_scalar($items[1]->properties['priceCents']) ? $items[1]->properties['priceCents'] : 0));
    }

    public function testCreatePersistsAndReturnsRecord(): void
    {
        $record = $this->source->create(new DataPayload([
            'name' => 'Yellow gadget',
            'sku' => 'GADGET-2',
            'priceCents' => 2500,
            'createdAt' => new DateTimeImmutable('2026-05-06T10:00:00'),
        ]));

        self::assertSame('Yellow gadget', $record->properties['name']);
        self::assertSame(5, $this->source->count(new DataQuery('products')));
    }

    public function testUpdateMutatesAndReturnsRecord(): void
    {
        $page = $this->source->search(new DataQuery('products'));
        $first = $page->asArray()[0];

        $updated = $this->source->update($first->identifier, new DataPayload([
            'name' => 'Updated name',
            'priceCents' => 999,
        ]));

        self::assertSame('Updated name', $updated->properties['name']);
        self::assertSame(999, (int) (\is_scalar($updated->properties['priceCents']) ? $updated->properties['priceCents'] : 0));
    }

    public function testUpdateRejectsUnknownIdentifier(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->update('999999', new DataPayload(['name' => 'x']));
    }

    public function testDeleteRemovesRecord(): void
    {
        $page = $this->source->search(new DataQuery('products'));
        $first = $page->asArray()[0];

        $this->source->delete($first->identifier);

        self::assertSame(3, $this->source->count(new DataQuery('products')));
    }

    public function testDeleteIdempotentOnUnknownId(): void
    {
        $this->source->delete('999999'); // must not throw
        self::assertSame(4, $this->source->count(new DataQuery('products')));
    }

    public function testRecordPropertiesIncludeSerialisedDates(): void
    {
        $page = $this->source->search(new DataQuery('products'));
        $first = $page->asArray()[0];

        self::assertIsString($first->properties['createdAt']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $first->properties['createdAt']);
    }

    private function seed(): void
    {
        $rows = [
            ['Blue widget', 'WIDGET-1', 1000],
            ['Red widget', 'WIDGET-2', 5000],
            ['Green gadget', 'GADGET-1', 500],
            ['Premium frobnicator', 'FROB-1', 10000],
        ];

        foreach ($rows as [$name, $sku, $priceCents]) {
            $product = new Product();
            $product->name = $name;
            $product->sku = $sku;
            $product->priceCents = $priceCents;
            $this->em->persist($product);
        }
        $this->em->flush();
        $this->em->clear();
    }
}
