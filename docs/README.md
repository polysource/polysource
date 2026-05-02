# Polysource — Documentation

> Polysource est un moteur d'administration Symfony pour ressources non-Doctrine ou multi-source : Messenger failed messages, feature flags, Redis, fichiers, APIs externes, Meilisearch, jobs, webhooks, configurations YAML/JSON.
>
> Le code lui-même n'est pas encore écrit (cf. [roadmap/development-plan.md](./roadmap/development-plan.md)).

## Contenu

### Vision et architecture
- [**Vision produit**](./strategy/product-vision.md) — vision, non-objectifs, philosophie d'architecture, premier cas d'usage recommandé, stratégie open source et packages
- [**Architecture cible**](./architecture/target-architecture.md) — packages, interfaces (signatures PHP), flux d'une requête, esquisses d'adapters Doctrine/HTTP/Redis/Messenger

### Décisions
- [**Architecture Decision Records (ADR)**](./adr/) — décisions structurantes documentées (identifiants, routing, immutabilité, versions PHP/Symfony, environnement de dev, etc.)

### Roadmap
- [**Plan de développement**](./roadmap/development-plan.md) — Phases 0 à 10 détaillées avec livrables, fichiers à créer, risques, critères d'acceptation, complexité

## Note pour les contributeurs

Polysource est en **phase de design**. Avant tout PR de code, lire dans l'ordre :

1. [Vision produit](./strategy/product-vision.md) — comprendre le scope strict
2. [Architecture cible](./architecture/target-architecture.md) — comprendre les contrats
3. [ADR](./adr/) — comprendre les choix tranchés
4. [Plan de développement](./roadmap/development-plan.md) — savoir où on en est

Voir [CONTRIBUTING.md](../CONTRIBUTING.md) à la racine pour le workflow de contribution.
