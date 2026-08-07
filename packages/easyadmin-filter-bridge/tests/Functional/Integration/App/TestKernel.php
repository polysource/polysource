<?php

declare(strict_types=1);

namespace Polysource\EasyAdminFilterBridge\Tests\Functional\Integration\App;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Polysource\EasyAdminFilterBridge\PolysourceEasyAdminFilterBridgeBundle;
use Polysource\Filter\PolysourceFilterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal Symfony kernel for the bridge integration tests.
 *
 * Wires the smallest possible stack to exercise the bridge's
 * controllers end-to-end against a real EntityManager + a real
 * router + the bridge's bundle config.
 *
 * Bundles:
 *  - FrameworkBundle + TwigBundle + SecurityBundle — Symfony base
 *  - DoctrineBundle — ORM with SQLite in-memory
 *  - EasyAdminBundle — required by the bridge's DI extension's
 *    C1 guard; the bridge no-ops without it
 *  - PolysourceFilterBundle — the saved-views/column-prefs primitives
 *  - PolysourceEasyAdminFilterBridgeBundle — the unit under test
 *
 * We deliberately do NOT set up an EA Dashboard or CrudController
 * fixture — the 3 bridge endpoints under test (matching-count,
 * export, column-preferences) are standalone HTTP entry points
 * that take the entity FQCN as a URL parameter and operate on
 * Doctrine directly. The host's Dashboard isn't load-bearing.
 *
 * Locks in regression coverage for:
 *  - C5 (entity metadata cache cold path 404)
 *  - C6 (fputcsv PHP 8.4 deprecation)
 *  - C7 (toIterable + HYDRATE_ARRAY on entity yield 0 in ORM 2.x)
 *  - C8 (DateTime export as ISO 8601)
 *  - C10 (IN / NOT IN / IS [NOT] NULL silently dropped)
 *
 * @since 0.6.0
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @return iterable<\Symfony\Component\HttpKernel\Bundle\BundleInterface>
     */
    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new SecurityBundle(),
            new DoctrineBundle(),
            new EasyAdminBundle(),
            new PolysourceFilterBundle(),
            new PolysourceEasyAdminFilterBridgeBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bridge-integration/' . $this->environment . '/' . spl_object_id($this) . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bridge-integration/' . $this->environment . '/' . spl_object_id($this) . '/logs';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test',
            'router' => ['utf8' => true, 'resource' => 'kernel::loadRoutes', 'type' => 'service'],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'test' => true,
        ]);

        $container->loadFromExtension('twig', [
            'default_path' => __DIR__ . '/templates',
            'strict_variables' => false,
        ]);

        // Minimal security — anonymous firewall, no auth, all paths public.
        // Tests don't exercise auth paths; the bridge endpoints just need
        // a request scope to fire.
        $container->loadFromExtension('security', [
            'providers' => [
                'in_memory' => ['memory' => null],
            ],
            'firewalls' => [
                'main' => [
                    'pattern' => '^/',
                    'security' => false,
                ],
            ],
        ]);

        // Doctrine — SQLite in-memory, single connection, polysource
        // entities + the test fixture entity mapped via attribute.
        $container->loadFromExtension('doctrine', [
            'dbal' => [
                'driver' => 'pdo_sqlite',
                'memory' => true,
                'charset' => 'UTF8',
            ],
            'orm' => [
                'validate_xml_mapping' => true,
                'naming_strategy' => 'doctrine.orm.naming_strategy.underscore_number_aware',
                'auto_mapping' => false,
                'mappings' => [
                    'TestApp' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => __DIR__ . '/Entity',
                        'prefix' => 'Polysource\\EasyAdminFilterBridge\\Tests\\Functional\\Integration\\App\\Entity',
                        'alias' => 'TestApp',
                    ],
                    'PolysourceFilter' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        // In monorepo dev mode, polysource/filter sits at
                        // packages/filter/src — relative to this kernel at
                        // packages/easyadmin-filter-bridge/tests/Functional/Integration/App,
                        // that's 5 dirs up + 'filter/src'.
                        'dir' => \dirname(__DIR__, 5) . '/filter/src',
                        'prefix' => 'Polysource\\Filter',
                        'alias' => 'PolysourceFilter',
                    ],
                ],
            ],
        ]);

        $container->loadFromExtension('polysource_easyadmin_filter_bridge', []);

        // Row-detail fixtures (v1.1.0): a provider for TestItem plus a
        // subject-only voter denying archived items, so the endpoint
        // tests exercise the entity-as-voter-subject gate end-to-end.
        $container->register(RowDetail\TestItemRowDetailProvider::class)
            ->addTag('polysource.row_detail_provider')
        ;
        $container->register(RowDetail\TestItemRowDetailVoter::class)
            ->addTag('security.voter')
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        // The bridge auto-imports its 8 polysource_* routes via
        // Bundle::boot() (v0.5.4). EA's stock Dashboard routes aren't
        // required for the 3 standalone bridge endpoints under test.
    }
}
