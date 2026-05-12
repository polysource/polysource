# Installation

## Requirements

Polysource ships with a **per-package compatibility baseline** (cf.
[ADR-015](../adr/0015-multi-version-compatibility-baseline.md)). The
floor differs between primitive packages (`filter`, `easyadmin-filter-bridge`)
and bundle/adapter packages that use Symfony 6.2+ APIs.

| Package | PHP | Symfony | Notes |
|---|---|---|---|
| `polysource/core` | `>=8.1` | none | Pure PHP, zero Symfony dependency. |
| `polysource/filter` | `>=8.1` | `^5.4 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0` | Standalone-usable. |
| `polysource/easyadmin-filter-bridge` | `>=8.1` | `^5.4 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0` | Plus EasyAdmin `^4.24 \|\| ^5.0`. |
| `polysource/twig-theme` | `>=8.1` | n/a (templates) | Pure Twig, no PHP. |
| `polysource/symfony-bundle` | `>=8.1` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Uses Sf 6.2+ APIs (`ValueResolverInterface`, renamed `SecurityBundle\Security`). |
| `polysource/adapter-messenger` | `>=8.1` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Same baseline as `symfony-bundle`. |
| `polysource/adapter-{doctrine,redis,flysystem,http,meilisearch}` | `>=8.1` | `^5.4 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0` | Plus the adapter's own backend constraint. |
| `polysource/{audit,bulk-async,widgets,search,workflow-bridge}` | `>=8.1` | `^5.4 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0` | Capability plugins, opt-in. |
| Composer | **2.x** | — | — |

You also need a working Symfony Security firewall protecting the URL
prefix where Polysource will be mounted (default: `/admin`). Polysource
**refuses** to fall back to a permissive default — see
[concepts/permission.md](./concepts/permission.md).

### JavaScript / Stimulus prerequisite

Several Polysource packages (`polysource/filter`,
`polysource/easyadmin-filter-bridge`, `polysource/bulk-async`,
`polysource/search`) ship Stimulus controllers that power interactive
UI features:

| Package | Controller(s) | What needs Stimulus |
|---|---|---|
| `polysource/filter` | `polysource--filter-modal-layout`, `polysource--filter-chips`, `polysource--filter-subpanel` | tab / group filter layout, chip × close button, subpanel mode |
| `polysource/easyadmin-filter-bridge` | `polysource--filter` | numeric `quick_ranges` buttons, datetime `presets` buttons, `show_clear` button |
| `polysource/bulk-async` | `polysource--bulk-async--progress` | live progress bar for async bulk actions |
| `polysource/search` | `polysource--search--cmdk` | command-palette overlay |

Server-side features (filter rendering, chips bar markup, session
persistence, custom filter types, chip text formatting, page rendering,
async dispatch, search backends) **do NOT require Stimulus** and work
on any host.

Auto-discovery via `extra.symfony.controllers` (the standard Symfony UX
manifest mechanism) is **scheduled for v0.2** — for v0.1.x, hosts
register the controllers manually in their Stimulus application. See
each package's getting-started guide for the snippet.

If your host has no Stimulus pipeline at all (classic Webpack Encore
with manual `.addEntry()`, plain jQuery / vanilla JS frontend), the
listed interactive features render as inert UI elements (buttons in
the DOM that don't react to clicks, etc.). The recommended path is
to either (a) install `@symfony/stimulus-bundle` and register the
controllers, or (b) opt out of the JS-driven options in each
controller / configuration — the bridge and filter primitives degrade
to a usable read-only / submit-by-form-button surface.

## Packages

Polysource ships as a small monorepo of Composer packages. You install
only the ones you need.

| Package | What it provides | Required? |
|---|---|---|
| `polysource/core` | Pure-PHP contracts and value objects (`DataSourceInterface`, `ResourceInterface`, `DataQuery`, …). Zero Symfony dependency. | yes (transitive) |
| `polysource/symfony-bundle` | Symfony bundle (Symfony `^6.4 \|\| ^7.0 \|\| ^8.0`): routing, controllers, DI, Twig view layer. | yes |
| `polysource/twig-theme` | Layout + index + detail + paginator + 6 field templates. Pure templates, no PHP. | yes (transitive) |
| `polysource/adapter-messenger` | Read-only data source over Symfony Messenger's failed transport, plus retry / dismiss / retry-all / purge actions. | only for the Messenger dashboard |

> **Two install paths.** The standard install pulls the packages from
> Packagist (recommended for any consumer). The dev install at the
> bottom of this page wires the packages from a local clone of the
> monorepo as a Composer path repository — useful only if you're
> contributing to Polysource itself or testing an unreleased branch.

## Standard install (from Packagist)

```bash
composer require polysource/symfony-bundle
```

The bundle auto-registers via Symfony Flex. If you don't use Flex,
add it manually to `config/bundles.php`:

```php
return [
    // …
    Polysource\Bundle\PolysourceBundle::class => ['all' => true],
];
```

Then mount the route loader by creating
`config/routes/polysource.yaml`:

```yaml
polysource:
    resource: .
    type: polysource
```

Optionally tune the bundle in
`config/packages/polysource.yaml`:

```yaml
polysource:
    url_prefix: /admin     # default
    max_page_size: 200     # default — caps the `pageSize` query parameter
    max_bulk_ids: 500      # default — caps the number of IDs in a bulk action
```

For the Messenger dashboard:

```bash
composer require polysource/adapter-messenger
```

```yaml
# config/packages/polysource_messenger.yaml
polysource_messenger:
    failed_transport_name: failed   # the transport name in framework.messenger
    resource_slug: failed-messages  # the URL slug under {url_prefix}
```

Continue with [getting-started.md](./getting-started.md) for the
5-minute path to a working dashboard.

## Dev install (from this repository) {#dev-install}

If you're contributing to Polysource itself or testing an unreleased
branch, depend on the packages through a Composer path repository
instead of Packagist:

```bash
git clone git@github.com:polysource/polysource.git ../polysource
```

In your application's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../polysource/packages/*"
        }
    ],
    "require": {
        "polysource/symfony-bundle": "0.1.x-dev",
        "polysource/adapter-messenger": "0.1.x-dev"
    },
    "minimum-stability": "dev",
    "prefer-stable": true
}
```

```bash
composer update polysource/*
```

Symfony Flex auto-registration does not run for path repositories —
register the bundles manually in `config/bundles.php`:

```php
return [
    // …
    Polysource\Bundle\PolysourceBundle::class => ['all' => true],
    Polysource\Adapter\Messenger\PolysourceMessengerBundle::class => ['all' => true],
];
```

## Verifying the install

After registering the bundle and mounting the routes, check that
Polysource sees them:

```bash
bin/console debug:router | grep polysource
```

You should see at least four routes per resource:

```
polysource_<slug>_index           GET     /admin/<slug>
polysource_<slug>_detail          GET     /admin/<slug>/{id}
polysource_<slug>_bulk_action     POST    /admin/<slug>/batch/{action}
polysource_<slug>_action          POST    /admin/<slug>/{id}/{action}
```

If no routes appear, you likely forgot to import the
`polysource:` route loader — re-check `config/routes/polysource.yaml`.

## Troubleshooting

### `LogicException: PolysourceBundle could not find a Symfony Security firewall.`

Polysource refuses to ship a permissive default. Configure a firewall
covering your `polysource.url_prefix` (default `/admin`) in
`config/packages/security.yaml`, or alias
`Polysource\Core\Permission\PermissionInterface` to a custom
implementation. See [concepts/permission.md](./concepts/permission.md).

### `LogicException: Polysource route key collision`

Two resources have slugs that normalise to the same Symfony route key
(`my-resource` and `my_resource` both → `my_resource`). Rename one of
them via `ResourceInterface::getName()` or via the adapter's
`resource_slug` config knob.

### `LogicException: Polysource Messenger adapter requires a ListableReceiverInterface`

The adapter only works with transports that expose a list of failed
envelopes (Doctrine, Redis, AMQP, InMemory). SQS and Beanstalk do not.
See [adapters/messenger.md](./adapters/messenger.md#supported-transports).

## Next step

[getting-started.md](./getting-started.md) — five minutes to a working
Messenger failed-messages dashboard.
