# polysource/twig-theme

Default Twig theme for [polysource/symfony-bundle](https://github.com/polysource/polysource/tree/main/packages/symfony-bundle/).

This package contains **only templates and CSS** — no PHP. The
`polysource/symfony-bundle` package registers its `templates/` directory
under the `@Polysource` Twig namespace at boot.

## Layout

```
public/
└── polysource.css              — the theme stylesheet

templates/
├── layout.html.twig            — base layout (Bootstrap 5 CDN)
├── index.html.twig             — resource index page
├── detail.html.twig            — single-record detail page
├── error.html.twig             — generic error page
├── paginator.html.twig         — offset + cursor paginators
├── row_detail_page.html.twig   — standalone no-JS row-detail page (ADR-027)
├── _navigation.html.twig       — top header partial
├── _flash.html.twig            — Symfony flash messages partial
├── _filters_form.html.twig     — the "Filters" modal, one row per declared filter
├── _filter_chips.html.twig     — removable pill per active criterion + "Clear all"
├── embed/
│   └── listing.html.twig       — read-only listing embedded in an expanded row detail
└── field/
    ├── text.html.twig
    ├── boolean.html.twig
    ├── datetime.html.twig
    ├── code.html.twig
    ├── id.html.twig
    └── generic.html.twig       — fallback for unconfigured fields
```

## Customising

Override any template by re-registering its name in the host application's
own Twig theme path with higher priority. Polysource resolves
`@Polysource/index.html.twig` so any matching path wins.

For listing tweaks you rarely want the whole file. `index.html.twig`
exposes three named blocks so you can extend it and replace just the part
you care about:

| Block | Wraps |
|---|---|
| `table_body_row` | the entire `<tr>` for one record — the outermost hook |
| `table_row_cells` | only the data `<td>`s |
| `table_row_actions` | only the per-row action buttons cell |

## License

MIT — see [LICENSE](https://github.com/polysource/polysource/blob/main/LICENSE).
See [ATTRIBUTIONS.md](./ATTRIBUTIONS.md) for upstream credits.
