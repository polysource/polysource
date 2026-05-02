<?php

declare(strict_types=1);

namespace Polysource\Examples\Preview;

use Polysource\Adapter\Messenger\PolysourceMessengerBundle;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\SpyMessageBus;
use Polysource\Bundle\PolysourceBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal Symfony kernel for the preview app.
 *
 * Boots the symfony-bundle and the messenger adapter with two seeded
 * resources: a Feature flags fixture and the failed-messages dashboard
 * (with retry / dismiss / retry-all / purge buttons).
 */
final class PreviewKernel extends Kernel
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
            new PolysourceBundle(),
            new PolysourceMessengerBundle(),
        ];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir() . '/polysource-preview/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-preview/' . $this->environment . '/logs';
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader): void
    {
        $container->loadFromExtension('framework', [
            'secret' => 'preview',
            'router' => ['utf8' => true],
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'session' => ['storage_factory_id' => 'session.storage.factory.mock_file'],
        ]);

        $container->loadFromExtension('twig', [
            'default_path' => __DIR__ . '/templates',
            'strict_variables' => false,
        ]);

        $container->loadFromExtension('polysource', []);
        $container->loadFromExtension('polysource_messenger', []);

        $container->register(FlagsResource::class, FlagsResource::class)
            ->setPublic(true)
            ->addTag('polysource.resource')
        ;

        // Failed-messages fixture: preload an in-memory listable receiver
        // with a couple of failed envelopes so the dashboard renders
        // immediately.
        $container->register('messenger.transport.failed', InMemoryListableReceiver::class)
            ->setPublic(true)
            ->setFactory([self::class, 'createFailedTransport'])
        ;

        // SpyMessageBus stands in for the real messenger bus — every
        // retry click pushes the envelope onto an in-memory list.
        $container->register(SpyMessageBus::class, SpyMessageBus::class)
            ->setPublic(true)
        ;
        $container->setAlias(MessageBusInterface::class, SpyMessageBus::class)
            ->setPublic(true)
        ;

        // No Symfony Security firewall in the preview app — alias the
        // permission interface to AlwaysGrantPermission. Production
        // apps wire this through Symfony's AuthorizationCheckerInterface.
        $container->register(\Polysource\Bundle\Tests\Fixture\AlwaysGrantPermission::class, \Polysource\Bundle\Tests\Fixture\AlwaysGrantPermission::class)
            ->setPublic(true)
        ;
        $container->setAlias(\Polysource\Core\Permission\PermissionInterface::class, \Polysource\Bundle\Tests\Fixture\AlwaysGrantPermission::class)
            ->setPublic(true)
        ;

        // CSRF tokens persist across requests via the session (default
        // Symfony storage). The PHP built-in server keeps the session
        // file under sys_get_temp_dir thanks to mock_file storage.
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'polysource');
    }

    /**
     * Delegates to {@see PreviewState} which holds the singleton on a
     * static class property — survives across `php -S` requests.
     */
    public static function createFailedTransport(): InMemoryListableReceiver
    {
        return PreviewState::failedTransport();
    }
}
