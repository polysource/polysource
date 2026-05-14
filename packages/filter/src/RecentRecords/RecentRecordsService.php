<?php

declare(strict_types=1);

namespace Polysource\Filter\RecentRecords;

use DateTimeImmutable;
use Polysource\Filter\RecentRecords\Model\RecentRecord;
use Polysource\Filter\RecentRecords\Storage\RecentRecordsStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Application service for the per-user recently-viewed records
 * log.
 *
 * Hosts call `recordView()` from their detail / edit action;
 * the service upserts the (current user, resource, recordId)
 * triplet with the current timestamp. The "recently viewed"
 * widget reads back via `recentForCurrentUser()`.
 *
 * Anonymous users get a no-op (same convention as other
 * Polysource services).
 *
 * @since 0.5.0
 */
final class RecentRecordsService
{
    public function __construct(
        private readonly RecentRecordsStorageInterface $storage,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    /**
     * Record a view of `(resourceName, recordId)` by the current
     * user. Upserts: same record viewed multiple times stays as a
     * single row with the latest timestamp.
     */
    public function recordView(
        string $resourceName,
        string $recordId,
        ?string $label = null,
        ?DateTimeImmutable $viewedAt = null,
    ): void {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return;
        }

        $this->storage->upsert(new RecentRecord(
            ownerId: $ownerId,
            resourceName: $resourceName,
            recordId: $recordId,
            viewedAt: $viewedAt ?? new DateTimeImmutable(),
            label: $label,
        ));
    }

    /**
     * @return list<RecentRecord>
     */
    public function recentForCurrentUser(string $resourceName, int $limit = 10): array
    {
        $ownerId = $this->resolveOwnerId();
        if (null === $ownerId) {
            return [];
        }

        return $this->storage->recent($ownerId, $resourceName, $limit);
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
