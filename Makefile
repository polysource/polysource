# Polysource — Makefile
#
# Conventions:
# - All targets are documented inline (`## description`).
# - `make help` lists them.
# - Docker is the default execution context. To run on host PHP, prefix targets with `local-`.
#
# See ADR-008 for the rationale.

.DEFAULT_GOAL := help

DOCKER_COMPOSE := docker compose
PHP_RUN := $(DOCKER_COMPOSE) run --rm php

UID := $(shell id -u)
GID := $(shell id -g)
export UID GID

##@ Help

.PHONY: help
help: ## Display this help
	@awk 'BEGIN {FS = ":.*##"; printf "\nUsage: make \033[36m<target>\033[0m\n"} /^[a-zA-Z_0-9-]+:.*?##/ { printf "  \033[36m%-20s\033[0m %s\n", $$1, $$2 } /^##@/ { printf "\n\033[1m%s\033[0m\n", substr($$0, 5) }' $(MAKEFILE_LIST)

##@ Setup

.PHONY: install
install: ## Build the Docker image and install Composer dependencies
	$(DOCKER_COMPOSE) build
	$(PHP_RUN) composer install

.PHONY: update
update: ## Update Composer dependencies
	$(PHP_RUN) composer update

##@ Quality

.PHONY: test
test: ## Run PHPUnit tests across all packages
	$(PHP_RUN) vendor/bin/phpunit

.PHONY: test-unit
test-unit: ## Run only unit tests
	$(PHP_RUN) vendor/bin/phpunit --testsuite=unit

.PHONY: test-functional
test-functional: ## Run only functional tests
	$(PHP_RUN) vendor/bin/phpunit --testsuite=functional

.PHONY: phpstan
phpstan: ## Run PHPStan static analysis (level max)
	$(PHP_RUN) vendor/bin/phpstan analyse --memory-limit=2G

.PHONY: cs-check
cs-check: ## Check code style without modifying files (matches CI exactly)
	$(PHP_RUN) vendor/bin/php-cs-fixer fix --dry-run --diff --no-interaction --using-cache=no

.PHONY: cs-fix
cs-fix: ## Apply PSR-12 + Symfony code style fixes
	$(PHP_RUN) vendor/bin/php-cs-fixer fix

.PHONY: coverage
coverage: ## Run PHPUnit with Clover coverage and gate `core` at >= 90%
	$(PHP_RUN) sh -c 'mkdir -p var && vendor/bin/phpunit --testsuite=unit --coverage-clover=var/coverage-core.xml --coverage-filter=packages/core/src'
	$(PHP_RUN) php scripts/coverage-gate.php var/coverage-core.xml 90

.PHONY: validate
validate: ## Validate root + sub-package composer.json files (strict)
	$(PHP_RUN) composer validate --strict --no-check-publish
	@for pkg in packages/*/composer.json; do \
		if [ -f "$$pkg" ]; then \
			echo "Validating $$pkg..."; \
			$(PHP_RUN) composer validate --strict --no-check-publish --working-dir="$$(dirname $$pkg)" || exit 1; \
		fi \
	done

.PHONY: smoke
smoke: ## Smoke test (pre-publish, path repos): install polysource/symfony-bundle on a vanilla Sf 7.4 skeleton — run before every release
	./scripts/smoke-vanilla-symfony.sh

.PHONY: smoke-packagist
smoke-packagist: ## Smoke test (post-publish, real Packagist): install polysource/symfony-bundle from Packagist on a vanilla Sf 7.4 skeleton — run after every release
	./scripts/smoke-packagist.sh

.PHONY: smoke-packagist-bridge
smoke-packagist-bridge: ## Smoke test (post-publish, real Packagist, bridge-alone): install polysource/easyadmin-filter-bridge from Packagist — catches B2-style Twig parse errors on bridge-alone installs
	./scripts/smoke-packagist-bridge.sh

.PHONY: ci
ci: validate cs-check phpstan test coverage ## Reproduce the 5 GitHub Actions CI jobs locally (run before every push)

##@ Preview

.PHONY: preview
preview: ## Serve the Polysource Twig theme preview at http://localhost:8080
	@echo "→ http://localhost:8080/admin/flags"
	docker run --rm -it -p 8080:8080 -v $$(pwd):/app -w /app polysource-dev:php8.4 \
		php -S 0.0.0.0:8080 -t examples/preview examples/preview/index.php

##@ Demo

.PHONY: demo
demo: ## Start the Messenger failed-messages demo on http://localhost:8080
	@$(MAKE) -C examples/messenger-demo up

.PHONY: demo-down
demo-down: ## Stop the demo container
	@$(MAKE) -C examples/messenger-demo down

.PHONY: demo-logs
demo-logs: ## Tail demo container logs
	@$(MAKE) -C examples/messenger-demo logs

.PHONY: demo-bridge
demo-bridge: ## Start the EasyAdmin v5 filter bridge demo on http://localhost:8081 (PHP 8.4 + Sf 7.4 + EA 5)
	@$(MAKE) -C examples/easyadmin-bridge-demo up

.PHONY: demo-bridge-down
demo-bridge-down: ## Stop the bridge demo container
	@$(MAKE) -C examples/easyadmin-bridge-demo down

.PHONY: demo-bridge-clean
demo-bridge-clean: ## Wipe the bridge demo vendor + database
	@$(MAKE) -C examples/easyadmin-bridge-demo clean

.PHONY: demo-bridge-v4
demo-bridge-v4: ## Start the EasyAdmin v4 filter bridge demo on http://localhost:8083 (PHP 8.1 + Sf 6.4 + EA 4.29 — proves the floor)
	@$(MAKE) -C examples/easyadmin-bridge-demo-v4 up

.PHONY: demo-bridge-v4-down
demo-bridge-v4-down: ## Stop the v4 bridge demo container
	@$(MAKE) -C examples/easyadmin-bridge-demo-v4 down

.PHONY: demo-bridge-v4-clean
demo-bridge-v4-clean: ## Wipe the v4 bridge demo vendor + database
	@$(MAKE) -C examples/easyadmin-bridge-demo-v4 clean

.PHONY: demo-filter
demo-filter: ## Start the standalone polysource/filter demo on http://localhost:8082 (vanilla Symfony, no EasyAdmin)
	@$(MAKE) -C examples/filter-standalone-demo install
	@$(MAKE) -C examples/filter-standalone-demo serve

.PHONY: demo-filter-down
demo-filter-down: ## Stop the standalone filter demo container
	@$(MAKE) -C examples/filter-standalone-demo clean

.PHONY: showcase
showcase: ## [HERO v0.1.0] Boot the ShopCo SaaS showcase on http://localhost:8084 (16 packages, 8 services — cf. ADR-025)
	@$(MAKE) -C examples/showcase-demo up

.PHONY: showcase-down
showcase-down: ## Stop the showcase stack (preserves volumes)
	@$(MAKE) -C examples/showcase-demo down

.PHONY: showcase-reset
showcase-reset: ## Stop the showcase stack and wipe ALL volumes (data loss)
	@$(MAKE) -C examples/showcase-demo reset

.PHONY: showcase-logs
showcase-logs: ## Tail logs from all showcase services
	@$(MAKE) -C examples/showcase-demo logs

.PHONY: showcase-screenshots
showcase-screenshots: ## Regenerate Panther screenshots into docs/user/screenshots/ (Phase I+)
	@$(MAKE) -C examples/showcase-demo screenshots

.PHONY: demo-clean
demo-clean: ## Wipe demo vendor + database (rebuild on next `make demo`)
	@$(MAKE) -C examples/messenger-demo clean

##@ Maintenance

.PHONY: shell
shell: ## Open a shell inside the PHP container
	$(PHP_RUN) sh

.PHONY: clean
clean: ## Remove vendor/, var/, caches
	rm -rf vendor/ packages/*/vendor/ var/ \
		.phpunit.result.cache .phpunit.cache/ \
		.php-cs-fixer.cache \
		coverage/

.PHONY: clean-all
clean-all: clean demo-down ## clean + stop containers + remove docker images
	$(DOCKER_COMPOSE) down -v --rmi local

##@ Local (without Docker)

.PHONY: local-test
local-test: ## Run tests on host PHP (requires PHP 8.4+ + composer)
	vendor/bin/phpunit

.PHONY: local-phpstan
local-phpstan: ## Run PHPStan on host PHP
	vendor/bin/phpstan analyse --memory-limit=2G

.PHONY: local-cs-fix
local-cs-fix: ## Apply code style on host PHP
	vendor/bin/php-cs-fixer fix
