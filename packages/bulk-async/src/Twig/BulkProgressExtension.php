<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Twig;

use Polysource\BulkAsync\Controller\ProgressController;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Mercure\MercureBulkJobBroadcaster;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Twig extension exposing:
 *  - `polysource_bulk_progress(BulkJob, mercureTopic = null)` —
 *    renders the Bootstrap progress card with embedded Stimulus
 *    bindings. When `mercureTopic` is provided the Stimulus
 *    controller subscribes via EventSource; otherwise it falls
 *    back to polling against {@see ProgressController} (cf.
 *    ADR-024 §9).
 *  - `polysource_bulk_progress_payload(BulkJob)` — returns the
 *    canonical progress JSON shape as an array (useful for hosts
 *    embedding the data in a custom template).
 *  - `polysource_bulk_progress_topic(BulkJob)` — returns the
 *    canonical Mercure topic string `polysource/bulk-jobs/{actorId}/{id}`.
 *    Use it when constructing the Mercure subscribe URL so client
 *    and broadcaster agree on the topic shape (including URL-encoded
 *    actor segment).
 */
final class BulkProgressExtension extends AbstractExtension
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $progressUrlTemplate = '/admin/bulk-jobs/%s/progress',
    ) {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('polysource_bulk_progress', $this->renderProgress(...), ['is_safe' => ['html']]),
            new TwigFunction('polysource_bulk_progress_payload', $this->payload(...)),
            new TwigFunction('polysource_bulk_progress_topic', $this->topic(...)),
        ];
    }

    /**
     * Canonical Mercure topic for the job (matches the broadcaster).
     */
    public function topic(BulkJob $job): string
    {
        return MercureBulkJobBroadcaster::topicFor($job->actorId, $job->id);
    }

    public function renderProgress(BulkJob $job, ?string $mercureTopic = null): string
    {
        return $this->twig->render('@PolysourceBulkAsync/_progress.html.twig', [
            'job' => $job,
            'payload' => ProgressController::serialise($job),
            'mercure_topic' => $mercureTopic,
            'progress_url' => \sprintf($this->progressUrlTemplate, $job->id),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(BulkJob $job): array
    {
        return ProgressController::serialise($job);
    }
}
