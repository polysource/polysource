<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\EventListener\FilterSessionPersistenceSubscriber;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Verifies the save / restore behaviour of the session persistence
 * subscriber.
 *
 * `AdminContext` and `CrudDto` are final in EasyAdmin v5; we use
 * `ReflectionClass::newInstanceWithoutConstructor()` to satisfy the
 * typehints, then poke private fields via Reflection to inject the
 * test data the subscriber reads (currentAction, controllerFqcn,
 * request).
 */
final class FilterSessionPersistenceSubscriberTest extends TestCase
{
    private const SESSION_KEY_PREFIX = 'polysource.filters.';

    public function test_subscribes_to_before_crud_action_event(): void
    {
        $events = FilterSessionPersistenceSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(BeforeCrudActionEvent::class, $events);
        self::assertSame('onBeforeCrudAction', $events[BeforeCrudActionEvent::class]);
    }

    public function test_save_filters_to_session_when_query_carries_them(): void
    {
        $request = Request::create('/admin?crudAction=index&filters%5BcreatedAt%5D%5Bvalue%5D=2026-05-01');
        $session = $this->createMock(SessionInterface::class);

        $expectedKey = self::SESSION_KEY_PREFIX . hash('xxh128', 'App\\Controller\\Admin\\ProductCrudController');
        $session->expects(self::once())
            ->method('set')
            ->with($expectedKey, self::callback(function (array $value): bool {
                return isset($value['createdAt']['value']) && '2026-05-01' === $value['createdAt']['value'];
            }))
        ;
        $session->expects(self::never())->method('get');

        $subscriber = new FilterSessionPersistenceSubscriber($this->makeRequestStackWithSession($request, $session));
        $event = new BeforeCrudActionEvent($this->makeContext($request, Action::INDEX, 'App\\Controller\\Admin\\ProductCrudController'));

        $subscriber->onBeforeCrudAction($event);

        self::assertFalse($event->isPropagationStopped(), 'No redirect on the save path — filters were already in the URL');
    }

    public function test_restore_filters_via_redirect_when_query_has_none_and_session_does(): void
    {
        $request = Request::create('/admin?crudAction=index');
        $session = $this->createMock(SessionInterface::class);

        $expectedKey = self::SESSION_KEY_PREFIX . hash('xxh128', 'App\\Controller\\Admin\\ProductCrudController');
        $session->method('get')
            ->with($expectedKey)
            ->willReturn(['createdAt' => ['value' => '2026-04-01', 'comparison' => '>=']])
        ;
        $session->expects(self::never())->method('set');

        $subscriber = new FilterSessionPersistenceSubscriber($this->makeRequestStackWithSession($request, $session));
        $event = new BeforeCrudActionEvent($this->makeContext($request, Action::INDEX, 'App\\Controller\\Admin\\ProductCrudController'));

        $subscriber->onBeforeCrudAction($event);

        self::assertTrue($event->isPropagationStopped());
        $response = $event->getResponse();
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertStringContainsString('filters', $response->getTargetUrl());
        self::assertStringContainsString('2026-04-01', $response->getTargetUrl());
    }

    public function test_does_nothing_when_no_filters_in_query_and_no_session_state(): void
    {
        $request = Request::create('/admin?crudAction=index');
        $session = $this->createMock(SessionInterface::class);
        $session->method('get')->willReturn(null);
        $session->expects(self::never())->method('set');

        $subscriber = new FilterSessionPersistenceSubscriber($this->makeRequestStackWithSession($request, $session));
        $event = new BeforeCrudActionEvent($this->makeContext($request, Action::INDEX, 'App\\Controller\\Admin\\ProductCrudController'));

        $subscriber->onBeforeCrudAction($event);

        self::assertFalse($event->isPropagationStopped());
    }

    public function test_no_op_for_non_index_actions(): void
    {
        $request = Request::create('/admin?crudAction=edit&filters%5Bfoo%5D=bar');
        $session = $this->createMock(SessionInterface::class);
        $session->expects(self::never())->method('set');
        $session->expects(self::never())->method('get');

        $subscriber = new FilterSessionPersistenceSubscriber($this->makeRequestStackWithSession($request, $session));
        $event = new BeforeCrudActionEvent($this->makeContext($request, Action::EDIT, 'App\\Controller\\Admin\\ProductCrudController'));

        $subscriber->onBeforeCrudAction($event);

        self::assertFalse($event->isPropagationStopped());
    }

    public function test_scopes_session_key_per_controller_fqcn(): void
    {
        $key1 = hash('xxh128', 'App\\Controller\\Admin\\ProductCrudController');
        $key2 = hash('xxh128', 'App\\Controller\\Admin\\OrderCrudController');

        self::assertNotSame(
            $key1,
            $key2,
            'Different CRUD controllers must produce different session keys — otherwise filters leak across resources',
        );
    }

    private function makeRequestStackWithSession(Request $request, SessionInterface $session): RequestStack
    {
        $stack = new RequestStack();
        $request->setSession($session);
        $stack->push($request);

        return $stack;
    }

    private function makeContext(Request $request, string $action, string $controllerFqcn): AdminContext
    {
        $crud = (new \ReflectionClass(CrudDto::class))->newInstanceWithoutConstructor();

        $actionProp = new \ReflectionProperty(CrudDto::class, 'actionName');
        $actionProp->setValue($crud, $action);

        $fqcnProp = new \ReflectionProperty(CrudDto::class, 'controllerFqcn');
        $fqcnProp->setValue($crud, $controllerFqcn);

        $context = (new \ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();

        $crudProp = new \ReflectionProperty(AdminContext::class, 'crudContext');
        $crudContext = (new \ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext::class))->newInstanceWithoutConstructor();
        $crudDtoProp = new \ReflectionProperty(\EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext::class, 'crudDto');
        $crudDtoProp->setValue($crudContext, $crud);
        $crudProp->setValue($context, $crudContext);

        $requestContextProp = new \ReflectionProperty(AdminContext::class, 'requestContext');
        $requestContext = (new \ReflectionClass(\EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext::class))->newInstanceWithoutConstructor();
        $requestProp = new \ReflectionProperty(\EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext::class, 'request');
        $requestProp->setValue($requestContext, $request);
        $requestContextProp->setValue($context, $requestContext);

        return $context;
    }
}
