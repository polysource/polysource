# Polysource filter-standalone demo — Make targets.
#
# This demo deliberately runs on the PHP 8.1 / Symfony 5.4 floor
# (cf. ADR-015) to prove the polysource/filter primitive works on
# legacy stacks without any admin framework (no EasyAdmin, no
# Sonata, no API Platform).

.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
PHP_RUN := $(DOCKER_COMPOSE) run --rm -T php
UID := $(shell id -u)
GID := $(shell id -g)
export UID GID

.PHONY: help
help: ## Show this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)

.PHONY: build
build: ## Build the PHP 8.1 container image
	$(DOCKER_COMPOSE) build

.PHONY: install
install: build ## Install Composer dependencies + create the SQLite saved-views schema
	$(PHP_RUN) sh -c 'mkdir -p var && composer install --no-interaction --no-progress'
	$(PHP_RUN) sh -c 'rm -f var/data.db && php bin/console doctrine:schema:create --no-interaction'

.PHONY: serve
serve: ## Start the dev server on http://localhost:8082
	$(DOCKER_COMPOSE) up

.PHONY: clean
clean: ## Drop containers + cache + saved-views database
	$(DOCKER_COMPOSE) down -v
	rm -rf var/cache var/log var/data.db
