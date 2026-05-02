<?php

declare(strict_types=1);

namespace Polysource\Bundle\Security;

/**
 * Canonical permission attribute names used by Polysource controllers.
 *
 * Hosts wire these to their own voters / role hierarchy:
 *
 *     # config/packages/security.yaml
 *     security:
 *         role_hierarchy:
 *             ROLE_ADMIN:
 *                 - POLYSOURCE_RESOURCE_VIEW
 *                 - POLYSOURCE_ACTION_INVOKE
 *
 * Or use a custom Voter that maps these attributes onto domain roles.
 */
final class PermissionAttributes
{
    /**
     * Granted when the current user can read a resource (index / detail).
     * Resource-level `getPermission()` overrides this when set.
     */
    public const RESOURCE_VIEW = 'POLYSOURCE_RESOURCE_VIEW';

    /**
     * Granted when the current user can invoke an action.
     * Action-level `getPermission()` overrides this when set.
     */
    public const ACTION_INVOKE = 'POLYSOURCE_ACTION_INVOKE';

    private function __construct()
    {
    }
}
