<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\ColumnPreference\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\ColumnPreference\Model\ColumnPreference;

#[CoversClass(ColumnPreference::class)]
final class ColumnPreferenceTest extends TestCase
{
    #[Test]
    public function isHiddenReturnsTrueForPropertiesInHiddenList(): void
    {
        $pref = new ColumnPreference('alice', 'orders', ['paidAt', 'shippedAt']);

        self::assertTrue($pref->isHidden('paidAt'));
        self::assertTrue($pref->isHidden('shippedAt'));
        self::assertFalse($pref->isHidden('reference'));
    }

    #[Test]
    public function withHiddenReturnsNewInstanceWithUpdatedListAndDeduplicates(): void
    {
        $pref = new ColumnPreference('alice', 'orders', ['paidAt']);

        $updated = $pref->withHidden(['paidAt', 'shippedAt', 'paidAt']);

        self::assertSame(['paidAt', 'shippedAt'], $updated->hiddenColumns);
        self::assertSame('alice', $updated->ownerId);
        self::assertSame('orders', $updated->resourceName);
        // Original VO is immutable
        self::assertSame(['paidAt'], $pref->hiddenColumns);
    }
}
