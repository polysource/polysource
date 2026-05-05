<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Functional\DataSource;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\DataSource\AuditLogDataSource;
use Polysource\Audit\Logger\DoctrineAuditLogger;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;
use Polysource\Core\Query\DataPage;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Query\FilterCriterion;
use Polysource\Core\Query\Pagination;

/**
 * Exercises the read path through Doctrine — every supported filter
 * operator, the newest-first ordering, the pagination, and the
 * shape of the DataRecord rows the UI consumes.
 */
final class AuditLogDataSourceTest extends TestCase
{
    private EntityManager $em;
    private AuditLogDataSource $source;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/src/Storage/Doctrine'],
            isDevMode: true,
        );
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $tool = new SchemaTool($this->em);
        $tool->createSchema([$this->em->getClassMetadata(AuditEntryRecord::class)]);

        $this->source = new AuditLogDataSource($this->em);

        $this->seed();
    }

    public function testSearchReturnsAllEntriesNewestFirst(): void
    {
        $page = $this->source->search(new DataQuery('audit-log'));

        self::assertSame(5, $page->total);
        $items = $this->itemsOf($page);
        self::assertCount(5, $items);
        // Newest first — alice-flags at 14:00 comes first.
        self::assertSame('alice-flags', $items[0]->identifier);
        self::assertSame('alice-noon', $items[1]->identifier);
    }

    public function testFindReturnsRecordById(): void
    {
        $record = $this->source->find('alice-morning');
        self::assertNotNull($record);
        self::assertSame('alice', $record->properties['actorId']);
        self::assertSame('orders', $record->properties['resourceName']);
    }

    public function testFindReturnsNullForUnknownId(): void
    {
        self::assertNull($this->source->find('nope'));
    }

    public function testFilterByActorIdEqRestrictsResults(): void
    {
        $query = (new DataQuery('audit-log'))
            ->withFilter('actor', new FilterCriterion('actorId', 'eq', 'bob'));

        $page = $this->source->search($query);

        self::assertSame(2, $page->total);
        foreach ($this->itemsOf($page) as $record) {
            self::assertSame('bob', $record->properties['actorId']);
        }
    }

    public function testFilterByOutcomeInRestrictsResults(): void
    {
        $query = (new DataQuery('audit-log'))
            ->withFilter('outcome', new FilterCriterion('outcome', 'in', ['failure', 'exception']));

        $page = $this->source->search($query);

        self::assertSame(3, $page->total);
        foreach ($this->itemsOf($page) as $record) {
            self::assertContains($record->properties['outcome'], ['failure', 'exception']);
        }
    }

    public function testFilterByOccurredAtBetweenRestrictsResults(): void
    {
        $query = (new DataQuery('audit-log'))
            ->withFilter(
                'occurredAt',
                new FilterCriterion('occurredAt', 'between', ['2026-05-05T08:00:00', '2026-05-05T11:00:00']),
            );

        $page = $this->source->search($query);

        // Only alice-morning (10:00) falls in the [08:00, 11:00] window.
        self::assertSame(1, $page->total);
        self::assertSame('alice-morning', $this->itemsOf($page)[0]->identifier);
    }

    public function testFilterByResourceNameInRestrictsResults(): void
    {
        $query = (new DataQuery('audit-log'))
            ->withFilter('resourceName', new FilterCriterion('resourceName', 'in', ['flags']));

        $page = $this->source->search($query);

        self::assertSame(1, $page->total);
        self::assertSame('flags', $this->itemsOf($page)[0]->properties['resourceName']);
    }

    public function testCombinedFiltersAreAppliedAsConjunction(): void
    {
        $query = (new DataQuery('audit-log'))
            ->withFilter('actor', new FilterCriterion('actorId', 'eq', 'alice'))
            ->withFilter('outcome', new FilterCriterion('outcome', 'in', ['success']));

        $page = $this->source->search($query);

        self::assertSame(2, $page->total);
        foreach ($this->itemsOf($page) as $record) {
            self::assertSame('alice', $record->properties['actorId']);
            self::assertSame('success', $record->properties['outcome']);
        }
    }

    public function testPaginationLimitsAndOffsetsCorrectly(): void
    {
        $query = new DataQuery(
            resourceName: 'audit-log',
            pagination: new Pagination(offset: 2, limit: 2),
        );

        $page = $this->source->search($query);

        // Offset 2 and limit 2 over 5 newest-first → 3rd and 4th entries.
        self::assertCount(2, $this->itemsOf($page));
        self::assertSame(5, $page->total);
    }

    public function testUnsupportedFilterPropertyIsSkipped(): void
    {
        $query = (new DataQuery('audit-log'))
            ->withFilter('unknown', new FilterCriterion('unknownProperty', 'eq', 'whatever'));

        $page = $this->source->search($query);

        // No restriction — full set.
        self::assertSame(5, $page->total);
    }

    /**
     * Narrow `DataPage::$items` (declared `iterable`) to a positional
     * array of {@see DataRecord} so PHPStan stops complaining about
     * non-offsetable iterables. Our adapter always returns an array,
     * so this is safe.
     *
     * @return list<DataRecord>
     */
    private function itemsOf(DataPage $page): array
    {
        $list = [];
        foreach ($page->items as $item) {
            $list[] = $item;
        }

        return $list;
    }

    private function seed(): void
    {
        $logger = new DoctrineAuditLogger($this->em);

        $logger->log($this->makeEntry('alice-morning', 'alice', 'orders', 'retry', AuditOutcome::Success, '10:00:00'));
        $logger->log($this->makeEntry('alice-noon', 'alice', 'orders', 'retry', AuditOutcome::Success, '12:00:00'));
        $logger->log($this->makeEntry('bob-failure', 'bob', 'orders', 'retry', AuditOutcome::Failure, '11:30:00'));
        $logger->log($this->makeEntry('bob-exception', 'bob', 'orders', 'dismiss', AuditOutcome::Exception, '11:45:00'));
        $logger->log($this->makeEntry('alice-flags', 'alice', 'flags', 'toggle', AuditOutcome::Failure, '14:00:00'));
    }

    private function makeEntry(
        string $id,
        string $actorId,
        string $resource,
        string $action,
        AuditOutcome $outcome,
        string $time,
    ): AuditEntry {
        return new AuditEntry(
            id: $id,
            occurredAt: new DateTimeImmutable("2026-05-05T{$time}", new DateTimeZone('UTC')),
            actorId: $actorId,
            actorLabel: ucfirst($actorId),
            resourceName: $resource,
            actionName: $action,
            recordIds: [],
            outcome: $outcome,
            message: null,
            durationMs: 10,
        );
    }
}
