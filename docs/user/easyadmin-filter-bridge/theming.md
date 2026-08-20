# Theming the EasyAdmin filter bridge

> Since `polysource/easyadmin-filter-bridge` v0.10.0.
> Before v0.10.0 the bridge inlined its CSS in the page; the
> variables and override points below did not exist.

The bridge's own chrome — chips bar, filter-modal tabs and groups,
subpanel mode — is styled by **one stylesheet** shipped with the
bundle and themable at three levels, from cheapest to most invasive:

1. [CSS variables](#css-variables) — recolor / resize without
   touching any template. Covers "I want chips in my company
   colors".
2. [Twig template overrides](#twig-template-overrides) — change the
   markup of a chip, the chips bar, or the whole index page.
3. [Replacing the stylesheet](#replacing-the-stylesheet) — ship your
   own CSS entirely.

## How the stylesheet is loaded

The bridge ships two published assets:

| File | Role |
|---|---|
| `polysource-filter-bridge.css` | All static styling (chips, tabs, groups, subpanel) |
| `polysource-filter-shim.js` | Defensive re-bind of EA's filter button on Turbo-enabled stacks |

Both live in the bundle's `Resources/public/` and are copied (or
symlinked) to `public/bundles/polysourceeasyadminfilterbridge/` by
`assets:install` — which Symfony Flex runs automatically on every
`composer install`/`update`. If your deploy pipeline skips composer
scripts, add the command explicitly:

```console
$ php bin/console assets:install --symlink
```

The bridge's `crud/index.html.twig` override links them with
`asset()`, so the URLs go through whatever asset strategy your app
uses — plain `/bundles/...` paths, a CDN base URL, or AssetMapper's
versioned URLs. Nothing to configure.

**CSP note.** Since the styling and the shim are external files, the
bridge no longer needs `style-src 'unsafe-inline'` or
`script-src 'unsafe-inline'` in your Content-Security-Policy, with
one documented exception: when a user hides columns via the
column-visibility dropdown, the per-user hiding rules are emitted as
a small inline `<style>` block (they depend on the user's saved
preferences, so they cannot be a static file). Allow it with a
nonce/hash on that page, or accept `'unsafe-inline'` for `style-src`
only.

## CSS variables

The colors and key dimensions of the chips bar, the filter-modal tabs
and groups, and the subpanel all flow through a `--polysource-*`
custom property declared on `:root`. Override any of them from your
app stylesheet — no build step, no template override:

```css
/* assets/styles/admin.css — loaded after the bridge stylesheet */
:root {
    --polysource-accent: #0f766e;          /* active tab, counters   */
    --polysource-subpanel-width: 640px;    /* wider filter subpanel  */
}
```

| Variable | Default | Used by |
|---|---|---|
| `--polysource-accent` | `var(--bs-primary, #4f46e5)` | Active tab underline, tab/group applied-filter counters |
| `--polysource-accent-contrast` | `#fff` | Text on accent-colored surfaces (counter pills) |
| `--polysource-danger` | `var(--bs-danger, #dc3545)` | "Clear all" hover, chip × hover |
| `--polysource-border-color` | `var(--bs-border-color, #dee2e6)` | Chips-bar border, chip border, tab-strip baseline |
| `--polysource-muted-color` | `var(--bs-secondary-color, #6c757d)` | Headings, separators, inactive tabs, group titles |
| `--polysource-emphasis-color` | `var(--bs-emphasis-color, #212529)` | Chip labels, hovered tabs |
| `--polysource-body-color` | `var(--bs-body-color, #212529)` | Chip values |
| `--polysource-surface-bg` | `var(--bs-body-bg, #fff)` | Chip background |
| `--polysource-surface-muted-bg` | `var(--bs-tertiary-bg, #f5f5f5)` | Chips-bar background |
| `--polysource-radius` | `0.375rem` | Chips-bar corner radius |
| `--polysource-subpanel-width` | `560px` | Subpanel width (desktop) |
| `--polysource-subpanel-width-tablet` | `480px` | Subpanel width (576–991 px viewports) |

Because the defaults resolve to Bootstrap 5.3 variables (the ones
EasyAdmin ships), the bridge **follows your admin's light/dark theme
automatically** — you only need overrides for deliberate deviations.
The hex fallbacks kick in on stacks without Bootstrap CSS variables
(EA 4 / Bootstrap < 5.3).

**Not covered by a variable yet:** the row-details chevron added in
v1.1 (`.polysource-row-detail-toggle`, rendered by
`crud/field/row_detail.html.twig`). It carries no rules in
`polysource-filter-bridge.css` — it rides on Bootstrap's
`btn btn-sm btn-link` utilities and inherits your admin's link color
as-is. To restyle it, target the class from your own stylesheet:

```css
.polysource-row-detail-toggle { color: #0f766e; }
```

Variables can also be scoped instead of global — e.g. recolor chips
on one CRUD only, keyed off EA's per-page body id:

```css
body#ea-index-Order {
    --polysource-accent: #b45309;
}
```

## Twig template overrides

### The safe way to extend the index page — read this first

The bridge works by splicing its views into the `@EasyAdmin` Twig
namespace at higher priority, so **every** CRUD index page renders
through the bridge's `crud/index.html.twig` (chips bar, column
visibility dropdown, saved views) without any host configuration.

Symfony's app-level override convention
(`templates/bundles/EasyAdminBundle/crud/index.html.twig`) takes
priority over the bridge's splice. That's the trap: if that file
extends upstream EA directly —

```twig
{# ⚠️ silently disables the bridge on every index page #}
{% extends '@!EasyAdmin/crud/index.html.twig' %}
```

— the chips bar, the column-visibility dropdown, and the saved-views
dropdown all vanish, with no error anywhere. The `@!` bang-prefix
skips override resolution entirely, so the bridge's template is
never reached.

The correct parent for an app-level index override is the bridge's
template, which itself falls through to upstream EA:

```twig
{# templates/bundles/EasyAdminBundle/crud/index.html.twig #}
{% extends '@PolysourceEasyAdminFilterBridge/crud/index.html.twig' %}

{% block table_body %}
    {# your customisation — chips bar and dropdowns stay intact #}
{% endblock %}
```

Resolution chain: your override → bridge template → upstream EA.

### Removing or relocating the toolbar and the chips bar

The bridge index wraps its two injected regions in named blocks, so
an app-level override can drop or move either one without touching
the rest of the page:

| Block | Contains |
|---|---|
| `polysource_datagrid_toolbar` | saved-views dropdown + column-visibility dropdown |
| `polysource_chips_bar` | the active-filters chips bar |

Override a block with an empty body to opt out on a given CRUD (for
example while rolling saved views out gradually), or with custom
markup to reposition it:

```twig
{# templates/bundles/EasyAdminBundle/crud/index.html.twig #}
{% extends '@PolysourceEasyAdminFilterBridge/crud/index.html.twig' %}

{# no saved-views / column toolbar on this app, chips bar untouched #}
{% block polysource_datagrid_toolbar %}{% endblock %}
```

Per-CRUD opt-out works the same way with a controller-specific
template (`setCrudTemplate`/`overrideTemplate('crud/index', …)`)
that extends the bridge index. For cosmetic changes to the chips
themselves, prefer the partial overrides below.

### Chip and chips-bar markup

The chips bar is split into two templates precisely so the common
overrides stay small:

| You want… | Override (app-level path) |
|---|---|
| Different styling/markup for a single chip | `templates/bundles/EasyAdminBundle/crud/_chip.html.twig` |
| Different bar layout (placement, grouping, no "Clear all") | `templates/bundles/EasyAdminBundle/crud/_chips_bar.html.twig` |

`_chip.html.twig` receives `property`, `label`, `comparison`,
`value`, `value2`, and `slice` — see the input contract documented
in the template's header. Two Twig functions do the rendering work:
`polysource_chip_value(property, value)` resolves the value through
the chip-formatter chain, and `polysource_chip_operator(comparison)`
turns the raw URL comparison (`like`, `>=`, `IN`…) into the same
wording EasyAdmin uses in the filter modal's comparison select.
A minimal recolored chip:

```twig
{# templates/bundles/EasyAdminBundle/crud/_chip.html.twig #}
<span class="ea-filter-chip badge text-bg-info">
    {{ label }}: {{ polysource_chip_value(property, value) }}
</span>
```

### Swapping the stylesheet only

The `<link>` tag lives in its own `polysource_stylesheets` block
inside the bridge's `configured_stylesheets`. A template extending
the bridge's index can replace just that:

```twig
{% extends '@PolysourceEasyAdminFilterBridge/crud/index.html.twig' %}

{% block polysource_stylesheets %}
    <link rel="stylesheet" href="{{ asset('styles/my-filter-theme.css') }}">
{% endblock %}
```

Start from a copy of the bundle's
`Resources/public/polysource-filter-bridge.css` — the tab-pane
pairing rules at its core are load-bearing (they implement the
zero-JS tab switching), keep them.

## Replacing the stylesheet

If you bundle CSS through AssetMapper, Webpack Encore, or another
pipeline and want the bridge styles in your build instead of a
second `<link>`, import the file from the bundle path (composer
vendor dir) into your entry point, then empty the block:

```twig
{% block polysource_stylesheets %}{% endblock %}
```

## Subpanel mode

The right-anchored filter subpanel is an opt-in template per CRUD
controller:

```php
public function configureCrud(Crud $crud): Crud
{
    return $crud->overrideTemplate(
        'crud/index',
        '@PolysourceEasyAdminFilterBridge/crud/index_subpanel.html.twig',
    );
}
```

It reuses the same stylesheet (the subpanel rules are gated on a
`polysource-filter-subpanel` body class) — so the variables above,
including the two width variables, are all it takes to resize or
recolor it.
