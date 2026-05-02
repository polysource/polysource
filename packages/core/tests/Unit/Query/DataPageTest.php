<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataRecord;

#[CoversClass(DataPage::class)]
final class DataPageTest extends TestCase
{
    #[Test]
    public function emptyPageWithKnownTotal(): void
    {
        $p = new DataPage(items: [], total: 0);
        self::assertSame(0, $p->total);
        self::assertTrue($p->isEmpty());
        self::assertSame([], $p->asArray());
    }

    #[Test]
    public function emptyPageWithUnknownTotal(): void
    {
        $p = new DataPage(items: [], total: null);
        self::assertNull($p->total);
        self::assertTrue($p->isEmpty());
    }

    #[Test]
    public function nonEmptyPageWithItems(): void
    {
        $records = [
            new DataRecord(1, ['name' => 'a']),
            new DataRecord(2, ['name' => 'b']),
        ];
        $p = new DataPage(items: $records, total: 2);
        self::assertSame(2, $p->total);
        self::assertFalse($p->isEmpty());
        self::assertCount(2, $p->asArray());
    }

    #[Test]
    public function cursorBasedPageWithoutTotal(): void
    {
        $p = new DataPage(
            items: [new DataRecord(1, [])],
            total: null,
            nextCursor: 'opaque-token',
            prevCursor: null,
        );
        self::assertNull($p->total);
        self::assertSame('opaque-token', $p->nextCursor);
        self::assertNull($p->prevCursor);
    }
}
