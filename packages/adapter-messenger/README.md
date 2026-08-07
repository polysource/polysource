# polysource/adapter-messenger

Polysource adapter exposing Symfony Messenger's **failed transport** as a
browsable, actionable Polysource resource.

Symfony's Messenger has no built-in admin UI for inspecting failed
messages — only the `messenger:failed:*` CLI. `polysource/adapter-messenger`
plugs into the same `failed` transport and renders it inside
`/admin/failed-messages`.

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

## Actions

Browsing is only half of it — the four write actions ship today, each
behind its own permission:

| Action | Kind | Permission |
|---|---|---|
| `retry` | inline | `POLYSOURCE_FAILED_MESSAGE_RETRY` |
| `dismiss` | inline | `POLYSOURCE_FAILED_MESSAGE_DISMISS` |
| `retry-all` | bulk | `POLYSOURCE_FAILED_MESSAGE_RETRY` |
| `purge` | bulk | `POLYSOURCE_FAILED_MESSAGE_PURGE` |

## Status

**Shipped — v1.1.0 (2026-08-07).** Public API frozen under strict SemVer
since v1.0.0 (2026-08-06): breaking changes only in a new major.

## Architectural decisions

- [ADR-001](https://github.com/polysource/polysource/blob/main/docs/adr/0001-data-record-identifier.md) — identifier type
- [ADR-002](https://github.com/polysource/polysource/blob/main/docs/adr/0002-data-page-total-semantics.md) — count = null for cursor sources
- [ADR-006](https://github.com/polysource/polysource/blob/main/docs/adr/0006-envelope-mapper-serialization.md) — payload serialization

## License

MIT
