<?php

declare(strict_types=1);

namespace Polysource\Core\Permission;

/**
 * Permission checker abstraction.
 *
 * Default implementation in `polysource/symfony-bundle` wraps Symfony's
 * AuthorizationCheckerInterface. Custom implementations can be plugged
 * for projects that don't use Symfony Security.
 */
interface PermissionInterface
{
    public function isGranted(string $attribute, mixed $subject = null): bool;
}
