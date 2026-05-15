# Security (CSP) and accessibility (WCAG) audit — polysource v0.6.x

This page covers two concerns that don't fit elsewhere:

1. **Content Security Policy** — how Polysource's templates interact
   with a strict CSP header
2. **Accessibility** — what's covered, what's verified, what
   hosts may want to extend

Both are live concerns — the audit baseline below is current as of
v0.6.1. Hosts whose stack adds a CSP layer or pushes beyond WCAG
2.2 AA should treat this page as the contract surface.

## 1. Content Security Policy

### Summary

Polysource templates **work under a `style-src 'self' 'unsafe-inline'`
CSP** without further configuration. Strict no-inline CSPs need
either:

- a CSP nonce wired through Symfony Security's `csp_nonce`
  request listener, OR
- a host-level template override that moves the inline CSS out

No `script-src 'unsafe-inline'` or `'unsafe-eval'` is needed —
Polysource doesn't use inline event handlers (`onclick`, etc.) or
`eval`.

### Where inline CSS lives today

| Template | Block | Why inline | Workaround |
|---|---|---|---|
| `easyadmin-filter-bridge/crud/index.html.twig` | `<style>…</style>` (≈ 250 lines) | Bridge-wide filter chip / tab / dropdown styles. Inline so the bridge ships zero CSS files (hosts don't need an asset pipeline). | Override the `configured_stylesheets` block at the app level + ship the same CSS as a `.css` file via AssetMapper/Encore. |
| `easyadmin-filter-bridge/crud/index_subpanel.html.twig` | `<style>` (subpanel slide-in) | Same rationale. | Same. |
| `easyadmin-filter-bridge/crud/filters.html.twig` | `<style>` generated per-tab via `:has()` for tab→pane visibility | Number of rules is host-config-dependent (one per `Polysource::tab(...)`), so it has to be emitted from the template. | Use a CSP nonce — see "Adopting a strict CSP" below. |
| `filter/modes/subpanel.html.twig` | `<style>` (subpanel mode CSS) | Same as bridge index. | Same. |
| `bulk-async/_progress.html.twig` | Inline `style="width: {{ progress_pct }}%"` | Dynamic progress-bar width — value comes from runtime state. | Use a CSP nonce (the only realistic option for a dynamic computed width). |

### Adopting a strict CSP — recipe

If your host enforces `Content-Security-Policy: style-src 'self'`
(no `'unsafe-inline'`), wire Symfony's CSP nonce mechanism:

```yaml
# config/packages/framework.yaml
framework:
    csp:
        # Symfony Security 7.1+ ships a CspNonceListener that
        # generates a per-request nonce and exposes it as
        # `csp_nonce` in Twig.
        nonce: true
```

Then override the bridge's `configured_stylesheets` block at the
app level to inject the nonce on every `<style>` tag:

```twig
{# templates/bundles/EasyAdminBundle/crud/index.html.twig #}
{% extends '@!PolysourceEasyAdminFilterBridge/crud/index.html.twig' %}
{% block configured_stylesheets %}
    {# parent() emits inline <style> blocks — wrap them in nonce attr #}
    {% apply replace({'<style>': '<style nonce="' ~ csp_nonce() ~ '">'}) %}
        {{ parent() }}
    {% endapply %}
{% endblock %}
```

Same pattern for `_progress.html.twig`:

```twig
{# templates/bundles/PolysourceBulkAsyncBundle/_progress.html.twig #}
{% extends '@!PolysourceBulkAsync/_progress.html.twig' %}
{% block progress_bar %}
    <div class="progress-bar" style="width: {{ progress_pct }}%" nonce="{{ csp_nonce() }}">…</div>
{% endblock %}
```

**Future direction (v0.7+):** make CSP-nonce wiring opt-in via a
bundle config flag (`polysource_easyadmin_filter_bridge.csp_nonce: true`)
so hosts don't need template overrides. Out of scope for v0.6.x.

### What polysource does NOT do (verified)

- ✓ No `eval()` / `Function()` / `setTimeout("string")`
- ✓ No inline `onclick=`, `onchange=`, etc. handlers — all JS lives
  in Stimulus controllers under `assets/controllers/`
- ✓ No `javascript:` URIs in `href` / `src`
- ✓ No `<script>` blocks emitted from templates (a single inline
  `<script>` in `easyadmin-filter-bridge/crud/index.html.twig`
  exists for the filter-modal AJAX loader — see "Strict-CSP script
  guidance" below)

### Strict-CSP script guidance

The bridge ships ONE inline `<script>` block in
`crud/index.html.twig` for the modal AJAX loader. Same nonce
pattern as inline `<style>` applies:

```twig
{# Override at the app level #}
{% block configured_javascripts %}
    {% apply replace({'<script>': '<script nonce="' ~ csp_nonce() ~ '">'}) %}
        {{ parent() }}
    {% endapply %}
{% endblock %}
```

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
| Filter modal / subpanel | `role="dialog"` + `aria-modal="true"` + focus trap (EA stock + bridge preserves) |
| Filter tabs | `<details name="polysource-tab">` provides ARIA `disclosure` semantics natively; tab navigation via arrow keys works in modern browsers |
| Chips bar | Each chip has `aria-label` describing label + value + remove action |
| Saved-views dropdown | Bootstrap dropdown with `aria-haspopup` + `aria-expanded` |
| Save-view modal | Form labels properly associated; modal trap on focus |
| Bulk-async progress | `role="progressbar"` + `aria-valuenow` / `aria-valuemin` / `aria-valuemax` |
| Search palette | `role="combobox"` + `aria-expanded` + `aria-controls` |
| Tooltips / pop overs | Use Bootstrap 5's built-in ARIA wiring |

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
- **Stimulus-less hosts**: chip × close buttons render as visible
  but inert clickable elements (per ADR-027 progressive
  enhancement). Hosts without Stimulus have a "buttons that don't
  do anything" UX — not strictly an a11y bug but a usability one.
  Either install Stimulus or hide the close buttons via the
  documented CSS class.

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
