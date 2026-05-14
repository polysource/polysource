<?php

declare(strict_types=1);

namespace Polysource\Filter\BulkActionHistory\Storage\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for the `polysource_bulk_action_history` table.
 *
 * Hosts must run a Doctrine migration to create the table; the
 * canonical SQL ships in
 * `docs/user/filter/bulk-action-history.md`.
 *
 * @since 0.5.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'polysource_bulk_action_history')]
#[ORM\Index(name: 'polysource_bulk_action_history_resource_idx', columns: ['resource_name'])]
#[ORM\Index(name: 'polysource_bulk_action_history_owner_idx', columns: ['owner_id'])]
#[ORM\Index(name: 'polysource_bulk_action_history_occurred_idx', columns: ['occurred_at'])]
class BulkActionHistoryRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 64)]
    public string $id;

    #[ORM\Column(name: 'owner_id', type: 'string', length: 128)]
    public string $ownerId;

    #[ORM\Column(name: 'resource_name', type: 'string', length: 128)]
    public string $resourceName;

    #[ORM\Column(name: 'action_name', type: 'string', length: 128)]
    public string $actionName;

    #[ORM\Column(name: 'affected_count', type: 'integer')]
    public int $affectedCount;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    public DateTimeImmutable $occurredAt;

    /** JSON-encoded free-form metadata. */
    #[ORM\Column(name: 'metadata_json', type: 'text', nullable: true)]
    public ?string $metadataJson = null;
}
