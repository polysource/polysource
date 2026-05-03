<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\EventListener;

use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Auto-registers the bridge form theme into the active CRUD's
 * `formThemes` array.
 *
 * EasyAdmin renders the filter modal with
 * `{% form_theme filters_form with ea().crud.formThemes only %}` —
 * the `only` keyword means the global `twig.form_themes` config is
 * ignored for the filter form. So even though our DI extension's
 * `prepend()` adds the bridge theme to `twig.form_themes`, the
 * filter form never picks it up unless we also add it to the
 * CrudDto's `formThemes`.
 *
 * Why `kernel.controller` and not `BeforeCrudActionEvent`:
 * `AbstractCrudController::renderFilters()` (the AJAX endpoint that
 * builds the filter modal HTML) **does not dispatch**
 * `BeforeCrudActionEvent` — only `index()`, `detail()`, `edit()`,
 * `new()`, `delete()` do. A Symfony `kernel.controller` listener
 * fires for every controller invocation including `renderFilters`.
 * We grab the AdminContext from the provider service rather than
 * inspecting the controller signature so this stays decoupled from
 * EA's routing internals.
 *
 * `addFormTheme()` is append-only and Twig dedupes identical paths
 * silently, so multiple invocations per request are harmless.
 */
final class FilterFormThemeRegistrationSubscriber implements EventSubscriberInterface
{
    private const FORM_THEME_PATH = '@PolysourceEasyAdminFilterBridge/form/polysource_filter_theme.html.twig';

    public function __construct(private readonly AdminContextProviderInterface $adminContextProvider)
    {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onKernelController',
        ];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $context = $this->adminContextProvider->getContext();
        if (null === $context) {
            return;
        }

        $crud = $context->getCrud();
        if (null === $crud) {
            return;
        }

        $crud->addFormTheme(self::FORM_THEME_PATH);
    }
}
