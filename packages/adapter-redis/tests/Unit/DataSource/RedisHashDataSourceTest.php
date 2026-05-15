<?php

declare(strict_types=1);

namespace Polysource\Adapter\Redis\Tests\Unit\DataSource;

use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Redis\DataSource\RedisHashDataSource;
use Polysource\Adapter\Redis\Tests\InMemory\InMemoryRedisHashClient;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\FilterOperator;
use Polysource\Core\Query\Pagination;
use RuntimeException;

final class RedisHashDataSourceTest extends TestCase
{
    private InMemoryRedisHashClient $client;
    private RedisHashDataSource $source;

    protected function setUp(): void
    {
        $this->client = new InMemoryRedisHashClient();
        $this->source = new RedisHashDataSource($this->client, 'polysource:flag:');

        $this->client->seed('polysource:flag:beta-checkout', ['name' => 'beta-checkout', 'enabled' => '1', 'description' => 'New checkout flow']);
        $this->client->seed('polysource:flag:dark-mode', ['name' => 'dark-mode', 'enabled' => '1', 'description' => 'Toggle dark UI']);
        $this->client->seed('polysource:flag:legacy-pdf', ['name' => 'legacy-pdf', 'enabled' => '0', 'description' => 'Legacy PDF renderer']);
        $this->client->seed('other:unrelated', ['ignored' => '1']); // outside our prefix
    }

    public function testSearchReturnsHashesUnderPrefixOnly(): void
    {
        $page = $this->source->search(new DataQuery('flags'));
        $items = $page->asArray();

        self::assertCount(3, $items);
        $ids = array_map(static fn ($r) => $r->identifier, $items);
        sort($ids);
        self::assertSame(['beta-checkout', 'dark-mode', 'legacy-pdf'], $ids);
    }

    public function testCountIsAlwaysNullForCursorBasedSource(): void
    {
        self::assertNull($this->source->count(new DataQuery('flags')));
    }

    public function testFindReturnsHashRecord(): void
    {
        $record = $this->source->find('dark-mode');
        self::assertNotNull($record);
        self::assertSame('dark-mode', $record->identifier);
        self::assertSame('1', $record->properties['enabled']);
    }

    public function testFindReturnsNullForUnknownKey(): void
    {
        self::assertNull($this->source->find('does-not-exist'));
    }

    public function testFilterEqOnString(): void
    {
        $query = (new DataQuery('flags'))
            ->withFilter('enabled', new FilterCriterion('enabled', FilterOperator::Eq, '0'));

        $items = $this->source->search($query)->asArray();
        self::assertCount(1, $items);
        self::assertSame('legacy-pdf', $items[0]->identifier);
    }

    public function testFilterEqOnBoolCoercesStoredString(): void
    {
        $query = (new DataQuery('flags'))
            ->withFilter('enabled', new FilterCriterion('enabled', FilterOperator::Eq, true));

        $ids = array_map(static fn ($r) => $r->identifier, $this->source->search($query)->asArray());
        sort($ids);
        self::assertSame(['beta-checkout', 'dark-mode'], $ids);
    }

    public function testFilterInWhitelist(): void
    {
        $query = (new DataQuery('flags'))
            ->withFilter('name', new FilterCriterion('name', FilterOperator::In, ['beta-checkout', 'legacy-pdf']));

        self::assertCount(2, $this->source->search($query)->asArray());
    }

    public function testFilterLikeIsCaseInsensitive(): void
    {
        $query = (new DataQuery('flags'))
            ->withFilter('description', new FilterCriterion('description', FilterOperator::Like, 'CHECKOUT'));

        $items = $this->source->search($query)->asArray();
        self::assertCount(1, $items);
        self::assertSame('beta-checkout', $items[0]->identifier);
    }

    public function testSearchTextScansAllProperties(): void
    {
        $query = (new DataQuery('flags'))
            ->withSearchText('Legacy');

        $items = $this->source->search($query)->asArray();
        self::assertCount(1, $items);
        self::assertSame('legacy-pdf', $items[0]->identifier);
    }

    public function testCreateRejectsExistingKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->create(new DataPayload([
            'id' => 'beta-checkout',
            'enabled' => true,
        ]));
    }

    public function testCreatePersistsAndStringifies(): void
    {
        $record = $this->source->create(new DataPayload([
            'id' => 'new-flag',
            'enabled' => true,
            'description' => 'Just added',
        ]));

        self::assertSame('new-flag', $record->identifier);
        self::assertSame('1', $record->properties['enabled']);
        self::assertSame('Just added', $record->properties['description']);

        $reread = $this->source->find('new-flag');
        self::assertNotNull($reread);
        self::assertSame('1', $reread->properties['enabled']);
    }

    public function testUpdateRejectsMissingKey(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->update('does-not-exist', new DataPayload(['enabled' => false]));
    }

    public function testUpdateMergesFields(): void
    {
        $updated = $this->source->update('dark-mode', new DataPayload([
            'enabled' => false,
        ]));

        self::assertSame('0', $updated->properties['enabled']);
        // pre-existing field preserved (HMSET semantics)
        self::assertSame('dark-mode', $updated->properties['name']);
        self::assertSame('Toggle dark UI', $updated->properties['description']);
    }

    public function testDeleteIsIdempotent(): void
    {
        $this->source->delete('dark-mode');
        self::assertNull($this->source->find('dark-mode'));

        // second call must not throw
        $this->source->delete('dark-mode');
    }

    public function testDataPageHonoursOffsetLimitAndExposesTotal(): void
    {
        // setUp seeds 3 baseline flags + 1 outside-prefix; this test
        // adds 250 more under the same prefix → 253 records total.
        for ($i = 0; $i < 250; ++$i) {
            $this->client->seed("polysource:flag:bulk-{$i}", ['name' => "bulk-{$i}", 'enabled' => '1']);
        }

        $query = (new DataQuery('flags'))
            ->withPagination(new Pagination(offset: 0, limit: 20));

        $page = $this->source->search($query);
        self::assertCount(20, $page->asArray());
        // Switched from cursor to offset/limit pagination — total is
        // now the full materialised count, not null.
        self::assertSame(253, $page->total);
        self::assertNull($page->nextCursor);

        // Page 2 lands on items 20-39 of the sorted set, deterministic.
        $page2 = $this->source->search(
            (new DataQuery('flags'))->withPagination(new Pagination(offset: 20, limit: 20)),
        );
        self::assertCount(20, $page2->asArray());
        self::assertSame(253, $page2->total);
        // Pages do not overlap.
        $idsOf = static function (array $records): array {
            $out = [];
            foreach ($records as $r) {
                /** @var DataRecord $r */
                $out[] = $r->identifier;
            }

            return $out;
        };
        self::assertSame([], array_intersect($idsOf($page->asArray()), $idsOf($page2->asArray())));
    }

    public function testPayloadWithoutIdRejected(): void
    {
        $this->expectException(RuntimeException::class);
        $this->source->create(new DataPayload(['enabled' => true]));
    }

    public function testNullPayloadValuesBecomeEmptyStrings(): void
    {
        $record = $this->source->create(new DataPayload([
            'id' => 'with-null',
            'description' => null,
        ]));

        self::assertSame('', $record->properties['description']);
    }
}
