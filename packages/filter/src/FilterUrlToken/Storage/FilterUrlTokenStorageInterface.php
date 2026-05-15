<?php

declare(strict_types=1);

namespace Polysource\Filter\FilterUrlToken\Storage;

use DateTimeImmutable;
use Polysource\Filter\FilterUrlToken\Model\FilterUrlToken;

/**
 * Read+write storage for filter URL tokens.
 *
 * @since 0.5.0
 */
interface FilterUrlTokenStorageInterface
{
    public function save(FilterUrlToken $token): void;

    /**
     * Resolve a token back to its full record, or `null` when the
     * token doesn't exist (or was pruned).
     */
    public function find(string $token): ?FilterUrlToken;

    /**
     * Remove every token whose `createdAt` is strictly older than
     * the cutoff. Returns the number of rows deleted. Hosts wire
     * this via `polysource:filter-url-tokens:purge --ttl=…` on a
     * cron — the table grows unbounded otherwise (every share-button
     * click mints a new token).
     *
     * @since 0.6.1
     */
    public function purgeOlderThan(DateTimeImmutable $cutoff): int;
}
