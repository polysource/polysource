<?php

declare(strict_types=1);

namespace Polysource\BulkAsync\Tests\Unit\Messenger;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Polysource\BulkAsync\Messenger\BulkJobMessage;

final class BulkJobMessageTest extends TestCase
{
    public function testCarriesJobId(): void
    {
        $message = new BulkJobMessage('01HF000000000000000000ABCD');

        self::assertSame('01HF000000000000000000ABCD', $message->jobId);
    }

    public function testRejectsEmptyId(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BulkJobMessage('');
    }
}
