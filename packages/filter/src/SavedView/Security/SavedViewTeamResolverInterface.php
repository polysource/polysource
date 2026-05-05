<?php

declare(strict_types=1);

namespace Polysource\Filter\SavedView\Security;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Optional contract: maps the current Symfony user to a team
 * identifier so the {@see SavedViewVoter} can decide visibility for
 * `SavedViewScope::TEAM` views.
 *
 * Polysource doesn't ship a default implementation — teams are a
 * host-specific concept. Hosts that don't have teams simply don't
 * inject this resolver, and TEAM-scoped views never appear in their
 * apps (effectively the same as PRIVATE for the owner only).
 *
 * Per ADR-019 §5.
 *
 * @since 0.1.0
 */
interface SavedViewTeamResolverInterface
{
    /**
     * Returns the team id the user belongs to, or null when the user
     * has no team. Implementations should be cheap (no DB call per
     * voter invocation) — cache results if the host model requires a
     * lookup.
     */
    public function teamIdFor(UserInterface $user): ?string;
}
