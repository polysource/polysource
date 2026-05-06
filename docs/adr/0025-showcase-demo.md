# ADR-025 — Showcase demo & v0.1.0 hero launch (Phase 23)

- **Date** : 2026-05-06
- **Statut** : Accepté
- **Décide pour** : Phase 23 — application Symfony complète exploitant les 15 packages du monorepo, devenant le hero du launch v0.1.0
- **En lien avec** : [ADR-008 — Dev environment](./0008-development-environment.md), [ADR-012 — Dual-product positioning](./0012-dual-product-positioning.md), [ADR-015 — Multi-version compatibility baseline](./0015-multi-version-compatibility-baseline.md), [ADR-017 — Cherry-picking from Filament study](./0017-cherry-picking-from-filament-study.md)

## Contexte

À fin Phase 22, le monorepo livre **15 packages** : tronc commun
(`core`, `filter`, `twig-theme`, `symfony-bundle`), bridge
(`easyadmin-filter-bridge`), 6 adapters (`messenger`, `doctrine`,
`redis`, `flysystem`, `http`, `meilisearch`), 5 capabilities
transverses (`audit`, `workflow-bridge`, `widgets`, `search`,
`bulk-async`).

Quatre démos existent : `messenger-demo`, `easyadmin-bridge-demo`
(EA v5), `easyadmin-bridge-demo-v4` (EA v4 — prouve le floor
ADR-015), `filter-standalone-demo`. Chacune isole **un** package.

**Aucune démo ne montre la combinaison réelle** que ADR-012 promet :
"EasyAdmin admin le Doctrine, Polysource admin le reste, dans la
**même** application Symfony". Pour un évaluateur, l'expérience
actuelle implique de boot 4 démos séparées et d'imaginer leur
intégration. Pour le launch v0.1.0, c'est un blocage.

Par ailleurs, le repo EasyAdmin documente ses features avec des
**screenshots embedded** régénérés depuis une app interne. Polysource
n'a pas d'équivalent : la doc utilisateur de Phase 9 est en texte
pur. Pour une lib qui se vend sur la richesse UX (filtres, chips,
saved views, dashboards, palette Cmd+K, progress live), c'est un
handicap.

## Décision

### 1. Une seule application showcase, hero du launch

Construire `examples/showcase-demo/` — une application Symfony
unique, réaliste, déployable en `make showcase`, qui exploite
**les 15 packages** dans un scénario métier cohérent. Cette démo
**devient le hero** de l'annonce v0.1.0 (le tag v0.1.0 ne sort
**qu'après** que la showcase est mergée et stable).

> Décision contre-intuitive : la roadmap Phase 10 (release v0.1.0)
> est repoussée pour faire passer la showcase d'abord. Justification :
> sans démonstration intégrée, l'annonce tombe à plat. Coût accepté :
> ~3-4 semaines de retard sur le tag.

### 2. Domaine métier : ShopCo SaaS (e-commerce B2C)

Vertical retenu : e-commerce. Justification : c'est le scénario où
**chaque** package trouve une place naturelle non-forcée. Les 15
features s'enchaînent dans des user stories que tout dev Symfony
reconnaît (catalogue produits, panier, commandes, paiements,
expédition, retours).

| Package | Cas métier ShopCo |
|---|---|
| `easyadmin-filter-bridge` | CRUDs EA : Product, Customer, Order, Refund (Doctrine pur) |
| `filter` (saved views) | "Commandes en retard de livraison" sauvegardée par l'ops |
| `adapter-messenger` | Failed transport des notifications email/SMS qui ont planté |
| `adapter-doctrine` | Sessions / tokens — exposés en standalone pour ne pas polluer EA |
| `adapter-redis` | Cache produits, cache panier (lecture + invalidation) |
| `adapter-flysystem` | MinIO : photos produits + factures PDF |
| `adapter-http` | WireMock : 3 microservices simulés (payment, shipping, notifications) |
| `adapter-meilisearch` | Index produits — sync via Messenger handler |
| `audit` | Trace de toutes les actions admin (RGPD Art. 30) |
| `workflow-bridge` | State machine `OrderWorkflow` (cart → paid → shipped → delivered) + branches cancelled / refunded |
| `widgets` | Dashboard d'accueil : 3 Counter + 2 List + 1 Chart |
| `search` (Cmd+K) | Palette cross-resources : products, orders, customers, jobs, files |
| `bulk-async` | "Retry 5k failed emails" + "Reindex 50k products" avec live progress |
| `symfony-bundle` permissions | 3 rôles : `ROLE_ADMIN`, `ROLE_OPS`, `ROLE_VIEWER` |

### 3. Stack technique — état de l'art 2026

| Layer | Choix | Justification |
|---|---|---|
| PHP | **8.4** | Sweet spot 2026. Évite 8.5 (trop fraîche pour bundles tiers). |
| Symfony | **7.4 LTS** | Sortie nov 2025, supportée jusqu'en nov 2030. |
| EasyAdmin | **5.x** dernière mineure | Major courant, AssetMapper natif. |
| Doctrine ORM | **3.x** + DBAL 4.x | Major courant. |
| Foundry | **2.x** static factories | État de l'art fixtures Symfony. |
| Asset pipeline | **AssetMapper** (Symfony natif) | Webpack Encore = legacy depuis Sf 6.3. |
| Frontend | **Symfony UX** (Stimulus + Turbo + Twig Components) | Cohérent avec les Stimulus controllers de `widgets`/`search`/`bulk-async`. |
| DB | **PostgreSQL 17** | JSONB exploitable pour audit `context`. Plus aligné Symfony community 2026 que MySQL. |
| Cache | **Redis 7** | Image officielle. Consommée par `adapter-redis`. |
| Search | **Meilisearch 1.x** | Image officielle. Consommée par `adapter-meilisearch`. |
| S3 compat | **MinIO** | Standard local S3-testing. Consommé par `adapter-flysystem`. |
| Live updates | **Mercure Hub** (`dunglas/mercure`) | Consommé par `bulk-async`. |
| Mailer | **Mailpit** | Standard 2026 SMTP capture. |
| HTTP mocks | **WireMock** | Pour `adapter-http` sans dépendance externe. |
| Messenger | Doctrine transport (`failed`) + Sync (autres) | Pas de RabbitMQ requis. |
| Auth | `form_login` natif Sf 7.4 + 3 rôles | Pas d'OAuth/SSO custom. |
| Workflow | `symfony/workflow` | Consommé par `workflow-bridge`. |
| E2E + screenshots | **Symfony Panther** (Chromium headless) | Cohérent EA. Full-PHP, pas de Node. |
| Coverage | **PCOV** (Docker) | Plus rapide que Xdebug en CI. |

> Ces choix montent **uniquement** la showcase. Le baseline ADR-015
> (PHP 8.1+ / Sf 5.4+ / EA 4+ / Doctrine 2+) reste intact côté
> packages — les démos `bridge-v4` et `filter-standalone` continuent
> de prouver le floor.

### 4. Pipeline screenshots automatisé (style EasyAdmin)

`bin/console showcase:screenshots` : journey scriptée Panther qui
boot la démo, joue les parcours par rôle, capture **30-40 PNG**
versionnés dans `docs/user/screenshots/`. Cible Makefile :
`make screenshots`. Re-régénération en CI sur PR labelled
`needs-screenshots`. La doc `docs/user/` est ensuite réécrite
EA-style avec screenshots embedded.

### 5. Garde-fous anti-scope-creep

- **Une feature = un cas métier ShopCo.** Pas de "page démo de
  la feature X" décontextualisée.
- **Aucune feature qui n'existe pas dans Polysource.** La showcase
  ne doit rien réclamer en plus à la lib — si quelque chose manque,
  on l'ajoute en upstream (ou on retire du scope).
- **Une seule app Symfony, un seul docker-compose.** Pas de
  microservices externes hors WireMock pour `adapter-http`.
- **Tests E2E Panther en CI dès Phase 23-A.** Toute régression
  bloque le merge.
- **Pipeline screenshots commit-checked.** La doc ne dérive pas.
- **Fixtures Foundry 2.x exclusivement.** Pas de SQL inline,
  pas de `LoadFixtures` legacy.

### 6. Branche

Travail mergé directement sur `main` par sous-phase A→J. Décision
explicite du product owner : la showcase = canon de référence, on
veut l'historique granulaire dans `git log` plutôt qu'une PR
gigantesque.

## Conséquences

### Positives

- **Hero du launch v0.1.0** crédible : "regarde ShopCo, tu peux
  l'avoir comme ça en 5 minutes".
- **Smoke test cross-package** gratuit : si la showcase boot, les
  15 packages sont câblables ensemble.
- **Doc EA-style** : screenshots embedded générés depuis une source
  unique de vérité, pas de drift code/doc.
- **Pédagogie** : un évaluateur lit le code de ShopCo pour comprendre
  comment câbler chaque feature dans son propre projet.
- **Vitrine Foundry/AssetMapper/Symfony UX** : le repo devient
  exemplaire en plus d'être utile.

### Négatives / coûts

- **Release v0.1.0 retardée de ~3-4 semaines.** Compensé par un
  launch beaucoup plus impactant.
- **Maintenance** : 5e démo à maintenir + ses 15 dépendances.
  Mitigation : tests E2E Panther en CI bloquent les régressions.
- **Containers requis pour `make showcase`** : Postgres + Redis +
  Meilisearch + MinIO + Mercure + Mailpit + WireMock = 8 services.
  Mitigation : image PHP-FPM pré-buildée GHCR, healthchecks dans
  `docker-compose.yml`.
- **Stack 2026 vs floor ADR-015** : la showcase tourne sur PHP 8.4
  + Sf 7.4 + EA 5, ce qui ne **prouve** rien sur les baselines plus
  anciennes. Les démos `bridge-v4` et `filter-standalone` restent
  responsables de cette preuve.

## Plan d'implémentation (Phase 23)

10 sous-phases A→J détaillées dans
[`docs/roadmap/development-plan.md` §Phase 23](../roadmap/development-plan.md).

| Batch | Objet | Durée |
|---|---|---|
| **A** | Bootstrap app + auth + Docker (8 services) + Makefile | 2 j |
| **B** | Domaine + 9 entités + Foundry factories + 4 Stories | 2 j |
| **C** | EA + 4 CRUDs + filter-bridge wired | 2 j |
| **D** | Polysource standalone : `symfony-bundle` + `adapter-messenger` + `adapter-doctrine` | 1,5 j |
| **E** | 4 adapters non-Doctrine (Redis, Flysystem MinIO, HTTP WireMock, Meilisearch) | 3 j |
| **F** | 5 capabilities transverses (audit, workflow, widgets, search, bulk-async) | 4 j |
| **G** | Permissions 3 rôles + saved views + AssetMapper polish | 2 j |
| **H** | Suite E2E Panther | 2 j |
| **I** | Pipeline screenshots + 30-40 PNG dans `docs/user/screenshots/` | 2 j |
| **J** | Réécriture doc EA-style + README + GIF + 2 annonces v0.1.0 | 3 j |

**Total : ~22 jours-homme.**

## Alternatives écartées

### A. Continuer avec les 4 démos isolées + tag v0.1.0 maintenant

Rejeté : l'annonce tombe à plat, l'évaluateur doit reconstituer
mentalement l'intégration. ADR-012 promet un dual-product, il faut
le **prouver** dans la même app.

### B. 2 showcases séparées (une EA-bridge, une standalone)

Rejeté : duplique la maintenance, ne prouve pas la cohabitation
qui est précisément le pitch ADR-012.

### C. Showcase mais hors scope launch (post-v0.1.0)

Rejeté : ferait passer le tag v0.1.0 sur des démos siloïsées, ce
qui dévalue le launch. Mieux vaut un launch retardé qui frappe
fort qu'un launch à l'heure qui ne convertit personne.

### D. Domaine métier différent (CMS, SaaS B2B, plateforme de réservation)

Rejeté : e-commerce reste le scénario le plus large où les 15
features s'enchaînent sans forcer (cf. tableau §2).

### E. Webpack Encore au lieu d'AssetMapper

Rejeté : Encore est officiellement legacy depuis Sf 6.3. Une
showcase "irréprochable 2026" doit montrer la pile par défaut Sf 7.

### F. Playwright au lieu de Panther

Rejeté : ajoute une dépendance Node au repo full-PHP. Panther est
l'orthodoxie Symfony et c'est ce qu'EA utilise. Tradeoff accepté :
Panther est moins puissant que Playwright sur certains scénarios
(network mocking avancé), mais largement suffisant pour des
screenshots scriptés.

## Validation produit

Validée par le product owner le 2026-05-06 :
- Stack ADR-025 §3 confirmé.
- Domaine "ShopCo SaaS" confirmé.
- Périmètre 15 packages confirmé.
- Showcase = hero du launch confirmé (release v0.1.0 retardée).
- Merge direct sur `main` par sous-phase confirmé (pas de feature
  branch globale).
