# Polysource — Documentation

> Polysource — deux outils complémentaires pour Symfony, partageant les mêmes primitives :
>
> 1. **`polysource/easyadmin-filter-bridge`** — enrichit les filtres d'une app EasyAdmin v5 existante (presets, ranges, multi-select, saved views, chips bar) sans forker EA.
> 2. **`polysource/admin`** — admin standalone pour ressources non-Doctrine : Messenger, Redis, S3, REST, Meilisearch, configs.

Statut : Phases 1 → 22 livrées sur `main` (16 packages). Pré-v0.1.0, tag en attente. Voir [`roadmap/development-plan.md`](./roadmap/development-plan.md).

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
- [**Architecture Decision Records (ADR)**](./adr/) — 25 ADRs structurants : identifiants, routing, immutabilité, baseline multi-version (ADR-015), dual-product positioning (ADR-012), architecture plugin (ADR-018), saved views (ADR-019), audit (ADR-020), workflow-bridge (ADR-021), widgets (ADR-022), search (ADR-023), bulk async (ADR-024), showcase demo (ADR-025), etc.

### Roadmap
- [**Plan de développement**](./roadmap/development-plan.md) — Phases 0 à 22 livrées, Phase 10 (release v0.1.0) en cours
- [**Pre-v1.0 freeze checklist (ADR-011)**](./adr/0011-pre-v1.0-freeze-checklist.md)

## Note pour les contributeurs

Avant tout PR de code, lire dans l'ordre :

1. [Vision produit](./strategy/product-vision.md) — comprendre le scope strict
2. [Architecture cible](./architecture/target-architecture.md) — comprendre les contrats
3. [ADR](./adr/) — comprendre les choix tranchés
4. [Plan de développement](./roadmap/development-plan.md) — savoir où on en est

Voir [CONTRIBUTING.md](../CONTRIBUTING.md) à la racine pour le workflow.

## Quality bar (à jour 2026-05-08)

- **674 tests unitaires + fonctionnels / 1684 assertions** au niveau packages
- **27 tests d'intégration** au niveau showcase
- **PHPStan level max** partout
- **PHP-CS-Fixer** PSR-12 + Symfony rules
- **Core coverage ≥ 90%** (`polysource/core` à 99.17 %)
- **CI matrix** : PHP 8.1/8.2/8.3/8.4 × Symfony 6.4/7.2/7.4 × EasyAdmin 4.24/5.0
