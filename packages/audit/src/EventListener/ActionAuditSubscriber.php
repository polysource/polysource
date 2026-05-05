<?php

declare(strict_types=1);

namespace Polysource\Audit\EventListener;

use DateTimeImmutable;
use DateTimeZone;
use Polysource\Audit\Logger\AuditLoggerInterface;
use Polysource\Audit\Model\AuditActorInterface;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Polysource\Bundle\Event\ActionExecutedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;

/**
 * Bridges {@see ActionExecutedEvent} from `polysource/symfony-bundle`
 * into {@see AuditEntry} rows logged through the `AuditLoggerInterface`
 * (typically the `AggregateAuditLogger`, cf. ADR-020 §5).
 *
 * The subscriber runs synchronously in the request-response cycle.
 * Failures inside any downstream logger are contained by the
 * aggregator — the subscriber itself only takes responsibility for:
 *  1. Building the entry from the event payload.
 *  2. Stamping actor + standard request context (IP / UA / RequestID).
 *  3. Mapping `ActionResult` + `Throwable` to {@see AuditOutcome}.
 *
 * Truncation: the exception trace is capped at `MAX_TRACE_BYTES` to
 * avoid blowing out the JSON column on a deeply-nested vendor stack.
 * Hosts that need full traces ship a custom subscriber + listener.
 */
final class ActionAuditSubscriber implements EventSubscriberInterface
{
    public const MAX_TRACE_BYTES = 8192;

    public function __construct(
        private readonly AuditLoggerInterface $logger,
        private readonly AuditActorInterface $actor,
    ) {
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ActionExecutedEvent::class => 'onActionExecuted',
        ];
    }

    public function onActionExecuted(ActionExecutedEvent $event): void
    {
        $entry = new AuditEntry(
            id: Uuid::v7()->toRfc4122(),
            occurredAt: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            actorId: $this->actor->getActorId(),
            actorLabel: $this->actor->getActorLabel(),
            resourceName: $event->resource->getName(),
            actionName: $event->action->getName(),
            recordIds: $event->recordIds,
            outcome: self::resolveOutcome($event),
            message: $event->result->message,
            durationMs: $event->durationMs,
            context: self::buildContext($event),
        );

        $this->logger->log($entry);
    }

    private static function resolveOutcome(ActionExecutedEvent $event): AuditOutcome
    {
        if (null !== $event->exception) {
            return AuditOutcome::Exception;
        }

        return $event->result->success ? AuditOutcome::Success : AuditOutcome::Failure;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildContext(ActionExecutedEvent $event): array
    {
        $context = [
            'ip' => self::clientIp($event->request),
            'userAgent' => self::userAgent($event->request),
            'requestId' => self::requestId($event->request),
        ];

        if ([] !== $event->result->context) {
            $context['actionContext'] = $event->result->context;
        }

        if (null !== $event->exception) {
            $trace = $event->exception->getTraceAsString();
            $context['errorClass'] = $event->exception::class;
            $context['errorTrace'] = \strlen($trace) > self::MAX_TRACE_BYTES
                ? substr($trace, 0, self::MAX_TRACE_BYTES) . '… [truncated]'
                : $trace;
        }

        return $context;
    }

    private static function clientIp(Request $request): ?string
    {
        // `getClientIp()` returns null for sub-requests / synthetic
        // requests. Audit accepts that — null IP just means "we don't
        // know" rather than "0.0.0.0".
        return $request->getClientIp();
    }

    private static function userAgent(Request $request): ?string
    {
        $ua = $request->headers->get('User-Agent');

        return null === $ua || '' === $ua ? null : $ua;
    }

    private static function requestId(Request $request): string
    {
        $header = $request->headers->get('X-Request-Id');
        if (\is_string($header) && '' !== $header) {
            return $header;
        }

        // No upstream id (no edge proxy stamping the header) — emit a
        // freshly-minted UUID v4 so the audit row still correlates
        // with downstream sinks (Sentry, Datadog) that we control via
        // their own SDK.
        return Uuid::v4()->toRfc4122();
    }
}
