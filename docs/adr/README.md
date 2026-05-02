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
| [ADR-009](./0009-ai-assistant-context.md) | Système de contexte projet persistant (the project context file) | Accepté |
| [ADR-010](./0010-core-api-surface-criterion.md) | Critère de surface d'API du core (≤ 40 types + critères qualitatifs) | Accepté — remplace seuil §14 |
| [ADR-011](./0011-pre-v1.0-freeze-checklist.md) | Checklist des items API à trancher avant le freeze v1.0 | Accepté (vivant) |

## Convention

- Numérotation séquentielle stricte (zéro-pad sur 4 digits).
- Statuts possibles : `Proposé`, `Accepté`, `Refusé`, `Déprécié`, `Remplacé par #N`.
- Toute modification structurante post-v1.0 doit faire l'objet d'une nouvelle ADR.
- Une ADR refusée ou dépréciée n'est pas supprimée — elle est conservée avec son statut mis à jour.

## Pourquoi des ADR ?

- **Mémoire d'équipe** : pourquoi telle décision, pas telle autre.
- **Onboarding** : un nouveau contributeur peut comprendre les choix sans les rejouer.
- **Stabilité API** : toute proposition de breaking change doit citer l'ADR qu'elle remplace.
- **Adoption outils** : les ADR sont consommées par les outils internes (cf. ADR-009) pour rester cohérents avec les décisions prises.
