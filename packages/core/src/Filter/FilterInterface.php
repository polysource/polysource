<?php

declare(strict_types=1);

namespace Polysource\Core\Filter;

use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;

/**
 * Declaration of a filter that can be applied to a {@see DataQuery}.
 *
 * Filters are storage-agnostic: they produce a {@see FilterCriterion} that
 * each adapter translates into its native query language. Cf. ADR architecture.
 */
interface FilterInterface
{
    public function getProperty(): string;

    public function getLabel(): string;

    /**
     * @return list<string> standard operators are: eq, neq, gt, gte, lt, lte, like, in, between, null, notnull
     */
    public function getSupportedOperators(): array;

    /**
     * Apply the filter to the query, returning a *new* DataQuery.
     * Filters never mutate — they always return a fresh DataQuery.
     */
    public function applyToQuery(DataQuery $query, FilterCriterion $criterion): DataQuery;

    public function getAsDto(): FilterDto;
}
