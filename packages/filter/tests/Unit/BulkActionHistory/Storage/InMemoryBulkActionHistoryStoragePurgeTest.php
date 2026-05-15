<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\BulkActionHistory\Storage;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\BulkActionHistory\Model\BulkActionEntry;
use Polysource\Filter\BulkActionHistory\Storage\InMemoryBulkActionHistoryStorage;

#[CoversClass(InMemoryBulkActionHistoryStorage::class)]
final class InMemoryBulkActionHistoryStoragePurgeTest extends TestCase
{
    #[Test]
    public function purgesEntriesStrictlyOlderThanCutoff(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        $storage->append($this->entry('e-old', new DateTimeImmutable('2025-01-01T00:00:00Z')));
        $storage->append($this->entry('e-cut', new DateTimeImmutable('2026-05-01T00:00:00Z')));
        $storage->append($this->entry('e-new', new DateTimeImmutable('2026-05-15T00:00:00Z')));

        $removed = $storage->purgeOlderThan(new DateTimeImmutable('2026-05-01T00:00:00Z'));

        // Strict less-than → boundary survives.
        self::assertSame(1, $removed);

        $remaining = $storage->recent(null, null, 10);
        $ids = array_map(static fn (BulkActionEntry $e): string => $e->id, $remaining);
        self::assertContains('e-cut', $ids);
        self::assertContains('e-new', $ids);
        self::assertNotContains('e-old', $ids);
    }

    #[Test]
    public function purgeOnEmptyStorageReportsZero(): void
    {
        $storage = new InMemoryBulkActionHistoryStorage();
        self::assertSame(0, $storage->purgeOlderThan(new DateTimeImmutable('now')));
    }

    private function entry(string $id, DateTimeImmutable $occurredAt): BulkActionEntry
    {
        return new BulkActionEntry(
            id: $id,
            ownerId: 'tester',
            resourceName: 'App\\Entity\\Order',
            actionName: 'archive',
            affectedCount: 1,
            occurredAt: $occurredAt,
            metadata: [],
        );
    }
}
