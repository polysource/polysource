<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Mercure;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Event\BulkJobProgressEvent;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Mercure\MercureBulkJobBroadcaster;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\TokenFactoryInterface;
use Symfony\Component\Mercure\Jwt\TokenProviderInterface;
use Symfony\Component\Mercure\Update;

final class MercureBulkJobBroadcasterTest extends TestCase
{
    public function testPublishesProgressJsonOnCanonicalTopic(): void
    {
        $hub = new RecordingHub();
        $broadcaster = new MercureBulkJobBroadcaster($hub);
        $job = $this->makeJob()->withProgress(2, 1);

        $broadcaster->onProgress(new BulkJobProgressEvent($job));

        self::assertCount(1, $hub->updates);
        $update = $hub->updates[0];
        self::assertSame(['polysource/bulk-jobs/' . $job->id], $update->getTopics());

        /** @var array<string, mixed> $payload */
        $payload = json_decode($update->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($job->id, $payload['id']);
        self::assertSame('running', $payload['status']);
        self::assertSame(2, $payload['processed']);
        self::assertSame(1, $payload['failed']);
        self::assertSame(3, $payload['total']);
    }

    public function testHubFailureIsSwallowed(): void
    {
        $broadcaster = new MercureBulkJobBroadcaster(new ThrowingHub());
        $job = $this->makeJob();

        // No exception bubbles up — worker loop must not be poisoned.
        $broadcaster->onProgress(new BulkJobProgressEvent($job));

        $this->expectNotToPerformAssertions();
    }

    public function testSubscribesToProgressEvent(): void
    {
        self::assertSame(
            [BulkJobProgressEvent::class => 'onProgress'],
            MercureBulkJobBroadcaster::getSubscribedEvents(),
        );
    }

    private function makeJob(): BulkJob
    {
        return new BulkJob(
            id: 'job-mercure-test',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'orders',
            actionName: 'retry-all',
            actorId: 'alice',
            recordIds: ['r-1', 'r-2', 'r-3'],
            status: BulkJobStatus::Running,
        );
    }
}

final class RecordingHub implements HubInterface
{
    /** @var list<Update> */
    public array $updates = [];

    public function getUrl(): string
    {
        return 'http://localhost/.well-known/mercure';
    }

    public function getPublicUrl(): string
    {
        return 'http://localhost/.well-known/mercure';
    }

    public function getProvider(): TokenProviderInterface
    {
        return new class implements TokenProviderInterface {
            public function getJwt(): string
            {
                return 'stub';
            }
        };
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        $this->updates[] = $update;

        return 'urn:uuid:' . bin2hex(random_bytes(16));
    }
}

final class ThrowingHub implements HubInterface
{
    public function getUrl(): string
    {
        return 'http://localhost/.well-known/mercure';
    }

    public function getPublicUrl(): string
    {
        return 'http://localhost/.well-known/mercure';
    }

    public function getProvider(): TokenProviderInterface
    {
        return new class implements TokenProviderInterface {
            public function getJwt(): string
            {
                return 'stub';
            }
        };
    }

    public function getFactory(): ?TokenFactoryInterface
    {
        return null;
    }

    public function publish(Update $update): string
    {
        unset($update);

        throw new RuntimeException('synthetic Mercure outage');
    }
}
