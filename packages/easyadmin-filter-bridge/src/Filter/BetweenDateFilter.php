<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Polysource\EasyAdminFilterBridge\Form\Type\BetweenDateFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * EasyAdmin filter that always applies a date BETWEEN range, with
 * graceful one-sided fallback when only one bound is provided.
 *
 * Behavior matrix:
 *   value=null,   value2=null   → no-op (filter not applied)
 *   value=null,   value2=set    → `field <= value2`
 *   value=set,    value2=null   → `field >= value`
 *   value=set,    value2=set    → `field BETWEEN value AND value2`
 *
 * Designed to complement EasyAdmin's built-in `DateTimeFilter` which
 * exposes a comparison dropdown the end-user has to navigate. This
 * filter strips that to just a "from" and "to" pair — the cognitive
 * load is lower for time-window queries (typical in admins: orders
 * since X, sign-ups in the last week, etc.).
 *
 * Activate manually via `configureFilters()`:
 *
 *     yield BetweenDateFilter::new('createdAt', 'Created');
 */
final class BetweenDateFilter implements FilterInterface
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
            ->setFormType(BetweenDateFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();
        $value = $filterDataDto->getValue();
        $value2 = $filterDataDto->getValue2();
        $parameterName = $filterDataDto->getParameterName();
        $parameter2Name = $filterDataDto->getParameter2Name();

        $hasLower = null !== $value && '' !== $value;
        $hasUpper = null !== $value2 && '' !== $value2;

        if (!$hasLower && !$hasUpper) {
            return;
        }

        if ($hasLower && $hasUpper) {
            $queryBuilder
                ->andWhere(\sprintf('%s.%s BETWEEN :%s AND :%s', $alias, $property, $parameterName, $parameter2Name))
                ->setParameter($parameterName, $value)
                ->setParameter($parameter2Name, $value2);

            return;
        }

        if ($hasLower) {
            $queryBuilder
                ->andWhere(\sprintf('%s.%s >= :%s', $alias, $property, $parameterName))
                ->setParameter($parameterName, $value);

            return;
        }

        $queryBuilder
            ->andWhere(\sprintf('%s.%s <= :%s', $alias, $property, $parameter2Name))
            ->setParameter($parameter2Name, $value2);
    }
}
