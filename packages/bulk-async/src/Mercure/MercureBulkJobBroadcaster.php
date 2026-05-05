<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Mercure;

use Polysource\BulkAsync\Controller\ProgressController;
use Polysource\BulkAsync\Event\BulkJobProgressEvent;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Throwable;

/**
 * Mercure SSE bridge for {@see BulkJobProgressEvent} (cf. ADR-024 §8).
 *
 * Service registration is gated on
 * `class_exists(\Symfony\Component\Mercure\HubInterface)` so apps
 * without Mercure don't pay the runtime cost — they fall back to
 * Stimulus polling against {@see ProgressController}.
 *
 * Topic format (stable contract): `polysource/bulk-jobs/{id}`. The
 * payload mirrors {@see ProgressController::serialise()} so SSE
 * subscribers and polling clients consume identical JSON shape.
 *
 * Failure isolation: any Hub exception is caught + logged, never
 * re-thrown. A broken Mercure broker must not poison the worker
 * loop (the worker still persists the row through storage; polling
 * still serves it). This is the same try/catch contention pattern
 * the audit aggregator uses.
 */
final class MercureBulkJobBroadcaster implements EventSubscriberInterface
{
    public const TOPIC_TEMPLATE = 'polysource/bulk-jobs/%s';

    private LoggerInterface $logger;

    public function __construct(
        private readonly HubInterface $hub,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @return array<class-string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            BulkJobProgressEvent::class => 'onProgress',
        ];
    }

    public function onProgress(BulkJobProgressEvent $event): void
    {
        $job = $event->job;

        try {
            $update = new Update(
                topics: \sprintf(self::TOPIC_TEMPLATE, $job->id),
                data: (string) json_encode(ProgressController::serialise($job), \JSON_THROW_ON_ERROR),
            );
            $this->hub->publish($update);
        } catch (Throwable $e) {
            $this->logger->warning('Polysource: Mercure broadcast failed (worker continues).', [
                'job_id' => $job->id,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);
        }
    }
}
