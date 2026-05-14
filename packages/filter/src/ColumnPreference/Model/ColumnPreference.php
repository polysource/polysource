<?php

declare(strict_types=1);

namespace Polysource\Filter\ColumnPreference\Model;

/**
 * Domain value object — per-user, per-resource column visibility
 * preference.
 *
 * A user looking at the index of a resource (orders, customers,
 * products …) can hide columns they don't care about. The preference
 * is persisted server-side keyed by (owner_id, resource_name) — at
 * most one record per pair. Reloading the index applies the saved
 * preference.
 *
 * Kept deliberately small: just a list of *hidden* property names.
 * The "visible" set is computed at render time by subtracting from
 * the resource's full column list, so adding a column to the
 * resource later doesn't require a migration of every user's prefs.
 *
 * @since 0.3.0
 */
final class ColumnPreference
{
    /**
     * @param list<string> $hiddenColumns property names hidden by the user
     */
    public function __construct(
        public readonly string $ownerId,
        public readonly string $resourceName,
        public readonly array $hiddenColumns,
    ) {
    }

    public function isHidden(string $property): bool
    {
        return \in_array($property, $this->hiddenColumns, true);
    }

    /**
     * @param list<string> $hiddenColumns
     */
    public function withHidden(array $hiddenColumns): self
    {
        return new self($this->ownerId, $this->resourceName, array_values(array_unique($hiddenColumns)));
    }
}
