<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Adapter\Messenger\Resource\FailedMessageResource;
use Polysource\Adapter\Messenger\Tests\Functional\App\TestKernel;
use Polysource\Bundle\Registry\ResourceRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * End-to-end test for the Messenger adapter.
 */
final class MessengerAdapterSmokeTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    /**
     * @param array<mixed> $options
     */
    protected static function createKernel(array $options = []): KernelInterface
    {
        return parent::createKernel($options + ['debug' => false]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        restore_exception_handler();
    }

    #[Test]
    public function dataSourceIsRegisteredAndExposesSeededEnvelopes(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        self::assertTrue($container->has(MessengerFailedDataSource::class));
        $dataSource = $container->get(MessengerFailedDataSource::class);
        self::assertInstanceOf(MessengerFailedDataSource::class, $dataSource);

        $page = $dataSource->search(new \Polysource\Core\Query\DataQuery('failed-messages'));
        $items = $page->asArray();

        self::assertNull($page->total);
        self::assertCount(2, $items);
        self::assertSame('msg-1', $items[0]->identifier);
        self::assertSame('msg-2', $items[1]->identifier);
    }

    #[Test]
    public function resourceIsRegisteredUnderConfiguredSlug(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $registry = $container->get(ResourceRegistry::class);
        self::assertInstanceOf(ResourceRegistry::class, $registry);
        self::assertTrue($registry->has('failed-messages'));
        self::assertInstanceOf(FailedMessageResource::class, $registry->get('failed-messages'));
    }

    #[Test]
    public function failedMessagesIndexRouteResponds(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/failed-messages', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringContainsString('Failed messages', $body);
        self::assertStringContainsString('/admin/failed-messages/msg-1', $body);
        self::assertStringContainsString('/admin/failed-messages/msg-2', $body);
    }

    #[Test]
    public function failedMessageDetailRouteResolvesById(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/failed-messages/msg-1', 'GET'));

        self::assertSame(200, $response->getStatusCode());
        $body = (string) $response->getContent();
        self::assertStringContainsString('data-polysource-record="msg-1"', $body);
    }
}
