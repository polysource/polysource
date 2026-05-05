<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Exception\SavedViewDuplicateNameException;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Security\SavedViewVoter;
use Polysource\Filter\SavedView\Storage\InMemorySavedViewStorage;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SavedViewService::class)]
final class SavedViewServiceTest extends TestCase
{
    #[Test]
    public function listVisibleFiltersThroughVoter(): void
    {
        $storage = new InMemorySavedViewStorage();
        $aliceView = $this->makeView('a', ownerId: 'alice', scope: SavedViewScope::PUBLIC);
        $bobView = $this->makeView('b', ownerId: 'bob', scope: SavedViewScope::PUBLIC);
        $storage->save($aliceView);
        $storage->save($bobView);

        // Voter denies Bob's view via a host policy (e.g. blacklisted)
        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->method('isGranted')->willReturnCallback(
            static fn (string $attr, mixed $subject = null): bool => $subject instanceof SavedView && 'a' === $subject->id,
        );

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $voter,
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $visible = $service->listVisible('products');
        self::assertCount(1, $visible);
        self::assertSame('a', $visible[0]->id);
    }

    #[Test]
    public function loadReturnsNullWhenAbsent(): void
    {
        $service = new SavedViewService(
            storage: new InMemorySavedViewStorage(),
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        self::assertNull($service->load('missing'));
    }

    #[Test]
    public function loadReturnsNullWhenVoterDenies(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'bob', scope: SavedViewScope::PRIVATE));

        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->method('isGranted')->willReturn(false);

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $voter,
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        self::assertNull($service->load('a'));
    }

    #[Test]
    public function loadRoundtripsAndRemembersAsLastUsed(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'alice'));

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $requestStack = new RequestStack();
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->setSession($session);
        $requestStack->push($request);

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('alice'),
            requestStack: $requestStack,
        );

        $loaded = $service->load('a');
        self::assertNotNull($loaded);
        self::assertSame(
            'a',
            $session->get('polysource.filter.saved_view.last.products'),
            'load() should remember the view id under the resource-specific session key.',
        );
    }

    #[Test]
    public function saveOnNewViewBypassesEditCheck(): void
    {
        $storage = new InMemorySavedViewStorage();

        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->expects(self::never())->method('isGranted'); // no existing view → no voter call

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $voter,
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $view = $this->makeView('a');
        $service->save($view);

        self::assertNotNull($storage->find('a'));
    }

    #[Test]
    public function saveOnExistingViewRequiresEdit(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a'));

        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->method('isGranted')->willReturn(false);

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $voter,
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to edit saved view "a"');
        $service->save($this->makeView('a'));
    }

    #[Test]
    public function saveOnScopeChangeRequiresShare(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', scope: SavedViewScope::PRIVATE));

        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->method('isGranted')->willReturnCallback(
            static fn (string $attribute): bool => SavedViewVoter::EDIT === $attribute,
        );

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $voter,
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Not authorized to change scope');
        $service->save($this->makeView('a', scope: SavedViewScope::PUBLIC));
    }

    #[Test]
    public function deleteRequiresVoterApproval(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a'));

        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->method('isGranted')->willReturn(false);

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $voter,
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $this->expectException(RuntimeException::class);
        $service->delete('a');
    }

    #[Test]
    public function deleteOnUnknownIdIsNoOp(): void
    {
        $service = new SavedViewService(
            storage: new InMemorySavedViewStorage(),
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $service->delete('never-existed');
        $this->expectNotToPerformAssertions();
    }

    #[Test]
    public function saveRejectsDuplicateNameForSameOwnerAndResource(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'alice'));

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        // makeView() defaults the name to "View {id}" — same owner, same
        // resource, different id, but try to give it the same name as
        // view 'a'.
        $duplicate = new SavedView(
            id: 'b',
            name: 'View a', // collides with id=a's auto-name
            resourceName: 'products',
            ownerId: 'alice',
            scope: SavedViewScope::PRIVATE,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
        );

        $this->expectException(SavedViewDuplicateNameException::class);
        $this->expectExceptionMessage('saved view named "View a" already exists for user "alice"');
        $service->save($duplicate);
    }

    #[Test]
    public function saveAllowsSameNameForDifferentOwners(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'alice'));

        // Bob wants to save a view with the same name as alice's.
        // Two separate users => two separate buckets => OK.
        $bobView = new SavedView(
            id: 'b',
            name: 'View a',
            resourceName: 'products',
            ownerId: 'bob',
            scope: SavedViewScope::PRIVATE,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
        );

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('bob'),
        );

        $service->save($bobView);
        self::assertNotNull($storage->find('b'));
    }

    #[Test]
    public function saveAllowsRenameOfTheSameView(): void
    {
        // The duplicate-name check must NOT fire when the same view
        // is being saved (same id) with potentially renamed data.
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'alice'));

        $renamed = new SavedView(
            id: 'a', // same id
            name: 'Brand new name',
            resourceName: 'products',
            ownerId: 'alice',
            scope: SavedViewScope::PRIVATE,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
        );

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $service->save($renamed);
        $persisted = $storage->find('a');
        self::assertNotNull($persisted);
        self::assertSame('Brand new name', $persisted->name);
    }

    #[Test]
    public function defaultForReturnsSessionRememberedView(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView('a', ownerId: 'alice'));

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set('polysource.filter.saved_view.last.products', 'a');
        $requestStack = new RequestStack();
        $request = new \Symfony\Component\HttpFoundation\Request();
        $request->setSession($session);
        $requestStack->push($request);

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('alice'),
            requestStack: $requestStack,
        );

        $default = $service->defaultFor('products');
        self::assertNotNull($default);
        self::assertSame('a', $default->id);
    }

    #[Test]
    public function defaultForFallsBackToRoleDefault(): void
    {
        $storage = new InMemorySavedViewStorage();
        $storage->save($this->makeView(
            'a',
            ownerId: 'alice',
            scope: SavedViewScope::PUBLIC,
            isDefault: true,
            roleAsDefault: 'ROLE_USER',
        ));

        $voter = $this->createMock(AuthorizationCheckerInterface::class);
        $voter->method('isGranted')->willReturn(true);

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $voter,
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        $default = $service->defaultFor('products');
        self::assertNotNull($default);
        self::assertSame('a', $default->id);
    }

    #[Test]
    public function defaultForReturnsNullWhenNoMatch(): void
    {
        $service = new SavedViewService(
            storage: new InMemorySavedViewStorage(),
            authChecker: $this->grantAllChecker(),
            tokenStorage: $this->tokenStorageFor('alice'),
        );

        self::assertNull($service->defaultFor('products'));
    }

    private function makeView(
        string $id,
        string $ownerId = 'alice',
        SavedViewScope $scope = SavedViewScope::PRIVATE,
        ?string $teamId = null,
        bool $isDefault = false,
        ?string $roleAsDefault = null,
    ): SavedView {
        return new SavedView(
            id: $id,
            name: "View {$id}",
            resourceName: 'products',
            ownerId: $ownerId,
            scope: $scope,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
            teamId: $teamId,
            isDefault: $isDefault,
            roleAsDefault: $roleAsDefault,
        );
    }

    private function tokenStorageFor(string $userId): TokenStorage
    {
        $storage = new TokenStorage();
        $user = new InMemoryUser($userId, null, ['ROLE_USER']);
        $storage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        return $storage;
    }

    private function grantAllChecker(): AuthorizationCheckerInterface
    {
        $checker = $this->createMock(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn(true);

        return $checker;
    }
}
