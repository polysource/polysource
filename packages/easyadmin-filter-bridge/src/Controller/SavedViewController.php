<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Polysource\EasyAdminFilterBridge\Filter\FilterUrlParser;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Exception\SavedViewAccessDeniedException;
use Polysource\Filter\SavedView\Exception\SavedViewDuplicateNameException;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Security\SavedViewTeamResolverInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Default controller for saved-view create/delete shipped by the
 * EasyAdmin filter bridge so hosts get a working Save / Delete out of
 * the box. Hosts that need custom redirect/permission logic override
 * the routes by re-declaring them at higher priority.
 *
 * Routes:
 *   - POST /admin/saved-views                → polysource_saved_view_create
 *   - POST /admin/saved-views/{id}/delete    → polysource_saved_view_delete
 *
 * Decodes EasyAdmin's filter URL shape:
 *
 *     filters[<property>][comparison]=<op>
 *     filters[<property>][value]=<scalar>
 *     filters[<property>][value][]=<v1>&filters[<property>][value][]=<v2>
 *     filters[<property>][value][min/max] / [from/to]      (between)
 *
 * Comparison operators are mapped to Polysource canonical names
 * (`eq`/`neq`/`gt`/`gte`/`lt`/`lte`/`like`/`in`/`between`).
 *
 * @internal hosts override by registering routes with the same names
 *           at higher priority; this controller is final to keep the
 *           bundle's SemVer surface lean
 */
final class SavedViewController
{
    public function __construct(
        private readonly SavedViewService $service,
        private readonly Security $security,
        private readonly ?SavedViewTeamResolverInterface $teamResolver = null,
    ) {
    }

    #[Route('/admin/saved-views', name: 'polysource_saved_view_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $user = $this->security->getUser();
        if ($user === null) {
            throw new AccessDeniedHttpException();
        }

        $resource = (string) $request->query->get('resource', '');
        $name = trim((string) $request->request->get('name', ''));
        $scopeRaw = (string) $request->request->get('scope', 'private');
        $filterQs = (string) $request->request->get('filter_querystring', '');

        if ($resource === '' || $name === '') {
            $this->flash($request, 'warning', 'Saved view requires a non-empty name and resource.');

            return $this->redirectToReferrer($request);
        }

        parse_str($filterQs, $parsed);
        $filterRaw = (array) ($parsed['filters'] ?? $parsed['filter'] ?? []);
        /** @var array<string, mixed> $filterRaw */
        $criteria = self::buildCriteria($filterRaw);
        // Delegation lives in self::buildCriteria below — kept as a
        // thin shim so existing test/host call-sites stay valid.

        if ($criteria === []) {
            $this->flash($request, 'warning', 'Apply at least one filter before saving a view.');

            return $this->redirectToReferrer($request);
        }

        $scope = SavedViewScope::tryFrom($scopeRaw) ?? SavedViewScope::PRIVATE;
        $teamId = null;

        if (SavedViewScope::TEAM === $scope) {
            // SavedView's invariant: TEAM scope requires a non-empty
            // teamId. Resolve it via the optional team resolver. When
            // the host hasn't wired one (or the resolver returns null
            // for this user), the view falls back to PRIVATE so the
            // save doesn't crash with InvalidArgumentException.
            $teamId = null !== $this->teamResolver
                ? $this->teamResolver->teamIdFor($user)
                : null;
            if (null === $teamId || '' === $teamId) {
                $scope = SavedViewScope::PRIVATE;
                $this->flash(
                    $request,
                    'warning',
                    'Team scope unavailable (no team resolver wired or user has no team) — saved as private instead.',
                );
            }
        }

        $view = new SavedView(
            id: Uuid::v7()->toRfc4122(),
            name: $name,
            resourceName: $resource,
            ownerId: $user->getUserIdentifier(),
            scope: $scope,
            filters: new FilterCollection($resource, $criteria),
            teamId: $teamId,
        );

        try {
            $this->service->save($view);
            $this->flash($request, 'success', \sprintf('View "%s" saved.', $name));
        } catch (SavedViewDuplicateNameException $e) {
            $this->flash($request, 'warning', \sprintf('A view named "%s" already exists.', $e->name));
        } catch (SavedViewAccessDeniedException) {
            // The voter denied EDIT or SHARE on the existing view. Translate
            // to 403 instead of leaking the framework exception — the user
            // gets a clean denial rather than a 500.
            throw new AccessDeniedHttpException('You are not authorized to save this view.');
        }

        return $this->redirectToReferrer($request);
    }

    #[Route('/admin/saved-views/{id}/delete', name: 'polysource_saved_view_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): RedirectResponse
    {
        try {
            $this->service->delete($id);
        } catch (SavedViewAccessDeniedException) {
            throw new AccessDeniedHttpException('You are not authorized to delete this view.');
        }
        $this->flash($request, 'success', 'View deleted.');

        return $this->redirectToReferrer($request);
    }

    /**
     * Mark/unmark the target view as the current user's personal
     * default for its resource. Toggles the flag — if the view is
     * already the user's default, this clears it; otherwise it sets
     * it (and clears the flag on every other personal-default view
     * the same user owns for the same resource).
     *
     * The semantics distinguish *personal* default (this endpoint —
     * any authenticated user can set their own) from *role* default
     * (admin-configured, written to storage directly via fixtures or
     * the admin UI). Role defaults have a non-null `roleAsDefault`
     * and are left untouched by this endpoint.
     *
     * @since 0.3.0
     */
    #[Route('/admin/saved-views/{id}/default', name: 'polysource_saved_view_toggle_default', methods: ['POST'])]
    public function toggleDefault(string $id, Request $request): RedirectResponse
    {
        $view = $this->service->load($id);
        if (null === $view) {
            throw new AccessDeniedHttpException('You are not authorized to mark this view as default.');
        }

        try {
            if ($view->isDefault && null === $view->roleAsDefault) {
                $this->service->unmarkAsDefault($id);
                $this->flash($request, 'success', 'View unmarked as default.');
            } else {
                $this->service->markAsDefault($id);
                $this->flash($request, 'success', 'View marked as default.');
            }
        } catch (SavedViewAccessDeniedException) {
            throw new AccessDeniedHttpException('You are not authorized to mark this view as default.');
        }

        return $this->redirectToReferrer($request);
    }

    private function redirectToReferrer(Request $request): RedirectResponse
    {
        // Fall back to `/` rather than `/admin`: the latter assumes
        // a specific EA mount which breaks on multi-tenant hosts
        // (`/{channel}/admin`) and on apps with a custom prefix.
        // Surfaced 2026-05-14 dogfooding round 3 (friction C9).
        $referrer = (string) $request->headers->get('referer', '/');

        return new RedirectResponse('' !== $referrer ? $referrer : '/');
    }

    private function flash(Request $request, string $type, string $message): void
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof SessionInterface && method_exists($session, 'getFlashBag')) {
            /** @phpstan-ignore-next-line — Symfony Session doesn't declare getFlashBag in iface */
            $session->getFlashBag()->add($type, $message);
        }
    }

    /**
     * Decode a filter-URL slice into canonical {@see FilterCriterion}
     * objects. Thin shim over {@see FilterUrlParser::buildCriteria()}
     * — the parsing logic was extracted in v0.9.0 (previously a
     * single 85-line static method with 9+ branches and 3-level
     * nesting). Kept here as a static facade so existing tests and
     * host call-sites stay valid.
     *
     * @param array<string, mixed> $raw
     *
     * @return list<FilterCriterion>
     */
    public static function buildCriteria(array $raw): array
    {
        return FilterUrlParser::buildCriteria($raw);
    }
}
