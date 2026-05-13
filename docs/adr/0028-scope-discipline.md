# ADR-028 — Scope discipline : couche UX filter+listing, pas une plateforme d'admin

- **Date** : 2026-05-13
- **Statut** : Accepté
- **Décide pour** : v0.2.0+, **gate de toute proposition de feature**
- **En lien avec** : [ADR-012 — Dual-product positioning](./0012-dual-product-positioning.md), [ADR-013 — Filter package architecture](./0013-filter-package-architecture.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md), [ADR-018 — Admin plugin interface and public contracts](./0018-admin-plugin-interface-and-public-contracts.md), [ADR-027 — Progressive enhancement](./0027-progressive-enhancement.md)

## Contexte

ADR-012 a posé un positionnement dual : `polysource/admin` standalone +
`polysource/easyadmin-filter-bridge` côté EA. Mais "côté EA" n'est pas
borné de façon opératoire — la session de dogfooding du 2026-05-12 a
fait remonter une **dérive de scope** mesurable :

| Feature ajoutée v0.1.x | Verdict après dogfooding |
|---|---|
| `presets` sur DateTimeFilter | Boutons inertes (Stimulus-only, [ADR-027](./0027-progressive-enhancement.md)) + pickers HTML5 natifs font mieux |
| `show_clear` sur DateTimeFilter | EA expose déjà un "Reset" — duplication |
| `quick_ranges` sur NumericFilter | Cas d'usage rare, l'hôte peut ajouter ses propres boutons |
| Stimulus controllers `polysource--filter-presets`, `-quick-ranges` | Suite logique des features ci-dessus |

Le pattern commun : on a ajouté des features parce que techniquement
faisables, sans question préalable *"est-ce que ça fille un vrai gap
EA, ou est-ce qu'on dérive vers une couche d'admin plus large ?"*.

Le risque vital : un projet OSS solo / petite équipe meurt par
**scope creep** avant v1.0. Odoo a 5-10 ans-dev pour soutenir 30
features admin (Kanban, chatter, reporting, etc.). Polysource a 1
mainteneur et ~3-4 mois de runway avant launch. Tenter de matcher
Odoo en surface = livrer 30 features cassées au lieu de 5 features
irréprochables.

Trois directions étaient possibles :

1. **Étendre vers plateforme admin complète** (Kanban, chatter,
   reporting, import en masse, multi-tenant...) — ✗ scope x5,
   maintenance impossible en solo, on perd le différenciateur
   "drop-in EA enhancer".
2. **Rester collé à EA stricto-sensu** — uniquement ce qu'EA expose
   comme extension points officiels. ✗ Trop étroit : on n'aurait pas
   les saved views, le subpanel mode, l'audit log, le bulk-async, qui
   sont précisément la valeur ajoutée.
3. **Discipline narrow + world-class** — un périmètre explicite
   *filter / listing / detail-page UX* avec un gate écrit qui force
   la décision sur chaque proposition future.

## Décision

**Polysource est la couche UX filter+listing+detail-page pour
EasyAdmin.** Rien d'autre. Le scope est défini par inclusion et par
exclusion explicite.

### Gate à appliquer sur toute proposition de feature

Une feature entre dans le scope si et seulement si **les deux**
conditions sont satisfaites :

1. **Elle fille un gap réel de l'UX EA** dans le périmètre
   filter / listing / detail-page (pas un manque inventé, pas une
   duplication d'une feature EA existante).
2. **Elle s'arrête à la frontière UX-layer** — ne réimplemente pas
   un domaine adjacent (BI, CRM, workflow d'équipe, multi-tenant,
   storage / sécurité applicative).

Si la réponse à *"est-ce un gap EA dans le réalm filter / listing /
detail-page UX ?"* est *"non, c'est plutôt de la couche [BI |
CRM | workflow | platform]"*, la feature est **déclinée** avec un
pointeur *"pour X, voir Y"*.

### Périmètre — accepté

Les features ci-dessous sont *in-scope* et soit déjà livrées, soit
planifiées sur la roadmap v0.2 → v0.5 :

**Filtres**
- Types custom qui fillent un gap EA : `BetweenDateFilter`, `InFilter`,
  `NotNullFilter`, `FullTextSearchFilter`
- Chips bar (chip rendering, suppression, formatage cohérent
  table↔chip via `chipFormatter`)
- Session persistence des filtres
- Whitelist `comparisons` par filtre
- `include_null` sur BooleanFilter, `placeholder` sur EntityFilter
- Saved views (dropdown, apply, create, delete, scopes
  PRIVATE / TEAM / PUBLIC) — cf. [ADR-019](./0019-saved-views-architecture.md)
- Tab / group filter organization (filter modal layout)
- Subpanel mode (panneau latéral vs modale centrée)

**Listing (v0.3.0)**
- Column visibility toggle (per-user)
- Export current view (CSV / XLSX honorant filtres + sort + selection)
- Default filter / saved view per user
- Row conditional styles (Twig helper `polysource_row_class`)

**Listing avancé (v0.4.0 — Tier 1 game-changers)**
- Filter from cell value (right-click → "filter where x = this")
- Per-column quick filter row (input row sous les headers)
- Saved column configurations (combinables avec saved views →
  concept *perspective*)
- Cross-page selection + bulk dry-run preview
- Empty state design system (messages contextuels + CTAs)

**Polish (v0.5.0 — Tier 2)**
- Column reordering (drag headers)
- Frozen / sticky columns
- Row density toggle
- Toast notifications pour bulk actions
- Keyboard shortcuts (j/k nav, `/` search, `e` edit, `?` cheat-sheet)
- Recent records dans la palette de commandes
- Filter URL deep linking (token court partageable)
- Bulk action history (extension `polysource/audit` + `polysource/bulk-async`)

**Cross-cutting (sub-packages)**
- `polysource/audit` — audit log action-level
- `polysource/bulk-async` — bulk actions large-volume (Mercure)
- `polysource/workflow-bridge` — intégration Symfony Workflow
- `polysource/search` — command palette + recherche globale
- `polysource/widgets` — smart buttons sur detail page

### Périmètre — décliné de manière permanente

| Feature demandée | Pourquoi déclinée | Pointeur alternatif |
|---|---|---|
| Kanban view | Sub-project 1-3 mois, vue spécialisée hors filter/listing | Package séparé / repo tiers |
| Calendar / Gantt view | Idem Kanban | Bundle dédié type `tattali/calendar-bundle` |
| Pivot view | Outil de BI déguisé | Metabase / Looker / Tableau |
| Graph / Map view | Visualisation spécialisée | Sub-package futur si justifié |
| Chatter / followers / activities | Odoo's mail thread = 50k+ LOC, scope CRM | Sub-package distinct si besoin |
| Reporting / analytics | Territoire BI tool | Metabase / Looker |
| Import en masse (CSV mapping, validation, dedup, relations) | Sub-project complet en lui-même | Bundle dédié `import-bundle` à concevoir |
| Pièces jointes / preview | Domaine *file management* | `vich/uploaderbundle` + `liip/imagine` |
| Field-level perms dans le detail | Domaine Symfony Security | Voters Symfony directs |
| Multi-tenant / row-level security | Architecture hôte | Doctrine filter + Voter côté hôte |
| Quick edit popover | Overlap avec inline cell editing — pick one | Tier 3, scope à trancher |
| Email digests sur saved views | Sub-project notifications | Symfony Mailer + Scheduler côté hôte |
| Schedule bulk action plus tard | Symfony Scheduler le fait nativement | Symfony Scheduler |
| Activity feed temps réel | Sous-feature chatter | Voir "chatter" ci-dessus |

Cette liste n'est pas exhaustive et grandira au fil des demandes. La
règle d'écriture : *"pour X, utiliser Y"*. On ne dit jamais *"on le
fera plus tard"* sans engagement de roadmap ; on dit *"hors scope,
voir Y"*.

### Cas Tier 3 — borné, post-v0.5

Quelques features valent un sub-projet *à condition d'être scopées
strictement* — pas une "couche Polysource native" :

- **Inline cell editing** — *uniquement* text / bool / select. Pas de
  rich-text, pas de relations, pas de file upload. Si le scope dérive
  → décliner.
- **Relations preview** (hover FK pour voir l'entité liée) — read-only,
  pas d'édition.
- **Statusbar** (timeline workflow sur detail page) — read-only,
  consomme l'état Symfony Workflow.
- **Bulk action confirmation détaillée** (montrer N lignes affectées
  + sample) — pas un confirmation modal opinionated, juste un summary.

## Conséquences

### Positives

- **Maintenance soutenable.** Périmètre borné = surface bug bornée =
  1 mainteneur peut faire vivre le projet à v1.0+ sans burn-out.
- **Différenciation claire.** Polysource ≠ Sonata ≠ API Platform ≠
  Odoo. On reste "le drop-in EA enhancer". Argument de vente net.
- **Décision plus rapide sur les feature requests.** Le gate à 2
  questions tranche en 30 secondes. Pas de débats récurrents.
- **Cohérence avec ADR-027.** Une feature "Kanban" forcerait du JS
  riche obligatoire ; rester narrow rend la discipline progressive-
  enhancement tenable.

### Négatives / coûts assumés

- **Refus de demandes communautaires possibles.** Quand v1.0 sera
  publié, des demandes Kanban / chatter / reporting arriveront. La
  réponse sera "hors scope, voir [pointeur]". Frustration côté
  demandeur acceptée — c'est le prix d'un produit qui *finit*.
- **Pas de leverage cross-vente "platform admin complete".** On ne
  vend pas Polysource comme "Odoo en PHP/Sf". Volontaire.
- **Le terme *bridge* dans `polysource/easyadmin-filter-bridge` peut
  sembler étroit** alors que le scope inclut listing/detail. Aucune
  intention de renommer post-v1.0 — le mot *bridge* reste exact (le
  package fait le pont entre `polysource/filter` et EA) ; le scope
  large vit dans les packages voisins (`audit`, `widgets`, `search`).

### Neutres

- **Cette ADR est un document vivant.** Si un sous-domaine décliné
  s'avère, *avec preuve*, être un vrai gap UX-layer et non un domaine
  adjacent, il pourra basculer en *accepté* via une ADR ultérieure
  qui amende celle-ci.
- **Aucun impact sur les contrats publics actuels.** Les retraits
  v0.2.0 (`presets`, `show_clear`, `quick_ranges` + Stimulus
  controllers associés) sont breaking-changes assumés pré-v1.0 ;
  ils seront documentés dans le CHANGELOG mais ne déclenchent pas
  d'ADR séparée — c'est ce document qui les justifie.

## Historique

- 2026-05-13 — rédigée après la session de dogfooding qui a chiffré
  la dérive scope et fait remonter la roadmap Tier 1 / Tier 2 / Tier 3
  explicite, en référence à
  [`project_roadmap_v020_to_v050`](../../README.md).
- 2026-05-13 — amendée : entrée `BetweenDateFilter` retirée de la
  liste de simplification v0.2.0. Investigation lors de l'attaque
  v0.2.0 a montré que la migration `comparisons: ['between']` sur
  EA's `DateTimeFilter` (a) demande une refacto non-triviale (le
  whitelisting d'opérateurs via `comparison_type_options` ne
  s'inherit pas naturellement vers `DateTimeFilterType` /
  `NumericFilterType` qui utilisent `getParent()` plutôt qu'une
  vraie inheritance PHP), et (b) ne reproduit pas le UX clé du
  `BetweenDateFilter` (pas de dropdown vide à "between", juste 2
  pickers). Le custom filter `BetweenDateFilter` reste *in-scope*
  comme gap-filler EA. ADR-028 ne prescrit plus son retrait.
