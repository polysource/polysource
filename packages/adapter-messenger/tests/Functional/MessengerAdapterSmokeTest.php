<?php

declare(strict_types=1);

namespace Polysource\Adapter\Messenger\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource;
use Polysource\Adapter\Messenger\Resource\FailedMessageResource;
use Polysource\Adapter\Messenger\Tests\Fixture\InMemoryListableReceiver;
use Polysource\Adapter\Messenger\Tests\Fixture\SpyMessageBus;
use Polysource\Adapter\Messenger\Tests\Functional\App\TestKernel;
use Polysource\Bundle\Controller\ActionController;
use Polysource\Bundle\Registry\ResourceRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

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
    public function indexRendersInlineAndBulkActionFormsWithCsrfTokens(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/failed-messages', 'GET'));
        $body = (string) $response->getContent();

        // Inline buttons (one form per action × per record)
        self::assertStringContainsString('data-polysource-action="inline-retry"', $body);
        self::assertStringContainsString('data-polysource-action="inline-dismiss"', $body);
        self::assertStringContainsString('action="/admin/failed-messages/msg-1/retry"', $body);
        self::assertStringContainsString('action="/admin/failed-messages/msg-1/dismiss"', $body);

        // Bulk toolbar
        self::assertStringContainsString('data-polysource-bulk-toolbar', $body);
        self::assertStringContainsString('data-polysource-action="bulk-retry-all"', $body);
        self::assertStringContainsString('data-polysource-action="bulk-purge"', $body);
        self::assertStringContainsString('action="/admin/failed-messages/batch/retry-all"', $body);
        self::assertStringContainsString('action="/admin/failed-messages/batch/purge"', $body);

        // CSRF token field present
        self::assertStringContainsString('name="_token"', $body);
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

    #[Test]
    public function retryActionDispatchesViaBusAndAcksOriginal(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);
        $container = self::getContainer();

        $tokenManager = $container->get(CsrfTokenManagerInterface::class);
        \assert($tokenManager instanceof CsrfTokenManagerInterface);
        $token = $tokenManager->getToken(ActionController::CSRF_TOKEN_ID)->getValue();

        $response = $kernel->handle(Request::create(
            '/admin/failed-messages/msg-1/retry',
            'POST',
            ['_token' => $token],
        ));

        self::assertSame(302, $response->getStatusCode());
        self::assertStringContainsString('/admin/failed-messages', (string) $response->headers->get('Location'));

        $bus = $container->get(SpyMessageBus::class);
        \assert($bus instanceof SpyMessageBus);
        self::assertCount(1, $bus->dispatched);

        $receiver = $container->get('messenger.transport.failed');
        \assert($receiver instanceof InMemoryListableReceiver);
        self::assertNull($receiver->find('msg-1'));
        self::assertNotNull($receiver->find('msg-2'));
    }

    #[Test]
    public function retryActionRejectsRequestsWithoutCsrfToken(): void
    {
        self::bootKernel();
        $kernel = self::$kernel;
        \assert($kernel instanceof HttpKernelInterface);

        $response = $kernel->handle(Request::create('/admin/failed-messages/msg-1/retry', 'POST'));

        self::assertSame(403, $response->getStatusCode());
    }
}
