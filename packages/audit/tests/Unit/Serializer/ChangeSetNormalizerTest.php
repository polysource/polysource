<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Serializer;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Serializer\ChangeSetNormalizer;
use RuntimeException;

#[CoversClass(ChangeSetNormalizer::class)]
final class ChangeSetNormalizerTest extends TestCase
{
    #[Test]
    public function normaliseChangeSetEmitsOldNewEnvelopeShape(): void
    {
        $changeSet = [
            'name' => ['Old', 'New'],
            'price' => [99, 199],
        ];

        $result = ChangeSetNormalizer::normaliseChangeSet($changeSet);

        self::assertSame(
            [
                'name' => ['old' => 'Old', 'new' => 'New'],
                'price' => ['old' => 99, 'new' => 199],
            ],
            $result,
        );
    }

    #[Test]
    public function normaliseChangeSetDropsMalformedPairs(): void
    {
        $changeSet = [
            'valid' => ['Old', 'New'],
            'broken' => 'not-an-array',
            'partial' => [0 => 'Only old'],
        ];

        $result = ChangeSetNormalizer::normaliseChangeSet(
            // PHPStan-defeating cast — we explicitly test the
            // defensive guard so the input shape intentionally
            // violates the declared @param.
            /** @phpstan-ignore-next-line argument.type */
            $changeSet,
        );

        self::assertCount(1, $result);
        self::assertArrayHasKey('valid', $result);
    }

    #[Test]
    public function snapshotMetadataCollectsFromGetter(): void
    {
        $fields = ['name', 'email', 'createdAt'];
        $getter = static fn (string $field): mixed => match ($field) {
            'name' => 'Widget',
            'email' => 'w@acme.com',
            'createdAt' => new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
            default => null,
        };

        $snapshot = ChangeSetNormalizer::snapshotMetadata($fields, $getter);

        self::assertSame([
            'name' => 'Widget',
            'email' => 'w@acme.com',
            'createdAt' => '2026-01-01T00:00:00+00:00',
        ], $snapshot);
    }

    #[Test]
    public function snapshotMetadataSkipsFieldsWhereGetterThrows(): void
    {
        // Doctrine getFieldValue can throw on uninitialised typed
        // properties — defensive guard keeps the snapshot partial
        // rather than failing the whole audit entry.
        $getter = static function (string $field): mixed {
            if ('broken' === $field) {
                throw new RuntimeException('uninitialised');
            }

            return strtoupper($field);
        };

        $snapshot = ChangeSetNormalizer::snapshotMetadata(['name', 'broken', 'email'], $getter);

        self::assertSame(['name' => 'NAME', 'email' => 'EMAIL'], $snapshot);
    }
}
