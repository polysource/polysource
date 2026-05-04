<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Filter\InFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\InFilterType;
use ReflectionClass;

/**
 * Behavioural test for `InFilter`.
 *
 * Covers:
 * - factory wires property/formType/fqcn,
 * - empty/missing value → no-op,
 * - non-array value → no-op (defensive: form might mis-submit),
 * - IN comparison → `… IN (:param)` with parameter set to list,
 * - NOT IN comparison → `… NOT IN (:param)`.
 */
final class InFilterTest extends TestCase
{
    public function testFactoryConfiguresFormType(): void
    {
        $dto = InFilter::new('status', 'Status')->getAsDto();

        self::assertSame('status', $dto->getProperty());
        self::assertSame('Status', $dto->getLabel());
        self::assertSame(InFilterType::class, $dto->getFormType());
        self::assertSame(InFilter::class, $dto->getFqcn());
    }

    public function testApplyNoopsWhenValueIsNull(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        InFilter::new('status')->apply($qb, $this->makeFilterData(null, 'IN'), null, $this->makeEntityDto());
    }

    public function testApplyNoopsWhenValueIsEmptyArray(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        InFilter::new('status')->apply($qb, $this->makeFilterData([], 'IN'), null, $this->makeEntityDto());
    }

    public function testApplyNoopsWhenValueIsNotAnArray(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        InFilter::new('status')->apply($qb, $this->makeFilterData('draft', 'IN'), null, $this->makeEntityDto());
    }

    public function testApplyEmitsInClauseWithListParameter(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.status IN (:status_0)')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('status_0', ['draft', 'published'])
            ->willReturnSelf();

        InFilter::new('status')->apply(
            $qb,
            $this->makeFilterData(['draft', 'published'], 'IN'),
            null,
            $this->makeEntityDto(),
        );
    }

    public function testApplyEmitsNotInClauseWhenComparisonIsNegated(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.status NOT IN (:status_0)')
            ->willReturnSelf();

        InFilter::new('status')->apply(
            $qb,
            $this->makeFilterData(['archived'], 'NOT IN'),
            null,
            $this->makeEntityDto(),
        );
    }

    private function makeFilterData(mixed $value, string $comparison): FilterDataDto
    {
        $filterDto = new FilterDto();
        $filterDto->setProperty('status');

        return FilterDataDto::new(0, $filterDto, 'e', [
            'comparison' => $comparison,
            'value' => $value,
        ]);
    }

    /**
     * @return EntityDto<object>
     */
    private function makeEntityDto(): EntityDto
    {
        /** @var EntityDto<object> $dto */
        $dto = (new ReflectionClass(EntityDto::class))->newInstanceWithoutConstructor();

        return $dto;
    }
}
