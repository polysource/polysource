# Saved views walkthrough

Saved views let your users name a particular filter combination,
revisit it from a dropdown, and share it with teammates without
copy/pasting URL fragments. The feature ships in
`polysource/filter` and is consumed identically by the standalone
filter primitive and the EasyAdmin bridge.

This walkthrough covers a vanilla Symfony app. If you are on the
EasyAdmin bridge, the wiring is zero-config and the bridge docs cover
the surrounding column features — see
[../easyadmin-filter-bridge/saved-column-configurations.md](../easyadmin-filter-bridge/saved-column-configurations.md).

## What you get

- A Bootstrap 5 dropdown above (or beside) your filter form
  rendered by the `saved_views_dropdown(resourceName)` Twig
  function.
- Three visibility scopes — `private`, `team`, `public` —
  enforced by a Symfony Voter (`SavedViewVoter`) and
  filtered server-side at list time.
- A modal trigger for "Save current as view" with name + scope
  inputs.
- Per-row delete forms scoped to the owner.
- Default storage on Doctrine ORM via
  `DoctrineSavedViewStorage` — a single table
  (`polysource_saved_views`) with two indexes
  (`resource_name`, `owner_id`).

## When this feature is wired

`polysource/filter` registers the saved-view services
**conditionally** on Doctrine ORM's `EntityManagerInterface`
being available (cf.
[ADR-019](../../adr/0019-saved-views-architecture.md) §4). Hosts
that don't depend on Doctrine see no service registered and the
`saved_views_dropdown()` Twig function is absent — wrap your
template usage in
`polysource_saved_views_available()` (the bridge's helper) or
ship a host-side check if you need graceful degradation.

In a Doctrine-backed Symfony app you don't have to do anything
to enable the wiring — installing `polysource/filter` plus
`doctrine/orm` is enough.

## 1. Create the storage table

The default storage maps the `SavedView` value object to a single
Doctrine entity, `Polysource\Filter\SavedView\Storage\Doctrine\SavedViewRecord`.

Generate a migration the usual way:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

…or, in a demo / dev sandbox, create the schema directly:

```bash
php bin/console doctrine:schema:create
```

The resulting table:

The resulting table (mapped by `SavedViewRecord`):

| Column | Type | Notes |
|---|---|---|
| `id` | `string(64)`, primary key | Host-generated identifier |
| `name` | `string(255)` | View label shown in the dropdown |
| `resource_name` | `string(128)` | Logical resource scope (e.g. `products`, an EA `entityFqcn`) — indexed |
| `owner_id` | `string(128)` | The Symfony user identifier — indexed |
| `scope` | `string(16)` | `private` / `team` / `public` |
| `filters_json` | `text` | Serialised `FilterCollection` criteria — JSON list of `{property, operator, values}` |
| `columns_json` | `text` | JSON `list<string>` of selected columns, in display order |
| `sort_json` | `text` | JSON `map<string, "asc"\|"desc">` |
| `page_size` | `integer` nullable | Rows per page for this view |
| `team_id` | `string(128)` nullable | Required when `scope = team` |
| `is_default` | `boolean` | Personal default (with `role_as_default` null) or role default (with it set) |
| `role_as_default` | `string(64)` nullable | Role this view is the admin-configured default for |
| `column_widths_json` | `text` nullable | JSON `map<string, int>` — column → pixel width override (since v0.5.0; nullable so pre-v0.5.0 rows stay valid without backfill) |

Note the `_json` suffixes: the three payload columns are `text`
holding JSON, not Doctrine `json` columns. `SavedView` (the value
object) exposes them as `$filters`, `$columns`, `$sort` and
`$columnWidths`; `DoctrineSavedViewStorage` does the conversion.

By design, `SavedViewService` exposes no `apply()` — per
[ADR-019](../../adr/0019-saved-views-architecture.md) §6 the host (or
the EasyAdmin bridge) takes a view's filters, columns and sort and
hydrates them into its own form, exactly as it does for URL-driven
filter state.

### v0.5.0 migration (column widths)

The `column_widths_json` column was added in v0.5.0. Existing
deployments need a one-shot `ALTER TABLE`:

```sql
ALTER TABLE polysource_saved_views ADD column_widths_json TEXT DEFAULT NULL;
```

The column is nullable — pre-v0.5.0 rows decode as an empty
width map and continue to behave like before.

## 2. Render the dropdown

Add the call wherever the filter form lives — typically just above
the chips bar:

```twig
{# templates/product/list.html.twig #}
<div class="d-flex justify-content-between mb-3">
    <div>{{ filter_tags(collection, definitions) }}</div>
    <div>{{ saved_views_dropdown('products') }}</div>
</div>
```

The argument (`'products'` here) is the **resource name** —
Polysource scopes saved views per resource. Pick a stable
identifier:
- A logical name like `'products'` or `'orders'`.
- A controller FQCN if you have one resource per controller.
- An EasyAdmin `entityFqcn` (the bridge does this automatically).

The dropdown shows views the current user is allowed to see for
that resource:
- All their `private` views.
- Any `team` view whose `team_id` matches the current user's
  team (resolved via `SavedViewTeamResolverInterface` — see §6).
- All `public` views regardless of owner.

## 3. Wire the apply route

The dropdown's apply links are plain `<a href>` pointing to the
current page with `?view=<id>` appended. Your controller is
responsible for reading that query parameter, hydrating the
filter collection, and replaying the filter URL so the form
inputs hydrate naturally.

```php
final class ProductController extends AbstractController
{
    public function __construct(
        private readonly SavedViewService $savedViews,
        // …
    ) {
    }

    public function list(Request $request): Response
    {
        $viewId = (string) $request->query->get('view', '');
        if ('' !== $viewId) {
            $view = $this->savedViews->load($viewId);
            if (null !== $view) {
                return new RedirectResponse(
                    $request->getPathInfo() . '?' . $this->serializeFilters($view->filters),
                );
            }
        }

        // …regular filter handling on the resulting URL
    }
}
```

`SavedViewService::load()` runs through the voter — a user trying
to load another user's `private` view gets `null` back rather
than a snooping success.

## 4. Wire the save route

The Twig modal (`save_modal.html.twig`) POSTs to a host-defined
route named `polysource_saved_view_create`. A minimal
implementation:

```php
#[Route('/saved-views', name: 'polysource_saved_view_create', methods: ['POST'])]
public function create(Request $request): RedirectResponse
{
    $user = $this->security->getUser();
    if (null === $user) {
        throw $this->createAccessDeniedException();
    }

    $resource = (string) $request->query->get('resource', 'products');
    $name = trim((string) $request->request->get('name', ''));
    $scope = SavedViewScope::tryFrom(
        (string) $request->request->get('scope', 'private'),
    ) ?? SavedViewScope::PRIVATE;
    $filterQs = (string) $request->request->get('filter_querystring', '');

    parse_str($filterQs, $parsed);
    $criteria = $this->buildCriteriaFromUrl((array) ($parsed['filter'] ?? []));

    $view = new SavedView(
        id: Uuid::v7()->toRfc4122(),
        name: $name,
        resourceName: $resource,
        ownerId: $user->getUserIdentifier(),
        scope: $scope,
        filters: new FilterCollection($resource, $criteria),
    );

    try {
        $this->savedViews->save($view);
    } catch (SavedViewDuplicateNameException $e) {
        $this->addFlash('warning', \sprintf(
            'You already have a saved view named "%s".', $e->name,
        ));
        return $this->redirectToRoute('products');
    }

    return $this->redirectToRoute('products', ['view' => $view->id]);
}
```

`SavedViewService::save()` rejects duplicate `(ownerId,
resourceName, name)` triples — surface that to the user via
`SavedViewDuplicateNameException`.

The dropdown also POSTs to `polysource_saved_view_delete`
for the per-row × button — a 3-line endpoint:

```php
#[Route('/saved-views/{id}/delete', name: 'polysource_saved_view_delete', methods: ['POST'])]
public function delete(string $id): RedirectResponse
{
    $this->savedViews->delete($id);
    return $this->redirectToRoute('products');
}
```

## 5. URL-shareable filter state with `buildUrl()`

Sharing a permalink to a *non-saved* filter combination is a
related but distinct feature. `FilterService::buildUrl()` is the
PHP helper:

```php
$url = $this->filters->buildUrl(
    path: $this->generateUrl('products'),
    collection: $collection,
    extraQuery: ['page' => 2],
    formName: 'filter',
);
// /products?page=2&filter[status][operator]==&filter[status][values][0]=active
```

Use this for "copy share link" buttons, dashboard tile deep
links, email reports. The encoding shape is:

```
?<formName>[<property>][operator]=<op>
 &<formName>[<property>][values][0]=<v1>
 &<formName>[<property>][values][1]=<v2>
```

Mirror it in your URL parser when reading filters back. The EA
bridge uses `formName: 'filters'` — pass that explicitly when
generating links into an EA controller.

## 6. Customising team resolution

By default any user is treated as belonging to no team — `team`
views become invisible across all users. Implement
`SavedViewTeamResolverInterface` to wire up your tenancy model:

```php
final class TeamResolver implements SavedViewTeamResolverInterface
{
    public function __construct(private readonly Security $security)
    {
    }

    public function teamIdFor(UserInterface $user): ?string
    {
        if (!$user instanceof AppUser) {
            return null;
        }
        return $user->getCompany()?->getId()?->toRfc4122();
    }
}
```

Symfony's autoconfigure picks it up — no `services.yaml`
ceremony required. Once registered, `team` views become visible
to any user whose `teamIdFor()` matches the saved view's
`team_id`.

## 7. Visibility cheat sheet

| User scope | View scope | Owner match | Team match | Visible? |
|---|---|---|---|---|
| Anyone | `private` | Yes | — | ✅ |
| Anyone | `private` | No | — | ❌ |
| Anyone | `team` | — | Yes | ✅ |
| Anyone | `team` | — | No | ❌ |
| Anyone | `public` | — | — | ✅ |

Edit / delete permissions follow the same pattern via the
voter:

| Action | Owner | Same team | Other |
|---|---|---|---|
| `VIEW` | ✅ | scope=team / public | scope=public |
| `EDIT` | ✅ | ❌ | ❌ |
| `DELETE` | ✅ | ❌ | ❌ |
| `SHARE` | ✅ | ❌ | ❌ |

(The voter is `SavedViewVoter` in `Polysource\Filter\SavedView\Security` — drop in your own if your tenancy model needs sharper rules.)

## 8. Defaults, and what's still out of scope

**Default views are implemented** (since v0.3.0), in two flavours
resolved by `SavedViewService::defaultFor()`:

- **Personal default** — `is_default = true` with `role_as_default`
  null. The owner marks their own view via `markAsDefault()` /
  `unmarkAsDefault()` (both require `EDIT` on the view). At most one
  personal default per user per resource: setting a new one clears
  the flag on that user's other views for the same resource.
- **Role default** — `is_default = true` with `role_as_default` set
  to an attribute. An admin pre-configures it; it applies to every
  user for whom that attribute is granted. Marking a personal default
  never clears a role default — the two are separate policies.

Resolution order on a request: an explicit `?view=<id>` wins; then,
if the URL carries user-applied filters, no default fires at all (and
the session's last-used entry is forgotten); then the current user's
personal default; then a role default whose attribute is granted;
otherwise none.

**Column visibility also ships** — as its own per-user preference
store rather than through saved views. See
[column-preferences.md](./column-preferences.md).

Genuinely out of scope today:

- Export / import (downloading a view as JSON to share across
  environments).
- A maker command for the migration — use
  `doctrine:migrations:diff`.

## See also

- [ADR-019](../../adr/0019-saved-views-architecture.md) — design
  rationale, why we chose Doctrine as the default storage, why
  the team resolver is a separate seam.
- [`filter-standalone-demo`](../../../examples/filter-standalone-demo/README.md) —
  runnable demo that exercises the entire flow with two users
  (alice / bob) and a SQLite database.
