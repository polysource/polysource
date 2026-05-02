<?php

declare(strict_types=1);

namespace Polysource\Demo\Messenger\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Demo-only voter that maps `ROLE_ADMIN` to every `POLYSOURCE_*`
 * attribute Polysource asks for.
 *
 * Symfony's default `RoleHierarchyVoter` only votes on attributes that
 * start with `ROLE_` (or `IS_AUTHENTICATED_*`). Polysource's
 * permission attributes (`POLYSOURCE_RESOURCE_VIEW`,
 * `POLYSOURCE_FAILED_MESSAGE`, etc.) are arbitrary strings, so
 * `role_hierarchy` would silently abstain on them — see the bundle's
 * doc on PermissionAttributes.
 *
 * Real applications will replace this with a domain voter that decides
 * per attribute (e.g. "anyone in the on-call team can RETRY but only
 * SREs can PURGE"). For the demo we keep it simple: ROLE_ADMIN ⇒ all.
 */
final class PolysourceAdminVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'POLYSOURCE_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (null === $user) {
            return false;
        }

        return \in_array('ROLE_ADMIN', $token->getRoleNames(), true);
    }
}
