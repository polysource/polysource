<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Twig;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Job\BulkJob;
use Polysource\BulkAsync\Job\BulkJobStatus;
use Polysource\BulkAsync\Twig\BulkProgressExtension;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

final class BulkProgressExtensionTest extends TestCase
{
    public function testRegistersExpectedFunctions(): void
    {
        $extension = new BulkProgressExtension($this->makeTwig());
        $names = array_map(static fn (TwigFunction $f): string => $f->getName(), $extension->getFunctions());

        self::assertContains('polysource_bulk_progress', $names);
        self::assertContains('polysource_bulk_progress_payload', $names);
    }

    public function testPayloadReturnsCanonicalShape(): void
    {
        $extension = new BulkProgressExtension($this->makeTwig());
        $job = $this->makeJob(BulkJobStatus::Running)->withProgress(2, 1);

        $payload = $extension->payload($job);

        self::assertSame($job->id, $payload['id']);
        self::assertSame('running', $payload['status']);
        self::assertSame(2, $payload['processed']);
        self::assertSame(1, $payload['failed']);
        self::assertSame(3, $payload['total']);
        self::assertEqualsWithDelta(2 / 3, $payload['progress'], 0.0001);
    }

    public function testRenderProgressEmbedsStimulusBindings(): void
    {
        $extension = new BulkProgressExtension($this->makeTwig(), '/admin/bulk-jobs/%s/progress');
        $job = $this->makeJob(BulkJobStatus::Running)->withProgress(2, 1);

        $html = $extension->renderProgress($job);

        self::assertStringContainsString('data-controller="polysource-bulk-progress"', $html);
        self::assertStringContainsString('data-polysource-bulk-progress-url-value="/admin/bulk-jobs/' . $job->id . '/progress"', $html);
        self::assertStringContainsString('data-polysource-bulk-progress-payload-value', $html);
        self::assertStringNotContainsString('mercure-url-value', $html);
        self::assertStringContainsString('progress-bar', $html);
    }

    public function testRenderProgressIncludesMercureTopicWhenProvided(): void
    {
        $extension = new BulkProgressExtension($this->makeTwig());
        $job = $this->makeJob(BulkJobStatus::Pending);

        $html = $extension->renderProgress($job, 'http://localhost/.well-known/mercure?topic=polysource/bulk-jobs/' . $job->id);

        self::assertStringContainsString('data-polysource-bulk-progress-mercure-url-value="http://localhost/.well-known/mercure?topic=polysource/bulk-jobs/' . $job->id . '"', $html);
    }

    private function makeTwig(): Environment
    {
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../../../Resources/views', 'PolysourceBulkAsync');

        return new Environment($loader, ['strict_variables' => true]);
    }

    private function makeJob(BulkJobStatus $status): BulkJob
    {
        return new BulkJob(
            id: 'job-twig-test',
            createdAt: new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC')),
            resourceName: 'orders',
            actionName: 'retry-all',
            actorId: 'alice',
            recordIds: ['r-1', 'r-2', 'r-3'],
            status: $status,
        );
    }
}
