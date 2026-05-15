<?php

declare(strict_types=1);

namespace Polysource\Core\Query;

/**
 * Single result row returned by a {@see \Polysource\Core\DataSource\DataSourceInterface}.
 *
 * Cf. ADR-001 — identifier is `string|int` to accommodate both numeric PKs
 * (Doctrine entities) and opaque/string IDs (S3 object keys, Messenger
 * envelope IDs, Redis keys, Meilisearch documents).
 */
final class DataRecord
{
    /**
     * @param array<string, mixed> $properties map of property name => value
     * @param mixed                $rawSource  escape hatch for adapter authors
     *                                         to expose the underlying storage
     *                                         object (Doctrine entity, Messenger
     *                                         envelope, S3 metadata array, etc.).
     *
     * @internal Adapter authors only. Application code MUST NOT downcast
     *           `$rawSource` to a storage-specific type — that breaks the
     *           data-source abstraction (ADR-013) and pins your app to the
     *           current adapter. Use the typed `$properties` map instead.
     *           Closure of ADR-011 item A3 — `rawSource` is exempt from the
     *           v1.0 SemVer surface.
     */
    public function __construct(
        public readonly string|int $identifier,
        public readonly array $properties,
        public readonly mixed $rawSource = null,
    ) {
    }

    public function get(string $property, mixed $default = null): mixed
    {
        return $this->properties[$property] ?? $default;
    }

    public function has(string $property): bool
    {
        return \array_key_exists($property, $this->properties);
    }
}
