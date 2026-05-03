# Adapter — Symfony Messenger failed transport

`polysource/adapter-messenger` exposes Symfony Messenger's **failed**
transport as a Polysource resource. You get an admin dashboard listing
every envelope routed to the failure transport, with **Retry**,
**Dismiss**, **Retry-all** and **Purge** actions wired in.

This page is the reference for the adapter — what it ships, how to
configure it, what to be aware of. For the 5-minute install path see
[../getting-started.md](../getting-started.md). For a copy-paste
template including custom field types see
[../cookbook/messenger-failed-dashboard.md](../cookbook/messenger-failed-dashboard.md).

## What it ships

| Class | Purpose |
|---|---|
| `MessengerFailedDataSource` | Read-only `DataSourceInterface` over a `ListableReceiverInterface`. |
| `EnvelopeMapper` | Converts a `Symfony\Component\Messenger\Envelope` into a `DataRecord`. JSON-first payload serialisation with a `print_r` fallback. |
| `FailedMessageResource` | A `ResourceInterface` (subclassable) that auto-tags via `#[AsResource]`. |
| `RetryFailedMessageAction` | Inline action — re-dispatches an envelope through the bus. |
| `DismissFailedMessageAction` | Inline action — acks an envelope without retrying. |
| `RetryAllFailedMessagesAction` | Bulk action — retries up to `max_retry_all` envelopes. |
| `PurgeFailedMessagesAction` | Bulk action — acks up to `max_purge` envelopes. |

The package ships its own bundle (`PolysourceMessengerBundle`) and is a
`symfony-bundle` Composer type. Symfony Flex registers it
automatically; otherwise add it to `config/bundles.php` (see
[../installation.md](../installation.md)).

## Installation

```bash
composer require polysource/adapter-messenger
```

The base `polysource/symfony-bundle` is required and pulled in as a
dependency.

## Configuration

```yaml
# config/packages/polysource_messenger.yaml
polysource_messenger:
    failed_transport_name: failed         # default
    resource_slug: failed-messages        # default — URL slug under polysource.url_prefix
    payload_max_bytes: 50000              # default — payload truncation threshold
    max_retry_all: 1000                   # default — cap for the Retry-all bulk action
    max_purge: 1000                       # default — cap for the Purge bulk action
```

| Knob | Default | What it controls |
|---|---|---|
| `failed_transport_name` | `failed` | The Messenger transport name to expose. Must match a transport declared in `framework.messenger.transports`. |
| `resource_slug` | `failed-messages` | URL slug. Final path is `{polysource.url_prefix}/{resource_slug}`. |
| `payload_max_bytes` | `50000` | Maximum serialised payload size. Larger payloads are truncated with a marker — adjust if you handle big binary blobs. |
| `max_retry_all` | `1000` | Hard cap on how many envelopes a single Retry-all click processes. |
| `max_purge` | `1000` | Hard cap on Purge. |

The caps protect you from a runaway click on a hot transport; they are
**not** a rate limiter — see [concepts/permission.md → Rate limiting](../concepts/permission.md#what-about-rate-limiting).

## Supported transports {#supported-transports}

The adapter requires the underlying receiver to implement
`Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface`.
Construction fails fast (`LogicException`) on a non-listable receiver.

| Transport | Listable? | Works with the adapter? |
|---|---|---|
| Doctrine | ✅ | ✅ |
| Redis | ✅ | ✅ |
| AMQP | ✅ | ✅ |
| InMemory | ✅ | ✅ |
| Beanstalk | ❌ | ❌ |
| Amazon SQS | ❌ | ❌ |

For SQS / Beanstalk you'll need a different adapter — none ships with
v0.1. The same DataSource pattern applies; cf.
[../concepts/data-source.md](../concepts/data-source.md).

## What a failed envelope looks like as a `DataRecord`

`EnvelopeMapper` converts each envelope into a `DataRecord` whose
`properties` array carries:

| Property | Type | Source |
|---|---|---|
| `message_class` | `string` | `$envelope->getMessage()::class` |
| `exception_class` | `?string` | First `ErrorDetailsStamp::getExceptionClass()` |
| `exception_message` | `?string` | First `ErrorDetailsStamp::getExceptionMessage()` |
| `failed_at` | `?\DateTimeImmutable` | First `RedeliveryStamp::getRedeliveredAt()` (most recent) |
| `payload` | `string` | JSON-serialised message; falls back to `print_r` if not JSON-serialisable; truncated to `payload_max_bytes` |

The `DataRecord::$rawSource` carries the original `Envelope` so the
Retry / Dismiss actions can re-dispatch / ack it. Avoid relying on
`$rawSource` from your own resource — see
[../concepts/data-source.md](../concepts/data-source.md#datarecord) for
why.

## How the dashboard works

### Index page

`GET /admin/{resource_slug}` calls
`MessengerFailedDataSource::search()`, which iterates the receiver's
`all($limit)` and yields a `DataPage` with `total: null` (Messenger
transports don't expose a total count cheaply — see ADR-002). The UI
renders cursor-style pagination instead of a "Page X / Y" indicator.

### Detail page

`GET /admin/{resource_slug}/{id}` calls
`MessengerFailedDataSource::find($id)`, where `$id` is the Messenger
transport id. Returns `null` if the envelope is no longer in the
transport (e.g. another worker acked it concurrently); the controller
renders a 404 in that case.

### Retry — what it does to the envelope

`RetryFailedMessageAction::execute()` does, in order:

1. Strip `SentToFailureTransportStamp`, `ReceivedStamp` and
   `TransportMessageIdStamp` so the envelope routes normally instead
   of looping back to `failed`.
2. `bus->dispatch($reborn)` — re-emits the envelope.
3. `failedReceiver->ack($envelope)` — removes the original from the
   failed transport.

If the dispatch throws, the original envelope **stays** in `failed`
(no ack). The action returns `ActionResult::failure(...)` and a flash
message is shown to the operator. Logs go through PSR-3 if a logger is
wired.

### Dismiss

`DismissFailedMessageAction::execute()` simply calls
`failedReceiver->ack($envelope)`. The message is gone with no retry.

### Retry-all

`RetryAllFailedMessagesAction::executeBatch()` iterates the selected
records (capped at `max_retry_all`) and applies the same Retry logic
to each. Failures are accumulated; the final `ActionResult` reports
how many succeeded and how many failed. Other selected envelopes are
not affected by one envelope's failure (best-effort batch).

### Purge

`PurgeFailedMessagesAction::executeBatch()` acks each selected
envelope, capped at `max_purge`. Same best-effort semantics.

## Permission attributes

Out of the box the adapter advertises:

| Attribute | Where it's checked |
|---|---|
| `POLYSOURCE_FAILED_MESSAGE` | Resource-level (any access to the dashboard). |
| `POLYSOURCE_FAILED_MESSAGE_RETRY` | Per-row Retry button + Retry-all bulk action. |
| `POLYSOURCE_FAILED_MESSAGE_DISMISS` | Per-row Dismiss button. |
| `POLYSOURCE_FAILED_MESSAGE_PURGE` | Purge bulk action. |

None of these are real Symfony roles — they're voter-style attributes.
You need a voter that knows how to grant them. See
[../concepts/permission.md](../concepts/permission.md) for the
default Symfony binding and
[../cookbook/permissions-with-roles.md](../cookbook/permissions-with-roles.md)
for a granular role-to-attribute mapping example.

## Customising the displayed columns

`FailedMessageResource` is **intentionally not `final`**. v0.1 of
`polysource/core` ships only the abstract `FieldInterface` +
`FieldTrait`, so the upstream `configureFields()` returns an empty
list. Subclass to add your own field set:

```php
namespace App\Polysource;

use Polysource\Adapter\Messenger\Resource\FailedMessageResource;

final class MyFailedMessageResource extends FailedMessageResource
{
    public function configureFields(string $page): iterable
    {
        yield IdField::new('message_class', 'Message');
        yield TextField::new('exception_class', 'Exception')->onlyOnIndex();
        yield TextField::new('exception_message', 'Reason');
        yield DateTimeField::new('failed_at', 'Failed at');
        yield CodeField::new('payload', 'Payload')->onlyOnDetail();
    }
}
```

Then swap the upstream service:

```yaml
# config/services.yaml
services:
    Polysource\Adapter\Messenger\Resource\FailedMessageResource:
        class: App\Polysource\MyFailedMessageResource
        arguments:
            $dataSource: '@Polysource\Adapter\Messenger\DataSource\MessengerFailedDataSource'
            $slug: '%polysource_messenger.resource_slug%'
            $actions:
                - '@Polysource\Adapter\Messenger\Action\RetryFailedMessageAction'
                - '@Polysource\Adapter\Messenger\Action\DismissFailedMessageAction'
                - '@Polysource\Adapter\Messenger\Action\RetryAllFailedMessagesAction'
                - '@Polysource\Adapter\Messenger\Action\PurgeFailedMessagesAction'
```

> **Why arguments must be re-declared.** Re-defining a service id in a
> YAML config does **not** inherit the explicit `replaceArgument` calls
> that `PolysourceMessengerExtension::load()` made during compilation —
> Symfony merges the YAML on top of the prior definition and forces
> autowiring to recompute for the new class, which silently drops the
> slug + actions args the extension had injected. Always re-declare
> them.

The full template, end-to-end, is in
[../cookbook/messenger-failed-dashboard.md](../cookbook/messenger-failed-dashboard.md).

## Logging

`MessengerFailedDataSource` and the four actions all accept an
optional PSR-3 `LoggerInterface`. The bundle wires
`@?logger` automatically — the adapter falls back to `NullLogger`
otherwise.

What gets logged:

| Event | Level |
|---|---|
| Per-record envelope-mapping failure (rare; bad serialisation) | `warning` |
| Retry action failure | `error` |
| Retry-all / Purge per-envelope failures | `error` |

Mapping failures **never** crash the page — the malformed envelope is
skipped and the rest of the page still renders.

## Limitations and known caveats

- **Deep pagination is O(offset+limit).** Symfony's listable receivers
  expose only `all($limit)`, no `skip()`. The adapter emulates offset
  by fetching `offset+limit` envelopes and discarding the first
  `offset`. Acceptable for a few hundred failures, painful at 100k+.
  If you routinely have very deep failure backlogs, run a Purge to cap
  the queue.
- **No total count.** `count()` returns `null`; the UI uses cursor
  pagination. This is honest, not lazy — the underlying receiver
  doesn't expose a cheap total.
- **Concurrent worker activity may produce 404s.** If a parallel
  `messenger:failed:retry` consumes an envelope while the operator is
  reading the detail page, the next click may hit a vanished id. The
  controller renders 404; refresh the index.
- **Subclasses must keep the constructor signature.** The bundle's
  extension wires `($dataSource, $slug, $actions)` — a subclass that
  changes the signature breaks the DI. Add new dependencies via
  property setters instead, or override the service definition
  entirely.

## See also

- [../concepts/data-source.md](../concepts/data-source.md) — the
  contracts every adapter implements.
- [../concepts/action.md](../concepts/action.md) — what the four
  shipped actions actually do.
- [../concepts/permission.md](../concepts/permission.md) — how the
  `POLYSOURCE_*` attributes are answered.
- [../cookbook/messenger-failed-dashboard.md](../cookbook/messenger-failed-dashboard.md)
  — full configuration template.
- [../cookbook/adding-a-custom-action.md](../cookbook/adding-a-custom-action.md)
  — write your own `RetryWithDelayAction`.
