<?php

declare(strict_types=1);

namespace Polysource\Filter\ColumnPreference;

use Polysource\Filter\ColumnPreference\Model\ColumnPreference;
use Polysource\Filter\ColumnPreference\Storage\ColumnPreferenceStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Application service for per-user column-visibility preferences.
 *
 * Resolves the current user from Symfony's `TokenStorageInterface`,
 * delegates persistence to {@see ColumnPreferenceStorageInterface}.
 *
 * Same authentication flow as
 * {@see \Polysource\Filter\SavedView\SavedViewService}: anonymous
 * users (no token / no `UserInterface`) get a no-op behaviour — they
 * can't have prefs, so we return null and silently drop writes.
 * Hosts that need anonymous prefs can subclass the service or wire
 * a fallback owner identifier in their kernel.
 *
 * @since 0.3.0
 */
final class ColumnPreferenceService
{
    public function __construct(
        private readonly ColumnPreferenceStorageInterface $storage,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Return the current user's preference for the resource, or null
     * when no preference exists OR the user is anonymous (the caller
     * should treat null as "all columns visible").
     */
    public function findForCurrentUser(string $resourceName): ?ColumnPreference
    {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return null;
        }

        return $this->storage->find($ownerId, $resourceName);
    }

    /**
     * Return the list of hidden property names for the current user,
     * empty if no preference is saved (or user anonymous).
     *
     * @return list<string>
     */
    public function hiddenColumns(string $resourceName): array
    {
        $pref = $this->findForCurrentUser($resourceName);

        return null === $pref ? [] : $pref->hiddenColumns;
    }

    /**
     * Replace the current user's hidden-column list for the resource.
     * No-op for anonymous users.
     *
     * @param list<string> $hiddenColumns
     */
    public function setHiddenColumns(string $resourceName, array $hiddenColumns): void
    {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return;
        }

        // Preserve the existing `orderedColumns` override (since v0.5.0)
        // — flipping visibility shouldn't lose the user's reorder choice.
        $existing = $this->storage->find($ownerId, $resourceName);
        $orderedColumns = null === $existing ? null : $existing->orderedColumns;

        $pref = new ColumnPreference(
            ownerId: $ownerId,
            resourceName: $resourceName,
            hiddenColumns: array_values(array_unique($hiddenColumns)),
            orderedColumns: $orderedColumns,
        );

        $this->storage->save($pref);
    }

    /**
     * Return the current user's column order override for the
     * resource, or `null` when none is saved (caller falls back to
     * the host's default order).
     *
     * @return ?list<string>
     *
     * @since 0.5.0
     */
    public function orderedColumns(string $resourceName): ?array
    {
        $pref = $this->findForCurrentUser($resourceName);

        return null === $pref ? null : $pref->orderedColumns;
    }

    /**
     * Resolve the host's default column list through the user's
     * explicit order override (if any). Convenience for
     * `$service->findForCurrentUser($resource)?->applyOrder($defaults)
     * ?? $defaults`.
     *
     * @param list<string> $defaultColumns
     *
     * @return list<string>
     *
     * @since 0.5.0
     */
    public function applyOrder(string $resourceName, array $defaultColumns): array
    {
        $pref = $this->findForCurrentUser($resourceName);

        return null === $pref ? array_values($defaultColumns) : $pref->applyOrder($defaultColumns);
    }

    /**
     * Replace the current user's column order for the resource.
     * No-op for anonymous users. Pass `null` to clear the override.
     *
     * @param ?list<string> $orderedColumns
     *
     * @since 0.5.0
     */
    public function setColumnOrder(string $resourceName, ?array $orderedColumns): void
    {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return;
        }

        $existing = $this->storage->find($ownerId, $resourceName);
        $hidden = null === $existing ? [] : $existing->hiddenColumns;

        $next = new ColumnPreference(
            ownerId: $ownerId,
            resourceName: $resourceName,
            hiddenColumns: $hidden,
            orderedColumns: null === $orderedColumns ? null : array_values(array_unique($orderedColumns)),
        );

        $this->storage->save($next);
    }

    /**
     * Drop the user's preference for the resource (reset to default
     * "all visible"). No-op for anonymous users.
     */
    public function reset(string $resourceName): void
    {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return;
        }

        $this->storage->delete($ownerId, $resourceName);
    }

    private function resolveOwnerId(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return $user->getUserIdentifier();
    }
}
