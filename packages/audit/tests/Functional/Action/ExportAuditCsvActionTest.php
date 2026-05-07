<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Functional\Action;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Action\ExportAuditCsvAction;
use Polysource\Audit\Export\AuditCsvExporter;
use Polysource\Audit\Logger\DoctrineAuditLogger;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;
use Polysource\Core\Query\DataRecord;

/**
 * Boots Doctrine + SQLite, seeds 3 audit entries, runs the action,
 * then re-reads the produced CSV from disk and asserts:
 *  - header row + N data rows
 *  - column order matches the locked contract
 *  - context (ip, requestId) survives the round trip
 *  - empty selection exports the full filtered set
 */
final class ExportAuditCsvActionTest extends TestCase
{
    private EntityManager $em;
    private string $exportDir;
    private ExportAuditCsvAction $action;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/src/Storage/Doctrine'],
            isDevMode: true,
        );
        // Doctrine ORM 3.x: opt into PHP 8.4 native lazy objects only on PHP 8.4+.
        // The method itself is fine to call but requires 8.4 to actually enable proxies.
        // On older PHPs Doctrine falls back to symfony/var-exporter ghosts; these test
        // entities have no associations so no proxy is ever materialised either way.
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->em = new EntityManager($connection, $config);

        $tool = new SchemaTool($this->em);
        $tool->createSchema([$this->em->getClassMetadata(AuditEntryRecord::class)]);

        $this->exportDir = sys_get_temp_dir() . \DIRECTORY_SEPARATOR . 'polysource-audit-test-' . bin2hex(random_bytes(4));

        $this->action = new ExportAuditCsvAction(
            $this->em,
            new AuditCsvExporter(),
            $this->exportDir,
        );

        $this->seed();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->exportDir)) {
            $files = glob($this->exportDir . \DIRECTORY_SEPARATOR . '*');
            $files = false === $files ? [] : $files;
            foreach ($files as $file) {
                @unlink($file);
            }
            // Also nuke any nested test sub-dirs.
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $nested = glob($file . \DIRECTORY_SEPARATOR . '*');
                    foreach (false === $nested ? [] : $nested as $entry) {
                        @unlink($entry);
                    }
                    @rmdir($file);
                }
            }
            @rmdir($this->exportDir);
        }
    }

    public function testEmptySelectionExportsFullAuditLog(): void
    {
        $result = $this->action->executeBatch([]);

        self::assertTrue($result->success);
        self::assertSame(3, $result->context['rowCount']);

        $path = $result->context['exportPath'];
        self::assertIsString($path);
        self::assertFileExists($path);

        $rows = $this->readCsv($path);
        // 1 header + 3 data rows.
        self::assertCount(4, $rows);

        // Locked column order.
        self::assertSame(
            ['id', 'occurred_at', 'actor_id', 'actor_label', 'resource_name', 'action_name', 'outcome', 'message', 'duration_ms', 'record_ids', 'context_ip', 'context_request_id'],
            $rows[0],
        );
    }

    public function testSelectionRestrictsExportToProvidedIds(): void
    {
        $records = [
            new DataRecord('alice-1', []),
            new DataRecord('bob-1', []),
        ];

        $result = $this->action->executeBatch($records);

        self::assertTrue($result->success);
        self::assertSame(2, $result->context['rowCount']);

        $path = $result->context['exportPath'];
        self::assertIsString($path);
        $rows = $this->readCsv($path);
        self::assertCount(3, $rows); // header + 2

        $ids = array_column(\array_slice($rows, 1), 0);
        sort($ids);
        self::assertSame(['alice-1', 'bob-1'], $ids);
    }

    public function testContextFieldsAreFlattenedIntoDedicatedColumns(): void
    {
        $result = $this->action->executeBatch([new DataRecord('alice-1', [])]);
        self::assertTrue($result->success);

        $path = $result->context['exportPath'];
        self::assertIsString($path);
        $rows = $this->readCsv($path);
        $dataRow = $rows[1];

        // Column 10 = context_ip, column 11 = context_request_id (0-indexed).
        self::assertSame('192.0.2.1', $dataRow[10]);
        self::assertSame('req-alice-1', $dataRow[11]);
    }

    public function testCreatesExportDirectoryWhenMissing(): void
    {
        // Force a fresh sub-directory by appending a child path.
        $dir = $this->exportDir . \DIRECTORY_SEPARATOR . 'nested';
        $action = new ExportAuditCsvAction($this->em, new AuditCsvExporter(), $dir);

        $result = $action->executeBatch([]);

        self::assertTrue($result->success);
        self::assertDirectoryExists($dir);
    }

    public function testFilenameIsUniqueAcrossInvocations(): void
    {
        $a = $this->action->executeBatch([]);
        $b = $this->action->executeBatch([]);

        self::assertNotSame($a->context['exportPath'], $b->context['exportPath']);
    }

    private function seed(): void
    {
        $logger = new DoctrineAuditLogger($this->em);

        foreach (['alice-1' => 'alice', 'alice-2' => 'alice', 'bob-1' => 'bob'] as $id => $actor) {
            $logger->log(new AuditEntry(
                id: $id,
                occurredAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
                actorId: $actor,
                actorLabel: ucfirst($actor),
                resourceName: 'orders',
                actionName: 'retry',
                recordIds: ['ord-' . $id],
                outcome: AuditOutcome::Success,
                message: 'ok',
                durationMs: 12,
                context: ['ip' => '192.0.2.1', 'requestId' => 'req-' . $id, 'userAgent' => 'curl/8.0'],
            ));
        }
    }

    /**
     * @return list<list<string>>
     */
    private function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        self::assertNotFalse($handle);
        while (false !== $row = fgetcsv($handle, escape: '')) {
            /** @var list<string> $row */
            $rows[] = $row;
        }
        fclose($handle);

        return $rows;
    }
}
