# polysource/audit

> GDPR Art. 30 / HIPAA audit trail for Polysource admin actions.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## When to use

You're running a regulated workload (healthcare, finance, B2B SaaS) and need a write-only, queryable log of every admin action: who did what, when, from where, with what outcome.

## What it ships

- **`AuditEntry`** VO + **`AuditOutcome`** enum + **`AuditActorInterface`** (`SymfonySecurityAuditActor` default impl).
- **`AuditLoggerInterface`** (write-only) with fan-out via `AggregateAuditLogger`.
- **`DoctrineAuditLogger`** + Doctrine entity (`polysource_audit_log` table with 3 indexes for Art. 30 queries).
- **`ActionAuditSubscriber`** — bridges `ActionAboutToExecuteEvent` / `ActionExecutedEvent` (dispatched by `polysource/symfony-bundle`) to the logger. UUID v7 per entry, IP/UA/RequestID in context, trace truncated to 8KB.
- **`AuditLogResource`** — browsable admin resource with 5 standard filters (time range, actor, outcome, action name, resource).
- **`ExportAuditCsvAction`** — GDPR Art. 30 export with 12 locked columns (RFC 4180).
- **`polysource:audit:purge --before`** — retention command with cutoff exclusive, `--dry-run`, exit codes.

See [ADR-020](../../docs/adr/0020-audit-non-doctrine-actions.md).

## Install

```bash
composer require polysource/audit
```

Register the bundle:

```php
return [
    Polysource\Audit\PolysourceAuditBundle::class => ['all' => true],
];
```

Run the migration to create `polysource_audit_log`.

## Extend it

`AuditLoggerInterface` is **1 method** (`log(AuditEntry $entry)`). To pipe events to Splunk, Datadog, OpenSearch, or any SIEM:

```php
#[AutoconfigureTag('polysource.audit.logger')]
final class SplunkAuditLogger implements AuditLoggerInterface
{
    public function log(AuditEntry $entry): void
    {
        $this->splunk->send([
            'time' => $entry->occurredAt->format(\DateTimeInterface::ATOM),
            'event' => $entry->actionName,
            'actor' => $entry->actorId,
            'outcome' => $entry->outcome->value,
        ]);
    }
}
```

The `AggregateAuditLogger` fan-outs across every tagged logger with try/catch isolation — Splunk timing out doesn't break the Doctrine write. See [extensibility map](../../docs/user/extensibility.md#4-pipe-audit-log-to-your-siem).

## Documentation

- [Audit walkthrough](../../docs/user/audit/)
