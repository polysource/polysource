<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Phase D — translate POLYSOURCE_* permission attributes to the ROLE_*
 * mapping declared in security.yaml's role_hierarchy.
 *
 * Symfony's built-in RoleVoter only recognises attributes prefixed with
 * the configured role prefix (`ROLE_`). The Polysource attributes are
 * arbitrary strings that get dropped without an explicit voter. We use
 * RoleHierarchyInterface to inflate the user's roles before checking
 * — so ROLE_ADMIN automatically grants every ROLE_VIEWER permission.
 *
 * Phase G replaces this with a domain-aware voter that scopes per
 * resource and per action (e.g. POLYSOURCE_BULK_JOB_CANCEL only on
 * jobs the operator created).
 */
final class PolysourcePermissionVoter extends Voter
{
    /** Map: PERMISSION_ATTRIBUTE → required role. */
    private const PERMISSION_TO_ROLE = [
        // Read access — every authenticated user can browse resources.
        'POLYSOURCE_RESOURCE_VIEW' => 'ROLE_VIEWER',
        'POLYSOURCE_AUDIT_VIEW' => 'ROLE_VIEWER',
        'POLYSOURCE_BULK_JOB_VIEW' => 'ROLE_VIEWER',
        'POLYSOURCE_LOGIN_ATTEMPTS_VIEW' => 'ROLE_VIEWER',
        'POLYSOURCE_FAILED_MESSAGE' => 'ROLE_VIEWER',
        // Phase E adapters.
        'POLYSOURCE_CACHE_VIEW' => 'ROLE_VIEWER',
        'POLYSOURCE_S3_VIEW' => 'ROLE_VIEWER',
        'POLYSOURCE_MICROSERVICES_VIEW' => 'ROLE_VIEWER',
        'POLYSOURCE_SEARCH_INDEX_VIEW' => 'ROLE_VIEWER',
        // Edit / mutate — ops and above.
        'POLYSOURCE_RESOURCE_EDIT' => 'ROLE_OPS',
        'POLYSOURCE_BULK_JOB_CANCEL' => 'ROLE_OPS',
        'POLYSOURCE_WORKFLOW_TRANSITION' => 'ROLE_OPS',
        'POLYSOURCE_FAILED_MESSAGE_RETRY' => 'ROLE_OPS',
        'POLYSOURCE_FAILED_MESSAGE_DISMISS' => 'ROLE_OPS',
        'POLYSOURCE_CACHE_DROP' => 'ROLE_OPS',
        // Destructive / compliance — admin only.
        'POLYSOURCE_AUDIT_EXPORT' => 'ROLE_ADMIN',
        'POLYSOURCE_FAILED_MESSAGE_PURGE' => 'ROLE_ADMIN',
    ];

    public function __construct(private readonly RoleHierarchyInterface $roleHierarchy)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return isset(self::PERMISSION_TO_ROLE[$attribute]);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if ($token->getUser() === null) {
            return false;
        }

        $required = self::PERMISSION_TO_ROLE[$attribute];
        $reachable = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());

        return \in_array($required, $reachable, true);
    }
}
