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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(SavedViewExtension::class)]
final class SavedViewExtensionTest extends TestCase
{
    #[Test]
    public function exposesSavedViewsDropdownTwigFunction(): void
    {
        $extension = $this->makeExtension(visible: [], current: null);

        $functions = $extension->getFunctions();
        self::assertCount(1, $functions);

        $function = $functions[0];
        self::assertSame('saved_views_dropdown', $function->getName());

        $safe = $function->getSafe(new \Twig\Node\Node());
        self::assertNotNull($safe, 'TwigFunction should declare an is_safe spec');
        self::assertContains('html', $safe);
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
            $request = new \Symfony\Component\HttpFoundation\Request();
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
