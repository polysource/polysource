<?php

declare(strict_types=1);

namespace Polysource\Audit\Action;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Audit\Export\AuditCsvExporter;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Query\DataRecord;

/**
 * Bulk action that materialises selected audit entries as a CSV file
 * and exposes its path on the {@see ActionResult::$context} so the
 * host UI can render a download link via flash message.
 *
 * v0.1 limitation: the bulk-action contract returns `ActionResult`,
 * not a Symfony `Response` — we can't stream the CSV directly to the
 * client through `ActionController::bulk()`. Instead the action
 * writes to a host-configured directory (defaults to `sys_get_temp_dir()`)
 * and surfaces the absolute path via the context payload. Hosts that
 * want a streamed download instead can wire a custom controller +
 * route consuming {@see AuditCsvExporter} directly — see the
 * walkthrough in `docs/user/audit/`.
 *
 * GDPR Art. 30 angle: this is the "extract the register" capability
 * compliance officers ask for. With no records selected, the action
 * exports the entire audit log (filtered by whatever is currently
 * applied in the resource index URL).
 */
final class ExportAuditCsvAction implements BulkActionInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AuditCsvExporter $exporter,
        private readonly string $exportDirectory,
    ) {
    }

    public function getName(): string
    {
        return 'export-csv';
    }

    public function getLabel(): string
    {
        return 'Export CSV';
    }

    /**
     * Always returns 'download' — kept nullable to honour the parent
     * `ActionInterface::getIcon()` contract for LSP / future
     * subclassing.
     *
     * @phpstan-ignore-next-line return.unusedType — interface contract is `?string`; we always know
     */
    public function getIcon(): ?string
    {
        return 'download';
    }

    public function getPermission(): string
    {
        // CSV export of the audit log is a destructive / compliance-
        // sensitive operation: it produces a GDPR Art. 30 register
        // extraction containing actor identifiers, IPs, and full
        // action context for every audited request. Returning `null`
        // would fall back to the framework's coarse
        // POLYSOURCE_ACTION_INVOKE which most hosts don't map to any
        // role in their voter — the action would be hidden for every
        // user (showcase repro). A dedicated attribute lets hosts
        // grant export to their compliance/admin tier explicitly.
        // Read-only audit browsing stays gated at the resource level
        // on POLYSOURCE_AUDIT_VIEW, independent of this.
        return 'POLYSOURCE_AUDIT_EXPORT';
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function executeBatch(iterable $records): ActionResult
    {
        $ids = $this->extractIds($records);

        $entries = $this->fetchRecords($ids);

        if (!is_dir($this->exportDirectory) && !mkdir($this->exportDirectory, 0o755, true) && !is_dir($this->exportDirectory)) {
            return ActionResult::failure(\sprintf('Could not create export directory "%s".', $this->exportDirectory));
        }

        $filename = \sprintf(
            'polysource-audit-%s-%s.csv',
            (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd-His'),
            bin2hex(random_bytes(4)),
        );
        $path = rtrim($this->exportDirectory, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . $filename;

        $handle = fopen($path, 'w');
        if (false === $handle) {
            return ActionResult::failure(\sprintf('Could not open export file "%s" for writing.', $path));
        }

        try {
            $count = $this->exporter->write($entries, $handle);
        } finally {
            fclose($handle);
        }

        return ActionResult::success(
            \sprintf('%d audit %s exported to %s', $count, 1 === $count ? 'entry' : 'entries', $path),
            ['exportPath' => $path, 'rowCount' => $count],
        );
    }

    /**
     * @param iterable<DataRecord> $records
     *
     * @return list<string>
     */
    private function extractIds(iterable $records): array
    {
        $ids = [];
        foreach ($records as $record) {
            $ids[] = (string) $record->identifier;
        }

        return $ids;
    }

    /**
     * @param list<string> $ids
     *
     * @return iterable<AuditEntryRecord>
     */
    private function fetchRecords(array $ids): iterable
    {
        $qb = $this->em->createQueryBuilder()
            ->select('r')
            ->from(AuditEntryRecord::class, 'r')
            ->orderBy('r.occurredAt', 'DESC');

        if ([] !== $ids) {
            $qb->where('r.id IN (:ids)')->setParameter('ids', $ids);
        }

        /** @var iterable<AuditEntryRecord> $result */
        $result = $qb->getQuery()->toIterable();

        return $result;
    }
}
