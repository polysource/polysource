<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\Pagination;

#[CoversClass(Pagination::class)]
final class PaginationTest extends TestCase
{
    #[Test]
    public function itDefaultsTo20PerPageStartingAtOffset0(): void
    {
        $p = new Pagination();
        self::assertSame(0, $p->offset);
        self::assertSame(20, $p->limit);
        self::assertNull($p->cursor);
    }

    #[Test]
    public function itAcceptsCustomOffsetAndLimit(): void
    {
        $p = new Pagination(40, 10);
        self::assertSame(40, $p->offset);
        self::assertSame(10, $p->limit);
    }

    #[Test]
    public function itRejectsNegativeOffset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('offset must be >= 0');
        new Pagination(-1, 10);
    }

    #[Test]
    public function itRejectsZeroOrNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('limit must be >= 1');
        new Pagination(0, 0);
    }

    #[Test]
    public function withersReturnNewInstanceWithoutMutating(): void
    {
        $original = new Pagination(0, 20);
        $modified = $original->withOffset(50)->withLimit(10)->withCursor('abc');

        self::assertSame(0, $original->offset);
        self::assertSame(20, $original->limit);
        self::assertNull($original->cursor);

        self::assertSame(50, $modified->offset);
        self::assertSame(10, $modified->limit);
        self::assertSame('abc', $modified->cursor);
    }
}
