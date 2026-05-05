# Polysource Messenger demo — Make targets.
#
# Run from `examples/messenger-demo/`. Prefer `make demo` from the repo
# root which wraps these steps for first-time visitors.

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
install: build ## Install Composer dependencies + create the SQLite database
	$(PHP_RUN) sh -c 'mkdir -p var && composer install --no-interaction --no-progress'
	$(PHP_RUN) sh -c 'rm -f var/data.db && php bin/console messenger:setup-transports --no-interaction'

.PHONY: seed
seed: ## Push 5 sample envelopes onto the failed transport
	$(PHP_RUN) php bin/console polysource:demo:seed

.PHONY: up
up: install seed ## Boot the demo (install + seed + serve on :8080)
	@echo ''
	@echo '▶ Polysource demo is starting on http://localhost:8080/admin/failed-messages'
	@echo '  Login: admin / admin'
	@echo ''
	$(DOCKER_COMPOSE) run --rm --service-ports php php -S 0.0.0.0:8080 -t public public/index.php

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
clean: down ## Stop + remove vendor + database
	rm -rf vendor/ var/
