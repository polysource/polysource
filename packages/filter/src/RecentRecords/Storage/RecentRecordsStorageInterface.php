<?php

declare(strict_types=1);

namespace Polysource\Filter\RecentRecords\Storage;

use Polysource\Filter\RecentRecords\Model\RecentRecord;

/**
 * Upsert-by-natural-key storage for the per-user
 * recently-viewed records log.
 *
 * @since 0.5.0
 */
interface RecentRecordsStorageInterface
{
    /**
     * Upsert by (ownerId, resourceName, recordId). The latest
     * `viewedAt` wins; the label is replaced on each call.
     */
    public function upsert(RecentRecord $record): void;

    /**
     * Most-recently-viewed first, scoped to (ownerId, resourceName).
     *
     * @return list<RecentRecord>
     */
    public function recent(string $ownerId, string $resourceName, int $limit): array;
}
