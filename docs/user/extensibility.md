# Extend Polysource

> **14+ public extension points. Zero forks. No magic.**
>
> Every visible feature in Polysource is a thin shell over a contract you can re-implement. Replace the audit sink with Splunk. Plug Algolia into the Cmd+K palette. Add a Stripe adapter in 80 lines. Ship a custom widget that rotates through your incident KPIs. The contracts are tiny on purpose.

This page is the map. Each section is one extension point: what to implement, where to register it, where to learn more.

---

## TL;DR — the contracts you'll touch most

| You want to… | Implement | Methods | Time |
|---|---|---|---|
| Admin a new data source (Stripe, your microservice, a queue, …) | `DataSourceInterface` | **3** read + 3 write | 1-2 hours |
| Plug a custom backend into Cmd+K search | `SearchProviderInterface` | **3** | 30 min |
| Ship a self-contained capability as an opt-in package | `AdminPluginInterface` + `#[AsPlugin]` | **3 metadata** | 1 hour |
| Pipe audit log to Splunk / Datadog / a SIEM | `AuditLoggerInterface` | **1** (`log`) | 15 min |
| Render a custom dashboard tile | `WidgetInterface` | **5** | 1 hour |
| Add a paginator for an unusual REST API | `PaginationStrategyInterface` | **2** | 30 min |
| Persist saved views somewhere that's not Doctrine | `SavedViewStorageInterface` | **5** | 1 hour |
| Resolve which "team" a user belongs to (for shared saved views) | `SavedViewTeamResolverInterface` | **1** | 5 min |
| Format a filter chip your way | `ChipFormatterInterface` | **1** | 10 min |
| Replace the permission backend (LDAP / OPA / your custom voter) | `PermissionInterface` | **1** | 15 min |

**Most extension points are 1-5 methods.** That's the design budget — if a contract grows past 5 methods we open an ADR and discuss splitting it (cf. ADR-010 on the core API surface criterion).

---

## 1. Build a custom adapter

The single most useful extension point. Anything that can answer "list me some records, optionally with a filter, with pagination" can become a Polysource resource.

```php
namespace App\Polysource\Stripe;

use Polysource\Core\DataSource\DataSourceInterface;
use Polysource\Core\Query\{DataQuery, DataPage, DataRecord, Pagination};

final class StripeChargesDataSource implements DataSourceInterface
{
    public function __construct(private \Stripe\StripeClient $stripe) {}

    public function search(DataQuery $query): DataPage
    {
        $params = ['limit' => $query->pagination->limit];
        // Map polysource filters to Stripe params...

        $charges = $this->stripe->charges->all($params);

        $records = array_map(
            fn($c) => new DataRecord($c->id, ['amount' => $c->amount, 'status' => $c->status]),
            iterator_to_array($charges->autoPagingIterator())
        );

        return new DataPage($records, total: null); // null = unknown, cursor-based
    }

    public function find(string|int $identifier): ?DataRecord { /* ... */ }
    public function count(DataQuery $query): ?int { return null; }
}
```

Make it writable too? Add `WritableDataSourceInterface` (3 more methods: `create`, `update`, `delete`). The UI auto-detects and hides write affordances when the source is read-only.

**Walk-through with all the gotchas**: [Cookbook — build your own adapter](./cookbook/build-your-own-adapter.md) — patterns we learned shipping the 6 bundled adapters (Doctrine, Messenger, Redis, Flysystem, HTTP, Meilisearch).

---

## 2. Plug into the Cmd+K search palette

Already shipped: a `ResourceSearchProvider` that wraps any Polysource Resource. But you can plug **anything**:

```php
namespace App\Polysource\Search;

use Polysource\Search\Search\{SearchProviderInterface, SearchResult};

#[AutoconfigureTag('polysource.search.provider')]
final class AlgoliaSearchProvider implements SearchProviderInterface
{
    public function getId(): string { return 'algolia:products'; }
    public function getLabel(): string { return 'Products (Algolia)'; }

    public function search(string $query, int $limit, float $deadline): array
    {
        // Algolia/ES/custom — return list of SearchResult.
        // The aggregator enforces the deadline globally; you just respect $deadline.
    }
}
```

**Future bridges** (`polysource/search-meilisearch`, `polysource/search-algolia`, `polysource/search-elasticsearch`) are nothing more than a package shipping one of these. See [ADR-023](../adr/0023-global-search-cmdk.md).

---

## 3. Ship a capability as a plugin (ADR-018)

Want to ship "Stripe-charges admin + an audit hook + a custom widget" as one Composer package? That's a plugin:

```php
#[AsPlugin(name: 'acme/stripe-admin', version: '1.2.0')]
final class StripeAdminBundle extends AbstractBundle implements AdminPluginInterface
{
    public function getRequirements(): array { return ['polysource/admin' => '^0.1']; }
    public function getCapabilities(): array { return ['stripe.charges', 'stripe.subscriptions']; }
}
```

The bundle's `services.php` wires resources / actions / widgets. Hosts opt in with one `composer require`. See [ADR-018](../adr/0018-admin-plugin-interface-and-public-contracts.md) and the cookbook chapter on plugins.

This is exactly how the bundled `polysource/audit`, `polysource/bulk-async`, `polysource/widgets`, `polysource/search`, `polysource/workflow-bridge` ship — each is a plugin. **You can ship yours the same way.**

---

## 4. Pipe audit log to your SIEM

Out of the box, `polysource/audit` writes to Doctrine. To add Splunk, Datadog, OpenSearch, or your custom SIEM — implement one method:

```php
#[AutoconfigureTag('polysource.audit_logger')]
final class SplunkAuditLogger implements AuditLoggerInterface
{
    public function log(AuditEntry $entry): void
    {
        $this->splunk->send([
            'time' => $entry->occurredAt->format(\DateTimeInterface::ATOM),
            'event' => $entry->actionName,
            'actor' => $entry->actorId,
            'outcome' => $entry->outcome->value,
            'context' => $entry->context,
        ]);
    }
}
```

The bundled `AggregateAuditLogger` fan-outs to every tagged logger with try/catch isolation — Splunk timing out doesn't break the Doctrine write. See [ADR-020](../adr/0020-audit-non-doctrine-actions.md) §10.

---

## 5. Custom dashboard widgets

The 3 bundled widgets (counter / list / chart) cover 80%. For the 20% that don't fit:

```php
#[AutoconfigureTag('polysource.widgets.dashboard')]
final class IncidentRotatorWidget extends AbstractWidget
{
    public function getName(): string { return 'incident_rotator'; }
    public function getTemplate(): string { return '@App/widgets/incident_rotator.html.twig'; }
    public function getData(): array { /* ... */ }
}
```

See [ADR-022](../adr/0022-dashboard-widgets.md). Drag-drop composition deferred to v0.2 — for now, dashboards are code-defined.

---

## 6. Custom HTTP pagination strategy

The bundled `polysource/adapter-http` ships **page-number** (Stripe-like) and **cursor** (GitHub-like) strategies. For an API that does neither:

```php
final class XPaginationLinkStrategy implements PaginationStrategyInterface
{
    public function buildRequest(DataQuery $query): array { /* return query/headers */ }
    public function parseResponse(ResponseInterface $response): DataPage { /* ... */ }
}
```

Inject your strategy into `HttpDataSource` and you're done. No fork needed.

---

## 7. Persist saved views somewhere else

Default storage = Doctrine. If you want Redis, MongoDB, an HTTP service, anything:

```php
final class RedisSavedViewStorage implements SavedViewStorageInterface
{
    public function find(string $id): ?SavedView { /* ... */ }
    public function save(SavedView $view): void { /* ... */ }
    public function delete(string $id): void { /* ... */ }
    /** @return list<SavedView> */
    public function listVisible(string $resourceName, string $ownerId, ?string $teamId = null): array { /* ... */ }
    public function findDefaultFor(string $resourceName, string $userId, array $roles): ?SavedView { /* ... */ }
}
```

Alias `Polysource\Filter\SavedView\Storage\SavedViewStorageInterface` → your service in DI and the rest of the saved-view pipeline (voter, controller, dropdown) keeps working unchanged.

See [ADR-019](../adr/0019-saved-views-architecture.md).

---

## 8. Resolve teams for shared saved views

If you scope saved views to "team", Polysource needs to know which team the current user belongs to:

```php
final class MyTeamResolver implements SavedViewTeamResolverInterface
{
    public function teamIdFor(UserInterface $user): ?string
    {
        // Anything: user.organisation.id, an LDAP attribute, a JWT claim...
        return $user->getCurrentOrganisation()?->getId();
    }
}
```

When this is unwired, TEAM-scoped saves gracefully fall back to PRIVATE with a flash message — no crash.

---

## 9. Custom filter chip formatter (ADR-016)

When the default chip rendering ("Status: paid, shipped") doesn't fit your domain (e.g., you want "3 statuses selected"), implement:

```php
final class StatusChipFormatter implements ChipFormatterInterface
{
    public function format(FilterCriterion $criterion, AdminContext $context): string
    {
        return \count($criterion->values) > 2
            ? \sprintf('%d statuses selected', \count($criterion->values))
            : implode(', ', $criterion->values);
    }
}
```

Inject by property name. See [ADR-016](../adr/0016-bridge-contracts-shared-with-polysource-filter.md).

---

## 10. Replace the permission backend

Default = Symfony `AuthorizationCheckerInterface`. To use OPA, LDAP groups, a custom voter, anything:

```php
final class OpaPermission implements PermissionInterface
{
    public function isGranted(string $attribute, mixed $subject = null): bool
    {
        return $this->opaClient->evaluate('polysource', $attribute, $subject);
    }
}
```

Alias `Polysource\Core\Permission\PermissionInterface` → your service. Field-level + action-level + resource-level permissions all flow through this single interface.

---

## 11-14. The rest, in one breath

| Interface | Use case |
|---|---|
| `BatchableDataSourceInterface::findMany` | Avoid N+1 when a column resolves a foreign reference across resources |
| `FilterMapperInterface` / `FilterFormatterInterface` / `FilterRendererInterface` | Take over the URL → criteria → Symfony Form pipeline of `polysource/filter` |
| `WorkflowAwareInterface` | Mark a resource as workflow-driven (transitions auto-discovered) |
| `BulkJobStorageInterface` | Persist bulk-async jobs somewhere other than Doctrine |
| `AuditActorInterface` | Customise how the "who" is captured (multi-tenant, impersonation, API tokens) |

---

## Anti-patterns we explicitly resist

We considered — and rejected — adding extension points for:

- **Inline editing** in tables (rejected by ADR-017 — creates ambiguous form state, conflicts with bulk actions).
- **Conditional fields based on form state** (rejected — Symfony Form already covers this; adding a parallel API duplicates the surface).
- **Polymorphic resources** (rejected — adds a coordination tax that 95% of users don't need; subclass the resource instead).
- **Visual / no-code form builders** (rejected — out of scope, see product vision §2).

If you're missing an extension point, **open an issue describing the use case** — we'd rather extend the contracts than push you to fork.

---

## How extensions are registered

Symfony service tags, every time. No magic registries, no XML files, no global state.

| Capability | Tag | Bundle wiring |
|---|---|---|
| Data source | `polysource.data_source` | Auto via `#[AsResource]` |
| Resource | `polysource.resource` | Auto via `#[AsResource]` |
| Plugin metadata | `polysource.plugin` | Auto via `#[AsPlugin]` |
| Search provider | `polysource.search.provider` | Autoconfigure |
| Audit logger | `polysource.audit_logger` | Autoconfigure |
| Widget | `polysource.widgets.dashboard` | Autoconfigure |
| Filter mapper / formatter / renderer | `polysource.filter.mapper`, `.formatter`, `.renderer` | Autoconfigure |
| Filter configurator (EA bridge) | `ea.filter_configurator` | Autoconfigure (EA's own tag) |
| Permission | n/a | Service alias on `PermissionInterface` |

All tags are scanned by `tagged_iterator(...)` in services.php — discoverable by reading `packages/<plugin>/Resources/config/services.php`.

> About `polysource.field_configurator`, `polysource.action`, and `polysource.permission`: these tags are part of the public naming convention. `polysource.action` is autoconfigured on every `ActionInterface` implementation today (`PolysourceExtension::registerForAutoconfiguration`), but no global registry consumes it yet — capability packages use scoped tags (e.g. `polysource.audit.action`, `polysource.bulk_async.action`). `polysource.field_configurator` and `polysource.permission` are reserved names; the corresponding extension points will land in a later v0.x.

---

## Test discipline for your extensions

Polysource itself ships **782 tests / 1932 assertions** in the package matrix (plus 29 Panther browser E2E and 15 adapter real-container tests). The same discipline applies to your extensions:

- **Pure adapters/providers** → unit-test with the in-memory fakes Polysource ships (`InMemoryRedisHashFake`, `InMemoryMeilisearchFake`, `InMemorySavedViewStorage`, etc.). Zero infrastructure.
- **Plugins** → functional-test against `Symfony\Bundle\FrameworkBundle\Test\KernelTestCase`.
- **Browser-driven UX** (Cmd+K, modal Stimulus, Mercure SSE) → Symfony Panther.

Take a look at `packages/<pkg>/tests/` for working patterns — they're meant to be copied.

---

## Where to read next

- [Core architecture](../architecture/target-architecture.md) — full interface signatures, request flow, adapter sketches
- [Plugin architecture (ADR-018)](../adr/0018-admin-plugin-interface-and-public-contracts.md) — formal plugin contract
- [Cookbook — build your own adapter](./cookbook/build-your-own-adapter.md) — full walkthrough with patterns and gotchas
- [Cookbook — adding a custom action](./cookbook/adding-a-custom-action.md) — inline / bulk / global actions
- [Strategy / vision](../strategy/product-vision.md) §5 — the architectural principles behind these extension points (ISP, immutable VOs, no implementation leakage)
