# Saved column configurations (perspectives)

> Task #18 of v0.4.0 — "saved column configs combinable with saved views".

## TL;DR — it's already there

`polysource/filter`'s `SavedView` entity (shipped in v0.1.0) already
persists three things together:

- **Filter slice** (`filters_json`) — the criteria
- **Sort order** (`sort_json`) — column → direction
- **Column selection** (`columns_json`) — list of property names

Saving a view captures all three at once. Applying a view restores
all three. This is the "perspective" concept — a named combination
of filter + sort + columns.

```text
                       ┌────────────────────────────┐
                       │ SavedView "Pending refunds"│
                       ├────────────────────────────┤
                       │ filters: status=refunded   │
                       │ sort: refundedAt desc      │
                       │ columns: [ref, customer,   │
                       │   total, refundedAt]       │
                       └────────────────────────────┘
```

## Wiring

Hosts that already let users save views via
`@PolysourceFilter/saved_view/save_modal.html.twig` get this
behaviour out of the box. The save form needs to include a hidden
field carrying the current column selection (the
`polysource_hidden_columns(resource)` Twig helper introduced in
v0.3.0 — see `column-preferences.md` — returns the current user's
hidden set, which the host inverts to get the visible set).

```twig
{# In your save-modal form override: #}
<form action="{{ path('polysource_saved_view_create') }}" method="post">
    {# … name, scope, filters_json, sort_json … #}

    {# Saved column selection: invert the current hidden list. #}
    {% set hidden = polysource_hidden_columns(resource_name) %}
    {% for property in all_visible_columns(resource_name) %}
        {% if property not in hidden %}
            <input type="hidden" name="columns[]" value="{{ property }}">
        {% endif %}
    {% endfor %}
</form>
```

`all_visible_columns()` is a host-side helper that lists every
column declared in the EA `CrudController::configureFields()`. The
bridge can't ship a generic version of it without coupling to
EA's internal field iteration; the host knows their fields list.

## Applying a saved view restores columns

When the user clicks a saved view in the dropdown, the apply
listener (`SavedViewApplySubscriber`, shipped in v0.1.0) redirects
to the view's filter+sort URL. The view's `columns` list is
accessible via `view.columns` in the template.

To restore the column visibility on a fresh page load when a view
is active, the host can sync the `ColumnPreferenceService` with the
view's column list:

```php
public function configureFilters(Filters $filters): Filters
{
    $view = $this->savedViewService->defaultFor($this->getEntityFqcn());
    if (null !== $view && count($view->columns) > 0) {
        // Persist the view's column selection to the user's
        // column-preferences (computed as everything NOT in view.columns).
        $allColumns = $this->allFieldNames();
        $hidden = array_values(array_diff($allColumns, $view->columns));
        $this->columnPreferenceService->setHiddenColumns(
            $this->getEntityFqcn(),
            $hidden,
        );
    }

    return parent::configureFilters($filters);
}
```

## Why no separate "PolysourceColumnConfig" entity in v0.4.0

A dedicated entity for named column configs (separate from saved
views) was considered. The conclusion: ADR-028 scope discipline.
Saved views already carry columns; adding a parallel
"column-only saved configs" feature is duplicate cognitive surface
for end-users without clear additional value. If a real use case
emerges that genuinely needs columns-without-filters (e.g. "I want
column config A but apply it to any filter combination"), a
separate entity can be carved out in v0.5+ with explicit migration.

## Width + ordering (deferred to v0.5+)

The current `columns_json` is a flat list. Per-column **width**
and explicit **ordering** (independent of declaration order in
`configureFields()`) are deferred to v0.5.0 polish. The current
shape covers visibility + display order; width is a CSS concern
hosts can solve today via inline styles or class overrides.

## Related

- [Column preferences (v0.3.0)](../filter/column-preferences.md)
- Saved views — `polysource/filter` README and the saved-view ADR
- [Row conditional styles (v0.3.0)](./row-styles.md)
