<?php

declare(strict_types=1);

namespace Polysource\Adapter\Http\Tests\Unit\DataSource;

use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Http\DataSource\HttpDataSource;
use Polysource\Adapter\Http\Pagination\CursorPaginationStrategy;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\Pagination;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpDataSourceTest extends TestCase
{
    public function testSearchSendsPageParamsAndMapsResponse(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = ['method' => $method, 'url' => $url, 'options' => $options];

            return new MockResponse((string) json_encode([
                'data' => [
                    ['id' => 1, 'name' => 'Alice'],
                    ['id' => 2, 'name' => 'Bob'],
                ],
                'meta' => ['total' => 12],
            ]), ['response_headers' => ['Content-Type: application/json']]);
        });

        $source = new HttpDataSource($client, 'https://api.example.com/users');

        $page = $source->search(
            (new DataQuery('users'))->withPagination(new Pagination(offset: 0, limit: 25))
        );

        self::assertSame(12, $page->total);
        $items = $page->asArray();
        self::assertCount(2, $items);
        self::assertSame(1, $items[0]->identifier);
        self::assertSame('Alice', $items[0]->properties['name']);
        self::assertSame('GET', $captured[0]['method']);
        self::assertStringContainsString('page=1', $captured[0]['url']);
        self::assertStringContainsString('per_page=25', $captured[0]['url']);
    }

    public function testFilterEqMappedToQueryParam(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = $url;

            return new MockResponse((string) json_encode(['data' => [], 'meta' => ['total' => 0]]));
        });

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        $source->search(
            (new DataQuery('users'))
                ->withFilter('role', new FilterCriterion('role', 'eq', 'admin'))
        );

        self::assertStringContainsString('role=admin', $captured[0]);
    }

    public function testSearchTextMappedToQParam(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = $url;

            return new MockResponse((string) json_encode(['data' => [], 'meta' => ['total' => 0]]));
        });

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        $source->search((new DataQuery('users'))->withSearchText('alice'));

        self::assertStringContainsString('q=alice', $captured[0]);
    }

    public function testFindReturnsRecord(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse((string) json_encode(['id' => 42, 'name' => 'Carol'])));

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        $record = $source->find(42);

        self::assertNotNull($record);
        self::assertSame(42, $record->identifier);
        self::assertSame('Carol', $record->properties['name']);
    }

    public function testFindReturnsNullOn404(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('Not Found', ['http_code' => 404]));

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        self::assertNull($source->find(999));
    }

    public function testCursorStrategyExposesNextCursor(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse((string) json_encode([
            'data' => [['id' => 'tx_1'], ['id' => 'tx_2']],
            'next_cursor' => 'tx_3',
        ])));

        $source = new HttpDataSource(
            $client,
            'https://api.example.com/transactions',
            new CursorPaginationStrategy(),
        );

        $page = $source->search(new DataQuery('transactions'));
        self::assertSame('tx_3', $page->nextCursor);
        self::assertNull($page->total);
    }

    public function testCreateSendsPostAndReturnsRecord(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = ['method' => $method, 'body' => $options['body'] ?? null];

            return new MockResponse((string) json_encode(['id' => 99, 'name' => 'Created']), ['http_code' => 201]);
        });

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        $record = $source->create(new DataPayload(['name' => 'Created']));

        self::assertSame(99, $record->identifier);
        self::assertSame('POST', $captured[0]['method']);
        self::assertSame('{"name":"Created"}', $captured[0]['body']);
    }

    public function testUpdateSendsPatchAndReturnsRecord(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = ['method' => $method, 'url' => $url];

            return new MockResponse((string) json_encode(['id' => 7, 'name' => 'Renamed']));
        });

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        $record = $source->update(7, new DataPayload(['name' => 'Renamed']));

        self::assertSame('PATCH', $captured[0]['method']);
        self::assertStringContainsString('/users/7', $captured[0]['url']);
        self::assertSame('Renamed', $record->properties['name']);
    }

    public function testCreateThrowsOnHttpError(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('{"error":"validation failed"}', ['http_code' => 422]));

        $source = new HttpDataSource($client, 'https://api.example.com/users');

        $this->expectException(RuntimeException::class);
        $source->create(new DataPayload(['name' => '']));
    }

    public function testDeleteAcceptsBoth204And404(): void
    {
        $callCount = 0;
        $client = new MockHttpClient(static function () use (&$callCount): MockResponse {
            ++$callCount;

            return 1 === $callCount
                ? new MockResponse('', ['http_code' => 204])
                : new MockResponse('Not Found', ['http_code' => 404]);
        });

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        $source->delete(1); // 204
        $source->delete(2); // 404 — must not throw (idempotent convention)

        self::assertSame(2, $callCount);
    }

    public function testDeleteThrowsOnNon404Error(): void
    {
        $client = new MockHttpClient(static fn (): MockResponse => new MockResponse('Server error', ['http_code' => 500]));

        $source = new HttpDataSource($client, 'https://api.example.com/users');
        $this->expectException(RuntimeException::class);
        $source->delete(1);
    }

    public function testDefaultHeadersAreSent(): void
    {
        $captured = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$captured): MockResponse {
            $captured[] = $options['headers'] ?? [];

            return new MockResponse((string) json_encode(['data' => [], 'meta' => ['total' => 0]]));
        });

        $source = new HttpDataSource(
            $client,
            'https://api.example.com/users',
            defaultHeaders: ['Authorization' => 'Bearer secret'],
        );
        $source->search(new DataQuery('users'));

        // Symfony normalises headers into "Name: Value" strings.
        /** @var array<int|string, mixed> $headers */
        $headers = $captured[0];
        $serialised = implode("\n", array_map(
            static fn ($h): string => \is_array($h) ? implode("\n", array_map(self::stringify(...), $h)) : self::stringify($h),
            $headers,
        ));
        self::assertStringContainsString('Authorization: Bearer secret', $serialised);
    }

    private static function stringify(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;
        }
        if (\is_scalar($value) || (\is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return '';
    }
}
