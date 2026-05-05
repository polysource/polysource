<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Job;

/**
 * Persistence contract for {@see BulkJob}.
 *
 * Default impl is {@see DoctrineBulkJobStorage} (gated on Doctrine
 * availability per ADR-024 §4). Hosts on alternate stores
 * (Redis-backed for high-volume ops, in-memory for tests) ship
 * their own implementation under the same alias.
 */
interface BulkJobStorageInterface
{
    /**
     * Insert or update — idempotent on `id`.
     */
    public function save(BulkJob $job): void;

    public function find(string $id): ?BulkJob;

    /**
     * Most-recent-first list of jobs dispatched by the given actor.
     *
     * @return list<BulkJob>
     */
    public function listForActor(string $actorId, int $limit = 50): array;
}
