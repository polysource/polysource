# Integrate Polysource into an existing Symfony app

A walkthrough validated on a production Symfony app (PHP 8.4,
Symfony 6.4, EasyAdmin 5, Doctrine ORM 2, Webpack Encore, Cognito
auth, multi-app Kernel). Two integrations:

1. **Enhance an existing EA CRUD with the filter bridge** — zero
   refactor, drop-in package install. Target: a CRUD with text +
   choice + custom filter classes.
2. **Replace a hand-rolled Redis listing with a Polysource resource** —
   a worker-queue dashboard reads from a Redis LIST (not hash) via
   a custom queue manager. We adapt it to `DataSourceInterface` in
   ~50 lines and get a paginated, filterable admin table for free.

Both run side-by-side, no clash.

---

## Part 1 — Bridge install (5 minutes)

### 1.1 — composer.json

Add the bridge package. Until v0.1.0 hits Packagist, install from a
local path repository.

**Real-world tip**: if your Symfony app runs inside Docker and the
polysource checkout lives **outside** the container's mount, use a
`vendor-local/` sub-directory of your project so the path repos
resolve from inside the container too:

```bash
mkdir -p vendor-local/polysource
for pkg in core filter twig-theme symfony-bundle easyadmin-filter-bridge; do
    cp -R /path/to/polysource/packages/$pkg vendor-local/polysource/$pkg
done
```

Then:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "vendor-local/polysource/core",
            "options": { "symlink": true }
        },
        {
            "type": "path",
            "url": "vendor-local/polysource/filter",
            "options": { "symlink": true }
        },
        {
            "type": "path",
            "url": "vendor-local/polysource/twig-theme",
            "options": { "symlink": true }
        },
        {
            "type": "path",
            "url": "vendor-local/polysource/symfony-bundle",
            "options": { "symlink": true }
        },
        {
            "type": "path",
            "url": "vendor-local/polysource/easyadmin-filter-bridge",
            "options": { "symlink": true }
        }
    ],
    "require": {
        "polysource/core": "0.1.x-dev as 0.1.0",
        "polysource/filter": "0.1.x-dev as 0.1.0",
        "polysource/twig-theme": "0.1.x-dev as 0.1.0",
        "polysource/symfony-bundle": "0.1.x-dev as 0.1.0",
        "polysource/easyadmin-filter-bridge": "0.1.x-dev as 0.1.0"
    }
}
```

The `as 0.1.0` alias bypasses the `minimum-stability: stable`
default — most production apps don't lower their stability floor
to `dev` for one package.

```bash
composer update polysource/easyadmin-filter-bridge polysource/symfony-bundle polysource/twig-theme polysource/filter polysource/core -W
```

### 1.2 — Register the bundles

If your project has a **single Kernel** (90% of Symfony apps) and
Symfony Flex is installed, the bundles auto-register. Skip ahead.

If you have a **multi-app Kernel** (one Kernel serving multiple
apps via an `appId`, where each app has its own `bundles.php`),
Flex auto-registers in the shared `config/bundles.php` — but then
the bundles boot for every app, which is wrong if EasyAdmin only
loads in the admin/backend app. **Move the Polysource bundle
declarations to the EA-app's own `bundles.php`**:

```php
// apps/backend/config/bundles.php
use Polysource\Bundle\PolysourceBundle;
use Polysource\EasyAdminFilterBridge\PolysourceEasyAdminFilterBridgeBundle;
use Polysource\Filter\PolysourceFilterBundle;

return [
    BackendBundle::class       => ['all' => true],
    EasyAdminBundle::class     => ['all' => true],
    // ...other backend-specific bundles
    PolysourceFilterBundle::class                  => ['all' => true],
    PolysourceEasyAdminFilterBridgeBundle::class   => ['all' => true],
    PolysourceBundle::class                        => ['all' => true],
];
```

And **remove** them from the shared `config/bundles.php` if Flex
put them there.

### 1.3 — Hot-reload the page

Visit `/admin?routeName=admin&crudControllerFqcn=Backend%5CController%5CAdmin%5CSampleResourceCrudController&crudAction=index`.

What happens **without ANY change to your CrudController**:

| Original filter | Enhancement applied |
|---|---|
| `'name'` (TextFilter implicit) | Mode toggle (contains / starts_with / exact / not exact) inline. |
| `ChoiceFilter $offer / $type` | Choices render as dropdown chips when there are ≤ 5 options. |
| `'universalAdId'` (TextFilter implicit) | Same as `name`. |
| Custom `AssociationByIdFilter` | Untouched (the bridge only enhances the 8 built-in EA filter types). |
| Custom `Freewheel*Filter` | Untouched. |

You also get **chips above the table** showing every active filter with
an X to remove it individually, and a **Clear all** link.

### 1.4 — Optional opt-ins

Want filter **tabs** or **groups** in the modal? Add markers in
`configureFilters()`:

```php
use Polysource\EasyAdminFilterBridge\Bridge\Polysource;

public function configureFilters(Filters $filters): Filters
{
    $offerFilter = ChoiceFilter::new('offer')->setChoices(PubConstants::getActiveOffersChoices());
    $typeFilter  = ChoiceFilter::new('type')->setChoices(PubConstants::getActiveTypesChoices());

    return $filters
        ->add(Polysource::tab('Identification'))
        ->add(AssociationByIdFilter::new('macroOrder', 'Id de la commande'))
        ->add('name')
        ->add('universalAdId')

        ->add(Polysource::tab('Status'))
        ->add(FreewheelCreativeIsSentFilter::new('isSent')->setFormTypeOption('mapped', false))
        ->add(FreewheelCreativeIsInErrorFilter::new('hasError')->setFormTypeOption('mapped', false))

        ->add(Polysource::tab('Classification'))
        ->add($typeFilter)
        ->add($offerFilter);
}
```

The modal now renders a Bootstrap tab strip with a per-tab counter
showing how many filters from that tab are active. Useful when you
have 10+ filters and want to keep the modal short.

Want **saved views** (per-user filter combinations stored in DB)?
Three steps in [Cookbook → Saved views](../filter/saved-views.md). One
Doctrine migration, the dropdown shows up automatically next to the
EA Filters button.

### 1.5 — AssetMapper or Webpack Encore?

The bridge ships its Stimulus controllers under
`vendor/polysource/easyadmin-filter-bridge/assets/controllers/`. Either:

- **AssetMapper** (recommended): the bundle's DI extension prepends
  the asset path automatically. Make sure your EA Dashboard exposes
  the host importmap with `Assets::new()->addAssetMapperEntry('app')`
  in `configureAssets()`.
- **Webpack Encore**: import the controllers manually in
  `bootstrap.js`:
  ```js
  import filterModalLayoutController from '@polysource/filter/controllers/filter_modal_layout_controller.js';
  app.register('polysource--filter-modal-layout', filterModalLayoutController);
  ```

If neither is wired, the modal still renders but stays flat (no
tabs/accordions even with `Polysource::tab()` markers). The chips
bar works either way (server-rendered HTML).

---

## Part 2 — Workers queue as a Polysource resource

The current `WorkerStatusController::workersQueueAction` reads from a
Redis LIST via `JobQueueManager::getQueuedRequests`, hand-builds an
array, and renders a custom Twig template. Pagination, filters, sort
— none of that exists. We're going to adapt the same data into a
Polysource resource.

### 2.1 — Add the standalone bundles

```bash
composer require polysource/symfony-bundle polysource/twig-theme polysource/core
```

`apps/backend/config/bundles.php`:

```php
return [
    // ...existing
    Polysource\Bundle\PolysourceBundle::class => ['all' => true],
];
```

`apps/backend/config/packages/polysource.yaml`:

```yaml
polysource:
    url_prefix: /admin/polysource
    max_page_size: 100
```

`apps/backend/config/routes.yaml`:

```yaml
polysource:
    resource: '@PolysourceBundle/Resources/config/routing.php'
    type: polysource
```

### 2.2 — Custom DataSource adapting `JobQueueManager`

The data lives in a Redis LIST (`lrange` / `llen`), not a hash. Our
`polysource/adapter-redis` package handles hashes. For lists we
implement `DataSourceInterface` directly — it's 3 methods.

`apps/backend/src/Polysource/DataSource/WorkersQueueDataSource.php`:

```php
<?php

declare(strict_types=1);

namespace Backend\Polysource\DataSource;

use Polysource\Core\DataPage;
use Polysource\Core\DataQuery;
use Polysource\Core\DataRecord;
use Polysource\Core\DataSource\DataSourceInterface;
use Shared\Constant\Queue;
use Shared\Constant\Worker;
use Shared\Repository\VideoOrderRepositoryInterface;
use Shared\Service\Worker\JobQueueManager;
use Shared\ValueObject\MoveDateTime;

/**
 * Adapts JobQueueManager (Redis list of JSON strings) to the
 * Polysource DataSourceInterface contract.
 *
 * Each list entry is a JSON-encoded payload like:
 *   {"videoOrderId": 12345, "expirationTimestamp": 1714000000}
 *
 * We hydrate it into a DataRecord including the joined VideoOrder /
 * MacroOrder so the index page can render rich columns.
 *
 * Filters supported:
 *   - priority: "high" | "low" (in)
 *   - workflowStep (eq)
 *   - macroOrderId (eq)
 *
 * Pagination is offset-based (the underlying lrange takes start/stop).
 */
final readonly class WorkersQueueDataSource implements DataSourceInterface
{
    public function __construct(
        private JobQueueManager $jobQueueManager,
        private VideoOrderRepositoryInterface $videoOrderRepository,
    ) {
    }

    public function search(DataQuery $query): DataPage
    {
        $priorities = $this->priorityFilter($query);
        $records = [];

        foreach ($priorities as $priority) {
            $rawEntries = $this->jobQueueManager->getQueuedRequests(
                Worker::JOB_TYPE_RESUME_WORKFLOW,
                $priority,
                limit: $query->pagination->limit + $query->pagination->offset,
            );
            foreach ($rawEntries as $rawEntry) {
                $payload = json_decode($rawEntry, true, 512, JSON_THROW_ON_ERROR);
                $videoOrder = $this->videoOrderRepository->findOneById($payload['videoOrderId'] ?? null);
                if ($videoOrder === null) {
                    continue;
                }

                // Apply filters client-side (Redis LIST has no native
                // filtering — would need a server-side script for
                // bigger scale, fine here since the queue is capped
                // at 10000 by JobQueueManager::MAX_QUEUE_LENGTH).
                if (!$this->matchesCriteria($payload, $videoOrder, $query)) {
                    continue;
                }

                $expiration = new MoveDateTime('@' . $payload['expirationTimestamp']);
                $macroOrder = $videoOrder->getMacroOrder();

                $records[] = new DataRecord(
                    identifier: (string) $videoOrder->getId(),
                    attributes: [
                        'priority' => $priority,
                        'videoOrderId' => $videoOrder->getId(),
                        'macroOrderId' => $macroOrder?->getId(),
                        'macroOrderTitle' => $macroOrder?->getLongTitle(),
                        'screen' => $videoOrder->getScreen()?->getLabel(),
                        'workflowStep' => $videoOrder->getWorkflowStepLabel(),
                        'expiration' => $expiration->format('Y-m-d H:i:s'),
                    ],
                );
            }
        }

        // Apply offset+limit AFTER filtering — client-side
        // pagination since Redis LIST can't filter natively.
        $total = \count($records);
        $page = \array_slice($records, $query->pagination->offset, $query->pagination->limit);

        return new DataPage(items: $page, total: $total);
    }

    public function find(string $identifier): ?DataRecord
    {
        // Detail page would need to scan the list to find the entry
        // with this id. For workers/queue the index is enough; we
        // return null so the bundle skips the detail link.
        return null;
    }

    public function count(DataQuery $query): ?int
    {
        $high = $this->jobQueueManager->getQueueLength(Worker::JOB_TYPE_RESUME_WORKFLOW, Queue::HIGH_PRIORITY);
        $low = $this->jobQueueManager->getQueueLength(Worker::JOB_TYPE_RESUME_WORKFLOW, Queue::LOW_PRIORITY);

        return $high + $low;
    }

    /**
     * @return list<string> Priority queues to scan, after filtering.
     */
    private function priorityFilter(DataQuery $query): array
    {
        $criterion = $query->filters['priority'] ?? null;
        if ($criterion === null) {
            return [Queue::HIGH_PRIORITY, Queue::LOW_PRIORITY];
        }

        $values = (array) $criterion->value;

        return array_values(array_filter(
            [Queue::HIGH_PRIORITY, Queue::LOW_PRIORITY],
            static fn (string $p): bool => \in_array($p, $values, true),
        ));
    }

    private function matchesCriteria(array $payload, $videoOrder, DataQuery $query): bool
    {
        foreach ($query->filters as $name => $criterion) {
            if ($name === 'priority') {
                continue; // already filtered at the queue level
            }
            $value = match ($name) {
                'macroOrderId' => $videoOrder->getMacroOrder()?->getId(),
                'workflowStep' => $videoOrder->getWorkflowStepLabel(),
                'screen' => $videoOrder->getScreen()?->getLabel(),
                default => null,
            };
            if ($value === null) {
                return false;
            }
            $needle = $criterion->value;
            $matches = match ($criterion->operator) {
                'eq' => (string) $value === (string) $needle,
                'like' => stripos((string) $value, (string) $needle) !== false,
                'in' => \in_array((string) $value, array_map('strval', (array) $needle), true),
                default => true,
            };
            if (!$matches) {
                return false;
            }
        }

        return true;
    }
}
```

### 2.3 — Resource declaration

`apps/backend/src/Polysource/Resource/WorkersQueueResource.php`:

```php
<?php

declare(strict_types=1);

namespace Backend\Polysource\Resource;

use Backend\Polysource\DataSource\WorkersQueueDataSource;
use Backend\Polysource\Filter\WorkersQueueFilter;
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Field\FieldInterface;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Resource\AbstractResource;

#[AsResource]
final class WorkersQueueResource extends AbstractResource
{
    public function __construct(WorkersQueueDataSource $dataSource)
    {
        parent::__construct(
            dataSource: $dataSource,
            slug: 'workers-queue',
            label: 'Workers queue',
            // Re-use the host app's existing permission constant
            // if the legacy route is gated.
            permission: 'POLYSOURCE_WORKERS_QUEUE_VIEW',
        );
    }

    public function configureFields(string $page): iterable
    {
        // Use the package's Field helpers OR build minimal FieldDto
        // directly — depends on which template helpers you wire.
        // The showcase has an `App\Polysource\Field\Field` factory
        // class you can copy.
        yield Field::new('priority', 'Priority')->asText();
        yield Field::new('videoOrderId', 'Video order')->asId();
        yield Field::new('macroOrderId', 'Macro order')->asText();
        yield Field::new('macroOrderTitle', 'Title')->asText();
        yield Field::new('screen', 'Screen')->asText();
        yield Field::new('workflowStep', 'Workflow step')->asText();
        yield Field::new('expiration', 'Expires at')->asText();
    }

    public function configureFilters(): iterable
    {
        yield WorkersQueueFilter::priority();
        yield WorkersQueueFilter::macroOrderId();
        yield WorkersQueueFilter::workflowStep();
        yield WorkersQueueFilter::screen();
    }
}
```

### 2.4 — Filter declaration

`apps/backend/src/Polysource/Filter/WorkersQueueFilter.php`:

```php
<?php

declare(strict_types=1);

namespace Backend\Polysource\Filter;

use Polysource\Core\Filter\FilterDto;
use Polysource\Core\Filter\FilterInterface;
use Polysource\Core\Query\DataQuery;
use Polysource\Core\Query\FilterCriterion;
use Shared\Constant\Queue;

final class WorkersQueueFilter implements FilterInterface
{
    public function __construct(
        private readonly string $property,
        private readonly string $label,
        private readonly array $supportedOperators,
        private readonly array $customOptions = [],
    ) {
    }

    public function getProperty(): string { return $this->property; }
    public function getLabel(): string { return $this->label; }
    public function getSupportedOperators(): array { return $this->supportedOperators; }

    public function applyToQuery(DataQuery $query, FilterCriterion $criterion): DataQuery
    {
        return $query->withFilter($this->property, $criterion);
    }

    public function getAsDto(): FilterDto
    {
        return new FilterDto($this->property, $this->label, $this->supportedOperators, customOptions: $this->customOptions);
    }

    public static function priority(): self
    {
        return new self('priority', 'Priority', ['in'], [
            'choices' => [
                'High' => Queue::HIGH_PRIORITY,
                'Low' => Queue::LOW_PRIORITY,
            ],
        ]);
    }

    public static function macroOrderId(): self
    {
        return new self('macroOrderId', 'Macro order ID', ['eq']);
    }

    public static function workflowStep(): self
    {
        return new self('workflowStep', 'Workflow step', ['like', 'eq']);
    }

    public static function screen(): self
    {
        return new self('screen', 'Screen', ['like', 'eq']);
    }
}
```

### 2.5 — Permission

`apps/backend/src/Security/Voter/PolysourceWorkersQueueVoter.php`:

Map `POLYSOURCE_WORKERS_QUEUE_VIEW` to whatever role gates the
existing `WORKER_QUEUE` route in `Permission::TO_ACCESS_ROUTE`.

```php
final class PolysourceWorkersQueueVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return str_starts_with($attribute, 'POLYSOURCE_WORKERS_QUEUE_');
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Reuse whatever role/permission gates the existing /workers/queue route.
        return $this->security->isGranted(Permission::TO_ACCESS_ROUTE, RouteConstants::WORKER_QUEUE);
    }
}
```

### 2.6 — Visit `/admin/polysource/workers-queue`

Page renders with:
- Sidebar (uses Polysource's default theme until you wire a custom
  `polysource.layout_template` to match your existing chrome).
- Table with the 7 columns above.
- "Filters" button → modal with 4 filters: priority dropdown,
  macroOrderId text, workflowStep text (LIKE), screen text (LIKE).
- Active-filter chips above the table.
- Pagination at the bottom.

The original `/workers/queue` route still works — they live
side-by-side. Either decommission the old one when you're confident,
or keep both (the new one paginates and filters; the old one shows
a flat dump).

---

## Part 3 — `workers_status` (job occupations)

`workers_status` displays the **occupation** of each worker (what
videoOrder it's currently processing). The data is in a different
Redis structure (one HASH per worker) and joins with `VideoOrder`
records.

This one fits `polysource/adapter-redis` directly — `RedisHashDataSource`
expects key prefix + hash format. You'd:

```php
use Polysource\Adapter\Redis\Resource\RedisHashResource;
use Polysource\Adapter\Redis\DataSource\RedisHashDataSource;

#[AsResource]
final class WorkersStatusResource extends RedisHashResource
{
    public function __construct(RedisHashClientInterface $client)
    {
        parent::__construct(
            dataSource: new RedisHashDataSource($client, 'worker:occupation:'),
            slug: 'workers-status',
            label: 'Workers status',
            permission: 'POLYSOURCE_WORKERS_STATUS_VIEW',
        );
    }
    // configureFields() + configureFilters() — same shape as Part 2.
}
```

The fields would be hash properties: `name`, `status`, `videoOrderId`,
`startedAt`, etc. Filters: `status` (in: idle/working/error),
`videoOrderId` (eq), `startedAt` (between).

Plus side: you get `RedisHashResource` features for free (SCAN
cursor pagination — works on production-size key spaces, unlike
client-side pagination of LIST which has to load everything).

---

## Common pitfalls (learned from real integrations)

### Channel/tenant URL prefix

Some apps enforce a tenant prefix on every URL via a host
middleware (e.g. a `/{channel}/...` pattern that gates every
incoming request). The Polysource bundle's `url_prefix` only sets
the part AFTER the mount; it doesn't know about your tenant
prefix. If you see
`'admin' is not a valid channel` (or similar) when hitting
`/admin/polysource/...`, prefix the import:

```yaml
polysource:
    resource: .
    type: polysource
    prefix: /%channel%

polysource_easyadmin_filter_bridge:
    resource: '../../../../vendor/polysource/easyadmin-filter-bridge/src/Controller/'
    type: attribute
    prefix: /%channel%
```

`%channel%` is whatever the host parameter is called. The
**route names stay unchanged**, so the dropdown's
`path('polysource_saved_view_create', …)` still resolves — the
`{channel}` segment is auto-filled from the request.

### Doctrine ORM mapping for SavedViewRecord

If you see:

```
The class 'Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord'
was not found in the chain configured namespaces …
```

You're on a `polysource/filter` < dev-main version that didn't
auto-prepend the Doctrine mapping. Either bump to a newer dev tag
or add the mapping manually:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        mappings:
            PolysourceFilterSavedView:
                type: attribute
                is_bundle: false
                dir: '%kernel.project_dir%/vendor-local/polysource/filter/src/SavedView/Storage/Doctrine'
                prefix: 'Polysource\Filter\SavedView\Storage\Doctrine'
                alias: PolysourceFilterSavedView
```

You also need to run a migration to create `polysource_saved_views`.
Either generate one with `doctrine:migrations:diff` (filter the
generated SQL down to just the `polysource_saved_views` table) or
use the canonical SQL from `docs/user/filter/saved-views.md`.

### Multi-app Kernel — bundles per app, not shared

Symfony Flex auto-registers bundles in the SHARED `config/bundles.php`.
On a multi-app Kernel where EasyAdmin only loads in the backend
app (and not in job/privateapi/etc), the bridge bundle would also
load there and fail because EasyAdmin classes are missing. Move
the bundle declarations to the backend's per-app `bundles.php` and
remove from shared.

### Webpack Encore (no AssetMapper) — Stimulus controllers absent

The bridge ships Stimulus controllers under
`vendor/polysource/easyadmin-filter-bridge/assets/controllers/`.
With AssetMapper they auto-load. With Webpack Encore they don't —
your Encore config doesn't know about them. The data-controller
attrs in the rendered HTML do nothing. Symptoms:

- Filter modal tabs render flat (`Polysource::tab()` markers
  ignored)
- Date presets / quick ranges / clear button on enhanced filters
  don't fire

Fix: `npm install` the controllers as a node module pointing at
the path, OR copy the controller files into your `assets/`
directory and import them in `bootstrap.js`:

```js
import filterModalLayoutController from './polysource_filter_modal_layout_controller.js';
app.register('polysource--filter-modal-layout', filterModalLayoutController);
```

The chips bar + saved-views dropdown work either way (server-
rendered HTML, no JS dependency).

## Validation checklist

After installing:

- [ ] `/admin?...&SampleResourceCrudController...` shows
      enhanced text/choice filters and chips bar above the table.
- [ ] Custom filters (`AssociationByIdFilter`, `Freewheel*Filter`)
      still work unchanged.
- [ ] No existing test in `tests/Functional/Controller/Admin/`
      breaks — the bridge is purely additive.
- [ ] `/admin/polysource/workers-queue` renders the queue with
      filters + pagination. Existing `/workers/queue` route is
      untouched.
- [ ] `composer.json` validates: `composer validate`.
- [ ] CI green: `vendor/bin/phpunit`.

If something doesn't look right, check the
[upstream package changelogs](../../../CHANGELOG.md) — the
integration points (`polysource_layout` config, saved-view URL
contract, importmap requirements, EA layout cascade) all changed
in the run-up to v0.1.0.
