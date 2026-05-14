<?php

declare(strict_types=1);

namespace Polysource\Filter\RecentRecords\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Domain value object — one entry in the per-user "most
 * recently viewed records" list.
 *
 * Used to power a "Recently viewed" section in a command palette
 * or quick-nav menu: when a user opens a record (detail or
 * edit page), the host calls `RecentRecordsService::recordView()`;
 * the service upserts the (owner, resource, recordId) triplet
 * with the current timestamp. Reading back returns the
 * most-recently-touched records for that user + resource.
 *
 * Storage is upsert-by-natural-key — only the most recent
 * view-time per (user, resource, recordId) is preserved, so
 * the table grows linearly with the number of distinct records
 * a user touches (not with every visit).
 *
 * Immutable. Built via the constructor.
 *
 * @since 0.5.0
 */
final class RecentRecord
{
    /**
     * @param string            $ownerId      User identifier
     * @param string            $resourceName Logical resource (e.g. "orders")
     * @param string            $recordId     The record's identifier as a string (cast from int / UUID)
     * @param DateTimeImmutable $viewedAt     When the user last viewed it
     * @param ?string           $label        Optional display label for the command palette (e.g. "ORD-001 — Acme Co.")
     */
    public function __construct(
        public readonly string $ownerId,
        public readonly string $resourceName,
        public readonly string $recordId,
        public readonly DateTimeImmutable $viewedAt,
        public readonly ?string $label = null,
    ) {
        if ('' === $ownerId) {
            throw new InvalidArgumentException('RecentRecord ownerId cannot be empty.');
        }
        if ('' === $resourceName) {
            throw new InvalidArgumentException('RecentRecord resourceName cannot be empty.');
        }
        if ('' === $recordId) {
            throw new InvalidArgumentException('RecentRecord recordId cannot be empty.');
        }
        if (null !== $label && '' === $label) {
            throw new InvalidArgumentException('RecentRecord label, when provided, cannot be the empty string.');
        }
    }
}
