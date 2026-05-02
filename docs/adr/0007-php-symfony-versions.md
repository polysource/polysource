# ADR-007 — PHP and Symfony versions support

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+ (avec stratégie de migration vers v0.5+)

## Contexte

Polysource cible la communauté Symfony PHP. Choisir les versions supportées implique des arbitrages :

- **Versions trop modernes** → exclut une grande partie de l'audience qui est encore sur Symfony 5.4 LTS (décembre 2021, EOL nov 2025) ou Symfony 6.4 LTS (nov 2023, EOL nov 2027).
- **Versions trop anciennes** → impossibilité d'utiliser `final readonly class` (PHP 8.2+), `enum` (PHP 8.1+), `never` (PHP 8.1+), `readonly properties` (PHP 8.1+), `first-class callable syntax` (PHP 8.1+) — confort moderne perdu.

Le contexte v0.1 est :
- 1 développeur senior solo
- Volonté de livrer rapidement (6-8 semaines)
- Objectif : 100 ⭐ + 10 utilisateurs publics à 18 mois (sinon stop-loss)

Une compatibilité large dès le jour 1 multiplie le coût de développement par ~1,5-2× (chaque feature doit être compatible avec le PGCD des versions). Cette charge est inacceptable pour un solo en phase de lancement.

## Options envisagées

### Option A — PHP 8.4 + Symfony 7.4 LTS strict, dès v0.1

Se concentrer sur le moderne. Élargir plus tard.

**Pour** :
- Développement rapide (`final readonly class`, `enum`, `never`, etc.).
- Code idiomatique PHP moderne.
- 0 ifdef de version.

**Contre** :
- Audience initiale restreinte (Symfony 5.4 LTS et 6.4 LTS exclus jusqu'à v0.5).
- Risque de perdre des early adopters qui restent sur des LTS plus anciennes.

### Option B — PHP 8.0+ + Symfony 5.4+ dès v0.1 (modèle EasyAdmin 4.x)

Compatibilité large dès le départ.

**Pour** : audience maximale.
**Contre** :
- Pas de `readonly` properties → boilerplate important pour les VO immuables.
- Pas d'`enum` → classes constantes.
- Pas de `first-class callable` → `[$obj, 'method']`.
- Tests de compatibilité multipliés (matrix CI explose).
- Code moins lisible.

### Option C — Stratégie graduée

v0.1 cible le moderne (PHP 8.4 + Sf 7.4). v0.5 (post-stabilisation API) élargit à PHP 8.0+ et Sf 5.4+.

**Pour** :
- Vélocité maximale en phase fragile.
- Validation du contrat avant compatibilité élargie.
- Coût de migration concentré et planifié.

**Contre** :
- Audience initiale restreinte (mais c'est un lancement de niche, pas un produit grand public).
- Migration coûteuse (~1-2 semaines de dev en v0.5).

## Décision

**Option C — Stratégie graduée** est retenue.

### Versions cibles v0.1

| Composant | Contrainte composer | Notes |
|---|---|---|
| PHP | `^8.4` | versions 8.4.x |
| Symfony | `^7.4` | LTS courante au lancement |

### Versions cibles v0.5+ (élargissement)

| Composant | Contrainte composer | Notes |
|---|---|---|
| PHP | `>=8.0` (effective : `^8.0 \| ^8.1 \| ^8.2 \| ^8.3 \| ^8.4`) | jusqu'à fin de vie 8.4 |
| Symfony | `^5.4 \| ^6.4 \| ^7.4` | LTS+stable récents |

L'élargissement vise à reproduire la matrix de compatibilité d'EasyAdmin v4.x au moment de son apogée, pour atteindre le maximum de projets Symfony existants.

## Migration v0.1 → v0.5+ (à exécuter en v0.5)

### Patterns à remplacer

| v0.1 (PHP 8.4) | v0.5 (PHP 8.0+) |
|---|---|
| `final readonly class DataQuery { public readonly string $foo; }` | `final class DataQuery { /** readonly by convention */ private string $foo; public function getFoo(): string { return $this->foo; } }` |
| `enum SortDirection { case ASC; case DESC; }` | `final class SortDirection { public const ASC = 'asc'; public const DESC = 'desc'; }` |
| `function foo(): never` | `function foo(): void { throw ... }` |
| `$callable = $this->method(...);` (first-class callable) | `$callable = [$this, 'method'];` |
| `is_string($x) || is_int($x)` natif via `string\|int` | OK depuis PHP 8.0 ✓ |
| Constructor promotion `public function __construct(public string $foo)` | OK depuis PHP 8.0 ✓ |
| `match(...)` | OK depuis PHP 8.0 ✓ |
| `?->` nullsafe operator | OK depuis PHP 8.0 ✓ |
| Named arguments | OK depuis PHP 8.0 ✓ |
| Attributes `#[...]` | OK depuis PHP 8.0 ✓ |

### Stratégie de migration

1. **Branche dédiée** : créer une branche `feat/php80-compat` qui n'est pas mergée sur main avant validation.
2. **Refactor incrémental** :
   - Étape 1 : remplacer `final readonly class` par convention immutable (~2 jours).
   - Étape 2 : remplacer les `enum` par classes constantes (~0.5 jour).
   - Étape 3 : audit des `never`, first-class callables (~1 jour).
3. **CI matrix élargie** : ajouter PHP 8.0/8.1/8.2/8.3 et Symfony 5.4/6.4 au workflow.
4. **Tests de non-régression** : la suite v0.4 doit passer telle quelle sur PHP 8.0.
5. **Tag v0.5.0** : annonce de la nouvelle compatibilité.

### Code à éviter en v0.1 (anticipation v0.5)

Pour faciliter la migration, certains patterns sont **proscrits dès la v0.1**, même si PHP 8.4 les autorise :

- ❌ `enum` avec méthodes complexes (juste `case` simples).
- ❌ Intersection types (`A&B`).
- ❌ Asymmetric visibility (PHP 8.4 specific).
- ❌ Property hooks (PHP 8.4 specific).
- ✅ `final readonly class` (remplaçable mécaniquement).
- ✅ `enum` simples (remplaçables par classes constantes).

Cette discipline coûte ~5 % de confort en v0.1 mais réduit drastiquement le coût de migration en v0.5.

## Conséquences

### Positives

- **Vélocité v0.1 maximale** : code idiomatique moderne, pas de boilerplate.
- **Migration planifiée** : v0.5 n'est pas un saut dans l'inconnu.
- **Audience croissante** : v0.5 ouvre l'audience aux projets en LTS plus ancienne.
- **Aligné avec la communauté** : Symfony 7.4 LTS sera la norme courante au moment du lancement.

### Négatives

- **Audience initiale restreinte** : projets Symfony 5.4 / 6.4 doivent attendre v0.5 (3-6 mois).
- **Risque de stagnation v0.5** : si le projet n'atteint pas la traction nécessaire en v0.4, v0.5 ne se fera jamais. Acceptable selon le stop-loss.
- **Discipline patterns proscrits** : doit être communiqué à toute future contributor.

### Comparaison avec EasyAdmin

EasyAdmin v5.x exige `php >=8.2` et `symfony/framework-bundle: ^6.4|^7.0|^8.0` — donc plus restrictif que ce qu'on prévoit en v0.5+. Notre stratégie d'élargissement nous met en avantage de portée.

## Références

- [PHP supported versions](https://www.php.net/supported-versions.php)
- [Symfony releases](https://symfony.com/releases)
- EasyAdmin v5 `composer.json` : `php >=8.2`, `symfony/framework-bundle ^6.4|^7.0|^8.0`
- EasyAdmin v4.x (apogée 2023) : `php >=8.0.2`, `symfony/framework-bundle ^5.4|^6.0` — modèle pour notre v0.5
