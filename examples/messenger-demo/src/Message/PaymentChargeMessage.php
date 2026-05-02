<?php

declare(strict_types=1);

namespace Polysource\Demo\Messenger\Message;

final readonly class PaymentChargeMessage
{
    public function __construct(
        public string $orderId,
        public int $amountCents,
        public string $currency = 'EUR',
    ) {
    }
}
