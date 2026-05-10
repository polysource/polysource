# ADR-026 — Monorepo unique + 16 mirrors Packagist via subtree split + GitHub App

- **Date** : 2026-05-10
- **Statut** : Accepté
- **Décide pour** : v0.1.0+ — toute la stratégie de release et de distribution Composer
- **En lien avec** : [ADR-008 — Dev environment](./0008-development-environment.md), [ADR-015 — Multi-version compatibility baseline](./0015-multi-version-compatibility-baseline.md), [ADR-018 — Admin plugin interface and public contracts](./0018-admin-plugin-interface-and-public-contracts.md)

## Contexte

À fin Phase 22, le monorepo `polysource/polysource` livre **16 packages**
sous `packages/<pkg>/` :

```
core, symfony-bundle, twig-theme, filter, easyadmin-filter-bridge,
audit, widgets, search, workflow-bridge, bulk-async,
adapter-messenger, adapter-doctrine, adapter-redis, adapter-flysystem,
adapter-http, adapter-meilisearch
```

Le DX dev est ouvertement monorepo : un seul clone, une seule CI, une
seule arborescence de PRs, un seul tag de release qui couvre les 16
packages simultanément. C'est ce qui permet à Phase 16 (`bulk-async`)
de toucher en une PR `core/` (events), `symfony-bundle/` (subscriber)
et `audit/` (event bridge) sans coordination cross-repo.

**Mais Composer/Packagist a une contrainte non-négociable** : *un
package = un repository Git distinct, à la racine*. Packagist ne sait
pas indexer un dossier `packages/<pkg>/` à l'intérieur d'un repo plus
grand. Donc pour que `composer require polysource/core` fonctionne, il
faut qu'il existe un repo `polysource/core` avec `composer.json` à la
racine et avec ses propres tags.

Trois options structurelles s'offrent au projet :

1. **Polyrepo** — abandonner le monorepo, vivre directement dans 16
   repos. ✗ Tue le DX cross-package, multiplie la CI par 16, force
   16 PRs coordonnées pour toute évolution touchant plusieurs packages.
2. **Monorepo + 16 mirrors auto-générés** — garder le monorepo comme
   source de vérité, dériver mécaniquement 16 mirrors *read-only*
   pour Packagist. C'est le pattern Symfony / Laravel / Sylius / API
   Platform / Doctrine.
3. **Monorepo unique sur Packagist** — publier `polysource/polysource`
   en un seul package vendant l'autoload pour les 16 namespaces. ✗
   Force les utilisateurs à tirer 16 packages dont 14 inutiles, casse
   ADR-018 (chaque package est un plugin opt-in indépendant), brise
   le scope strict ADR-012.

**L'option 2 est la seule cohérente avec ADR-012 et ADR-018.** Reste à
trancher *comment* : quel splitter, quelle authentification de push,
quel modèle d'audit/rotation.

## Décisions

### 1. Source de vérité : monorepo `polysource/polysource`

- Les 16 packages vivent sous `packages/<pkg>/`
- Tout commit, toute PR, tout tag (`vX.Y.Z`) se fait sur le monorepo
- Les 16 mirrors sont **read-only en aval** : aucune PR n'y est
  acceptée, un workflow `close-pull-request.yml` y est poussé pour
  rediriger les contributeurs vers le monorepo (pattern emprunté à
  `sync-packages.php` de Symfony — cf. analyse comparative dans le
  guide maintainer)

### 2. Splitter : `git subtree split` natif (pas `splitsh-lite`)

Symfony et Laravel utilisent [`splitsh/lite`](https://github.com/splitsh/lite)
(C, ~100x plus rapide que `git subtree split`) parce qu'ils ont 30+
sous-packages et un historique très long. À 16 packages et un
historique pré-v0.1, `git subtree split` natif passe en < 60 s sur un
runner GitHub-hosted standard, sans dépendance externe à installer
dans le runner. **Choix : pas de complexité prématurée**, on bascule
sur `splitsh/lite` *si et seulement si* le run dépasse 5 min un jour.

### 3. Orchestration : GitHub Actions, jamais sur la machine du mainteneur

Symfony fait son split **hors CI** sur la machine personnelle de
Nicolas Grekas, qui pousse avec sa clé SSH perso (member de l'org
`symfony`). Modèle inadmissible pour Polysource :

- bus factor = 1
- la clé qui pousse a write sur **tous** les repos persos du mainteneur
- aucun audit log visible côté org

**Tout split + push se fait en CI** via
`.github/workflows/auto-split.yml`, déclenché sur :
- `push` sur `main`  → resync des 16 mirrors
- `push` de tag `v*` → resync + mirroring du tag (donc Packagist
  indexe la version)

Le bootstrap initial (création des 16 repos vides + premier tag) reste
manuel via `scripts/split-packages.sh` parce qu'il n'a lieu **qu'une
fois**, et qu'il manipule l'API GitHub `repo create` que la CI ne
devrait jamais avoir le droit de faire.

### 4. Authentification de push : GitHub App dédiée, pas PAT, pas deploy keys

L'arbre de décision a été parcouru en entier — détails dans le guide
maintainer. Résumé des raisons de rejet :

| Option | Raison de rejet |
|---|---|
| Clé SSH perso d'un mainteneur (pattern Symfony) | scope = tous les repos persos du mainteneur, audit log noyé, casse à la rotation perso |
| PAT classique (token utilisateur) | scope = tous les repos visibles par l'utilisateur, expiration max 1 an, secret long-terme à rotater à la main |
| Fine-grained PAT scopé aux 16 mirrors | mieux, mais reste un secret long-terme attaché à un compte humain, expiration manuelle à 1 an, audit dilué |
| Deploy keys SSH (1 paire pour les 16 mirrors) | **techniquement impossible** : GitHub indexe les deploy keys par fingerprint, une même fingerprint ne peut être attachée qu'à *un seul* repo |
| Deploy keys SSH (16 paires distinctes) | 16 secrets à rotater, 16 paires à régénérer, scaling cassé dès qu'on ajoute un 17ᵉ package |
| Machine user GitHub (compte bot dédié) | consomme un siège, 2FA séparée à maintenir, pousse avec une identité "humaine factice" qui pollue l'audit |
| **GitHub App `polysource-split` ⭐** | scope chirurgical, token éphémère ≤60 min, secret long-terme inactif sans App ID, audit attribué à l'App, révocation atomique, pattern *first-class principal* GitHub |

**Décision : GitHub App `polysource-split`** installée sur l'org
`polysource` avec :
- **Permissions** : `contents: write` + `metadata: read`, **rien** d'autre
- **Repository selection** : *Only select repositories* → exactement
  les 16 mirrors (le monorepo n'est volontairement pas dans la liste —
  l'App n'a aucun pouvoir sur lui)
- **Webhook** : désactivé (l'App ne reçoit pas d'events, elle est
  uniquement utilisée comme identité de push)

Chaque run du workflow `auto-split.yml` :
1. Mint un *installation token* éphémère (≤60 min) via
   [`actions/create-github-app-token`](https://github.com/actions/create-github-app-token),
   scopé au seul repo de la matrix en cours (`repositories: ${{ matrix.package }}`)
2. Push via HTTPS `https://x-access-token:${TOKEN}@github.com/polysource/<pkg>.git`
3. Le token expire automatiquement à la fin du run

Le seul secret long-terme est la **private key** de l'App, stockée
chiffrée comme `SPLIT_APP_PRIVATE_KEY` sur le monorepo. Cette clé seule
ne donne aucun accès direct ; elle sert uniquement à signer les JWTs
qui demandent un installation token. L'App est identifiée par son
**Client ID** stocké en variable repo `SPLIT_APP_CLIENT_ID` (variable,
pas secret — c'est un identifiant public au sens GitHub).

**Garde-fou critique** : `actions/checkout@v4` est appelé avec
`persist-credentials: false`. Sans ça, le `GITHUB_TOKEN` du runner est
écrit comme `http.extraheader` dans `.git/config`, intercepte tous les
appels HTTPS vers github.com, et **shadowne** le token App embed dans
l'URL — le push retombe alors sur l'identité par défaut
(`github-actions[bot]`) qui n'a aucun droit sur les mirrors. Ce point
a été identifié à la première itération du workflow et est documenté
dans un commentaire en clair dans `auto-split.yml`.

### 5. Indexation Packagist : webhook par mirror, pas Packagist Maintainer Token

Chaque mirror `polysource/<pkg>` est enregistré sur Packagist et
configure le **webhook GitHub Packagist** (`https://packagist.org/api/github`)
sur l'event `push`. Conséquence :
- À chaque tag mirror, Packagist reçoit le push event et ré-indexe le
  package en quelques secondes.
- Aucun secret Packagist côté monorepo, aucun token Packagist à rotater.
- L'inscription initiale du package sur Packagist génère le webhook
  automatiquement (Packagist le pousse sur le repo via l'OAuth GitHub
  du mainteneur qui crée le package).

## Conséquences

### Positives

- **Cohérence DX/distribution** : le mainteneur travaille sur un
  monorepo, l'utilisateur consomme 1-N packages indépendants. ADR-012
  et ADR-018 honorés.
- **Atomicité des releases** : un seul tag `v0.1.0` sur le monorepo
  → 16 mirrors taggés v0.1.0 → 16 versions Packagist alignées.
  Impossible d'avoir un `polysource/core` v0.1.5 incompatible avec un
  `polysource/symfony-bundle` v0.1.4.
- **Sécurité** : aucun secret long-terme dans le runner, scope
  chirurgical, audit log par App, révocation atomique en 1 clic.
- **Scaling** : ajouter un 17ᵉ package = (a) nouveau dossier dans
  `packages/`, (b) nouveau repo mirror via `scripts/split-packages.sh`,
  (c) ajouter le repo à la sélection de l'App, (d) ajouter une ligne à
  la matrix du workflow. Aucun nouveau secret, aucune nouvelle clé.

### Négatives / coûts assumés

- **Force-push systématique** sur `main` des mirrors. C'est par
  conception : `git subtree split` regénère un historique déterministe
  à chaque run ; le HEAD du mirror est *toujours* écrasé. Conséquence :
  les mirrors ne peuvent pas être forkés-puis-PRs (c'est précisément
  pourquoi on installe le workflow `close-pull-request.yml` dans chacun).
- **Bus factor sur la GitHub App** : si la private key est perdue,
  toute l'org `polysource` doit en générer une nouvelle. Mitigation :
  procédure de rotation documentée dans le guide maintainer, l'opération
  prend < 5 min.
- **Coût CI** : 16 jobs parallèles à chaque push sur `main`. À ~30 s
  par job, c'est ~8 min de runner-minutes par push. Acceptable au volume
  actuel (≤10 pushes / jour). Si un jour ça devient un problème, on
  pourra (a) batcher avec un seul job qui boucle, (b) ne lancer que
  les packages dont le subtree a réellement changé via `git diff` sur
  `packages/<pkg>/`.

### Neutres

- **Le monorepo n'est pas sur Packagist en tant que méta-package.**
  `composer require polysource/polysource` n'a pas de sens ; les
  utilisateurs requièrent les 16 packages individuellement (en pratique
  1-3 selon leur cas d'usage).
- **Les contributions externes passent uniquement par le monorepo.**
  Le `close-pull-request.yml` sur les mirrors redirige les PRs ; le
  CONTRIBUTING.md est exclusivement sur le monorepo.

## Référence opérationnelle

Tout ce qui est *comment opérer* (release flow, ajout de package,
debug d'un échec de split, rotation de la private key, bootstrap
initial) est documenté dans
[`docs/maintainers/release-and-split.md`](../maintainers/release-and-split.md).
Cet ADR documente le **pourquoi** ; le guide documente le **comment**.

## Historique

- 2026-05-10 — bootstrap initial via `scripts/split-packages.sh`
  (auteur : mainteneur, depuis sa machine, identité SSH personnelle —
  *one-shot pour créer les 16 repos vides et poser le premier tag*)
- 2026-05-10 — webhooks Packagist installés sur les 16 mirrors
- 2026-05-10 — GitHub App `polysource-split` créée + workflow
  `auto-split.yml` migré du design SSH deploy-key (jamais fonctionnel —
  voir tableau §4) vers App + token éphémère ; premier run automatisé
  validé (commits `da00886` puis `8fa505c` pour le fix
  `persist-credentials: false`)
- 2026-05-10 — toggle org *Repository deploy keys* désactivé
  (défense en profondeur — aucun usage, on referme la porte)
