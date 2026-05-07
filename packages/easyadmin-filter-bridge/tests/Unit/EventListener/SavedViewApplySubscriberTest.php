<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterConfigDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\EventListener\SavedViewApplySubscriber;
use Polysource\Filter\Model\FilterCollection;
use Polysource\Filter\Model\FilterCriterion;
use Polysource\Filter\SavedView\Model\SavedView;
use Polysource\Filter\SavedView\Model\SavedViewScope;
use Polysource\Filter\SavedView\SavedViewService;
use Polysource\Filter\SavedView\Storage\InMemorySavedViewStorage;
use ReflectionClass;
use ReflectionProperty;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Pins the BeforeCrudAction lifecycle of {@see SavedViewApplySubscriber} —
 * the redirect path users hit when clicking a saved view in the dropdown.
 *
 * The unit tests already cover URL-shape generation in isolation. This
 * file proves the orchestration around it:
 *
 *   1. No `view` param           → no redirect (event untouched).
 *   2. Unknown view id           → no redirect.
 *   3. View resourceName mismatch → no redirect (cross-resource theft guard).
 *   4. Empty filter set on view  → no redirect (avoids ?-only redirect loop).
 *   5. Valid view + extra params → redirect URL drops `view`, keeps `sort`/`page`.
 *   6. BooleanFilter → bare scalar in redirect URL.
 *
 * Each scenario was a real bug surfaced during the 2026-05-07 client integration.
 */
final class SavedViewApplySubscriberTest extends TestCase
{
    private const ORDER_FQCN = 'App\\Entity\\Order';
    private const PRODUCT_FQCN = 'App\\Entity\\Product';
    private const ALICE = 'alice';

    private SavedViewService $service;
    private RequestStack $requestStack;
    private SavedViewApplySubscriber $subscriber;

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

        $this->service = new SavedViewService(
            storage: new InMemorySavedViewStorage(),
            authChecker: $authChecker,
            tokenStorage: $tokenStorage,
        );
        $this->requestStack = new RequestStack();
        $this->subscriber = new SavedViewApplySubscriber($this->service, $this->requestStack);
    }

    #[Test]
    public function noViewQueryParamLeavesEventUntouched(): void
    {
        $event = $this->makeEventForRequest(Request::create('/admin?crudControllerFqcn=OrderCrud'));

        $this->subscriber->onBeforeCrudAction($event);

        self::assertFalse($event->isPropagationStopped(), 'no response = no redirect');
    }

    #[Test]
    public function unknownViewIdLeavesEventUntouched(): void
    {
        $request = Request::create('/admin?crudControllerFqcn=OrderCrud&view=does-not-exist');
        $event = $this->makeEventForRequest($request);

        $this->subscriber->onBeforeCrudAction($event);

        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function viewBelongingToDifferentResourceIsRejectedSilently(): void
    {
        // Save a view under PRODUCT_FQCN, request lands on ORDER_FQCN page.
        $view = $this->persistView(
            resourceName: self::PRODUCT_FQCN,
            name: 'My Products',
            filters: new FilterCollection(self::PRODUCT_FQCN, [new FilterCriterion('name', 'like', ['Hat'])]),
        );

        $request = Request::create('/admin?crudControllerFqcn=OrderCrud&view=' . $view->id);
        $event = $this->makeEventForRequest($request, entityFqcn: self::ORDER_FQCN);

        $this->subscriber->onBeforeCrudAction($event);

        self::assertFalse(
            $event->isPropagationStopped(),
            'cross-resource view ids must not redirect — that would leak filter state across CRUD controllers',
        );
    }

    #[Test]
    public function viewWithEmptyFilterSetLeavesEventUntouched(): void
    {
        // Edge case: somehow a view with no criteria exists. Replaying it
        // would emit `?` with no params — pointless redirect, breaks back-button
        // history. The subscriber should bail.
        $view = $this->persistView(
            resourceName: self::ORDER_FQCN,
            name: 'Empty',
            filters: new FilterCollection(self::ORDER_FQCN, []),
        );

        $request = Request::create('/admin?crudControllerFqcn=OrderCrud&view=' . $view->id);
        $event = $this->makeEventForRequest($request, entityFqcn: self::ORDER_FQCN);

        $this->subscriber->onBeforeCrudAction($event);

        self::assertFalse($event->isPropagationStopped());
    }

    #[Test]
    public function validViewRedirectsToFilteredUrlAndDropsViewParam(): void
    {
        $view = $this->persistView(
            resourceName: self::ORDER_FQCN,
            name: 'Pending orders',
            filters: new FilterCollection(self::ORDER_FQCN, [
                new FilterCriterion('name', 'like', ['Pending']),
            ]),
        );

        $request = Request::create('/admin?crudControllerFqcn=OrderCrud&view=' . $view->id);
        $event = $this->makeEventForRequest($request, entityFqcn: self::ORDER_FQCN, filters: [
            TextFilter::new('name'),
        ]);

        $this->subscriber->onBeforeCrudAction($event);

        self::assertTrue($event->isPropagationStopped(), 'a valid view must produce a redirect');
        $response = $event->getResponse();
        self::assertSame(302, $response->getStatusCode());

        $location = $response->headers->get('Location') ?? '';
        self::assertStringNotContainsString('view=', $location, 'redirect URL must not preserve the view param');
        self::assertStringContainsString('filters%5Bname%5D%5Bcomparison%5D=like', $location);
        self::assertStringContainsString('filters%5Bname%5D%5Bvalue%5D=Pending', $location);
    }

    #[Test]
    public function redirectPreservesUnrelatedQueryParamsLikeSortAndPage(): void
    {
        $view = $this->persistView(
            resourceName: self::ORDER_FQCN,
            name: 'High value',
            filters: new FilterCollection(self::ORDER_FQCN, [
                new FilterCriterion('total', 'gte', ['100']),
            ]),
        );

        // User clicks the saved view on a page that already has sort+page.
        $request = Request::create(\sprintf(
            '/admin?crudControllerFqcn=OrderCrud&sort%%5BcreatedAt%%5D=DESC&page=2&view=%s',
            $view->id,
        ));
        $event = $this->makeEventForRequest($request, entityFqcn: self::ORDER_FQCN, filters: [
            NumericFilter::new('total'),
        ]);

        $this->subscriber->onBeforeCrudAction($event);

        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        $location = $response->headers->get('Location') ?? '';

        self::assertStringContainsString('sort%5BcreatedAt%5D=DESC', $location, 'sort must survive the redirect');
        self::assertStringContainsString('page=2', $location, 'page must survive the redirect');
        self::assertStringContainsString('crudControllerFqcn=OrderCrud', $location, 'EA controller param must survive');
        self::assertStringContainsString('filters%5Btotal%5D%5Bcomparison%5D=%3E%3D', $location, 'gte → %3E%3D (>=)');
        self::assertStringContainsString('filters%5Btotal%5D%5Bvalue%5D=100', $location);
        self::assertStringNotContainsString('view=', $location);
    }

    #[Test]
    public function booleanFilterReplaysAsBareScalarNotEnvelope(): void
    {
        // The cross-cutting bug: BooleanFilterType extends ChoiceType (no
        // comparison/value envelope). Replaying it as `filters[isPaid][value]=1`
        // silently drops the filter. Replay it as `filters[isPaid]=1`.
        $view = $this->persistView(
            resourceName: self::ORDER_FQCN,
            name: 'Paid',
            filters: new FilterCollection(self::ORDER_FQCN, [
                new FilterCriterion('isPaid', 'eq', ['1']),
            ]),
        );

        $request = Request::create('/admin?crudControllerFqcn=OrderCrud&view=' . $view->id);
        $event = $this->makeEventForRequest($request, entityFqcn: self::ORDER_FQCN, filters: [
            BooleanFilter::new('isPaid'),
        ]);

        $this->subscriber->onBeforeCrudAction($event);

        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        $location = $response->headers->get('Location') ?? '';
        self::assertStringContainsString('filters%5BisPaid%5D=1', $location, 'BooleanFilter → bare scalar');
        self::assertStringNotContainsString('filters%5BisPaid%5D%5Bcomparison%5D', $location, 'BooleanFilter must NOT use envelope');
        self::assertStringNotContainsString('filters%5BisPaid%5D%5Bvalue%5D', $location);
    }

    private function persistView(
        string $resourceName,
        string $name,
        FilterCollection $filters,
    ): SavedView {
        $view = new SavedView(
            id: bin2hex(random_bytes(8)),
            name: $name,
            resourceName: $resourceName,
            ownerId: self::ALICE,
            scope: SavedViewScope::PRIVATE,
            filters: $filters,
        );

        return $this->service->save($view);
    }

    /**
     * @param list<\EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface|string> $filters
     */
    private function makeEventForRequest(
        Request $request,
        string $entityFqcn = self::ORDER_FQCN,
        array $filters = [],
    ): BeforeCrudActionEvent {
        // The subscriber pulls request from the stack (not the event).
        $this->requestStack->push($request);

        $crudDto = new CrudDto();
        $crudDto->setCurrentAction(Action::INDEX);
        /** @phpstan-ignore-next-line argument.type — test passes an arbitrary string FQCN */
        $crudDto->setEntityFqcn($entityFqcn);
        /** @phpstan-ignore-next-line argument.type — test passes an arbitrary controller string */
        $crudDto->setControllerFqcn('OrderCrud');

        $filterConfig = new FilterConfigDto();
        foreach ($filters as $filter) {
            $filterConfig->addFilter($filter);
        }
        $crudDto->setFiltersConfig($filterConfig);

        $crudContext = new CrudContext(
            crudDto: $crudDto,
            entityDto: null,
            searchDto: null,
            adminControllers: $this->createMock(AdminControllerRegistryInterface::class),
        );

        $context = (new ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();

        $crudContextProp = new ReflectionProperty(AdminContext::class, 'crudContext');
        $crudContextProp->setAccessible(true);
        $crudContextProp->setValue($context, $crudContext);

        return new BeforeCrudActionEvent($context);
    }
}
