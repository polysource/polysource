<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Filter;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterFormatterInterface;

final class PriceRangeFormatter implements FilterFormatterInterface
{
    public const NAME = 'product.priceRange';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function format(FilterCriterion $criterion): string
    {
        $min = $criterion->values[0] ?? '';
        $max = $criterion->values[1] ?? '';

        if ('' !== $min && '' !== $max) {
            return \sprintf('Price: %s € – %s €', $min, $max);
        }
        if ('' !== $min) {
            return \sprintf('Price: ≥ %s €', $min);
        }
        if ('' !== $max) {
            return \sprintf('Price: ≤ %s €', $max);
        }

        return 'Price: any';
    }
}
