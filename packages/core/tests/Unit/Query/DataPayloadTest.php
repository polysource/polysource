<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Query;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Query\DataPayload;

#[CoversClass(DataPayload::class)]
final class DataPayloadTest extends TestCase
{
    #[Test]
    public function getAndHasFollowSameSemanticsAsRecord(): void
    {
        $p = new DataPayload(['name' => 'foo', 'count' => 0]);
        self::assertSame('foo', $p->get('name'));
        self::assertSame(0, $p->get('count'));
        self::assertNull($p->get('missing'));
        self::assertSame('default', $p->get('missing', 'default'));
        self::assertTrue($p->has('count'));
        self::assertFalse($p->has('missing'));
    }

    #[Test]
    public function withReturnsNewInstanceWithoutMutating(): void
    {
        $original = new DataPayload(['name' => 'foo']);
        $modified = $original->with('email', 'foo@example.com');

        self::assertSame(['name' => 'foo'], $original->properties);
        self::assertSame(['name' => 'foo', 'email' => 'foo@example.com'], $modified->properties);
        self::assertNotSame($original, $modified);
    }

    #[Test]
    public function withoutRemovesProperty(): void
    {
        $original = new DataPayload(['name' => 'foo', 'email' => 'foo@example.com']);
        $modified = $original->without('email');

        self::assertTrue($original->has('email'));
        self::assertFalse($modified->has('email'));
    }
}
