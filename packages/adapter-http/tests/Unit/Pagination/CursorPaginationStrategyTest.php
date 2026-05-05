<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Tests\Unit\Pagination;

use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Http\Pagination\CursorPaginationStrategy;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\Pagination;

final class CursorPaginationStrategyTest extends TestCase
{
    public function testFirstPageOmitsCursorParam(): void
    {
        $strategy = new CursorPaginationStrategy();
        $params = $strategy->buildQueryParams(
            (new DataQuery('users'))->withPagination(new Pagination(offset: 0, limit: 30))
        );

        self::assertSame(['limit' => 30], $params);
    }

    public function testNonZeroOffsetTreatedAsOpaqueCursor(): void
    {
        $strategy = new CursorPaginationStrategy();
        $params = $strategy->buildQueryParams(
            (new DataQuery('users'))->withPagination(new Pagination(offset: 999, limit: 30))
        );

        self::assertSame(['limit' => 30, 'cursor' => '999'], $params);
    }

    public function testParseExtractsCursor(): void
    {
        $strategy = new CursorPaginationStrategy();
        $parsed = $strategy->parseResponse(
            ['data' => [['id' => 'a']], 'next_cursor' => 'next-token'],
            [],
        );

        self::assertCount(1, $parsed['items']);
        self::assertNull($parsed['total']);
        self::assertSame('next-token', $parsed['nextCursor']);
    }

    public function testParseEmptyCursorReturnsNull(): void
    {
        $strategy = new CursorPaginationStrategy();
        $parsed = $strategy->parseResponse(['data' => [], 'next_cursor' => ''], []);

        self::assertNull($parsed['nextCursor']);
    }
}
