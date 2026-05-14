# Toast notifications

> Since `polysource/easyadmin-filter-bridge` v0.5.0.

A Twig helper that renders Symfony flash messages as Bootstrap
alerts positioned in the top-right corner of the page — a
toast-like UX that works without any client-side JavaScript.

Picks up EA's built-in bulk-action `success`/`warning` flashes
automatically, plus any custom flash types your application sets.

## Usage

In your `templates/bundles/EasyAdminBundle/crud/index.html.twig`
(or your project-wide EA layout override), drop the helper
somewhere reliably rendered on every page:

```twig
{% block main %}
    {{ polysource_toasts() }}
    {{ parent() }}
{% endblock %}
```

That's it. After every bulk action (or any controller call that
sets a flash via `$this->addFlash('success', '…')`), the helper
renders the messages as styled alerts in the top-right corner.

## Flash type → alert variant mapping

| Flash type             | Bootstrap variant | Use for                              |
| ---------------------- | ----------------- | ------------------------------------ |
| `success`              | `alert-success`   | Successful operations                |
| `error`, `danger`      | `alert-danger`    | Errors, failures                     |
| `warning`              | `alert-warning`   | Non-fatal warnings                   |
| `info`, `notice`, *    | `alert-info`      | Informational, fallback for unknowns |

## Why alerts, not Bootstrap toasts?

Bootstrap's `.toast` component requires JavaScript to display
(`Toast.show()` or `data-bs-toggle="toast"` auto-init). Per
ADR-027 progressive enhancement, Polysource avoids interactive
features that require JS to even render.

The helper uses Bootstrap's `.alert` component (always visible,
server-renderable) with toast-like placement (`position-fixed
top-0 end-0`) — the UX feels toast-like but the message is
visible without any client-side scripting.

With Bootstrap's JS bundle loaded, the close button
(`data-bs-dismiss="alert"`) becomes interactive. Without it, the
user dismisses by navigating to another page (the flash bag
clears on read — the message won't reappear).

## Auto-dismiss (optional, host-side)

Polysource doesn't ship auto-dismiss JS. Hosts who want it wire
a 4-line Stimulus controller or vanilla snippet:

```javascript
setTimeout(() => {
    document.querySelectorAll('.polysource-toast').forEach((el) => {
        bootstrap.Alert.getOrCreateInstance(el).close()
    })
}, 5000)
```

The helper output carries the `polysource-toast` class on each
alert as a hook for exactly this kind of host-side enhancement.

## XSS safety

Flash message content is HTML-escaped before rendering — strings
with `<`, `>`, `&`, `"`, `'` are escaped via
`htmlspecialchars(..., ENT_QUOTES | ENT_HTML5)`.

If your bulk action wants to emit HTML formatting in the flash
(e.g. a link to the affected resource), set the flash with a
`Twig\Markup` instance — but at that point you're trusting the
source; sanitise carefully.

## Z-index

The container sits at `z-index: 1080` — above modals (1050+) and
dropdowns (1000+), so bulk-action confirmations remain visible
after a modal closes.
