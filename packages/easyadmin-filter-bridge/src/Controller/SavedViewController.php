<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Polysource\EasyAdminFilterBridge\Http\SafeReferer;
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
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
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
 *   - POST /admin/saved-views/{id}/default   → polysource_saved_view_toggle_default
 *
 * Security:
 *   - Authentication required (`getUser()` must be non-null).
 *   - CSRF token required on every POST. Tokens are scoped per
 *     operation so a leaked create-token cannot be replayed against
 *     delete/toggle-default. Token IDs:
 *       - `polysource_saved_view_create`
 *       - `polysource_saved_view_delete`
 *       - `polysource_saved_view_toggle_default`
 *   - Authorization is voter-enforced inside `SavedViewService`.
 *   - Redirects are filtered through {@see SafeReferer} so a crafted
 *     `Referer` header cannot become an open-redirect chain.
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
    private const CSRF_CREATE = 'polysource_saved_view_create';
    private const CSRF_DELETE = 'polysource_saved_view_delete';
    private const CSRF_TOGGLE_DEFAULT = 'polysource_saved_view_toggle_default';

    public function __construct(
        private readonly SavedViewService $service,
        private readonly Security $security,
        private readonly ?CsrfTokenManagerInterface $csrfTokenManager = null,
        private readonly ?SavedViewTeamResolverInterface $teamResolver = null,
    ) {
    }

    #[Route('/admin/saved-views', name: 'polysource_saved_view_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $this->assertCsrf($request, self::CSRF_CREATE);

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
        $this->assertCsrf($request, self::CSRF_DELETE);

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
        $this->assertCsrf($request, self::CSRF_TOGGLE_DEFAULT);

        // Auth-failure paths intentionally converge on a single
        // AccessDeniedHttpException with the same message regardless
        // of which step failed:
        //
        //   1. `load()` returns null when the view doesn't exist OR
        //      when the user has no VIEW permission on it (the voter
        //      runs inside `SavedViewService::load`).
        //   2. `markAsDefault()` / `unmarkAsDefault()` throw
        //      SavedViewAccessDeniedException when the user has VIEW
        //      but not EDIT on the target view.
        //
        // Both code paths emit the SAME exception with the SAME
        // message — no timing leak between "view doesn't exist" and
        // "view exists but user can't edit it". The leak the v0.9.0
        // audit flagged as MEDIUM was a misreading of load()'s
        // contract.
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

    /**
     * Validate the submitted `_token` field against the given CSRF id.
     * Throws 403 on missing/invalid token. Exits the request before
     * any state mutation so unauthorized POSTs cannot persist anything.
     *
     * Fails closed when the CSRF token manager is not wired — hosts
     * that haven't enabled `framework.csrf_protection` get 403 on
     * every save-view POST. Better than autowire-failure that breaks
     * the entire DI compilation (the previous v0.9.0 PR1 attempt at
     * a non-nullable required dep blew up the bundle's compile on
     * kernels without CSRF). Hosts MUST enable
     * `framework.csrf_protection` to actually use saved views.
     */
    private function assertCsrf(Request $request, string $tokenId): void
    {
        if (null === $this->csrfTokenManager) {
            throw new AccessDeniedHttpException('CSRF protection not configured — enable `framework.csrf_protection` to use saved views.');
        }

        $submitted = (string) $request->request->get('_token', '');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $submitted))) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }
    }

    private function redirectToReferrer(Request $request): RedirectResponse
    {
        // Fall back to `/` rather than `/admin`: the latter assumes
        // a specific EA mount which breaks on multi-tenant hosts
        // (`/{channel}/admin`) and on apps with a custom prefix.
        // Surfaced 2026-05-14 dogfooding round 3 (friction C9).
        // SafeReferer rejects external hosts so a crafted Referer
        // header cannot turn this POST into an open redirect.
        return new RedirectResponse(SafeReferer::resolve($request, '/'));
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
     * @param array<string, mixed> $raw
     *
     * @return list<FilterCriterion>
     */
    public static function buildCriteria(array $raw): array
    {
        $criteria = [];

        foreach ($raw as $field => $config) {
            $field = (string) $field;
            if (!\is_array($config)) {
                if (!\is_scalar($config) || $config === '') {
                    continue;
                }
                $criteria[] = new FilterCriterion($field, 'eq', [(string) $config]);
                continue;
            }

            $value = $config['value'] ?? null;
            // Operator key is `comparison` in EasyAdmin's URL shape
            // (`?filters[X][comparison]==&[value]=foo`) and `op` in
            // Polysource's URL shape (`?filter[X][op]=like&[value]=foo`).
            // Reading both lets the same controller handle saves from
            // either page family.
            $comparison = \is_string($config['comparison'] ?? null)
                ? $config['comparison']
                : (\is_string($config['op'] ?? null) ? $config['op'] : '');

            // The Polysource shape can also pack a multi-value list as
            // `?filter[X][values][]=a&[values][]=b` (operator usually
            // `in`). Promote it so the list branch below sees it.
            if ($value === null && \is_array($config['values'] ?? null) && $config['values'] !== []) {
                $value = $config['values'];
            }

            // Polysource between shape: `?filter[X][op]=between&[min]=..&[max]=..`
            // packs min/max as direct siblings of op (not nested in
            // `value`). Promote into the EA-shaped `{min, max}`
            // envelope so the next branch picks them up.
            if ($value === null && (isset($config['min']) || isset($config['max']))) {
                $value = [
                    'min' => $config['min'] ?? '',
                    'max' => $config['max'] ?? '',
                ];
            }

            if ($value === '' || $value === null || (\is_array($value) && $value === [])) {
                continue;
            }

            // between (date range / numeric range).
            if (\is_array($value) && (isset($value['min']) || isset($value['max']) || isset($value['from']) || isset($value['to']))) {
                $minRaw = $value['min'] ?? $value['from'] ?? '';
                $maxRaw = $value['max'] ?? $value['to'] ?? '';
                $min = \is_scalar($minRaw) ? (string) $minRaw : '';
                $max = \is_scalar($maxRaw) ? (string) $maxRaw : '';
                if ($min !== '' || $max !== '') {
                    $criteria[] = new FilterCriterion($field, 'between', [$min, $max]);
                }
                continue;
            }

            // Indexed list → in (multi-select choice).
            // Force `in` regardless of the comparison operator
            // EA may carry: EA's ChoiceFilter with canSelectMultiple
            // submits as `?filters[X][comparison]==&[value][]=a&[value][]=b`.
            // Translating `=` to `eq` would store an exact-equality
            // criterion against a list, which the data sources can't
            // honour. The semantic of "value IS one of these" is
            // always `in` — fall through.
            if (\is_array($value) && $value === array_values($value)) {
                $criteria[] = new FilterCriterion(
                    $field,
                    'in',
                    array_values(array_map(static fn ($v): string => \is_scalar($v) ? (string) $v : '', $value)),
                );
                continue;
            }

            $scalar = \is_scalar($value) ? (string) $value : '';
            $criteria[] = new FilterCriterion(
                $field,
                self::mapComparison($comparison, 'eq'),
                [$scalar],
            );
        }

        return $criteria;
    }

    /**
     * Map a raw operator from the request to a Polysource canonical
     * name. Unknown operators fall back to `$default` rather than
     * passing through unchecked — passthrough would let a hostile
     * client persist a criterion with an arbitrary operator string
     * that downstream data sources would have to defensively reject.
     * Hardened in v0.9.0 per architectural audit.
     */
    private static function mapComparison(string $comparison, string $default): string
    {
        return match ($comparison) {
            '=', 'eq' => 'eq',
            '!=', '<>', 'neq' => 'neq',
            '>', 'gt' => 'gt',
            '>=', 'gte' => 'gte',
            '<', 'lt' => 'lt',
            '<=', 'lte' => 'lte',
            'like', 'like*', '*like', 'not like' => 'like',
            'in', 'not in' => 'in',
            'between' => 'between',
            default => $default,
        };
    }
}
