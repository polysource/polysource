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
| `admin@shop.co` | `shopco` | `ROLE_ADMIN` | tout, dont CSV export + audit purge |
| `ops@shop.co` | `shopco` | `ROLE_OPS` | retry / dismiss / cancel / workflow transition |
| `viewer@shop.co` | `shopco` | `ROLE_VIEWER` | lecture seule |

Reset DB to fixtures baseline before each full pass:

```bash
docker compose -f examples/showcase-demo/docker-compose.yml exec -T php \
  bin/console doctrine:fixtures:load --no-interaction --env=dev
```

---

## Test format

For each test you fill in `✅` / `❌` + a one-line note for failures.

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

### A1 — TextFilter (enrichi via `EnhancedTextFilterType`)

- Page: `/admin/customer`
- Open Filters → field `Email`
- **Expected**: input texte EA standard, mais block prefix
  `polysource_enhanced_text_filter` (vérifier en inspectant le DOM)
- Tape `alice` → Apply → table filtered

### A2 — ChoiceFilter multi-select (Tom Select via `EnhancedChoiceFilterType`)

- Page: `/admin/customer` → Filters → `Country`
- **Expected**: Tom Select dropdown (recherche + chips de sélection),
  pas le `<select multiple>` natif d'EA
- Sélectionne 2-3 valeurs → Apply
- URL contient `filters[country][value][]=FR&[]=DE`

### A3 — NumericFilter (`EnhancedNumericFilterType`)

- Page: `/admin/product` → Filters → `Price (cents)`
- **Expected**: comparison dropdown (`=`, `≠`, `>`, `<`, `>=`, `<=`,
  `between`) + value input (+ optional `quick_ranges` buttons si
  configurés — vide dans la showcase actuelle)
- Comparison `>=`, value `5000` → Apply → Products ≥ 50€ only

### A4 — DateTimeFilter avec presets ⭐ (`EnhancedDateTimeFilterType`)

- Page: `/admin/order` → Filters → tab **Dates** → `Order date`
- **Expected**: 5 boutons preset visibles : **Today / Last 7 days /
  Last 30 days / This month / Custom**
- Click **Last 7 days** → input rempli auto avec date d'il y a 7 jours
- Apply → table filtered
- Click **This month** → input rempli avec début du mois courant

### A5 — EntityFilter (Tom Select via `EnhancedEntityFilterType`)

- ⚠️ Pas wired dans la showcase actuelle (aucun `EntityFilter::new()`).
- Pour tester, ajouter dans `OrderCrudController::configureFilters()` :
  `->add(EntityFilter::new('customer'))`. **Skipped pour v0.1**.

### A6 — BooleanFilter (`EnhancedBooleanFilterType`)

- ⚠️ Pas wired dans la showcase actuelle. **Skipped pour v0.1**.

### A7 — ComparisonFilter (`EnhancedComparisonFilterType`)

- Implicite — c'est le parent de NumericFilter, DateTimeFilter, etc.
- **Couvert indirectement par A3 et A4**.

### A8 — ArrayFilter (`EnhancedArrayFilterType`)

- ⚠️ Pas wired dans la showcase. **Skipped pour v0.1**.

---

## B — Filtres custom shipped par le bridge

Filtres qui n'existent PAS dans EA upstream — ajoutés par le bridge.

### B1 — NotNullFilter (tri-state Any / Has value / Empty)

- Page: `/admin/order` → Filters → tab **Lifecycle** → `Has shipped`
- **Expected**: 3 radio buttons : Any / Has value / Empty
- Test "**Has value**" → Apply → orders avec `shippedAt IS NOT NULL`
- Test "**Empty**" → orders avec `shippedAt IS NULL`
- Test "**Any**" → pas de filtre + chip "Has shipped: Any" (commit `d70953b`)
- Variantes : `Has refunded` (Order), `Customer: Has phone`

### B2 — BetweenDateFilter (range picker)

- Page: `/admin/order` → Filters → tab **Dates** → `Paid between (range picker)`
- **Expected**: 2 inputs side-by-side `from` + `to`
- Saisir 2026-01-01 → 2026-12-31 → Apply
- Chip "Paid between (range picker): 2026-01-01 → 2026-12-31"

### B3 — InFilter (`IS IN [...]` multi-value)

- Page: `/admin/customer` → Filters → `City is one of`
- Saisir `Paris, Lyon, Marseille` → Apply
- Variante : `/admin/product` → `SKU is one of` → `WIDGET-1, WIDGET-2`

### B4 — FullTextSearchFilter (cross-cols LIKE)

- Page: `/admin/product` → Filters → `Full-text in description`
- Saisir un mot du contenu (ex: `premium`) → Apply
- **Expected**: table filtrée aux Products dont la description contient ce mot

---

## C — Organisation modale (Markers `Polysource::tab` / `::group`)

### C1 — Tabs

- Page: `/admin/order` → Filters
- **Expected**: modal divisée en 4 tabs : **Identification / Dates / Money / Lifecycle**
- Click chaque tab → contenu différent

### C2 — Groups (accordion)

- `/admin/order` → Filters → tab **Dates**
- **Expected**: 2 sections accordion : **Created** + **Paid**
- Chaque section contient ses filtres groupés visuellement

### C3 — Per-tab applied count badge

- Applique un filtre dans tab "Money" (`Total >= 5000`) sans fermer le modal
- **Expected**: tab "Money" affiche un badge `1` (1 filtre actif dans ce tab)

---

## D — Modes UI (modal / subpanel / integrated)

### D1 — Modal mode (default sur la showcase EA)

- `/admin/order` → click bouton **Filters** → modal Bootstrap s'ouvre
- **Expected**: modal avec tabs + groups + form

### D2 — Subpanel mode

- ⚠️ Pas activé sur la showcase Order. Activé dans `examples/easyadmin-bridge-demo`.
- Pour tester : `make demo-bridge` → http://localhost:8081

### D3 — Integrated mode (no EA, `polysource/filter` standalone)

- ⚠️ Pas dans la showcase. Disponible via `make demo-filter` (http://localhost:8082)

---

## E — Chips bar

### E1 — Chip render après apply

- `/admin/order` → applique 2-3 filtres → Apply
- **Expected**: barre chips au-dessus de la table avec un chip par filtre actif

### E2 — Chip label = label déclaré du filtre

- Filter `Order date` → chip dit "Order date: ..."
- (pas "Created at" qui serait l'humanisation par défaut depuis le property name)

### E3 — Chip value formatting (5-stage chain)

- ChoiceFilter → labels traduits affichés (pas valeurs DB)
  - ex: `status` chip = "Cart, Paid" (pas "cart, paid")
- BooleanFilter → "Yes" / "No" (pas "1" / "0")
- EntityFilter → `__toString()` de l'entité (pas l'ID)
- DateTimeFilter → date formatée localement
- Custom `chip_formatter` (option du filtre) → callable de l'host fire en stage 1

### E4 — NotNullFilter "Any" chip (commit `d70953b`)

- Couvert par B1 — chip "Has shipped: Any" doit s'afficher

### E5 — Chip X remove individuel

- Avec plusieurs filtres → click le X d'un chip
- **Expected**: ce filtre seul retiré, les autres restent

### E6 — "Clear all"

- Avec plusieurs filtres → click **Clear all** en bout de barre
- **Expected**: tous les filtres retirés, URL clean

### E7 — Chip remove sans JS (server-driven fallback)

- DevTools → Cmd+Shift+P → "Disable JavaScript"
- Reload → applique filtres → click X d'un chip
- **Expected**: ça marche quand même (le X est un `<a href>` server-driven)

---

## F — Session persistence

### F1 — Filter survie navigation

- `/admin/order` → applique un filtre → Apply
- Navigue vers `/admin/customer`, puis re-clique "Orders" dans le menu
- **Expected**: retour sur `/admin/order` avec les filtres précédents restaurés

### F2 — Reset detection

- URL `/admin/order?filters[status]=paid` (filtré)
- Navigue vers URL clean `/admin/order` (no filter)
- **Expected**: session entry cleared, retour `/admin/order` sans filtres

---

## G — Saved views

### G1 — Liste des views par scope

- Login `admin@shop.co` → `/admin/order` → ouvre dropdown "Saved views"
- **Expected**: 4 vues seedées (toutes scope=public)
- Login `ops@shop.co` → mêmes 4 vues visibles

### G2 — Private view invisible aux autres

- `admin@shop.co` → `/admin/polysource/audit-log` → dropdown
- **Expected**: voit "Admin actions" (private, owner=admin)
- Logout → `ops@shop.co` → même page → dropdown
- **Expected**: "Admin actions" PAS visible

### G3 — Apply view → redirect au filter URL au 1er clic ⭐

- `admin@shop.co` → `/admin/order` → dropdown → click "Late deliveries"
- **Expected**: URL devient `?filters[status][value][]=paid&[]=preparing`,
  table filtrée
- (commits `3f91cce` + `548ea66` — bug fixé pre-v0.1.0)

### G4 — Switch view A → view B au 1er clic ⭐ (commit `3f91cce`)

- Click "Late deliveries" → filtré
- Click "Paid Orders" → URL devient `?filters[status][value][]=paid`
- **Expected**: pas besoin de cliquer 2 fois

### G5 — Save current view

- Applique un filtre custom → click "Save current" dans dropdown
- Modal s'ouvre → name + scope (Private / Team / Public) → Save
- **Expected**: nouvelle view dans dropdown

### G6 — Delete view (owner only)

- Dropdown → click le X à côté d'une view dont tu es owner
- Confirm → view supprimée

### G7 — Delete cross-user denied (commit `a6e0cbb` — typed exception)

- Login `ops@shop.co`
- Tente POST `/admin/saved-views/sv-audit-admin/delete` (admin's view)
  - depuis DevTools, copie le form de delete d'une view d'ops, change `id`
- **Expected**: 403 propre (pas un 500 stack trace)

### G8 — "Clear current view" link

- Apply une view → URL `?filters=...` → dropdown affiche cette view active
- Click "Clear current view"
- **Expected**: URL devient clean `/admin/order`, dropdown back to default state

### G9 — Cache buster `_t` (commit `cd2a7be`)

- Inspect dropdown HTML
- **Expected**: chaque link saved-view a `&_t=<chiffres>` unique
- Pas dans l'URL après redirect (strippé par les apply listeners)

### G10 — Headers `no-store` sur 302 (commit `cd2a7be`)

```bash
curl -sI -b /tmp/admin.txt 'http://localhost:8084/admin/order?view=sv-late-deliveries' | grep -i cache
# → Cache-Control: no-store, no-cache, must-revalidate
```

### G11 — Roundtrip per filter type ⭐ (test critique)

Pour chaque filtre wired, save + replay :

| Filter | Save criterion | Expected replay |
|---|---|---|
| TextFilter | `reference contains "ORD"` | Apply view → input rempli |
| ChoiceFilter multi | `status in [paid, preparing]` | Multi-select restauré (commit `548ea66`) |
| NumericFilter | `totalCents >= 5000` | Comparison + value restaurés |
| DateTimeFilter | `createdAt > 2026-01-01` | Date restaurée |
| BetweenDateFilter | `paidAt between [X, Y]` | Range restauré |
| InFilter | `city in [Paris, Lyon]` | Multi-value restauré |
| NotNullFilter | "Has value" | `value=not_null` restauré |

---

## H — Polysource standalone (resources non-EA)

### H1 — Filtres sur `/admin/polysource/audit-log`

- Click bouton "Filters" sur la page
- **Expected**: modal avec 5 filtres (occurredAt, actorId, resourceName, actionName, outcome)
- Apply → table filtrée

### H2 — Filtres sur `/admin/polysource/bulk-jobs`

- **Expected**: 4 filtres (actorId, status, createdAt, resourceName)

### H3 — Filtres sur `/admin/polysource/failed-messages`

- **Expected**: filtres customs depuis `FailedMessageResource::configureFilters()`

### H4 — Saved views sur Polysource standalone (commit `30697ca`)

- `admin@shop.co` → `/admin/polysource/audit-log` → dropdown
- Apply "Admin actions" (private admin view)
- **Expected**: URL devient `?filter[actor_id][value]=admin@shop.co`
  (Polysource shape, pas EA shape)

---

## Quick balayage en 5 minutes

```
1. admin@shop.co
   - /admin/order : Filters (modal opens, tabs visible)
                    saved views (dropdown, click "Late deliveries"), apply
                    chip bar visible, click X
   - /admin/customer : NotNullFilter "Has phone" tri-state
   - /admin/product : FullTextSearchFilter
   - /admin/polysource/audit-log : private "Admin actions" visible
2. Logout → ops@shop.co
   - /admin/polysource/audit-log : "Admin actions" PAS visible
3. Logout → viewer@shop.co
   - read-only check
```

---

## 🤖 Automation

**Yes**, all these tests are automatable with **Symfony Panther**
(headless Chrome). The showcase already has the infra — see
`examples/showcase-demo/tests/Showcase/AbstractShowcasePantherTestCase.php`.

### Coverage actuelle (~17 scenarios couverts)

| Test file | Scenarios manuels couverts |
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

### Lacunes prioritaires à automatiser

| Test | Priorité | Notes |
|---|---|---|
| **A4 (DateTime presets)** | HIGH | preset clicks remplissent l'input |
| **A2 (ChoiceFilter Tom Select)** | HIGH | multi-select interaction Stimulus |
| **B1 (NotNullFilter tri-state + Any chip)** | HIGH | régression `d70953b` à pin |
| **G4 (switch view A → B 1er clic)** | HIGH | régression `3f91cce` à pin |
| **G7 (cross-user delete 403)** | HIGH | régression `a6e0cbb` à pin |
| **G8 (clear current view)** | MEDIUM | régression `0dc1f55` |
| **G9 (cache buster `_t`)** | MEDIUM | check DOM attribute |
| **G10 (no-store headers)** | LOW | curl-able, pas besoin de browser |
| **E5/E6 (chip X / Clear all)** | MEDIUM | DOM interaction Stimulus + server-driven |
| **E7 (no-JS fallback)** | LOW | nécessite désactivation JS Panther |
| **F1/F2 (session persist)** | MEDIUM | multi-request Panther |
| **C3 (per-tab badge count)** | LOW | DOM check |

### Comment lancer la suite Panther actuelle

```bash
# Démarre le service Chrome (headless)
docker compose -f examples/showcase-demo/docker-compose.yml --profile e2e up -d chrome

# Lance la suite Panther
docker compose -f examples/showcase-demo/docker-compose.yml exec -T php \
  /repo/examples/showcase-demo/vendor/bin/phpunit --group panther
```

CI YAML déjà wired (cf. `.github/workflows/showcase.yml` futur). Voir `docs/user/cookbook/build-your-own-adapter.md` pour le pattern Panther test.

### Roadmap d'automatisation suggérée

**Sprint 1 — combler les régressions critiques (~4h)** :
1. `FilterPresetsTest` : DateTime presets click + value injection
2. `SavedViewSwitchTest` : switch view A → view B 1er clic (régression `3f91cce`)
3. `SavedViewAccessDeniedTest` : POST cross-user → assertResponseStatusCodeSame(403)
4. `NotNullFilterChipTest` : "Any" chip render (régression `d70953b`)

**Sprint 2 — couvrir le reste (~4h)** :
5. `TomSelectInteractionTest` : multi-select Tom Select via Selenium WebDriver
6. `ChipInteractionTest` : X individual + Clear all + no-JS fallback
7. `SessionPersistenceTest` : navigate away + come back → filtres restaurés
8. `FilterRoundtripTest` (per-filter-type matrix) : compléter `SavedViewRoundtripTest` avec NumericFilter, NotNullFilter, InFilter, FullTextSearchFilter, EnhancedTextFilter min_length

**Sprint 3 — couvrir les Polysource standalone resources (~2h)** :
9. `PolysourceFilterModalTest` : `/admin/polysource/audit-log` + bulk-jobs + failed-messages → modal opens, filter applies
10. `PolysourceSavedViewApplyTest` : `?view=<id>` redirect sur Polysource native pages

**Total ~10h** pour couverture E2E complète. Une fois en place, `make test-panther` te donne la garantie de non-régression sur tout ce plan.

### Setup d'un nouveau test Panther (template)

```php
<?php
// examples/showcase-demo/tests/Showcase/MonNouveauTest.php

declare(strict_types=1);

namespace App\Tests\Showcase;

use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;

/**
 * @group panther
 */
final class MonNouveauTest extends AbstractShowcasePantherTestCase
{
    public function testMaFonctionnalite(): void
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

        // ... interactions Selenium ...
        $client->findElement(WebDriverBy::cssSelector('...'))->click();

        // Assert
        self::assertStringContainsString('expected', $client->getCurrentURL());
    }
}
```

---

## Référence — fixes de cette session

Bugs trouvés et corrigés pre-v0.1.0 sur la stack filter / saved-views :

| Commit | Description |
|---|---|
| `cd2a7be` | Headers `no-store` + cache buster `_t` (défense en profondeur cache HTTP) |
| `548ea66` | `eq` op sur multi-select ChoiceFilter → array shape `value[]=` |
| `3f91cce` | 2 subscribers en conflit (cause racine du "1er clic ne marche pas") |
| `d70953b` | Chip pour NotNullFilter "Any" |
| `30697ca` | `SavedViewApplyListener` moved to `polysource/filter` package |
| `a6e0cbb` | `SavedViewAccessDeniedException` typed (vs bare RuntimeException) |
| `aaebd5a` | Resource overrides re-wire `$actions` tagged_iterator |
| `5e082d1` `100bb0c` | EasyAdminAuditSubscriber capture diff complet sur EA edits |
| `b2672c7` | UTF-8 BOM + newline collapse sur CSV export |
| `f597b7d` | Stub message handlers pour que "Retry all" succeed visiblement |

Chaque fix doit avoir un test de non-régression dans la liste ci-dessus.
