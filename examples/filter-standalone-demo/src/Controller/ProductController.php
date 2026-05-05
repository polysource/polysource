<?php

declare(strict_types=1);

namespace Polysource\Demo\FilterStandalone\Controller;

use Polysource\Demo\FilterStandalone\Filter\ProductFilterApplier;
use Polysource\Demo\FilterStandalone\Filter\ProductFilters;
use Polysource\Demo\FilterStandalone\Repository\InMemoryProductRepository;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\SavedViewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Single-route demo controller.
 *
 * Builds a FilterCollection from URL query params (no Symfony Form
 * for v1 — keep the demo legible), applies it via
 * ProductFilterApplier, then renders:
 *   - the filter form (plain HTML inputs, GET submission)
 *   - the chips bar via filter_tags() Twig function
 *   - the resulting product list
 *
 * URL shape:
 *   /?filter[category][]=Tech&filter[category][]=Books
 *    &filter[price][min]=50&filter[price][max]=200
 *    &filter[releasedAt]=2024-01-01
 *    &filter[isAvailable]=1
 */
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly InMemoryProductRepository $repository,
        private readonly ProductFilterApplier $applier,
        private readonly SavedViewService $savedViews,
    ) {
    }

    public function list(Request $request): Response|RedirectResponse
    {
        // If `?view=<id>` is present, load the saved view and replay
        // its filters as URL params — gives us shareable links AND
        // hydrates the form inputs naturally on the next request.
        $viewId = (string) $request->query->get('view', '');
        if ('' !== $viewId) {
            $view = $this->savedViews->load($viewId);
            if (null !== $view) {
                return new RedirectResponse(
                    $request->getPathInfo() . '?' . $this->serializeFilters($view->filters),
                );
            }
        }

        /** @var array<string, mixed> $raw */
        $raw = $request->query->all('filter');
        $collection = $this->buildCollection($raw);

        $products = $this->applier->apply($this->repository->findAll(), $collection);

        return $this->render('product/list.html.twig', [
            'products' => $products,
            'collection' => $collection,
            'definitions' => ProductFilters::all(),
            'filterValues' => $raw,
        ]);
    }

    /**
     * Serialises a FilterCollection back into the `?filter[name]=...`
     * URL shape the controller already understands. Round-trips
     * cleanly with $request->query->all('filter').
     */
    private function serializeFilters(FilterCollection $collection): string
    {
        $payload = ['filter' => []];
        foreach ($collection as $criterion) {
            switch ($criterion->property) {
                case 'category':
                    $payload['filter']['category'] = array_values($criterion->values);
                    break;
                case 'price':
                    $payload['filter']['price'] = [
                        'min' => (string) ($criterion->values[0] ?? ''),
                        'max' => (string) ($criterion->values[1] ?? ''),
                    ];
                    break;
                case 'releasedAt':
                    $payload['filter']['releasedAt'] = (string) ($criterion->values[0] ?? '');
                    break;
                case 'isAvailable':
                    $payload['filter']['isAvailable'] = (string) ($criterion->values[0] ?? '');
                    break;
            }
        }

        return http_build_query($payload);
    }

    /**
     * Translates raw URL query into typed `FilterCriterion`
     * instances. Skips empty values so the chips bar stays clean
     * when a user clears one input.
     *
     * @param array<string, mixed> $raw
     */
    private function buildCollection(array $raw): FilterCollection
    {
        $criteria = [];

        $category = $raw['category'] ?? [];
        if (\is_array($category) && [] !== $category) {
            $criteria[] = new FilterCriterion('category', 'in', array_values($category));
        }

        $price = $raw['price'] ?? [];
        if (\is_array($price) && (isset($price['min']) || isset($price['max']))) {
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

        return new FilterCollection('products', $criteria);
    }
}
