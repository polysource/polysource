<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Functional\App;

use DateTimeImmutable;
use LogicException;
use Polysource\Adapter\Messenger\PolysourceMessengerBundle;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\PlainMessage;
use Polysource\Bundle\PolysourceBundle;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\ErrorDetailsStamp;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

/**
 * Minimal kernel for adapter-messenger functional tests.
 *
 * Bypasses Symfony Messenger's framework config — we register
 * `messenger.transport.failed` directly as our fixture
 * {@see InMemoryListableReceiver}, seeded with two failed envelopes.
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
        return sys_get_temp_dir() . '/polysource-adapter-messenger-tests/' . $this->environment . '/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir() . '/polysource-adapter-messenger-tests/' . $this->environment . '/logs';
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
        $container->loadFromExtension('polysource_messenger', []);

        // Register our fixture receiver as the failed transport. The
        // PolysourceMessengerExtension resolves the transport via a
        // service reference, so any service registered under this id
        // works.
        $container->register('messenger.transport.failed', InMemoryListableReceiver::class)
            ->setPublic(true)
            ->setFactory([self::class, 'createFailedTransport'])
        ;
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'polysource');
    }

    public static function createFailedTransport(): InMemoryListableReceiver
    {
        return new InMemoryListableReceiver([
            (new Envelope(new PlainMessage('payment', 1)))
                ->with(new TransportMessageIdStamp('msg-1'))
                ->with(new ErrorDetailsStamp(RuntimeException::class, 0, 'Database is down'))
                ->with(new RedeliveryStamp(retryCount: 0, redeliveredAt: new DateTimeImmutable('2026-04-30T08:00:00+00:00'))),
            (new Envelope(new PlainMessage('email', 2)))
                ->with(new TransportMessageIdStamp('msg-2'))
                ->with(new ErrorDetailsStamp(LogicException::class, 0, 'SMTP timeout'))
                ->with(new RedeliveryStamp(retryCount: 1, redeliveredAt: new DateTimeImmutable('2026-04-30T09:30:00+00:00'))),
        ]);
    }
}
