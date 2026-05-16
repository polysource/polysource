<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\Unit\DataSource;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Redis\DataSource\RedisSetDataSource;
use Polysource\Adapter\Redis\Tests\InMemory\InMemoryRedisClient;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;

#[CoversClass(RedisSetDataSource::class)]
final class RedisSetDataSourceTest extends TestCase
{
    private InMemoryRedisClient $client;
    private RedisSetDataSource $source;

    protected function setUp(): void
    {
        $this->client = new InMemoryRedisClient();
        $this->source = new RedisSetDataSource($this->client, 'online:');
    }

    #[Test]
    public function listsOnlySetKeysWithinPrefix(): void
    {
        $this->client->seedSet('online:europe', ['alice', 'bob', 'carol']);
        $this->client->seedSet('online:asia', ['dora']);
        $this->client->seedString('online:total', '4');           // ← skip

        $page = $this->source->search(new DataQuery('online'));

        self::assertSame(2, $page->total);
        $items = $page->asArray();
        self::assertSame('asia', $items[0]->identifier);
        self::assertSame(1, $items[0]->properties['cardinality']);
        self::assertSame('europe', $items[1]->identifier);
        self::assertSame(3, $items[1]->properties['cardinality']);
        self::assertSame(['alice', 'bob', 'carol'], $items[1]->properties['members']);
    }

    #[Test]
    public function createSaddsMembers(): void
    {
        $record = $this->source->create(new DataPayload(['id' => 'newroom', 'members' => ['x', 'y']]));

        self::assertSame(2, $record->properties['cardinality']);
        self::assertSame(['x', 'y'], $this->client->smembers('online:newroom'));
    }

    #[Test]
    public function updateIsAdditive(): void
    {
        $this->client->seedSet('online:room', ['a']);

        $record = $this->source->update('room', new DataPayload(['members' => ['b', 'a']]));

        // 'a' was already there — set semantics dedupe it.
        self::assertSame(2, $record->properties['cardinality']);
    }

    #[Test]
    public function deleteDropsTheSet(): void
    {
        $this->client->seedSet('online:gone', ['x']);

        $this->source->delete('gone');

        self::assertNull($this->source->find('gone'));
    }

    #[Test]
    public function findReturnsNullForWrongType(): void
    {
        $this->client->seedList('online:badtype', ['x']);

        self::assertNull($this->source->find('badtype'));
    }
}
