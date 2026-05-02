# ADR-003 — Routing strategy

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

Polysource doit exposer ses contrôleurs via des URLs lisibles. Deux paradigmes existent dans l'écosystème admin Symfony :

- **EasyAdmin** : URL unique avec query string. Ex : `/admin?crudControllerFqcn=App\Controller\ProductCrudController&crudAction=index&entityId=42`
- **Sonata / API Platform** : routes physiques RESTful. Ex : `/admin/products/42/edit`

## Options envisagées

### Option A — Query string (modèle EasyAdmin)

Une seule route catchall `/admin` qui dispatche selon les query params.

**Pour** :
- Simplicité de déclaration (1 route Symfony)
- Cache de routing minimal

**Contre** :
- URLs illisibles, peu SEO-friendly
- Difficile à debugger (logs serveur peu utiles)
- Peu naturel à partager (« va sur /admin?foo=bar&... »)
- Pose problème pour les permissions Symfony qui sont path-based (`access_control` regex sur les URLs)
- Dans le code utilisateur, les liens doivent passer par `AdminUrlGenerator` — verbeux

### Option B — Routes physiques par resource

`/admin/{resourceName}` (index), `/admin/{resourceName}/{id}` (detail), `/admin/{resourceName}/{id}/{action}` (action), etc.

**Pour** :
- URLs lisibles et partageables : `/admin/failed-messages`, `/admin/failed-messages/123`
- Compatible nativement avec `access_control` regex Symfony
- Debug serveur naturel
- API publique simple : `path('polysource_index', ['resourceName' => 'products'])` ou simplement `/admin/products`

**Contre** :
- Plus de routes générées (×N par le nombre de resources)
- Risque de collision avec d'autres routes utilisateur (mitigeable avec préfixe `polysource_`)

### Option C — Hybride

Routes physiques pour list/detail, query string pour les actions complexes.

**Contre** : incohérence, complexité de la doc.

## Décision

**Option B — Routes physiques par resource** est retenue.

### Schéma des routes

| Verbe | URL | Route name | Controller |
|---|---|---|---|
| GET | `/admin/{resourceName}` | `polysource_index` | `IndexController::__invoke` |
| GET | `/admin/{resourceName}/{id}` | `polysource_detail` | `DetailController::__invoke` |
| POST | `/admin/{resourceName}/{id}/{action}` | `polysource_action` | `ActionController::__invoke` |
| POST | `/admin/{resourceName}/batch/{action}` | `polysource_bulk_action` | `ActionController::bulk` |

### Conventions

- Préfixe `/admin/` configurable via `polysource.yaml` (`polysource.url_prefix`).
- Le `resourceName` est le slug défini par `ResourceInterface::getName()` — kebab-case recommandé (`failed-messages`, pas `FailedMessages`).
- Les actions custom utilisent leur `getName()` comme slug.
- Le route loader `Polysource\Bundle\Routing\PolysourceRouteLoader` est appelé une fois au boot, génère toutes les routes via les `Resource` enregistrés via tag DI.

### Url generation

```php
$urlGenerator->generate('polysource_detail', [
    'resourceName' => 'failed-messages',
    'id' => $envelope->getId(),
]);
```

Helper Twig pour la simplicité utilisateur :

```twig
{{ polysource_url(record, 'detail') }}
{{ polysource_url(record, 'retry') }}
```

## Conséquences

### Positives

- URLs propres, faciles à partager et bookmarker.
- Symfony `access_control` directement applicable :
  ```yaml
  security:
      access_control:
          - { path: ^/admin/failed-messages, roles: ROLE_ADMIN }
  ```
- Logs serveur lisibles.
- Profiler Symfony plus utile (les routes sont identifiées).
- Compatible avec les conventions REST du reste de l'écosystème Symfony.

### Négatives

- Plus de routes générées. Pour 50 resources × 4 actions de base = 200 routes. Impact mesurable sur le routing cache mais marginal (Symfony Router est performant).
- Le `RouteCollection` doit être invalidé quand on ajoute/retire une resource — géré nativement par le cache Symfony.

### Mitigation des collisions

- Préfixe par défaut `/admin/` change configurable.
- Les `resourceName` sont uniques par contrainte du registry (compiler pass throws si doublon).
- Symfony Router prend la première route qui match → un utilisateur peut prioriser ses propres routes en les déclarant **avant** Polysource dans `routes.yaml`.

## Références

- [Symfony Routing](https://symfony.com/doc/current/routing.html)
- [API Platform routes](https://api-platform.com/docs/core/operations/) — modèle similaire
- EasyAdmin v5 a partiellement migré vers ce modèle avec `#[AdminRoute]` (`src/Router/AdminRouteGenerator.php`) — confirme la tendance.
