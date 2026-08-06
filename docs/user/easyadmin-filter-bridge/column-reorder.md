# Column reordering

> Since `polysource/easyadmin-filter-bridge` v0.5.0.

Persistent column ordering on the EA index page — users move
columns left/right via per-header ← → buttons; the new order is
saved on their `ColumnPreference` record and applied on every
subsequent visit.

Pure server-side per ADR-027: anchor-based buttons, GET-based
endpoint, full page reload. Hosts who want HTML5 drag-and-drop
layer their own Stimulus controller on top of the same
persistence API.

## Usage

In your `templates/bundles/EasyAdminBundle/crud/index.html.twig`
override, render the reorder buttons next to each column header
inside the `table_head` block:

```twig
{% extends '@PolysourceEasyAdminFilterBridge/crud/index.html.twig' %}

{% block table_head %}
    {% set resource = ea.entity.fqcn %}
    {% set ordered_columns = polysource_apply_order(resource, columns|map(c => c.property)|list) %}
    <tr>
        {% for property in ordered_columns %}
            <th>
                {{ columns[property].label }}
                {{ polysource_column_reorder_buttons(resource, property, ordered_columns) }}
            </th>
        {% endfor %}
    </tr>
{% endblock %}
```

The helper emits two `<a href>` anchors per header. Clicking ←
reloads the page with the column swapped one step to the left;
clicking → swaps one step to the right. Both ends of the row
render the corresponding button as `.disabled` so visual
feedback is preserved.

## Server-side API

Hosts who need to manipulate the order outside the templating
layer go through the `ColumnPreferenceService`:

```php
use Polysource\Filter\ColumnPreference\ColumnPreferenceService;

final class MyAdminController
{
    public function __construct(private ColumnPreferenceService $columns) {}

    public function someAction(): Response
    {
        // Resolve effective order (override on top of host defaults):
        $effective = $this->columns->applyOrder('orders', ['id', 'reference', 'status']);

        // Persist a brand-new order:
        $this->columns->setColumnOrder('orders', ['status', 'reference', 'id']);

        // Clear the override (revert to host default):
        $this->columns->setColumnOrder('orders', null);
    }
}
```

## Drag-and-drop (optional, host-side)

The bridge deliberately ships only the anchor-based baseline so
the feature works without JS. Hosts who want drag-to-reorder
wire a small Stimulus controller against the same persistence
backend — submit the full new order via `fetch()` to the move
endpoint, or use `setColumnOrder()` from a controller they own.

A reference Stimulus controller stub (vanilla, no external
deps):

```javascript
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = { resource: String, columns: Array }

    connect() {
        this.element.draggable = true
        this.element.addEventListener('dragstart', this.onDragStart.bind(this))
        this.element.addEventListener('dragover', this.onDragOver.bind(this))
        this.element.addEventListener('drop', this.onDrop.bind(this))
    }

    // … assemble the new order, then POST to a host endpoint that
    // calls ColumnPreferenceService::setColumnOrder()
}
```

## Persistence

The order override lives in
`polysource_column_preferences.column_order_json` (added in
v0.5.0; nullable for BC — pre-v0.5.0 rows decode as "no
override").

Hosts running production apply the migration:

```sql
ALTER TABLE polysource_column_preferences ADD column_order_json TEXT DEFAULT NULL;
```

## Why no third-party drag-and-drop lib?

Polysource's scope (ADR-028) is the filter+listing UX layer.
Shipping a drag-and-drop library would expand the bundle's
JavaScript surface and force a choice between Sortable.js,
@hotwired/dropzone, etc. The server-side baseline + a
documented hook is the irréprochable path: it works without
JS, it's accessible (keyboard / screen reader), and hosts who
want drag UX wire the lib of their choice.
