<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Tests\Functional;

use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Http\DataSource\HttpDataSource;
use Polysource\Adapter\Http\Pagination\PageNumberPaginationStrategy;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\Pagination;
use Symfony\Component\HttpClient\HttpClient;

/**
 * Wire-level test against a REAL WireMock container (HTTP stubs).
 *
 * Skipped when `POLYSOURCE_REAL_HTTP` is missing. CI's e2e job
 * sets the var to the showcase compose stack's WireMock service.
 *
 * Catches integration drift the MockHttpClient hides:
 * Symfony HttpClient API changes, real HTTP semantics around
 * query params + headers, real JSON parsing of response bodies.
 *
 * @group real-container
 */
final class RealHttpContainerTest extends TestCase
{
    private string $base;
    /** @var \Symfony\Contracts\HttpClient\HttpClientInterface */
    private $rawClient;

    protected function setUp(): void
    {
        $base = getenv('POLYSOURCE_REAL_HTTP');
        if ($base === false || $base === '') {
            self::markTestSkipped('Set POLYSOURCE_REAL_HTTP to a WireMock URL.');
        }
        $this->base = rtrim($base, '/');
        $this->rawClient = HttpClient::create();

        // Reset any previously-defined stubs so each test starts clean.
        $this->rawClient->request('DELETE', $this->base . '/__admin/mappings');
    }

    public function testSearchDecodesPaginatedJsonFromRealServer(): void
    {
        // Define a WireMock stub for `GET /products?page=1` returning
        // a 3-item page envelope.
        $this->stub([
            'request' => [
                'method' => 'GET',
                'urlPath' => '/products',
                'queryParameters' => [
                    'page' => ['equalTo' => '1'],
                ],
            ],
            'response' => [
                'status' => 200,
                'jsonBody' => [
                    'data' => [
                        ['id' => 'p1', 'name' => 'Hat'],
                        ['id' => 'p2', 'name' => 'Boots'],
                        ['id' => 'p3', 'name' => 'Scarf'],
                    ],
                    'meta' => [
                        'total' => 42,
                        'page' => 1,
                    ],
                ],
                'headers' => ['Content-Type' => 'application/json'],
            ],
        ]);

        $dataSource = new HttpDataSource(
            client: $this->rawClient,
            baseUri: $this->base . '/products',
            pagination: new PageNumberPaginationStrategy(),
        );

        $page = $dataSource->search(
            (new DataQuery('http-test'))
                ->withPagination(new Pagination(0, 20)),
        );

        $items = [...$page->items];
        self::assertCount(3, $items);
        self::assertSame('p1', $items[0]->identifier);
        self::assertSame('Hat', $items[0]->properties['name'] ?? null);
        self::assertSame(42, $page->total, 'PageNumberPaginationStrategy must read meta.total from the envelope.');
    }

    public function testFilterCriterionFlowsAsQueryParameter(): void
    {
        // Stub asserts the exact query string including the filter.
        $this->stub([
            'request' => [
                'method' => 'GET',
                'urlPath' => '/charges',
                'queryParameters' => [
                    'status' => ['equalTo' => 'paid'],
                ],
            ],
            'response' => [
                'status' => 200,
                'jsonBody' => [
                    'data' => [['id' => 'ch_001', 'status' => 'paid', 'amount' => 1099]],
                    'meta' => ['total' => 1, 'page' => 1],
                ],
            ],
        ]);

        $dataSource = new HttpDataSource(
            client: $this->rawClient,
            baseUri: $this->base . '/charges',
        );

        $query = (new DataQuery('http-test'))
            ->withFilter('status', new \Polysource\Core\Query\FilterCriterion('status', 'eq', 'paid'));

        $page = $dataSource->search($query);
        $items = [...$page->items];

        self::assertCount(1, $items, 'Real HTTP server must receive the filter and respond accordingly.');
        self::assertSame('ch_001', $items[0]->identifier);
    }

    public function testEmptyResponseFromRealServerProducesEmptyPage(): void
    {
        $this->stub([
            'request' => ['method' => 'GET', 'urlPath' => '/empty'],
            'response' => [
                'status' => 200,
                'jsonBody' => [
                    'data' => [],
                    'meta' => ['total' => 0, 'page' => 1],
                ],
            ],
        ]);

        $dataSource = new HttpDataSource(
            client: $this->rawClient,
            baseUri: $this->base . '/empty',
        );

        $page = $dataSource->search(new DataQuery('http-test'));
        self::assertCount(0, [...$page->items]);
        self::assertSame(0, $page->total);
    }

    /**
     * Register a WireMock stub mapping via its admin API.
     *
     * @param array<string, mixed> $mapping
     */
    private function stub(array $mapping): void
    {
        $response = $this->rawClient->request('POST', $this->base . '/__admin/mappings', [
            'json' => $mapping,
        ]);
        if ($response->getStatusCode() >= 300) {
            self::fail(\sprintf('WireMock rejected stub: %d %s', $response->getStatusCode(), $response->getContent(false)));
        }
    }
}
