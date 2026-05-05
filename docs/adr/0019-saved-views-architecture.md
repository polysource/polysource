# ADR-019 — Architecture des saved views (Phase 11)

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : Phase 11 — première capability Phase 10+ après le pivot plugin
- **En lien avec** : [ADR-013 — `polysource/filter` architecture](./0013-filter-package-architecture.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md), [ADR-018 — `AdminPluginInterface` + public contracts](./0018-admin-plugin-interface-and-public-contracts.md)

## Contexte

[ADR-017](./0017-cherry-picking-from-filament-study.md) §2a retient les
**saved views** comme la première feature Phase 10+ : un user qui revient
quotidiennement sur le même cas (« commandes en attente de fraud review »,
« clients churn-risk > 0,7 ») devrait pouvoir le sauvegarder et le rappeler
en un clic, plutôt que ré-appliquer 5 filtres à chaque fois.

L'étude `symfony-admin-framework-analysis.md` §2.2 cite 5 produits qui
shippent ça en first-class (Filament, Nova, Forest, Linear, Notion). Aucun
outil Symfony ne le ship aujourd'hui — c'est P0 avec haute valeur user et
faible coût marginal au-dessus de la session persistence déjà présente
dans `polysource/filter` (cf. ADR-013).

Pré-requis posés par [ADR-018](./0018-admin-plugin-interface-and-public-contracts.md) :

- Tag DI primaire : `polysource.saved_view_scope`
- Interface contributeur : `SavedViewScopeInterface`
- Le tout ship dans un plugin séparé (pas dans `polysource/symfony-bundle`)
  ou dans `polysource/filter` directement — décision dans cette ADR.

## Décision

### 1. Package de livraison : `polysource/filter`

Les saved views sont une **extension du primitive filter**, pas une feature
admin-engine :

- Une saved view = une `FilterCollection` persistée + métadata (nom, scope, …).
- La sérialisation/désérialisation `FilterCriterion[]` existe déjà dans
  `FilterService::serialize()` / `deserialize()`.
- Hosts qui consomment `polysource/filter` standalone (cf.
  `examples/filter-standalone-demo`) en bénéficient sans installer
  `polysource/symfony-bundle`.

Donc : **no new package**. Les classes vont dans
`Polysource\Filter\SavedView\*` du package `polysource/filter`.

### 2. Modèle — `SavedView` value object

Immutable, en ligne avec [ADR-004](./0004-admin-context-immutability.md) /
[ADR-013](./0013-filter-package-architecture.md) :

```php
namespace Polysource\Filter\SavedView\Model;

final class SavedView
{
    public function __construct(
        public readonly string $id,                  // UUID v7 host-generated
        public readonly string $name,                // user-provided label
        public readonly string $resourceName,        // scope: e.g. "products"
        public readonly string $ownerId,             // user identifier (string-cast)
        public readonly SavedViewScope $scope,       // private | team | public
        public readonly FilterCollection $filters,   // the saved criteria
        public readonly array $columns = [],         // list<string> selected columns (empty = default)
        public readonly array $sort = [],            // ['column' => 'asc'|'desc']
        public readonly ?int $pageSize = null,       // null = host default
        public readonly ?string $teamId = null,      // required when scope = team
        public readonly bool $isDefault = false,     // if true, applies on first visit per role
        public readonly ?string $roleAsDefault = null, // role this is a default for
    ) {
        // Validation: scope === team implies teamId; isDefault requires roleAsDefault
    }
}
```

Le `id` est un UUID v7 généré côté **host** (pas d'auto-incrément). Ça
laisse la persistance choisir son backend sans coupler à Doctrine.

### 3. Scope — enum minimal

```php
namespace Polysource\Filter\SavedView\Model;

enum SavedViewScope: string
{
    case PRIVATE = 'private';   // owner only
    case TEAM    = 'team';      // all users with same teamId
    case PUBLIC  = 'public';    // every user
}
```

3 niveaux suffisent pour 95% des cas. Plus de granularité = ACL = scope
creep. Hosts qui ont besoin de matching par rôle Symfony composent via
le `SavedViewVoter` (§5).

### 4. Persistence — `SavedViewStorageInterface`

```php
namespace Polysource\Filter\SavedView\Storage;

interface SavedViewStorageInterface
{
    public function save(SavedView $view): void;

    public function find(string $id): ?SavedView;

    /**
     * @return iterable<SavedView> views visible to (ownerId|teamId|public)
     *                             for the given resource
     */
    public function listVisible(
        string $resourceName,
        string $ownerId,
        ?string $teamId = null,
    ): iterable;

    public function delete(string $id): void;
}
```

**v0.1 ne ship aucune implémentation par défaut.** Hosts consomment :

- une impl Doctrine (qu'on ship en v0.2 dans un sous-package optionnel
  `polysource/saved-views-doctrine`)
- une impl session-only (à ship plus tard si demandé)
- ou la leur

Cohérent avec ADR-013 §"Pas de package admin-engine en v0.1 pour le
filter primitive" — on ship le contrat, pas l'implémentation.

**Pour le Phase 11 release v0.1.0** : on ship Doctrine impl side-by-side
dans le même PR (sinon le feature n'est pas testable end-to-end). Décision
d'**inclure dans `polysource/filter`** sous condition `class_exists(\Doctrine\ORM\EntityManagerInterface::class)` — pas
de hard dep.

### 5. Authorization — `SavedViewVoter`

Voter Symfony standard ([Symfony Security](https://symfony.com/doc/current/security/voters.html)) :

```php
namespace Polysource\Filter\SavedView\Security;

final class SavedViewVoter extends Voter
{
    public const VIEW   = 'POLYSOURCE_VIEW_SAVED_VIEW';
    public const EDIT   = 'POLYSOURCE_EDIT_SAVED_VIEW';
    public const DELETE = 'POLYSOURCE_DELETE_SAVED_VIEW';
    public const SHARE  = 'POLYSOURCE_SHARE_SAVED_VIEW';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::SHARE], true)
            && $subject instanceof SavedView;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        // Default rules:
        //   VIEW   → owner|team-member|public
        //   EDIT   → owner only
        //   DELETE → owner only (admins via separate ROLE_ADMIN voter)
        //   SHARE  → owner only (changing scope private→team→public)
    }
}
```

Hosts qui veulent une politique plus fine (ex. ROLE_ADMIN peut tout
éditer) ajoutent un voter custom au-dessus — Symfony permet de cumuler.

### 6. Service haut niveau — `SavedViewService`

```php
namespace Polysource\Filter\SavedView;

final class SavedViewService
{
    public function __construct(
        private SavedViewStorageInterface $storage,
        private AuthorizationCheckerInterface $authChecker,
        private RequestStack $requestStack,
    ) {}

    /**
     * @return iterable<SavedView>
     */
    public function listVisible(string $resourceName): iterable;

    public function save(SavedView $view): SavedView;
    public function load(string $id): ?SavedView;
    public function delete(string $id): void;

    /**
     * Returns the default view for the current user on the given
     * resource. First match wins among:
     *   1. user's last-used view (session)
     *   2. role default view
     *   3. null (host's vanilla default)
     */
    public function defaultFor(string $resourceName): ?SavedView;
}
```

`SavedViewService` n'expose PAS de méthode `apply()` — c'est le host (ou
le bridge) qui prend la `FilterCollection` du saved view et la hydrate
dans le formulaire, exactement comme aujourd'hui pour les filtres
URL-driven.

### 7. UI — Twig function `saved_views_dropdown(resourceName)`

```twig
{# In the host's index template #}
{{ saved_views_dropdown('products') }}
```

Rend le dropdown <select> ou <ul> de toutes les vues visibles + une
option « Save current as view » qui ouvre un modal (form Symfony).

Le markup Bootstrap 5 par défaut est dans
`packages/filter/Resources/views/saved_view/dropdown.html.twig`. Hosts
overridable via le namespace Twig `@PolysourceFilter`.

L'UX exact (single-select, multi-select, recents, search) est sortie
de scope cette ADR — décidée à l'implémentation Phase 11 sur retour
visuel.

### 8. URL-shareable filter state (bonus du même phase)

[ADR-017](./0017-cherry-picking-from-filament-study.md) §2a liste aussi
"URL-shareable filter state" comme à inclure Phase 11. C'est trivial à
livrer :

- `FilterCollection` se sérialise déjà en `?filter[name]=...&filter[…]`
- Saved view click → set URL params → page reload
- `Save current as view` lit l'URL pour pré-remplir le form

Pas d'ADR séparée — c'est du UI plumbing.

### 9. Default views par rôle

Acté §2 (`isDefault: true` + `roleAsDefault: 'ROLE_USER'`).

Behavior : à la première visite d'un user sur une resource, si :

- il n'a pas de saved-view-id en session
- ET il a un rôle qui correspond à une saved view marked `isDefault`

→ rediriger vers `?view=<id>`.

Edge case : plusieurs `isDefault` matchent (user a 3 rôles). Premier match
in priority order défini par `getRoleHierarchy()` Symfony. Si la hiérarchie
est ambiguë, le premier saved retourné par `listVisible()` order-by-created
gagne.

### 10. Out of scope v0.1

Différé pour quand le besoin remonte :

- **Saved view sharing par lien** (post-v0.2) : copier un lien qui
  re-crée la vue chez le destinataire.
- **Saved view inheritance / override** (post-v0.2) : « comme la vue X
  mais avec un filtre en plus ».
- **Vue collaborative** (post-v0.5+) : édition simultanée. Mercure
  presence comme dans Linear.
- **Schedule a saved view email digest** (post-v0.5+) : envoi
  automatique par cron + Notifier. Combine bien avec le widget framework
  (Phase 12) et l'audit (Phase 16+).

## Conséquences

### Positives

1. **Feature P0 livrée dans le scope ADR-012**. Filter primitive enrichi
   sans toucher l'admin engine.
2. **Adoption EA-bridge naturelle**. Le bridge bénéficie automatiquement
   du dropdown Twig — il branche `saved_views_dropdown('product')` dans
   son template `index.html.twig`.
3. **Pattern réutilisable**. Le tag `polysource.saved_view_scope` (cf.
   ADR-018) permettra plus tard à un plugin d'introduire des scopes
   custom (ex : `organization`, `project`) sans toucher au core.
4. **Storage swappable**. Hosts peuvent ship leur propre
   `SavedViewStorageInterface` (S3 JSON files, Redis hash, REST API)
   — cohérent avec l'angle multi-source-first de Polysource.

### Négatives / Trade-offs

1. **Couplage Doctrine pour la default impl**. La ship en v0.1 demande
   `doctrine/orm` côté host quand il veut le storage out-of-the-box.
   Marqué `suggest:` dans `composer.json`, pas `require:`.
2. **Migration Schema**. Hosts qui adoptent doivent run un Doctrine
   migration pour la table `polysource_saved_views`. Documenté dans
   le walkthrough.
3. **Pas d'UI de management dédiée**. v0.1 ship le dropdown + le modal
   "save current as view"; supprimer/renommer un saved view passe par
   le dropdown (icône poubelle dans l'item). Pas de page admin séparée
   pour les vues — pas avant que le besoin soit confirmé.
4. **La vue par défaut peut être surprenante**. Un user qui a pinné une
   vue oubliée et revient 3 mois plus tard se demande pourquoi son
   listing est filtré. Mitigation : badge UI claire « Vue : Mon dashboard
   du lundi (épingle) » avec un X pour reset.

## Alternatives considérées

### A. Saved views dans `polysource/symfony-bundle` (admin engine)

**Rejeté.** Force l'install de l'admin engine pour avoir des saved views,
même quand le host utilise `polysource/filter` standalone (Sonata, API
Platform, vanilla Symfony). L'angle audience-capture du standalone-demo
serait perdu.

### B. Implémentation session-only seulement

**Rejeté pour MVP.** Pas de sharing private/team/public, pas de défauts
par rôle = manque le P0 « daily-use UX » identifié par l'étude. Session-
only suffirait pour un usage solo mais pas pour le « inbox-shaped tool »
positionné par ADR-017.

### C. Doctrine entity gérée par Polysource (auto-migrate, pas de bundle séparé)

**Rejeté.** Polysource ne ship pas d'auto-migration. Hosts doivent owner
leur schema. Sub-package Doctrine optionnel (`polysource/saved-views-doctrine`
si extracté) reste l'option propre v0.2.

### D. Stocker en localStorage / IndexedDB côté navigateur

**Rejeté.** Pas de sharing, pas de role defaults, perte au clear cache.
Bonne idée pour des préférences UI privées (densité, theme), mauvaise
pour une working state qu'un user veut retrouver à 6 mois.

## Plan d'exécution (Phase 11)

1. Cette ADR-019 (signée, ce commit).
2. **Phase 11 task #1** — `polysource/filter/src/SavedView/Model/`:
   - `SavedView` VO + tests
   - `SavedViewScope` enum + tests
3. **Phase 11 task #2** — `polysource/filter/src/SavedView/Storage/`:
   - `SavedViewStorageInterface`
   - `DoctrineSavedViewStorage` (gated par `class_exists`) + Doctrine
     entity + table migration documentée
4. **Phase 11 task #3** — `polysource/filter/src/SavedView/Security/`:
   - `SavedViewVoter` + tests
5. **Phase 11 task #4** — `polysource/filter/src/SavedView/SavedViewService.php`:
   - High-level service + tests fonctionnels
6. **Phase 11 task #5** — Twig:
   - `saved_views_dropdown()` Twig function
   - `Resources/views/saved_view/dropdown.html.twig`
   - `Resources/views/saved_view/save_modal.html.twig`
7. **Phase 11 task #6** — Bridge integration:
   - `polysource/easyadmin-filter-bridge` ajoute le dropdown via
     `index.html.twig` override existant.
8. **Phase 11 task #7** — URL-shareable state:
   - `FilterService::buildUrl(FilterCollection)` helper
   - Document le pattern dans `docs/user/filter/saved-views.md`
9. **Phase 11 task #8** — Demo:
   - `examples/filter-standalone-demo` ajoute la dropdown + DB SQLite
     pour le storage Doctrine
10. **Phase 11 task #9** — Docs:
    - `docs/user/filter/saved-views.md` walkthrough
    - `docs/user/easyadmin-filter-bridge/whats-new.md` ajoute saved views

Estimation : **~2-3 semaines** (storage + voter + service + Twig +
bridge integration + demos + docs).

## Pour relire cette décision plus tard

Réviser cette ADR si :

- Le storage Doctrine devient un blocker pour adoption (host non-Doctrine
  qui veut saved views) → extraire un package séparé
  `polysource/saved-views-session` ou `polysource/saved-views-redis`.
- L'UX dropdown s'avère insuffisante → refonte UI dans une ADR-019b.
- 5+ users distincts demandent le sharing par lien (out of scope §10) →
  ADR follow-up.
