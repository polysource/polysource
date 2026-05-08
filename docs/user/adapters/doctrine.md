# `polysource/adapter-doctrine`

Read+write any Doctrine ORM entity through the Polysource admin —
side-by-side with non-Doctrine resources (Messenger failed messages,
S3 files, Redis flags, …).

This adapter exists for the **cohabitation** case ([ADR-012](../../adr/0012-dual-product-positioning.md)):
hosts that already run EasyAdmin on their core Doctrine entities and
want to add a few more entity types into the *same* Polysource admin
panel without touching their EA setup. Polysource does **not**
attempt to compete with EA on full-blown Doctrine CRUD — it just
gives you a uniform admin surface across all your data sources.

## Install

```bash
composer require polysource/adapter-doctrine
```

```php
// config/bundles.php
return [
    // …
    Polysource\Adapter\Doctrine\PolysourceAdapterDoctrineBundle::class => ['all' => true],
];
```

The package itself ships **no** auto-registered resource — you wire
one resource class per entity you want to admin (one entity, one
resource, but you can stack several resources off the same entity if
you need different filters / scopes — e.g. "active products" and
"archived products").

## Wire a resource

```php
use Doctrine\ORM\EntityManagerInterface;
use Polysource\Adapter\Doctrine\DataSource\DoctrineDataSource;
use Polysource\Adapter\Doctrine\Resource\DoctrineEntityResource;
use Polysource\Bundle\Attribute\AsResource;

#[AsResource]
final class ProductResource extends DoctrineEntityResource
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct(
            dataSource: new DoctrineDataSource(
                em: $em,
                entityClass: Product::class,
                allowedFilters: [
                    'name'      => 'name',
                    'sku'       => 'sku',
                    'priceCents'=> 'priceCents',
                    'createdAt' => 'createdAt',
                ],
                searchProperty: 'name',
            ),
            slug: 'products',
            label: 'Products',
            permission: 'POLYSOURCE_PRODUCT_VIEW',
        );
    }

    public function configureFilters(): iterable
    {
        // Use polysource/filter primitives or any FilterInterface impl —
        // see the Filter docs.
        return [];
    }
}
```

That's it. Polysource picks up the resource via `#[AsResource]`,
exposes it at `/admin/products`, and uses every operation that
`WritableDataSourceInterface` advertises (read, create, update,
delete) against your entity — alongside the rest of your Polysource
resources.

## Filter operators supported

The `DoctrineDataSource` understands the standard set out of the box:

| Operator | Maps to |
|---|---|
| `eq` | `field = :value` |
| `neq` | `field != :value` |
| `gt` / `gte` / `lt` / `lte` | comparison |
| `in` | `field IN (:values)` |
| `between` | `field BETWEEN :start AND :end` |
| `like` | `field LIKE %:value%` (server-side wildcard wrapping) |

Unknown filter properties are silently skipped — same convention as
`AuditLogDataSource`. This avoids a typo in your filter form
becoming a free SQL surface on a sensitive column.

## Why a generic class rather than per-entity?

Per-entity classes would multiply boilerplate without adding value —
the `EntityManager` API is uniform. Hosts compose with the
`DoctrineEntityResource` base which carries the entity-specific
identity (FQCN, identifier property, label, permission). If you need
custom write logic (workflow guards, audit hooks beyond ADR-020),
**override** the data source by subclassing.

## See also

- [ADR-012 — Dual product positioning](../../adr/0012-dual-product-positioning.md) — why cohabitation matters.
- [ADR-001 — DataRecord identifier type](../../adr/0001-data-record-identifier.md) — the contract.
- [`docs/user/cookbook/build-your-own-adapter.md`](../cookbook/build-your-own-adapter.md) — when to write your own data source instead.
