<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SendOrderConfirmationEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Showcase stub — accepts every retried envelope so the
 * "Retry all" / per-row Retry actions on `/admin/polysource/failed-messages`
 * succeed visibly in the demo. A production app would wire SwiftMailer /
 * Mailgun / Sendgrid here; for the showcase we just log + acknowledge so
 * the UI flow ends in `success`, the failed-messages list shrinks, and
 * the audit log records a `retry` entry with `outcome=success`.
 *
 * Without this handler, every retry throws `NoHandlerForMessageException`
 * and the user sees "0 retried, 100 failed" — by-design for v0.1 (the
 * package contract is "we re-dispatch onto the bus") but confusing in
 * a demo where there's no real consumer downstream. Added to make the
 * retry-success flow exercisable.
 */
#[AsMessageHandler]
final class SendOrderConfirmationEmailHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(SendOrderConfirmationEmail $message): void
    {
        $this->logger->info('[showcase stub] SendOrderConfirmationEmail handled', [
            'order' => $message->orderReference,
            'recipient' => $message->customerEmail,
            'total_cents' => $message->totalCents,
        ]);
    }
}
