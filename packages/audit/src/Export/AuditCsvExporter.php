<?php

declare(strict_types=1);

namespace Polysource\Audit\Export;

use InvalidArgumentException;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;

/**
 * Streams audit entries as RFC 4180 CSV, the canonical "register of
 * processing activities" format compliance officers ask for under
 * GDPR Art. 30.
 *
 * Column choice (and order) is locked: any host integration that
 * relies on a particular column position would silently break if we
 * reordered. The header row is always emitted.
 *
 * Design seam: the exporter takes an *iterable* of records so callers
 * pick the strategy — a Doctrine `iterate()` cursor for very large
 * exports (millions of rows), or an in-memory array for the common
 * case (≤ 100k entries / month). The exporter never buffers more than
 * one row at a time so memory stays flat regardless of total volume.
 *
 * Output goes to a `$handle` (file pointer or `php://output`) — the
 * caller controls where bytes land. Hosts wanting a streamed HTTP
 * download wire up a Symfony `StreamedResponse` whose callable opens
 * `php://output` and pipes it through this method.
 */
final class AuditCsvExporter
{
    /**
     * Locked column order. Snake_case names match the Art. 30 register
     * conventions used by most compliance tooling (OneTrust, Trustarc,
     * Vanta).
     *
     * @var list<string>
     */
    private const COLUMNS = [
        'id',
        'occurred_at',
        'actor_id',
        'actor_label',
        'resource_name',
        'action_name',
        'outcome',
        'message',
        'duration_ms',
        'record_ids',
        'context_ip',
        'context_request_id',
    ];

    /**
     * @param iterable<AuditEntryRecord> $records
     * @param resource                   $handle  open for writing — typically `php://output` for streaming or a tmp file
     *
     * @return int number of data rows written (header excluded)
     */
    public function write(iterable $records, $handle): int
    {
        if (!\is_resource($handle)) {
            throw new InvalidArgumentException('AuditCsvExporter::write() requires an open file handle.');
        }

        fputcsv($handle, self::COLUMNS, escape: '');

        $count = 0;
        foreach ($records as $record) {
            fputcsv($handle, $this->row($record), escape: '');
            ++$count;
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function row(AuditEntryRecord $record): array
    {
        $decoded = json_decode($record->contextJson, true);
        // Narrow the json_decode return to the shape we accept. A
        // mis-encoded context_json (impossible if writes always go
        // through DoctrineAuditLogger) becomes an empty context
        // rather than a CSV that omits the columns entirely.
        $context = \is_array($decoded) ? $decoded : [];

        return [
            $record->id,
            $record->occurredAt->format(\DATE_ATOM),
            $record->actorId,
            $record->actorLabel ?? '',
            $record->resourceName,
            $record->actionName,
            $record->outcome,
            $record->message ?? '',
            (string) $record->durationMs,
            $record->recordIdsJson,
            self::stringField($context, 'ip'),
            self::stringField($context, 'requestId'),
        ];
    }

    /**
     * @param array<mixed> $context
     */
    private static function stringField(array $context, string $key): string
    {
        $value = $context[$key] ?? '';

        return \is_string($value) ? $value : '';
    }
}
