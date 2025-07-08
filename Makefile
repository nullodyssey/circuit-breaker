SHELL := /bin/bash # https://askubuntu.com/a/95385
PHPUNIT       = ./vendor/bin/phpunit
PHPSTAN       = ./vendor/bin/phpstan
PHP_CS_FIXER  = ./vendor/bin/php-cs-fixer

# Executables (local)
DOCKER_COMP = docker compose

# Docker containers
PHP_CONT = $(DOCKER_COMP) exec php

# Executables
PHP      = $(PHP_CONT) php -d memory_limit=-1
COMPOSER = $(PHP_CONT) composer

# Misc
.DEFAULT_GOAL = help
.PHONY        : help build up start down logs sh composer vendor cc test quality phpcs phpstan

## —— 🎵 🐳 The Symfony Docker Makefile 🐳 🎵 ——————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_-]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

## —— Docker 🐳 ————————————————————————————————————————————————————————————————
build: ## Builds the Docker images
	@$(DOCKER_COMP) build

up: ## Start the docker hub in detached mode (no logs)
	@$(DOCKER_COMP) up --detach

start: build up ## Build and start the containers

down: ## Stop the docker hub
	@$(DOCKER_COMP) down --remove-orphans

logs: ## Show live logs
	@$(DOCKER_COMP) logs --tail=0 --follow

sh: ## Connect to the FrankenPHP container with sh
	@$(PHP_CONT) sh

composer:
	@$(eval c ?=)
	@$(COMPOSER) $(c)

vendor: ## Install vendors according to the current composer.lock file
vendor: c=install --prefer-dist --no-dev --no-progress --no-scripts --no-interaction
vendor: composer

test:
	@$(DOCKER_COMP) exec -e APP_ENV=test php ./vendor/bin/phpunit -c phpunit.xml -d memory_limit=2048M --stop-on-failure

quality: phpcs phpstan

phpstan:
	@$(DOCKER_COMP) exec php $(PHPSTAN) analyse -c phpstan.neon --memory-limit 1G

phpcs:
	@$(DOCKER_COMP) exec php $(PHP_CS_FIXER) fix --allow-risky=yes --config=.php-cs-fixer.php