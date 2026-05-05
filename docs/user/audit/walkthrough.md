# Audit walkthrough — first hour

This is the "you've installed `polysource/audit`, now what" page.
We use the runnable `examples/messenger-demo/` for screenshots, but
the same flow applies to any host.

## Prerequisites

You've completed the [installation](./installation.md) steps —
bundle registered, schema created, voter granting
`POLYSOURCE_AUDIT_VIEW` to your operators.

## 1. Trigger an audited action

Go to your admin and trigger any action — for the demo:

```bash
cd examples/messenger-demo
make up
# Open http://localhost:8080/admin/failed-messages
# Login: admin / admin
# Click the "retry" button on any envelope.
```

Behind the scenes:
1. `ActionController::__invoke()` runs CSRF + permission checks.
2. The action's callable is wrapped in `safelyRun()`.
3. `ActionAboutToExecuteEvent` fires.
4. The callable runs.
5. `ActionExecutedEvent` fires (regardless of success / failure /
   exception).
6. `ActionAuditSubscriber` (from `polysource/audit`) consumes the
   event, builds an `AuditEntry`, and calls
   `AggregateAuditLogger::log()`.
7. `DoctrineAuditLogger` persists the row.

Total overhead: one INSERT per action. The synchronous flush is
deliberate — audit must be durable before the user-facing redirect.

## 2. Browse the audit log

Visit `/admin/audit-log`. You'll see the most recent entries first
(`ORDER BY occurred_at DESC`), with the 5 standard filters:

| Filter | Operator | Use case |
|---|---|---|
| Occurred at | between | "everything between 2026-01-01 and 2026-01-31" |
| Actor | eq | "everything Alice did" |
| Resource | in | "only failed-messages and audit-log" |
| Action | in | "only retry and dismiss" |
| Outcome | in | "only failures and exceptions" |

The 3 indexes on the table cover all combinations — the typical
GDPR query ("last 13 months for actor=alice on resource=orders")
is O(log n).

## 3. Filter and paginate

Filters compose as conjunctions (`AND`). Polysource's URL shape
matches the vanilla `polysource/filter` convention:

```
/admin/audit-log?filter[actorId][operator]=eq&filter[actorId][values][0]=alice
                &filter[outcome][operator]=in&filter[outcome][values][0]=failure
                &filter[outcome][values][1]=exception
```

You can also build URLs from PHP via
`FilterService::buildUrl(...)` — useful for "Recent failures" tile
links on a dashboard:

```php
$url = $this->filterService->buildUrl(
    path: $this->generateUrl('polysource_audit_log_index'),
    collection: new FilterCollection('audit-log', [
        new FilterCriterion('outcome', 'in', ['failure', 'exception']),
    ]),
);
```

## 4. Export the GDPR Art. 30 register

Compliance officers ask for a "register of processing activities"
quarterly. The `Export CSV` bulk action ships pre-wired:

1. On the audit log index page, optionally apply filters (e.g.
   `Occurred at between 2026-01-01 and 2026-12-31`).
2. Optionally select specific rows via checkboxes (or leave all
   unchecked to export the full filtered set).
3. Click `Export CSV`.

The action writes the file to
`%kernel.cache_dir%/polysource-audit-export/polysource-audit-{YYYYMMDD-HHMMSS}-{8 hex}.csv`
and stamps the path on the success flash. Hosts that want a
streamed download instead consume `AuditCsvExporter` directly —
see [extending.md](./extending.md).

The CSV column order is locked (12 columns, snake_case) so
compliance pipelines (OneTrust, Trustarc, Vanta) can ingest
unchanged across versions:

```
id,occurred_at,actor_id,actor_label,resource_name,action_name,
outcome,message,duration_ms,record_ids,context_ip,context_request_id
```

## 5. Schedule retention

Audit logs grow. The `polysource:audit:purge` command deletes
entries older than a configurable cutoff. HIPAA / GDPR Art. 30
baselines suggest 13 months retention:

```bash
# Dry-run first to verify the cutoff
bin/console polysource:audit:purge --before=2025-04-01 --dry-run

# Schedule weekly (every Sunday at 03:00 UTC)
0 3 * * 0 /app/bin/console polysource:audit:purge --before=$(date -d "13 months ago" +\%Y-\%m-\%d)
```

The cutoff is **mandatory** — running `polysource:audit:purge` with
no `--before` exits with code 1 and refuses to wipe anything. Same
spirit as `doctrine:database:drop --force`.

The cutoff is **exclusive** (`occurred_at < cutoff`). A row stamped
exactly at the cutoff survives. Useful when scripting around month
boundaries.

## 6. Inspect a specific row

Click any row in the audit log index to see all fields including
the full `context_json` payload — IP, User-Agent, X-Request-Id, the
upstream `actionContext`, and (when applicable) `errorClass` +
truncated `errorTrace` for exceptions.

For incident response: filter
`actor=$ATTACKER outcome in [failure, exception]` to see exactly
what they tried, when, and what failed.

## See also

- [extending.md](./extending.md) — fan out to Datadog, custom actor
  resolution, streamed download responses.
- [ADR-020](../../adr/0020-audit-non-doctrine-actions.md) — why
  things are wired the way they are.
