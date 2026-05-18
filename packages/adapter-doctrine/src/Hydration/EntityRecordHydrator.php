<?php

declare(strict_types=1);

namespace Polysource\Adapter\Doctrine\Hydration;

use DateTimeImmutable;
use Doctrine\ORM\Mapping\ClassMetadata;
use Polysource\Core\Query\DataPayload;
use Polysource\Core\Query\DataRecord;
use Throwable;

/**
 * Bidirectional bridge between Doctrine entities and Polysource
 * {@see DataRecord} / {@see DataPayload} value objects:
 *
 *   - `toRecord()` — read every mapped scalar field off an entity,
 *     coerce DateTimeImmutable to ISO-8601, build a DataRecord
 *     keyed by the entity's identifier.
 *   - `applyPayload()` — write a DataPayload's properties back onto
 *     an entity through Doctrine's metadata setter. Skips read-only
 *     fields and properties that aren't mapped.
 *   - `instantiate()` — newInstanceWithoutConstructor + caller can
 *     override by subclassing.
 *
 * Doctrine-aware but stateless — every method takes the metadata it
 * needs as an argument. Lets DoctrineDataSource focus on
 * coordination, makes the hydration logic testable in isolation.
 *
 * Extracted from `DoctrineDataSource` in v0.10.0 per audit task #69.
 *
 * @since 0.10.0
 */
final class EntityRecordHydrator
{
    /**
     * @param ClassMetadata<object> $metadata
     */
    public static function toRecord(ClassMetadata $metadata, object $entity): DataRecord
    {
        $idField = $metadata->getSingleIdentifierFieldName();
        $rawId = $metadata->getFieldValue($entity, $idField);

        $properties = [];
        foreach ($metadata->getFieldNames() as $field) {
            $value = $metadata->getFieldValue($entity, $field);
            $properties[$field] = $value instanceof DateTimeImmutable
                ? $value->format(\DATE_ATOM)
                : $value;
        }

        return new DataRecord(self::stringifyId($rawId), $properties, $entity);
    }

    /**
     * Apply a payload back onto a Doctrine entity. Properties not
     * mapped by the metadata are silently dropped; setter failures
     * (read-only fields, computed properties) are caught so a single
     * bad value can't crash the whole write.
     *
     * @param ClassMetadata<object>  $metadata
     * @param array<string, string>  $propertyToField map of payload
     *                                                property name to
     *                                                entity field name
     *                                                (typically the same)
     */
    public static function applyPayload(
        ClassMetadata $metadata,
        object $entity,
        DataPayload $payload,
        array $propertyToField,
    ): void {
        foreach ($payload->properties as $property => $value) {
            $field = $propertyToField[$property] ?? $property;
            if (!$metadata->hasField($field) && !$metadata->hasAssociation($field)) {
                continue;
            }
            try {
                $metadata->setFieldValue($entity, $field, $value);
            } catch (Throwable) {
                // Read-only field, computed property — skip rather
                // than crash the whole write.
            }
        }
    }

    /**
     * Allocate a Doctrine entity without invoking its constructor.
     * Doctrine entities should always be
     * `newInstanceWithoutConstructor`-safe; hosts whose entities
     * need constructor args subclass DoctrineDataSource and override
     * the hook in the data source.
     *
     * @param ClassMetadata<object> $metadata
     */
    public static function instantiate(ClassMetadata $metadata): object
    {
        return $metadata->getReflectionClass()->newInstanceWithoutConstructor();
    }

    private static function stringifyId(mixed $rawId): string|int
    {
        if (\is_int($rawId) || \is_string($rawId)) {
            return $rawId;
        }
        if (\is_scalar($rawId) || (\is_object($rawId) && method_exists($rawId, '__toString'))) {
            return (string) $rawId;
        }

        return '';
    }
}
