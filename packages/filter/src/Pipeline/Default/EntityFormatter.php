<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Default;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterFormatterInterface;

/**
 * Default formatter for `entity` filters.
 *
 * Renders chip text "<property> <operator> <values>" with values
 * joined by an en-dash. Hosts that want richer / locale-aware labels
 * override by declaring a service tagged `polysource.filter.formatter`
 * with the same `name`.
 */
final class EntityFormatter implements FilterFormatterInterface
{
    public const NAME = 'entity';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function format(FilterCriterion $criterion): string
    {
        if ([] === $criterion->values) {
            return \sprintf('%s %s', $criterion->property, $criterion->operator);
        }

        $rendered = array_map(
            static fn (mixed $value): string => \is_scalar($value)
                ? (string) $value
                : (string) \json_encode($value),
            $criterion->values,
        );

        return \sprintf(
            '%s %s %s',
            $criterion->property,
            $criterion->operator,
            implode(' – ', $rendered),
        );
    }
}
