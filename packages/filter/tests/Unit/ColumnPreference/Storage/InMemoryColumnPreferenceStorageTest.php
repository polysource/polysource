<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\ColumnPreference\Storage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\ColumnPreference\Model\ColumnPreference;
use Polysource\Filter\ColumnPreference\Storage\InMemoryColumnPreferenceStorage;

#[CoversClass(InMemoryColumnPreferenceStorage::class)]
final class InMemoryColumnPreferenceStorageTest extends TestCase
{
    #[Test]
    public function findReturnsNullForUnknownPair(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();

        self::assertNull($storage->find('alice', 'orders'));
    }

    #[Test]
    public function savePersistsAndFindRetrievesByCompositeKey(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $pref = new ColumnPreference('alice', 'orders', ['paidAt']);

        $storage->save($pref);

        self::assertSame($pref, $storage->find('alice', 'orders'));
        // Different owner — no leak
        self::assertNull($storage->find('bob', 'orders'));
        // Different resource — no leak
        self::assertNull($storage->find('alice', 'customers'));
    }

    #[Test]
    public function saveUpsertsExistingPair(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $storage->save(new ColumnPreference('alice', 'orders', ['paidAt']));
        $storage->save(new ColumnPreference('alice', 'orders', ['shippedAt']));

        $stored = $storage->find('alice', 'orders');

        self::assertNotNull($stored);
        self::assertSame(['shippedAt'], $stored->hiddenColumns);
    }

    #[Test]
    public function deleteRemovesThePairOnly(): void
    {
        $storage = new InMemoryColumnPreferenceStorage();
        $storage->save(new ColumnPreference('alice', 'orders', ['paidAt']));
        $storage->save(new ColumnPreference('alice', 'customers', ['phone']));

        $storage->delete('alice', 'orders');

        self::assertNull($storage->find('alice', 'orders'));
        self::assertNotNull($storage->find('alice', 'customers'));
    }
}
