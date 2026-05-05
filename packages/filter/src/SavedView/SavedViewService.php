<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView;

use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Security\SavedViewTeamResolverInterface;
use Polysource\Filter\SavedView\Security\SavedViewVoter;
use Polysource\Filter\SavedView\Storage\SavedViewStorageInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * High-level API for saved views.
 *
 * Combines the storage layer, the Symfony voter, and the optional
 * team resolver to expose a workflow-shaped API:
 *
 *   - `listVisible()` — saved views the current user is allowed to see
 *   - `save()`       — persist a view (gated by voter EDIT/SHARE)
 *   - `load()`       — fetch + permission-check a single view
 *   - `delete()`     — remove a view (gated by voter DELETE)
 *   - `defaultFor()` — pick the right initial view for a resource
 *
 * Per ADR-019 §6 the service does NOT expose `apply()` — hosts (or
 * the bridge) take a view's `FilterCollection` + columns + sort and
 * hydrate them into the form themselves, exactly as they do for
 * URL-driven filter state.
 *
 * @since 0.1.0
 */
final class SavedViewService
{
    private const SESSION_KEY_PREFIX = 'polysource.filter.saved_view.last.';

    public function __construct(
        private readonly SavedViewStorageInterface $storage,
        private readonly AuthorizationCheckerInterface $authChecker,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly ?SavedViewTeamResolverInterface $teamResolver = null,
        private readonly ?RequestStack $requestStack = null,
    ) {
    }

    /**
     * Returns every saved view visible to the current user on the
     * given resource. Applies storage-level pre-filtering AND the
     * voter for defense-in-depth (storage filter is performance,
     * voter is authoritative — cf. SavedViewStorageInterface).
     *
     * @return list<SavedView>
     */
    public function listVisible(string $resourceName): array
    {
        $userId = $this->currentUserId();
        if (null === $userId) {
            // Anonymous user — show only PUBLIC views (the voter will
            // double-check). We still call storage to pre-filter.
            $userId = '';
        }

        $teamId = $this->currentTeamId();

        $candidates = $this->storage->listVisible($resourceName, $userId, $teamId);

        $visible = [];
        foreach ($candidates as $view) {
            if ($this->authChecker->isGranted(SavedViewVoter::VIEW, $view)) {
                $visible[] = $view;
            }
        }

        return $visible;
    }

    /**
     * Persists a saved view. EDIT permission required when the view
     * already exists; create-time check delegates to the storage
     * (saving a new view is implicitly allowed for any authenticated
     * user — they'll be the owner).
     */
    public function save(SavedView $view): SavedView
    {
        $existing = $this->storage->find($view->id);
        if (null !== $existing && !$this->authChecker->isGranted(SavedViewVoter::EDIT, $existing)) {
            throw new RuntimeException(\sprintf('Not authorized to edit saved view "%s".', $view->id));
        }

        // Scope change requires SHARE on the previous-state view.
        if (null !== $existing && $existing->scope !== $view->scope
            && !$this->authChecker->isGranted(SavedViewVoter::SHARE, $existing)
        ) {
            throw new RuntimeException(\sprintf('Not authorized to change scope of saved view "%s".', $view->id));
        }

        $this->storage->save($view);

        return $view;
    }

    public function load(string $id): ?SavedView
    {
        $view = $this->storage->find($id);
        if (null === $view) {
            return null;
        }

        if (!$this->authChecker->isGranted(SavedViewVoter::VIEW, $view)) {
            return null;
        }

        $this->rememberAsLastUsed($view);

        return $view;
    }

    public function delete(string $id): void
    {
        $existing = $this->storage->find($id);
        if (null === $existing) {
            return;
        }

        if (!$this->authChecker->isGranted(SavedViewVoter::DELETE, $existing)) {
            throw new RuntimeException(\sprintf('Not authorized to delete saved view "%s".', $id));
        }

        $this->storage->delete($id);
        $this->forgetLastUsed($existing->resourceName);
    }

    /**
     * Returns the view to apply on first visit. First match wins:
     *
     *   1. session "last used view" for this resource
     *   2. any visible view with isDefault=true matching a role the
     *      current user has (Symfony role hierarchy is consulted via
     *      `is_granted`)
     *   3. null — host falls back to vanilla default
     */
    public function defaultFor(string $resourceName): ?SavedView
    {
        // 1. session-remembered last-used
        $lastId = $this->readLastUsed($resourceName);
        if (null !== $lastId) {
            $view = $this->load($lastId);
            if (null !== $view) {
                return $view;
            }
        }

        // 2. role default
        foreach ($this->listVisible($resourceName) as $view) {
            if (!$view->isDefault || null === $view->roleAsDefault) {
                continue;
            }
            if ($this->authChecker->isGranted($view->roleAsDefault)) {
                return $view;
            }
        }

        // 3. nothing
        return null;
    }

    private function currentUserId(): ?string
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

    private function currentTeamId(): ?string
    {
        if (null === $this->teamResolver) {
            return null;
        }

        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        return $this->teamResolver->teamIdFor($user);
    }

    private function rememberAsLastUsed(SavedView $view): void
    {
        if (null === $this->requestStack) {
            return;
        }

        try {
            $session = $this->requestStack->getSession();
        } catch (\Symfony\Component\HttpFoundation\Exception\SessionNotFoundException) {
            // No session bound (e.g. running in a CLI context) —
            // silently skip; defaultFor() will just fall through.
            return;
        }

        $session->set(self::SESSION_KEY_PREFIX . $view->resourceName, $view->id);
    }

    private function forgetLastUsed(string $resourceName): void
    {
        if (null === $this->requestStack) {
            return;
        }

        try {
            $session = $this->requestStack->getSession();
        } catch (\Symfony\Component\HttpFoundation\Exception\SessionNotFoundException) {
            return;
        }

        $session->remove(self::SESSION_KEY_PREFIX . $resourceName);
    }

    private function readLastUsed(string $resourceName): ?string
    {
        if (null === $this->requestStack) {
            return null;
        }

        try {
            $session = $this->requestStack->getSession();
        } catch (\Symfony\Component\HttpFoundation\Exception\SessionNotFoundException) {
            return null;
        }

        $value = $session->get(self::SESSION_KEY_PREFIX . $resourceName);

        return \is_string($value) && '' !== $value ? $value : null;
    }
}
