<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retry an outbound webhook delivery to the payment provider.
 *
 * Used by the seed command to create realistic 3rd-party API
 * timeouts on the failed transport.
 */
final class RetryPaymentWebhook
{
    public function __construct(
        public readonly string $orderReference,
        public readonly string $transactionId,
        public readonly string $endpoint,
    ) {
    }
}
