<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Filter\BetweenDateFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\BetweenDateFilterType;
use ReflectionClass;

/**
 * Behavioural test for `BetweenDateFilter`.
 *
 * The applier branches on the (value, value2) tuple — we cover the
 * 4 combinations and assert the SQL fragment + parameters that land
 * on the QueryBuilder.
 */
final class BetweenDateFilterTest extends TestCase
{
    public function testFactoryConfiguresFormTypeAndProperty(): void
    {
        $filter = BetweenDateFilter::new('createdAt', 'Created');
        $dto = $filter->getAsDto();

        self::assertSame('createdAt', $dto->getProperty());
        self::assertSame('Created', $dto->getLabel());
        self::assertSame(BetweenDateFilterType::class, $dto->getFormType());
        self::assertSame(BetweenDateFilter::class, $dto->getFqcn());
    }

    public function testApplyDoesNothingWhenBothBoundsAreEmpty(): void
    {
        $qb = $this->makeQueryBuilder();
        $qb->expects(self::never())->method('andWhere');
        $qb->expects(self::never())->method('setParameter');

        BetweenDateFilter::new('createdAt')
            ->apply($qb, $this->makeFilterDataDto(value: null, value2: null), null, $this->makeEntityDto());
    }

    public function testApplyEmitsBetweenWhenBothBoundsAreSet(): void
    {
        $qb = $this->makeQueryBuilder();
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.createdAt BETWEEN :createdAt_0 AND :createdAt_1')
            ->willReturnSelf();
        $qb->expects(self::exactly(2))
            ->method('setParameter')
            ->willReturnSelf();

        BetweenDateFilter::new('createdAt')
            ->apply($qb, $this->makeFilterDataDto(value: '2026-01-01', value2: '2026-01-31'), null, $this->makeEntityDto());
    }

    public function testApplyFallsBackToGreaterEqualWhenOnlyLowerIsSet(): void
    {
        $qb = $this->makeQueryBuilder();
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.createdAt >= :createdAt_0')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('createdAt_0', '2026-01-01')
            ->willReturnSelf();

        BetweenDateFilter::new('createdAt')
            ->apply($qb, $this->makeFilterDataDto(value: '2026-01-01', value2: null), null, $this->makeEntityDto());
    }

    public function testApplyFallsBackToLessEqualWhenOnlyUpperIsSet(): void
    {
        $qb = $this->makeQueryBuilder();
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.createdAt <= :createdAt_1')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('createdAt_1', '2026-01-31')
            ->willReturnSelf();

        BetweenDateFilter::new('createdAt')
            ->apply($qb, $this->makeFilterDataDto(value: null, value2: '2026-01-31'), null, $this->makeEntityDto());
    }

    public function testApplyTreatsEmptyStringAsAbsent(): void
    {
        $qb = $this->makeQueryBuilder();
        $qb->expects(self::never())->method('andWhere');

        BetweenDateFilter::new('createdAt')
            ->apply($qb, $this->makeFilterDataDto(value: '', value2: ''), null, $this->makeEntityDto());
    }

    /**
     * @return QueryBuilder&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeQueryBuilder(): QueryBuilder
    {
        return $this->createMock(QueryBuilder::class);
    }

    private function makeFilterDataDto(mixed $value, mixed $value2): FilterDataDto
    {
        $filterDto = new FilterDto();
        $filterDto->setProperty('createdAt');

        return FilterDataDto::new(0, $filterDto, 'e', [
            'comparison' => ComparisonType::BETWEEN,
            'value' => $value,
            'value2' => $value2,
        ]);
    }

    /**
     * @return \EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto<object>
     */
    private function makeEntityDto(): \EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto
    {
        // EntityDto is `final`; the apply() under test never reads
        // from it, so we can hand back an instance built without
        // running the constructor (which would require a real
        // Doctrine ClassMetadata).
        /** @var \EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto<object> $dto */
        $dto = (new ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto::class))
            ->newInstanceWithoutConstructor();

        return $dto;
    }
}
