<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use ArrayObject;
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

    #[Test]
    public function asArrayMaterialisesGeneratorItems(): void
    {
        $generator = static function () {
            yield new DataRecord(1, []);
            yield new DataRecord(2, []);
        };

        $p = new DataPage(items: $generator(), total: null);
        $array = $p->asArray();

        self::assertCount(2, $array);
        self::assertSame(0, array_keys($array)[0]);
    }

    #[Test]
    public function isEmptyWithCountableTraversable(): void
    {
        // ArrayObject is iterable + Countable.
        $p = new DataPage(items: new ArrayObject([]), total: null);
        self::assertTrue($p->isEmpty());

        $p = new DataPage(items: new ArrayObject([new DataRecord(1, [])]), total: null);
        self::assertFalse($p->isEmpty());
    }

    #[Test]
    public function isEmptyFallsBackToTotalWhenItemsAreNonCountableTraversable(): void
    {
        // Generator is iterable but NOT Countable.
        $generator = static function () { yield new DataRecord(1, []); };

        $p = new DataPage(items: $generator(), total: 5);
        self::assertFalse($p->isEmpty());

        $p = new DataPage(items: $generator(), total: 0);
        self::assertTrue($p->isEmpty());
    }

    #[Test]
    public function isEmptyMaterialisesNonCountableTraversableAsLastResort(): void
    {
        // `yield from []` produces an empty Generator without unreachable code.
        $generator = static function () {
            yield from [];
        };

        $p = new DataPage(items: $generator(), total: null);
        self::assertTrue($p->isEmpty());
    }
}
