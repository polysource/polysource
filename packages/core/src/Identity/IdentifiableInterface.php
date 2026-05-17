<?php

declare(strict_types=1);

namespace Polysource\Core\Identity;

/**
 * Opt-in contract for entities that want Polysource subsystems —
 * audit logs, bulk-async tracking, saved-view targeting, etc. — to
 * extract a stable identifier without resorting to duck-typing.
 *
 * The audit subscriber falls back to `getId()` / `getUuid()` /
 * public `$id` / `$uuid` discovery for hosts that haven't adopted
 * this interface, but those probes catch `Throwable` and silently
 * skip on failure. An entity that implements `IdentifiableInterface`
 * gets:
 *
 *   - explicit guarantee that the identifier exists at audit time
 *   - typed return (string) instead of mixed-and-stringified
 *   - one extraction path instead of four
 *   - the ability for the audit subscriber to throw — instead of
 *     silently dropping the entry — when the identifier resolves to
 *     an empty value (a clear bug signal vs. a defensively-swallowed
 *     one)
 *
 * Implementations are free to return the database PK, a UUID, a
 * composite key concatenation, or any opaque token — as long as
 * the value is stable for the lifetime of the entity and unique
 * within its class.
 *
 * @since 0.9.0
 */
interface IdentifiableInterface
{
    /**
     * Stable, non-empty string identifier for this entity. Polysource
     * subsystems treat the value as opaque — store, compare, and
     * surface it as-is. Must not change across the entity's lifetime;
     * must be unique within the implementing class.
     */
    public function getIdentifier(): string;
}
