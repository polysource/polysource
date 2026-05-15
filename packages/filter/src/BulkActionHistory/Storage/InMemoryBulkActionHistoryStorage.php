<?php

declare(strict_types=1);

namespace Polysource\Filter\BulkActionHistory\Storage;

use DateTimeImmutable;
use Polysource\Filter\BulkActionHistory\Model\BulkActionEntry;

/**
 * In-memory storage — primarily for unit tests, but also useful
 * for hosts who want bulk-action history within a single request
 * (debugging) without provisioning a database table.
 *
 * @since 0.5.0
 */
final class InMemoryBulkActionHistoryStorage implements BulkActionHistoryStorageInterface
{
    /** @var list<BulkActionEntry> */
    private array $entries = [];

    public function append(BulkActionEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    public function recent(?string $resourceName, ?string $ownerId, int $limit): array
    {
        $filtered = array_filter(
            $this->entries,
            static function (BulkActionEntry $entry) use ($resourceName, $ownerId): bool {
                if (null !== $resourceName && $entry->resourceName !== $resourceName) {
                    return false;
                }
                if (null !== $ownerId && $entry->ownerId !== $ownerId) {
                    return false;
                }

                return true;
            },
        );

        // Most-recent-first by occurredAt then by id (stable
        // tiebreaker so unit tests can rely on deterministic order).
        $sorted = array_values($filtered);
        usort(
            $sorted,
            static function (BulkActionEntry $a, BulkActionEntry $b): int {
                $cmp = $b->occurredAt <=> $a->occurredAt;

                return 0 !== $cmp ? $cmp : strcmp($b->id, $a->id);
            },
        );

        return array_values(\array_slice($sorted, 0, max(0, $limit)));
    }

    public function purgeOlderThan(DateTimeImmutable $cutoff): int
    {
        $kept = [];
        $removed = 0;
        foreach ($this->entries as $entry) {
            if ($entry->occurredAt < $cutoff) {
                ++$removed;
                continue;
            }
            $kept[] = $entry;
        }
        $this->entries = $kept;

        return $removed;
    }
}
