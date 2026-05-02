<?php

declare(strict_types=1);

namespace Polysource\Demo\Messenger\Message;

final readonly class GenerateInvoiceMessage
{
    public function __construct(
        public string $invoiceId,
        public string $customerId,
        public int $totalCents,
    ) {
    }
}
