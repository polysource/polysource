<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Filter;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterFormatterInterface;

/**
 * Formats the chip label for an active `category` filter — joins
 * the selected category names with " or " for readability.
 *
 * Auto-tagged via the `polysource/filter` bundle's `autoconfigure`
 * registration of `FilterFormatterInterface`. The `name` returned by
 * supports() must match the FilterDefinition::$name in
 * ProductFilters::all().
 */
final class CategoryFormatter implements FilterFormatterInterface
{
    public const NAME = 'product.category';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function format(FilterCriterion $criterion): string
    {
        $values = array_map('strval', $criterion->values);

        return 'Category: ' . implode(' or ', $values);
    }
}
