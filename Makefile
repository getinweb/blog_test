.DEFAULT_GOAL := help

COMPOSE := docker compose -f compose.yaml -f compose.dev.yaml
PHP_RUN := $(COMPOSE) run --rm --no-deps -T php
TEST_RUN := $(COMPOSE) --profile test run --rm -T php-test
SASS_RUN := $(COMPOSE) run --rm --no-deps -T sass
LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)
export LOCAL_UID LOCAL_GID

# To update images, change the tags here, run make pin-images, then copy the
# printed references into the matching Dockerfiles and Compose files.
PINNED_IMAGES ?= \
	php:8.5.11-fpm-bookworm \
	nginx:1.30.5-alpine \
	mysql:8.4.11 \
	composer:2.10.3 \
	alpine:3.24.2

.PHONY: help env config build up down restart logs shell ps pin-images
.PHONY: install lint cs-check cs-fix analyse test test-unit test-integration test-functional check
.PHONY: css-dev css-watch css-build css-check
.PHONY: app-config-check

help:
	@printf '%s\n' \
		'Available commands:' \
		'  make help     Show available commands.' \
		'  make env      Create .env without overwriting an existing file.' \
		'  make config   Validate the dev Compose configuration.' \
		'  make app-config-check  Validate application settings without connecting to MySQL.' \
		'  make build    Build dev images using the pinned base images.' \
		'  make up       Start dev services and the SCSS watcher.' \
		'  make down     Stop dev and test containers; preserve dev database data.' \
		'  make restart  Restart the dev containers.' \
		'  make logs     Follow the last 100 log lines from dev containers.' \
		'  make shell    Open a shell in the PHP container.' \
		'  make ps       Show dev container status.' \
		'  make pin-images  Print image digests for an explicit version update.' \
		'  make install  Install Composer dependencies from the lock file.' \
		'  make lint     Check PHP syntax.' \
		'  make cs-check Check PHP code style without changing files.' \
		'  make cs-fix   Fix PHP code style.' \
		'  make analyse Run PHPStan at level 8.' \
		'  make test     Run all test suites with an isolated MySQL database.' \
		'  make test-unit         Run unit tests without starting MySQL.' \
		'  make test-integration  Run integration tests.' \
		'  make test-functional   Run functional tests.' \
		'  make check    Validate app settings, Composer, PHP syntax/style/types/tests and SCSS.' \
		'  make css-dev   Compile dev CSS with embedded source maps.' \
		'  make css-watch Start the SCSS watcher in the background.' \
		'  make css-build Build compressed CSS in app/var/build/assets/css.' \
		'  make css-check Check SCSS compilation without changing built assets.'

env: .env.example
	@if [ -e .env ] || [ -L .env ]; then \
		printf '%s\n' '.env already exists; left unchanged.'; \
	else \
		(umask 077; set -C; cat .env.example > .env) && \
		printf '%s\n' 'Created .env from .env.example.'; \
	fi

config build up down restart logs shell ps: env
install lint cs-check cs-fix analyse test test-unit test-integration test-functional check: env
css-dev css-watch css-build css-check: env
app-config-check: env

config:
	$(COMPOSE) config --quiet

app-config-check:
	$(PHP_RUN) php bin/check-config.php

build:
	$(COMPOSE) build --pull

up:
	$(COMPOSE) up --detach --wait --wait-timeout 120

down:
	$(COMPOSE) --profile test down

restart:
	$(COMPOSE) restart

logs:
	$(COMPOSE) logs --follow --tail=100

shell:
	$(COMPOSE) exec php sh

ps:
	$(COMPOSE) ps

install:
	$(PHP_RUN) composer install --no-interaction --prefer-dist

lint:
	$(PHP_RUN) composer lint

cs-check:
	$(PHP_RUN) composer cs-check

cs-fix:
	$(PHP_RUN) composer cs-fix

analyse:
	$(PHP_RUN) composer analyse

test:
	$(TEST_RUN) composer test

test-unit:
	$(COMPOSE) --profile test run --rm --no-deps -T php-test composer test:unit

test-integration:
	$(TEST_RUN) composer test:integration

test-functional:
	$(TEST_RUN) composer test:functional

check: app-config-check css-check
	$(TEST_RUN) composer check

css-dev:
	$(SASS_RUN) --style=expanded --embed-source-map --embed-sources --no-error-css assets/scss:public/assets/css

css-watch:
	$(COMPOSE) up --detach --wait sass

css-build:
	$(SASS_RUN) --style=compressed --no-source-map --no-error-css --stop-on-error assets/scss:var/build/assets/css

css-check:
	$(SASS_RUN) --style=compressed --no-source-map --no-error-css --stop-on-error assets/scss:/tmp/css-check

pin-images:
	@set -eu; \
	for image in $(PINNED_IMAGES); do \
		docker pull --quiet "$$image" > /dev/null; \
		reference=$$(docker image inspect --format '{{index .RepoDigests 0}}' "$$image"); \
		printf '%s@%s\n' "$$image" "$${reference##*@}"; \
	done
