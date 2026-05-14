<?php

declare(strict_types=1);

namespace Polysource\Filter\BulkActionHistory\Model;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Domain value object — one row of the bulk-action audit log.
 *
 * Append-only by design: hosts record an entry each time a bulk
 * action runs against a resource. The log lets admins audit who
 * did what, on how many rows, and when. Rollback is NOT in scope
 * for v0.5.0 (host-specific — each action knows how to undo
 * itself); the log just preserves the trail.
 *
 * Immutable. Built via the constructor; never mutated.
 *
 * @since 0.5.0
 */
final class BulkActionEntry
{
    /**
     * @param string               $id             host-generated globally-unique identifier (UUID v7 recommended)
     * @param string               $ownerId        the user who triggered the action
     * @param string               $resourceName   logical resource (e.g. "orders")
     * @param string               $actionName     symbolic action name (e.g. "archive", "mark_paid")
     * @param int                  $affectedCount  number of rows the action touched (non-negative)
     * @param DateTimeImmutable    $occurredAt     when it ran
     * @param array<string, mixed> $metadata       free-form action-specific payload (filters, target ids, etc.)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $ownerId,
        public readonly string $resourceName,
        public readonly string $actionName,
        public readonly int $affectedCount,
        public readonly DateTimeImmutable $occurredAt,
        public readonly array $metadata = [],
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('BulkActionEntry id cannot be empty.');
        }
        if ('' === $ownerId) {
            throw new InvalidArgumentException('BulkActionEntry ownerId cannot be empty.');
        }
        if ('' === $resourceName) {
            throw new InvalidArgumentException('BulkActionEntry resourceName cannot be empty.');
        }
        if ('' === $actionName) {
            throw new InvalidArgumentException('BulkActionEntry actionName cannot be empty.');
        }
        if ($affectedCount < 0) {
            throw new InvalidArgumentException('BulkActionEntry affectedCount cannot be negative.');
        }
    }
}
