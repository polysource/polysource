# ADR-013 — `polysource/filter` architecture (form/datasource separation + 3-tag pipeline)

- **Date** : 2026-05-04
- **Statut** : Accepté
- **Décide pour** : v0.1.0+
- **Étend** : [ADR-012 — Positionnement dual-produit](./0012-dual-product-positioning.md) §Phase 9.5

## Contexte

ADR-012 a entériné la création d'un package `polysource/filter` standalone, indépendant d'EasyAdmin, qui doit servir de primitive partagée par tous les bridges (EasyAdmin v0.1, Sonata / API Platform / `polysource/admin` plus tard). La Phase 9.7 a livré `polysource/easyadmin-filter-bridge` en premier (drop-in EA enhancement). La Phase 9.5 livre maintenant la primitive sous-jacente.

Une revue d'un système legacy interne de listing/filtres (étudié en privé en mai 2026, hors scope public) a confirmé un pattern qui a fait ses preuves sur ~10 ans dans cet outil :

1. **Séparation explicite form-spec / datasource-spec** dans la déclaration d'un filtre. Le filtre porte simultanément :
   - sa face **présentation** : le `FormType` à utiliser, ses options de rendu, son label, le groupe auquel il appartient.
   - sa face **application** : les colonnes ciblées, l'opérateur SQL/NoSQL, la stratégie de transformation de la valeur.
   Les deux faces vivent dans le même objet de configuration mais sont consommées par des couches différentes (le moteur de rendu vs le moteur d'application en QueryBuilder/JSON/etc.).

2. **Pipeline 3-phases** (3 tags DI distincts, 3 interfaces) :
   - `mapper` : request ↔ Filter model (hydratation bidirectionnelle). C'est le pont entre la requête HTTP brute et le value object immutable.
   - `formatter` : Filter model → texte humain (chip label, tooltip, audit log). C'est ce qui produit "Date: 01/05 → 03/05" quand un filtre date est actif.
   - `renderer` : Filter model → FormType FQCN (le widget de saisie effectif). C'est ce qui décide quel composant Symfony Form afficher.

3. **Persistance session par `collectionId`**, déjà extraite dans la v0.1 EasyAdmin bridge mais à généraliser pour qu'elle soit indépendante d'EasyAdmin.

4. **Modes UI multiples** : afficher les filtres dans un modal classique (`integrated`) ou dans un panneau latéral coulissant (`subpanel`) selon les besoins UX du host. Multi-group → tabs.

5. **Hydratation bidirectionnelle** Form ↔ Model via `FormEvents::PRE_SET_DATA` (model → form data) + `FormEvents::PRE_SUBMIT` (form data → model). Le pattern résout proprement l'asymétrie entre la représentation interne (immutable value object) et la représentation HTTP (array brut).

## Décision

`polysource/filter` v0.1.0 ship :

### Domain models (immutables, `final readonly class`)

- `FilterCriterion` — `(property: string, operator: string, values: array)`. Un filtre actif unique.
- `FilterCollection` — `(id: string, criteria: FilterCriterion[])`. Le scope de session est l'`id` (ex : hash du FQCN d'un Resource ou d'un CrudController).
- `FilterDefinition` — schéma déclaratif d'un filtre disponible : `(name, label, group?, formSpec, datasourceSpec)` où `formSpec` et `datasourceSpec` sont des structures distinctes consommées par le renderer et l'applier respectivement.

### Pipeline (3 interfaces + DI tags)

```php
interface FilterMapperInterface {
    public function supports(string $name): bool;
    public function fromRequest(array $rawValues): FilterCriterion;
    public function toFormData(FilterCriterion $criterion): array;
}

interface FilterFormatterInterface {
    public function supports(string $name): bool;
    public function format(FilterCriterion $criterion): string;
}

interface FilterRendererInterface {
    public function supports(string $name): bool;
    public function getFormType(): string;  // FQCN of a Symfony FormType
}
```

Tags DI :
- `polysource.filter.mapper`
- `polysource.filter.formatter`
- `polysource.filter.renderer`

Compiler pass `PipelineCompilerPass` collecte les services tagués et les indexe par `name` pour O(1) lookup.

Default impls livrés pour 7 types : `text`, `numeric`, `datetime`, `boolean`, `choice`, `array`, `entity`. Les hosts surchargent en exposant un service avec le même `name`.

### `FilterService` agnostique d'EasyAdmin

```php
final class FilterService {
    public function save(FilterCollection $collection): void;
    public function load(string $id): ?FilterCollection;
    public function clear(string $id): void;
}
```

Implémentation interne : `RequestStack::getSession()` + clé `polysource.filter.{xxh128(id)}`. Aucune dépendance à EasyAdmin. Le bridge EasyAdmin (Phase 9.7, déjà livré) sera **migré** pour déléguer à `FilterService` au lieu de réimplémenter la logique session.

### Hydratation bidirectionnelle

`FilterCollectionType` (Symfony FormType) écoute :
- `FormEvents::PRE_SET_DATA` → applique `FilterMapperInterface::toFormData()` sur chaque criterion existant pour pré-remplir le formulaire.
- `FormEvents::PRE_SUBMIT` → applique `FilterMapperInterface::fromRequest()` sur les données soumises pour reconstruire le `FilterCollection` immutable.

Le contrat des mappers est unidirectionnel à chaque appel mais la combinaison fromRequest+toFormData préserve l'invariant `fromRequest(toFormData(c)) == c` pour tout `c`.

### Modes UI (2 pour v0.1.0)

- **`integrated`** (défaut) : chips bar visible au-dessus de la liste + click sur "Filters" ouvre un modal Bootstrap classique. Les groupes deviennent des sections `<details>` collapsible dans le modal.
- **`subpanel`** : chips bar + panneau latéral coulissant déclenché par "Filters". Les groupes deviennent des **tabs**. Recommandé quand le host veut éviter la modale (pages où les filtres restent visibles en permanence pendant l'analyse).

Le mode `simple` (popover inline sur chip click, sans modal/panel) du système legacy étudié est **reporté à v0.2** — l'éditeur inline demande un design d'interaction qui mérite son propre cycle.

### Multi-group

Optionnel, opt-in :

```php
FilterDefinition::new('createdAt', 'Created at')->setGroup('Timestamps')
```

- Groupe `null` (défaut) → tous les filtres en flat.
- Groupe non-null → en mode `integrated` : `<details>` par groupe ; en mode `subpanel` : un tab par groupe.

### Stimulus controllers (2)

- `polysource--filter-chips` : `removeChip` (supprime un criterion + soumet la form), `expandOverflow` (toggle "+N more" quand >7 chips actives).
- `polysource--filter-subpanel` : `open`/`close` (avec backdrop class sur `<body>`), `switchTab` (multi-group), focus trap, ESC to close.

Distribution via Symfony UX manifest (`assets/package.json`) avec `name` override explicite — convention validée sur Phase 9.7.

## Conséquences

### Positives

1. **Bridge EasyAdmin allégé** : `FilterSessionPersistenceSubscriber` perd sa logique session et délègue à `FilterService`. La duplication entre les futurs bridges (Sonata, API Platform, `polysource/admin`) est éliminée d'avance.
2. **Testabilité** : chaque phase du pipeline (mapper, formatter, renderer) est testable isolément. Pas besoin de booter Symfony Form pour tester un mapper.
3. **Extensibilité par tag DI** : un host qui veut un mapper custom (ex : opérateur `geolocation_within`) ajoute un service tagué `polysource.filter.mapper`. Pas de fork, pas de PR upstream.
4. **Multi-group sans complexité v0.1** : la séparation `formSpec` / `datasourceSpec` permet d'ajouter le grouping comme un attribut transverse, sans toucher la pipeline.
5. **Hydratation bidirectionnelle propre** : Form ↔ Model découplé via le mapper, contrairement aux approches qui font les deux via le même FormType (couplage rigide).

### Négatives / Trade-offs

1. **3 interfaces vs 1** : un host qui ajoute un nouveau type de filtre custom doit implémenter 3 services au lieu d'un FormType unique. Atténué par les default impls qui couvrent les 7 cas standards et par le fait que les 3 interfaces sont petites (1-2 méthodes chacune).
2. **Compiler pass requis** : la résolution des pipelines à compile-time nécessite `PipelineCompilerPass`. Coût de maintenance modéré, bien isolé. Ne ralentit pas le runtime (lookup O(1) après compilation).
3. **Session lock-in** : le `FilterService` v0.1 utilise `SessionInterface` exclusivement. Si un host veut persister en BDD ou Redis, il devra ré-implémenter `FilterService`. Acceptable à v0.1, élargissement possible v0.2 (interface `FilterStorageInterface`).
4. **Symfony deps non triviales** : `polysource/filter` requiert `symfony/form`, `symfony/http-foundation`, `symfony/event-dispatcher`, `twig/twig`. Plus lourd que `polysource/core` (zéro dep Symfony). Justifié par le scope (`polysource/filter` est un produit Symfony, pas une primitive PHP pure).

## Alternatives considérées

### A. Tout pousser dans `polysource/core`

Rejeté : `core` doit rester sans dépendance Symfony (cf. the project context file §Architectural constraints). Y mettre `FilterService` (qui dépend de Session) violerait la règle.

### B. Un seul `FilterTypeInterface` qui combine mapper/formatter/renderer

Tentant pour la simplicité (1 service par type au lieu de 3), mais bloque la composition. Avec 3 interfaces, un host peut ré-utiliser le mapper standard tout en customisant le formatter (ex : afficher les chips dans une langue/format précis sans toucher l'hydratation). Un mega-interface obligerait à hériter et tout réimplémenter.

### C. Reporter `subpanel` à v0.2

Tentant pour réduire le scope de v0.1.0. Rejeté parce que le mode `subpanel` est l'un des principaux différenciateurs UX par rapport au modal stock EasyAdmin (filtres visibles + modifiables pendant l'analyse de la liste). Sans lui, `polysource/filter` devient un "EasyAdmin filters mais en plus joli" sans valeur structurelle.

### D. Skip la séparation form-spec / datasource-spec

Tentant parce que la v0.1 ne ship pas encore de moteur d'application autre qu'EasyAdmin Doctrine. Rejeté parce que la séparation est ce qui rend `polysource/filter` ré-utilisable hors EasyAdmin. Un Filter qui mélange les deux faces est un Filter EasyAdmin — pas une primitive.

## Vérification technique

- Le compiler pass exposé par `PolysourceFilterExtension` est testé en isolation via `ContainerBuilder` minimal (cf. pattern Phase 9.7 `BridgeAutoConfigurationTest`).
- L'hydratation bidirectionnelle est testée via `Forms::createFormFactoryBuilder()` + un `FilterCollectionType` réel + des fixtures de criteria pour chaque type standard.
- Les modes Twig (`integrated`, `subpanel`) sont testés via le pattern `FormThemeRenderingTest` de Phase 9.7 : env Twig + Form en mémoire, asserts sur le HTML produit.
- Les Stimulus controllers ont leur suite Vitest dédiée (jsdom + pinned Application cleanup), même pattern que Phase 9.7.
- Test E2E Playwright contre l'app de démo : assert qu'apply→reset→navigate-back→apply-different cycle fonctionne avec les chips qui se mettent à jour.

## Décision adoptée

ADR signé sur cette base. Implémentation suit en 12 tâches structurées (cf. tasks #54-#65 dans le plan de la session 2026-05-04). Pattern legacy restant (lifecycle 3-phases DataSource) reporté à v0.2+ avec son propre ADR-014.
