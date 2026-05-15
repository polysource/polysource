# ADR-029 — `AdminContext` decomposition strategy

- **Date** : 2026-05-15
- **Statut** : Accepté (planification — non implémenté avant un besoin concret)
- **Décide pour** : v1.x (post-v1.0)
- **En lien avec** : [ADR-004 — Final readonly value objects](./0004-final-readonly-value-objects.md), [ADR-011 — Pre-v1.0 freeze checklist](./0011-pre-v1.0-freeze-checklist.md) (item A8)

## Contexte

`Polysource\Bundle\Context\AdminContext` est le value object passé à
chaque controller Polysource via `AdminContextProvider`. Il porte 7
propriétés à v0.7 :

```php
final class AdminContext
{
    public function __construct(
        public readonly Request $request,
        public readonly ResourceInterface $resource,
        public readonly string $action,
        public readonly ?string $recordId,
        public readonly string $locale,
        public readonly ?UserInterface $user,
        public readonly DataQuery $query,
    ) {}
}
```

ADR-004 fixe une limite douce de **10 propriétés** avant que la
décomposition en sous-VOs devienne obligatoire. Les évolutions
prévisibles (cf. ADR-011 item A8) franchiraient ce plafond :

- `?CsrfToken $csrf` (audit / form actions)
- `array $flashes` (carry-over de messages flash en plein form replay)
- `string $referer` (post-action redirect logic)
- `?array<string, mixed> $auditMetadata` (correlation IDs, request hash,
  user-agent — pour le polysource/audit log)
- `?Theme $theme` (preview mode du polysource/twig-theme)
- `?FeatureFlags $features` (gates dynamiques côté listeners)

Ajouter ces 5-6 propriétés à plat ferait 12-13 propriétés sur
`AdminContext` — au-delà de la limite ADR-004 et difficile à lire
sans avoir le PR sous les yeux.

## Décision

Définir le plan de décomposition **maintenant**, l'implémenter **quand
le 8e champ doit être ajouté** (premier besoin concret du runtime ou
d'un adapter).

### Structure cible

```php
final class AdminContext
{
    public function __construct(
        public readonly RequestContext $request,    // Request + locale + referer
        public readonly ResourceContext $resource,  // ResourceInterface + action + recordId
        public readonly UserContext $user,          // ?UserInterface + ?CsrfToken + ?array $auditMetadata
        public readonly QueryContext $query,        // DataQuery + ?FeatureFlags
        public readonly ?PresentationContext $presentation = null,  // optionnel: ?Theme + array $flashes
    ) {}
}
```

5 propriétés au lieu de 7-13. Chaque sous-contexte porte sa propre
limite de 10 props (donc plafond global théorique de 50 — large
marge).

### Découpage des sous-VOs

```php
final class RequestContext
{
    public function __construct(
        public readonly Request $request,
        public readonly string $locale,
        public readonly string $referer,
    ) {}
}

final class ResourceContext
{
    public function __construct(
        public readonly ResourceInterface $resource,
        public readonly string $action,        // 'index', 'detail', 'edit', …
        public readonly ?string $recordId,
    ) {}
}

final class UserContext
{
    public function __construct(
        public readonly ?UserInterface $user,
        public readonly ?CsrfToken $csrf = null,
        /** @var array<string, mixed> */
        public readonly array $auditMetadata = [],
    ) {}
}

final class QueryContext
{
    public function __construct(
        public readonly DataQuery $query,
        public readonly ?FeatureFlags $features = null,
    ) {}
}

final class PresentationContext
{
    public function __construct(
        public readonly ?Theme $theme = null,
        /** @var array<string, list<string>> */
        public readonly array $flashes = [],
    ) {}
}
```

### Stratégie de migration

La décomposition est un **breaking change** sur l'API publique
(`AdminContext::$request: Request` → `AdminContext::$request: RequestContext`).
Étapes :

1. **Phase préparatoire (avant tout split)** — Stabiliser le sous-VO
   `RequestContext` en interne. Le construire dans `AdminContextProvider`
   et exposer ses champs au niveau `AdminContext` (façade plate
   identique à v0.7).
2. **Phase split (v1.x mineur après v1.0)** — Bump majeur dans
   les types : `AdminContext::$request` change de type. CHANGELOG
   marque `BREAKING`. Une seule façade `AdminContext::getRequest(): Request`
   peut être conservée 1-2 minors pour migration douce, marquée
   `@deprecated`.
3. **Phase clean (v2.0)** — Suppression des façades plates dépréciées.

### Critère de déclenchement

La décomposition n'est PAS faite préventivement. Elle est
déclenchée la **première fois qu'un PR voudrait ajouter une 8e
propriété directement sur `AdminContext`**. Le code reviewer (souvent
le mainteneur lui-même) refuse l'ajout direct, ouvre l'issue
"v1.x : décomposer AdminContext en sous-VOs", et reprend la
structure cible ci-dessus.

Cette ADR est la **référence à ne pas réinventer** au moment du
déclenchement.

## Alternatives considérées

### Alt 1 — décomposer immédiatement

Avantage : pas de breaking change tardif, structure idéale dès v1.0.
Inconvénient : viole YAGNI — on porte 6 sous-VOs vides ou
quasi-vides pendant 6-12 mois pour des cas qui peut-être ne
surviendront jamais. Coût immédiat de refactoring + churn de tous
les consumers (listeners, controllers, extensions Twig).

**Rejeté** : la limite des 10 props existe précisément pour ne pas
préoptimiser. Tant qu'on a marge, on garde la plate.

### Alt 2 — abandonner la limite de 10 props (ADR-004 amendé)

Avantage : zéro refactoring jamais. Inconvénient : `AdminContext`
devient une grosse bag of properties (10 → 15 → 20…), difficile à
lire, à tester, à composer.

**Rejeté** : la limite ADR-004 est volontaire. Si on l'abandonne,
on perd la garantie que les VOs restent lisibles.

### Alt 3 — `array $extra` extensible

Avantage : ajouter une donnée nouvelle ne change pas la signature.
Inconvénient : `array<string, mixed>` empêche PHPStan de typer
les accès, force le runtime à valider les clés, et invite à du
duck typing.

**Rejeté** : on perd la valeur du `final readonly` typé.

### Alt 4 — événements pour passer les données

Avantage : zéro propriété à ajouter, juste un nouveau listener qui
écoute un event spécifique. Inconvénient : explosion d'events ad
hoc, dépendances cachées entre listeners, debug très difficile.

**Rejeté** : ce serait substituer un anti-pattern à un problème de
visibilité.

## Conséquences

### Positives

- Plan défini, donc la pression "ne pas dépasser 10 props" est
  réelle mais pas paralysante — on sait quoi faire le jour où
  ça arrive
- Pas de coût immédiat (zéro ligne de code dans cette ADR — pure
  planification)
- Tout futur reviewer a un seul document à citer ("voir ADR-029")
  au lieu de devoir re-débattre

### Négatives

- Le jour où la décomposition arrive, elle sera un **breaking
  change** sur le contrat public — visible dans la migration major
  qui suit v1.0
- Le sous-VO `PresentationContext` est optionnel par design — un
  léger smell de structure-coupling (les flashes sont
  request-scoped, le theme est presentation-scoped — pourquoi
  ensemble ?). Acceptable parce que les deux sont consommés par les
  mêmes templates Twig

## Références

- [ADR-004 — Final readonly value objects](./0004-final-readonly-value-objects.md) (plafond de 10 props)
- [ADR-011 — Pre-v1.0 freeze checklist](./0011-pre-v1.0-freeze-checklist.md) (item A8 — la dette traitée par cette ADR)
- [ADR-013 — `polysource/filter` architecture](./0013-filter-package-architecture.md) (séparation contracts/storage)
