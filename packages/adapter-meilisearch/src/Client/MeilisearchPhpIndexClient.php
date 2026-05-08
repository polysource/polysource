<?php

declare(strict_types=1);

namespace Polysource\Adapter\Meilisearch\Client;

use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;

/**
 * Default {@see MeilisearchIndexInterface} implementation wrapping
 * the official meilisearch-php client's {@see Indexes} endpoint.
 *
 * Soft dependency — only loaded when the host actually uses it.
 * Hosts on a custom client ship their own adapter implementing the
 * same 4-method contract.
 */
final class MeilisearchPhpIndexClient implements MeilisearchIndexInterface
{
    public function __construct(private readonly Indexes $index)
    {
    }

    public function search(string $query, array $options = []): array
    {
        $result = $this->index->search($query, $options);

        // The official client returns a SearchResult value object with
        // toArray(). Normalise into our canonical shape regardless of
        // server version (`estimatedTotalHits` vs `totalHits`).
        $payload = $result->toArray();
        /** @var list<array<string, mixed>> $hits */
        $hits = $payload['hits'] ?? [];
        $estimated = $payload['estimatedTotalHits'] ?? null;
        $total = $payload['totalHits'] ?? null;

        return [
            'hits' => $hits,
            'estimatedTotalHits' => \is_int($estimated) ? $estimated : null,
            'totalHits' => \is_int($total) ? $total : null,
        ];
    }

    public function getDocument(string $id): ?array
    {
        try {
            /** @var array<string, mixed> $document */
            $document = $this->index->getDocument($id);

            return $document;
        } catch (ApiException $e) {
            // 404 → genuinely missing document → null is the contract.
            // Any other status (5xx server error, 401/403 auth failure,
            // 408 timeout, etc.) is a real fault that the caller needs
            // to know about — silently returning null would mask outages
            // as "not found" and mislead the operator.
            if (404 === $e->httpStatus) {
                return null;
            }

            throw $e;
        }
    }

    public function addDocuments(array $documents): void
    {
        if ([] === $documents) {
            return;
        }
        $this->index->addDocuments($documents);
    }

    public function deleteDocument(string $id): void
    {
        try {
            $this->index->deleteDocument($id);
        } catch (ApiException $e) {
            // Idempotency narrowly applies to 404 — deleting an already-
            // missing document is a no-op. Any other status (5xx, auth,
            // timeout) is a real failure that must propagate so the
            // caller can retry / alert / surface in the audit log.
            if (404 === $e->httpStatus) {
                return;
            }

            throw $e;
        }
    }
}
