<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Unit\DataSource;

use DateTimeImmutable;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Adapter\Messenger\DataSource\EnvelopeMapper;
use Polysource\Adapter\Messenger\Tests\Fixture\CircularMessage;
use Polysource\Adapter\Messenger\Tests\Fixture\ClosureMessage;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

#[CoversClass(EnvelopeMapper::class)]
final class EnvelopeMapperTest extends TestCase
{
    #[Test]
    public function mapsPlainMessageToJsonPayload(): void
    {
        $mapper = new EnvelopeMapper();
        $envelope = self::wrap(new PlainMessage('hello', 42), id: 'abc-1');

        $record = $mapper->map($envelope);

        self::assertSame('abc-1', $record->identifier);
        self::assertSame(PlainMessage::class, $record->get('message_class'));
        self::assertSame('json', $record->get('payload_format'));
        $payload = $record->get('payload');
        self::assertIsString($payload);
        self::assertStringContainsString('"name": "hello"', $payload);
        self::assertStringContainsString('"count": 42', $payload);
    }

    #[Test]
    public function closureContainingMessagesStillEncodeAsJson(): void
    {
        // Closures serialise to `{}` in json_encode rather than throwing,
        // so the JSON path holds. Documented v0.1 behaviour.
        $mapper = new EnvelopeMapper();
        $envelope = self::wrap(new ClosureMessage(static fn () => 1), id: 'abc-2');

        $record = $mapper->map($envelope);

        self::assertSame('json', $record->get('payload_format'));
    }

    #[Test]
    public function fallsBackToPrintROnCircularReferences(): void
    {
        $mapper = new EnvelopeMapper();
        $envelope = self::wrap(new CircularMessage(), id: 'abc-3');

        $record = $mapper->map($envelope);

        self::assertSame('print_r', $record->get('payload_format'));
        $payload = $record->get('payload');
        self::assertIsString($payload);
        self::assertStringContainsString('*RECURSION*', $payload);
    }

    #[Test]
    public function truncatesOversizedPayloads(): void
    {
        $mapper = new EnvelopeMapper(payloadMaxBytes: 256);
        $envelope = self::wrap(new PlainMessage(str_repeat('A', 1000), 0), id: 'abc-4');

        $record = $mapper->map($envelope);

        $payload = $record->get('payload');
        self::assertIsString($payload);
        self::assertStringContainsString('truncated, original size:', $payload);
        self::assertLessThan(1000, \strlen($payload));
    }

    #[Test]
    public function extractsExceptionDetailsAndFailedAt(): void
    {
        $mapper = new EnvelopeMapper();
        $when = new DateTimeImmutable('2026-04-30T08:15:00+00:00');
        $envelope = self::wrap(new PlainMessage('x', 1), id: 'abc-5')
            ->with(new ErrorDetailsStamp(RuntimeException::class, 0, 'database is down'))
            ->with(new RedeliveryStamp(retryCount: 0, redeliveredAt: $when))
        ;

        $record = $mapper->map($envelope);

        self::assertSame(RuntimeException::class, $record->get('exception_class'));
        self::assertSame('database is down', $record->get('exception_message'));
        $failedAt = $record->get('failed_at');
        self::assertInstanceOf(DateTimeImmutable::class, $failedAt);
        self::assertSame('2026-04-30T08:15:00+00:00', $failedAt->format('c'));
    }

    #[Test]
    public function throwsWhenTransportMessageIdStampIsMissing(): void
    {
        $mapper = new EnvelopeMapper();
        $envelope = new Envelope(new PlainMessage('x', 1));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('TransportMessageIdStamp');
        $mapper->map($envelope);
    }

    private static function wrap(object $message, string $id): Envelope
    {
        return (new Envelope($message))->with(new TransportMessageIdStamp($id));
    }
}
