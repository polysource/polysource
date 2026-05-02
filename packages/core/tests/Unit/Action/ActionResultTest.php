<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Action\ActionResult;

#[CoversClass(ActionResult::class)]
final class ActionResultTest extends TestCase
{
    #[Test]
    public function successFactoryProducesPositiveResult(): void
    {
        $r = ActionResult::success('Done');
        self::assertTrue($r->success);
        self::assertSame('Done', $r->message);
        self::assertSame([], $r->context);
    }

    #[Test]
    public function failureFactoryProducesNegativeResult(): void
    {
        $r = ActionResult::failure('Connection lost', ['code' => 503]);
        self::assertFalse($r->success);
        self::assertSame('Connection lost', $r->message);
        self::assertSame(['code' => 503], $r->context);
    }

    #[Test]
    public function successCanCarryNoMessage(): void
    {
        $r = ActionResult::success();
        self::assertTrue($r->success);
        self::assertNull($r->message);
    }
}
