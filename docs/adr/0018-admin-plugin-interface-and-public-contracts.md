# ADR-018 — `AdminPluginInterface` + versioning des contrats publics

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : Phase 10+ — préalable à la release v0.1.0
- **En lien avec** : [ADR-005 — Configuration via interface methods + PHP attributes](./0005-configuration-mechanism.md), [ADR-012 — Dual-product positioning](./0012-dual-product-positioning.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md)

## Contexte

[ADR-017](./0017-cherry-picking-from-filament-study.md) acte que Phase 10+
ajoute 7 features à Polysource (saved views, widgets, bulk async, workflow
integration, global search, command palette, audit non-Doctrine), et que
**chacune doit ship comme plugin** plutôt que comme feature monolithique du
`polysource/symfony-bundle` :

> Construire widgets/bulk/workflow/search comme features du symfony-bundle au
> lieu de plugins = dette structurelle.

Pour que ce sequencing tienne, il faut d'abord acter ce qu'est un "plugin"
dans Polysource, comment il se déclare, comment il est découvert, et quels
contrats publics il consomme avec quelles garanties de stabilité.

État actuel — on a 80% de la fondation :

- **DI tags** déjà conventionnés (cf. `the project context file` §"Symfony DI tags") :
  `polysource.data_source`, `polysource.resource`, `polysource.field_configurator`,
  `polysource.action`, `polysource.permission`.
- **Compiler passes** qui collectent les services tagged dans des registries
  (`PipelineCompilerPass` du tronc commun `polysource/filter`, par exemple).
- **PHP attribute** `#[AsResource]` (cf. ADR-005) qui auto-tagge un Resource
  sans services.yaml.
- **6 packages** publiés ensemble en v0.1.0 (cf. ADR-012) : `core`,
  `symfony-bundle`, `twig-theme`, `adapter-messenger`, `filter`,
  `easyadmin-filter-bridge`.

Les 20% manquants :

1. **Pas de notion formelle de "plugin"** au runtime. Aucun moyen pour un host
   admin de répondre à `bin/console polysource:plugins:list` ou pour un
   plugin de déclarer son nom + sa version.
2. **Pas de garantie semver** sur les interfaces publiques. Aujourd'hui
   chaque `interface` est implicitement de l'API publique, avec aucune
   discipline de breaking change → fragilité écosystème.
3. **Pas de point d'extension par capability** explicite. Quand on ajoute
   widgets en Phase 12, par où passe la déclaration ? Aujourd'hui la réponse
   serait "ajoute un tag `polysource.widget` et un compiler pass" — c'est
   correct mais non documenté comme contrat ; un futur plugin author n'a
   aucune source de vérité.

## Décision

### 1. Le primat des tags Symfony

**Les tags DI restent le contrat d'extension primaire.** Toutes les
contributions concrètes (Resources, Widgets, BulkActions, SearchProviders,
…) passent par des services tagged. C'est le pattern qui marche déjà, c'est
idiomatique Symfony, et c'est ce que l'étude
[ADR-017](./0017-cherry-picking-from-filament-study.md) §5.2 #1 recommande
("Tagged services — the primary extension contract").

L'introduction de `AdminPluginInterface` **ne remplace pas** ce système
— elle l'enveloppe d'une couche metadata + introspection.

### 2. `AdminPluginInterface` minimal

Un plugin est, runtime-side, un bundle Symfony qui implémente
`AdminPluginInterface`. Le contrat reste minimal — pas de méthode
`getMenuContributions()` ou `getWidgets()` parce que ces capabilities sont
fournies par les services tagged, pas par le plugin lui-même.

```php
namespace Polysource\Core\Plugin;

interface AdminPluginInterface
{
    /**
     * Globally-unique plugin identifier (Composer package name).
     *
     * Method named `getPluginName()` rather than `getName()` because
     * `Symfony\Component\HttpKernel\Bundle\Bundle::getName()` is final
     * (returns the bundle's PHP class basename — different concept).
     */
    public function getPluginName(): string;

    /**
     * Plugin version (semver string, e.g. "0.1.0", "1.2.3-beta.1").
     */
    public function getPluginVersion(): string;
}
```

Two methods, no return-type complexity. No PHP-extension dep, no external
requirement. **Bootstrap lifecycle** is handled by Symfony's existing
`Bundle::boot()` hook — duplicating it on the plugin interface created a
trait-vs-final-method conflict (Symfony's `Bundle::boot()` collides with
a trait-defined `boot()`) and added no value over the existing bundle
lifecycle. Plugins that need init logic override `Bundle::boot()` directly.

### 3. `#[AsPlugin]` attribute pour la DX

Pour éviter à chaque plugin author d'écrire 3 méthodes triviales, un
attribute fournit le shortcut :

```php
namespace Polysource\Plugin\Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class AsPlugin
{
    public function __construct(
        public readonly string $name,
        public readonly string $version,
    ) {
    }
}
```

```php
// Plugin author writes just this:
#[AsPlugin(name: 'polysource/adapter-messenger', version: '0.1.0')]
final class PolysourceMessengerBundle extends Bundle
{
}
```

Le `PolysourceBundle` (kernel) lit l'attribute via reflection au moment du
build et installe automatiquement le contrat `AdminPluginInterface` via un
trait interne. Le plugin author n'écrit aucune méthode.

Pour les plugins qui veulent un boot custom, l'interface est implémentable
explicitement — l'attribute reste optionnel.

### 4. Discovery + registry

Auto-discovery via tag `polysource.plugin` (auto-applied par l'attribute ou
quand un bundle implémente l'interface) + `PluginRegistry` collecté par
compiler pass. Le registry expose :

```php
final class PluginRegistry
{
    /** @return iterable<AdminPluginInterface> */
    public function all(): iterable;

    public function get(string $name): ?AdminPluginInterface;

    public function has(string $name): bool;
}
```

Un command Symfony `polysource:plugins:list` consomme le registry et affiche
le plugin set installé — utile pour le support et le debug.

### 5. Capability extension : un ADR dédié par capability

Chaque feature de Phase 11+ ([ADR-017](./0017-cherry-picking-from-filament-study.md) §4)
définit son propre contrat dans son propre ADR. Le pattern est uniforme :

| Capability | Tag DI | Interface contributeur | ADR |
|---|---|---|---|
| Saved views | `polysource.saved_view_scope` | `SavedViewScopeInterface` | [ADR-019](./0019-saved-views-architecture.md) (Phase 11) |
| Widgets | `polysource.widget` | `WidgetInterface` | ADR-020 (Phase 12) |
| Bulk actions | `polysource.bulk_action` | `BulkActionInterface` | ADR-021 (Phase 13) |
| Workflow | `polysource.workflow_resource` | `WorkflowAwareResource` | ADR-022 (Phase 14) |
| Search providers | `polysource.search_provider` | `SearchProviderInterface` | ADR-023 (Phase 15) |
| Cmd palette commands | `polysource.command` | `PaletteCommandInterface` | ADR-023 (Phase 15) |
| Audit handlers | `polysource.audit_handler` | `AuditHandlerInterface` | ADR-024 (Phase 16) |

Cette ADR-018 ne spécifie aucune de ces interfaces — elle pose le pattern.
Chaque ADR de capability suit le même format (interface + tag + registry +
compiler pass) pour que tous les plugin authors puissent compiler la même
mental model.

### 6. Versioning des contrats publics

Polysource adopte **semver strict** sur les interfaces du namespace
`Polysource\Plugin\Contract\*` à partir de v1.0.0. Avant v1.0.0 (état actuel)
les interfaces peuvent évoluer librement entre minors.

**Convention `@since` PHPDoc** sur chaque interface publique :

```php
namespace Polysource\Plugin;

/**
 * @since 0.1.0
 */
interface AdminPluginInterface
{
    /** @since 0.1.0 */
    public function getName(): string;

    /** @since 0.1.0 */
    public function getVersion(): string;

    /** @since 0.1.0 */
    public function boot(): void;
}
```

Ajouter une méthode à une interface publique = **breaking change** =
**MAJOR bump**. Pour éviter ce coût, les capabilities additives passent par
de **nouvelles interfaces** (`MenuContributorV2Interface` ou
`AdvancedWidgetInterface`) qu'un plugin implémente en addition.

L'attribute `#[AsPlugin]` lui-même est public-API et soumis à la même règle.

### 7. Periodic plugin compatibility check (out of scope v0.1)

Idée déférée : un système où chaque plugin déclare la `polysource/symfony-bundle`
version qu'il a été testé contre, et le kernel warn (pas crash) si on
charge un plugin testé contre une version trop ancienne. Pattern Filament
("required ranges" by plugins). À spécifier dans une ADR ultérieure si le
besoin se confirme post-v0.2.

### 8. Migration path des packages existants

Les 6 packages v0.1 actuels reçoivent l'annotation au moment de Phase 10
(release prep) :

```php
#[AsPlugin(name: 'polysource/symfony-bundle', version: '0.1.0')]
final class PolysourceBundle extends Bundle { … }

#[AsPlugin(name: 'polysource/adapter-messenger', version: '0.1.0')]
final class PolysourceAdapterMessengerBundle extends Bundle { … }

// idem pour twig-theme, filter, easyadmin-filter-bridge
```

`polysource/core` n'est pas un bundle (zéro Symfony dep par ADR-001), donc
n'est pas un plugin. Il reste une lib utilisée par les autres.

`polysource/twig-theme` est un bundle template-only. On lui donne
l'attribute pour cohérence, même si sa contribution est uniquement Twig
(pas de tagged services PHP).

Migration coût : ~30 minutes (6 attributs + bundle classes existent
déjà). Tests : ajouter 1 test fonctionnel `PluginRegistry::all()` retourne
les 5 bundles attendus.

## Conséquences

### Positives

1. **Plugin authors ont une source de vérité.** Le pattern "déclare un bundle
   + tag tes services + ajoute `#[AsPlugin]`" est documentable en 1 page
   markdown et reproductible.
2. **Introspection at runtime.** `bin/console polysource:plugins:list`,
   support qui peut demander "quels plugins, quelles versions", debug
   facile.
3. **Versioning explicite.** À partir de v1.0.0, les contrats publics sont
   semver-stables. Plugin authors savent quand un major bump arrive et ce
   qu'il implique.
4. **Pattern uniforme pour Phase 11+.** Chaque capability suit la même
   recette (interface + tag + registry + compiler pass + ADR). Pas
   d'invention par feature — productivité de design accrue.
5. **Compatible avec l'existant.** Aucun breaking change runtime — l'ajout
   de `AdminPluginInterface` est additif. Les 6 packages actuels migrent
   sans douleur.

### Négatives / Trade-offs

1. **Une nouvelle abstraction à comprendre.** "Plugin" vient s'ajouter à
   "Bundle" et "Resource" dans le vocabulaire Polysource. Risque de
   confusion. Mitigé par : convention claire (Plugin = Bundle annoté) et
   docs.
2. **Coût Phase 10.** Migration des 6 bundles existants + écriture des
   classes (`AdminPluginInterface`, `AsPlugin`, `PluginRegistry`,
   `PluginCompilerPass`, `PluginListCommand`) ≈ 1-2 jours.
3. **Versioning strict pre-1.0 = self-imposed discipline.** Avant v1.0.0
   on est libres techniquement, mais on s'oblige par cette ADR à documenter
   chaque évolution des contrats publics avec `@since` et changelog. Coût
   de discipline non nul.
4. **Pas de mécanisme de désactivation runtime.** Un plugin chargé est
   actif. Dé-tagger ou désinstaller passe par Composer/services.yaml, pas
   par un runtime toggle. Acceptable pour v0.1 ; à reconsidérer si on
   ajoute un dashboard d'admin des plugins.

## Alternatives considérées

### A. Pas d'`AdminPluginInterface`, tags-only

**Rejeté.** C'est l'état actuel. Marche pour les capabilities mais ne donne
pas de réponse à "quels plugins sont installés", "quelle version", "ce
plugin est-il compatible avec mon kernel". Le coût marginal d'ajouter
3 méthodes + un attribute est tellement faible que l'option "pas
d'abstraction du tout" perd.

### B. `AdminPluginInterface` complet (Filament-style)

L'étude propose :

```php
interface AdminPluginInterface
{
    public function getName(): string;
    public function getVersion(): string;
    public function boot(ContainerInterface $container): void;
    public function getMenuContributions(): iterable;       // MenuItem[]
    public function getDashboardWidgets(): iterable;        // WidgetInterface[]
    public function getSearchProviders(): iterable;         // SearchProviderInterface[]
    public function getRoutes(): RouteCollection;
}
```

**Rejeté.** Bake les capabilities (menu, widgets, search) dans le contrat
core. Conséquence : ajouter une nouvelle capability = breaking change de
`AdminPluginInterface`. Mauvaise structure. Le pattern "core minimal +
capabilities séparées" qu'on retient (§5) est strictement plus extensible.

### C. Interface seule, pas d'attribute

**Rejeté.** Force chaque plugin author à écrire 3 méthodes triviales. DX
dégradée vs. l'attribute. Coût zéro pour offrir les deux options.

### D. Attribute seul, pas d'interface

**Rejeté.** Pas de boot lifecycle, pas de typage explicite. Une partie des
plugins futurs voudront un boot personnalisé (validation de dépendances,
log structuré, self-test) — sans interface ils doivent inventer leur
propre mécanisme. Coût zéro pour offrir les deux options.

### E. Symfony Bundle = Plugin (sans abstraction supplémentaire)

**Rejeté.** Tous les bundles ne sont pas des plugins Polysource (un host
peut avoir 50 bundles dans son `config/bundles.php` dont seulement 3
sont Polysource-related). Mélanger les deux concepts pollue l'API et
empêche `bin/console polysource:plugins:list` de filtrer les plugins
Polysource des autres bundles.

## Plan d'exécution

1. Cette ADR-018 (signée, ce commit).
2. **Phase 10 task #1** — créer `polysource/core` ajout :
   - `Polysource\Plugin\AdminPluginInterface`
   - `Polysource\Plugin\Attribute\AsPlugin`
   - `Polysource\Plugin\Trait\HasPluginMetadata` (lit l'attribute)
   - PHPUnit : interface + attribute + trait round-trip.
3. **Phase 10 task #2** — créer `polysource/symfony-bundle` ajout :
   - `Polysource\Bundle\Plugin\PluginRegistry`
   - `Polysource\Bundle\Plugin\PluginCompilerPass` (collecte les services
     tagged `polysource.plugin`)
   - `Polysource\Bundle\Command\PluginListCommand` (rend `polysource:plugins:list`)
   - PHPUnit fonctionnel : kernel boot + registry + commande.
4. **Phase 10 task #3** — annoter les 5 bundles existants :
   `PolysourceBundle`, `PolysourceAdapterMessengerBundle`,
   `PolysourceTwigThemeBundle`, `PolysourceFilterBundle`,
   `PolysourceEasyAdminFilterBridgeBundle`.
5. **Phase 10 task #4** — documenter dans `docs/user/concepts/plugin.md`
   le pattern complet : "comment écrire un plugin Polysource" + exemple
   du dummy plugin "hello world" (3 fichiers).
6. **Phase 10 release v0.1.0** ship le tout (cf. ADR-017 §4 sequencing).

Estimation Phase 10 dédiée à ADR-018 : **2-3 jours**, tests inclus.
Inscrit dans le budget Phase 10 (≤ 1 semaine total) avec le reste du
release prep.

## Pour relire cette décision plus tard

Cette ADR doit être révisée si :

- Un mécanisme de désactivation runtime des plugins devient nécessaire
  (UI admin, feature flags par plugin, etc.) — pas le cas v0.1.
- Le pattern "core minimal + capabilities séparées" se révèle insuffisant
  — il faudrait alors documenter les patterns que les plugin authors
  réinventent et choisir lesquels remonter au core.
- L'écosystème dépasse 20 plugins tiers : à ce moment, des contraintes
  comme "compatibilité matrix" ou "plugin permissions" (un plugin peut-il
  modifier le menu d'un autre ?) deviendront pertinentes. Pas avant.
