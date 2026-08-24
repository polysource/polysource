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
use Symfony\Component\Mercure\Update;

final class MercureBulkJobBroadcasterTest extends TestCase
{
    public function testPublishesProgressJsonOnCanonicalTopic(): void
    {
        $recorder = new UpdateRecorder();
        $broadcaster = new MercureBulkJobBroadcaster($this->hubRecordingInto($recorder));
        $job = $this->makeJob()->withProgress(2, 1);

        $broadcaster->onProgress(new BulkJobProgressEvent($job));

        self::assertCount(1, $recorder->updates);
        $update = $recorder->updates[0];
        self::assertSame(
            ['polysource/bulk-jobs/' . rawurlencode($job->actorId) . '/' . $job->id],
            $update->getTopics(),
            'topic must include actor segment so hosts can constrain Mercure JWT subscriber claims per-user',
        );

        /** @var array<string, mixed> $payload */
        $payload = json_decode($update->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame($job->id, $payload['id']);
        self::assertSame('running', $payload['status']);
        self::assertSame(2, $payload['processed']);
        self::assertSame(1, $payload['failed']);
        self::assertSame(3, $payload['total']);
    }

    public function testTopicForUrlEncodesActorIdSoSpecialCharsDoNotBreakTopicPath(): void
    {
        // Email-style identifiers contain '@' which is harmless,
        // but slashes / spaces / unicode would shred topic routing.
        self::assertSame(
            'polysource/bulk-jobs/alice%40acme.com/job-1',
            MercureBulkJobBroadcaster::topicFor('alice@acme.com', 'job-1'),
        );
        self::assertSame(
            'polysource/bulk-jobs/team%2Fadmins/job-2',
            MercureBulkJobBroadcaster::topicFor('team/admins', 'job-2'),
        );
    }

    public function testHubFailureIsSwallowed(): void
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('publish')->willThrowException(new RuntimeException('synthetic Mercure outage'));

        $broadcaster = new MercureBulkJobBroadcaster($hub);
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

    /**
     * `HubInterface` is not a frozen contract: symfony/mercure 0.8 added
     * `getProtocolVersion()` and `getCookieName()` to it, and a
     * hand-written stub had to be edited in lockstep or the whole test
     * file fataled at class-load time. Mocking delegates that to PHPUnit,
     * so this test stays green across every Mercure line the package
     * advertises (0.6 through 0.8+).
     */
    private function hubRecordingInto(UpdateRecorder $recorder): HubInterface
    {
        $hub = $this->createMock(HubInterface::class);
        $hub->method('publish')->willReturnCallback(
            static function (Update $update) use ($recorder): string {
                $recorder->updates[] = $update;

                return 'urn:uuid:' . bin2hex(random_bytes(16));
            },
        );

        return $hub;
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

/**
 * Plain collector the mocked hub appends to. Deliberately NOT a
 * `HubInterface` implementation, so it never has to track the
 * interface's shape.
 */
final class UpdateRecorder
{
    /** @var list<Update> */
    public array $updates = [];
}
