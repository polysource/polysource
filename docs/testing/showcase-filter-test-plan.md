# Showcase — manual filter test plan

Comprehensive walkthrough of every filter-related feature shipped by
`polysource/filter` and `polysource/easyadmin-filter-bridge`, exercised
in the showcase demo (`make showcase`, http://localhost:8084).

Use this when validating a release candidate, or after a refactor that
touches the filter packages, to make sure no UX regression slipped in.
Each test is a 30-second click sequence with a clear pass/fail signal.

> **Automation status**: see [§ Automation](#-automation) at the bottom.
> 17 of these scenarios are already covered by Panther E2E tests
> (`examples/showcase-demo/tests/Showcase/`). Gaps are explicitly
> flagged so you know when you have to walk through manually vs. when
> `make test-panther` is enough.

---

## Pre-requisites

```bash
make showcase                       # http://localhost:8084
docker compose -f examples/showcase-demo/docker-compose.yml --profile e2e up -d chrome
```

| Login | Pass | Role | Permissions |
|---|---|---|---|
| `admin@shop.co` | `shopco` | `ROLE_ADMIN` | everything, including CSV export + audit purge |
| `ops@shop.co` | `shopco` | `ROLE_OPS` | retry / dismiss / cancel / workflow transition |
| `viewer@shop.co` | `shopco` | `ROLE_VIEWER` | read-only |

Reset DB to fixtures baseline before each full pass:

```bash
docker compose -f examples/showcase-demo/docker-compose.yml exec -T php \
  bin/console doctrine:fixtures:load --no-interaction --env=dev
```

---

## Test format

For each test fill in `✅` / `❌` + a one-line note for failures.

```
A1 ✅
A4 ❌  presets buttons not visible — Chrome 147 / macOS 14
G3 ✅
```

---

## A — Enriched filter form types (Configurators C1–C8)

Each EasyAdmin filter type gets swapped at runtime for an enhanced
version with extra UX (presets, quick ranges, Tom Select, …). Tests
on EA pages.

### A1 — TextFilter (enhanced via `EnhancedTextFilterType`)

- Page: `/admin/customer`
- Open Filters → field `Email`
- **Expected**: standard EA text input, but block prefix
  `polysource_enhanced_text_filter` (verify by inspecting the DOM)
- Type `alice` → Apply → table filtered

### A2 — ChoiceFilter multi-select (Tom Select via `EnhancedChoiceFilterType`)

- Page: `/admin/customer` → Filters → `Country`
- **Expected**: Tom Select dropdown (search + selection chips), not
  EA's native `<select multiple>`
- Pick 2-3 values → Apply
- URL contains `filters[country][value][]=FR&[]=DE`

### A3 — NumericFilter (`EnhancedNumericFilterType`)

- Page: `/admin/product` → Filters → `Price (cents)`
- **Expected**: comparison dropdown (`=`, `≠`, `>`, `<`, `>=`, `<=`,
  `between`) + value input (+ optional `quick_ranges` buttons when
  configured — empty in the current showcase)
- Comparison `>=`, value `5000` → Apply → Products ≥ 50€ only

### A4 — DateTimeFilter with presets ⭐ (`EnhancedDateTimeFilterType`)

- Page: `/admin/order` → Filters → tab **Dates** → `Order date`
- **Expected**: 5 preset buttons visible — **Today / Last 7 days /
  Last 30 days / This month / Custom**
- Click **Last 7 days** → input auto-populated with the date 7 days ago
- Apply → table filtered
- Click **This month** → input populated with the start of the current month

### A5 — EntityFilter (Tom Select via `EnhancedEntityFilterType`)

- ⚠️ Not wired in the current showcase (no `EntityFilter::new()` calls).
- To exercise, add to `OrderCrudController::configureFilters()`:
  `->add(EntityFilter::new('customer'))`. **Skipped for v0.1**.

### A6 — BooleanFilter (`EnhancedBooleanFilterType`)

- ⚠️ Not wired in the current showcase. **Skipped for v0.1**.

### A7 — ComparisonFilter (`EnhancedComparisonFilterType`)

- Implicit — it's the parent of NumericFilter, DateTimeFilter, etc.
- **Indirectly covered by A3 and A4**.

### A8 — ArrayFilter (`EnhancedArrayFilterType`)

- ⚠️ Not wired in the showcase. **Skipped for v0.1**.

---

## B — Custom filters shipped by the bridge

Filters that do NOT exist in EA upstream — added by the bridge.

### B1 — NotNullFilter (tri-state Any / Has value / Empty)

- Page: `/admin/order` → Filters → tab **Lifecycle** → `Has shipped`
- **Expected**: 3 radio buttons — Any / Has value / Empty
- Test "**Has value**" → Apply → orders with `shippedAt IS NOT NULL`
- Test "**Empty**" → orders with `shippedAt IS NULL`
- Test "**Any**" → no filter applied + chip "Has shipped: Any" (commit `d70953b`)
- Variants: `Has refunded` (Order), `Customer: Has phone`

### B2 — BetweenDateFilter (range picker)

- Page: `/admin/order` → Filters → tab **Dates** → `Paid between (range picker)`
- **Expected**: 2 side-by-side inputs `from` + `to`
- Enter 2026-01-01 → 2026-12-31 → Apply
- Chip "Paid between (range picker): 2026-01-01 → 2026-12-31"

### B3 — InFilter (`IS IN [...]` multi-value)

- Page: `/admin/customer` → Filters → `City is one of`
- Enter `Paris, Lyon, Marseille` → Apply
- Variant: `/admin/product` → `SKU is one of` → `WIDGET-1, WIDGET-2`

### B4 — FullTextSearchFilter (cross-cols LIKE)

- Page: `/admin/product` → Filters → `Full-text in description`
- Enter a word from the seed data (e.g. `premium`) → Apply
- **Expected**: table filtered to Products whose description contains the word

---

## C — Modal organization (Markers `Polysource::tab` / `::group`)

### C1 — Tabs

- Page: `/admin/order` → Filters
- **Expected**: modal split into 4 tabs — **Identification / Dates / Money / Lifecycle**
- Click each tab → different content

### C2 — Groups (accordion)

- `/admin/order` → Filters → tab **Dates**
- **Expected**: 2 accordion sections — **Created** + **Paid**
- Each section contains its filters grouped visually

### C3 — Per-tab applied count badge

- Apply a filter inside the "Money" tab (`Total >= 5000`) without closing the modal
- **Expected**: the "Money" tab shows a `1` badge (1 active filter in that tab)

---

## D — UI modes (modal / subpanel / integrated)

### D1 — Modal mode (default on the EA showcase)

- `/admin/order` → click **Filters** button → Bootstrap modal opens
- **Expected**: modal with tabs + groups + form

### D2 — Subpanel mode

- ⚠️ Not enabled on the showcase Order page. Enabled in `examples/easyadmin-bridge-demo`.
- To test: `make demo-bridge` → http://localhost:8081

### D3 — Integrated mode (no EA, `polysource/filter` standalone)

- ⚠️ Not in the showcase. Available via `make demo-filter` (http://localhost:8082)

---

## E — Chips bar

### E1 — Chip render after apply

- `/admin/order` → apply 2-3 filters → Apply
- **Expected**: chips bar above the table with one chip per active filter

### E2 — Chip label = filter's declared label

- Filter `Order date` → chip says "Order date: ..."
- (not "Created at" which would be the default humanisation from the property name)

### E3 — Chip value formatting (5-stage chain)

- ChoiceFilter → translated labels rendered (not DB values)
  - e.g. `status` chip = "Cart, Paid" (not "cart, paid")
- BooleanFilter → "Yes" / "No" (not "1" / "0")
- EntityFilter → entity's `__toString()` (not the ID)
- DateTimeFilter → locally-formatted date
- Custom `chip_formatter` (filter option) → host callable fires at stage 1

### E4 — NotNullFilter "Any" chip (commit `d70953b`)

- Covered by B1 — "Has shipped: Any" chip must render

### E5 — Individual chip X remove

- With multiple filters active → click the X on one chip
- **Expected**: only that filter is removed, the others stay

### E6 — "Clear all"

- With multiple filters active → click **Clear all** at the end of the bar
- **Expected**: every filter removed, URL clean

### E7 — Chip remove without JS (server-driven fallback)

- DevTools → Cmd+Shift+P → "Disable JavaScript"
- Reload → apply filters → click X on a chip
- **Expected**: still works (the X is a server-driven `<a href>`)

---

## F — Session persistence

### F1 — Filter survives navigation

- `/admin/order` → apply a filter → Apply
- Navigate to `/admin/customer`, then click "Orders" in the menu again
- **Expected**: back on `/admin/order` with the previous filters restored

### F2 — Reset detection

- URL `/admin/order?filters[status]=paid` (filtered)
- Navigate to clean URL `/admin/order` (no filter)
- **Expected**: session entry cleared, lands on `/admin/order` without filters

---

## G — Saved views

### G1 — View list per scope

- Login `admin@shop.co` → `/admin/order` → open the "Saved views" dropdown
- **Expected**: 4 seeded views (all scope=public)
- Login `ops@shop.co` → same 4 views visible

### G2 — Private view invisible to others

- `admin@shop.co` → `/admin/polysource/audit-log` → dropdown
- **Expected**: sees "Admin actions" (private, owner=admin)
- Logout → `ops@shop.co` → same page → dropdown
- **Expected**: "Admin actions" NOT visible

### G3 — Apply view → redirect to filter URL on first click ⭐

- `admin@shop.co` → `/admin/order` → dropdown → click "Late deliveries"
- **Expected**: URL becomes `?filters[status][value][]=paid&[]=preparing`,
  table filtered
- (commits `3f91cce` + `548ea66` — bug fixed pre-v0.1.0)

### G4 — Switch view A → view B on first click ⭐ (commit `3f91cce`)

- Click "Late deliveries" → filtered
- Click "Paid Orders" → URL becomes `?filters[status][value][]=paid`
- **Expected**: no need to click twice

### G5 — Save current view

- Apply a custom filter → click "Save current" in the dropdown
- Modal opens → name + scope (Private / Team / Public) → Save
- **Expected**: new view appears in the dropdown

### G6 — Delete view (owner only)

- Dropdown → click the X next to a view you own
- Confirm → view deleted

### G7 — Cross-user delete denied (commit `a6e0cbb` — typed exception)

- Login `ops@shop.co`
- Try POST `/admin/saved-views/sv-audit-admin/delete` (admin's view)
  - via DevTools, copy the delete form of one of ops' views, swap `id`
- **Expected**: clean 403 (not a 500 stack trace)

### G8 — "Clear current view" link

- Apply a view → URL `?filters=...` → dropdown shows that view as active
- Click "Clear current view"
- **Expected**: URL becomes clean `/admin/order`, dropdown back to default state

### G9 — Cache buster `_t` (commit `cd2a7be`)

- Inspect the dropdown HTML
- **Expected**: each saved-view link has a unique `&_t=<digits>` param
- Not present in the URL after redirect (stripped by the apply listeners)

### G10 — `no-store` headers on 302 (commit `cd2a7be`)

```bash
curl -sI -b /tmp/admin.txt 'http://localhost:8084/admin/order?view=sv-late-deliveries' | grep -i cache
# → Cache-Control: no-store, no-cache, must-revalidate
```

### G11 — Roundtrip per filter type ⭐ (critical test)

For each wired filter, save + replay:

| Filter | Save criterion | Expected replay |
|---|---|---|
| TextFilter | `reference contains "ORD"` | Apply view → input populated |
| ChoiceFilter multi | `status in [paid, preparing]` | Multi-select restored (commit `548ea66`) |
| NumericFilter | `totalCents >= 5000` | Comparison + value restored |
| DateTimeFilter | `createdAt > 2026-01-01` | Date restored |
| BetweenDateFilter | `paidAt between [X, Y]` | Range restored |
| InFilter | `city in [Paris, Lyon]` | Multi-value restored |
| NotNullFilter | "Has value" | `value=not_null` restored |

---

## H — Polysource standalone (non-EA resources)

### H1 — Filters on `/admin/polysource/audit-log`

- Click the "Filters" button on the page
- **Expected**: modal with 5 filters (occurredAt, actorId, resourceName, actionName, outcome)
- Apply → table filtered

### H2 — Filters on `/admin/polysource/bulk-jobs`

- **Expected**: 4 filters (actorId, status, createdAt, resourceName)

### H3 — Filters on `/admin/polysource/failed-messages`

- **Expected**: custom filters from `FailedMessageResource::configureFilters()`

### H4 — Saved views on Polysource standalone (commit `30697ca`)

- `admin@shop.co` → `/admin/polysource/audit-log` → dropdown
- Apply "Admin actions" (private admin view)
- **Expected**: URL becomes `?filter[actor_id][value]=admin@shop.co`
  (Polysource shape, not the EA shape)

---

## 5-minute quick sweep

```
1. admin@shop.co
   - /admin/order : Filters (modal opens, tabs visible)
                    saved views (dropdown, click "Late deliveries"), apply
                    chip bar visible, click X
   - /admin/customer : NotNullFilter "Has phone" tri-state
   - /admin/product : FullTextSearchFilter
   - /admin/polysource/audit-log : private "Admin actions" visible
2. Logout → ops@shop.co
   - /admin/polysource/audit-log : "Admin actions" NOT visible
3. Logout → viewer@shop.co
   - read-only check
```

---

## 🤖 Automation

**Yes**, all these tests are automatable with **Symfony Panther**
(headless Chrome). The showcase already has the infra — see
`examples/showcase-demo/tests/Showcase/AbstractShowcasePantherTestCase.php`.

### Existing coverage (~17 scenarios covered)

| Test file | Manual scenarios covered |
|---|---|
| `EasyAdminSmokeTest` | A1 (renders) |
| `EasyAdminNonRegressionTest` | EA pagination/sort/search baseline |
| `FilterModalTest` | C1 (modal opens), E1 (chip after apply) |
| `SavedViewDropdownTest` | G1 (dropdown opens, seeded views), G3 (apply redirects) |
| `SavedViewRoundtripTest` | G11 partial (TextFilter, ChoiceFilter multi, BetweenDateFilter) |
| `CapabilitiesTest` | G2 (private scope cross-user invisible) |
| `PermissionsByRoleTest` | role matrix |
| `JourneyTest` | login + auth flow |
| `PolysourceStandaloneTest` | H1, H2 (resource indexes render) |

### Priority gaps to automate

| Test | Priority | Notes |
|---|---|---|
| **A4 (DateTime presets)** | HIGH | preset clicks populate the input |
| **A2 (ChoiceFilter Tom Select)** | HIGH | multi-select Stimulus interaction |
| **B1 (NotNullFilter tri-state + Any chip)** | HIGH | pin regression of `d70953b` |
| **G4 (switch view A → B first click)** | HIGH | pin regression of `3f91cce` |
| **G7 (cross-user delete 403)** | HIGH | pin regression of `a6e0cbb` |
| **G8 (clear current view)** | MEDIUM | regression of `0dc1f55` |
| **G9 (cache buster `_t`)** | MEDIUM | DOM attribute check |
| **G10 (no-store headers)** | LOW | curl-able, no browser needed |
| **E5/E6 (chip X / Clear all)** | MEDIUM | DOM interaction Stimulus + server-driven |
| **E7 (no-JS fallback)** | LOW | requires Panther JS toggling |
| **F1/F2 (session persist)** | MEDIUM | multi-request Panther |
| **C3 (per-tab badge count)** | LOW | DOM check |

### Running the existing Panther suite

```bash
# Start the (headless) Chrome service
docker compose -f examples/showcase-demo/docker-compose.yml --profile e2e up -d chrome

# Run the Panther tests
docker compose -f examples/showcase-demo/docker-compose.yml exec -T php \
  /repo/examples/showcase-demo/vendor/bin/phpunit --group panther
```

CI YAML already wired (cf. `.github/workflows/showcase.yml` future). See
`docs/user/cookbook/build-your-own-adapter.md` for the Panther test pattern.

### Suggested automation roadmap

**Sprint 1 — close the critical regressions (~4h)**:
1. `FilterPresetsTest` — DateTime presets click + value injection
2. `SavedViewSwitchTest` — switch view A → view B on first click (regression `3f91cce`)
3. `SavedViewAccessDeniedTest` — POST cross-user → assertResponseStatusCodeSame(403)
4. `NotNullFilterChipTest` — "Any" chip render (regression `d70953b`)

**Sprint 2 — broader coverage (~4h)**:
5. `TomSelectInteractionTest` — multi-select Tom Select via Selenium WebDriver
6. `ChipInteractionTest` — X individual + Clear all + no-JS fallback
7. `SessionPersistenceTest` — navigate away + come back → filters restored
8. `FilterRoundtripTest` (per-filter-type matrix) — extend `SavedViewRoundtripTest`
   with NumericFilter, NotNullFilter, InFilter, FullTextSearchFilter,
   EnhancedTextFilter min_length

**Sprint 3 — cover the Polysource standalone resources (~2h)**:
9. `PolysourceFilterModalTest` — `/admin/polysource/audit-log` + bulk-jobs +
   failed-messages → modal opens, filter applies
10. `PolysourceSavedViewApplyTest` — `?view=<id>` redirect on Polysource
    native pages

**Total ~10h** for full E2E coverage. Once in place, `make test-panther`
gives non-regression confidence on this entire plan.

### New Panther test setup (template)

```php
<?php
// examples/showcase-demo/tests/Showcase/MyNewTest.php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * @group panther
 */
final class MyNewTest extends AbstractShowcasePantherTestCase
{
    public function testMyFeature(): void
    {
        $this->loginViaForm('admin@shop.co');
        $client = $this->browser();

        $client->request('GET', '/admin/order');
        // Wait for the saved-views dropdown
        $client->wait(5)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(
                WebDriverBy::cssSelector('.polysource-saved-views .dropdown-toggle'),
            ),
        );

        // ... Selenium interactions ...
        $client->findElement(WebDriverBy::cssSelector('...'))->click();

        // Assert
        self::assertStringContainsString('expected', $client->getCurrentURL());
    }
}
```

---

## Reference — fixes from this session

Bugs found and fixed pre-v0.1.0 on the filter / saved-views stack:

| Commit | Description |
|---|---|
| `cd2a7be` | `no-store` headers + `_t` cache buster (defence-in-depth against HTTP cache) |
| `548ea66` | `eq` op on multi-select ChoiceFilter → array shape `value[]=` |
| `3f91cce` | 2 subscribers conflict (root cause of the "first click does nothing" bug) |
| `d70953b` | Chip rendered for NotNullFilter "Any" |
| `30697ca` | `SavedViewApplyListener` moved to `polysource/filter` package |
| `a6e0cbb` | `SavedViewAccessDeniedException` typed (vs bare `RuntimeException`) |
| `aaebd5a` | Resource overrides re-wire `$actions` tagged_iterator |
| `5e082d1` `100bb0c` | `EasyAdminAuditSubscriber` captures full diff on EA edits |
| `b2672c7` | UTF-8 BOM + newline collapse in CSV export |
| `f597b7d` | Stub message handlers so "Retry all" succeeds visibly |

Each fix should have a regression test in the list above.
