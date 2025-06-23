FROM 588699980324.dkr.ecr.us-west-2.amazonaws.com/php:8.3-apache AS builder

# Copy everything from common for building
COPY ./.build-config/common/ /common/

# Use local source instead of composer create-project
COPY . /opt/mautic
WORKDIR /opt/mautic
RUN COMPOSER_ALLOW_SUPERUSER=1 COMPOSER_PROCESS_TIMEOUT=10000 composer install --no-interaction --prefer-dist --optimize-autoloader

FROM 588699980324.dkr.ecr.us-west-2.amazonaws.com/php:8.3-apache

# Setting PHP properties
ENV PHP_INI_VALUE_DATE_TIMEZONE='UTC' \
    PHP_INI_VALUE_MEMORY_LIMIT=512M \
    PHP_INI_VALUE_UPLOAD_MAX_FILESIZE=512M \
    PHP_INI_VALUE_POST_MAX_FILESIZE=512M \
    PHP_INI_VALUE_MAX_EXECUTION_TIME=300

# Setting worker env vars
ENV DOCKER_MAUTIC_WORKERS_CONSUME_EMAIL=2 \
    DOCKER_MAUTIC_WORKERS_CONSUME_HIT=2 \
    DOCKER_MAUTIC_WORKERS_CONSUME_FAILED=2

ENV DOCKER_MAUTIC_ROLE=mautic_web \
    DOCKER_MAUTIC_RUN_MIGRATIONS=false \
    DOCKER_MAUTIC_LOAD_TEST_DATA=false

ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Copy php settings and extensions from builder
COPY --from=builder /usr/local/lib/php/extensions /usr/local/lib/php/extensions
COPY --from=builder /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/

# Copy php.ini from templates
COPY --from=builder /common/templates/php.ini /usr/local/etc/php/php.ini

COPY --from=builder --chown=www-data:www-data /opt/mautic /var/www/html

# Copy all files needed for startup
COPY --from=builder --chmod=755 /common/startup/ /startup/
COPY --from=builder --chmod=755 /common/templates/ /templates/
COPY --from=builder --chmod=755 /common/docker-entrypoint.sh /entrypoint.sh
COPY --from=builder --chmod=755 /common/entrypoint_mautic_web.sh /entrypoint_mautic_web.sh
COPY --from=builder --chmod=755 /common/entrypoint_mautic_cron.sh /entrypoint_mautic_cron.sh
COPY --from=builder --chmod=755 /common/entrypoint_mautic_worker.sh /entrypoint_mautic_worker.sh

# Copy supervisord configuration for workers
COPY --from=builder /common/templates/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Install composer
COPY --from=builder /usr/bin/composer /usr/bin/composer


# Install PHP extensions requirements and other dependencie

# Rebuild web assets
RUN cd /var/www/html && \
    npm install && \
    php bin/console mautic:assets:generate && \
    php bin/console cache:clear

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Enable Apache Rewrite Module
RUN a2enmod rewrite

# Set correct ownership for Mautic var folder
RUN chown -R www-data:www-data /var/www/html/var/

# Define Mautic volumes to persist data
VOLUME /var/www/html/config
VOLUME /var/www/html/var/logs
VOLUME /var/www/html/docroot/media

WORKDIR /var/www/html

LABEL vendor="Mautic"
LABEL maintainer="Mautic core team <>"

ENTRYPOINT ["/entrypoint.sh"]

CMD ["apache2-foreground"]
