<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Job;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * One bulk job — captures who dispatched it, which records it
 * targets, where it stands. Immutable; lifecycle transitions
 * produce new instances via the `with*()` mutators.
 *
 * The progress fields are explicit fields rather than derived
 * from the records list because async workers update progress
 * incrementally without re-loading the full record list at every
 * tick.
 */
final class BulkJob
{
    public const ERROR_MESSAGE_MAX_BYTES = 8192;

    public readonly DateTimeImmutable $createdAt;
    public readonly ?DateTimeImmutable $startedAt;
    public readonly ?DateTimeImmutable $completedAt;

    /** @var list<string> */
    public readonly array $recordIds;

    /**
     * @param list<string> $recordIds
     */
    public function __construct(
        public readonly string $id,
        DateTimeImmutable $createdAt,
        public readonly string $resourceName,
        public readonly string $actionName,
        public readonly string $actorId,
        array $recordIds,
        public readonly BulkJobStatus $status,
        public readonly int $processedCount = 0,
        public readonly int $failedCount = 0,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $completedAt = null,
        public readonly ?string $errorMessage = null,
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('BulkJob id cannot be empty.');
        }
        if ('' === $resourceName) {
            throw new InvalidArgumentException('BulkJob resourceName cannot be empty.');
        }
        if ('' === $actionName) {
            throw new InvalidArgumentException('BulkJob actionName cannot be empty.');
        }
        if ('' === $actorId) {
            throw new InvalidArgumentException('BulkJob actorId cannot be empty.');
        }
        if (!array_is_list($recordIds)) {
            throw new InvalidArgumentException('BulkJob recordIds must be a list.');
        }
        foreach ($recordIds as $i => $rid) {
            if (!\is_string($rid) || '' === $rid) {
                throw new InvalidArgumentException(\sprintf('BulkJob recordIds[%d] must be a non-empty string.', $i));
            }
        }
        if ($processedCount < 0) {
            throw new InvalidArgumentException('BulkJob processedCount must be >= 0.');
        }
        if ($failedCount < 0) {
            throw new InvalidArgumentException('BulkJob failedCount must be >= 0.');
        }
        $total = \count($recordIds);
        if ($processedCount > $total) {
            throw new InvalidArgumentException(\sprintf('BulkJob processedCount (%d) cannot exceed total (%d).', $processedCount, $total));
        }
        if ($failedCount > $total) {
            throw new InvalidArgumentException(\sprintf('BulkJob failedCount (%d) cannot exceed total (%d).', $failedCount, $total));
        }
        if (null !== $errorMessage && \strlen($errorMessage) > self::ERROR_MESSAGE_MAX_BYTES) {
            throw new InvalidArgumentException(\sprintf('BulkJob errorMessage exceeds %d bytes; truncate at the call site.', self::ERROR_MESSAGE_MAX_BYTES));
        }

        $utc = new DateTimeZone('UTC');
        $this->createdAt = $createdAt->getTimezone()->getName() === 'UTC' ? $createdAt : $createdAt->setTimezone($utc);
        $this->startedAt = null === $startedAt ? null : ($startedAt->getTimezone()->getName() === 'UTC' ? $startedAt : $startedAt->setTimezone($utc));
        $this->completedAt = null === $completedAt ? null : ($completedAt->getTimezone()->getName() === 'UTC' ? $completedAt : $completedAt->setTimezone($utc));
        $this->recordIds = $recordIds;
    }

    public function total(): int
    {
        return \count($this->recordIds);
    }

    public function progress(): float
    {
        $total = $this->total();

        return 0 === $total ? 1.0 : $this->processedCount / $total;
    }

    public function withStatus(BulkJobStatus $status): self
    {
        return new self(
            $this->id, $this->createdAt, $this->resourceName, $this->actionName,
            $this->actorId, $this->recordIds, $status,
            $this->processedCount, $this->failedCount,
            $this->startedAt, $this->completedAt, $this->errorMessage,
        );
    }

    public function withStartedAt(DateTimeImmutable $startedAt): self
    {
        return new self(
            $this->id, $this->createdAt, $this->resourceName, $this->actionName,
            $this->actorId, $this->recordIds, $this->status,
            $this->processedCount, $this->failedCount,
            $startedAt, $this->completedAt, $this->errorMessage,
        );
    }

    public function withCompletedAt(DateTimeImmutable $completedAt): self
    {
        return new self(
            $this->id, $this->createdAt, $this->resourceName, $this->actionName,
            $this->actorId, $this->recordIds, $this->status,
            $this->processedCount, $this->failedCount,
            $this->startedAt, $completedAt, $this->errorMessage,
        );
    }

    public function withProgress(int $processedCount, int $failedCount): self
    {
        return new self(
            $this->id, $this->createdAt, $this->resourceName, $this->actionName,
            $this->actorId, $this->recordIds, $this->status,
            $processedCount, $failedCount,
            $this->startedAt, $this->completedAt, $this->errorMessage,
        );
    }

    public function withErrorMessage(?string $errorMessage): self
    {
        return new self(
            $this->id, $this->createdAt, $this->resourceName, $this->actionName,
            $this->actorId, $this->recordIds, $this->status,
            $this->processedCount, $this->failedCount,
            $this->startedAt, $this->completedAt, $errorMessage,
        );
    }
}
