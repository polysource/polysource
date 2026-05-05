<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Filter;

use Polysource\Filter\Model\FilterDefinition;

/**
 * Static catalogue of filter definitions for the Product list.
 *
 * 4 filters covering every UI shape the primitive supports:
 *  - multi-select on `category` (operator `in`)
 *  - between on `price` (numeric range)
 *  - date on `releasedAt` (greater-than-or-equal)
 *  - boolean on `isAvailable`
 *
 * The `name` field is the pipeline routing key — each value here
 * must match the `supports()` clause of a `FilterFormatterInterface`
 * service (see CategoryFormatter / PriceRangeFormatter / etc.).
 */
final class ProductFilters
{
    /**
     * @return list<FilterDefinition>
     */
    public static function all(): array
    {
        return [
            new FilterDefinition(
                name: 'product.category',
                property: 'category',
                label: 'Category',
                formSpec: [
                    'choices' => ['Tech', 'Books', 'Apparel', 'Home'],
                    'multiple' => true,
                ],
            ),
            new FilterDefinition(
                name: 'product.priceRange',
                property: 'price',
                label: 'Price (€)',
                formSpec: [
                    'min' => 0,
                    'max' => 999,
                    'step' => 1,
                ],
            ),
            new FilterDefinition(
                name: 'product.releasedAfter',
                property: 'releasedAt',
                label: 'Released after',
                formSpec: [
                    'format' => 'Y-m-d',
                ],
            ),
            new FilterDefinition(
                name: 'product.availability',
                property: 'isAvailable',
                label: 'In stock',
                formSpec: [
                    'choices' => ['Yes' => '1', 'No' => '0'],
                ],
            ),
        ];
    }
}
