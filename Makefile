.DEFAULT_GOAL := help

COMPOSE := docker compose -f compose.yaml -f compose.dev.yaml
LOCAL_UID ?= $(shell id -u)
LOCAL_GID ?= $(shell id -g)
export LOCAL_UID LOCAL_GID

# To update images, change the tags here, run make pin-images, then copy the
# printed references into the matching Dockerfiles and compose.yaml.
PINNED_IMAGES ?= \
	php:8.5.11-fpm-bookworm \
	nginx:1.30.5-alpine \
	mysql:8.4.11

.PHONY: help env config build up down restart logs shell ps pin-images

help:
	@printf '%s\n' \
		'Available commands:' \
		'  make help     Show available commands.' \
		'  make env      Create .env without overwriting an existing file.' \
		'  make config   Validate the dev Compose configuration.' \
		'  make build    Build dev images using the pinned base images.' \
		'  make up       Start the dev environment and wait for healthy services.' \
		'  make down     Stop the dev environment without deleting database data.' \
		'  make restart  Restart the dev containers.' \
		'  make logs     Follow the last 100 log lines from dev containers.' \
		'  make shell    Open a shell in the PHP container.' \
		'  make ps       Show dev container status.' \
		'  make pin-images  Print image digests for an explicit version update.'

env: .env.example
	@if [ -e .env ] || [ -L .env ]; then \
		printf '%s\n' '.env already exists; left unchanged.'; \
	else \
		(umask 077; set -C; cat .env.example > .env) && \
		printf '%s\n' 'Created .env from .env.example.'; \
	fi

config build up down restart logs shell ps: env

config:
	$(COMPOSE) config --quiet

build:
	$(COMPOSE) build --pull

up:
	$(COMPOSE) up --detach --wait --wait-timeout 120

down:
	$(COMPOSE) down

restart:
	$(COMPOSE) restart

logs:
	$(COMPOSE) logs --follow --tail=100

shell:
	$(COMPOSE) exec php sh

ps:
	$(COMPOSE) ps

pin-images:
	@set -eu; \
	for image in $(PINNED_IMAGES); do \
		docker pull --quiet "$$image" > /dev/null; \
		reference=$$(docker image inspect --format '{{index .RepoDigests 0}}' "$$image"); \
		printf '%s@%s\n' "$$image" "$${reference##*@}"; \
	done
