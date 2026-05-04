<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDto;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\Filter\FullTextSearchFilter;
use Polysource\EasyAdminFilterBridge\Form\Type\FullTextSearchFilterType;
use ReflectionClass;

final class FullTextSearchFilterTest extends TestCase
{
    public function testFactoryWiring(): void
    {
        $dto = FullTextSearchFilter::new('q', 'Search')->getAsDto();

        self::assertSame('q', $dto->getProperty());
        self::assertSame(FullTextSearchFilterType::class, $dto->getFormType());
        self::assertSame(FullTextSearchFilter::class, $dto->getFqcn());
    }

    public function testApplyNoopsWhenValueIsNullOrEmptyOrBlank(): void
    {
        foreach ([null, '', '   ', 42] as $value) {
            $qb = $this->createMock(QueryBuilder::class);
            $qb->expects(self::never())->method('andWhere');

            FullTextSearchFilter::new('q')->apply(
                $qb,
                $this->makeFilterData($value, ['name']),
                null,
                $this->makeEntityDto(),
            );
        }
    }

    public function testApplyNoopsWhenPropertiesAreEmpty(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        FullTextSearchFilter::new('q')->apply(
            $qb,
            $this->makeFilterData('alice', []),
            null,
            $this->makeEntityDto(),
        );
    }

    public function testApplyNoopsWhenAllPropertiesAreInvalid(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::never())->method('andWhere');

        FullTextSearchFilter::new('q')->apply(
            $qb,
            $this->makeFilterData('alice', ['', 42, null]),
            null,
            $this->makeEntityDto(),
        );
    }

    public function testApplyEmitsOrLikeAcrossAllProperties(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('(LOWER(e.name) LIKE LOWER(:q_0) OR LOWER(e.email) LIKE LOWER(:q_0))')
            ->willReturnSelf();
        $qb->expects(self::once())
            ->method('setParameter')
            ->with('q_0', '%alice%')
            ->willReturnSelf();

        FullTextSearchFilter::new('q')->apply(
            $qb,
            $this->makeFilterData('alice', ['name', 'email']),
            null,
            $this->makeEntityDto(),
        );
    }

    public function testApplyEmitsLikeForSingleProperty(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $qb->expects(self::once())
            ->method('andWhere')
            ->with('(LOWER(e.name) LIKE LOWER(:q_0))')
            ->willReturnSelf();

        FullTextSearchFilter::new('q')->apply(
            $qb,
            $this->makeFilterData('alice', ['name']),
            null,
            $this->makeEntityDto(),
        );
    }

    /**
     * @param list<mixed> $properties
     */
    private function makeFilterData(mixed $value, array $properties): FilterDataDto
    {
        $filterDto = new FilterDto();
        $filterDto->setProperty('q');
        $filterDto->setFormTypeOption('properties', $properties);

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
