<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Logger;

use PHPUnit\Framework\TestCase;
use Polysource\Audit\Logger\AggregateAuditLogger;
use Polysource\Audit\Logger\AuditLoggerInterface;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Throwable;

/**
 * Pin the fan-out + fault-isolation contract:
 *  - Every downstream logger receives every entry.
 *  - One throwing logger does NOT stop the others (contention).
 *  - Failures are reported via PSR-3 with the failing logger class
 *    + entry id + exception in `context['exception']` so operators
 *    can correlate via Sentry / monolog.
 */
final class AggregateAuditLoggerTest extends TestCase
{
    public function testFanOutsToEveryDownstreamLogger(): void
    {
        $a = new RecordingLogger();
        $b = new RecordingLogger();
        $c = new RecordingLogger();
        $aggregate = new AggregateAuditLogger([$a, $b, $c]);

        $entry = $this->makeEntry('01HF000000000000000000FAN1');
        $aggregate->log($entry);

        self::assertSame([$entry], $a->logged);
        self::assertSame([$entry], $b->logged);
        self::assertSame([$entry], $c->logged);
    }

    public function testThrowingLoggerDoesNotInterruptOthers(): void
    {
        $a = new RecordingLogger();
        $broken = new ThrowingLogger(new RuntimeException('Datadog 502'));
        $b = new RecordingLogger();
        $errors = new RecordingPsrLogger();

        $aggregate = new AggregateAuditLogger([$a, $broken, $b], $errors);

        $entry = $this->makeEntry('01HF000000000000000000FAN2');
        $aggregate->log($entry);

        // a + b still received the entry despite the middle one
        // throwing.
        self::assertSame([$entry], $a->logged);
        self::assertSame([$entry], $b->logged);
        // The broken logger's failure surfaced via PSR-3.
        self::assertCount(1, $errors->records);
        self::assertSame('error', $errors->records[0]['level']);
        self::assertStringContainsString('Datadog 502', $errors->records[0]['rendered']);
        self::assertSame('01HF000000000000000000FAN2', $errors->records[0]['context']['entryId']);
        self::assertSame(ThrowingLogger::class, $errors->records[0]['context']['logger']);
        self::assertInstanceOf(RuntimeException::class, $errors->records[0]['context']['exception']);
    }

    public function testEmptyLoggerListIsAccepted(): void
    {
        $this->expectNotToPerformAssertions();

        $aggregate = new AggregateAuditLogger([]);
        $aggregate->log($this->makeEntry('01HF000000000000000000EMPT'));

        // Empty is a legitimate host config (audit feature installed
        // but no sink wired yet). We assert nothing — passing means
        // the call returned without throwing.
    }

    public function testGeneratorLoggersAreIterable(): void
    {
        // Generators are common when DI gives us a tagged_iterator
        // — make sure the aggregator doesn't assume a re-iterable
        // collection.
        $a = new RecordingLogger();
        $generator = (static function () use ($a) {
            yield $a;
        })();

        $aggregate = new AggregateAuditLogger($generator);
        $entry = $this->makeEntry('01HF000000000000000000ITER');
        $aggregate->log($entry);

        self::assertSame([$entry], $a->logged);
    }

    private function makeEntry(string $id): AuditEntry
    {
        return AuditEntry::nowFor(
            id: $id,
            actorId: AuditEntry::ANONYMOUS_ACTOR_ID,
            actorLabel: null,
            resourceName: 'orders',
            actionName: 'retry',
            outcome: AuditOutcome::Success,
        );
    }
}

final class RecordingLogger implements AuditLoggerInterface
{
    /** @var list<AuditEntry> */
    public array $logged = [];

    public function log(AuditEntry $entry): void
    {
        $this->logged[] = $entry;
    }
}

final class ThrowingLogger implements AuditLoggerInterface
{
    public function __construct(private readonly Throwable $error)
    {
    }

    public function log(AuditEntry $entry): void
    {
        throw $this->error;
    }
}

/**
 * Tiny PSR-3 sink — captures level + interpolated message + raw
 * context so we can assert without depending on monolog/monolog.
 *
 * Context shape kept loose (`array<mixed>`) for LSP-correctness with
 * `Psr\Log\LoggerInterface::log()` — the interface declares plain
 * `array`, so child overrides must accept the same width or wider.
 */
final class RecordingPsrLogger extends AbstractLogger
{
    /** @var list<array{level: string, rendered: string, context: array<mixed>}> */
    public array $records = [];

    /**
     * @param array<mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelString = match (true) {
            \is_string($level) => $level,
            $level instanceof Stringable => (string) $level,
            default => 'unknown',
        };

        $rendered = (string) $message;
        foreach ($context as $key => $value) {
            if (\is_scalar($value) || $value instanceof Stringable) {
                $rendered = str_replace('{' . $key . '}', (string) $value, $rendered);
            }
        }
        $this->records[] = [
            'level' => $levelString,
            'rendered' => $rendered,
            'context' => $context,
        ];
    }
}
