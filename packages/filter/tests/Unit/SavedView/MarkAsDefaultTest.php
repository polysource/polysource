<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\SavedView\Exception\SavedViewAccessDeniedException;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Storage\InMemorySavedViewStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Pin v0.3.0 personal-default semantic on {@see SavedViewService}.
 */
#[CoversClass(SavedViewService::class)]
final class MarkAsDefaultTest extends TestCase
{
    #[Test]
    public function markAsDefaultSetsTheFlagAndClearsOtherPersonalDefaultsOfSameOwner(): void
    {
        $storage = new InMemorySavedViewStorage();
        $previous = $this->makeView('prev', 'alice', 'orders', isDefault: true);
        $candidate = $this->makeView('cand', 'alice', 'orders', isDefault: false);
        $sameUserOtherResource = $this->makeView('other', 'alice', 'customers', isDefault: true);
        $otherUserSameResource = $this->makeView('shared', 'bob', 'orders', isDefault: true, scope: SavedViewScope::PUBLIC);
        foreach ([$previous, $candidate, $sameUserOtherResource, $otherUserSameResource] as $v) {
            $storage->save($v);
        }

        $service = $this->makeService('alice', $storage);

        $service->markAsDefault('cand');

        // Target is now default
        self::assertTrue($storage->find('cand')?->isDefault);
        // Previous alice/orders default cleared
        self::assertFalse($storage->find('prev')?->isDefault);
        // Other resource untouched
        self::assertTrue($storage->find('other')?->isDefault);
        // Other user's view untouched even though same resource
        self::assertTrue($storage->find('shared')?->isDefault);
    }

    #[Test]
    public function markAsDefaultIsRejectedWhenUserDoesNotOwnTheView(): void
    {
        $storage = new InMemorySavedViewStorage();
        $bobsView = $this->makeView('b', 'bob', 'orders', isDefault: false, scope: SavedViewScope::PUBLIC);
        $storage->save($bobsView);

        $service = $this->makeService('alice', $storage);

        $this->expectException(SavedViewAccessDeniedException::class);
        $service->markAsDefault('b');
    }

    #[Test]
    public function unmarkAsDefaultClearsTheFlagOnlyForPersonalDefaults(): void
    {
        $storage = new InMemorySavedViewStorage();
        $personal = $this->makeView('p', 'alice', 'orders', isDefault: true);
        $roleDef = $this->makeView('r', 'alice', 'orders', isDefault: true, roleAsDefault: 'ROLE_USER');
        $storage->save($personal);
        $storage->save($roleDef);

        $service = $this->makeService('alice', $storage);

        $service->unmarkAsDefault('p');
        self::assertFalse($storage->find('p')?->isDefault);

        // Role default is not cleared by unmarkAsDefault (separate concern)
        $service->unmarkAsDefault('r');
        self::assertTrue($storage->find('r')?->isDefault);
    }

    #[Test]
    public function defaultForReturnsThePersonalDefaultOnCleanUrl(): void
    {
        $storage = new InMemorySavedViewStorage();
        $defaultView = $this->makeView('d', 'alice', 'orders', isDefault: true);
        $other = $this->makeView('o', 'alice', 'orders', isDefault: false);
        $storage->save($defaultView);
        $storage->save($other);

        $service = $this->makeService('alice', $storage);

        $resolved = $service->defaultFor('orders');

        self::assertNotNull($resolved);
        self::assertSame('d', $resolved->id);
    }

    private function makeView(
        string $id,
        string $ownerId,
        string $resourceName,
        bool $isDefault = false,
        ?string $roleAsDefault = null,
        SavedViewScope $scope = SavedViewScope::PRIVATE,
    ): SavedView {
        return new SavedView(
            id: $id,
            name: 'View ' . $id,
            resourceName: $resourceName,
            ownerId: $ownerId,
            scope: $scope,
            filters: new FilterCollection($resourceName, []),
            isDefault: $isDefault,
            roleAsDefault: $roleAsDefault,
        );
    }

    private function makeService(string $userIdentifier, InMemorySavedViewStorage $storage): SavedViewService
    {
        $tokenStorage = new TokenStorage();
        $user = new InMemoryUser($userIdentifier, null, ['ROLE_USER']);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturnCallback(
            // Owner-equals-current-user check, simplified — the real
            // voter consults the AuthorizationChecker which itself
            // hits the firewall + voters. For unit tests we directly
            // mimic the voter's owner check.
            static function (string $attribute, mixed $subject = null) use ($userIdentifier): bool {
                if (str_starts_with($attribute, 'ROLE_')) {
                    return true;
                }
                if ($subject instanceof SavedView) {
                    return $subject->ownerId === $userIdentifier;
                }

                return false;
            },
        );

        return new SavedViewService(
            storage: $storage,
            authChecker: $authChecker,
            tokenStorage: $tokenStorage,
        );
    }
}
