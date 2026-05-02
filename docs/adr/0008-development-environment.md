# ADR-008 — Development environment

- **Date** : 2026-05-02
- **Statut** : Accepté
- **Décide pour** : v0.1+

## Contexte

Le projet Polysource a besoin d'un environnement de développement qui :
1. **Pour le mainteneur** (initialement solo) : permet d'écrire/tester du code rapidement.
2. **Pour les contributeurs** : démarre en moins de 5 minutes, peu importe l'OS (macOS, Linux, Windows WSL).
3. **Pour les utilisateurs de la démo** : `make demo` ou équivalent pour voir Polysource en action en quelques secondes.
4. **Pour la CI** : reproductible, GitHub Actions.

L'utilisateur principal a une habitude **Docker + Makefile**. Il est aussi ouvert à **DDEV** (qu'il ne connaît pas mais souhaite découvrir).

## Options envisagées

### Option A — Docker Compose + Makefile uniquement

Un `docker-compose.yml` (php-fpm 8.4 + nginx) et un `Makefile` qui wrappe les commandes.

**Pour** :
- Familiar pour les devs PHP.
- Léger.
- Cible Docker = standard sur tous les OS modernes.

**Contre** : pas de support direct pour les devs DDEV.

### Option B — DDEV uniquement

DDEV est un wrapper Docker pour le dev PHP (Symfony, Drupal, WordPress, Laravel). Il automatise SSL local, hosts, mailpit, phpmyadmin, etc.

**Pour** :
- Très bien pour le dev Symfony.
- Communauté Symfony l'utilise pas mal.
- Configuration `.ddev/config.yaml` simple.

**Contre** :
- Force l'installation de DDEV (en plus de Docker).
- Moins de contrôle fin sur les services.
- Le mainteneur ne le connaît pas (apprentissage).

### Option C — Docker Compose + Makefile + DDEV optionnel

Le repo fournit **les deux** :
- `docker-compose.yml` + `Makefile` : chemin par défaut.
- `.ddev/config.yaml` : alternative pour les devs DDEV.

**Pour** :
- Choix laissé au contributeur.
- Le mainteneur utilise Docker + Make qu'il connaît.
- Les devs habitués DDEV ne sont pas exclus.

**Contre** :
- Duplication de config (mais minime, ~50 lignes).
- 2 environnements à maintenir (1 ligne de doc supplémentaire).

### Option D — Pas de Docker du tout (composer install local)

Pour le **dev de la lib** (pas la démo), Docker n'est pas obligatoire. `composer install && phpunit` suffit.

**Pour** : minimal, rapide.
**Contre** : tests d'intégration (Messenger avec failed transport) demandent un service tiers.

## Décision

**Option C — Docker Compose + Makefile + DDEV optionnel** est retenue.

Avec une nuance : pour le **dev de base de la lib** (Phases 1-3), Docker n'est pas strictement obligatoire. `composer install && composer test` fonctionnera sur n'importe quelle machine PHP 8.4. Docker devient nécessaire à partir de la **Phase 4** (`adapter-messenger` test fonctionnel) et de la **démo** (Phase 8).

### Structure de fichiers

```
polysource/
├── Dockerfile                 (php-fpm 8.4 + extensions Symfony)
├── docker-compose.yml         (php-fpm + nginx pour dev/test)
├── Makefile                   (wrappers commandes)
└── .ddev/
    └── config.yaml            (config DDEV équivalente)
```

### Cibles Makefile principales

```makefile
.PHONY: help install test phpstan cs-fix demo demo-down clean

help:               ## Affiche cette aide
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | sort

install:            ## Build le container et installe les dépendances Composer
	docker compose build
	docker compose run --rm php composer install

test:               ## Lance PHPUnit sur tous les packages
	docker compose run --rm php vendor/bin/phpunit

phpstan:            ## Static analysis level max
	docker compose run --rm php vendor/bin/phpstan analyse

cs-fix:             ## Applique le code style PSR-12 + Symfony rules
	docker compose run --rm php vendor/bin/php-cs-fixer fix

demo:               ## Démarre la démo Messenger failed
	cd examples/messenger-demo && docker compose up -d
	@echo "Démo disponible sur http://localhost:8080/admin/failed-messages"

demo-down:          ## Arrête la démo
	cd examples/messenger-demo && docker compose down

clean:              ## Nettoie vendor/, var/, caches
	rm -rf vendor/ var/cache/ var/log/ .phpunit.result.cache
```

### docker-compose.yml minimal

```yaml
services:
  php:
    build: .
    volumes:
      - .:/app
    working_dir: /app
    user: ${UID:-1000}:${GID:-1000}

  # nginx ajouté en Phase 8 (démo) seulement
```

### Dockerfile minimal

```dockerfile
FROM php:8.4-cli-alpine

RUN apk add --no-cache git unzip libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-install intl zip pdo pdo_mysql opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

Pas de Postgres / Redis / MySQL en dev de la lib — les tests utilisent `InMemoryTransport` Messenger. Ces services apparaissent uniquement dans `examples/messenger-demo/docker-compose.yml`.

### Config DDEV minimale (`.ddev/config.yaml`)

```yaml
name: polysource
type: php
docroot: ""
php_version: "8.4"
webserver_type: nginx-fpm
database:
  type: postgres
  version: "16"
omit_containers: [db, dba, ddev-ssh-agent]   # par défaut, pas de db
nodejs_version: "20"
```

L'utilisateur DDEV peut faire :
```bash
ddev start
ddev composer install
ddev exec phpunit
```

## Conséquences

### Positives

- **Mainteneur productif** : Docker + Make = environnement familier.
- **Contributeurs DDEV bienvenus** : alternative supportée.
- **Démo virale** : `make demo` en une commande.
- **CI alignée** : GitHub Actions utilise les mêmes images Docker (Dockerfile partagé).
- **Reproductibilité** : versions épinglées dans Dockerfile.

### Négatives

- **Léger overhead Docker** : ~30 secondes au premier `make install`. Acceptable.
- **Deux environnements à documenter** : Docker/Make ET DDEV. Mitigé : 1 page de doc chacun.
- **DDEV non testé en CI** (la CI n'utilise que Docker). Documenté : DDEV est best-effort, à reporter en issue si problème.

### Pour les contributeurs

Doc CONTRIBUTING.md (à compléter en Phase 0) :

> ## Local development
>
> **Option 1 (recommended) — Docker + Make :**
> ```bash
> make install
> make test
> ```
>
> **Option 2 — DDEV :**
> ```bash
> ddev start
> ddev composer install
> ddev exec phpunit
> ```
>
> **Option 3 — local PHP 8.4 :**
> ```bash
> composer install
> vendor/bin/phpunit
> ```

## Références

- [Docker Compose v2](https://docs.docker.com/compose/)
- [DDEV docs](https://ddev.readthedocs.io/)
- Symfony Demo App utilise Docker Compose minimal — modèle similaire.
