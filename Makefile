SHELL = /bin/bash
IMAGE = php:8.5-alpine
# :Z relabels the mount for SELinux. Without it the container cannot read the project.
RUN_ARGS = --rm -e HOME=/tmp -v "$(CURDIR)":/app:Z -w /app

.PHONY: help install shell repl test analyze lint fmt

help: ## Show this help
	@printf "\033[33m%s:\033[0m\n" 'Available commands'
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z0-9_-]+:.*?## / {printf "  \033[32m%-18s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

install: ## Install dependencies in a one-off PHP 8.5 container
	docker run $(RUN_ARGS) $(IMAGE) sh -c 'php -r "copy(\"https://getcomposer.org/installer\", \"/tmp/composer-setup.php\");" && php /tmp/composer-setup.php --install-dir=/tmp --filename=composer && php /tmp/composer update -n --with-all-dependencies'

shell: ## Shell in a one-off PHP 8.5 container
	docker run -it $(RUN_ARGS) $(IMAGE) sh

repl: ## Run the REPL in a one-off PHP 8.5 container
	docker run -it $(RUN_ARGS) $(IMAGE) ./repl

test: ## Run all tests
	docker run $(RUN_ARGS) $(IMAGE) php vendor/bin/phpunit -c phpunit.xml --display-errors --display-notices --display-warnings tests

analyze: ## Run Mago static analysis
	docker run $(RUN_ARGS) $(IMAGE) php vendor/bin/mago analyze

lint: ## Run Mago linter
	docker run $(RUN_ARGS) $(IMAGE) php vendor/bin/mago lint

fmt: ## Format PHP with Mago
	docker run $(RUN_ARGS) $(IMAGE) php vendor/bin/mago fmt
