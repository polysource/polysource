<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;

#[CoversClass(FilterCriterion::class)]
final class FilterCriterionTest extends TestCase
{
    #[Test]
    public function itHoldsPropertyOperatorAndValue(): void
    {
        $c = new FilterCriterion('status', FilterOperator::Eq, 'active');
        self::assertSame('status', $c->property);
        self::assertSame(FilterOperator::Eq, $c->operator);
        self::assertSame('eq', $c->operator->value);
        self::assertSame('active', $c->value);
    }

    #[Test]
    public function itAcceptsArrayValueForBetweenOrIn(): void
    {
        $c = new FilterCriterion('age', FilterOperator::Between, [18, 65]);
        self::assertSame([18, 65], $c->value);
    }

    #[Test]
    public function itAcceptsNullValueForNullChecks(): void
    {
        $c = new FilterCriterion('deleted_at', FilterOperator::IsNull);
        self::assertNull($c->value);
    }
}
