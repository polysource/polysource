<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Storage;

use Polysource\Filter\SavedView\Model\SavedView;

/**
 * Persistence contract for saved views.
 *
 * Per ADR-019 §4, hosts pick their backend (Doctrine, Redis, S3 JSON
 * files, REST, …). v0.1 ships {@see DoctrineSavedViewStorage} as the
 * out-of-the-box impl gated by `class_exists(EntityManagerInterface)`,
 * but adopters with non-Doctrine stacks bring their own.
 *
 * Implementations MUST persist `SavedView` instances by their `id`
 * (host-generated UUID) and respect scope semantics on
 * {@see listVisible()} — private = owner only, team = same teamId,
 * public = everyone.
 *
 * Note: storage-level filtering is performance, not security. The
 * authoritative permission gate is
 * {@see \Polysource\Filter\SavedView\Security\SavedViewVoter}, which
 * any read path MUST consult before exposing a view to the user.
 *
 * @since 0.1.0
 */
interface SavedViewStorageInterface
{
    /**
     * Inserts a new view or updates an existing one (matched by `id`).
     */
    public function save(SavedView $view): void;

    /**
     * Returns the view by host-generated id, or null when absent.
     */
    public function find(string $id): ?SavedView;

    /**
     * Returns every view visible to the current user on the given
     * resource — owner's private views, team views shared with the
     * given teamId, and every public view.
     *
     * @return iterable<SavedView>
     */
    public function listVisible(
        string $resourceName,
        string $ownerId,
        ?string $teamId = null,
    ): iterable;

    /**
     * Removes the view identified by `id`. No-op when absent.
     */
    public function delete(string $id): void;
}
