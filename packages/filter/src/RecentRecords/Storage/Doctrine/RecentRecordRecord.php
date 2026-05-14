<?php

declare(strict_types=1);

namespace Polysource\Filter\RecentRecords\Storage\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for the `polysource_recent_records` table.
 *
 * Composite primary key on (owner_id, resource_name, record_id) —
 * upserting the same triplet just updates `viewed_at`/`label`.
 *
 * Hosts must run a Doctrine migration to create the table; the
 * canonical SQL ships in
 * `docs/user/filter/recent-records.md`.
 *
 * @since 0.5.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'polysource_recent_records')]
#[ORM\Index(name: 'polysource_recent_records_viewed_idx', columns: ['viewed_at'])]
class RecentRecordRecord
{
    #[ORM\Id]
    #[ORM\Column(name: 'owner_id', type: 'string', length: 128)]
    public string $ownerId;

    #[ORM\Id]
    #[ORM\Column(name: 'resource_name', type: 'string', length: 128)]
    public string $resourceName;

    #[ORM\Id]
    #[ORM\Column(name: 'record_id', type: 'string', length: 128)]
    public string $recordId;

    #[ORM\Column(name: 'viewed_at', type: 'datetime_immutable')]
    public DateTimeImmutable $viewedAt;

    #[ORM\Column(name: 'label', type: 'string', length: 255, nullable: true)]
    public ?string $label = null;
}
