<?php

declare(strict_types=1);

namespace Polysource\Core\Tests\Unit\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Core\Exception\ResourceNotFoundException;

#[CoversClass(ResourceNotFoundException::class)]
final class ResourceNotFoundExceptionTest extends TestCase
{
    #[Test]
    public function forNameProducesAReadableMessage(): void
    {
        $e = ResourceNotFoundException::forName('failed-messages');

        self::assertStringContainsString('failed-messages', $e->getMessage());
    }
}
