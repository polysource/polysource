<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\Model;

use PHPUnit\Framework\TestCase;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\SymfonySecurityAuditActor;
use RuntimeException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Pin the contract:
 *  - With a logged-in user, getActorId() returns the user identifier.
 *  - With no Security service injected, getActorId() returns the
 *    anonymous sentinel (used by host apps without security-bundle).
 *  - With a logged-out Security (getUser() returns null), same sentinel.
 *  - With a Security that throws (sub-request, detached firewall),
 *    the actor falls back to anonymous rather than propagating.
 */
final class SymfonySecurityAuditActorTest extends TestCase
{
    public function testActorIdMatchesUserIdentifierWhenLoggedIn(): void
    {
        $user = new InMemoryUser('alice', null);
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $actor = new SymfonySecurityAuditActor($security);

        self::assertSame('alice', $actor->getActorId());
        self::assertSame('alice', $actor->getActorLabel());
    }

    public function testReturnsAnonymousWhenNoSecurityInjected(): void
    {
        $actor = new SymfonySecurityAuditActor(null);

        self::assertSame(AuditEntry::ANONYMOUS_ACTOR_ID, $actor->getActorId());
        self::assertNull($actor->getActorLabel());
    }

    public function testReturnsAnonymousWhenSecurityHasNoUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $actor = new SymfonySecurityAuditActor($security);

        self::assertSame(AuditEntry::ANONYMOUS_ACTOR_ID, $actor->getActorId());
        self::assertNull($actor->getActorLabel());
    }

    public function testReturnsAnonymousWhenSecurityThrows(): void
    {
        // Emulates sub-requests / CLI commands where Security is wired
        // but the firewall context isn't available — getUser() throws.
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willThrowException(new RuntimeException('No firewall context.'));

        $actor = new SymfonySecurityAuditActor($security);

        self::assertSame(AuditEntry::ANONYMOUS_ACTOR_ID, $actor->getActorId());
        self::assertNull($actor->getActorLabel());
    }

    public function testEmptyUserIdentifierFallsBackToAnonymous(): void
    {
        // Some custom UserInterface impls return '' from getUserIdentifier()
        // for incomplete users — treat as anonymous so audit grouping stays
        // sane (no row attributed to "" actor).
        $user = $this->createMock(UserInterface::class);
        $user->method('getUserIdentifier')->willReturn('');
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $actor = new SymfonySecurityAuditActor($security);

        self::assertSame(AuditEntry::ANONYMOUS_ACTOR_ID, $actor->getActorId());
    }
}
