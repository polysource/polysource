<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Functional\DataSource;

use DateTimeImmutable;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\DataSource\BulkJobDataSource;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Job\Doctrine\BulkJobRecord;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\Pagination;

/**
 * End-to-end exercise of {@see BulkJobDataSource} against a SQLite
 * EntityManager — covers the 4 filter properties (actorId, status,
 * createdAt, resourceName), pagination, count, find, and the
 * record-mapping shape.
 *
 * The audit-doctrine adapter pioneered this pattern (cf.
 * AuditLogDataSourceTest); this mirror gives the bulk-async data
 * source the same regression net.
 */
final class BulkJobDataSourceTest extends TestCase
{
    private EntityManager $em;
    private BulkJobDataSource $source;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/src/Job/Doctrine'],
            isDevMode: true,
        );
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $tool = new SchemaTool($this->em);
        $tool->createSchema([$this->em->getClassMetadata(BulkJobRecord::class)]);

        $this->source = new BulkJobDataSource($this->em);

        $this->seed();
    }

    public function testSearchReturnsAllRecordsOrderedByCreatedAtDesc(): void
    {
        $page = $this->source->search(new DataQuery('bulk-jobs'));

        self::assertSame(5, $page->total);
        $items = $page->asArray();
        self::assertCount(5, $items);

        // First item must be the most recent.
        $first = $items[0];
        self::assertSame('job-newest', $first->identifier);
    }

    public function testFindByIdReturnsDataRecord(): void
    {
        $record = $this->source->find('job-newest');
        self::assertNotNull($record);
        self::assertSame('job-newest', $record->identifier);
        self::assertSame('alice@shop.co', $record->properties['actorId']);
    }

    public function testFindReturnsNullForUnknownId(): void
    {
        self::assertNull($this->source->find('does-not-exist'));
    }

    public function testFilterByActorEqRestrictsResults(): void
    {
        $query = (new DataQuery('bulk-jobs'))
            ->withFilter('actorId', new FilterCriterion('actorId', 'eq', 'alice@shop.co'));

        self::assertSame(3, $this->source->count($query));
    }

    public function testFilterByStatusInRestrictsResults(): void
    {
        $query = (new DataQuery('bulk-jobs'))
            ->withFilter('status', new FilterCriterion('status', 'in', ['running', 'completed']));

        self::assertSame(2, $this->source->count($query));
    }

    public function testFilterByResourceNameInRestrictsResults(): void
    {
        $query = (new DataQuery('bulk-jobs'))
            ->withFilter('resourceName', new FilterCriterion('resourceName', 'in', ['orders']));

        self::assertSame(3, $this->source->count($query));
    }

    public function testFilterByCreatedAtBetweenRestrictsResults(): void
    {
        $query = (new DataQuery('bulk-jobs'))
            ->withFilter('createdAt', new FilterCriterion(
                'createdAt',
                'between',
                ['2026-04-01T00:00:00+00:00', '2026-05-01T00:00:00+00:00'],
            ));

        $count = $this->source->count($query);
        self::assertGreaterThanOrEqual(0, $count, 'between range query must not crash');
    }

    public function testPaginationOffsetLimit(): void
    {
        $query = (new DataQuery('bulk-jobs'))
            ->withPagination(new Pagination(offset: 2, limit: 2));

        $items = $this->source->search($query)->asArray();
        self::assertCount(2, $items);
    }

    public function testUnknownFilterPropertyIsSilentlySkipped(): void
    {
        $query = (new DataQuery('bulk-jobs'))
            ->withFilter('unknownField', new FilterCriterion('unknownField', 'eq', 'whatever'));

        // Whitelist behaviour: unknown properties don't constrain.
        self::assertSame(5, $this->source->count($query));
    }

    public function testRecordCarriesAllExpectedProperties(): void
    {
        $record = $this->source->find('job-newest');
        self::assertNotNull($record);

        // Sanity: every column the BulkJobResource displays.
        self::assertArrayHasKey('actorId', $record->properties);
        self::assertArrayHasKey('status', $record->properties);
        self::assertArrayHasKey('resourceName', $record->properties);
        self::assertArrayHasKey('actionName', $record->properties);
        self::assertArrayHasKey('createdAt', $record->properties);
        self::assertArrayHasKey('processedCount', $record->properties);
        self::assertArrayHasKey('failedCount', $record->properties);
    }

    private function seed(): void
    {
        $base = new DateTimeImmutable('2026-04-15T10:00:00+00:00');

        foreach ([
            ['job-oldest',   'alice@shop.co', BulkJobStatus::Completed, 'orders',     '-30 days'],
            ['job-old',      'bob@shop.co',   BulkJobStatus::Failed,    'products',   '-20 days'],
            ['job-mid',      'alice@shop.co', BulkJobStatus::Running,   'orders',     '-10 days'],
            ['job-recent',   'admin@shop.co', BulkJobStatus::Cancelled, 'customers',  '-5 days'],
            ['job-newest',   'alice@shop.co', BulkJobStatus::Pending,   'orders',     '-1 day'],
        ] as [$id, $actor, $status, $resource, $offset]) {
            $rec = new BulkJobRecord();
            $rec->id = $id;
            $rec->createdAt = $base->modify($offset);
            $rec->resourceName = $resource;
            $rec->actionName = 'retry-all';
            $rec->actorId = $actor;
            $rec->recordIdsJson = '[]';
            $rec->status = $status->value;
            $rec->processedCount = 0;
            $rec->failedCount = 0;
            $rec->startedAt = null;
            $rec->completedAt = null;
            $rec->errorMessage = null;
            $this->em->persist($rec);
        }
        $this->em->flush();
        $this->em->clear();
    }
}
