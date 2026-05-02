# ADR-001 — `DataRecord::identifier` type

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

`DataRecord` représente une ligne unique d'une source de données (entité Doctrine, message Messenger failed, fichier S3, ligne Redis, document Meilisearch, etc.). Chaque record doit avoir un identifiant utilisable pour :
- construire l'URL de détail : `/admin/{resourceName}/{identifier}`
- récupérer un record via `DataSourceInterface::find($identifier)`
- exécuter une action ciblée

Les sources que Polysource doit gérer ont des conventions très hétérogènes :
- Doctrine ORM : généralement `int` auto-increment (mais aussi `string` pour UUID, ULID)
- Messenger failed : `int` (transport Doctrine) ou `string` (transport AMQP/Redis)
- S3 : `string` (chemin objet)
- Redis : `string` (clé)
- Meilisearch : variable selon la config de l'index (généralement `string`, parfois `int`)
- HTTP API externe : variable, souvent `string`

## Options envisagées

### Option A — `string` uniquement

Forcer toutes les sources à exposer un identifiant string. Les sources avec PK int feraient un cast.

**Pour** : simplicité du contrat, URLs prédictibles.
**Contre** : casts implicites partout, perte d'information de type, contraint les `find()` à reconstituer le type natif côté adapter.

### Option B — `int` uniquement

Inacceptable : la majorité des sources non-relationnelles utilisent des strings.

### Option C — `string|int` (union type PHP 8.0+)

Le contrat accepte les deux, chaque adapter retourne son type natif.

**Pour** : préserve la sémantique source. Pas de cast forcé. PHP 8.0+ supporte les union types nativement.
**Contre** : le code consommateur doit gérer les deux types (acceptable avec match expression).

### Option D — `mixed`

Inacceptable : pas de garantie de type, risque d'objets passés par erreur.

### Option E — Type dédié `Identifier` (objet)

Créer une classe `Identifier(string|int $value)` immuable.

**Pour** : encapsulation parfaite, possibilité d'ajouter de la logique (ex : URL-safe encoding pour les `string` avec `/`).
**Contre** : verbosité côté utilisateur (`new Identifier(123)` vs `123`), surcharge inutile pour 95 % des cas.

## Décision

**Option C — `string|int`** est retenue.

```php
namespace Polysource\Core\Query;

final readonly class DataRecord
{
    public function __construct(
        public string|int $identifier,
        public array $properties,
        public mixed $rawSource = null,
    ) {}
}
```

```php
namespace Polysource\Core\DataSource;

interface DataSourceInterface
{
    public function find(string|int $identifier): ?DataRecord;
    // ...
}
```

## Conséquences

### Positives

- Contrat aligné avec la majorité des sources.
- Aucun cast forcé.
- Compatible PHP 8.0+ (union types disponibles depuis 8.0 — important pour la roadmap d'élargissement, cf. ADR-007).
- Les URLs de détail restent simples : `/admin/products/123` ou `/admin/files/path%2Fto%2Ffile.txt`.

### Négatives

- Le code consommateur doit gérer les deux types. Mitigé par PHPStan (le type est statique).
- Les sources avec **clé composite** doivent encoder leur identifier en `string` (ex : Doctrine entity à PK composite : `"{$pk1}-{$pk2}"`). Documenté dans la doc utilisateur. Cas marginal (cf. EasyAdmin qui rejette explicitement les composite PK).

### Encodage URL

Un identifier `string` peut contenir des caractères spéciaux (`/`, `:`, etc.). L'URL generation utilise `urlencode()` automatiquement. La `route` regex doit accepter `[^/]+` ou utiliser un `requirements` lâche.

## Références

- [PHP 8.0 union types](https://wiki.php.net/rfc/union_types_v2)
- EasyAdmin v5 : `EntityDto::getPrimaryKeyValue(): mixed` (plus permissif, mais on retient `string|int` pour la sécurité de type)
- Sylius `ResourceInterface::getId(): int|string|null` (même principe)
