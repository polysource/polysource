<?php

declare(strict_types=1);

namespace Polysource\Audit\EventListener;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeEntityUpdatedEvent;
use Polysource\Audit\Logger\AuditLoggerInterface;
use Polysource\Audit\Model\AuditActorInterface;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Stringable;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Bridges EasyAdmin's entity lifecycle events into the audit trail.
 *
 * Without this subscriber, EA CRUD edits (Edit / New / Delete on
 * Doctrine entities through `AbstractCrudController`) bypass
 * `polysource/symfony-bundle::ActionController` and therefore never
 * fire `ActionExecutedEvent` — `ActionAuditSubscriber` would never see
 * them and the audit log would only carry Polysource-native action
 * traces (failed-message retry, bulk-job cancel, workflow transitions).
 *
 * One audit entry per EA event:
 *   - `AfterEntityPersistedEvent` → `actionName = "create"`,
 *     `message` = comma-separated `field=value` snapshot of inserted columns,
 *     `context.changes` = `{field: {old: null, new: <value>}}`.
 *   - `AfterEntityUpdatedEvent`   → `actionName = "update"`,
 *     `message` = comma-separated `field: 'old' → 'new'` summary,
 *     `context.changes` = `{field: {old: <old>, new: <new>}}`.
 *   - `AfterEntityDeletedEvent`   → `actionName = "delete"`,
 *     `message` = comma-separated `field=value` snapshot of the removed row,
 *     `context.snapshot` = full property map at deletion time.
 *
 * Capture mechanism: the change set must be read BEFORE Doctrine flushes
 * (`AfterEntity*Event` fires post-flush, when the unit of work has
 * cleared its dirty map). The subscriber listens to both `Before*` and
 * `After*` variants — captures the change set in the Before phase under
 * the entity's `spl_object_id`, then reads + clears it in the After
 * phase to materialise the audit entry. If the flush throws, the
 * captured state is GC-ed naturally on the next request — we never log
 * a half-applied change.
 *
 * Service registration is gated on
 * `class_exists(AfterEntityUpdatedEvent::class)` so hosts without
 * EasyAdmin installed don't autoload its symbols. EA is a `suggest`
 * dependency in `polysource/audit`'s composer.json.
 *
 * @since 0.1.0
 */
final class EasyAdminAuditSubscriber implements EventSubscriberInterface
{
    /**
     * Maximum length of the human-readable diff summary stored in
     * `AuditEntry::message`. Anything longer is truncated with a
     * trailing "… [truncated]" — the full structured diff still goes
     * into `context.changes` / `context.snapshot` (which has no length
     * cap because it's stored as JSON in a `text` column).
     */
    public const MAX_MESSAGE_BYTES = 1024;

    /**
     * Per-request buffer of captured change sets, keyed by
     * `spl_object_id($entity)`. Cleared at the end of each After*
     * handler so two consecutive edits don't leak across requests on
     * a long-running worker.
     *
     * @var array<int, array{action: string, changes?: array<string, array{old: mixed, new: mixed}>, snapshot?: array<string, mixed>}>
     */
    private array $pending = [];

    public function __construct(
        private readonly AuditLoggerInterface $logger,
        private readonly AuditActorInterface $actor,
        private readonly RequestStack $requestStack,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BeforeEntityPersistedEvent::class => 'onBeforePersisted',
            BeforeEntityUpdatedEvent::class => 'onBeforeUpdated',
            BeforeEntityDeletedEvent::class => 'onBeforeDeleted',
            AfterEntityPersistedEvent::class => 'onPersisted',
            AfterEntityUpdatedEvent::class => 'onUpdated',
            AfterEntityDeletedEvent::class => 'onDeleted',
        ];
    }

    /**
     * @param BeforeEntityPersistedEvent<object> $event
     */
    public function onBeforePersisted(BeforeEntityPersistedEvent $event): void
    {
        // INSERT — UnitOfWork::computeChangeSets() must run for the
        // entity to appear in the scheduled insertions list. The
        // changeset for a fresh entity is `[null, $newValue]` per
        // field — equivalent to a "snapshot of inserted columns".
        $entity = $event->getEntityInstance();
        $this->em->getUnitOfWork()->computeChangeSets();
        /** @var array<string, array{mixed, mixed}> $changeSet — Doctrine docblock returns mixed-pair tuples; PersistentCollection entries are filtered out by normaliseChangeSet. */
        $changeSet = $this->em->getUnitOfWork()->getEntityChangeSet($entity);

        $this->pending[spl_object_id($entity)] = [
            'action' => 'create',
            'changes' => self::normaliseChangeSet($changeSet),
        ];
    }

    /**
     * @param BeforeEntityUpdatedEvent<object> $event
     */
    public function onBeforeUpdated(BeforeEntityUpdatedEvent $event): void
    {
        $entity = $event->getEntityInstance();
        $this->em->getUnitOfWork()->computeChangeSets();
        /** @var array<string, array{mixed, mixed}> $changeSet — Doctrine docblock returns mixed-pair tuples; PersistentCollection entries are filtered out by normaliseChangeSet. */
        $changeSet = $this->em->getUnitOfWork()->getEntityChangeSet($entity);

        $this->pending[spl_object_id($entity)] = [
            'action' => 'update',
            'changes' => self::normaliseChangeSet($changeSet),
        ];
    }

    /**
     * @param BeforeEntityDeletedEvent<object> $event
     */
    public function onBeforeDeleted(BeforeEntityDeletedEvent $event): void
    {
        // Doctrine has no "delete change set"; capture a snapshot of
        // the entity's mapped scalar fields instead. Useful for "what
        // did the row contain when we deleted it" forensics.
        $entity = $event->getEntityInstance();
        $this->pending[spl_object_id($entity)] = [
            'action' => 'delete',
            'snapshot' => $this->snapshotEntity($entity),
        ];
    }

    /**
     * @param AfterEntityPersistedEvent<object> $event
     */
    public function onPersisted(AfterEntityPersistedEvent $event): void
    {
        $this->emit($event->getEntityInstance(), 'create');
    }

    /**
     * @param AfterEntityUpdatedEvent<object> $event
     */
    public function onUpdated(AfterEntityUpdatedEvent $event): void
    {
        $this->emit($event->getEntityInstance(), 'update');
    }

    /**
     * @param AfterEntityDeletedEvent<object> $event
     */
    public function onDeleted(AfterEntityDeletedEvent $event): void
    {
        $this->emit($event->getEntityInstance(), 'delete');
    }

    private function emit(object $entity, string $expectedAction): void
    {
        $oid = spl_object_id($entity);
        $captured = $this->pending[$oid] ?? null;
        unset($this->pending[$oid]);

        // Captured action mismatch shouldn't happen unless EA changes
        // its event ordering. Fall back to "no diff" rather than
        // dropping the audit entry entirely.
        $changes = ($captured['action'] ?? null) === $expectedAction
            ? ($captured['changes'] ?? null)
            : null;
        $snapshot = ($captured['action'] ?? null) === $expectedAction
            ? ($captured['snapshot'] ?? null)
            : null;

        $entry = new AuditEntry(
            id: Uuid::v7()->toRfc4122(),
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            actorId: $this->actor->getActorId(),
            actorLabel: $this->actor->getActorLabel(),
            resourceName: $entity::class,
            actionName: $expectedAction,
            recordIds: self::extractIdentifier($entity),
            outcome: AuditOutcome::Success,
            message: self::summariseDiff($expectedAction, $changes, $snapshot),
            durationMs: 0,
            context: $this->buildContext($changes, $snapshot),
        );

        $this->logger->log($entry);
    }

    /**
     * Human-readable single-line diff summary for the `message` column
     * — appears in the CSV export, the dropdown, and tail-style queries.
     * Capped at {@see self::MAX_MESSAGE_BYTES}.
     *
     * @param array<string, array{old: mixed, new: mixed}>|null $changes
     * @param array<string, mixed>|null $snapshot
     */
    private static function summariseDiff(string $action, ?array $changes, ?array $snapshot): ?string
    {
        if ('delete' === $action && null !== $snapshot && [] !== $snapshot) {
            $parts = [];
            foreach ($snapshot as $field => $value) {
                $parts[] = $field . '=' . self::formatScalar($value);
            }

            return self::truncate(implode(', ', $parts));
        }

        if (null === $changes || [] === $changes) {
            return null;
        }

        $parts = [];
        foreach ($changes as $field => $delta) {
            if ('create' === $action) {
                $parts[] = $field . '=' . self::formatScalar($delta['new']);
                continue;
            }
            $parts[] = \sprintf(
                '%s: %s → %s',
                $field,
                self::formatScalar($delta['old']),
                self::formatScalar($delta['new']),
            );
        }

        return self::truncate(implode(', ', $parts));
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}>|null $changes
     * @param array<string, mixed>|null $snapshot
     *
     * @return array<string, mixed>
     */
    private function buildContext(?array $changes, ?array $snapshot): array
    {
        $request = $this->requestStack->getCurrentRequest();

        $context = null === $request ? [] : [
            'ip' => $request->getClientIp(),
            'userAgent' => self::headerOrNull($request, 'User-Agent'),
            'requestId' => self::requestId($request),
        ];

        if (null !== $changes && [] !== $changes) {
            $context['changes'] = $changes;
        }
        if (null !== $snapshot && [] !== $snapshot) {
            $context['snapshot'] = $snapshot;
        }

        return $context;
    }

    /**
     * Normalise Doctrine's change set to a plain array shape that
     * survives JSON encoding without leaking entity references.
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private static function normaliseChangeSet(array $changeSet): array
    {
        $out = [];
        foreach ($changeSet as $field => $pair) {
            if (!\is_array($pair) || !\array_key_exists(0, $pair) || !\array_key_exists(1, $pair)) {
                continue;
            }
            $out[$field] = [
                'old' => self::serialiseValue($pair[0]),
                'new' => self::serialiseValue($pair[1]),
            ];
        }

        return $out;
    }

    /**
     * Reflection-based snapshot of mapped scalar properties — used at
     * delete time when Doctrine has no change set to offer. Skips
     * relations (objects) at top level; consumers wanting deep-dump
     * snapshots subclass and override.
     *
     * @return array<string, mixed>
     */
    private function snapshotEntity(object $entity): array
    {
        try {
            $metadata = $this->em->getClassMetadata($entity::class);
        } catch (Throwable) {
            return [];
        }

        $snapshot = [];
        foreach ($metadata->getFieldNames() as $field) {
            try {
                $value = $metadata->getFieldValue($entity, $field);
            } catch (Throwable) {
                continue;
            }
            $snapshot[$field] = self::serialiseValue($value);
        }

        return $snapshot;
    }

    /**
     * Coerce arbitrary Doctrine column values to JSON-friendly scalars.
     */
    private static function serialiseValue(mixed $value): mixed
    {
        if (null === $value || \is_scalar($value)) {
            return $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (\is_array($value)) {
            return array_map(self::serialiseValue(...), $value);
        }

        // Entity reference / object without __toString — record the
        // class for forensic context, drop the body.
        if (\is_object($value)) {
            return '[object ' . $value::class . ']';
        }

        return null;
    }

    private static function formatScalar(mixed $value): string
    {
        if (null === $value) {
            return 'null';
        }
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (\is_string($value)) {
            return "'" . $value . "'";
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if (\is_array($value)) {
            return 'array(' . \count($value) . ')';
        }

        return '?';
    }

    private static function truncate(string $message): string
    {
        if (\strlen($message) <= self::MAX_MESSAGE_BYTES) {
            return $message;
        }

        return substr($message, 0, self::MAX_MESSAGE_BYTES) . '… [truncated]';
    }

    /**
     * @return list<string>
     */
    private static function extractIdentifier(object $entity): array
    {
        foreach (['getId', 'getUuid'] as $method) {
            if (!method_exists($entity, $method)) {
                continue;
            }
            try {
                /** @phpstan-ignore-next-line method.dynamicName — host entities may declare either method */
                $value = self::stringifyOrNull($entity->{$method}());
                if (null !== $value) {
                    return [$value];
                }
            } catch (Throwable) {
                // Method threw — try the next strategy.
            }
        }

        foreach (['id', 'uuid'] as $property) {
            if (!property_exists($entity, $property)) {
                continue;
            }
            try {
                $value = self::stringifyOrNull($entity->{$property} ?? null);
                if (null !== $value) {
                    return [$value];
                }
            } catch (Throwable) {
                // Property uninitialised — skip.
            }
        }

        return [];
    }

    private static function stringifyOrNull(mixed $value): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }
        if (\is_scalar($value)) {
            return (string) $value;
        }
        if ($value instanceof Stringable) {
            $stringified = (string) $value;

            return '' === $stringified ? null : $stringified;
        }

        return null;
    }

    private static function headerOrNull(Request $request, string $name): ?string
    {
        $value = $request->headers->get($name);

        return null === $value || '' === $value ? null : $value;
    }

    private static function requestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-Id');
        if (\is_string($header) && '' !== $header) {
            return $header;
        }

        return Uuid::v4()->toRfc4122();
    }
}
