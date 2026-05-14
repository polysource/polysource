# Keyboard shortcuts

> Since `polysource/easyadmin-filter-bridge` v0.5.0.

A server-rendered cheat sheet for the recommended keyboard
shortcuts on EA index pages, plus a documented Stimulus
controller pattern hosts wire to actually bind the keys.

The cheat sheet is a native HTML `<details>` element — fully
server-renderable, toggleable without JS, accessible to screen
readers out of the box. Actually binding the shortcuts is a
host-side concern (the bundle does not ship the JS, per
ADR-027 scope discipline + ADR-028 — keyboard navigation is
optional polish, not a core listing-UX feature).

## Recommended shortcuts

| Key   | Action                              | Scope    |
| ----- | ----------------------------------- | -------- |
| `j`   | Next row                            | Listing  |
| `k`   | Previous row                        | Listing  |
| Enter | Open the focused row                | Listing  |
| `/`   | Focus the search field              | Search   |
| `f`   | Open the filters modal              | Filters  |
| `c`   | Toggle column visibility menu       | Columns  |
| `n`   | Create new record                   | Actions  |
| `?`   | Toggle this help panel              | Help     |
| Esc   | Close any open modal / panel        | Global   |

## Rendering the help

Drop the helper anywhere in your layout — typically in a quiet
footer area:

```twig
{% block footer %}
    {{ parent() }}
    {{ polysource_keyboard_shortcuts_help() }}
{% endblock %}
```

The output is a `<details>` element containing a `<table>` of
the recommended bindings. Closed by default; the user clicks
the `<summary>` to expand.

## Programmatic access

Hosts who'd rather render their own help table or pass the
list to a JS controller as JSON:

```twig
{# In a Stimulus controller value or a JSON script tag #}
<script type="application/json" data-controller="shortcuts">
    {{ polysource_keyboard_shortcuts_list() | json_encode | raw }}
</script>
```

The helper returns the canonical list of
`{key, label, scope}` triplets.

## Wiring the bindings (host-side)

The bundle does NOT ship a Stimulus controller — hosts pick
their own UX (which scope to bind in, how to track focused row,
whether to layer Bootstrap's data attributes on top, etc.).

Reference Stimulus controller stub (vanilla, no external deps):

```javascript
import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = { shortcuts: Array }

    connect() {
        this.boundOnKey = this.onKey.bind(this)
        document.addEventListener('keydown', this.boundOnKey)
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundOnKey)
    }

    onKey(event) {
        // Skip when the user is typing in an input/textarea.
        const target = event.target
        if (target.matches('input, textarea, [contenteditable="true"]')) {
            return
        }

        switch (event.key) {
            case 'j': this.moveSelection(1); break
            case 'k': this.moveSelection(-1); break
            case '/': this.focusSearch(event); break
            case 'f': this.openFilters(event); break
            case 'n': this.createNew(event); break
            case '?': this.toggleHelp(event); break
            case 'Escape': this.closeOpenPanels(event); break
        }
    }

    // … implementations follow the host's existing DOM hooks
}
```

## Why not ship the controller?

Polysource's scope (ADR-028) is the **filter+listing UX layer**
for EA — not a generic admin platform. Keyboard navigation
overlaps with browser-level accessibility (tab + enter already
navigate rows). Shipping a Stimulus controller would:

1. Pull `@hotwired/stimulus` into the bundle's dependency graph
   (it's a host-side dep today).
2. Hardcode UX choices (which selector locates "the focused
   row"?) that vary by host.
3. Risk conflicts with the host's existing keyboard handlers.

The documented pattern keeps Polysource lean and gives hosts
full control. Hosts who want a turnkey solution copy the
Stimulus stub above and customise.

## Accessibility note

The native `<details>`/`<summary>` pattern is keyboard-accessible
and screen-reader-friendly out of the box: tab + enter opens
the cheat sheet, esc closes it (in modern browsers), and the
`<kbd>` elements get semantic announcements.

The host's Stimulus controller, if shipped, should respect
`event.target.matches('input, textarea, [contenteditable]')`
so shortcuts don't fire while users are typing — the reference
stub does this.
