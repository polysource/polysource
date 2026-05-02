<?php

declare(strict_types=1);

namespace Polysource\Bundle\Security;

use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * {@see PermissionInterface} backed by Symfony's
 * {@see AuthorizationCheckerInterface}.
 *
 * The bundle wires this as the default permission checker. Polysource
 * never enforces *which* roles your app uses — it just asks Symfony if
 * the current user holds a string attribute (typically a role like
 * `ROLE_ADMIN` or a custom voter attribute like `POLYSOURCE_FAILED_MESSAGE`).
 *
 * If the host kernel does not register a Security firewall (e.g. in a
 * minimal test app), the constructor accepts `null` and the checker
 * degrades to a permissive grant-everything implementation. Production
 * apps MUST register a firewall — Polysource is intentionally not a
 * full auth solution.
 */
final readonly class SymfonyAuthorizationCheckerPermission implements PermissionInterface
{
    public function __construct(
        private ?AuthorizationCheckerInterface $authorizationChecker = null,
    ) {
    }

    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        if (null === $this->authorizationChecker) {
            // No firewall configured — defer auth to the host. Logged in
            // a debug toolbar or stderr would be ideal but Phase 6 keeps
            // it minimal.
            return true;
        }

        return $this->authorizationChecker->isGranted($attribute, $subject);
    }
}
