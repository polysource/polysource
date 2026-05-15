<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Functional\SavedView;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Security\SavedViewVoter;
use Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord;
use Polysource\Filter\SavedView\Storage\DoctrineSavedViewStorage;
use Polysource\Filter\SavedView\Twig\SavedViewExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * End-to-end functional test for the saved-views feature stack:
 * Doctrine storage (SQLite in-memory) + voter + service + Twig
 * extension with the real bundled `dropdown.html.twig` template.
 *
 * Boots a self-contained Doctrine ORM + Symfony Security setup
 * without a full Symfony kernel — keeps the test fast and isolated
 * while still exercising the full vertical slice (database write
 * → permission check → Twig render).
 *
 * Validates ADR-019 §2-7 ship a working pipeline end to end.
 */
final class SavedViewEndToEndTest extends TestCase
{
    private EntityManager $entityManager;
    private DoctrineSavedViewStorage $storage;
    private SavedViewService $service;
    private SavedViewExtension $twigExtension;

    protected function setUp(): void
    {
        // 1. Doctrine ORM + SQLite in-memory.
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [\dirname(__DIR__, 3) . '/src/SavedView/Storage/Doctrine'],
            isDevMode: true,
        );
        // Doctrine 3.x defaults to lazy-ghost proxies that require either
        // symfony/var-exporter or PHP 8.4 native objects. The CI matrix
        // spans PHP 8.1→8.4, so gate the native opt-in on 8.4+ — older
        // PHPs fall back to symfony/var-exporter (always available via
        // Symfony 6.2+), and this entity has no associations anyway.
        if (\PHP_VERSION_ID >= 80400) {
            $config->enableNativeLazyObjects(true);
        }
        $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->entityManager = new EntityManager($connection, $config);

        // 2. Schema for the SavedViewRecord entity.
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->createSchema([
            $this->entityManager->getClassMetadata(SavedViewRecord::class),
        ]);

        // 3. Storage on top of the EM.
        $this->storage = new DoctrineSavedViewStorage($this->entityManager);

        // 4. Token storage with an authenticated user.
        $tokenStorage = new TokenStorage();
        $user = new InMemoryUser('alice', null, ['ROLE_USER']);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        // 5. Authorization checker wired to the real voter — no mocks.
        $voter = new SavedViewVoter();
        $accessDecisionManager = new AccessDecisionManager([$voter]);
        $authChecker = new AuthorizationChecker($tokenStorage, $accessDecisionManager);

        // 6. RequestStack with a started session for last-used persistence.
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack->push($request);

        // 7. Service.
        $this->service = new SavedViewService(
            storage: $this->storage,
            authChecker: $authChecker,
            tokenStorage: $tokenStorage,
            requestStack: $requestStack,
        );

        // 8. Twig with the real bundled dropdown template.
        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 3) . '/Resources/views', 'PolysourceFilter');
        $twig = new Environment($loader);
        // Stub `path()` and translation helpers used by the templates.
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $name, array $params = []): string => '/'));
        $twig->addExtension(new TranslationStubExtension());

        $this->twigExtension = new SavedViewExtension($this->service, $twig);
        // Register on the env so the bundled save_modal.html.twig (used by
        // renderDropdown) can call `polysource_route_exists` exposed by
        // the extension itself.
        $twig->addExtension($this->twigExtension);
    }

    #[Test]
    public function fullRoundtripSaveListLoadDeleteRender(): void
    {
        // Save 3 views with distinct scopes.
        $aliceViewPrivate = $this->makeView('view-1', 'My products', SavedViewScope::PRIVATE);
        $aliceViewPublic = $this->makeView('view-2', 'Active products', SavedViewScope::PUBLIC);
        $bobViewPrivate = $this->makeView('view-3', 'Bob private', SavedViewScope::PRIVATE, ownerId: 'bob');

        $this->service->save($aliceViewPrivate);
        $this->service->save($aliceViewPublic);
        // Bob's private view is saved via the storage directly (Alice can't save Bob's view).
        $this->storage->save($bobViewPrivate);

        // Alice sees: her private + her public + (because PUBLIC is universal) Bob's would only show if PUBLIC.
        $visible = $this->service->listVisible('products');
        $ids = array_map(static fn (SavedView $v): string => $v->id, $visible);
        sort($ids);
        self::assertSame(['view-1', 'view-2'], $ids, 'Alice must see her own + the public view; Bob private is hidden.');

        // Load by id, voter-gated.
        $loaded = $this->service->load('view-1');
        self::assertNotNull($loaded);
        self::assertSame('My products', $loaded->name);
        self::assertCount(1, $loaded->filters);

        // Loading Bob's private view returns null (voter denies).
        self::assertNull($this->service->load('view-3'));

        // Twig dropdown render shows alice's two views + the "Save" trigger.
        $output = $this->twigExtension->renderDropdown('products');
        self::assertStringContainsString('My products', $output);
        self::assertStringContainsString('Active products', $output);
        self::assertStringNotContainsString('Bob private', $output, 'Voter must hide Bob\'s view from Alice.');
        self::assertStringContainsString('polysource-saved-views', $output);
        // The dropdown HTML carries an apply-link (?view=...) for each visible view.
        self::assertStringContainsString('?view=view-1', $output);
        self::assertStringContainsString('?view=view-2', $output);

        // Delete one — voter passes for owner.
        $this->service->delete('view-1');
        self::assertNull($this->storage->find('view-1'));

        // After delete, dropdown re-render no longer shows it.
        $output = $this->twigExtension->renderDropdown('products');
        self::assertStringNotContainsString('My products', $output);
        self::assertStringContainsString('Active products', $output);
    }

    #[Test]
    public function defaultForReturnsNullOnCleanUrlEvenWithSessionEntry(): void
    {
        // The previous contract auto-returned the
        // session-remembered view on every defaultFor() call —
        // including on a "clean URL" with no `?view=` and no
        // `?filter[...]=...`. That made the dropdown lie: it
        // highlighted the saved view as `current`, but the
        // rendered table was unfiltered.
        //
        // The new contract: `current` only fires when (a) the URL
        // carries the matching `?view=<id>` (apply round-trip), or
        // (b) a role-default view applies. Clean URL = null. The
        // session entry is preserved (so re-applying via the
        // dropdown stays one click) but it no longer leaks into
        // the displayed `current` state.
        $alice = $this->makeView('view-default', 'Daily dashboard', SavedViewScope::PRIVATE);
        $this->service->save($alice);

        // No session, clean URL → null.
        self::assertNull($this->service->defaultFor('products'));

        // Loading sets the session marker.
        $this->service->load('view-default');

        // Clean URL still: dropdown does not lie about current.
        self::assertNull($this->service->defaultFor('products'));
        // The bundled dropdown.html.twig flips the trigger label to
        // the i18n placeholder when `current` is null AND skips
        // emitting the `active` class on every menu row. Assert
        // both: no `<span class="polysource-saved-views__current">`
        // (only rendered when current is not null) AND no `active`
        // marker on Daily dashboard.
        $output = $this->twigExtension->renderDropdown('products');
        self::assertStringNotContainsString('polysource-saved-views__current', $output);
        self::assertStringNotContainsString('polysource-saved-views__item active', $output);
    }

    #[Test]
    public function teamScopeVisibilityAcrossUsersInSameAndDifferentTeams(): void
    {
        // v0.6.1 — locks in the TEAM-scope contract end-to-end:
        // a TEAM view is visible to every user whose
        // TeamResolver::teamIdFor() returns the matching teamId, and
        // hidden from users in other teams.
        //
        // Closes Tier 3 item 12 from the v0.6.0 wrap-up — the voter
        // was already covered by SavedViewVoterTest unit tests but
        // no functional test exercised the full
        // service.listVisible() filtering with a wired resolver.

        $teamResolver = new StaticTeamResolver([
            'alice' => 'team-red',
            'bob' => 'team-red',
            'carol' => 'team-blue',
        ]);

        // Alice creates a TEAM-scoped view for team-red.
        $aliceTeamView = new SavedView(
            id: 'view-team-red',
            name: 'Q4 review',
            resourceName: 'products',
            ownerId: 'alice',
            scope: SavedViewScope::TEAM,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
            teamId: 'team-red',
        );
        $this->storage->save($aliceTeamView);

        // Spin up a 2nd service+authChecker for Bob (same team as Alice).
        $bobTokenStorage = new TokenStorage();
        $bobUser = new InMemoryUser('bob', null, ['ROLE_USER']);
        $bobTokenStorage->setToken(new UsernamePasswordToken($bobUser, 'main', $bobUser->getRoles()));
        $bobVoter = new SavedViewVoter(teamResolver: $teamResolver);
        $bobAuth = new AuthorizationChecker($bobTokenStorage, new AccessDecisionManager([$bobVoter]));
        $bobService = new SavedViewService(
            storage: $this->storage,
            authChecker: $bobAuth,
            tokenStorage: $bobTokenStorage,
            teamResolver: $teamResolver,
            requestStack: $this->newRequestStack(),
        );

        // Bob is a teammate → he sees Alice's TEAM view.
        $bobVisible = $bobService->listVisible('products');
        $bobIds = array_map(static fn (SavedView $v): string => $v->id, $bobVisible);
        self::assertContains('view-team-red', $bobIds, 'Bob (team-red) must see Alice\'s TEAM-scoped view');

        // Carol — different team → she does NOT see Alice's TEAM view.
        $carolTokenStorage = new TokenStorage();
        $carolUser = new InMemoryUser('carol', null, ['ROLE_USER']);
        $carolTokenStorage->setToken(new UsernamePasswordToken($carolUser, 'main', $carolUser->getRoles()));
        $carolVoter = new SavedViewVoter(teamResolver: $teamResolver);
        $carolAuth = new AuthorizationChecker($carolTokenStorage, new AccessDecisionManager([$carolVoter]));
        $carolService = new SavedViewService(
            storage: $this->storage,
            authChecker: $carolAuth,
            tokenStorage: $carolTokenStorage,
            teamResolver: $teamResolver,
            requestStack: $this->newRequestStack(),
        );

        $carolVisible = $carolService->listVisible('products');
        $carolIds = array_map(static fn (SavedView $v): string => $v->id, $carolVisible);
        self::assertNotContains('view-team-red', $carolIds, 'Carol (team-blue) must NOT see Alice\'s team-red view');

        // Anonymous user: TEAM views are never visible without a token.
        $anonTokenStorage = new TokenStorage(); // no token set
        $anonVoter = new SavedViewVoter(teamResolver: $teamResolver);
        $anonAuth = new AuthorizationChecker($anonTokenStorage, new AccessDecisionManager([$anonVoter]));
        $anonService = new SavedViewService(
            storage: $this->storage,
            authChecker: $anonAuth,
            tokenStorage: $anonTokenStorage,
            teamResolver: $teamResolver,
            requestStack: $this->newRequestStack(),
        );
        $anonVisible = $anonService->listVisible('products');
        $anonIds = array_map(static fn (SavedView $v): string => $v->id, $anonVisible);
        self::assertNotContains('view-team-red', $anonIds, 'Anonymous user must NOT see TEAM views');
    }

    private function newRequestStack(): RequestStack
    {
        $stack = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack->push($request);

        return $stack;
    }

    private function makeView(
        string $id,
        string $name,
        SavedViewScope $scope,
        string $ownerId = 'alice',
    ): SavedView {
        return new SavedView(
            id: $id,
            name: $name,
            resourceName: 'products',
            ownerId: $ownerId,
            scope: $scope,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
        );
    }
}

/**
 * Static map-based team resolver — useful as a test fixture and as
 * a reference impl hosts can crib for their own user→team mapping.
 *
 * @internal test fixture
 */
final class StaticTeamResolver implements \Polysource\Filter\SavedView\Security\SavedViewTeamResolverInterface
{
    /**
     * @param array<string, string> $userToTeam map of userIdentifier → teamId
     */
    public function __construct(private readonly array $userToTeam)
    {
    }

    public function teamIdFor(\Symfony\Component\Security\Core\User\UserInterface $user): ?string
    {
        return $this->userToTeam[$user->getUserIdentifier()] ?? null;
    }
}

/**
 * Stub Twig translator filter so the dropdown template can call
 * `'foo'|trans` without a real Symfony translator being wired.
 *
 * @internal
 */
final class TranslationStubExtension extends \Twig\Extension\AbstractExtension
{
    /**
     * @return list<\Twig\TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new \Twig\TwigFilter(
                'trans',
                static fn (string $key): string => $key, // pass-through, returns the key
            ),
        ];
    }
}
