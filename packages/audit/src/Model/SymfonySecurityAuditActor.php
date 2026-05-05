<?php

declare(strict_types=1);

namespace Polysource\Audit\Model;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Throwable;

/**
 * Default `AuditActorInterface` implementation — pulls the current
 * actor from Symfony Security's `Security::getUser()`.
 *
 * Hosts running outside Symfony Security (Auth0 SDK, custom JWT
 * middleware, machine-to-machine API tokens) implement
 * `AuditActorInterface` directly and rebind the
 * `Polysource\Audit\Model\AuditActorInterface` alias in their
 * services config.
 *
 * The class accepts a nullable `Security` so it boots in stripped-
 * down apps that don't ship `symfony/security-bundle`. In that case
 * every entry stamps the anonymous sentinel — which is correct: no
 * Security ⇒ no actor concept ⇒ all actions are anonymous from the
 * audit log's POV.
 */
final class SymfonySecurityAuditActor implements AuditActorInterface
{
    public function __construct(private readonly ?Security $security = null)
    {
    }

    public function getActorId(): string
    {
        $user = $this->resolveUser();
        if (!$user instanceof UserInterface) {
            return AuditEntry::ANONYMOUS_ACTOR_ID;
        }

        $id = $user->getUserIdentifier();

        return '' === $id ? AuditEntry::ANONYMOUS_ACTOR_ID : $id;
    }

    public function getActorLabel(): ?string
    {
        $user = $this->resolveUser();
        if (!$user instanceof UserInterface) {
            return null;
        }

        // Hosts whose User implements a __toString or has a
        // domain-specific display name will customise via their own
        // AuditActorInterface impl. The default stays minimal: we
        // expose the identifier as the label so the UI never shows
        // an empty cell, but we don't try to call optional getters
        // (getEmail / getFullName / …) we can't guarantee exist.
        return $user->getUserIdentifier();
    }

    private function resolveUser(): ?UserInterface
    {
        if (null === $this->security) {
            return null;
        }

        try {
            return $this->security->getUser();
        } catch (Throwable) {
            // Sub-requests with detached firewall / CLI commands /
            // request-less code paths can throw on getUser(). Treat
            // any of those as "no actor" rather than crashing the
            // audit pipeline.
            return null;
        }
    }
}
