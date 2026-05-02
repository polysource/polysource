# polysource/twig-theme

Default Twig theme for [polysource/symfony-bundle](../symfony-bundle).

This package contains **only templates and CSS** — no PHP. The
`polysource/symfony-bundle` package registers its `templates/` directory
under the `@Polysource` Twig namespace at boot.

## Layout

```
templates/
├── layout.html.twig        — base layout (Bootstrap 5 CDN)
├── index.html.twig         — resource index page
├── detail.html.twig        — single-record detail page
├── error.html.twig         — generic error page
├── paginator.html.twig     — offset + cursor paginators
├── _navigation.html.twig   — top header partial
├── _flash.html.twig        — Symfony flash messages partial
└── field/
    ├── text.html.twig
    ├── boolean.html.twig
    ├── datetime.html.twig
    ├── code.html.twig
    ├── id.html.twig
    └── generic.html.twig   — fallback for unconfigured fields
```

## Customising

Override any template by re-registering its name in the host application's
own Twig theme path with higher priority. Polysource resolves
`@Polysource/index.html.twig` so any matching path wins.

## License

MIT — see [LICENSE](../../LICENSE).
See [ATTRIBUTIONS.md](./ATTRIBUTIONS.md) for upstream credits.
