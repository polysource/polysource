# polysource/adapter-messenger

Polysource adapter exposing Symfony Messenger's **failed transport** as a
read-only Polysource resource.

This is the cas tueur for v0.1: Symfony's Messenger has no built-in admin
UI for inspecting failed messages — only the `messenger:failed:*` CLI.
`polysource/adapter-messenger` plugs into the same `failed` transport and
renders it inside `/admin/failed-messages`.

## Installation

```bash
composer require polysource/adapter-messenger
```

The bundle auto-registers via Symfony Flex.

## Configuration

```yaml
# config/packages/polysource_messenger.yaml
polysource_messenger:
    failed_transport_name: failed   # default
```

The named transport must implement
`Symfony\Component\Messenger\Transport\Receiver\ListableReceiverInterface`
(Doctrine, Redis, AMQP, InMemory all do). The bundle throws a
`LogicException` at boot if the transport is non-listable.

## What the resource exposes

| Property | Source |
|---|---|
| `message_class` | `Envelope::getMessage()::class` |
| `failed_at` | `RedeliveryStamp::getRedeliveredAt()` (UTC) |
| `exception_class` | `ErrorDetailsStamp::getExceptionClass()` |
| `exception_message` | `ErrorDetailsStamp::getExceptionMessage()` |
| `payload` | JSON-encoded message body, var_export fallback (ADR-006) |
| `payload_format` | `'json'` or `'var_export'` |

Records over 50 KB are truncated with a marker.

## Status

**v0.1 — read-only.** Retry / dismiss / retry-all / purge actions ship in
Phase 5 of the development plan.

## Architectural decisions

- [ADR-001](../../docs/adr/0001-data-record-identifier.md) — identifier type
- [ADR-002](../../docs/adr/0002-data-page-total-semantics.md) — count = null for cursor sources
- [ADR-006](../../docs/adr/0006-envelope-mapper-serialization.md) — payload serialization

## License

MIT
