<?php

declare(strict_types=1);

namespace Polysource\Audit\Tests\Unit\EventListener;

use LogicException;
use PHPUnit\Framework\TestCase;
use Polysource\Audit\EventListener\ActionAuditSubscriber;
use Polysource\Audit\Logger\AuditLoggerInterface;
use Polysource\Audit\Model\AuditActorInterface;
use Polysource\Audit\Model\AuditEntry;
use Polysource\Audit\Model\AuditOutcome;
use Polysource\Bundle\Event\ActionExecutedEvent;
use Polysource\Core\Action\ActionInterface;
use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;
use Polysource\Core\Resource\ResourceInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

/**
 * Pin the bridge contract:
 *  - subscribed event = ActionExecutedEvent
 *  - outcome mapping = success → Success, failure → Failure, exception → Exception
 *  - context.requestId falls back to a generated UUID when no
 *    X-Request-Id header is present
 *  - exception trace is truncated to MAX_TRACE_BYTES
 *  - actor id + label come from the AuditActorInterface
 */
final class ActionAuditSubscriberTest extends TestCase
{
    public function testSubscribesOnlyToActionExecutedEvent(): void
    {
        self::assertSame(
            [ActionExecutedEvent::class => 'onActionExecuted'],
            ActionAuditSubscriber::getSubscribedEvents(),
        );
    }

    public function testSuccessfulResultProducesSuccessOutcome(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', 'Alice Doe'));

        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::success('2 retried'),
        ));

        self::assertCount(1, $logger->entries);
        $entry = $logger->entries[0];
        self::assertSame(AuditOutcome::Success, $entry->outcome);
        self::assertSame('alice', $entry->actorId);
        self::assertSame('Alice Doe', $entry->actorLabel);
        self::assertSame('2 retried', $entry->message);
    }

    public function testGracefulFailureResultProducesFailureOutcome(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', null));

        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::failure('downstream API rejected the payload'),
        ));

        self::assertSame(AuditOutcome::Failure, $logger->entries[0]->outcome);
        self::assertSame('downstream API rejected the payload', $logger->entries[0]->message);
        self::assertNull($logger->entries[0]->actorLabel);
    }

    public function testExceptionPathStampsExceptionOutcomeAndTrace(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', null));

        $exception = new RuntimeException('payment gateway 502');
        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::failure('Action "retry" failed unexpectedly. Operators have been notified.'),
            exception: $exception,
        ));

        $entry = $logger->entries[0];
        self::assertSame(AuditOutcome::Exception, $entry->outcome);
        self::assertSame(RuntimeException::class, $entry->context['errorClass']);
        self::assertIsString($entry->context['errorTrace']);
        self::assertNotEmpty($entry->context['errorTrace']);
    }

    public function testExceptionTraceIsTruncatedToConfiguredCap(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', null));

        // PHP's `Exception::getTraceAsString()` is final — can't be
        // mocked. Build a real long trace via recursion. Each frame
        // is ~80 bytes; 200 frames easily clear the 8192 cap.
        try {
            $this->throwFromDeepStack(200);
            self::fail('throwFromDeepStack should have thrown');
        } catch (RuntimeException $exception) {
            // captured for use below
        }

        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::failure('boom'),
            exception: $exception,
        ));

        $trace = $logger->entries[0]->context['errorTrace'];
        self::assertIsString($trace);
        self::assertLessThanOrEqual(
            ActionAuditSubscriber::MAX_TRACE_BYTES + 30, // + truncation marker length
            \strlen($trace),
        );
        self::assertStringEndsWith('… [truncated]', $trace);
    }

    private function throwFromDeepStack(int $remaining): void
    {
        if (0 === $remaining) {
            throw new RuntimeException('boom from deep stack');
        }
        $this->throwFromDeepStack($remaining - 1);
    }

    public function testRequestIdHeaderIsPreservedWhenPresent(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', null));

        $request = new Request();
        $request->headers->set('X-Request-Id', 'req-abc-123');
        $request->headers->set('User-Agent', 'curl/8.0');

        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::success(),
            request: $request,
        ));

        self::assertSame('req-abc-123', $logger->entries[0]->context['requestId']);
        self::assertSame('curl/8.0', $logger->entries[0]->context['userAgent']);
    }

    public function testRequestIdIsGeneratedWhenHeaderMissing(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', null));

        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::success(),
            request: new Request(),
        ));

        $generated = $logger->entries[0]->context['requestId'];
        self::assertIsString($generated);
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $generated,
        );
    }

    public function testActionResultContextIsPropagatedUnderActionContextKey(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', null));

        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::success('done', ['retriedIds' => ['ord-1', 'ord-2']]),
        ));

        self::assertSame(
            ['retriedIds' => ['ord-1', 'ord-2']],
            $logger->entries[0]->context['actionContext'],
        );
    }

    public function testRecordIdsAreCarriedFromEvent(): void
    {
        $logger = new RecordingLogger();
        $subscriber = new ActionAuditSubscriber($logger, new FixedActor('alice', null));

        $subscriber->onActionExecuted($this->makeEvent(
            result: ActionResult::success(),
            recordIds: ['ord-1', 'ord-2', 'ord-3'],
        ));

        self::assertSame(['ord-1', 'ord-2', 'ord-3'], $logger->entries[0]->recordIds);
    }

    /**
     * @param list<string> $recordIds
     */
    private function makeEvent(
        ActionResult $result,
        ?Throwable $exception = null,
        ?Request $request = null,
        array $recordIds = ['ord-42'],
    ): ActionExecutedEvent {
        return new ActionExecutedEvent(
            action: new FixedAction('retry'),
            resource: new FixedResource('orders'),
            recordIds: $recordIds,
            request: $request ?? new Request(),
            result: $result,
            durationMs: 17,
            exception: $exception,
        );
    }
}

final class RecordingLogger implements AuditLoggerInterface
{
    /** @var list<AuditEntry> */
    public array $entries = [];

    public function log(AuditEntry $entry): void
    {
        $this->entries[] = $entry;
    }
}

final class FixedActor implements AuditActorInterface
{
    public function __construct(
        private readonly string $id,
        private readonly ?string $label,
    ) {
    }

    public function getActorId(): string
    {
        return $this->id;
    }

    public function getActorLabel(): ?string
    {
        return $this->label;
    }
}

final class FixedAction implements InlineActionInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return ucfirst($this->name);
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
        return ActionResult::success();
    }
}

final class FixedResource implements ResourceInterface
{
    public function __construct(private readonly string $name)
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->name;
    }

    public function getIdentifierProperty(): string
    {
        return 'id';
    }

    public function getDataSource(): \Polysource\Core\DataSource\DataSourceInterface
    {
        throw new LogicException('Not used in subscriber tests.');
    }

    /** @return iterable<ActionInterface> */
    public function configureActions(): iterable
    {
        return [];
    }

    /** @return iterable<\Polysource\Core\Field\FieldInterface> */
    public function configureFields(string $page): iterable
    {
        return [];
    }

    /** @return iterable<\Polysource\Core\Filter\FilterInterface> */
    public function configureFilters(): iterable
    {
        return [];
    }

    public function getPermission(): ?string
    {
        return null;
    }
}
