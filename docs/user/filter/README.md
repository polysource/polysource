# `polysource/filter` — standalone filter primitives

`polysource/filter` is a Symfony bundle that gives any host application a
**self-contained, EasyAdmin-agnostic** building block for filter UIs:
declarative filter definitions, immutable criterion model, session
persistence, a chips bar, and two ready-to-use rendering modes
(integrated form, side subpanel).

It is the foundation that the `polysource/easyadmin-filter-bridge`
plugs into. **Both packages can be installed in the same app** —
`polysource/filter` does not depend on EasyAdmin and is happy to power
controllers in plain Symfony, in `polysource/admin`, or anywhere else
you need a filter form that survives a page reload.

## What's in this folder

| File | What's in it |
|---|---|
| [getting-started.md](./getting-started.md) | Install, declare a filter collection, render it in a Twig template, persist across requests. End-to-end runnable snippets. |

## Status

`polysource/filter` is **pre-v0.1.0** and shipped from the same monorepo
as the bridge. The public API is the one documented in
[ADR-013](../../adr/0013-filter-package-architecture.md) — the form/datasource
separation, the 3-tag pipeline (`mapper` / `formatter` / `renderer`),
the `FilterService` session contract, and the two rendering modes
(`integrated` default, `subpanel` opt-in).

## See also

- [ADR-013](../../adr/0013-filter-package-architecture.md) — why the
  package exists, what the contracts are, what's deferred to v0.2+.
- [ADR-014](../../adr/0014-datasource-lifecycle-deferred.md) — the
  Factory→Builder→Loader datasource lifecycle that the bridge uses
  internally and that hosts will be able to consume directly in v0.2+.
- [Bridge what's-new](../easyadmin-filter-bridge/whats-new.md) — what
  the bridge layers on top when used inside an EasyAdmin v5 app.
