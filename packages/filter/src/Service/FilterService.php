<?php

declare(strict_types=1);

namespace Polysource\Filter\Service;

use InvalidArgumentException;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Throwable;

/**
 * Persists `FilterCollection` instances in the HTTP session,
 * scoped by collection `id`.
 *
 * The service is intentionally **EasyAdmin-agnostic**: it speaks
 * only `RequestStack` + `SessionInterface`. Hosts (EasyAdmin, Sonata,
 * `polysource/admin`, custom Symfony apps) compose this service into
 * their own EventSubscribers / controllers / Twig contexts.
 *
 * Storage layout: each collection stored under
 * `polysource.filter.{xxh128(collection.id)}`. The hash keeps session
 * keys short (32 hex chars) regardless of the host's chosen scope id
 * (which can be a long FQCN).
 *
 * Session payload schema (one slot per collection):
 *   [
 *     ['property' => 'createdAt', 'operator' => 'between', 'values' => ['2026-01-01', '2026-12-31']],
 *     ['property' => 'price',     'operator' => '>=',      'values' => [50]],
 *     …
 *   ]
 *
 * The id itself is NOT stored in the session payload — it's the key.
 * Saving a collection with id "X" overwrites any previous collection
 * stored at the same id.
 */
final class FilterService
{
    private const SESSION_KEY_PREFIX = 'polysource.filter.';

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Persists the collection in the session under its `id`.
     *
     * No-op (silently) when no session is available — e.g. CLI
     * commands, stateless routes, or sub-requests with detached
     * RequestStack. Hosts that need stricter behaviour can check the
     * return value.
     *
     * @return bool true if persisted, false if no session was available
     */
    public function save(FilterCollection $collection): bool
    {
        $session = $this->getSession();
        if (null === $session) {
            return false;
        }

        $session->set($this->keyFor($collection->id), $this->serialize($collection));

        return true;
    }

    /**
     * Reads the collection stored at the given `id`. Returns null
     * when no slot exists or the slot's payload is malformed (the
     * subscriber can fall back to "no filters" rather than crashing
     * on a corrupted session).
     */
    public function load(string $id): ?FilterCollection
    {
        if ('' === $id) {
            throw new InvalidArgumentException('FilterService::load() id cannot be empty.');
        }

        $session = $this->getSession();
        if (null === $session) {
            return null;
        }

        $raw = $session->get($this->keyFor($id));
        if (!\is_array($raw)) {
            return null;
        }

        try {
            return $this->deserialize($id, $raw);
        } catch (Throwable) {
            // Corrupted payload (e.g. session schema drift between
            // bridge versions). Treat as "no saved filters" — better
            // than crashing the request.
            return null;
        }
    }

    /**
     * Build a URL that lands on `$path` with the given filter
     * `$collection` pre-applied. URL-shareable filter state — useful
     * for dashboard tile deep links, email reports, "open in admin"
     * permalinks, copy-link buttons next to a saved view.
     *
     * Encoding shape (mirrors the form data layer that
     * `FilterCollectionType` reads back from the request):
     *
     *   $path?<formName>[<property>][operator]=<op>
     *        &<formName>[<property>][values][0]=<v1>
     *        &<formName>[<property>][values][1]=<v2>
     *
     * The default `formName` is `filter` — matches the vanilla
     * `tags/chips.html.twig` template and the `FilterCollectionType`
     * default. Hosts using a different form name (EA's `filters`,
     * a host-specific `myFilter`) pass it explicitly so chip-remove
     * links and `buildUrl()` agree.
     *
     * Empty collection → returns `$path` unchanged (no trailing `?`).
     *
     * `$extraQuery` is merged into the final query string. Useful
     * for preserving pagination/sort cursor when generating the URL.
     * Existing query strings already on `$path` are preserved too —
     * the helper performs a structural merge (see test
     * `testBuildUrlPreservesExistingQueryStringOnPath`).
     *
     * @param array<string, mixed> $extraQuery merged on top of the encoded filter state
     */
    public function buildUrl(
        string $path,
        FilterCollection $collection,
        array $extraQuery = [],
        string $formName = 'filter',
    ): string {
        if ('' === $path) {
            throw new InvalidArgumentException('FilterService::buildUrl() path cannot be empty.');
        }
        if ('' === $formName) {
            throw new InvalidArgumentException('FilterService::buildUrl() formName cannot be empty.');
        }

        // Split any pre-existing query string off the path so we can
        // merge structurally rather than concatenating with `?` /
        // `&` (which would double-encode nested keys on a path like
        // `/admin/users?page=1`).
        $existingQuery = [];
        $cleanPath = $path;
        $qPos = strpos($path, '?');
        if (false !== $qPos) {
            $cleanPath = substr($path, 0, $qPos);
            parse_str(substr($path, $qPos + 1), $existingQuery);
        }

        $filterPayload = $this->encodeForUrl($collection);

        $query = $existingQuery;
        if ([] !== $filterPayload) {
            $query[$formName] = $filterPayload;
        }
        // Shallow merge — extraQuery typically carries paging/sort,
        // not filter overrides. Hosts wanting to surgically override
        // a single criterion's operator can compose a new
        // FilterCollection rather than passing nested extraQuery.
        $query = array_merge($query, $extraQuery);

        if ([] === $query) {
            return $cleanPath;
        }

        return $cleanPath . '?' . http_build_query($query);
    }

    /**
     * @return array<string, array{operator: string, values: list<mixed>}>
     */
    private function encodeForUrl(FilterCollection $collection): array
    {
        $payload = [];
        foreach ($collection as $criterion) {
            $payload[$criterion->property] = [
                'operator' => $criterion->operator,
                'values' => $criterion->values,
            ];
        }

        return $payload;
    }

    /**
     * Wipes the slot associated with the given `id`. No-op when the
     * slot doesn't exist or when no session is available.
     */
    public function clear(string $id): bool
    {
        if ('' === $id) {
            throw new InvalidArgumentException('FilterService::clear() id cannot be empty.');
        }

        $session = $this->getSession();
        if (null === $session) {
            return false;
        }

        $session->remove($this->keyFor($id));

        return true;
    }

    /**
     * @return list<array{property: string, operator: string, values: list<mixed>}>
     */
    private function serialize(FilterCollection $collection): array
    {
        $payload = [];
        foreach ($collection as $criterion) {
            $payload[] = [
                'property' => $criterion->property,
                'operator' => $criterion->operator,
                'values' => $criterion->values,
            ];
        }

        return $payload;
    }

    /**
     * @param array<int|string, mixed> $raw
     */
    private function deserialize(string $id, array $raw): FilterCollection
    {
        $criteria = [];
        foreach ($raw as $entry) {
            if (!\is_array($entry)
                || !isset($entry['property'], $entry['operator'], $entry['values'])
                || !\is_string($entry['property'])
                || !\is_string($entry['operator'])
            ) {
                // Strict: every entry MUST carry property + operator + values
                // explicitly. A missing `values` key is a schema mismatch
                // (older bridge writing payload without it, or hand-edited
                // session) — surface it via the load()-level fallback to
                // null instead of silently producing empty-values criteria.
                throw new InvalidArgumentException('Malformed filter session payload.');
            }
            $values = $entry['values'];
            if (!\is_array($values) || !array_is_list($values)) {
                throw new InvalidArgumentException('Malformed filter session payload (values).');
            }
            $criteria[] = new FilterCriterion($entry['property'], $entry['operator'], $values);
        }

        return new FilterCollection($id, $criteria);
    }

    private function keyFor(string $id): string
    {
        return self::SESSION_KEY_PREFIX . hash('xxh128', $id);
    }

    private function getSession(): ?SessionInterface
    {
        try {
            return $this->requestStack->getSession();
        } catch (Throwable) {
            // No active request / no session bag wired (CLI, sub-request).
            return null;
        }
    }
}
