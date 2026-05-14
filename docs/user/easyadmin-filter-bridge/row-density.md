# Row density toggle

> Since `polysource/easyadmin-filter-bridge` v0.5.0.

A 2-state toggle (compact ↔ normal) for table row density.
Compact uses Bootstrap's `table-sm` class (~33% taller scroll
density); normal uses Bootstrap's default `.table` spacing.

Pure server-side: the current density is a URL query parameter
(`?density=compact|normal`), the toggle is a pair of anchor
links — no JS, no cookies, no client-side state.

## Usage

In your `templates/bundles/EasyAdminBundle/crud/index.html.twig`
(EA's standard host-side override path), splice the helper
output into the `<table>` element's class list and render the
toggle wherever you want the control to appear:

```twig
{% extends '@!EasyAdmin/crud/index.html.twig' %}

{% block content_header %}
    {{ parent() }}
    {{ polysource_row_density_toggle() }}
{% endblock %}

{% block main %}
    <table class="table {{ polysource_row_density_class() }}">
        …
    </table>
{% endblock %}
```

That's it. Clicking "Compact" reloads the page with
`?density=compact`; the helper sees that and emits `table-sm`;
the `<table>` shrinks. Clicking "Normal" reloads with
`?density=normal` and the class output goes back to empty.

## Available helpers

| Function | Returns | Used for |
| -------- | ------- | -------- |
| `polysource_row_density_class()`  | `string` (`table-sm` or empty)   | The Bootstrap class to splice into `<table class="...">` |
| `polysource_row_density_current()` | `string` (`compact` or `normal`) | Branch in templates if you need to render conditionally |
| `polysource_row_density_toggle()`  | `Markup` (HTML)                  | The pre-rendered Bootstrap `.btn-group` toggle UI       |

## Query-parameter preservation

The toggle preserves every other URL query parameter — filters,
sort, page, search. Clicking the toggle keeps your view state
intact; only the `density` slice changes.

## Persistence beyond the page

The default behaviour is **URL-only**: the density resets when
the user navigates away. Hosts who want session-level (or
user-level) persistence wire a `RequestListener` that reads the
`density` query param on a kernel event, stores it in
`$session`, and re-applies it to subsequent requests — similar
to `Polysource\EasyAdminFilterBridge\EventListener\FilterSessionPersistenceSubscriber`
shipped for filter slices.

A future Polysource version may ship a built-in session
persistence layer for density; v0.5.0 keeps the toggle stateless
to avoid taking a stance on storage.

## Why no "comfortable" state?

A 3-state compact/normal/comfortable toggle would require
shipping custom CSS (Bootstrap 5 only has `.table-sm` —
"comfortable" doesn't exist). The bridge deliberately avoids
shipping a stylesheet (see ADR-027) — sticking to Bootstrap's
existing classes means hosts get the feature with zero CSS
pipeline work.

Hosts who want a 3-state toggle ship their own CSS and override
the `polysource_row_density_class` helper output in their
templates.
