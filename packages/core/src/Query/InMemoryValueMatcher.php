<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Apply a Polysource {@see FilterOperator} predicate against an
 * in-memory value pair, returning true iff the value matches.
 *
 * Three families of adapter share this matcher — all of them load
 * records into PHP memory rather than pushing the WHERE down to a
 * query engine:
 *
 *   - {@see \Polysource\Adapter\Flysystem\DataSource\FlysystemDataSource}
 *   - {@see \Polysource\Adapter\Redis\DataSource\*} (5 type-specific
 *     adapters)
 *   - {@see \Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource}
 *
 * Before extraction each adapter shipped its own near-identical
 * `match($operator)` block, with subtle divergences (Redis added
 * `asBool` coercion that Flysystem skipped, Messenger had Between
 * + Null-check support that others didn't). The matcher lives in
 * core so every in-memory adapter inherits the same operator
 * vocabulary by default. Adapters that want a narrower subset
 * still call this helper — operators outside their declared
 * capability simply never get dispatched.
 *
 * Extracted from 3 adapters in v0.10.0 per audit task #65.
 *
 * @since 0.10.0
 */
final class InMemoryValueMatcher
{
    /**
     * Test whether `$value` satisfies the given criterion's
     * `$operator $expected` predicate.
     *
     * Returns true unconditionally for {@see FilterOperator} variants
     * the matcher doesn't know how to handle — keeps narrow-support
     * adapters (Flysystem, Redis hash) unconstrained on the
     * remainder of the query rather than blanket-rejecting every
     * record.
     */
    public static function matches(mixed $value, FilterOperator $operator, mixed $expected): bool
    {
        return match ($operator) {
            FilterOperator::Eq => self::looseEquals($value, $expected),
            FilterOperator::Neq => !self::looseEquals($value, $expected),
            FilterOperator::In => \is_array($expected) && self::isInList($value, $expected),
            FilterOperator::Nin => \is_array($expected) && !self::isInList($value, $expected),
            FilterOperator::Like => \is_string($value) && \is_string($expected) && false !== stripos($value, $expected),
            FilterOperator::Gt => self::compareDateOrScalar($value, $expected) > 0,
            FilterOperator::Gte => self::compareDateOrScalar($value, $expected) >= 0,
            FilterOperator::Lt => self::compareDateOrScalar($value, $expected) < 0,
            FilterOperator::Lte => self::compareDateOrScalar($value, $expected) <= 0,
            FilterOperator::Between => self::matchesBetween($value, $expected),
            FilterOperator::IsNull => null === $value,
            FilterOperator::IsNotNull => null !== $value,
        };
    }

    private static function looseEquals(mixed $value, mixed $expected): bool
    {
        if (\is_bool($expected)) {
            return self::asBool($value) === $expected;
        }

        return self::asString($value) === self::asString($expected);
    }

    /**
     * @param array<int|string, mixed> $list
     */
    private static function isInList(mixed $value, array $list): bool
    {
        $needle = self::asString($value);
        foreach ($list as $candidate) {
            if (self::asString($candidate) === $needle) {
                return true;
            }
        }

        return false;
    }

    private static function matchesBetween(mixed $value, mixed $expected): bool
    {
        if (!\is_array($expected) || 2 !== \count($expected)) {
            return false;
        }
        [$min, $max] = array_values($expected);

        return self::compareDateOrScalar($value, $min) >= 0
            && self::compareDateOrScalar($value, $max) <= 0;
    }

    /**
     * Compare two values numerically when both parse as numbers,
     * temporally when both parse as ISO-8601 dates, else
     * lexicographically. Returns -1/0/1 like {@see strcmp()}.
     */
    private static function compareDateOrScalar(mixed $value, mixed $expected): int
    {
        $valueDate = self::tryParseDate($value);
        $expectedDate = self::tryParseDate($expected);
        if (null !== $valueDate && null !== $expectedDate) {
            return $valueDate <=> $expectedDate;
        }

        if (is_numeric($value) && is_numeric($expected)) {
            return (float) $value <=> (float) $expected;
        }

        return self::asString($value) <=> self::asString($expected);
    }

    private static function tryParseDate(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }
        if (!\is_string($value) || 1 !== preg_match('/^\d{4}-\d{2}-\d{2}/', $value)) {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private static function asString(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }

    private static function asBool(mixed $value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }
        if (\is_string($value)) {
            return \in_array(strtolower($value), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        return (bool) $value;
    }
}
