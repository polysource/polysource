<?php

declare(strict_types=1);

namespace Polysource\Search\Search;

use Polysource\Bundle\Routing\PolysourceUrlGenerator;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\Pagination;
use Polysource\Core\Resource\ResourceInterface;

/**
 * Default {@see SearchProviderInterface} that wraps any Polysource
 * resource: delegates `searchText` to the underlying
 * `DataSource::search()`.
 *
 * Each adapter knows what "matching a text" means in its own
 * universe (LIKE in SQL, fulltext in Doctrine, native search in
 * Meilisearch). We never re-implement matching here.
 *
 * Hosts shipping a custom backend (Meilisearch, Algolia,
 * Elasticsearch) ship their own `SearchProviderInterface` impl
 * tagged `polysource.search.provider` *in addition to* (or *instead
 * of*) the default ResourceSearchProvider.
 */
final class ResourceSearchProvider implements SearchProviderInterface
{
    public function __construct(
        private readonly ResourceInterface $resource,
        private readonly PolysourceUrlGenerator $urls,
    ) {
    }

    public function getId(): string
    {
        return 'resource:' . $this->resource->getName();
    }

    public function getLabel(): string
    {
        return $this->resource->getLabel();
    }

    public function search(string $query, int $limit, float $deadline): array
    {
        $page = $this->resource->getDataSource()->search(
            (new DataQuery($this->resource->getName()))
                ->withSearchText($query)
                ->withPagination(new Pagination(0, $limit)),
        );

        $results = [];
        foreach ($page->items as $record) {
            if (microtime(true) > $deadline) {
                break;
            }
            $results[] = new SearchResult(
                id: $this->resource->getName() . ':' . (string) $record->identifier,
                label: $this->labelFor($record),
                href: $this->urls->detail($this->resource->getName(), $record->identifier),
                resourceName: $this->resource->getLabel(),
            );
        }

        return $results;
    }

    /**
     * Pick the first non-empty string property as the user-facing
     * label, fallback to the identifier. Hosts that need richer
     * labels ship their own provider.
     */
    private function labelFor(DataRecord $record): string
    {
        foreach ($record->properties as $value) {
            if (\is_string($value) && '' !== $value) {
                return $value;
            }
        }

        return (string) $record->identifier;
    }
}
