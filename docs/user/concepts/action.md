# Concept — Action

An **action** is a server-side operation a user can trigger from the
admin UI. Polysource has three flavours:

- **Inline** — operates on one record (the row's "Edit", "Delete",
  "Retry" button).
- **Bulk** — operates on N selected records at once ("Retry selected",
  "Delete selected").
- **Global** — operates on no specific record ("New product",
  "Export CSV", "Purge all").

All three share a tiny base contract.

## The base contract

```php
namespace Polysource\Core\Action;

interface ActionInterface
{
    public function getName(): string;          // slug used in URLs (kebab-case)
    public function getLabel(): string;         // display label
    public function getIcon(): ?string;         // optional icon name
    public function getPermission(): ?string;   // attribute checked via PermissionInterface
    public function isDisplayed(array $context = []): bool;
}
```

`getName()` is the URL slug — the action lives at
`POST /admin/{resourceSlug}/{id}/{actionName}` (inline) or
`POST /admin/{resourceSlug}/batch/{actionName}` (bulk). Slugs are
lowercase, kebab-case, e.g. `retry`, `dismiss`, `purge`,
`retry-with-delay`.

`isDisplayed($context)` lets an action hide itself based on the row's
state. The framework calls it once per row on the index page and once
per detail page. A `Retry` action that should hide on already-acked
records would inspect `$context['record']` and return `false`.

## Inline actions

```php
interface InlineActionInterface extends ActionInterface
{
    public function execute(DataRecord $record): ActionResult;
}
```

Inline actions render as buttons next to each row on the index page,
plus a button on the detail page. The framework wires
`POST /admin/{resourceSlug}/{id}/{actionName}` to call `execute()` with
the matching record. CSRF tokens are validated automatically.

The Messenger adapter ships two inline actions:

- `RetryFailedMessageAction` — strips
  `SentToFailureTransportStamp`, `ReceivedStamp` and
  `TransportMessageIdStamp` from the envelope, dispatches it back
  through the bus, then acks the failed envelope.
- `DismissFailedMessageAction` — acks the envelope without retrying.

## Bulk actions

```php
interface BulkActionInterface extends ActionInterface
{
    public function executeBatch(iterable $records): ActionResult;
}
```

Bulk actions render at the top of the index page (or as a dropdown,
depending on the theme). The user selects N rows via checkboxes and
clicks the action. The framework calls `executeBatch()` with the
matching `DataRecord` iterable.

The number of selected ids is capped at `polysource.max_bulk_ids`
(default 500). Beyond that cap the request is rejected before the
action ever runs — preventing a runaway click from saturating the
worker pool.

The Messenger adapter ships two bulk actions:

- `RetryAllFailedMessagesAction` — loops over the page (capped at
  `polysource_messenger.max_retry_all`, default 1000) and retries each
  envelope.
- `PurgeFailedMessagesAction` — same loop but acks-without-retry, also
  capped at `polysource_messenger.max_purge`.

## Global actions

A class implementing **only** `ActionInterface` (not the inline or
bulk sub-interfaces) is treated as a global action. These render on
the index page header (or a "More" menu) without being tied to a row.
Use them for "New …", "Export CSV", "Refresh cache", etc.

In v0.1 there is no built-in global action; declare your own and tag
it with the resource via `configureActions()`.

## The result

```php
final readonly class ActionResult
{
    public function __construct(
        public bool $success,
        public ?string $message = null,
        public array $context = [],
    ) {}

    public static function success(?string $message = null, array $context = []): self;
    public static function failure(string $message, array $context = []): self;
}
```

Use the static factories:

```php
return ActionResult::success(\sprintf('Message %s queued for retry.', $record->identifier));
return ActionResult::failure(\sprintf('Retry of message %s failed.', $record->identifier));
```

The framework converts the result into a Symfony flash message and
redirects back to the previous page. Populate `$context` with metadata
your audit log might want (user id, target id, before/after state).

## Wiring an action into a resource

Actions appear in the UI when `ResourceInterface::configureActions()`
yields them:

```php
public function configureActions(): iterable
{
    yield $this->retryAction;
    yield $this->dismissAction;
    yield $this->retryAllAction;
    yield $this->purgeAction;
}
```

The most common pattern is to inject the actions through the
constructor (the bundle's DI extension does this automatically for the
Messenger adapter):

```php
public function __construct(
    MessengerFailedDataSource $dataSource,
    private readonly RetryFailedMessageAction $retryAction,
    private readonly DismissFailedMessageAction $dismissAction,
    /* … */
) {
    parent::__construct($dataSource);
}
```

## Permissions and `isDisplayed()`

Two distinct gates protect every action:

1. **`getPermission()`** — checked by Polysource via
   `PermissionInterface` *before* `execute()` runs. Returns `null` if
   any authenticated user may invoke the action; otherwise returns the
   attribute string (e.g. `POLYSOURCE_FAILED_MESSAGE_RETRY`).
2. **`isDisplayed($context)`** — checked when rendering the UI. Used
   to hide a button based on row state (don't show "Retry" on a
   succeeded row), not for security. Security lives in
   `getPermission()`.

The view layer **also** filters action buttons by permission, so a
user without the attribute simply never sees the button. The server-side
check in step 1 is the authoritative gate (a hand-crafted POST request
without permission still gets 403).

## Hiding an action conditionally

```php
public function isDisplayed(array $context = []): bool
{
    $record = $context['record'] ?? null;
    if (!$record instanceof DataRecord) {
        return true;     // no row context → bulk/global, always show
    }

    return $record->get('status') !== 'succeeded';
}
```

The `$context` array is open-ended; the bundle currently passes
`'record'` and `'page'` keys. Treat unknown keys as "may appear in a
future version" rather than hard-failing on them.

## Testing an action

Actions are plain PHP classes — no controller, no HTTP layer. Unit
tests instantiate the class with the dependencies it needs and call
`execute()` directly:

```php
public function test_it_acknowledges_envelope_after_dispatch(): void
{
    $bus = $this->createMock(MessageBusInterface::class);
    $bus->expects(self::once())->method('dispatch')->willReturnCallback(
        static fn (Envelope $e) => new Envelope($e->getMessage()),
    );

    $receiver = $this->createMock(ListableReceiverInterface::class);
    $receiver->expects(self::once())->method('ack');

    $action = new RetryFailedMessageAction($bus, $receiver);
    $record = new DataRecord(
        identifier: 'envelope-1',
        properties: ['message_class' => 'Foo'],
        rawSource: new Envelope(new \stdClass()),
    );

    $result = $action->execute($record);
    self::assertTrue($result->success);
}
```

## See also

- [resource.md](./resource.md) — `configureActions()` is where actions
  bind to a resource.
- [permission.md](./permission.md) — how `getPermission()` is checked.
- [../cookbook/adding-a-custom-action.md](../cookbook/adding-a-custom-action.md)
  — write your own `RetryWithDelayAction`.
- [../adapters/messenger.md](../adapters/messenger.md) — the four
  shipped Messenger actions and their config caps.
