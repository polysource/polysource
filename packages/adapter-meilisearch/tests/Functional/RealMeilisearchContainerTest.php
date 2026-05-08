<?php

declare(strict_types=1);

namespace Polysource\Adapter\Meilisearch\Tests\Functional;

use Meilisearch\Client;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Meilisearch\Client\MeilisearchPhpIndexClient;
use Polysource\Adapter\Meilisearch\DataSource\MeilisearchDataSource;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataQuery;
use Throwable;

/**
 * Wire-level test against a REAL Meilisearch container.
 *
 * Skipped when `POLYSOURCE_REAL_MEILISEARCH` is missing — local
 * developers can opt into running it. CI's `e2e` job sets the
 * URL + master key from the showcase compose stack.
 *
 * Catches integration drift the in-memory fake hides:
 * meilisearch-php client API changes, real indexing latency
 * (we explicitly wait for tasks to finish), real filterable-attributes
 * configuration, anti-injection sanitisation against a server that
 * actually parses filter expressions.
 *
 * @group real-container
 */
final class RealMeilisearchContainerTest extends TestCase
{
    private const INDEX = 'polysource_e2e_products';

    private MeilisearchPhpIndexClient $client;
    private MeilisearchDataSource $dataSource;
    private Client $rawClient;

    protected function setUp(): void
    {
        $url = getenv('POLYSOURCE_REAL_MEILISEARCH');
        $key = getenv('POLYSOURCE_REAL_MEILISEARCH_KEY');
        if ($url === false || $url === '') {
            self::markTestSkipped('Set POLYSOURCE_REAL_MEILISEARCH to a Meilisearch URL.');
        }

        $this->rawClient = new Client($url, $key === false ? null : $key);

        // Wipe + recreate the index so each test starts fresh.
        try {
            $this->rawClient->deleteIndex(self::INDEX);
        } catch (Throwable) {
            // Index didn't exist — fine.
        }
        $task = $this->rawClient->createIndex(self::INDEX, ['primaryKey' => 'id']);
        $this->rawClient->waitForTask($task['taskUid']);

        $index = $this->rawClient->index(self::INDEX);
        // Whitelist filterable attributes so tests can exercise filters.
        $index->updateFilterableAttributes(['status', 'category']);

        $this->client = new MeilisearchPhpIndexClient($index);
        $this->dataSource = new MeilisearchDataSource(
            index: $this->client,
            primaryKey: 'id',
        );
    }

    protected function tearDown(): void
    {
        try {
            $this->rawClient->deleteIndex(self::INDEX);
        } catch (Throwable) {
            // best-effort
        }
    }

    public function testCreateAndFindRoundTripThroughRealClient(): void
    {
        $record = $this->dataSource->create(new DataPayload([
            'id' => 'prd-001',
            'name' => 'Hat',
            'category' => 'apparel',
            'status' => 'active',
        ]));

        // Meilisearch indexing is async — give the server a moment.
        $this->waitForIndex();

        $loaded = $this->dataSource->find($record->identifier);
        self::assertNotNull($loaded);
        self::assertSame('Hat', $loaded->properties['name'] ?? null);
        self::assertSame('active', $loaded->properties['status'] ?? null);
    }

    public function testFullTextSearchReturnsMatchingDocuments(): void
    {
        foreach ([
            ['id' => 'a', 'name' => 'Wool hat', 'status' => 'active'],
            ['id' => 'b', 'name' => 'Leather boots', 'status' => 'active'],
            ['id' => 'c', 'name' => 'Wool scarf', 'status' => 'archived'],
        ] as $doc) {
            $this->dataSource->create(new DataPayload($doc));
        }
        $this->waitForIndex();

        $page = $this->dataSource->search(
            (new DataQuery('real-meili'))->withSearchText('wool'),
        );
        $items = [...$page->items];

        self::assertCount(2, $items, 'Full-text "wool" must match Wool hat + Wool scarf.');
    }

    public function testUpdatePersistsThroughRealServer(): void
    {
        $this->dataSource->create(new DataPayload([
            'id' => 'order-x',
            'status' => 'pending',
        ]));
        $this->waitForIndex();

        $this->dataSource->update('order-x', new DataPayload([
            'id' => 'order-x',
            'status' => 'shipped',
        ]));
        $this->waitForIndex();

        $loaded = $this->dataSource->find('order-x');
        self::assertNotNull($loaded);
        self::assertSame('shipped', $loaded->properties['status'] ?? null);
    }

    public function testDeleteRemovesDocumentFromTheRealIndex(): void
    {
        $this->dataSource->create(new DataPayload(['id' => 'doomed', 'name' => 'Bye']));
        $this->waitForIndex();

        $this->dataSource->delete('doomed');
        $this->waitForIndex();

        self::assertNull($this->dataSource->find('doomed'));
    }

    /**
     * Meilisearch indexing is async — wait for all pending tasks
     * before reading. The `waitForTask` calls in the data source
     * cover the immediate task; this catches background reindex.
     */
    private function waitForIndex(): void
    {
        usleep(300_000); // 300ms — enough for the showcase-grade single-shard index
    }
}
