<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Unit\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\CrudDto;
use PHPUnit\Framework\TestCase;
use Polysource\EasyAdminFilterBridge\EventListener\FilterFormThemeRegistrationSubscriber;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Contract for the form-theme injector subscriber.
 *
 * `AdminContext` and `CrudDto` are final in EasyAdmin v5; we
 * instantiate them without their constructor and poke private state
 * through Reflection — same trick as the session persistence
 * subscriber test.
 */
final class FilterFormThemeRegistrationSubscriberTest extends TestCase
{
    private const FORM_THEME_PATH = '@PolysourceEasyAdminFilterBridge/form/polysource_filter_theme.html.twig';

    public function test_subscribes_to_kernel_controller(): void
    {
        $events = FilterFormThemeRegistrationSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::CONTROLLER, $events);
        self::assertSame('onKernelController', $events[KernelEvents::CONTROLLER]);
    }

    public function test_adds_form_theme_when_admin_context_is_present(): void
    {
        $crud = new CrudDto();
        $context = $this->makeAdminContextWithCrud($crud);

        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $subscriber = new FilterFormThemeRegistrationSubscriber($provider);
        $subscriber->onKernelController($this->makeControllerEvent());

        self::assertContains(
            self::FORM_THEME_PATH,
            $crud->getFormThemes(),
            'Bridge theme path must be appended to the CRUD form themes',
        );
    }

    public function test_no_op_when_no_admin_context(): void
    {
        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn(null);

        $subscriber = new FilterFormThemeRegistrationSubscriber($provider);
        $subscriber->onKernelController($this->makeControllerEvent());

        // No exception, no side effect — implicit by reaching this line.
        self::assertTrue(true);
    }

    public function test_no_op_when_admin_context_has_no_crud(): void
    {
        $crudContext = new CrudContext(
            crudDto: null,
            entityDto: null,
            searchDto: null,
            adminControllers: $this->createMock(AdminControllerRegistryInterface::class),
        );
        $context = $this->makeAdminContext($crudContext);

        $provider = $this->createMock(AdminContextProviderInterface::class);
        $provider->method('getContext')->willReturn($context);

        $subscriber = new FilterFormThemeRegistrationSubscriber($provider);
        $subscriber->onKernelController($this->makeControllerEvent());

        self::assertTrue(true);
    }

    private function makeAdminContextWithCrud(CrudDto $crud): AdminContext
    {
        $crudContext = CrudContext::forTesting(crudDto: $crud);

        return $this->makeAdminContext($crudContext);
    }

    private function makeAdminContext(CrudContext $crudContext): AdminContext
    {
        $context = (new \ReflectionClass(AdminContext::class))->newInstanceWithoutConstructor();

        $reflection = new \ReflectionProperty(AdminContext::class, 'crudContext');
        $reflection->setAccessible(true);
        $reflection->setValue($context, $crudContext);

        return $context;
    }

    private function makeControllerEvent(): ControllerEvent
    {
        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            static fn () => null,
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
