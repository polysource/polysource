<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Indexing: when a product is created/updated, push it to Meilisearch.
 *
 * Real handler shipped in Phase E (adapter-meilisearch wiring).
 */
final class SyncProductToMeilisearch
{
    public function __construct(
        public readonly string $productId,
        public readonly string $sku,
        public readonly string $name,
    ) {
    }
}
