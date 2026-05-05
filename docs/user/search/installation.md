# Installing `polysource/search`

## 1. Composer

```bash
composer require polysource/search
```

Pure-PHP. Twig + Symfony DI deps. The Stimulus controller is
shipped as a JS asset — hosts using AssetMapper / Stimulus Bundle
get the Cmd+K shortcut for free.

## 2. Register the bundle

`config/bundles.php`:

```php
return [
    // …existing bundles…
    Polysource\Search\PolysourceSearchBundle::class => ['all' => true],
];
```

Discoverable via `bin/console polysource:plugins:list`.

## 3. Wire the palette into your layout

```twig
{# templates/admin/base.html.twig #}
<body>
    {{ polysource_search_palette() }}
    {% block sidebar %}…{% endblock %}
    {% block main %}…{% endblock %}
</body>
```

Including the helper once enables Cmd+K everywhere.

## 4. Wire the route

```yaml
# config/routes.yaml
polysource_search:
    resource: '@PolysourceSearchBundle/Controller/'
    type: attribute
```

Smoke test:

```bash
curl 'http://localhost/admin/search?q=test'
# {"query":"test","results":[]}
```

## 5. Register search providers

```yaml
# config/services.yaml
services:
    Polysource\Search\Search\ResourceSearchProvider:
        arguments:
            $resource: '@App\Polysource\OrderResource'
            $urls:     '@Polysource\Bundle\Routing\PolysourceUrlGenerator'
        tags: ['polysource.search.provider']
```

For richer backends, ship a custom provider:

```php
final class MeilisearchOrderProvider implements SearchProviderInterface
{
    public function __construct(private readonly MeilisearchClient $client) {}

    public function getId(): string    { return 'meilisearch:orders'; }
    public function getLabel(): string { return 'Orders'; }

    public function search(string $query, int $limit, float $deadline): array
    {
        $hits = $this->client->index('orders')->search($query, ['limit' => $limit])->getHits();
        return array_map(
            fn (array $hit) => new SearchResult(
                id: 'orders:' . $hit['id'],
                label: $hit['customer_name'],
                href: '/admin/orders/' . $hit['id'],
                resourceName: 'Orders',
                hint: $hit['email'] ?? null,
                score: $hit['_score'] ?? 1.0,
            ),
            $hits,
        );
    }
}
```

```yaml
services:
    App\Search\MeilisearchOrderProvider:
        tags: ['polysource.search.provider']
```

## 6. Permission gate

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/search, roles: ROLE_ADMIN }
```

Per-provider gating happens inside the provider's `search()` —
return `[]` if the current user shouldn't see those results.

## See also

- [walkthrough.md](./walkthrough.md) — UX flow + custom provider patterns.
- [ADR-023](../../adr/0023-global-search-cmdk.md) — design rationale.
