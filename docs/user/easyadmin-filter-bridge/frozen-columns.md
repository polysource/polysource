# Frozen / sticky columns

> Since `polysource/easyadmin-filter-bridge` v0.5.0.

A Twig helper that pins a table column to the left or right edge
of its scroll container — useful for keeping ID, name, or
action columns visible while the user scrolls a wide listing
horizontally.

Pure server-side: uses CSS `position: sticky`, no JS shipped by
the bundle, no external stylesheet to wire.

## Usage

In your `templates/bundles/EasyAdminBundle/crud/index.html.twig`
(EA's standard host-side override path), override `table_head`
and `table_body` and decorate the cells you want to freeze:

```twig
{% extends '@!EasyAdmin/crud/index.html.twig' %}

{% block table_head %}
    <tr>
        <th {{ polysource_frozen_column() }}>ID</th>
        <th {{ polysource_frozen_column('left', 60) }}>Reference</th>
        <th>Customer</th>
        <th>Status</th>
        <th>Total</th>
        <th {{ polysource_frozen_column('right') }}>Actions</th>
    </tr>
{% endblock %}

{% block table_body %}
    {% for entity in entities %}
        <tr>
            <td {{ polysource_frozen_column() }}>{{ entity.instance.id }}</td>
            <td {{ polysource_frozen_column('left', 60) }}>{{ entity.instance.reference }}</td>
            <td>{{ entity.instance.customerName }}</td>
            <td>{{ entity.instance.status }}</td>
            <td>{{ entity.instance.totalCents }}</td>
            <td {{ polysource_frozen_column('right') }}>…</td>
        </tr>
    {% endfor %}
{% endblock %}
```

The helper emits the `class="..."` and `style="..."` attributes
inline — no shared CSS file to import.

## Arguments

| Arg      | Type           | Default | Meaning                                  |
| -------- | -------------- | ------- | ---------------------------------------- |
| `side`   | `'left' \| 'right'` | `'left'`  | Which edge of the scroll container to pin to |
| `offset` | `int` (pixels) | `0`     | Distance from that edge — use to stack multiple frozen columns |

Unknown `side` values fall back to `'left'`. Negative offsets
are clamped to 0.

## Stacking multiple frozen columns

To freeze the first **two** columns on the left, give the second
one an offset equal to the width of the first:

```twig
<th {{ polysource_frozen_column('left', 0)  }} style="min-width: 60px">ID</th>
<th {{ polysource_frozen_column('left', 60) }} style="min-width: 200px">Name</th>
```

The two `style` attributes will merge — Twig concatenates them
(both are rendered into the same `<th>`). If you need finer
control, hosts can drop the helper and write their own classes
overriding the `.polysource-frozen-column` selector.

## Z-index + background

The helper applies:

- `position: sticky`
- `left` or `right` offset
- `z-index: 2` — above table content, well below modals
  (z-index 1050+) and dropdowns (1000+)
- `background-color: var(--bs-body-bg, #fff)` — opaque background
  so the column underneath doesn't bleed through. Works in both
  Bootstrap 5 light + dark themes via the CSS custom property.

## Progressive enhancement (ADR-027)

If the host's CSP strips inline styles (`style-src 'self'`
without `'unsafe-inline'`), the freeze visual effect drops but
the table still renders correctly — just without the pinning.
The columns become normal scrollable columns.

Hosts on strict CSP move the rules to a stylesheet:

```css
.polysource-frozen-column--left  { position: sticky; left: 0;  background: var(--bs-body-bg, #fff); z-index: 2; }
.polysource-frozen-column--right { position: sticky; right: 0; background: var(--bs-body-bg, #fff); z-index: 2; }
```

…and skip the helper, applying the class manually:

```twig
<th class="polysource-frozen-column polysource-frozen-column--left">…</th>
```

## Browser support

`position: sticky` is universally supported since 2017 (Chrome 56,
Firefox 32, Safari 13, Edge 16+) — Polysource's PHP 8.1+ /
Symfony 5.4-8.x compatibility window is narrower than the
browser baseline, so no fallback strategy is needed.
