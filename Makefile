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

.PHONY: validate
validate: ## Validate root + sub-package composer.json files (strict)
	$(PHP_RUN) composer validate --strict --no-check-publish
	@for pkg in packages/*/composer.json; do \
		if [ -f "$$pkg" ]; then \
			echo "Validating $$pkg..."; \
			$(PHP_RUN) composer validate --strict --no-check-publish --working-dir="$$(dirname $$pkg)" || exit 1; \
		fi \
	done

.PHONY: ci
ci: validate cs-check phpstan test ## Reproduce the 4 GitHub Actions CI jobs locally (run before every push)

##@ Preview

.PHONY: preview
preview: ## Serve the Polysource Twig theme preview at http://localhost:8080
	@echo "→ http://localhost:8080/admin/flags"
	docker run --rm -it -p 8080:8080 -v $$(pwd):/app -w /app polysource-dev:php8.4 \
		php -S 0.0.0.0:8080 -t examples/preview examples/preview/index.php

##@ Demo

.PHONY: demo
demo: ## Start the Messenger failed messages demo (Phase 8)
	@if [ ! -d examples/messenger-demo ]; then \
		echo "Demo not yet built (Phase 8)."; exit 1; \
	fi
	cd examples/messenger-demo && $(DOCKER_COMPOSE) up -d
	@echo "✓ Demo running at http://localhost:8080/admin/failed-messages"
	@echo "  Login: admin / admin"

.PHONY: demo-down
demo-down: ## Stop the demo
	@if [ -d examples/messenger-demo ]; then \
		cd examples/messenger-demo && $(DOCKER_COMPOSE) down; \
	fi

.PHONY: demo-logs
demo-logs: ## Tail demo logs
	cd examples/messenger-demo && $(DOCKER_COMPOSE) logs -f

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

##@ Context (ADR-009)

.PHONY: context-check
context-check: ## Verify the project context file mentions the latest ADR
	@last_adr=$$(ls docs/adr/0*.md 2>/dev/null | sort | tail -1) && \
	if [ -z "$$last_adr" ]; then \
		echo "No ADRs found in docs/adr/"; exit 0; \
	fi && \
	last_id=$$(basename "$$last_adr" .md | sed 's/-.*//') && \
	if ! grep -q "ADR-$$last_id" the project context file; then \
		echo "⚠️ the project context file does not mention $$last_adr"; \
		echo "   Update the project context file to reference the latest ADR."; \
		exit 1; \
	fi && \
	echo "✓ the project context file mentions latest ADR ($$last_adr)"

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
