<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\SortDirection;

#[CoversClass(SortDirection::class)]
final class SortDirectionTest extends TestCase
{
    #[Test]
    public function itExposesAscAndDescAsStringValues(): void
    {
        self::assertSame('asc', SortDirection::ASC->value);
        self::assertSame('desc', SortDirection::DESC->value);
    }

    #[Test]
    public function itCanBeBuiltFromString(): void
    {
        self::assertSame(SortDirection::ASC, SortDirection::from('asc'));
        self::assertSame(SortDirection::DESC, SortDirection::from('desc'));
    }
}
