<?php

declare(strict_types=1);

namespace App\Controller;

use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Exception\SavedViewDuplicateNameException;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Receives the "Save current as view" form submitted by polysource/filter's
 * bundled save_modal. Two routes referenced by the bundle templates:
 *  - polysource_saved_view_create  (POST /saved-views)
 *  - polysource_saved_view_delete  (POST /saved-views/{id}/delete)
 *
 * Phase G replaces the bare redirect with proper EA referer handling.
 */
final class SavedViewController extends AbstractController
{
    public function __construct(
        private readonly SavedViewService $service,
        private readonly Security $security,
    ) {
    }

    #[Route('/saved-views', name: 'polysource_saved_view_create', methods: ['POST'])]
    public function create(Request $request): RedirectResponse
    {
        $user = $this->security->getUser();
        if ($user === null) {
            throw $this->createAccessDeniedException();
        }

        $resource = (string) $request->query->get('resource', 'orders');
        $name = trim((string) $request->request->get('name', ''));
        $scopeRaw = (string) $request->request->get('scope', 'private');
        $filterQs = (string) $request->request->get('filter_querystring', '');

        if ($name === '') {
            $this->addFlash('warning', 'Saved view requires a non-empty name.');

            return $this->redirectToReferrer($request);
        }

        parse_str($filterQs, $parsed);
        $filterRaw = (array) ($parsed['filter'] ?? []);
        $criteria = $this->buildCriteria($filterRaw);

        if ($criteria === []) {
            $this->addFlash('warning', 'Apply at least one filter before saving a view.');

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
            $this->addFlash('success', sprintf('View "%s" saved.', $name));
        } catch (SavedViewDuplicateNameException $e) {
            $this->addFlash('warning', sprintf('A view named "%s" already exists.', $e->name));
        }

        return $this->redirectToReferrer($request);
    }

    #[Route('/saved-views/{id}/delete', name: 'polysource_saved_view_delete', methods: ['POST'])]
    public function delete(string $id, Request $request): RedirectResponse
    {
        $this->service->delete($id);
        $this->addFlash('success', 'View deleted.');

        return $this->redirectToReferrer($request);
    }

    private function redirectToReferrer(Request $request): RedirectResponse
    {
        $referrer = (string) $request->headers->get('referer', '/admin');

        return new RedirectResponse($referrer !== '' ? $referrer : '/admin');
    }

    /**
     * Generic decoder for filter querystrings produced by the EA filter bridge.
     *
     * @param array<string, mixed> $raw
     *
     * @return list<FilterCriterion>
     */
    private function buildCriteria(array $raw): array
    {
        $criteria = [];

        foreach ($raw as $field => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if (\is_array($value)) {
                if ($value === array_values($value)) {
                    // Indexed list → "in" filter.
                    $criteria[] = new FilterCriterion((string) $field, 'in', array_map('strval', $value));
                    continue;
                }

                $min = (string) ($value['min'] ?? $value['from'] ?? '');
                $max = (string) ($value['max'] ?? $value['to'] ?? '');
                if ($min !== '' || $max !== '') {
                    $criteria[] = new FilterCriterion((string) $field, 'between', [$min, $max]);
                }
                continue;
            }

            $criteria[] = new FilterCriterion((string) $field, 'eq', [(string) $value]);
        }

        return $criteria;
    }
}
