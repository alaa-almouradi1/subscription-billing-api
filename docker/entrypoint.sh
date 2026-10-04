#!/bin/sh
set -e

# Cache config, routes, events and views using the container's runtime
# environment, so no request reads .env files or rebuilds the route table.
php artisan optimize --no-interaction > /dev/null

exec docker-php-entrypoint "$@"
