<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\InMemory;

use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStorageInterface;

/**
 * Test-only implementation of {@see BulkJobStorageInterface}.
 *
 * Records every `save()` so tests can assert the throttled flush
 * behaviour of {@see \Polysource\BulkAsync\Messenger\BulkJobHandler}
 * without spinning up a Doctrine fixture.
 */
final class InMemoryBulkJobStorage implements BulkJobStorageInterface
{
    /** @var array<string, BulkJob> */
    private array $jobs = [];

    /** @var list<BulkJob> */
    public array $saves = [];

    public function save(BulkJob $job): void
    {
        $this->jobs[$job->id] = $job;
        $this->saves[] = $job;
    }

    public function find(string $id): ?BulkJob
    {
        return $this->jobs[$id] ?? null;
    }

    public function listForActor(string $actorId, int $limit = 50): array
    {
        $matching = array_values(array_filter(
            $this->jobs,
            static fn (BulkJob $j): bool => $j->actorId === $actorId,
        ));

        usort(
            $matching,
            static fn (BulkJob $a, BulkJob $b): int => $b->createdAt <=> $a->createdAt,
        );

        return \array_slice($matching, 0, $limit);
    }

    /**
     * Direct manipulation hook for tests that need to flip a job's
     * status mid-iteration (cancellation simulation).
     */
    public function poke(BulkJob $job): void
    {
        $this->jobs[$job->id] = $job;
    }
}
