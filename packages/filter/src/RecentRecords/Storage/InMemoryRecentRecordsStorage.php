<?php

declare(strict_types=1);

namespace Polysource\Filter\RecentRecords\Storage;

use Polysource\Filter\RecentRecords\Model\RecentRecord;

/**
 * In-memory storage — primarily for unit tests; useful in
 * single-request debugging too.
 *
 * @since 0.5.0
 */
final class InMemoryRecentRecordsStorage implements RecentRecordsStorageInterface
{
    /** @var array<string, RecentRecord> keyed by `owner\0resource\0recordId` */
    private array $byKey = [];

    public function upsert(RecentRecord $record): void
    {
        $this->byKey[$this->key($record->ownerId, $record->resourceName, $record->recordId)] = $record;
    }

    public function recent(string $ownerId, string $resourceName, int $limit): array
    {
        $matching = array_values(array_filter(
            $this->byKey,
            static fn (RecentRecord $r): bool => $r->ownerId === $ownerId && $r->resourceName === $resourceName,
        ));

        usort(
            $matching,
            static function (RecentRecord $a, RecentRecord $b): int {
                $cmp = $b->viewedAt <=> $a->viewedAt;

                return 0 !== $cmp ? $cmp : strcmp($b->recordId, $a->recordId);
            },
        );

        return array_values(\array_slice($matching, 0, max(0, $limit)));
    }

    private function key(string $ownerId, string $resourceName, string $recordId): string
    {
        return $ownerId . "\0" . $resourceName . "\0" . $recordId;
    }
}
