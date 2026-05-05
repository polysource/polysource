<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Job;

use Doctrine\ORM\EntityManagerInterface;
use Polysource\BulkAsync\Job\Doctrine\BulkJobRecord;

/**
 * Doctrine ORM-backed storage for {@see BulkJob}.
 *
 * Save semantics: idempotent on `id`. Existing record updated in
 * place, otherwise inserted. Flush is immediate so the worker
 * sees the new state on the next iteration (cancellation respect).
 */
final class DoctrineBulkJobStorage implements BulkJobStorageInterface
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function save(BulkJob $job): void
    {
        $record = $this->em->find(BulkJobRecord::class, $job->id) ?? new BulkJobRecord();

        $record->id = $job->id;
        $record->createdAt = $job->createdAt;
        $record->resourceName = $job->resourceName;
        $record->actionName = $job->actionName;
        $record->actorId = $job->actorId;
        $record->recordIdsJson = json_encode($job->recordIds, \JSON_THROW_ON_ERROR);
        $record->status = $job->status->value;
        $record->processedCount = $job->processedCount;
        $record->failedCount = $job->failedCount;
        $record->startedAt = $job->startedAt;
        $record->completedAt = $job->completedAt;
        $record->errorMessage = $job->errorMessage;

        $this->em->persist($record);
        $this->em->flush();
    }

    public function find(string $id): ?BulkJob
    {
        $record = $this->em->find(BulkJobRecord::class, $id);
        if (!$record instanceof BulkJobRecord) {
            return null;
        }

        return $this->toJob($record);
    }

    public function listForActor(string $actorId, int $limit = 50): array
    {
        $records = $this->em->createQueryBuilder()
            ->select('r')
            ->from(BulkJobRecord::class, 'r')
            ->where('r.actorId = :actorId')
            ->setParameter('actorId', $actorId)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $jobs = [];
        /** @var iterable<BulkJobRecord> $records */
        foreach ($records as $record) {
            $jobs[] = $this->toJob($record);
        }

        return $jobs;
    }

    private function toJob(BulkJobRecord $record): BulkJob
    {
        $decoded = json_decode($record->recordIdsJson, true);
        if (!\is_array($decoded)) {
            $decoded = [];
        }
        /** @var list<non-empty-string> $recordIds */
        $recordIds = array_values(array_filter(
            $decoded,
            static fn ($v): bool => \is_string($v) && '' !== $v,
        ));

        $status = BulkJobStatus::tryFrom($record->status) ?? BulkJobStatus::Pending;

        return new BulkJob(
            id: $record->id,
            createdAt: $record->createdAt,
            resourceName: $record->resourceName,
            actionName: $record->actionName,
            actorId: $record->actorId,
            recordIds: $recordIds,
            status: $status,
            processedCount: $record->processedCount,
            failedCount: $record->failedCount,
            startedAt: $record->startedAt,
            completedAt: $record->completedAt,
            errorMessage: $record->errorMessage,
        );
    }
}
