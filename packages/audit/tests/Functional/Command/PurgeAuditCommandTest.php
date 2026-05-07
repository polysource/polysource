<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Functional\Command;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Command\PurgeAuditCommand;
use Polysource\Audit\Logger\DoctrineAuditLogger;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Pin the retention contract:
 *  - `--before` is required.
 *  - Cutoff is exclusive: rows with occurredAt < cutoff are deleted,
 *    rows with occurredAt >= cutoff are kept.
 *  - `--dry-run` reports the count without touching the table.
 *  - Bad input fails with EXIT_BAD_INPUT (1) — never silently no-op.
 */
final class PurgeAuditCommandTest extends TestCase
{
    private EntityManager $em;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/src/Storage/Doctrine'],
            isDevMode: true,
        );
        // Doctrine ORM 3.x native lazy objects gated on PHP 8.4 — see ExportAuditCsvActionTest.
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $tool = new SchemaTool($this->em);
        $tool->createSchema([$this->em->getClassMetadata(AuditEntryRecord::class)]);

        $this->tester = new CommandTester(new PurgeAuditCommand($this->em));

        $this->seed();
    }

    public function testMissingBeforeOptionFails(): void
    {
        $exit = $this->tester->execute([]);

        self::assertSame(PurgeAuditCommand::EXIT_BAD_INPUT, $exit);
        self::assertStringContainsString('--before=YYYY-MM-DD is required', $this->tester->getDisplay());
    }

    public function testInvalidDateFails(): void
    {
        $exit = $this->tester->execute(['--before' => 'not-a-date']);

        self::assertSame(PurgeAuditCommand::EXIT_BAD_INPUT, $exit);
        self::assertStringContainsString('Invalid --before value', $this->tester->getDisplay());
    }

    public function testCutoffExclusiveDeletesOlderRows(): void
    {
        // Seeded: old-1 (2025-01-01), old-2 (2025-06-01), recent-1 (2026-04-01).
        $exit = $this->tester->execute(['--before' => '2026-01-01']);

        self::assertSame(PurgeAuditCommand::EXIT_OK, $exit);
        self::assertStringContainsString('Deleted 2 audit entries', $this->tester->getDisplay());

        // Only the recent entry survives.
        $remaining = $this->em->getRepository(AuditEntryRecord::class)->findAll();
        self::assertCount(1, $remaining);
        self::assertSame('recent-1', $remaining[0]->id);
    }

    public function testDryRunReportsCountWithoutDeleting(): void
    {
        $exit = $this->tester->execute([
            '--before' => '2026-01-01',
            '--dry-run' => true,
        ]);

        self::assertSame(PurgeAuditCommand::EXIT_OK, $exit);
        self::assertStringContainsString('[dry-run]', $this->tester->getDisplay());
        self::assertStringContainsString('Would delete 2 audit entries', $this->tester->getDisplay());

        // Nothing actually deleted.
        self::assertCount(3, $this->em->getRepository(AuditEntryRecord::class)->findAll());
    }

    public function testZeroMatchingRowsReportsAndExitsCleanly(): void
    {
        // A cutoff before any seeded date — no matches.
        $exit = $this->tester->execute(['--before' => '2024-01-01']);

        self::assertSame(PurgeAuditCommand::EXIT_OK, $exit);
        self::assertStringContainsString('No audit entries older than', $this->tester->getDisplay());

        // All seeded rows survive.
        self::assertCount(3, $this->em->getRepository(AuditEntryRecord::class)->findAll());
    }

    public function testCutoffOnExactBoundaryKeepsBoundaryRow(): void
    {
        // A row stamped exactly at the cutoff should NOT be deleted
        // (cutoff is `<`, not `<=`). Seed an extra row at an exact
        // boundary and verify.
        $logger = new DoctrineAuditLogger($this->em);
        $logger->log($this->makeEntry('boundary', '2026-01-01T00:00:00'));

        $exit = $this->tester->execute(['--before' => '2026-01-01']);
        self::assertSame(PurgeAuditCommand::EXIT_OK, $exit);

        $survivors = array_map(
            static fn (AuditEntryRecord $r) => $r->id,
            $this->em->getRepository(AuditEntryRecord::class)->findAll(),
        );
        sort($survivors);
        // recent-1 + boundary survive; old-1 + old-2 deleted.
        self::assertSame(['boundary', 'recent-1'], $survivors);
    }

    private function seed(): void
    {
        $logger = new DoctrineAuditLogger($this->em);
        $logger->log($this->makeEntry('old-1', '2025-01-01T10:00:00'));
        $logger->log($this->makeEntry('old-2', '2025-06-01T10:00:00'));
        $logger->log($this->makeEntry('recent-1', '2026-04-01T10:00:00'));
    }

    private function makeEntry(string $id, string $isoDate): AuditEntry
    {
        return new AuditEntry(
            id: $id,
            occurredAt: new DateTimeImmutable($isoDate, new DateTimeZone('UTC')),
            actorId: 'alice',
            actorLabel: null,
            resourceName: 'orders',
            actionName: 'retry',
            recordIds: [],
            outcome: AuditOutcome::Success,
            message: null,
            durationMs: 0,
        );
    }
}
