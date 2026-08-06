<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\DataSource;

use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterOperator;

/**
 * Translates a DataQuery's `id` filter into a Redis `SCAN MATCH`
 * glob pattern.
 *
 * Shared by the four key-scanning data sources (string / list / set /
 * sorted-set) — before v0.10 each carried a byte-identical private
 * `scanPattern()` copy (audit task #65 tail; the hash source filters
 * in memory via `InMemoryValueMatcher` instead and never scans by
 * value).
 *
 * Semantics (unchanged from the private copies):
 * - only the `id` criterion participates — Redis can't SCAN on
 *   values, so every other property filter is applied downstream
 *   (or, for these type-pure sources, intentionally ignored);
 * - only {@see FilterOperator::Like} translates — any other operator
 *   on `id` degrades to match-everything rather than erroring;
 * - a needle without glob metacharacters is auto-wrapped as
 *   `*needle*` (contains semantics, mirroring SQL LIKE defaults).
 *
 * The pre-extraction copies also carried a dead
 * `$op instanceof FilterOperator` branch dating from before v0.7,
 * when {@see \Polysource\Core\Query\FilterCriterion::$operator} was a
 * free-form string. The property has been typed `FilterOperator`
 * since v0.7.0 — the branch is gone.
 *
 * @since 0.10.0
 */
final class ScanPatternResolver
{
    public static function resolve(DataQuery $query, string $keyPrefix): string
    {
        $criterion = $query->filters['id'] ?? null;
        if (null === $criterion) {
            return $keyPrefix . '*';
        }
        if (
            FilterOperator::Like !== $criterion->operator
            || !\is_string($criterion->value)
            || '' === $criterion->value
        ) {
            return $keyPrefix . '*';
        }

        $glob = $criterion->value;
        if (!str_contains($glob, '*') && !str_contains($glob, '?')) {
            $glob = '*' . $glob . '*';
        }

        return $keyPrefix . $glob;
    }
}
