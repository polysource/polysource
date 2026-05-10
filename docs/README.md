# Polysource — Documentation

> Polysource — deux outils complémentaires pour Symfony, partageant les mêmes primitives :
>
> 1. **`polysource/easyadmin-filter-bridge`** — enrichit les filtres d'une app EasyAdmin existante (4.24+ ou 5.0+) — presets, ranges, multi-select, saved views, chips bar — sans forker EA.
> 2. **`polysource/admin`** — admin standalone pour ressources non-Doctrine : Messenger, Redis, S3, REST, Meilisearch, configs.

Statut : **v0.1.0 publiée le 2026-05-10** — 16 packages distribués sur Packagist comme `polysource/<pkg>`, mirrorés depuis ce monorepo via le pipeline subtree-split documenté dans [ADR-026](./adr/0026-monorepo-split-and-packagist-mirrors.md). Pour l'historique versionné voir [`CHANGELOG.md`](../CHANGELOG.md), pour ce qui vient ensuite voir [`ROADMAP.md`](../ROADMAP.md), et pour les items à trancher avant le freeze v1.0 voir [ADR-011](./adr/0011-pre-v1.0-freeze-checklist.md).

## Pour les utilisateurs

La documentation utilisateur (installation, getting-started, guides par package, cookbook, référence API) est en anglais dans [`docs/user/`](./user/) :

- [Index utilisateur](./user/README.md)
- [Showcase tour avec captures d'écran](./user/showcase-tour.md)
- [Installation](./user/installation.md)
- [Getting started — dashboard fonctionnel en 5 minutes](./user/getting-started.md)
- [EasyAdmin filter bridge — getting started](./user/easyadmin-filter-bridge/getting-started.md)
- [Cookbook — construire son propre adapter](./user/cookbook/build-your-own-adapter.md)
- [Cookbook — ajouter une action custom](./user/cookbook/adding-a-custom-action.md)
- [Cookbook — permissions par rôle](./user/cookbook/permissions-with-roles.md)

## Pour les contributeurs (français)

### Vision et architecture
- [**Vision produit**](./strategy/product-vision.md) — vision, scope strict, non-objectifs, philosophie d'architecture
- [**Architecture cible**](./architecture/target-architecture.md) — packages, interfaces (signatures PHP), flux d'une requête, esquisses d'adapters

### Décisions
- [**Architecture Decision Records (ADR)**](./adr/) — 26 ADRs structurants : identifiants, routing, immutabilité, baseline multi-version (ADR-015), dual-product positioning (ADR-012), architecture plugin (ADR-018), saved views (ADR-019), audit (ADR-020), workflow-bridge (ADR-021), widgets (ADR-022), search (ADR-023), bulk async (ADR-024), showcase demo (ADR-025), monorepo split + Packagist mirrors (ADR-026), etc.

### Release & distribution
- [**Monorepo split + Packagist mirrors (ADR-026)**](./adr/0026-monorepo-split-and-packagist-mirrors.md) — pourquoi 1 monorepo de dev + 16 mirrors read-only sur Packagist
- [**Guide release & split**](./maintainers/release-and-split.md) — comment releaser, ajouter un package, debugger un échec de split, rotater la clé de l'App

### Roadmap & historique
- [**ROADMAP.md**](../ROADMAP.md) — ce qui est livré en v0.1, ce qui est planifié en v0.2+
- [**CHANGELOG.md**](../CHANGELOG.md) — historique versionné des releases (v0.1.0 → 2026-05-10)
- [**Pre-v1.0 freeze checklist (ADR-011)**](./adr/0011-pre-v1.0-freeze-checklist.md) — items API à trancher avant le freeze v1.0

> Le plan de développement détaillé (par phase, par item, par jour) reste un document de travail interne du mainteneur — non publié.

## Note pour les contributeurs

Avant tout PR de code, lire dans l'ordre :

1. [Vision produit](./strategy/product-vision.md) — comprendre le scope strict
2. [Architecture cible](./architecture/target-architecture.md) — comprendre les contrats
3. [ADR](./adr/) — comprendre les choix tranchés
4. [CHANGELOG](../CHANGELOG.md) + [ROADMAP](../ROADMAP.md) — savoir où on en est et où on va

Voir [CONTRIBUTING.md](../CONTRIBUTING.md) à la racine pour le workflow.

## Quality bar (v0.1.0 — 2026-05-10)

- **782 tests unitaires + fonctionnels / 1932 assertions** au niveau packages
- **29 tests E2E browser** (Panther) + **15 tests d'intégration adapter** sur conteneurs réels (Redis, S3 MinIO, Meilisearch, HTTP API)
- **PHPStan level max** partout
- **PHP-CS-Fixer** PSR-12 + Symfony rules
- **Core coverage ≥ 90%** (`polysource/core` à 99.17 %)
- **CI matrix** : PHP 8.1/8.2/8.3/8.4 × Symfony 6.4/7.2/7.4 × EasyAdmin 4.24/5.0
