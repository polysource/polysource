<?php

declare(strict_types=1);

namespace Polysource\Filter\ColumnPreference\Storage;

use Polysource\Filter\ColumnPreference\Model\ColumnPreference;

/**
 * Persistence contract for per-user column-visibility preferences.
 *
 * Implementations:
 * - {@see DoctrineColumnPreferenceStorage} — Doctrine-backed, default.
 * - {@see InMemoryColumnPreferenceStorage} — test-only.
 *
 * Same pattern as {@see \Polysource\Filter\SavedView\Storage\SavedViewStorageInterface}.
 */
interface ColumnPreferenceStorageInterface
{
    /**
     * Returns the user's preference for the resource, or null if
     * none has been saved (host should treat as "all columns visible").
     */
    public function find(string $ownerId, string $resourceName): ?ColumnPreference;

    /**
     * Save (insert or update) the user's preference. Upsert semantics:
     * exactly one row per (ownerId, resourceName).
     */
    public function save(ColumnPreference $preference): void;

    /**
     * Drop the user's preference for the resource. Host should treat
     * a subsequent `find()` returning null as "all columns visible".
     */
    public function delete(string $ownerId, string $resourceName): void;
}
