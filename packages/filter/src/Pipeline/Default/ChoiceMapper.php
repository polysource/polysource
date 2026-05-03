<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline\Default;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Pipeline\FilterMapperInterface;

/**
 * Default mapper for `choice` filters.
 */
final class ChoiceMapper implements FilterMapperInterface
{
    public const NAME = 'choice';

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

        return new FilterCriterion($property, $operator, $values);
    }

    public function toFormData(FilterCriterion $criterion): array
    {
        $data = ['comparison' => $criterion->operator];
        if (isset($criterion->values[0])) {
            $data['value'] = $criterion->values[0];
        }

        return $data;
    }
}
