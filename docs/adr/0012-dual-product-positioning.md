# ADR-012 — Positionnement dual-produit pour v0.1

- **Date** : 2026-05-03
- **Statut** : Accepté
- **Décide pour** : v0.1+
- **Modifie** : la lecture qui était faite du scope dans
  [`docs/strategy/product-vision.md`](../strategy/product-vision.md) §3 et §7.

## Contexte

Phase 9 (documentation utilisateur) a été livrée le 2026-05-03. Au moment de
préparer la Phase 10 (release v0.1.0), une revue honnête du scope a fait
remonter trois constats :

1. **v0.1 est trop maigre comme produit publié.** Un seul adapter
   (Messenger), pas de filtres, pas de personnalisation de templates, pas de
   préférences utilisateur. C'est un PoC, pas un produit.
2. **EasyAdmin v5 expose des points d'extension non utilisés** par sa propre
   audience (notamment `FilterConfiguratorInterface` auto-discovered par tag
   DI `ea.filter_configurator`). Voir le mapping détaillé en
   [§Vérification technique](#vérification-technique).
3. **La cible "non-Doctrine" et la cible "améliorer EasyAdmin" sont
   complémentaires, pas alternatives.** Un même utilisateur Symfony peut
   vouloir les deux : `polysource/admin` pour ses Messenger failed messages,
   `polysource/easyadmin-filter-bridge` pour enrichir les filtres de ses
   entités Doctrine.

L'ancien plan (un seul produit "Polysource Admin" axé sur les ressources
non-Doctrine, avec un éventuel bridge EasyAdmin en v0.3) ne capture pas
cette double opportunité et reporte trop tard la valeur communautaire la
plus immédiate.

## Décision

À partir de la v0.1.0, **Polysource est un produit dual** :

### Produit 1 — `polysource/admin` (standalone)

Ce que la v0.1 avait construit : un panel d'admin Symfony pour des sources
**non-Doctrine** (Messenger failed, S3, Redis, HTTP, Meilisearch). Il vit
sous son propre URL prefix (`/admin/...` par défaut), il a son routage, ses
controllers, son thème Twig, ses controllers d'action.

Cette branche **ne change pas** par rapport à ce qui a été livré aux
phases 1-9. Elle est juste **renommée** dans la documentation et le pitch
pour bien la séparer du Produit 2.

### Produit 2 — `polysource/easyadmin-filter-bridge` (NOUVEAU)

Un package qui s'installe par-dessus une application EasyAdmin v5 existante
et **enrichit le système de filtres natif** sans forker EasyAdmin :

- Auto-tag des `FilterConfiguratorInterface` qui swappent les `formType` des
  filtres built-in (`DateTimeFilter`, `TextFilter`, `NumericFilter`,
  `BooleanFilter`, `ChoiceFilter`, `EntityFilter`, `ComparisonFilter`) pour
  des form types plus riches (presets dates, ranges, multi-select, Select2-like).
- Filtres custom additionnels (`BetweenDateFilter`, `InFilter`,
  `NotNullFilter`, `FullTextSearchFilter`) que l'utilisateur ajoute via le
  classique `configureFilters()` d'EasyAdmin.
- Override Twig de `bundles/EasyAdminBundle/crud/filters.html.twig` pour
  afficher des **chips/tags** des filtres actifs au-dessus du tableau.
- `EventSubscriber` sur `BeforeCrudActionEvent` pour **persister les filtres
  en session** (par CRUD controller FQCN), de sorte qu'un opérateur retrouve
  ses filtres en revenant sur la page.
- Modes UI configurables (subpanel coulissant / accordéon / simple).

**Argument de vente** : `composer require polysource/easyadmin-filter-bridge`,
zéro config, et tous les filtres EasyAdmin existants gagnent les
fonctionnalités ci-dessus.

### Primitives partagées

Les deux produits reposent sur des primitives mutualisées :

| Package | Statut | Rôle |
|---|---|---|
| `polysource/core` | déjà fait | `DataSourceInterface`, `FilterCriterion`, value objects immutables |
| `polysource/filter` | **NOUVEAU** | `FilterCollection`, `FilterService` (session), form types abstraits, Twig extension `filter_tags` |
| `polysource/twig-theme` | déjà fait | templates partagés |
| `polysource/symfony-bundle` | déjà fait | wiring DI/routing/controllers (Produit 1) |
| `polysource/easyadmin-filter-bridge` | **NOUVEAU** | configurators + form types + EventListener (Produit 2) |
| `polysource/adapter-messenger` | déjà fait | adapter showcase (Produit 1) |

`polysource/filter` est **utilisable seul** : un projet Symfony sans
EasyAdmin ni Polysource Admin peut l'installer juste pour son système de
filtres avec persistance session et form-types riches.

## Conséquences

### Ce qui change pour la roadmap

L'ordre des phases est modifié. **Phase 10** (release v0.1.0) est repoussée.
Deux nouvelles phases s'intercalent :

- **Phase 9.5** — `polysource/filter` (extraction et durcissement de la
  primitive filtre depuis `polysource/core`, ajout du `FilterService` session,
  des form types abstraits, du Twig rendering).
- **Phase 9.7** — `polysource/easyadmin-filter-bridge` (les 7 configurators,
  les 7 enhanced form types, l'EventSubscriber, les overrides Twig).

L'estimation Phase 9.5 + 9.7 : **4 à 6 semaines**. Voir
[`../roadmap/development-plan.md`](../roadmap/development-plan.md) pour le
détail des livrables.

### Ce qui change pour la communication

Le pitch de la v0.1.0 cesse d'être *« un admin Symfony pour les ressources
non-Doctrine »* et devient :

> *« Polysource — deux outils complémentaires pour Symfony :*
> *(1) une couche d'enrichissement pour les filtres EasyAdmin, et*
> *(2) un panel d'admin standalone pour les ressources non-Doctrine. »*

L'annonce v0.1.0 vise **deux audiences distinctes** :

- **Utilisateurs EasyAdmin existants** (audience large, ~12k stars sur
  EasyAdmin) — message : « tes filtres deviennent magiques en 5 minutes ».
- **Utilisateurs Symfony qui ont des Messenger failed / S3 / Redis à
  administrer** (audience niche) — message : « un panel d'admin pour ce
  qu'EasyAdmin ne couvre pas ».

### Ce qui ne change pas

- Le scope **strict** de chacun des deux produits. Aucun n'essaie de
  remplacer EasyAdmin sur Doctrine pur (cf. `product-vision.md` §2).
  `polysource/easyadmin-filter-bridge` est un **complément** d'EasyAdmin,
  pas un remplaçant.
- Les principes architecturaux du `core` : ISP, value objects immutables,
  zéro fuite Doctrine, tag DI pour adapters (cf. `product-vision.md` §5).
- Le stop-loss à 18 mois (cf. `product-vision.md` §6).

### Risque introduit

- **Maintenance d'un bridge contre une dépendance externe** (EasyAdmin v5).
  Si EasyAdmin sort une v6 incompatible, le bridge devra suivre. Mitigation :
  CI matrix avec EasyAdmin 5.x et future 6.x dès qu'elle sort.
- **Confusion possible** entre les deux produits dans la doc et la
  communication. Mitigation : README racine clair, deux sections distinctes
  dans `docs/user/`, deux annonces séparées.

## Vérification technique

Les seams EasyAdmin nécessaires ont été vérifiés sur le code source de
EasyAdmin v5 (référence locale au 2026-05-03) :

| Seam | Verdict | Source |
|---|---|---|
| `FilterConfiguratorInterface` auto-tagué et appelé sur tous les filtres | 🟢 Disponible | `src/Contracts/Filter/FilterConfiguratorInterface.php`, `src/DependencyInjection/EasyAdminExtension.php:47-48` |
| Mutation du `formType` d'un filtre via le DTO | 🟢 Disponible | `src/Filter/FilterTrait.php` : `setFormType()`, `setFormTypeOptions()` |
| Override Twig `crud/filters.html.twig` | 🟢 Disponible | `src/DependencyInjection/EasyAdminExtension.php:71-81` (path `templates/bundles/EasyAdminBundle/`) |
| EventSubscriber sur `BeforeCrudActionEvent` | 🟢 Disponible | `src/Event/BeforeCrudActionEvent.php` |
| Plug d'une `EntityRepositoryInterface` non-Doctrine | 🔴 Bloqué | `src/Contracts/Orm/EntityRepositoryInterface.php:16` retourne `Doctrine\ORM\QueryBuilder` |
| Override automatique de `DateTimeFilter` pour TOUS les `DATETIME_MUTABLE` | 🟢 Disponible (via Configurator) | `src/Factory/FilterFactory.php:81-89` (la map `$doctrineTypeToFilterClass` est `private static` mais le Configurator est appelé après et peut muter le DTO) |

Le verdict **rouge** sur les datasources non-Doctrine via EasyAdmin
confirme la décision : `polysource/admin` reste un produit standalone, pas un
plug-in EasyAdmin.

## Alternatives considérées et rejetées

### Alternative 1 — Sortir v0.1.0 avec uniquement le standalone, comme prévu

**Rejeté.** Le produit serait trop maigre pour justifier une annonce
publique. Stop-loss à 18 mois mal positionné — on partirait avec une dette
de visibilité dès le jour 1.

### Alternative 2 — Forker EasyAdmin pour l'ouvrir aux datasources non-Doctrine

**Rejeté** (analyse détaillée hors-repo). Coût ~38 % de `src/` à réécrire,
divergence permanente avec upstream, aucune chance d'être merged. Mauvais
ratio effort / valeur.

### Alternative 3 — Reporter le bridge EasyAdmin à v0.3 (plan original)

**Rejeté.** La v0.3 est à 6 mois minimum. Sortir un produit standalone
maigre puis attendre 6 mois pour le hit communautaire est un mauvais
pari sur l'attention.

## Critères de succès propres au pivot

À 12 mois de la release v0.1.0 :

- [ ] `polysource/easyadmin-filter-bridge` : 200 installations Packagist,
      30 stars GitHub
- [ ] Au moins **un** issue / PR EasyAdmin référençant le bridge comme
      solution recommandée pour un cas filtre
- [ ] `polysource/admin` (standalone) : 50 installations Packagist
- [ ] CI matrix verte sur EasyAdmin 5.x + 6.x si sortie

Si le bridge n'atteint pas ces seuils à 12 mois, **revoir le positionnement
dual** : soit l'audience EasyAdmin n'est pas atteignable, soit le bridge
n'apporte pas la valeur perçue. Décision à prendre entre :
- recentrer sur le standalone (et accepter une audience plus niche),
- ou revoir la pitch / les features du bridge.

## Références

- [`../strategy/product-vision.md`](../strategy/product-vision.md) — vision
  produit mise à jour pour refléter le pivot
- [`../roadmap/development-plan.md`](../roadmap/development-plan.md) —
  Phases 9.5 et 9.7 ajoutées
- [`0011-pre-v1.0-freeze-checklist.md`](./0011-pre-v1.0-freeze-checklist.md) —
  items de gel API à traiter avant v1.0 (inchangés par cet ADR)
