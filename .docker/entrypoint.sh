#!/usr/bin/env sh
set -eu

cd /var/www

# Use docker-specific env if no .env present
if [ ! -f ".env" ] && [ -f ".env.docker" ]; then
  cp .env.docker .env
fi

# Ensure sqlite file exists
if [ ! -f "database/database.sqlite" ]; then
  mkdir -p database
  touch database/database.sqlite
fi

# Ensure dependency directories exist
mkdir -p vendor node_modules

# Shared lock files across services (on the bind mount) to avoid concurrent installs
COMPOSER_LOCK_FILE=/var/www/.composer-install.lock
NPM_LOCK_FILE=/var/www/.npm-install.lock

# Install PHP dependencies
flock "${COMPOSER_LOCK_FILE}" -c "composer install --no-interaction --prefer-dist --no-progress"

# Install JS dependencies
flock "${NPM_LOCK_FILE}" -c "npm install --no-progress"

# Clear cached config in case a host cache was mounted
php artisan config:clear

# Generate key if missing
if ! grep -q "^APP_KEY=" .env || [ -z "$(grep '^APP_KEY=' .env | cut -d= -f2-)" ]; then
  php artisan key:generate --force --no-interaction
fi

# Run migrations
php artisan migrate --force --no-interaction

exec "$@"
