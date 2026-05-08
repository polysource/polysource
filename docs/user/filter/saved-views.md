# Saved views walkthrough

Saved views let your users name a particular filter combination,
revisit it from a dropdown, and share it with teammates without
copy/pasting URL fragments. The feature ships in
`polysource/filter` and is consumed identically by the standalone
filter primitive and the EasyAdmin bridge.

This walkthrough covers a vanilla Symfony app. A dedicated
zero-config EasyAdmin variant page (`docs/user/easyadmin-filter-bridge/saved-views.md`)
will land alongside the bridge cookbook in a later docs sprint.

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

| Column | Type | Notes |
|---|---|---|
| `id` | `string(36)` | UUID v7 generated host-side |
| `name` | `string(120)` | View label shown in the dropdown |
| `resource_name` | `string(120)` | Logical resource scope (e.g. `products`, an EA `entityFqcn`) — indexed |
| `owner_id` | `string(120)` | The Symfony user identifier — indexed |
| `scope` | `string(16)` | `private` / `team` / `public` |
| `team_id` | `string(120)` nullable | Required when `scope = team` |
| `filters` | `json` | Serialised `FilterCollection` payload |
| `columns` | `json` | Reserved for v0.2 (column visibility) |
| `sort` | `json` | Reserved for v0.2 (sort state) |
| `page_size` | `integer` nullable | Reserved for v0.2 |
| `is_default` | `bool` | Reserved for v0.2 (per-role default) |
| `role_as_default` | `string` nullable | Reserved for v0.2 |

The columns/sort/page-size/is-default fields are persisted today
but the dropdown UI only reads `filters`. Hosts experimenting
with column-visibility persistence can populate them; the v0.1
contract does not expose them in the dropdown yet.

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

## 8. Limitations and roadmap

What's intentionally **out of v0.1**:
- Per-role defaults (`isDefault` + `roleAsDefault` fields are
  persisted but ignored by the dropdown).
- Column visibility / sort / page-size persistence (fields exist;
  no UI hook yet).
- Export / import (downloading a view as JSON to share across
  environments).
- A maker command for the migration.

These are tracked under `polysource/filter` v0.2+ — the
storage schema is forward-compatible so you can opt in early.

## See also

- [ADR-019](../../adr/0019-saved-views-architecture.md) — design
  rationale, why we chose Doctrine as the default storage, why
  the team resolver is a separate seam.
- [`filter-standalone-demo`](../../../examples/filter-standalone-demo/README.md) —
  runnable demo that exercises the entire flow with two users
  (alice / bob) and a SQLite database.
