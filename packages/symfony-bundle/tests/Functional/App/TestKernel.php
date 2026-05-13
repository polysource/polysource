<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Functional\App;

use Polysource\Bundle\PolysourceBundle;
use Polysource\Bundle\Tests\Fixture\AlwaysGrantPermission;
use Polysource\Core\Permission\PermissionInterface;
use Polysource\Filter\PolysourceFilterBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal Symfony kernel for Polysource bundle functional tests.
 *
 * Not `final` so the {@see DeniedTestKernel} variant can override
 * permission wiring for negative-path tests.
 */
class TestKernel extends Kernel
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
            new PolysourceFilterBundle(),
            new PolysourceBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-bundle-tests/' . $this->environment . '/logs';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'test',
            'router' => ['utf8' => true],
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

        $container->loadFromExtension('polysource', []);

        // No Symfony Security firewall in this minimal kernel — alias
        // PermissionInterface to a permissive fixture so controllers
        // don't trip the "fail closed" check in
        // SymfonyAuthorizationCheckerPermission. The DeniedTestKernel
        // overrides this with AlwaysDenyPermission for negative tests.
        $container->register(AlwaysGrantPermission::class, AlwaysGrantPermission::class)
            ->setPublic(true)
        ;
        $container->setAlias(PermissionInterface::class, AlwaysGrantPermission::class)->setPublic(true);

        $container->register(TestResource::class, TestResource::class)
            ->setPublic(true)
            ->addTag('polysource.resource')
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'polysource');
    }
}
