# polysource/symfony-bundle

Symfony bundle that wires [polysource/core](../core) contracts into Symfony 7.4
(DI, routing, controllers, Twig view layer).

This package is the entry point for any Symfony application using Polysource Admin.
The pure-PHP `core` package can be used standalone, but most users will install
this bundle to get full HTTP/Twig integration.

## Installation

```bash
composer require polysource/symfony-bundle
```

The bundle auto-registers itself via Symfony Flex.

## Configuration

```yaml
# config/packages/polysource.yaml
polysource:
    url_prefix: /admin   # default
```

## Status

**v0.1 — work in progress.** Public API not yet frozen. See
[ROADMAP](../../ROADMAP.md) Phase 2.

## Architectural decisions

This package implements:

- [ADR-003](../../docs/adr/0003-routing-strategy.md) — physical routes per resource
- [ADR-004](../../docs/adr/0004-admin-context-immutability.md) — `final readonly AdminContext`
- [ADR-005](../../docs/adr/0005-configuration-mechanism.md) — `#[AsResource]` attribute + interface methods

## License

MIT
