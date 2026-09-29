#!/bin/sh
# EduVers container start-up: prepare storage, cache config, run migrations, start Apache.
set -e
cd /var/www/html

echo "▶ EduVers: preparing storage…"
# A persistent disk mounted on storage/app starts empty — recreate the folders
mkdir -p storage/app/private storage/app/public \
         storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache
rm -f public/hot   # never point at a Vite dev server in production

if [ -z "$APP_KEY" ]; then
    echo "✖ APP_KEY is not set. Generate one locally with:  php artisan key:generate --show" >&2
    echo "  and add it as the APP_KEY environment variable (Render → Environment)." >&2
    exit 1
fi

echo "▶ EduVers: caching config, routes, views…"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "▶ EduVers: running migrations…"
    attempt=1
    until php artisan migrate --force --no-interaction; do
        if [ "$attempt" -ge 5 ]; then
            echo "✖ Migrations failed after $attempt attempts." >&2
            exit 1
        fi
        echo "  Database not ready yet — retrying in 5s ($attempt/5)…"
        attempt=$((attempt + 1))
        sleep 5
    done
fi

if [ "${SEED_DEMO_DATA:-false}" = "true" ]; then
    echo "▶ EduVers: seeding DEMO data (developer@/teacher@/student@eduvers.test, password: password)…"
    php artisan db:seed --force --no-interaction
fi

# Files created above by root must be writable by Apache (www-data)
chown -R www-data:www-data storage bootstrap/cache

# Background queue worker for queued emails (new lesson / quiz / announcement alerts).
# Runs as www-data next to Apache and restarts itself if it stops. Skipped when
# QUEUE_CONNECTION=sync (jobs then run inside the web request) or RUN_QUEUE_WORKER=false
# (e.g. when you run a separate worker service).
if [ "${RUN_QUEUE_WORKER:-true}" = "true" ] && [ "${QUEUE_CONNECTION:-database}" != "sync" ]; then
    echo "▶ EduVers: starting queue worker (${QUEUE_CONNECTION:-database})…"
    as_www_data="runuser -u www-data --"
    command -v runuser >/dev/null 2>&1 || as_www_data=""
    (
        while true; do
            $as_www_data php artisan queue:work --sleep=3 --tries=3 --max-time=3600 --no-interaction || true
            echo "  queue worker stopped — restarting in 5s…" >&2
            sleep 5
        done
    ) &
fi

# mod_php needs exactly one MPM (prefork). Some hosts/base-image builds leave
# mpm_event enabled too, which makes Apache refuse to start ("More than one MPM loaded").
rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.*
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod mpm_prefork >/dev/null

echo "▶ EduVers: starting web server on port ${PORT}…"
exec "$@"
