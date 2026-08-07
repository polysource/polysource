# `polysource/filter` — standalone filter primitives

`polysource/filter` is a Symfony bundle that gives any host application a
**self-contained, EasyAdmin-agnostic** building block for filter UIs:
declarative filter definitions, immutable criterion model, session
persistence, a chips bar, and two ready-to-use rendering modes
(integrated form, side subpanel).

It is the foundation that the `polysource/easyadmin-filter-bridge`
plugs into. **Both packages can be installed in the same app** —
`polysource/filter` does not depend on EasyAdmin and is happy to power
controllers in plain Symfony, in a Polysource standalone admin, or
anywhere else you need a filter form that survives a page reload.

## What's in this folder

| File | What's in it |
|---|---|
| [getting-started.md](./getting-started.md) | Install, declare a filter collection, render it in a Twig template, persist across requests. End-to-end runnable snippets. |
| [saved-views.md](./saved-views.md) | Render the saved-views dropdown (private / team / public scopes), wire the apply / save / delete routes, customise team resolution, generate shareable filter permalinks via `FilterService::buildUrl()`. |
| [column-preferences.md](./column-preferences.md) | Per-user column visibility, persisted by `(user, resource)`. Since v0.3.0. |
| [bulk-action-history.md](./bulk-action-history.md) | Append-only log of bulk actions — who ran what, on how many rows, when. Since v0.5.0. |
| [recent-records.md](./recent-records.md) | Per-user "recently viewed records" log. Since v0.5.0. |

## Status

**Shipped — v1.1.0 (2026-08-07).** Public API frozen since v1.0.0
under strict SemVer. The contracts are the ones documented in
[ADR-013](../../adr/0013-filter-package-architecture.md) — the
form/datasource separation, the 3-tag pipeline (`mapper` /
`formatter` / `renderer`), the `FilterService` session contract, and
the two rendering modes (`integrated` default, `subpanel` opt-in).

## See also

- [ADR-013](../../adr/0013-filter-package-architecture.md) — why the
  package exists and what the contracts are.
- [ADR-014](../../adr/0014-datasource-lifecycle-deferred.md) — the
  Factory→Builder→Loader datasource lifecycle. Still a blueprint: as
  of v1.1.0 no such lifecycle ships, each adapter keeps its three
  phases internal, and the ADR's activation conditions (a real
  multi-source composition case) have not been met.
- [Bridge what's-new](../easyadmin-filter-bridge/whats-new.md) — what
  the bridge layers on top when used inside an existing EasyAdmin
  app (4.24+ or 5.0+).
