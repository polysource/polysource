# `polysource/bulk-async` walkthrough

End-to-end: opt an existing bulk action into async, dispatch it from
your controller, watch the operator UI, cancel mid-flight, and verify
the audit log captured the run.

We'll use the `RetryAllFailedMessagesAction` from
`polysource/adapter-messenger` as the running example — it's the
canonical "this needs to go async" case.

## 1. Mark an action as async-aware (optional)

If you want the action to *self-declare* "I'd rather run async above
N records", implement `AsyncAwareBulkActionInterface`:

```php
use Polysource\BulkAsync\Action\AsyncAwareBulkActionInterface;
use Polysource\Core\Action\BulkActionInterface;

final class RetryAllFailedMessagesAction implements
    BulkActionInterface,
    AsyncAwareBulkActionInterface
{
    public function shouldRunAsync(int $recordCount): bool
    {
        return $recordCount >= 100;
    }

    // … existing executeBatch() implementation unchanged …
}
```

Hosts that don't implement it can still force-async an action from
the controller — the dispatcher accepts any `BulkActionInterface`.

## 2. Dispatch the job

Inject `AsyncBulkActionDispatcher` and route the controller branch:

```php
use Polysource\BulkAsync\Action\AsyncAwareBulkActionInterface;
use Polysource\BulkAsync\Dispatcher\AsyncBulkActionDispatcher;

final class FailedMessagesController
{
    public function __construct(
        private readonly AsyncBulkActionDispatcher $dispatcher,
    ) {}

    public function retryAll(Request $request, FailedMessageResource $resource, RetryAllFailedMessagesAction $action, Security $security): Response
    {
        $ids = $request->request->all('ids');
        $user = $security->getUser();

        $shouldRunAsync = $action instanceof AsyncAwareBulkActionInterface
            && $action->shouldRunAsync(\count($ids));

        if ($shouldRunAsync) {
            $job = $this->dispatcher->dispatch(
                $resource,
                $action,
                array_values($ids),
                $user?->getUserIdentifier() ?? '__anonymous__',
            );

            return new RedirectResponse('/admin/bulk-jobs/' . $job->id);
        }

        // Below the threshold — keep the synchronous path.
        $result = $action->executeBatch($resource->getDataSource()->findMany($ids));

        return new RedirectResponse('/admin/failed-messages');
    }
}
```

The dispatcher:
1. Generates a UUID v7 for the job.
2. Persists a `BulkJob` in `Pending` status.
3. Hands a `BulkJobMessage(jobId)` to the Messenger bus.

The HTTP response returns instantly — typically < 50 ms regardless of
how many records were enqueued.

## 3. Watch the worker pick it up

```bash
bin/console messenger:consume polysource_bulk_async -vv
```

You'll see log lines for each progress flush (every 5 records or
500 ms — whichever comes first). The handler:
- Re-fetches the job each iteration so a Cancel from the UI takes
  effect on the very next record.
- Catches per-record exceptions and counts them as failures rather
  than aborting the whole job.
- Stamps `started_at` on the Running flip and `completed_at` on the
  terminal flush.

## 4. Operator UI

Two surfaces ship out of the box.

### 4.1 Browsable dashboard

Visit `/admin/bulk-jobs` — the index lists all jobs with the four
canonical filters:
- `actorId` (eq) — "show only my jobs"
- `status` (in) — "all running jobs across the system"
- `createdAt` (between/gte/lte) — "today's jobs"
- `resourceName` (in) — "all retry-all jobs on failed-messages"

Each running row carries the inline `cancel` action (only displayed
while `status` is `pending` or `running`).

### 4.2 Live progress card

Embed in any Twig template:

```twig
{% set job = bulk_job %}{# loaded by your detail controller #}

<div class="container py-4">
    <h1>Bulk job {{ job.id }}</h1>
    {{ polysource_bulk_progress(job) }}
</div>
```

The card renders a Bootstrap progress bar with:
- Status pill (badge colour follows status).
- "X / Y records" + "Z failed" counts.
- Animated stripes while running, solid bar on terminal.
- Client-computed ETA based on elapsed wall-clock.

The Stimulus controller polls `GET /admin/bulk-jobs/{id}/progress`
every 2 seconds and stops on terminal status. If you wired Mercure,
pass the topic and the controller switches to SSE:

```twig
{{ polysource_bulk_progress(job, mercure(['polysource/bulk-jobs/' ~ job.id])) }}
```

If Mercure drops the connection mid-run, the controller falls back to
polling automatically — operators never see a stuck progress bar.

## 5. Cancel mid-flight

From the dashboard, click `Cancel` on a Pending or Running row. The
inline action flips the status to `Cancelled` and the worker honours
it on its next record (sub-second on a normal load).

Idempotent: cancelling a job that already terminated returns a
success flash (`"Bulk job already completed — nothing to cancel."`)
rather than an error.

## 6. Verify the audit trail

If you have `polysource/audit` installed, the worker dispatches
`ActionExecutedEvent` on terminal status with:
- `actionName` = `bulk:<original-action-name>` (e.g.
  `bulk:retry-all-failed-messages`)
- `recordIds` = the full list the job targeted
- `result.context.processedCount` / `failedCount` / `jobId`

```bash
bin/console doctrine:query:sql "
SELECT action_name, outcome, json_extract(context_json, '$.actionContext') AS ctx
FROM polysource_audit_log
WHERE action_name LIKE 'bulk:%'
ORDER BY occurred_at DESC LIMIT 5
"
```

You'll see one row per terminated async job — synchronous bulk runs
keep their original `actionName`, so a single dashboard query can
slice "sync vs async" without ambiguity.

## See also

- [installation.md](./installation.md) — bundle / transport / route wiring.
- [extending.md](./extending.md) — custom storage, retention, Mercure topic security.
- [ADR-024](../../adr/0024-bulk-async-mercure.md) — design rationale.
