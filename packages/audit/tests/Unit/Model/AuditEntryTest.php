<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Model;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;

/**
 * Pin the constructor validation surface + UTC normalisation. Each
 * invariant guards against silent corruption in the persisted audit
 * log: empty string actor → ungroupable rows, non-list recordIds →
 * JSON ambiguity in storage, non-UTC dates → comparable-by-luck
 * across hosts.
 */
final class AuditEntryTest extends TestCase
{
    public function testHappyPathBuildsImmutableEntry(): void
    {
        $occurred = new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC'));

        $entry = new AuditEntry(
            id: '01HF000000000000000000ABCD',
            occurredAt: $occurred,
            actorId: 'alice',
            actorLabel: 'Alice Doe',
            resourceName: 'orders',
            actionName: 'retry',
            recordIds: ['ord-1', 'ord-2'],
            outcome: AuditOutcome::Success,
            message: '2 orders retried',
            durationMs: 142,
            context: ['ip' => '192.0.2.1'],
        );

        self::assertSame('01HF000000000000000000ABCD', $entry->id);
        self::assertSame('alice', $entry->actorId);
        self::assertSame('Alice Doe', $entry->actorLabel);
        self::assertSame('orders', $entry->resourceName);
        self::assertSame('retry', $entry->actionName);
        self::assertSame(['ord-1', 'ord-2'], $entry->recordIds);
        self::assertSame(AuditOutcome::Success, $entry->outcome);
        self::assertSame('2 orders retried', $entry->message);
        self::assertSame(142, $entry->durationMs);
        self::assertSame(['ip' => '192.0.2.1'], $entry->context);
        self::assertSame($occurred, $entry->occurredAt);
    }

    public function testNonUtcDateIsNormalisedToUtc(): void
    {
        $paris = new DateTimeImmutable('2026-05-05T12:00:00', new DateTimeZone('Europe/Paris'));

        $entry = $this->makeEntry(['occurredAt' => $paris]);

        self::assertSame('UTC', $entry->occurredAt->getTimezone()->getName());
        // 12:00 Paris (CEST, UTC+2) → 10:00 UTC.
        self::assertSame('2026-05-05T10:00:00+00:00', $entry->occurredAt->format(\DATE_ATOM));
    }

    public function testEmptyIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['id' => '']);
    }

    public function testEmptyActorIdIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['actorId' => '']);
    }

    public function testAnonymousSentinelIsAccepted(): void
    {
        $entry = $this->makeEntry(['actorId' => AuditEntry::ANONYMOUS_ACTOR_ID]);
        self::assertSame('__anonymous__', $entry->actorId);
    }

    public function testEmptyResourceNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['resourceName' => '']);
    }

    public function testEmptyActionNameIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['actionName' => '']);
    }

    public function testNegativeDurationIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['durationMs' => -1]);
    }

    public function testZeroDurationIsAccepted(): void
    {
        $entry = $this->makeEntry(['durationMs' => 0]);
        self::assertSame(0, $entry->durationMs);
    }

    public function testRecordIdsMustBeAList(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['recordIds' => ['a' => 'rec-1']]);
    }

    public function testRecordIdsMustNotContainEmptyStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['recordIds' => ['rec-1', '']]);
    }

    public function testRecordIdsMustNotContainNonStrings(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->makeEntry(['recordIds' => ['rec-1', 42]]);
    }

    public function testEmptyRecordIdsIsAcceptedForGlobalActions(): void
    {
        $entry = $this->makeEntry(['recordIds' => []]);
        self::assertSame([], $entry->recordIds);
    }

    public function testNullActorLabelAndMessageAreAccepted(): void
    {
        $entry = $this->makeEntry(['actorLabel' => null, 'message' => null]);
        self::assertNull($entry->actorLabel);
        self::assertNull($entry->message);
    }

    public function testNowForFactoryStampsCurrentUtcTime(): void
    {
        $before = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $entry = AuditEntry::nowFor(
            id: '01HF000000000000000000XYZW',
            actorId: 'alice',
            actorLabel: null,
            resourceName: 'orders',
            actionName: 'dismiss',
            outcome: AuditOutcome::Success,
        );
        $after = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        self::assertSame('UTC', $entry->occurredAt->getTimezone()->getName());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $entry->occurredAt->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $entry->occurredAt->getTimestamp());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function makeEntry(array $overrides = []): AuditEntry
    {
        // Pull each field out of the merged map with explicit casts /
        // narrowings. The override layer is intentionally `mixed` so
        // tests can feed garbage to exercise the constructor's
        // runtime guards (negative durations, non-list recordIds, …).
        // PHPStan would (rightly) reject passing `mixed` straight to
        // typed parameters — so we narrow here at the seam.
        $id = $overrides['id'] ?? '01HF000000000000000000ABCD';
        $occurredAt = $overrides['occurredAt'] ?? new DateTimeImmutable('2026-05-05T10:00:00', new DateTimeZone('UTC'));
        $actorId = $overrides['actorId'] ?? 'alice';
        $actorLabel = \array_key_exists('actorLabel', $overrides) ? $overrides['actorLabel'] : null;
        $resourceName = $overrides['resourceName'] ?? 'orders';
        $actionName = $overrides['actionName'] ?? 'retry';
        $recordIds = $overrides['recordIds'] ?? [];
        $outcome = $overrides['outcome'] ?? AuditOutcome::Success;
        $message = \array_key_exists('message', $overrides) ? $overrides['message'] : null;
        $durationMs = $overrides['durationMs'] ?? 0;
        $context = $overrides['context'] ?? [];

        \assert(\is_string($id));
        \assert($occurredAt instanceof DateTimeImmutable);
        \assert(\is_string($actorId));
        \assert(null === $actorLabel || \is_string($actorLabel));
        \assert(\is_string($resourceName));
        \assert(\is_string($actionName));
        \assert(\is_array($recordIds));
        \assert($outcome instanceof AuditOutcome);
        \assert(null === $message || \is_string($message));
        \assert(\is_int($durationMs));
        \assert(\is_array($context));

        /** @var list<string> $recordIds — relax: tests may feed malformed lists to exercise the runtime guard */
        /** @var array<string, mixed> $context */
        return new AuditEntry(
            id: $id,
            occurredAt: $occurredAt,
            actorId: $actorId,
            actorLabel: $actorLabel,
            resourceName: $resourceName,
            actionName: $actionName,
            recordIds: $recordIds,
            outcome: $outcome,
            message: $message,
            durationMs: $durationMs,
            context: $context,
        );
    }
}
