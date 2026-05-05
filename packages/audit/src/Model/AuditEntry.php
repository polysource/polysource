<?php

declare(strict_types=1);

namespace Polysource\Audit\Model;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

/**
 * One row of the audit log — captures who did what to which records,
 * when, and how it went. Immutable.
 *
 * Anonymous actor convention: when no Symfony user is bound to the
 * request (cron, public endpoint), `actorId` MUST be the literal
 * sentinel `'__anonymous__'`. We deliberately keep this as a string
 * (not nullable) so downstream queries / aggregations can group on
 * `actorId` without `IS NULL` checks scattered everywhere.
 *
 * Constructor validates:
 *  - id, actorId, resourceName, actionName non-empty
 *  - durationMs >= 0
 *  - recordIds is a list of non-empty strings
 *  - occurredAt is normalised to UTC (we re-create it in UTC if the
 *    caller hands us a different timezone — audit logs cross
 *    timezones and must be comparable)
 *
 * @see AuditOutcome
 */
final class AuditEntry
{
    public const ANONYMOUS_ACTOR_ID = '__anonymous__';

    public readonly DateTimeImmutable $occurredAt;

    /** @var list<string> */
    public readonly array $recordIds;

    /** @var array<string, mixed> */
    public readonly array $context;

    /**
     * @param list<string>         $recordIds — empty list for global actions (e.g. "Export CSV")
     * @param array<string, mixed> $context   — IP / user agent / requestId / action context (free-form)
     */
    public function __construct(
        public readonly string $id,
        DateTimeImmutable $occurredAt,
        public readonly string $actorId,
        public readonly ?string $actorLabel,
        public readonly string $resourceName,
        public readonly string $actionName,
        array $recordIds,
        public readonly AuditOutcome $outcome,
        public readonly ?string $message,
        public readonly int $durationMs,
        array $context = [],
    ) {
        if ('' === $id) {
            throw new InvalidArgumentException('AuditEntry id cannot be empty.');
        }
        if ('' === $actorId) {
            throw new InvalidArgumentException('AuditEntry actorId cannot be empty (use AuditEntry::ANONYMOUS_ACTOR_ID for unauthenticated calls).');
        }
        if ('' === $resourceName) {
            throw new InvalidArgumentException('AuditEntry resourceName cannot be empty.');
        }
        if ('' === $actionName) {
            throw new InvalidArgumentException('AuditEntry actionName cannot be empty.');
        }
        if ($durationMs < 0) {
            throw new InvalidArgumentException(\sprintf('AuditEntry durationMs must be >= 0, got %d.', $durationMs));
        }
        if (!array_is_list($recordIds)) {
            throw new InvalidArgumentException('AuditEntry recordIds must be a list (positional array).');
        }
        foreach ($recordIds as $i => $recordId) {
            if (!\is_string($recordId) || '' === $recordId) {
                throw new InvalidArgumentException(\sprintf('AuditEntry recordIds[%d] must be a non-empty string.', $i));
            }
        }

        // UTC-normalise. Comparing audit rows across timezones requires
        // a single anchor — picking UTC over the host's default keeps
        // the storage layer trivial and avoids leaking server-local
        // offsets into the persisted JSON context.
        $utc = new DateTimeZone('UTC');
        $this->occurredAt = $occurredAt->getTimezone()->getName() === 'UTC'
            ? $occurredAt
            : $occurredAt->setTimezone($utc);

        $this->recordIds = $recordIds;
        $this->context = $context;
    }

    /**
     * Convenience factory for the most common case: an action started
     * "now" with the request's actor. Useful in tests + the action
     * subscriber.
     *
     * @param list<string>         $recordIds
     * @param array<string, mixed> $context
     */
    public static function nowFor(
        string $id,
        string $actorId,
        ?string $actorLabel,
        string $resourceName,
        string $actionName,
        AuditOutcome $outcome,
        ?string $message = null,
        int $durationMs = 0,
        array $recordIds = [],
        array $context = [],
    ): self {
        return new self(
            id: $id,
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            actorId: $actorId,
            actorLabel: $actorLabel,
            resourceName: $resourceName,
            actionName: $actionName,
            recordIds: $recordIds,
            outcome: $outcome,
            message: $message,
            durationMs: $durationMs,
            context: $context,
        );
    }
}
