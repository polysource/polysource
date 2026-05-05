<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Messenger;

use DateTimeImmutable;
use DateTimeZone;
use Polysource\BulkAsync\Audit\BulkActionView;
use Polysource\BulkAsync\Event\BulkJobProgressEvent;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Job\BulkJobStorageInterface;
use Polysource\Bundle\Event\ActionAboutToExecuteEvent;
use Polysource\Bundle\Event\ActionExecutedEvent;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\ResourceInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Async worker for {@see BulkJobMessage}.
 *
 * Lifecycle (cf. ADR-024 §5):
 *   1. Re-fetch the job — exit if missing or no longer Pending
 *      (re-delivery safety).
 *   2. Mark Running + stamp `startedAt`, persist.
 *   3. Resolve resource + bulk action via {@see ResourceRegistry}.
 *   4. For each record id:
 *      a. Re-fetch the job to honour Cancelled (operator stop).
 *      b. Load the record from the resource's {@see DataSourceInterface}.
 *      c. Run `executeBatch([$record])` and accumulate progress.
 *      d. Throttle persist (every {@see PROGRESS_FLUSH_EVERY_N_RECORDS}
 *         records or every {@see PROGRESS_FLUSH_EVERY_MS} ms).
 *   5. Final persist with terminal {@see BulkJobStatus} + `completedAt`.
 *   6. Dispatch {@see ActionExecutedEvent} so the audit subscriber
 *      (`polysource/audit`, ADR-020) traces the async job too.
 *
 * The handler runs one record at a time on purpose: progress
 * reporting needs sub-batch granularity, and most non-Doctrine
 * sources (Messenger, Redis, S3) don't materialise a transaction
 * across records anyway. Hosts who batch internally ship their own
 * handler.
 */
final class BulkJobHandler
{
    public const PROGRESS_FLUSH_EVERY_N_RECORDS = 5;
    public const PROGRESS_FLUSH_EVERY_MS = 500;

    private LoggerInterface $logger;

    public function __construct(
        private readonly BulkJobStorageInterface $storage,
        private readonly ResourceRegistry $resources,
        ?LoggerInterface $logger = null,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function __invoke(BulkJobMessage $message): void
    {
        $job = $this->storage->find($message->jobId);
        if (null === $job) {
            $this->logger->warning('Polysource: bulk job not found, dropping message.', ['job_id' => $message->jobId]);

            return;
        }

        if (BulkJobStatus::Pending !== $job->status) {
            // Re-delivery (broker retry, manual replay) — the job
            // already moved on. Idempotent skip.
            $this->logger->info('Polysource: bulk job not Pending, skipping.', [
                'job_id' => $job->id,
                'status' => $job->status->value,
            ]);

            return;
        }

        $resource = $this->resolveResource($job);
        if (null === $resource) {
            $this->finaliseFailed($job, \sprintf('Resource "%s" is not registered.', $job->resourceName));

            return;
        }

        $action = $this->resolveAction($resource, $job->actionName);
        if (null === $action) {
            $this->finaliseFailed($job, \sprintf('Bulk action "%s" not found on resource "%s".', $job->actionName, $resource->getName()));

            return;
        }

        $job = $job->withStatus(BulkJobStatus::Running)->withStartedAt($this->now());
        $this->storage->save($job);
        $this->dispatchProgress($job);

        $job = $this->processRecords($job, $resource, $action);
        $this->dispatchAuditEvent($job, $resource, $action);
    }

    private function processRecords(BulkJob $job, ResourceInterface $resource, BulkActionInterface $action): BulkJob
    {
        $dataSource = $resource->getDataSource();
        $processed = $job->processedCount;
        $failed = $job->failedCount;
        $sinceFlushCount = 0;
        $lastFlushAtMs = $this->nowMs();
        $cancelled = false;

        foreach ($job->recordIds as $recordId) {
            $live = $this->storage->find($job->id);
            if (null === $live || BulkJobStatus::Cancelled === $live->status) {
                $cancelled = true;
                $job = $live ?? $job;

                break;
            }
            $job = $live;

            $record = $this->loadRecord($dataSource, $recordId);
            if (null === $record) {
                ++$failed;
            } else {
                try {
                    $result = $action->executeBatch([$record]);
                    if (!$result->success) {
                        ++$failed;
                    }
                } catch (Throwable $e) {
                    $this->logger->error('Polysource: bulk action threw on a record.', [
                        'job_id' => $job->id,
                        'record_id' => $recordId,
                        'exception_class' => $e::class,
                        'exception_message' => $e->getMessage(),
                    ]);
                    ++$failed;
                }
            }

            ++$processed;
            ++$sinceFlushCount;

            $shouldFlush = $sinceFlushCount >= self::PROGRESS_FLUSH_EVERY_N_RECORDS
                || ($this->nowMs() - $lastFlushAtMs) >= self::PROGRESS_FLUSH_EVERY_MS;

            if ($shouldFlush) {
                $job = $job->withProgress($processed, $failed);
                $this->storage->save($job);
                $this->dispatchProgress($job);
                $sinceFlushCount = 0;
                $lastFlushAtMs = $this->nowMs();
            }
        }

        $job = $job->withProgress($processed, $failed)->withCompletedAt($this->now());

        if ($cancelled) {
            $job = $job->withStatus(BulkJobStatus::Cancelled);
        } elseif ($failed > 0) {
            $job = $job->withStatus(BulkJobStatus::Failed);
        } else {
            $job = $job->withStatus(BulkJobStatus::Completed);
        }

        $this->storage->save($job);
        $this->dispatchProgress($job);

        return $job;
    }

    private function dispatchProgress(BulkJob $job): void
    {
        $this->dispatcher?->dispatch(new BulkJobProgressEvent($job));
    }

    private function loadRecord(DataSourceInterface $dataSource, string $recordId): ?DataRecord
    {
        try {
            return $dataSource->find($recordId);
        } catch (Throwable $e) {
            $this->logger->error('Polysource: data source lookup failed.', [
                'record_id' => $recordId,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function resolveResource(BulkJob $job): ?ResourceInterface
    {
        return $this->resources->has($job->resourceName)
            ? $this->resources->get($job->resourceName)
            : null;
    }

    private function resolveAction(ResourceInterface $resource, string $actionName): ?BulkActionInterface
    {
        foreach ($resource->configureActions() as $action) {
            if ($action->getName() === $actionName && $action instanceof BulkActionInterface) {
                return $action;
            }
        }

        return null;
    }

    private function finaliseFailed(BulkJob $job, string $errorMessage): void
    {
        $truncated = \strlen($errorMessage) > BulkJob::ERROR_MESSAGE_MAX_BYTES
            ? substr($errorMessage, 0, BulkJob::ERROR_MESSAGE_MAX_BYTES)
            : $errorMessage;

        $failed = $job
            ->withStatus(BulkJobStatus::Failed)
            ->withStartedAt($job->startedAt ?? $this->now())
            ->withCompletedAt($this->now())
            ->withErrorMessage($truncated);

        $this->storage->save($failed);

        $this->logger->error('Polysource: bulk job aborted before processing.', [
            'job_id' => $job->id,
            'reason' => $truncated,
        ]);
    }

    private function dispatchAuditEvent(BulkJob $job, ResourceInterface $resource, BulkActionInterface $action): void
    {
        if (null === $this->dispatcher) {
            return;
        }

        $request = new Request();
        $auditAction = new BulkActionView($action);
        $duration = null !== $job->startedAt && null !== $job->completedAt
            ? max(0, (int) (($job->completedAt->getTimestamp() - $job->startedAt->getTimestamp()) * 1000))
            : 0;

        $result = match ($job->status) {
            BulkJobStatus::Completed => ActionResult::success(null, [
                'processedCount' => $job->processedCount,
                'failedCount' => $job->failedCount,
                'jobId' => $job->id,
            ]),
            default => ActionResult::failure($job->errorMessage ?? \sprintf('Bulk job ended with status %s.', $job->status->value), [
                'processedCount' => $job->processedCount,
                'failedCount' => $job->failedCount,
                'jobId' => $job->id,
                'jobStatus' => $job->status->value,
            ]),
        };

        $this->dispatcher->dispatch(new ActionAboutToExecuteEvent(
            action: $auditAction,
            resource: $resource,
            recordIds: $job->recordIds,
            request: $request,
        ));

        $this->dispatcher->dispatch(new ActionExecutedEvent(
            action: $auditAction,
            resource: $resource,
            recordIds: $job->recordIds,
            request: $request,
            result: $result,
            durationMs: $duration,
            exception: null,
        ));
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    private function nowMs(): int
    {
        return (int) (microtime(true) * 1000);
    }
}
