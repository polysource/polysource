# Recently viewed records

> Since `polysource/filter` v0.5.0.

Per-user "most recently viewed records" log — powers a
"Recently viewed" widget on the resource index page or feeds a
command palette with the user's MRU records.

## Usage

In your detail / edit action, call `recordView()` once per
page render:

```php
use Polysource\Filter\RecentRecords\RecentRecordsService;

final class OrderCrudController extends AbstractCrudController
{
    public function __construct(private RecentRecordsService $mru) {}

    public function detail(AdminContext $context): KeyValueStore | Response
    {
        $order = $context->getEntity()->getInstance();

        $this->mru->recordView(
            resourceName: 'orders',
            recordId: (string) $order->getId(),
            label: $order->getReference() . ' — ' . $order->getCustomerName(),
        );

        return parent::detail($context);
    }
}
```

Same call from `edit()`. The service upserts by the
`(user, resource, recordId)` triplet — so the same record
viewed multiple times produces one row with the latest
timestamp, not a growing log.

## Reading the list

```php
$recent = $service->recentForCurrentUser('orders', limit: 10);

foreach ($recent as $entry) {
    echo "<a href=\"...\">{$entry->label}</a> — viewed {$entry->viewedAt->format('Y-m-d H:i')}";
}
```

The list is most-recent-first.

## Storage table

```sql
CREATE TABLE polysource_recent_records (
    owner_id VARCHAR(128) NOT NULL,
    resource_name VARCHAR(128) NOT NULL,
    record_id VARCHAR(128) NOT NULL,
    viewed_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    label VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (owner_id, resource_name, record_id)
);
CREATE INDEX polysource_recent_records_viewed_idx ON polysource_recent_records (viewed_at);
```

Or generate via `php bin/console doctrine:migrations:diff`.

## Retention

The bundle does not prune the log automatically. For most apps
the upsert-by-natural-key strategy means the table size is
bounded by `users × records-touched`, which is naturally
manageable. Apps with many records may install a periodic
trim:

```sql
-- Keep the last 100 entries per user/resource
DELETE FROM polysource_recent_records r
USING (
    SELECT owner_id, resource_name, record_id
    FROM (
        SELECT owner_id, resource_name, record_id,
            ROW_NUMBER() OVER (
                PARTITION BY owner_id, resource_name
                ORDER BY viewed_at DESC
            ) AS rn
        FROM polysource_recent_records
    ) ranked
    WHERE rn > 100
) old
WHERE r.owner_id = old.owner_id
  AND r.resource_name = old.resource_name
  AND r.record_id = old.record_id;
```

## Privacy / GDPR

The log is scoped per-user — it does not expose other users'
browsing history. When a user account is deleted, hosts MUST
clean up their entries:

```sql
DELETE FROM polysource_recent_records WHERE owner_id = :userId;
```

Wire that into your user-deletion endpoint.

## Per ADR-028 scope discipline

The recently-viewed log is a small filter+listing UX
enhancement — feeding a "quick nav" widget. It is NOT a
generic activity log; for that hosts ship their own audit
subsystem.
