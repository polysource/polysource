# ADR-014 — DataSource 3-phases lifecycle (deferred to v0.2+)

- **Date** : 2026-05-04
- **Statut** : Accepté — implémentation reportée
- **Décide pour** : v0.2+
- **En lien avec** : [ADR-001 — DataRecord identifier](./0001-data-record-identifier.md), [ADR-002 — DataPage total semantics](./0002-data-page-total-semantics.md), [ADR-013 — `polysource/filter` architecture](./0013-filter-package-architecture.md)

## Contexte

Lors de l'étude privée d'un système legacy interne de listing/filtres en mai 2026, un pattern d'architecture DataSource est apparu intéressant pour Polysource quand le projet ship plus d'un type d'adapter :

> Lifecycle en 3 phases distinctes par DataSource :
> 1. **`Factory`** — instancie un DataSource pour un type donné (`doctrine`, `redis`, `messenger`, `http`, etc.). Reçoit la configuration brute du Resource.
> 2. **`Builder`** — applique les filtres / tri / pagination sur le DataSource. Étape mutable mais cantonnée à un objet de "build context" séparé du DataSource lui-même.
> 3. **`Loader`** — exécute la requête contre la source (SQL, Redis command, HTTP call) et retourne le `DataPage`.
>
> Un service central `DataSourceManager` collecte les Factories tagués par `polysource.data_source.factory` et dispatche par `$type`. `DataSourceLoader` est un dispatcher orthogonal sur la phase 3.

Ce pattern paie quand l'architecture doit composer plusieurs types de sources hétérogènes (un même Resource pourrait pull depuis Doctrine + enrichir depuis HTTP, par exemple) ou quand il faut centraliser le logging / monitoring des phases.

## État actuel (v0.1.0)

`polysource/core` définit `DataSourceInterface` avec 3 méthodes (`find`, `findOne`, `count`). Chaque adapter (actuellement seul `polysource/adapter-messenger`) implémente l'interface en gérant son propre cycle de vie en interne. Pas de Factory, pas de Builder externe, pas de Loader dédié.

C'est une approche **monolithique par adapter** — chaque adapter est self-contained, ses 3 phases sont implicites dans son code.

## Décision

**Reporter le pattern 3-phases à v0.2+.** Documenter ici le blueprint pour qu'un implémenteur futur ait :
- les pré-conditions explicites pour activer le pattern
- la signature des 3 interfaces sketchée
- les alternatives considérées et leurs trade-offs
- les escape hatches pour ne pas se sur-engager prématurément

### Pré-conditions pour activation

Le pattern n'est implémenté que quand **au moins un** de ces déclencheurs apparaît :

1. **Polysource ship 3+ adapters concrets** (Doctrine + Redis + Messenger, par exemple). À 1 adapter, le pattern est de l'over-engineering. À 2, c'est borderline. À 3+, il paie.
2. **Un cas réel de composition multi-source dans un même Resource** apparaît (ex : "lister les commandes Doctrine + enrichir avec le statut de livraison venant d'une API HTTP externe").
3. **Le besoin de monitoring/logging granulaire par phase** se manifeste (ex : tracer le temps passé en Builder vs Loader pour identifier les goulets d'étranglement).

Tant qu'aucun de ces déclencheurs n'est présent, l'approche monolithique actuelle reste préférable.

### Sketch d'interfaces pour l'implémentation future

```php
interface DataSourceFactoryInterface
{
    public function supports(string $type): bool;
    public function create(ResourceDto $resource): DataSourceInterface;
}

interface DataSourceBuilderInterface
{
    public function supports(string $type): bool;
    public function build(DataSourceInterface $source, DataQuery $query): DataSourceContext;
    // DataSourceContext porte les filtres résolus + sort + pagination
    // sans muter le DataSource original.
}

interface DataSourceLoaderInterface
{
    public function supports(string $type): bool;
    public function load(DataSourceContext $context): DataPage;
}

final class DataSourceManager
{
    /** @param iterable<DataSourceFactoryInterface> $factories */
    public function __construct(private iterable $factories) {}
    public function create(ResourceDto $resource): DataSourceInterface;
}
```

Tags DI :
- `polysource.data_source.factory`
- `polysource.data_source.builder`
- `polysource.data_source.loader`

Compiler pass `DataSourceLifecyclePass` indexe par `$type` pour O(1) dispatch.

### Migration depuis l'API actuelle

Quand le pattern est activé en v0.2+, l'`DataSourceInterface` actuel reste valide pour les adapters simples (1-phase implicite). Les adapters complexes opt-in en exposant Factory/Builder/Loader séparément. Pas de breaking change forcé sur l'API existante.

## Alternatives considérées

### A. Implémenter dès v0.1.0

Rejeté pour 3 raisons :

1. **Un seul consommateur (Messenger)** — dessiner une abstraction sur un cas pour qu'elle soit ré-utilisée plus tard est le pattern classique d'over-engineering. Les contraintes du 2e cas (Doctrine ou autre) émergent invariablement avec des subtilités qui forcent un refactor des interfaces.
2. **Coût d'apprentissage gratuit** — un contributeur qui ajoute un nouvel adapter doit comprendre 3 interfaces + 2 compiler passes au lieu de 1 interface. À 1 adapter ce coût est pure friction.
3. **Risque d'API churn** — si on doit refactorer les 3 interfaces en v0.2 quand le 2e adapter arrive avec ses propres contraintes, on cassera le seul consommateur (Messenger) et on aura introduit complexity sans bénéfice.

### B. Pattern alternatif "DataSource fluent"

Une `DataSourceBuilder::for($source)->withFilters()->withSort()->load()` chaîne fluent qui simule les 3 phases sans 3 interfaces. Tentant pour la lisibilité, rejeté parce que la fluentness cache les phases au compiler — le DI ne peut pas dispatcher. C'est une API runtime au lieu d'une architecture compile-time.

### C. Adopter dès maintenant juste la Factory

Implémenter seulement `DataSourceFactoryInterface` v0.1.0, garder Builder/Loader implicites. Tentant parce que la Factory est la moins controversée. Rejeté parce que c'est un demi-pattern : sans dispatch sur les 3 phases, la Factory seule ajoute du code sans bénéfice (le seul adapter Messenger n'a pas besoin d'être instancié par Factory dispatcher — il est unique).

## Conséquences

### Positives

1. **v0.1.0 reste simple** — `DataSourceInterface` à 3 méthodes, 1 adapter, 0 ceremony.
2. **Blueprint disponible** — quand un implémenteur futur ouvre une PR pour ajouter Doctrine, il a les interfaces sketchées et les pré-conditions explicites. Pas de "on improvise".
3. **Pas de breaking change forcé v0.2** — l'opt-in pour les adapters complexes laisse Messenger v0.1 intact.

### Négatives / Trade-offs

1. **Risque que la dette s'accumule silencieusement** — quand le 2e adapter arrivera, le mainteneur peut être tenté de continuer en monolithique pour garder la cohérence avec Messenger. Mitigation : ajouter un test (ou une phpstan rule) en v0.2 qui exige le pattern dès qu'un 2e adapter apparaît.
2. **Lecteurs de l'ADR sans contexte historique** — un nouveau contributeur peut découvrir l'ADR et se demander pourquoi le pattern n'est pas implémenté. Mitigation : la section "Pré-conditions pour activation" est explicite.

## Vérification

L'ADR sera **revisité** quand l'un des déclencheurs apparaît. À ce moment :
- Reprendre le sketch d'interfaces ci-dessus comme point de départ.
- Confronter au cas concret (Doctrine, Redis, autre) pour voir quelles méthodes manquent / sont mal nommées.
- Ouvrir un nouvel ADR (`ADR-XXX — DataSource 3-phases lifecycle activation`) qui mette à jour celui-ci.

## Décision adoptée

Pattern reconnu, blueprint capturé, implémentation reportée. La v0.1.0 ship `polysource/filter` (cf. ADR-013) sans toucher à l'architecture DataSource.
