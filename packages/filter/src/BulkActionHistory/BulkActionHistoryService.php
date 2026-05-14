<?php

declare(strict_types=1);

namespace Polysource\Filter\BulkActionHistory;

use DateTimeImmutable;
use Polysource\Filter\BulkActionHistory\Model\BulkActionEntry;
use Polysource\Filter\BulkActionHistory\Storage\BulkActionHistoryStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Application service for the bulk-action audit log.
 *
 * Hosts call `record()` from their bulk-action endpoint after the
 * action commits — the service resolves the current user, stamps
 * the entry with the current time, and appends to the configured
 * storage.
 *
 * Anonymous users (no token / no `UserInterface`) get a no-op
 * behaviour — same convention as
 * {@see \Polysource\Filter\SavedView\SavedViewService}.
 *
 * @since 0.5.0
 */
final class BulkActionHistoryService
{
    public function __construct(
        private readonly BulkActionHistoryStorageInterface $storage,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Record a bulk action in the audit log. No-op for anonymous
     * users.
     *
     * @param array<string, mixed> $metadata free-form action-specific payload
     */
    public function record(
        string $id,
        string $resourceName,
        string $actionName,
        int $affectedCount,
        array $metadata = [],
        ?DateTimeImmutable $occurredAt = null,
    ): void {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return;
        }

        $this->storage->append(new BulkActionEntry(
            id: $id,
            ownerId: $ownerId,
            resourceName: $resourceName,
            actionName: $actionName,
            affectedCount: $affectedCount,
            occurredAt: $occurredAt ?? new DateTimeImmutable(),
            metadata: $metadata,
        ));
    }

    /**
     * Return the most recent bulk actions for the current user
     * against a specific resource. Used by the host to render a
     * "recent activity" widget on the resource's index page.
     *
     * @return list<BulkActionEntry>
     */
    public function recentForCurrentUser(string $resourceName, int $limit = 10): array
    {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return [];
        }

        return $this->storage->recent($resourceName, $ownerId, $limit);
    }

    /**
     * Admin view — return recent bulk actions across all users for
     * a resource. Hosts MUST gate the calling endpoint behind their
     * own admin firewall / voter; the service trusts the caller.
     *
     * @return list<BulkActionEntry>
     */
    public function recentForResource(string $resourceName, int $limit = 50): array
    {
        return $this->storage->recent($resourceName, null, $limit);
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
