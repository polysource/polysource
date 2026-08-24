# Installation

## Requirements

Since v1.0.0 every package shares one floor — **PHP 8.2+ and Symfony
6.4 LTS+** (cf. [ADR-011](../adr/0011-pre-v1.0-freeze-checklist.md)
and [ADR-015](../adr/0015-multi-version-compatibility-baseline.md)).

Since v1.2.0 the ceiling is tested too: **PHP 8.5 and Symfony 8.1**
(with EasyAdmin 5) run the full suite in CI, and a dedicated smoke job
installs the bridge from Packagist onto a vanilla Symfony 8.1 skeleton
on every push. Nothing about the floor moved, so upgrading to v1.2.0
on an older stack is a no-op. The full picture of what is advertised
versus what is gated lives in the
[Symfony compatibility audit](../maintainers/symfony-compat-audit.md).

| Package | PHP | Symfony | Notes |
|---|---|---|---|
| `polysource/core` | `>=8.2` | none | Pure PHP, zero Symfony dependency. |
| `polysource/filter` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Standalone-usable. |
| `polysource/easyadmin-filter-bridge` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Plus EasyAdmin `^4.24 \|\| ^5.0`. |
| `polysource/twig-theme` | `>=8.2` | n/a (templates) | Pure Twig, no PHP. |
| `polysource/symfony-bundle` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Uses Sf 6.2+ APIs (`ValueResolverInterface`, renamed `SecurityBundle\Security`). |
| `polysource/adapter-messenger` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Same baseline as `symfony-bundle`. |
| `polysource/adapter-{doctrine,redis,flysystem,http,meilisearch}` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Plus the adapter's own backend constraint. |
| `polysource/{audit,bulk-async,widgets,search,workflow-bridge}` | `>=8.2` | `^6.4 \|\| ^7.0 \|\| ^8.0` | Capability plugins, opt-in. |
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
| `polysource/filter` | `polysource--filter-chips` | in-place chip removal + "+N more" overflow (the chips bar itself and its × links are server-rendered) |
| `polysource/filter` | `polysource--row-details` (lazy) | expands a row's detail panel in place; without it the chevron is a plain link to the standalone detail page |
| `polysource/easyadmin-filter-bridge` | `polysource--filter` | value-only data attrs (`step`, `min_length`, `include_null`, …) for host-side JS layers to read — no behaviour of its own since v0.2.0 |
| `polysource/bulk-async` | `polysource--bulk-async--progress` | live progress bar for async bulk actions |
| `polysource/search` | `polysource--search--cmdk` | command-palette overlay |

Server-side features (filter rendering, chips bar markup, session
persistence, custom filter types, chip text formatting, page rendering,
async dispatch, search backends) **do NOT require Stimulus** and work
on any host.

#### Auto-discovery — already wired

Every Polysource package that ships Stimulus controllers declares
them via the canonical Symfony UX manifest in `assets/package.json`
(`symfony.controllers`), with an explicit `name` override that
preserves the short identifier used by templates
(`polysource--filter`, `polysource--filter-chips`, etc.).

- **Webpack Encore + `@symfony/stimulus-bridge`**: auto-discovery
  works out-of-the-box once you `composer require` a Polysource
  package — the bridge reads `assets/package.json` and registers
  each controller under its `name`.
- **AssetMapper + `@symfony/stimulus-bundle`**: auto-discovery works
  too — the bundle reads the same `assets/package.json` manifest
  (cf. `ControllersMapGenerator::loadUxControllers()`) and honors
  the `name` field. The only manual step is adding the package to
  your host's `assets/controllers.json`:

  ```json
  {
      "controllers": {
          "@polysource/filter": {
              "polysource--filter-chips": { "enabled": true },
              "polysource--row-details": { "enabled": true }
          },
          "@polysource/easyadmin-filter-bridge": {
              "polysource--filter": { "enabled": true }
          }
      }
  }
  ```

  A future Symfony Flex recipe (tracked in `symfony/recipes-contrib`)
  will populate these entries automatically on `composer require` —
  until then, the snippet above is one-time manual work.

#### Hosts without any Stimulus pipeline

If your app has no Stimulus pipeline (classic Webpack Encore with
manual `.addEntry()`, plain jQuery / vanilla JS frontend), the
listed interactive features render as inert UI elements (buttons
in the DOM that don't react to clicks, etc.). The recommended path
is to either (a) install `@hotwired/stimulus` + one of the discovery
bundles above, or (b) opt out of the JS-driven options in each
controller / configuration — the bridge and filter primitives
degrade to a usable read-only / submit-by-form-button surface.

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

> **⚠ Always use `composer require`** — do **not** add Polysource
> packages by editing `composer.json` manually. The two operations
> look equivalent but aren't: `composer require` atomically updates
> `composer.json` + `composer.lock` + runs Flex post-install. A
> manual edit leaves the lock out of sync, which makes any
> downstream `composer install --no-scripts` (typical in
> Makefile-driven `make build` setups) fail with:
>
> ```
> - Required package "polysource/symfony-bundle" is not present in the lock file.
> ```
>
> Same applies to `composer remove` over manual deletions.

> **⚠ Multi-kernel hosts** (per-app `apps/*/config/bundles.php`
> alongside the root `config/bundles.php`): Symfony Flex
> registers the bundle in the **root** `config/bundles.php`,
> which loads it on every kernel. If you only want Polysource on
> one kernel (typically `backend`), move the entry from
> `config/bundles.php` to `apps/backend/config/bundles.php`
> after running `composer require`. Same applies to
> `polysource/easyadmin-filter-bridge` which only makes sense
> alongside EasyAdmin.

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
        "polysource/symfony-bundle": "1.3.x-dev",
        "polysource/adapter-messenger": "1.3.x-dev"
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

You should see five routes per resource:

```
polysource_<slug>_index           GET     /admin/<slug>
polysource_<slug>_detail          GET     /admin/<slug>/{id}
polysource_<slug>_detail_panel    GET     /admin/<slug>/{id}/detail-panel
polysource_<slug>_bulk_action     POST    /admin/<slug>/batch/{action}
polysource_<slug>_action          POST    /admin/<slug>/{id}/{action}
```

If no routes appear, you likely forgot to import the
`polysource:` route loader — re-check `config/routes/polysource.yaml`.

### `polysource:doctor` — self-diagnostic command (since v0.6.0)

For a one-command health check that runs the most common
install-time checks, use:

```bash
bin/console polysource:doctor
```

Output:

```
Polysource Doctor

 ----------------------- ----------- -------------------------------------------
  Check                   Status      Detail
 ----------------------- ----------- -------------------------------------------
  PHP version             ✓ PASS     8.4.20 (>= 8.2)
  Polysource bundles      ✓ PASS     3 registered (PolysourceBundle, …)
  EA bridge co-load       ✓ PASS     EasyAdminBundle is loaded alongside the bridge.
  Polysource plugins      ✓ PASS     7 discovered (polysource/core 1.1.0, …)
  Doctrine schema         ✓ PASS     5 Polysource entities in sync with the database.
 ----------------------- ----------- -------------------------------------------

 [OK] All checks passed.
```

Exit code: 0 if all checks PASS or WARN, 1 if any FAIL. Suitable
for CI / pre-deploy gates.

The checks mirror the frictions surfaced during v0.5.7 dogfooding:
- C1 (bridge bundle loaded on a kernel without EasyAdmin → WARN)
- C3 (Doctrine schema out of sync → FAIL with `migrations:diff` hint)

If a check fails, the remediation hint is in the "Detail" column.

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
