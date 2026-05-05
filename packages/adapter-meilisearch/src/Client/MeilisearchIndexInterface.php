<?php

declare(strict_types=1);

namespace Polysource\Adapter\Meilisearch\Client;

/**
 * Minimal Meilisearch index surface used by the Polysource adapter
 * — abstracted to keep the package decoupled from any specific
 * Meilisearch PHP client (the official one is supported via
 * {@see MeilisearchPhpIndexClient}).
 *
 * Only the 4 operations actually exercised are exposed. Hosts on
 * a non-official client implement this 4-method contract.
 *
 * Why a custom interface rather than typing on the official client:
 *
 *  - The official client uses generic `array<string, mixed>`
 *    everywhere — typing on it surfaces no useful contract.
 *  - In-memory test fakes implement only this surface (cf.
 *    `InMemoryMeilisearchIndex` in tests).
 *  - Hosts can wrap RediSearch / Algolia behind the same contract
 *    as a path to a uniform Polysource search experience.
 */
interface MeilisearchIndexInterface
{
    /**
     * Run a search and return the canonical response shape.
     *
     * Search options follow the Meilisearch query parameters
     * (`limit`, `offset`, `filter`, `sort`, `attributesToRetrieve`,
     * …) — implementers pass them through unchanged.
     *
     * @param array<string, mixed> $options
     *
     * @return array{hits: list<array<string, mixed>>, estimatedTotalHits: ?int, totalHits: ?int}
     */
    public function search(string $query, array $options = []): array;

    /**
     * Fetch a single document by its primary key.
     *
     * @return array<string, mixed>|null
     */
    public function getDocument(string $id): ?array;

    /**
     * Upsert one or many documents.
     *
     * @param list<array<string, mixed>> $documents
     */
    public function addDocuments(array $documents): void;

    /**
     * Delete a single document by primary key. Idempotent — missing
     * documents are a no-op.
     */
    public function deleteDocument(string $id): void;
}
