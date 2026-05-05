<?php

declare(strict_types=1);

namespace Polysource\Search\Search;

/**
 * Cross-resource search contributor.
 *
 * Hosts tag custom providers `polysource.search.provider`. The
 * built-in {@see ResourceSearchProvider} is auto-registered for
 * every Polysource resource so the 80% case (search by
 * `DataSource::search()` with `searchText`) works out of the box.
 *
 * Future bridges (`polysource/search-meilisearch`,
 * `polysource/search-algolia`, …) ship as separate add-on
 * packages implementing this interface against their respective
 * backends.
 */
interface SearchProviderInterface
{
    /**
     * Stable id used in the URL `?provider=` filter and in
     * `polysource:plugins:list` introspection. Recommended:
     * `resource:<name>` for default Resource providers,
     * `meilisearch:<index>` for Meilisearch, etc.
     */
    public function getId(): string;

    /**
     * Display name in the palette grouping header.
     */
    public function getLabel(): string;

    /**
     * Return up to `$limit` results for `$query`. MUST honour
     * `$deadline` (microtime timestamp) — if reached before
     * producing results, return what was built so far rather than
     * blocking. Providers that throw are silently skipped by the
     * aggregator.
     *
     * @return list<SearchResult>
     */
    public function search(string $query, int $limit, float $deadline): array;
}
