<?php

declare(strict_types=1);

namespace Polysource\Audit\Logger;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Storage\Doctrine\AuditEntryRecord;

/**
 * Doctrine ORM-backed logger — persists each {@see AuditEntry} into
 * the `polysource_audit_log` table via a one-row insert.
 *
 * Behaviour:
 *  - `flush()` runs immediately on each call. Audit rows must be
 *    durable before the user-facing redirect; deferring to a
 *    kernel.terminate listener would lose entries on a fatal
 *    application crash mid-request.
 *  - Failures (DB unavailable, schema drift) propagate to the
 *    {@see AggregateAuditLogger} contention layer which logs them
 *    via PSR-3 and continues with the next sink.
 *  - JSON encoding uses `JSON_THROW_ON_ERROR`: a non-encodable
 *    `context` array (e.g. a DateTime stamped by a careless host)
 *    becomes a logger failure rather than a silent corruption of
 *    the `context_json` column.
 */
final class DoctrineAuditLogger implements AuditLoggerInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function log(AuditEntry $entry): void
    {
        $record = new AuditEntryRecord();
        $record->id = $entry->id;
        $record->occurredAt = $entry->occurredAt;
        $record->actorId = $entry->actorId;
        $record->actorLabel = $entry->actorLabel;
        $record->resourceName = $entry->resourceName;
        $record->actionName = $entry->actionName;
        $record->recordIdsJson = json_encode($entry->recordIds, \JSON_THROW_ON_ERROR);
        $record->outcome = $entry->outcome->value;
        $record->message = $entry->message;
        $record->durationMs = $entry->durationMs;
        $record->contextJson = json_encode($entry->context, \JSON_THROW_ON_ERROR);

        $this->em->persist($record);
        $this->em->flush();
    }
}
