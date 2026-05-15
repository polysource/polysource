# Architecture Decision Records

> Décisions structurantes pour Polysource. Chaque ADR documente : contexte, options envisagées, décision retenue, conséquences, et statut.
>
> **Format** : adapté de [Michael Nygard's ADR template](https://github.com/joelparkerhenderson/architecture-decision-record/blob/main/locales/en/templates/decision-record-template-by-michael-nygard/index.md).

## Index

| # | Titre | Statut |
|---|---|---|
| [ADR-001](./0001-data-record-identifier.md) | `DataRecord::identifier` type | Accepté |
| [ADR-002](./0002-data-page-total-semantics.md) | `DataPage::total` semantics (null = inconnu) | Accepté |
| [ADR-003](./0003-routing-strategy.md) | Routes physiques par resource | Accepté |
| [ADR-004](./0004-admin-context-immutability.md) | `AdminContext` immutable (`final readonly`) | Accepté |
| [ADR-005](./0005-configuration-mechanism.md) | Configuration via interface methods + PHP attributes | Accepté |
| [ADR-006](./0006-envelope-mapper-serialization.md) | EnvelopeMapper : JSON-first + fallback | Accepté |
| [ADR-007](./0007-php-symfony-versions.md) | Versions PHP/Symfony : 8.4/7.4 v0.1 → 8.0+/5.4+ v0.5 | Accepté |
| [ADR-008](./0008-development-environment.md) | Docker + Makefile + DDEV optionnel | Accepté |
| ADR-009 | _retiré_ — concernait l'outillage local du mainteneur, pas une décision d'architecture projet ; supprimé avant publication. Le numéro reste réservé pour préserver la traçabilité historique. | — |
| [ADR-010](./0010-core-api-surface-criterion.md) | Critère de surface d'API du core (≤ 40 types + critères qualitatifs) | Accepté — remplace seuil §14 |
| [ADR-011](./0011-pre-v1.0-freeze-checklist.md) | Checklist des items API à trancher avant le freeze v1.0 | Accepté (vivant) |
| [ADR-012](./0012-dual-product-positioning.md) | Dual-product positioning (bridge EA + admin standalone) | Accepté |
| [ADR-013](./0013-filter-package-architecture.md) | `polysource/filter` architecture (tronc commun) | Accepté |
| [ADR-014](./0014-datasource-lifecycle-deferred.md) | DataSource lifecycle 3 phases — différé v0.3+ | Accepté (différé) |
| [ADR-015](./0015-multi-version-compatibility-baseline.md) | Multi-version baseline PHP 8.1+ / Symfony 5.4+ / EA 4.24+ | Accepté |
| [ADR-016](./0016-bridge-contracts-shared-with-polysource-filter.md) | `ChipFormatterInterface` dans le tronc commun | Accepté |
| [ADR-017](./0017-cherry-picking-from-filament-study.md) | Cherry-picking depuis l'étude Filament-for-Symfony | Accepté |
| [ADR-018](./0018-admin-plugin-interface-and-public-contracts.md) | `AdminPluginInterface` + versioning des contrats publics | Accepté |
| [ADR-019](./0019-saved-views-architecture.md) | Architecture des saved views (Phase 11) | Accepté |
| [ADR-020](./0020-audit-non-doctrine-actions.md) | Audit non-Doctrine actions (Phase 12) | Accepté |
| [ADR-021](./0021-symfony-workflow-bridge.md) | Symfony Workflow bridge (Phase 13) | Accepté |
| [ADR-022](./0022-dashboard-widgets.md) | Dashboard widgets (Phase 14) | Accepté |
| [ADR-023](./0023-global-search-cmdk.md) | Global search + Cmd+K (Phase 15) | Accepté |
| [ADR-024](./0024-bulk-async-mercure.md) | Bulk async + Mercure (Phase 16) | Accepté |
| [ADR-025](./0025-showcase-demo.md) | Showcase demo "ShopCo SaaS" + hero du launch v0.1.0 (Phase 23) | Accepté |
| [ADR-026](./0026-monorepo-split-and-packagist-mirrors.md) | Monorepo unique + 16 mirrors Packagist via subtree split + GitHub App | Accepté |
| [ADR-027](./0027-progressive-enhancement.md) | Progressive enhancement : tout interactif a un fallback serveur | Accepté |
| [ADR-028](./0028-scope-discipline.md) | Scope discipline : couche UX filter+listing, pas une plateforme d'admin | Accepté |
| [ADR-029](./0029-admin-context-decomposition.md) | `AdminContext` décomposition planifiée en sous-VOs au seuil ADR-004 | Accepté (planification) |

## Convention

- Numérotation séquentielle stricte (zéro-pad sur 4 digits).
- Statuts possibles : `Proposé`, `Accepté`, `Refusé`, `Déprécié`, `Remplacé par #N`.
- Toute modification structurante post-v1.0 doit faire l'objet d'une nouvelle ADR.
- Une ADR refusée ou dépréciée n'est pas supprimée — elle est conservée avec son statut mis à jour.

## Pourquoi des ADR ?

- **Mémoire d'équipe** : pourquoi telle décision, pas telle autre.
- **Onboarding** : un nouveau contributeur peut comprendre les choix sans les rejouer.
- **Stabilité API** : toute proposition de breaking change doit citer l'ADR qu'elle remplace.
