<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\RetryPaymentWebhook;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Showcase stub — see {@see SendOrderConfirmationEmailHandler} for
 * rationale.
 */
#[AsMessageHandler]
final class RetryPaymentWebhookHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(RetryPaymentWebhook $message): void
    {
        $this->logger->info('[showcase stub] RetryPaymentWebhook handled', [
            'order' => $message->orderReference,
            'transaction' => $message->transactionId,
            'endpoint' => $message->endpoint,
        ]);
    }
}
