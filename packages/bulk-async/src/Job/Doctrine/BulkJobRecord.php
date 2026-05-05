<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Job\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for the `polysource_bulk_jobs` table.
 *
 * Indexes optimise the canonical queries:
 *  - `(created_at)` — chronological scrolling
 *  - `(actor_id, created_at)` — "my recent jobs"
 *  - `(status)` — "all running / failed jobs across the system"
 *
 * @since 0.1.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'polysource_bulk_jobs')]
#[ORM\Index(name: 'polysource_bulk_jobs_created_idx', columns: ['created_at'])]
#[ORM\Index(name: 'polysource_bulk_jobs_actor_idx', columns: ['actor_id', 'created_at'])]
#[ORM\Index(name: 'polysource_bulk_jobs_status_idx', columns: ['status'])]
class BulkJobRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    public DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'resource_name', type: 'string', length: 120)]
    public string $resourceName;

    #[ORM\Column(name: 'action_name', type: 'string', length: 120)]
    public string $actionName;

    #[ORM\Column(name: 'actor_id', type: 'string', length: 120)]
    public string $actorId;

    /** JSON list<string> — record ids the job targets. */
    #[ORM\Column(name: 'record_ids_json', type: 'text')]
    public string $recordIdsJson;

    #[ORM\Column(type: 'string', length: 16)]
    public string $status;

    #[ORM\Column(name: 'processed_count', type: 'integer')]
    public int $processedCount = 0;

    #[ORM\Column(name: 'failed_count', type: 'integer')]
    public int $failedCount = 0;

    #[ORM\Column(name: 'started_at', type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $startedAt = null;

    #[ORM\Column(name: 'completed_at', type: 'datetime_immutable', nullable: true)]
    public ?DateTimeImmutable $completedAt = null;

    #[ORM\Column(name: 'error_message', type: 'text', nullable: true)]
    public ?string $errorMessage = null;
}
