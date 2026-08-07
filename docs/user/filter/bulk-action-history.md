# Bulk action history

> Since `polysource/filter` v0.5.0.

An append-only audit log for bulk actions: who ran what, on how
many rows, when. Lets admins audit user activity and gives
hosts the data they need to implement rollback (Polysource
doesn't ship rollback — each action knows how to undo itself).

## Usage

In your bulk-action endpoint, call `record()` after the action
commits:

```php
use Polysource\Filter\BulkActionHistory\BulkActionHistoryService;
use Symfony\Component\Uid\Uuid;

final class OrderBulkController
{
    public function __construct(private BulkActionHistoryService $history) {}

    public function archive(Request $request): Response
    {
        // … run the bulk action, get $affectedCount …

        $this->history->record(
            id: Uuid::v7()->toRfc4122(),
            resourceName: 'orders',
            actionName: 'archive',
            affectedCount: $affectedCount,
            metadata: [
                'filterSlice' => $request->query->all('filters'),
                'targetIds' => $request->request->all('selected'),
            ],
        );

        return $this->redirectToReferer();
    }
}
```

The service resolves the current user automatically from the
Symfony security token. Anonymous users get a no-op (`record()`
silently drops their entries — same convention as
`SavedViewService`).

## Reading the log

```php
// Current user's recent activity on a resource (for a widget on
// the index page):
$mine = $service->recentForCurrentUser('orders', limit: 10);

// Admin view — all users' activity on a resource:
$all = $service->recentForResource('orders', limit: 50);
```

Hosts MUST gate the admin view behind their own admin firewall —
the service trusts the caller.

## Storage table

The default `DoctrineBulkActionHistoryStorage` persists to
`polysource_bulk_action_history`. Hosts must run a migration:

```sql
CREATE TABLE polysource_bulk_action_history (
    id VARCHAR(64) NOT NULL,
    owner_id VARCHAR(128) NOT NULL,
    resource_name VARCHAR(128) NOT NULL,
    action_name VARCHAR(128) NOT NULL,
    affected_count INTEGER NOT NULL,
    occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    metadata_json TEXT DEFAULT NULL,
    PRIMARY KEY (id)
);
CREATE INDEX polysource_bulk_action_history_resource_idx ON polysource_bulk_action_history (resource_name);
CREATE INDEX polysource_bulk_action_history_owner_idx ON polysource_bulk_action_history (owner_id);
CREATE INDEX polysource_bulk_action_history_occurred_idx ON polysource_bulk_action_history (occurred_at);
```

Or use `php bin/console doctrine:migrations:diff` — the entity
mapping auto-discovers on Doctrine bootstrap.

## Why no rollback?

Each bulk action knows how to undo itself in terms specific to
the host's domain (a soft-delete archive vs. a hard-delete
purge). Polysource preserves the audit trail; the host wires
the rollback UI / endpoint on top.

There is no `Reversible` interface and no generic "Undo last
action" button, and none is on the roadmap — a generic undo would
have to guess at domain semantics it cannot know.

## Retention

The bundle does not prune the log — entries live forever
unless the host wires a scheduled cleanup. For most apps that's
fine (the table grows linearly with bulk-action volume); apps
with many bulk operations per day install a simple
`DELETE FROM polysource_bulk_action_history WHERE occurred_at < NOW() - INTERVAL '90 days'`
cron.

## Per ADR-028 scope discipline

The bulk-action history is **filter+listing UX-adjacent** (it
augments the bulk-action affordance Polysource already ships
via `polysource_bulk_scope_toggle()` etc.). It is NOT an
attempt to build a generic activity log for the whole app —
hosts who need that ship their own audit subsystem.
