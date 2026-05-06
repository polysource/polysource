<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Outbound notification: order paid → email confirmation to customer.
 *
 * Real handler shipped in Phase F when bulk-async use cases land. For
 * Phase D we only need this class to exist so the seed command can
 * craft realistic Envelopes for the failed transport.
 */
final class SendOrderConfirmationEmail
{
    public function __construct(
        public readonly string $orderReference,
        public readonly string $customerEmail,
        public readonly int $totalCents,
    ) {
    }
}
