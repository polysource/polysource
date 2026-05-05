<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Dispatcher;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Job\BulkJobStorageInterface;
use Polysource\BulkAsync\Messenger\BulkJobMessage;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Host-facing entry point for async bulk dispatch (cf. ADR-024 §6).
 *
 * Typical use from an action controller:
 *
 *     $job = $dispatcher->dispatch($resource, $action, $ids, $user->getUserIdentifier());
 *     return new RedirectResponse($urlGen->detail('bulk-jobs', $job->id));
 *
 * The flow is intentionally minimal:
 *  1. Generate a UUID v7 — gives us monotonic, sortable ids without
 *     any extra dependency.
 *  2. Build a Pending {@see BulkJob} and persist it through
 *     {@see BulkJobStorageInterface} so the row is visible in the UI
 *     before the worker picks the message up.
 *  3. Hand a {@see BulkJobMessage} to the {@see MessageBusInterface}.
 *
 * The dispatcher does not check permissions or CSRF — that stays in
 * the host's controller before the call. We accept any
 * {@see BulkActionInterface}, not only
 * {@see \Polysource\BulkAsync\Action\AsyncAwareBulkActionInterface},
 * so hosts can force-async an action without modifying it (e.g.
 * "this `RetryAllFailedMessages` is now too slow at 5k+ envelopes").
 */
final class AsyncBulkActionDispatcher
{
    public function __construct(
        private readonly BulkJobStorageInterface $storage,
        private readonly MessageBusInterface $bus,
    ) {
    }

    /**
     * @param list<string> $recordIds
     */
    public function dispatch(
        ResourceInterface $resource,
        BulkActionInterface $action,
        array $recordIds,
        string $actorId,
    ): BulkJob {
        if ('' === $actorId) {
            throw new InvalidArgumentException('AsyncBulkActionDispatcher: actorId cannot be empty.');
        }
        if ([] === $recordIds) {
            throw new InvalidArgumentException('AsyncBulkActionDispatcher: recordIds cannot be empty.');
        }

        $job = new BulkJob(
            id: Uuid::v7()->toRfc4122(),
            createdAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            resourceName: $resource->getName(),
            actionName: $action->getName(),
            actorId: $actorId,
            recordIds: array_values($recordIds),
            status: BulkJobStatus::Pending,
        );

        $this->storage->save($job);
        $this->bus->dispatch(new BulkJobMessage($job->id));

        return $job;
    }
}
