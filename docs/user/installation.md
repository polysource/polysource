# Installation

## Requirements

| Requirement | Version | Notes |
|---|---|---|
| PHP | **8.4** | Required by the v0.1 line. PHP 8.0+ support is on the v0.5 roadmap (cf. [ADR-007](../adr/0007-php-symfony-versions.md)). |
| Symfony | **7.4 LTS** | Required by the v0.1 line. Symfony 5.4+ / 6.4+ support is on the v0.5 roadmap. |
| Composer | **2.x** | |

You also need a working Symfony Security firewall protecting the URL
prefix where Polysource will be mounted (default: `/admin`). Polysource
**refuses** to fall back to a permissive default — see
[concepts/permission.md](./concepts/permission.md).

## Packages

Polysource ships as a small monorepo of Composer packages. You install
only the ones you need.

| Package | What it provides | Required? |
|---|---|---|
| `polysource/core` | Pure-PHP contracts and value objects (`DataSourceInterface`, `ResourceInterface`, `DataQuery`, …). Zero Symfony dependency. | yes (transitive) |
| `polysource/symfony-bundle` | Symfony bundle (5.4 / 6.4 / 7.4 LTS): routing, controllers, DI, Twig view layer. | yes |
| `polysource/twig-theme` | Layout + index + detail + paginator + 6 field templates. Pure templates, no PHP. | yes (transitive) |
| `polysource/adapter-messenger` | Read-only data source over Symfony Messenger's failed transport, plus retry / dismiss / retry-all / purge actions. | only for the Messenger dashboard |

> **Heads-up.** The packages are not yet published on Packagist
> (pre-v0.1.0). Until they are, install directly from the Git monorepo
> as a Composer path repository — see [the dev install](#dev-install)
> below.

## Standard install (once published on Packagist)

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

## Dev install (pre-v0.1.0, from this repository) {#dev-install}

While the packages are unpublished, depend on them through a Composer
path repository:

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
