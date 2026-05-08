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
up: install ## Boot the showcase: up the stack, install deps, migrate DB, load fixtures, serve on :8084
	$(DOCKER_COMPOSE) up -d
	@echo ""
	@echo "▶ Waiting for services to become healthy..."
	@$(DOCKER_COMPOSE) ps
	@# DB schema is recreated only when the volume is empty (e.g. after
	@# `make showcase-reset`). Skip silently if the schema is already there.
	@if $(PHP_EXEC) php bin/console doctrine:query:sql "SELECT 1 FROM shopco_user LIMIT 1" --no-interaction >/dev/null 2>&1; then \
		echo "▶ DB already initialized — skipping db + fixtures (use \`make showcase-reset\` to wipe)"; \
	else \
		echo "▶ Initializing database..."; \
		$(MAKE) db; \
		echo "▶ Loading fixtures (1700 rows + 50 failed messages + 30 cache keys + 15 S3 files + 200 Meilisearch docs)..."; \
		$(MAKE) fixtures; \
	fi
	@echo ""
	@echo "▶ Polysource Showcase ready: http://localhost:8084/"
	@echo "  Login: admin@shop.co / shopco  (or ops@shop.co / viewer@shop.co)"
	@echo "  Mailpit:     http://localhost:8025/"
	@echo "  MinIO UI:    http://localhost:9101/  (shopcoadmin / shopcoadmin-secret)"
	@echo "  Meilisearch: http://localhost:7702/  (key: showcase-meili-master-key)"
	@echo "  WireMock:    http://localhost:8089/__admin/"
	@echo ""

.PHONY: down
down: ## Stop the stack (preserves volumes; includes the e2e profile so chrome shuts down too)
	$(DOCKER_COMPOSE) --profile e2e down --remove-orphans

.PHONY: reset
reset: ## Stop the stack and wipe all volumes (data loss!)
	$(DOCKER_COMPOSE) --profile e2e down --volumes --remove-orphans

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
screenshots: ## Regenerate Panther screenshots into docs/user/screenshots/ (re-runs seeds first to avoid empty captures)
	@# Always re-run the non-Doctrine seeds before capturing — Postgres
	@# fixtures persist between runs, but Redis/MinIO/Meilisearch can drift
	@# (volume reset, restarted container, partial seed failure). Capturing
	@# screenshots of an empty showcase is worse than no screenshots — they
	@# end up in the public docs and make the project look broken.
	@echo "▶ Refreshing non-Doctrine seeds (Redis cache + MinIO files + Meilisearch docs)..."
	$(PHP_EXEC) php -d memory_limit=2G bin/console app:seed-stores
	$(PHP_EXEC) php -d memory_limit=2G bin/console app:seed-failed-messages || true
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
