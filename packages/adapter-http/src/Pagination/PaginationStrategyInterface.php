<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Pagination;

use Polysource\Core\Query\DataQuery;

/**
 * Maps Polysource's {@see DataQuery} pagination + filter / sort
 * intent into HTTP request parameters, then parses the response
 * into the canonical shape the data source consumes.
 *
 * REST APIs differ wildly in their pagination conventions
 * (page-based, cursor-based, offset, RFC 5988 Link header). Hosts
 * pick a strategy per endpoint.
 *
 * @phpstan-type ParsedResponse array{items: list<array<string, mixed>>, total: ?int, nextCursor: ?string, prevCursor: ?string}
 */
interface PaginationStrategyInterface
{
    /**
     * Build the query parameters to add to the request URI.
     *
     * @return array<string, scalar|list<scalar>>
     */
    public function buildQueryParams(DataQuery $query): array;

    /**
     * Parse the JSON-decoded response body + response headers into
     * the canonical shape the data source consumes.
     *
     * @param array<string, mixed>|list<mixed> $body
     * @param array<string, list<string>>      $headers normalised lowercase keys
     *
     * @return ParsedResponse
     */
    public function parseResponse(array $body, array $headers): array;
}
