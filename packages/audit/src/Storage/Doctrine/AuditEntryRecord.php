<?php

declare(strict_types=1);

namespace Polysource\Audit\Storage\Doctrine;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Doctrine entity for the `polysource_audit_log` table.
 *
 * Kept separate from {@see \Polysource\Audit\Model\AuditEntry} on
 * purpose: the VO carries domain semantics (immutability, UTC
 * normalisation, invariants), the record carries persistence
 * concerns (column mapping, JSON serialisation of `recordIds` /
 * `context`).
 *
 * Conversion VO ↔ record lives in
 * {@see \Polysource\Audit\Logger\DoctrineAuditLogger}.
 *
 * Hosts run their own Doctrine migration to create the table — the
 * recommended SQL ships in `docs/user/audit/install.md`. Polysource
 * does NOT auto-migrate (cf. ADR-020 §"Conséquences" — same
 * convention as ADR-019 saved views).
 *
 * @since 0.1.0
 */
#[ORM\Entity]
#[ORM\Table(name: 'polysource_audit_log')]
#[ORM\Index(name: 'polysource_audit_log_occurred_idx', columns: ['occurred_at'])]
#[ORM\Index(name: 'polysource_audit_log_actor_resource_idx', columns: ['actor_id', 'resource_name'])]
#[ORM\Index(name: 'polysource_audit_log_resource_action_idx', columns: ['resource_name', 'action_name'])]
class AuditEntryRecord
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(name: 'occurred_at', type: 'datetime_immutable')]
    public DateTimeImmutable $occurredAt;

    #[ORM\Column(name: 'actor_id', type: 'string', length: 120)]
    public string $actorId;

    #[ORM\Column(name: 'actor_label', type: 'string', length: 120, nullable: true)]
    public ?string $actorLabel = null;

    #[ORM\Column(name: 'resource_name', type: 'string', length: 120)]
    public string $resourceName;

    #[ORM\Column(name: 'action_name', type: 'string', length: 120)]
    public string $actionName;

    /** JSON list<string> — record ids the action targeted (empty for global actions). */
    #[ORM\Column(name: 'record_ids_json', type: 'text')]
    public string $recordIdsJson;

    #[ORM\Column(type: 'string', length: 16)]
    public string $outcome;

    #[ORM\Column(type: 'text', nullable: true)]
    public ?string $message = null;

    #[ORM\Column(name: 'duration_ms', type: 'integer')]
    public int $durationMs = 0;

    /** JSON map<string, mixed> — IP / UA / RequestID / actionContext / errorClass / errorTrace. */
    #[ORM\Column(name: 'context_json', type: 'text')]
    public string $contextJson;
}
