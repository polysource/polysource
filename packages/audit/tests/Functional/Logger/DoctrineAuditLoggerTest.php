<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Functional\Logger;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Logger\DoctrineAuditLogger;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;

/**
 * Boots a Doctrine ORM + SQLite in-memory stack to exercise the
 * write path end-to-end:
 *  AuditEntry VO → DoctrineAuditLogger::log() → SQL row → re-read → assert.
 *
 * No Symfony kernel — keeps the test fast and isolated while
 * still validating the full schema mapping (column names, JSON
 * encoding of recordIds/context, datetime_immutable UTC round-trip).
 */
final class DoctrineAuditLoggerTest extends TestCase
{
    private EntityManager $em;
    private DoctrineAuditLogger $logger;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/src/Storage/Doctrine'],
            isDevMode: true,
        );
        // Doctrine 3.x lazy-ghost proxies need PHP 8.4 native objects
        // or symfony/var-exporter. Opt-in to the native variant only on PHP 8.4+
        // — older PHPs fall back to symfony/var-exporter ghosts (which Symfony 6.2+
        // ships transitively). Test entities have no associations so this is moot
        // either way, but the call itself fails on PHP < 8.4 with ORM 3.x.
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $tool = new SchemaTool($this->em);
        $tool->createSchema([$this->em->getClassMetadata(AuditEntryRecord::class)]);

        $this->logger = new DoctrineAuditLogger($this->em);
    }

    public function testRoundTripsAuditEntryThroughDoctrine(): void
    {
        $entry = new AuditEntry(
            id: '01HF000000000000000000ABCD',
            occurredAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            actorId: 'alice',
            actorLabel: 'Alice Doe',
            resourceName: 'orders',
            actionName: 'retry',
            recordIds: ['ord-1', 'ord-2'],
            outcome: AuditOutcome::Success,
            message: '2 orders retried',
            durationMs: 42,
            context: ['ip' => '192.0.2.1', 'requestId' => 'req-abc'],
        );

        $this->logger->log($entry);
        $this->em->clear();

        $record = $this->em->find(AuditEntryRecord::class, '01HF000000000000000000ABCD');
        self::assertNotNull($record);
        self::assertSame('alice', $record->actorId);
        self::assertSame('Alice Doe', $record->actorLabel);
        self::assertSame('orders', $record->resourceName);
        self::assertSame('retry', $record->actionName);
        self::assertSame('success', $record->outcome);
        self::assertSame('2 orders retried', $record->message);
        self::assertSame(42, $record->durationMs);
        self::assertSame(['ord-1', 'ord-2'], json_decode($record->recordIdsJson, true));
        self::assertSame(
            ['ip' => '192.0.2.1', 'requestId' => 'req-abc'],
            json_decode($record->contextJson, true),
        );
        self::assertSame('UTC', $record->occurredAt->getTimezone()->getName());
        self::assertSame('2026-05-05T10:00:00+00:00', $record->occurredAt->format(\DATE_ATOM));
    }

    public function testGlobalActionWithEmptyRecordIdsIsAccepted(): void
    {
        $entry = AuditEntry::nowFor(
            id: '01HF000000000000000000GLOB',
            actorId: AuditEntry::ANONYMOUS_ACTOR_ID,
            actorLabel: null,
            resourceName: 'orders',
            actionName: 'export-csv',
            outcome: AuditOutcome::Success,
        );

        $this->logger->log($entry);
        $this->em->clear();

        $record = $this->em->find(AuditEntryRecord::class, '01HF000000000000000000GLOB');
        self::assertNotNull($record);
        self::assertSame('[]', $record->recordIdsJson);
        self::assertSame('__anonymous__', $record->actorId);
    }

    public function testFailureOutcomeIsPersistedAsValueString(): void
    {
        $entry = AuditEntry::nowFor(
            id: '01HF000000000000000000FAIL',
            actorId: 'alice',
            actorLabel: null,
            resourceName: 'orders',
            actionName: 'retry',
            outcome: AuditOutcome::Failure,
            message: 'downstream API rejected',
            durationMs: 17,
        );

        $this->logger->log($entry);
        $this->em->clear();

        $record = $this->em->find(AuditEntryRecord::class, '01HF000000000000000000FAIL');
        self::assertNotNull($record);
        self::assertSame('failure', $record->outcome);
    }

    public function testMultipleEntriesAreIndependentlyPersisted(): void
    {
        $a = AuditEntry::nowFor('01HF000000000000000000AAAA', 'alice', null, 'orders', 'retry', AuditOutcome::Success);
        $b = AuditEntry::nowFor('01HF000000000000000000BBBB', 'bob', null, 'orders', 'dismiss', AuditOutcome::Failure);

        $this->logger->log($a);
        $this->logger->log($b);
        $this->em->clear();

        self::assertNotNull($this->em->find(AuditEntryRecord::class, '01HF000000000000000000AAAA'));
        self::assertNotNull($this->em->find(AuditEntryRecord::class, '01HF000000000000000000BBBB'));

        $count = $this->em->createQuery('SELECT COUNT(r.id) FROM ' . AuditEntryRecord::class . ' r')->getSingleScalarResult();
        self::assertSame(2, (int) $count);
    }
}
