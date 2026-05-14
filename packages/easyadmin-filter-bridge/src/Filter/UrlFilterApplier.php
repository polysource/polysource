<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Filter;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;

/**
 * Applies a `?filters[...]` URL slice to a Doctrine QueryBuilder.
 *
 * Reads the request's query string in the EA conventional shape and
 * mutates the QB with the corresponding WHERE clauses. Pays the
 * technical debt left by v0.3.0 (ExportController unfiltered) and
 * v0.4.0 (bulk dry-run no count) — both documented as "filter-aware
 * deferred to v0.5+" in their CHANGELOGs.
 *
 * ### Supported URL shapes
 *
 *   - `?filters[status]=paid` (scalar equality)
 *   - `?filters[status][value]=paid&filters[status][comparison]==`
 *     (EA's expanded shape — supports `=`, `!=`, `<`, `<=`, `>`,
 *     `>=`, `between`, `like`)
 *   - `?filters[country][]=FR&filters[country][]=DE` (list-style,
 *     handled as IN())
 *   - `?filters[totalCents][value]=100&filters[totalCents][value2]=500&filters[totalCents][comparison]=between`
 *     (EA's between shape)
 *
 * ### Limitations (vs. full EA QueryBuilder parity)
 *
 *   - No relation joins (no support for `customer.country` style
 *     filter slices). Hosts whose filter slice includes relations
 *     should integrate via the EA Action pattern instead — see
 *     `docs/user/easyadmin-filter-bridge/filter-aware-export.md`.
 *   - No FullTextSearch / NotNull custom filters. Same recommendation.
 *   - The applier validates that the property exists on the entity's
 *     Doctrine metadata BEFORE applying the clause; unknown properties
 *     are silently dropped (defensive — no DQL injection risk).
 *
 * @since 0.5.0
 */
final class UrlFilterApplier
{
    /**
     * Apply the `filters` slice from the given URL query array to
     * the QueryBuilder. Returns the number of clauses actually
     * applied (excluding dropped/skipped ones).
     *
     * @param array<string, mixed>    $query    the request query (`$request->query->all()`)
     * @param ClassMetadata<object>   $metadata the entity's Doctrine metadata
     * @param string                  $rootAlias the QB's root alias (typically the SELECT alias passed to `from()`)
     */
    public function apply(
        QueryBuilder $qb,
        array $query,
        ClassMetadata $metadata,
        string $rootAlias,
    ): int {
        /** @var array<string, mixed> $filters */
        $filters = isset($query['filters']) && \is_array($query['filters']) ? $query['filters'] : [];
        $applied = 0;
        $paramIndex = 0;

        foreach ($filters as $property => $raw) {
            if (!\is_string($property) || '' === $property) {
                continue;
            }
            // Defensive: only allow filtering on properties actually
            // mapped by Doctrine. Refuses anything else without
            // touching the QB.
            if (!$metadata->hasField($property)) {
                continue;
            }

            if ($this->applyOne($qb, $rootAlias, $property, $raw, $paramIndex)) {
                ++$applied;
                ++$paramIndex;
            }
        }

        return $applied;
    }

    private function applyOne(
        QueryBuilder $qb,
        string $rootAlias,
        string $property,
        mixed $raw,
        int $paramIndex,
    ): bool {
        $paramName = 'polysource_f_' . $paramIndex;
        $fullProperty = $rootAlias . '.' . $property;

        // Scalar shape: `?filters[X]=value`
        if (\is_scalar($raw)) {
            if ('' === (string) $raw) {
                return false;
            }
            $qb->andWhere(\sprintf('%s = :%s', $fullProperty, $paramName));
            $qb->setParameter($paramName, $this->coerceParameter($raw));

            return true;
        }

        if (!\is_array($raw)) {
            return false;
        }

        // List shape: `?filters[X][]=a&filters[X][]=b` → IN (...)
        if ($this->isList($raw)) {
            /** @var list<scalar> $values */
            $values = array_values(array_filter($raw, static fn ($v): bool => \is_scalar($v) && '' !== (string) $v));
            if ([] === $values) {
                return false;
            }
            $qb->andWhere(\sprintf('%s IN (:%s)', $fullProperty, $paramName));
            $qb->setParameter($paramName, $values);

            return true;
        }

        // Expanded shape: `{value: "...", comparison: "...", value2?: "..."}`
        $comparison = isset($raw['comparison']) && \is_string($raw['comparison']) ? $raw['comparison'] : '=';
        $value = $raw['value'] ?? null;
        $value2 = $raw['value2'] ?? null;

        return $this->applyExpanded($qb, $fullProperty, $paramName, $comparison, $value, $value2);
    }

    private function applyExpanded(
        QueryBuilder $qb,
        string $fullProperty,
        string $paramName,
        string $comparison,
        mixed $value,
        mixed $value2,
    ): bool {
        // Accept comparisons case-insensitively — EA's InFilter emits
        // upper-case 'IN'/'NOT IN' (via `HiddenType` empty_data) but
        // hand-written URLs may use lower-case. Normalising here
        // keeps the match arms small.
        $cmp = strtolower($comparison);
        $normalised = match ($cmp) {
            '=', 'eq' => '=',
            '!=', '<>', 'neq' => '!=',
            '<', 'lt' => '<',
            '<=', 'lte' => '<=',
            '>', 'gt' => '>',
            '>=', 'gte' => '>=',
            'between' => 'between',
            'like' => 'like',
            'not like', 'not_like' => 'not like',
            'in' => 'in',
            'not in', 'not_in' => 'not in',
            'is null', 'is_null', 'null' => 'is null',
            'is not null', 'is_not_null', 'not_null' => 'is not null',
            default => null,
        };

        if (null === $normalised) {
            return false;
        }

        // IS NULL / IS NOT NULL — value is irrelevant (the predicate
        // is the data). EA's NotNullFilter submits these so the chips
        // bar and the WHERE clause must both honour them.
        // Surfaced 2026-05-14 dogfooding round 3 (friction C10).
        if ('is null' === $normalised || 'is not null' === $normalised) {
            $op = 'is null' === $normalised ? 'IS NULL' : 'IS NOT NULL';
            $qb->andWhere(\sprintf('%s %s', $fullProperty, $op));

            return true;
        }

        // IN / NOT IN — value is an array (EA's InFilter emits
        // `filters[X][value][]=v1&filters[X][value][]=v2` alongside
        // `filters[X][comparison]=IN`). Without this branch the
        // expanded-shape code path silently dropped these filters
        // (the scalar guard below rejected the array value), so
        // every IN query returned ALL rows. Surfaced 2026-05-14
        // dogfooding round 3 (friction C10).
        if ('in' === $normalised || 'not in' === $normalised) {
            $values = \is_array($value) ? array_values(array_filter(
                $value,
                static fn ($v): bool => \is_scalar($v) && '' !== (string) $v,
            )) : [];
            if ([] === $values) {
                return false;
            }
            $op = 'in' === $normalised ? 'IN' : 'NOT IN';
            $qb->andWhere(\sprintf('%s %s (:%s)', $fullProperty, $op, $paramName));
            $qb->setParameter($paramName, $values);

            return true;
        }

        if ('between' === $normalised) {
            if (!\is_scalar($value) || !\is_scalar($value2)) {
                return false;
            }
            $paramLow = $paramName . '_low';
            $paramHigh = $paramName . '_high';
            $qb->andWhere(\sprintf('%s BETWEEN :%s AND :%s', $fullProperty, $paramLow, $paramHigh));
            $qb->setParameter($paramLow, $this->coerceParameter($value));
            $qb->setParameter($paramHigh, $this->coerceParameter($value2));

            return true;
        }

        if ('like' === $normalised || 'not like' === $normalised) {
            if (!\is_scalar($value)) {
                return false;
            }
            $op = 'like' === $normalised ? 'LIKE' : 'NOT LIKE';
            $qb->andWhere(\sprintf('%s %s :%s', $fullProperty, $op, $paramName));
            $qb->setParameter($paramName, '%' . (string) $value . '%');

            return true;
        }

        if (!\is_scalar($value) || '' === (string) $value) {
            return false;
        }
        $qb->andWhere(\sprintf('%s %s :%s', $fullProperty, $normalised, $paramName));
        $qb->setParameter($paramName, $this->coerceParameter($value));

        return true;
    }

    /**
     * Coerces an URL string value to a typed parameter Doctrine can
     * bind cleanly. Booleans flagged via "true"/"false" come back as
     * actual bools; numeric strings stay strings (Doctrine handles
     * the cast).
     */
    private function coerceParameter(mixed $value): mixed
    {
        if (!\is_scalar($value)) {
            return $value;
        }
        $str = (string) $value;
        if ('true' === $str) {
            return true;
        }
        if ('false' === $str) {
            return false;
        }

        return $str;
    }

    /**
     * @param array<int|string, mixed> $arr
     */
    private function isList(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }
        $i = 0;
        foreach (array_keys($arr) as $key) {
            if ($key !== $i) {
                return false;
            }
            ++$i;
        }

        return true;
    }
}
