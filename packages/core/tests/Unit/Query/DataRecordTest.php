<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\DataRecord;
use stdClass;

#[CoversClass(DataRecord::class)]
final class DataRecordTest extends TestCase
{
    #[Test]
    public function itAcceptsIntIdentifier(): void
    {
        $r = new DataRecord(42, ['name' => 'foo']);
        self::assertSame(42, $r->identifier);
    }

    #[Test]
    public function itAcceptsStringIdentifier(): void
    {
        $r = new DataRecord('uuid-1234', ['name' => 'foo']);
        self::assertSame('uuid-1234', $r->identifier);
    }

    #[Test]
    public function getReturnsPropertyValueOrDefault(): void
    {
        $r = new DataRecord(1, ['name' => 'foo', 'count' => 0]);
        self::assertSame('foo', $r->get('name'));
        self::assertSame(0, $r->get('count'));
        self::assertNull($r->get('missing'));
        self::assertSame('default', $r->get('missing', 'default'));
    }

    #[Test]
    public function hasDistinguishesUnsetFromNull(): void
    {
        $r = new DataRecord(1, ['name' => null, 'count' => 0]);
        self::assertTrue($r->has('name'));
        self::assertTrue($r->has('count'));
        self::assertFalse($r->has('missing'));
    }

    #[Test]
    public function rawSourceIsOptional(): void
    {
        $obj = new stdClass();
        $r = new DataRecord(1, [], $obj);
        self::assertSame($obj, $r->rawSource);

        $r2 = new DataRecord(1, []);
        self::assertNull($r2->rawSource);
    }
}
