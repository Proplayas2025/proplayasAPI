#!/bin/bash
set -e

# Verificar si el directorio vendor existe, si no, ejecutar composer install
if [ ! -d "vendor" ]; then
    echo "Installing composer dependencies..."
    composer install --no-dev --optimize-autoloader
fi

# Verificar si el archivo .env existe
if [ ! -f .env ]; then
    echo "Creating .env file from .env.example"
    cp .env.example .env
fi

# Generar clave de aplicación si no existe
if ! grep -q "APP_KEY=base64:" .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Esperar un poco a que la base de datos esté lista
echo "Waiting for database to be ready..."
sleep 10

# Generar secreto JWT si no existe
if ! grep -q "JWT_SECRET=" .env; then
    echo "Generating JWT secret..."
    php artisan jwt:secret --force
fi

# Crear enlace simbólico de storage si no existe
if [ ! -L public/storage ]; then
    echo "Creating storage link..."
    php artisan storage:link
fi

# Ejecutar migraciones
echo "Running migrations..."
php artisan migrate --force

# Crear permisos y roles básicos si es necesario
if php artisan tinker --execute="echo App\\Models\\User::count();" | grep -q "0"; then
    echo "Running seeders..."
    php artisan db:seed --force
fi

echo "Laravel application initialized successfully!"

# Iniciar el worker de colas en segundo plano
php artisan queue:work --daemon --tries=3 --timeout=30 &

# Iniciar el scheduler en segundo plano
while true; do
    php artisan schedule:run >> /dev/null 2>&1
    sleep 60
done &

# Iniciar PHP-FPM
exec "$@"
