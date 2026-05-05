<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\Security\SavedViewTeamResolverInterface;
use Polysource\Filter\SavedView\Security\SavedViewVoter;
use stdClass;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserInterface;

#[CoversClass(SavedViewVoter::class)]
final class SavedViewVoterTest extends TestCase
{
    #[Test]
    public function abstainsOnUnsupportedSubject(): void
    {
        $voter = new SavedViewVoter();
        $decision = $voter->vote(
            $this->tokenFor('alice'),
            new stdClass(),
            [SavedViewVoter::VIEW],
        );

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $decision);
    }

    #[Test]
    public function abstainsOnUnsupportedAttribute(): void
    {
        $voter = new SavedViewVoter();
        $decision = $voter->vote(
            $this->tokenFor('alice'),
            $this->makeView(ownerId: 'alice'),
            ['UNRELATED'],
        );

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $decision);
    }

    #[Test]
    public function ownerCanViewEditDeleteShare(): void
    {
        $voter = new SavedViewVoter();
        $token = $this->tokenFor('alice');
        $view = $this->makeView(ownerId: 'alice');

        foreach ([SavedViewVoter::VIEW, SavedViewVoter::EDIT, SavedViewVoter::DELETE, SavedViewVoter::SHARE] as $attribute) {
            self::assertSame(
                VoterInterface::ACCESS_GRANTED,
                $voter->vote($token, $view, [$attribute]),
                "owner should pass {$attribute}",
            );
        }
    }

    #[Test]
    public function nonOwnerCannotEditDeleteShare(): void
    {
        $voter = new SavedViewVoter();
        $bobToken = $this->tokenFor('bob');
        $aliceView = $this->makeView(ownerId: 'alice', scope: SavedViewScope::PUBLIC);

        foreach ([SavedViewVoter::EDIT, SavedViewVoter::DELETE, SavedViewVoter::SHARE] as $attribute) {
            self::assertSame(
                VoterInterface::ACCESS_DENIED,
                $voter->vote($bobToken, $aliceView, [$attribute]),
                "non-owner must be denied {$attribute}",
            );
        }
    }

    #[Test]
    public function privateScopeHidesViewFromNonOwner(): void
    {
        $voter = new SavedViewVoter();
        $bobToken = $this->tokenFor('bob');
        $aliceView = $this->makeView(ownerId: 'alice', scope: SavedViewScope::PRIVATE);

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($bobToken, $aliceView, [SavedViewVoter::VIEW]),
        );
    }

    #[Test]
    public function publicScopeGrantsViewToEveryone(): void
    {
        $voter = new SavedViewVoter();
        $aliceView = $this->makeView(ownerId: 'alice', scope: SavedViewScope::PUBLIC);

        foreach (['bob', 'charlie', 'eve'] as $userId) {
            self::assertSame(
                VoterInterface::ACCESS_GRANTED,
                $voter->vote($this->tokenFor($userId), $aliceView, [SavedViewVoter::VIEW]),
                "every authenticated user should view a PUBLIC view (saw deny for {$userId})",
            );
        }
    }

    #[Test]
    public function teamScopeRequiresMatchingTeamResolver(): void
    {
        $resolver = new class implements SavedViewTeamResolverInterface {
            public function teamIdFor(UserInterface $user): ?string
            {
                /** @var array<string, ?string> $byUser */
                $byUser = [
                    'bob' => 'team-acme',
                    'eve' => 'team-other',
                    'orphan' => null,
                ];

                return $byUser[$user->getUserIdentifier()] ?? null;
            }
        };
        $voter = new SavedViewVoter($resolver);
        $aliceView = $this->makeView(
            ownerId: 'alice',
            scope: SavedViewScope::TEAM,
            teamId: 'team-acme',
        );

        // Bob is in team-acme → grant.
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->tokenFor('bob'), $aliceView, [SavedViewVoter::VIEW]),
        );
        // Eve is in team-other → deny.
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor('eve'), $aliceView, [SavedViewVoter::VIEW]),
        );
    }

    #[Test]
    public function teamScopeWithoutResolverDeniesNonOwner(): void
    {
        $voter = new SavedViewVoter(); // no resolver
        $aliceView = $this->makeView(
            ownerId: 'alice',
            scope: SavedViewScope::TEAM,
            teamId: 'team-acme',
        );

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($this->tokenFor('bob'), $aliceView, [SavedViewVoter::VIEW]),
        );
    }

    #[Test]
    public function anonymousUserCanViewPublicOnly(): void
    {
        $voter = new SavedViewVoter();
        $anonToken = $this->anonymousToken();
        $publicView = $this->makeView(ownerId: 'alice', scope: SavedViewScope::PUBLIC);
        $privateView = $this->makeView(ownerId: 'alice', scope: SavedViewScope::PRIVATE);

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($anonToken, $publicView, [SavedViewVoter::VIEW]),
        );
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($anonToken, $privateView, [SavedViewVoter::VIEW]),
        );
        // Anonymous never gets EDIT.
        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote($anonToken, $publicView, [SavedViewVoter::EDIT]),
        );
    }

    private function makeView(
        string $ownerId = 'alice',
        SavedViewScope $scope = SavedViewScope::PRIVATE,
        ?string $teamId = null,
    ): SavedView {
        return new SavedView(
            id: 'view-1',
            name: 'My products',
            resourceName: 'products',
            ownerId: $ownerId,
            scope: $scope,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
            teamId: $teamId,
        );
    }

    private function tokenFor(string $userId): TokenInterface
    {
        $user = new InMemoryUser($userId, null, ['ROLE_USER']);
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }

    private function anonymousToken(): TokenInterface
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn(null);

        return $token;
    }
}
