<?php

declare(strict_types=1);

namespace Polysource\Audit\Serializer;

use Throwable;

/**
 * Normalises Doctrine UnitOfWork change sets and entity snapshots
 * into JSON-storable shapes for the audit log.
 *
 * Two responsibilities, both pure functions of their input:
 *   - `normaliseChangeSet()` — accepts Doctrine's `[old, new]` pair
 *     map and emits the canonical `{old, new}` envelope shape used
 *     by audit consumers + persistence.
 *   - `snapshotMetadata()` — reflection-based scalar snapshot of an
 *     entity's mapped fields. Called at delete time when Doctrine
 *     has no change set to offer.
 *
 * Extracted from `EasyAdminAuditSubscriber` in v0.10.0 per audit
 * task #68. Keeps the subscriber focused on event lifecycle; the
 * Doctrine-aware bits live here and are unit-testable in isolation.
 *
 * @since 0.10.0
 */
final class ChangeSetNormalizer
{
    /**
     * Normalise Doctrine's `getEntityChangeSet()` output (a
     * `field => [old, new]` pair map) to a `field => {old, new}`
     * shape suitable for JSON encoding. Both `old` and `new` are
     * passed through {@see AuditValueSerializer::serialise()} so
     * entity references don't leak and dates serialise cleanly.
     *
     * Drops fields where the pair shape is malformed (defensive
     * against PersistentCollection entries that Doctrine includes
     * in some change-set variants).
     *
     * @param array<string, array{0: mixed, 1: mixed}> $changeSet
     *
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function normaliseChangeSet(array $changeSet): array
    {
        $out = [];
        foreach ($changeSet as $field => $pair) {
            if (!\is_array($pair) || !\array_key_exists(0, $pair) || !\array_key_exists(1, $pair)) {
                continue;
            }
            $out[$field] = [
                'old' => AuditValueSerializer::serialise($pair[0]),
                'new' => AuditValueSerializer::serialise($pair[1]),
            ];
        }

        return $out;
    }

    /**
     * Serialise a Doctrine-metadata snapshot of an entity's mapped
     * scalar fields. Caller passes the list of fields it wants to
     * include (typically from `ClassMetadata::getFieldNames()`) and
     * a getter callable (typically `$metadata->getFieldValue(...)`).
     *
     * The getter is provided as a callable rather than a Doctrine
     * dependency so this class stays Doctrine-agnostic.
     *
     * @param list<string>                 $fieldNames
     * @param callable(string $field): mixed $getter
     *
     * @return array<string, mixed>
     */
    public static function snapshotMetadata(array $fieldNames, callable $getter): array
    {
        $snapshot = [];
        foreach ($fieldNames as $field) {
            try {
                $value = $getter($field);
            } catch (Throwable) {
                continue;
            }
            $snapshot[$field] = AuditValueSerializer::serialise($value);
        }

        return $snapshot;
    }
}
