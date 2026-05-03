<?php

declare(strict_types=1);

namespace Polysource\Filter\Pipeline;

use Polysource\Filter\Model\FilterCriterion;

/**
 * First phase of the filter pipeline — translates between HTTP request
 * data and the immutable `FilterCriterion` model.
 *
 * One mapper per filter `name` (e.g. `text`, `datetime`, `numeric`).
 * Tagged services are registered as `polysource.filter.mapper` and
 * indexed by name by `MapperRegistry` (compile-time).
 *
 * The contract is **strictly bidirectional**:
 *   `fromRequest(toFormData($criterion)) == $criterion`
 *
 * for any criterion the mapper claims to support. This invariant lets
 * `FilterCollectionType` round-trip a criterion through the form
 * submit cycle without losing information.
 */
interface FilterMapperInterface
{
    /**
     * @return bool true when this mapper handles filters declared with the given `name`
     */
    public function supports(string $name): bool;

    /**
     * Build a criterion from a single filter's raw request slice.
     *
     * Example for a datetime range filter, `$rawValues` is typically:
     *     ['comparison' => 'between', 'value' => '2026-01-01', 'value2' => '2026-12-31']
     *
     * The mapper decides the criterion's `operator` and `values`
     * positional layout. Implementations MUST throw
     * `\InvalidArgumentException` when the raw input is structurally
     * invalid; callers (`FilterCollectionType` PRE_SUBMIT listener)
     * are responsible for catching and surfacing form errors.
     *
     * @param array<string, mixed> $rawValues
     */
    public function fromRequest(string $property, array $rawValues): FilterCriterion;

    /**
     * Inverse of `fromRequest()` — produce the raw values shape that
     * the form widget expects, given a stored criterion. Used in
     * `PRE_SET_DATA` to pre-fill the form when the user re-opens a
     * page with persisted filters.
     *
     * @return array<string, mixed>
     */
    public function toFormData(FilterCriterion $criterion): array;
}
