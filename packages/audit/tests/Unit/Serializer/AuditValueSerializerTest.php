<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Serializer;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\Serializer\AuditValueSerializer;
use stdClass;

#[CoversClass(AuditValueSerializer::class)]
final class AuditValueSerializerTest extends TestCase
{
    #[Test]
    public function passesThroughNullAndScalars(): void
    {
        self::assertNull(AuditValueSerializer::serialise(null));
        self::assertSame(42, AuditValueSerializer::serialise(42));
        self::assertSame(1.5, AuditValueSerializer::serialise(1.5));
        self::assertTrue(AuditValueSerializer::serialise(true));
        self::assertSame('hello', AuditValueSerializer::serialise('hello'));
    }

    #[Test]
    public function dateTimesSerialiseToIsoAtom(): void
    {
        $dt = new DateTimeImmutable('2026-05-18T10:30:00+00:00', new DateTimeZone('UTC'));

        self::assertSame('2026-05-18T10:30:00+00:00', AuditValueSerializer::serialise($dt));
    }

    #[Test]
    public function stringablesUseToString(): void
    {
        $stringable = new class {
            public function __toString(): string
            {
                return 'stringified-value';
            }
        };

        self::assertSame('stringified-value', AuditValueSerializer::serialise($stringable));
    }

    #[Test]
    public function arraysAreRecursivelySerialised(): void
    {
        $dt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
        $result = AuditValueSerializer::serialise([
            'name' => 'Widget',
            'created' => $dt,
            'nested' => ['ratio' => 0.5],
        ]);

        self::assertSame([
            'name' => 'Widget',
            'created' => '2026-01-01T00:00:00+00:00',
            'nested' => ['ratio' => 0.5],
        ], $result);
    }

    #[Test]
    public function nonStringableObjectsReturnClassMarker(): void
    {
        $obj = new stdClass();

        self::assertSame('[object stdClass]', AuditValueSerializer::serialise($obj));
    }
}
