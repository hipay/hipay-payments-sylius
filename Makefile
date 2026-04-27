COMPOSER=symfony composer
CONSOLE=symfony console
PHP=symfony php

ACT ?= act
ACT_IMAGE ?= act-sylius-runner:latest
ACT_DOCKERFILE ?= Dockerfile.act
ACT_PLATFORM ?= linux/amd64
WORKFLOW ?= .github/workflows/build.yaml
JOB ?= tests
ENV_FILE ?= .env.act
# Sylius Test Application — Symfony CLI workflow
# Docker is started by the Symfony server via .symfony.local.yaml (workers.docker_compose).
# Requires: Symfony CLI (symfony), PHP 8.2+, Node/Yarn
# See: https://symfony.com/doc/current/setup/symfony_cli.html

# Expected versions (read from dotfiles)
EXPECTED_PHP_MAJOR_MINOR=$(shell cat .php-version 2>/dev/null || echo "8.2")
EXPECTED_NODE_MAJOR=$(shell cat .nvmrc 2>/dev/null || echo "20")

# Test commands: APP_ENV=test forces the test environment, which activates
# when@test Doctrine config using DATABASE_TEST_URL (not DATABASE_URL).
# This avoids Symfony CLI Docker injection overriding the DB connection.
TEST_CONSOLE=APP_ENV=test $(CONSOLE) --env=test
TEST_PHP=APP_ENV=test $(PHP)

.PHONY: help check up stop scheduler.consume install clean phpstan ecs phpunit behat migration \
	test.all test.composer test.phpstan test.phpmd test.phpunit test.phpunit.unit test.phpunit.integration test.phpunit.functional \
	test.phpspec test.ecs test.ecs.fix test.container test.yaml test.schema test.twig \
	test.db.create test.db.migrate test.db.fixtures test.db.init test.db.drop test.db.reset \
	test.behat ci.install ci.test ci.test.lint ci.test.phpunit ci.test.behat act \
	act-image act-clean act

help:
	@echo "Sylius Test Application (Symfony CLI)"
	@echo ""
	@echo "  make check            Verify all prerequisites (symfony, php, node, yarn, composer)"
	@echo "  make up               Start Symfony server (Docker via .symfony.local.yaml)"
	@echo "  make stop             Stop Symfony server"
	@echo "  make install          Check prerequisites, composer install, up, database-init, frontend-build"
	@echo "  make clean            Database clean, stop server, remove vendor/ node_modules/"
	@echo ""
	@echo "  make phpstan          Run PHPStan"
	@echo "  make test.ecs         Run ECS (coding standard check)"
	@echo "  make test.ecs.fix     Run ECS and fix issues"
	@echo "  make phpunit          Run PHPUnit"
	@echo "  make behat            Run Behat"
	@echo "  make migration        Generate a Doctrine migration diff for the plugin"
	@echo ""
	@echo "  composer run database-init   Create DB, migrate, load fixtures"
	@echo "  composer run frontend-clear  Yarn in test-application + assets:install"

## Prerequisites check — verifies all required tools and versions
check: php.ini
	@echo "==> Checking prerequisites..."
	@echo ""
	@# --- Symfony CLI ---
	@printf "  Symfony CLI ... "
	@if command -v symfony >/dev/null 2>&1; then \
		echo "OK ($$(symfony version --no-ansi 2>&1 | head -1))"; \
	else \
		echo "MISSING"; \
		echo "    Install: https://symfony.com/download"; \
		exit 1; \
	fi
	@# --- PHP version via Symfony CLI ---
	@printf "  PHP (via symfony) ... "
	@if command -v symfony >/dev/null 2>&1; then \
		PHP_VERSION=$$(symfony php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;" 2>/dev/null); \
		if [ "$$PHP_VERSION" = "$(EXPECTED_PHP_MAJOR_MINOR)" ]; then \
			echo "OK ($$PHP_VERSION)"; \
		else \
			FULL_VERSION=$$(symfony php -r "echo PHP_VERSION;" 2>/dev/null); \
			echo "WARNING — found $$FULL_VERSION, expected $(EXPECTED_PHP_MAJOR_MINOR).x (see .php-version)"; \
		fi; \
	else \
		echo "SKIPPED (symfony CLI missing)"; \
	fi
	@# --- Composer ---
	@printf "  Composer ... "
	@if command -v composer >/dev/null 2>&1; then \
		echo "OK ($$(composer --version --no-ansi 2>/dev/null | head -1))"; \
	else \
		echo "MISSING"; \
		echo "    Install: https://getcomposer.org/download/"; \
		exit 1; \
	fi
	@# --- Node.js ---
	@printf "  Node.js ... "
	@if command -v node >/dev/null 2>&1; then \
		NODE_MAJOR=$$(node -e "process.stdout.write(String(process.versions.node.split('.')[0]))"); \
		if [ "$$NODE_MAJOR" = "$(EXPECTED_NODE_MAJOR)" ]; then \
			echo "OK ($$(node --version 2>/dev/null))"; \
		else \
			echo "WARNING — found $$(node --version), expected v$(EXPECTED_NODE_MAJOR).x (see .nvmrc)"; \
			echo "    Hint: nvm use / fnm use"; \
		fi; \
	else \
		echo "MISSING"; \
		echo "    Install: https://nodejs.org/ or use nvm/fnm"; \
		exit 1; \
	fi
	@# --- Yarn ---
	@printf "  Yarn ... "
	@if command -v yarn >/dev/null 2>&1; then \
		echo "OK ($$(yarn --version 2>/dev/null))"; \
	else \
		echo "MISSING"; \
		echo "    Install: corepack enable && corepack prepare yarn@stable --activate"; \
		exit 1; \
	fi
	@# --- Docker (optional, used by Symfony server for services) ---
	@printf "  Docker ... "
	@if command -v docker >/dev/null 2>&1; then \
		if docker info >/dev/null 2>&1; then \
			echo "OK (running)"; \
		else \
			echo "WARNING — installed but daemon not running"; \
			echo "    Start Docker Desktop or the Docker daemon"; \
		fi; \
	else \
		echo "NOT FOUND (optional — needed for database via .symfony.local.yaml)"; \
	fi
	@echo ""
	@echo "==> All required prerequisites OK."
	@echo ""

up:
	@symfony proxy:status 2>&1 | grep -qi "not running" && symfony proxy:start || echo "Proxy already running."
	@symfony server:status 2>&1 | grep -qi "not running" && symfony server:start || echo "Server already running."

stop:
	@symfony proxy:status 2>&1 | grep -qi "not running" && echo "Proxy not running." || symfony proxy:stop
	@symfony server:status 2>&1 | grep -qi "not running" && echo "Server not running." || symfony server:stop

# APP_DEBUG=0 is required: Symfony 7.4.x TraceableEventDispatcher crashes in
# debug mode when ResetServicesListener fires during WorkerRunningEvent dispatch.
# The symfony server:start worker (.symfony.local.yaml) already sets APP_DEBUG=0.
scheduler.consume:
	APP_DEBUG=0 $(CONSOLE) messenger:consume scheduler_hipay_notifications --time-limit=3600 --memory-limit=128M -vv

proxy.start:
	@symfony proxy:start

proxy.stop:
	@symfony proxy:stop

install: check
	$(COMPOSER) install
	$(MAKE) up
	@sleep 5 # Wait for Docker to start
	$(MAKE) generate-key
	$(COMPOSER) run database-init
	cd vendor/sylius/test-application && yarn install
	cd vendor/sylius/test-application && yarn build
	$(COMPOSER) run asset-install
	symfony server:status

refill:
	$(COMPOSER) run database-init

php.ini: php.ini.dist
	cp php.ini.dist php.ini

clean:
	$(COMPOSER) run database-clean
	$(MAKE) test.db.drop
	$(MAKE) stop
	-rm -rf vendor/ node_modules/ var/

cc:
	$(COMPOSER) run cache-clear

generate-key:
	$(COMPOSER) run generate-key

fixtures:
	$(COMPOSER) run database-init

front.build:
	cd vendor/sylius/test-application && yarn build

front.watch:
	cd vendor/sylius/test-application && yarn watch

# --- Test database (isolated from dev database via APP_ENV=test) ---
# The test environment uses DATABASE_TEST_URL (not DATABASE_URL) via when@test
# Doctrine config. APP_ENV=test ensures Symfony CLI doesn't inject dev env vars.

test.db.create: ## Create the test database (separate from dev)
	$(TEST_CONSOLE) doctrine:database:create --if-not-exists

test.db.migrate: ## Run migrations on the test database
	$(TEST_CONSOLE) doctrine:migrations:migrate --no-interaction

test.db.fixtures: ## Load fixtures into the test database
	$(TEST_CONSOLE) sylius:fixtures:load --no-interaction

test.db.init: test.db.drop test.db.create test.db.migrate ## Full test DB setup (create + migrate, no fixtures needed for PHPUnit)

test.db.drop: ## Drop the test database
	$(TEST_CONSOLE) doctrine:database:drop --force --if-exists

test.db.reset: test.db.drop test.db.init ## Reset test DB from scratch

# --- Quality & tests ---
test.all: test.composer test.ecs test.phpstan test.phpmd test.phpunit test.behat test.yaml test.schema test.twig test.container ## Run all tests in once

test.composer: ## Validate composer.json
	$(COMPOSER) validate --strict

test.phpstan: ## Run PHPStan
	$(COMPOSER) phpstan

test.phpmd: ## Run PHPMD
	$(COMPOSER) phpmd

test.phpunit: test.phpunit.unit test.phpunit.integration test.phpunit.functional ## Run all PHPUnit tests

test.phpunit.unit: ## Run PHPUnit unit tests only (no DB, no Kernel)
	$(TEST_PHP) vendor/bin/phpunit --testsuite=unit

test.phpunit.integration: test.db.init ## Run PHPUnit integration tests (requires test DB)
	$(TEST_PHP) vendor/bin/phpunit --testsuite=integration

test.phpunit.functional: test.db.init ## Run PHPUnit functional tests (requires test DB)
	$(TEST_PHP) vendor/bin/phpunit --testsuite=functional

test.phpspec: ## Run PHPSpec
	$(COMPOSER) phpspec

test.ecs: ## Run ECS (coding standard check only)
	$(COMPOSER) ecs

test.ecs.fix: ## Run ECS and fix issues if possible
	$(COMPOSER) ecs:fix

test.container: ## Lint the symfony container
	$(TEST_CONSOLE) lint:container

test.yaml: ## Lint the symfony Yaml files
	$(TEST_CONSOLE) lint:yaml config

test.schema: ## Validate MySQL Schema (requires DB in sync; skipped if DB not available)
	$(TEST_CONSOLE) doctrine:schema:validate || true

test.behat: test.db.init ## Run Behat tests (requires test DB + running server)
	$(TEST_PHP) vendor/bin/behat --colors --strict --no-interaction -vvv

migration: ## Generate a Doctrine migration diff for the plugin
	$(CONSOLE) doctrine:migrations:diff --namespace="HiPay\\SyliusHiPayPlugin\\Migrations" -n

test.twig: ## Validate Twig templates
	$(TEST_CONSOLE) lint:twig --no-debug templates/
