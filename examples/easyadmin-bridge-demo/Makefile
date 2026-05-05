# Polysource EasyAdmin filter bridge demo — Make targets.
#
# Run from `examples/easyadmin-bridge-demo/`. Prefer `make demo-bridge` from the
# repo root which wraps these steps for first-time visitors.

.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
PHP_RUN := $(DOCKER_COMPOSE) run --rm -T php
# Some macOS users on directory-managed accounts (LDAP/AD/Kerberos)
# have host UID/GID values larger than Alpine's `addgroup`/`adduser`
# range (max 256000). Fall back to 1000 in that case — Docker Desktop's
# osxfs translates UID/GID at the volume mount boundary, so the
# container UID doesn't need to match the host one.
HOST_UID := $(shell id -u)
HOST_GID := $(shell id -g)
UID := $(shell [ $(HOST_UID) -gt 256000 ] && echo 1000 || echo $(HOST_UID))
GID := $(shell [ $(HOST_GID) -gt 256000 ] && echo 1000 || echo $(HOST_GID))
export UID GID

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

.PHONY: build
build: ## Build the PHP container image
	$(DOCKER_COMPOSE) build

.PHONY: install
install: build ## Install Composer dependencies + bundle assets + create the SQLite database + schema
	$(PHP_RUN) sh -c 'mkdir -p var && composer install --no-interaction --no-progress'
	$(PHP_RUN) sh -c 'php bin/console assets:install --symlink public'
	$(PHP_RUN) sh -c 'rm -f var/data.db && php bin/console doctrine:schema:create --no-interaction'

.PHONY: seed
seed: ## Seed sample products + categories
	$(PHP_RUN) php bin/console polysource:demo:seed-products

.PHONY: up
up: install seed ## Boot the demo (install + seed + serve on :8081)
	@echo ''
	@echo '▶ Polysource EasyAdmin filter bridge demo is starting on http://localhost:8081/admin/'
	@echo '  Login: admin / admin'
	@echo ''
	$(DOCKER_COMPOSE) run --rm --service-ports php php -S 0.0.0.0:8081 -t public public/dev-router.php

.PHONY: down
down: ## Stop the demo container
	$(DOCKER_COMPOSE) down

.PHONY: logs
logs: ## Tail demo logs
	$(DOCKER_COMPOSE) logs -f

.PHONY: shell
shell: ## Open a shell inside the container
	$(PHP_RUN) sh

.PHONY: clean
clean: down ## Stop + remove vendor + database + symlinked bundle assets
	rm -rf vendor/ var/ public/bundles/
