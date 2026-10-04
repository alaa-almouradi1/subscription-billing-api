# One image, four roles (see docker-compose.yml):
#   api        Apache + mod_php serving public/
#   worker     php artisan queue:work      (renewals and other queued jobs)
#   relay      php artisan outbox:relay    (domain events to Kafka)
#   scheduler  php artisan schedule:work   (queues renewals, pruning, reconciliation)
FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev librdkafka-dev \
    && docker-php-ext-install pdo_mysql pcntl zip opcache \
    && yes '' | pecl install rdkafka redis \
    && docker-php-ext-enable rdkafka redis \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/* /tmp/pear

# Production PHP and Apache settings.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-billing.ini"
COPY docker/apache.conf /etc/apache2/conf-available/zz-billing.conf
RUN a2enconf zz-billing

# Serve Laravel's public/ directory.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies first, so code changes do not invalidate this layer.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/billing-entrypoint
ENTRYPOINT ["billing-entrypoint"]
CMD ["apache2-foreground"]

# Structured JSON logs on stderr, collected by the container runtime.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_STDERR_FORMATTER="Monolog\\Formatter\\JsonFormatter"

EXPOSE 80
