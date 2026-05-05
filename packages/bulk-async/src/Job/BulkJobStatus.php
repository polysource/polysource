<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Job;

/**
 * Lifecycle states a {@see BulkJob} flows through.
 *
 * Per ADR-024 §3:
 *  - `Pending`   — message dispatched, worker hasn't picked it up yet
 *  - `Running`   — worker started executing
 *  - `Completed` — all records done, zero failures (terminal)
 *  - `Failed`    — terminal but ≥1 record failed (still ran to the end)
 *  - `Cancelled` — operator stopped the job before completion
 *
 * The string backing values are the persisted contract on the
 * `polysource_bulk_jobs.status` column.
 */
enum BulkJobStatus: string
{
    case Pending = 'pending';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Failed, self::Cancelled => true,
            self::Pending, self::Running => false,
        };
    }
}
