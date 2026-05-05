<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Filter;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterFormatterInterface;

final class AvailabilityFormatter implements FilterFormatterInterface
{
    public const NAME = 'product.availability';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function format(FilterCriterion $criterion): string
    {
        $value = (string) ($criterion->values[0] ?? '');

        return match ($value) {
            '1' => 'In stock',
            '0' => 'Out of stock',
            default => 'Availability: any',
        };
    }
}
