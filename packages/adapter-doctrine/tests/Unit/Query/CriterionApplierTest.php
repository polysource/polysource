<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\Tests\Unit\Query;

use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Doctrine\Query\CriterionApplier;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;

#[CoversClass(CriterionApplier::class)]
final class CriterionApplierTest extends TestCase
{
    #[Test]
    public function unknownPropertyIsSilentlySkipped(): void
    {
        // Defense against arbitrary DQL: a criterion against a
        // property NOT in the allow-list must produce no clauses.
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');
        $qb->expects(self::never())->method('setParameter');

        $criterion = new FilterCriterion('hostile', FilterOperator::Eq, 'value');
        CriterionApplier::apply($qb, $criterion, 0, ['allowed' => 'real_field']);
    }

    #[Test]
    public function eqProducesEqualsClauseWithParameter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('r.status = :p0')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('p0', 'paid')
            ->willReturnSelf();

        $criterion = new FilterCriterion('status', FilterOperator::Eq, 'paid');
        CriterionApplier::apply($qb, $criterion, 0, ['status' => 'status']);
    }

    #[Test]
    public function likeWrapsValueInPercentSigns(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('r.name LIKE :p3')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('p3', '%foo%')
            ->willReturnSelf();

        $criterion = new FilterCriterion('name', FilterOperator::Like, 'foo');
        CriterionApplier::apply($qb, $criterion, 3, ['name' => 'name']);
    }

    #[Test]
    public function inEmitsListBinding(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('r.status IN (:p0)')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('p0', ['paid', 'pending'])
            ->willReturnSelf();

        $criterion = new FilterCriterion('status', FilterOperator::In, ['paid', 'pending']);
        CriterionApplier::apply($qb, $criterion, 0, ['status' => 'status']);
    }

    #[Test]
    public function inSkipsEmptyValueList(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        $criterion = new FilterCriterion('status', FilterOperator::In, []);
        CriterionApplier::apply($qb, $criterion, 0, ['status' => 'status']);
    }

    #[Test]
    public function betweenEmitsTwoParameters(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('r.createdAt BETWEEN :p0a AND :p0b')
            ->willReturnSelf();
        // Two setParameter calls — one per bound.
        $qb->expects(self::exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        $criterion = new FilterCriterion('createdAt', FilterOperator::Between, ['2026-01-01', '2026-12-31']);
        CriterionApplier::apply($qb, $criterion, 0, ['createdAt' => 'createdAt']);
    }

    #[Test]
    public function betweenSkipsMalformedValuePair(): void
    {
        // Defensive: BETWEEN requires exactly [start, end]. Other
        // shapes are silently dropped rather than crashing the query.
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        $criterion = new FilterCriterion('createdAt', FilterOperator::Between, ['only-one']);
        CriterionApplier::apply($qb, $criterion, 0, ['createdAt' => 'createdAt']);
    }

    #[Test]
    public function unmappedOperatorIsSilentlySkipped(): void
    {
        // Nin / IsNull / IsNotNull are not yet mapped — should not
        // crash the query but should not produce a clause either.
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        $criterion = new FilterCriterion('status', FilterOperator::IsNull, null);
        CriterionApplier::apply($qb, $criterion, 0, ['status' => 'status']);
    }
}
