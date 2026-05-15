# Cookbook — wrap the Polysource standalone admin in EasyAdmin chrome

> **Context**: you run Polysource standalone admin (`polysource/symfony-bundle`)
> alongside EasyAdmin in the same Symfony app. Out of the box, the
> Polysource pages render under the `polysource/twig-theme` minimal
> Bootstrap layout — visually disconnected from the EA pages your users
> already know. This cookbook integrates the Polysource pages **inside**
> the EA chrome (same sidebar, header, breadcrumb, color scheme) so the
> two products feel like one admin.

## The problem

Default Polysource layout vs your EA chrome:

```
┌─────────────────────────────────────────────────────────────┐
│ ⚠ Default Polysource layout                                 │
│                                                             │
│ ┌─ Polysource ─────────────────────────────────────────┐   │
│ │  /admin/cache-keys                                    │   │
│ └───────────────────────────────────────────────────────┘   │
│                                                             │
│   Redis cache                                               │
│   cache-keys                                                │
│   [Vues sauvegardées ▼]                                     │
│                                                             │
│   ┌─── table ───┬─────┬───────┐                            │
│   │ Clé         │ TTL │ Taille│                            │
│   ├─────────────┴─────┴───────┤                            │
│   │ ...                       │                            │
│                                                             │
│ — No EA sidebar, no EA top-bar, no breadcrumb. Users        │
│   lose context when they click a Polysource menu link. —    │
└─────────────────────────────────────────────────────────────┘
```

vs the same page wrapped in EA chrome:

```
┌─────────────────────────────────────────────────────────────┐
│ EA top-bar  (User menu, Locale, Logout)                     │
├──────────┬──────────────────────────────────────────────────┤
│ EA       │  Polysource › Redis cache                        │
│ sidebar  │                                                  │
│          │  ┌─── table ───┬─────┬───────┐                  │
│ Doctrine │  │ Clé         │ TTL │ Taille│                  │
│ entities │  ...                                             │
│          │                                                  │
│ Polysource                                                  │
│ ▸ Redis  │                                                  │
│   cache  │                                                  │
└──────────┴──────────────────────────────────────────────────┘
```

## The fix in 2 steps

### Step 1 — point Polysource at your EA-wrapping layout

```yaml
# config/packages/polysource.yaml
polysource:
    layout_template: 'admin/_polysource_layout.html.twig'
```

This config node (shipped since v0.6) tells Polysource's
`index.html.twig` / `detail.html.twig` to `extends` the host-supplied
template instead of `@Polysource/layout.html.twig`.

### Step 2 — write the wrapping layout

Create `templates/admin/_polysource_layout.html.twig`:

```twig
{# Reuse EA's own layout so the chrome (sidebar, top-bar, user
 # menu, locale switcher) matches every other admin page. #}
{% extends '@EasyAdmin/layout.html.twig' %}

{# Inject Polysource theme stylesheets into EA's <head> so chips,
 # tabs and table styles render correctly. #}
{% block head_custom_stylesheets %}
    {{ parent() }}
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          crossorigin="anonymous">
{% endblock %}

{# Page title — EA's layout exposes a `page_title` block. #}
{% block page_title %}{{ resource.label|default('Polysource') }}{% endblock %}

{# Replace EA's main content block with whatever Polysource
 # renders. Polysource's index.html.twig / detail.html.twig both
 # define a `content` block that fills here. #}
{% block main %}
    {% block content %}{% endblock %}
{% endblock %}

{# Optional: pre-fill the EA breadcrumb so users see where they
 # are within your admin. #}
{% block content_title %}
    <h1 class="title">
        Polysource
        {% if resource is defined %}
            <span class="text-muted">› {{ resource.label }}</span>
        {% endif %}
    </h1>
{% endblock %}
```

That's it. Refresh `/admin/polysource/{resource}` — the page now
renders inside EA's chrome.

## What about the sidebar?

EA's sidebar items are declared in your `DashboardController::configureMenuItems()`.
Add a Polysource section:

```php
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;

public function configureMenuItems(): iterable
{
    yield MenuItem::section('Doctrine entities');
    yield MenuItem::linkToCrud('Products', 'fa fa-box', Product::class);
    yield MenuItem::linkToCrud('Orders', 'fa fa-receipt', Order::class);

    yield MenuItem::section('Polysource (non-Doctrine)');
    yield MenuItem::linkToRoute('Redis cache', 'fa fa-database',
        'polysource_cache_keys_index', ['resourceName' => 'cache-keys']);
    yield MenuItem::linkToRoute('Failed messages', 'fa fa-envelope-open',
        'polysource_failed_messages_index', ['resourceName' => 'failed-messages']);
}
```

EA's sidebar voter automatically highlights the right item based
on the current route.

## Multi-tenant variant (e.g. `/{channel}/admin/...`)

If your EA is mounted under a channel prefix, the sidebar links
need the `{channel}` parameter:

```php
yield MenuItem::linkToRoute('Redis cache', 'fa fa-database',
    'polysource_cache_keys_index',
    ['channel' => $this->currentChannel->getName(), 'resourceName' => 'cache-keys']);
```

If you set `polysource_easyadmin_filter_bridge.auto_register_routes: false`
(per the multi-tenant install path in
[installation.md](../installation.md)), do the same for the
Polysource standalone admin — `url_prefix` config goes alongside.

## Limitations

- **EA filter UI vs Polysource filter UI**: each renders its own
  filter bar. They don't share state. If you want a single UI,
  use the `polysource/easyadmin-filter-bridge` for the Doctrine
  resources and let Polysource standalone keep its own filters
  for the non-Doctrine ones.
- **EA's `crud.html.twig` extension hooks** (`add_content_actions`,
  etc.) are NOT available from Polysource pages — Polysource's
  templates define their own blocks (`content`, `content_header`,
  `content_footer`). Override at the Polysource layer if you
  need similar slots.
- **Asset bundling**: if you use AssetMapper or Encore, prefer
  serving Bootstrap from your bundle config rather than the CDN
  shown above. The CDN is convenient but adds a dependency on an
  external service.

## See also

- [Installation guide](../installation.md) — base install + multi-tenant config
- [polysource/symfony-bundle README](../../../packages/symfony-bundle/README.md) — full list of bundle config options
- [Security and a11y audit](../security-and-a11y.md) — CSP considerations if you adopt a strict policy
