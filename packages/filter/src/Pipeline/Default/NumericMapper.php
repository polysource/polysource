<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Default;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterMapperInterface;

/**
 * Default mapper for `numeric` filters.
 */
final class NumericMapper implements FilterMapperInterface
{
    public const NAME = 'numeric';

    public function supports(string $name): bool
    {
        return self::NAME === $name;
    }

    public function fromRequest(string $property, array $rawValues): FilterCriterion
    {
        $comparison = $rawValues['comparison'] ?? null;
        $operator = \is_string($comparison) && '' !== $comparison ? $comparison : '=';

        $values = [];
        $value = $rawValues['value'] ?? null;
        if (null !== $value && '' !== $value) {
            $values[] = $value;
        }
        $value2 = $rawValues['value2'] ?? null;
        if (\is_string($value2) && '' !== $value2) {
            $values[] = $value2;
        }

        return new FilterCriterion($property, $operator, $values);
    }

    public function toFormData(FilterCriterion $criterion): array
    {
        $data = ['comparison' => $criterion->operator];
        if (isset($criterion->values[0])) {
            $data['value'] = $criterion->values[0];
        }
        if (isset($criterion->values[1])) {
            $data['value2'] = $criterion->values[1];
        }

        return $data;
    }
}
