<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Controller;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Controller\ProgressController;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Tests\InMemory\InMemoryBulkJobStorage;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProgressControllerTest extends TestCase
{
    public function testReturnsJsonShapeForExistingJob(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $job = $this->makeJob(BulkJobStatus::Running)->withProgress(2, 1);
        $storage->save($job);

        $controller = new ProgressController($storage, $this->grantingChecker());
        $response = $controller($job->id);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(200, $response->getStatusCode());

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($job->id, $payload['id']);
        self::assertSame('running', $payload['status']);
        self::assertSame(2, $payload['processed']);
        self::assertSame(1, $payload['failed']);
        self::assertSame(3, $payload['total']);
        self::assertEqualsWithDelta(2 / 3, $payload['progress'], 0.0001);
        self::assertNull($payload['startedAt']);
        self::assertNull($payload['completedAt']);
        self::assertNull($payload['errorMessage']);
    }

    public function testCacheHeadersDisablePersistence(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $storage->save($this->makeJob(BulkJobStatus::Pending));

        $response = (new ProgressController($storage, $this->grantingChecker()))('job-progress-test');

        $cacheControl = $response->headers->get('Cache-Control') ?? '';
        self::assertStringContainsString('no-cache', $cacheControl);
        self::assertStringContainsString('no-store', $cacheControl);
        self::assertStringContainsString('must-revalidate', $cacheControl);
    }

    public function testReturns404WhenJobMissing(): void
    {
        $controller = new ProgressController(new InMemoryBulkJobStorage(), $this->grantingChecker());

        $this->expectException(NotFoundHttpException::class);
        $controller('does-not-exist');
    }

    public function testReturns403WhenPermissionDenied(): void
    {
        $controller = new ProgressController(new InMemoryBulkJobStorage(), $this->denyingChecker());

        $this->expectException(AccessDeniedHttpException::class);
        $controller('any-id');
    }

    public function testSerialisesTerminalJobWithCompletionStamps(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $now = new DateTimeImmutable('2026-05-05T11:00:00', new DateTimeZone('UTC'));
        $job = $this->makeJob(BulkJobStatus::Completed)
            ->withProgress(3, 0)
            ->withStartedAt($now)
            ->withCompletedAt($now);
        $storage->save($job);

        $response = (new ProgressController($storage, $this->grantingChecker()))($job->id);
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame('completed', $payload['status']);
        self::assertEqualsWithDelta(1.0, $payload['progress'], 0.0001);
        self::assertSame($now->format(\DATE_ATOM), $payload['startedAt']);
        self::assertSame($now->format(\DATE_ATOM), $payload['completedAt']);
    }

    private function makeJob(BulkJobStatus $status): BulkJob
    {
        return new BulkJob(
            id: 'job-progress-test',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'orders',
            actionName: 'retry-all',
            actorId: 'alice',
            recordIds: ['r-1', 'r-2', 'r-3'],
            status: $status,
        );
    }

    private function grantingChecker(): PermissionInterface
    {
        $checker = $this->createMock(PermissionInterface::class);
        $checker->method('isGranted')->willReturn(true);

        return $checker;
    }

    private function denyingChecker(): PermissionInterface
    {
        $checker = $this->createMock(PermissionInterface::class);
        $checker->method('isGranted')->willReturn(false);

        return $checker;
    }
}
