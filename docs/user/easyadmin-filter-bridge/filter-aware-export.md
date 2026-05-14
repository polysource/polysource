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
`between`, `like`, `not like`. Anything else is silently dropped
(defensive — no DQL injection).

The string values `"true"` / `"false"` are coerced to PHP booleans
so boolean columns behave as expected; numeric strings stay as
strings (Doctrine handles the SQL cast).

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

Properties that aren't on the entity's Doctrine field map are
silently dropped — the applier validates against
`ClassMetadata::hasField()` before emitting any DQL.

## Going beyond the lean applier — wire a custom EA Action

When you need the full EA filter pipeline (relations, custom
filters, security voters that depend on the filtered slice),
**wire a custom EA Action** on your CrudController and call the
`Exporter` service directly with EA's filter-aware QueryBuilder:

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
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
        // including relation joins, FullTextSearch, etc.
        $qb = $this->createIndexQueryBuilder(
            $context->getSearch(),
            $context->getEntity(),
            $context->getCrud()->getFieldsForPageName(Crud::PAGE_INDEX),
            $context->getCrud()->getFiltersForPageName(Crud::PAGE_INDEX),
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
