<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Polysource\EasyAdminFilterBridge\Form\Type\NotNullFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * EasyAdmin filter that toggles `IS NULL` / `IS NOT NULL` on a
 * nullable column.
 *
 * Tri-state form (Any / Has value / Empty). Selecting "Any" applies
 * no filter at all (which is the default).
 *
 * Activate via `configureFilters()`:
 *
 *     yield NotNullFilter::new('archivedAt', 'Archive state');
 *
 * Custom labels:
 *
 *     yield NotNullFilter::new('deletedAt')
 *         ->setFormTypeOption('labels', [
 *             'any' => 'All',
 *             'not_null' => 'Soft-deleted',
 *             'null' => 'Active',
 *         ]);
 */
final class NotNullFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(NotNullFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();

        if (NotNullFilterType::VALUE_NOT_NULL === $value) {
            $queryBuilder->andWhere(\sprintf('%s.%s IS NOT NULL', $alias, $property));

            return;
        }

        if (NotNullFilterType::VALUE_NULL === $value) {
            $queryBuilder->andWhere(\sprintf('%s.%s IS NULL', $alias, $property));

            return;
        }

        // VALUE_ANY ('') or anything unrecognised: no-op.
    }
}
