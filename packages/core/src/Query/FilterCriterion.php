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
 * The set of canonical operators is defined by {@see FilterOperator}.
 * Adapters may handle additional cases internally but the core contract
 * is the enum.
 *
 * @since 0.1.0
 * @since 0.7.0 `$operator` is now typed as {@see FilterOperator} instead
 *              of `string`. Closes ADR-011 item A4.
 */
final class FilterCriterion
{
    public function __construct(
        public readonly string $property,
        public readonly FilterOperator $operator,
        public readonly mixed $value = null,
    ) {
    }
}
