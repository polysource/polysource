# Showcase — manual filter test plan

Comprehensive walkthrough of the listing-UX features shipped by
`polysource/filter` and `polysource/easyadmin-filter-bridge` — filters,
chips, saved views, and since v1.1.0 expandable row details — exercised
in the showcase demo (`make showcase`, http://localhost:8084).

Use this when validating a release candidate, or after a refactor that
touches the filter packages, to make sure no UX regression slipped in.
Each test is a 30-second click sequence with a clear pass/fail signal.

> **Automation status**: see [§ Automation](#-automation) at the bottom.
> About 30 of the 47 numbered scenarios below are now covered by the
> showcase E2E suite — 25 files in
> `examples/showcase-demo/tests/Showcase/`, 47 Panther methods plus 24
> WebTestCase methods. Gaps are explicitly flagged so you know when you
> have to walk through manually vs. when
> `make -C examples/showcase-demo panther` is enough.

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

- ⚠️ Still not wired in the showcase (verified 2026-08-07 — no
  `EntityFilter::new()` calls in `src/Controller/Admin/`).
- To exercise, add to `OrderCrudController::configureFilters()`:
  `->add(EntityFilter::new('customer'))`. **Open backlog** — the
  configurator ships and is unit-tested, but no showcase surface
  exercises it end to end.

### A6 — BooleanFilter (`EnhancedBooleanFilterType`)

- ⚠️ Still not wired in the showcase (verified 2026-08-07).
  **Open backlog**, same shape as A5.

### A7 — ComparisonFilter (`EnhancedComparisonFilterType`)

- Implicit — it's the parent of NumericFilter, DateTimeFilter, etc.
- **Indirectly covered by A3 and A4**.

### A8 — ArrayFilter (`EnhancedArrayFilterType`)

- ⚠️ Still not wired in the showcase (verified 2026-08-07).
  **Open backlog**, same shape as A5.

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

## I — Expandable row details (v1.1.0)

Shipped in v1.1.0 (cf. [ADR-033](../adr/0033-expandable-row-details.md)).
On the showcase, the Order listing declares `Polysource::rowDetail()`
in `OrderCrudController::configureFields()` and the panel content
comes from `App\Polysource\RowDetail\OrderRowDetailProvider`
(autoconfigured on `RowDetailProviderInterface`).

### I1 — Chevron renders, details are NOT preloaded

- Page: `/admin/order`
- **Expected**: a chevron control (`.polysource-row-detail-toggle`) in
  the leading cell of every row, `aria-expanded="false"`
- **Expected**: no `.polysource-row-detail-row` in the initial HTML —
  the panel is lazy, nothing is fetched until you click

### I2 — Click expands the row and injects the line items

- Click a chevron
- **Expected**: a detail row appears with
  `data-polysource-row-detail-state="expanded"`, containing the
  provider's template output (the order's line items)
- Click again → the detail row is removed (collapse)

### I3 — No-JS baseline: the chevron is a real link

- Inspect the chevron's `href` → points at
  `/admin/polysource/row-detail/…`
- Navigate that URL directly (or disable JavaScript first)
- **Expected**: the server renders a standalone wrapper page
  (`polysource-row-detail-page`) with the same panel content. The
  chevron is progressive enhancement over a working link, per
  [ADR-027](../adr/0027-progressive-enhancement.md).

### I4 — Nested listing as row detail (`RowDetail::listing()`)

- ⚠️ **Not exercised by the showcase** — `OrderRowDetailProvider`
  returns a template, not a nested listing. The nested-listing path
  (an embedded Polysource listing inside the panel, paged via the
  `rd_page` query param without touching the outer listing's page) is
  covered by package-level tests only:
  `packages/symfony-bundle/tests/Functional/RowDetailNestedListingTest.php`
  and
  `packages/easyadmin-filter-bridge/tests/Unit/Controller/RowDetailControllerListingTest.php`.
- **Open backlog**: add a showcase surface returning
  `RowDetail::listing()` so the manual sweep can cover it too.

### I5 — Per-row permission gate

- The panel controller gates **per record**: the entity (bridge path)
  or the `DataRecord` (native path) is passed to the voter as the
  subject, and a denial raises a 403 before any panel is rendered.
  It fails closed — a declared attribute with no security layer wired
  denies rather than silently allowing.
- ⚠️ **Not exercised by the showcase**: `OrderRowDetailProvider` does
  not override `getPermission()`, which defaults to `null`, so no gate
  fires on `/admin/order`. To test manually, override
  `getPermission(): ?string { return 'ORDER_VIEW'; }` on the provider
  and re-run as `viewer@shop.co`.
- **Open backlog**: give the showcase provider a real attribute so the
  403 path is demoed and E2E-covered.

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

### Existing coverage

25 files in `examples/showcase-demo/tests/Showcase/` — 47 Panther
methods (classes extending `AbstractShowcasePantherTestCase`) plus 24
`WebTestCase` methods. Verified 2026-08-07.

| Test file | Kind | Manual scenarios covered |
|---|---|---|
| `EasyAdminSmokeTest` | WebTestCase | A1 (enhanced text filter renders) |
| `EasyAdminNonRegressionTest` | Panther | EA pagination / sort / search / detail / batch baseline |
| `FilterModalTest` | Panther | C1 + D1 (modal opens, AJAX content), E1 (chip after apply) |
| `TomSelectInteractionTest` | Panther | A2 (ChoiceFilter multi-select via Tom Select) |
| `FilterRoundtripExtendedTest` | WebTestCase | A3 (numeric `>=`), B1, B3, B4, plus TextFilter `like` — the G11 envelope shapes |
| `NotNullFilterChipTest` | WebTestCase | B1 in full (Any / Has value / Empty chips — regression `d70953b`) |
| `ChipInteractionTest` | Panther | E5 (individual X), E6 (Clear all); the X is a plain `<a href>`, which is also the E7 mechanism |
| `SessionPersistenceTest` | WebTestCase | F1 (survives navigation), F2 (explicit reset clears session) |
| `SavedViewDropdownTest` | Panther | G1 (dropdown, seeded views), G3 (apply redirects) |
| `SavedViewSwitchTest` | WebTestCase | G4 (switch A → B on first click — regression `3f91cce`) |
| `SavedViewRoundtripTest` | WebTestCase | G11 partial (Text, Choice multi, BetweenDate) + cross-resource and unknown-id rejection |
| `SavedViewAccessDeniedTest` | WebTestCase | G6 (owner can delete), G7 (cross-user → 403, regression `a6e0cbb`) |
| `PolysourceSavedViewApplyTest` | WebTestCase | H4 (`?view=` redirect on native pages, incl. wrong-resource no-op) |
| `CapabilitiesTest` | Panther | G2 (private scope invisible cross-user), bulk progress JSON, audit detail, custom layout |
| `PermissionsByRoleTest` | WebTestCase | role decision matrix + every role sees the dashboard |
| `JourneyTest` | Panther | login + firewall redirects |
| `PolysourceStandaloneTest` | Panther | H1, H2 (native indexes render, pagination, empty state, detail) |
| `PolysourceFilterModalTest` | Panther | H1, H2, H3 (declared filters exposed on the 3 native resources) |
| `RowDetailExpandTest` | Panther | I1 (lazy chevron), I2 (expand + collapse), I3 (no-JS standalone page) |
| `CmdkPaletteTest` | Panther | Cmd+K palette open / escape |
| `V050TableHelpersTest` | Panther | frozen column, column reorder, quick-filter row, row classes, cell filter menu |
| `V050ColumnVisibilityTest` | Panther | column visibility dropdown |
| `V050PageLevelHelpersTest` | Panther | row density, shortcuts cheat sheet, filter share button |
| `V050BackendIntegrationTest` | Panther | export actions + endpoint, bulk scope toggle, recent records |

### Automation status — the 2026 roadmap is closed

The three-sprint automation roadmap that used to live here is done,
bar one item. For the record, what it asked for and what shipped:

| Planned test | Status |
|---|---|
| `FilterPresetsTest` (A4 DateTime presets) | ❌ **never written** — still the one open gap |
| `SavedViewSwitchTest` (G4) | ✅ shipped |
| `SavedViewAccessDeniedTest` (G7) | ✅ shipped |
| `NotNullFilterChipTest` (B1) | ✅ shipped |
| `TomSelectInteractionTest` (A2) | ✅ shipped |
| `ChipInteractionTest` (E5/E6) | ✅ shipped |
| `SessionPersistenceTest` (F1/F2) | ✅ shipped |
| `FilterRoundtripTest` (per-type matrix) | ✅ shipped as `FilterRoundtripExtendedTest` |
| `PolysourceFilterModalTest` (H1–H3) | ✅ shipped |
| `PolysourceSavedViewApplyTest` (H4) | ✅ shipped |

### Remaining gaps (still manual)

| Scenario | Why it's still manual |
|---|---|
| **A4** — DateTime presets click → input populated | no test written; the highest-value remaining gap |
| **A5 / A6 / A8** — Entity / Boolean / Array filters | not wired into any showcase CRUD, so there is nothing to drive |
| **C2** — accordion groups inside a tab | modal opening is asserted, group structure is not |
| **C3** — per-tab applied-count badge | DOM check, never written |
| **E2 / E3** — chip label + 5-stage value formatting | asserted incidentally by `NotNullFilterChipTest`; no dedicated matrix test |
| **E7** — explicit no-JS toggle | the server-driven `<a href>` path is asserted by `ChipInteractionTest` and `RowDetailExpandTest`, but nothing runs the suite with JS disabled |
| **G5** — save current view | modal + persistence never automated |
| **G8** — clear current view | never written |
| **G9 / G10** — `_t` cache buster + `no-store` headers | never written (G10 needs no browser — a curl assertion would do) |
| **G11** — DateTimeFilter round-trip | the other 6 filter types are covered, this one is not |
| **I4** — nested `RowDetail::listing()` | covered by package tests only, no showcase surface |
| **I5** — per-row permission 403 | showcase provider declares no attribute, so nothing to deny |

### Running the existing Panther suite

```bash
# Boots headless Chrome and runs @group panther in one step
make -C examples/showcase-demo panther

# Fast suite, excludes the panther group
make -C examples/showcase-demo test
```

Under the hood that is:

```bash
docker compose -f examples/showcase-demo/docker-compose.yml --profile e2e up -d chrome
docker compose -f examples/showcase-demo/docker-compose.yml exec -T php \
  php -d memory_limit=2G vendor/bin/phpunit --group panther
```

CI runs the same suite in the `e2e` job of
`.github/workflows/ci.yml`, which boots the full compose stack and
invokes `phpunit -c phpunit.panther-ci.xml --group=panther`. See
`docs/user/cookbook/build-your-own-adapter.md` for the Panther test
pattern.

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
