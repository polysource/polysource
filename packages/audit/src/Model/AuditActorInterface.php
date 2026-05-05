<?php

declare(strict_types=1);

namespace Polysource\Audit\Model;

/**
 * Resolves the *current* actor for an audit entry — the user-id and
 * (optionally) display label that should be stamped on the row.
 *
 * The default implementation introspects Symfony Security
 * (`Security::getUser()`) but hosts with a non-Symfony auth model
 * (Auth0, custom JWT middleware, machine-to-machine tokens) ship
 * their own.
 *
 * When no actor can be determined the implementation returns the
 * sentinel id {@see AuditEntry::ANONYMOUS_ACTOR_ID} — never an empty
 * string and never null. Audit rows must always be groupable by
 * actor without `IS NULL` checks.
 */
interface AuditActorInterface
{
    /**
     * Stable identifier for the current actor. MUST match the
     * `UserInterface::getUserIdentifier()` value when a Symfony user
     * is logged in, so audit rows correlate with the host's user
     * table without an extra mapping table.
     *
     * Returns {@see AuditEntry::ANONYMOUS_ACTOR_ID} when no actor is
     * resolvable.
     */
    public function getActorId(): string;

    /**
     * Optional human-readable label (full name, email, …) — null when
     * not available. Used by the audit log UI to avoid forcing a
     * join back to the host's user table for display.
     */
    public function getActorLabel(): ?string;
}
