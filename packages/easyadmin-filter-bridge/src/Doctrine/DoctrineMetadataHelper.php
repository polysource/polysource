<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Doctrine;

/**
 * Doctrine ORM 2.x vs 3.x association-mapping shape helper.
 *
 * Per ADR-015 (multi-version baseline) the bridge supports both
 * Doctrine majors. The mapping returned by `ClassMetadata::getAssociationMapping()`
 * changed shape between them:
 *
 *   - **Doctrine 2.x**: returns `array{targetEntity: class-string, ...}`
 *   - **Doctrine 3.x**: returns `Doctrine\ORM\Mapping\AssociationMapping` —
 *                       a stdClass-shaped object with a public
 *                       `targetEntity` property.
 *
 * Without this helper every consumer had to inline a defensive
 * `(array) $mapping` cast that worked on both shapes by coincidence
 * (2.x: no-op; 3.x: object→array reflection). The cast is correct
 * but masks intent and makes PHPStan unable to narrow either branch
 * since whichever major is installed defines the "real" type.
 *
 * This helper takes `mixed` so the static analyser stays neutral,
 * inspects the runtime shape, and returns a typed result. Consumers
 * call it once and forget the cross-version trivia.
 *
 * @since 0.9.0
 */
final class DoctrineMetadataHelper
{
    /**
     * Extract `targetEntity` from a Doctrine association mapping.
     * Returns null when:
     *
     *   - the mapping shape is neither array nor object (caller
     *     passed garbage — silently safe)
     *   - the `targetEntity` key/property is absent or empty
     *   - the value names a non-existent class
     *
     * @return class-string|null
     */
    public static function extractTargetEntity(mixed $mapping): ?string
    {
        $candidate = self::readField($mapping, 'targetEntity');
        if (!\is_string($candidate) || '' === $candidate || !class_exists($candidate)) {
            return null;
        }

        return $candidate;
    }

    /**
     * Read a single field from an association mapping regardless of
     * Doctrine major. Exposed publicly so adapters that need
     * less-common fields (`inversedBy`, `mappedBy`, `joinColumns`,
     * …) don't reimplement the shape detection.
     */
    public static function readField(mixed $mapping, string $field): mixed
    {
        if (\is_array($mapping)) {
            return $mapping[$field] ?? null;
        }

        if (\is_object($mapping)) {
            // Object-shaped mapping (Doctrine 3.x). PHPStan can't
            // narrow against the class-specific type because the
            // analyser only sees the Doctrine major it's installed
            // with. The array-cast reflects every public property
            // into a sibling key — works on both 2.x (already array)
            // and 3.x (object with public properties).
            $asArray = (array) $mapping;

            return $asArray[$field] ?? null;
        }

        return null;
    }
}
