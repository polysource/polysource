<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\SyncProductToMeilisearch;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Showcase stub — see {@see SendOrderConfirmationEmailHandler} for
 * rationale.
 */
#[AsMessageHandler]
final class SyncProductToMeilisearchHandler
{
    public function __construct(private readonly LoggerInterface $logger)
    {
    }

    public function __invoke(SyncProductToMeilisearch $message): void
    {
        $this->logger->info('[showcase stub] SyncProductToMeilisearch handled', [
            'product_id' => $message->productId,
            'sku' => $message->sku,
            'name' => $message->name,
        ]);
    }
}
