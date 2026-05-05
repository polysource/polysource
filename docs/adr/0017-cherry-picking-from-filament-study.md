# ADR-017 — Cherry-picking depuis l'étude "Filament-for-Symfony"

- **Date** : 2026-05-05
- **Statut** : Accepté
- **Décide pour** : v0.1.0+ (oriente Phase 10+ post-9.7)
- **En lien avec** : [ADR-012 — Dual-product positioning](./0012-dual-product-positioning.md), [ADR-013 — `polysource/filter` architecture](./0013-filter-package-architecture.md), [ADR-016 — Bridge contracts shared with `polysource/filter`](./0016-bridge-contracts-shared-with-polysource-filter.md)

## Contexte

Une étude `symfony-admin-framework-analysis.md` a circulé pendant la phase
post-9.7. Elle est issue d'un exploratoire (l'auteur a demandé "liste-moi
les fonctionnalités que EasyAdmin ne fait pas") — **ce n'est pas un document
stratégique**, c'est un inventaire générique sans positionnement produit.

L'étude propose un MVP de 14 features autour d'une thèse "the Filament for
Symfony" : framework d'admin plugin-first, opinionated, full-feature,
positionné en concurrence frontale avec EasyAdmin sur le territoire Doctrine
(audit log Doctrine, multi-tenancy Doctrine filter, field-level perms sur
forms, inline editing, conditional fields, wizards, CSV/Excel import/export
Doctrine, etc.).

Cette thèse **est en conflit direct avec [ADR-012](./0012-dual-product-positioning.md)**
qui acte le positionnement dual-produit :

> Polysource = (1) `polysource/easyadmin-filter-bridge` enrichissant les
> filtres EasyAdmin **sans le forker** + (2) `polysource/admin` standalone
> pour ressources **non-Doctrine** (Messenger, Redis, S3, Meilisearch,
> REST APIs, …).
>
> **Pas un fork d'EasyAdmin. Pas un concurrent frontal sur le cas Doctrine.**

Sans ADR explicite, le risque est qu'un futur contributeur (ou un futur
contributeur) ressorte l'étude et pousse pour des features hors-scope au nom de
"completeness" ou "feature parity avec Filament". Il faut acter dès
maintenant ce qu'on prend, ce qu'on rejette, et pourquoi.

## Décision

### 1. Rejet du framing "Filament for Symfony"

Le mot "Filament" met une attente implicite de feature-parity (form builders,
no-code, full-feature CRUD admin sur Doctrine) **qu'on ne veut pas tenir**
([ADR-012](./0012-dual-product-positioning.md)). Le pitch public Polysource
reste celui de [ADR-012](./0012-dual-product-positioning.md) :

- "Drop-in qui enrichit les filtres EasyAdmin sans le forker"
- "Admin pour ressources non-Doctrine que EA ne couvre pas (Messenger,
  Redis, S3, …)"

Plus modeste, mais c'est ce qu'on construit réellement et c'est défendable.

### 2. Features récupérées

#### 2a. Pour `polysource/filter` + `polysource/easyadmin-filter-bridge`

| Feature étude | Où elle entre | Justification |
|---|---|---|
| **Saved views** (filtres + colonnes + tri, scope private/team/public) | `polysource/filter` | P0 #3 de l'étude. Aucun outil Symfony ne le ship. Coût marginal faible au-dessus de la session persistence déjà prévue. |
| **AND/OR filter builder UI** | `polysource/filter` | Option avancée du multi-mode (simple / integrated / subpanel) déjà en place. |
| **URL-shareable filter state** | `polysource/filter` | Cheap, pair naturellement avec saved views. |
| **Field-level permissions côté bridge** | `polysource/easyadmin-filter-bridge` | EA a `setPermission()` mais grossier. Décorer le filter form pour cacher des filtres selon rôle est un add-on net. |

#### 2b. Pour `polysource/admin` (standalone non-Doctrine)

| Feature étude | Justification |
|---|---|
| **Plugin architecture formelle** (`AdminPluginInterface` + lifecycle) | On a déjà 80% de la fondation (DI tags, compiler passes, `#[AsResource]` cf. ADR-005). Formaliser un `AdminPluginInterface` documenté + versionné transforme l'outil en écosystème. **Foundation : doit venir avant les autres features**. |
| **Dashboard widget framework** (`WidgetInterface`, registry tagged `polysource.widget`) | Premier écran utile pour Messenger Failed / Files / Webhooks. Cohérent avec la convention DI tags existante. |
| **Bulk operations framework async via Messenger + Mercure progress** | On a déjà 4 actions Messenger (retry, dismiss, retry-all, purge). Formaliser un `BulkActionInterface` + progress streaming = différenciateur net contre EA dont le bulk est partiel. |
| **Symfony Workflow integration** (transition buttons, state badges) | Aucun outil PHP ne le fait. S'applique parfaitement à des ressources non-Doctrine (états sur Messenger envelopes, feature flags, jobs). Très Symfony-native — colle à notre ADN. |
| **Global cross-source search** | Notre cas est plus différenciant que celui de l'étude : chercher across S3 files + Messenger queues + Meilisearch + REST APIs en une fois. Multi-source-first est notre force. |
| **Command palette (Cmd+K)** | Cheap si la search existe. Bon "wow factor" pour la démo. |
| **Audit log pour actions admin sur ressources non-Doctrine** | "Qui a retried quel message", "qui a flippé tel feature flag", "qui a purgé telle queue". Les compliance reasons qui justifient l'audit Doctrine s'appliquent à l'identique sur **notre** territoire. **Légitime**, dans le scope. |

### 3. Features explicitement rejetées

| Feature étude | Raison du rejet |
|---|---|
| **Audit log + revisions sur entités Doctrine** | Territoire EA + bundles existants (`damienharper/auditor-bundle`, `simplethings/entity-audit-bundle`). Pas notre job. Note : l'audit pour ressources **non-Doctrine** est dans le scope (cf. §2b). |
| **Multi-tenancy Doctrine filter** | Out of scope explicite (cf. the project context file §"Out of scope" + product vision). |
| **Field-level permissions sur forms Doctrine** | Frontal EA. EA a `setPermission()` (grossier mais existant) ; les bundles communautaires complètent. Note : §2a décore les **filtres** pas les **forms** — différent. |
| **Inline list editing** | Frontal EA sur Doctrine. |
| **Conditional fields show/hide** | Frontal EA sur Doctrine. |
| **Multi-step wizard forms** | Frontal EA sur Doctrine. |
| **Polymorphic relations** | Frontal EA sur Doctrine. |
| **CSV/Excel import/export Doctrine** | EA + plugins existants. |
| **Real-time collaborative editing** (CRDTs, OT) | v2+ explicite (the project context file). Mercure presence suffit pour v1 si jamais on y vient. |
| **Visual builders** (forms, workflows, no-code) | L'étude elle-même dit "DO NOT". Confirmé. |
| **Headless API mode / API Platform integration** | Laisser API Platform faire son boulot. P3 indéfini. |
| **PHP attribute config** | Déjà fait via `#[AsResource]` (ADR-005). Rien à ajouter. |
| **2FA wrap de `scheb/2fa-bundle`** | Project-level, pas framework-level. Documenter, pas wrapper. |
| **SSO / SAML** | Idem 2FA. |
| **Personal access tokens** | Idem. |

### 4. Sequencing du Phase 10+

L'ordre matter parce que **chaque feature doit ship comme plugin** (l'étude
elle-même dit "every default feature must itself be a plugin"). Construire
widgets/bulk/workflow/search comme features du symfony-bundle au lieu de
plugins = dette structurelle.

```
Phase 10  Plugin architecture formelle              foundation, non-négo
Phase 11  Saved views + URL-shareable state         coup rapide, haute valeur
Phase 12  Dashboard widget framework                premier écran utile
Phase 13  Bulk async + Mercure progress             différenciation
Phase 14  Workflow integration                      Symfony-native, unique
Phase 15  Global search + command palette           UX wow
Phase 16  Audit non-Doctrine actions                compliance sur notre territoire
```

Estimation grossière : ~3-5 mois de travail dans le scope ADR-012, sans
entrer en concurrence frontale avec EA.

### 5. Ce que cette ADR n'acte PAS

- Le plugin contract précis (signatures de `AdminPluginInterface`, lifecycle)
  → ADR-018 séparée à écrire avant la Phase 10.
- La couche audit non-Doctrine (model, storage, voter integration)
  → ADR-019 ou plus tard, à écrire avant Phase 16.
- Les détails de chaque feature → décisions de design lors de la phase
  correspondante.

Cette ADR est un **filtre stratégique**, pas une spec d'implémentation.

## Conséquences

### Positives

1. **Discipline scope verrouillée**. Un futur contributeur (ou IA) qui ressort
   l'étude pour proposer audit-Doctrine ou multi-tenancy-Doctrine doit
   d'abord proposer une révision d'ADR-012 + ADR-017 — pas juste pousser un
   PR.
2. **Direction Phase 10+ tracée**. 7 features concrètes, ordonnées, dans le
   scope.
3. **Différenciation préservée**. Polysource garde son angle "non-Doctrine
   first + bridge EA" plutôt que de devenir un n-ième concurrent EA.
4. **Audience-capture intact**. Saved views + chips bar dans le bridge =
   audience EA capturée. Widgets + bulk + workflow + search dans `admin` =
   audience non-Doctrine attaquée.

### Négatives / Trade-offs

1. **Pas de "Filament for Symfony" pitch**. On rate l'opportunité (théorique)
   du slot enterprise full-feature. Mais cette opportunité demanderait
   d'abandonner ADR-012, donc un changement de produit, pas un ajout.
2. **L'étude n'est pas implémentable telle quelle**. C'est OK — elle ne
   prétend pas l'être (c'est un exploratoire). L'utiliser comme inventaire
   plutôt que comme roadmap.
3. **Audit log non-Doctrine demande de la conception spécifique**. Les
   bundles existants (`damienharper/auditor-bundle`) ciblent Doctrine. On
   doit écrire le nôtre pour les actions sur ressources arbitraires
   (Messenger, Redis, …). Coût ~2 semaines en Phase 16.

## Alternatives considérées

### A. Adopter le MVP intégral de l'étude (14 features)

**Rejeté.** Coût ~13-18 semaines + abandonne ADR-012 + entre en concurrence
frontale avec EA sur Doctrine. Aucun gain audience qui justifie le pivot.

### B. Rejeter l'étude entièrement

**Rejeté.** Plusieurs features de l'étude sont **réellement utiles** dans
notre scope (saved views, widgets, bulk async, workflow, search). Les
ignorer serait de la rigidité plus que de la discipline.

### C. Pas d'ADR — décider feature par feature au moment où ça vient

**Rejeté.** L'étude existe et va ressortir. Sans ADR explicite, chaque
feature proposée demanderait de rejouer l'arbitrage. L'ADR matérialise le
filtre une fois pour toutes.

## Plan d'exécution

1. Cette ADR-017 (signée, ce commit).
2. Avant Phase 10 : ADR-018 spécifie `AdminPluginInterface` + lifecycle +
   versioning des contrats publics. **Acté.**
3. Avant Phase 11 : [ADR-019](./0019-saved-views-architecture.md) spécifie
   l'architecture des saved views. **Acté.**
4. Phase 10-15 enchaînent dans l'ordre §4 ci-dessus. ADR-020-024 à acter
   au début de chaque phase suivante.
5. Avant Phase 16 : ADR-024 spécifie la couche audit non-Doctrine
   (storage, model, intégration voters).
6. À mi-parcours (~Phase 13), refléter ADR-017 dans la roadmap publique +
   `whats-new.md` du bridge pour informer l'audience que la direction est
   verrouillée.

## Pour relire cette décision plus tard

Cette ADR doit être révisée si **et seulement si** :

- ADR-012 est elle-même révisée (changement de positionnement produit)
- Un signal marché clair indique que le slot "Filament for Symfony" est
  réellement vacant ET que Polysource est le bon candidat pour le combler
  (à mesurer en feedback users de v0.1, pas en spéculation)
- Une feature de la reject list devient **demandée explicitement et
  répétitivement** par 5+ users distincts en post-v0.1.0

Sans un de ces 3 signaux, l'arbitrage tient.
