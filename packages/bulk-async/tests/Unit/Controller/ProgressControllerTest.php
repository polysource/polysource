<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Controller;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Controller\ProgressController;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Resource\BulkJobResource;
use Polysource\BulkAsync\Tests\InMemory\InMemoryBulkJobStorage;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

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

    public function testReturns403WhenRequesterIsNotJobOwnerAndLacksViewAnyAttribute(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $storage->save($this->makeJob(BulkJobStatus::Running)); // owner: alice

        $controller = new ProgressController(
            $storage,
            $this->grantOnly(BulkJobResource::PERMISSION_VIEW),
            $this->tokenStorageFor('mallory'),
        );

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessageMatches('/belongs to another actor/');
        $controller('job-progress-test');
    }

    public function testGrantsAccessToJobOwnerEvenWithoutViewAny(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $storage->save($this->makeJob(BulkJobStatus::Running));

        $controller = new ProgressController(
            $storage,
            $this->grantOnly(BulkJobResource::PERMISSION_VIEW),
            $this->tokenStorageFor('alice'),
        );

        $response = $controller('job-progress-test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testGrantsAccessToNonOwnerWhenViewAnyIsHeld(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $storage->save($this->makeJob(BulkJobStatus::Running));

        $controller = new ProgressController(
            $storage,
            $this->grantingChecker(), // grants every attribute including VIEW_ANY
            $this->tokenStorageFor('mallory'),
        );

        $response = $controller('job-progress-test');
        self::assertSame(200, $response->getStatusCode());
    }

    public function testFallsBackToCoarseGateWhenNoSecurityFirewallIsWired(): void
    {
        $storage = new InMemoryBulkJobStorage();
        $storage->save($this->makeJob(BulkJobStatus::Running));

        // No TokenStorage → host has no firewall → coarse gate suffices.
        $controller = new ProgressController(
            $storage,
            $this->grantOnly(BulkJobResource::PERMISSION_VIEW),
            null,
        );

        $response = $controller('job-progress-test');
        self::assertSame(200, $response->getStatusCode());
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

    private function grantOnly(string $allowedAttribute): PermissionInterface
    {
        $checker = $this->createMock(PermissionInterface::class);
        $checker->method('isGranted')->willReturnCallback(
            static fn (string $attr): bool => $attr === $allowedAttribute,
        );

        return $checker;
    }

    private function tokenStorageFor(string $userIdentifier): TokenStorageInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUserIdentifier')->willReturn($userIdentifier);

        $storage = $this->createMock(TokenStorageInterface::class);
        $storage->method('getToken')->willReturn($token);

        return $storage;
    }
}
