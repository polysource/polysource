# Polysource — Vision produit

> Document court, à relire à chaque release majeure. Si le code commence à dériver de cette vision, c'est ici qu'on remet l'aiguille au centre.

## 1. Vision

> *Polysource fournit deux outils complémentaires aux applications Symfony :*
> *(1) une couche d'enrichissement pour les filtres EasyAdmin qui s'installe par dessus EasyAdmin sans le forker ;*
> *(2) un panel d'admin standalone pour les ressources que Doctrine ORM ne couvre pas naturellement (Messenger, S3, Redis, HTTP, Meilisearch).*

Les deux produits **partagent les mêmes primitives** (`polysource/core`,
`polysource/filter`) et **peuvent vivre dans la même application Symfony**.
Le pivot dual-produit a été acté en [ADR-012](../adr/0012-dual-product-positioning.md).

Le succès du projet se mesure à deux questions :

- *« En combien de temps un utilisateur EasyAdmin existant gagne des
  filtres avancés (presets dates, ranges, multi-select, persistance
  session, chips) ? »* — cible : **5 minutes**, zéro config.
- *« En combien de temps un utilisateur Symfony passe d'un Messenger failed
  transport invisible à un dashboard avec retry/dismiss qui marche ? »* —
  cible : **5 minutes**.

## 2. Non-objectifs (ce que Polysource ne fera pas)

Le projet a un scope étroit et discipliné. Sont **explicitement hors scope** :

- **Remplacer EasyAdmin sur le cas Doctrine pur.** EasyAdmin a 9 ans d'avance et est excellent. Polysource cohabite avec lui via un bridge optionnel, ne le concurrence pas frontalement.
- **Générateur de CRUD Doctrine.** Doctrine est un adapter parmi d'autres, pas la cible.
- **Internal-tool builder no-code** (Retool / Appsmith).
- **Dashboards de BI** (Grafana / Metabase).
- **Multi-tenant SaaS** (Forest Admin).
- **Real-time / live update** v1 (peut-être v2, sur demande utilisateur réel).
- **Permissions fines (RBAC granulaire en UI).** Polysource s'appuie sur les Voters Symfony existants. Pas de UI de gestion des rôles.
- **Authentication.** Polysource utilise la firewall Symfony en place. Pas d'auth fournie.

Ces non-objectifs ne sont pas négociables avant d'avoir validé le scope étroit avec **au moins 10 utilisateurs publics**.

## 3. Positionnement par rapport à EasyAdmin

Polysource cohabite avec EasyAdmin de **deux manières distinctes** selon le
produit considéré :

### Produit 1 — `polysource/admin` standalone

| Aspect | EasyAdmin | `polysource/admin` |
|---|---|---|
| Cible | Entités Doctrine ORM | Ressources techniques non-Doctrine ou multi-source |
| Couplage | Doctrine obligatoire | Aucune dépendance Doctrine dans `core` |
| Premier cas | Admin produit / utilisateur / commande | Messenger failed messages / S3 / Redis |
| Cohabitation | — | URL prefix séparé, peut vivre dans le même app Symfony |

Ici le projet **refuse explicitement de se définir comme « concurrent
d'EasyAdmin »**. Pitch : *« un admin Symfony pour tout ce qui n'est pas une
entité Doctrine. »*

### Produit 2 — `polysource/easyadmin-filter-bridge`

Ici Polysource n'est pas un concurrent : c'est un **complément qui
s'installe par-dessus EasyAdmin** et enrichit son système de filtres natif
**sans forker**. Voir [ADR-012](../adr/0012-dual-product-positioning.md) pour
les seams techniques utilisés (`FilterConfiguratorInterface` auto-tagué,
override Twig de `crud/filters.html.twig`, EventSubscriber sur
`BeforeCrudActionEvent`).

Pitch : *« Tu as déjà EasyAdmin. Installe `polysource/easyadmin-filter-bridge`,
tes filtres deviennent magiques. »*

### Règle commune aux deux produits

Aucun des deux ne tente de remplacer EasyAdmin sur le cas Doctrine pur.
Aucun ne se présente comme « alternative à EasyAdmin ». Les deux sont
documentés comme **outils complémentaires** dans toute communication
publique (issues GitHub, PR, blog, talks).

## 4. Premier cas d'usage recommandé

**Messenger failed messages — dashboard avec retry / retry-all / dismiss / purge.**

Pourquoi ce cas en premier :

1. **Lacune objective et reconnue.** Symfony Messenger n'a pas d'UI native (seulement le CLI `messenger:failed:*`). Ça fait 6+ ans que la communauté en parle.
2. **Démo visuelle parfaite.** `docker compose up`, planter quelques messages, voir le dashboard.
3. **Différenciant immédiat.** Aucun outil Symfony existant ne couvre ce cas avec une UX correcte.
4. **Met l'architecture à l'épreuve.** Source read-only sans total count, identifiants opaques, actions custom non-CRUD — tous les critères que Polysource prétend gérer mieux que EasyAdmin.
5. **Petit en taille.** Un seul adapter, ~500 lignes de code, 1 démo.

C'est le **cheval de Troie** : un utilisateur installe Polysource pour cette fonctionnalité, puis découvre les autres adapters.

## 5. Philosophie d'architecture

Quatre principes structurants (voir [`../architecture/target-architecture.md`](../architecture/target-architecture.md) pour les signatures PHP) :

1. **Interface Segregation Principle (ISP)** — `DataSourceInterface` à 3 méthodes (`search`, `find`, `count`). `WritableDataSourceInterface` étend avec 3 méthodes supplémentaires (`create`, `update`, `delete`). Une source read-only n'implémente pas l'écriture, et l'UI s'adapte automatiquement.

2. **Value objects immutables** — `DataQuery`, `DataPage`, `DataRecord`, `DataPayload` sont `readonly`. Aucune mutation interne ne fuit dans l'API publique.

3. **Aucune fuite d'implémentation** — aucune méthode publique ne retourne ou n'accepte un type Doctrine, Redis, HttpClient, etc. Tout passe par les VO du `core`. C'est la leçon la plus importante de l'analyse d'EasyAdmin (qui fuit `QueryBuilder` dans 5 contrats publics).

4. **Tag de service Symfony pour adapters** — modèle Sylius Grid : `polysource.data_source` avec alias. Permet d'enregistrer un adapter Redis, Meilisearch, S3 sans modifier le core.

Inspirations directes :
- **Sylius Grid** — `DataSourceInterface` minimal + tag service.
- **React Admin** — 9 méthodes `dataProvider` comme vocabulaire commun.
- **Filament** (DX uniquement) — builders fluides `Form::schema([...])`, à introduire après v0.3.
- **Orchid** — séparation `query() → array` et `layout(array)`.

Anti-inspirations :
- **Sonata `AdminInterface` à 80 méthodes** — God Object à éviter.
- **EasyAdmin et `QueryBuilder` dans les contrats publics** — couplage qu'on refuse.

## 6. Stratégie open source

| Aspect | Choix |
|---|---|
| Licence | **MIT** (compatible avec EasyAdmin pour réutilisation des templates Twig) |
| Hébergement | GitHub `github.com/polysource/polysource`, monorepo avec composer split par package |
| Versionning | SemVer strict. Avant v1.0, classes `@experimental` peuvent évoluer. Après v1.0, gel API. |
| CI | GitHub Actions, matrix PHP 8.1/8.2/8.3/8.4 × Symfony 6.4/7.2/7.4 × EasyAdmin 4.24/5.0 (cf. ADR-015) |
| Coverage | `core` ≥ 90 % unit tests, intégration testcontainers par adapter |
| Gouvernance | Solo-mainteneur première année, ouverture progressive à des co-mainteneurs par adapter |
| Contribution | CONTRIBUTING.md formel, ADR publics pour décisions structurantes, issues étiquetées par adapter |

## 7. Stratégie de packages

Le repo `polysource/polysource` est un **monorepo avec composer split**. Chaque package a son propre `composer.json`, son propre `composer install`, et est publié indépendamment sur Packagist.

### Packages v0.1 (16 packages livrés sur `main`, tag en cours)

**Primitives partagées** (utilisables seules, zéro Symfony dans `core`) :

- `polysource/core` — contracts + value objects, **zéro dépendance Symfony**
- `polysource/filter` — `FilterCollection`, `FilterService` (session), saved
  views, enhanced form types, Twig extension `filter_tags` (utilisable seul,
  sans Polysource Admin ni EasyAdmin)

**Produit 1 — `polysource/admin` standalone** :

- `polysource/symfony-bundle` — wiring (DI, routing, ArgumentResolvers, Twig)
- `polysource/twig-theme` — templates Twig par défaut (copiés et adaptés depuis EasyAdmin v5, MIT)
- 6 adapters : `polysource/adapter-{messenger,doctrine,redis,flysystem,http,meilisearch}`

**Produit 2 — Bridge EasyAdmin** :

- `polysource/easyadmin-filter-bridge` — `FilterConfiguratorInterface`
  auto-tagués, enhanced form types, 4 custom filters (Between/In/NotNull/FullText),
  EventSubscribers (session, saved-view apply), override Twig. Ne touche pas
  au code d'EasyAdmin.

**Capabilités transverses (opt-in, packages séparés)** :

- `polysource/audit` — log GDPR Art. 30 / HIPAA des actions admin (Doctrine
  storage, CSV export, retention purge)
- `polysource/bulk-async` — bulk actions over Messenger avec progression
  live (Mercure) + cancel mid-flight
- `polysource/widgets` — widgets dashboard (KPI counters, top-N lists,
  sparkline charts)
- `polysource/search` — palette Cmd+K cross-resource avec aggregator fan-out
- `polysource/workflow-bridge` — intégration Symfony Workflow (transition
  buttons auto, state chip)

### Packages post-v0.1 (à demande utilisateur réel uniquement)

- `polysource/adapter-config` — fichiers YAML/JSON (déjà couvert partiellement
  par `polysource/adapter-flysystem` pour les contenus, à matérialiser si
  besoin de form binding spécifique)
- Bridges futurs (`search-meilisearch`, `search-algolia`, `search-elasticsearch`)
  — extensions du package `polysource/search` via `SearchProviderInterface`

**Aucun package supplémentaire ne sera ajouté avant v1.0 sans utilisateur identifié qui en a besoin.** Voir le plan détaillé dans [`../roadmap/development-plan.md`](../roadmap/development-plan.md).

### Conventions

- **Namespace racine** : `Polysource\Core\…`, `Polysource\Bundle\…`, `Polysource\Adapter\Messenger\…`, etc.
- **Vendor Composer** : `polysource/<package-name>`
- **Tag DI** : `polysource.<purpose>` (ex: `polysource.data_source`, `polysource.field_configurator`, `polysource.action`)
- **Maker command** : `bin/console make:polysource:resource`, `make:polysource:adapter`

## 8. À relire à chaque release majeure

Cette vision n'est pas figée. Elle doit être contestée avant chaque sortie majeure. Si un critère a changé (ex : un utilisateur réel demande une feature hors scope), ouvrir une ADR pour discuter, ne pas l'implémenter sous le capot.
