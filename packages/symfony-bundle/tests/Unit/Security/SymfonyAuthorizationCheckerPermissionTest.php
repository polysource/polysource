<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Security\SymfonyAuthorizationCheckerPermission;
use Symfony\Component\Security\Core\Authorization\AccessDecision;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

#[CoversClass(SymfonyAuthorizationCheckerPermission::class)]
final class SymfonyAuthorizationCheckerPermissionTest extends TestCase
{
    #[Test]
    public function grantsEverythingWhenNoFirewallIsRegistered(): void
    {
        $permission = new SymfonyAuthorizationCheckerPermission(null);

        self::assertTrue($permission->isGranted('ANY_ATTRIBUTE'));
    }

    #[Test]
    public function delegatesToTheSymfonyChecker(): void
    {
        $checker = new class implements AuthorizationCheckerInterface {
            public mixed $lastAttribute = null;
            public mixed $lastSubject = null;

            public function isGranted(mixed $attribute, mixed $subject = null, ?AccessDecision $accessDecision = null): bool
            {
                $this->lastAttribute = $attribute;
                $this->lastSubject = $subject;

                return 'GRANTED' === $attribute;
            }
        };

        $permission = new SymfonyAuthorizationCheckerPermission($checker);

        self::assertTrue($permission->isGranted('GRANTED', subject: 'subject'));
        self::assertFalse($permission->isGranted('DENIED'));
        self::assertSame('DENIED', $checker->lastAttribute);
    }
}
