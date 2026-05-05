# Polysource — EasyAdmin v4 filter bridge demo

A runnable Symfony 6.4 LTS application running EasyAdmin **v4.29**
with [`polysource/easyadmin-filter-bridge`](../../packages/easyadmin-filter-bridge)
installed. Pinned on the **floor** of the multi-version baseline (cf.
[ADR-015](../../docs/adr/0015-multi-version-compatibility-baseline.md)):

- **PHP 8.1**
- **Symfony 6.4 LTS**
- **EasyAdmin 4.29**
- **Doctrine ORM 2.20+ \|\| 3.6+**

This is the proof that the bridge works on EA v4 hosts without
forking, without porting controllers, without upgrading EA. Anyone
running a stack like this in production can install the bridge and
get presets / quick-ranges / chips bar / multi-select / between-date
filters — for free.

## Run it

```bash
cd examples/easyadmin-bridge-demo-v4
make install
make seed
make up
# open http://localhost:8083/admin/   (login: admin / admin)
```

## What's identical to the v5 demo

- `composer require polysource/easyadmin-filter-bridge` — same package
- `Polysource::filter()` / `Polysource::field()` proxies — same API
- `chipFormatter()` slot accepting `callable | ChipFormatterInterface`
  — same contract (cf. ADR-016)
- The 4 custom filters (`BetweenDateFilter`, `InFilter`,
  `NotNullFilter`, `FullTextSearchFilter`) — same classes
- The 8 enhancers (presets, quick-ranges, multi-select, …) — same
  options
- Chips bar rendering above the index — same Twig template path
  (`@EasyAdmin/crud/index.html.twig`, the bridge splices itself into
  the `@EasyAdmin` Twig namespace at higher priority on both EA majors)

## What this demo had to adapt for EA v4

- `DashboardController` — `#[AdminDashboard(routePath, routeName)]`
  is EA 5+. EA 4 uses Symfony's `#[Route('/admin', name: 'admin')]`
  attribute on the dashboard's `index()` method.
- `MenuItem::linkToCrud(label, icon, EntityFqcn::class)` — EA 4
  signature. EA 5+ added `MenuItem::linkTo(CrudControllerFqcn)`.
- `Doctrine ORM` config — removed `enable_native_lazy_objects: true`
  which requires PHP 8.4. Doctrine falls back to its proxy-based
  lazy loading on PHP 8.1.
- URL dispatch — EA 4 uses pretty per-resource routes
  (`/admin/product`, `/admin/category`) instead of EA 5's query-string
  routing. The bridge emits chip "remove" links via `ea_url()` which
  works the same on both EA majors.

## What's been verified end-to-end

| Behaviour | Status on EA 4.29 |
|---|---|
| `composer install` clean | OK |
| `bin/console cache:clear --env=prod` | OK |
| `bin/console doctrine:schema:create` | OK |
| `bin/console polysource:demo:seed-products` | OK (5 categories + 30 products) |
| Dashboard at `/admin/` | OK |
| Product list at `/admin/product` | OK (30 results, Bootstrap 5 layout) |
| Bridge Twig namespace override | OK (`debug:twig` shows bridge views/ at higher priority than EA's templates/) |
| Chips bar above the list | OK |
| Boolean filter applies | OK (`?filters[isActive][value]=1` returns 16/30) |
| Comparison filter applies | OK (`?filters[stock][value]=20&filters[stock][comparison]=>=` returns 29/30) |
| `FilterTagsExtension` Twig function | OK (registered when TwigBundle is present) |

## Known v4-specific quirks

- The dashboard uses `#[AdminDashboard]` attribute discovery in
  EA 5; EA 4 needs the explicit `#[Route('/admin', name: 'admin')]`
  shown above.

## NumericFilter quick_ranges audit (2026-05-05)

Initial concern: the bridge's `quick_ranges` Stimulus controller may
emit the wrong URL shape on EA 4. **Audit result: no incompatibility.**

The bridge's `polysource--filter` Stimulus controller selects inputs
via name-suffix matching:

```js
this.element.querySelector('select[name$="[comparison]"]')
this.element.querySelector('input[name$="[value]"]')
this.element.querySelector('input[name$="[value2]"]')
```

EA 4 and EA 5 both render numeric filters as:

```
<select name="filters[price][comparison]">…</select>
<input  name="filters[price][value]"  type="number">
<input  name="filters[price][value2]" type="number">
```

(Both call `ComparisonFilterType::buildForm()` which adds `comparison`
+ `value` children, then `NumericFilterType::buildForm()` adds
`value2`.) So the `name$=` selectors match identically; the click sets
the values; EA's own form submits `filters[price][value]=50&filters[price][value2]=200&filters[price][comparison]=between`,
which both EA majors decode the same way (`FilterDataDto`'s shape is
unchanged between v4 and v5).

URL replays (a user copy-pasting `?filters[price]…`) work on both
majors for the same reason — the URL is just a serialisation of the
identical FilterDataDto.

## Stack comparison vs `easyadmin-bridge-demo/`

| Demo | PHP | Symfony | EA | Audience |
|---|---|---|---|---|
| `easyadmin-bridge-demo/` (bleeding-edge) | 8.4 | 7.4 LTS | 5.0 | Modern stacks |
| `easyadmin-bridge-demo-v4/` (this one) | 8.1 | 6.4 LTS | 4.29 | The EA v4 audience that didn't migrate yet |

Both share the same `polysource/easyadmin-filter-bridge` package
from `../../packages/easyadmin-filter-bridge` (Composer path
repository). The bridge's binary surface is identical on both
demos — only the host wiring differs.
