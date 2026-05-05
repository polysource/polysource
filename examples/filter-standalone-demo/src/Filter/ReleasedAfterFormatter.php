<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Filter;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterFormatterInterface;

final class ReleasedAfterFormatter implements FilterFormatterInterface
{
    public const NAME = 'product.releasedAfter';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function format(FilterCriterion $criterion): string
    {
        $date = (string) ($criterion->values[0] ?? '');

        return '' === $date
            ? 'Released after: any'
            : \sprintf('Released after %s', $date);
    }
}
