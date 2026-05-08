<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Controller;

use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStorageInterface;
use Polysource\BulkAsync\Resource\BulkJobResource;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * JSON progress endpoint backing the Stimulus polling fallback (cf.
 * ADR-024 §9). Mercure broadcasts deliver the same shape via SSE
 * when wired.
 *
 * Permission gating mirrors {@see BulkJobResource}: anyone holding
 * `POLYSOURCE_BULK_JOB_VIEW` can read any job's progress. Hosts that
 * want stricter "actor-only" rules ship a custom voter for the
 * attribute and grant it conditionally.
 *
 * Response shape (stable contract — Mercure broadcaster mirrors it):
 *   {
 *     "id":             "<uuid>",
 *     "status":         "pending|running|completed|failed|cancelled",
 *     "processed":      <int>,
 *     "failed":         <int>,
 *     "total":          <int>,
 *     "progress":       <float 0..1>,
 *     "startedAt":      "<iso8601>"|null,
 *     "completedAt":    "<iso8601>"|null,
 *     "errorMessage":   "<string>"|null
 *   }
 *
 * Cache headers explicitly disable caching — operators see live
 * counts on every poll.
 */
final class ProgressController
{
    public function __construct(
        private readonly BulkJobStorageInterface $storage,
        private readonly PermissionInterface $permission,
    ) {
    }

    #[Route(
        path: '/admin/bulk-jobs/{id}/progress',
        name: 'polysource_bulk_async_progress',
        requirements: ['id' => '[0-9a-fA-F\-]{36}'],
        methods: ['GET'],
    )]
    public function __invoke(string $id): Response
    {
        if (!$this->permission->isGranted(BulkJobResource::PERMISSION_VIEW)) {
            throw new AccessDeniedHttpException(\sprintf('Access denied (attribute %s).', BulkJobResource::PERMISSION_VIEW));
        }

        $job = $this->storage->find($id);
        if (null === $job) {
            throw new NotFoundHttpException(\sprintf('Bulk job "%s" not found.', $id));
        }

        $response = new JsonResponse(self::serialise($job));
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    public static function serialise(BulkJob $job): array
    {
        return [
            'id' => $job->id,
            'status' => $job->status->value,
            'processed' => $job->processedCount,
            'failed' => $job->failedCount,
            'total' => $job->total(),
            'progress' => $job->progress(),
            'startedAt' => $job->startedAt?->format(\DATE_ATOM),
            'completedAt' => $job->completedAt?->format(\DATE_ATOM),
            'errorMessage' => $job->errorMessage,
        ];
    }
}
