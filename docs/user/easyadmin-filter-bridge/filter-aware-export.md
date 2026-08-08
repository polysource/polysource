# Filter-aware export + bulk dry-run count

> Since `polysource/easyadmin-filter-bridge` v0.5.0.

Both v0.3.0's CSV/XLSX export and v0.4.0's bulk-scope dry-run
helper now honour the current `?filters[...]` URL slice — so an
"Export" button on a filtered listing exports only the matching
rows, and the bulk dry-run preview returns the count + samples
of rows the bulk action would actually touch.

This page documents the two endpoints, their security contract,
the filter shapes the shared `UrlFilterApplier` understands, and
the coverage limitations vs. EA's full QueryBuilder pipeline.

## Endpoints

| Method + Path                                          | Name                       | Returns                                 |
| ------------------------------------------------------ | -------------------------- | --------------------------------------- |
| `GET /admin/polysource/export/{resource}.{format}`     | `polysource_export`        | Streamed `csv` or `xlsx`                |
| `GET /admin/polysource/matching-count/{resource}`      | `polysource_matching_count` | JSON `{count: int, samples: list}`     |

`{resource}` is the entity FQCN with backslashes (`App\Entity\Order` →
`App\\Entity\\Order` once URL-encoded). Both endpoints read the
same `?filters[...]` slice EA's index page emits.

## Filter shapes honoured

The `UrlFilterApplier` service translates the URL slice into
Doctrine `WHERE` clauses on the QueryBuilder. Supported shapes:

```
# Scalar equality
?filters[status]=paid

# Expanded shape with comparison operator
?filters[status][value]=paid&filters[status][comparison]==
?filters[totalCents][value]=100&filters[totalCents][comparison]=>=
?filters[name][value]=alice&filters[name][comparison]=like

# Between (low/high)
?filters[totalCents][value]=100&filters[totalCents][value2]=500&filters[totalCents][comparison]=between

# List-style multi-select → IN (...)
?filters[country][]=FR&filters[country][]=DE
```

Supported comparison operators: `=`, `!=`, `<`, `<=`, `>`, `>=`,
`between`, `like`, `not like`, `in`, `not in`, `is null`,
`is not null` (all case-insensitive). Anything else **fails the
export with `422 Unprocessable Entity`**: since the URL carried a
filtering intent the endpoint cannot honour, exporting anyway would
silently return a broader dataset than the user's filtered view — a
data leak. (No DQL injection either way: nothing unsupported ever
reaches the QueryBuilder.)

The string values `"true"` / `"false"` are coerced to PHP booleans
so boolean columns behave as expected; numeric strings stay as
strings (Doctrine handles the SQL cast).

### Column value formatting

The CSV/XLSX writer (`Exporter`) coerces each cell value to text via:

| Type | Output |
|---|---|
| `null` | empty string |
| `bool` | `"1"` / `"0"` |
| scalar (string, int, float) | direct cast |
| `Stringable` | `__toString()` |
| `DateTimeInterface` | ISO 8601 / RFC 3339 (e.g. `2026-03-31T08:52:54+02:00`) — universal, sortable as text, opens correctly in Excel and parses cleanly with every modern date library |
| `BackedEnum` | the case's backing value |
| `UnitEnum` | the case's name |
| `array` | JSON-encoded with `JSON_UNESCAPED_UNICODE \| JSON_UNESCAPED_SLASHES` |
| other | empty string (defensive) |

Hosts wanting different per-column formatting (e.g. localised
date format, money formatting) build their own iterable upstream
of `Exporter::streamCsv()` — see "Going beyond the lean applier"
below.

## Coverage limitations

The `UrlFilterApplier` is a **lean parser**, not a full
re-implementation of EA's filter pipeline. It covers ~90% of the
common cases without taking a dependency on EA internals. It
does **NOT** support:

- **Relation joins** — filtering by `customer.country` requires a
  JOIN the applier doesn't emit.
- **`FullTextSearch` / `NotNull` / custom filters** from the
  bridge's own `polysource/easyadmin-filter-bridge` filter set —
  these need EA's QueryBuilder.

Properties that aren't on the entity's Doctrine field map cannot be
translated by the lean applier (it validates against
`ClassMetadata::hasField()` before emitting any DQL). On the generic
export endpoint this is **fail-closed**: any URL filter that carries
a real intent but cannot be applied — including the bridge's own
`FullTextSearch`/`NotNull` on virtual properties and association
fields — rejects the request with `422` *before* streaming starts,
instead of exporting a broader dataset than the on-screen selection.
Use the custom EA Action below when you need those filters honoured.

## Going beyond the lean applier — wire a custom EA Action

When you need the full EA filter pipeline (relations, custom
filters, security voters that depend on the filtered slice),
**wire a custom EA Action** on your CrudController and call the
`Exporter` service directly with EA's filter-aware QueryBuilder:

```php
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Factory\FilterFactory;
use Polysource\EasyAdminFilterBridge\Export\Exporter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;

#[AsController]
final class OrderCrudController extends AbstractCrudController
{
    public function __construct(private readonly Exporter $exporter)
    {
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->add(
            Crud::PAGE_INDEX,
            Action::new('exportFiltered', 'Export filtered')
                ->linkToCrudAction('exportFiltered'),
        );
    }

    public function exportFiltered(AdminContext $context): StreamedResponse
    {
        // EA hands us the QueryBuilder with EVERY filter applied —
        // including relation joins, FullTextSearch, etc. Fields and
        // filters are built the same way EA's own index() does
        // (EA 4.24 → 5.x — the old CrudDto::getFieldsForPageName()
        // helper no longer exists in EA 5.5):
        $fields = new FieldCollection($this->configureFields(Crud::PAGE_INDEX));
        $filters = $this->container->get(FilterFactory::class)->create(
            $context->getCrud()->getFiltersConfig(),
            $fields,
            $context->getEntity(),
        );
        $qb = $this->createIndexQueryBuilder(
            $context->getSearch(),
            $context->getEntity(),
            $fields,
            $filters,
        );

        $query = $qb->getQuery();
        $query->setHydrationMode(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        $rows = (function () use ($query): \Generator {
            foreach ($query->toIterable() as $row) {
                yield $row;
            }
        })();

        return $this->exporter->streamCsv($rows, ['id', 'reference', 'totalCents', 'status']);
    }
}
```

This path uses EA's own `createIndexQueryBuilder()` — so every
filter (including the ones the lean applier doesn't understand)
is honoured.

## Security

Both endpoints are routed under `/admin/...` — the **host MUST
gate them behind their EA firewall and voters**. The controllers
do not perform their own access checks; they trust the host's
Symfony security config to deny anonymous or unauthorised
requests upstream.

The same goes for export volume: hosts who want a hard ceiling
on rows-per-export wire a voter that inspects
`Request::query` and refuses unfiltered exports above a
threshold.

## Bulk dry-run wiring

The `polysource_bulk_dry_run_url(actionUrl)` Twig helper (shipped
in v0.4.0) returns the action URL with `?dry_run=1` appended. By
default the helper assumes you build the dry-run response
yourself. To leverage the v0.5.0 `polysource_matching_count`
endpoint, point the dry-run link at it instead — typically by
calling it from your bulk-action submit handler before the real
action runs:

```twig
<a class="btn"
   href="{{ path('polysource_matching_count', {
       resource: 'App\\\\Entity\\\\Order',
   }) }}{{ app.request.queryString ? '?' ~ app.request.queryString : '' }}">
    Preview affected rows
</a>
```

The response shape:

```json
{
    "count": 1234,
    "samples": [
        { "id": 1, "reference": "ORD-001", "status": "paid", ... },
        ...
    ]
}
```

`samples` defaults to 5 rows; override via `?samples=N` (capped
at 50). Use it to render a confirmation modal with the count + a
short preview list before the destructive bulk action runs.
