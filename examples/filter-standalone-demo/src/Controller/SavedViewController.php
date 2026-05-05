<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Controller;

use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Uid\Uuid;

/**
 * Receives the "Save current as view" form submitted from the
 * dropdown's modal, plus a delete endpoint.
 *
 * Routes referenced by the bundled
 * `@PolysourceFilter/saved_view/save_modal.html.twig` template:
 *  - polysource_saved_view_create  (POST /saved-views?resource=…)
 *  - polysource_saved_view_delete  (POST /saved-views/{id}/delete)
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
        if (null === $user) {
            throw $this->createAccessDeniedException();
        }

        $resource = (string) $request->query->get('resource', 'products');
        $name = trim((string) $request->request->get('name', ''));
        $scopeRaw = (string) $request->request->get('scope', 'private');
        $filterQs = (string) $request->request->get('filter_querystring', '');

        if ('' === $name) {
            $this->addFlash('warning', 'Saved view requires a non-empty name.');

            return $this->redirectToRoute('products');
        }

        // Decode the live filter URL into a FilterCollection.
        parse_str($filterQs, $parsed);
        $filterRaw = (array) ($parsed['filter'] ?? []);
        $criteria = $this->buildCriteria($filterRaw);

        $view = new SavedView(
            id: Uuid::v7()->toRfc4122(),
            name: $name,
            resourceName: $resource,
            ownerId: $user->getUserIdentifier(),
            scope: SavedViewScope::tryFrom($scopeRaw) ?? SavedViewScope::PRIVATE,
            filters: new FilterCollection($resource, $criteria),
        );

        $this->service->save($view);
        $this->addFlash('success', \sprintf('View "%s" saved.', $name));

        return $this->redirectToRoute('products', ['view' => $view->id]);
    }

    #[Route('/saved-views/{id}/delete', name: 'polysource_saved_view_delete', methods: ['POST'])]
    public function delete(string $id): RedirectResponse
    {
        $this->service->delete($id);
        $this->addFlash('success', 'View deleted.');

        return $this->redirectToRoute('products');
    }

    /**
     * Mirrors the URL-shape decoding done by ProductController::list().
     *
     * @param array<string, mixed> $raw
     *
     * @return list<FilterCriterion>
     */
    private function buildCriteria(array $raw): array
    {
        $criteria = [];

        $category = $raw['category'] ?? [];
        if (\is_array($category) && [] !== $category) {
            $criteria[] = new FilterCriterion('category', 'in', array_values($category));
        }

        $price = $raw['price'] ?? [];
        if (\is_array($price)) {
            $min = (string) ($price['min'] ?? '');
            $max = (string) ($price['max'] ?? '');
            if ('' !== $min || '' !== $max) {
                $criteria[] = new FilterCriterion('price', 'between', [$min, $max]);
            }
        }

        $releasedAt = (string) ($raw['releasedAt'] ?? '');
        if ('' !== $releasedAt) {
            $criteria[] = new FilterCriterion('releasedAt', 'gte', [$releasedAt]);
        }

        $availability = $raw['isAvailable'] ?? null;
        if (\is_string($availability) && '' !== $availability) {
            $criteria[] = new FilterCriterion('isAvailable', 'eq', [$availability]);
        }

        return $criteria;
    }
}
