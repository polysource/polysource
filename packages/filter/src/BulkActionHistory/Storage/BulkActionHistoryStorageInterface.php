<?php

declare(strict_types=1);

namespace Polysource\Filter\BulkActionHistory\Storage;

use DateTimeImmutable;
use Polysource\Filter\BulkActionHistory\Model\BulkActionEntry;

/**
 * Append-only storage for the bulk-action audit log.
 *
 * @since 0.5.0
 */
interface BulkActionHistoryStorageInterface
{
    public function append(BulkActionEntry $entry): void;

    /**
     * Most-recent-first. `$resourceName === null` returns entries
     * across all resources (admin view). `$ownerId === null` returns
     * entries across all owners (admin view). Both scoped together
     * return only that user's actions for that resource.
     *
     * @return list<BulkActionEntry>
     */
    public function recent(?string $resourceName, ?string $ownerId, int $limit): array;

    /**
     * Remove every entry whose `occurredAt` is strictly older than
     * the cutoff. Returns the number of rows deleted. Hosts wire
     * this via `polysource:bulk-action-history:purge --ttl=…` on a
     * cron — the table grows unbounded otherwise.
     *
     * Hosts under regulatory retention requirements (GDPR Art. 30,
     * SOX, …) should NOT call this — the table IS the compliance
     * register. The TTL knob is for casual audit-log housekeeping
     * only.
     *
     * @since 0.6.1
     */
    public function purgeOlderThan(DateTimeImmutable $cutoff): int;
}
