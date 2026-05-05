<?php

declare(strict_types=1);

namespace Polysource\Audit\Logger;

use Polysource\Audit\Model\AuditEntry;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

/**
 * Fan-out aggregator — dispatches each {@see AuditEntry} to every
 * registered downstream logger and contains failures so that one
 * misbehaving sink (Datadog timeout, full disk, syslog crash) cannot
 * propagate an exception back up to the user-facing action.
 *
 * Why this exists rather than letting Symfony auto-wire a tagged
 * iterator into the subscriber:
 *  - Centralises the try/catch contention (no need to repeat in every
 *    subscriber that ever consumes audit).
 *  - Logs failures via PSR-3 so operators can spot a broken sink in
 *    `monolog/audit.log` without adding bespoke instrumentation.
 *  - Makes "the default service" point to a single class — host apps
 *    that want a different aggregation strategy (e.g. fan-out then
 *    archive in Messenger) ship their own implementation under the
 *    same alias.
 */
final class AggregateAuditLogger implements AuditLoggerInterface
{
    private readonly LoggerInterface $errorLogger;

    /** @var iterable<AuditLoggerInterface> */
    private readonly iterable $loggers;

    /**
     * @param iterable<AuditLoggerInterface> $loggers     — downstream sinks tagged `polysource.audit_logger`
     * @param LoggerInterface|null           $errorLogger — used to surface contained failures (defaults to NullLogger)
     */
    public function __construct(iterable $loggers, ?LoggerInterface $errorLogger = null)
    {
        $this->loggers = $loggers;
        $this->errorLogger = $errorLogger ?? new NullLogger();
    }

    public function log(AuditEntry $entry): void
    {
        foreach ($this->loggers as $logger) {
            try {
                $logger->log($entry);
            } catch (Throwable $e) {
                // Contention: one bad sink shouldn't break the user
                // action. Surface the failure via PSR-3 so the host
                // can monitor it via Sentry / Datadog APM / whatever
                // it already wires for app errors.
                $this->errorLogger->error('Audit logger {logger} failed for entry {entryId}: {error}', [
                    'logger' => $logger::class,
                    'entryId' => $entry->id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }
    }
}
