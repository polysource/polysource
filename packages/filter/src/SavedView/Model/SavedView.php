<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Model;

use InvalidArgumentException;
use Polysource\Filter\Model\FilterCollection;

/**
 * A persisted working state on a Resource — name + filters + columns
 * + sort + page-size + scope. Cf. ADR-019.
 *
 * Immutable. Build via the constructor; mutate via `with*()` setters
 * (returning a new instance) once those are added.
 *
 * The `id` is **host-generated** (typically UUID v7). Polysource does
 * not generate ids — it lets the storage choose its convention. This
 * keeps the model decoupled from any persistence backend.
 *
 * @since 0.1.0
 */
final class SavedView
{
    /**
     * @param string                $id             Host-generated globally-unique identifier
     * @param string                $name           User-provided label, displayed in the dropdown
     * @param string                $resourceName   Resource scope (e.g. "products")
     * @param string                $ownerId        User identifier (string-cast — the host's user model field)
     * @param SavedViewScope        $scope          Visibility level
     * @param FilterCollection      $filters        Persisted criteria
     * @param list<string>          $columns        Selected columns (empty = host's vanilla default)
     * @param array<string, string> $sort           Column → 'asc'|'desc' direction map
     * @param ?int                  $pageSize       Override host's vanilla page size; null keeps the default
     * @param ?string               $teamId         Required when scope = TEAM
     * @param bool                  $isDefault      If true, applied automatically on first visit per role
     * @param ?string               $roleAsDefault  Symfony role this view defaults for (required when isDefault)
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $resourceName,
        public readonly string $ownerId,
        public readonly SavedViewScope $scope,
        public readonly FilterCollection $filters,
        public readonly array $columns = [],
        public readonly array $sort = [],
        public readonly ?int $pageSize = null,
        public readonly ?string $teamId = null,
        public readonly bool $isDefault = false,
        public readonly ?string $roleAsDefault = null,
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('SavedView id cannot be empty.');
        }
        if ('' === $name) {
            throw new InvalidArgumentException('SavedView name cannot be empty.');
        }
        if ('' === $resourceName) {
            throw new InvalidArgumentException('SavedView resourceName cannot be empty.');
        }
        if ('' === $ownerId) {
            throw new InvalidArgumentException('SavedView ownerId cannot be empty.');
        }
        if (SavedViewScope::TEAM === $scope && (null === $teamId || '' === $teamId)) {
            throw new InvalidArgumentException('SavedView with scope TEAM requires a non-empty teamId.');
        }
        if (SavedViewScope::TEAM !== $scope && null !== $teamId) {
            throw new InvalidArgumentException(\sprintf('SavedView with scope %s must not carry a teamId — that field is reserved for TEAM scope.', $scope->value));
        }
        if ($isDefault && (null === $roleAsDefault || '' === $roleAsDefault)) {
            throw new InvalidArgumentException('SavedView marked isDefault requires a non-empty roleAsDefault.');
        }
        if (!$isDefault && null !== $roleAsDefault) {
            throw new InvalidArgumentException('SavedView with roleAsDefault set must also have isDefault=true.');
        }

        // Validate sort directions (defensive — typed arrays would be
        // better but PHP doesn't enforce them at runtime).
        foreach ($sort as $column => $direction) {
            if (!\is_string($column) || '' === $column) {
                throw new InvalidArgumentException('SavedView sort keys must be non-empty strings.');
            }
            if (!\in_array($direction, ['asc', 'desc'], true)) {
                throw new InvalidArgumentException(\sprintf('SavedView sort direction must be "asc" or "desc", got %s for column "%s".', var_export($direction, true), $column));
            }
        }

        // Validate columns are non-empty strings (a list, not assoc).
        foreach ($columns as $column) {
            if (!\is_string($column) || '' === $column) {
                throw new InvalidArgumentException('SavedView columns must be a list of non-empty strings.');
            }
        }

        if (null !== $pageSize && $pageSize <= 0) {
            throw new InvalidArgumentException('SavedView pageSize, when provided, must be positive.');
        }
    }

    /**
     * True when the current user (identified by `$userId` and an
     * optional team) is allowed to *see* this view based on its
     * scope alone. The full permission check lives in
     * {@see \Polysource\Filter\SavedView\Security\SavedViewVoter}
     * which can layer additional rules on top.
     */
    public function isVisibleTo(string $userId, ?string $teamId = null): bool
    {
        return match ($this->scope) {
            SavedViewScope::PRIVATE => $this->ownerId === $userId,
            SavedViewScope::TEAM => null !== $teamId && $this->teamId === $teamId,
            SavedViewScope::PUBLIC => true,
        };
    }
}
