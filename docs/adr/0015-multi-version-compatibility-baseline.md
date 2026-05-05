# ADR-015 — Multi-version compatibility baseline (PHP 8.1+ / Symfony 5.4+ / EA 4.24+)

- **Date** : 2026-05-04
- **Statut** : Accepté
- **Décide pour** : v0.1.0+
- **Révise** : [ADR-007 — PHP/Symfony versions](./0007-php-symfony-versions.md)

## Contexte

ADR-007 (signé en début de Phase 1) avait acté un baseline strict pour v0.1 :
**PHP 8.4 + Symfony 7.4 LTS uniquement**. Le rationale : ne pas se freiner sur les
features modernes pendant que l'API se stabilisait, élargir au-dessus seulement
en v0.5+.

Phase 9.7 a livré le bridge `polysource/easyadmin-filter-bridge`. Pendant la
revue de scope (mai 2026), **deux signaux** ont émergé :

1. **Audience EA réelle** — beaucoup d'apps en production tournent encore
   EasyAdmin v4.x. La migration v4 → v5 est non triviale (templates renommés,
   FilterDataDto signatures, Bootstrap version), et pas encore réalisée par
   tous. Si on ne supporte que EA 5.x, on perd potentiellement >50% de
   l'audience cible. Or ce sont **précisément** ces apps qui tireraient le plus
   de bénéfice du bridge (chips bar, presets, multi-tab) — elles n'ont
   probablement pas le budget pour migrer EA en parallèle.

2. **Coût de migration linéaire vers le baseline final** — chaque jour passé
   sur PHP 8.4 + Symfony 7.4 strict ajoute des `final readonly class` /
   first-class callable / etc. à migrer plus tard. Si on lock un baseline plus
   large MAINTENANT, on évite le big-bang v0.5+.

ADR-007 prévoyait d'attendre la stabilisation de l'API. Avec Phase 9.5/9.7
shippées et l'API publique stable (Polysource facade, BridgeOptions, 4 custom
filters, 3 modes UI), **le moment est venu de revoir le baseline**.

## Décision

**Lock le baseline v0.1.0 à :**

| Composant | Contrainte |
|---|---|
| **PHP** | `>=8.1` (matches EA 4.29 convention; accepts PHP 9.x forward) |
| **Symfony** | `^5.4 \|\| ^6.0 \|\| ^7.0 \|\| ^8.0` (toutes les versions ≥ 5.4, pas seulement les LTS) |
| **EasyAdmin** | `^4.24 \|\| ^5.0` (couvre v4 audience) |
| **Doctrine ORM** | `^2.20 \|\| ^3.6` (les 2 majors actives) |

PHP 8.1 est le sweet spot : on garde `readonly properties`, `enum`, first-class
callable syntax `func(...)`, `never` return type — soit l'essentiel des
features modernes 8.x sans pousser jusqu'à 8.2+ (qui fermerait Symfony 5.4 ×
PHP 8.1 du marché).

**Amendement 2026-05-05 — alignement sur EA 4.29.** Le baseline initial était
strict LTS (`^5.4 || ^6.4 || ^7.4`). Après audit de la politique
`easycorp/easyadmin-bundle` 4.29 (`php: >=8.1`, `symfony/form: ^5.4|^6.0|^7.0|^8.0`)
on élargit pour matcher exactement leur couverture — soit toutes les versions
de Symfony à partir de 5.4 (LTS + non-LTS) et forward-compat pour Symfony 8.x
quand il sortira. Coût pratique nul (les non-LTS étaient déjà supportées par
le code, on ne faisait que les exclure du composer.json), gain audience non
nul (un host sur Sf 6.2 ou 7.1 peut désormais installer sans patch). PHP `^8.1`
devient `>=8.1` pour la même raison : EA 4.29 utilise `>=`, on aligne.

### Audience couverte

Combinaisons réelles supportées (CI matrix prioritaire, 5 jobs) :

| PHP | Symfony | EasyAdmin | Profil app |
|---|---|---|---|
| 8.1 | 5.4 | 4.x | Legacy en cours de upgrade prudent |
| 8.2 | 6.4 | 4.x | Production typique 2024-2025 |
| 8.2 | 6.4 | 5.x | Bridge migration audience |
| 8.3 | 7.4 | 5.x | Modern stack |
| 8.4 | 7.4 | 5.x | Bleeding edge (notre dev local) |

Les ~5% d'apps hors de ces combos (PHP 8.0 strict, Doctrine 1.x, Symfony 5.4 ×
EA 5.0+) sont **best-effort** : pas de breakage volontaire, mais pas testé en
CI. Le `composer.json` advertise honnêtement `^8.1`.

### Conséquences sur le code

Le refactor depuis l'état actuel (PHP 8.4) implique :

1. **`final readonly class X {}` → `final class X { public readonly TYPE $foo; }`**
   — `readonly class` (déclaration au niveau class) est PHP 8.2+ ; les
   propriétés `readonly` sont 8.1+. ~30 fichiers concernés (FilterCriterion,
   FilterCollection, FilterDefinition, etc.).

2. **`enum X { case Y; }`** — déjà 8.1+, on garde tel quel.

3. **First-class callable `$cb = func(...)`** — déjà 8.1+, on garde.

4. **`never` return type** — déjà 8.1+, on garde.

5. **DNF types `(A&B)|null`** — pas utilisé, et 8.2+ donc on l'évite.

6. **Typed class constants** — pas utilisé, et 8.3+ donc on l'évite.

7. **Asymmetric visibility `public(set)`** — pas utilisé, et 8.4 donc on
   l'évite.

8. **AssetMapper en Symfony 5.4** — n'existe pas. Stratégie : on ship les
   contrôleurs Stimulus comme fichiers JS standards dans `assets/`. AssetMapper
   (6.3+) les sert nativement ; les hosts en Symfony 5.4 utilisent Webpack
   Encore + `@symfony/stimulus-bridge` qui lit la même `assets/package.json`.
   → **Aucun changement code package** ; uniquement de la doc d'install
   différenciée.

9. **Doctrine ORM 2.x vs 3.x — `AssociationMapping`** :
   - 2.x : `$mapping['targetEntity']` (array)
   - 3.x : `$mapping->targetEntity` (object property)
   → `ChipValueFormatter::formatEntity()` doit gérer les 2 (déjà fait
     partiellement, à compléter avec `is_array()` guard).

10. **EasyAdmin v4 vs v5 différences** — `FilterDataDto` shape, template
    paths, FormType namespaces. À auditer + ajuster les 8 enhancers et les 4
    custom filters par tests fonctionnels sur les 2 majeures.

### Stratégie de tests

CI matrix sur la table ci-dessus (5 jobs explicites via `include:` plutôt qu'un
produit cartésien complet — évite les combinaisons impossibles comme PHP 8.1 ×
Symfony 7.4).

PHPStan : `phpVersion: 80100` dans `phpstan.neon.dist` pour bloquer toute
régression involontaire vers du 8.2+.

PHP-CS-Fixer : `'@PHP81Migration'` ruleset pour normaliser le code 8.1.

## Conséquences

### Positives

1. **Audience couverte** : ~95% des apps Symfony EA-using réelles. EA v4
   audience capturée immédiatement.
2. **Migration big-bang évitée** : on cap maintenant la dette ; chaque PR
   désormais respecte le baseline.
3. **3 LTS Symfony** : couvre la quasi-totalité des projets sérieusement
   maintenus.
4. **Tronc commun préservé** : `polysource/filter` lui-même devient
   consommable par n'importe quel framework Symfony (Sonata, API Platform,
   raw apps), pas seulement EA.

### Négatives / Trade-offs

1. **Refactor 1 jour de drag** sur l'état actuel (PHP 8.4 strict) — coût
   d'opportunité immédiat.
2. **PHP 8.1 EOL nov 2025** — après cette date, on conseille l'upgrade aux
   hosts mais on ne casse pas. Le composer.json reste `^8.1` jusqu'à ce qu'on
   ship une v0.2 qui passe à 8.2+.
3. **Tests sur 5 combos** — augmente le temps CI de 2-3× (mais reste sous 5
   min).
4. **AssetMapper vs Webpack Encore double-doc** — install path différencié
   selon la version Symfony. Maintenance docs.
5. **PHP 8.1 limite la modernité** : pas de `readonly class` (8.2+), pas de
   typed class constants (8.3+), pas de propriété hooks (8.4+). Acceptable —
   notre code n'a pas besoin de ces features.

## Alternatives considérées

### A. Garder ADR-007 strict (PHP 8.4 + Symfony 7.4)

Rejeté : repousse la migration big-bang à v0.5+, coût accumulé linéairement.
Audience EA v4 perdue à court terme.

### B. PHP 8.0+ / Symfony 5.4+ / EA 4+ (max audience)

Rejeté : PHP 8.0 EOL depuis nov 2023 (>30 mois en 2026). Audience marginale.
Coût élevé : drop `readonly`, drop `enum`, drop first-class callable, drop
`never`. Refacto ~2 semaines pour gain audience <5%.

### C. PHP 8.2+ / Symfony 6.4+ (modern only)

Rejeté : exclut Symfony 5.4 LTS et donc tous les hosts encore sur 5.4-stable
qui ne migreront que progressivement. Symfony 5.4 LTS support fini nov 2025
mais tooling Composer continue d'installer.

## Plan d'exécution

1. Cette ADR-015 (signée, ce commit).
2. Mise à jour de `composer.json` (root + 6 packages) avec les nouvelles
   contraintes.
3. Refactor `final readonly class` → `final class` + `readonly` properties
   (codemod automatable via PHPStan/PHPCS rules).
4. Audit features 8.2+ involontaires + ajustements.
5. Doctrine 2.x compat dans `ChipValueFormatter::formatEntity`.
6. CI matrix élargie à 5 jobs.
7. Tester sur les 5 combos (probablement quelques fixes par combo).
8. Documentation des install paths AssetMapper (6.3+) vs Webpack Encore (5.4).

Estimation totale : **3-4 jours**.

[ADR-016](./0016-bridge-contracts-shared-with-polysource-filter.md) couvre la
question orthogonale des **contrats de formatters** (callable inline vs service
DI) et le placement des interfaces de bridge dans le tronc commun
`polysource/filter` — applicable au même cycle de migration.
