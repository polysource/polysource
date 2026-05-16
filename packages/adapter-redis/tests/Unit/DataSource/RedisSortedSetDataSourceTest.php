<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\Unit\DataSource;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Redis\DataSource\RedisSortedSetDataSource;
use Polysource\Adapter\Redis\Tests\InMemory\InMemoryRedisClient;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;

#[CoversClass(RedisSortedSetDataSource::class)]
final class RedisSortedSetDataSourceTest extends TestCase
{
    private InMemoryRedisClient $client;
    private RedisSortedSetDataSource $source;

    protected function setUp(): void
    {
        $this->client = new InMemoryRedisClient();
        $this->source = new RedisSortedSetDataSource($this->client, 'leaderboard:');
    }

    #[Test]
    public function listsOnlyZsetKeysWithinPrefix(): void
    {
        $this->client->seedZset('leaderboard:global', ['alice' => 100.0, 'bob' => 50.0, 'carol' => 75.0]);
        $this->client->seedZset('leaderboard:weekly', ['dora' => 10.0]);
        $this->client->seedSet('leaderboard:tags', ['shooter', 'puzzle']);  // ← skip

        $page = $this->source->search(new DataQuery('leaderboards'));

        self::assertSame(2, $page->total);
        $items = $page->asArray();

        $global = $items[0];
        self::assertSame('global', $global->identifier);
        self::assertSame(3, $global->properties['cardinality']);
        // zrange ascending by score: bob (50) → carol (75) → alice (100)
        self::assertSame(['bob' => 50.0, 'carol' => 75.0, 'alice' => 100.0], $global->properties['topMembers']);
    }

    #[Test]
    public function topLimitTruncates(): void
    {
        $source = new RedisSortedSetDataSource($this->client, 'leaderboard:', topLimit: 2);
        $this->client->seedZset('leaderboard:big', ['a' => 1.0, 'b' => 2.0, 'c' => 3.0]);

        $record = $source->find('big');

        self::assertNotNull($record);
        self::assertSame(3, $record->properties['cardinality']);
        $top = $record->properties['topMembers'];
        self::assertIsArray($top);
        self::assertCount(2, $top);
    }

    #[Test]
    public function createZaddsScoredMembers(): void
    {
        $record = $this->source->create(new DataPayload([
            'id' => 'new',
            'scoredMembers' => ['alice' => 42.0, 'bob' => 17.0],
        ]));

        self::assertSame(2, $record->properties['cardinality']);
        self::assertSame(['bob' => 17.0, 'alice' => 42.0], $this->client->zrange('leaderboard:new', 0, -1));
    }

    #[Test]
    public function updateZaddsAdditive(): void
    {
        $this->client->seedZset('leaderboard:room', ['a' => 5.0]);

        $record = $this->source->update('room', new DataPayload([
            'scoredMembers' => ['b' => 10.0, 'a' => 7.0],  // 'a' score bumped
        ]));

        self::assertSame(2, $record->properties['cardinality']);
        self::assertSame(['a' => 7.0, 'b' => 10.0], $this->client->zrange('leaderboard:room', 0, -1));
    }

    #[Test]
    public function refusesNonNumericScore(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->source->create(new DataPayload([
            'id' => 'bad',
            'scoredMembers' => ['alice' => 'not-a-number'],
        ]));
    }
}
