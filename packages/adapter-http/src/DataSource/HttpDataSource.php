<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\DataSource;

use Polysource\Adapter\Http\Pagination\PageNumberPaginationStrategy;
use Polysource\Adapter\Http\Pagination\PaginationStrategyInterface;
use Polysource\Core\DataSource\WritableDataSourceInterface;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterOperator;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Read+write data source over a REST endpoint.
 *
 * Two collaborators decouple the diversity of REST conventions:
 *
 *  - {@see PaginationStrategyInterface} maps DataQuery → query
 *    params and parses the response envelope. Two strategies ship:
 *    {@see PageNumberPaginationStrategy} (the most common shape)
 *    and {@see \Polysource\Adapter\Http\Pagination\CursorPaginationStrategy}
 *    (Stripe / Twitter / GitHub v4).
 *  - The host's `identifierProperty` tells the data source which key
 *    in each row carries the primary id (defaults to `id`).
 *
 * Filter mapping in v0.1 is **passthrough**: each `FilterCriterion`
 * with operator `eq` becomes a query param `?<property>=<value>`.
 * Hosts that need richer mapping ship their own data source against
 * the same contract — same pattern as Doctrine / Redis.
 *
 * Cf. ADR-002 — count returns whatever the strategy parses (often
 * null for cursor APIs, an int for page-based ones).
 *
 * @phpstan-import-type ParsedResponse from PaginationStrategyInterface
 */
final class HttpDataSource implements WritableDataSourceInterface
{
    private readonly PaginationStrategyInterface $pagination;

    /**
     * @param array<string, string|list<string>> $defaultHeaders e.g. `['Authorization' => 'Bearer …']`
     */
    public function __construct(
        private readonly HttpClientInterface $client,
        private readonly string $baseUri,
        ?PaginationStrategyInterface $pagination = null,
        private readonly string $identifierProperty = 'id',
        private readonly array $defaultHeaders = [],
    ) {
        $this->pagination = $pagination ?? new PageNumberPaginationStrategy();
    }

    public function search(DataQuery $query): DataPage
    {
        $queryParams = $this->pagination->buildQueryParams($query);
        $queryParams = array_merge($queryParams, self::filtersToQuery($query));
        if (null !== $query->searchText && '' !== $query->searchText) {
            $queryParams['q'] = $query->searchText;
        }

        try {
            $response = $this->client->request('GET', $this->baseUri, [
                'query' => $queryParams,
                'headers' => $this->defaultHeaders,
            ]);
            /** @var array<string, mixed>|list<mixed> $body */
            $body = $response->toArray(throw: true);
            $headers = self::normaliseHeaders($response->getHeaders(throw: false));
        } catch (HttpClientExceptionInterface) {
            return new DataPage([], null);
        }

        /** @var ParsedResponse $parsed */
        $parsed = $this->pagination->parseResponse($body, $headers);

        $records = [];
        foreach ($parsed['items'] as $row) {
            $records[] = $this->toDataRecord($row);
        }

        return new DataPage(
            items: $records,
            total: $parsed['total'],
            nextCursor: $parsed['nextCursor'],
            prevCursor: $parsed['prevCursor'],
        );
    }

    public function find(int|string $identifier): ?DataRecord
    {
        try {
            $response = $this->client->request('GET', $this->baseUri . '/' . rawurlencode((string) $identifier), [
                'headers' => $this->defaultHeaders,
            ]);

            $statusCode = $response->getStatusCode();
            if (404 === $statusCode) {
                return null;
            }

            /** @var array<string, mixed> $body */
            $body = $response->toArray(throw: true);

            return $this->toDataRecord($body);
        } catch (HttpClientExceptionInterface) {
            return null;
        }
    }

    public function count(DataQuery $query): ?int
    {
        // Count is whatever the pagination strategy advertises after
        // executing a single search. We return the cached one from
        // the most recent search-call when available; otherwise
        // perform a search() and use its total. This is intentionally
        // simple for v0.1 — hosts who need an exact-but-cheap count
        // should hit a dedicated endpoint via a custom data source.
        return $this->search($query)->total;
    }

    public function create(DataPayload $payload): DataRecord
    {
        try {
            $response = $this->client->request('POST', $this->baseUri, [
                'json' => $payload->properties,
                'headers' => $this->defaultHeaders,
            ]);
            /** @var array<string, mixed> $body */
            $body = $response->toArray(throw: true);
        } catch (HttpExceptionInterface $e) {
            throw new RuntimeException(\sprintf('HttpDataSource: POST %s failed (%d): %s', $this->baseUri, $e->getResponse()->getStatusCode(), $e->getMessage()), 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('HttpDataSource: POST %s transport error: %s', $this->baseUri, $e->getMessage()), 0, $e);
        }

        return $this->toDataRecord($body);
    }

    public function update(int|string $identifier, DataPayload $payload): DataRecord
    {
        $url = $this->baseUri . '/' . rawurlencode((string) $identifier);
        try {
            $response = $this->client->request('PATCH', $url, [
                'json' => $payload->properties,
                'headers' => $this->defaultHeaders,
            ]);
            /** @var array<string, mixed> $body */
            $body = $response->toArray(throw: true);
        } catch (HttpExceptionInterface $e) {
            throw new RuntimeException(\sprintf('HttpDataSource: PATCH %s failed (%d): %s', $url, $e->getResponse()->getStatusCode(), $e->getMessage()), 0, $e);
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('HttpDataSource: PATCH %s transport error: %s', $url, $e->getMessage()), 0, $e);
        }

        return $this->toDataRecord($body);
    }

    public function delete(int|string $identifier): void
    {
        $url = $this->baseUri . '/' . rawurlencode((string) $identifier);
        try {
            $response = $this->client->request('DELETE', $url, [
                'headers' => $this->defaultHeaders,
            ]);
            $statusCode = $response->getStatusCode();
            if ($statusCode >= 400 && 404 !== $statusCode) {
                throw new RuntimeException(\sprintf('HttpDataSource: DELETE %s failed with status %d.', $url, $statusCode));
            }
        } catch (TransportExceptionInterface $e) {
            throw new RuntimeException(\sprintf('HttpDataSource: DELETE %s transport error: %s', $url, $e->getMessage()), 0, $e);
        }
        // 404 is treated as success — same idempotent convention as
        // Doctrine / Flysystem / Redis adapters.
    }

    /**
     * @param array<string, mixed> $row
     */
    private function toDataRecord(array $row): DataRecord
    {
        $rawId = $row[$this->identifierProperty] ?? null;
        if (\is_int($rawId) || \is_string($rawId)) {
            $identifier = $rawId;
        } elseif (\is_scalar($rawId) || (\is_object($rawId) && method_exists($rawId, '__toString'))) {
            $identifier = (string) $rawId;
        } else {
            $identifier = '';
        }

        return new DataRecord($identifier, $row);
    }

    /**
     * @return array<string, scalar|list<scalar>>
     */
    private static function filtersToQuery(DataQuery $query): array
    {
        $params = [];
        foreach ($query->filters as $criterion) {
            if (FilterOperator::Eq !== $criterion->operator) {
                continue; // v0.1: only `eq` maps cleanly to a passthrough query param
            }
            $value = $criterion->value;
            if (null === $value) {
                continue;
            }
            if (\is_scalar($value)) {
                $params[$criterion->property] = $value;
            }
        }

        return $params;
    }

    /**
     * @param array<string, list<string>|string> $headers
     *
     * @return array<string, list<string>>
     */
    private static function normaliseHeaders(array $headers): array
    {
        $normalised = [];
        foreach ($headers as $name => $values) {
            $normalised[strtolower($name)] = \is_array($values) ? array_values($values) : [$values];
        }

        return $normalised;
    }
}
