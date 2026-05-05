# ADR-022 — Dashboard widgets (Phase 14)

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : Phase 14 — quatrième capability ADR-017 cherry-picks
- **En lien avec** : [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md), [ADR-018 — Plugin architecture](./0018-admin-plugin-interface-and-public-contracts.md)

## Contexte

[ADR-017](./0017-cherry-picking-from-filament-study.md) §5 retient
**dashboard widgets** parmi les 7 features Phase 10+ : exposer des
KPI (counters), top-N (lists), micro-graphes (sparklines)
agencés en une page de dashboard composable, indépendante des
ressources individuelles.

Les admins Symfony actuels (EasyAdmin, Sonata) demandent aux hosts
de hand-roller ces widgets en HTML/Twig, sans contrat partagé. Pas
de registry, pas de réutilisation entre dashboards, pas de
catalogue de widgets pré-fabriqués.

Filament (Laravel) a popularisé l'idée d'un widget = composant
self-contained avec un `getViewData()` + un template — c'est la
forme qu'on adopte ici, en restant Symfony-natif (Twig au lieu de
Blade, DI au lieu de Service Container Laravel).

## Décision

### 1. Package séparé `polysource/widgets`

Comme audit + workflow-bridge : opt-in. Vit dans son propre package
pour ne pas alourdir `polysource/symfony-bundle`. Pas de dépendance
exotique — juste Twig + Symfony DI.

```
polysource/widgets
├── src/
│   ├── PolysourceWidgetsBundle.php           # #[AsPlugin]
│   ├── DependencyInjection/
│   │   └── PolysourceWidgetsExtension.php
│   ├── Widget/
│   │   ├── WidgetInterface.php               # contrat unique
│   │   ├── AbstractWidget.php                # base class
│   │   ├── CounterWidget.php                 # single-metric KPI
│   │   ├── ListWidget.php                    # top-N records
│   │   └── ChartWidget.php                   # sparkline data points
│   ├── Dashboard/
│   │   ├── Dashboard.php                     # value object: name + rows of widgets
│   │   └── DashboardRegistry.php             # name → Dashboard map
│   └── Twig/
│       └── DashboardExtension.php            # render_widget() + render_dashboard()
├── Resources/
│   ├── config/services.php
│   └── views/
│       ├── dashboard.html.twig               # layout
│       └── widgets/
│           ├── counter.html.twig
│           ├── list.html.twig
│           └── chart.html.twig
└── composer.json
```

### 2. `WidgetInterface` — contrat minimal

5 méthodes : `getId()`, `getTitle()`, `getColumnSpan()`,
`getTemplate()`, `getViewData(): array`. Chaque widget concrète
remplit le contrat.

### 3. `CounterWidget` — KPI single-metric

Use case canonique : "Failed messages today: 47", "MRR: $42,300",
"P95 latency: 124ms". Champs : `value`, `unit?`, `trend?`,
`palette?`. Rendering Bootstrap badge + grosse valeur.

### 4. `ListWidget` — top-N records

Use case : "Last 5 failed messages", "Top 10 customers by MRR",
"Recent compliance audits". Champs : `items: list<T>`,
`labelFn: callable`, `hrefFn: callable|null`.

### 5. `ChartWidget` — sparkline data

v0.1 ship une représentation **textuelle** (table des points).
Le rendering JS (Chart.js / Apache ECharts) ship en v0.2 — hosts
qui veulent du graphique vrai overrident le template
`widgets/chart.html.twig`.

### 6. `Dashboard` — composition

Une `Dashboard` est purement déclarative (immutable VO) — pas de
logique. `name` + `title` + `rows: list<list<WidgetInterface>>`.
Les widgets dedans sont déjà construits par le host.

### 7. `DashboardRegistry` — map global

Hosts taguent leurs `Dashboard` instances avec
`polysource.widgets.dashboard` et appellent
`$registry->get('overview')` depuis leur controller.

### 8. Twig — `render_dashboard($name)` + `render_widget($widget)`

`render_widget` est un wrapper Twig qui charge `widget.template`
avec `widget.viewData` + `widget` lui-même comme contexte. Layout
basé sur Bootstrap 5 grid (rows + col-md-N).

### 9. Plugin (ADR-018)

`PolysourceWidgetsBundle` implémente `AdminPluginInterface` via
`#[AsPlugin(name: 'polysource/widgets')]` — surfaces in
`polysource:plugins:list`.

## Conséquences

### Positives

- Audience produit débloquée : tout admin métier veut un dashboard
  d'accueil (KPIs principaux, top-N, dérives récentes).
- Composition simple : un Dashboard = une liste de rangées de
  widgets.
- Réutilisation transverse : un même `CounterWidget` peut apparaître
  sur plusieurs dashboards (le widget est un service).

### Négatives / coûts

- 1 nouveau package à maintenir.
- L'API graphique reste textuelle en v0.1 — frustrant pour les
  utilisateurs qui s'attendent à des charts. Mitigation : doc
  explicit sur le path d'override + roadmap v0.2 ChartJS.
- Pas de drag-drop / reordering UI en v0.1 : les dashboards sont
  composés en code.

## Plan d'implémentation (Phase 14)

| Batch | Tâches |
|---|---|
| **A** | `WidgetInterface` + `AbstractWidget` + `CounterWidget` + `ListWidget` + `ChartWidget` + tests |
| **B** | `Dashboard` VO + `DashboardRegistry` + tests |
| **C** | `DashboardExtension` Twig + 4 templates + bundle/extension/services.php + plugin manifest |
| **D** | `docs/user/widgets/` |

Estimation : **~1.5 semaines** (le plus rapide des 5 cherry-picks
restants — pas de DB, pas d'async).

## Alternatives rejetées

### A. Forcer une intégration `Polysource\Core\Resource`

Une widget = un sous-cas de Resource avec un seul record. Rejeté :
les widgets ne match pas le contrat ResourceInterface.

### B. Stocker la composition en DB plutôt qu'en code

Permet drag-drop UI mais ajoute une table + sérialisation. Rejeté
pour v0.1 : code-first plus simple à raisonner.

### C. Embedder un graph engine JS dans le bundle

Trop d'opinion front-end imposée. v0.1 textuel, hosts override.

## Suite (post-v0.1)

- v0.2 : `ChartJsExtension` rendant les ChartWidget en vrais graphes.
- v0.2 : `DashboardLayoutInterface` pour drag-drop UI persistance.
- v0.3 : `MercureWidget` qui auto-refresh via SSE.
- v0.3 : `WidgetFilterInterface` pour widgets filtrables (date
  range picker au-dessus du dashboard).
