# ADR-0031 — Operator translation: `InMemoryValueMatcher` + per-adapter native translation (no `OperatorTranslatorInterface`)

- **Date** : 2026-08-06 (rétro-documentation d'une décision shippée en v0.9.0, commit `df657df` ; complétée en v0.10)
- **Statut** : Accepté
- **Décide pour** : `polysource/core` + les 6 adapters
- **En lien avec** : [ADR-011 item A4 — `FilterOperator` enum](./0011-pre-v1.0-freeze-checklist.md), audit task #65 (`docs/maintainers/v0.9.0-architectural-cleanup.md`)

## Contexte

L'audit v0.9.0 (task #65) constatait que chaque adapter traduisait
les 12 cas de `FilterOperator` dans son dialecte natif avec du code
dupliqué, et proposait une abstraction `OperatorTranslatorInterface`
partagée par les 6 adapters, gardée par une suite de conformance.

L'investigation d'implémentation a montré que les 6 adapters se
répartissent en **deux familles qui ne partagent rien au niveau
implémentation** :

| Famille | Adapters | Question posée | Type de sortie |
|---|---|---|---|
| **In-memory** | flysystem, messenger, redis-hash | « ce record déjà chargé matche-t-il ? » | `bool` |
| **Query-string** | doctrine (DQL/QueryBuilder), meilisearch (filter string), http (query params) | « comment pousser ce critère vers le backend ? » | effet de bord sur QueryBuilder / string propriétaire / array de params — **trois signatures incompatibles** |

(Les 4 sources Redis non-hash — string/list/set/sorted-set — sont un
cas à part : leur seul « filtre » est la traduction du critère
`Like` sur `id` en glob `SCAN MATCH` ; aucun autre adapter n'a
d'analogue.)

## Décision

1. **Pas d'`OperatorTranslatorInterface` commun aux 6 adapters.**
   Une interface couvrant à la fois « muter un QueryBuilder », «
   produire une string Meilisearch » et « produire des query params
   HTTP » forcerait toutes les implémentations à passer par `mixed`
   — une abstraction plus-petit-dénominateur qui documente moins
   qu'elle ne cache. La proposition de l'audit est **rejetée** pour
   la famille query-string.

2. **La famille in-memory partage `Polysource\Core\Query\InMemoryValueMatcher`**
   (shippé v0.9.0) : `matches(mixed $value, FilterOperator $op, mixed $expected): bool`,
   exhaustif sur les 12 cas (égalité loose avec coercition bool,
   `Like` insensible à la casse, comparaisons date-aware pour
   Gt/Gte/Lt/Lte, `Between` inclusif, null-checks stricts).
   Flysystem, Messenger et Redis-hash y délèguent — c'est la seule
   duplication qui était réelle, et elle est éliminée.

3. **La famille query-string garde sa traduction native par adapter**,
   avec la même politique de dégradation : un opérateur non supporté
   par le dialecte est **silencieusement ignoré** (le critère ne
   filtre pas) plutôt que de jeter — un listing qui affiche trop de
   lignes se corrige à l'œil, un listing qui 500 sur un opérateur
   d'URL inconnu est une DoS involontaire. Le sous-ensemble supporté
   par dialecte est documenté dans chaque classe :
   - Doctrine `CriterionApplier` : 9/12 (pas Nin/IsNull/IsNotNull) ;
   - Meilisearch : 7/12 (pas Like/Nin/Between/IsNull/IsNotNull) ;
   - HTTP : 1/12 (`Eq` passthrough only, par design du protocole).

4. **Les 4 `scanPattern()` Redis sont unifiés dans
   `ScanPatternResolver`** (v0.10) — extraction mécanique de la
   seule duplication byte-identique restante, qui supprime au
   passage la branche morte `instanceof FilterOperator` datant
   d'avant le typage enum (v0.7).

## Suite de conformance

La « conformance suite » de l'audit prend cette forme :

- **Sémantique des valeurs** : `core/tests/Unit/Query/InMemoryValueMatcherTest`
  couvre les 12 opérateurs + coercitions — c'est l'autorité unique
  pour la famille in-memory.
- **Routage par adapter** : chaque adapter in-memory a des tests de
  routage représentatifs (un par famille d'opérateurs) qui
  garantissent que le dispatch passe bien par le matcher —
  `FlysystemDataSourceTest`, `MessengerFailedDataSourceTest`,
  `RedisHashDataSourceTest`.
- **Famille query-string** : les tests par adapter verrouillent le
  sous-ensemble supporté ET la dégradation silencieuse des cas non
  supportés (`CriterionApplierTest`, `MeilisearchDataSourceTest`,
  `HttpDataSourceTest`).
- **Redis SCAN** : `ScanPatternResolverTest` couvre l'unique
  implémentation partagée (avant v0.10, 3 des 4 copies n'avaient
  aucun test).

## Conséquences

- Ajouter un opérateur à `FilterOperator` impose de compléter
  `InMemoryValueMatcher` (le `match` est exhaustif — PHPStan casse
  sinon) et de *décider explicitement* par dialecte query-string :
  traduire ou documenter l'ignore.
- Un futur adapter choisit sa famille : in-memory → déléguer au
  matcher (3 lignes) ; query-string → traduction native + tests du
  sous-ensemble.
- La proposition d'interface reste rejetable tant qu'aucun TROISIÈME
  consommateur d'une même signature n'existe (règle des trois).
