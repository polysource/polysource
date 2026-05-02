<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\DataSource;

use DateTimeImmutable;
use JsonException;
use LogicException;
use Polysource\Core\Query\DataRecord;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * Maps a Symfony Messenger {@see Envelope} to a Polysource {@see DataRecord}.
 *
 * Cf. ADR-006 — payload serialization is JSON-first with a `var_export`
 * fallback so the mapper never crashes on exotic message bodies. Payloads
 * larger than `payloadMaxBytes` are truncated with a clear marker.
 */
final readonly class EnvelopeMapper
{
    public function __construct(
        private int $payloadMaxBytes = 50_000,
    ) {
    }

    public function map(Envelope $envelope): DataRecord
    {
        $message = $envelope->getMessage();
        $payload = $this->serializeMessage($message);

        return new DataRecord(
            identifier: self::extractIdentifier($envelope),
            properties: [
                'message_class' => $message::class,
                'failed_at' => self::extractFailedAt($envelope),
                'exception_class' => self::extractExceptionClass($envelope),
                'exception_message' => self::extractExceptionMessage($envelope),
                'payload' => $payload['value'],
                'payload_format' => $payload['format'],
            ],
            rawSource: $envelope,
        );
    }

    /**
     * @return string|int the same id the underlying transport recognises in `find($id)`
     */
    public static function extractIdentifier(Envelope $envelope): string|int
    {
        $stamp = $envelope->last(TransportMessageIdStamp::class);
        if ($stamp instanceof TransportMessageIdStamp) {
            $id = $stamp->getId();
            if (\is_string($id) || \is_int($id)) {
                return $id;
            }
            if (\is_scalar($id)) {
                return (string) $id;
            }
            if (\is_object($id) && method_exists($id, '__toString')) {
                return (string) $id;
            }

            throw new LogicException(\sprintf('TransportMessageIdStamp::getId() returned an unsupported %s — Polysource needs a string|int|stringable.', get_debug_type($id)));
        }

        throw new LogicException('Envelope is missing a TransportMessageIdStamp — cannot derive a stable identifier. Configure your transport to set TransportMessageIdStamp on outgoing messages.');
    }

    private static function extractFailedAt(Envelope $envelope): ?DateTimeImmutable
    {
        $stamps = $envelope->all(RedeliveryStamp::class);
        if ([] === $stamps) {
            return null;
        }
        $last = end($stamps);
        if (!$last instanceof RedeliveryStamp) {
            return null;
        }

        $when = $last->getRedeliveredAt();

        return $when instanceof DateTimeImmutable
            ? $when
            : DateTimeImmutable::createFromInterface($when);
    }

    private static function extractExceptionClass(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(ErrorDetailsStamp::class);

        return $stamp instanceof ErrorDetailsStamp ? $stamp->getExceptionClass() : null;
    }

    private static function extractExceptionMessage(Envelope $envelope): ?string
    {
        $stamp = $envelope->last(ErrorDetailsStamp::class);

        return $stamp instanceof ErrorDetailsStamp ? $stamp->getExceptionMessage() : null;
    }

    /**
     * @return array{value: string, format: 'json'|'print_r'}
     */
    private function serializeMessage(object $message): array
    {
        try {
            $value = json_encode(
                $message,
                \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES,
            );
            $format = 'json';
        } catch (JsonException) {
            // print_r handles recursion natively (`*RECURSION*`) and
            // resources gracefully. We picked it over var_export — which
            // emits warnings on recursion and returns 'NULL' for
            // resources. Cf. ADR-006 §Tests à écrire.
            $value = print_r($message, true);
            $format = 'print_r';
        }

        if (\strlen($value) > $this->payloadMaxBytes) {
            $original = \strlen($value);
            $value = substr($value, 0, $this->payloadMaxBytes)
                . "\n\n[... truncated, original size: " . $original . ' bytes]';
        }

        return ['value' => $value, 'format' => $format];
    }
}
