# ADR-023 — Global search + Cmd+K palette (Phase 15)

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : Phase 15 — cinquième capability ADR-017 cherry-picks
- **En lien avec** : [ADR-001 — DataSource](./0001-datasource-contract.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md), [ADR-018 — Plugin architecture](./0018-admin-plugin-interface-and-public-contracts.md)

## Contexte

[ADR-017](./0017-cherry-picking-from-filament-study.md) §7 retient
**global search + Cmd+K palette** parmi les 7 features Phase 10+ :
permettre à un opérateur de taper `Cmd+K` (ou `Ctrl+K` sur Linux/Win),
saisir un mot-clé, et naviguer vers n'importe quelle ressource —
indépendamment des menus de navigation.

Ce pattern est devenu standard dans les outils opérationnels
modernes (Linear / Notion / GitHub / Stripe Dashboard). Symfony
admin ecosystem n'offre rien d'équivalent : EasyAdmin ship un input
search par-CRUD, Sonata aussi ; aucun n'agrège transversalement.

## Décision

### 1. Package séparé `polysource/search`

Comme audit / workflow-bridge / widgets : opt-in.

```
polysource/search
├── src/
│   ├── PolysourceSearchBundle.php
│   ├── DependencyInjection/PolysourceSearchExtension.php
│   ├── Search/
│   │   ├── SearchResult.php
│   │   ├── SearchProviderInterface.php
│   │   ├── ResourceSearchProvider.php
│   │   └── SearchAggregator.php
│   ├── Controller/SearchController.php
│   └── Twig/SearchExtension.php
├── Resources/
│   ├── config/services.php
│   └── views/_palette.html.twig
├── assets/controllers/cmdk_controller.js
└── composer.json
```

### 2. `SearchResult` VO

Champs : `id`, `label`, `href`, `resourceName`, `icon?`, `hint?`,
`score` (float, default 1.0). Score libre — chaque provider
décide de son barème (cosine similarity Meilisearch, BM25 SQL,
Levenshtein, ou 1.0 par défaut). L'aggregator trie sur ce score
sans normalisation.

### 3. `SearchProviderInterface`

3 méthodes : `getId(): string`, `getLabel(): string`,
`search(query, limit, deadline): list<SearchResult>`. Le
`deadline` est un timestamp `microtime(true)` que le provider
DOIT respecter ; les providers responsables coupent proprement,
les providers déficients sont post-deadline coupés par
l'aggregator (résultats jetés silencieusement).

### 4. `ResourceSearchProvider` — default

Pour toute ressource Polysource, instance auto-registrée par un
compiler pass. Délègue **complètement** à `DataSource::search()`
+ `DataQuery::searchText` — chaque adapter sait déjà ce que
"matcher un texte" veut dire dans son propre univers (LIKE en SQL,
fulltext Doctrine, native-search Meilisearch, …). Aucune logique
de matching ré-implémentée côté search package.

### 5. `SearchAggregator`

Fan-out sur tous les providers tagués
`polysource.search.provider`. Choix :
- `perProviderLimit = 5` : assez pour dépasser le bruit, scannable.
- `totalBudgetMs = 250` : palette UX fluide. Provider lent qui
  dépasse coupe les autres providers en aval ; à v0.2 on
  parallélisera via Symfony Process / fibers si la latence devient
  un sujet.
- Try/catch contention : un provider qui throw est silencieusement
  skippé.

### 6. `SearchController`

Route `GET /admin/search?q=…` → JSON `{query, results: [...]}`.
JSON-only par design — la palette est rendue côté client par
Stimulus, le serveur ship que les data.

### 7. Stimulus `cmdk_controller.js`

- bind `Cmd+K` / `Ctrl+K` (et `/` GitHub-style)
- ouvre overlay avec input + liste de résultats
- debounce 150ms avant fetch
- groupe les résultats par `resource`
- navigue au `Enter` ou clic
- ferme à `Esc` / clic-dehors

Hosts sans Stimulus ship leur propre client (route JSON stable).

### 8. Twig `SearchExtension`

`polysource_search_palette()` retourne le HTML de l'overlay
vide. Hosts incluent dans leur layout admin :

```twig
{{ polysource_search_palette() }}
```

### 9. Plugin (ADR-018)

`#[AsPlugin(name: 'polysource/search')]`.

## Conséquences

### Positives

- UX moderne attendue.
- Audience SRE / on-call : palette = navigation fastest-path.
- Architecture extensible : Meilisearch / Algolia / Elasticsearch
  bridges deviennent des `SearchProviderInterface` distincts —
  futurs add-ons `polysource/search-meilisearch`, etc.
- Réutilisation : `ResourceSearchProvider` exploite la `DataSource`
  déjà câblée par chaque adapter.

### Négatives / coûts

- Latence palette dépendante de la vitesse des `DataSource`. Pour
  Messenger / Redis non-indexed, le `searchText` peut être lent.
  Mitigation : deadline budget + doc explicit + suggestion
  Meilisearch provider pour ressources critiques.
- Pas d'historique de recherche v0.1. Post-v0.1 si demande.
- Stimulus dependency front : hosts sans AssetMapper / Stimulus
  Bundle perdent le shortcut Cmd+K mais peuvent toujours hit la
  route JSON.

## Plan d'implémentation (Phase 15)

| Batch | Tâches |
|---|---|
| **A** | `SearchResult` VO + `SearchProviderInterface` + `SearchAggregator` + tests |
| **B** | `ResourceSearchProvider` + `SearchController` + tests |
| **C** | `SearchExtension` Twig + `_palette.html.twig` + `cmdk_controller.js` Stimulus + bundle + extension + services.php + plugin manifest |
| **D** | `docs/user/search/` |

Estimation : ~2 semaines.

## Alternatives rejetées

### A. Forcer un index Meilisearch / Elasticsearch d'office

Refuser de marcher sans backend dédié = barrière à l'entrée trop
haute. `ResourceSearchProvider` qui tape la `DataSource` directe
est "bon enough" pour les volumes initiaux, et facilement
remplaçable.

### B. Server-Side Rendering du palette

Plus simple côté ops mais moins fluide UX (full page reload).
Cmd+K est une attente client-side.

### C. Provider unique cross-resource

Rejeté : empêche les hosts de remplacer un provider par-resource
(Meilisearch pour `orders` mais Doctrine LIKE pour `audit-log`).

### D. Pagination dans le palette

Rejeté pour v0.1 : 25 résultats max suffit pour 90% des cas.

## Migration / breaking changes

Aucun. Nouveau package opt-in.

## Suite (post-v0.1)

- v0.2 : `polysource/search-meilisearch` add-on.
- v0.2 : palette pagination + history (recent searches in localStorage).
- v0.3 : actions globales dans le palette (Linear-style "create new
  order" raccourci-clavier).
- v0.3 : cross-resource fuzzy match coordination (BM25 normalisé).
