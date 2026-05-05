<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Functional\Stub;

use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Test stub — every permission check passes. Used to wire the
 * SavedView services in the bare-container PipelineCompilerPassTest;
 * functional saved-view permission semantics are covered by the
 * dedicated SavedViewVoterTest + SavedViewEndToEndTest.
 *
 * The 3rd parameter (`$accessDecision`, mixed) keeps the signature
 * compatible across Symfony Security 5.4 / 6.x / 7.x (2-arg signature)
 * AND Sf 8 (3-arg signature with `?AccessDecision $accessDecision`).
 *
 * @internal
 */
final class AlwaysGrantAuthorizationChecker implements AuthorizationCheckerInterface
{
    public function isGranted(mixed $attribute, mixed $subject = null, mixed $accessDecision = null): bool
    {
        return true;
    }
}
