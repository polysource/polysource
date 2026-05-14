<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\ColumnPreference;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\ColumnPreference\Model\ColumnPreference;

/**
 * Covers the v0.5.0 column-order extension to {@see ColumnPreference}:
 * the optional `orderedColumns` field, `withOrder()` factory, and
 * the `applyOrder()` pure function that merges an override on top
 * of the host's default ordering.
 */
#[CoversClass(ColumnPreference::class)]
final class ColumnOrderTest extends TestCase
{
    #[Test]
    public function defaultsOrderedColumnsToNull(): void
    {
        $pref = new ColumnPreference('alice', 'orders', []);

        self::assertNull($pref->orderedColumns);
    }

    #[Test]
    public function withOrderReplacesTheOverride(): void
    {
        $pref = new ColumnPreference('alice', 'orders', [], null);

        $next = $pref->withOrder(['status', 'reference']);

        self::assertSame(['status', 'reference'], $next->orderedColumns);
        // Original instance untouched — immutability.
        self::assertNull($pref->orderedColumns);
    }

    #[Test]
    public function withOrderNullClearsTheOverride(): void
    {
        $pref = new ColumnPreference('alice', 'orders', [], ['status', 'reference']);

        $cleared = $pref->withOrder(null);

        self::assertNull($cleared->orderedColumns);
    }

    #[Test]
    public function withOrderDeduplicatesEntries(): void
    {
        $pref = new ColumnPreference('alice', 'orders', []);

        $next = $pref->withOrder(['status', 'reference', 'status']);

        self::assertSame(['status', 'reference'], $next->orderedColumns);
    }

    #[Test]
    public function applyOrderReturnsDefaultsWhenNoOverride(): void
    {
        $pref = new ColumnPreference('alice', 'orders', []);

        $resolved = $pref->applyOrder(['id', 'reference', 'status']);

        self::assertSame(['id', 'reference', 'status'], $resolved);
    }

    #[Test]
    public function applyOrderRespectsOverrideAndAppendsUnlistedDefaults(): void
    {
        $pref = new ColumnPreference('alice', 'orders', [], ['status', 'reference']);

        $resolved = $pref->applyOrder(['id', 'reference', 'status', 'createdAt']);

        // Override comes first in override-order; defaults not in the
        // override (id, createdAt) follow in their default order.
        self::assertSame(['status', 'reference', 'id', 'createdAt'], $resolved);
    }

    #[Test]
    public function applyOrderDropsOverrideEntriesNotInTheDefaultColumns(): void
    {
        // Defensive: an override that references a stale column the
        // host no longer renders must NOT inject a bogus column into
        // the resolved list.
        $pref = new ColumnPreference('alice', 'orders', [], ['removed', 'reference']);

        $resolved = $pref->applyOrder(['id', 'reference']);

        self::assertSame(['reference', 'id'], $resolved);
    }

    #[Test]
    public function withHiddenPreservesTheOrderOverride(): void
    {
        $pref = new ColumnPreference('alice', 'orders', ['legacy'], ['status', 'reference']);

        $next = $pref->withHidden(['archived']);

        self::assertSame(['status', 'reference'], $next->orderedColumns);
    }
}
