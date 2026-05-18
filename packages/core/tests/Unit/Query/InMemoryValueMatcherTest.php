<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\FilterOperator;
use Polysource\Core\Query\InMemoryValueMatcher;

#[CoversClass(InMemoryValueMatcher::class)]
final class InMemoryValueMatcherTest extends TestCase
{
    #[Test]
    public function eqLooseComparesScalarsAsStrings(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches('42', FilterOperator::Eq, 42));
        self::assertTrue(InMemoryValueMatcher::matches('paid', FilterOperator::Eq, 'paid'));
        self::assertFalse(InMemoryValueMatcher::matches('paid', FilterOperator::Eq, 'cancelled'));
    }

    #[Test]
    public function eqWithBooleanExpectedCoercesValueToBool(): void
    {
        // Adapter Redis stored '1'/'0' for booleans. The matcher
        // coerces the value-side to bool when the expected-side is
        // a bool, preserving Redis's behaviour.
        self::assertTrue(InMemoryValueMatcher::matches('1', FilterOperator::Eq, true));
        self::assertTrue(InMemoryValueMatcher::matches('yes', FilterOperator::Eq, true));
        self::assertTrue(InMemoryValueMatcher::matches('0', FilterOperator::Eq, false));
        self::assertFalse(InMemoryValueMatcher::matches('1', FilterOperator::Eq, false));
    }

    #[Test]
    public function neqIsInverseOfEq(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches('paid', FilterOperator::Neq, 'cancelled'));
        self::assertFalse(InMemoryValueMatcher::matches('paid', FilterOperator::Neq, 'paid'));
    }

    #[Test]
    public function inMatchesAnyOfList(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches('paid', FilterOperator::In, ['paid', 'pending']));
        self::assertFalse(InMemoryValueMatcher::matches('paid', FilterOperator::In, ['cancelled', 'pending']));
    }

    #[Test]
    public function inReturnsFalseForNonArrayExpected(): void
    {
        // Defensive: non-array `expected` must NOT crash and must
        // NOT match (criterion is malformed).
        self::assertFalse(InMemoryValueMatcher::matches('paid', FilterOperator::In, 'not-a-list'));
    }

    #[Test]
    public function ninIsInverseOfIn(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches('paid', FilterOperator::Nin, ['cancelled']));
        self::assertFalse(InMemoryValueMatcher::matches('paid', FilterOperator::Nin, ['paid']));
    }

    #[Test]
    public function likeIsCaseInsensitiveSubstring(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches('Hello World', FilterOperator::Like, 'world'));
        self::assertTrue(InMemoryValueMatcher::matches('Hello World', FilterOperator::Like, 'HELLO'));
        self::assertFalse(InMemoryValueMatcher::matches('Hello World', FilterOperator::Like, 'goodbye'));
    }

    #[Test]
    public function likeReturnsFalseForNonString(): void
    {
        self::assertFalse(InMemoryValueMatcher::matches(42, FilterOperator::Like, 'foo'));
        self::assertFalse(InMemoryValueMatcher::matches('foo', FilterOperator::Like, 42));
    }

    #[Test]
    public function gtComparesNumerically(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches(10, FilterOperator::Gt, 5));
        self::assertTrue(InMemoryValueMatcher::matches('10', FilterOperator::Gt, '5'));
        self::assertFalse(InMemoryValueMatcher::matches(5, FilterOperator::Gt, 10));
    }

    #[Test]
    public function gteIsInclusive(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches(5, FilterOperator::Gte, 5));
        self::assertTrue(InMemoryValueMatcher::matches(10, FilterOperator::Gte, 5));
        self::assertFalse(InMemoryValueMatcher::matches(4, FilterOperator::Gte, 5));
    }

    #[Test]
    public function dateComparisonsRespectChronology(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches('2026-05-18', FilterOperator::Gt, '2026-01-01'));
        self::assertFalse(InMemoryValueMatcher::matches('2026-01-01', FilterOperator::Gt, '2026-05-18'));

        // DateTimeInterface objects compared via timestamp.
        $earlier = new DateTimeImmutable('2026-01-01');
        $later = new DateTimeImmutable('2026-05-18');
        self::assertTrue(InMemoryValueMatcher::matches($later, FilterOperator::Gt, $earlier));
    }

    #[Test]
    public function betweenIncludesBothBounds(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches(5, FilterOperator::Between, [1, 10]));
        self::assertTrue(InMemoryValueMatcher::matches(1, FilterOperator::Between, [1, 10]));
        self::assertTrue(InMemoryValueMatcher::matches(10, FilterOperator::Between, [1, 10]));
        self::assertFalse(InMemoryValueMatcher::matches(0, FilterOperator::Between, [1, 10]));
        self::assertFalse(InMemoryValueMatcher::matches(11, FilterOperator::Between, [1, 10]));
    }

    #[Test]
    public function betweenRequiresExactlyTwoBounds(): void
    {
        // Defensive against malformed `expected`.
        self::assertFalse(InMemoryValueMatcher::matches(5, FilterOperator::Between, [1]));
        self::assertFalse(InMemoryValueMatcher::matches(5, FilterOperator::Between, [1, 2, 3]));
        self::assertFalse(InMemoryValueMatcher::matches(5, FilterOperator::Between, 'not-an-array'));
    }

    #[Test]
    public function isNullChecksForNull(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches(null, FilterOperator::IsNull, null));
        self::assertFalse(InMemoryValueMatcher::matches('', FilterOperator::IsNull, null));
        self::assertFalse(InMemoryValueMatcher::matches(0, FilterOperator::IsNull, null));
    }

    #[Test]
    public function isNotNullIsInverseOfIsNull(): void
    {
        self::assertTrue(InMemoryValueMatcher::matches('', FilterOperator::IsNotNull, null));
        self::assertTrue(InMemoryValueMatcher::matches(0, FilterOperator::IsNotNull, null));
        self::assertFalse(InMemoryValueMatcher::matches(null, FilterOperator::IsNotNull, null));
    }
}
