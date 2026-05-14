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
     * @param list<string>  $hiddenColumns  property names hidden by the user
     * @param ?list<string> $orderedColumns Explicit display order (since v0.5.0). Null means "use the host's default order". When non-null, the host's render loop iterates this list (falling back to default columns for any not listed).
     */
    public function __construct(
        public readonly string $ownerId,
        public readonly string $resourceName,
        public readonly array $hiddenColumns,
        public readonly ?array $orderedColumns = null,
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
        return new self(
            $this->ownerId,
            $this->resourceName,
            array_values(array_unique($hiddenColumns)),
            $this->orderedColumns,
        );
    }

    /**
     * Set the explicit display order for the columns. Pass `null` to
     * clear the override (revert to the host's default ordering).
     *
     * @param ?list<string> $orderedColumns
     *
     * @since 0.5.0
     */
    public function withOrder(?array $orderedColumns): self
    {
        return new self(
            $this->ownerId,
            $this->resourceName,
            $this->hiddenColumns,
            null === $orderedColumns ? null : array_values(array_unique($orderedColumns)),
        );
    }

    /**
     * Apply the explicit order (if any) on top of the host's default
     * column list. Columns appearing in the override come first in
     * the override's order; any default-column not in the override is
     * appended in default order. Pure function — returns a new list,
     * never mutates the inputs.
     *
     * @param list<string> $defaultColumns the host's default ordering
     *
     * @return list<string>
     *
     * @since 0.5.0
     */
    public function applyOrder(array $defaultColumns): array
    {
        if (null === $this->orderedColumns || [] === $this->orderedColumns) {
            return array_values($defaultColumns);
        }

        $ordered = [];
        foreach ($this->orderedColumns as $column) {
            if (\in_array($column, $defaultColumns, true)) {
                $ordered[] = $column;
            }
        }
        foreach ($defaultColumns as $column) {
            if (!\in_array($column, $ordered, true)) {
                $ordered[] = $column;
            }
        }

        return $ordered;
    }
}
