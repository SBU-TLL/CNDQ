# syntax=docker/dockerfile:1
###############################################################################
# Production image — CNDQ (chemistry negotiation/marketplace game)
#
# Tech stack : PHP 8.x app (index.php + api/ + lib/) with a Slim-less custom
#              router; SQLite storage (lib/Database.php -> data/cndq.db,
#              event-sourced); Composer deps (google/apiclient, codeguy/upload);
#              optional Google Sheets integration (spreadsheet.php).
# Web server : Apache (php:8.3-apache) so the app .htaccess is honored.
#
# Build is multi-stage: a composer stage builds vendor/ (not committed), then
# the runtime stage copies it into the Apache image.
#
# Authentication: Shibboleth SSO enforced at the ingress / reverse proxy
#   (Ansible-managed); the app also has an in-repo auth layer (userData.php) that
#   is FAIL-CLOSED in production (APP_ENV=production disables the dev.php mock).
#   No secrets are baked into the image:
#     * .env / dev-auth mock  -> excluded (.dockerignore); prod uses Shibboleth
#     * credentials.json (Google service account) -> NOT in the image; mount it
#       at runtime (see .env.production.example). Path override via
#       GOOGLE_APPLICATION_CREDENTIALS is honored by the app's config.
#
# SQLite data (data/cndq.db) is runtime state -> mount a volume at
# /var/www/html/data for persistence (the baked dir is empty + writable).
#
# Runs non-root (www-data) on unprivileged port 8080.
###############################################################################

# --- Stage 1: build Composer dependencies (vendor/) --------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist \
        --optimize-autoloader --no-interaction --no-progress

# --- Stage 2: runtime image --------------------------------------------------
FROM php:8.3-apache

# PHP extensions: pdo_sqlite/sqlite3 are enabled by default in the official
# image; add curl for the Google API client's HTTP transport.
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libcurl4-openssl-dev; \
    docker-php-ext-install curl; \
    rm -rf /var/lib/apt/lists/*

# --- Apache modules the app's .htaccess needs ---
RUN set -eux; \
    a2enmod rewrite headers

# --- Run as a non-root user on an unprivileged port (8080) ---
RUN set -eux; \
    sed -ri 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf; \
    sed -ri 's/:80>/:8080>/' /etc/apache2/sites-available/000-default.conf

# --- Security hardening (suppress server tokens/signature, TRACE, ETag) ---
RUN set -eux; \
    { \
      echo 'ServerTokens Prod'; \
      echo 'ServerSignature Off'; \
      echo 'TraceEnable Off'; \
      echo 'FileETag None'; \
    } > /etc/apache2/conf-available/zzz-hardening.conf; \
    a2enconf zzz-hardening

# --- Docroot policy: parse .htaccess (AllowOverride All), no dir listing,
#     log to stdout/stderr for container log capture ---
RUN set -eux; \
    { \
      echo '<Directory /var/www/html>'; \
      echo '    Options -Indexes +FollowSymLinks'; \
      echo '    AllowOverride All'; \
      echo '    Require all granted'; \
      echo '</Directory>'; \
      echo 'ErrorLog /dev/stderr'; \
      echo 'CustomLog /dev/stdout combined'; \
    } > /etc/apache2/conf-available/zzz-docroot.conf; \
    a2enconf zzz-docroot

# --- Application code. .dockerignore excludes .ddev/, .git/, .env*,
#     credentials.json, /data, vendor/, and the dev/test/debug scripts,
#     screenshots, playwright logs and docs. ---
COPY --chown=www-data:www-data . /var/www/html/

# --- Composer vendor/ from the build stage ---
COPY --from=vendor --chown=www-data:www-data /app/vendor /var/www/html/vendor

# --- Permissions: read-only app tree owned by www-data; a writable data/ dir
#     for the SQLite DB (mount a volume here in production for persistence) ---
RUN set -eux; \
    find /var/www/html -type d -exec chmod 0755 {} +; \
    find /var/www/html -type f -exec chmod 0644 {} +; \
    mkdir -p /var/www/html/data; \
    chown -R www-data:www-data /var/www/html/data; \
    chmod 0775 /var/www/html/data; \
    chown -R www-data:www-data /var/run/apache2 /var/log/apache2 /var/lock; \
    chmod -R g=u /var/run/apache2 /var/log/apache2 /var/lock

USER www-data
EXPOSE 8080
VOLUME ["/var/www/html/data"]

# php:apache base CMD = apache2-foreground
