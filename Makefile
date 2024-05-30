SHELL = /bin/bash
DC_RUN_ARGS = --rm --user "$(shell id -u):$(shell id -g)"

.PHONY: repl

help: ## Show this help
	@printf "\033[33m%s:\033[0m\n" 'Available commands'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[32m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

build: ## Build containers
	docker compose build

install: ## Install dependencies
	docker compose run $(DC_RUN_ARGS) app composer install -n

shell: ## App container shell
	docker compose run $(DC_RUN_ARGS) app sh

repl: ## Run REPL in app container
	docker compose run $(DC_RUN_ARGS) app ./repl

test: ## Run all tests
	docker compose run $(DC_RUN_ARGS) app composer test

test-current: ## Run tests for current functionality
	docker compose run $(DC_RUN_ARGS) app composer test-current

type-check: ## Run phpstan
	docker compose run $(DC_RUN_ARGS) app composer type-check

style-fix: ## Run php-cs-fixer
	docker compose run $(DC_RUN_ARGS) app composer style-fix
