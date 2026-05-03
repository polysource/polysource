# Concept — Resource

A **Resource** is the unit of administration in Polysource. One resource
= one screen in the admin (an index page, a detail page, an action set,
a permission scope). The Messenger failed-messages dashboard is a
resource. A feature-flag dashboard would be another.

## What a resource declares

A class implementing `Polysource\Core\Resource\ResourceInterface`
declares **seven** things:

| Method | Returns | Purpose |
|---|---|---|
| `getName()` | `string` | URL slug (kebab-case recommended), e.g. `failed-messages`. |
| `getLabel()` | `string` | Display label for menus and breadcrumbs. |
| `getIdentifierProperty()` | `string` | The property carrying the unique id in each `DataRecord`. Used to build detail URLs. |
| `getDataSource()` | `DataSourceInterface` | Where the records come from (Messenger transport, S3, Redis, an HTTP API, …). |
| `configureFields($page)` | `iterable<FieldInterface>` | Which columns to show on `'index'`, `'detail'`, `'edit'`, `'new'`. |
| `configureActions()` | `iterable<ActionInterface>` | Inline / bulk / global actions. |
| `configureFilters()` | `iterable<FilterInterface>` | Filters on the index page. |
| `getPermission()` | `?string` | Resource-level permission attribute checked before any access. |

## Use the abstract base class

Most users extend
`Polysource\Core\Resource\AbstractResource` and override only the
methods that differ from the defaults. The base class provides:

- `getDataSource()` returns the constructor-injected source.
- `getIdentifierProperty()` returns `'id'`.
- `configureFields()`, `configureActions()`, `configureFilters()`
  return empty iterables.
- `getPermission()` returns `null`.

A minimal resource therefore looks like:

```php
<?php

namespace App\Polysource;

use Polysource\Core\Resource\AbstractResource;

final class FeatureFlagResource extends AbstractResource
{
    public function getName(): string
    {
        return 'feature-flags';
    }

    public function getLabel(): string
    {
        return 'Feature flags';
    }

    public function getIdentifierProperty(): string
    {
        return 'key';
    }

    public function getPermission(): string
    {
        return 'POLYSOURCE_FEATURE_FLAG';
    }
}
```

## How a resource is registered

Resources are services tagged with `polysource.resource`. The
**recommended** way to apply that tag is the `#[AsResource]` attribute
shipped by `polysource/symfony-bundle`:

```php
use Polysource\Bundle\Attribute\AsResource;
use Polysource\Core\Resource\AbstractResource;

#[AsResource]
final class FeatureFlagResource extends AbstractResource { /* … */ }
```

Combined with `autowire: true`, `autoconfigure: true` in your
`services.yaml`, the bundle picks the class up automatically.

You can also tag manually if you prefer explicit YAML:

```yaml
services:
    App\Polysource\FeatureFlagResource:
        tags: ['polysource.resource']
```

The route loader iterates over every tagged service and generates four
routes per resource (index, detail, action, bulk action) under
`{polysource.url_prefix}/{slug}`.

## What the routes look like

For `getName(): 'feature-flags'` and `url_prefix: /admin`:

```
GET   /admin/feature-flags
GET   /admin/feature-flags/{id}
POST  /admin/feature-flags/batch/{action}
POST  /admin/feature-flags/{id}/{action}
```

Slug normalisation: a slug with non-word characters is converted to
underscores in the Symfony route name (e.g. `my-resource` →
`polysource_my_resource_index`). Two slugs that normalise to the same
key collide at boot time with a `LogicException` — rename one of them.

## Per-page field configuration

`configureFields($page)` is called once per page. The `$page` parameter
is one of `'index'`, `'detail'`, `'edit'`, `'new'`. Branch on it to
show / hide fields. The shorthand methods on `FieldTrait`
(`onlyOnIndex()`, `onlyOnDetail()`, `onlyOnForms()`, `hideOnIndex()`)
already handle the most common cases; you usually only need to read the
`$page` parameter when columns differ structurally between pages.

```php
public function configureFields(string $page): iterable
{
    yield IdField::new('key', 'Flag key');
    yield TextField::new('description');
    yield BooleanField::new('enabled');
    yield CodeField::new('payload')->onlyOnDetail();
    yield DateTimeField::new('created_at')->hideOnIndex();
}
```

See [field.md](./field.md) for how fields actually render.

## Permission

`getPermission()` returns a string attribute checked against
`PermissionInterface` before **any** access to the resource (index,
detail, action). Returning `null` means "no resource-level permission
applies, only per-action checks happen".

In the Messenger adapter, `FailedMessageResource` returns
`'POLYSOURCE_FAILED_MESSAGE'`. Each action additionally returns its own
attribute (`POLYSOURCE_FAILED_MESSAGE_RETRY`, `_DISMISS`, `_PURGE`),
which lets you grant granular access — see
[permission.md](./permission.md) and
[../cookbook/permissions-with-roles.md](../cookbook/permissions-with-roles.md).

## Identifier property

`getIdentifierProperty()` names the **key in `DataRecord::$properties`**
that carries the unique id. The same value is used by
`DataSourceInterface::find()` to look up a record by id. The default
returned by `AbstractResource` is `'id'`.

For the Messenger adapter the underlying transport id is the envelope
id (a string like `12345`), but the resource exposes
`'message_class'` as the user-facing label — the resource is free to
expose **any** property as the identifier as long as `find()` knows how
to resolve it back to a record.

## What a resource is *not*

- It is **not** a database entity. There is no Doctrine mapping. The
  resource is a *declaration*; the actual data lives behind the
  `DataSourceInterface`.
- It is **not** a controller. The bundle ships
  `IndexController`, `DetailController` and `ActionController` that
  read the resource declaration and render the appropriate page. You
  do not write controllers per resource.
- It is **not** a form. Fields are render hints, not Symfony Form
  types. (Form integration arrives in v0.3+ — see the
  [development plan](../../roadmap/development-plan.md).)

## See also

- [data-source.md](./data-source.md) — the read/write contract behind
  every resource.
- [field.md](./field.md) — how columns render.
- [action.md](./action.md) — how buttons run server logic.
- [permission.md](./permission.md) — how access control plugs in.
