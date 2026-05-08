# `polysource/audit` — audit log for non-Doctrine actions

`polysource/audit` is a Symfony bundle that captures every action
executed through `polysource/symfony-bundle` — across every adapter
(Messenger, Redis, S3, Meilisearch, custom HTTP, Doctrine) — into a
durable, queryable, exportable audit log.

Two consumers run side-by-side:
- **Compliance officers** get a GDPR Art. 30 / HIPAA / SOX register
  of "who did what, when, with what outcome" — extractable as CSV.
- **SRE / on-call** get a chronological view of every retry, dismiss,
  or purge invoked across the admin, with IP / User-Agent / request
  ID per row for incident triage.

Per [ADR-020](../../adr/0020-audit-non-doctrine-actions.md), audit
ships as a **separate package** rather than baked into
`polysource/symfony-bundle`, so apps that don't need it pay no
storage / DI cost. The bundle is opt-in.

## What's in this folder

| File | What's in it |
|---|---|
| [installation.md](./installation.md) | Install the bundle, register the schema, customise the actor / logger. |
| [walkthrough.md](./walkthrough.md) | End-to-end: trigger an action, browse the audit log, filter by actor / outcome / date, export CSV, schedule retention. |
| [extending.md](./extending.md) | Custom logger sinks (Datadog / Splunk), custom actor resolution (Auth0 / JWT / M2M), streamed download responses. |

## Status

Pre-v0.1.0. The public API is the one documented in
[ADR-020](../../adr/0020-audit-non-doctrine-actions.md):
- 12-field `AuditEntry` value object
- `AuditOutcome` enum (success / failure / exception)
- `AuditLoggerInterface` (single method, fan-out via `AggregateAuditLogger`)
- `AuditActorInterface` (default `SymfonySecurityAuditActor`)
- 2 events emitted by `ActionController::safelyRun()` — listeners can be added without touching `polysource/symfony-bundle`
- Browsable `AuditLogResource` at `/admin/audit-log`
- `polysource:audit:purge --before=YYYY-MM-DD` retention command

## Why this matters

Existing Symfony audit packages (`simplethings/entity-audit-bundle`,
Doctrine triggers, Sonata audit) only cover Doctrine ORM mutations.
Polysource's whole point is non-Doctrine resources — Messenger
failed messages, S3 objects, Meilisearch indexes, HTTP APIs. The
auditable surface for those is the **action level**, not the
mutation level. `polysource/audit` is the only Symfony solution
that treats actions as the primitive.

Concretely this unblocks regulated audiences (fintech, health, gov)
that can't ship an admin without an audit trail.

## See also

- [ADR-020 — Audit non-Doctrine actions](../../adr/0020-audit-non-doctrine-actions.md)
- [ADR-018 — AdminPluginInterface + public contracts](../../adr/0018-admin-plugin-interface-and-public-contracts.md)
- [`examples/messenger-demo/`](../../../examples/messenger-demo/) — runnable demo with the audit log wired up
