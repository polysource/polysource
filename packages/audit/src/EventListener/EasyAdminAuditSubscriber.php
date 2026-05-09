<?php

declare(strict_types=1);

namespace Polysource\Audit\EventListener;

use DateTimeImmutable;
use DateTimeZone;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityDeletedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityPersistedEvent;
use EasyCorp\Bundle\EasyAdminBundle\Event\AfterEntityUpdatedEvent;
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
 *   - `AfterEntityPersistedEvent` → `actionName = "create"`
 *   - `AfterEntityUpdatedEvent`   → `actionName = "update"`
 *   - `AfterEntityDeletedEvent`   → `actionName = "delete"`
 *
 * `resourceName` is set to the entity's FQCN (matches saved-view
 * scoping on EA pages, cf. ADR-019 §EA-bridge — both use FQCN as the
 * scoping key). `recordIds` is best-effort: tries `getId()` first,
 * falls back to `getUuid()`, then any public `id`/`uuid` property,
 * then empty list. Hosts whose entities use exotic identifiers can
 * subclass this listener and override `extractIdentifier()`.
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
    public function __construct(
        private readonly AuditLoggerInterface $logger,
        private readonly AuditActorInterface $actor,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AfterEntityPersistedEvent::class => 'onPersisted',
            AfterEntityUpdatedEvent::class => 'onUpdated',
            AfterEntityDeletedEvent::class => 'onDeleted',
        ];
    }

    /**
     * @param AfterEntityPersistedEvent<object> $event
     */
    public function onPersisted(AfterEntityPersistedEvent $event): void
    {
        $this->record($event->getEntityInstance(), 'create');
    }

    /**
     * @param AfterEntityUpdatedEvent<object> $event
     */
    public function onUpdated(AfterEntityUpdatedEvent $event): void
    {
        $this->record($event->getEntityInstance(), 'update');
    }

    /**
     * @param AfterEntityDeletedEvent<object> $event
     */
    public function onDeleted(AfterEntityDeletedEvent $event): void
    {
        $this->record($event->getEntityInstance(), 'delete');
    }

    private function record(object $entity, string $actionName): void
    {
        $entry = new AuditEntry(
            id: Uuid::v7()->toRfc4122(),
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            actorId: $this->actor->getActorId(),
            actorLabel: $this->actor->getActorLabel(),
            resourceName: $entity::class,
            actionName: $actionName,
            recordIds: self::extractIdentifier($entity),
            outcome: AuditOutcome::Success,
            message: null,
            durationMs: 0,
            context: $this->buildContext(),
        );

        $this->logger->log($entry);
    }

    /**
     * Best-effort identifier extraction. Returns a list with the
     * stringified id, or an empty list when no recognised identifier
     * is exposed. Reflection-only — no Doctrine metadata coupling so
     * the subscriber stays useful for entities that aren't Doctrine-
     * managed (rare in EA contexts but possible with custom data
     * sources).
     *
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
                // Method threw (e.g. uninitialised property) — try the next strategy.
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
                // Property uninitialised or access denied — skip and fall through.
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

    /**
     * @return array<string, mixed>
     */
    private function buildContext(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        return [
            'ip' => $request->getClientIp(),
            'userAgent' => self::headerOrNull($request, 'User-Agent'),
            'requestId' => self::requestId($request),
        ];
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
