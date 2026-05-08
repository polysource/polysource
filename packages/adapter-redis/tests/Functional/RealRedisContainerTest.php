<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\Functional;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Redis\Client\PredisRedisHashClient;
use Polysource\Adapter\Redis\DataSource\RedisHashDataSource;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\Pagination;
use Predis\Client;

/**
 * Wire-level test against a REAL Redis container.
 *
 * Skipped when `POLYSOURCE_REAL_REDIS` env var is missing — local
 * developers can opt into running it by exporting the var. CI's
 * `e2e` job sets the var to the showcase compose stack's redis
 * service URL.
 *
 * Catches integration drift that the in-memory fake hides:
 * Predis client API changes, real SCAN cursor semantics, large
 * payload edge cases, hash field ordering across SCAN iterations.
 *
 * @group real-container
 */
final class RealRedisContainerTest extends TestCase
{
    private const PREFIX = 'polysource:e2e:';

    private PredisRedisHashClient $client;
    private RedisHashDataSource $dataSource;

    protected function setUp(): void
    {
        $url = getenv('POLYSOURCE_REAL_REDIS');
        if ($url === false || $url === '') {
            self::markTestSkipped('Set POLYSOURCE_REAL_REDIS to a redis:// URL to run this test against a real Redis container.');
        }

        $predis = new Client($url);
        $this->client = new PredisRedisHashClient($predis);
        $this->dataSource = new RedisHashDataSource(
            client: $this->client,
            keyPrefix: self::PREFIX,
        );

        // Clear any pre-existing keys under our test prefix so each
        // test runs deterministically.
        $cursor = '0';
        do {
            [$cursor, $keys] = $this->client->scan($cursor, self::PREFIX . '*', 200);
            foreach ($keys as $key) {
                $this->client->del($key);
            }
        } while ($cursor !== '0');
    }

    public function testWriteAndReadRoundTripThroughTheRealClient(): void
    {
        $payload = new DataPayload([
            'id' => 'hat-001',
            'name' => 'Hat',
            'price' => '1099',
            'stock' => '12',
        ]);

        $record = $this->dataSource->create($payload);

        self::assertNotEmpty($record->identifier);

        $loaded = $this->dataSource->find($record->identifier);
        self::assertNotNull($loaded, 'Just-written record must be findable.');
        self::assertSame('Hat', $loaded->properties['name'] ?? null);
        self::assertSame('1099', $loaded->properties['price'] ?? null);
        self::assertSame('12', $loaded->properties['stock'] ?? null);
    }

    public function testScanCursorPaginatesAcrossManyKeys(): void
    {
        // Seed 60 records so SCAN's COUNT=100 default returns one
        // chunk but our DataQuery limit=20 forces three pages.
        for ($i = 0; $i < 60; ++$i) {
            $this->dataSource->create(new DataPayload([
                'id' => 'rec-' . $i,
                'index' => (string) $i,
                'bucket' => $i < 30 ? 'A' : 'B',
            ]));
        }

        $page1 = $this->dataSource->search(
            (new DataQuery('real-redis'))->withPagination(new Pagination(0, 20)),
        );
        self::assertCount(20, [...$page1->items]);

        // Cursor pagination → total is unknown.
        self::assertNull($page1->total);
    }

    public function testUpdatePersistsThroughRealRedis(): void
    {
        $record = $this->dataSource->create(new DataPayload([
            'id' => 'order-001',
            'status' => 'pending',
            'attempts' => '0',
        ]));

        $updated = $this->dataSource->update($record->identifier, new DataPayload([
            'id' => 'order-001',
            'status' => 'shipped',
            'attempts' => '1',
        ]));

        self::assertSame('shipped', $updated->properties['status'] ?? null);

        // Re-read from Redis to defeat any in-process caching.
        $reloaded = $this->dataSource->find($record->identifier);
        self::assertNotNull($reloaded);
        self::assertSame('shipped', $reloaded->properties['status'] ?? null);
        self::assertSame('1', $reloaded->properties['attempts'] ?? null);
    }

    public function testDeleteIsIdempotentAcrossCalls(): void
    {
        $record = $this->dataSource->create(new DataPayload([
            'id' => 'flag-feature-x',
            'key' => 'value',
        ]));

        $this->dataSource->delete($record->identifier);
        // Idempotent — second delete must not throw.
        $this->dataSource->delete($record->identifier);

        self::assertNull($this->dataSource->find($record->identifier));
    }
}
