# Polysource — Plan de développement

> Plan détaillé de la phase 0 à la phase 10. Objectif : sortir une **v0.1.0**
> publiée sur Packagist avec un produit dual (cf.
> [ADR-012](../adr/0012-dual-product-positioning.md)) :
> *(1)* `polysource/easyadmin-filter-bridge` — couche d'enrichissement filtres
> EasyAdmin ; *(2)* `polysource/admin` standalone avec adapter Messenger.
>
> Ce plan est **un contrat**. Avant de commencer le code, il doit être
> validé. Toute déviation significative pendant l'implémentation doit faire
> l'objet d'une mise à jour explicite de ce fichier (ADR si nécessaire).
>
> **Mise à jour majeure 2026-05-03** — pivot dual-produit acté
> ([ADR-012](../adr/0012-dual-product-positioning.md)). Phases 9.5 et 9.7
> ajoutées. Phase 10 (release) repoussée. Estimation totale v0.1.0 portée de
> ~7 à ~12-14 semaines.

## 0. Hypothèses de travail

- **1 développeur senior à temps plein** (ou équivalent ~25 h/semaine)
- **Stack v0.1** : **PHP `>=8.1`**. Symfony **par-package** : le tronc commun (`polysource/filter`) + le bridge (`polysource/easyadmin-filter-bridge`) advertise `^5.4 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0` (audience-capture EA v4) ; `polysource/symfony-bundle` + `polysource/adapter-messenger` requièrent `^6.4 \|\| ^7.0 \|\| ^8.0` (utilisent des APIs Sf 6.2+). Twig 3. Cf. [ADR-015](../adr/0015-multi-version-compatibility-baseline.md) amendement 2026-05-05 (2/2), supersedes ADR-007.
- **Bridge EasyAdmin** : EA **`^4.24 \|\| ^5.0`** (capture audience EA v4)
- **Doctrine ORM** (côté bridge) : **`^2.20 \|\| ^3.6`**
- **CI matrix** : 5 jobs (4 LTS + 1 non-LTS Sf 7.2) — floor effectif Sf 6.4 LTS car la matrice teste le monorepo entier incluant `polysource/symfony-bundle`. Sf 5.4 pour filter + bridge est composer-validé (advertised) mais pas test-gated par cette matrice (cf. ADR-015 amendement 2026-05-05 (2/2))
- **Démos runnable** (`make demo*` depuis la racine) :
  - `make demo` → Messenger failed-messages (Phase 8, port 8080)
  - `make demo-bridge` → EA v5 + bridge (Phase 9.7, port 8081, PHP 8.4 + Sf 7.4 + EA 5)
  - `make demo-bridge-v4` → EA v4 + bridge (port 8083, PHP 8.1 + Sf 6.4 + EA 4.29 — **prouve le floor du baseline ADR-015**)
  - `make demo-filter` → `polysource/filter` standalone (port 8082, vanilla Symfony sans EasyAdmin — **vitrine du tronc commun pour audience non-EA**)
- **Environnement de dev** : Docker Compose + Makefile + DDEV optionnel (cf. [ADR-008](../adr/0008-development-environment.md))
- **Pas de DX builders fluides en v0.1** (Filament-style arrive en v0.3+)
- **Pas de bridge EasyAdmin en v0.1** (v0.3)
- **Pas d'écriture (`Writable*`) en v0.1** — Messenger failed est lecture + actions custom, pas CRUD
- **Templates Twig copiés depuis EasyAdmin v5** sous licence MIT avec attribution

## 0bis. Vue d'ensemble

| Phase | Objet | Estimation | Livrable visible | Statut |
|---|---|---|---|---|
| 0 | Setup repo + docs + Docker + Makefile + ADR | 0,5 sem | repo prêt à recevoir du code | ✅ |
| 1 | `core` — contracts + VO `final readonly` | 1 sem | package Composer publiable | ✅ |
| 2 | `symfony-bundle` — DI + routing | 1 sem | `composer require` qui ne plante pas | ✅ |
| 3 | `twig-theme` — templates minimaux | 0,5 sem | rendu HTML basique | ✅ |
| 4 | `adapter-messenger` — read-only | 1 sem | liste failed messages affichée | ✅ |
| 5 | Actions retry / dismiss | 0,5 sem | boutons fonctionnels | ✅ |
| 6 | Permissions Symfony | 0,3 sem | Voter respecté | ✅ |
| 7 | Tests unitaires + fonctionnels | 1 sem | CI verte, coverage `core` ≥ 90 % | ✅ |
| 8 | App de démo Docker | 0,5 sem | `make demo` qui marche | ✅ |
| 9 | Documentation utilisateur | 0,5 sem | guide « 5 minutes » prêt | ✅ |
| **9.5** | **`polysource/filter`** — extraction primitive filtre | **2-3 sem** | **package autonome avec session + form types abstraits** | ⏳ |
| **9.7** | **`polysource/easyadmin-filter-bridge`** — Produit 2 | **2-3 sem** | **drop-in dans une app EasyAdmin** | ⏳ |
| 10 | Préparation release v0.1.0 (dual product) | 0,5 sem | tags, Packagist, 2 annonces | ⏳ |
| **Total v0.1** | | **~12-14 semaines** | v0.1.0 publiée (dual product) |

## Phase 0 — Setup repo, Docker et ADR (0,5 semaine)

### Objectif

Le repo doit être prêt à recevoir du code : structure de dossiers, fichiers de gouvernance, environnement Docker fonctionnel, CI configurée, ADR rédigées.

### Livrables

- Arborescence finale du repo (cf. §11)
- Fichiers de gouvernance : `README.md`, `LICENSE`, `CONTRIBUTING.md`, `CODE_OF_CONDUCT.md` ✅ (déjà fait)
- 9 ADR dans `docs/adr/` ✅ (à faire en Phase 0)
- `composer.json` racine pour le monorepo
- `.github/workflows/ci.yml` — CI matrix v0.1 (PHP 8.4 × Symfony 7.4)
- **Environnement Docker** : `Dockerfile` (php-fpm 8.4) + `docker-compose.yml`
- **`Makefile`** avec cibles `install`, `test`, `phpstan`, `cs-fix`, `demo`, `clean`
- **Config DDEV** optionnelle : `.ddev/config.yaml`
- `the project context file` racine (contexte projet persistant)
- `.gitignore`, `.gitattributes`

### Fichiers à créer en Phase 0

```
.github/workflows/ci.yml
.github/ISSUE_TEMPLATE/bug_report.md
.github/ISSUE_TEMPLATE/feature_request.md
.github/PULL_REQUEST_TEMPLATE.md
.gitignore
.gitattributes
.local/CLAUDE.local.md           (gitignored — pointeur privé)
the project context file                          (public — contexte projet persistant)
Makefile
Dockerfile                         (php-fpm 8.4 + extensions Symfony)
docker-compose.yml
.ddev/config.yaml                  (optionnel)
composer.json                      (monorepo root, autoload-dev)
phpstan.neon.dist                  (level max)
phpstan-baseline.neon              (vide initialement)
.php-cs-fixer.dist.php             (PSR-12 + Symfony rules)
docs/adr/0001-data-record-identifier.md
docs/adr/0002-data-page-total-semantics.md
docs/adr/0003-routing-strategy.md
docs/adr/0004-admin-context-immutability.md
docs/adr/0005-configuration-mechanism.md
docs/adr/0006-envelope-mapper-serialization.md
docs/adr/0007-php-symfony-versions.md
docs/adr/0008-development-environment.md
docs/adr/0009-ai-assistant-context.md
docs/adr/README.md                 (index ADR)
```

### Dépendances

Aucune (juste git + Docker).

### Risques

- Faible — c'est de la plomberie standard.
- Risque mineur : config Docker mal alignée avec versions Symfony 7.4 — vérifier que les extensions PHP requises sont présentes (`pdo`, `pdo_mysql`, `intl`, `zip`, `opcache`).

### Critères d'acceptation

- [ ] `make install` clone, build le container, exécute `composer install`.
- [ ] `make test` lance PHPUnit sur tous les packages.
- [ ] `make phpstan` passe à level max.
- [ ] `docker compose up` démarre sans erreur sur une machine vierge.
- [ ] CI déclenchée à chaque push exécute au moins `composer validate` sur tous les packages.
- [ ] PR template impose de référencer une issue.
- [ ] Les 9 ADR sont rédigées et lues par l'utilisateur.

### Complexité

**Faible-moyenne** (Docker + Makefile demandent rigueur).

### Ordre d'implémentation

1. ADR 1-9 (à valider avant tout code)
2. `.gitignore`, `.gitattributes`, `composer.json` racine
3. Dockerfile + docker-compose.yml
4. Makefile
5. CI workflow GitHub Actions
6. Templates issue/PR
7. the project context file
8. Config DDEV optionnelle

## Phase 1 — Architecture core (1 semaine)

### Objectif

Définir les **contrats publics** et les **value objects** dans `polysource/core` — package PHP pur, **zéro dépendance Symfony**. C'est la fondation : si on se trompe ici, tout dérive.

### Livrables

- Package `polysource/core` autonome
- 3 interfaces `DataSource*`
- 7+ value objects immutables (`final readonly class` — PHP 8.4)
- 1 enum (`SortDirection` — PHP 8.4 enums OK)
- Tests unitaires 100 % sur les VO

### Fichiers/classes à créer

```
packages/core/
├── composer.json              ("php": "^8.4")
├── README.md
├── src/
│   ├── DataSource/
│   │   ├── DataSourceInterface.php
│   │   ├── WritableDataSourceInterface.php
│   │   └── BatchableDataSourceInterface.php   (optionnelle, pour findMany)
│   ├── Query/
│   │   ├── DataQuery.php                       (final readonly)
│   │   ├── DataPage.php                        (final readonly)
│   │   ├── DataRecord.php                      (final readonly)
│   │   ├── DataPayload.php                     (final readonly)
│   │   ├── FilterCriterion.php                 (final readonly)
│   │   ├── Pagination.php                      (final readonly)
│   │   └── SortDirection.php                   (enum)
│   ├── Resource/
│   │   ├── ResourceInterface.php
│   │   └── AbstractResource.php
│   ├── Field/
│   │   ├── FieldInterface.php
│   │   ├── FieldDto.php
│   │   └── FieldTrait.php
│   ├── Action/
│   │   ├── ActionInterface.php
│   │   ├── InlineActionInterface.php
│   │   ├── BulkActionInterface.php
│   │   └── ActionResult.php                    (final readonly)
│   ├── Filter/
│   │   ├── FilterInterface.php
│   │   └── FilterDto.php
│   ├── Permission/
│   │   └── PermissionInterface.php
│   ├── Exception/
│   │   ├── ResourceNotFoundException.php
│   │   ├── UnsupportedOperationException.php
│   │   └── DataSourceException.php
│   └── Polysource.php                          (constants, version)
└── tests/Unit/
    ├── Query/
    │   ├── DataQueryTest.php
    │   ├── DataPageTest.php
    │   └── FilterCriterionTest.php
    └── Resource/
        └── AbstractResourceTest.php
```

### Dépendances Composer

```json
{
    "name": "polysource/core",
    "require": {
        "php": "^8.4"
    },
    "require-dev": {
        "phpunit/phpunit": "^11.0"
    }
}
```

Aucune dépendance Composer obligatoire en runtime → package indépendant de Symfony.

### Risques

- **Risque #1 : interface `DataSourceInterface` mal dimensionnée.** Trop étroite → contributeurs vont devoir tout subclasser. Trop large → adapters simples deviennent impossibles à écrire.
  - **Mitigation** : se forcer à 3 méthodes (`search`, `find`, `count`).
- **Risque #2 : conventions PHP 8.4 inutilisables en PHP 8.0** (à anticiper pour v0.5).
  - **Mitigation** : documenter explicitement dans ADR-007 §Migration les patterns à remplacer.
- **Risque #3 : `DataRecord::identifier` confus** (`string|int` vs composite).
  - **Mitigation** : ADR-001 explicite. Décision : `string|int`.

### Critères d'acceptation

- [ ] Tous les VO sont `final readonly class` (PHP 8.4).
- [ ] `SortDirection` est un `enum` (PHP 8.4).
- [ ] `DataQuery::withFilter()`, `withSort()` retournent un nouveau VO (vérifié par tests).
- [ ] `DataPage` accepte `total = null` (sources cursor — cf. ADR-002).
- [ ] `WritableDataSourceInterface` étend `DataSourceInterface` (ISP).
- [ ] PHPStan level max passe sans erreur.
- [ ] Coverage unit ≥ 90 %.
- [ ] `composer require polysource/core` fonctionne sur un projet vide PHP 8.4.

### Complexité

**Moyenne.** L'écriture est mécanique avec PHP 8.4 (`final readonly` simplifie tout). Mais les choix de design sont structurants — chaque méthode publique sera difficile à changer après v1.0.

### Ordre d'implémentation

1. `Polysource.php` + composer.json (squelette)
2. VO simples : `Pagination`, `FilterCriterion` ; enum `SortDirection`
3. VO composés : `DataQuery`, `DataPage`, `DataRecord`, `DataPayload`
4. Interfaces : `DataSourceInterface`, `WritableDataSourceInterface`, `BatchableDataSourceInterface`
5. Resource : `ResourceInterface`, `AbstractResource`
6. Field : `FieldInterface` + `FieldDto` + `FieldTrait`
7. Action : `ActionInterface` + sous-types
8. Tests unitaires en parallèle de chaque étape (TDD).

## Phase 2 — Bundle Symfony (1 semaine)

### Objectif

`polysource/symfony-bundle` câble `core` dans un projet Symfony 7.4 : DI extension, autoconfiguration, routing dynamique, ArgumentResolver pour `AdminContext`, kernel.view listener pour rendre Twig.

### Livrables

- Bundle Symfony installable via `composer require`
- Routing avec routes physiques par resource (`/admin/{resourceName}`, cf. ADR-003)
- ArgumentResolver pour injecter `AdminContext`
- Listener `kernel.view` qui rend les templates

### Fichiers/classes à créer

```
packages/symfony-bundle/
├── composer.json              ("symfony/framework-bundle": "^7.4")
├── src/
│   ├── PolysourceBundle.php
│   ├── DependencyInjection/
│   │   ├── PolysourceExtension.php
│   │   ├── Configuration.php
│   │   └── Compiler/
│   │       └── DataSourceRegistryPass.php
│   ├── Resources/config/services.php
│   ├── Routing/
│   │   ├── PolysourceRouteLoader.php
│   │   └── PolysourceUrlGenerator.php
│   ├── Controller/
│   │   ├── IndexController.php
│   │   ├── DetailController.php
│   │   └── ActionController.php
│   ├── EventListener/
│   │   ├── PolysourceRouterSubscriber.php
│   │   └── PolysourceResponseListener.php
│   ├── ArgumentResolver/
│   │   └── AdminContextResolver.php
│   ├── Context/
│   │   ├── AdminContext.php             (final readonly — cf. ADR-004)
│   │   └── AdminContextProvider.php
│   └── Registry/
│       ├── ResourceRegistry.php
│       └── DataSourceRegistry.php
└── tests/Functional/
    └── BundleSmokeTest.php
```

### Dépendances Composer

```json
{
    "require": {
        "php": "^8.4",
        "polysource/core": "self.version",
        "symfony/framework-bundle": "^7.4",
        "symfony/twig-bundle": "^7.4",
        "symfony/security-bundle": "^7.4"
    }
}
```

### Risques

- **Risque #1 : routing dynamique mal pensé.** EasyAdmin a un dispatcher unique avec query string ; on fait mieux avec des routes physiques nommées par resource (cf. ADR-003).
  - **Mitigation** : commencer simple — une route par action.
- **Risque #2 : couplage à des internals Symfony qui peuvent changer.**
  - **Mitigation** : ne pas wrapper `Request`, ne pas réimplémenter le routing.

### Critères d'acceptation

- [ ] `composer require polysource/symfony-bundle` ajoute le bundle au `bundles.php`.
- [ ] Routes générées physiquement (pas query string) : `/admin/{resourceName}`, `/admin/{resourceName}/{id}`, `/admin/{resourceName}/{id}/{action}`.
- [ ] ArgumentResolver injecte `AdminContext` dans `IndexController::__invoke(AdminContext $ctx)`.
- [ ] Smoke test fonctionnel avec un Resource fake.
- [ ] Tag DI `polysource.data_source` est collecté par le compiler pass.

### Complexité

**Élevée.**

### Ordre d'implémentation

1. PolysourceBundle + Extension + Configuration
2. Resource + DataSource registries
3. PolysourceRouteLoader (routes physiques)
4. AdminContext (`final readonly`) + AdminContextProvider + Resolver
5. Front controllers (Index, Detail, Action)
6. EventListener kernel.view
7. Smoke test fonctionnel

## Phase 3 — Thème Twig minimal (0,5 semaine)

### Objectif

Templates Twig pour rendre liste + détail + page d'erreur. **Copie depuis EasyAdmin v5** (licence MIT compatible), avec attribution explicite.

### Livrables

- Package `polysource/twig-theme`
- 4 templates principaux : `layout`, `index`, `detail`, `error`
- Quelques templates de field (`text`, `boolean`, `datetime`, `code`)
- CSS minimal — Bootstrap 5 CDN

### Fichiers à créer

```
packages/twig-theme/
├── composer.json
├── templates/
│   ├── layout.html.twig
│   ├── index.html.twig
│   ├── detail.html.twig
│   ├── error.html.twig
│   ├── paginator.html.twig
│   ├── _navigation.html.twig
│   ├── _flash.html.twig
│   └── field/
│       ├── text.html.twig
│       ├── boolean.html.twig
│       ├── datetime.html.twig
│       ├── code.html.twig
│       ├── id.html.twig
│       └── generic.html.twig
├── public/
│   ├── polysource.css
│   └── polysource.js
└── ATTRIBUTIONS.md            (mention licence EasyAdmin v5 MIT)
```

### Dépendances

- `polysource/symfony-bundle` (consommateur)
- Bootstrap 5 inclus en CDN

### Risques

- **Risque #1 : copie incorrecte des templates EasyAdmin v5.**
  - **Mitigation** : ne copier que les templates qui consomment des DTO génériques.
- **Risque #2 : pagination cursor mal rendue** (cf. ADR-002).
  - **Mitigation** : si `page.total === null` → afficher uniquement « Précédent / Suivant ».

### Critères d'acceptation

- [ ] `index.html.twig` rend une `DataPage` avec items.
- [ ] `detail.html.twig` rend un `DataRecord`.
- [ ] Paginator s'adapte à `total === null`.
- [ ] Tous les fields ont un template fallback (`generic.html.twig`).
- [ ] `ATTRIBUTIONS.md` cite EasyAdmin v5 avec licence MIT.
- [ ] Le rendu fonctionne sur Symfony 7.4.

### Complexité

**Faible-moyenne.**

### Ordre d'implémentation

1. layout + Bootstrap 5 CDN
2. index (avec stub DataPage)
3. detail
4. paginator
5. field/* (4-5 templates min)
6. error + flash messages

## Phase 4 — Adapter Messenger failed (1 semaine)

### Objectif

Premier vrai adapter. Lire les messages échoués depuis le `failed` transport Symfony Messenger et les exposer via `DataSourceInterface`.

### Livrables

- Package `polysource/adapter-messenger`
- `MessengerFailedDataSource` read-only
- `FailedMessageResource` configurée
- Configuration via PHP attributes (cf. ADR-005)

### Fichiers à créer

```
packages/adapter-messenger/
├── composer.json              ("symfony/messenger": "^7.4")
├── src/
│   ├── DataSource/
│   │   ├── MessengerFailedDataSource.php
│   │   └── EnvelopeMapper.php          (cf. ADR-006)
│   ├── Resource/
│   │   └── FailedMessageResource.php
│   ├── DependencyInjection/
│   │   ├── PolysourceMessengerExtension.php
│   │   └── Configuration.php
│   ├── Resources/config/services.php
│   └── PolysourceMessengerBundle.php
└── tests/
    ├── Unit/EnvelopeMapperTest.php
    └── Functional/MessengerFailedDataSourceTest.php
```

### Dépendances

- `polysource/core`
- `polysource/symfony-bundle`
- `symfony/messenger` ^7.4

### Risques

- **Risque #1 : `failed` transport pas configuré chez l'utilisateur.**
  - **Mitigation** : message d'erreur explicite avec instruction de config.
- **Risque #2 : transport ne supporte pas `ListableReceiverInterface`.**
  - **Mitigation** : check au démarrage avec message clair.
- **Risque #3 : payload non-sérialisable** (cf. ADR-006).
  - **Mitigation** : `EnvelopeMapper` essaie `json_encode(JSON_THROW_ON_ERROR)`, fallback `var_export`.
- **Risque #4 : timezone des `failedAt`.**
  - **Mitigation** : toujours stocker en UTC, formatter au rendu.

### Critères d'acceptation

- [ ] `MessengerFailedDataSource::search()` retourne tous les messages du failed transport.
- [ ] `find($id)` retourne un message ou `null`.
- [ ] `count()` retourne `null`.
- [ ] Le `DataRecord` expose `messageClass`, `failedAt` (UTC), `exceptionClass`, `exceptionMessage`, `payload` sérialisé.
- [ ] Test fonctionnel avec `InMemoryTransport`.
- [ ] L'adapter s'auto-enregistre via le tag `polysource.data_source`.

### Complexité

**Moyenne.**

### Ordre d'implémentation

1. composer.json + bundle stub
2. EnvelopeMapper (avec tests)
3. MessengerFailedDataSource read-only
4. FailedMessageResource (avec attributes)
5. Configuration DI + auto-tag
6. Test fonctionnel end-to-end

## Phase 5 — Actions retry / dismiss (0,5 semaine)

### Objectif

Implémenter les 4 actions custom : `retry`, `dismiss`, `retry-all`, `purge`.

### Livrables

- 4 classes Action implémentant `InlineActionInterface` ou `BulkActionInterface`
- Boutons Twig avec confirmation
- Flash messages

### Fichiers à créer

```
packages/adapter-messenger/src/Action/
├── RetryFailedMessageAction.php
├── DismissFailedMessageAction.php
├── RetryAllFailedMessagesAction.php
└── PurgeFailedMessagesAction.php
```

### Dépendances

- `polysource/core`
- `polysource/adapter-messenger` (Phase 4)

### Risques

- **Risque #1 : retry échoue silencieusement.**
  - **Mitigation** : `ActionResult::failure()` avec message lisible.
- **Risque #2 : `RetryAll` long-running.**
  - **Mitigation** : déléguer à un Messenger handler async.
- **Risque #3 : double-clic UI.**
  - **Mitigation** : CSRF + bouton désactivé après clic.

### Critères d'acceptation

- [ ] 4 actions disponibles + flashes + permissions check + CSRF.
- [ ] Bouton « Retry all » et « Purge all » avec confirmation forte.

### Complexité

**Faible.**

### Ordre d'implémentation

1. RetryFailedMessageAction
2. DismissFailedMessageAction
3. PurgeFailedMessagesAction
4. RetryAllFailedMessagesAction
5. Templates Twig + modals

## Phase 6 — Permissions Symfony (0,3 semaine)

### Objectif

Brancher le système Symfony existant. Polysource utilise des **attributs string** passés au `AuthorizationCheckerInterface`. Pas de UI pour gérer les rôles.

### Livrables

- `PermissionInterface` du `core` implémentée par `SymfonyAuthorizationCheckerPermission`
- Permission check à 4 endroits clés

### Fichiers à créer

```
packages/symfony-bundle/src/Security/
├── SymfonyAuthorizationCheckerPermission.php
├── PolysourceVoter.php
└── PermissionAttributes.php
```

### Dépendances

- `polysource/core`
- `symfony/security-bundle` ^7.4

### Critères d'acceptation

- [ ] Resource avec permission cachée pour users sans rôle.
- [ ] Action avec permission n'affiche pas son bouton si refusé.
- [ ] Test fonctionnel → 403.

### Complexité

**Faible-moyenne.**

### Ordre d'implémentation

1. PermissionInterface impl
2. Check dans 3 controllers
3. Test fonctionnel

## Phase 7 — Tests unitaires et fonctionnels (1 semaine)

### Objectif

Coverage `core` ≥ 90 % + 1 test fonctionnel e2e par adapter.

### Livrables

- Tests unitaires PHPUnit
- Tests fonctionnels Symfony
- CI GitHub Actions matrix verte

### Fichiers à créer

```
packages/core/tests/Unit/
packages/symfony-bundle/tests/Functional/
├── BundleSmokeTest.php
├── RoutingTest.php
├── PermissionTest.php
└── App/                      (Symfony test app minimal)
packages/adapter-messenger/tests/
├── Unit/EnvelopeMapperTest.php
└── Functional/
    ├── MessengerFailedDataSourceTest.php
    └── ActionExecutionTest.php
```

### Dépendances

- `phpunit/phpunit` ^11
- `symfony/phpunit-bridge` ^7.4
- `symfony/browser-kit`, `symfony/dom-crawler`

### Risques

- **Risque #1 : tests fonctionnels lents.**
  - **Mitigation** : InMemoryTransport, partager Symfony Kernel.
- **Risque #2 : coverage trompeuse.**
  - **Mitigation** : tests behavior-driven.

### Critères d'acceptation

- [ ] `core` : coverage ≥ 90 %.
- [ ] `symfony-bundle` : 4+ tests fonctionnels.
- [ ] `adapter-messenger` : 1 test e2e.
- [ ] CI matrix v0.1 (PHP 8.4 × Symfony 7.4) verte.
- [ ] PHPStan level max sans erreur.
- [ ] PHP-CS-Fixer PSR-12 sans erreur.

### Complexité

**Moyenne.**

### Ordre d'implémentation

1. Tests unitaires `core`
2. Test app Symfony minimal
3. Smoke + Routing + Permission tests
4. Adapter Messenger tests
5. CI workflow

## Phase 8 — Application de démo Docker (0,5 semaine)

### Objectif

Une application Symfony complète, livrée dans `examples/messenger-demo/`, qui démontre le cas d'usage en `make demo`.

### Livrables

- Symfony app minimal avec auth basique
- Messenger configuré avec un `failed` transport en Doctrine
- Job qui génère 10-20 failed messages
- Dashboard accessible sur `/admin/failed-messages`
- GIF de démo

### Fichiers à créer

```
examples/messenger-demo/
├── docker-compose.yml
├── Dockerfile
├── Makefile
├── README.md
├── composer.json
├── config/
│   ├── packages/polysource.yaml
│   ├── packages/messenger.yaml
│   ├── packages/security.yaml
│   ├── routes/polysource.yaml
│   └── services.yaml
├── src/
│   ├── Kernel.php
│   ├── Message/
│   ├── MessageHandler/
│   └── Command/SeedFailedMessagesCommand.php
├── public/index.php
└── .env
```

### Dépendances

- Les 4 packages `polysource/*`
- Docker + Docker Compose (cf. ADR-008)

### Risques

- **Risque #1 : démo casse au moindre changement.**
  - **Mitigation** : workflow CI dédié.
- **Risque #2 : `docker compose up` lent.**
  - **Mitigation** : image php-fpm pré-buildée dans GHCR.

### Critères d'acceptation

- [ ] `make demo` démarre sans intervention.
- [ ] Après 30 s, 10 failed messages visibles.
- [ ] Cliquer « Retry », « Retry all », « Dismiss » fonctionnent.
- [ ] README contient les commandes en clair.

### Complexité

**Moyenne.**

### Ordre d'implémentation

1. App Symfony minimal (Flex)
2. Messenger config + Doctrine `failed` transport
3. Messages + handlers qui plantent
4. SeedCommand
5. Polysource configuré
6. Dockerfile + docker-compose.yml + Makefile
7. README démo
8. Enregistrement GIF

## Phase 9 — Documentation utilisateur (0,5 semaine)

### Objectif

Un utilisateur tiers peut, en lisant la doc, installer Polysource et avoir un dashboard Messenger failed en moins de 10 minutes.

### Livrables

- `docs/user/` : doc utilisateur
- Guide installation
- Guide « 5 minutes »
- Référence API publique
- 3 cookbook articles minimum

### Fichiers à créer

```
docs/user/
├── README.md
├── installation.md
├── getting-started.md
├── concepts/
│   ├── resource.md
│   ├── data-source.md
│   ├── field.md
│   ├── action.md
│   └── permission.md
├── adapters/
│   └── messenger.md
├── cookbook/
│   ├── messenger-failed-dashboard.md
│   ├── adding-a-custom-action.md
│   └── permissions-with-roles.md
└── api/
```

### Dépendances

- Phases 1-8 terminées

### Risques

- **Risque #1 : doc dérive du code.**
  - **Mitigation** : exemples extraits du code de la démo.
- **Risque #2 : trop de doc avant utilisateurs.**
  - **Mitigation** : 3 cookbook seulement en v0.1.

### Critères d'acceptation

- [ ] Installation sans questions à poser.
- [ ] Le « 5 minutes » fonctionne sur projet Symfony 7.4 vierge.
- [ ] Tous les exemples copiables-collables.
- [ ] Référence API liste les 3 interfaces principales.

### Complexité

**Moyenne.**

### Ordre d'implémentation

1. installation.md
2. getting-started.md
3. concepts/* (5 fichiers)
4. adapters/messenger.md
5. cookbook/* (3 articles)

## Phase 9.5 — `polysource/filter` (primitive autonome) — 2-3 semaines

> Phase **ajoutée par [ADR-012](../adr/0012-dual-product-positioning.md)**
> (pivot dual-produit acté le 2026-05-03).

### Objectif

Extraire le système de filtres dans son propre package
`polysource/filter`, **utilisable seul** par une application Symfony
quelconque (sans Polysource Admin ni EasyAdmin), et **réutilisé** par
les deux produits v0.1 :
- `polysource/admin` standalone (Phase 9.7 + cas Messenger).
- `polysource/easyadmin-filter-bridge` (Phase 9.7).

### Livrables

- **Nouveau package** `polysource/filter` avec :
  - `FilterCollection`, `Filter` (immutable VO + builders)
  - `FilterService` — persistance en session HTTP par identifiant
    de collection
  - `FilterCollectionFormType` — Symfony FormType paramétré, build
    dynamique des champs depuis `FilterCollection`
  - Form types abstraits : `EnhancedDateTimeType` (presets : aujourd'hui,
    7 derniers jours, 30 derniers jours, ce mois, custom range),
    `EnhancedChoiceType` (multi-select avec recherche, Select2-style),
    `BetweenType`, `InType`
  - Twig extension `filter_tags` avec template par défaut
    (chips avec X pour retirer)
  - Modes UI : `simple` (chips bar uniquement), `integrated`
    (accordéon inline), `subpanel` (panneau coulissant)
  - Tag DI `polysource.filter.form_type` pour permettre à un host
    d'enregistrer ses propres form types
- Tests unitaires `core` ≥ 90 % coverage
- Doc utilisateur `docs/user/concepts/filter.md` mise à jour

### Critères d'acceptation

- [ ] `composer require polysource/filter` sur un projet Symfony 7.4
      vierge : OK.
- [ ] `composer require polysource/filter` sans Doctrine ni
      Polysource Admin : OK.
- [ ] Les filtres soumis sont persistés en session ; refresh de page
      → filtres restaurés.
- [ ] Click sur le X d'un chip → filtre retiré → form re-soumis.
- [ ] Les 4 form types riches (date avec presets, multi-select, range,
      between) marchent en isolation (form Symfony classique, sans le
      reste de Polysource).

### Risques

- **Risque #1 : couplage involontaire à `polysource/symfony-bundle`.**
  Mitigation : tests d'intégration dans un kernel Symfony minimal sans
  `PolysourceBundle`.
- **Risque #2 : explosion du scope (« et un picker date plus joli »,
  « et un range slider »).** Mitigation : 4 form types pour v0.1, pas
  un de plus.

### Ordre d'implémentation

1. Extraction `FilterCollection` + `Filter` depuis `polysource/core`
   (les types restent dans `core` mais le builder + service vont
   dans `filter`).
2. `FilterService` (session) + tests unitaires.
3. `FilterCollectionFormType` + tests fonctionnels en kernel minimal.
4. Form types riches (1 par jour : date / choice / between / in).
5. Twig extension + chips template.
6. Modes UI multiples (subpanel / integrated / simple).
7. Doc + cookbook.

## Phase 9.7 — `polysource/easyadmin-filter-bridge` — 2-3 semaines

> Phase **ajoutée par [ADR-012](../adr/0012-dual-product-positioning.md)**.

### Objectif

Drop-in package qui s'installe dans une application EasyAdmin v5
existante et **enrichit le système de filtres natif sans forker**.

### Livrables

- **Nouveau package** `polysource/easyadmin-filter-bridge` avec :
  - 7 `FilterConfiguratorInterface` (auto-tagués `ea.filter_configurator`)
    qui swappent les `formType` des filtres built-in EasyAdmin :
    `DateTimeFilter`, `TextFilter`, `NumericFilter`, `BooleanFilter`,
    `ComparisonFilter`, `ArrayFilter`, `EntityFilter`
  - 4 nouveaux `FilterInterface` activables manuellement par
    `configureFilters()` : `BetweenDateFilter`, `InFilter`,
    `NotNullFilter`, `FullTextSearchFilter`
  - `EventSubscriber` sur `BeforeCrudActionEvent` qui persiste les
    filtres en session par CRUD controller FQCN
  - Override Twig `templates/bundles/EasyAdminBundle/crud/filters.html.twig`
    pour afficher les chips au-dessus du tableau
  - Bridge avec `polysource/filter` pour réutiliser les form types
    riches déjà construits en Phase 9.5
- Tests fonctionnels avec une app EasyAdmin v5 minimale
- Application de démo `examples/easyadmin-bridge-demo/` (Symfony 7.4
  + EasyAdmin v5 + bridge + 1 entité Doctrine simple type Product)
- Doc utilisateur `docs/user/easyadmin-bridge/` (parallèle à
  `docs/user/adapters/messenger.md`)

### Critères d'acceptation

- [ ] `composer require polysource/easyadmin-filter-bridge` sur un
      projet EasyAdmin v5 existant : zéro config, les filtres existants
      gagnent les form types riches sans modification de code.
- [ ] La démo `examples/easyadmin-bridge-demo/` boot avec `make demo-bridge`
      et montre les 5 améliorations en action (presets, multi-select,
      ranges, chips, persistance session).
- [ ] CI matrix verte sur EasyAdmin 5.x.
- [ ] Aucune ligne de code d'EasyAdmin n'a été modifiée (vérification
      par `composer diff` ou équivalent dans la CI).

### Risques

- **Risque #1 : EasyAdmin v6 sort en cours de route avec breaking
  changes.** Mitigation : surveiller le repo EasyAdmin, ajouter v6 à
  la CI matrix dès qu'une beta sort.
- **Risque #2 : conflit avec un autre Configurator déjà installé**
  (priorité, ordre d'application). Mitigation : tag DI avec priorité
  négative, doc d'override claire.
- **Risque #3 : dépendance circulaire `polysource/filter` ↔
  `easyadmin-filter-bridge`.** Mitigation : `polysource/filter` ne
  dépend **jamais** d'EasyAdmin ; seul le bridge dépend des deux.

### Ordre d'implémentation

1. Squelette du package + DI extension + tests vides.
2. App de démo `examples/easyadmin-bridge-demo/` avec EasyAdmin nu
   (avant le bridge) — sert de baseline visuelle.
3. Le `DateTimeFilterEnhancer` end-to-end (Configurator + form type
   réutilisé de `polysource/filter` + tests fonctionnels). Quand
   celui-là marche, les 6 autres sont du copier-coller.
4. Les 6 autres Configurators (1 par jour).
5. EventSubscriber session.
6. Override Twig pour les chips.
7. Doc utilisateur.

## Phase 10 — Préparation release v0.1.0 dual-product (0,5 semaine)

> **Mise à jour 2026-05-03** ([ADR-012](../adr/0012-dual-product-positioning.md)) :
> phase reformatée pour refléter le pivot dual-produit. La release v0.1.0
> publie **6 packages** (au lieu de 4 prévus initialement) et fait
> **2 annonces distinctes** ciblant 2 audiences.

### Objectif

Tagger v0.1.0, publier les 6 packages sur Packagist, écrire les annonces.

### Livrables

- **6 packages publiés** :
  - `polysource/core`
  - `polysource/filter` (nouveau, Phase 9.5)
  - `polysource/twig-theme`
  - `polysource/symfony-bundle`
  - `polysource/adapter-messenger`
  - `polysource/easyadmin-filter-bridge` (nouveau, Phase 9.7)
- Tag Git `v0.1.0` annoté
- **Deux annonces séparées** :
  - **Annonce A — audience EasyAdmin** (large) : *"Enhance your
    EasyAdmin filters in 5 minutes"* — focus sur le bridge.
    Canaux : r/symfony, X, Symfony Insider, issue dédiée sur le repo
    EasyAdmin.
  - **Annonce B — audience non-Doctrine** (niche) : *"Polysource:
    admin for non-Doctrine resources"* — focus sur le standalone et
    le cas Messenger. Canaux : r/symfony, forum Symfony, Reddit
    spécialisés.
- CHANGELOG.md (Keep a Changelog format) listant les deux produits

### Tâches

- [ ] Vérifier `composer.json` de chaque package
- [ ] Tester `composer require polysource/symfony-bundle:^0.1` sur projet Symfony 7.4 vierge
- [ ] Créer le tag `v0.1.0` annoté
- [ ] Pousser le tag → publication Packagist
- [ ] Vérifier sur Packagist
- [ ] CHANGELOG.md (Keep a Changelog format)
- [ ] Annonce :
  - Tweet/X avec GIF
  - Discussion forum Symfony
  - Reddit r/symfony
  - Newsletter Symfony Insider
  - **Pas** de promotion agressive sur threads EasyAdmin
- [ ] Ouvrir 3-5 issues GitHub « help wanted »

### Dépendances

- Phases 1-9 terminées et CI verte

### Risques

- **Risque #1 : Packagist webhook KO.**
  - **Mitigation** : push manuel dry-run.
- **Risque #2 : annonce mal reçue.**
  - **Mitigation** : relire 24h avant.

### Critères d'acceptation

- [ ] 4 packages publiés sur Packagist v0.1.0.
- [ ] Tag GitHub `v0.1.0` annoté.
- [ ] Annonce sur 3+ canaux.
- [ ] CHANGELOG.md à la racine.

### Complexité

**Faible.**

### Ordre d'implémentation

1. Vérif composer.json
2. Test `composer require`
3. CHANGELOG.md + release notes
4. Drafts annonces
5. Tag + push
6. Vérif Packagist
7. Publication annonces

## 11. Structure technique cible

### Monorepo ou multi-repo ?

**Monorepo avec composer split**, justifié par :

- **Cohérence des releases** : tous les packages avancent ensemble.
- **Refacto facilités**.
- **Bus factor 1** : un seul mainteneur ne peut pas maintenir 5 repos indépendants.
- **Précédent** : Symfony lui-même est un monorepo composer split.

### Arborescence cible v0.1

```
polysource/
├── .local/
│   └── CLAUDE.local.md          (gitignored, pointeur privé local)
├── .github/
│   ├── workflows/ci.yml
│   ├── ISSUE_TEMPLATE/
│   └── PULL_REQUEST_TEMPLATE.md
├── .ddev/                       (config DDEV optionnelle)
│   └── config.yaml
├── docs/
│   ├── README.md                (index public)
│   ├── adr/                     (9 ADR)
│   ├── architecture/
│   │   └── target-architecture.md
│   ├── strategy/
│   │   └── product-vision.md
│   ├── roadmap/
│   │   └── development-plan.md
│   └── user/                    (Phase 9)
├── packages/
│   ├── core/
│   ├── symfony-bundle/
│   ├── twig-theme/
│   └── adapter-messenger/
├── examples/
│   └── messenger-demo/
├── the project context file                    (contexte projet persistant)
├── README.md
├── LICENSE
├── CONTRIBUTING.md
├── CODE_OF_CONDUCT.md
├── Makefile
├── Dockerfile
├── docker-compose.yml
├── composer.json
├── phpstan.neon.dist
├── .php-cs-fixer.dist.php
├── .gitignore
└── .gitattributes
```

### Packages à reporter

- `adapter-doctrine`, `adapter-flysystem`, `adapter-http`, `adapter-redis` → v0.2 / v0.3
- `easyadmin-bridge` → v0.3
- `adapter-meilisearch`, `adapter-config` → v1.0

### Conventions Composer

```json
{
    "name": "polysource/core",
    "description": "Polysource Admin — core contracts and value objects",
    "type": "library",
    "license": "MIT",
    "require": {
        "php": "^8.4"
    },
    "autoload": {
        "psr-4": {
            "Polysource\\Core\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Polysource\\Core\\Tests\\": "tests/"
        }
    },
    "extra": {
        "branch-alias": {
            "dev-main": "0.1.x-dev"
        }
    }
}
```

### Conventions de namespaces

| Package | Namespace racine |
|---|---|
| polysource/core | `Polysource\Core\` |
| polysource/symfony-bundle | `Polysource\Bundle\` |
| polysource/twig-theme | (pas de PHP, juste Twig) |
| polysource/adapter-messenger | `Polysource\Adapter\Messenger\` |
| polysource/adapter-doctrine | `Polysource\Adapter\Doctrine\` |
| polysource/easyadmin-bridge | `Polysource\Bridge\EasyAdmin\` |

### Stratégie de tests

| Package | Type | Outil | Objectif coverage |
|---|---|---|---|
| `core` | Unit | PHPUnit 11 | 90 %+ |
| `symfony-bundle` | Functional (test app) | PHPUnit + WebTestCase | tous les flux principaux |
| `twig-theme` | Snapshot (optionnel) | symfony/twig-bundle | non-régression |
| `adapter-messenger` | Functional + Unit | PHPUnit + InMemoryTransport | 80 %+ |
| Démo | Smoke | bash via Makefile | démarrage OK |

### Environnement de dev (cf. ADR-008)

**Cibles Makefile principales** (à implémenter en Phase 0) :

```makefile
.PHONY: help install test phpstan cs-fix demo clean

help:           ## Affiche cette aide
install:        ## Build le container et installe les dépendances Composer
test:           ## Lance PHPUnit sur tous les packages
phpstan:        ## Static analysis level max
cs-fix:         ## Applique le code style PSR-12 + Symfony rules
demo:           ## Démarre la démo Messenger failed
demo-down:      ## Arrête la démo
clean:          ## Nettoie vendor/, var/, .phpunit.result.cache
```

**docker-compose.yml** : php-fpm 8.4 + nginx pour le dev. Pas de Postgres en dev (pas nécessaire pour la lib).

**DDEV** : config `.ddev/config.yaml` optionnelle pour devs habitués (équivalent fonctionnel au docker-compose).

## 12. ADR de référence

Toutes les décisions structurantes sont dans `docs/adr/` :

- [ADR-001](../adr/0001-data-record-identifier.md) — `DataRecord::identifier` type
- [ADR-002](../adr/0002-data-page-total-semantics.md) — `DataPage::total` semantics
- [ADR-003](../adr/0003-routing-strategy.md) — Routing strategy
- [ADR-004](../adr/0004-admin-context-immutability.md) — `AdminContext` immutability
- [ADR-005](../adr/0005-configuration-mechanism.md) — Configuration mechanism
- [ADR-006](../adr/0006-envelope-mapper-serialization.md) — `EnvelopeMapper` serialization
- [ADR-007](../adr/0007-php-symfony-versions.md) — Versions PHP/Symfony + roadmap migration
- [ADR-008](../adr/0008-development-environment.md) — Dev environment (Docker/Make/DDEV)
- [ADR-009](../adr/0009-ai-assistant-context.md) — local agent context system

## 13. Ordre recommandé d'implémentation

1. **Phase 0** (setup repo + ADR) — peut commencer immédiatement
2. **Phase 1** (core) — bloqué par ADR
3. **Phase 2** (symfony-bundle) — peut commencer dès que `core` a des stubs
4. **Phase 3** (twig-theme) — en parallèle de Phase 2
5. **Phase 4** (adapter-messenger) — bloqué par Phase 1+2
6. **Phase 5** (actions) — bloqué par Phase 4
7. **Phase 6** (permissions) — peut être amorcé dès Phase 2
8. **Phase 7** (tests) — déjà entamé en parallèle, finalisé ici
9. **Phase 8** (démo) — bloqué par Phase 1-6
10. **Phase 9** (doc utilisateur) — peut commencer dès Phase 4
11. **Phase 10** (release) — séquentiel à la fin

## 14. Critères « stop-the-line »

À la fin de Phase 7 :

- [ ] `core` respecte les critères de surface d'API (cf. [ADR-010](../adr/0010-core-api-surface-criterion.md)) : ≤ 40 types publics + critères qualitatifs (ISP, single responsibility, pas de redondance, utilité prouvée).
- [ ] `symfony-bundle` installable sur projet Symfony 7.4 vierge ?
- [ ] Adapter Messenger : install < 10 min pour dev tiers ?
- [ ] CI matrix v0.1 (PHP 8.4 × Symfony 7.4) verte ?

Si l'un de ces critères n'est pas atteint, **ne pas continuer en Phase 8**.

> **Note** : le critère original (« `core` < 12 classes/interfaces publiques ») a été révisé suite à l'analyse Phase 1, qui a montré que ce seuil arbitraire mélangeait des catégories incomparables (interfaces, VO, exceptions). Détail dans [ADR-010](../adr/0010-core-api-surface-criterion.md).

## 15. Roadmap au-delà de v0.1

### v0.2 (3 mois après v0.1)
- `polysource/adapter-doctrine` (cohabitation EasyAdmin)
- `polysource/adapter-flysystem` (S3, fichiers)
- Pagination cursor (Messenger n'a pas de total)
- Formulaires create/edit basiques

### v0.3 (6 mois)
- `polysource/adapter-http`
- `polysource/adapter-redis`
- `polysource/easyadmin-bridge`
- Bulk actions
- Builders fluides Filament-style

### v0.5 — Élargissement compatibilité (cf. ADR-007 §Migration)
- **Support PHP 8.0+** : remplacer `final readonly class` par classes immutables conventionnelles, remplacer `enum` par classes constantes
- **Support Symfony 5.4+ et 6.x+** : ajout au composer matrix
- CI matrix élargie : PHP 8.0/8.1/8.2/8.3/8.4 × Symfony 5.4/6.4/7.x
- Cible : élargir l'audience aux projets en LTS plus anciennes

### v1.0 (12 mois)
- `polysource/adapter-meilisearch`
- `polysource/adapter-config`
- Documentation complète, vidéos, cookbook
- Gel API publique
- Talk SymfonyCon

## 16. Validation requise avant code

Ce plan doit être validé par le product owner avant la Phase 1.

Modifications attendues :
- Ajustement des estimations si l'allocation de temps réel diffère
- Validation des 9 ADR (cf. `docs/adr/`)
- Confirmation de la cible Symfony 7.4 LTS comme principale pour v0.1
