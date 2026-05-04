<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Polysource\EasyAdminFilterBridge\Form\Type\InFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * EasyAdmin filter that emits an `IN (…)` (or `NOT IN (…)`) SQL
 * fragment from a multi-select choice picker.
 *
 * Use case: status pickers, category pickers, role pickers — any
 * "field is one of N values" query. EA's built-in `ChoiceFilter`
 * already does this but only for a single value; this filter is the
 * multi-value variant.
 *
 * Activate via `configureFilters()`:
 *
 *     yield InFilter::new('status', 'Status')
 *         ->setFormTypeOption('choices', [
 *             'Draft'     => 'draft',
 *             'Published' => 'published',
 *             'Archived'  => 'archived',
 *         ]);
 *
 * Toggle to `NOT IN` semantics:
 *
 *     yield InFilter::new('status', 'Excluding statuses')
 *         ->setFormTypeOption('negate', true)
 *         ->setFormTypeOption('choices', [...]);
 */
final class InFilter implements FilterInterface
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
            ->setFormType(InFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (!\is_array($value) || [] === $value) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();
        $parameterName = $filterDataDto->getParameterName();
        $operator = 'NOT IN' === $filterDataDto->getComparison() ? 'NOT IN' : 'IN';

        $queryBuilder
            ->andWhere(\sprintf('%s.%s %s (:%s)', $alias, $property, $operator, $parameterName))
            ->setParameter($parameterName, $value);
    }
}
