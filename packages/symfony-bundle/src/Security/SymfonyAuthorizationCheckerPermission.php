<?php

declare(strict_types=1);

namespace Polysource\Bundle\Security;

use LogicException;
use Polysource\Core\Permission\PermissionInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * {@see PermissionInterface} backed by Symfony's
 * {@see AuthorizationCheckerInterface}.
 *
 * The bundle wires this as the default permission checker. Polysource
 * never enforces *which* roles your app uses — it just asks Symfony if
 * the current user holds a string attribute (typically a role like
 * `ROLE_ADMIN` or a custom voter attribute like
 * `POLYSOURCE_FAILED_MESSAGE`).
 *
 * **Fail-closed contract**: if the host kernel does not register a
 * Security firewall, this implementation throws a `LogicException`
 * instead of silently granting access. Polysource refuses to ship a
 * permissive default for /admin endpoints. Test kernels that need to
 * bypass auth must alias `PermissionInterface` to a fixture
 * implementation (see `tests/Fixture/AlwaysGrantPermission.php`).
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
            throw new LogicException('PolysourceBundle could not find a Symfony Security firewall. Configure security.firewalls covering your `polysource.url_prefix` (default `/admin`), or alias `Polysource\\Core\\Permission\\PermissionInterface` to a custom implementation in your services.yaml. Polysource intentionally refuses to fall back to a permissive default.');
        }

        return $this->authorizationChecker->isGranted($attribute, $subject);
    }
}
