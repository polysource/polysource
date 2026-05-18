<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\Query;

use DateTimeImmutable;
use Doctrine\ORM\QueryBuilder;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Throwable;

/**
 * Translates a Polysource {@see FilterCriterion} into one or more
 * Doctrine `WHERE` clauses on a {@see QueryBuilder}. The
 * {@see FilterOperator} dispatch lives here, separated from the
 * data-source coordination logic so each criterion variant is
 * unit-testable without spinning up a full data source.
 *
 * Caller supplies:
 *   - the QueryBuilder to mutate
 *   - a `property → entity-field` whitelist (rejects criteria
 *     against unknown properties — defense against arbitrary DQL)
 *   - the criterion itself
 *   - a bind index (so parameters from multiple criteria don't
 *     collide; each criterion uses `:p<index>` and optional `_a`/`_b`
 *     suffixes for BETWEEN)
 *
 * Extracted from `DoctrineDataSource::applyCriterion()` in v0.10.0
 * per audit task #69.
 *
 * @since 0.10.0
 */
final class CriterionApplier
{
    /**
     * Apply a single criterion to the QueryBuilder. Unknown
     * properties (not in `$allowedFilters`) and unmapped operators
     * are silently skipped — the rest of the query still works.
     *
     * @param array<string, string> $allowedFilters property → entity-field map
     */
    public static function apply(
        QueryBuilder $qb,
        FilterCriterion $criterion,
        int $bindIndex,
        array $allowedFilters,
    ): void {
        $field = $allowedFilters[$criterion->property] ?? null;
        if (null === $field) {
            return;
        }

        $alias = 'r.' . $field;
        $param = ':p' . $bindIndex;
        $value = $criterion->value;

        match ($criterion->operator) {
            FilterOperator::Eq => self::whereScalar($qb, "{$alias} = {$param}", $param, $value),
            FilterOperator::Neq => self::whereScalar($qb, "{$alias} != {$param}", $param, $value),
            FilterOperator::Gt => self::whereScalar($qb, "{$alias} > {$param}", $param, $value),
            FilterOperator::Gte => self::whereScalar($qb, "{$alias} >= {$param}", $param, $value),
            FilterOperator::Lt => self::whereScalar($qb, "{$alias} < {$param}", $param, $value),
            FilterOperator::Lte => self::whereScalar($qb, "{$alias} <= {$param}", $param, $value),
            FilterOperator::Like => self::whereScalar(
                $qb,
                "{$alias} LIKE {$param}",
                $param,
                '%' . (\is_scalar($value) ? (string) $value : '') . '%',
            ),
            FilterOperator::In => self::whereIn($qb, "{$alias} IN ({$param})", $param, $value),
            FilterOperator::Between => self::whereBetween($qb, $alias, $value, $bindIndex),
            // Nin / IsNull / IsNotNull not yet mapped — silently
            // skipped so the rest of the query still works.
            default => null,
        };
    }

    private static function whereScalar(QueryBuilder $qb, string $clause, string $param, mixed $value): void
    {
        if (null === $value) {
            return;
        }
        $qb->andWhere($clause);
        $qb->setParameter(ltrim($param, ':'), self::normaliseDateBound($value));
    }

    private static function whereIn(QueryBuilder $qb, string $clause, string $param, mixed $value): void
    {
        if (!\is_array($value) || [] === $value) {
            return;
        }
        $qb->andWhere($clause);
        $qb->setParameter(ltrim($param, ':'), array_values($value));
    }

    private static function whereBetween(QueryBuilder $qb, string $alias, mixed $value, int $bindIndex): void
    {
        if (!\is_array($value) || 2 !== \count($value)) {
            return;
        }
        [$start, $end] = array_values($value);
        $a = ':p' . $bindIndex . 'a';
        $b = ':p' . $bindIndex . 'b';
        $qb->andWhere("{$alias} BETWEEN {$a} AND {$b}");
        $qb->setParameter(ltrim($a, ':'), self::normaliseDateBound($start));
        $qb->setParameter(ltrim($b, ':'), self::normaliseDateBound($end));
    }

    /**
     * Coerce ISO-8601 date strings into DateTimeImmutable so DBAL
     * binds them as TIMESTAMP rather than TEXT. Non-date values pass
     * through unchanged.
     */
    private static function normaliseDateBound(mixed $value): mixed
    {
        if (\is_string($value) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            try {
                return new DateTimeImmutable($value);
            } catch (Throwable) {
                return $value;
            }
        }

        return $value;
    }
}
