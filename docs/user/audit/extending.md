# Extending `polysource/audit`

The defaults cover the 80% case (Doctrine + Symfony Security +
synchronous logging). This page is the seam catalogue for the other
20% — custom logger sinks, non-Symfony auth models, hosts without
Doctrine, streamed downloads.

## Custom logger sinks (fan-out)

`AggregateAuditLogger` iterates every service tagged
`polysource.audit_logger`. Add as many sinks as you want:

```yaml
# config/services.yaml
services:
    App\Audit\DatadogAuditLogger:
        arguments:
            $apiKey: '%env(DATADOG_API_KEY)%'
        tags: ['polysource.audit_logger']
```

```php
final class DatadogAuditLogger implements AuditLoggerInterface
{
    public function __construct(private readonly string $apiKey)
    {
    }

    public function log(AuditEntry $entry): void
    {
        // Forward to Datadog Logs HTTP API.
    }
}
```

The aggregator wraps each call in try/catch — a Datadog 5xx won't
break the user-facing action. Failures bubble to PSR-3 (Monolog by
default), so operators can spot a broken sink in
`var/log/dev.log`.

For the canonical sinks:
- `Polysource\Audit\Logger\DoctrineAuditLogger` — default, ships in
  the bundle.
- `Polysource\Audit\Logger\NullAuditLogger` — no-op, default
  for hosts without Doctrine.

## Custom actor resolution

Default is `SymfonySecurityAuditActor` (pulls from
`Security::getUser()`). Hosts with non-Symfony auth implement
`AuditActorInterface`:

```php
final class Auth0AuditActor implements AuditActorInterface
{
    public function __construct(private readonly Auth0SDK $auth0)
    {
    }

    public function getActorId(): string
    {
        $session = $this->auth0->getCredentials();
        return $session?->user['sub'] ?? AuditEntry::ANONYMOUS_ACTOR_ID;
    }

    public function getActorLabel(): ?string
    {
        $session = $this->auth0->getCredentials();
        return $session?->user['email'] ?? null;
    }
}
```

```yaml
# config/services.yaml
services:
    Polysource\Audit\Model\AuditActorInterface:
        alias: App\Audit\Auth0AuditActor
```

The contract enforces `actor_id` always non-empty — return
`AuditEntry::ANONYMOUS_ACTOR_ID` for unauthenticated requests. Audit
queries can then group by actor without `IS NULL` filters.

For machine-to-machine tokens / service accounts, name them
explicitly: `'__service__:billing-cron'` or `'__bot__:slackbot'`.
Convention: prefix with double-underscore so they sort apart from
real users and stay visually distinct in the UI.

## Streamed CSV download

`ExportAuditCsvAction` writes the CSV to disk + stamps the path on
the flash because the bulk-action contract returns `ActionResult`,
not `Response`. For hosts that want a direct streamed download,
ship a custom controller consuming `AuditCsvExporter`:

```php
#[Route('/admin/audit-log/export', name: 'app_audit_export')]
public function export(
    EntityManagerInterface $em,
    AuditCsvExporter $exporter,
    Security $security,
): StreamedResponse {
    if (!$security->isGranted('POLYSOURCE_AUDIT_VIEW')) {
        throw $this->createAccessDeniedException();
    }

    $records = $em->createQuery('SELECT r FROM ' . AuditEntryRecord::class . ' r ORDER BY r.occurredAt DESC')
                  ->toIterable();

    $response = new StreamedResponse(function () use ($exporter, $records) {
        $handle = fopen('php://output', 'wb');
        $exporter->write($records, $handle);
        fclose($handle);
    });
    $response->headers->set('Content-Type', 'text/csv');
    $response->headers->set('Content-Disposition', 'attachment; filename="audit.csv"');

    return $response;
}
```

The exporter only buffers one row at a time, so memory stays flat
even for million-row exports.

## Hosts without Doctrine

`PolysourceAuditExtension::load()` gates all Doctrine-dependent
services (`DoctrineAuditLogger`, `AuditLogDataSource`,
`AuditLogResource`, `ExportAuditCsvAction`,
`PurgeAuditCommand`) on `interface_exists(EntityManagerInterface)`.
Apps without Doctrine still see:
- The 2 events in `polysource/symfony-bundle`.
- `ActionAuditSubscriber` running on every action.
- `NullAuditLogger` as the default sink (do-nothing).
- `AuditActorInterface` with the Symfony Security default.

To actually persist, ship a non-Doctrine logger — append-only file,
Redis stream, NATS subject, whatever:

```php
final class JsonlAuditLogger implements AuditLoggerInterface
{
    public function __construct(private readonly string $path)
    {
    }

    public function log(AuditEntry $entry): void
    {
        $row = [
            'id' => $entry->id,
            'occurred_at' => $entry->occurredAt->format(\DATE_ATOM),
            'actor_id' => $entry->actorId,
            'resource_name' => $entry->resourceName,
            'action_name' => $entry->actionName,
            'outcome' => $entry->outcome->value,
            'duration_ms' => $entry->durationMs,
        ];
        file_put_contents(
            $this->path,
            json_encode($row, \JSON_THROW_ON_ERROR) . "\n",
            \FILE_APPEND,
        );
    }
}
```

You give up the browsable index + Art. 30 CSV export, but the
write path is portable.

## Custom audit log actions

The `AuditLogResource` wires a `tagged_iterator('polysource.audit.action')`
into its `configureActions()`. Ship your own:

```php
final class FlagAsReviewedAction implements BulkActionInterface
{
    public function getName(): string { return 'flag-as-reviewed'; }
    public function getLabel(): string { return 'Flag as reviewed'; }
    public function getIcon(): ?string { return 'check'; }
    public function getPermission(): ?string { return 'POLYSOURCE_AUDIT_REVIEW'; }
    public function isDisplayed(array $context = []): bool { return true; }

    public function executeBatch(iterable $records): ActionResult
    {
        // Append a "reviewed by $actor at $time" note into a side
        // table (audit entries themselves are immutable).
        // …
        return ActionResult::success('marked');
    }
}
```

```yaml
services:
    App\Audit\FlagAsReviewedAction:
        tags: ['polysource.audit.action']
```

## What's *not* extensible (yet)

- **Mutating the AuditEntry shape**. The 12 fields are locked;
  hosts wanting extra columns ship a parallel side-table joined
  on `id`. v0.2 may introduce an `AuditContextEnricherInterface`
  contributor tag if demand is real.
- **Async write path**. v0.1 logs synchronously. v0.2 ships an
  `AsyncAuditLogger` decorator that dispatches a Messenger
  message, freeing the request cycle for slow sinks (Datadog
  HTTP). Track the issue list if you need it now.

## See also

- [ADR-020](../../adr/0020-audit-non-doctrine-actions.md) §"Suite (post-v0.1)"
  — what's coming after.
- [installation.md](./installation.md) — wiring basics.
- [walkthrough.md](./walkthrough.md) — UX flow.
