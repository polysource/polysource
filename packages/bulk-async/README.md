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
- **`ProgressController`** — JSON `GET /admin/bulk-jobs/{id}/progress`. Two-stage gate: coarse `POLYSOURCE_BULK_JOB_VIEW` permission + ownership check (requester must own the job, or hold `POLYSOURCE_BULK_JOB_VIEW_ANY`).
- **`MercureBulkJobBroadcaster`** — gated on `class_exists(HubInterface)`, hub failures swallowed, topic `polysource/bulk-jobs/{actorId}/{id}` (actor segment URL-encoded). Pair with the `polysource_bulk_progress_topic(job)` Twig helper so client and broadcaster always agree on the topic shape; configure your Mercure JWT subscriber claims to restrict per-actor for defence-in-depth.
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

## Extend it

`BulkJobStorageInterface` is **3 methods**. To persist jobs in Redis / Mongo / your service instead of Doctrine, implement it and alias the interface to your service in DI. The handler, the `ProgressController`, the Mercure broadcaster all keep working.

See [extensibility map](../../docs/user/extensibility.md#11-14-the-rest-in-one-breath).

## Documentation

- [Bulk-async walkthrough](../../docs/user/bulk-async/)
