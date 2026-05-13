# Column visibility preferences

> Since `polysource/filter` v0.3.0.

The filter package ships a per-user column-visibility preference
store. Users can hide columns they don't care about on an EA index
page; the preference persists across sessions, keyed by
`(user_identifier, resource_name)`.

## Migration

The bridge auto-registers the Doctrine entity mapping. Hosts on
Doctrine ORM need to run a migration that creates the table.

```sql
CREATE TABLE polysource_column_preferences (
    owner_id VARCHAR(128) NOT NULL,
    resource_name VARCHAR(128) NOT NULL,
    hidden_columns_json TEXT NOT NULL,
    PRIMARY KEY (owner_id, resource_name)
);
```

Run via `doctrine:migrations:diff` + `doctrine:migrations:migrate` if
you use `doctrine-migrations-bundle`, or apply the SQL directly.

The recommended migration generation, using Symfony's
`doctrine:migrations:diff` command:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Wiring

If you installed `polysource/easyadmin-filter-bridge` along with
`polysource/filter`, the toggle dropdown is auto-rendered in the EA
index page header (next to the saved-views dropdown). Hosts opt in
to the routes by adding to `config/routes.yaml`:

```yaml
polysource_easyadmin_filter_bridge:
    resource: '@PolysourceEasyAdminFilterBridge/config/routes.php'
    type: php
```

(Same resource that wires the saved-views routes; no separate entry.)

## UX

1. User clicks the "Columns" button in the index header.
2. Dropdown lists every column rendered on the page; each is a
   checkbox checked = visible.
3. User toggles checkboxes, clicks "Apply".
4. Form POSTs to `polysource_column_preferences_update`, the server
   persists the inverted (hidden) list, redirects back via Referer.
5. On reload, the index template emits `[data-column="X"] { display: none }`
   CSS for each hidden column — EA's `<th>` and `<td>` cells carry
   that data attribute, so a single rule per hidden column blanks
   the entire column without touching EA's table iteration.

## Twig helpers

The filter package exposes two Twig functions from
`Polysource\Filter\ColumnPreference\Twig\ColumnPreferenceExtension`:

```twig
{# Returns true if the current user has hidden the given column. #}
{% if polysource_column_hidden('App\\Entity\\Order', 'paidAt') %}…{% endif %}

{# Returns the full hidden-list for the resource. #}
{% set hidden = polysource_hidden_columns('App\\Entity\\Order') %}
```

Both return safe defaults (`false` / `[]`) for anonymous users or
when the host hasn't wired storage (no DoctrineBundle / no
SecurityBundle).

## Anonymous users

The service silently no-ops for anonymous users: `findForCurrentUser`
returns null, `setHiddenColumns` is a noop, the Twig helpers return
defaults. Hosts that want anonymous prefs can subclass
`ColumnPreferenceService` and override `resolveOwnerId()` to return
a session-based or cookie-based identifier.

## Server-rendered (no JS)

The toggle UI is a regular `<form method="post">`. The dropdown
opens via Bootstrap's `data-bs-toggle="dropdown"` (already on every
EA index — no extra JS shipped by Polysource). Submit reloads the
page with the new preferences applied via the server-rendered
visibility CSS.

Per ADR-027, no Stimulus controller is required for this feature.
