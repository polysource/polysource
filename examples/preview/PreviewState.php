<?php

declare(strict_types=1);

namespace Polysource\Examples\Preview;

use DateTimeImmutable;
use LogicException;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

/**
 * Seeded fixtures for the preview app.
 *
 * Note: each `php -S` request spins up a fresh interpreter context, so
 * the receiver is **re-seeded on every page load**. Action POSTs
 * (retry / dismiss / retry-all / purge) still hit the controller and
 * return a 302 with a flash message — but the next GET shows the
 * pristine fixtures again. This is by design for the preview;
 * stateful demos belong in `examples/messenger-demo/` (Phase 8).
 */
final class PreviewState
{
    public static function failedTransport(): InMemoryListableReceiver
    {
        return new InMemoryListableReceiver([
            (new Envelope(new PlainMessage('PaymentCharge', 1)))
                ->with(new TransportMessageIdStamp('msg-1'))
                ->with(new ErrorDetailsStamp(RuntimeException::class, 0, 'Stripe API timeout after 30s'))
                ->with(new RedeliveryStamp(retryCount: 2, redeliveredAt: new DateTimeImmutable('2026-04-30T08:00:00+00:00'))),
            (new Envelope(new PlainMessage('SendWelcomeEmail', 2)))
                ->with(new TransportMessageIdStamp('msg-2'))
                ->with(new ErrorDetailsStamp(LogicException::class, 0, 'SMTP rate limit hit (5/min)'))
                ->with(new RedeliveryStamp(retryCount: 0, redeliveredAt: new DateTimeImmutable('2026-04-30T09:30:00+00:00'))),
            (new Envelope(new PlainMessage('GenerateMonthlyInvoice', 3)))
                ->with(new TransportMessageIdStamp('msg-3'))
                ->with(new ErrorDetailsStamp(RuntimeException::class, 0, 'PDF rendering OOM'))
                ->with(new RedeliveryStamp(retryCount: 1, redeliveredAt: new DateTimeImmutable('2026-05-01T03:15:00+00:00'))),
        ]);
    }
}
