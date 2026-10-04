#!/bin/sh
set -e

# Cache config, routes and events using the container's runtime environment,
# so no request reads .env files or rebuilds the route table. Not
# `artisan optimize`: it also runs view:cache, which fails in this API-only
# app because there is no resources/views directory.
php artisan config:cache --no-interaction > /dev/null
php artisan route:cache --no-interaction > /dev/null
php artisan event:cache --no-interaction > /dev/null

exec docker-php-entrypoint "$@"