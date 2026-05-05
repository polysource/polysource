<?php

declare(strict_types=1);

namespace Polysource\Bundle\Tests\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Polysource\Bundle\Context\AdminContext;
use Polysource\Bundle\Controller\ActionController;
use Polysource\Bundle\Event\ActionAboutToExecuteEvent;
use Polysource\Bundle\Event\ActionExecutedEvent;
use Polysource\Bundle\Routing\PolysourceUrlGenerator;
use Polysource\Bundle\Tests\Fixture\FakeResource;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\BulkActionInterface;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\AbstractResource;
use RuntimeException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Throwable;

#[CoversClass(ActionController::class)]
final class ActionControllerTest extends TestCase
{
    #[Test]
    public function inlineActionRejectsMissingCsrfToken(): void
    {
        $controller = $this->buildController();
        $context = $this->buildContext(action: 'retry', recordId: '1');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('CSRF');
        ($controller)($context);
    }

    #[Test]
    public function inlineActionRejectsInvalidCsrfToken(): void
    {
        $controller = $this->buildController();
        $context = $this->buildContext(action: 'retry', recordId: '1', token: 'wrong');

        $this->expectException(AccessDeniedHttpException::class);
        ($controller)($context);
    }

    #[Test]
    public function inlineActionExecutesWhenCsrfTokenIsValid(): void
    {
        $action = new RecordingInlineAction();
        $resource = new ActionableResource('flags', [$action], records: [
            new DataRecord('1', ['name' => 'flag-a']),
        ]);

        [$controller, $token] = $this->buildControllerWithToken($resource);
        $context = $this->buildContext(
            resource: $resource,
            action: 'retry',
            recordId: '1',
            token: $token,
        );

        $response = ($controller)($context);
        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertCount(1, $action->executions);
    }

    #[Test]
    public function bulkActionEnforcesIdsCap(): void
    {
        [$controller, $token] = $this->buildControllerWithToken();
        $ids = array_map(static fn (int $i) => (string) $i, range(1, 1000));
        $context = $this->buildContext(
            action: 'retry-all',
            recordId: null,
            token: $token,
            postData: ['ids' => $ids],
            attributes: ['action' => 'retry-all'],
            maxBulkIds: 500,
        );

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('at most 500');
        $controller->bulk($context);
    }

    #[Test]
    public function bulkActionExecutesWithCappedAndDeduplicatedIds(): void
    {
        $action = new RecordingBulkAction();
        $resource = new ActionableResource('flags', [$action], records: [
            new DataRecord('1', []),
            new DataRecord('2', []),
        ]);

        [$controller, $token] = $this->buildControllerWithToken($resource, maxBulkIds: 5);
        $context = $this->buildContext(
            resource: $resource,
            action: 'retry-all',
            recordId: null,
            token: $token,
            postData: ['ids' => ['1', '1', '2', '2']],
            attributes: ['action' => 'retry-all'],
            maxBulkIds: 5,
        );

        $response = $controller->bulk($context);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertCount(1, $action->executions);
        self::assertCount(2, $action->executions[0]); // deduplicated
    }

    private function buildController(int $maxBulkIds = 500): ActionController
    {
        return new ActionController(
            $this->urlGenerator(),
            $this->csrfManager(),
            new \Polysource\Bundle\Controller\ControllerSupport(new \Polysource\Bundle\Tests\Fixture\AlwaysGrantPermission()),
            $maxBulkIds,
        );
    }

    /**
     * @return array{ActionController, string}
     */
    private function buildControllerWithToken(
        ?AbstractResource $resource = null,
        int $maxBulkIds = 500,
        ?EventDispatcherInterface $dispatcher = null,
    ): array {
        $manager = $this->csrfManager();
        $controller = new ActionController(
            $this->urlGenerator(),
            $manager,
            new \Polysource\Bundle\Controller\ControllerSupport(new \Polysource\Bundle\Tests\Fixture\AlwaysGrantPermission()),
            $maxBulkIds,
            null,
            $dispatcher,
        );
        $token = $manager->getToken(ActionController::CSRF_TOKEN_ID)->getValue();

        return [$controller, $token];
    }

    #[Test]
    public function inlineActionDispatchesAboutToExecuteAndExecutedEventsOnSuccess(): void
    {
        $action = new RecordingInlineAction();
        $resource = new ActionableResource('flags', [$action], records: [
            new DataRecord('1', ['name' => 'flag-a']),
        ]);
        $dispatcher = new RecordingDispatcher();

        [$controller, $token] = $this->buildControllerWithToken($resource, dispatcher: $dispatcher);
        $context = $this->buildContext(
            resource: $resource,
            action: 'retry',
            recordId: '1',
            token: $token,
        );

        ($controller)($context);

        self::assertCount(2, $dispatcher->events);
        $about = $dispatcher->events[0];
        $executed = $dispatcher->events[1];
        self::assertInstanceOf(ActionAboutToExecuteEvent::class, $about);
        self::assertInstanceOf(ActionExecutedEvent::class, $executed);

        self::assertSame('retry', $about->action->getName());
        self::assertSame('flags', $about->resource->getName());
        self::assertSame(['1'], $about->recordIds);

        self::assertSame(['1'], $executed->recordIds);
        self::assertTrue($executed->result->success);
        self::assertNull($executed->exception);
        self::assertGreaterThanOrEqual(0, $executed->durationMs);
    }

    #[Test]
    public function inlineActionEmitsExecutedEventWithExceptionWhenActionThrows(): void
    {
        $action = new ThrowingInlineAction(new RuntimeException('payment gateway 502'));
        $resource = new ActionableResource('orders', [$action], records: [
            new DataRecord('42', []),
        ]);
        $dispatcher = new RecordingDispatcher();

        [$controller, $token] = $this->buildControllerWithToken($resource, dispatcher: $dispatcher);
        $context = $this->buildContext(
            resource: $resource,
            action: 'retry',
            recordId: '42',
            token: $token,
        );

        ($controller)($context);

        self::assertCount(2, $dispatcher->events);
        $executed = $dispatcher->events[1];
        self::assertInstanceOf(ActionExecutedEvent::class, $executed);
        // safelyRun synthesises a graceful failure so the user-facing
        // flash stays clean. The original Throwable is preserved on
        // the event so the audit subscriber can stamp it.
        self::assertFalse($executed->result->success);
        self::assertInstanceOf(RuntimeException::class, $executed->exception);
        self::assertSame('payment gateway 502', $executed->exception->getMessage());
    }

    #[Test]
    public function bulkActionDispatchesEventsWithFullRecordIdList(): void
    {
        $action = new RecordingBulkAction();
        $resource = new ActionableResource('flags', [$action], records: [
            new DataRecord('1', []),
            new DataRecord('2', []),
        ]);
        $dispatcher = new RecordingDispatcher();

        [$controller, $token] = $this->buildControllerWithToken($resource, maxBulkIds: 5, dispatcher: $dispatcher);
        $context = $this->buildContext(
            resource: $resource,
            action: 'retry-all',
            recordId: null,
            token: $token,
            postData: ['ids' => ['1', '1', '2', '2']],
            attributes: ['action' => 'retry-all'],
            maxBulkIds: 5,
        );

        $controller->bulk($context);

        self::assertCount(2, $dispatcher->events);
        $about = $dispatcher->events[0];
        $executed = $dispatcher->events[1];
        self::assertInstanceOf(ActionAboutToExecuteEvent::class, $about);
        self::assertInstanceOf(ActionExecutedEvent::class, $executed);
        // Deduplicated by ActionController before reaching safelyRun.
        self::assertSame(['1', '2'], $about->recordIds);
        self::assertSame(['1', '2'], $executed->recordIds);
        self::assertTrue($executed->result->success);
    }

    private function urlGenerator(): PolysourceUrlGenerator
    {
        return new PolysourceUrlGenerator(new InMemoryUrlGenerator());
    }

    private function csrfManager(): CsrfTokenManagerInterface
    {
        return new CsrfTokenManager(
            new UriSafeTokenGenerator(),
            new InMemoryTokenStorage(),
        );
    }

    /**
     * @param array<string, mixed>  $postData
     * @param array<string, scalar> $attributes
     */
    private function buildContext(
        ?AbstractResource $resource = null,
        string $action = 'retry',
        ?string $recordId = null,
        ?string $token = null,
        array $postData = [],
        array $attributes = [],
        int $maxBulkIds = 500,
    ): AdminContext {
        $resource ??= new FakeResource('flags');
        if (null !== $token) {
            $postData['_token'] = $token;
        }

        $request = new Request(request: $postData);
        foreach ($attributes as $name => $value) {
            $request->attributes->set($name, $value);
        }

        return new AdminContext(
            request: $request,
            resource: $resource,
            action: $action,
            recordId: $recordId,
            locale: 'en',
            user: null,
            query: new DataQuery($resource->getName()),
        );
    }
}

/**
 * Resource fixture exposing pre-baked actions and records.
 */
final class ActionableResource extends AbstractResource
{
    /**
     * @param list<\Polysource\Core\Action\ActionInterface> $actions
     * @param list<DataRecord>                              $records
     */
    public function __construct(
        private readonly string $slug,
        private readonly array $actions,
        array $records = [],
    ) {
        parent::__construct(new \Polysource\Bundle\Tests\Fixture\FakeDataSource($records));
    }

    public function getName(): string
    {
        return $this->slug;
    }

    public function getLabel(): string
    {
        return $this->slug;
    }

    public function configureActions(): iterable
    {
        return $this->actions;
    }
}

final class RecordingInlineAction implements InlineActionInterface
{
    /** @var list<DataRecord> */
    public array $executions = [];

    public function getName(): string
    {
        return 'retry';
    }

    public function getLabel(): string
    {
        return 'Retry';
    }

    public function getIcon(): ?string
    {
        return null;
    }

    public function getPermission(): ?string
    {
        return null;
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function execute(DataRecord $record): ActionResult
    {
        $this->executions[] = $record;

        return ActionResult::success();
    }
}

final class RecordingBulkAction implements BulkActionInterface
{
    /** @var list<list<DataRecord>> */
    public array $executions = [];

    public function getName(): string
    {
        return 'retry-all';
    }

    public function getLabel(): string
    {
        return 'Retry all';
    }

    public function getIcon(): ?string
    {
        return null;
    }

    public function getPermission(): ?string
    {
        return null;
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function executeBatch(iterable $records): ActionResult
    {
        $batch = [];
        foreach ($records as $record) {
            $batch[] = $record;
        }
        $this->executions[] = $batch;

        return ActionResult::success();
    }
}

final class InMemoryUrlGenerator implements UrlGeneratorInterface
{
    private ?\Symfony\Component\Routing\RequestContext $context = null;

    /**
     * @param array<mixed> $parameters
     */
    public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
    {
        return '/' . $name;
    }

    public function setContext(\Symfony\Component\Routing\RequestContext $context): void
    {
        $this->context = $context;
    }

    public function getContext(): \Symfony\Component\Routing\RequestContext
    {
        return $this->context ??= new \Symfony\Component\Routing\RequestContext();
    }
}

final class ThrowingInlineAction implements InlineActionInterface
{
    public function __construct(private readonly Throwable $error)
    {
    }

    public function getName(): string
    {
        return 'retry';
    }

    public function getLabel(): string
    {
        return 'Retry';
    }

    public function getIcon(): ?string
    {
        return null;
    }

    public function getPermission(): ?string
    {
        return null;
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function execute(DataRecord $record): ActionResult
    {
        throw $this->error;
    }
}

/**
 * In-memory dispatcher — records every dispatched event so tests
 * can introspect the order, the count, and each event's payload.
 * Implements only the contract method we need (`dispatch`).
 */
final class RecordingDispatcher implements EventDispatcherInterface
{
    /** @var list<object> */
    public array $events = [];

    public function dispatch(object $event, ?string $eventName = null): object
    {
        $this->events[] = $event;

        return $event;
    }
}

final class InMemoryTokenStorage implements TokenStorageInterface
{
    /** @var array<string, string> */
    private array $tokens = [];

    public function getToken(string $tokenId): string
    {
        if (!isset($this->tokens[$tokenId])) {
            throw new \Symfony\Component\Security\Csrf\Exception\TokenNotFoundException();
        }

        return $this->tokens[$tokenId];
    }

    public function setToken(string $tokenId, string $token): void
    {
        $this->tokens[$tokenId] = $token;
    }

    public function removeToken(string $tokenId): ?string
    {
        $token = $this->tokens[$tokenId] ?? null;
        unset($this->tokens[$tokenId]);

        return $token;
    }

    public function hasToken(string $tokenId): bool
    {
        return isset($this->tokens[$tokenId]);
    }
}
