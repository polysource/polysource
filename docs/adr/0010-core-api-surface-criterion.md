# ADR-010 — Core API surface criterion

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+
- **Remplace** : critère "stop-the-line" §14 du plan de développement initial (`core` < 12 classes/interfaces publiques)

## Contexte

Le `development-plan.md` §14 incluait à l'origine un critère "stop-the-line" :

> Le `core` a-t-il moins de 12 classes/interfaces publiques ? (sinon → trop d'API surface)

Ce critère visait à **résister au scope creep** et à éviter le piège « God Object » (Sonata `AdminInterface` à 80 méthodes — cf. analyse interne). Intention saine.

À la fin de la Phase 1, `packages/core` contenait **26 types publics**. v0.7 a coupé `BatchableDataSourceInterface` (ADR-011 A1, 26→25) et ajouté l'enum `FilterOperator` (ADR-011 A4, 25→26). v0.7.1 ajoute 5 concrete field types pour combler le gap dogfood "configureFields(): [] → rows vides" (26→31) :

| Catégorie | Nombre | Détail |
|---|---|---|
| Interfaces | 9 | `DataSourceInterface`, `WritableDataSourceInterface`, `ResourceInterface`, `FieldInterface`, `FilterInterface`, `ActionInterface`, `InlineActionInterface`, `BulkActionInterface`, `PermissionInterface` |
| Value objects (`final readonly class`) | 9 | `DataQuery`, `DataPage`, `DataRecord`, `DataPayload`, `Pagination`, `FilterCriterion`, `ActionResult`, `FieldDto`, `FilterDto` |
| Concrete field types | 5 | `TextField`, `IdField`, `BooleanField`, `DateTimeField`, `CodeField` (v0.7.1 — chacun = 1 wrapper de 5 lignes sur `FieldTrait` + 1 template `@Polysource/field/*.html.twig`) |
| Exceptions (`final class` ou `class`) | 3 | `DataSourceException`, `ResourceNotFoundException`, `UnsupportedOperationException` |
| Abstract base class | 1 | `AbstractResource` |
| Enum | 2 | `SortDirection`, `FilterOperator` (v0.7 — per ADR-011 A4) |
| Trait | 1 | `FieldTrait` |
| Constants holder | 1 | `Polysource` |
| **Total** | **31** (v0.7.1) | |

**26 > 12.** Si on applique le critère littéralement, c'est un échec. Mais la lecture qualitative est différente.

## Analyse de la décomposition

Examinons si chaque type est justifié.

### Les 10 interfaces respectent l'ISP

- `DataSourceInterface` (3 méthodes) — read-only minimal
- `WritableDataSourceInterface extends DataSourceInterface` (+ 3 méthodes) — opt-in pour l'écriture

> Note historique : une 3e interface `BatchableDataSourceInterface`
> (`findMany`) a existé en v0.1 → v0.6 mais a été coupée en v0.7
> (ADR-011 A1) car aucun adapter ne l'implémentait — pure spéculation.
> Réintroductible en v1.x si un cas réel apparaît.

Ces interfaces **ne peuvent pas** être fusionnées sans violer l'ISP (cf. ADR-001/002). Une source read-only ne doit pas être forcée d'implémenter `create()`/`update()`/`delete()`.

Idem pour `ActionInterface`/`InlineActionInterface`/`BulkActionInterface` : décomposition obligatoire pour différencier les signatures (`execute(DataRecord)` vs `executeBatch(iterable)`).

`ResourceInterface` est un contrat utilisateur. `FieldInterface`, `FilterInterface`, `PermissionInterface` sont des points d'extension explicites.

**Verdict** : aucune interface n'est redondante. Aucune ne pourrait être fusionnée sans casser un principe.

### Les 9 VO sont chacun la signature d'une méthode publique

- `DataQuery` est l'argument de `DataSourceInterface::search()`
- `DataPage` est le retour de `search()`
- `DataRecord` est le retour de `find()`
- `DataPayload` est l'argument de `WritableDataSourceInterface::create()`/`update()`
- `Pagination`, `FilterCriterion` sont des composants de `DataQuery`
- `ActionResult` est le retour de `execute()`/`executeBatch()`
- `FieldDto` est le retour de `FieldInterface::getAsDto()`
- `FilterDto` est le retour de `FilterInterface::getAsDto()`

**Verdict** : chaque VO porte une responsabilité distincte. Aucun ne peut être supprimé sans perdre l'expressivité ou recourir à des `array` non typés (anti-pattern).

### Les 3 exceptions forment une hiérarchie utile

- `DataSourceException` (base, catchable globalement)
- `ResourceNotFoundException` (cas précis, granularité utile pour catch sélectif)
- `UnsupportedOperationException` (cas précis, granularité utile)

**Verdict** : standard PHP. On pourrait n'en garder qu'une, mais le coût en clarté est trop élevé.

### `AbstractResource`, `FieldTrait`, `SortDirection`, `Polysource` sont chacun nécessaires

- `AbstractResource` : convenience base, retire 80 % du boilerplate
- `FieldTrait` : pattern Filament-style (~120 lignes réutilisables)
- `SortDirection` : enum strict-typed
- `Polysource` : constants centralisées (version, page names, DI tags)

**Verdict** : aucun n'est redondant.

## Pourquoi le critère original était inadéquat

Trois problèmes :

1. **Mauvais niveau de granularité.** « 12 classes » mélange interfaces, VO, exceptions, traits, enums. Or chaque catégorie a sa propre échelle naturelle. Une lib avec 10 VO immutables et 3 interfaces ne fait pas la même chose qu'une lib avec 13 interfaces qui se chevauchent.

2. **Pas de critère qualitatif.** Le seuil ne dit rien sur **la qualité** de la décomposition. On peut avoir 5 classes obèses (mauvais) ou 30 classes single-responsibility (bon). Le nombre seul ne discrimine pas.

3. **Borne arbitraire.** « 12 » a été choisi sans justification dans le plan initial. C'était une intuition pour résister au scope creep, pas une mesure étayée.

## Décision

**Le critère "stop-the-line" du `development-plan.md` §14 sur la taille d'API du core est remplacé par les critères suivants** :

### Critère 1 — Plafond indicatif (pas absolu)

`core` peut avoir jusqu'à **40 types publics** sans nécessiter d'ADR de justification. Au-delà, écrire une nouvelle ADR pour expliquer la croissance.

Justification du nouveau seuil : `core` actuel a 26 types. Marge confortable pour ajouter ~14 types (10-15 field types courants comme `TextField`, `IntegerField`, `BooleanField`, etc., avant de saturer). Au-delà, c'est probablement un signal qu'il faut splitter en sous-packages (`polysource/fields`, `polysource/filters` séparés).

### Critère 2 — Critères qualitatifs (vrais "stop-the-line")

Avant chaque release majeure, vérifier :

- [ ] **ISP** : aucune interface ne force une implémentation à fournir une méthode dont elle n'a pas besoin.
- [ ] **Single Responsibility** : aucune classe ne dépasse 200 lignes ou 12 méthodes publiques.
- [ ] **Pas de redondance** : aucune classe ne fait ce qu'une autre fait déjà.
- [ ] **Utilité prouvée** : chaque type public est utilisé par au moins 2 packages downstream (ou est un contrat utilisateur officiel comme `ResourceInterface`).
- [ ] **Suppression non triviale** : pour chaque type, la question « peut-on le supprimer ? » a une réponse claire : non, et voici pourquoi.

Si l'un de ces critères est violé → vraie alerte, ouvrir une issue ou une ADR.

### Critère 3 — Anti-cas test

Vérifier régulièrement que `core` n'est pas devenu un God Object déguisé :

- [ ] **Aucune classe Façade > 5 dépendances injectées** (signe de Service Locator).
- [ ] **Aucun « manager » / « registry » dans `core`** — le registry vit dans `polysource/symfony-bundle`.
- [ ] **Aucune dépendance Symfony, Doctrine, Redis, HTTP** dans `core` (cf. ADR architecture).

## Conséquences

### Positives

- **Critère justifiable** plutôt qu'arbitraire.
- **Marge de croissance** raisonnable (40 max, 26 actuels).
- **Focus sur la qualité** plutôt que sur le comptage.
- **Plus difficile à contourner** : on ne peut pas « fusionner deux classes pour rester sous le seuil » — il faut justifier qualitativement.

### Négatives

- **Plus subjectif** que « 12 ou pas 12 ». Demande du jugement.
- **Risque modéré** : si le mainteneur perd la discipline, l'API surface peut grossir lentement. Mitigation : revue obligatoire avant chaque release majeure.

### Mise à jour des documents

Cette ADR remplace le critère "stop-the-line" précédent (« `core` package: <
12 public classes/interfaces ») par : `core` package ≤ 40 public types,
avec des critères qualitatifs (ISP, single responsibility, no redundancy).

## Suivi

À chaque release majeure (v1.0, v2.0, etc.), refaire le décompte des types publics. Si on dépasse 40, ouvrir une nouvelle ADR pour soit splitter `core` en sous-packages, soit justifier la croissance.

### Décompte v1.0 / v1.1 (2026-08-07)

**38 types publics** dans `packages/core/src/` — sous le plafond de 40, avec 2 de marge seulement :

| Catégorie | Nombre | Delta vs v0.7.1 |
|---|---|---|
| Interfaces | 12 | +3 : `StyledActionInterface`, `IdentifiableInterface`, `AdminPluginInterface` |
| Value objects | 11 | +2 : `InMemoryValueMatcher` (ADR-0031), `RowDetail` (ADR-0033, v1.1) |
| Concrete field types | 5 | = |
| Exceptions | 3 | = |
| Abstract base / trait | 3 | +1 : `HasPluginMetadata` |
| Enums | 2 | = |
| Attribute + constants holder | 2 | +1 : `AsPlugin` |
| **Total** | **38** | 31 → 38 |

Marge restante : **2 types**. Tout ajout de type public au core en v1.x doit
être pesé contre ce plafond ; à 40, l'alternative split/justification de cette
ADR s'applique.

Si on tombe en dessous de 26 par refactoring (regroupement de classes), parfait — c'est même mieux. Mais ne jamais regrouper artificiellement « pour rester sous un seuil ».

## Références

- [Interface Segregation Principle](https://en.wikipedia.org/wiki/Interface_segregation_principle)
- ADR-001 (DataRecord identifier), ADR-002 (DataPage total), ADR-004 (immutabilité)
- État actuel post-Phase 1 : 26 types publics dans `packages/core/src/`
