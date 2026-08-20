<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\EventListener;

use BadMethodCallException;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\EventListener\FilterSessionPersistenceSubscriber;
use Polysource\Filter\Service\FilterService;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Verifies that the bridge's subscriber delegates persistence to
 * `polysource/filter`'s `FilterService` and keeps the EA-specific
 * Referer-based reset detection + canonical-path redirect logic.
 *
 * Uses a real `FilterService` + `InMemorySession` rather than mocks
 * because `FilterService` is final. We assert the OBSERVABLE effect
 * (session contents after the subscriber runs / response set on the
 * event) instead of which methods got called.
 */
final class FilterSessionPersistenceSubscriberTest extends TestCase
{
    private const PRODUCT_FQCN = 'App\\Controller\\Admin\\ProductCrudController';

    private InMemorySession $session;
    private RequestStack $requestStack;
    private FilterService $filterService;

    protected function setUp(): void
    {
        $this->session = new InMemorySession();
        $this->requestStack = new RequestStack();
        $request = new Request();
        $request->setSession($this->session);
        $this->requestStack->push($request);

        $this->filterService = new FilterService($this->requestStack);
    }

    public function testSubscribesToBeforeCrudActionEvent(): void
    {
        $events = FilterSessionPersistenceSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(BeforeCrudActionEvent::class, $events);
        self::assertSame('onBeforeCrudAction', $events[BeforeCrudActionEvent::class]);
    }

    public function testSaveStoresCollectionInSession(): void
    {
        $request = Request::create('/admin?crudAction=index&filters%5BcreatedAt%5D%5Bvalue%5D=2026-05-01&filters%5BcreatedAt%5D%5Bcomparison%5D=%3E%3D');
        $event = $this->makeEvent($request);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        $loaded = $this->filterService->load(self::PRODUCT_FQCN);
        self::assertNotNull($loaded);
        self::assertCount(1, $loaded);
        self::assertSame('createdAt', $loaded->criteria[0]->property);
        self::assertSame('>=', $loaded->criteria[0]->operator);
        self::assertSame(['2026-05-01'], $loaded->criteria[0]->values);
    }

    public function testLoadRedirectsWithUrlEncodedFiltersWhenSessionHasACollection(): void
    {
        // Pre-seed the session through the service (going through
        // FilterService keeps the same hash key the subscriber will
        // look up).
        $this->filterService->save(new \Polysource\Filter\Model\FilterCollection(self::PRODUCT_FQCN, [
            new \Polysource\Filter\Model\FilterCriterion('price', 'between', [50, 200]),
        ]));

        $request = Request::create('/admin/product');
        $event = $this->makeEvent($request);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('filters%5Bprice%5D%5Bcomparison%5D=between', $response->getTargetUrl());
        self::assertStringContainsString('filters%5Bprice%5D%5Bvalue%5D=50', $response->getTargetUrl());
        self::assertStringContainsString('filters%5Bprice%5D%5Bvalue2%5D=200', $response->getTargetUrl());
    }

    public function testExplicitResetClearsSession(): void
    {
        $this->filterService->save(new \Polysource\Filter\Model\FilterCollection(self::PRODUCT_FQCN, [
            new \Polysource\Filter\Model\FilterCriterion('name', 'like', ['hat']),
        ]));

        $request = Request::create('/admin/product');
        $request->headers->set('Referer', 'http://localhost/admin/product?filters%5Bname%5D%5Bvalue%5D=hat');
        $event = $this->makeEvent($request);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        self::assertNull($this->filterService->load(self::PRODUCT_FQCN));
    }

    /**
     * The reset redirect must not drop non-filter query parameters:
     * hosts scope CRUDs with context params (`?deploymentGroup=2`) and
     * the previous bare-path canonicalisation 404'd them (2026-08 host
     * regression). When the URL already IS canonical (context params
     * only, no `filters`), no redirect at all — the page just renders.
     */
    public function testExplicitResetKeepsNonFilterQueryParamsWithoutRedirect(): void
    {
        $this->filterService->save(new \Polysource\Filter\Model\FilterCollection(self::PRODUCT_FQCN, [
            new \Polysource\Filter\Model\FilterCriterion('name', 'like', ['hat']),
        ]));

        $request = Request::create('/admin/product?deploymentGroup=2');
        $request->headers->set('Referer', 'http://localhost/admin/product?deploymentGroup=2&filters%5Bname%5D%5Bvalue%5D=hat');
        $event = $this->makeEvent($request);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        self::assertNull($this->filterService->load(self::PRODUCT_FQCN), 'session must still be cleared');
        self::assertFalse($event->isPropagationStopped(), 'canonical URL with context params must render, not redirect');
    }

    public function testExplicitResetRedirectPreservesNonFilterQueryParams(): void
    {
        $this->filterService->save(new \Polysource\Filter\Model\FilterCollection(self::PRODUCT_FQCN, [
            new \Polysource\Filter\Model\FilterCriterion('name', 'like', ['hat']),
        ]));

        // Non-canonical URI (trailing '&' artefact) → redirect, but to a
        // URL that keeps the host's context param.
        $request = Request::create('/admin/product?deploymentGroup=2&');
        $request->headers->set('Referer', 'http://localhost/admin/product?filters%5Bname%5D%5Bvalue%5D=hat');
        $event = $this->makeEvent($request);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/product?deploymentGroup=2', $response->getTargetUrl());
    }

    public function testExplicitResetTrailingQuestionMarkRedirectsToBarePath(): void
    {
        $request = Request::create('/admin/product');
        $request->server->set('REQUEST_URI', '/admin/product?');
        $request->headers->set('Referer', 'http://localhost/admin/product?filters%5Bname%5D%5Bvalue%5D=hat');
        $event = $this->makeEvent($request);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/admin/product', $response->getTargetUrl());
    }

    public function testNoOpForNonIndexAction(): void
    {
        $request = Request::create('/admin/product/1/edit?filters%5Bname%5D%5Bvalue%5D=hat');
        $event = $this->makeEvent($request, currentAction: Action::EDIT);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        self::assertNull($this->filterService->load(self::PRODUCT_FQCN));
        self::assertFalse($event->isPropagationStopped(), 'no response set means propagation continues');
    }

    public function testLoadReturningNullLeavesRequestUntouched(): void
    {
        // Empty session — load returns null, no redirect.
        $request = Request::create('/admin/product');
        $event = $this->makeEvent($request);

        (new FilterSessionPersistenceSubscriber($this->filterService))->onBeforeCrudAction($event);

        self::assertFalse($event->isPropagationStopped(), 'no response set means no redirect');
    }

    private function makeEvent(
        Request $request,
        string $currentAction = Action::INDEX,
        string $controllerFqcn = self::PRODUCT_FQCN,
    ): BeforeCrudActionEvent {
        $crudDto = new CrudDto();
        $crudDto->setCurrentAction($currentAction);
        /** @phpstan-ignore-next-line argument.type — test passes an arbitrary string FQCN */
        $crudDto->setControllerFqcn($controllerFqcn);

        $crudContext = new CrudContext(
            crudDto: $crudDto,
            entityDto: null,
            searchDto: null,
            adminControllers: $this->createMock(AdminControllerRegistryInterface::class),
        );

        $requestContext = new \EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext(
            request: $request,
            user: null,
        );

        $context = (new ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();

        $crudContextProp = new ReflectionProperty(AdminContext::class, 'crudContext');
        $crudContextProp->setAccessible(true);
        $crudContextProp->setValue($context, $crudContext);

        $requestContextProp = new ReflectionProperty(AdminContext::class, 'requestContext');
        $requestContextProp->setAccessible(true);
        $requestContextProp->setValue($context, $requestContext);

        return new BeforeCrudActionEvent($context);
    }
}

/**
 * Tiny in-memory session double — same shape as the one in
 * polysource/filter's FilterServiceTest.
 */
final class InMemorySession implements SessionInterface
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function start(): bool
    {
        return true;
    }

    public function getId(): string
    {
        return 'in-memory';
    }

    public function setId(string $id): void
    {
    }

    public function getName(): string
    {
        return 'session';
    }

    public function setName(string $name): void
    {
    }

    public function invalidate(?int $lifetime = null): bool
    {
        $this->data = [];

        return true;
    }

    public function migrate(bool $destroy = false, ?int $lifetime = null): bool
    {
        return true;
    }

    public function save(): void
    {
    }

    public function has(string $name): bool
    {
        return isset($this->data[$name]);
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->data[$name] ?? $default;
    }

    public function set(string $name, mixed $value): void
    {
        $this->data[$name] = $value;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->data;
    }

    /** @phpstan-ignore-next-line missingType.iterableValue — LSP requires bare `array` to match SessionInterface::replace() */
    public function replace(array $attributes): void
    {
        /** @var array<string, mixed> $attributes */
        $this->data = $attributes;
    }

    public function remove(string $name): mixed
    {
        $v = $this->data[$name] ?? null;
        unset($this->data[$name]);

        return $v;
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function isStarted(): bool
    {
        return true;
    }

    public function registerBag(\Symfony\Component\HttpFoundation\Session\SessionBagInterface $bag): void
    {
    }

    public function getBag(string $name): \Symfony\Component\HttpFoundation\Session\SessionBagInterface
    {
        throw new BadMethodCallException();
    }

    public function getMetadataBag(): \Symfony\Component\HttpFoundation\Session\Storage\MetadataBag
    {
        throw new BadMethodCallException();
    }
}
