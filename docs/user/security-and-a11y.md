# Security (CSP) and accessibility (WCAG) audit

This page covers two concerns that don't fit elsewhere:

1. **Content Security Policy** — how Polysource's templates interact
   with a strict CSP header
2. **Accessibility** — what's covered, what's verified, what
   hosts may want to extend

Both are live concerns — the audit baseline below was re-verified
against the templates shipped in **v1.1.0 (2026-08-07)**. Hosts whose
stack adds a CSP layer or pushes beyond WCAG 2.2 AA should treat this
page as the contract surface.

## 1. Content Security Policy

### Summary

Polysource templates work out of the box under a CSP that allows
`'unsafe-inline'` for both `style-src` and `script-src`. Tightening
beyond that is possible, but the required work differs per construct,
so it is worth knowing exactly what ships.

Nothing needs `'unsafe-eval'`: there is no `eval()`, no
`new Function()`, no string-argument `setTimeout`, no `javascript:`
URI, and no inline `onclick=`/`onchange=` handler anywhere in the
templates. All event wiring goes through Stimulus controllers or
`addEventListener`.

### The full inline inventory

Three templates emit an inline block — `filter/modes/subpanel.html.twig`
emits both a `<style>` and a `<script>`. This is the complete list,
verified against v1.1.0 sources.

**Inline `<style>` blocks:**

| Template | What it emits | Why it can't be a static file |
|---|---|---|
| `easyadmin-filter-bridge/crud/index.html.twig` | One `[data-column="X"] { display: none !important; }` rule per column the user has hidden | The rule set is per-user runtime state (the column-visibility preference), not authorable ahead of time |
| `filter/modes/subpanel.html.twig` | The subpanel mode's CSS (~120 lines) | Ships with the template so the subpanel works with no asset pipeline at all |

Note what is **not** on that list any more: the bridge's bulk of
CSS is a real stylesheet,
`Resources/public/polysource-filter-bridge.css`, published by
`assets:install`, as is the standalone theme's
`twig-theme/public/polysource.css`. `crud/index_subpanel.html.twig`
and `crud/filters.html.twig` emit no `<style>` at all.

**Inline `<script>` blocks:**

| Template | What it does | Degrades to |
|---|---|---|
| `filter/modes/subpanel.html.twig` | ESC-to-close and focus-the-first-control-on-open for the `<details>` panel | The panel still opens and closes — it's a native `<details>` |
| `twig-theme/_filters_form.html.twig` | Auto-ticks a filter's checkbox when its value/operator input is touched, and disables unchecked fields on submit so they stay out of the URL | Users tick the checkbox themselves |

Both blocks are pure enhancement (ADR-027) and both are removable by
overriding the template in your own `templates/bundles/` directory —
the subpanel template says so in a comment.

The EasyAdmin bridge's filter shim is deliberately an **external**
file (`<script src="…/polysource-filter-shim.js" defer>`) precisely so
that hosts don't need `script-src 'unsafe-inline'` for the bridge.

**Inline `style="…"` attributes** (four, all small): the bulk-async
progress bar's `height: 8px` and computed `width: {{ progress_pct }}%`,
a `max-width: 12rem` on the operator `<select>` in the standalone
filter form, and the layout rule on the bridge's standalone
row-detail page.

**One external origin:** `twig-theme/layout.html.twig` loads Bootstrap
from `https://cdn.jsdelivr.net`. If you enforce CSP you must allow
that origin in `script-src`, or (better) override the layout and serve
Bootstrap from your own asset pipeline. The template carries a
standing note to add an `integrity=` SRI hash — do that if you keep
the CDN.

### Adopting a strict CSP — recipe

Symfony's FrameworkBundle has **no** CSP configuration: there is no
`framework.csp` node and no `CspNonceListener`. You need either a
bundle or a few lines of your own.

**Recommended: `nelmio/security-bundle`.** It sends the header and
provides `csp_nonce()` in Twig; calling the function is what adds
the matching `'nonce-…'` to the header for that request.

```bash
composer require nelmio/security-bundle
```

```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    csp:
        enforce:
            default-src: ["'self'"]
            script-src:
                - "'self'"
                # Only if you keep the CDN Bootstrap in the standalone theme:
                - "https://cdn.jsdelivr.net"
            style-src: ["'self'"]
            # Inline style="…" attributes are governed separately and
            # a nonce does NOT cover them — see the caveat below.
            style-src-attr: ["'unsafe-inline'"]
```

Then override the two templates that emit inline blocks and stamp
the nonce on them:

```twig
{# templates/bundles/PolysourceFilterBundle/modes/subpanel.html.twig #}
{% extends '@!PolysourceFilter/modes/subpanel.html.twig' %}
```

…or, if you'd rather not fork the markup, copy the file into your own
`templates/bundles/` tree and add `nonce="{{ csp_nonce('style') }}"`
to its `<style>` tag and `nonce="{{ csp_nonce('script') }}"` to its
`<script>` tag. The same applies to
`@PolysourceEasyAdminFilterBridge/crud/index.html.twig` and
`@Polysource/_filters_form.html.twig`.

**Hand-rolled alternative.** If you don't want another dependency, a
`kernel.response` listener that generates a nonce per request, exposes
it as a request attribute (so Twig can read it), and sets the
`Content-Security-Policy` header is around 30 lines. Use the same
template overrides.

**The caveat that trips people up:** a nonce applies to `<style>` and
`<script>` *elements* only. It cannot whitelist a `style="…"`
*attribute* — those are governed by `style-src-attr`, which needs
`'unsafe-inline'` (or `'unsafe-hashes'` plus a hash per value, which
is impractical for the computed `width: {{ progress_pct }}%`). Either
keep `style-src-attr: 'unsafe-inline'`, or override
`bulk-async/_progress.html.twig` to drive the bar's width from a CSS
custom property set by the Stimulus controller instead of an
attribute.

## 2. Accessibility (WCAG 2.2 AA)

### Coverage today

Polysource templates pass the **WCAG 2.2 AA structural baseline**:
semantic HTML, ARIA labels on interactive widgets without
visible text, focus management on modals, keyboard navigation.

Manual screen-reader tests (NVDA + VoiceOver) have been run on
the showcase as part of the v0.5.0 release gates.

### What's verified

| Feature | A11y guarantee |
|---|---|
| Filter subpanel | Native `<details>`/`<summary>` disclosure semantics; the panel body is a `role="region"` labelled by the panel title. ESC-to-close and focus-into-panel-on-open come from the template's enhancement script |
| Filter modal (integrated mode) | Bootstrap 5 modal — its stock ARIA wiring and focus trap, plus an `aria-label`led close button |
| Filter tabs | `<details name="…">` groups give mutually-exclusive disclosure semantics natively |
| Chips bar | The chips region is `role="region"` with an `aria-label`; each chip's remove control carries its own `aria-label` |
| Saved-views dropdown | Bootstrap dropdown with `aria-expanded`; the per-view "make default" toggle is `aria-pressed` and its `aria-label` names the view |
| Save-view modal | Form labels properly associated; Bootstrap modal focus handling |
| Bulk-async progress | `role="progressbar"` + `aria-label` + `aria-valuenow` / `aria-valuemin` / `aria-valuemax` |
| Search palette | `role="dialog"` + `aria-label` on the palette, `role="listbox"` on the results; ↑/↓/↵/Esc keyboard model, with the shortcuts shown in the palette footer |
| **Row-detail chevron** *(v1.1)* | The toggle is a real `<a href>` (it navigates to the standalone panel page without JS) marked `role="button"` with `aria-expanded="false"` and a translated `aria-label`. The glyph itself is `aria-hidden="true"` — decorative, the label carries the meaning. On toggle, the Stimulus controller flips `aria-expanded` and swaps the `aria-label` between the "expand" and "collapse" strings, so the control always announces what it will do next. `aria-expanded` reads `true` during loading and error states too, matching the fact that a row has been inserted |
| Tooltips / popovers | Use Bootstrap 5's built-in ARIA wiring |

### Known gaps (host-side, not polysource issues)

These are caveats hosts should be aware of — none block Polysource
itself from passing AA, but they need host attention:

- **Filter values rendered via `chipFormatter`**: hosts choose the
  string content. If a host emits emoji-only chips ("👁️ Visible"),
  screen readers may pronounce them inconsistently. Pair emoji
  with text or set `aria-label` on the chip.
- **Custom EA filter form types**: if a host registers their own
  form type and skips Bootstrap label associations, the bridge
  can't add them retroactively.
- **Color contrast**: the bridge inherits EA's color tokens. If a
  host changes the EA Sass theme to low-contrast colors, the
  bridge's button accents inherit them. Verify with axe-core or
  Lighthouse.
- **Stimulus-less hosts**: nothing renders inert. Per ADR-027 the
  chip × control is a real `<a href>` to the same page minus that
  filter, the row-detail chevron is a real `<a href>` to the
  standalone panel page, and the subpanel is a native `<details>`.
  Without Stimulus each of these costs a page load instead of
  updating in place — a performance difference, not a broken
  control. The one thing that does go away is the chips
  overflow "+N more" `<button>`, which needs JS to reveal the
  hidden chips.

### Recommended host-side audit tools

- `axe-core` via Playwright / Panther (the showcase's E2E suite
  could be extended with axe runs — open issue if you want this)
- Lighthouse (Chrome DevTools) for color contrast + tap-target sizing
- NVDA / VoiceOver / TalkBack for screen-reader spot-checks on
  every major user flow

## See also

- [ADR-027 — progressive enhancement](../adr/0027-progressive-enhancement.md)
  — why every interactive widget has a server-side baseline
- [MDN — CSP / `style-src`](https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Security-Policy/style-src)
- [WCAG 2.2 Quick Reference](https://www.w3.org/WAI/WCAG22/quickref/)
