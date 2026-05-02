# Polysource preview

Tiny Symfony app that boots `polysource/symfony-bundle` + `polysource/twig-theme`
with a single in-memory **Feature flags** resource. Designed for visual
verification of the templates during development — not a feature demo.

For the full Messenger-failed cas tueur, see `examples/messenger-demo/`
(arrives in Phase 8 of the development plan).

## Usage

```bash
make preview
```

Then open <http://localhost:8080/admin/flags>.

The make target runs PHP's built-in server inside the project Docker
image and maps port 8080 to the host. Stop with `Ctrl+C`.

## What you'll see

- **Index** (`/admin/flags`): a Bootstrap 5 table with 4 seeded records,
  exercising `id`, `text`, `boolean` and `datetime` field templates.
- **Detail** (`/admin/flags/checkout-v2`): the same record displayed as a
  definition list, including `code` (JSON) and additional text fields.
- **Failed messages** (`/admin/failed-messages`): 3 seeded failed
  envelopes with **Retry / Dismiss** buttons per row and a **Retry all
  / Purge all** toolbar.

### Caveat — stateless preview

Each `php -S` request rebuilds the in-memory transport from scratch, so
clicking **Retry** returns a 302 + success flash but the same 3
records appear on reload. This is intentional — full stateful demos
ship in `examples/messenger-demo/` (Phase 8). The CSRF round-trip,
redirect, and flash plumbing are exercised on every click.

## Files

| Path | Role |
|---|---|
| `index.php` | Front controller for `php -S` |
| `Kernel.php` | `PreviewKernel` — minimal MicroKernelTrait kernel |
| `Resources.php` | In-memory data source + `FlagsResource` + concrete fields |

The concrete `TextField`/`BooleanField`/etc. live in this preview app
because v0.1 of `polysource/core` ships only the abstract
`FieldInterface` + `FieldTrait`. Dedicated field types arrive in a
later phase.
