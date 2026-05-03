# Cookbook — Adding a custom action

This recipe walks you through writing a custom action and wiring it
into the Messenger failed-messages dashboard. The example is a
`RetryWithDelayAction`: instead of dispatching the envelope
immediately, it re-dispatches it with a `DelayStamp` so it lands back
in the queue after a configurable delay.

The same pattern works for any action — replace the body of
`execute()` with whatever your action needs to do.

## What you'll have at the end

- A new **Retry in 5 min** button on each row of `/admin/failed-messages`.
- A new permission attribute, `POLYSOURCE_FAILED_MESSAGE_RETRY_DELAYED`,
  granted independently of plain Retry.
- The action visible in `bin/console debug:router` as
  `polysource_failed_messages_action` with `action: retry-with-delay`.

## Prerequisites

A working dashboard from
[messenger-failed-dashboard.md](./messenger-failed-dashboard.md). This
recipe builds on that setup.

## 1. Write the action class

`src/Polysource/Action/RetryWithDelayAction.php`:

```php
namespace App\Polysource\Action;

use Polysource\Core\Action\ActionResult;
use Polysource\Core\Action\InlineActionInterface;
use Polysource\Core\Query\DataRecord;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;
use Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface;
use Throwable;

final readonly class RetryWithDelayAction implements InlineActionInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private MessageBusInterface $bus,
        private ListableReceiverInterface $failedReceiver,
        private int $delayMs = 300_000,        // 5 minutes
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function getName(): string
    {
        return 'retry-with-delay';              // URL slug
    }

    public function getLabel(): string
    {
        return 'Retry in 5 min';
    }

    public function getIcon(): string
    {
        return 'clock-history';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_FAILED_MESSAGE_RETRY_DELAYED';
    }

    public function isDisplayed(array $context = []): bool
    {
        return true;
    }

    public function execute(DataRecord $record): ActionResult
    {
        $envelope = $record->rawSource;
        if (!$envelope instanceof Envelope) {
            return ActionResult::failure(\sprintf('Cannot retry record "%s": missing envelope.', $record->identifier));
        }

        try {
            $reborn = $envelope
                ->withoutAll(SentToFailureTransportStamp::class)
                ->withoutAll(ReceivedStamp::class)
                ->withoutAll(TransportMessageIdStamp::class)
                ->with(new DelayStamp($this->delayMs))
            ;
            $this->bus->dispatch($reborn);
            $this->failedReceiver->ack($envelope);

            return ActionResult::success(\sprintf('Message %s queued for retry in %d ms.', $record->identifier, $this->delayMs));
        } catch (Throwable $e) {
            $this->logger->error('Polysource: failed to retry envelope with delay', [
                'envelope_id' => $record->identifier,
                'exception_class' => $e::class,
                'exception_message' => $e->getMessage(),
            ]);

            return ActionResult::failure(\sprintf('Delayed retry of message %s failed.', $record->identifier));
        }
    }
}
```

## 2. Wire the service

`config/services.yaml` (in addition to what
[messenger-failed-dashboard.md](./messenger-failed-dashboard.md) set
up):

```yaml
services:
    App\Polysource\Action\RetryWithDelayAction:
        arguments:
            $bus: '@messenger.default_bus'
            $failedReceiver: '@messenger.transport.failed'
            $delayMs: 300000
            $logger: '@?logger'
```

`messenger.transport.failed` is the service id Symfony creates for the
transport named `failed` in your `framework.messenger.transports`
config. Adjust if your transport is named differently.

## 3. Add the action to the resource's action list

Edit the service swap from
[messenger-failed-dashboard.md → step 6](./messenger-failed-dashboard.md):

```yaml
    Polysource\Adapter\Messenger\Resource\FailedMessageResource:
        class: App\Polysource\AppFailedMessageResource
        arguments:
            $dataSource: '@Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource'
            $slug: '%polysource_messenger.resource_slug%'
            $actions:
                - '@Polysource\Adapter\Messenger\Action\RetryFailedMessageAction'
                - '@App\Polysource\Action\RetryWithDelayAction'   # ← new
                - '@Polysource\Adapter\Messenger\Action\DismissFailedMessageAction'
                - '@Polysource\Adapter\Messenger\Action\RetryAllFailedMessagesAction'
                - '@Polysource\Adapter\Messenger\Action\PurgeFailedMessagesAction'
```

The order in the list controls the button order in the UI.

## 4. Grant the new attribute

The `PolysourceAdminVoter` from
[messenger-failed-dashboard.md](./messenger-failed-dashboard.md)
already grants every `POLYSOURCE_*` to `ROLE_ADMIN`, so the new
attribute is implicitly covered. To restrict the action to a subset
of admins (e.g. only on-call), see
[permissions-with-roles.md](./permissions-with-roles.md).

## 5. Verify

```bash
bin/console cache:clear
```

Reload `/admin/failed-messages`. Each row now has a **Retry in 5 min**
button alongside Retry / Dismiss / Detail. Clicking it returns a flash
message *"Message {id} queued for retry in 300000 ms."*; checking
`messenger:stats` afterwards shows the message back on the `async`
transport with the appropriate delay.

## Bulk variant

If you want a **Retry-all-with-delay** bulk action, implement
`BulkActionInterface` instead:

```php
use Polysource\Core\Action\BulkActionInterface;

final readonly class RetryAllWithDelayAction implements BulkActionInterface
{
    /* same getName/getLabel/getIcon/getPermission/isDisplayed */

    public function executeBatch(iterable $records): ActionResult
    {
        $succeeded = 0;
        $failed = 0;

        foreach ($records as $record) {
            $result = $this->retryOne($record);
            $result->success ? $succeeded++ : $failed++;
        }

        return $failed === 0
            ? ActionResult::success(\sprintf('%d message(s) queued for retry.', $succeeded))
            : ActionResult::failure(\sprintf('%d succeeded, %d failed.', $succeeded, $failed));
    }
}
```

The framework caps `$records` at `polysource.max_bulk_ids` (default
500) before your code ever sees it.

## Hiding the button conditionally

To show the button only for envelopes whose `failed_at` is older than
1 minute (so an operator doesn't accidentally re-delay a fresh
failure):

```php
public function isDisplayed(array $context = []): bool
{
    $record = $context['record'] ?? null;
    if (!$record instanceof DataRecord) {
        return true;
    }

    $failedAt = $record->get('failed_at');
    if (!$failedAt instanceof \DateTimeImmutable) {
        return true;
    }

    return $failedAt < new \DateTimeImmutable('-1 minute');
}
```

`isDisplayed()` is **only** a UI hint, never a security gate — see
[../concepts/permission.md](../concepts/permission.md#permissions-and-isdisplayed).

## Testing your action

Actions are plain PHP. Unit-test them by hand-rolling a `DataRecord`
with the right `rawSource`:

```php
public function test_it_dispatches_with_delay_stamp(): void
{
    $bus = $this->createMock(MessageBusInterface::class);
    $bus->expects(self::once())
        ->method('dispatch')
        ->with(self::callback(function (Envelope $e): bool {
            return $e->last(DelayStamp::class)?->getDelay() === 300_000;
        }))
        ->willReturnCallback(static fn (Envelope $e) => $e);

    $receiver = $this->createMock(ListableReceiverInterface::class);
    $receiver->expects(self::once())->method('ack');

    $action = new RetryWithDelayAction($bus, $receiver, 300_000);

    $envelope = new Envelope(new \stdClass(), [new SentToFailureTransportStamp('failed')]);
    $record = new DataRecord(identifier: 'env-1', properties: [], rawSource: $envelope);

    $result = $action->execute($record);
    self::assertTrue($result->success);
}
```

## See also

- [../concepts/action.md](../concepts/action.md) — the inline / bulk /
  global contract, in detail.
- [../adapters/messenger.md](../adapters/messenger.md#retry-—-what-it-does-to-the-envelope)
  — what the upstream `RetryFailedMessageAction` does to envelopes.
- [permissions-with-roles.md](./permissions-with-roles.md) — granting
  the new `POLYSOURCE_FAILED_MESSAGE_RETRY_DELAYED` attribute to
  specific roles.
