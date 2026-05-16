<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\Unit\DataSource;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Redis\DataSource\RedisListDataSource;
use Polysource\Adapter\Redis\Tests\InMemory\InMemoryRedisClient;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;

#[CoversClass(RedisListDataSource::class)]
final class RedisListDataSourceTest extends TestCase
{
    private InMemoryRedisClient $client;
    private RedisListDataSource $source;

    protected function setUp(): void
    {
        $this->client = new InMemoryRedisClient();
        $this->source = new RedisListDataSource($this->client, 'queue:');
    }

    #[Test]
    public function listsOnlyListKeysWithinPrefix(): void
    {
        $this->client->seedList('queue:jobs', ['j1', 'j2', 'j3']);
        $this->client->seedList('queue:retries', ['r1']);
        $this->client->seedString('queue:counter', '7');         // ← skip
        $this->client->seedHash('queue:meta', ['v' => '1']);     // ← skip

        $page = $this->source->search(new DataQuery('queues'));

        self::assertSame(2, $page->total);
        $items = $page->asArray();
        self::assertSame('jobs', $items[0]->identifier);
        self::assertSame(3, $items[0]->properties['length']);
        self::assertSame(['j1', 'j2', 'j3'], $items[0]->properties['head']);
        self::assertSame("j1\nj2\nj3", $items[0]->properties['headPreview']);
    }

    #[Test]
    public function headLimitTruncatesPreview(): void
    {
        $source = new RedisListDataSource($this->client, 'queue:', headLimit: 2);
        $this->client->seedList('queue:big', ['a', 'b', 'c', 'd', 'e']);

        $record = $source->find('big');

        self::assertNotNull($record);
        self::assertSame(5, $record->properties['length']);
        self::assertSame(['a', 'b'], $record->properties['head']);
    }

    #[Test]
    public function createRpushesItems(): void
    {
        $record = $this->source->create(new DataPayload(['id' => 'new', 'items' => ['x', 'y']]));

        self::assertSame('new', $record->identifier);
        self::assertSame(2, $record->properties['length']);
        self::assertSame(['x', 'y'], $this->client->lrange('queue:new', 0, -1));
    }

    #[Test]
    public function updateAppendsItems(): void
    {
        $this->client->seedList('queue:existing', ['a']);

        $record = $this->source->update('existing', new DataPayload(['items' => ['b', 'c']]));

        self::assertSame(3, $record->properties['length']);
        self::assertSame(['a', 'b', 'c'], $this->client->lrange('queue:existing', 0, -1));
    }

    #[Test]
    public function deleteDropsTheList(): void
    {
        $this->client->seedList('queue:gone', ['x']);

        $this->source->delete('gone');

        self::assertNull($this->source->find('gone'));
    }

    #[Test]
    public function createRefusesEmptyItemsList(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->source->create(new DataPayload(['id' => 'k', 'items' => []]));
    }
}
