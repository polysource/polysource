# Plugin

> Foundation concept introduced in v0.1.0 per
> [ADR-018](../../adr/0018-admin-plugin-interface-and-public-contracts.md).

A **Polysource plugin** is a Symfony bundle that contributes admin
functionality (resources, widgets, bulk actions, search providers, …)
through the standard Symfony DI tag system, with optional metadata
(`name`, `version`) discoverable at runtime via `PluginRegistry`.

The vocabulary distinction matters:

- A **Symfony bundle** is the framework-level unit that registers
  services + config in a kernel.
- A **Polysource plugin** is a Symfony bundle that **also** declares
  `#[AsPlugin]` so it shows up in `polysource:plugins:list`.

Every `polysource/*` package is itself a plugin — except
`polysource/core`, which is a pure-PHP library, and
`polysource/twig-theme`, which is template-only.

## How tag-based extension works

Capability extension is the **primary** contract. Each capability
ships an interface + a service tag + a registry:

| Capability | Tag | Interface |
|---|---|---|
| Resources | `polysource.resource` (auto via `#[AsResource]`) | `ResourceInterface` |
| Data sources | `polysource.data_source` | `DataSourceInterface` |
| Actions (per-Resource) | `polysource.action` | `ActionInterface` |
| Plugins | `polysource.plugin` (auto via `#[AsPlugin]`) | `AdminPluginInterface` |
| Filter pipeline — mapper | `polysource.filter.mapper` | `FilterMapperInterface` |
| Filter pipeline — formatter | `polysource.filter.formatter` | `FilterFormatterInterface` |
| Filter pipeline — renderer | `polysource.filter.renderer` | `FilterRendererInterface` |
| Filter chip formatter | `polysource.chip_formatter` | `ChipFormatterInterface` |
| Search providers | `polysource.search.provider` | `SearchProviderInterface` |
| Audit log fan-out | `polysource.audit_logger` | `AuditLoggerInterface` |
| Audit subscribers | `polysource.audit.action` | `EventSubscriberInterface` |
| Bulk-async dispatchers | `polysource.bulk_async.action` | `BulkActionInterface` |
| Dashboard widgets | `polysource.widgets.dashboard` | `WidgetInterface` |
| Messenger actions | `polysource.messenger.action` | `ActionInterface` |
| Row details (EA bridge) | `polysource.row_detail_provider` | `RowDetailProviderInterface` |

A plugin is **anything that registers tagged services in any of those
slots**. The `AdminPluginInterface` itself is just a metadata layer on
top of that.

## How to write a plugin — minimal example

The minimal "hello, polysource" plugin is **3 files**.

### 1. `composer.json`

```json
{
    "name": "acme/polysource-hello",
    "type": "symfony-bundle",
    "description": "Trivial Polysource plugin showing the contract.",
    "require": {
        "php": ">=8.2",
        "polysource/core": "^1.0",
        "symfony/http-kernel": "^6.4 || ^7.0 || ^8.0"
    },
    "autoload": {
        "psr-4": { "Acme\\PolysourceHello\\": "src/" }
    }
}
```

### 2. `src/AcmePolysourceHelloBundle.php`

```php
<?php

namespace Acme\PolysourceHello;

use Polysource\Core\Plugin\AdminPluginInterface;
use Polysource\Core\Plugin\Attribute\AsPlugin;
use Polysource\Core\Plugin\HasPluginMetadata;
use Symfony\Component\HttpKernel\Bundle\Bundle;

#[AsPlugin(name: 'acme/polysource-hello', version: '0.1.0')]
final class AcmePolysourceHelloBundle extends Bundle implements AdminPluginInterface
{
    use HasPluginMetadata;
}
```

That's it for the plugin metadata side. The `#[AsPlugin]` attribute +
`HasPluginMetadata` trait + interface marker is the canonical shape.
Method bodies are 0 lines — the trait reads the attribute via
reflection.

### 3. (Optional) Register the bundle in your host app

If your host doesn't use Symfony Flex's auto-discovery:

```php
// config/bundles.php
return [
    // …
    Acme\PolysourceHello\AcmePolysourceHelloBundle::class => ['all' => true],
];
```

## Verifying it's discovered

Run:

```bash
bin/console polysource:plugins:list
```

The output lists every bundle annotated with `#[AsPlugin]`:

```
 Polysource plugins
 ==================

 ----------------------------------- ----------------
  Name                                Version
 ----------------------------------- ----------------
  acme/polysource-hello               0.1.0
  polysource/symfony-bundle           1.1.0
  polysource/adapter-messenger        1.1.0
  polysource/filter                   1.1.0
  polysource/easyadmin-filter-bridge  1.1.0
 ----------------------------------- ----------------
```

If your bundle doesn't appear, double-check:

- Is the bundle registered in `config/bundles.php` (or auto-discovered
  via Flex)?
- Does the class have `#[AsPlugin(name: …, version: …)]`?
- Does it `implement AdminPluginInterface` AND `use HasPluginMetadata`?

## Contributing capabilities

Once your plugin is discoverable, the actual admin functionality is
contributed via tagged services:

```yaml
# config/services.yaml in your plugin
services:
    Acme\PolysourceHello\Widget\GreetingWidget:
        tags:
            - { name: polysource.widgets.dashboard, position: 'top-left' }

    Acme\PolysourceHello\BulkAction\GreetEveryone:
        tags:
            - polysource.bulk_async.action
```

The `polysource.*` tag is what wires the service into the matching
Polysource registry. `#[AsPlugin]` is for metadata (debugging,
versioning, support); the tags are for behaviour.

## Versioning + public API

Per [ADR-018 §6](../../adr/0018-admin-plugin-interface-and-public-contracts.md#6-versioning-des-contrats-publics):

- The `Polysource\Core\Plugin\*` namespace has been **stable since
  v1.0.0** (2026-08-06), under strict SemVer. Adding a method to
  `AdminPluginInterface` is now a breaking change → MAJOR bump.
- Plugins pin `polysource/core: ^1.0` and pick up minors without
  touching their code.
- Each public interface carries a `@since` PHPDoc tag indicating the
  version it was added in.

## What this concept is **not**

- Not a runtime toggle. A plugin loaded by the kernel is active until
  uninstalled via Composer / `bundles.php`. There is no "disable
  plugin X for this request" mechanism, and none is planned.
- Not a sandboxing boundary. Plugins share the same container, can
  read each other's services, can override each other's templates. The
  kernel does not enforce isolation.
- Not a dependency manager. Plugins declare their requirements via
  `composer.json` like any Symfony bundle. There is no bespoke plugin
  dependency graph.

## See also

- [ADR-017 — Cherry-picking from Filament study](../../adr/0017-cherry-picking-from-filament-study.md) (why we add a plugin layer).
- [ADR-018 — `AdminPluginInterface` + public contracts](../../adr/0018-admin-plugin-interface-and-public-contracts.md) (the full design rationale).
- [`docs/user/concepts/resource.md`](./resource.md) — the most common capability a plugin contributes.
