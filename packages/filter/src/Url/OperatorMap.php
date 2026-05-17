<?php

declare(strict_types=1);

namespace Polysource\Filter\Url;

/**
 * Bidirectional mapping between Polysource canonical operator names
 * (`eq`, `neq`, `gt`, `gte`, `lt`, `lte`, `like`, `in`, `between`,
 * `is null`, `is not null`) and the EasyAdmin URL operator strings
 * (`=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `like`, `IN`, `between`).
 *
 * Single source of truth for the operator vocabulary that crosses
 * the EA ↔ Polysource boundary. Before this class the mapping lived
 * in three places that drifted independently:
 *
 *   - `SavedViewController::mapComparison()` (EA → canonical)
 *   - `SavedViewApplySubscriber::criteriaToEaQuery()` (canonical → EA)
 *   - `UrlFilterApplier::applyExpanded()` (URL → DQL operator)
 *
 * Centralising it here means an operator added to one direction is
 * automatically available to all consumers — and impossible to ship
 * with an unbalanced inverse (the round-trip is asserted in tests).
 *
 * @since 0.9.0
 */
final class OperatorMap
{
    /**
     * Polysource canonical operator names. Order matches the typical
     * UX progression (equality first, then range, then text, then
     * collection, then null-check).
     */
    public const EQ = 'eq';
    public const NEQ = 'neq';
    public const GT = 'gt';
    public const GTE = 'gte';
    public const LT = 'lt';
    public const LTE = 'lte';
    public const LIKE = 'like';
    public const NOT_LIKE = 'not like';
    public const IN = 'in';
    public const NOT_IN = 'not in';
    public const BETWEEN = 'between';
    public const IS_NULL = 'is null';
    public const IS_NOT_NULL = 'is not null';

    /**
     * Canonical → EA URL operator. The EA form uses the conventional
     * mathematical symbols (`=`, `!=`) for scalar comparisons and
     * keyword strings (`like`, `between`) for the rest. Used when
     * Polysource-side state (e.g. SavedView's FilterCollection) has
     * to be rendered into an EA-compatible URL slice.
     */
    private const CANONICAL_TO_EA = [
        self::EQ => '=',
        self::NEQ => '!=',
        self::GT => '>',
        self::GTE => '>=',
        self::LT => '<',
        self::LTE => '<=',
        self::LIKE => 'like',
        self::NOT_LIKE => 'not like',
        self::IN => 'IN',
        self::NOT_IN => 'NOT IN',
        self::BETWEEN => 'between',
        self::IS_NULL => 'IS NULL',
        self::IS_NOT_NULL => 'IS NOT NULL',
    ];

    /**
     * EA URL operator → canonical. The map accepts every alias the
     * EA UI may submit (`!=`/`<>`, `like`/`LIKE`, `is null`/`IS NULL`,
     * etc.) and normalises to the canonical name. Comparison is
     * case-insensitive — apply `strtolower()` before lookup.
     *
     * @var array<string, string>
     */
    private const EA_TO_CANONICAL = [
        '=' => self::EQ,
        'eq' => self::EQ,
        '!=' => self::NEQ,
        '<>' => self::NEQ,
        'neq' => self::NEQ,
        '>' => self::GT,
        'gt' => self::GT,
        '>=' => self::GTE,
        'gte' => self::GTE,
        '<' => self::LT,
        'lt' => self::LT,
        '<=' => self::LTE,
        'lte' => self::LTE,
        'like' => self::LIKE,
        'like*' => self::LIKE,
        '*like' => self::LIKE,
        'not like' => self::NOT_LIKE,
        'not_like' => self::NOT_LIKE,
        'in' => self::IN,
        'not in' => self::NOT_IN,
        'not_in' => self::NOT_IN,
        'between' => self::BETWEEN,
        'is null' => self::IS_NULL,
        'is_null' => self::IS_NULL,
        'null' => self::IS_NULL,
        'is not null' => self::IS_NOT_NULL,
        'is_not_null' => self::IS_NOT_NULL,
        'not_null' => self::IS_NOT_NULL,
    ];

    /**
     * Translate an EA URL operator into a canonical Polysource name.
     * Unknown operators fall back to `$default` rather than passing
     * through verbatim — hostile clients cannot persist criteria with
     * arbitrary operator text downstream consumers have to defensively
     * reject (defense-in-depth hardening from the v0.9.0 audit).
     */
    public static function fromEa(string $easyAdminOperator, string $default = self::EQ): string
    {
        $key = strtolower(trim($easyAdminOperator));
        if ('' === $key) {
            return $default;
        }

        return self::EA_TO_CANONICAL[$key] ?? $default;
    }

    /**
     * Translate a canonical Polysource operator into its EA URL form.
     * The default fallback covers truly unknown canonical names —
     * callers should pass values they already validated to come from
     * the `OperatorMap::*` constants.
     */
    public static function toEa(string $canonicalOperator, string $default = '='): string
    {
        $key = strtolower(trim($canonicalOperator));

        return self::CANONICAL_TO_EA[$key] ?? $default;
    }

    /**
     * True iff the operator is a Polysource canonical name. Useful
     * for guard clauses in services that compose criteria.
     */
    public static function isCanonical(string $operator): bool
    {
        return \array_key_exists(strtolower(trim($operator)), self::CANONICAL_TO_EA);
    }
}
