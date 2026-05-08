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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * JSON progress endpoint backing the Stimulus polling fallback (cf.
 * ADR-024 §9). Mercure broadcasts deliver the same shape via SSE
 * when wired.
 *
 * Two-stage permission gating:
 *  1. {@see BulkJobResource::PERMISSION_VIEW} — coarse gate any
 *     authenticated operator must hold (typical: SRE / ops dashboard).
 *  2. **Ownership** — once the job is loaded, the requester must
 *     either own it (`$job->actorId == currentUserIdentifier`) or
 *     hold {@see BulkJobResource::PERMISSION_VIEW_ANY} (admin-style
 *     override). Without this second stage, any user with the coarse
 *     gate could observe other users' bulk-job progress in real time
 *     (sensitive: actorId, errorMessage, record counts) — the
 *     pre-v0.1.0 security audit flagged this as a horizontal access
 *     leak.
 *
 * If no {@see TokenStorageInterface} is wired (no Symfony Security
 * firewall — typically tests or hosts running their own ACL), the
 * controller falls back to the coarse gate alone. Production hosts
 * always have a firewall and therefore always go through both stages.
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
        private readonly ?TokenStorageInterface $tokenStorage = null,
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

        $this->assertOwnerOrAdmin($job);

        $response = new JsonResponse(self::serialise($job));
        $response->headers->addCacheControlDirective('no-cache');
        $response->headers->addCacheControlDirective('no-store');
        $response->headers->addCacheControlDirective('must-revalidate');

        return $response;
    }

    /**
     * Ownership stage of the gate. If the host wires no Symfony
     * Security firewall (no token storage) we cannot identify the
     * requester — the coarse gate stands alone. With Security wired,
     * non-owners need {@see BulkJobResource::PERMISSION_VIEW_ANY}.
     */
    private function assertOwnerOrAdmin(BulkJob $job): void
    {
        if ($this->permission->isGranted(BulkJobResource::PERMISSION_VIEW_ANY)) {
            return;
        }

        $currentActorId = $this->tokenStorage?->getToken()?->getUserIdentifier();
        if (null === $currentActorId) {
            return; // no firewall — coarse gate suffices
        }

        if ($currentActorId !== $job->actorId) {
            throw new AccessDeniedHttpException(\sprintf('Bulk job "%s" belongs to another actor; the %s attribute is required to view it.', $job->id, BulkJobResource::PERMISSION_VIEW_ANY));
        }
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
