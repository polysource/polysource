<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Filter;

use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\Url\OperatorMap;

/**
 * Decodes the `?filters[...]` URL slice into a canonical
 * {@see FilterCriterion} list. Two URL shapes feed in:
 *
 *   - EasyAdmin's:
 *       filters[X][comparison]=<op>
 *       filters[X][value]=<scalar>|<list>|{min,max}|{from,to}
 *
 *   - Polysource standalone's:
 *       filter[X][op]=<op>
 *       filter[X][value]=<scalar>
 *       filter[X][values][]=<v1>&filter[X][values][]=<v2>
 *       filter[X][min]=<v>&filter[X][max]=<v>          (between)
 *
 *   - Plus EA's BooleanFilter scalar shape:
 *       filters[X]=<scalar>
 *
 * 8 latent regressions surfaced in 2026-05-07 production integration
 * — every one of them is pinned by tests against this class.
 *
 * Extracted from `SavedViewController::buildCriteria()` in v0.9.0 —
 * the previous 85-line static method violated SRP (parsing multiple
 * URL shapes + comparison aliasing + between/list promotion in one
 * giant `foreach`). The pipeline now reads top-down:
 *
 *     parseField()
 *       ├─ short-circuit on empty / non-scalar / scalar-shorthand
 *       ├─ extractComparison()       (EA `comparison` or Polysource `op`)
 *       ├─ promoteFromValues()       (Polysource `values[]` → `value`)
 *       ├─ promoteToBetween()        (Polysource min/max → {min, max} envelope)
 *       ├─ buildBetweenCriterion()
 *       ├─ buildInCriterion()
 *       └─ buildScalarCriterion()    (default — uses mapComparison)
 *
 * @since 0.9.0
 */
final class FilterUrlParser
{
    /**
     * @param array<string, mixed> $raw the `filters` slice from a request query
     *
     * @return list<FilterCriterion>
     */
    public static function buildCriteria(array $raw): array
    {
        $criteria = [];

        foreach ($raw as $field => $config) {
            $criterion = self::parseField((string) $field, $config);
            if (null !== $criterion) {
                $criteria[] = $criterion;
            }
        }

        return $criteria;
    }

    private static function parseField(string $field, mixed $config): ?FilterCriterion
    {
        // EA BooleanFilter scalar shape: `filters[X]=v` — config is
        // not an array. Anything non-scalar/non-array (or empty
        // scalar) is dropped silently.
        if (!\is_array($config)) {
            if (!\is_scalar($config) || '' === $config) {
                return null;
            }

            return new FilterCriterion($field, 'eq', [(string) $config]);
        }

        // PHPStan can't narrow `array` from a generic `is_array()`
        // check to a string-keyed shape. The input always comes from
        // an HTTP query (`?filters[<prop>][comparison]=…`) where the
        // outer key is `<prop>` (string) and the inner keys
        // (`comparison`, `value`, `op`, `values`, `min`, `max`) are
        // string literals.
        /** @var array<string, mixed> $config */
        $comparison = self::extractComparison($config);
        $value = $config['value'] ?? null;
        $value = self::promoteFromValues($config, $value);
        $value = self::promoteToBetween($config, $value);

        if ('' === $value || null === $value || (\is_array($value) && [] === $value)) {
            return null;
        }

        // between (date range / numeric range).
        if (\is_array($value) && (isset($value['min']) || isset($value['max']) || isset($value['from']) || isset($value['to']))) {
            /** @var array<string, mixed> $value */
            return self::buildBetweenCriterion($field, $value);
        }

        // Indexed list → in (multi-select choice).
        if (\is_array($value) && $value === array_values($value)) {
            return self::buildInCriterion($field, $value);
        }

        $scalar = \is_scalar($value) ? (string) $value : '';

        return new FilterCriterion(
            $field,
            self::mapComparison($comparison, 'eq'),
            [$scalar],
        );
    }

    /**
     * Read the operator string from a filter envelope. EA uses
     * `comparison`; Polysource standalone uses `op`. Accept both so
     * the same parser handles saves from either page family.
     *
     * @param array<string, mixed> $config
     */
    private static function extractComparison(array $config): string
    {
        if (\is_string($config['comparison'] ?? null)) {
            return $config['comparison'];
        }
        if (\is_string($config['op'] ?? null)) {
            return $config['op'];
        }

        return '';
    }

    /**
     * Polysource shape `?filter[X][values][]=a&[values][]=b` packs the
     * multi-value list as a `values` sibling of `op` instead of nesting
     * it in `value`. Promote it so the indexed-list branch downstream
     * picks it up.
     *
     * @param array<string, mixed> $config
     */
    private static function promoteFromValues(array $config, mixed $value): mixed
    {
        if (null !== $value) {
            return $value;
        }

        $values = $config['values'] ?? null;

        return \is_array($values) && [] !== $values ? $values : $value;
    }

    /**
     * Polysource between shape `?filter[X][op]=between&[min]=..&[max]=..`
     * packs `min` and `max` as direct siblings of `op`. Promote into
     * the EA-shaped `{min, max}` envelope so the between branch
     * downstream picks them up.
     *
     * @param array<string, mixed> $config
     */
    private static function promoteToBetween(array $config, mixed $value): mixed
    {
        if (null !== $value) {
            return $value;
        }
        if (!isset($config['min']) && !isset($config['max'])) {
            return $value;
        }

        return [
            'min' => $config['min'] ?? '',
            'max' => $config['max'] ?? '',
        ];
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function buildBetweenCriterion(string $field, array $value): ?FilterCriterion
    {
        $minRaw = $value['min'] ?? $value['from'] ?? '';
        $maxRaw = $value['max'] ?? $value['to'] ?? '';
        $min = \is_scalar($minRaw) ? (string) $minRaw : '';
        $max = \is_scalar($maxRaw) ? (string) $maxRaw : '';

        if ('' === $min && '' === $max) {
            return null;
        }

        return new FilterCriterion($field, 'between', [$min, $max]);
    }

    /**
     * Build an IN criterion from an indexed list. Force `in` regardless
     * of the comparison operator EA carries: EA's ChoiceFilter with
     * canSelectMultiple submits as
     * `?filters[X][comparison]==&[value][]=a&[value][]=b`. Translating
     * `=` to `eq` would store an exact-equality criterion against a
     * list, which the data sources can't honour. The semantic of
     * "value IS one of these" is always `in`. Bug found 2026-05-07.
     *
     * @param list<mixed> $value
     */
    private static function buildInCriterion(string $field, array $value): FilterCriterion
    {
        return new FilterCriterion(
            $field,
            'in',
            array_values(array_map(
                static fn (mixed $v): string => \is_scalar($v) ? (string) $v : '',
                $value,
            )),
        );
    }

    /**
     * Map an EA URL operator to a Polysource canonical name. Delegates
     * to {@see OperatorMap::fromEa()} — the bidirectional source of
     * truth (PR 4). Unknown operators fall back to `$default` rather
     * than passing through verbatim (hardened in v0.9.0 per the
     * architectural audit).
     */
    private static function mapComparison(string $comparison, string $default): string
    {
        return OperatorMap::fromEa($comparison, $default);
    }
}
