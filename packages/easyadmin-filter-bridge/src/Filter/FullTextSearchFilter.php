<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Polysource\EasyAdminFilterBridge\Form\Type\FullTextSearchFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * EasyAdmin filter that searches a single text input across multiple
 * configured columns using LIKE clauses (`OR`-combined).
 *
 * Use case: a sticky "Search…" box at the top of an admin list that
 * matches against name, email, slug, etc. simultaneously. Cheaper
 * than a full-blown Meilisearch hookup when the data fits in a
 * single Doctrine entity.
 *
 * Activate via `configureFilters()`:
 *
 *     yield FullTextSearchFilter::new('q', 'Search')
 *         ->setFormTypeOption('properties', ['name', 'email', 'slug']);
 *
 * Implementation notes:
 * - Each property generates one `LOWER(alias.prop) LIKE LOWER(:param)`
 *   clause, all OR-combined and AND-combined with the rest of the
 *   query (so it doesn't bypass other filters).
 * - The user term is wrapped with `%…%` for substring matching.
 * - SQL injection is impossible: the term goes through Doctrine
 *   parameter binding; the column names come from the DI-frozen
 *   `properties` option (host code, not request data).
 * - The filter's `property` attribute is purely a routing key (e.g.
 *   "q") and is *not* used in the SQL — the real columns are in
 *   `properties`.
 */
final class FullTextSearchFilter implements FilterInterface
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
            ->setFormType(FullTextSearchFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        if (!\is_string($value) || '' === trim($value)) {
            return;
        }

        $properties = $filterDataDto->getFormTypeOption('properties');
        if (!\is_array($properties) || [] === $properties) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $parameterName = $filterDataDto->getParameterName();

        $orClauses = [];
        foreach ($properties as $property) {
            if (!\is_string($property) || '' === $property) {
                continue;
            }
            $orClauses[] = \sprintf(
                'LOWER(%s.%s) LIKE LOWER(:%s)',
                $alias,
                $property,
                $parameterName,
            );
        }

        if ([] === $orClauses) {
            return;
        }

        $queryBuilder
            ->andWhere('(' . implode(' OR ', $orClauses) . ')')
            ->setParameter($parameterName, '%' . $value . '%');
    }
}
