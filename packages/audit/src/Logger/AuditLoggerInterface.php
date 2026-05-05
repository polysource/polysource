<?php

declare(strict_types=1);

namespace Polysource\Audit\Logger;

use Polysource\Audit\Model\AuditEntry;

/**
 * Write-only sink for {@see AuditEntry} rows.
 *
 * Single-method by design (cf. ADR-020 §4):
 *  - Reads go through `AuditLogResource` (Doctrine queryBuilder), not
 *    through the logger — separating read/write keeps each
 *    implementation focused and lets us ship logger-only adapters
 *    that don't know how to query (Datadog, Splunk HTTP).
 *  - Multiple loggers can run in parallel under the same DI tag
 *    `polysource.audit_logger`. They are aggregated by
 *    {@see AggregateAuditLogger} which becomes the default service
 *    consumed by the `ActionAuditSubscriber`.
 *
 * Implementations MUST NOT throw — a failing audit logger must NEVER
 * propagate an exception that would bubble back to the user-facing
 * action. The aggregator wraps each call in try/catch; standalone
 * implementations should do the same internally if their backend can
 * fail (DB unavailable, HTTP timeout, …).
 */
interface AuditLoggerInterface
{
    public function log(AuditEntry $entry): void;
}
