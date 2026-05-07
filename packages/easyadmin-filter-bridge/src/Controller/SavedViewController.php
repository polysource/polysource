<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Controller;

use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Exception\SavedViewDuplicateNameException;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
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

        if ($criteria === []) {
            $this->flash($request, 'warning', 'Apply at least one filter before saving a view.');

            return $this->redirectToReferrer($request);
        }

        $view = new SavedView(
            id: Uuid::v7()->toRfc4122(),
            name: $name,
            resourceName: $resource,
            ownerId: $user->getUserIdentifier(),
            scope: SavedViewScope::tryFrom($scopeRaw) ?? SavedViewScope::PRIVATE,
            filters: new FilterCollection($resource, $criteria),
        );

        try {
            $this->service->save($view);
            $this->flash($request, 'success', \sprintf('View "%s" saved.', $name));
        } catch (SavedViewDuplicateNameException $e) {
            $this->flash($request, 'warning', \sprintf('A view named "%s" already exists.', $e->name));
        }

        return $this->redirectToReferrer($request);
    }

    #[Route('/admin/saved-views/{id}/delete', name: 'polysource_saved_view_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): RedirectResponse
    {
        $this->service->delete($id);
        $this->flash($request, 'success', 'View deleted.');

        return $this->redirectToReferrer($request);
    }

    private function redirectToReferrer(Request $request): RedirectResponse
    {
        $referrer = (string) $request->headers->get('referer', '/admin');

        return new RedirectResponse($referrer !== '' ? $referrer : '/admin');
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
            if (\is_array($value) && $value === array_values($value)) {
                $criteria[] = new FilterCriterion(
                    $field,
                    self::mapComparison($comparison, 'in'),
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

    private static function mapComparison(string $comparison, string $default): string
    {
        return match ($comparison) {
            '=' => 'eq',
            '!=', '<>' => 'neq',
            '>' => 'gt',
            '>=' => 'gte',
            '<' => 'lt',
            '<=' => 'lte',
            'like', 'like*', '*like', 'not like' => 'like',
            'in', 'not in' => 'in',
            '' => $default,
            default => $comparison,
        };
    }
}
