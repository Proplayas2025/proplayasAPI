PHONY := help build up down restart logs logs-app logs-db logs-nginx shell shell-db clean migrate migrate-fresh seed fresh install composer-update config-clear cache-clear optimize test tinker key-generate jwt-secret permissions ps stats storage-link storage-check

help: ## Mostrar ayuda
	@echo "Comandos disponibles:"
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'


# Production build (uses `docker-compose.yml`)
build: ## Construir contenedores para producción (docker-compose.yml)
	docker-compose -f docker-compose.yml build

# Development build (uses `docker-compose.dev.yml`)
dev-build: ## Construir contenedores para desarrollo (docker-compose.dev.yml)
	docker-compose -f docker-compose.dev.yml build


# Production: levantar con docker-compose.yml
prod: ## Levantar stack de producción (docker-compose.yml)
	docker-compose -f docker-compose.yml up -d

# Development: levantar con docker-compose.dev.yml
dev: ## Levantar stack de desarrollo (docker-compose.dev.yml)
	docker-compose -f docker-compose.dev.yml up -d

# Generic up (default to production)
up: ## Iniciar los contenedores (alias a `prod`)
	docker-compose -f docker-compose.yml up -d


down: ## Detener contenedores (producción)
	docker-compose -f docker-compose.yml down

restart: ## Reiniciar los contenedores
	docker-compose restart

stop: ## Detener los contenedores sin eliminarlos
	docker-compose stop

start: ## Iniciar contenedores detenidos
	docker-compose start

logs: ## Ver logs de todos los contenedores
	docker-compose logs -f

logs-app: ## Ver logs del contenedor PHP
	docker-compose logs -f app

logs-db: ## Ver logs de la base de datos
	docker-compose logs -f db

logs-nginx: ## Ver logs de Nginx
	docker-compose logs -f webserver

shell: ## Entrar al contenedor PHP con bash
	docker-compose exec app bash

shell-db: ## Entrar a MySQL
	docker-compose exec db mysql -u proplayas_user -ppassword proplayas


# Clean only this project's stacks (both prod and dev) without touching other projects
clean: ## Bajar y eliminar volúmenes de este proyecto (dev + prod)
	docker-compose -p proplayas -f docker-compose.yml down -v --remove-orphans || true
	docker-compose -p proplayas -f docker-compose.dev.yml down -v --remove-orphans || true

migrate: ## Ejecutar migraciones
	docker-compose exec app php artisan migrate

migrate-fresh: ## Ejecutar migraciones desde cero (elimina todo)
	docker-compose exec app php artisan migrate:fresh

seed: ## Ejecutar seeders
	docker-compose exec app php artisan db:seed

fresh: ## Migraciones frescas + seeders
	docker-compose exec app php artisan migrate:fresh --seed

install: ## Instalar dependencias de Composer
	docker-compose exec app composer install

composer-update: ## Actualizar dependencias de Composer
	docker-compose exec app composer update

config-clear: ## Limpiar caché de configuración
	docker-compose exec app php artisan config:clear

cache-clear: ## Limpiar todas las cachés
	docker-compose exec app php artisan cache:clear
	docker-compose exec app php artisan config:clear
	docker-compose exec app php artisan route:clear
	docker-compose exec app php artisan view:clear

optimize: ## Optimizar aplicación para producción
	docker-compose exec app php artisan config:cache
	docker-compose exec app php artisan route:cache
	docker-compose exec app php artisan view:cache

test: ## Ejecutar tests
	docker-compose exec app php artisan test

tinker: ## Abrir Laravel Tinker
	docker-compose exec app php artisan tinker

key-generate: ## Generar APP_KEY
	docker-compose exec app php artisan key:generate

jwt-secret: ## Generar JWT_SECRET
	docker-compose exec app php artisan jwt:secret

permissions: ## Arreglar permisos de storage y cache
	docker-compose exec app chmod -R 775 storage bootstrap/cache
	docker-compose exec app chown -R www-data:www-data storage bootstrap/cache

ps: ## Ver estado de los contenedores
	docker-compose ps

stats: ## Ver uso de recursos de los contenedores
	docker stats proplayas_php proplayas_db proplayas_nginx proplayas_mailhog

storage-link: ## Crear enlace simbólico public/storage -> storage/app/public
	docker-compose exec app php artisan storage:link

storage-check: ## Listar contenido de public/storage/uploads/profiles
	docker-compose exec app ls -la public/storage/uploads/profiles || true
