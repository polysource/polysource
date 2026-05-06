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
     *
     * @throws Exception\SavedViewDuplicateNameException
     *         when the (ownerId, resourceName, name) triple already
     *         exists on a different view (different id)
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

        // Uniqueness check: a user can't have two views with the same
        // name on the same resource. Skipped when we're updating the
        // same id (that's the same view being saved with potentially
        // mutated data).
        $this->assertUniqueName($view, $existing);

        $this->storage->save($view);

        return $view;
    }

    private function assertUniqueName(SavedView $candidate, ?SavedView $existing): void
    {
        // Walk the storage owner-scoped slice; cheap on the in-memory
        // impl, single SQL roundtrip on Doctrine.
        foreach ($this->storage->listVisible($candidate->resourceName, $candidate->ownerId) as $other) {
            if ($other->ownerId !== $candidate->ownerId) {
                continue; // not our view; PUBLIC views from others don't count
            }
            if ($other->id === $candidate->id) {
                continue; // self
            }
            if ($other->name === $candidate->name) {
                throw new Exception\SavedViewDuplicateNameException(name: $candidate->name, resourceName: $candidate->resourceName, ownerId: $candidate->ownerId);
            }
        }
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
     * Returns the view considered "currently applied" for the
     * resource. The dropdown uses this to mark one entry as active.
     *
     * Resolution rules:
     *   - URL has `?view=<id>` → that view (the apply round-trip
     *     hands the matching `<id>` over before the host's apply
     *     listener redirects to filters; we keep it highlighted
     *     during that single hop).
     *   - URL has `?filter[...]=...` (Polysource) or
     *     `?filters[...]=...` (EA) → null + forget session entry.
     *     The user is on user-applied filters; pretending the saved
     *     view is still active would be a lie.
     *   - URL is clean (no view, no filter) → null. The session's
     *     `last-used` is preserved (the user may re-pick from the
     *     dropdown to re-apply) but NOT shown as `current` because
     *     the table is not actually filtered. Showing the view as
     *     active here used to confuse users — dropdown said "Late
     *     deliveries" but the table showed every order.
     *   - Role default with `isDefault=true` matching the user's
     *     role still fires unconditionally (it's an admin-decided
     *     default, not user history).
     */
    public function defaultFor(string $resourceName): ?SavedView
    {
        $request = $this->requestStack?->getCurrentRequest();
        $viewId = null !== $request ? (string) $request->query->get('view', '') : '';

        // 1. Apply round-trip: `?view=<id>` is the SOURCE OF TRUTH.
        if ('' !== $viewId) {
            $view = $this->load($viewId);
            if (null !== $view) {
                return $view;
            }
        }

        // 2. User-applied custom filters: dismiss any session view.
        if ($this->hasUserAppliedFilters()) {
            $this->forgetLastUsed($resourceName);

            return null;
        }

        // 3. Role default (admin-configured). Independent of session
        //    history — reflects an org policy.
        foreach ($this->listVisible($resourceName) as $view) {
            if (!$view->isDefault || null === $view->roleAsDefault) {
                continue;
            }
            if ($this->authChecker->isGranted($view->roleAsDefault)) {
                return $view;
            }
        }

        // 4. Otherwise null. The session-stored last-used is
        //    preserved (so a future click on the dropdown can
        //    recover it quickly) but not displayed as `current`,
        //    because the rendered table doesn't reflect it.
        return null;
    }

    private function hasUserAppliedFilters(): bool
    {
        // RequestStack is optional (CLI / tests boot the service
        // without it); when absent, no request → no user-applied
        // filters to detect.
        if (null === $this->requestStack) {
            return false;
        }
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }
        // Saved-view apply round-trip: when `?view=<id>` is present
        // we assume the host's apply listener will redirect to the
        // saved-view's filters — keep the view highlighted.
        if ('' !== (string) $request->query->get('view', '')) {
            return false;
        }

        return $request->query->has('filter') || $request->query->has('filters');
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

    public function forgetLastUsed(string $resourceName): void
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

}
