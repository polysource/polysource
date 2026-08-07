# Search walkthrough — first hour

After [installation](./installation.md), this page walks the
operator UX flow + how to extend with a custom provider.

## 1. Open the palette

Three triggers (configurable via the Stimulus controller `endpoint`
+ `debounce` data values):
- `Cmd+K` (macOS)
- `Ctrl+K` (Linux/Windows)
- `/` GitHub-style — fires only when the focus isn't already inside
  a text input

## 2. Type and navigate

The Stimulus controller debounces 150ms before hitting
`GET /admin/search?q=<term>`. As results arrive:
- Up / Down arrow keys move the active row
- Enter navigates to `result.href`
- Esc / click outside close the palette

Results are grouped by `resourceName` (the provider's `getLabel()`).

## 3. Behind the scenes

```
User types "ORD-42"
   ↓
Stimulus debounce 150ms
   ↓
GET /admin/search?q=ORD-42
   ↓
SearchController → SearchAggregator
   ↓
Fan-out across every tagged provider:
   - resource:orders        (ResourceSearchProvider)   ← Doctrine LIKE
   - resource:audit-log     (ResourceSearchProvider)   ← Doctrine LIKE
   - meilisearch:customers  (custom MeilisearchProvider)
   ↓
Merge + sort by score desc + return JSON
   ↓
Stimulus renders rows grouped by resource
```

Three contention layers protect UX:
- per-provider limit (default 5)
- 250ms total wall-clock budget — slow provider gets cut
- try/catch contention — one bad provider doesn't break the palette

## 4. Custom provider for "actions"

Linear-style actions in the palette ("create new order",
"navigate to settings") fit the same provider contract:

```php
final class GlobalActionsProvider implements SearchProviderInterface
{
    public function getId(): string    { return 'actions'; }
    public function getLabel(): string { return 'Actions'; }

    public function search(string $query, int $limit, float $deadline): array
    {
        $actions = [
            ['Create order', '/admin/orders/new'],
            ['Settings', '/admin/settings'],
            ['Audit log', '/admin/audit-log'],
        ];
        $matches = [];
        foreach ($actions as [$label, $href]) {
            if (false !== stripos($label, $query)) {
                $matches[] = new SearchResult(
                    id: 'action:' . md5($label),
                    label: $label,
                    href: $href,
                    resourceName: 'Actions',
                    icon: 'arrow-right',
                    score: 2.0, // boost actions above plain records
                );
            }
        }
        return $matches;
    }
}
```

Score = 2.0 floats the actions to the top of the merged list.

## 5. Pagination

The palette does not paginate, by design — it is a jump-to, not a
search-results page. `SearchAggregator` caps each provider at
`DEFAULT_PER_PROVIDER_LIMIT` (5) and the whole aggregation at
`DEFAULT_TOTAL_BUDGET_MS` (250 ms) of wall clock; providers that
blow the deadline return what they have. Both are constructor
arguments, so raise them in DI if your palette should show more.
There is no "Show all N results" link and none is planned; wire the
query into your own results page if you need one.

## 6. CDN / staging tip

Hosts behind a CDN sometimes cache `/admin/search` aggressively.
Either:
- Set `Cache-Control: no-store` in your kernel.response listener
  for the `polysource_search` route, OR
- Send a uniqueness query param from the Stimulus controller
  (`&_=<timestamp>`) — patch the `fetch()` call in
  `cmdk_controller.js`

## See also

- [installation.md](./installation.md) — wiring basics.
- [ADR-023](../../adr/0023-global-search-cmdk.md) — design
  rationale. The follow-ups it sketches (a Meilisearch search
  add-on, action history, fuzzy-match coordination) have not
  shipped; `SearchProviderInterface` is the extension point if you
  want to build one yourself.
