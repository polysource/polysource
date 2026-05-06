# Polysource Showcase Demo — Make targets.
#
# Run from `examples/showcase-demo/`. The repo-root Makefile aliases
# `make showcase` to `make -C examples/showcase-demo up`.

.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
PHP_RUN := $(DOCKER_COMPOSE) run --rm -T php
PHP_EXEC := $(DOCKER_COMPOSE) exec -T php

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
	$(DOCKER_COMPOSE) build php

.PHONY: install
install: build ## Install Composer dependencies
	$(PHP_RUN) sh -c 'mkdir -p var && composer install --no-interaction --no-progress'

.PHONY: db
db: ## Reset and migrate the database (dev)
	$(PHP_EXEC) php bin/console doctrine:database:drop --if-exists --force --no-interaction
	$(PHP_EXEC) php bin/console doctrine:database:create --no-interaction
	$(PHP_EXEC) php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
	$(PHP_EXEC) php bin/console messenger:setup-transports --no-interaction

.PHONY: fixtures
fixtures: ## Load Foundry fixtures + seed messenger/redis/minio/meilisearch
	$(PHP_EXEC) php -d memory_limit=2G bin/console doctrine:fixtures:load --no-interaction
	$(PHP_EXEC) php -d memory_limit=2G bin/console app:seed-failed-messages
	$(PHP_EXEC) php -d memory_limit=2G bin/console app:seed-stores

.PHONY: assets
assets: ## Compile AssetMapper public/ files
	$(PHP_EXEC) php bin/console importmap:install
	$(PHP_EXEC) php bin/console asset-map:compile

.PHONY: up
up: install ## Boot the showcase: up the stack, install deps, init db, fixtures, serve on :8084
	$(DOCKER_COMPOSE) up -d
	@echo ""
	@echo "▶ Waiting for services to become healthy..."
	@$(DOCKER_COMPOSE) ps
	$(MAKE) db || echo "(db init skipped — Phase A bootstrap, no entities yet)"
	@echo ""
	@echo "▶ Polysource Showcase ready: http://localhost:8084/"
	@echo "  Login: admin@shop.co / shopco  (or ops@shop.co / viewer@shop.co)"
	@echo "  Mailpit:     http://localhost:8025/"
	@echo "  MinIO UI:    http://localhost:9101/  (shopcoadmin / shopcoadmin-secret)"
	@echo "  Meilisearch: http://localhost:7702/  (key: showcase-meili-master-key)"
	@echo "  WireMock:    http://localhost:8089/__admin/"
	@echo ""

.PHONY: down
down: ## Stop the stack (preserves volumes)
	$(DOCKER_COMPOSE) down

.PHONY: reset
reset: ## Stop the stack and wipe all volumes (data loss!)
	$(DOCKER_COMPOSE) down --volumes --remove-orphans

.PHONY: logs
logs: ## Tail logs of all services
	$(DOCKER_COMPOSE) logs -f

.PHONY: shell
shell: ## Open a shell in the PHP container
	$(DOCKER_COMPOSE) exec php sh

.PHONY: test
test: ## Run fast PHPUnit suite (no Panther)
	$(PHP_EXEC) php -d memory_limit=2G vendor/bin/phpunit --exclude-group=panther

.PHONY: panther
panther: ## Boot Selenium Chromium + run @group panther E2E tests
	$(DOCKER_COMPOSE) --profile e2e up -d chrome
	@echo "▶ waiting for Selenium grid…"
	@for i in 1 2 3 4 5 6 7 8 9 10; do \
		curl -s http://localhost:4444/wd/hub/status | grep -q '"ready":true' && break; \
		sleep 2; \
	done
	$(PHP_EXEC) php -d memory_limit=2G vendor/bin/phpunit --group=panther

.PHONY: phpstan
phpstan: ## Static analysis on showcase code
	$(PHP_EXEC) php vendor/bin/phpstan analyse src tests --level=max

.PHONY: cs-fix
cs-fix: ## Apply PSR-12 + Symfony rules
	$(PHP_EXEC) php vendor/bin/php-cs-fixer fix

.PHONY: screenshots
screenshots: ## Regenerate Panther screenshots into docs/user/screenshots/
	$(DOCKER_COMPOSE) --profile e2e up -d chrome
	@echo "▶ waiting for Selenium grid…"
	@for i in 1 2 3 4 5 6 7 8 9 10; do \
		curl -s http://localhost:4444/wd/hub/status | grep -q '"ready":true' && break; \
		sleep 2; \
	done
	$(PHP_EXEC) php -d memory_limit=2G bin/console app:showcase:screenshots

.PHONY: clean
clean: down ## Stop + remove vendor/, var/, build artefacts
	rm -rf vendor/ var/ public/assets/
