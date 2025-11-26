# Variables para archivos de Docker Compose y comando
DOCKER_COMPOSE := docker compose
DEV_FILE := docker-compose.dev.yml
PROD_FILE := docker-compose.yml

PHONY := help build up down restart logs logs-app logs-db logs-nginx shell shell-db clean migrate migrate-fresh seed fresh install composer-update config-clear cache-clear optimize test tinker key-generate jwt-secret permissions ps stats storage-link storage-check

help: ## Mostrar ayuda
	@echo "Comandos disponibles:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'


# Production build (uses `docker-compose.yml`)
build: ## Construir contenedores para producción (docker-compose.yml)
	$(DOCKER_COMPOSE) -f $(PROD_FILE) build

# Development build (uses `docker-compose.dev.yml`)
dev-build: ## Construir contenedores para desarrollo (docker-compose.dev.yml)
	$(DOCKER_COMPOSE) -f $(DEV_FILE) build


# Production: levantar con docker-compose.yml
prod: ## Levantar stack de producción (docker-compose.yml)
	$(DOCKER_COMPOSE) -f $(PROD_FILE) up -d

# Development: levantar con docker-compose.dev.yml
dev: ## Levantar stack de desarrollo (docker-compose.dev.yml)
	$(DOCKER_COMPOSE) -f $(DEV_FILE) up -d

# Generic up (default to production)
up: ## Iniciar los contenedores (alias a `prod`)
	$(DOCKER_COMPOSE) -f $(PROD_FILE) up -d


down: ## Detener contenedores (producción)
	$(DOCKER_COMPOSE) -f $(PROD_FILE) down

restart: ## Reiniciar los contenedores
	$(DOCKER_COMPOSE) restart

stop: ## Detener los contenedores sin eliminarlos
	$(DOCKER_COMPOSE) stop

start: ## Iniciar contenedores detenidos
	$(DOCKER_COMPOSE) start

logs: ## Ver logs de todos los contenedores
	$(DOCKER_COMPOSE) logs -f

logs-app: ## Ver logs del contenedor PHP
	$(DOCKER_COMPOSE) logs -f app

logs-db: ## Ver logs de la base de datos
	$(DOCKER_COMPOSE) logs -f db

logs-nginx: ## Ver logs de Nginx
	$(DOCKER_COMPOSE) logs -f webserver

shell: ## Entrar al contenedor PHP con bash
	$(DOCKER_COMPOSE) exec app bash

shell-db: ## Entrar a MySQL
	$(DOCKER_COMPOSE) exec db mysql -u proplayas_user -ppassword proplayas


# Clean only this project's stacks (both prod and dev) without touching other projects
clean: ## Bajar y eliminar volúmenes de este proyecto (dev + prod)
	$(DOCKER_COMPOSE) -p proplayas -f $(PROD_FILE) down -v --remove-orphans || true
	$(DOCKER_COMPOSE) -p proplayas -f $(DEV_FILE) down -v --remove-orphans || true

migrate: ## Ejecutar migraciones
	$(DOCKER_COMPOSE) exec app php artisan migrate

migrate-fresh: ## Ejecutar migraciones desde cero (elimina todo)
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh

seed: ## Ejecutar seeders
	$(DOCKER_COMPOSE) exec app php artisan db:seed

fresh: ## Migraciones frescas + seeders
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh --seed

install: ## Instalar dependencias de Composer
	$(DOCKER_COMPOSE) exec app composer install

composer-update: ## Actualizar dependencias de Composer
	$(DOCKER_COMPOSE) exec app composer update

config-clear: ## Limpiar caché de configuración
	$(DOCKER_COMPOSE) exec app php artisan config:clear

cache-clear: ## Limpiar todas las cachés
	$(DOCKER_COMPOSE) exec app php artisan cache:clear
	$(DOCKER_COMPOSE) exec app php artisan config:clear
	$(DOCKER_COMPOSE) exec app php artisan route:clear
	$(DOCKER_COMPOSE) exec app php artisan view:clear

optimize: ## Optimizar aplicación para producción
	$(DOCKER_COMPOSE) exec app php artisan config:cache
	$(DOCKER_COMPOSE) exec app php artisan route:cache
	$(DOCKER_COMPOSE) exec app php artisan view:cache

test: ## Ejecutar tests
	$(DOCKER_COMPOSE) exec app php artisan test

tinker: ## Abrir Laravel Tinker
	$(DOCKER_COMPOSE) exec app php artisan tinker

key-generate: ## Generar APP_KEY
	$(DOCKER_COMPOSE) exec app php artisan key:generate

jwt-secret: ## Generar JWT_SECRET
	$(DOCKER_COMPOSE) exec app php artisan jwt:secret

permissions: ## Arreglar permisos de storage y cache
	$(DOCKER_COMPOSE) exec app chmod -R 775 storage bootstrap/cache
	$(DOCKER_COMPOSE) exec app chown -R www-data:www-data storage bootstrap/cache

ps: ## Ver estado de los contenedores
	$(DOCKER_COMPOSE) ps

stats: ## Ver uso de recursos de los contenedores
	docker stats proplayas_php proplayas_db proplayas_nginx proplayas_mailhog

storage-link: ## Crear enlace simbólico public/storage -> storage/app/public
	$(DOCKER_COMPOSE) exec app php artisan storage:link

storage-check: ## Listar contenido de public/storage/uploads/profiles
	$(DOCKER_COMPOSE) exec app ls -la public/storage/uploads/profiles || true
