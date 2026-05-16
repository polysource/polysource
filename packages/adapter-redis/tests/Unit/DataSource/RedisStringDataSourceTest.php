<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\Unit\DataSource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Redis\DataSource\RedisStringDataSource;
use Polysource\Adapter\Redis\Tests\InMemory\InMemoryRedisClient;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Polysource\Core\Query\Pagination;

#[CoversClass(RedisStringDataSource::class)]
final class RedisStringDataSourceTest extends TestCase
{
    private InMemoryRedisClient $client;
    private RedisStringDataSource $source;

    protected function setUp(): void
    {
        $this->client = new InMemoryRedisClient();
        $this->source = new RedisStringDataSource($this->client, 'cache:');
    }

    #[Test]
    public function listsOnlyStringKeysWithinPrefix(): void
    {
        $this->client->seedString('cache:a', 'one');
        $this->client->seedString('cache:b', 'two');
        $this->client->seedHash('cache:hash', ['x' => '1']);    // ← type mismatch, must be skipped
        $this->client->seedList('cache:list', ['x', 'y']);      // ← type mismatch, must be skipped
        $this->client->seedString('other:c', 'three');          // ← prefix mismatch, must be skipped

        $page = $this->source->search(new DataQuery('cache'));

        self::assertSame(2, $page->total);
        $items = $page->asArray();
        self::assertSame('a', $items[0]->identifier);
        self::assertSame('one', $items[0]->properties['value']);
        self::assertSame('b', $items[1]->identifier);
    }

    #[Test]
    public function findReturnsNullForMissingOrNonStringKey(): void
    {
        $this->client->seedList('cache:queue', ['x']);
        $this->client->seedString('cache:k', 'v');

        self::assertNull($this->source->find('missing'));
        self::assertNull($this->source->find('queue'));      // wrong type
        self::assertNotNull($this->source->find('k'));
    }

    #[Test]
    public function createSetsTheStringValueAndOptionalTtl(): void
    {
        $record = $this->source->create(new DataPayload(['id' => 'k1', 'value' => 'v1']));

        self::assertSame('k1', $record->identifier);
        self::assertSame('v1', $record->properties['value']);
        self::assertSame(-1, $record->properties['ttl']);
        self::assertSame('v1', $this->client->get('cache:k1'));
    }

    #[Test]
    public function createHonoursTtl(): void
    {
        $record = $this->source->create(new DataPayload(['id' => 'k2', 'value' => 'v2', 'ttl' => 60]));

        self::assertSame(60, $record->properties['ttl']);
    }

    #[Test]
    public function deleteDropsTheKey(): void
    {
        $this->client->seedString('cache:gone', 'bye');

        $this->source->delete('gone');

        self::assertNull($this->source->find('gone'));
    }

    #[Test]
    public function paginatesAndFiltersByPattern(): void
    {
        for ($i = 1; $i <= 5; ++$i) {
            $this->client->seedString('cache:user-' . $i, 'data');
        }
        $this->client->seedString('cache:other', 'data');

        $query = (new DataQuery('cache'))
            ->withFilter('id', new FilterCriterion('id', FilterOperator::Like, 'user-'))
            ->withPagination(new Pagination(offset: 0, limit: 3));

        $page = $this->source->search($query);

        self::assertSame(5, $page->total);
        self::assertCount(3, $page->asArray());
    }
}
