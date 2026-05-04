<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

/**
 * Single filter clause applied to a {@see DataQuery}.
 *
 * Adapters are responsible for translating each operator into their native
 * query language (Doctrine DQL, Redis SCAN match, HTTP query string,
 * Meilisearch filter syntax, etc.).
 *
 * Standard operators (an adapter may declare additional ones):
 *   - eq, neq          : equality / inequality
 *   - gt, gte, lt, lte : numeric/date comparisons
 *   - like             : substring (semantics adapter-dependent — cf. risks.R17)
 *   - in, nin          : membership in a list
 *   - between          : range (value must be a 2-element array)
 *   - null, notnull    : presence check (value ignored)
 */
final class FilterCriterion
{
    public function __construct(
        public readonly string $property,
        public readonly string $operator,
        public readonly mixed $value = null,
    ) {
    }
}
