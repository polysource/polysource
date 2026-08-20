# Polysource — Documentation

> Polysource — deux outils complémentaires pour Symfony, partageant les mêmes primitives :
>
> 1. **`polysource/easyadmin-filter-bridge`** — enrichit les filtres d'une app EasyAdmin existante (4.24+ ou 5.0+) — ranges, multi-select, custom filter types, saved views, chips bar — sans forker EA.
> 2. **Polysource standalone admin** (à installer via `polysource/symfony-bundle` + les adapters utiles) — admin pour ressources non-Doctrine : Messenger, Redis, S3, REST, Meilisearch, configs.

Statut : **v1.1.2 publiée le 2026-08-20** — 16 packages distribués sur Packagist comme `polysource/<pkg>`, mirrorés depuis ce monorepo via le pipeline subtree-split documenté dans [ADR-026](./adr/0026-monorepo-split-and-packagist-mirrors.md). **API publique gelée depuis v1.0.0 (2026-08-06)** per [ADR-018](./adr/0018-admin-plugin-interface-and-public-contracts.md) — SemVer strict, breaking changes en versions majeures uniquement. Pour l'historique versionné voir [`CHANGELOG.md`](../CHANGELOG.md), pour ce qui vient ensuite voir [`ROADMAP.md`](../ROADMAP.md).

## Pour les utilisateurs

La documentation utilisateur (installation, getting-started, guides par package, cookbook, référence API) est en anglais dans [`docs/user/`](./user/) :

- [Index utilisateur](./user/README.md)
- [Showcase tour avec captures d'écran](./user/showcase-tour.md)
- [Installation](./user/installation.md)
- [Getting started — dashboard fonctionnel en 5 minutes](./user/getting-started.md)
- [EasyAdmin filter bridge — getting started](./user/easyadmin-filter-bridge/getting-started.md)
- [Row details — EasyAdmin bridge](./user/easyadmin-filter-bridge/row-details.md)
- [Row details — thème natif + nested listing](./user/row-details.md)
- [Cookbook — construire son propre adapter](./user/cookbook/build-your-own-adapter.md)
- [Cookbook — ajouter une action custom](./user/cookbook/adding-a-custom-action.md)
- [Cookbook — permissions par rôle](./user/cookbook/permissions-with-roles.md)

## Pour les contributeurs (français)

### Vision et architecture
- [**Vision produit**](./strategy/product-vision.md) — vision, scope strict, non-objectifs, philosophie d'architecture
- [**Architecture cible**](./architecture/target-architecture.md) — packages, interfaces (signatures PHP), flux d'une requête, esquisses d'adapters

### Décisions
- [**Architecture Decision Records (ADR)**](./adr/) — 31 ADRs structurants : identifiants, routing, immutabilité, baseline multi-version (ADR-015), dual-product positioning (ADR-012), architecture plugin (ADR-018), saved views (ADR-019), audit (ADR-020), workflow-bridge (ADR-021), widgets (ADR-022), search (ADR-023), bulk async (ADR-024), showcase demo (ADR-025), monorepo split + Packagist mirrors (ADR-026), etc.

### Release & distribution
- [**Monorepo split + Packagist mirrors (ADR-026)**](./adr/0026-monorepo-split-and-packagist-mirrors.md) — pourquoi 1 monorepo de dev + 16 mirrors read-only sur Packagist
- [**Guide release & split**](./maintainers/release-and-split.md) — comment releaser, ajouter un package, debugger un échec de split, rotater la clé de l'App
- [**Symfony Flex recipes (prepared)**](./maintainers/flex-recipes/README.md) — 14 manifests prêts pour soumission à `symfony/recipes-contrib` + workflow de submission
- [**TestKernel patterns**](./maintainers/test-kernel-patterns.md) — conventions pour les TestKernels intra-monorepo (PHPUnit 11 risky, cache-dirs uniques, SchemaTool reset, ORM 2.x quirks)
- [**Symfony compatibility audit**](./maintainers/symfony-compat-audit.md) — ce qu'on advertise vs ce qu'on teste (Sf 5.4 → 8.0, PHP 8.1 → 8.5, EA 4.24 / 5.0, Doctrine ORM 2.x / 3.x), gaps et action items v0.7+ / v1.0

### Roadmap & historique
- [**ROADMAP.md**](../ROADMAP.md) — ce qui est livré (v0.1 → v1.1), ce qui est en backlog
- [**CHANGELOG.md**](../CHANGELOG.md) — historique versionné des releases (v0.1.0 → v1.1.2)
- [**Pre-v1.0 freeze checklist (ADR-011)**](./adr/0011-pre-v1.0-freeze-checklist.md) — la checklist API du freeze v1.0 (close au tag v1.0.0 ; conserve les planchers PHP 8.2+ / Sf 6.4+)

> Le plan de développement détaillé (par phase, par item, par jour) reste un document de travail interne du mainteneur — non publié.

## Note pour les contributeurs

Avant tout PR de code, lire dans l'ordre :

1. [Vision produit](./strategy/product-vision.md) — comprendre le scope strict
2. [Architecture cible](./architecture/target-architecture.md) — comprendre les contrats
3. [ADR](./adr/) — comprendre les choix tranchés
4. [CHANGELOG](../CHANGELOG.md) + [ROADMAP](../ROADMAP.md) — savoir où on en est et où on va

Voir [CONTRIBUTING.md](../CONTRIBUTING.md) à la racine pour le workflow.

## Quality bar

Les chiffres à jour (tests, matrice CI, coverage) vivent dans la
section [« Quality bar » du README racine](../README.md#quality-bar) —
source unique, resynchronisée à chaque release.
