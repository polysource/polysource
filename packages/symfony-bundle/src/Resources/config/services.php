<?php

declare(strict_types=1);

use Polysource\Bundle\ArgumentResolver\AdminContextResolver;
use Polysource\Bundle\Command\PluginListCommand;
use Polysource\Bundle\Context\AdminContextProvider;
use Polysource\Bundle\Controller\ActionController;
use Polysource\Bundle\Controller\ControllerSupport;
use Polysource\Bundle\Controller\DetailController;
use Polysource\Bundle\Controller\IndexController;
use Polysource\Bundle\EventListener\PolysourceViewListener;
use Polysource\Bundle\Plugin\PluginRegistry;
use Polysource\Bundle\Registry\ResourceRegistry;
use Polysource\Bundle\Routing\PolysourceRouteLoader;
use Polysource\Bundle\Routing\PolysourceUrlGenerator;
use Polysource\Bundle\Security\SymfonyAuthorizationCheckerPermission;
use Polysource\Bundle\Twig\PolysourceFilterExtension;
use Polysource\Core\Permission\PermissionInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

/*
 * Polysource bundle services.
 *
 * Single registration point for the bundle. Users do NOT need to override
 * anything here — Resources are discovered via the `polysource.resource` tag
 * (auto-applied by `#[AsResource]` or set manually in `services.yaml`).
 */
return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire(false)
            ->autoconfigure(false)
            ->private();

    $services
        ->set(ResourceRegistry::class)
        ->args([tagged_iterator('polysource.resource')])
        ->public()
    ;

    $services
        ->set(AdminContextProvider::class)
        ->tag('kernel.reset', ['method' => 'reset'])
        ->public()
    ;

    $services
        ->set(AdminContextResolver::class)
        ->args([
            service(ResourceRegistry::class),
            service(AdminContextProvider::class),
            service(Security::class)->nullOnInvalid(),
            '%polysource.max_page_size%',
        ])
        ->tag('controller.argument_value_resolver', ['priority' => 110])
    ;

    $services
        ->set(PolysourceRouteLoader::class)
        ->args([
            service(ResourceRegistry::class),
            '%polysource.url_prefix%',
        ])
        ->tag('routing.loader')
    ;

    $services
        ->set(PolysourceUrlGenerator::class)
        ->args([service(UrlGeneratorInterface::class)])
        ->public()
    ;

    $services
        ->set(SymfonyAuthorizationCheckerPermission::class)
        ->args([service(AuthorizationCheckerInterface::class)->nullOnInvalid()])
        ->public()
    ;
    $services->alias(PermissionInterface::class, SymfonyAuthorizationCheckerPermission::class)->public();

    $services
        ->set(ControllerSupport::class)
        ->args([service(PermissionInterface::class)])
    ;

    $services
        ->set(IndexController::class)
        ->args([service(ControllerSupport::class)])
        ->public()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(DetailController::class)
        ->args([service(ControllerSupport::class)])
        ->public()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(ActionController::class)
        ->args([
            service(PolysourceUrlGenerator::class),
            service(CsrfTokenManagerInterface::class),
            service(ControllerSupport::class),
            '%polysource.max_bulk_ids%',
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->public()
        ->tag('controller.service_arguments')
    ;

    $services
        ->set(PolysourceFilterExtension::class)
        ->args([service(UrlGeneratorInterface::class)])
        ->tag('twig.extension')
    ;

    $services
        ->set(PolysourceViewListener::class)
        ->args([
            service(Environment::class)->nullOnInvalid(),
            service(LoggerInterface::class)->nullOnInvalid(),
        ])
        ->tag('kernel.event_subscriber')
    ;

    // ─── Plugin discovery (ADR-018) ────────────────────────────────
    // PluginRegistry is patched at compile time by PluginCompilerPass
    // which collects every service tagged `polysource.plugin` (and
    // auto-tags any service whose class implements AdminPluginInterface).
    // Public so a command / debug tooling can pull it from the
    // container.
    $services
        ->set(PluginRegistry::class)
        ->args([tagged_iterator('polysource.plugin')])
        ->public()
    ;

    $services
        ->set(PluginListCommand::class)
        ->args([service(PluginRegistry::class)])
        ->tag('console.command')
    ;
};
