# Extending `polysource/bulk-async`

Most apps will run the defaults from [installation.md](./installation.md).
This page covers the three real customisation hooks.

## Custom storage

The default `DoctrineBulkJobStorage` covers most cases. Two real
reasons to swap it:

- **High write volume** (10k+ jobs/hour with thousands of records
  each). The throttled flushing already smooths this, but a Redis or
  in-memory store removes the Doctrine round-trip from the worker
  loop entirely.
- **No Doctrine ORM**. If your stack is pure DBAL or another data
  store altogether, you ship your own `BulkJobStorageInterface`.

The contract is three methods:

```php
namespace Polysource\BulkAsync\Job;

interface BulkJobStorageInterface
{
    public function save(BulkJob $job): void;          // idempotent on $job->id
    public function find(string $id): ?BulkJob;
    public function listForActor(string $actorId, int $limit = 50): array;
}
```

Then alias it in your DI:

```php
// config/services.php
$services->set(App\Storage\RedisBulkJobStorage::class)
    ->arg('$redis', service('snc_redis.default'));

$services->alias(
    Polysource\BulkAsync\Job\BulkJobStorageInterface::class,
    App\Storage\RedisBulkJobStorage::class,
);
```

The browsable `BulkJobResource` uses a separate Doctrine-coupled
`BulkJobDataSource` under the hood. If you swap storage but keep
Doctrine, leave the data source alone — it queries the table
directly. If you go fully non-Doctrine, ship your own
`DataSourceInterface` against your store and re-bind it in DI.

## Retention

`polysource/bulk-async` ships no purge command — as of v1.1.0 there
is no `polysource:bulk-jobs:purge`. (`polysource:audit:purge` exists,
but it prunes the audit log, not the job table.) Strategies that
work today:

- **Doctrine cron job** — a one-line SQL works:

  ```sql
  DELETE FROM polysource_bulk_jobs
  WHERE status IN ('completed','failed','cancelled')
    AND completed_at < NOW() - INTERVAL '30 days';
  ```

- **Custom Symfony command** mirroring the `polysource:audit:purge`
  signature (`--before=YYYY-MM-DD`). `PurgeAuditCommand` in
  `polysource/audit` is a short, readable model to copy.

If you keep more than ~3 months of jobs, partition the table by
month — the `created_at` index keeps queries fast even at 10M+ rows.

## Async opt-in beyond `AsyncAwareBulkActionInterface`

Implementing the marker interface is the canonical way to advertise
"I want async above N records". For finer control:

- **Per-resource policy.** Inject `RequestStack` into your
  `BulkActionInterface` impl and inspect headers (`X-Async-Force: 1`)
  or a query param (`?async=1`) before deciding. Useful for letting
  ops force-async from a specific URL without redeploying.
- **Per-actor policy.** Inject `Security` and gate the threshold:
  internal `ROLE_OPS` operators get `0` (always async),
  external API consumers get `100` (async above 100 records).
- **Force-sync escape hatch.** Always keep the synchronous fallback
  path in the controller — if Messenger is down, sync is still
  better than nothing.

## Mercure topic security

The default broadcaster publishes on `polysource/bulk-jobs/{id}`.
Anyone subscribed to that topic sees the progress payload — which
contains the actor id and the error message.

Two mitigations, in order of strength:

1. **Use Mercure JWT topic claims.** The standard `mercure()` Twig
   helper from `symfony/mercure` accepts a `subscribe` claim list:

   ```twig
   {{ polysource_bulk_progress(job, mercure(
       ['polysource/bulk-jobs/' ~ job.id],
       { subscribe: ['polysource/bulk-jobs/' ~ job.id] }
   )) }}
   ```

   The Mercure hub then refuses any subscriber that doesn't carry a
   matching JWT — operators must be authenticated through your
   firewall before Symfony hands them a JWT cookie.

2. **Per-actor topic prefix.** Override
   `MercureBulkJobBroadcaster::TOPIC_TEMPLATE` in your own subscriber
   to namespace topics by actor (`polysource/bulk-jobs/{actor}/{id}`)
   and grant subscribe claims accordingly. Heavier wiring, only worth
   it if the default JWT gate isn't enough.

If you can't expose Mercure at all (some on-prem deployments forbid
SSE through their proxy), the polling fallback is a strict
super-set — it works through any HTTP proxy and the operator UX is
identical (every 2 seconds vs. instant).

## See also

- [installation.md](./installation.md) — bundle / transport / route wiring.
- [walkthrough.md](./walkthrough.md) — the canonical end-to-end flow.
- [ADR-024](../../adr/0024-bulk-async-mercure.md) — design rationale + alternatives rejected.
