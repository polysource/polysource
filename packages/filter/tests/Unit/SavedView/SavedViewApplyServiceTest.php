<?php

declare(strict_types=1);

namespace Polysource\Filter\Tests\Unit\SavedView;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewApplyService;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Storage\InMemorySavedViewStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

#[CoversClass(SavedViewApplyService::class)]
final class SavedViewApplyServiceTest extends TestCase
{
    private const ALICE = 'alice';
    private const RESOURCE = 'App\\Entity\\Order';

    private SavedViewService $savedViewService;
    private SavedViewApplyService $applyService;
    private InMemorySavedViewStorage $storage;

    protected function setUp(): void
    {
        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->willReturn(true);

        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser(self::ALICE, null, ['ROLE_USER']),
            'main',
            ['ROLE_USER'],
        ));

        $this->storage = new InMemorySavedViewStorage();
        $this->savedViewService = new SavedViewService(
            storage: $this->storage,
            authChecker: $authChecker,
            tokenStorage: $tokenStorage,
        );
        $this->applyService = new SavedViewApplyService($this->savedViewService);
    }

    #[Test]
    public function resolveViewReturnsNullForEmptyId(): void
    {
        self::assertNull($this->applyService->resolveView('', self::RESOURCE));
    }

    #[Test]
    public function resolveViewReturnsNullForEmptyResource(): void
    {
        $this->storage->save($this->view('view-1'));

        self::assertNull($this->applyService->resolveView('view-1', ''));
    }

    #[Test]
    public function resolveViewReturnsNullForUnknownView(): void
    {
        self::assertNull($this->applyService->resolveView('unknown', self::RESOURCE));
    }

    #[Test]
    public function resolveViewReturnsNullWhenResourceMismatches(): void
    {
        // Stale link or shared URL across resources — must NOT apply.
        $this->storage->save($this->view('view-1', resource: self::RESOURCE));

        self::assertNull($this->applyService->resolveView('view-1', 'App\\Entity\\Customer'));
    }

    #[Test]
    public function resolveViewReturnsViewOnMatch(): void
    {
        $view = $this->view('view-1');
        $this->storage->save($view);

        $resolved = $this->applyService->resolveView('view-1', self::RESOURCE);

        self::assertNotNull($resolved);
        self::assertSame('view-1', $resolved->id);
    }

    #[Test]
    public function buildRedirectAttachesCacheBustHeaders(): void
    {
        // The Cache-Control + Pragma headers are essential: without
        // them a stale 200 from a prior `?view=<id>` request shadows
        // the 302 and the user sees "first click does nothing". This
        // is a pre-v0.1.0 demo bug that must never regress.
        $response = $this->applyService->buildRedirect('/admin/orders', ['filter' => ['status' => 'paid']]);

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        self::assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        self::assertSame('no-cache', $response->headers->get('Pragma'));
    }

    #[Test]
    public function buildRedirectComposesPathAndQuery(): void
    {
        $response = $this->applyService->buildRedirect(
            '/admin/orders',
            ['filter' => ['status' => 'paid'], 'sort' => ['createdAt' => 'desc']],
        );

        $location = (string) $response->headers->get('Location');
        self::assertStringStartsWith('/admin/orders?', $location);
        self::assertStringContainsString('filter%5Bstatus%5D=paid', $location);
        self::assertStringContainsString('sort%5BcreatedAt%5D=desc', $location);
    }

    #[Test]
    public function buildRedirectOmitsQueryStringWhenQueryIsEmpty(): void
    {
        $response = $this->applyService->buildRedirect('/admin/orders', []);

        self::assertSame('/admin/orders', $response->headers->get('Location'));
    }

    private function view(string $id, string $resource = self::RESOURCE): SavedView
    {
        return new SavedView(
            id: $id,
            name: 'Test view',
            resourceName: $resource,
            ownerId: self::ALICE,
            scope: SavedViewScope::PRIVATE,
            filters: new FilterCollection($resource, [new FilterCriterion('status', 'eq', ['paid'])]),
        );
    }
}
