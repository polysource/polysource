# Symfony Flex recipes — prepared manifests for `symfony/recipes-contrib`

This folder contains the **prepared** Symfony Flex recipe manifests for
every Polysource bundle distributed on Packagist. Recipes here are the
**source of truth**; they are not yet published to
[`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib).

Submitting them is a maintainer task — see [Submission workflow](#submission-workflow)
below.

## Why ship recipes?

Symfony Flex's auto-generation already registers Polysource bundles in
`config/bundles.php` on install, so the basic case works without a
contrib recipe. Shipping a contrib recipe adds three concrete wins:

1. **Default config files with helpful comments.** For the bridge, we
   want to surface the `auto_register_routes: true` opt-out at install
   time (multi-tenant hosts need this — see
   [installation.md](../../user/installation.md)). Without a recipe,
   hosts only discover this option after reading docs.
2. **Stable shortcuts.** `composer require polysource-filter` instead
   of `composer require polysource/filter`. Useful for showcase
   walkthroughs and tutorials.
3. **Discoverability.** Listed in
   [`symfony.com/recipes`](https://symfony.com/recipes), increasing
   the project's visibility to Symfony developers browsing for admin
   tooling.

## ⚠ Multi-kernel hosts

Symfony Flex's recipe runner registers bundles in
`config/bundles.php` (the **root**, app-wide file). Multi-kernel
apps that read this file across all kernels work fine, but apps
following the [Symfony multi-app
pattern](https://symfony.com/doc/current/configuration/multiple_kernels.html)
that maintain per-kernel `apps/*/config/bundles.php` files get an
incorrectly-scoped registration:

- The recipe adds the bundle to `config/bundles.php` (shared)
- The bundle then loads on every kernel — including ones where its
  dependencies aren't installed (e.g. Polysource standalone admin
  loading on the `job` kernel that has no EasyAdmin or twig setup)

**Workaround**: after running `composer require polysource/<pkg>`,
remove the bundle entry from the shared `config/bundles.php` and
add it manually to the per-kernel file(s) where you actually want
it. Example for `polysource/symfony-bundle` on an app that
exposes Polysource only on `apps/backend`:

```diff
--- a/config/bundles.php
+++ b/config/bundles.php
@@ ... @@
-    Polysource\Bundle\PolysourceBundle::class => ['all' => true],
 ];
```

```diff
--- a/apps/backend/config/bundles.php
+++ b/apps/backend/config/bundles.php
@@ ... @@
+    Polysource\Bundle\PolysourceBundle::class => ['all' => true],
 ];
```

This isn't a polysource-specific issue — every Flex recipe behaves
the same way. We can't fix it from the recipe manifest because Flex
has no notion of multi-kernel layout. Document the workaround in
the host app's own README.

## What's prepared

14 recipes, one per bundle. The monorepo ships 16 packages, but
`polysource/core` and `polysource/twig-theme` register no bundle and
need no Flex recipe.

| Package | Recipe | Default config? |
|---|---|---|
| [polysource/filter](polysource/filter/0.1/) | ✓ | – |
| [polysource/easyadmin-filter-bridge](polysource/easyadmin-filter-bridge/0.1/) | ✓ | `polysource_easyadmin_filter_bridge.yaml` (documents `auto_register_routes`) |
| [polysource/symfony-bundle](polysource/symfony-bundle/0.1/) | ✓ | – |
| [polysource/widgets](polysource/widgets/0.1/) | ✓ | – |
| [polysource/search](polysource/search/0.1/) | ✓ | – |
| [polysource/bulk-async](polysource/bulk-async/0.1/) | ✓ | – |
| [polysource/audit](polysource/audit/0.1/) | ✓ | – |
| [polysource/workflow-bridge](polysource/workflow-bridge/0.1/) | ✓ | – |
| [polysource/adapter-messenger](polysource/adapter-messenger/0.1/) | ✓ | – |
| [polysource/adapter-doctrine](polysource/adapter-doctrine/0.1/) | ✓ | – |
| [polysource/adapter-redis](polysource/adapter-redis/0.1/) | ✓ | – |
| [polysource/adapter-flysystem](polysource/adapter-flysystem/0.1/) | ✓ | – |
| [polysource/adapter-http](polysource/adapter-http/0.1/) | ✓ | – |
| [polysource/adapter-meilisearch](polysource/adapter-meilisearch/0.1/) | ✓ | – |

All recipes still target the `0.1` version directory. Flex picks the
most specific compatible version at install time, so a `0.1/` recipe
keeps applying to every later release — including v1.0.0 and v1.1.0 —
until a `1.0/` directory exists upstream.

> ⚠️ **Overdue.** v1.0.0 shipped 2026-08-06 and v1.1.0 on 2026-08-07,
> and no `1.0/` directory has been branched. See
> [Maintaining recipes across versions](#maintaining-recipes-across-versions)
> for the steps. Installs are not broken — the `0.1/` recipe is still
> resolved and applied — but the recipes no longer reflect the 1.x
> default config.

## Recipe format primer

A Flex recipe is a directory containing:

- `manifest.json` — declares what bundles to register, what files to
  copy, env vars to add, etc. See the
  [Flex manifest schema](https://github.com/symfony/recipes/blob/main/SCHEMA.json).
- Optional `config/`, `templates/`, `src/`, … directories whose
  contents get **copied into the host application** at install time
  via the `copy-from-recipe` directive.

### Minimal manifest

```json
{
    "bundles": {
        "Polysource\\Filter\\PolysourceFilterBundle": ["all"]
    },
    "aliases": ["polysource-filter"]
}
```

### Manifest with default config

```json
{
    "bundles": {
        "Polysource\\EasyAdminFilterBridge\\PolysourceEasyAdminFilterBridgeBundle": ["all"]
    },
    "copy-from-recipe": {
        "config/": "%CONFIG_DIR%/"
    },
    "aliases": ["polysource-bridge"]
}
```

Then a sibling file `config/packages/polysource_easyadmin_filter_bridge.yaml`
in the recipe directory gets copied to `<app>/config/packages/` on install.

## Submission workflow

> **Important:** Recipes must NOT live in the Polysource monorepo at
> publish time. They live in `symfony/recipes-contrib`, which is the
> community-maintained recipes repository. The Symfony Flex client
> fetches from a JSON index served by
> [`flex.symfony.com`](https://flex.symfony.com).

### One-time setup

1. Fork [`symfony/recipes-contrib`](https://github.com/symfony/recipes-contrib)
   to your personal GitHub account.
2. Clone the fork locally:
   ```bash
   git clone git@github.com:<your-user>/recipes-contrib.git
   cd recipes-contrib
   git remote add upstream https://github.com/symfony/recipes-contrib.git
   ```

### Submitting (per package)

The submission flow is the same for every package. Example for
`polysource/easyadmin-filter-bridge`:

1. Sync the fork with upstream:
   ```bash
   git fetch upstream
   git checkout main
   git merge --ff-only upstream/main
   git push origin main
   ```
2. Branch:
   ```bash
   git checkout -b add-polysource-easyadmin-filter-bridge
   ```
3. Copy the prepared recipe directory verbatim:
   ```bash
   mkdir -p polysource/easyadmin-filter-bridge
   cp -R /private/var/www/polysource/docs/maintainers/flex-recipes/polysource/easyadmin-filter-bridge/0.1 \
         polysource/easyadmin-filter-bridge/
   ```
4. Validate the manifest locally (the contrib repo ships a CI script):
   ```bash
   composer install
   ./vendor/bin/validate-manifests polysource/easyadmin-filter-bridge/0.1
   ```
5. Commit and push:
   ```bash
   git add polysource/easyadmin-filter-bridge
   git commit -m "Add polysource/easyadmin-filter-bridge recipe"
   git push -u origin add-polysource-easyadmin-filter-bridge
   ```
6. Open a PR against `symfony/recipes-contrib` titled `Add polysource/easyadmin-filter-bridge recipe`.
   In the PR description, include:
   - Link to Packagist: https://packagist.org/packages/polysource/easyadmin-filter-bridge
   - Link to the polysource user docs:
     https://github.com/polysource/polysource/blob/main/docs/user/easyadmin-filter-bridge/getting-started.md
   - Note that the `auto_register_routes` config option is documented inline.

### Submitting multiple packages

If submitting all 14 recipes, the recommendation from the Flex team
(based on their contributor guidelines) is **one PR per package** —
not one bulk PR. This keeps each recipe reviewable independently and
prevents one rejection from blocking the others. Open them in batches
of 3–4 to avoid overwhelming the review queue.

### After merge

Once a recipe is merged into `symfony/recipes-contrib`, the
[`flex.symfony.com`](https://flex.symfony.com) index regenerates
within ~30 minutes and `composer require polysource/<pkg>` starts
applying the recipe automatically. No further action needed from the
Polysource side.

Test the recipe end-to-end after merge:

```bash
cd /tmp && rm -rf test-flex
composer create-project symfony/skeleton:^7.4 test-flex
cd test-flex
composer require polysource/easyadmin-filter-bridge:^0.1
# Verify config/packages/polysource_easyadmin_filter_bridge.yaml exists
cat config/packages/polysource_easyadmin_filter_bridge.yaml
```

## Maintaining recipes across versions

**Status: action overdue since 2026-08-06.** v1.0.0 (API freeze) and
v1.1.0 both shipped without a `1.0/` recipe directory being branched
upstream. Nothing is broken — Flex falls back to `0.1/` — but 1.x
installs get 0.x-era default config.

The work, to be done in a `symfony/recipes-contrib` fork (not in this
repo — the directories under `docs/maintainers/flex-recipes/polysource/`
are the submission source, and deliberately still show only `0.1/`):

1. Branch each existing `0.1/` directory into a sibling `1.0/`
   directory inside `symfony/recipes-contrib`.
2. Update default config for anything renamed or added since 0.1 —
   in particular the v1.1.0 row-details options on
   `polysource/symfony-bundle` and
   `polysource/easyadmin-filter-bridge`.
3. Submit one PR per package titled
   `Update polysource/<pkg> recipe for v1.0`.

The Flex client picks the most specific compatible version at install
time, so 0.x users keep getting the `0.1/` recipe once `1.0/` lands.

## Sanity checks before submitting

- [ ] `manifest.json` is valid JSON (use `python3 -m json.tool < manifest.json`)
- [ ] Bundle FQCN exactly matches the published package's namespace
- [ ] `aliases` are kebab-case and don't collide with existing recipes
      (check [recipes.json index](https://flex.symfony.com/index.json))
- [ ] No `env` block (Polysource bundles don't need env vars at install)
- [ ] No `composer-scripts` block (Polysource doesn't ship install
      scripts — bundles auto-wire on first cache:clear)
- [ ] Default config (if any) has explanatory comments, not just raw keys

## Source of these recipes

These manifests are derived from the actual bundle FQCNs in
`packages/<pkg>/src/Polysource*Bundle.php` and the default config from
`packages/easyadmin-filter-bridge/src/DependencyInjection/Configuration.php`
in the Polysource monorepo. Keep them in sync if you rename a bundle
class or add a new config option — the integration test that catches
drift is in `tests/Integration/Flex/RecipeManifestsTest.php` (TODO,
tracked separately).

## Related

- [Symfony recipes server schema](https://github.com/symfony/recipes/blob/main/SCHEMA.json)
- [Flex contributor guidelines](https://github.com/symfony/recipes-contrib/blob/main/CONTRIBUTING.md)
- [ADR-026 — monorepo split + Packagist mirrors](../../adr/0026-monorepo-split-and-packagist-mirrors.md)
- [Maintainer release pipeline](../release-and-split.md)
