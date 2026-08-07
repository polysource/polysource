# `polysource/bulk-async` — async bulk actions + live progress

`polysource/bulk-async` ships the missing piece for operators running
bulk actions on thousands of records: dispatch the action through
Symfony Messenger, persist a `BulkJob` row, watch progress live (via
Mercure SSE or a polling fallback), cancel mid-flight.

Two consumers run side-by-side:
- **SRE / on-call** stop fearing the "Retry all 5,000 envelopes"
  button — the action no longer hits the HTTP timeout, the operator
  sees a progress bar, and a Cancel button is one click away.
- **Compliance** still gets a full audit trail through
  `polysource/audit` — the handler dispatches `ActionExecutedEvent`
  on terminal status with `actionName = bulk:<original>` so the
  audit log captures async runs alongside synchronous ones.

Per [ADR-024](../../adr/0024-bulk-async-mercure.md), bulk-async ships
as a **separate package** rather than baked into
`polysource/symfony-bundle`, so apps that don't need Messenger /
Mercure pay no DI / dependency cost. The bundle is opt-in.

## What's in this folder

| File | What's in it |
|---|---|
| [installation.md](./installation.md) | Install the bundle, register the schema, configure the Messenger transport, wire Mercure (optional). |
| [walkthrough.md](./walkthrough.md) | End-to-end: opt an existing bulk action into async, dispatch it, watch progress, cancel mid-flight, browse the dashboard. |
| [extending.md](./extending.md) | Custom storage (Redis, in-memory), opt-in `AsyncAwareBulkActionInterface`, retention strategy, Mercure topic security. |

## Status

**Shipped — v1.1.0 (2026-08-07).** Public API frozen since v1.0.0 under
strict SemVer, documented in
[ADR-024](../../adr/0024-bulk-async-mercure.md):
- 12-field `BulkJob` value object + `BulkJobStatus` enum
- `BulkJobStorageInterface` (3 methods, default `DoctrineBulkJobStorage`)
- `BulkJobMessage` envelope + `BulkJobHandler` Messenger worker
- `AsyncBulkActionDispatcher` (host-facing entry point)
- `AsyncAwareBulkActionInterface` (opt-in marker)
- Browsable `BulkJobResource` at `/admin/bulk-jobs` with cancel inline action
- `GET /admin/bulk-jobs/{id}/progress` JSON endpoint
- `MercureBulkJobBroadcaster` (gated on `symfony/mercure`)
- Stimulus `progress_controller.js` + Twig `polysource_bulk_progress(job)`

## Why this matters

Existing Symfony admin packages run bulk actions synchronously inside
the request-response cycle. Above ~2,000 records on a typical PHP
30-second timeout, the operator sees a 502 / a blank page and has no
idea how many records actually got processed. Filament (Laravel) has
shipped `BulkAction::async()` since 2024; Polysource is the first
Symfony admin to ship the equivalent end-to-end (Messenger handler,
Mercure live progress, cancel from the UI, audit trail per ADR-020).

Concretely this unblocks regulated audiences (fintech, e-commerce,
ops-heavy SaaS) that operate on tens of thousands of records per
batch and can't accept "click retry-all and hope".

## See also

- [ADR-024 — Bulk async + Mercure](../../adr/0024-bulk-async-mercure.md)
- [ADR-020 — Audit non-Doctrine actions](../../adr/0020-audit-non-doctrine-actions.md) — every async run is audited
- [ADR-018 — AdminPluginInterface + public contracts](../../adr/0018-admin-plugin-interface-and-public-contracts.md)
- [`docs/user/audit/`](../audit/) — companion package; the audit log captures async jobs gracefully
