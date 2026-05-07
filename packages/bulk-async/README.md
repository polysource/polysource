# polysource/bulk-async

> Asynchronous bulk actions for Polysource — execute over Symfony Messenger with live progress (Mercure) and cancel mid-flight.

Part of the [Polysource](https://github.com/polysource/polysource) monorepo. MIT-licensed.

## When to use

The synchronous bulk action ("Retry 5 000 failed messages") times out after 30 s in production. This package fans the work out to Messenger workers, persists per-job progress every 5 records or 500 ms, and exposes a JSON endpoint + Mercure topic so the UI can show a live progress bar and a "Cancel" button.

See [ADR-024](../../docs/adr/0024-bulk-async-mercure.md).

## What it ships

- **`BulkJob`** immutable VO (12 fields, 8 KiB error cap) + **`BulkJobStatus`** enum (5 states, `isTerminal()`).
- **`BulkJobStorageInterface`** + **`DoctrineBulkJobStorage`** + Doctrine entity.
- **`BulkJobMessage`** + **`BulkJobHandler`** — re-fetches each iteration to honour Cancelled, throttled persist (5 records OR 500 ms), per-record exception isolation.
- **`AsyncBulkActionDispatcher`** — UUID v7 + Pending persist + Messenger dispatch.
- **`AsyncAwareBulkActionInterface`** — opt-in marker (parallel interface, no BC break to `BulkActionInterface`).
- **`BulkJobResource`** — browsable admin resource (`#[AsResource]`, slug `bulk-jobs`).
- **`CancelBulkJobAction`** — idempotent on terminal, gated `POLYSOURCE_BULK_JOB_CANCEL`.
- **`ProgressController`** — JSON `GET /admin/bulk-jobs/{id}/progress`.
- **`MercureBulkJobBroadcaster`** — gated on `class_exists(HubInterface)`, hub failures swallowed, topic `polysource/bulk-jobs/{id}`.
- **Stimulus `progress_controller.js`** — EventSource Mercure → polling fallback auto on error.

## Install

```bash
composer require polysource/bulk-async symfony/messenger
# Optional but recommended for live progress:
composer require symfony/mercure-bundle
```

Register the bundle:

```php
return [
    Polysource\BulkAsync\PolysourceBulkAsyncBundle::class => ['all' => true],
];
```

Run the migration to create `polysource_bulk_jobs`.

## Documentation

- [Bulk-async walkthrough](../../docs/user/bulk-async/)
