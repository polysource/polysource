# Dogfooding : lier une app hôte au monorepo sans taguer

Boucle de feedback rapide quand une app hôte réelle (une app cliente
existante, un projet interne…) consomme Polysource et remonte des
bugs : on ne tague pas à chaque fix — l'app pointe sur le clone local
via un **path repository symlinké**, et on ne tague qu'une fois le
lot validé.

## Mise en place (une fois, côté app hôte)

1. Monter le monorepo dans le conteneur PHP de l'app au même chemin
   qu'à l'extérieur (ex. `/var/www/polysource`).
2. Déclarer le path repo + la stabilité :

   ```bash
   composer config repositories.polysource-dev \
     '{"type": "path", "url": "/var/www/polysource/packages/*", "options": {"symlink": true}}'
   composer config minimum-stability dev
   composer config prefer-stable true
   ```

## Basculer

- **Mode dev (symlink live)** — les branch-aliases `dev-main` du
  monorepo (lignée `X.(Y+1).x-dev`) rendent la contrainte stable
  dans le temps :

  ```bash
  composer require 'polysource/easyadmin-filter-bridge:^1.3@dev' ...
  ```

  Toute édition dans `packages/*` est vivante immédiatement
  (`cache:clear` pour la DI/Twig ; `importmap:install` une fois
  après la bascule ; ne pas compiler l'asset-map en dev).

- **Retour en stable** (après tag) :

  ```bash
  composer require 'polysource/easyadmin-filter-bridge:^1.2' ...
  ```

  Le path repo peut rester déclaré : `prefer-stable` + contrainte
  stable ⇒ Composer reprend Packagist.

## Garde-fous obligatoires

- **CI de l'app hôte** : refuser tout lock en version dev —
  `! grep -q '"version": ".*x-dev"' composer.lock` avant deploy.
  Le mode dev vit sur la machine/branche du dev, jamais en prod.
- **Chaque bug remonté devient d'abord un test qui échoue** dans le
  monorepo (unit ou integration), puis le fix. Le symlink sert à
  reproduire vite ; le test empêche la régression (leçon du
  dogfooding de mai : 8 bugs basiques en 1 h faute d'E2E).
- Jamais de nom de client dans les commits/fichiers du monorepo
  ("an existing client app").

## Cadence de release

Boucle symlink (zéro tag) → lot validé dans l'app hôte →
`make docs-check` + `make smoke` → **un** tag (patch ou minor selon
SemVer) → [release flow](./release-and-split.md) → l'app hôte
rebascule en stable.
