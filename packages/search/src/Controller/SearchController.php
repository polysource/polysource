<?php

declare(strict_types=1);

namespace Polysource\Search\Controller;

use Polysource\Search\Search\SearchAggregator;
use Polysource\Search\Search\SearchResult;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Cmd+K palette JSON endpoint.
 *
 * Route: GET /admin/search?q=…
 *
 * Returns `{query, results: [{id, label, href, resource, icon, hint}]}`.
 *
 * JSON-only by design: the palette is rendered client-side by
 * Stimulus, the server only ships data. Hosts that want a server-
 * rendered fallback wire their own route + use
 * `polysource_search_palette()` Twig.
 */
final class SearchController
{
    public function __construct(private readonly SearchAggregator $aggregator)
    {
    }

    #[Route('/admin/search', name: 'polysource_search', methods: ['GET'])]
    public function __invoke(Request $request): JsonResponse
    {
        $query = (string) $request->query->get('q', '');
        $results = $this->aggregator->aggregate($query);

        return new JsonResponse([
            'query' => $query,
            'results' => array_map(
                static fn (SearchResult $r): array => [
                    'id' => $r->id,
                    'label' => $r->label,
                    'href' => $r->href,
                    'resource' => $r->resourceName,
                    'icon' => $r->icon,
                    'hint' => $r->hint,
                ],
                $results,
            ),
        ]);
    }
}
