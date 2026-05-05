<?php

declare(strict_types=1);

namespace Polysource\Adapter\Meilisearch\Tests\Unit\DataSource;

use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Meilisearch\DataSource\MeilisearchDataSource;
use Polysource\Adapter\Meilisearch\Tests\InMemory\InMemoryMeilisearchIndex;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\Pagination;
use Polysource\Core\Query\SortDirection;
use RuntimeException;

final class MeilisearchDataSourceTest extends TestCase
{
    private InMemoryMeilisearchIndex $index;
    private MeilisearchDataSource $source;

    protected function setUp(): void
    {
        $this->index = new InMemoryMeilisearchIndex();
        $this->source = new MeilisearchDataSource($this->index);

        $this->index->addDocuments([
            ['id' => 'p-1', 'name' => 'Blue widget', 'category' => 'gadgets', 'priceCents' => 1000],
            ['id' => 'p-2', 'name' => 'Red widget', 'category' => 'gadgets', 'priceCents' => 5000],
            ['id' => 'p-3', 'name' => 'Green frobnicator', 'category' => 'tools', 'priceCents' => 10000],
            ['id' => 'p-4', 'name' => 'Yellow gadget', 'category' => 'gadgets', 'priceCents' => 500],
        ]);
    }

    public function testEmptyQueryReturnsAllDocuments(): void
    {
        $page = $this->source->search(new DataQuery('products'));
        self::assertSame(4, $page->total);
        self::assertCount(4, $page->asArray());
    }

    public function testSearchTextRanksByFulltextMatch(): void
    {
        $page = $this->source->search((new DataQuery('products'))->withSearchText('widget'));
        self::assertSame(2, $page->total);
    }

    public function testFilterEqRestrictsResults(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('category', new FilterCriterion('category', 'eq', 'tools'));

        $items = $this->source->search($query)->asArray();
        self::assertCount(1, $items);
        self::assertSame('p-3', $items[0]->identifier);
    }

    public function testFilterInWhitelistsValues(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('id', new FilterCriterion('id', 'in', ['p-1', 'p-3']));

        $ids = array_map(static fn ($r) => $r->identifier, $this->source->search($query)->asArray());
        sort($ids);
        self::assertSame(['p-1', 'p-3'], $ids);
    }

    public function testFilterRangeOperators(): void
    {
        $query = (new DataQuery('products'))
            ->withFilter('priceCents', new FilterCriterion('priceCents', 'gte', 5000));

        self::assertSame(2, $this->source->count($query));
    }

    public function testSortDescending(): void
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

    public function testFindReturnsRecord(): void
    {
        $record = $this->source->find('p-1');
        self::assertNotNull($record);
        self::assertSame('Blue widget', $record->properties['name']);
    }

    public function testFindReturnsNullForUnknownDocument(): void
    {
        self::assertNull($this->source->find('p-999'));
    }

    public function testCreateUpsertsDocument(): void
    {
        $record = $this->source->create(new DataPayload([
            'id' => 'p-99',
            'name' => 'Brand new',
            'category' => 'tools',
            'priceCents' => 7777,
        ]));

        self::assertSame('p-99', $record->identifier);
        self::assertSame(5, $this->source->count(new DataQuery('products')));
    }

    public function testCreateRejectsMissingPrimaryKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->create(new DataPayload(['name' => 'no id']));
    }

    public function testUpdateForcesIdentifier(): void
    {
        $record = $this->source->update('p-1', new DataPayload([
            'name' => 'Updated widget',
            'category' => 'gadgets',
            'priceCents' => 1500,
        ]));

        self::assertSame('p-1', $record->identifier);
        $stored = $this->source->find('p-1');
        self::assertNotNull($stored);
        self::assertSame('Updated widget', $stored->properties['name']);
    }

    public function testDeleteIsIdempotent(): void
    {
        $this->source->delete('p-1');
        self::assertNull($this->source->find('p-1'));

        $this->source->delete('p-1'); // second call must not throw
    }

    public function testPaginationOffsetLimit(): void
    {
        $query = (new DataQuery('products'))
            ->withSort('priceCents', SortDirection::ASC)
            ->withPagination(new Pagination(offset: 1, limit: 2));

        $items = $this->source->search($query)->asArray();
        self::assertCount(2, $items);
        $prices = array_map(
            static fn ($r): int => \is_scalar($r->properties['priceCents']) ? (int) $r->properties['priceCents'] : 0,
            $items,
        );
        self::assertSame([1000, 5000], $prices);
    }

    public function testFilterPropertyNameIsSanitised(): void
    {
        // A filter property carrying SQL/Meilisearch syntax must
        // not slip into the filter expression — the data source
        // strips non-identifier characters.
        $query = (new DataQuery('products'))
            ->withFilter("category' OR 1=1; --", new FilterCriterion("category' OR 1=1; --", 'eq', 'tools'));

        // The malicious property is sanitised to "categoryOR11" which
        // matches no filterableAttribute on the index — no documents
        // get through. The point: no injection into the filter
        // expression.
        $items = $this->source->search($query)->asArray();
        self::assertCount(0, $items);
    }

    public function testCustomPrimaryKey(): void
    {
        $index = new InMemoryMeilisearchIndex(primaryKey: 'sku');
        $source = new MeilisearchDataSource($index, primaryKey: 'sku');

        $source->create(new DataPayload(['sku' => 'WIDGET-1', 'name' => 'Custom-keyed']));
        $found = $source->find('WIDGET-1');

        self::assertNotNull($found);
        self::assertSame('WIDGET-1', $found->identifier);
    }
}
