# ADR-002 — `DataPage::total` semantics

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

`DataPage` est le retour de `DataSourceInterface::search(DataQuery): DataPage`. Il porte les items de la page courante + l'information de pagination.

Selon la source, le **total d'éléments correspondant à la query** peut être :
- **Connu et bon marché** : Doctrine `Paginator::count()` via une COUNT query.
- **Connu et coûteux** : APIs HTTP qui doivent appeler un endpoint séparé.
- **Inconnu** : Messenger failed transport (`ListableReceiverInterface::all()` ne donne pas de total), Meilisearch (`estimatedTotalHits` est approximatif), Redis SCAN, S3 list (cursor-based).

L'UI paginator doit s'adapter :
- **Total connu** → pagination classique offset/limit avec « Page X / Y ».
- **Total inconnu** → pagination cursor : « Page suivante » seulement, ou « Précédent / Suivant ».

## Options envisagées

### Option A — `total: int` toujours obligatoire

Les sources sans total doivent en estimer un (en faisant un SCAN complet, ou en retournant `0`/`-1`).

**Pour** : type simple.
**Contre** : SCAN complet = coût rédhibitoire pour Redis/S3. `-1` ou `0` = sentinel value moche.

### Option B — `total: ?int` avec `null = inconnu`

`null` est explicite et idiomatique en PHP 8+.

**Pour** : zéro ambiguïté. PHPStan détecte les usages non-checked. Aligne avec la null-safety moderne.
**Contre** : le code consommateur (template Twig) doit gérer le null.

### Option C — Méthode séparée `hasTotal(): bool`

```php
class DataPage {
    public function hasTotal(): bool;
    public function getTotal(): int;  // throws if no total
}
```

**Pour** : API explicite.
**Contre** : verbosité, deux appels au lieu d'un. Pas idiomatique en PHP moderne.

### Option D — Type union `int|EstimatedInt|UnknownTotal`

Trop complexe pour un cas d'école.

## Décision

**Option B — `total: ?int` avec `null = inconnu`** est retenue.

```php
namespace Polysource\Core\Query;

final readonly class DataPage
{
    public function __construct(
        /** @var iterable<DataRecord> */
        public iterable $items,
        public ?int $total,                    // null = inconnu (cursor-based source)
        public ?string $nextCursor = null,
        public ?string $prevCursor = null,
    ) {}
}
```

## Conséquences

### Positives

- Sémantique idiomatique PHP 8+.
- Le template paginator branche sur `page.total === null` :
  - `null` → cursor pagination UI (« Précédent / Suivant »)
  - `int` → pagination classique avec total
- Compatible avec toutes les sources sans pénalité de performance.

### Négatives

- Le template Twig doit avoir 2 branches. Acceptable, c'est ~20 lignes de Twig.
- Docs utilisateur doivent expliciter quand un adapter retourne `null`.

### Bonnes pratiques pour les adapters

- Si la source supporte `count()` à coût raisonnable → retourner l'`int`.
- Si la source supporte un total approximatif (Meilisearch `estimatedTotalHits`) → retourner l'`int` avec une note dans la doc de l'adapter.
- Si la source ne supporte pas le total → retourner `null` + `nextCursor`.

### Encodage du cursor

`nextCursor` et `prevCursor` sont des `string` opaques. Chaque adapter les sérialise comme il veut (ex : id du dernier element, opaque token API, etc.). L'UI les passe tels quels à la query suivante via querystring `?cursor=...`.

## Références

- [React Admin pagination](https://marmelab.com/react-admin/DataProviders.html) — utilise `total` comme `int|undefined`, équivalent à notre `?int`.
- Sylius Grid retourne `Pagerfanta` qui force le total — anti-modèle évité.
