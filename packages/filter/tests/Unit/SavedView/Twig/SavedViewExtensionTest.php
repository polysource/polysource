<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Storage\InMemorySavedViewStorage;
use Polysource\Filter\SavedView\Twig\SavedViewExtension;
use RuntimeException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Throwable;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(SavedViewExtension::class)]
final class SavedViewExtensionTest extends TestCase
{
    #[Test]
    public function exposesSavedViewTwigFunctions(): void
    {
        // Since v0.1.4 `saved_views_dropdown` is owned by THIS extension
        // (not by polysource/symfony-bundle as in v0.1.0 — v0.1.3). The
        // ownership move is the architectural fix for v0.1.1's
        // install-time crash on bridge-alone installs: the function now
        // ships in the same package as the SavedView data model, and
        // the bridge gets it transitively via `polysource/filter`.
        $extension = $this->makeExtension(visible: [], current: null);

        $functions = $extension->getFunctions();
        // 5 functions since v0.9.0: saved_views_dropdown,
        // polysource_route_exists, polysource_team_scope_supported,
        // polysource_active_saved_view, polysource_csrf_token.
        self::assertCount(5, $functions);

        $names = array_map(static fn ($f) => $f->getName(), $functions);
        self::assertContains(
            'saved_views_dropdown',
            $names,
            'saved_views_dropdown must be registered here (v0.1.4 ownership fix). '
            . 'If you are removing it, ensure the bridge\'s crud/index.html.twig '
            . 'and twig-theme\'s index.html.twig no longer call it.'
        );
        self::assertContains('polysource_route_exists', $names);
        self::assertContains('polysource_active_saved_view', $names);
        self::assertContains('polysource_team_scope_supported', $names);
        self::assertContains('polysource_csrf_token', $names);
    }

    #[Test]
    public function savedViewsDropdownIsMarkedSafeHtml(): void
    {
        // The dropdown renders raw HTML via the configured Twig
        // template. If the safe flag is dropped, hosts will see
        // `&lt;div...` escaped in their pages.
        $extension = $this->makeExtension(visible: [], current: null);

        $function = null;
        foreach ($extension->getFunctions() as $f) {
            if ('saved_views_dropdown' === $f->getName()) {
                $function = $f;
                break;
            }
        }

        self::assertNotNull($function);
        $safe = $function->getSafe(new \Twig\Node\Node());
        self::assertIsArray($safe);
        self::assertContains('html', $safe);
    }

    #[Test]
    public function renderDropdownReturnsEmptyWhenServiceIsNull(): void
    {
        // v0.1.4 graceful-degradation contract: the extension can be
        // constructed with all-null deps (the DI extension does this
        // when DoctrineBundle or SecurityBundle is missing). Calling
        // the Twig function in that state must not crash — it must
        // return an empty string so templates that call it
        // unconditionally still render. Without this guarantee the
        // v0.1.1 install-time crash is one constructor refactor away.
        self::assertSame('', (new SavedViewExtension())->renderDropdown('any-resource'));
    }

    #[Test]
    public function renderDropdownReturnsEmptyWhenStorageThrowsDbalException(): void
    {
        // v0.5.7 graceful-degradation contract: storage IS wired but
        // the underlying Doctrine schema is stale (missing column,
        // missing table, read-only replica with no DDL parity, …).
        // ANY Doctrine failure must NOT 500 every EA index page in
        // the host — the bridge's auto-prepended `crud/index.html.twig`
        // calls `saved_views_dropdown()` unconditionally.
        // Surfaced 2026-05-14 dogfooding round 3 (friction C3): pre-v0.5
        // host upgraded to v0.5.x with stale saved_view table, missing
        // `column_widths_json` column → 500 on every admin page.
        $extension = $this->makeExtensionWithThrowingStorage(
            // Mirrors the dogfood symptom: "Unknown column" SQLSTATE
            // surfacing as a PDO/Doctrine error. Our catch is broad
            // (`Throwable`) so a plain RuntimeException pins the
            // behaviour without pulling a DBAL dependency into the test.
            new RuntimeException('SQLSTATE[42S22]: Column not found: 1054 Unknown column "p0_.column_widths_json"'),
        );

        self::assertSame(
            '',
            $extension->renderDropdown('products', 'inline'),
            'Doctrine exceptions from saved-view storage must degrade silently so EA index pages keep rendering on schema drift',
        );
    }

    #[Test]
    public function activeSavedViewReturnsNullWhenStorageThrowsDbalException(): void
    {
        $extension = $this->makeExtensionWithThrowingStorage(
            new RuntimeException('Stale schema — saved_view.column_widths_json missing'),
        );

        self::assertNull(
            $extension->activeSavedView('products'),
            'Doctrine exceptions from defaultFor() must NOT propagate — host templates depending on the active view degrade to defaults',
        );
    }

    private function makeExtensionWithThrowingStorage(Throwable $throwable): SavedViewExtension
    {
        $storage = new class($throwable) implements \Polysource\Filter\SavedView\Storage\SavedViewStorageInterface {
            public function __construct(private readonly Throwable $throwable)
            {
            }

            public function save(SavedView $view): void
            {
                throw $this->throwable;
            }

            public function find(string $id): ?SavedView
            {
                throw $this->throwable;
            }

            public function listVisible(string $resourceName, string $ownerId, ?string $teamId = null): iterable
            {
                throw $this->throwable;
            }

            public function delete(string $id): void
            {
                throw $this->throwable;
            }
        };

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $tokenStorage = new TokenStorage();
        $user = new InMemoryUser('alice', null, ['ROLE_USER']);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $authChecker,
            tokenStorage: $tokenStorage,
            requestStack: new \Symfony\Component\HttpFoundation\RequestStack(),
        );

        $twig = new Environment(new ArrayLoader([
            'inline' => 'never-reached',
        ]));

        return new SavedViewExtension($service, $twig);
    }

    #[Test]
    public function rendersTemplateWithVisibleViewsAndCurrent(): void
    {
        $views = [
            $this->makeView('a', 'My products', SavedViewScope::PRIVATE),
            $this->makeView('b', 'Shared queue', SavedViewScope::PUBLIC),
        ];
        $current = $views[0];

        $extension = $this->makeExtension(visible: $views, current: $current);

        $template = <<<'TWIG'
            {% for view in views %}{{ view.id }}:{{ view.name }};{% endfor %}|current={{ current is null ? 'none' : current.id }}|resource={{ resource_name }}
            TWIG;

        $output = $extension->renderDropdown('products', 'inline');
        self::assertStringContainsString('a:My products;', $output);
        self::assertStringContainsString('b:Shared queue;', $output);
        self::assertStringContainsString('current=a', $output);
        self::assertStringContainsString('resource=products', $output);
    }

    #[Test]
    public function rendersEmptyMarkerWhenNoViews(): void
    {
        $extension = $this->makeExtension(visible: [], current: null);

        $output = $extension->renderDropdown('products', 'inline');
        self::assertStringContainsString('current=none', $output);
        self::assertStringContainsString('resource=products', $output);
    }

    /**
     * @param list<SavedView> $visible
     */
    private function makeExtension(array $visible, ?SavedView $current): SavedViewExtension
    {
        // SavedViewService is final — can't mock. Use a real instance
        // with in-memory storage + grant-all auth checker. The "current"
        // arg is honored via session (defaultFor's first lookup is the
        // session-remembered last-used view).
        $storage = new InMemorySavedViewStorage();
        foreach ($visible as $view) {
            $storage->save($view);
        }

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $tokenStorage = new TokenStorage();
        $user = new InMemoryUser('alice', null, ['ROLE_USER']);
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        if (null !== $current) {
            $session = new \Symfony\Component\HttpFoundation\Session\Session(
                new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage(),
            );
            $session->start();
            $session->set('polysource.filter.saved_view.last.products', $current->id);
            // The new defaultFor() contract only treats a saved view
            // as `current` when the request URL carries `?view=<id>`
            // (apply round-trip). Simulate that hop here so the
            // dropdown render matches what a real apply produces.
            $request = new \Symfony\Component\HttpFoundation\Request(['view' => $current->id]);
            $request->setSession($session);
            $requestStack->push($request);
        }

        $service = new SavedViewService(
            storage: $storage,
            authChecker: $authChecker,
            tokenStorage: $tokenStorage,
            requestStack: $requestStack,
        );

        $twig = new Environment(new ArrayLoader([
            // The default template is at @PolysourceFilter/saved_view/dropdown.html.twig;
            // unit tests inline a tiny fixture so we don't need to register
            // the bundle's namespace.
            'inline' => '{% for view in views %}{{ view.id }}:{{ view.name }};{% endfor %}|current={{ current is null ? \'none\' : current.id }}|resource={{ resource_name }}',
        ]));

        return new SavedViewExtension($service, $twig);
    }

    private function makeView(
        string $id,
        string $name,
        SavedViewScope $scope,
        ?string $teamId = null,
    ): SavedView {
        return new SavedView(
            id: $id,
            name: $name,
            resourceName: 'products',
            ownerId: 'alice',
            scope: $scope,
            filters: new FilterCollection('products', [
                new FilterCriterion('isActive', 'eq', ['1']),
            ]),
            teamId: $teamId,
        );
    }
}
