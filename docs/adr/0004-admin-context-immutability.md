# ADR-004 — `AdminContext` immutability

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

`AdminContext` est l'objet central injecté dans les contrôleurs (via `AdminContextResolver`). Il porte l'état d'une requête : la `Resource` courante, l'action en cours, les données HTTP, l'utilisateur, la locale, les paramètres de pagination, etc.

EasyAdmin v5 utilise un `AdminContext` **mutable** avec sous-contextes (`RequestContext`, `CrudContext`, `DashboardContext`, `I18nContext`) — voir analyse interne. Cela facilite l'extension par les listeners mais crée des bugs subtils (un listener mute le contexte que le contrôleur utilisera plus tard).

## Options envisagées

### Option A — Mutable (modèle EasyAdmin)

Setters publics sur `AdminContext` et ses sous-contextes.

**Pour** :
- Permet aux listeners `kernel.request` d'enrichir le contexte avant que le contrôleur soit appelé.
- Simple d'usage.

**Contre** :
- Race conditions possibles si un listener mute alors qu'un autre lit.
- Difficile à raisonner : « qui a modifié ce champ et quand ? »
- PHPStan ne peut pas garantir l'invariance.
- Pas aligné avec les value objects du `core` qui sont tous `final readonly`.

### Option B — Immuable avec `final readonly class` (PHP 8.4)

Toutes les propriétés sont `readonly`, déclarées dans le constructor promotion. Les modifications passent par `with*()` qui retournent une nouvelle instance.

**Pour** :
- Invariance garantie par le compilateur PHP.
- Aligné avec les VO du `core`.
- Pas de side effect possible.
- Code plus simple à raisonner et à tester.

**Contre** :
- Les listeners qui veulent enrichir le contexte doivent retourner un nouveau contexte (le mécanisme exact dépend du listener — détaillé plus bas).
- PHP 8.4+ requis (compatible avec ADR-007 §v0.1).

### Option C — Immuable par convention

Setters privés, getters publics, `with*()` methods. Pas de mot-clé `readonly`.

**Pour** :
- Compatible avec PHP 8.0 (utile si on porte vers PHP 8.0+ en v0.5+).

**Contre** :
- Convention non vérifiée par le compilateur.
- Quelqu'un peut ajouter un setter public par mégarde.

## Décision

**Option B — `final readonly class`** est retenue pour la v0.1.

```php
namespace Polysource\Bundle\Context;

use Polysource\Core\Resource\ResourceInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

final readonly class AdminContext
{
    public function __construct(
        public Request $request,
        public ResourceInterface $resource,
        public string $action,                  // 'index', 'detail', 'new', 'edit', 'delete', or custom action name
        public ?string $recordId,
        public string $locale,
        public ?UserInterface $user,
        public DataQuery $query,                // built from request params
    ) {}

    public function withQuery(DataQuery $query): self
    {
        return new self(
            $this->request,
            $this->resource,
            $this->action,
            $this->recordId,
            $this->locale,
            $this->user,
            $query,
        );
    }
}
```

### Comment les listeners enrichissent le contexte

L'`AdminContextProvider` est un service stateful qui détient le `AdminContext` courant. Les listeners qui veulent enrichir le contexte appellent `$provider->setContext($newContext)` :

```php
namespace Polysource\Bundle\Context;

final class AdminContextProvider
{
    private ?AdminContext $context = null;

    public function getContext(): ?AdminContext { return $this->context; }
    public function setContext(AdminContext $context): void { $this->context = $context; }
}
```

C'est le **seul** point mutable du système : le provider garde une référence au contexte courant. L'`AdminContext` lui-même reste immuable.

### Pour le code utilisateur

```php
// Dans un controller
public function __invoke(AdminContext $context): Response
{
    $resource = $context->resource;        // accès direct, pas de getter
    $query = $context->query;

    // Pour modifier la query (ex: ajouter un filtre programmatiquement) :
    $newQuery = $query->withFilter('status', new FilterCriterion('status', 'eq', 'active'));
    $newContext = $context->withQuery($newQuery);
    // Mais on travaille généralement avec le query construit, on n'a pas besoin de muter le context.
}
```

## Conséquences

### Positives

- Invariance garantie par PHP 8.4.
- Cohérence avec les VO du `core`.
- PHPStan level max passe naturellement.
- Pas de race condition entre listeners.
- Tests simplifiés (pas besoin de setUp/tearDown stateful).

### Négatives

- Pour la v0.5+ qui doit supporter PHP 8.0, il faudra remplacer `final readonly class` par convention. Documenté dans ADR-007 §Migration.
- Les `with*()` methods sont verbeux pour les classes à beaucoup de champs. Mitigation : limiter `AdminContext` à 7-8 propriétés max.

### Critère « stop-the-line » associé

`AdminContext` ne doit jamais avoir plus de **10 propriétés**. Si on dépasse, c'est un signe qu'il faut décomposer en sous-contextes (comme EasyAdmin), avec discipline.

## Références

- [PHP 8.2 readonly classes](https://wiki.php.net/rfc/readonly_classes)
- [PHP 8.4 readonly amendments](https://wiki.php.net/rfc/readonly_amendments)
- Symfony `Request` est mutable mais c'est un fardeau historique — on n'imite pas.
