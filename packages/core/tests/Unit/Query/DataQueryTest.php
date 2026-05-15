<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Polysource\Core\Query\Pagination;
use Polysource\Core\Query\SortDirection;

#[CoversClass(DataQuery::class)]
final class DataQueryTest extends TestCase
{
    #[Test]
    public function itDefaultsToEmptyFiltersAndSort(): void
    {
        $q = new DataQuery('products');
        self::assertSame('products', $q->resourceName);
        self::assertNull($q->searchText);
        self::assertSame([], $q->filters);
        self::assertSame([], $q->sort);
        self::assertNull($q->pagination);
    }

    #[Test]
    public function withSearchTextReturnsNewInstance(): void
    {
        $original = new DataQuery('products');
        $modified = $original->withSearchText('blue');

        self::assertNull($original->searchText);
        self::assertSame('blue', $modified->searchText);
        self::assertNotSame($original, $modified);
    }

    #[Test]
    public function withFilterAddsToTheFilterMap(): void
    {
        $q = (new DataQuery('products'))
            ->withFilter('status', new FilterCriterion('status', FilterOperator::Eq, 'active'))
            ->withFilter('price', new FilterCriterion('price', FilterOperator::Gt, 10));

        self::assertCount(2, $q->filters);
        self::assertArrayHasKey('status', $q->filters);
        self::assertArrayHasKey('price', $q->filters);
    }

    #[Test]
    public function withFilterReplacesExistingFilterWithSameName(): void
    {
        $q = (new DataQuery('products'))
            ->withFilter('status', new FilterCriterion('status', FilterOperator::Eq, 'active'))
            ->withFilter('status', new FilterCriterion('status', FilterOperator::Eq, 'archived'));

        self::assertCount(1, $q->filters);
        self::assertSame('archived', $q->filters['status']->value);
    }

    #[Test]
    public function withoutFilterRemovesFromTheMap(): void
    {
        $q = (new DataQuery('products'))
            ->withFilter('status', new FilterCriterion('status', FilterOperator::Eq, 'active'));
        $q = $q->withoutFilter('status');

        self::assertSame([], $q->filters);
    }

    #[Test]
    public function withSortAddsAndReplacesSortEntries(): void
    {
        $q = (new DataQuery('products'))
            ->withSort('name', SortDirection::ASC)
            ->withSort('createdAt', SortDirection::DESC)
            ->withSort('name', SortDirection::DESC);

        self::assertSame(SortDirection::DESC, $q->sort['name']);
        self::assertSame(SortDirection::DESC, $q->sort['createdAt']);
        self::assertCount(2, $q->sort);
    }

    #[Test]
    public function withPaginationCanSetAndUnset(): void
    {
        $q = new DataQuery('products');
        $p = new Pagination(20, 10);
        $q1 = $q->withPagination($p);
        $q2 = $q1->withPagination(null);

        self::assertNull($q->pagination);
        self::assertSame($p, $q1->pagination);
        self::assertNull($q2->pagination);
    }
}
