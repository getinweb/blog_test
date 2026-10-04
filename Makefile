.DEFAULT_GOAL := help

.PHONY: help env

help:
	@printf '%s\n' \
		'Available commands:' \
		'  make help  Show available commands.' \
		'  make env   Create .env from .env.example without overwriting an existing file.'

env: .env.example
	@if [ -e .env ] || [ -L .env ]; then \
		printf '%s\n' '.env already exists; left unchanged.'; \
	else \
		(umask 077; set -C; cat .env.example > .env) && \
		printf '%s\n' 'Created .env from .env.example.'; \
	fi
