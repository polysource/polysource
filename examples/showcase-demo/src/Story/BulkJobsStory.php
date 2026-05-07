<?php

declare(strict_types=1);

namespace App\Story;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Job\Doctrine\BulkJobRecord;
use Symfony\Component\Uid\Uuid;
use Zenstruck\Foundry\Story;

/**
 * Seed 8 bulk jobs covering every BulkJobStatus state so the
 * /admin/polysource/bulk-jobs index renders a realistic mix.
 *
 * Includes one Running job with partial progress (3/127 processed)
 * to show off the live progress UI when the user clicks into it.
 */
final class BulkJobsStory extends Story
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function build(): void
    {
        $jobs = [
            ['Pending', 'failed-messages', 'retry-all', 50, null, null, 0, 0, null],
            ['Running', 'failed-messages', 'retry-all', 127, '-3 minutes', null, 3, 0, null],
            ['Completed', 'failed-messages', 'retry-all', 18, '-2 hours', '-1 hour 45 minutes', 18, 0, null],
            ['Completed', 'search-index', 'reindex-all', 200, '-1 day', '-23 hours', 200, 0, null],
            ['Failed', 'failed-messages', 'retry-all', 312, '-5 hours', '-4 hours 30 minutes', 290, 22, 'Bulk action terminated with 22 failures.'],
            ['Cancelled', 'search-index', 'reindex-all', 5000, '-2 days', '-1 day 23 hours 45 minutes', 1842, 0, null],
            ['Completed', 'cache-keys', 'flush-all', 30, '-6 hours', '-5 hours 59 minutes', 30, 0, null],
            ['Completed', 'failed-messages', 'dismiss-all', 50, '-3 days', '-2 days 23 hours 50 minutes', 50, 0, null],
        ];

        foreach ($jobs as [$status, $resource, $action, $count, $startedAt, $completedAt, $processed, $failed, $errorMsg]) {
            $record = new BulkJobRecord();
            $record->id = Uuid::v7()->toRfc4122();
            $record->createdAt = new DateTimeImmutable(\sprintf('-%d hours', random_int(1, 72)));
            $record->resourceName = $resource;
            $record->actionName = $action;
            $record->actorId = 'admin@shop.co';
            $record->recordIdsJson = json_encode(
                array_map(static fn () => Uuid::v7()->toRfc4122(), range(1, min($count, 10))),
                \JSON_THROW_ON_ERROR,
            );
            $record->status = strtolower($status);
            $record->processedCount = $processed;
            $record->failedCount = $failed;
            $record->startedAt = $startedAt !== null ? new DateTimeImmutable($startedAt) : null;
            $record->completedAt = $completedAt !== null ? new DateTimeImmutable($completedAt) : null;
            $record->errorMessage = $errorMsg;

            $this->em->persist($record);
        }

        $this->em->flush();

        // Suppress unused-import warning when BulkJobStatus is checked
        // by polysource at runtime — we write the lowercase status value
        // directly because the entity column is a plain string, but the
        // import documents the canonical case names.
        \assert(class_exists(BulkJobStatus::class));
    }
}
