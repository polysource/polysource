# Release & monorepo split — guide opérationnel

> **Audience** : mainteneurs avec droit `admin` sur l'org `polysource`.
> **Pour le rationale** (pourquoi monorepo + N mirrors + GitHub App
> plutôt que polyrepo, PAT, deploy keys, etc.), voir
> [ADR-026](../adr/0026-monorepo-split-and-packagist-mirrors.md).

## Vue d'ensemble du pipeline

```
┌─────────────────────────────────────┐
│ DÉVELOPPEMENT                       │
│ Monorepo polysource/polysource      │
│ packages/core, packages/symfony-…   │
│ 1 PR, 1 CI, 1 historique commun     │
└──────────────┬──────────────────────┘
               │ git push origin main / git push origin v0.1.x
               ▼
┌─────────────────────────────────────┐
│ ORCHESTRATION                       │
│ .github/workflows/auto-split.yml    │
│ Triggered: push main, push tag v*   │
│ 16 jobs parallèles (matrix)         │
└──────────────┬──────────────────────┘
               │ Mint installation token (≤60 min) via
               │ actions/create-github-app-token
               │ from GitHub App polysource-split
               ▼
┌─────────────────────────────────────┐
│ SPLIT                               │
│ git subtree split --prefix=…        │
│ → SHA déterministe par package      │
└──────────────┬──────────────────────┘
               │ git push --force HTTPS+token
               ▼
┌─────────────────────────────────────┐
│ MIRRORS (read-only)                 │
│ polysource/core                     │
│ polysource/symfony-bundle           │
│ … (16 repos)                        │
│ HEAD = split SHA, tags mirrorés     │
└──────────────┬──────────────────────┘
               │ Webhook GitHub Packagist (push event)
               ▼
┌─────────────────────────────────────┐
│ PACKAGIST                           │
│ packagist.org/packages/polysource/* │
│ Indexe le tag → composer require    │
│ marche dans les ~30 s               │
└─────────────────────────────────────┘
```

## Le release flow (cas nominal)

> Faire un nouveau release v0.X.Y, partant d'un `main` propre.

1. **Sur le monorepo, prepare le release**
   ```bash
   # CHANGELOG.md : déplacer [Unreleased] → [0.X.Y] avec la date
   # composer.json : mettre à jour les contraintes inter-packages si besoin
   git add CHANGELOG.md packages/*/composer.json
   git commit -m "release: v0.X.Y"
   ```

2. **Tag annoté** (jamais de tag léger sur le monorepo, l'annoté
   transporte un message + la signature)
   ```bash
   git tag -a v0.X.Y -m "Polysource v0.X.Y — <highlight>"
   ```

3. **Push commit + tag**
   ```bash
   git push origin main
   git push origin v0.X.Y
   ```

4. **Vérifier le run d'auto-split**
   ```bash
   gh run watch --workflow=auto-split.yml --repo polysource/polysource --exit-status
   ```
   Attendu : 16/16 jobs verts en < 2 min.

5. **Cross-check les SHAs**
   ```bash
   gh run view <run-id> --repo polysource/polysource --log \
     | grep "Split SHA:"
   for pkg in core symfony-bundle …; do
     gh api "repos/polysource/$pkg/commits/main" --jq '.sha'
   done
   ```
   Chaque `Split SHA` du run doit matcher exactement le HEAD du mirror
   correspondant.

6. **Vérifier que les tags ont été mirrorés**
   ```bash
   for pkg in core symfony-bundle …; do
     gh api "repos/polysource/$pkg/git/refs/tags/v0.X.Y" --jq '.ref'
   done
   ```

7. **Vérifier que Packagist a vu** (compter ~30-60 s après le push tag)
   ```bash
   curl -sf https://repo.packagist.org/p2/polysource/core.json \
     | jq '.packages."polysource/core" | map(.version) | sort'
   ```
   La version `v0.X.Y` (ou `0.X.Y`) doit apparaître.

8. **Créer la GitHub Release** (extrait du CHANGELOG)
   ```bash
   gh release create v0.X.Y --title "v0.X.Y" \
     --notes-file <(awk '/## \[0\.X\.Y\]/,/## \[/{print}' CHANGELOG.md \
                   | sed '$d')
   ```

## Ajouter un nouveau package au monorepo

1. **Créer le dossier** `packages/<new-pkg>/` avec son `composer.json`,
   ses sources, ses tests
2. **Créer le repo mirror vide** sur GitHub
   ```bash
   ./scripts/split-packages.sh --tag v0.X.Y <new-pkg>
   ```
   Le script appelle `gh repo create polysource/<new-pkg> --public`
   puis pousse le premier split + tag.
3. **Étendre l'installation de l'App** : org Settings → GitHub Apps →
   `polysource-split` → Configure → *Repository access* → cocher
   `polysource/<new-pkg>` dans la liste. **Sans cette étape, le
   workflow auto-split échouera au push avec un 403** au prochain run.
4. **Inscrire le package sur Packagist** :
   https://packagist.org/packages/submit, soumettre
   `https://github.com/polysource/<new-pkg>`. Packagist installe
   automatiquement son webhook sur le mirror.
5. **Étendre la matrix du workflow** : ajouter `- <new-pkg>` dans
   `.github/workflows/auto-split.yml` *et* dans
   `scripts/split-packages.sh` (constante `ALL_PACKAGES`).
6. **Commit + push** sur `main` → vérifier que le 17ᵉ job de la matrix
   passe vert.

## Debugger un échec d'auto-split

### Symptôme : le job échoue au step *Mint installation token*

Causes possibles :
- **`SPLIT_APP_PRIVATE_KEY` invalide ou expiré** (la clé a été
  révoquée depuis la page App settings) → régénérer + remplacer
  (procédure de rotation ci-dessous)
- **`SPLIT_APP_CLIENT_ID` ne correspond plus à l'App** (App
  supprimée et recréée) → vérifier `gh variable list --repo
  polysource/polysource` vs le Client ID affiché dans org Settings →
  GitHub Apps → polysource-split (le Client ID est à côté du
  numeric App ID, format `Iv23li…`)

### Symptôme : le job échoue au step *Push split to polysource/&lt;pkg&gt;*

Causes possibles :
- **`Permission to polysource/<pkg>.git denied to github-actions[bot]`**
  → `persist-credentials: false` a été retiré du `actions/checkout` ;
  le `GITHUB_TOKEN` du runner shadowne l'installation token. Restaurer.
- **`Permission … denied to polysource-split[bot]`** → le repo n'est
  pas dans la sélection de l'App. Aller dans org Settings → GitHub
  Apps → polysource-split → Configure → cocher le repo.
- **`Repository not found`** → typo dans la matrix ou le repo a été
  renommé/supprimé.

### Symptôme : le split réussit mais Packagist ne voit pas la nouvelle version

- Vérifier que le webhook Packagist existe et a été appelé :
  ```bash
  gh api repos/polysource/<pkg>/hooks --jq \
    '.[] | select(.config.url | contains("packagist")) | .last_response'
  ```
  `last_response.code` doit être `202`. Si autre (`401`, `404`,
  `5xx`), aller sur https://packagist.org/packages/polysource/<pkg>
  et cliquer *Update* manuellement, puis investiguer côté Packagist
  (token expiré côté Packagist, package mis en lecture-seule, etc.).

## Rotation de la private key de l'App

À faire au moins **une fois par an**, et **immédiatement** si on
soupçonne une fuite (clé loggée par accident, machine compromise,
etc.). Procédure :

1. **Générer une nouvelle private key**
   - https://github.com/organizations/polysource/settings/apps/polysource-split
   - Section *Private keys* → *Generate a private key*
   - Télécharger le `.pem`

2. **Remplacer le secret côté monorepo**
   ```bash
   gh secret set SPLIT_APP_PRIVATE_KEY \
     --repo polysource/polysource < /chemin/vers/nouveau.pem
   ```

3. **Tester** que la rotation marche
   ```bash
   gh workflow run auto-split.yml --repo polysource/polysource
   gh run watch --workflow=auto-split.yml --repo polysource/polysource \
     --exit-status
   ```
   Attendu : 16/16 jobs verts.

4. **Révoquer l'ancienne clé** depuis la même page App settings
   → *Private keys* → bouton *Delete* à côté de l'ancienne. À partir
   de cet instant, l'ancien `.pem` ne mint plus aucun token.

5. **Cleanup local**
   ```bash
   rm /chemin/vers/nouveau.pem
   ```
   Le secret est désormais sur GitHub, le `.pem` local est devenu un
   risque dormant — il ne doit pas survivre à la procédure.

## Bootstrap initial (one-shot — référence historique)

Cette procédure a été exécutée **une seule fois** le 2026-05-10 pour
amorcer les 16 mirrors. Elle est documentée pour mémoire et pour le
cas hypothétique où on devrait recréer l'org from scratch.

1. **Créer la GitHub App** (org Settings → GitHub Apps → New)
   - Name : `polysource-split`
   - Webhook : désactivé
   - Repository permissions : `Contents: Read and write`,
     `Metadata: Read-only`, **rien d'autre**
   - Where can this App be installed? → *Only on this account*
   - Générer + télécharger le premier `.pem`
   - Noter l'App ID (~ 7 chiffres)

2. **Créer les 16 repos vides + premier split**
   ```bash
   git tag v0.1.0  # tag annoté sur le monorepo d'abord
   ./scripts/split-packages.sh --tag v0.1.0
   ```

3. **Installer l'App sur les 16 mirrors**
   - org Settings → GitHub Apps → polysource-split → Install
   - *Only select repositories* → cocher les 16 mirrors
   - **Pas le monorepo** (l'App ne doit avoir aucun pouvoir dessus)

4. **Stocker secret + variable côté monorepo**
   ```bash
   gh secret set SPLIT_APP_PRIVATE_KEY \
     --repo polysource/polysource < polysource-split.YYYY-MM-DD.private-key.pem
   gh variable set SPLIT_APP_CLIENT_ID \
     --repo polysource/polysource --body "<client-id>"   # format Iv23li…
   rm polysource-split.YYYY-MM-DD.private-key.pem
   ```
   Note : on stocke le **Client ID** (chaîne `Iv23li…`), pas le
   numeric App ID. `actions/create-github-app-token@v3` accepte les
   deux mais préfère `client-id`.

5. **Inscrire les 16 packages sur Packagist** (UI Packagist) — installe
   le webhook GitHub Packagist sur chaque mirror.

6. **Désactiver le toggle org *Deploy keys*** une fois le bootstrap
   terminé : org Settings → Member privileges → Repository deploy keys
   → décocher. Défense en profondeur, on ne s'en sert pas.

## Anti-patterns à ne pas réintroduire

- ❌ **Ne jamais** stocker un PAT utilisateur comme secret de push.
  Scope trop large, expiration manuelle, audit dilué. ADR-026 §4.
- ❌ **Ne jamais** essayer de réutiliser la même clé SSH publique
  comme deploy key sur plusieurs mirrors — GitHub indexe par
  fingerprint et rejette dès le 2ᵉ repo.
- ❌ **Ne jamais** retirer `persist-credentials: false` de
  `actions/checkout` dans `auto-split.yml` — le `GITHUB_TOKEN` du
  runner shadowne alors le token App et tous les push échouent en 403.
- ❌ **Ne jamais** publier `polysource/polysource` comme méta-package
  sur Packagist. Casse ADR-018 (chaque package est un plugin opt-in
  indépendant). Les utilisateurs requièrent 1-N packages selon leur
  cas, jamais 16 d'un coup.
- ❌ **Ne jamais** accepter un PR sur un mirror. Le workflow
  `close-pull-request.yml` les ferme automatiquement avec une
  redirection vers le monorepo, mais la règle reste valable même si
  le workflow tombe : *single source of truth = monorepo*.
