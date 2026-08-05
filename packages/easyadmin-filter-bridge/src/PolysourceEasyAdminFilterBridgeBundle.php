<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge;

use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Polysource\EasyAdminFilterBridge\DependencyInjection\PolysourceEasyAdminFilterBridgeExtension;
use Polysource\EasyAdminFilterBridge\Routing\BundleRouteLoader;
use Symfony\Component\DependencyInjection\Exception\ExceptionInterface as ContainerExceptionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\Routing\Router;

/**
 * Symfony bundle entry point for the EasyAdmin filter bridge.
 *
 * Auto-registered via Symfony Flex thanks to the `symfony-bundle` composer
 * type. Users may also register it manually in `config/bundles.php`.
 *
 * The bundle injects `FilterConfiguratorInterface` services that EasyAdmin's
 * `FilterFactory` consumes to mutate filter DTOs (formType, formTypeOptions)
 * after the built-in filter classes have produced them. No EasyAdmin code
 * is modified — see ADR-012.
 *
 * Implements {@see AdminPluginInterface} per ADR-018 — discoverable
 * via `polysource:plugins:list`.
 */
#[AsPlugin(name: 'polysource/easyadmin-filter-bridge')]
final class PolysourceEasyAdminFilterBridgeBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;

    public function getContainerExtension(): ExtensionInterface
    {
        if (!$this->extension instanceof ExtensionInterface) {
            $this->extension = new PolysourceEasyAdminFilterBridgeExtension();
        }

        return $this->extension;
    }

    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    /**
     * Auto-import the bridge's routes on every kernel boot. Hosts
     * no longer need to manually add:
     *
     *     polysource_easyadmin_filter_bridge:
     *         resource: '@PolysourceEasyAdminFilterBridgeBundle/Resources/config/routes.php'
     *         type: php
     *
     * to their `config/routes.yaml` — a manual-import gate that was
     * easy to miss, silently breaking every host-side helper that
     * generates URLs through the router (export, column-prefs,
     * saved-view modal, filter-url-token, matching-count,
     * column-order). Surfaced 2026-05-14 by dogfooding round 2.
     *
     * Symfony 7.4 does NOT provide `AbstractBundle::configureRoutes()`
     * (that's 7.5+). Bundle::boot() is the universally-available
     * hook — fires on every container build, gives us access to the
     * router service, and lets us splice our route collection into
     * the host's at runtime. Manual import remains supported (the
     * loader is idempotent — same route names produce a single
     * registration).
     *
     * @since 0.5.4
     */
    public function boot(): void
    {
        parent::boot();
        if (null === $this->container) {
            return;
        }

        // C1 guard — same logic as the DI extension: bridge is a
        // no-op on EA-less kernels in multi-kernel apps. Without this,
        // routes pointing at controllers that aren't registered as
        // services would still appear in the collection and 500 on
        // first hit. Surfaced 2026-05-14 dogfooding round 3.
        $bundles = $this->container->hasParameter('kernel.bundles')
            ? $this->container->getParameter('kernel.bundles')
            : [];
        if (!\is_array($bundles) || !\array_key_exists('EasyAdminBundle', $bundles)) {
            return;
        }

        // C2 guard — opt-out for multi-tenant hosts that mount EA
        // under a custom prefix (e.g. `/{channel}/admin`). The
        // bridge's controllers hard-code `#[Route('/admin/...')]`
        // — auto-importing them in a tenant-prefixed host would
        // splice routes OUTSIDE the tenant namespace, leaking
        // links out of the channel scope. Hosts opt out via
        // `polysource_easyadmin_filter_bridge.auto_register_routes: false`
        // in their config and import
        // `@PolysourceEasyAdminFilterBridge/config/routes.php`
        // manually under their own prefix. Surfaced 2026-05-14
        // dogfooding round 3.
        if (
            $this->container->hasParameter('polysource_easyadmin_filter_bridge.auto_register_routes')
            && false === $this->container->getParameter('polysource_easyadmin_filter_bridge.auto_register_routes')
        ) {
            return;
        }

        if (!$this->container->has('router')) {
            return;
        }
        // Resolving the router constructs its RequestContext which
        // reads `framework.router.default_uri` — typically resolved
        // from the host's `.env` (`DEFAULT_URI` env var). Scripts
        // that boot the kernel without loading dotenv (`php -r`,
        // some test harnesses, broken CLI tools) miss this env and
        // the resolution throws an EnvNotFoundException. The narrow
        // catch — DI container exceptions only — keeps unrelated
        // throwables (logic errors, broken bundles) flowing up the
        // stack instead of being silently swallowed. Surfaced
        // 2026-05-14 dogfooding round 2; narrowed in v0.9.0 per
        // the architectural audit.
        try {
            $router = $this->container->get('router');
        } catch (ContainerExceptionInterface) {
            return;
        }
        if (!$router instanceof Router) {
            return;
        }

        // Idempotency: if the host already imported our routes via
        // routes.yaml, the route names are already in the collection.
        // We probe one well-known name and skip the auto-load if so.
        $collection = $router->getRouteCollection();
        if (null !== $collection->get('polysource_export')) {
            return;
        }

        $loader = new BundleRouteLoader();
        $collection->addCollection($loader->loadAll());
    }
}
