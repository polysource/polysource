<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Tests\Unit\Pagination;

use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Http\Pagination\PageNumberPaginationStrategy;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\Pagination;

final class PageNumberPaginationStrategyTest extends TestCase
{
    public function testDefaultParamsForFirstPage(): void
    {
        $strategy = new PageNumberPaginationStrategy();

        $params = $strategy->buildQueryParams(
            (new DataQuery('users'))->withPagination(new Pagination(offset: 0, limit: 25))
        );

        self::assertSame(['page' => 1, 'per_page' => 25], $params);
    }

    public function testPageDerivedFromOffset(): void
    {
        $strategy = new PageNumberPaginationStrategy();
        $params = $strategy->buildQueryParams(
            (new DataQuery('users'))->withPagination(new Pagination(offset: 50, limit: 25))
        );
        self::assertSame(['page' => 3, 'per_page' => 25], $params);
    }

    public function testParseDefaultEnvelope(): void
    {
        $strategy = new PageNumberPaginationStrategy();
        $parsed = $strategy->parseResponse(
            ['data' => [['id' => 1], ['id' => 2]], 'meta' => ['total' => 42]],
            [],
        );

        self::assertCount(2, $parsed['items']);
        self::assertSame(42, $parsed['total']);
        self::assertNull($parsed['nextCursor']);
    }

    public function testCustomKeysForWordPressShape(): void
    {
        $strategy = new PageNumberPaginationStrategy(
            itemsKey: 'posts',
            totalKey: 'meta.x_wp_total',
        );
        $parsed = $strategy->parseResponse(
            ['posts' => [['id' => 99]], 'meta' => ['x_wp_total' => '120']],
            [],
        );

        self::assertCount(1, $parsed['items']);
        self::assertSame(120, $parsed['total']);
    }

    public function testHandlesMissingTotalGracefully(): void
    {
        $strategy = new PageNumberPaginationStrategy();
        $parsed = $strategy->parseResponse(['data' => []], []);

        self::assertSame([], $parsed['items']);
        self::assertNull($parsed['total']);
    }
}
