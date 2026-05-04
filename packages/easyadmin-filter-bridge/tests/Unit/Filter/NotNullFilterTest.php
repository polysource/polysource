<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Filter\NotNullFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType;
use ReflectionClass;

final class NotNullFilterTest extends TestCase
{
    public function testFactoryWiring(): void
    {
        $dto = NotNullFilter::new('archivedAt', 'Archived')->getAsDto();

        self::assertSame('archivedAt', $dto->getProperty());
        self::assertSame(NotNullFilterType::class, $dto->getFormType());
        self::assertSame(NotNullFilter::class, $dto->getFqcn());
    }

    public function testApplyEmitsIsNotNullForHasValue(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.archivedAt IS NOT NULL')
            ->willReturnSelf();
        $qb->expects(self::never())->method('setParameter');

        NotNullFilter::new('archivedAt')->apply(
            $qb,
            $this->makeFilterData(NotNullFilterType::VALUE_NOT_NULL),
            null,
            $this->makeEntityDto(),
        );
    }

    public function testApplyEmitsIsNullForEmpty(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('e.archivedAt IS NULL')
            ->willReturnSelf();

        NotNullFilter::new('archivedAt')->apply(
            $qb,
            $this->makeFilterData(NotNullFilterType::VALUE_NULL),
            null,
            $this->makeEntityDto(),
        );
    }

    public function testApplyNoopsForAny(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        NotNullFilter::new('archivedAt')->apply(
            $qb,
            $this->makeFilterData(NotNullFilterType::VALUE_ANY),
            null,
            $this->makeEntityDto(),
        );
    }

    public function testApplyNoopsForUnrecognisedValue(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        NotNullFilter::new('archivedAt')->apply(
            $qb,
            $this->makeFilterData('garbage'),
            null,
            $this->makeEntityDto(),
        );
    }

    private function makeFilterData(mixed $value): FilterDataDto
    {
        $filterDto = new FilterDto();
        $filterDto->setProperty('archivedAt');

        return FilterDataDto::new(0, $filterDto, 'e', [
            'comparison' => '',
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
