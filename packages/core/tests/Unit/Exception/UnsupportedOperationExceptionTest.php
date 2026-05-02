<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Exception\UnsupportedOperationException;

#[CoversClass(UnsupportedOperationException::class)]
final class UnsupportedOperationExceptionTest extends TestCase
{
    #[Test]
    public function forMethodWithoutReason(): void
    {
        $e = UnsupportedOperationException::forMethod('count');

        self::assertStringContainsString('count', $e->getMessage());
    }

    #[Test]
    public function forMethodAppendsReasonWhenProvided(): void
    {
        $e = UnsupportedOperationException::forMethod('count', 'Cursor source has no total.');

        self::assertStringContainsString('Cursor source has no total.', $e->getMessage());
    }
}
